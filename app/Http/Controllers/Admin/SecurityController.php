<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\SystemSetting;
use App\Models\User;
use App\Models\Workspace;
use App\Support\Audit;
use App\Support\PlatformPermissions;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * /admin/security — platform-wide security center.
 *
 * Settings persisted under SystemSetting with `security.*` keys.
 * Default values returned by the policy() helper below — that's the
 * single source of truth for what controls are available and their
 * default state.
 */
class SecurityController extends Controller
{
    /**
     * Every security knob this page exposes. Single source of truth.
     * Adding a key here makes it surfaceable + saveable; reading a key
     * not yet stored falls through to the default value here.
     */
    public const POLICY_KEYS = [
        // Login + MFA
        'security.require_2fa_for_admins'      => false,
        'security.require_2fa_for_owners'      => false,
        'security.require_2fa_for_all'         => false,
        'security.allowed_2fa_methods'         => ['totp', 'email'],   // 'totp' | 'email' | 'telegram'
        'security.session_timeout_minutes'     => 60,
        'security.max_concurrent_sessions'     => 5,
        'security.remember_me_enabled'         => true,
        'security.lockout_after_failures'      => 5,
        'security.lockout_window_minutes'      => 15,
        // Password policy
        'security.password_min_length'         => 8,
        'security.password_require_symbol'     => false,
        'security.password_require_number'     => true,
        'security.password_require_upper'      => true,
        'security.password_max_age_days'       => 0,                   // 0 = never
        // WhatsApp guardrails.
        // Master switch for the whole card — OFF by default so deploying
        // enforcement changes NOTHING until an admin opts in:
        //   off     → no checks run (current behaviour)
        //   monitor → checks run, would-be blocks are logged, sends still go
        //   enforce → a violation blocks the send
        'security.wa_guardrails_mode'          => 'off',
        // Caps: 0 = unlimited, so a high-volume sender is never throttled
        // by surprise. Individual content filters default OFF too.
        'security.wa_max_sends_per_minute'     => 0,
        'security.wa_max_sends_per_day'        => 0,
        'security.wa_hold_on_scam_pattern'     => false,
        'security.wa_hold_on_links_count'      => 0,                   // 0 = no limit
        'security.wa_require_template_review'  => false,
        // Abuse filters
        'security.abuse_block_finance_terms'   => false,
        'security.abuse_block_short_links'     => false,
        'security.abuse_block_keyword_list'    => [],                  // array of strings
        // API / webhooks
        'security.api_rate_limit_per_minute'   => 600,
        'security.webhook_signature_required'  => true,
        'security.webhook_replay_window_sec'   => 300,
        // Devices
        'security.device_trust_required'       => false,
        'security.device_logout_on_inactive_days' => 30,
        // IP allowlist
        'security.ip_allowlist_enabled'        => false,
        'security.ip_allowlist_cidrs'          => [],                  // array of strings
        // Login alerts
        'security.alert_on_new_device'         => true,
        'security.alert_on_new_country'        => true,
        'security.alert_channel'               => 'email',             // 'email' | 'whatsapp' | 'both'
        // Audit log retention — feeds `php artisan audit:prune`.
        // 0 = keep forever (compliance setups), otherwise days.
        'security.audit_log_retention_days'    => 365,
        // Privacy — mask customer phone numbers in the inbox / chat UI.
        // ON (default) = show only the last 4 digits (e.g. xxxxxx8988) everywhere a
        // number is displayed. OFF = show the full number. Saved contact NAMES are
        // shown either way; this only controls the raw number.
        'security.mask_customer_numbers'       => true,
    ];

    public function index(): View
    {
        $policy = $this->loadPolicy();
        $kpis   = $this->computeKpis($policy);
        $risks  = $this->riskQueue();
        $controls = $this->controlsCoverage($policy);

        return view('admin.security.index', compact('policy', 'kpis', 'risks', 'controls'));
    }

