<?php

namespace App\Http\Controllers;

use App\Enums\WaProvider;
use App\Models\WaProviderConfig;
use App\Models\WaStorefront;
use App\Services\WhatsAppDispatcher;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;

/**
 * /connect/wa-store/* — provider-specific save endpoints used by the
 * connect wizard. Each endpoint validates, persists a
 * wa_provider_configs row, and (for Twilio) does a quick credentials
 * test before marking status='connected'. WABA Embedded Signup has
 * its own callback path so it gets a dedicated controller in Phase 3.
 */
class WaConnectController extends Controller
{
    public function saveTwilio(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'account_sid' => 'nullable|string|max:64',
            'auth_token'  => 'nullable|string|max:128',
            'from_number' => 'nullable|string|max:32',
            'sandbox'     => 'nullable|in:0,1',
        ]);

        $user = Auth::user();
        $workspaceId = $user->current_workspace_id;
        if (!$workspaceId) {
            return back()->withErrors(['from_number' => 'Pick or create a workspace first.']);
        }

        // Fall back to admin defaults when workspace fields are blank.
        $accountSid = $data['account_sid']     ?: (string) \App\Models\SystemSetting::get('twilio_account_sid', '');
        $authToken  = $data['auth_token']      ?: (string) \App\Models\SystemSetting::get('twilio_auth_token', '');
        $fromNumber = $data['from_number']     ?: (string) \App\Models\SystemSetting::get('twilio_whatsapp_number', '');
        $sandbox    = (bool) ($data['sandbox'] ?? false);

        if ($accountSid === '' || $authToken === '' || $fromNumber === '') {
            return back()->withErrors(['account_sid' => 'Account SID, auth token and from-number are all required.']);
        }

        // Cheap creds check: GET /Accounts/{sid}.json — Twilio returns
        // 200 if SID + token match.
        try {
            $res = Http::withBasicAuth($accountSid, $authToken)
                ->timeout(8)
                ->get("https://api.twilio.com/2010-04-01/Accounts/{$accountSid}.json");
            if (!$res->successful()) {
                $msg = $res->json('message') ?? ('HTTP ' . $res->status());
                return back()->withErrors(['account_sid' => 'Twilio rejected those credentials: ' . $msg]);
            }
        } catch (\Throwable $e) {
            return back()->withErrors(['account_sid' => 'Could not reach Twilio: ' . $e->getMessage()]);
        }

        // Multi-engine: key the row on (workspace_id, provider) so connecting
        // Twilio creates/updates ONLY the Twilio row and never clobbers an
        // existing Baileys/WABA row for the same workspace.
        $config = WaProviderConfig::firstOrNew([
            'workspace_id' => $workspaceId,
            'provider'     => WaProvider::Twilio->value,
        ]);

        // Plan limit (#11) — the /connect wizard previously bypassed the
        // device cap entirely (only DevicesController enforced it), so a
        // capped plan could add a 2nd number here. Enforce the UNIFIED cap
        // (Baileys devices + WABA + Twilio numbers), but only when this is a
        // brand-new config — firstOrNew returns the existing row when the
        // workspace switches engines, which is a replacement, not a new number.
        if (! $config->exists) {
            \App\Services\PlanLimitGuard::check(
                $user->currentWorkspace,
                'device_limit',
                \App\Models\Device::query()->forWorkspace($workspaceId, $user->id)->count()
                    + WaProviderConfig::query()->forWorkspace($workspaceId)
                        ->whereIn('provider', ['waba', 'twilio'])->count(),
            );
        }

        $config->fill([
            'provider'      => WaProvider::Twilio->value,
            'status'        => WaProviderConfig::STATUS_CONNECTED,
            'phone_number'  => $fromNumber,
            'display_label' => 'Twilio · ' . ($sandbox ? 'Sandbox' : 'Production'),
            'connected_at'  => now(),
            'is_primary'    => true,
            'meta_json'     => ['sandbox' => $sandbox],
        ]);
        $config->setCreds([
            'account_sid' => $accountSid,
            'auth_token'  => $authToken,
            'from_number' => $fromNumber,
            'sandbox'     => $sandbox,
        ]);
        $config->save();

        // Demote any other provider rows for this workspace so
        // WorkspaceEngine::for() resolves to Twilio immediately. Without
        // this, a workspace that previously had a WABA/Baileys row marked
        // primary would still return its old engine until admin manually
        // flipped the platform default at /admin/settings.
        WaProviderConfig::where('workspace_id', $workspaceId)
            ->where('id', '!=', $config->id)
            ->update(['is_primary' => false]);
        \App\Services\WorkspaceEngine::flush();

        $this->ensureStorefront($workspaceId);
        // Provider changed → flush Node's per-phone settings cache.
        \App\Services\NodeCacheBuster::bustWorkspace($workspaceId);

        // Return to wherever the connect came from. The /devices modal sends a
        // local `return_to=/devices`; the store-onboarding wizard sends nothing
        // → defaults to /store. Only same-app relative paths are honored (no
        // open redirect).
        $returnTo = (string) $request->input('return_to', '');
        $dest = (str_starts_with($returnTo, '/') && !str_starts_with($returnTo, '//')) ? $returnTo : '/devices';
        return redirect($dest)->with('status', 'Twilio connected. You can start sending now.');
    }

    /**
     * Baileys path. The Node bridge uses the phone number itself as
     * the device id (session folder = `baileys_auth/session_<phone>`),
     * so we collect the user's phone here, then call Node's
     * `GET /api/initialize-client/:phoneNumber` to kick off the QR
     * generation. Node will POST back to /api/update-status as the
     * QR / pair flow progresses.
     */
    public function saveBaileys(Request $request): JsonResponse
    {
        $data = $request->validate([
            'phone_number' => 'required|string|min:6|max:32',
            'country_code' => 'nullable|string|max:8',
            'device_name'  => 'nullable|string|max:64',
            'server_url'   => 'nullable|url|max:191',
        ]);

        $user = Auth::user();
        $workspaceId = $user->current_workspace_id;
        if (!$workspaceId) {
            return response()->json(['ok' => false, 'message' => 'No workspace.'], 422);
        }

        $serverUrl = $data['server_url']
            ?: (string) \App\Models\SystemSetting::get('baileys_server_url', env('SERVER_URL', ''));
        if ($serverUrl === '') {
            return response()->json(['ok' => false, 'message' => 'Set the Baileys Node server URL in /admin/settings first.'], 422);
        }

        // Normalise phone — Node expects digits only, no country code prefix
        // mixed with the national number (it builds session_<exact phone>).
        $cc    = preg_replace('/\D+/', '', (string) ($data['country_code'] ?? ''));
        $local = preg_replace('/\D+/', '', $data['phone_number']);
        $phone = $cc !== '' && !str_starts_with($local, $cc) ? $cc . $local : $local;

        // Multi-engine: key on (workspace_id, provider) so the Baileys row is
        // independent of any WABA/Twilio row for the same workspace.
        $config = WaProviderConfig::firstOrNew([
            'workspace_id' => $workspaceId,
            'provider'     => WaProvider::Baileys->value,
        ]);

        // Plan limit (#11) — same UNIFIED device cap as the Twilio wizard
        // path above; the /connect Baileys wizard also bypassed it. Only
        // enforce for a brand-new config (engine switch reuses the existing
        // row and must not be blocked).
        if (! $config->exists) {
            \App\Services\PlanLimitGuard::check(
                $user->currentWorkspace,
                'device_limit',
                \App\Models\Device::query()->forWorkspace($workspaceId, $user->id)->count()
                    + WaProviderConfig::query()->forWorkspace($workspaceId)
                        ->whereIn('provider', ['waba', 'twilio'])->count(),
            );
        }

        $config->fill([
            'provider'      => WaProvider::Baileys->value,
            'status'        => WaProviderConfig::STATUS_PENDING,
            'phone_number'  => $phone,
            'display_label' => $data['device_name'] ?: ('Baileys · ' . $phone),
            'is_primary'    => true,
            'meta_json'     => array_merge((array) ($config->meta_json ?? []), [
                'server_url' => $serverUrl,
                'phone'      => $phone,
            ]),
        ]);
        $config->setCreds([
            'server_url'   => $serverUrl,
            'phone_number' => $phone,
            'qr_data'      => null,
        ]);
        $config->save();

        // Demote any other provider rows for this workspace so
        // WorkspaceEngine::for() resolves to Baileys immediately. Same
        // reasoning as the Twilio save above — a previous WABA/Twilio
        // row marked primary would otherwise keep the old engine sticky.
        WaProviderConfig::where('workspace_id', $workspaceId)
            ->where('id', '!=', $config->id)
            ->update(['is_primary' => false]);
        \App\Services\WorkspaceEngine::flush();

        // Baileys provider just connected → flush Node's settings cache
        // so the next send picks up the new use_facebook_api state.
        \App\Services\NodeCacheBuster::bustWorkspace($workspaceId);

        // Kick off Node's QR flow. The endpoint is GET (Node's design).
        // Response is either { qr, status: 'qr_ready' } or { status: 'connected' }.
        try {
            $res = \Illuminate\Support\Facades\Http::timeout(15)
                ->acceptJson()
                ->get(rtrim($serverUrl, '/') . '/api/initialize-client/' . urlencode($phone));

            if ($res->successful()) {
                $body = $res->json();
                $qr = $body['qr'] ?? null;
                $status = $body['status'] ?? 'qr_ready';

                // Persist whatever Node gave us — frontend polls config
                // status to drive the UI.
                $creds = $config->creds();
                $creds['qr_data'] = $qr;
                $config->setCreds($creds);
                if ($status === 'connected') {
                    $config->status = WaProviderConfig::STATUS_CONNECTED;
                    $config->connected_at = now();
                }
                $config->save();
            }
        } catch (\Throwable $e) {
            return response()->json([
                'ok'        => false,
                'config_id' => $config->id,
                'message'   => 'Could not reach Node bridge: ' . $e->getMessage(),
            ], 200);
        }

        $this->ensureStorefront($workspaceId);

        return response()->json([
            'ok'             => true,
            'config_id'      => $config->id,
            'phone_number'   => $phone,
            'qr_poll_url'    => route('baileys.qr.poll', ['configId' => $config->id]),
            'status_poll_url'=> route('baileys.status.poll', ['configId' => $config->id]),
        ]);
    }

    public function disconnect(): \Illuminate\Http\RedirectResponse
    {
        $u = Auth::user();
        $cfg = WaProviderConfig::query()->primaryForWorkspace($u->current_workspace_id)->first();
        if (!$cfg) {
            return back()->with('status', 'No active connection.');
        }

        // For Baileys, ask Node to terminate the session so the next
        // /connect attempt can re-pair cleanly.
        if ($cfg->provider === 'baileys') {
            $creds = $cfg->creds();
            $serverUrl = $creds['server_url'] ?? '';
            $phone = $cfg->phone_number ?: ($creds['phone_number'] ?? '');
            if ($serverUrl && $phone) {
                try {
                    \Illuminate\Support\Facades\Http::timeout(8)
                        ->get(rtrim($serverUrl, '/') . '/api/terminate-client/' . urlencode($phone));
                } catch (\Throwable $e) { /* swallow — local row still gets cleared */ }
            }
        }

        $cfg->status = WaProviderConfig::STATUS_DISCONNECTED;
        $cfg->save();
        return back()->with('status', 'Disconnected. Reconnect any time from this page.');
    }

    /**
     * Frontend polls this every 2s. Proxies to Node's
     * GET /api/client-status/:phoneNumber so we don't have to wait for
     * Node's POST callback to update our row — pull-mode is more
     * reliable on flaky local networks.
     */
    public function pollQr(int $configId): JsonResponse
    {
        $user = Auth::user();
        $cfg = WaProviderConfig::query()
            ->where('id', $configId)
            ->where('workspace_id', $user->current_workspace_id)
            ->firstOrFail();

        $creds = $cfg->creds();
        $serverUrl = $creds['server_url'] ?? '';
        $phone = $cfg->phone_number ?: ($creds['phone_number'] ?? '');

        if ($serverUrl !== '' && $phone !== '') {
            try {
                $res = \Illuminate\Support\Facades\Http::timeout(6)
                    ->acceptJson()
                    ->get(rtrim($serverUrl, '/') . '/api/client-status/' . urlencode($phone));
                if ($res->successful()) {
                    $body = $res->json();
                    $status = $body['status'] ?? null;
                    $qr = $body['qr'] ?? null;
                    $creds['qr_data'] = $qr;
                    $cfg->setCreds($creds);
                    if ($status === 'connected') {
                        $cfg->status = WaProviderConfig::STATUS_CONNECTED;
                        if (!$cfg->connected_at) $cfg->connected_at = now();
                    }
                    $cfg->last_health_at = now();
                    $cfg->save();
                }
            } catch (\Throwable $e) { /* swallow — return cached state */ }
        }

        $cfg->refresh();
        $creds = $cfg->creds();
        return response()->json([
            'ok'      => true,
            'qr_data' => $creds['qr_data'] ?? null,
            'status'  => $cfg->status,
            'paired'  => $cfg->isConnected(),
            'phone'   => $cfg->phone_number,
        ]);
    }

    public function pollStatus(int $configId): JsonResponse
    {
        // Same as pollQr but returns only status — kept as a separate
        // endpoint for clarity and so JS can poll it after pairing
        // without re-fetching QR.
        return $this->pollQr($configId);
    }

    /**
     * POST /api/update-status — called by the Node bridge with one of:
     *   { status: 'qr_ready',  wid: phone, qr: <base64> }
     *   { status: 'code_sent', wid: phone, qr: <code> }
     *   { status: 'paired',    wid: phone }
     *   { status: 'connected', wid: phone, progress: 100 }
     *   { status: 'disconnected', wid: phone }
     *
     * Node already authenticates this with X-Node-Token; we double-check
     * the shared secret matches what's stored in system_settings.
     */
    public function nodeStatusCallback(Request $request): JsonResponse
    {
        // Refuse when token isn't configured — empty token must NOT
        // be a free pass for an unauthenticated callback.
        $expected = node_token();
        $given = (string) $request->header('X-Node-Token');
        if ($expected === '' || !hash_equals($expected, $given)) {
            return response()->json(['ok' => false, 'message' => 'forbidden'], 403);
        }

        $data = $request->validate([
            'wid'      => 'required|string|max:32',
            'status'   => 'required|string|max:64',
            'qr'       => 'nullable|string',
            'progress' => 'nullable|numeric',
        ]);

        $phone = preg_replace('/\D+/', '', $data['wid']);
        $cfg = WaProviderConfig::query()
            ->where('provider', 'baileys')
            ->where(function ($q) use ($phone) {
                $q->where('phone_number', $phone)
                  ->orWhere('phone_number', '+' . $phone);
            })
            ->first();
        if (!$cfg) {
            return response()->json(['ok' => false, 'message' => 'no matching config for ' . $phone]);
        }

        $creds = $cfg->creds();
        $creds['qr_data']         = $data['qr'] ?? $creds['qr_data'] ?? null;
        $creds['last_status_msg'] = (string) $data['status'];
        $creds['last_status_at']  = now()->toIso8601String();
        $cfg->setCreds($creds);

        // Expanded status mapping — Node sends 20+ distinct strings
        // (Ready, Logged Out, Connecting, Syncing data, QR generated,
        // Mismatch, Pair failed: <reason>, Connection Failed, …).
        // Bucket them into the three real states so downstream UI +
        // dispatcher checks can rely on $cfg->status alone.
        $statusLower = strtolower($data['status']);
        $isConnected =  $statusLower === 'ready'
                     || $statusLower === 'connected'
                     || $statusLower === 'paired'
                     || $statusLower === 'open';
        $isDisconnected = str_contains($statusLower, 'logged out')
                       || str_contains($statusLower, 'logged_out')
                       || str_contains($statusLower, 'disconnected')
                       || str_contains($statusLower, 'connection failed')
                       || str_contains($statusLower, 'connection_failed')
                       || str_contains($statusLower, 'pair failed')
                       || str_contains($statusLower, 'mismatch');

        if ($isConnected) {
            $cfg->status = WaProviderConfig::STATUS_CONNECTED;
            $cfg->connected_at = $cfg->connected_at ?: now();
        } elseif ($isDisconnected) {
            $cfg->status = WaProviderConfig::STATUS_DISCONNECTED;
        } else {
            $cfg->status = WaProviderConfig::STATUS_PENDING;
        }
        $cfg->last_health_at = now();
        $cfg->save();

        // Mirror the provider state into the matching Device rows so
        // the team-inbox conversation header + left-rail dot can show
        // an accurate connection badge. devices.phone_number is
        // encrypted, so we scan in PHP. We try workspace-scoped first
        // (multi-tenant safe), then fall back to any device matching
        // the phone digits with NULL workspace_id (legacy rows that
        // pre-date the workspace backfill — won't cross tenants since
        // a NULL workspace_id row by definition belongs to no one).
        $deviceStatus = $isConnected ? 'connected' : ($isDisconnected ? 'disconnected' : 'needs_pair');
        $matchedDevices = \App\Models\Device::query()
            ->where(function ($q) use ($cfg) {
                $q->where('workspace_id', $cfg->workspace_id)
                  ->orWhereNull('workspace_id');
            })
            ->get(['id', 'workspace_id', 'country_code', 'phone_number', 'status', 'active'])
            ->filter(function ($d) use ($phone) {
                $digits = preg_replace('/\D+/', '', (string) ($d->country_code . $d->phone_number));
                return $digits === $phone;
            });
        $deviceUpdatedCount = 0;
        foreach ($matchedDevices as $d) {
            $patch = ['status' => $deviceStatus, 'last_seen_at' => now()];
            if ($isConnected) $patch['active'] = true;
            // Backfill workspace_id on legacy rows that match — once
            // we know which workspace owns this phone, lock it in.
            if (!$d->workspace_id) $patch['workspace_id'] = $cfg->workspace_id;
            \App\Models\Device::where('id', $d->id)->update($patch);
            $deviceUpdatedCount++;
        }
        \Log::info('[node-status]', [
            'phone' => $phone, 'raw_status' => $data['status'], 'derived' => $cfg->status,
            'devices_updated' => $deviceUpdatedCount,
        ]);

        // Broadcast so any open /team-inbox tab refreshes its dot
        // without waiting for the polling tick. Channel is workspace-
        // scoped — Echo client listens on `device-status.workspace.{id}`.
        try {
            broadcast(new \App\Events\DeviceStatusChanged(
                $cfg->workspace_id,
                $phone,
                $cfg->status,
                $data['status']
            ))->toOthers();
        } catch (\Throwable $e) {
            \Log::warning('[device-status] broadcast failed: ' . $e->getMessage());
        }

        return response()->json(['ok' => true, 'derived_status' => $cfg->status]);
    }

    // ───────── Per-number proxy / IP isolation (Node ↔ Laravel) ─────────

    /**
     * Node fetches a device's proxy config at connect/reconnect time so the
     * Baileys socket egresses through the per-number proxy IP. X-Node-Token
     * gated; creds are decrypted server-side (encrypted cast) and only leave
     * over the trusted Node channel. {enabled:false} → Node uses the direct IP.
     */
    public function proxyConfig(Request $request, string $phone): JsonResponse
    {
        $expected = node_token();
        $given = (string) $request->header('X-Node-Token');
        if ($expected === '' || !hash_equals($expected, $given)) {
            return response()->json(['ok' => false, 'message' => 'forbidden'], 403);
        }
        $device = $this->deviceByPhoneDigits($phone);
        if (!$device || !$device->proxy_enabled || !$device->proxy_host) {
            return response()->json(['ok' => true, 'proxy' => ['enabled' => false]]);
        }
        return response()->json(['ok' => true, 'proxy' => [
            'enabled'  => true,
            'type'     => $device->proxy_type ?: 'http',
            'host'     => $device->proxy_host,
            'port'     => (int) $device->proxy_port,
            'username' => $device->proxy_username,   // decrypted by model cast
            'password' => $device->proxy_password,   // decrypted by model cast
        ]]);
    }

    /**
     * Node reports proxy health + the verified egress IP after its pre-flight
     * probe. Surfaces on the device UI; non-critical to the link itself.
     */
    public function proxyResult(Request $request): JsonResponse
    {
        $expected = node_token();
        $given = (string) $request->header('X-Node-Token');
        if ($expected === '' || !hash_equals($expected, $given)) {
            return response()->json(['ok' => false, 'message' => 'forbidden'], 403);
        }
        $data = $request->validate([
            'phone_number'    => 'required|string|max:32',
            'proxy_status'    => 'required|string|max:16',
            'proxy_egress_ip' => 'nullable|string|max:64',
            'error'           => 'nullable|string|max:255',
        ]);
        $device = $this->deviceByPhoneDigits($data['phone_number']);
        if (!$device) return response()->json(['ok' => false, 'message' => 'no device']);

        $patch = ['proxy_status' => $data['proxy_status'], 'proxy_checked_at' => now()];
        if (!empty($data['proxy_egress_ip'])) $patch['proxy_egress_ip'] = $data['proxy_egress_ip'];
        \App\Models\Device::where('id', $device->id)->update($patch);
        return response()->json(['ok' => true]);
    }

    /**
     * Resolve a Baileys device by its normalized phone digits. devices.phone_number
     * is encrypted, so we scan + compare in PHP (same pattern as nodeStatusCallback).
     */
    private function deviceByPhoneDigits(string $phone): ?\App\Models\Device
    {
        $phone = preg_replace('/\D+/', '', $phone);
        if ($phone === '') return null;
        return \App\Models\Device::query()
            ->get(['id', 'workspace_id', 'country_code', 'phone_number', 'proxy_enabled',
                   'proxy_type', 'proxy_host', 'proxy_port', 'proxy_username', 'proxy_password'])
            ->first(function ($d) use ($phone) {
                return preg_replace('/\D+/', '', (string) ($d->country_code . $d->phone_number)) === $phone;
            });
    }

    /**
     * Heartbeat from Node — fires every ~30 s with a list of live
     * Baileys sockets. We use this as a "still alive" signal: any
     * device listed gets `last_seen_at = now()`; any workspace device
     * NOT in the list AND last seen > 90 s ago is marked stale.
     *
     * Payload: { devices: [{ wid, status }] }
     */
    public function nodeHeartbeat(Request $request): JsonResponse
    {
        $expected = node_token();
        $given = (string) $request->header('X-Node-Token');
        if ($expected === '' || !hash_equals($expected, $given)) {
            return response()->json(['ok' => false, 'message' => 'forbidden'], 403);
        }

        $data = $request->validate([
            'devices'             => 'required|array',
            'devices.*.wid'       => 'required|string|max:32',
            'devices.*.status'    => 'nullable|string|max:64',
        ]);

        $liveDigits = collect($data['devices'])
            ->map(fn ($d) => preg_replace('/\D+/', '', (string) $d['wid']))
            ->filter()
            ->values()
            ->all();
        if (empty($liveDigits)) {
            return response()->json(['ok' => true, 'touched' => 0]);
        }

        // Touch last_seen_at on every live device. The PHP scan is
        // unavoidable — phone_number is encrypted, no SQL index.
        $touched = 0;
        \App\Models\Device::query()
            ->get(['id', 'country_code', 'phone_number'])
            ->each(function ($d) use ($liveDigits, &$touched) {
                $digits = preg_replace('/\D+/', '', (string) ($d->country_code . $d->phone_number));
                if (in_array($digits, $liveDigits, true)) {
                    \App\Models\Device::where('id', $d->id)->update(['last_seen_at' => now()]);
                    $touched++;
                }
            });

        // Mark devices stale: were "connected" but haven't been heard
        // from in > 90 s. Use per-instance saves (not a mass UPDATE) so the
        // Device model's `updated` hook fires the device_status_updated
        // webhook for each flip. Stale events are rare + the connected-device
        // count is small, so the loop cost is negligible.
        $staleCutoff = now()->subSeconds(90);
        $staleCount  = 0;
        \App\Models\Device::where('status', 'connected')
            ->where('last_seen_at', '<', $staleCutoff)
            ->get()
            ->each(function ($d) use (&$staleCount) {
                $d->update(['status' => 'disconnected']);
                $staleCount++;
            });

        // Long-inactivity device logout (security.device_logout_on_inactive_days,
        // 0 = off). Cache-gated to run at most hourly so it isn't repeated every
        // 30s heartbeat. Per-instance saves so the Device hook fires the
        // device_status_updated webhook. Fail-open.
        try {
            $idleDays = \App\Support\SecurityPolicy::int('device_logout_on_inactive_days', 0);
            if ($idleDays > 0 && \Illuminate\Support\Facades\Cache::add('device-idle-logout-lock', 1, 3600)) {
                \App\Models\Device::where('active', true)
                    ->where('last_seen_at', '<', now()->subDays($idleDays))
                    ->get()
                    ->each(fn ($d) => $d->update(['active' => false, 'status' => 'disconnected']));
            }
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('[HEARTBEAT] idle-device sweep failed: ' . $e->getMessage());
        }

        // Piggy-back the 30s heartbeat to fire any DUE scheduled / recurring
        // campaigns — WaDesk has no Laravel scheduler, so this is the tick.
        // Internally cache-locked so concurrent heartbeats can't double-fire.
        $sweptCampaigns = 0;
        try {
            $sweptCampaigns = app(\App\Services\CampaignScheduleSweeper::class)->sweep();
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('[HEARTBEAT] campaign sweep failed: ' . $e->getMessage());
        }

        // Durable retry for scheduled messages — re-fires any that failed
        // (e.g. device was offline at send time) once it's reachable again,
        // with per-feature backoff up to the configured max attempts.
        $sweptScheduled = 0;
        try {
            $sweptScheduled = app(\App\Services\ScheduledMessageSweeper::class)->sweep();
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('[HEARTBEAT] scheduled sweep failed: ' . $e->getMessage());
        }

        // Durable retry for broadcasts — re-fires failed/undelivered recipients
        // (only the unsent remainder) with backoff, up to the configured max.
        $sweptBroadcasts = 0;
        try {
            $sweptBroadcasts = app(\App\Services\BroadcastSweeper::class)->sweep();
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('[HEARTBEAT] broadcast sweep failed: ' . $e->getMessage());
        }

        // Once per day (cache-locked so 30s heartbeats don't re-run it), auto-wipe
        // the data of workspaces whose plan expired beyond the plan's
        // data_retention_days. Opt-in per plan (0 = never) — a complete no-op
        // unless an admin set a retention window. Heartbeat is our only tick.
        try {
            if (\Illuminate\Support\Facades\Cache::add('wipe-expired-daily-lock', 1, now()->addDay())) {
                \Illuminate\Support\Facades\Artisan::call('workspaces:wipe-expired', ['--force' => true]);
            }
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('[HEARTBEAT] data-retention wipe failed: ' . $e->getMessage());
        }

        // Deal task reminders — overdue tasks nudge their owner ONCE. No cron in
        // this system, so the heartbeat is the always-on path (it previously only
        // fired when someone had the Team Inbox open). Idempotent via
        // deal_activities.reminded_at; throttled to ~2 min so it isn't run on
        // every heartbeat. null = sweep all workspaces.
        $remindedTasks = 0;
        try {
            if (\Illuminate\Support\Facades\Cache::add('deal-reminder-sweep-lock', 1, now()->addSeconds(120))) {
                $remindedTasks = app(\App\Services\Deals\DealReminderService::class)->sweep(null, 500);
            }
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('[HEARTBEAT] deal reminder sweep failed: ' . $e->getMessage());
        }

        // Natural-language ordering — release expired stock holds so abandoned
        // carts don't pin inventory forever (anti-sellout, P2). Cache-locked +
        // throttled like the other sweeps; heartbeat-driven (no cron).
        $releasedHolds = 0;
        try {
            if (\Illuminate\Support\Facades\Cache::add('stock-hold-sweep-lock', 1, now()->addSeconds(60))) {
                $releasedHolds = app(\App\Services\Ordering\InventoryService::class)->sweepExpired(1000);
            }
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('[HEARTBEAT] stock-hold sweep failed: ' . $e->getMessage());
        }

        // Tell Node whether campaigns are STILL draining so it can tick fast
        // (back-to-back parallel chunks) instead of waiting the full 30s — a big
        // multi-thousand blast then finishes in one continuous run rather than one
        // 20s chunk per 30s heartbeat. Active = we fired one this tick OR one is
        // mid-chunk ('running'). Best-effort; false on any error keeps the calm 30s.
        $campaignsActive = false;
        try {
            $campaignsActive = $sweptCampaigns > 0
                || \App\Models\WpCampaign::where('status', 'running')->exists();
        } catch (\Throwable $e) { $campaignsActive = $sweptCampaigns > 0; }

        return response()->json(['ok' => true, 'touched' => $touched, 'marked_stale' => $staleCount, 'campaigns_fired' => $sweptCampaigns, 'campaigns_active' => $campaignsActive, 'scheduled_retried' => $sweptScheduled, 'broadcasts_retried' => $sweptBroadcasts, 'deal_tasks_reminded' => $remindedTasks, 'stock_holds_released' => $releasedHolds]);
    }

    /**
     * Storefront row is created the moment a provider is connected so
     * the workspace immediately gets a public URL. Slug is generated
     * from the workspace name; user can rename later in /store/storefront.
     */
    private function ensureStorefront(int $workspaceId): WaStorefront
    {
        return WaStorefront::firstOrCreate(
            ['workspace_id' => $workspaceId],
            [
                'theme_key' => WaStorefront::DEFAULT_THEME,
                'enabled'   => true,
            ]
        );
    }

    /**
     * GET /api/whatsapp-settings — Node bridge fetches this on boot
     * AND on every send (60-s cache) to decide Baileys vs WABA Cloud
     * API and to read the matching access_token + phone_number_id.
     *
     * Query string:
     *   ?phone=<digits>   sender phone (looks up wa_provider_configs)
     *
     * Returns the keys Node's `node/utils/helpers.js` reads:
     *   use_facebook_api / facebook_token / facebook_api_token /
     *   facebook_phone_id / facebook_phone_number_id / whatsapp_business_api /
     *   facebook_app_version
     *
     * Without a `?phone=` query we fall back to the platform-default
     * engine setting + legacy env-based credentials. Legacy installs
     * that used FACEBOOK_API_TOKEN / FACEBOOK_WP_ID still work.
     */
    public function nodeSettings(Request $request): JsonResponse
    {
        // SECURITY — endpoint returns the decrypted WABA access_token.
        // Without this gate, anyone could harvest tokens by scraping
        // phone numbers. Mirrors nodeStatusCallback's check.
        $expected = node_token();
        $given    = (string) $request->header('X-Node-Token');
        if ($expected === '' || !hash_equals($expected, $given)) {
            return response()->json(['ok' => false, 'message' => 'forbidden'], 403);
        }

        // Accept both `phone` and `phone_number` query keys — Node's
        // messageController uses `phone_number`, scheduleService uses
        // `phone`. Unifying server-side avoids touching 6+ Node callers.
        $phone = preg_replace('/\D+/', '',
            (string) ($request->query('phone') ?: $request->query('phone_number', ''))
        );

        $cfg = null;
        if ($phone !== '') {
            // EXACT match on normalized digits — substring used to leak
            // workspace A (9876543210) into queries for workspace B
            // (19876543210). For each candidate, strip non-digits and
            // require full equality.
            $cfg = \App\Models\WaProviderConfig::query()
                ->where('provider', 'waba')
                ->where('status', \App\Models\WaProviderConfig::STATUS_CONNECTED)
                ->get()
                ->first(function ($r) use ($phone) {
                    $candidates = [
                        $r->phone_number ?? null,
                        $r->meta_json['display_phone_number'] ?? null,
                    ];
                    foreach ($candidates as $c) {
                        if (!$c) continue;
                        $clean = preg_replace('/\D+/', '', (string) $c);
                        // Match if (a) full equality, (b) candidate ends
                        // with the query (handles "+91 9876543210" vs
                        // "9876543210" where country code was dropped),
                        // OR (c) query ends with candidate (handles the
                        // reverse — caller has country code, stored
                        // record doesn't).
                        if ($clean === $phone) return true;
                        if (str_ends_with($clean, $phone) && strlen($clean) - strlen($phone) <= 4) return true;
                        if (str_ends_with($phone, $clean) && strlen($phone) - strlen($clean) <= 4) return true;
                    }
                    return false;
                });
        }

        $useWaba       = false;
        $accessToken   = '';
        $phoneNumberId = '';
        if ($cfg) {
            $creds         = $cfg->creds();
            $useWaba       = true;
            $accessToken   = (string) ($creds['access_token']  ?? '');
            $phoneNumberId = (string) (($cfg->meta_json['phone_number_id'] ?? '') ?: ($creds['phone_number_id'] ?? ''));
        } elseif ($phone === '') {
            // No phone supplied — return defaults (engine flag only).
            // Credentials must come from a workspace's wa_provider_configs
            // row; we no longer fall back to platform env tokens because
            // those leak workspace A's secrets into workspace B's sends.
            $engine  = (string) \App\Models\SystemSetting::get('default_send_method', '');
            $useWaba = $engine === 'waba';
            // accessToken + phoneNumberId stay empty — Node interprets
            // missing creds as "no WABA available" and falls to Baileys.
        } else {
            // Phone WAS supplied but no matching workspace config.
            // Do NOT fall back to platform env — that would attribute
            // workspace A's send to workspace B's credentials.
            // Caller (Node) gets `use_facebook_api: false` and falls
            // back to Baileys (the correct behaviour for unmatched).
            \Log::info('[nodeSettings] no WABA config matched phone — returning Baileys defaults', ['phone' => $phone]);
        }

        // Twilio resolution — runs alongside WABA so a workspace using
        // Twilio for /chat sends can ALSO have flows that send via
        // Twilio. Without this block, flowService had no way to call
        // Twilio's REST API directly from Node and silently dropped
        // every Twilio outbound from flow nodes. Now Node detects
        // `use_twilio` + receives Twilio creds the same way it gets
        // WABA creds, and routes flow sends through the new
        // sendMessageViaTwilioApi helper.
        $useTwilio       = false;
        $twilioSid       = '';
        $twilioToken     = '';
        $twilioFrom      = '';
        $twilioSandbox   = false;
        if ($phone !== '') {
            $twilioCfg = \App\Models\WaProviderConfig::query()
                ->where('provider', 'twilio')
                ->where('status', \App\Models\WaProviderConfig::STATUS_CONNECTED)
                ->get()
                ->first(function ($r) use ($phone) {
                    $clean = preg_replace('/\D+/', '', (string) ($r->phone_number ?? ''));
                    if (!$clean) return false;
                    return $clean === $phone
                        || (str_ends_with($clean, $phone) && strlen($clean) - strlen($phone) <= 4)
                        || (str_ends_with($phone, $clean) && strlen($phone) - strlen($clean) <= 4);
                });
            if ($twilioCfg) {
                $tCreds        = $twilioCfg->creds();
                $useTwilio     = true;
                $twilioSid     = (string) ($tCreds['account_sid'] ?? '');
                $twilioToken   = (string) ($tCreds['auth_token']  ?? '');
                $twilioFrom    = (string) ($tCreds['from_number'] ?? $twilioCfg->phone_number ?? '');
                $twilioSandbox = (bool)   ($tCreds['sandbox']     ?? ($twilioCfg->meta_json['sandbox'] ?? false));
                // Twilio + WABA are mutually exclusive — if a workspace
                // somehow had both rows, the WABA one already matched
                // above. Reaching here means it's a Twilio workspace.
                if ($useTwilio) $useWaba = false;
            }
        }

        $version = (string) \App\Models\SystemSetting::get('waba_graph_api_version', 'v23.0');

        // Outbound footer — resolved per workspace via the plan gate +
        // user override. Cached on the Node session so we don't re-hit
        // Laravel on every send.
        //
        // Workspace resolution order:
        //   1. From the matched WABA config (if Cloud API workspace)
        //   2. From the Baileys device row (scan-by-phone, encrypted col)
        //   3. NULL → platform default (or none, per BrandingFooterService)
        //
        // Without step 2, Baileys workspaces (the majority of installs)
        // would always show `branding_footer: null` because they have no
        // WaProviderConfig row to anchor on.
        $bfWorkspace = $cfg ? \App\Models\Workspace::find($cfg->workspace_id) : null;
        if (!$bfWorkspace && $phone !== '') {
            $device = \App\Models\Device::query()
                ->where('active', true)
                ->get(['id', 'workspace_id', 'country_code', 'phone_number'])
                ->first(function ($d) use ($phone) {
                    $clean = preg_replace('/\D+/', '', (string) ($d->country_code . $d->phone_number));
                    return $clean === $phone
                        || (strlen($clean) > strlen($phone) && str_ends_with($clean, $phone))
                        || (strlen($phone) > strlen($clean) && str_ends_with($phone, $clean));
                });
            if ($device && $device->workspace_id) {
                $bfWorkspace = \App\Models\Workspace::find($device->workspace_id);
            }
        }
        $brandingFooter = \App\Services\BrandingFooterService::resolve($bfWorkspace);
        \Log::info('[FOOTER-API] resolved', [
            'phone'           => $phone,
            'workspace_id'    => $bfWorkspace?->id,
            'workspace_name'  => $bfWorkspace?->name,
            'branding_footer' => $brandingFooter,
            'use_waba'        => $useWaba,
        ]);

        return response()->json([
            'whatsapp_business_api'    => $useWaba ? 1 : 0,
            'use_facebook_api'         => $useWaba,
            'facebook_token'           => $accessToken,        // legacy key
            'facebook_api_token'       => $accessToken,        // node/utils/helpers.js
            'facebook_phone_id'        => $phoneNumberId,      // node/utils/helpers.js
            'facebook_phone_number_id' => $phoneNumberId,      // legacy key
            'facebook_app_version'     => $version,
            // Twilio creds — Node's sendMessageViaTwilioApi reads these
            // to POST directly to Twilio's REST API. Empty strings when
            // not a Twilio workspace.
            'use_twilio'               => $useTwilio,
            'twilio_account_sid'       => $twilioSid,
            'twilio_auth_token'        => $twilioToken,
            'twilio_from_number'       => $twilioFrom,
            'twilio_sandbox'           => $twilioSandbox,
            'branding_footer'          => $brandingFooter,     // plan-gated outbound footer
        ]);
    }

    /**
     * GET /api/whatsapp-message-settings — Node bridge reads this on
     * boot AND every 60 s to refresh pacing/batching config. Key
     * names MUST match what `node/index.js` reads:
     *   - msg_gap         (int seconds, default 3)
     *   - batches_gap     (int recipients per batch, default 50)
     *   - bw_msg_gap      (int minutes between batches, default 5)
     *   - enable_batches  (0/1 — Node coerces to a truthy check)
     *
     * These keys are written by admin/settings/wadesk-message →
     * AdminPagesController::settingsProvidersUpdate.
     */
    public function nodeMessageSettings(Request $request): JsonResponse
    {
        // Same X-Node-Token gate as nodeSettings — the pacing values
        // themselves aren't secrets but the endpoint shouldn't be
        // publicly enumerable either (info disclosure + endpoint
        // discoverability).
        $expected = node_token();
        $given    = (string) $request->header('X-Node-Token');
        if ($expected === '' || !hash_equals($expected, $given)) {
            return response()->json(['ok' => false, 'message' => 'forbidden'], 403);
        }

        return response()->json([
            'success'        => true,
            'msg_gap'        => (int) \App\Models\SystemSetting::get('msg_gap', 3),
            'batches_gap'    => (int) \App\Models\SystemSetting::get('batches_gap', 50),
            'bw_msg_gap'     => (int) \App\Models\SystemSetting::get('bw_msg_gap', 5),
            'enable_batches' => (bool) \App\Models\SystemSetting::get('enable_batches', false) ? 1 : 0,
        ]);
    }

}
