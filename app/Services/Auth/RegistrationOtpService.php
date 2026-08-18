<?php

namespace App\Services\Auth;

use App\Models\Device;
use App\Models\SystemSetting;
use App\Models\WaProviderConfig;
use App\Models\WaTemplate;
use App\Services\Waba\TemplateSender;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Registration mobile-verification OTP.
 *
 * Sends a one-time code to a registering user's WhatsApp using whichever engine
 * the admin-selected SENDER runs on. There is no workspace at registration time,
 * so the admin designates a platform-level sender in Admin > Settings. Two data
 * models back the three engines, so the sender is stored as a composite string:
 *
 *   registration_otp_sender = "device:<id>"   → Unofficial (Baileys) — a Device row
 *                             "waba:<id>"      → WABA   — a WaProviderConfig row
 *                             "twilio:<id>"    → Twilio — a WaProviderConfig row
 *
 * (Device rows ARE the Baileys channels; WABA/Twilio live in wa_provider_configs.)
 *
 * Per engine:
 *   - WABA    : business-initiated needs an approved template → the admin's
 *               APPROVED authentication template via TemplateSender (code = {{otp}}).
 *   - Baileys : free-form text to the recipient through the Node bridge.
 *   - Twilio  : free-form WhatsApp text via the Twilio Messages API.
 *
 * The code is never stored here — the controller keeps only a HASH of it in the
 * session (verify-before-create): no half-registered users, no plaintext at rest.
 */
class RegistrationOtpService
{
    /** Master gate: enabled AND a usable sender AND (for WABA) an approved template. */
    public function isActive(): bool
    {
        if (!(bool) SystemSetting::get('registration_otp_enabled', false)) {
            return false;
        }
        $s = $this->resolveSender();
        if (!$s) {
            return false;
        }
        if ($s['engine'] === 'waba') {
            $tpl = $this->template();
            if (!$tpl || strtoupper((string) ($tpl->meta_status ?? '')) !== 'APPROVED') {
                return false;
            }
        }
        return true;
    }

    public function length(): int
    {
        return max(4, min(8, (int) SystemSetting::get('registration_otp_length', 6)));
    }

    public function ttlMinutes(): int
    {
        $ttl = (int) SystemSetting::get('registration_otp_ttl_minutes', 0);
        if ($ttl <= 0) {
            $ttl = (int) ($this->template()?->code_expiration_minutes ?? 0);
        }
        return $ttl > 0 ? min(90, $ttl) : 5;
    }

    public function resendCooldownSec(): int
    {
        return max(15, (int) SystemSetting::get('registration_otp_resend_cooldown_sec', 60));
    }

    public function maxAttempts(): int
    {
        return max(1, (int) SystemSetting::get('registration_otp_max_attempts', 5));
    }

    public function generateCode(): string
    {
        $len = $this->length();
        $max = (10 ** $len) - 1;
        return str_pad((string) random_int(0, $max), $len, '0', STR_PAD_LEFT);
    }

    /**
     * Send $code to ($countryCode + $mobile) over the selected engine.
     * Returns ['ok' => bool, 'error' => ?string]. Never throws.
     */
    public function send(string $countryCode, string $mobile, string $code): array
    {
        $to = preg_replace('/\D+/', '', $countryCode . $mobile);
        if ($to === '') {
            return ['ok' => false, 'error' => 'Invalid phone number.'];
        }
        $s = $this->resolveSender();
        if (!$s) {
            return ['ok' => false, 'error' => 'No OTP sender is configured. Pick one in Admin settings.'];
        }

        try {
            return match ($s['engine']) {
                'waba'   => $this->sendWaba($s['cfg'], $to, $code),
                'twilio' => $this->sendTwilio($s['cfg'], $to, $code),
                default  => $this->sendBaileys($s['device'], $to, $code),
            };
        } catch (\Throwable $e) {
            Log::error('[REG-OTP] send failed', ['engine' => $s['engine'], 'error' => $e->getMessage()]);
            return ['ok' => false, 'error' => 'Could not send the code. Please try again.'];
        }
    }

    /** The WABA config when the selected sender is WABA — used by the create-template action. */
    public function wabaConfig(): ?WaProviderConfig
    {
        $s = $this->resolveSender();
        return ($s && $s['engine'] === 'waba') ? $s['cfg'] : null;
    }

    private function bodyText(string $code): string
    {
        // Prefer the OTP template's body (the one the admin created for this
        // channel), substituting the code for {{1}} / {{code}} / {{otp}}.
        $body = trim((string) ($this->template()?->template_body ?? ''));
        if ($body !== '') {
            return str_replace(['{{1}}', '{{code}}', '{{otp}}'], $code, $body);
        }
        $app  = (string) SystemSetting::get('app_name', config('app.name', 'WaDesk'));
        $mins = $this->ttlMinutes();
        return "*{$code}* is your {$app} verification code. It expires in {$mins} minutes. Do not share it with anyone.";
    }