    /**
     * POST /admin/security/mask — dedicated, JS-free save for the phone-mask
     * toggle. It lives in its OWN tiny form (via the HTML5 `form=` attribute)
     * so it never depends on the big settings form submitting. Plain POST, no
     * PATCH spoofing, no JS.
     */
    public function saveMask(Request $request): RedirectResponse
    {
        $on = $request->boolean('mask_customer_numbers');
        SystemSetting::set('security.mask_customer_numbers', $on, 'bool', 'Security policy: mask_customer_numbers');
        Audit::log('admin.security.policy_updated', [
            'layer' => 'platform',
            'meta'  => ['fields' => ['mask_customer_numbers'], 'to' => $on],
        ]);
        return back()->with('status', $on
            ? __('Phone masking turned ON — numbers show as the last 4 digits.')
            : __('Phone masking turned OFF — full numbers are shown.'));
    }

    public function update(Request $request): RedirectResponse
    {
        // Toggles/checkboxes: an UNCHECKED box submits NOTHING, and the
        // 'sometimes' rule then DROPS the field — so turning any toggle OFF was
        // silently ignored (it kept the previous value). Coerce every boolean
        // field to a concrete true/false first (unchecked → false, present)
        // so "off" actually persists.
        $booleanFields = [
            'require_2fa_for_admins', 'require_2fa_for_owners', 'require_2fa_for_all',
            'remember_me_enabled', 'password_require_symbol', 'password_require_number',
            'password_require_upper', 'wa_hold_on_scam_pattern', 'wa_require_template_review',
            'abuse_block_finance_terms', 'abuse_block_short_links', 'webhook_signature_required',
            'device_trust_required', 'ip_allowlist_enabled', 'alert_on_new_device',
            'alert_on_new_country',
            // NOTE: mask_customer_numbers is intentionally NOT here — it has its
            // own dedicated form + saveMask() route so it never depends on this
            // big form submitting. Handling it here too would clobber it to
            // false on every other save (its input isn't part of this form).
        ];
        $request->merge(
            collect($booleanFields)->mapWithKeys(fn ($f) => [$f => $request->boolean($f)])->all()
        );

        $data = $request->validate([
            // login + mfa
            'require_2fa_for_admins'      => 'sometimes|boolean',
            'require_2fa_for_owners'      => 'sometimes|boolean',
            'require_2fa_for_all'         => 'sometimes|boolean',
            'allowed_2fa_methods'         => 'sometimes|array',
            'allowed_2fa_methods.*'       => 'in:totp,email,telegram',
            'session_timeout_minutes'     => 'sometimes|nullable|integer|min:5|max:1440',
            'max_concurrent_sessions'     => 'sometimes|nullable|integer|min:1|max:50',
            'remember_me_enabled'         => 'sometimes|boolean',
            'lockout_after_failures'      => 'sometimes|nullable|integer|min:1|max:50',
            'lockout_window_minutes'      => 'sometimes|nullable|integer|min:1|max:1440',
            'password_min_length'         => 'sometimes|nullable|integer|min:6|max:128',
            'password_require_symbol'     => 'sometimes|boolean',
            'password_require_number'     => 'sometimes|boolean',
            'password_require_upper'      => 'sometimes|boolean',
            'password_max_age_days'       => 'sometimes|nullable|integer|min:0|max:3650',
            // wa
            'wa_guardrails_mode'          => 'sometimes|in:off,monitor,enforce',
            'wa_max_sends_per_minute'     => 'sometimes|nullable|integer|min:0|max:10000',
            'wa_max_sends_per_day'        => 'sometimes|nullable|integer|min:0|max:1000000',
            'wa_hold_on_scam_pattern'     => 'sometimes|boolean',
            'wa_hold_on_links_count'      => 'sometimes|nullable|integer|min:0|max:50',
            'wa_require_template_review'  => 'sometimes|boolean',
            // abuse
            'abuse_block_finance_terms'   => 'sometimes|boolean',
            'abuse_block_short_links'     => 'sometimes|boolean',
            'abuse_block_keyword_list'    => 'sometimes|nullable|string|max:5000',
            // api
            'api_rate_limit_per_minute'   => 'sometimes|nullable|integer|min:1|max:100000',
            'webhook_signature_required'  => 'sometimes|boolean',
            'webhook_replay_window_sec'   => 'sometimes|nullable|integer|min:10|max:3600',
            // devices
            'device_trust_required'           => 'sometimes|boolean',
            'device_logout_on_inactive_days'  => 'sometimes|nullable|integer|min:0|max:3650',
            // ip
            'ip_allowlist_enabled'        => 'sometimes|boolean',
            'ip_allowlist_cidrs'          => 'sometimes|nullable|string|max:5000',
            // alerts
            'alert_on_new_device'         => 'sometimes|boolean',
            'alert_on_new_country'        => 'sometimes|boolean',
            'alert_channel'               => 'sometimes|in:email,whatsapp,both',
            // audit retention
            'audit_log_retention_days'    => 'sometimes|nullable|integer|min:0|max:3650',
        ]);

        $changed = [];

        foreach ($data as $field => $value) {
            $key = 'security.' . $field;

            // Normalise textarea → array for the two list-of-strings fields.
            // These arrive as NULL when left empty (an empty <textarea> posts "",
            // which ConvertEmptyStringsToNull turns into null) — so cast before
            // the null check below, otherwise clearing a list could never be
            // saved. Empty textarea → empty list, which is the intent.
            if ($field === 'abuse_block_keyword_list' || $field === 'ip_allowlist_cidrs') {
                $value = collect(preg_split('/[\r\n,]+/', (string) $value))
                    ->map(fn ($v) => trim($v))
                    ->filter()
                    ->values()
                    ->all();
            } elseif ($value === null) {
                // A blank numeric input now passes validation (see 'nullable'
                // above) so one empty box can no longer reject the WHOLE form.
                // Skip it rather than writing null, which would wipe a good value.
                continue;
            }
            $previous = SystemSetting::get($key);
            if ($previous !== $value) {
                $changed[$field] = ['from' => $previous, 'to' => $value];
            }
            SystemSetting::set($key, $value, $this->typeFor($value), 'Security policy: ' . $field);
        }

        if ($changed) {
            Audit::log('admin.security.policy_updated', [
                'layer' => 'platform',
                'meta'  => ['fields' => array_keys($changed), 'changes' => $changed],
            ]);
        }

        return redirect()->route('admin.security.index')->with('status', count($changed) . ' setting(s) saved.');
    }

