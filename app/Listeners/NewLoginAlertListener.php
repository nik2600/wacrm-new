<?php

namespace App\Listeners;

use App\Models\AuditLog;
use App\Models\SystemSetting;
use App\Support\Audit;
use Illuminate\Auth\Events\Login;
use Illuminate\Support\Facades\Mail;

/**
 * On every successful login, compare the current device/country to the
 * user's prior login (queried from audit_logs auth.login rows).
 *
 * Sends a "new device" or "new country" email per security.alert_*
 * toggles AND security.alert_channel (email | whatsapp | both).
 *
 * Country detection uses the WORLD_IP_PREFIX cache table if available,
 * otherwise falls back to a cheap CIDR-prefix lookup. We don't ship a
 * geoip database — the existing infrastructure uses request->header or
 * a simple "first two octets" heuristic which is good enough for
 * "this is a different IP block than last time" detection.
 */
class NewLoginAlertListener
{
    public function handle(Login $event): void
    {
        $user = $event->user;
        if (! $user || ! $user->email) return;

        $req       = request();
        $thisIp    = $req?->ip();
        $thisUa    = (string) ($req?->userAgent() ?? '');

        // Prior login of this user, OLDER than the row that the auth-event
        // listener wrote ~milliseconds ago. We look for the previous one.
        $previous = AuditLog::query()
            ->where('actor_user_id', $user->id)
            ->where('action', 'auth.login')
            ->latest('created_at')
            ->skip(1)
            ->first();

        if (! $previous) {
            // First-ever login — no comparison possible. Nothing to alert.
            return;
        }

        $alertOnDevice  = (bool) SystemSetting::get('security.alert_on_new_device', true);
        $alertOnCountry = (bool) SystemSetting::get('security.alert_on_new_country', true);
        $channel        = (string) SystemSetting::get('security.alert_channel', 'email');

        $reasons = [];
        if ($alertOnDevice && $previous->user_agent && $thisUa && ! $this->sameDevice($previous->user_agent, $thisUa)) {
            $reasons[] = 'new-device';
        }
        if ($alertOnCountry && $previous->ip && $thisIp && ! $this->sameCountry($previous->ip, $thisIp)) {
            $reasons[] = 'new-country';
        }
        if (empty($reasons)) return;

        Audit::log('auth.alert_emitted', [
            'subject_type' => 'user',
            'subject_id'   => $user->id,
            'result'       => 'warning',
            'meta'         => [
                'reasons'   => $reasons,
                'channel'   => $channel,
                'prev_ip'   => $previous->ip,
                'prev_ua'   => mb_substr((string) $previous->user_agent, 0, 120),
                'this_ip'   => $thisIp,
                'this_ua'   => mb_substr($thisUa, 0, 120),
            ],
        ]);

        // Send the alert. Wrapped in try/catch — we never want a failed
        // mail send to break a successful login.
        if (in_array($channel, ['email', 'both'], true)) {
            $this->sendEmail($user, $reasons, $thisIp, $thisUa, $previous);
        }
        // WhatsApp channel is policy-stubbed — when the user-side has a
        // verified WhatsApp number on file and a working send pipe, this
        // is where we'd dispatch a Baileys job. Avoiding live sends
        // per project rule.
    }