    // --- engine paths -------------------------------------------------------

    private function sendWaba(?WaProviderConfig $cfg, string $to, string $code): array
    {
        if (!$cfg) {
            return ['ok' => false, 'error' => 'WABA sender is not configured.'];
        }
        $tpl = $this->template();
        if (!$tpl) {
            return ['ok' => false, 'error' => 'OTP template is not set. Create & submit it in Admin settings.'];
        }
        $res = app(TemplateSender::class)->send($tpl, $to, ['otp' => $code], $cfg);
        return ['ok' => (bool) ($res['ok'] ?? $res['success'] ?? false), 'error' => $res['error'] ?? null];
    }

    private function sendBaileys(?Device $device, string $to, string $code): array
    {
        if (!$device) {
            return ['ok' => false, 'error' => 'Unofficial sender device not found.'];
        }
        // server_url: the workspace's baileys config, else the platform default.
        $bcfg   = $device->workspace_id
            ? WaProviderConfig::query()->where('workspace_id', $device->workspace_id)->where('provider', 'baileys')->first()
            : null;
        $server = (string) ($bcfg?->creds()['server_url'] ?? '')
            ?: (string) (SystemSetting::get('baileys_server_url') ?: env('SERVER_URL', ''));
        if ($server === '') {
            return ['ok' => false, 'error' => 'Unofficial API server is not configured.'];
        }
        $sender = preg_replace('/\D+/', '', (string) ($device->country_code . $device->phone_number));
        if ($sender === '') {
            return ['ok' => false, 'error' => 'The selected device has no phone number.'];
        }
        $res = Http::timeout(20)
            ->withHeaders(['X-Node-Token' => (string) node_token()])
            ->post(rtrim($server, '/') . '/api/send-message/' . rawurlencode($sender), [
                'targetPhoneNumber' => $to,
                'message'           => $this->bodyText($code),
            ]);
        if ($res->successful() && ($res->json('success') ?? $res->json('ok') ?? true)) {
            return ['ok' => true, 'error' => null];
        }
        return ['ok' => false, 'error' => $res->json('error') ?? $res->json('message') ?? ('HTTP ' . $res->status())];
    }

    private function sendTwilio(?WaProviderConfig $cfg, string $to, string $code): array
    {
        if (!$cfg) {
            return ['ok' => false, 'error' => 'Twilio sender is not configured.'];
        }
        $creds = $cfg->creds();
        $sid   = (string) ($creds['account_sid'] ?? '');
        $token = (string) ($creds['auth_token'] ?? '');
        $from  = (string) ($creds['from_number'] ?? '');
        if ($sid === '' || $token === '' || $from === '') {
            return ['ok' => false, 'error' => 'Twilio credentials are incomplete.'];
        }
        $res = Http::asForm()->withBasicAuth($sid, $token)->timeout(20)
            ->post("https://api.twilio.com/2010-04-01/Accounts/{$sid}/Messages.json", [
                'From' => str_starts_with($from, 'whatsapp:') ? $from : 'whatsapp:' . $from,
                'To'   => 'whatsapp:+' . ltrim($to, '+'),
                'Body' => $this->bodyText($code),
            ]);
        return $res->successful()
            ? ['ok' => true, 'error' => null]
            : ['ok' => false, 'error' => $res->json('message') ?? ('HTTP ' . $res->status())];
    }

    // --- resolution ---------------------------------------------------------

    /**
     * Parse `registration_otp_sender` into ['engine', 'device'|null, 'cfg'|null].
     * Falls back to the legacy `registration_otp_device_id` (always Baileys).
     * Null when nothing usable is configured.
     */
    private function resolveSender(): ?array
    {
        $raw = trim((string) SystemSetting::get('registration_otp_sender', ''));
        if ($raw === '') {
            $legacy = (int) SystemSetting::get('registration_otp_device_id', 0);
            if ($legacy > 0) {
                $raw = 'device:' . $legacy;
            }
        }
        if ($raw === '') {
            return null;
        }
        [$kind, $idRaw] = array_pad(explode(':', $raw, 2), 2, '');
        $id = (int) $idRaw;
        if ($id <= 0) {
            return null;
        }
        if ($kind === 'device') {
            $d = Device::find($id);
            return $d ? ['engine' => 'baileys', 'device' => $d, 'cfg' => null] : null;
        }
        if ($kind === 'waba' || $kind === 'twilio') {
            $c = WaProviderConfig::find($id);
            return ($c && $c->provider === $kind) ? ['engine' => $kind, 'device' => null, 'cfg' => $c] : null;
        }
        return null;
    }

    private function template(): ?WaTemplate
    {
        $id = (int) SystemSetting::get('registration_otp_template_id', 0);
        return $id > 0 ? WaTemplate::find($id) : null;
    }
}