    public function revokeAllSessions(Request $request): RedirectResponse
    {
        if (!$this->requireSuperAdmin($request)) return back();
        DB::table('sessions')->truncate();
        Audit::log('admin.security.sessions_revoked', ['layer' => 'platform', 'result' => 'warning']);
        return back()->with('status', 'All sessions revoked. Every user must log in again.');
    }

    public function forcePasswordReset(Request $request): RedirectResponse
    {
        if (!$this->requireSuperAdmin($request)) return back();
        $affected = User::query()->update(['force_password_change' => true]);
        Audit::log('admin.security.force_password_reset_all', [
            'layer'  => 'platform',
            'result' => 'warning',
            'meta'   => ['affected_users' => $affected],
        ]);
        return back()->with('status', "{$affected} users must reset their password on next login.");
    }

    public function rotateWebhookSecrets(Request $request): RedirectResponse
    {
        if (!$this->requireSuperAdmin($request)) return back();
        // Touch every workspace's webhook_secret if the column exists.
        $rotated = 0;
        if (\Schema::hasColumn('workspaces', 'webhook_secret')) {
            Workspace::query()->chunkById(50, function ($chunk) use (&$rotated) {
                foreach ($chunk as $ws) {
                    $ws->update(['webhook_secret' => 'whsec_' . bin2hex(random_bytes(20))]);
                    $rotated++;
                }
            });
        }
        Audit::log('admin.security.webhook_secrets_rotated', [
            'layer'  => 'platform',
            'result' => 'warning',
            'meta'   => ['rotated' => $rotated],
        ]);
        return back()->with('status', "Rotated webhook secrets for {$rotated} workspace(s).");
    }