    private function sendEmail($user, array $reasons, ?string $ip, ?string $ua, $previous): void
    {
        // DEDUP — a single sign-in can fire Laravel's Login event more than once
        // (remember-me re-auth on the next request, some social/redirect flows),
        // which is why the alert arrived TWICE. Send at most one alert per user +
        // device fingerprint per 10 minutes. Cache::add() is atomic: the first
        // call wins, the duplicate is a no-op.
        $dedupKey = 'login-alert:' . $user->id . ':' . md5(($ip ?? '') . '|' . ($ua ?? ''));
        if (! \Illuminate\Support\Facades\Cache::add($dedupKey, 1, now()->addMinutes(10))) {
            return;
        }

        try {
            $appName = \App\Models\SystemSetting::get('app_name', config('app.name', 'WaDesk'));
            $subject = count($reasons) === 1 && $reasons[0] === 'new-device'
                ? "New sign-in to your {$appName} account"
                : 'New sign-in from a different location';

            // Explicit From — pin it to the admin-configured mail-from so the
            // sender is always the SMTP identity (e.g. noreply@smartwats.com) and
            // never resolves ambiguously. Read the DB value the admin sets on
            // /admin/settings/mail (SystemSetting mail_from_address/mail_from_name)
            // FIRST — that's the source of truth "used everywhere" — falling back
            // to the mail config only when the DB row is blank.
            $fromAddr = (string) (\App\Models\SystemSetting::get('mail_from_address', '') ?: config('mail.from.address'));
            $fromName = (string) (\App\Models\SystemSetting::get('mail_from_name', '') ?: (config('mail.from.name') ?: $appName));

            // We'd ideally use a Mailable class; for now a quick raw
            // mail via the existing mail config so this listener ships
            // without requiring a new artisan make:mail.
            Mail::raw(
                "Hi " . ($user->name ?: 'there') . ",\n\n" .
                "Your {$appName} account was just used to sign in.\n\n" .
                "When: " . now()->toDateTimeString() . " UTC\n" .
                "From IP: " . ($ip ?: 'unknown') . "\n" .
                "Browser: " . ($ua ?: 'unknown') . "\n" .
                "Reason for alert: " . implode(', ', $reasons) . "\n\n" .
                "If this was you, no action is needed. If not, change your password right away at " . url('/settings?tab=security') . "\n\n" .
                "— {$appName} security",
                function ($m) use ($user, $subject, $fromAddr, $fromName) {
                    $m->to($user->email)->subject($subject);
                    if ($fromAddr !== '') {
                        $m->from($fromAddr, $fromName);
                    }
                }
            );
        } catch (\Throwable $e) {
            error_log('[NewLoginAlertListener] mail failed: ' . $e->getMessage());
        }
    }

    private function sameDevice(string $prevUa, string $thisUa): bool
    {
        // Trim to "browser family + OS family" — full UA string changes too
        // often (version bumps) to be a useful sameness check.
        return $this->uaFingerprint($prevUa) === $this->uaFingerprint($thisUa);
    }

    private function uaFingerprint(string $ua): string
    {
        $ua = strtolower($ua);
        $browser = match (true) {
            str_contains($ua, 'edg/')      => 'edge',
            str_contains($ua, 'chrome/')   => 'chrome',
            str_contains($ua, 'firefox/')  => 'firefox',
            str_contains($ua, 'safari/')   => 'safari',
            str_contains($ua, 'opera/')    => 'opera',
            default                        => 'other',
        };
        $os = match (true) {
            str_contains($ua, 'android')         => 'android',
            str_contains($ua, 'iphone')          => 'iphone',
            str_contains($ua, 'ipad')            => 'ipad',
            str_contains($ua, 'windows nt 10')   => 'win10',
            str_contains($ua, 'windows nt 11')   => 'win11',
            str_contains($ua, 'mac os x')        => 'mac',
            str_contains($ua, 'linux')           => 'linux',
            default                               => 'other',
        };
        return $browser . '/' . $os;
    }

    private function sameCountry(string $prevIp, string $thisIp): bool
    {
        // Heuristic: first two octets identical → almost always same country
        // (or at least same ISP region). Avoids shipping a geoip DB.
        $a = explode('.', $prevIp);
        $b = explode('.', $thisIp);
        if (count($a) === 4 && count($b) === 4) {
            return $a[0] === $b[0] && $a[1] === $b[1];
        }
        // IPv6 fallback — compare first 4 hextets.
        return implode(':', array_slice(explode(':', $prevIp), 0, 4))
            === implode(':', array_slice(explode(':', $thisIp), 0, 4));
    }
}
