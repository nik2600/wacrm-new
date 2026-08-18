<?php

namespace App\Http\Controllers;

use App\Models\Device;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;

/**
 * Devices controller — ports the old project's
 * D:\wadesk_2806\New folder\app\Http\Controllers\UserDevicesController.php
 * (index / store / update / destroy / check / import) onto the
 * new Eloquent + encrypted-at-rest pattern.
 *
 * The page uses AJAX for filter pills and live search, so index()
 * returns either the full view or a JSON `{cards, counts}`
 * partial when the request asks for JSON / `partial=1`.
 */
class DevicesController extends Controller
{
    // -----------------------------------------------------------------
    // Pages
    // -----------------------------------------------------------------

    // -----------------------------------------------------------------
    // WABA multi-account actions
    // -----------------------------------------------------------------

    /**
     * POST /devices/waba/connect/manual — manual-paste connect flow.
     * The user pastes Phone Number ID + Access Token + WABA ID; we
     * probe Meta's Graph API to validate, save the row, and subscribe
     * their WABA to our app's webhooks.
     */
    public function wabaConnectManual(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'phone_number_id' => 'required|string|max:40',
            'waba_id'         => 'required|string|max:40',
            'business_id'     => 'nullable|string|max:40',
            'access_token'    => 'required|string|max:1024',
            'display_label'   => 'nullable|string|max:120',
            'app_id'          => 'nullable|string|max:64',
            'app_secret'      => 'nullable|string|max:128',
        ]);

        $wsId = Auth::user()?->current_workspace_id;
        if (!$wsId) return back()->withErrors(['workspace' => 'No active workspace. Pick one and try again.']);

        // Plan limit — WABA numbers count toward the UNIFIED device cap
        // (Baileys + WABA + Twilio together). This path previously bypassed
        // the cap entirely, so a capped plan could connect unlimited Meta
        // numbers. Fail fast before we hit Meta's APIs.
        \App\Services\PlanLimitGuard::check(
            Auth::user()->currentWorkspace,
            'device_limit',
            $this->unifiedDeviceCount((int) $wsId),
        );

        $version = (string) \App\Models\SystemSetting::get('waba_graph_api_version', 'v23.0');
        $base    = 'https://graph.facebook.com/' . ltrim($version, '/');

        // Auto-extend a TEMPORARY (short-lived) token to a long-lived (~60-day)
        // one when the app id + secret are supplied — silently, so the user never
        // has to think about token lifetimes (no button, no extra step). Keeps the
        // SAME scopes; on ANY failure we just keep the pasted token (it may still
        // work for now) rather than block the connect.
        // App id/secret: prefer what the user typed; otherwise fall back to the
        // PLATFORM's configured Meta app (the admin's waba_app_id / waba_app_secret
        // used for Embedded Signup) — so the CLIENT usually needn't type anything.
        // The exchange only succeeds when these belong to the app that ISSUED the
        // token (e.g. our own app via Embedded Signup). For a token minted by the
        // client's OWN app the platform creds won't match → exchange no-ops and we
        // keep the pasted token (never blocks the connect).
        $exAppId  = !empty($data['app_id'])     ? trim($data['app_id'])     : (string) \App\Models\SystemSetting::get('waba_app_id', '');
        $exSecret = !empty($data['app_secret']) ? trim($data['app_secret']) : (string) \App\Models\SystemSetting::get('waba_app_secret', '');
        if ($exAppId !== '' && $exSecret !== '') {
            try {
                $ex   = \Illuminate\Support\Facades\Http::acceptJson()->timeout(15)
                    ->get($base . '/oauth/access_token', [
                        'grant_type'        => 'fb_exchange_token',
                        'client_id'         => $exAppId,
                        'client_secret'     => $exSecret,   // used once, never stored
                        'fb_exchange_token' => trim($data['access_token']),
                    ]);
                $long = (string) ($ex->json('access_token') ?? '');
                if ($ex->successful() && $long !== '') {
                    $data['access_token'] = $long;   // probe + save now use the long-lived token
                    \Log::info('[WABA-connect] token auto-extended to long-lived', [
                        'waba_id'    => $data['waba_id'],
                        'expires_in' => (int) ($ex->json('expires_in') ?? 0),
                    ]);
                } else {
                    \Log::warning('[WABA-connect] token exchange skipped — kept pasted token', [
                        'err' => (string) ($ex->json('error.message') ?? 'no token returned'),
                    ]);
                }
            } catch (\Throwable $e) {
                \Log::warning('[WABA-connect] token exchange threw — kept pasted token', ['err' => $e->getMessage()]);
            }
        }

        // 1. Probe the phone number to confirm the token is valid + pull
        // the full set of v23.0 phone-number fields. Surfaces typed
        // error subcodes per Meta's docs so the user sees actionable
        // copy ("token expired" vs "permission missing") not a wall of
        // JSON.
        try {
            $probe = Http::withToken($data['access_token'])
                ->acceptJson()
                ->timeout(15)
                ->get("{$base}/{$data['phone_number_id']}", [
                    'fields' => 'verified_name,display_phone_number,quality_rating,messaging_limit_tier,'
                              . 'code_verification_status,name_status,account_mode,throughput,'
                              . 'platform_type,is_official_business_account,is_pin_enabled,last_onboarded_time',
                ]);
            if (!$probe->successful()) {
                return back()->withErrors([
                    'access_token' => $this->wabaErrorMessage($probe->json('error', [])),
                ])->withInput();
            }
            $info = $probe->json();
        } catch (\Throwable $e) {
            return back()->withErrors(['access_token' => 'Could not reach Meta: ' . $e->getMessage()])->withInput();
        }

        // Refuse a number that isn't ON Cloud API. Valid credentials say who we
        // are — they do NOT move a number between Meta's platforms, so an
        // ON_PREMISE number connects "green" and then fails EVERY send with
        // (#133010) Account not registered. Meta retired On-Premises on
        // 2025-10-23 ("can't be used to send messages anymore") and refuses
        // /register for it, so nothing here can recover it — only the operator
        // migrating the number in WhatsApp Manager can. Say that up front
        // instead of handing them a working-looking device that never sends.
        // COEXISTENCE / SMB_APP are legitimate (Business-app numbers we support)
        // — only block what Meta itself can no longer deliver for.
        $platform = strtoupper((string) ($info['platform_type'] ?? ''));
        if ($platform === 'ON_PREMISE') {
            \Log::warning('[WABA-connect] refused ON_PREMISE number', [
                'phone_number_id'   => $data['phone_number_id'],
                'waba_id'           => $data['waba_id'],
                'display'           => $info['display_phone_number'] ?? null,
                'code_verification' => $info['code_verification_status'] ?? null,
                'status'            => $info['status'] ?? null,
            ]);
            $verifyNote = (($info['code_verification_status'] ?? '') === 'NOT_VERIFIED')
                ? ' This number is also NOT VERIFIED — verify it first, then register.'
                : '';
            return back()->withErrors(['phone_number_id' =>
                'This number (' . ($info['display_phone_number'] ?? $data['phone_number_id']) . ') is still registered on '
                . 'WhatsApp\'s On-Premises API, which Meta shut down on 23 Oct 2025 — it cannot send through the Cloud API '
                . 'in this state, on any platform. Open WhatsApp Manager → Phone numbers, verify the number, then register '
                . 'it on the Cloud API (you will set a 6-digit PIN). Once it shows as Cloud API, connect it here again — '
                . 'these same credentials will work.' . $verifyNote,
            ])->withInput();
        }

        // 1b. Auto-resolve Business Manager ID from the WABA if the user
        // didn't paste it. Saves them a copy-paste from yet another tab.
        $businessId = $data['business_id'] ?? null;
        if (! $businessId) {
            try {
                $owner = Http::withToken($data['access_token'])->acceptJson()->timeout(10)
                    ->get("{$base}/{$data['waba_id']}", ['fields' => 'owner_business_info{id,name}']);
                $businessId = $owner->json('owner_business_info.id') ?: null;
            } catch (\Throwable $e) { /* best-effort */ }
        }

        // 2. Subscribe this WABA to webhooks — AND override the callback URL to
        // OUR inbound endpoint, so this customer's incoming messages reach US even
        // though we aren't the Tech Provider that owns the app. Per Meta's
        // subscribed_apps API, passing override_callback_uri + verify_token routes
        // THIS WABA's webhooks to our URL (validated by the GET hub.challenge our
        // /webhooks/whatsapp/inbound endpoint already answers). Needs the token to
        // hold whatsapp_business_management. With no verify token configured we
        // fall back to a plain subscribe. Idempotent on Meta's side. Surface
        // 1349174 (missing whatsapp_business_management scope) to the user.
        // Subscribe this WABA to webhooks AND override its callback URL to OUR
        // inbound endpoint. Users bring their OWN Meta app's WABA id + token, so
        // the number is subscribed to THEIR app — the ONLY way to make its inbound
        // reach THIS platform is override_callback_uri, which registers our URL as
        // the WABA's alternate callback (Meta verifies it synchronously via the GET
        // hub.challenge our /webhooks/whatsapp/inbound endpoint answers, so a
        // successful POST means the override is live). Requires the token to carry
        // whatsapp_business_management. verify_token is auto-provisioned so the
        // override is never silently skipped.
        $verifyTok    = $this->wabaWebhookVerifyToken();
        $webhookWired = true;
        try {
            // Meta rejects an override_callback_uri unless the app is ALREADY
            // subscribed to this WABA — it returns (#100) "Before override the
            // current callback uri, your app must be subscribed to receive
            // messages for WhatsApp Business Account". So subscribe PLAINLY first
            // (empty body), THEN override the callback to our inbound URL.
            Http::withToken($data['access_token'])->acceptJson()->timeout(15)
                ->post("{$base}/{$data['waba_id']}/subscribed_apps", []);

            $sub = Http::withToken($data['access_token'])
                ->acceptJson()
                ->timeout(15)
                ->post("{$base}/{$data['waba_id']}/subscribed_apps", [
                    'override_callback_uri' => url('/webhooks/whatsapp/inbound'),
                    'verify_token'          => $verifyTok,
                ]);
            if (!$sub->successful()) {
                $subCode = (int) ($sub->json('error.error_subcode') ?? 0);
                if ($subCode === 1349174) {
                    return back()->withErrors([
                        'access_token' => 'Token is missing the whatsapp_business_management permission. In the user\'s Meta app, generate the System-User token WITH whatsapp_business_management, then paste it again.',
                    ])->withInput();
                }
                $webhookWired = false;
                \Log::warning('[WABA-connect] subscribed_apps failed', ['code' => $subCode, 'body' => $sub->body()]);
            }
        } catch (\Throwable $e) {
            $webhookWired = false;
            \Log::warning('[WABA-connect] subscribed_apps threw', ['error' => $e->getMessage()]);
        }

        // 3. Write the row. Re-connecting the SAME number (same waba_id +
        // phone_number_id) UPDATES that row in place instead of creating a
        // duplicate — so the "Manage / reconnect" gear is a real re-auth, not a
        // second copy. (meta_json compared in PHP so it works even when stored
        // encrypted.) First WABA in the workspace becomes primary.
        $cfg = \App\Models\WaProviderConfig::query()->forWorkspace($wsId)->where('provider', 'waba')->get()
            ->first(function ($row) use ($data) {
                $m = (array) ($row->meta_json ?? []);
                return (string) ($m['waba_id'] ?? '') === (string) $data['waba_id']
                    && (string) ($m['phone_number_id'] ?? '') === (string) $data['phone_number_id'];
            }) ?: new \App\Models\WaProviderConfig();
        $isNew    = !$cfg->exists;
        $existing = \App\Models\WaProviderConfig::query()->forWorkspace($wsId)->where('provider', 'waba')->count();
        $cfg->workspace_id   = $wsId;
        $cfg->provider       = 'waba';
        $cfg->status         = \App\Models\WaProviderConfig::STATUS_CONNECTED;
        $cfg->phone_number   = (string) ($info['display_phone_number'] ?? '');
        $cfg->display_label  = (string) ($data['display_label'] ?: ($info['verified_name'] ?? ''));
        $cfg->meta_json      = [
            'waba_id'                       => $data['waba_id'],
            'phone_number_id'               => $data['phone_number_id'],
            'business_id'                   => $businessId,
            'verified_name'                 => $info['verified_name']                 ?? null,
            'display_phone_number'          => $info['display_phone_number']          ?? null,
            'quality_rating'                => $info['quality_rating']                ?? null,
            'messaging_limit_tier'          => $info['messaging_limit_tier']          ?? null,
            'code_verification_status'      => $info['code_verification_status']      ?? null,
            'name_status'                   => $info['name_status']                   ?? null,
            'account_mode'                  => $info['account_mode']                  ?? null,
            'throughput'                    => $info['throughput']                    ?? null,
            'platform_type'                 => $info['platform_type']                 ?? null,
            'is_official_business_account'  => $info['is_official_business_account']  ?? null,
            'is_pin_enabled'                => $info['is_pin_enabled']                ?? null,
            'last_onboarded_time'           => $info['last_onboarded_time']           ?? null,
            'connected_via'                 => 'manual',
        ];
        $cfg->connected_at   = now();
        $cfg->last_health_at = now();
        if ($isNew) {
            $cfg->is_primary = ($existing === 0);   // first WABA → primary; on re-auth keep current flag
        }
        // Persist the token + (if supplied) the app id/secret. We keep the
        // app_secret because override-routed inbound webhooks are signed by the
        // TOKEN-OWNER's app, so WaWebhookController needs it to verify the
        // X-Hub-Signature-256 for this WABA. Encrypted at rest via creds().
        $cfg->setCreds(array_filter([
            'access_token' => $data['access_token'],
            'app_id'       => !empty($data['app_id'])     ? trim($data['app_id'])     : null,
            'app_secret'   => !empty($data['app_secret']) ? trim($data['app_secret']) : null,
        ]));
        $cfg->save();

        // TEMP DIAGNOSTIC — a manual connect shows green, then flips to
        // disconnected seconds later. Stamp what we actually persisted so the
        // "status changed" line (WaProviderConfig observer) can name the writer
        // that undoes it, instead of us guessing. Grep both by `row`.
        \Log::info('[WABA-connect] manual saved CONNECTED', [
            'build'             => 'waba-connect-trace-v1',
            'row'               => $cfg->id,
            'workspace_id'      => $wsId,
            'phone_number_id'   => $data['phone_number_id'],
            'waba_id'           => $data['waba_id'],
            'phone_number'      => $cfg->phone_number ?: '(none returned by Meta)',
            'is_primary'        => (bool) $cfg->is_primary,
            'is_new'            => $isNew,
            'code_verification' => $info['code_verification_status'] ?? null,
            'account_mode'      => $info['account_mode'] ?? null,
            'platform_type'     => $info['platform_type'] ?? null,
            'connected_at'      => (string) $cfg->connected_at,
        ]);

        // Register on Cloud API — a manually-pasted number is NOT coexistence,
        // and without this the first send fails with (#133010) Account not
        // registered. Best-effort: a failure here (e.g. a number with an
        // existing 2FA pin) must not block the connect — the operator can still
        // use the per-number "Register" button with the correct pin.
        app(\App\Services\Waba\WabaNumberRegistrar::class)->register($cfg);

        // Post-connect verification: ask Meta which apps this WABA is subscribed
        // to and confirm OUR app is among them — so we tell the user for sure
        // whether inbound is wired, not just that the subscribe POST returned OK.
        $verified = $this->verifyInboundWired($cfg);
        $this->stampInboundWired($cfg, $verified);
        $wired = $verified ?? $webhookWired;   // definite verdict wins; else POST result

        \App\Support\Audit::log('devices.waba_connect_manual', [
            'subject_type' => 'wa_provider_config', 'subject_id' => $cfg->id,
            'meta' => ['phone' => $cfg->phone_number, 'waba_id' => $data['waba_id'], 'inbound_wired' => $verified],
        ]);

        $label    = $cfg->phone_number ?: $cfg->display_label;
        $redirect = redirect()->route('user.devices.index');
        if ($wired === false) {
            return $redirect
                ->with('status', 'Connected WABA number "' . $label . '" for sending.')
                ->with('warning', 'Inbound is NOT wired yet for "' . $label . '" — Meta did not accept the webhook override. Make sure the token was generated WITH the whatsapp_business_management permission, then open the number and click "Fix inbound".');
        }
        return $redirect->with('status', 'Connected WABA number "' . $label . '". Two-way messaging is live — inbound is routed to this platform.');
    }

    /**
     * POST /devices/waba/verify-token — live self-check of a pasted WABA token
     * BEFORE saving. Runs Meta's debug_token (validity + scopes + expiry), then
     * tries to READ the WABA and its templates so the admin sees exactly which
     * permission/capability is missing — the common #10 ("WABA un-shared / app
     * not a Tech Provider") and #3 ("app missing whatsapp_business_management")
     * errors — instead of saving a broken token and finding out later.
     */
    public function wabaVerifyToken(Request $request): \Illuminate\Http\JsonResponse
    {
        $data    = $request->validate([
            'access_token'    => 'required|string|max:1024',
            'waba_id'         => 'nullable|string|max:64',
            'phone_number_id' => 'nullable|string|max:64',
        ]);
        $token   = trim($data['access_token']);
        $wabaId  = trim((string) ($data['waba_id'] ?? ''));
        $version = (string) \App\Models\SystemSetting::get('waba_graph_api_version', 'v23.0');
        $base    = 'https://graph.facebook.com/' . ltrim($version, '/');
        $http    = fn (string $path, array $q = []) => \Illuminate\Support\Facades\Http::withToken($token)
            ->acceptJson()->timeout(15)->get($base . '/' . ltrim($path, '/'), $q);

        $checks = [];

        // 1. Token validity + scopes + expiry.
        try {
            $dbg     = $http('debug_token', ['input_token' => $token]);
            $d       = (array) ($dbg->json('data') ?? []);
            $valid   = (bool) ($d['is_valid'] ?? false);
            $scopes  = array_values((array) ($d['scopes'] ?? []));
            $expires = (int) ($d['expires_at'] ?? 0);
            $checks[] = [
                'ok'     => $valid,
                'title'  => $valid ? 'Token is valid' : 'Token is INVALID or expired',
                'detail' => $valid
                    ? ('Expires: ' . ($expires === 0 ? 'never (permanent System User token)' : date('Y-m-d H:i', $expires)))
                    : (string) ($dbg->json('error.message') ?? 'Meta could not validate this token.'),
            ];
            // 2. Required scopes present?
            $need     = ['whatsapp_business_messaging', 'whatsapp_business_management'];
            $missing  = array_values(array_diff($need, $scopes));
            $checks[] = [
                'ok'     => empty($missing),
                'title'  => empty($missing) ? 'Required permissions present' : 'Missing permission(s)',
                'detail' => empty($missing)
                    ? implode(', ', $need)
                    : ('Missing: ' . implode(', ', $missing) . '. Regenerate the System User token with these scopes.'),
            ];
        } catch (\Throwable $e) {
            $checks[] = ['ok' => false, 'title' => 'Could not reach Meta', 'detail' => $e->getMessage()];
        }

        // 3 + 4. WABA + templates readable? (catches #10 and #3).
        if ($wabaId !== '') {
            try {
                $w   = $http($wabaId, ['fields' => 'name,currency,timezone_id']);
                $err = (int) ($w->json('error.code') ?? 0);
                $checks[] = [
                    'ok'     => $w->successful(),
                    'title'  => $w->successful() ? 'WABA account readable' : ('WABA NOT readable (Meta error ' . $err . ')'),
                    'detail' => $w->successful()
                        ? ('Name: ' . ($w->json('name') ?? '—'))
                        : ($err === 10
                            ? 'The app is not a Tech/Solution Provider for this WABA, or the WABA was un-shared. Re-share the WABA or reconnect.'
                            : (string) ($w->json('error.message') ?? 'Unknown error.')),
                ];
                $t    = $http($wabaId . '/message_templates', ['limit' => 1]);
                $terr = (int) ($t->json('error.code') ?? 0);
                $checks[] = [
                    'ok'     => $t->successful(),
                    'title'  => $t->successful() ? 'Templates readable' : ('Templates NOT readable (Meta error ' . $terr . ')'),
                    'detail' => $t->successful()
                        ? 'The app can read this WABA\'s message templates.'
                        : ($terr === 3
                            ? 'App is missing the whatsapp_business_management capability (Advanced Access). Request it in Meta App Review.'
                            : (string) ($t->json('error.message') ?? 'Unknown error.')),
                ];
            } catch (\Throwable $e) {
                $checks[] = ['ok' => false, 'title' => 'Could not read the WABA', 'detail' => $e->getMessage()];
            }
        }

        $allOk = !empty($checks) && collect($checks)->every(fn ($c) => $c['ok']);
        return response()->json(['ok' => $allOk, 'checks' => $checks]);
    }

    /**
     * POST /devices/waba/exchange-token — turn a SHORT-LIVED (temporary) token
     * into a long-lived (~60-day) token via Meta's fb_exchange_token. Needs the
     * Meta app's ID + secret. The exchanged token keeps the SAME scopes, so it
     * does NOT add whatsapp_business_management — it only extends the lifetime.
     * (A permanent System User token is still the better production choice; this
     * just rescues a temporary token from expiring tomorrow.)
     */
    public function wabaExchangeToken(Request $request): \Illuminate\Http\JsonResponse
    {
        $data    = $request->validate([
            'access_token' => 'required|string|max:1024',
            'app_id'       => 'required|string|max:64',
            'app_secret'   => 'required|string|max:128',
        ]);
        $version = (string) \App\Models\SystemSetting::get('waba_graph_api_version', 'v23.0');
        $base    = 'https://graph.facebook.com/' . ltrim($version, '/');

        try {
            $resp = \Illuminate\Support\Facades\Http::acceptJson()->timeout(15)
                ->get($base . '/oauth/access_token', [
                    'grant_type'        => 'fb_exchange_token',
                    'client_id'         => trim($data['app_id']),
                    'client_secret'     => trim($data['app_secret']),   // used immediately, never stored/logged
                    'fb_exchange_token' => trim($data['access_token']),
                ]);
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'error' => 'Could not reach Meta: ' . $e->getMessage()]);
        }

        if (!$resp->successful()) {
            return response()->json(['ok' => false, 'error' => (string) ($resp->json('error.message') ?? 'Token exchange failed.')]);
        }
        $long = (string) ($resp->json('access_token') ?? '');
        if ($long === '') {
            return response()->json(['ok' => false, 'error' => 'Meta returned no token.']);
        }
        $secs = (int) ($resp->json('expires_in') ?? 0);   // seconds; 0 = never expires
        return response()->json([
            'ok'    => true,
            'token' => $long,
            'days'  => $secs > 0 ? (int) round($secs / 86400) : null,
            'never' => $secs === 0,
        ]);
    }

    /**
     * POST /devices/waba/connect/embedded — Embedded Signup callback.
     * The Meta JS SDK returns a short-lived code; we exchange for an
     * access token, list the WABAs + phone numbers the user granted,
     * and write one row per phone number.
     */
    public function wabaConnectEmbedded(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'code'             => 'required|string|max:500',
            'waba_id'          => 'nullable|string|max:40',
            'phone_number_id'  => 'nullable|string|max:40',
            'business_id'      => 'nullable|string|max:40',
            // Coexistence onboard (number stays live on the WhatsApp Business
            // app). This flow never calls /register, so there's no migration
            // risk — we capture the flag so the device card can badge it and
            // the inbox can expect smb_message_echoes / history webhooks.
            'coexistence'      => 'nullable|boolean',
        ]);
        $coexistence = (bool) $request->boolean('coexistence');

        $wsId = Auth::user()?->current_workspace_id;
        if (!$wsId) return back()->withErrors(['workspace' => 'No active workspace.']);

        \Log::info('[COEX-EMBED] start', [
            'workspace_id'    => (int) $wsId,
            'coexistence'     => $coexistence,
            'has_code'        => !empty($data['code']),
            'waba_id'         => $data['waba_id'] ?? null,
            'phone_number_id' => $data['phone_number_id'] ?? null,
            'business_id'     => $data['business_id'] ?? null,
        ]);

        // Plan limit — same UNIFIED cap as the manual WABA path (see
        // wabaConnectManual). Embedded Signup writes exactly one row per
        // call, so a fail-fast count here caps it correctly.
        \App\Services\PlanLimitGuard::check(
            Auth::user()->currentWorkspace,
            'device_limit',
            $this->unifiedDeviceCount((int) $wsId),
        );

        $appId     = (string) \App\Models\SystemSetting::get('waba_app_id', '');
        // waba_app_secret is in SystemSetting::ENCRYPTED_KEYS, so get() already
        // returns it decrypted — no second decrypt needed.
        $appSecret = (string) \App\Models\SystemSetting::get('waba_app_secret', '');
        if ($appId === '' || $appSecret === '') {
            return back()->withErrors(['embedded' => 'Embedded Signup is not configured. Ask the platform admin to fill App ID + Secret at /admin/settings/wadesk-message.']);
        }

        $version = (string) \App\Models\SystemSetting::get('waba_graph_api_version', 'v23.0');
        $base    = 'https://graph.facebook.com/' . ltrim($version, '/');

        // OAuth code → access token. The Embedded Signup `code` is an
        // in-window, token-based code (NO browser redirect), so the exchange
        // takes ONLY client_id + client_secret + code — it must NOT include a
        // redirect_uri. Passing one flips Graph into classic-OAuth validation
        // and the call fails (OAuthException 100/191) → no token → "no device
        // connects". Returns a ~60-day long-lived business token directly (no
        // second fb_exchange_token hop on v23.0+).
        try {
            $tok = Http::acceptJson()->timeout(15)->get("{$base}/oauth/access_token", [
                'client_id'     => $appId,
                'client_secret' => $appSecret,
                'code'          => $data['code'],
            ]);
            if (!$tok->successful()) {
                \Log::warning('[COEX-EMBED] token exchange FAILED', [
                    'workspace_id' => (int) $wsId, 'status' => $tok->status(), 'error' => $tok->json('error', []),
                ]);
                return back()->withErrors([
                    'embedded' => 'Meta token exchange failed: ' . $this->wabaErrorMessage($tok->json('error', [])),
                ]);
            }
            $accessToken = (string) $tok->json('access_token');
            $expiresIn   = (int)    ($tok->json('expires_in') ?? 0);
            \Log::info('[COEX-EMBED] token exchange OK', ['workspace_id' => (int) $wsId, 'expires_in' => $expiresIn]);
        } catch (\Throwable $e) {
            \Log::error('[COEX-EMBED] token exchange THREW', ['workspace_id' => (int) $wsId, 'error' => $e->getMessage()]);
            return back()->withErrors(['embedded' => 'Token exchange threw: ' . $e->getMessage()]);
        }

        // Embedded Signup hands us the waba_id (always) + phone_number_id
        // (standard flow only) via the postMessage channel, captured
        // client-side and posted with the form. waba_id is mandatory; the
        // phone number is resolved below when absent (the coexistence case).
        $wabaId = $data['waba_id'] ?? null;
        $pnid   = $data['phone_number_id'] ?? null;
        if (!$wabaId) {
            return back()->withErrors(['embedded' => 'Meta did not return a WhatsApp Business Account. Try again, or use Add WABA → Manual.']);
        }

        // Coexistence onboarding (linking an existing WhatsApp Business APP
        // number) registers the number with Cloud API ASYNCHRONOUSLY, so the
        // phone_number_id is usually NOT in Meta's postMessage at FINISH — only
        // the waba_id is. Resolve the number from the WABA's phone_numbers edge.
        // (Same fallback also rescues a standard flow where the dialog dropped
        // the phone_number_id postMessage.) Prefer the Business-App / SMB_APP
        // number when several exist.
        if (!$pnid) {
            try {
                $list = Http::withToken($accessToken)->acceptJson()->timeout(15)
                    ->get("{$base}/{$wabaId}/phone_numbers", [
                        'fields' => 'id,display_phone_number,platform_type,verified_name',
                    ]);
                $rows = $list->successful() ? (array) ($list->json('data') ?? []) : [];
                // The coexistence (WhatsApp Business app) number is flagged by
                // platform_type. Meta's current docs report "COEXISTENCE";
                // older/parallel surfaces use "SMB_APP" — accept BOTH so the
                // right number is preferred regardless of which Meta returns.
                $coexTypes = ['COEXISTENCE', 'SMB_APP'];
                $pick = null;
                foreach ($rows as $r) {
                    if (in_array($r['platform_type'] ?? '', $coexTypes, true)) { $pick = $r; break; }
                }
                $pick = $pick ?: ($rows[0] ?? null);
                $pnid = $pick['id'] ?? null;
                \Log::info('[COEX-EMBED] resolved phone from WABA', [
                    'workspace_id' => (int) $wsId, 'waba_id' => $wabaId,
                    'list_ok' => $list->successful(), 'count' => count($rows),
                    'picked_pnid' => $pnid, 'picked_platform_type' => $pick['platform_type'] ?? null,
                ]);
                if ($pnid && empty($data['phone_number_id'])) {
                    $coexistence = $coexistence || in_array($pick['platform_type'] ?? '', $coexTypes, true);
                }
            } catch (\Throwable $e) {
                \Log::warning('[COEX-EMBED] phone_numbers lookup threw', ['workspace_id' => (int) $wsId, 'waba' => $wabaId, 'error' => $e->getMessage()]);
            }
        }
        if (!$pnid) {
            \Log::warning('[COEX-EMBED] no phone number on WABA yet', ['workspace_id' => (int) $wsId, 'waba_id' => $wabaId, 'coexistence' => $coexistence]);
            return back()->withErrors(['embedded' => 'Signed in, but no phone number is registered on this WhatsApp Business Account yet. Coexistence registration can take a minute — wait and retry, finish the number step in Meta Business Suite, or use Add WABA → Manual to paste the Phone Number ID.']);
        }

        // Probe the phone number with the new v23.0 field set.
        try {
            $probe = Http::withToken($accessToken)->acceptJson()->timeout(15)
                ->get("{$base}/{$pnid}", [
                    'fields' => 'verified_name,display_phone_number,quality_rating,messaging_limit_tier,'
                              . 'code_verification_status,name_status,account_mode,throughput,'
                              . 'platform_type,is_official_business_account,is_pin_enabled,last_onboarded_time',
                ]);
            $info = $probe->successful() ? $probe->json() : [];
        } catch (\Throwable $e) {
            $info = [];
        }

        // Subscribe to webhooks. ES does NOT auto-subscribe — we have
        // to call this ourselves after the token exchange. Idempotent
        // on Meta's side. Empty JSON body for default-app subscription.
        try {
            $sub = Http::withToken($accessToken)->acceptJson()->timeout(15)
                ->post("{$base}/{$wabaId}/subscribed_apps", []);
            if (!$sub->successful()) {
                $subCode = (int) ($sub->json('error.error_subcode') ?? 0);
                if ($subCode === 1349174) {
                    \Log::warning('[COEX-EMBED] subscribed_apps missing whatsapp_business_management', ['workspace_id' => (int) $wsId, 'waba_id' => $wabaId]);
                    return back()->withErrors([
                        'embedded' => 'Your Meta app is missing the whatsapp_business_management permission. Submit your app for App Review with that scope to enable Embedded Signup for real merchants.',
                    ]);
                }
                \Log::warning('[COEX-EMBED] subscribed_apps failed', ['workspace_id' => (int) $wsId, 'code' => $subCode, 'body' => $sub->body()]);
            } else {
                \Log::info('[COEX-EMBED] subscribed_apps OK', ['workspace_id' => (int) $wsId, 'waba_id' => $wabaId]);
            }
        } catch (\Throwable $e) {
            \Log::warning('[COEX-EMBED] subscribed_apps threw', ['workspace_id' => (int) $wsId, 'error' => $e->getMessage()]);
        }

        // Write the row.
        $existing = \App\Models\WaProviderConfig::query()->forWorkspace($wsId)->where('provider', 'waba')->count();
        $cfg = new \App\Models\WaProviderConfig();
        $cfg->workspace_id   = $wsId;
        $cfg->provider       = 'waba';
        $cfg->status         = \App\Models\WaProviderConfig::STATUS_CONNECTED;
        $cfg->phone_number   = (string) ($info['display_phone_number'] ?? '');
        $cfg->display_label  = (string) ($info['verified_name'] ?? '');
        $cfg->meta_json      = [
            'waba_id'                       => $wabaId,
            'phone_number_id'               => $pnid,
            'business_id'                   => $data['business_id']                   ?? null,
            'verified_name'                 => $info['verified_name']                 ?? null,
            'display_phone_number'          => $info['display_phone_number']          ?? null,
            'quality_rating'                => $info['quality_rating']                ?? null,
            'messaging_limit_tier'          => $info['messaging_limit_tier']          ?? null,
            'code_verification_status'      => $info['code_verification_status']      ?? null,
            'name_status'                   => $info['name_status']                   ?? null,
            'account_mode'                  => $info['account_mode']                  ?? null,
            'throughput'                    => $info['throughput']                    ?? null,
            'platform_type'                 => $info['platform_type']                 ?? null,
            'is_official_business_account'  => $info['is_official_business_account']  ?? null,
            'is_pin_enabled'                => $info['is_pin_enabled']                ?? null,
            'last_onboarded_time'           => $info['last_onboarded_time']           ?? null,
            'token_expires_at'              => $expiresIn > 0 ? now()->addSeconds($expiresIn)->toIso8601String() : null,
            'connected_via'                 => 'embedded',
            // Coexistence: explicit flag from the onboard, OR inferred when
            // Meta reports the number is a Business-App number. platform_type
            // is "COEXISTENCE" per Meta's current docs (older surfaces: "SMB_APP").
            'coexistence'                   => ($coexistence || in_array($info['platform_type'] ?? '', ['COEXISTENCE', 'SMB_APP'], true)) ?: null,
        ];
        $cfg->connected_at   = now();
        $cfg->last_health_at = now();
        $cfg->is_primary     = ($existing === 0);
        $cfg->setCreds(['access_token' => $accessToken]);
        $cfg->save();

        // Register on Cloud API so the first send doesn't fail with (#133010)
        // Account not registered. The registrar SKIPS coexistence numbers on
        // its own (allowCoexistence defaults false) — registering one would
        // migrate it off the WhatsApp Business app — so this is safe to call
        // unconditionally. Best-effort; never blocks the connect.
        app(\App\Services\Waba\WabaNumberRegistrar::class)->register($cfg);

        \Log::info('[COEX-EMBED] CONNECTED', [
            'workspace_id'    => (int) $wsId,
            'config_id'       => $cfg->id,
            'waba_id'         => $wabaId,
            'phone_number_id' => $pnid,
            'phone_number'    => $cfg->phone_number,
            'coexistence'     => (bool) ($cfg->meta_json['coexistence'] ?? false),
            'is_primary'      => $cfg->is_primary,
        ]);

        \App\Support\Audit::log('devices.waba_connect_embedded', [
            'subject_type' => 'wa_provider_config', 'subject_id' => $cfg->id,
            'meta' => ['phone' => $cfg->phone_number, 'waba_id' => $wabaId],
        ]);

        return redirect()->route('user.devices.index')
            ->with('status', 'Connected WABA number "' . ($cfg->phone_number ?: $cfg->display_label) . '".');
    }

    /**
     * Translate Meta's typed Graph-API error envelopes into actionable
     * user-facing copy. Returns a single string; the caller wraps it
     * in a validation error so it surfaces inline on the form.
     *
     * Codes per Meta docs (May 2026):
     *   190           — token expired or invalid
     *   190/463       — token expired
     *   190/467       — invalid session
     *   190/460       — password changed (must re-auth)
     *   200/1349174   — missing whatsapp_business_management permission
     *   100           — bad parameter (often redirect_uri mismatch on OAuth)
     */
    private function wabaErrorMessage(array $err): string
    {
        $code     = (int) ($err['code']          ?? 0);
        $subcode  = (int) ($err['error_subcode'] ?? 0);
        $message  = (string) ($err['message']    ?? 'Unknown Meta error.');

        if ($code === 190) {
            return match ($subcode) {
                463     => 'Your Meta token has expired. Generate a new permanent System User token and paste it.',
                467     => 'Meta session is invalid. Re-authorize the app.',
                460     => 'The Facebook password for this account changed — re-authorize the WABA.',
                default => 'Meta rejected the token: ' . $message,
            };
        }
        if ($code === 200 && $subcode === 1349174) {
            return 'Your Meta token is missing the whatsapp_business_management permission. Regenerate the System User token with both whatsapp_business_messaging and whatsapp_business_management scopes.';
        }
        if ($code === 100) {
            // redirect_uri mismatch or unknown field — most common bad-param case
            return 'Meta returned "bad parameter": ' . $message . '. If this is the Embedded Signup callback, check the OAuth redirect_uri matches what is registered in your Meta app.';
        }
        return 'Meta error ' . $code . ($subcode ? '/' . $subcode : '') . ': ' . $message;
    }

    // -----------------------------------------------------------------
    // WABA promote + disconnect
    // -----------------------------------------------------------------

    /** POST /devices/waba/{id}/primary — mark this WABA row as the default sender. */
    public function wabaSetPrimary(int $id): RedirectResponse
    {
        $wsId = Auth::user()?->current_workspace_id;
        $cfg = \App\Models\WaProviderConfig::query()
            ->where('id', $id)->where('workspace_id', $wsId)->where('provider', 'waba')
            ->firstOrFail();
        $cfg->setAsPrimary();
        \App\Support\Audit::log('devices.waba_set_primary', [
            'subject_type' => 'wa_provider_config', 'subject_id' => $cfg->id,
            'meta' => ['phone' => $cfg->phone_number],
        ]);
        return back()->with('status', '"' . ($cfg->display_label ?: $cfg->phone_number ?: 'WABA account') . '" is now the primary sender.');
    }

    /** DELETE /devices/waba/{id}/disconnect — wipe the row's credentials
     *  and mark disconnected. We keep the row so historic logs still
     *  reference it; a clean delete is a separate admin action. */
    public function wabaDisconnect(int $id): RedirectResponse
    {
        $wsId = Auth::user()?->current_workspace_id;
        $cfg = \App\Models\WaProviderConfig::query()
            ->where('id', $id)->where('workspace_id', $wsId)->where('provider', 'waba')
            ->firstOrFail();
        $label = $cfg->display_label ?: $cfg->phone_number ?: 'WABA account';
        $wasPrimary = (bool) $cfg->is_primary;

        $cfg->update([
            'status'           => \App\Models\WaProviderConfig::STATUS_DISCONNECTED,
            'credentials_json' => null,
            'is_primary'       => false,
            'connected_at'     => null,
        ]);

        // If we just demoted the primary, promote the next-connected row
        // so outbound sends keep working without manual re-selection.
        if ($wasPrimary) {
            $next = \App\Models\WaProviderConfig::query()
                ->forWorkspace($wsId)->where('provider', 'waba')
                ->connected()->orderByDesc('connected_at')->orderByDesc('id')->first();
            $next?->setAsPrimary();
        }

        \App\Support\Audit::log('devices.waba_disconnect', [
            'subject_type' => 'wa_provider_config', 'subject_id' => $cfg->id,
            'meta' => ['phone' => $cfg->phone_number],
        ]);
        return back()->with('status', '"' . $label . '" disconnected.');
    }

    /** DELETE /devices/waba/{id}/remove — permanently REMOVE a WABA number
     *  from the workspace: deletes the wa_provider_configs row entirely so it
     *  disappears from the device list (unlike wabaDisconnect, which keeps a
     *  disconnected row). Re-promotes the next connected number if this was
     *  the primary so outbound sends keep working. */
    public function wabaRemove(int $id): RedirectResponse
    {
        $wsId = Auth::user()?->current_workspace_id;
        $cfg = \App\Models\WaProviderConfig::query()
            ->where('id', $id)->where('workspace_id', $wsId)->where('provider', 'waba')
            ->firstOrFail();
        $label      = $cfg->display_label ?: $cfg->phone_number ?: 'WABA account';
        $phone      = $cfg->phone_number;
        $wasPrimary = (bool) $cfg->is_primary;

        $cfg->delete(); // wa_provider_configs is NOT soft-deleted → row is purged

        // If we just removed the primary, promote the next-connected row.
        if ($wasPrimary) {
            $next = \App\Models\WaProviderConfig::query()
                ->forWorkspace($wsId)->where('provider', 'waba')
                ->connected()->orderByDesc('connected_at')->orderByDesc('id')->first();
            $next?->setAsPrimary();
        }

        \App\Support\Audit::log('devices.waba_remove', [
            'subject_type' => 'wa_provider_config', 'subject_id' => $id,
            'meta' => ['phone' => $phone],
        ]);
        return back()->with('status', '"' . $label . '" removed.');
    }

    /** DELETE /devices/twilio/{id}/remove — permanently REMOVE the workspace's
     *  Twilio account: deletes its wa_provider_configs row so it disappears from
     *  the channel list (same purge as wabaRemove — the row is NOT soft-deleted).
     *  A workspace has at most one Twilio row; if it was the primary sender we
     *  promote the next connected Twilio row (none, in practice) and leave the
     *  workspace-engine resolver to fall back to another engine. */
    public function twilioRemove(int $id): RedirectResponse
    {
        $wsId = Auth::user()?->current_workspace_id;
        $cfg = \App\Models\WaProviderConfig::query()
            ->where('id', $id)->where('workspace_id', $wsId)->where('provider', 'twilio')
            ->firstOrFail();
        $label      = $cfg->display_label ?: $cfg->phone_number ?: 'Twilio account';
        $phone      = $cfg->phone_number;
        $wasPrimary = (bool) $cfg->is_primary;

        $cfg->delete(); // wa_provider_configs is NOT soft-deleted → row is purged

        if ($wasPrimary) {
            $next = \App\Models\WaProviderConfig::query()
                ->forWorkspace($wsId)->where('provider', 'twilio')
                ->connected()->orderByDesc('connected_at')->orderByDesc('id')->first();
            $next?->setAsPrimary();
        }

        \App\Support\Audit::log('devices.twilio_remove', [
            'subject_type' => 'wa_provider_config', 'subject_id' => $id,
            'meta' => ['phone' => $phone],
        ]);
        return back()->with('status', '"' . $label . '" removed.');
    }

    /**
     * POST /devices/waba/{id}/resubscribe — re-subscribe THIS number to the
     * platform's Meta (Tech Provider) app for webhooks, reusing the already-stored
     * token. Recovery for a connected number that isn't receiving inbound (the
     * original subscribe failed, or it was connected before this fix). Plain
     * subscribe (empty body), EXACTLY like connect / Embedded Signup — inbound
     * flows to the app's configured Callback URL, so the user never configures a
     * webhook. Idempotent on Meta's side.
     */
    /**
     * "Sync chat history" — manual fallback for the Coexistence 6-month history
     * import when it didn't arrive automatically.
     *
     * IMPORTANT: Meta's Cloud API has NO endpoint to PULL past messages. Chat
     * history only ever arrives as a ONE-TIME PUSH (the `history` webhook) that
     * Meta sends after a Coexistence onboard. The only lever we have is to
     * RE-SUBSCRIBE the app to the WABA — for Coexistence numbers that re-triggers
     * Meta's state-sync/history push. So this re-subscribes and reports how many
     * historical messages have landed so far. It does nothing for a plain Cloud
     * API number (there is no history to send).
     */
    public function wabaSyncHistory(int $id): RedirectResponse
    {
        $cfg    = $this->resolveWabaConfig($id);
        $meta   = is_array($cfg->meta_json) ? $cfg->meta_json : [];
        $wabaId = (string) ($meta['waba_id'] ?? '');
        $token  = (string) ($cfg->creds()['access_token'] ?? '');

        if ($wabaId === '' || $token === '') {
            return back()->withErrors(['waba' => 'This number has no stored WABA id / token — reconnect it once, then try again.']);
        }

        $version = (string) \App\Models\SystemSetting::get('waba_graph_api_version', 'v23.0');
        $base    = 'https://graph.facebook.com/' . ltrim($version, '/');

        try {
            // Meta rejects an override_callback_uri unless the app is ALREADY
            // subscribed to this WABA — (#100) "Before override the current
            // callback uri, your app must be subscribed...". So subscribe
            // PLAINLY first (empty body), THEN override the callback.
            Http::withToken($token)->acceptJson()->timeout(15)
                ->post("{$base}/{$wabaId}/subscribed_apps", []);

            // Re-subscribe the app — the only action that can prompt Meta to
            // (re)push the Coexistence state-sync + history for this WABA.
            $sub = Http::withToken($token)->acceptJson()->timeout(15)
                ->post("{$base}/{$wabaId}/subscribed_apps", [
                    'override_callback_uri' => url('/webhooks/whatsapp/inbound'),
                    'verify_token'          => $this->wabaWebhookVerifyToken(),
                ]);
            if (! $sub->successful()) {
                \Log::warning('[WABA-history-sync] resubscribe failed', ['config_id' => $cfg->id, 'body' => $sub->body()]);
                return back()->withErrors(['waba' => $this->wabaErrorMessage((array) $sub->json('error', [])) ?: 'Meta rejected the request.']);
            }
        } catch (\Throwable $e) {
            \Log::warning('[WABA-history-sync] threw', ['config_id' => $cfg->id, 'error' => $e->getMessage()]);
            return back()->withErrors(['waba' => 'Could not reach Meta: ' . $e->getMessage()]);
        }

        // Count history-synced messages already imported for this workspace so the
        // operator sees whether any history actually arrived.
        $imported = 0;
        try {
            $imported = (int) \App\Models\InboxMessage::query()
                ->whereJsonContains('meta->history_sync', true)
                ->whereHas('conversation', fn ($q) => $q->where('workspace_id', (int) $cfg->workspace_id))
                ->count();
        } catch (\Throwable $e) { /* best-effort count */ }

        \Log::info('[WABA-history-sync] requested', ['config_id' => $cfg->id, 'ws' => $cfg->workspace_id, 'imported_so_far' => $imported]);

        return back()->with('status',
            $imported > 0
                ? "Requested a fresh history sync from Meta. {$imported} historical message(s) already imported into the Team Inbox — for Coexistence numbers Meta pushes more over the next few minutes."
                : 'Requested a history sync from Meta. For a Coexistence number the past chats push over the next few minutes and appear in the Team Inbox. A standard Cloud API number has no history to sync.'
        );
    }

    public function wabaResubscribe(int $id): RedirectResponse
    {
        $cfg    = $this->resolveWabaConfig($id);
        $meta   = is_array($cfg->meta_json) ? $cfg->meta_json : [];
        $wabaId = (string) ($meta['waba_id'] ?? '');
        $token  = (string) ($cfg->creds()['access_token'] ?? '');
        $label  = $cfg->display_label ?: $cfg->phone_number ?: 'WABA account';

        if ($wabaId === '' || $token === '') {
            return back()->withErrors(['waba' => 'This number has no stored WABA id / token — reconnect it once from "Add / Manage" to restore its credentials, then re-subscribe.']);
        }

        $version   = (string) \App\Models\SystemSetting::get('waba_graph_api_version', 'v23.0');
        $base      = 'https://graph.facebook.com/' . ltrim($version, '/');
        $verifyTok = $this->wabaWebhookVerifyToken();

        try {
            // Meta rejects an override_callback_uri unless the app is ALREADY
            // subscribed to this WABA — (#100) "Before override the current
            // callback uri, your app must be subscribed...". So subscribe
            // PLAINLY first (empty body), THEN override the callback.
            Http::withToken($token)->acceptJson()->timeout(15)
                ->post("{$base}/{$wabaId}/subscribed_apps", []);

            // Re-apply the WABA's alternate callback (override_callback_uri) so its
            // inbound is (re)routed to THIS platform's webhook, reusing the stored
            // token. A successful POST means Meta re-verified our URL and the
            // override is live.
            $sub = Http::withToken($token)->acceptJson()->timeout(15)
                ->post("{$base}/{$wabaId}/subscribed_apps", [
                    'override_callback_uri' => url('/webhooks/whatsapp/inbound'),
                    'verify_token'          => $verifyTok,
                ]);
            if (! $sub->successful()) {
                $subCode = (int) ($sub->json('error.error_subcode') ?? 0);
                $msg = $subCode === 1349174
                    ? 'The stored token is missing the whatsapp_business_management permission — regenerate the System-User token with that scope, then reconnect.'
                    : ($this->wabaErrorMessage((array) $sub->json('error', [])) ?: 'Meta rejected the re-subscribe.');
                \Log::warning('[WABA-resubscribe] failed', ['config_id' => $cfg->id, 'code' => $subCode, 'body' => $sub->body()]);
                return back()->withErrors(['waba' => $msg]);
            }
        } catch (\Throwable $e) {
            \Log::warning('[WABA-resubscribe] threw', ['config_id' => $cfg->id, 'error' => $e->getMessage()]);
            return back()->withErrors(['waba' => 'Could not reach Meta: ' . $e->getMessage()]);
        }

        // Verify our app is now actually among this WABA's subscribers, and stamp
        // the verdict so the card shows a definite Inbound wired ✓ / ✗.
        $verified = $this->verifyInboundWired($cfg);
        $this->stampInboundWired($cfg, $verified);

        \App\Support\Audit::log('devices.waba_resubscribe', [
            'subject_type' => 'wa_provider_config', 'subject_id' => $cfg->id,
            'meta' => ['phone' => $cfg->phone_number, 'waba_id' => $wabaId, 'inbound_wired' => $verified],
        ]);

        if ($verified === false) {
            return back()->with('warning', 'Re-subscribed "' . $label . '", but inbound is still NOT wired — this number is subscribed to a different Meta app. Reconnect it via "Add number" (Embedded Signup) using this platform\'s app.');
        }
        return back()->with('status', 'Re-subscribed "' . $label . '" to inbound webhooks — inbound wired. Send it a test message to confirm.');
    }

    /**
     * Register THIS number on the WhatsApp Cloud API. Fixes sends failing with
     * `(#133010) Account not registered` — a number added via manual paste or
     * the devices-popover embedded flow is marked connected but never
     * registered. Operator-initiated → the confirm dialog IS the explicit
     * consent, so we allow migrating a coexistence number here (unlike the
     * silent auto-register at connect time).
     */
    public function wabaRegister(int $id): RedirectResponse
    {
        $cfg   = $this->resolveWabaConfig($id);
        $label = $cfg->display_label ?: $cfg->phone_number ?: 'WABA account';

        // Optional two-step-verification PIN entered by the operator. A number
        // that already has 2FA set MUST supply its existing PIN — our stored /
        // random PIN is rejected with 133005. Digits only; blank → let the
        // registrar fall back to the stored/random pin.
        $pinRaw = preg_replace('/\D/', '', (string) request('pin'));
        $pin    = $pinRaw !== '' ? $pinRaw : null;

        $result = app(\App\Services\Waba\WabaNumberRegistrar::class)
            ->register($cfg, $pin, allowCoexistence: true);

        if (! $result['ok']) {
            return back()->withErrors(['waba' => 'Could not register "' . $label . '": ' . ($result['error'] ?: 'unknown error')]);
        }

        \App\Support\Audit::log('devices.waba_register', [
            'subject_type' => 'wa_provider_config', 'subject_id' => $cfg->id,
            'meta' => ['phone' => $cfg->phone_number],
        ]);

        return back()->with('status', 'Registered "' . $label . '" on the WhatsApp Cloud API — sending should work now. Send a test message to confirm.');
    }

    /**
     * The webhook verify token Meta echoes on subscription. Auto-provisions one
     * the first time it's needed so the override_callback_uri is NEVER silently
     * skipped for want of a token. Never overwrites an existing value (existing
     * subscriptions already passed their challenge against it).
     */
    private function wabaWebhookVerifyToken(): string
    {
        $tok = (string) \App\Models\SystemSetting::get('waba_webhook_verify_token', '');
        if ($tok === '') {
            $tok = \Illuminate\Support\Str::random(40);
            \App\Models\SystemSetting::set('waba_webhook_verify_token', $tok, 'string', 'Webhook verify token Meta echoes on subscription (auto-generated).');
        }
        return $tok;
    }

    /**
     * Confirm THIS WABA is actually delivering inbound to US: GET its
     * subscribed_apps and check that EITHER its alternate callback (override)
     * points at our inbound URL, OR our own Meta app is a subscriber. Returns
     * true (definitely wired) or null (inconclusive — Meta's GET may omit the
     * override, or the call/token is unavailable). Never returns a hard false:
     * a real failure is already surfaced by the subscribe POST result.
     */
    private function verifyInboundWired(\App\Models\WaProviderConfig $cfg): ?bool
    {
        $ourAppId = (string) \App\Models\SystemSetting::get('waba_app_id', '');
        $ourUrl   = rtrim(url('/webhooks/whatsapp/inbound'), '/');
        $meta     = is_array($cfg->meta_json) ? $cfg->meta_json : [];
        $wabaId   = (string) ($meta['waba_id'] ?? '');
        $token    = (string) ($cfg->creds()['access_token'] ?? '');
        if ($wabaId === '' || $token === '') return null;

        $version = (string) \App\Models\SystemSetting::get('waba_graph_api_version', 'v23.0');
        $base    = 'https://graph.facebook.com/' . ltrim($version, '/');
        try {
            $resp = Http::withToken($token)->acceptJson()->timeout(15)
                ->get("{$base}/{$wabaId}/subscribed_apps");
            if (! $resp->successful()) return null;
            foreach ((array) $resp->json('data', []) as $row) {
                $override = rtrim((string) ($row['override_callback_uri'] ?? ''), '/');
                if ($override !== '' && $override === $ourUrl) return true;
                $id = (string) ($row['whatsapp_business_api_data']['id'] ?? '');
                if ($ourAppId !== '' && $id === $ourAppId) return true;
            }
            return null;   // inconclusive — GET may not expose the override
        } catch (\Throwable $e) {
            \Log::warning('[WABA-inbound-check] threw', ['config_id' => $cfg->id, 'error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Persist the inbound-wired verdict onto meta_json['inbound_wired'] (+ a
     * timestamp) so device cards can show a definite green ✓ / red ✗ without a
     * live Meta call on every page load.
     */
    private function stampInboundWired(\App\Models\WaProviderConfig $cfg, ?bool $wired): void
    {
        $meta = is_array($cfg->meta_json) ? $cfg->meta_json : [];
        $meta['inbound_wired']      = $wired;   // true | false | null
        $meta['inbound_checked_at'] = now()->toIso8601String();
        $cfg->forceFill(['meta_json' => $meta])->save();
    }

    // -----------------------------------------------------------------
    // WABA account health (Meta diagnostics)
    // -----------------------------------------------------------------

    /**
     * GET /devices/waba/{id}/health — full Meta account-health dashboard
     * for one connected WABA number. Pulls everything Meta exposes
     * (number status, quality, messaging limits, account review +
     * business verification, token permissions, webhook subscription,
     * template tally) and surfaces any blocks / errors at the top.
     */
    /**
     * Paste / replace this WABA number's Meta access token directly — a fast
     * remediation when a connect (especially coexistence embedded signup) left
     * the token empty, so "Sync from Meta" fails with "missing access_token".
     * The token is VERIFIED against Meta before it's stored (a bad/expired token
     * is rejected, not silently re-saved), then merged into the config's creds
     * and the number is marked connected — no need to re-run the whole signup.
     */
    public function setWabaToken(int $id): RedirectResponse
    {
        $cfg  = $this->resolveWabaConfig($id);
        $data = request()->validate([
            'access_token' => 'required|string|min:20|max:1024',
        ]);
        $token = trim($data['access_token']);

        $gv   = (string) \App\Models\SystemSetting::get('waba_graph_api_version', 'v23.0');
        $base = 'https://graph.facebook.com/' . ltrim($gv, '/');
        $pnid = (string) (($cfg->meta_json['phone_number_id'] ?? null) ?: ($cfg->creds()['phone_number_id'] ?? ''));

        // Verify the token BEFORE storing. Probe the phone number this config
        // already knows (confirms the token can act for THIS number); fall back
        // to debug_token when no phone_number_id is stored yet.
        try {
            $probe = $pnid !== ''
                ? Http::withToken($token)->acceptJson()->timeout(15)
                    ->get("{$base}/{$pnid}", ['fields' => 'display_phone_number,verified_name'])
                : Http::withToken($token)->acceptJson()->timeout(15)
                    ->get("{$base}/debug_token", ['input_token' => $token]);
            if (! $probe->successful()) {
                return back()->withErrors(['access_token' => 'Meta rejected this token: ' . $this->wabaErrorMessage((array) $probe->json('error', []))]);
            }
        } catch (\Throwable $e) {
            return back()->withErrors(['access_token' => 'Could not reach Meta to verify the token: ' . $e->getMessage()]);
        }

        // Merge into existing creds (keep waba_id / phone_number_id / app_id if
        // present), store, mark connected.
        $creds = $cfg->creds();
        $creds['access_token'] = $token;
        $cfg->setCreds($creds);
        $cfg->status       = \App\Models\WaProviderConfig::STATUS_CONNECTED;
        $cfg->connected_at = $cfg->connected_at ?: now();
        $cfg->save();

        \App\Support\Audit::log('devices.waba_set_token', [
            'subject_type' => 'wa_provider_config', 'subject_id' => $cfg->id,
            'meta' => ['phone' => $cfg->phone_number],
        ]);

        return back()->with('status', 'Access token saved and verified — template sync and sending should work now.');
    }

    /**
     * POST /devices/waba/{id}/token-refresh — renew this number's Meta token
     * IN PLACE, WITHOUT re-running Embedded Signup / reconnecting the device.
     *
     * Uses Meta's fb_exchange_token ({current token} + the app id/secret) to get
     * a fresh long-lived token with the SAME scopes, then saves it over the old
     * one — so the "invalid/expiring token" is fixed with one click and sending
     * keeps working. This succeeds whenever the current token is still usable
     * (even if the health page's debug_token introspection was failing). A
     * genuinely REVOKED token can't be exchanged — only then must the number be
     * reconnected, and we say so instead of failing silently.
     */
    public function wabaRefreshToken(int $id): RedirectResponse
    {
        $cfg   = $this->resolveWabaConfig($id);
        $creds = $cfg->creds();
        $token = (string) ($creds['access_token'] ?? '');
        if ($token === '') {
            return back()->withErrors(['token' => 'No access token stored for this number — paste one via Replace access token, or reconnect.']);
        }

        // App creds: this device's own if present, else the platform Embedded-Signup app.
        $appId     = trim((string) ($creds['app_id'] ?? '')) ?: trim((string) \App\Models\SystemSetting::get('waba_app_id', ''));
        $appSecret = trim((string) ($creds['app_secret'] ?? '')) ?: trim((string) \App\Models\SystemSetting::get('waba_app_secret', ''));
        if ($appId === '' || $appSecret === '') {
            return back()->withErrors(['token' => 'Meta App ID + Secret are not configured (Admin → WhatsApp / WABA settings), so the token can not be renewed automatically. Add them, or paste a fresh token via Replace access token.']);
        }

        $version = (string) \App\Models\SystemSetting::get('waba_graph_api_version', 'v23.0');
        $base    = 'https://graph.facebook.com/' . ltrim($version, '/');

        try {
            $resp = Http::acceptJson()->timeout(15)->get($base . '/oauth/access_token', [
                'grant_type'        => 'fb_exchange_token',
                'client_id'         => $appId,
                'client_secret'     => $appSecret,   // used immediately, never stored/logged
                'fb_exchange_token' => $token,
            ]);
        } catch (\Throwable $e) {
            return back()->withErrors(['token' => 'Could not reach Meta to renew the token: ' . $e->getMessage()]);
        }

        if (! $resp->successful()) {
            $err = (array) $resp->json('error', []);
            return back()->withErrors(['token' =>
                'Meta could not renew this token: ' . $this->wabaErrorMessage($err)
                . ' — if it is expired or revoked you will need to reconnect the number.']);
        }

        $fresh = (string) ($resp->json('access_token') ?? '');
        if ($fresh === '') {
            return back()->withErrors(['token' => 'Meta returned no token — try Replace access token, or reconnect the number.']);
        }

        // Save the fresh token IN PLACE — no reconnect; everything keeps working.
        $creds['access_token'] = $fresh;
        $cfg->setCreds($creds);
        $cfg->status       = \App\Models\WaProviderConfig::STATUS_CONNECTED;
        $cfg->connected_at = $cfg->connected_at ?: now();
        $cfg->save();

        $secs = (int) ($resp->json('expires_in') ?? 0);   // 0 = never expires
        $life = $secs > 0 ? ('valid ~' . (int) round($secs / 86400) . ' more days') : 'a long-lived token';

        \App\Support\Audit::log('devices.waba_refresh_token', [
            'subject_type' => 'wa_provider_config', 'subject_id' => $cfg->id,
            'meta' => ['phone' => $cfg->phone_number],
        ]);

        return back()->with('status', 'Token renewed with Meta — ' . $life . '. Nothing else to do; sending keeps working (no reconnect needed).');
    }

    public function wabaHealth(int $id): View
    {
        $cfg    = $this->resolveWabaConfig($id);
        $health = app(\App\Services\WabaHealthService::class)->fetch($cfg);
        $this->persistHealth($cfg, $health);

        $verifiedName = (string) (\Illuminate\Support\Arr::get($health, 'phone.verified_name')
            ?: ($cfg->display_label ?? ($cfg->phone_number ?? '')));

        // The connected number's Meta access token — surfaced on the health page
        // so the operator can read / copy the exact token this number sends with
        // (support + Graph-Explorer debugging). Masked by default in the view.
        $accessToken = (string) ($cfg->creds()['access_token'] ?? '');

        return view('user.devices.waba-health', [
            'waba'                => $cfg,
            'health'              => $health,
            'accessToken'         => $accessToken,
            'usernameSuggestions' => $this->wabaUsernameSuggestions($cfg, $verifiedName),
        ]);
    }

    /**
     * Suggested usernames for the Claim window — mirrors what Gallabox shows:
     *   1. the handle Meta has RESERVED for this number (best-effort read of the
     *      phone-number `username` field), then
     *   2. handles derived from the brand/display name (joined, dotted, underscored).
     * Deduped, validated against Meta's rules, capped at 5. Never throws.
     */
    private function wabaUsernameSuggestions(\App\Models\WaProviderConfig $cfg, string $verifiedName): array
    {
        $out = [];

        // 1. Meta's reserved username (may be pre-reserved from the OBA / Meta
        //    Verified name / linked Facebook Page or Instagram handle).
        $creds = $cfg->creds();
        $token = (string) ($creds['access_token'] ?? '');
        $pnid  = (string) (is_array($cfg->meta_json) ? ($cfg->meta_json['phone_number_id'] ?? '') : '');
        if ($token !== '' && $pnid !== '') {
            $version = (string) \App\Models\SystemSetting::get('waba_graph_api_version', 'v23.0');
            $base    = 'https://graph.facebook.com/' . ltrim($version, '/');
            try {
                $r = \Illuminate\Support\Facades\Http::withToken($token)->timeout(12)->get("{$base}/{$pnid}", ['fields' => 'username']);
                if ($r->successful()) {
                    $u = $r->json('username');
                    $reserved = is_array($u) ? (string) ($u['name'] ?? ($u['username'] ?? '')) : (string) $u;
                    if ($reserved !== '') $out[] = strtolower($reserved);
                }
            } catch (\Throwable $e) { /* best-effort — suggestions must never break the page */ }
        }

        // 2. Brand/display-name derived candidates (like Gallabox generates).
        $name = trim($verifiedName);
        if ($name !== '') {
            $joined = strtolower(preg_replace('/[^a-z0-9]+/i', '', $name));
            $dotted = trim(strtolower(preg_replace('/[^a-z0-9]+/i', '.', $name)), '.');
            $scored = trim(strtolower(preg_replace('/[^a-z0-9]+/i', '_', $name)), '_');
            foreach ([$joined, $dotted, $scored] as $cand) {
                if ($this->isValidWaUsername($cand)) $out[] = $cand;
            }
        }

        return array_values(array_slice(array_unique(array_filter($out)), 0, 5));
    }

    /** True when a handle satisfies Meta's WhatsApp username rules. */
    private function isValidWaUsername(string $u): bool
    {
        return $u !== ''
            && (bool) preg_match('/^[a-z0-9._]{3,24}$/', $u)
            && (bool) preg_match('/[a-z]/', $u)
            && ! preg_match('/^\.|\.$|\.\./', $u)
            && ! str_starts_with($u, 'www')
            && ! preg_match('/\.(com|org|net|in|co|io|me|info|biz|app|dev|xyz|shop|store|online|site)$/', $u);
    }

    /** GET /devices/waba/{id}/health.json — same snapshot for the page's
     *  Re-check button (AJAX), so re-running doesn't reload the chrome. */
    public function wabaHealthJson(int $id): JsonResponse
    {
        $cfg    = $this->resolveWabaConfig($id);
        $health = app(\App\Services\WabaHealthService::class)->fetch($cfg);
        $this->persistHealth($cfg, $health);

        return response()->json($health);
    }

    /**
     * POST /devices/waba/{id}/username — claim/reserve a public WhatsApp Business
     * username (@handle) for this number via the Meta Cloud API. Meta holds it as
     * `reserved` (yellow) until the username feature is live in the region, then
     * flips it to `approved` (green). Validates against Meta's handle rules first
     * so an obviously-bad handle never leaves our server.
     */
    public function claimWabaUsername(\Illuminate\Http\Request $request, int $id)
    {
        $cfg  = $this->resolveWabaConfig($id);
        $data = $request->validate([
            // 3–24 chars, lowercase letters / digits / period / underscore.
            'username' => ['required', 'string', 'min:3', 'max:24', 'regex:/^[a-z0-9._]+$/'],
        ]);
        $username = strtolower(trim((string) $data['username']));

        // Meta's extra rules: must contain a letter; no leading/trailing period or
        // two in a row; not start with "www"; not end with a domain suffix.
        if (! preg_match('/[a-z]/', $username)
            || preg_match('/^\.|\.$|\.\./', $username)
            || str_starts_with($username, 'www')
            || preg_match('/\.(com|org|net|in|co|io|me|info|biz|app|dev|xyz|shop|store|online|site)$/', $username)) {
            return back()->withInput()->withErrors(['username' =>
                __('That handle breaks WhatsApp\'s rules — it must contain a letter, can\'t start/end with a dot or have two dots in a row, can\'t start with "www", and can\'t end like a website (.com, .in, …).')]);
        }

        $creds = $cfg->creds();
        $token = (string) ($creds['access_token'] ?? '');
        $pnid  = (string) (is_array($cfg->meta_json) ? ($cfg->meta_json['phone_number_id'] ?? '') : '');
        if ($token === '' || $pnid === '') {
            return back()->withErrors(['username' => __('This number is missing its Meta token or phone-number id — reconnect it first.')]);
        }

        $version = (string) \App\Models\SystemSetting::get('waba_graph_api_version', 'v23.0');
        $base    = 'https://graph.facebook.com/' . ltrim($version, '/');
        try {
            $resp = \Illuminate\Support\Facades\Http::withToken($token)->asForm()->timeout(20)
                ->post("{$base}/{$pnid}/username", ['username' => $username]);
        } catch (\Throwable $e) {
            \Log::warning('[WABA-USERNAME] claim threw', ['config_id' => $cfg->id, 'error' => $e->getMessage()]);
            return back()->withInput()->withErrors(['username' => __('Could not reach Meta: ') . $e->getMessage()]);
        }

        if (! $resp->successful()) {
            $error = (array) $resp->json('error', []);
            $code  = (int) ($error['code'] ?? 0);
            $rawMsg = strtolower((string) ($error['message'] ?? ''));
            \Log::info('[WABA-USERNAME] claim rejected', ['config_id' => $cfg->id, 'username' => $username, 'code' => $code, 'error' => $error['message'] ?? '']);

            // Code 100 "Object … does not exist / does not support this
            // operation" on the /{phone_id}/username edge is NOT a bad handle
            // and NOT our bug. It means either:
            //   (a) the number isn't registered on the Cloud API yet (same
            //       state that fails sends with 133010) — the /username edge
            //       only exists on a registered number; OR
            //   (b) WhatsApp usernames are a LIMITED-availability Meta feature
            //       this account / region isn't enrolled in yet.
            // The generic code-100 text talks about OAuth redirect_uri, which
            // is irrelevant here — give the operator the two real next steps.
            if ($code === 100
                || str_contains($rawMsg, 'does not support this operation')
                || str_contains($rawMsg, 'does not exist')) {
                return back()->withInput()->withErrors(['username' =>
                    __('WhatsApp couldn\'t set a username for this number. Two things to check: (1) make sure the number is registered on the Cloud API — open its menu and click "Register", then retry; (2) WhatsApp usernames are still rolling out and may not be enabled for this account or region yet. This is a Meta limitation, not a problem with your handle.')]);
            }

            $err = $this->wabaErrorMessage($error);
            return back()->withInput()->withErrors(['username' => $err ?: __('Meta rejected that username.')]);
        }

        // Meta returns success + the granted status (reserved | approved). Persist
        // so the page shows the handle + its badge without another Meta round-trip.
        $status = strtolower((string) ($resp->json('status') ?? $resp->json('username_status') ?? 'reserved'));
        $meta = is_array($cfg->meta_json) ? $cfg->meta_json : [];
        $meta['wa_username']        = $username;
        $meta['wa_username_status'] = in_array($status, ['reserved', 'approved'], true) ? $status : 'reserved';
        $meta['wa_username_at']     = now()->toIso8601String();
        $cfg->forceFill(['meta_json' => $meta])->save();

        \Log::info('[WABA-USERNAME] claimed', ['config_id' => $cfg->id, 'username' => $username, 'status' => $meta['wa_username_status']]);
        return back()->with('status', __('Username @:name claimed — :status.', ['name' => $username, 'status' => $meta['wa_username_status']]));
    }

    /**
     * DELETE /devices/waba/{id}/username — release the number's username on Meta,
     * then clear our stored copy. Tolerant: even if Meta's call fails we clear the
     * local record so the UI is never stuck showing a handle the user removed.
     */
    public function deleteWabaUsername(int $id)
    {
        $cfg   = $this->resolveWabaConfig($id);
        $creds = $cfg->creds();
        $token = (string) ($creds['access_token'] ?? '');
        $pnid  = (string) (is_array($cfg->meta_json) ? ($cfg->meta_json['phone_number_id'] ?? '') : '');

        if ($token !== '' && $pnid !== '') {
            $version = (string) \App\Models\SystemSetting::get('waba_graph_api_version', 'v23.0');
            $base    = 'https://graph.facebook.com/' . ltrim($version, '/');
            try {
                \Illuminate\Support\Facades\Http::withToken($token)->timeout(20)->delete("{$base}/{$pnid}/username");
            } catch (\Throwable $e) {
                \Log::warning('[WABA-USERNAME] delete threw', ['config_id' => $cfg->id, 'error' => $e->getMessage()]);
            }
        }

        $meta = is_array($cfg->meta_json) ? $cfg->meta_json : [];
        unset($meta['wa_username'], $meta['wa_username_status'], $meta['wa_username_at']);
        $cfg->forceFill(['meta_json' => $meta])->save();

        return back()->with('status', __('Username released.'));
    }

    /**
     * Fetch this number's message QR codes (click-to-chat) from Meta. Best-effort:
     * returns [] on any failure so it never breaks the health page render.
     */
    private function fetchWabaQrCodes(\App\Models\WaProviderConfig $cfg): array
    {
        $creds = $cfg->creds();
        $token = (string) ($creds['access_token'] ?? '');
        $pnid  = (string) (is_array($cfg->meta_json) ? ($cfg->meta_json['phone_number_id'] ?? '') : '');
        if ($token === '' || $pnid === '') return [];

        $version = (string) \App\Models\SystemSetting::get('waba_graph_api_version', 'v23.0');
        $base    = 'https://graph.facebook.com/' . ltrim($version, '/');
        try {
            $resp = \Illuminate\Support\Facades\Http::withToken($token)->timeout(15)
                ->get("{$base}/{$pnid}/message_qrdls", ['fields' => 'code,prefilled_message,deep_link_url,qr_image_url']);
            return $resp->successful() ? (array) $resp->json('data', []) : [];
        } catch (\Throwable $e) {
            \Log::warning('[WABA-QR] list threw', ['config_id' => $cfg->id, 'error' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * POST /devices/waba/{id}/qr-codes — create a click-to-chat QR code + short
     * link with a prefilled message. Customers scan it to open a chat with the
     * message pre-typed (deep link wa.me/message/{code}). Max 140 chars, PNG image.
     */
    public function createWabaQrCode(\Illuminate\Http\Request $request, int $id)
    {
        $cfg  = $this->resolveWabaConfig($id);
        $data = $request->validate([
            'prefilled_message' => ['required', 'string', 'max:140'],
        ]);

        $creds = $cfg->creds();
        $token = (string) ($creds['access_token'] ?? '');
        $pnid  = (string) (is_array($cfg->meta_json) ? ($cfg->meta_json['phone_number_id'] ?? '') : '');
        if ($token === '' || $pnid === '') {
            return back()->withErrors(['prefilled_message' => __('This number is missing its Meta token or phone-number id — reconnect it first.')]);
        }

        $version = (string) \App\Models\SystemSetting::get('waba_graph_api_version', 'v23.0');
        $base    = 'https://graph.facebook.com/' . ltrim($version, '/');
        try {
            $resp = \Illuminate\Support\Facades\Http::withToken($token)->asForm()->timeout(20)
                ->post("{$base}/{$pnid}/message_qrdls", [
                    'prefilled_message' => trim((string) $data['prefilled_message']),
                    'generate_qr_image' => 'PNG',
                ]);
        } catch (\Throwable $e) {
            return back()->withInput()->withErrors(['prefilled_message' => __('Could not reach Meta: ') . $e->getMessage()]);
        }
        if (! $resp->successful()) {
            return back()->withInput()->withErrors(['prefilled_message' =>
                $this->wabaErrorMessage((array) $resp->json('error', [])) ?: __('Meta rejected that QR code.')]);
        }

        return back()->with('status', __('QR code created.'));
    }

    /**
     * DELETE /devices/waba/{id}/qr-codes/{code} — remove a QR code from Meta.
     * Tolerant: a Meta error still returns to the page with a message.
     */
    public function deleteWabaQrCode(int $id, string $code)
    {
        $cfg   = $this->resolveWabaConfig($id);
        $creds = $cfg->creds();
        $token = (string) ($creds['access_token'] ?? '');
        $pnid  = (string) (is_array($cfg->meta_json) ? ($cfg->meta_json['phone_number_id'] ?? '') : '');

        if ($token !== '' && $pnid !== '' && $code !== '') {
            $version = (string) \App\Models\SystemSetting::get('waba_graph_api_version', 'v23.0');
            $base    = 'https://graph.facebook.com/' . ltrim($version, '/');
            try {
                \Illuminate\Support\Facades\Http::withToken($token)->timeout(20)
                    ->delete("{$base}/{$pnid}/message_qrdls/" . urlencode($code));
            } catch (\Throwable $e) {
                \Log::warning('[WABA-QR] delete threw', ['config_id' => $cfg->id, 'error' => $e->getMessage()]);
            }
        }

        return back()->with('status', __('QR code deleted.'));
    }

    /** Resolve a WABA row scoped to the caller's workspace (404 otherwise). */
    private function resolveWabaConfig(int $id): \App\Models\WaProviderConfig
    {
        $wsId = Auth::user()?->current_workspace_id;
        return \App\Models\WaProviderConfig::query()
            ->where('id', $id)->where('workspace_id', $wsId)->where('provider', 'waba')
            ->firstOrFail();
    }

    /** Stamp last_health_at + refresh the card's cached number fields so
     *  the /devices card reflects the latest quality / tier / status. */
    private function persistHealth(\App\Models\WaProviderConfig $cfg, array $health): void
    {
        $meta = is_array($cfg->meta_json) ? $cfg->meta_json : [];
        $p    = is_array($health['phone'] ?? null) ? $health['phone'] : [];
        foreach ([
            'verified_name', 'display_phone_number', 'quality_rating', 'messaging_limit_tier',
            'code_verification_status', 'name_status', 'account_mode', 'throughput',
            'platform_type', 'is_official_business_account', 'is_pin_enabled',
        ] as $k) {
            if (array_key_exists($k, $p)) $meta[$k] = $p[$k];
        }
        $cfg->forceFill(['meta_json' => $meta, 'last_health_at' => now()])->save();
    }

    // -----------------------------------------------------------------
    // Pages
    // -----------------------------------------------------------------

    public function index(Request $request)
    {
        $userId = Auth::id();
        $status = $request->string('status')->toString() ?: 'all';
        $region = $request->string('region')->toString() ?: 'all';
        $search = $request->string('q')->toString();

        $devices = Device::query()
            ->forCurrentWorkspace()
            ->withStatus($status)
            ->withRegion($region)
            ->orderByDesc('active')
            ->orderByDesc('id')
            ->get();

        $devices = Device::filterBySearch($devices, $search);
        $devices = $this->paginateCollection($devices, $request, 12);

        // Provider connections (WABA / Baileys / Twilio) live alongside
        // the legacy Device list so /devices is the single hub for
        // every connection a workspace owns. The page renders the
        // provider-connections panel up top, the legacy device table
        // below.
        $u = \Illuminate\Support\Facades\Auth::user();
        $wsId = $u?->current_workspace_id;
        $providerAllowed = \App\Models\SystemSetting::get('allowed_send_methods', ['waba', 'baileys', 'twilio']);
        $providerAllowed = is_array($providerAllowed) ? $providerAllowed : ['waba', 'baileys', 'twilio'];
        $providerConfig  = $wsId ? \App\Models\WaProviderConfig::query()->primaryForWorkspace($wsId)->first() : null;

        // Active engine for THIS workspace — resolved by WorkspaceEngine
        // so per-workspace overrides win over the platform default.
        // Otherwise an operator who connected a Twilio account would
        // still see the Baileys phone list because the platform-wide
        // setting is still `baileys`.
        $activeEngine = \App\Services\WorkspaceEngine::for($wsId);
        // Multi-engine: render a connect section for every engine the workspace is
        // ALLOWED to use (the admin-enabled set), NOT just the already-connected
        // ones — otherwise you could never reach the "Add WABA" / "Connect Twilio"
        // panel to make a first connection. $activeEngine is the one flagged
        // "default". (Single-engine installs get a 1-element set → page unchanged.)
        $enabledEngines = \App\Services\WorkspaceEngine::availableFor($wsId);

        // ALWAYS surface an engine the workspace has actually CONNECTED a
        // provider for, even if the plan's allowed set / enabled_engines subset
        // wouldn't otherwise list it. Without this, a connected Meta (WABA) or
        // Twilio number — and its management + health (account-quality) section
        // — is invisible on /devices, so the operator can't see or manage what
        // they connected. Display-only (sending engines stay gated elsewhere).
        try {
            $connectedProviders = \App\Models\WaProviderConfig::query()
                ->where('workspace_id', $wsId)
                ->whereIn('provider', ['waba', 'twilio'])
                ->where('status', \App\Models\WaProviderConfig::STATUS_CONNECTED)
                ->pluck('provider')->map(fn ($p) => (string) $p)->unique()->all();
            if (!empty($connectedProviders)) {
                $enabledEngines = array_values(array_unique(array_merge($enabledEngines, $connectedProviders)));
            }
        } catch (\Throwable $e) { /* pre-migration / no provider table → leave as-is */ }

        // The status tabs / region filter / KPI cards summarise EVERY engine
        // the workspace runs — the count/totals helpers iterate $enabledEngines
        // and sum each engine's own source (Baileys devices + WABA/Twilio
        // provider configs). Single-engine sums over one engine, so the page
        // is unchanged; a pure-Twilio workspace now shows Twilio numbers
        // instead of leftover Baileys device totals.

        // Multi-WABA — every connected WABA row for this workspace.
        // The view renders one card per row (matches multi-device
        // Baileys pattern). Primary row is ordered first.
        $wabaAccounts = $wsId
            ? \App\Models\WaProviderConfig::query()
                ->primaryForWorkspace($wsId)
                ->where('provider', 'waba')
                ->get()
            : collect();

        // Twilio — exactly one WaProviderConfig row per workspace (a
        // workspace has one Twilio account at a time, not multiple
        // like WABA). When workspace engine is `twilio`, the view
        // renders `_twilio_section` showing either the connect form
        // or the connected account card. Admin defaults shown as a
        // soft hint if no workspace-level creds are saved.
        $twilioAccount = $wsId
            ? \App\Models\WaProviderConfig::query()
                ->where('workspace_id', $wsId)
                ->where('provider', 'twilio')
                ->first()
            : null;
        $twilioAdminDefaults = [
            'account_sid'     => (string) \App\Models\SystemSetting::get('twilio_account_sid', ''),
            'whatsapp_number' => (string) \App\Models\SystemSetting::get('twilio_whatsapp_number', ''),
        ];

        // Instagram (via the linked Instaflow install). $hasInstagram gates the
        // "Add Instagram account" affordance + channel rows; it's true only when
        // an Instaflow connection is configured AND its last handshake succeeded.
        // $instagramAccounts are this workspace's mirror rows (WorkspaceIgAccount).
        // The real IG engine stays on Instaflow — these are display/sender mirrors.
        // Two ways Instagram can be live, and BOTH are honoured (nothing removed):
        //   • Remote Instaflow install (the original bridge) — isConnected(), OR
        //   • the in-place native add-on — admin flips instagram_enabled at
        //     /admin/settings/instagram and connects via /instagram/connect.
        $instagramNative = (bool) \App\Models\SystemSetting::get('instagram_enabled', false);
        $hasInstagram    = \App\Services\Instaflow\InstaflowClient::fromSettings()->isConnected() || $instagramNative;
        if (! $wsId || ! $hasInstagram) {
            $instagramAccounts = collect();
        } elseif ($instagramNative) {
            // Native add-on: rows come from the local instagram_accounts table,
            // shaped like the Instaflow mirror the row partial expects (+ a
            // `native` flag so its actions use the local routes, not Instaflow).
            $instagramAccounts = \App\Models\InstagramAccount::where('workspace_id', $wsId)->orderBy('username')->get()
                ->map(fn ($a) => (object) [
                    'id'                   => $a->id,
                    'native'               => true,
                    'username'             => $a->username,
                    'name'                 => $a->name,
                    'avatar'               => $a->profile_pic_url,
                    'status'               => $a->status,
                    'instaflow_account_id' => $a->ig_user_id,
                    'synced_at'            => null,
                ]);
        } else {
            $instagramAccounts = \App\Models\WorkspaceIgAccount::where('workspace_id', $wsId)->orderBy('username')->get();
        }
        // Deep-link "Manage" opens the account on the Instaflow app.
        $instaflowUrl = rtrim((string) \App\Models\SystemSetting::get('instaflow_url', ''), '/');

        // Connect-flow gate — when admin has set an Embedded Signup
        // Config ID at /admin/settings/wadesk-message, the "Add WABA"
        // button opens the FB JS-SDK iframe; otherwise it opens the
        // manual paste modal.
        $embeddedSignupConfigId = (string) \App\Models\SystemSetting::get('waba_config_id', '');
        $wabaAppId              = (string) \App\Models\SystemSetting::get('waba_app_id', '');
        $embeddedSignupReady    = $embeddedSignupConfigId !== '' && $wabaAppId !== '';

        // Workspace members feed the "Assign to" dropdown in the add
        // device modal. If the user has no workspace yet, the list is
        // empty and the dropdown shows only "you".
        $workspaceMembers = $wsId
            ? (\App\Models\Workspace::find($wsId)?->members()->orderBy('users.name')->get(['users.id', 'users.name', 'users.email']) ?? collect())
            : collect();

        // The status tabs + region rail are filter CONTROLS for the Baileys
        // device table below (which lists Device rows only), so their counts
        // must be scoped to Baileys whenever it's enabled — otherwise "Connected
        // (5)" would filter to 3 visible Baileys rows in a Baileys+WABA workspace.
        // The top KPI cards (totals) roll up the whole workspace, so they span
        // the enabled set. When Baileys isn't enabled the table doesn't render,
        // so the tab counts fall back to the enabled set (unchanged for pure
        // WABA/Twilio). Single-engine is byte-identical either way.
        // Single-engine Baileys → scope the status tabs to the Baileys device
        // table they filter. But the MULTI-engine view (below) renders ONE
        // unified "Connected channels" table spanning EVERY engine, so its
        // counts must span every enabled engine too — otherwise a workspace
        // that connected only a WABA/Twilio number (Baileys merely *allowed*
        // but unused) shows "0" in the rail while the table lists that channel.
        $tableCountEngines = (count($enabledEngines) > 1)
            ? $enabledEngines
            : (in_array(\App\Services\WorkspaceEngine::ENGINE_BAILEYS, $enabledEngines, true)
                ? [\App\Services\WorkspaceEngine::ENGINE_BAILEYS]
                : $enabledEngines);

        // Multi-engine: ONE unified "Connected channels" table lists every
        // connected sender across all enabled engines (Unofficial + WABA +
        // Twilio) — senders() is connected-only and default-first, exactly what
        // the table renders. Empty for single-engine (that page is unchanged).
        // Pass the MERGED engine set (allowed + already-connected providers) so
        // the hub lists every connected channel — a Twilio/WABA number the
        // operator connected shows even if the platform's allowed-send set
        // wouldn't otherwise include that engine. (senders() stays connected-only.)
        $connectedChannels = count($enabledEngines) > 1
            ? \App\Services\WorkspaceEngine::senders($wsId, $enabledEngines)
            : collect();

        $payload = [
            'devices'         => $devices,
            'counts'          => $this->counts($userId, $tableCountEngines),
            'regionCounts'    => $this->regionCounts($userId, $tableCountEngines),
            'totals'          => $this->totals($userId, $enabledEngines),
            // Real WhatsApp-number cap from the workspace plan (the "1 WhatsApp
            // number" the plan advertises = device_limit). null / 0 = unlimited.
            // Drives the footer badge so it stops showing a hardcoded "8".
            'deviceLimit'     => optional(Auth::user()?->currentWorkspace)->effectiveLimit('device_limit', null),
            'currentStatus'   => $status,
            'currentRegion'   => $region,
            'currentSearch'   => $search,
            'providerAllowed' => $providerAllowed,
            'providerConfig'  => $providerConfig,
            'workspaceMembers'=> $workspaceMembers,
            // WABA multi-account context — read in the view to pick
            // between the Baileys phone list and the WABA accounts list.
            'activeEngine'           => $activeEngine,
            'enabledEngines'         => $enabledEngines,
            'connectedChannels'      => $connectedChannels,
            // Tag device rows with their channel name in the unified multi-engine table.
            'channelTag'             => count($enabledEngines) > 1,
            'wabaAccounts'           => $wabaAccounts,
            // config id => inbound-wired verdict (true|false|null) for the card badges.
            'wabaInbound'            => collect($wabaAccounts)->mapWithKeys(function ($w) {
                $m = is_array($w->meta_json ?? null) ? $w->meta_json : [];
                return [(int) $w->id => ($m['inbound_wired'] ?? null)];
            })->all(),
            // Meta's platform for each number. A row can read `connected` while
            // Meta has it on the retired On-Premises API — it then fails every
            // send with 133010, and the green dot is the only thing saying
            // otherwise. Surfaced per-row so an already-connected bad number
            // explains itself instead of looking healthy. Rows connected before
            // the connect-time guard existed are exactly the ones that need it.
            'wabaPlatform'           => collect($wabaAccounts)->mapWithKeys(function ($w) {
                $m = is_array($w->meta_json ?? null) ? $w->meta_json : [];
                return [(int) $w->id => strtoupper((string) ($m['platform_type'] ?? ''))];
            })->all(),
            'embeddedSignupReady'    => $embeddedSignupReady,
            'embeddedSignupConfigId' => $embeddedSignupConfigId,
            'wabaAppId'              => $wabaAppId,
            'twilioAccount'          => $twilioAccount,
            'twilioAdminDefaults'    => $twilioAdminDefaults,
            // Instagram (via linked Instaflow) — chooser card + channel rows.
            'hasInstagram'           => $hasInstagram,
            'instagramAccounts'      => $instagramAccounts,
            'instaflowUrl'           => $instaflowUrl,
        ];

        if ($request->wantsJson() || $request->boolean('partial')) {
            // `cards` REPLACES #devices-list wholesale (user-devices-index.js:
            // list.innerHTML = data.cards), so it must carry every row the
            // container holds — Baileys rows AND the connected WABA/Twilio rows.
            // Sending only _table meant each refresh destroyed the channel rows:
            // the page rendered 3 channels, then silently dropped to 1. Keeping
            // those rows out of the container instead is not an option — card
            // view clones source.children, so anything outside it is invisible
            // there. _table's empty-state stays suppressed in multi-engine or a
            // 0-Baileys workspace shows "No data found" above its live channels.
            $multi = count($enabledEngines) > 1;
            return response()->json([
                'cards'        => view('user.devices._table', array_merge($payload, ['hideEmpty' => $multi]))->render()
                    // Pass the whole payload: the partial also reads wabaInbound /
                    // embeddedSignupReady for its per-row actions, and hand-picking
                    // keys here would break the moment it references another one.
                    . view('user.devices._channel_rows', array_merge($payload, ['multiEngine' => $multi]))->render(),
                'counts'       => $payload['counts'],
                'regionCounts' => $payload['regionCounts'],
                'totals'       => $payload['totals'],
                'pagination'   => view('user.partials.pagination', ['paginator' => $devices, 'dataAttr' => 'data-devices-page', 'label' => 'devices'])->render(),
                // Multi-engine appends connected WABA/Twilio rows OUTSIDE the
                // paginated Baileys table, and linked Instagram accounts are
                // appended too — include both so the footer's "Showing X of Y"
                // (and its show/hide) matches what's actually rendered.
                'shown'        => $devices->count() + $connectedChannels->where('engine', '!=', 'baileys')->count() + $instagramAccounts->count(),
                'total'        => $devices->total() + $connectedChannels->where('engine', '!=', 'baileys')->count() + $instagramAccounts->count(),
                'page'         => $devices->currentPage(),
            ]);
        }

        return view('user.devices.index', $payload);
    }

    // -----------------------------------------------------------------
    // Instagram — link/connect accounts that live on the linked Instaflow
    // -----------------------------------------------------------------

    /**
     * GET /devices/instagram/available — IG accounts connected on Instaflow that
     * this workspace hasn't linked yet, for the "link existing" picker. JSON.
     */
    public function instagramAvailable(): JsonResponse
    {
        $wsId = Auth::user()?->current_workspace_id;
        if (!$wsId) return response()->json(['ok' => false, 'accounts' => []], 422);

        $client = \App\Services\Instaflow\InstaflowClient::fromSettings();
        $linked = \App\Models\WorkspaceIgAccount::where('workspace_id', $wsId)
            ->pluck('instaflow_account_id')->map(fn ($v) => (string) $v)->all();

        // SECURITY: scope the "link existing" list to accounts owned by the SAME
        // person — matched by the logged-in user's email — so we never surface
        // another Instaflow tenant's Instagram accounts. No email → no list
        // (the operator uses "Connect new" instead, which OAuths + links one).
        $ownerEmail = (string) (Auth::user()?->email ?? '');
        $available = [];
        foreach ($client->accounts(null, $ownerEmail) as $a) {
            $id = (string) ($a['id'] ?? '');
            if ($id === '' || in_array($id, $linked, true)) continue;
            $available[] = [
                'id'        => $id,
                'username'  => (string) ($a['username'] ?? ''),
                'name'      => (string) ($a['name'] ?? ''),
                'avatar'    => (string) ($a['avatar'] ?? ''),
                'connected' => (bool) ($a['connected'] ?? false),
            ];
        }

        return response()->json(['ok' => true, 'accounts' => $available]);
    }

    /**
     * POST /devices/instagram/link — upsert a mirror row for an Instaflow IG
     * account into this workspace. Profile details are resolved from Instaflow
     * when not supplied. Content-negotiates: JSON for the modal, redirect-back
     * for a plain form POST (the per-row "Refresh" re-syncs via this same route).
     */
    public function instagramLink(Request $request)
    {
        $wsId = Auth::user()?->current_workspace_id;
        if (!$wsId) {
            return $request->wantsJson()
                ? response()->json(['ok' => false, 'error' => 'No active workspace.'], 422)
                : back()->withErrors(['instagram' => 'No active workspace.']);
        }

        $data = $request->validate([
            'instaflow_account_id' => 'required|string|max:64',
            'username'             => 'nullable|string|max:190',
            'name'                 => 'nullable|string|max:190',
            'avatar'               => 'nullable|string|max:1024',
            'followers'            => 'nullable|integer',
        ]);

        $overrides = array_filter([
            'username'  => $data['username']  ?? null,
            'name'      => $data['name']      ?? null,
            'avatar'    => $data['avatar']    ?? null,
            'followers' => $data['followers'] ?? null,
        ], fn ($v) => $v !== null);

        $row = $this->upsertIgMirror((int) $wsId, (string) $data['instaflow_account_id'], $overrides);
        if (!$row) {
            return $request->wantsJson()
                ? response()->json(['ok' => false, 'error' => 'That Instagram account was not found on ' . ig_brand_name() . '.'], 404)
                : back()->withErrors(['instagram' => 'That Instagram account was not found on ' . ig_brand_name() . '.']);
        }

        return $request->wantsJson()
            ? response()->json(['ok' => true, 'account' => $row])
            : back()->with('status', 'Instagram account @' . $row->handle() . ' updated.');
    }

    /**
     * POST /devices/instagram/connect-start — ask Instaflow for a signed popup
     * ticket to connect a NEW IG account, and return its OAuth URL. JSON {url}.
     */
    public function instagramConnectStart(): JsonResponse
    {
        $wsId = Auth::user()?->current_workspace_id;
        if (!$wsId) return response()->json(['ok' => false, 'error' => 'No active workspace.'], 422);

        $client = \App\Services\Instaflow\InstaflowClient::fromSettings();
        if (!$client->isConnected()) {
            return response()->json(['ok' => false, 'error' => ig_brand_name() . ' is not connected.'], 422);
        }

        $res = $client->connectStart((int) $wsId, route('user.devices.instagram.return'));
        if (empty($res['ok']) || empty($res['url'])) {
            return response()->json(['ok' => false, 'error' => (string) ($res['error'] ?? 'Could not start Instagram connect.')], 502);
        }

        return response()->json(['ok' => true, 'url' => (string) $res['url']]);
    }

    /**
     * GET /devices/instagram/return?ig_account=<id> — the no-opener fallback for
     * the "connect new" popup. Upserts the mirror row for the returned account,
     * then redirects to /devices. (The popup path normally posts a message and
     * the JS calls instagramLink() instead — this is only hit when the popup had
     * no opener, e.g. it was opened in the same tab.)
     */
    public function instagramReturn(Request $request): RedirectResponse
    {
        $wsId      = Auth::user()?->current_workspace_id;
        $accountId = (string) $request->query('ig_account', '');
        if ($wsId && $accountId !== '') {
            $this->upsertIgMirror((int) $wsId, $accountId);
        }
        return redirect()->route('user.devices.index')->with('status', 'Instagram account connected.');
    }

    /**
     * DELETE /devices/instagram/{id}/unlink — disconnect this workspace's link to
     * the Instagram account. The account itself stays connected on Instaflow, but
     * on THIS side we remove the mirror and, once the LAST Instagram account is
     * gone, purge the mirrored inbox data (conversations + messages) so the whole
     * channel disappears from the inbox, dashboard, analytics, flows, templates and
     * auto-reply. Config (templates / auto-reply / flows) is only HIDDEN while no
     * account is connected — reconnecting brings it back — but the transient DM
     * history is deleted outright, which is what "remove all the data" means here.
     */
    public function instagramUnlink(int $id): RedirectResponse
    {
        $wsId = Auth::user()?->current_workspace_id;
        if (!$wsId) return back()->with('status', 'Instagram account unlinked.');

        \DB::transaction(function () use ($wsId, $id) {
            \App\Models\WorkspaceIgAccount::where('workspace_id', $wsId)->where('id', $id)->delete();

            // Only wipe the mirrored inbox once NO Instagram account remains — a
            // workspace can link several handles, and unlinking one must not blow
            // away the others' threads.
            $stillLinked = \App\Models\WorkspaceIgAccount::where('workspace_id', $wsId)->exists();
            if (!$stillLinked) {
                $convoIds = \App\Models\Conversation::where('workspace_id', $wsId)
                    ->where('channel', 'instagram')
                    ->pluck('id');
                if ($convoIds->isNotEmpty()) {
                    \App\Models\InboxMessage::whereIn('conversation_id', $convoIds)->delete();
                    \App\Models\Conversation::whereIn('id', $convoIds)->delete();
                }
            }
        });

        return back()->with('status', 'Instagram account disconnected — its data has been removed.');
    }

    /**
     * Create/refresh a WorkspaceIgAccount mirror. Profile fields come from
     * $overrides first, else from Instaflow's account list (workspace-scoped
     * first — the account is stamped by the time "connect new" returns — then an
     * un-scoped lookup for "link existing"). Returns null when the account can't
     * be resolved on Instaflow and no username override was supplied, so a bogus
     * id can't create an empty mirror.
     */
    private function upsertIgMirror(int $wsId, string $accountId, array $overrides = []): ?\App\Models\WorkspaceIgAccount
    {
        $accountId = trim($accountId);
        if ($accountId === '') return null;

        $client  = \App\Services\Instaflow\InstaflowClient::fromSettings();
        $details = null;
        foreach ($client->accounts($wsId) as $a) {
            if ((string) ($a['id'] ?? '') === $accountId) { $details = $a; break; }
        }
        if ($details === null) {
            foreach ($client->accounts() as $a) {
                if ((string) ($a['id'] ?? '') === $accountId) { $details = $a; break; }
            }
        }

        // Integrity: don't mint a mirror for an id Instaflow doesn't know, unless
        // the caller supplied a username (the picker already had the details).
        if ($details === null && ($overrides['username'] ?? '') === '') {
            return null;
        }
        $details = $details ?? [];

        $attrs = [
            'username'  => $overrides['username'] ?? (string) ($details['username'] ?? ''),
            'name'      => $overrides['name']     ?? (string) ($details['name'] ?? ($details['username'] ?? '')),
            'avatar'    => $overrides['avatar']   ?? (string) ($details['avatar'] ?? ''),
            'status'    => (($details['connected'] ?? true) ? 'connected' : 'disconnected'),
            'synced_at' => now(),
        ];
        if (array_key_exists('followers', $overrides)) {
            $attrs['followers'] = $overrides['followers'];
        }

        return \App\Models\WorkspaceIgAccount::updateOrCreate(
            ['workspace_id' => $wsId, 'instaflow_account_id' => $accountId],
            $attrs
        );
    }

    public function show(int $id, Request $request, \App\Services\UnifiedMessageStream $stream): View
    {
        $device = Device::query()->forCurrentWorkspace()->findOrFail($id);

        // Device-scoped unified stream — every message in/out via this
        // device, across inbox / auto-reply / campaign / scheduled /
        // direct sources. Mirrors what /message-history shows at the
        // workspace level, narrowed to one number.
        $msgSources = (array) $request->input('sources', []);
        $msgSources = array_values(array_intersect(
            $msgSources ?: ['inbox','auto_reply','campaign','scheduled','legacy'],
            ['inbox','auto_reply','campaign','scheduled','legacy']
        ));
        $msgDirection = in_array($request->string('dir')->toString(), ['in','out','fail'], true)
            ? $request->string('dir')->toString() : 'all';
        $msgQ    = trim((string) $request->string('q')->toString());
        $msgPage = max(1, (int) $request->integer('page'));
        $msgPaginator = $stream->paginateForDevice($device->id, [
            'sources'   => $msgSources,
            'direction' => $msgDirection,
            'q'         => $msgQ,
            'page'      => $msgPage,
            'per_page'  => 25,
        ])->appends(array_filter([
            'sources' => $request->input('sources'),
            'dir'     => $msgDirection !== 'all' ? $msgDirection : null,
            'q'       => $msgQ ?: null,
        ]));
        $msgSourceCounts = $stream->countsForDevice($device->id);

        // We aggregate activity from THREE sources tied to this device:
        //   - messages          (chat composer sends) via conversations.device_id
        //   - wp_campaign_contacts (campaign sends)   via wpcampaigns.device_id
        //   - scheduled_messages  (cron-fired sends)  via scheduled_messages.device_id
        // Each source gets normalized into the same shape for the
        // "recent sends" + chart series:
        //   { ts, status, kind: 'chat'|'campaign'|'scheduled', to, body }
        $now    = now();
        $from7d = $now->copy()->subDays(6)->startOfDay();

        // 1) Chat-composer sends.
        $chatRows = \App\Models\Message::query()
            ->select(['id', 'status', 'direction', 'created_at', 'to_number', 'body', 'media_type'])
            ->whereHas('conversation', fn ($q) => $q->where('device_id', $device->id))
            ->where('direction', 'out')
            ->where('created_at', '>=', $from7d)
            ->get()
            ->map(fn ($m) => (object) [
                'kind'       => 'chat',
                'ts'         => $m->created_at,
                'status'     => (string) $m->status,
                'to'         => (string) ($m->to_number ?? ''),
                'body'       => (string) ($m->body ?? ''),
                'media_type' => (string) ($m->media_type ?? ''),
            ]);

        // 2) Campaign sends (via the campaign log).
        //
        // Match by device_id OR by orphaned-campaign-for-this-user.
        // Re-pairing the same phone creates a new devices row, leaving
        // older campaigns pointing at an id that no longer exists. We
        // surface those on the device that currently holds the same
        // phone so the activity isn't lost.
        $currentDeviceIdsForPhone = \App\Models\Device::query()
            ->forCurrentWorkspace()
            ->pluck('id');
        $userId = Auth::id();
        $campaignRows = \App\Models\WpCampaignContact::query()
            ->select(['id', 'campaign_id', 'status', 'sent_at', 'created_at', 'phone_number', 'recipient_name'])
            ->whereHas('campaign', function ($q) use ($device, $currentDeviceIdsForPhone, $userId) {
                $q->where(function ($w) use ($device, $currentDeviceIdsForPhone, $userId) {
                    $w->where('device_id', $device->id)
                      ->orWhere(function ($o) use ($currentDeviceIdsForPhone, $userId) {
                          // Orphaned: campaign points at a device id that
                          // no longer exists in the user's device list.
                          $o->where('created_by', $userId)
                            ->whereNotIn('device_id', $currentDeviceIdsForPhone);
                      });
                });
            })
            ->where('created_at', '>=', $from7d)
            ->get()
            ->map(fn ($r) => (object) [
                'kind'       => 'campaign',
                'ts'         => $r->sent_at ?: $r->created_at,
                'status'     => (string) $r->status,
                'to'         => (string) ($r->phone_number ?? ''),
                'body'       => 'Campaign #' . $r->campaign_id . ($r->recipient_name ? ' → ' . $r->recipient_name : ''),
                'media_type' => '',
            ]);

        // 3) Scheduled sends — only if the table + model exist (legacy).
        $scheduledRows = collect();
        if (\Illuminate\Support\Facades\Schema::hasTable('scheduled_messages')) {
            try {
                $scheduledRows = \DB::table('scheduled_messages')
                    ->where('device_id', $device->id)
                    ->where('created_at', '>=', $from7d)
                    ->select('id', 'status', 'created_at')
                    ->get()
                    ->map(fn ($r) => (object) [
                        'kind'       => 'scheduled',
                        'ts'         => \Carbon\Carbon::parse($r->created_at),
                        'status'     => (string) $r->status,
                        'to'         => '',
                        'body'       => 'Scheduled send #' . $r->id,
                        'media_type' => '',
                    ]);
            } catch (\Throwable $e) {
                $scheduledRows = collect();
            }
        }

        // Cast to a plain Support\Collection — the map() above returns
        // stdClass objects but the chain still inherits Eloquent\Collection,
        // whose merge() calls getKey() on items (which stdClass doesn't have).
        $allRows = collect($chatRows->all())
            ->merge($campaignRows->all())
            ->merge($scheduledRows->all())
            ->sortByDesc('ts')
            ->values();

        // Bucket per-day so the chart series has 7 points (oldest → today).
        $sentSeries = [];
        $failSeries = [];
        for ($i = 6; $i >= 0; $i--) {
            $day = $now->copy()->subDays($i)->startOfDay();
            $end = $day->copy()->endOfDay();
            $bucket = $allRows->filter(fn ($r) => $r->ts && $r->ts >= $day && $r->ts <= $end);
            $sentSeries[] = $bucket->whereNotIn('status', ['failed', 'pending', 'queued', 'scheduled'])->count();
            $failSeries[] = $bucket->where('status', 'failed')->count();
        }

        $sent7d      = array_sum($sentSeries);
        $failed7d    = array_sum($failSeries);
        $delivered7d = max(0, $sent7d - $failed7d);
        $deliveryPct = $sent7d ? round($delivered7d / $sent7d * 100, 1) : 0;

        // 24h slice.
        $last24 = $allRows->filter(fn ($r) => $r->ts && $r->ts >= $now->copy()->subDay());
        $sent24   = $last24->whereNotIn('status', ['failed', 'pending', 'queued', 'scheduled'])->count();
        $failed24 = $last24->where('status', 'failed')->count();

        // For the "Recent sends" feed: top 50 across all sources.
        $recentRows = $allRows->take(50);

        // Per-kind tile counts — quick at-a-glance breakdown.
        $kindCounts = [
            'chat'      => $chatRows->count(),
            'campaign'  => $campaignRows->count(),
            'scheduled' => $scheduledRows->count(),
        ];

        return view('user.devices.detail', compact(
            'device',
            'sentSeries', 'failSeries',
            'sent7d', 'failed7d', 'delivered7d', 'deliveryPct',
            'sent24', 'failed24',
            'recentRows', 'kindCounts',
            'msgPaginator', 'msgSourceCounts', 'msgSources', 'msgDirection', 'msgQ',
        ));
    }

    // -----------------------------------------------------------------
    // Mutations
    // -----------------------------------------------------------------

    public function store(Request $request): RedirectResponse|JsonResponse
    {
        $data = $request->validate([
            'device_name'            => 'required|string|min:2|max:191',
            'country_code'           => 'required|string|max:8',
            'phone_number'           => 'required|string|min:5|max:32',
            'region'                 => 'nullable|string|max:16',
            'assigned_user_id'       => 'nullable|integer|exists:users,id',
            'activate_after_pairing' => 'nullable|boolean',
        ]);

        // Plan limit — block creating a device beyond the package cap.
        // Workspace-scoped: the limit lives on the workspace plan, so
        // the count must be per-workspace too (a user with two
        // workspaces shouldn't hit one's cap while creating in the
        // other). The cap is UNIFIED across engines — a Baileys add is
        // blocked once Baileys + WABA + Twilio numbers together reach the
        // limit, not just when the Baileys count alone does.
        \App\Services\PlanLimitGuard::check(
            $request->user()->currentWorkspace,
            'device_limit',
            $this->unifiedDeviceCount((int) $request->user()->current_workspace_id),
        );

        // Normalise: store country_code as bare digits ("91", not "+91"),
        // and phone_number as the bare local part with the country-code
        // prefix stripped exactly once. Old rows had country code mixed
        // into both columns which made the "build the full E.164" concat
        // double-up whenever the local part didn't share the country
        // code's leading digits (e.g. "7690059356" with cc "+91" came
        // out as "91917690059356" — see /devices issue 2026-05-16).
        [$cc, $local] = $this->splitPhone($data['country_code'], $data['phone_number']);
        $full = $cc . $local;

        // Phone uniqueness in PHP — `phone_number` is encrypted-at-
        // rest so we can't put a unique index on the ciphertext.
        $taken = Device::query()->forCurrentWorkspace()->get(['id', 'phone_number', 'country_code'])
            ->first(fn ($d) => preg_replace('/\D+/', '', $d->country_code . $d->phone_number) === $full);
        if ($taken) {
            if ($request->wantsJson() || $request->ajax()) {
                return response()->json(['ok' => false, 'message' => 'A device with that phone number already exists.', 'existing_id' => $taken->id], 422);
            }
            return back()->withInput()->with('error', 'A device with that phone number already exists.');
        }

        $device = Device::create([
            'user_id'                => Auth::id(),
            'assigned_user_id'       => $data['assigned_user_id'] ?: Auth::id(),
            // Pin to the current workspace so cross-workspace scopes +
            // branding-footer resolution find the right row. Previously
            // omitted — every device landed with workspace_id=NULL and
            // got rescued only via the user_id → workspace fallback in
            // the index query.
            'workspace_id'           => Auth::user()?->current_workspace_id,
            'device_name'            => $data['device_name'],
            'country_code'           => $cc,
            'phone_number'           => $local,
            'region'                 => $data['region'] ?? $this->guessRegion($cc),
            'status'                 => 'disconnected',
            'active'                 => false,
            'activate_after_pairing' => (bool) ($data['activate_after_pairing'] ?? true),
        ]);

        // AJAX caller (the add-device modal) wants the row id back so
        // it can keep the modal open and start the QR loop without a
        // page reload. Browser form-post still gets the redirect.
        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'ok'        => true,
                'device_id' => $device->id,
                'phone'     => preg_replace('/\D+/', '', $full),
                'qr_url'    => route('user.devices.qr-code', $device->id),
                'status_url'=> route('user.devices.connection-status', $device->id),
            ]);
        }

        return redirect()->route('user.devices')->with('status', 'Device added — click Connect on the row to pair it.');
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $d = Device::query()->forCurrentWorkspace()->findOrFail($id);
        $data = $request->validate([
            'device_name'  => 'sometimes|string|min:2|max:191',
            'country_code' => 'sometimes|string|max:8',
            'phone_number' => 'sometimes|string|min:5|max:32',
            'region'       => 'sometimes|nullable|string|max:16',
            'status'       => 'sometimes|in:connected,disconnected,needs_pair,failed',
        ]);
        // Same digits-only normalisation as store() so an edit can't
        // re-introduce the double-country-code bug. Only re-split when
        // both fields were provided (or one of them is); otherwise the
        // existing stored values stay untouched.
        if (isset($data['country_code']) || isset($data['phone_number'])) {
            $cc = $data['country_code'] ?? $d->country_code;
            $pn = $data['phone_number'] ?? $d->phone_number;
            [$data['country_code'], $data['phone_number']] = $this->splitPhone((string) $cc, (string) $pn);
        }
        $d->fill($data);
        if (isset($data['status'])) {
            $d->active = $data['status'] === 'connected';
        }
        $d->save();
        return back()->with('status', 'Device updated.');
    }

    // -----------------------------------------------------------------
    // Per-number proxy / IP isolation
    // -----------------------------------------------------------------

    /** Resolve the current workspace for plan-gate checks. */
    private function currentWorkspace(): ?\App\Models\Workspace
    {
        $wsId = Auth::user()?->current_workspace_id;
        return $wsId ? \App\Models\Workspace::find($wsId) : null;
    }

    /**
     * Unified "connected numbers" count for the device cap. The cap spans
     * ALL engines: Baileys devices (devices table) + WABA/Twilio numbers
     * (wa_provider_configs). So a plan capped at N numbers allows N total
     * across engines — e.g. 1 Baileys OR 1 WABA OR 1 Twilio on a free plan,
     * never one of each. baileys provider_config rows are intentionally NOT
     * counted: a Baileys number is represented by its devices-table row, so
     * counting its config row too would double-count it.
     */
    private function unifiedDeviceCount(int $workspaceId): int
    {
        if ($workspaceId <= 0) return 0;
        // Count via the workspace scope (workspace_id match OR legacy NULL-workspace
        // rows owned by the current user), NOT a strict workspace_id match — devices
        // paired before workspace_id was stamped have NULL and would otherwise be
        // invisible to the cap, letting a capped plan connect unlimited numbers.
        $baileys = Device::query()->forWorkspace($workspaceId, Auth::id())->count();
        $cloud   = \App\Models\WaProviderConfig::query()->forWorkspace($workspaceId)
            ->whereIn('provider', ['waba', 'twilio'])->count();
        return $baileys + $cloud;
    }

    public function destroy(int $id): JsonResponse
    {
        $device = Device::query()->forCurrentWorkspace()->findOrFail($id);

        // The device row is hard-deleted (no FK cascade), which would otherwise
        // leave its conversations orphaned + still showing as live in the team
        // inbox. Close any open threads so they drop out of the active queue —
        // history stays readable, but the dead number can't be replied to.
        \App\Models\Conversation::where('device_id', $device->id)
            ->whereIn('inbox_status', ['open', 'pending', 'snoozed'])
            ->update([
                'inbox_status' => 'closed',
                'resolved_at'  => now(),
            ]);

        $device->delete();
        return response()->json(['data' => ['id' => $id], 'meta' => $this->counts(Auth::id())]);
    }

    /**
     * Toggle connected ↔ disconnected. The old project had a
     * separate updateStatus() endpoint; folding it into one
     * symmetric "toggle" matches the chat / meta-ads patterns.
     */
    public function toggle(int $id): JsonResponse|RedirectResponse
    {
        $d = Device::query()->forCurrentWorkspace()->findOrFail($id);
        $d->active       = !$d->active;
        $d->status       = $d->active ? 'connected' : 'disconnected';
        $d->last_seen_at = $d->active ? now() : $d->last_seen_at;
        $d->save();

        // The device DETAIL page ("Mark connected" / "Refresh session") posts a
        // plain HTML form — the browser would navigate to and render this raw
        // JSON. Redirect back so the page just re-renders with the new status.
        // The /devices index toggles via fetch (Accept: application/json), so
        // those still receive the JSON payload the list re-render needs.
        if (! request()->expectsJson()) {
            return back()->with('status', $d->active ? __('Device marked connected.') : __('Device disconnected.'));
        }

        return response()->json([
            'data' => ['id' => $d->id, 'active' => $d->active, 'status' => $d->status],
            'meta' => $this->counts(Auth::id()),
        ]);
    }

    // -----------------------------------------------------------------
    // Pairing flow — proxies to the Node WhatsApp bridge
    //   D:\wadesk_2806\New folder\public\admin_theme\assets\js\deviceadd.js
    // calls these four endpoints in a loop while the QR / pairing
    // code modal is open. We just forward to env('SERVER_URL') and
    // pass the response through so the front-end keeps working with
    // its existing polling shape: { success, status, progress }.
    // -----------------------------------------------------------------

    /**
     * Generate QR image for the device. Returns whatever the bridge
     * returns — JSON with `qr` data-URL, raw SVG, or a plain image
     * URL — so the JS can keep its multi-shape parser. Local dev
     * (no SERVER_URL) gets a deterministic placeholder data-URL.
     */
    public function qrCode(int $id)
    {
        $d = Device::query()->forCurrentWorkspace()->findOrFail($id);
        $base = $this->resolveNodeUrl();
        $phone = $this->normalisePhone($d->country_code . $d->phone_number);

        if ($base === '') {
            return response()->json([
                'qr'      => $this->demoQrDataUrl($d->id),
                'demo'    => true,
                'message' => 'No Node bridge configured — placeholder QR shown.',
            ]);
        }
        try {
            // Reconnect / regenerate-QR must reach Node in the SAME clean state
            // a brand-new "Add device" hits — i.e. with NO leftover in-memory
            // session for this number. A device that connected before leaves a
            // stale manager (dead socket / old QR) in Node, so reusing it made
            // the QR un-scannable while Add device (no prior manager) worked.
            // For a not-currently-connected device, terminate any stale Node
            // session first; initialize-client then builds a fresh socket + QR.
            // Best-effort — ignore failures so the QR still loads.
            if ($d->status !== 'connected') {
                try {
                    Http::timeout(8)->acceptJson()->withHeaders(['X-Node-Token' => node_token()])->get(rtrim($base, '/') . '/api/terminate-client/' . urlencode($phone));
                } catch (\Throwable $e) {
                }
            }

            // Node's actual endpoint: GET /api/initialize-client/:phoneNumber
            // Returns { qr, message, status: "qr_ready" | "connected" }.
            $res = Http::timeout(15)->acceptJson()->withHeaders(['X-Node-Token' => node_token()])->get(rtrim($base, '/') . '/api/initialize-client/' . urlencode($phone));
            $body = $res->json() ?: [];
            return response()->json([
                'success' => $res->successful(),
                'qr'      => $body['qr'] ?? null,
                'status'  => $body['status'] ?? 'pending',
                'message' => $body['message'] ?? null,
            ], $res->status());
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 502);
        }
    }

    /**
     * Generate the 8-digit pairing code (the alternative to QR).
     * Calls Node's /api/get-pairing-code/:phoneNumber.
     */
    public function pairingCode(int $id): JsonResponse
    {
        $d = Device::query()->forCurrentWorkspace()->findOrFail($id);
        $base = $this->resolveNodeUrl();
        $phone = $this->normalisePhone($d->country_code . $d->phone_number);

        if ($base === '') {
            $seed = abs(crc32('pair-' . $d->id));
            return response()->json([
                'success' => true,
                'code'    => str_pad((string) ($seed % 100000000), 8, '0', STR_PAD_LEFT),
                'demo'    => true,
            ]);
        }
        try {
            $res = Http::timeout(50)->acceptJson()->withHeaders(['X-Node-Token' => node_token()])->get(rtrim($base, '/') . '/api/get-pairing-code/' . urlencode($phone));
            return response()->json($res->json() ?: ['success' => false], $res->status());
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 502);
        }
    }

    /**
     * Poll the connection status for the QR / pairing modal.
     * Calls Node's /api/client-status/:phoneNumber.
     */
    public function connectionStatus(int $id): JsonResponse
    {
        $d = Device::query()->forCurrentWorkspace()->findOrFail($id);
        $base = $this->resolveNodeUrl();
        $phone = $this->normalisePhone($d->country_code . $d->phone_number);

        if ($base === '') {
            return response()->json($this->demoStatusFor($d));
        }
        try {
            $res = Http::timeout(8)->acceptJson()->withHeaders(['X-Node-Token' => node_token()])->get(rtrim($base, '/') . '/api/client-status/' . urlencode($phone));
            $body = $res->json() ?: [];
            $status = $body['status'] ?? 'pending';

            // When Node says "connected", flip our local row so the
            // table re-renders without waiting for the next reload.
            if ($status === 'connected' && $d->status !== 'connected') {
                $d->status = 'connected';
                $d->active = $d->activate_after_pairing ? true : $d->active;
                $d->last_seen_at = now();
                $d->save();
            }

            return response()->json([
                'success'  => $res->successful(),
                'status'   => $status,
                'progress' => $body['isReady'] ?? false ? 100 : ($status === 'qr_ready' ? 30 : 50),
                'qr'       => $body['qr'] ?? null,
                'phone'    => $body['phoneNumber'] ?? $phone,
            ], $res->status());
        } catch (\Throwable $e) {
            $base = wd_node_url();
            return response()->json(['success' => false, 'error' => $e->getMessage()], 502);
        }
    }

    /**
     * Save a device's per-number proxy / IP-isolation settings. Plan-gated by
     * access_proxy_isolation. Creds are encrypted at rest by the Device model.
     * Blank password on an enabled proxy keeps the previously-saved one.
     */
    public function saveProxy(Request $request, int $id): JsonResponse
    {
        \App\Services\PlanLimitGuard::feature(Auth::user()->currentWorkspace, 'access_proxy_isolation');
        $d = Device::query()->forCurrentWorkspace()->findOrFail($id);

        $data = $request->validate([
            'proxy_enabled'  => 'sometimes|boolean',
            'proxy_type'     => 'nullable|in:http,socks5',
            'proxy_host'     => 'nullable|string|max:255',
            'proxy_port'     => 'nullable|integer|min:1|max:65535',
            'proxy_username' => 'nullable|string|max:255',
            'proxy_password' => 'nullable|string|max:255',
        ]);

        $enabled = (bool) ($data['proxy_enabled'] ?? false);
        if ($enabled && (empty($data['proxy_host']) || empty($data['proxy_port']))) {
            return response()->json(['success' => false, 'message' => 'Host and port are required to enable a proxy.'], 422);
        }

        $d->proxy_enabled  = $enabled;
        $d->proxy_type     = $data['proxy_type'] ?? 'http';
        $d->proxy_host     = $data['proxy_host'] ?? null;
        $d->proxy_port     = $data['proxy_port'] ?? null;
        $d->proxy_username = $data['proxy_username'] ?? null;
        if ($request->filled('proxy_password')) {
            $d->proxy_password = $data['proxy_password'];   // changed
        } elseif (!$enabled) {
            $d->proxy_password = null;                       // cleared on disable
        } // else: keep the existing saved password
        if (!$enabled) {
            $d->proxy_status = null;
            $d->proxy_egress_ip = null;
        }
        $d->save();

        \App\Support\Audit::log('devices.proxy_saved', [
            'device_id' => $d->id, 'enabled' => $enabled, 'type' => $d->proxy_type, 'host' => $d->proxy_host,
        ]);
        return response()->json([
            'success' => true,
            'message' => $enabled
                ? 'Proxy saved. Reconnect the number to route it through the proxy IP.'
                : 'Proxy disabled — the number will use the server IP.',
        ]);
    }

    /**
     * Probe a proxy (posted form values, or the saved device proxy) and return
     * the egress IP so the user can verify it BEFORE linking. Plan-gated.
     * Uses cURL's proxy support via the HTTP client (http:// and socks5://).
     */
    public function testProxy(Request $request, int $id): JsonResponse
    {
        \App\Services\PlanLimitGuard::feature(Auth::user()->currentWorkspace, 'access_proxy_isolation');
        $d = Device::query()->forCurrentWorkspace()->findOrFail($id);

        $type = $request->input('proxy_type', $d->proxy_type) ?: 'http';
        $host = $request->input('proxy_host', $d->proxy_host);
        $port = $request->input('proxy_port', $d->proxy_port);
        $user = $request->input('proxy_username', $d->proxy_username);
        $pass = $request->filled('proxy_password') ? $request->input('proxy_password') : $d->proxy_password;

        if (!$host || !$port) {
            return response()->json(['success' => false, 'message' => 'Host and port are required.'], 422);
        }

        $scheme   = $type === 'socks5' ? 'socks5' : 'http';
        $auth     = $user ? rawurlencode((string) $user) . ':' . rawurlencode((string) $pass) . '@' : '';
        $proxyUrl = "{$scheme}://{$auth}{$host}:{$port}";

        try {
            $res = Http::withOptions(['proxy' => $proxyUrl])->timeout(15)
                ->get('https://api.ipify.org', ['format' => 'json']);
            $ip = $res->json('ip');
            if (!$res->successful() || !$ip) {
                throw new \RuntimeException('proxy did not return an IP');
            }
            $d->proxy_status = 'ok';
            $d->proxy_egress_ip = $ip;
            $d->proxy_checked_at = now();
            $d->save();
            return response()->json(['success' => true, 'ip' => $ip, 'message' => "Proxy works — egress IP {$ip}"]);
        } catch (\Throwable $e) {
            $d->proxy_status = 'unreachable';
            $d->proxy_checked_at = now();
            $d->save();
            return response()->json(['success' => false, 'message' => 'Proxy unreachable: ' . $e->getMessage()], 502);
        }
    }

    private function resolveNodeUrl(): string
    {
        $url = (string) \App\Models\SystemSetting::get('baileys_server_url', '');
        if ($url === '') $url = wd_node_url();
        return $url;
    }

    private function normalisePhone(string $raw): string
    {
        return preg_replace('/\D+/', '', $raw);
    }

    /**
     * Split a (country_code, phone_number) pair into bare digits.
     * Handles every pasted shape the form sees in the wild:
     *
     *   cc="+91" pn="+91 7690059356"  → ["91", "7690059356"]
     *   cc="+91" pn="+9176900 59356"  → ["91", "7690059356"]
     *   cc="91"  pn="7690059356"      → ["91", "7690059356"]
     *   cc="91"  pn="917690059356"    → ["91", "7690059356"]
     *
     * The trick: strip non-digits from both sides first, then peel
     * the country code off the local part only if it's a TRUE prefix
     * — never a partial match. The old `ltrim($pn, $cc)` was a
     * character-mask strip (greedy on individual chars), not a
     * string-prefix strip, which is why "+91 7690059356" ended up
     * doubling the country code in the DB.
     */
    private function splitPhone(string $countryCode, string $phoneNumber): array
    {
        $cc    = preg_replace('/\D+/', '', $countryCode) ?: '';
        $local = preg_replace('/\D+/', '', $phoneNumber) ?: '';
        // Only peel off the country code prefix when doing so still
        // leaves at least 9 digits behind — otherwise we'd corrupt
        // legitimate national numbers whose first two digits happen
        // to match the country code (e.g. Indian mobile 9145808988
        // starts with "91" but the whole 10 digits ARE the local
        // part, not "91 + 45808988").
        if ($cc !== '' && str_starts_with($local, $cc) && strlen($local) - strlen($cc) >= 9) {
            $local = substr($local, strlen($cc));
        }
        return [$cc, $local];
    }


    /**
     * Tear down the WhatsApp session for this device on the bridge
     * and flip our local row to disconnected. Mirrors the old
     * `killSession` route. Idempotent — safe to call repeatedly.
     */
    public function killSession(int $id): JsonResponse|RedirectResponse
    {
        $d = Device::query()->forCurrentWorkspace()->findOrFail($id);
        $base = wd_node_url();

        $bridgeOk = false;
        if ($base !== '') {
            // Actually TERMINATE the Baileys socket in Node. The old call hit a
            // non-existent `/kill-session/{db_id}` route (wrong path, keyed by DB
            // id not phone, and no auth header), so the socket stayed alive and
            // the next 10s status poll saw it "connected" and flipped the row
            // back within ~2s — the client's "won't disconnect" report. Use the
            // real handler the QR/reconnect path already uses:
            //   GET /api/terminate-client/{phone}  → clientManager.logout().
            $phone = $this->normalisePhone($d->country_code . $d->phone_number);
            if ($phone !== '') {
                try {
                    $res = Http::timeout(8)->acceptJson()
                        ->withHeaders(['X-Node-Token' => node_token()])
                        ->get(rtrim($base, '/') . '/api/terminate-client/' . urlencode($phone));
                    $bridgeOk = $res->successful();
                } catch (\Throwable $e) {
                    $bridgeOk = false;
                }
            }
        }

        $d->active = false;
        $d->status = 'disconnected';
        $d->save();

        // Same as toggle(): the device DETAIL page's "Disconnect" is a plain form
        // POST, so redirect back instead of dumping raw JSON to the browser. The
        // index / status JS calls this with Accept: application/json → JSON.
        if (! request()->expectsJson()) {
            return back()->with('status', __('Device disconnected.'));
        }

        return response()->json([
            'status'   => 200,
            'success'  => true,
            'bridge'   => $bridgeOk,
            'data'     => ['id' => $d->id, 'active' => false, 'status' => 'disconnected'],
            'meta'     => $this->counts(Auth::id()),
        ]);
    }

    /**
     * Mark the device connected after the bridge reports `Ready`.
     * Mirrors the old `updateDeviceStatus` POST. Accepts `status`
     * 1 (connected) / 0 (disconnected) so deviceadd.js can post
     * the same payload it always did.
     */
    public function updateDeviceStatus(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'status' => 'required|in:0,1',
        ]);
        $d = Device::query()->forCurrentWorkspace()->findOrFail($id);
        $isOn = (int) $data['status'] === 1;
        $d->active = $isOn;
        $d->status = $isOn ? 'connected' : 'disconnected';
        if ($isOn) $d->last_seen_at = now();
        $d->save();
        return response()->json([
            'status'  => 200,
            'success' => true,
            'data'    => ['id' => $d->id, 'active' => $isOn, 'status' => $d->status],
            'meta'    => $this->counts(Auth::id()),
        ]);
    }

    // -----------------------------------------------------------------
    // Demo helpers (no SERVER_URL → deterministic local progression)
    // -----------------------------------------------------------------

    private function demoQrDataUrl(int $id): string
    {
        // Tiny SVG with the device id baked in so each device's QR
        // visibly differs even in the demo path.
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 200 200"><rect width="200" height="200" fill="#FBFAF6"/><rect x="20" y="20" width="40" height="40" fill="#0B1F1C"/><rect x="140" y="20" width="40" height="40" fill="#0B1F1C"/><rect x="20" y="140" width="40" height="40" fill="#0B1F1C"/><text x="100" y="110" text-anchor="middle" font-family="monospace" font-size="14" fill="#0B1F1C">DEMO #' . $id . '</text></svg>';
        return 'data:image/svg+xml;base64,' . base64_encode($svg);
    }

    /**
     * Walk a device through a synthetic Ready-in-12s state machine
     * so the connect modal flows end-to-end without a bridge. Time
     * since the row was last touched drives the step.
     */
    private function demoStatusFor(Device $d): array
    {
        $age = $d->updated_at ? now()->diffInSeconds($d->updated_at) : 0;
        if ($age < 3)  return ['success' => true, 'status' => 'Qr generated',   'progress' => 25,  'demo' => true];
        if ($age < 7)  return ['success' => true, 'status' => 'Syncing data',   'progress' => 60,  'demo' => true];
        if ($age < 12) return ['success' => true, 'status' => 'Syncing data',   'progress' => 85,  'demo' => true];
        return ['success' => true, 'status' => 'Ready', 'progress' => 100, 'demo' => true];
    }

    /**
     * Health-check ping. Mirrors the old `check()` endpoint that
     * hit env('SERVER_URL') and reset stuck devices when the
     * upstream node bridge was down. Env-gated so dev works.
     */
    public function check(): JsonResponse
    {
        $base = $this->resolveNodeUrl();
        if ($base === '') {
            return response()->json([
                'ok'      => false,
                'reason'  => 'SERVER_URL not configured',
                'reset'   => 0,
            ]);
        }

        // GRACE WINDOW — the core of the "device shows disconnected while
        // WhatsApp is actually connected" fix. Baileys legitimately reports
        // isReady=false for a few seconds on EVERY routine transient reconnect
        // (515 post-pair stream-restart, 15s keep-alive miss, brief network
        // blip, WhatsApp-side 500). The Node layer recovers on its own
        // (infinite-retry backoff + 60s health watchdog). So a single
        // not-ready reading must NOT flip a previously-live device offline —
        // we only flip it once it has had NO live heartbeat (last_seen_at)
        // for the whole grace window. 120s comfortably outlasts the Node
        // watchdog's 60s patience plus a couple of reconnect cycles.
        $graceSeconds = 120;
        $cutoff       = now()->subSeconds($graceSeconds);

        // Bridge reachability probe. A single slow/failed probe (Node restart
        // or a momentary hiccup) must not nuke every device — probe twice
        // before declaring the whole bridge down.
        $bridgeUp = false;
        for ($attempt = 0; $attempt < 2; $attempt++) {
            try {
                $res = Http::timeout(5)->get($base);
                if ($res->successful() && (int) ($res->json('status') ?? 0) === 200) {
                    $bridgeUp = true;
                    break;
                }
            } catch (\Throwable $e) {
                // fall through to retry
            }
            if ($attempt === 0) usleep(800_000); // 0.8s before the second probe
        }

        if (!$bridgeUp) {
            // Bridge looks down — but still respect the grace window. A device
            // with a fresh heartbeat is mid-blip, not dead; only flip rows that
            // have been unreachable past the grace window (or never seen).
            $reset = Device::query()
                ->forCurrentWorkspace()
                ->where('active', true)
                ->where(function ($q) use ($cutoff) {
                    $q->whereNull('last_seen_at')->orWhere('last_seen_at', '<', $cutoff);
                })
                ->update(['active' => false, 'status' => 'disconnected']);
            return response()->json(['ok' => false, 'reset' => $reset, 'reason' => 'bridge unreachable']);
        }

        // Bridge is up — ask Node for EACH device's real state. Catches the
        // case where the bridge is alive but the user unlinked the device from
        // their phone's Linked Devices screen (Baileys gets a 401 /
        // stream:error device_removed; client_ready flips to false).
        $devices = Device::query()->forCurrentWorkspace()->get();
        $reset   = 0;
        foreach ($devices as $d) {
            $phone = $this->normalisePhone($d->country_code . $d->phone_number);
            if ($phone === '') continue;
            try {
                $r = Http::timeout(4)->acceptJson()->withHeaders(['X-Node-Token' => node_token()])->get(rtrim($base, '/') . '/api/client-status/' . urlencode($phone));
                if (!$r->successful()) continue;
                $body    = $r->json() ?: [];
                $status  = (string) ($body['status']  ?? '');
                $isReady = (bool)   ($body['isReady'] ?? false);
                $live    = ($status === 'connected' && $isReady);
                // Remember the row's state BEFORE we mutate it, so we can fire a
                // notification only on a real transition (never on every poll).
                $wasConnected = ($d->status === 'connected');

                if ($live) {
                    // Live now — refresh the heartbeat EVERY poll (this drives
                    // the grace clock) and promote the row if it had drifted.
                    $d->forceFill([
                        'status'       => 'connected',
                        'active'       => $d->activate_after_pairing ? true : $d->active,
                        'last_seen_at' => now(),
                    ])->save();
                    // Reconnected after being offline → "Device connected" alert.
                    if (!$wasConnected) {
                        $this->notifyDeviceState($d, true);
                    }
                } elseif ($d->status === 'connected' || $d->active) {
                    // Node reports not-ready. Only flip OFFLINE if the device
                    // has had no live heartbeat for the whole grace window —
                    // otherwise it's a transient blip the Node layer is already
                    // recovering from; leave it connected.
                    $lastSeen = $d->last_seen_at;
                    if (!$lastSeen) {
                        // No heartbeat recorded yet (legacy / just-paired row):
                        // seed the grace clock now instead of flipping on the
                        // first transient miss.
                        $d->forceFill(['last_seen_at' => now()])->save();
                    } elseif ($lastSeen->lt($cutoff)) {
                        $d->forceFill([
                            'status' => 'disconnected',
                            'active' => false,
                        ])->save();
                        $reset++;
                        // Went offline past the grace window → "Device
                        // disconnected" alert (in-app + email if opted in).
                        $this->notifyDeviceState($d, false);
                    }
                    // else: within grace — transient blip, do nothing.
                }
            } catch (\Throwable $e) {
                // Skip this device; don't punish the others.
            }
        }
        return response()->json(['ok' => true, 'reset' => $reset]);
    }

    // -----------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------

    /**
     * Fire the "Device connected" / "Device disconnected" workspace
     * notification. Routed through NotificationHelper so it lands in the
     * in-app bell AND is emailed when the workspace opted that event into the
     * email channel (/settings?tab=notifications) — the reported "notifications
     * not working if account is disconnected/connected" was simply that NOTHING
     * ever raised these events; the settings toggles had no producer.
     *
     * We notify the workspace OWNER (and pass the device's own workspace_id so
     * the pref lookup + bell row are stamped to the right tenant). A per-device
     * per-state cache guard stops a burst of concurrent polls double-sending.
     */
    private function notifyDeviceState(Device $d, bool $connected): void
    {
        try {
            $wsId = (int) ($d->workspace_id ?: 0);
            if ($wsId <= 0) return;

            $event = $connected ? 'device_connected' : 'device_disconnected';
            $guard = "notify_device_state:{$d->id}:{$event}";
            // 5-min window: coalesces the 10s poll bursts / multiple open tabs.
            if (!\Illuminate\Support\Facades\Cache::add($guard, 1, now()->addMinutes(5))) return;

            $ownerId = (int) (\App\Models\Workspace::whereKey($wsId)->value('owner_user_id') ?: 0);
            if ($ownerId <= 0) return;

            $label = trim((string) ($d->device_name ?: $d->phone_number)) ?: ('Device #' . $d->id);
            $title = $connected ? __('Device connected') : __('Device disconnected');
            $msg   = $connected
                ? __(':name is back online and ready to send.', ['name' => $label])
                : __(':name went offline. Reconnect it to keep messages flowing.', ['name' => $label]);

            \App\Helpers\NotificationHelper::toUser($ownerId, $title, $msg, [
                'event'        => $event,
                'category'     => 'device',
                'severity'     => $connected ? 'info' : 'warning',
                'workspace_id' => $wsId,
                'source_type'  => Device::class,
                'source_id'    => $d->id,
                'action_url'   => route('user.devices.index'),
            ]);
        } catch (\Throwable $e) {
            // Never let a notification failure break the status sweep.
            \Illuminate\Support\Facades\Log::warning('[notify][device-state] ' . $e->getMessage());
        }
    }

    /**
     * Resolve the status-bucket counts cards across the ENABLED ENGINE SET.
     * A workspace can run Baileys + WABA + Twilio at once, so we SUM the
     * per-engine result: Baileys counts the devices table; WABA/Twilio each
     * count their own wa_provider_configs rows (one per connected number).
     * Single-engine is byte-identical — the sum over [default] is exactly
     * the old single branch — and a pure-Twilio workspace now correctly
     * sums Twilio instead of falling through to leftover Baileys numbers.
     *
     * @param array<int,string> $engines Enabled engine set (non-empty).
     */
    /**
     * The engine set the /devices status counts must span.
     *
     * Mirrors index()'s $tableCountEngines: a single-engine Baileys workspace
     * scopes to the Baileys device table the tabs filter; every other case spans
     * the enabled set — PLUS any engine the workspace has actually CONNECTED, so
     * a WABA/Twilio number the plan doesn't list still counts.
     */
    private function countEngines(): array
    {
        $wsId    = (int) (Auth::user()?->current_workspace_id ?? 0);
        $engines = \App\Services\WorkspaceEngine::availableFor($wsId);

        try {
            $connected = \App\Models\WaProviderConfig::query()
                ->where('workspace_id', $wsId)
                ->whereIn('provider', ['waba', 'twilio'])
                ->where('status', \App\Models\WaProviderConfig::STATUS_CONNECTED)
                ->pluck('provider')->map(fn ($p) => (string) $p)->unique()->all();
            if (!empty($connected)) {
                $engines = array_values(array_unique(array_merge($engines, $connected)));
            }
        } catch (\Throwable $e) { /* pre-migration / no provider table → leave as-is */ }

        if (count($engines) > 1) return $engines;
        return in_array(\App\Services\WorkspaceEngine::ENGINE_BAILEYS, $engines, true)
            ? [\App\Services\WorkspaceEngine::ENGINE_BAILEYS]
            : $engines;
    }

    /**
     * @param ?array<int,string> $engines Enabled engine set; null → resolve via
     *   countEngines(). This USED to default to the [ENGINE_BAILEYS] literal, so
     *   the JSON endpoints (which pass no engines) silently returned Unofficial-
     *   only totals: a WABA-only workspace rendered its rail correctly on page
     *   load, then every AJAX refresh reset it to 0 while the table still listed
     *   the connected channel. Resolving on null keeps the default honest.
     */
    private function counts(?int $userId, ?array $engines = null): array
    {
        $engines = $engines ?: $this->countEngines();

        $out = [
            'all'          => 0,
            'connected'    => 0,
            'disconnected' => 0,
            'needs_pair'   => 0,
            'failed'       => 0,
        ];
        foreach ($engines as $engine) {
            if ($engine === \App\Services\WorkspaceEngine::ENGINE_WABA
                || $engine === \App\Services\WorkspaceEngine::ENGINE_TWILIO) {
                $rows = \App\Models\WaProviderConfig::query()->forWorkspace(\Illuminate\Support\Facades\Auth::user()?->current_workspace_id)
                    ->where('provider', $engine)
                    ->selectRaw('status, COUNT(*) as c')
                    ->groupBy('status')
                    ->pluck('c', 'status');
                // Only CONNECTED provider senders are rendered in the device list
                // (senders() is connected-only). Disconnected/pending/failed
                // wa_provider_configs rows are kept for later reconnect but never
                // LISTED, so counting them produced phantom totals — e.g. "5
                // devices, 4 disconnected" while the Disconnected tab showed
                // "0 of 0". Count only what is actually shown.
                $connectedCount = (int) ($rows['connected'] ?? 0);
                $out['all']       += $connectedCount;
                $out['connected'] += $connectedCount;
                continue;
            }
            // Baileys — the devices table.
            $rows = Device::query()->forCurrentWorkspace()
                ->selectRaw('status, COUNT(*) as c')
                ->groupBy('status')
                ->pluck('c', 'status');
            $out['all']          += Device::query()->forCurrentWorkspace()->count();
            $out['connected']    += (int) ($rows['connected']    ?? 0);
            $out['disconnected'] += (int) ($rows['disconnected'] ?? 0);
            $out['needs_pair']   += (int) ($rows['needs_pair']   ?? 0);
            $out['failed']       += (int) ($rows['failed']       ?? 0);
        }
        // Native Instagram accounts are listed as connected channels too — count
        // them so the status tabs match the table (they're always "connected").
        $ig = $this->instagramConnectedCount(\Illuminate\Support\Facades\Auth::user()?->current_workspace_id);
        $out['all']       += $ig;
        $out['connected'] += $ig;
        return $out;
    }

    /**
     * Connected Instagram accounts for a workspace — native add-on rows
     * (instagram_accounts) in native mode, else the WorkspaceIgAccount mirror.
     * Shared by counts() + totals() so the KPI cards and status tabs both
     * include the Instagram channel that /devices actually lists.
     */
    private function instagramConnectedCount(?int $wsId): int
    {
        if (! $wsId) return 0;
        try {
            if ((bool) \App\Models\SystemSetting::get('instagram_enabled', false)
                && class_exists(\App\Models\InstagramAccount::class)) {
                return (int) \App\Models\InstagramAccount::where('workspace_id', $wsId)
                    ->where('status', 'connected')->count();
            }
            if (class_exists(\App\Models\WorkspaceIgAccount::class)) {
                return (int) \App\Models\WorkspaceIgAccount::where('workspace_id', $wsId)->count();
            }
        } catch (\Throwable $e) { /* never break the devices page over a count */ }
        return 0;
    }

    /**
     * @param array<int,string> $engines Enabled engine set (non-empty).
     */
    /** @param ?array<int,string> $engines null → countEngines(); see counts(). */
    private function regionCounts(?int $userId, ?array $engines = null): array
    {
        $engines = $engines ?: $this->countEngines();

        // Only Baileys devices carry a region column (no region on
        // wa_provider_configs). Return the Baileys region counts when
        // Baileys is in the enabled set, else [] so the region filter
        // group hides instead of showing stale regions.
        if (!in_array(\App\Services\WorkspaceEngine::ENGINE_BAILEYS, $engines, true)) return [];
        return Device::query()->forCurrentWorkspace()
            ->selectRaw('region, COUNT(*) as c')
            ->whereNotNull('region')
            ->groupBy('region')
            ->pluck('c', 'region')
            ->all();
    }

    /**
     * Resolve the totals/KPI cards (total / connected / 24h sent / 24h
     * failed) across the ENABLED ENGINE SET, summing per engine. Baileys
     * reads the devices table; WABA/Twilio each read their own
     * wa_provider_configs rows plus the 24h Message aggregate filtered to
     * that provider. Single-engine is byte-identical; pure-Twilio now sums
     * Twilio instead of falling through to Baileys.
     *
     * @param array<int,string> $engines Enabled engine set (non-empty).
     */
    private function totals(?int $userId, array $engines = [\App\Services\WorkspaceEngine::ENGINE_BAILEYS]): array
    {
        $out = [
            'total'      => 0,
            'connected'  => 0,
            'sent_24h'   => 0,
            'failed_24h' => 0,
        ];
        foreach ($engines as $engine) {
            if ($engine === \App\Services\WorkspaceEngine::ENGINE_WABA
                || $engine === \App\Services\WorkspaceEngine::ENGINE_TWILIO) {
                $wsId = \Illuminate\Support\Facades\Auth::user()?->current_workspace_id;
                $rows = \App\Models\WaProviderConfig::query()->forWorkspace($wsId)
                    ->where('provider', $engine)->get(['status']);
                // KPI "Total devices" must match the connected-only device LIST —
                // disconnected/ghost provider configs are kept for reconnect but
                // never listed, so counting them showed "Total 5" for 1 real
                // device. Count connected only for the cards; keep $total (all
                // rows) purely as the guard for the 24h message query below.
                $total = $rows->count();
                $connectedTotal = $rows->where('status', 'connected')->count();
                $out['total']     += $connectedTotal;
                $out['connected'] += $connectedTotal;

                // 24h sent/failed — filtered by the `provider` column on
                // messages so each engine sees ONLY messages it actually
                // sent. Falls back to 0 if no accounts exist for this
                // provider (defensive — can't have sent via one you lack).
                if ($total > 0) {
                    $since = now()->subDay();
                    $msg24h = \App\Models\Message::query()
                        ->where('workspace_id', $wsId)
                        ->where('provider', $engine)
                        ->where('created_at', '>=', $since)
                        ->selectRaw("SUM(CASE WHEN status IN ('sent','delivered','read') THEN 1 ELSE 0 END) AS sent, SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) AS failed")
                        ->first();
                    $out['sent_24h']   += (int) ($msg24h->sent   ?? 0);
                    $out['failed_24h'] += (int) ($msg24h->failed ?? 0);
                }
                continue;
            }
            // Baileys — the devices table.
            $items = Device::query()->forCurrentWorkspace()->get(['active', 'sent_24h', 'failed_24h']);
            $out['total']      += $items->count();
            $out['connected']  += $items->where('active', true)->count();
            $out['sent_24h']   += (int) $items->sum('sent_24h');
            $out['failed_24h'] += (int) $items->sum('failed_24h');
        }
        // Native Instagram accounts are connected channels listed on /devices —
        // include them in Total + Connected so the KPI cards + Health % match.
        $ig = $this->instagramConnectedCount(\Illuminate\Support\Facades\Auth::user()?->current_workspace_id);
        $out['total']     += $ig;
        $out['connected'] += $ig;
        return $out;
    }

    private function guessRegion(string $countryCode): string
    {
        return match (preg_replace('/\D+/', '', $countryCode)) {
            '91'  => 'IN',
            '1'   => 'US',
            '44'  => 'GB',
            '971' => 'AE',
            '49'  => 'DE',
            '39'  => 'IT',
            '82'  => 'KR',
            '234' => 'NG',
            default => 'XX',
        };
    }
}