    public function emergencyStopSends(Request $request): RedirectResponse
    {
        if (!$this->requireSuperAdmin($request)) return back();
        SystemSetting::set('platform.emergency_send_halt', true, 'bool', 'Global emergency switch — when true, ALL outbound sends are blocked.');
        Audit::log('admin.security.emergency_halt_engaged', ['layer' => 'platform', 'result' => 'warning']);
        return back()->with('status', 'Emergency stop engaged. ALL outbound sends are blocked.');
    }

    public function emergencyResumeSends(Request $request): RedirectResponse
    {
        if (!$this->requireSuperAdmin($request)) return back();
        SystemSetting::set('platform.emergency_send_halt', false, 'bool', 'Global emergency switch.');
        Audit::log('admin.security.emergency_halt_released', ['layer' => 'platform', 'result' => 'success']);
        return back()->with('status', 'Sends resumed.');
    }

    /**
     * Gate the 5 nuclear actions behind Super Admin. The route group is
     * already inside the admin auth middleware, so a regular `admin`
     * role user can hit these URLs — that's the hole this plugs.
     * Records the denied attempt so audit trail shows who tried.
     */
    private function requireSuperAdmin(Request $request): bool
    {
        if (PlatformPermissions::isSuperAdmin($request->user())) return true;
        Audit::log('admin.security.danger_action_denied', [
            'layer'  => 'platform',
            'result' => 'failure',
            'meta'   => ['route' => $request->route()?->getName(), 'reason' => 'requires_super_admin'],
        ]);
        $request->session()->flash('error', 'This emergency control requires Super Admin. Your account is missing that role.');
        return false;
    }

    // ─── helpers ─────────────────────────────────────────────────────

    private function loadPolicy(): array
    {
        $out = [];
        foreach (self::POLICY_KEYS as $key => $default) {
            $out[substr($key, strlen('security.'))] = SystemSetting::get($key, $default);
        }
        $out['emergency_send_halt'] = (bool) SystemSetting::get('platform.emergency_send_halt', false);
        return $out;
    }

    private function computeKpis(array $policy): array
    {
        // Open risks: unresolved failure/warning audit rows in the last 7d.
        $openRisks = AuditLog::query()
            ->whereIn('result', ['failure', 'warning'])
            ->where('created_at', '>=', now()->subDays(7))
            ->count();
        $highPriority = AuditLog::query()
            ->where('result', 'failure')
            ->where('created_at', '>=', now()->subDays(7))
            ->count();

        // Blocked attempts: failed auth in the last 24h.
        $blockedAttempts = AuditLog::query()
            ->where('action', 'auth.failed')
            ->where('created_at', '>=', now()->subDay())
            ->count();

        // Campaign holds: warning audit rows in the last 24h (placeholder
        // until a dedicated campaigns_holds table exists).
        $campaignHolds = AuditLog::query()
            ->where('result', 'warning')
            ->where('created_at', '>=', now()->subDay())
            ->count();

        // 2FA coverage of admin role users.
        $totalAdmins   = User::where('role', 'admin')->count();
        $enrolledAdmins= User::where('role', 'admin')->whereNotNull('two_factor_confirmed_at')->count();
        $coverage2fa   = $totalAdmins > 0 ? round($enrolledAdmins * 100 / $totalAdmins) : 0;

        // Webhook failures as % of total webhook events in the last 24h
        // (any audit row tagged with 'webhook' in action).
        $webhookTotal = AuditLog::where('action', 'like', '%webhook%')
            ->where('created_at', '>=', now()->subDay())->count();
        $webhookFailures = AuditLog::where('action', 'like', '%webhook%')
            ->where('result', 'failure')
            ->where('created_at', '>=', now()->subDay())->count();
        $webhookPct = $webhookTotal > 0 ? round($webhookFailures * 100 / $webhookTotal, 2) : 0;

        // Security score: simple weighted sum of enabled controls.
        $score = $this->securityScore($policy);

        return [
            'security_score'    => $score,
            'open_risks'        => $openRisks,
            'high_priority'     => $highPriority,
            'blocked_attempts'  => $blockedAttempts,
            'campaign_holds'    => $campaignHolds,
            'tfa_coverage'      => $coverage2fa,
            'tfa_enrolled'      => $enrolledAdmins,
            'tfa_admins_total'  => $totalAdmins,
            'webhook_failures'  => $webhookPct,
        ];
    }

    private function securityScore(array $policy): int
    {
        // 10 toggle controls × 10 points each. Yes, simple by design —
        // the admin can see what they enabled and what's still off.
        $controls = [
            (bool) $policy['require_2fa_for_admins'],
            (bool) $policy['wa_hold_on_scam_pattern'],
            (bool) $policy['wa_require_template_review'],
            (bool) $policy['abuse_block_finance_terms'],
            (bool) $policy['abuse_block_short_links'],
            (bool) $policy['webhook_signature_required'],
            (bool) $policy['alert_on_new_device'],
            (bool) $policy['alert_on_new_country'],
            (int)  $policy['session_timeout_minutes'] > 0,
            (int)  $policy['lockout_after_failures'] > 0,
        ];
        return (int) (array_sum($controls) * 10);
    }

    private function riskQueue(): array
    {
        // 4 most recent failure/warning rows for the summary table.
        $rows = AuditLog::query()
            ->whereIn('result', ['failure', 'warning'])
            ->latest('created_at')
            ->limit(4)
            ->get();

        $userIds = $rows->pluck('actor_user_id')->filter()->unique();
        $wsIds   = $rows->pluck('workspace_id')->filter()->unique();
        $users   = User::whereIn('id', $userIds)->get()->keyBy('id');
        $wss     = Workspace::whereIn('id', $wsIds)->get()->keyBy('id');

        $appName = \App\Models\SystemSetting::get('app_name', config('app.name', 'WaDesk'));
        return $rows->map(function ($r) use ($users, $wss, $appName) {
            $severity = match (true) {
                $r->result === 'failure' && $r->created_at?->diffInHours() < 24 => 'high',
                $r->result === 'failure'                                         => 'medium',
                $r->result === 'warning'                                         => 'watch',
                default                                                          => 'info',
            };
            return [
                'id'        => $r->id,
                'severity'  => $severity,
                'signal'    => $r->action,
                'detail'    => $r->payload['_label'] ?? ($r->subject_type ? $r->subject_type . '#' . $r->subject_id : ''),
                'workspace' => $r->workspace_id ? ($wss[$r->workspace_id]?->name ?? 'WS#' . $r->workspace_id) : ($r->layer === 'platform' ? $appName . ' Platform' : '—'),
                'owner'     => $r->actor_user_id ? ($users[$r->actor_user_id]?->name ?? 'User #' . $r->actor_user_id) : 'System',
            ];
        })->all();
    }

    private function controlsCoverage(array $policy): array
    {
        // Four buckets of related toggles, expressed as a % enabled.
        $buckets = [
            'Admin access'             => [
                $policy['require_2fa_for_admins'],
                $policy['lockout_after_failures'] > 0,
                $policy['session_timeout_minutes'] > 0,
                $policy['max_concurrent_sessions'] > 0,
            ],
            'WhatsApp abuse prevention'=> [
                $policy['wa_hold_on_scam_pattern'],
                $policy['wa_require_template_review'],
                $policy['abuse_block_finance_terms'],
                $policy['abuse_block_short_links'],
            ],
            'API hardening'            => [
                $policy['webhook_signature_required'],
                $policy['api_rate_limit_per_minute'] > 0,
                $policy['webhook_replay_window_sec'] > 0,
            ],
            'Incident readiness'       => [
                $policy['alert_on_new_device'],
                $policy['alert_on_new_country'],
                ! $policy['emergency_send_halt'],   // green when halt is OFF
            ],
        ];
        $out = [];
        foreach ($buckets as $label => $checks) {
            $enabled = count(array_filter($checks));
            $total   = count($checks);
            $out[] = [
                'label' => $label,
                'pct'   => $total > 0 ? round($enabled * 100 / $total) : 0,
                'on'    => $enabled,
                'of'    => $total,
            ];
        }
        return $out;
    }

    private function typeFor($value): string
    {
        return match (true) {
            is_bool($value)  => 'bool',
            is_int($value)   => 'int',
            is_float($value) => 'float',
            is_array($value) => 'json',
            default          => 'string',
        };
    }
}
