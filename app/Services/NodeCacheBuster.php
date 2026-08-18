<?php

namespace App\Services;

use App\Models\Device;
use App\Models\SystemSetting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Fires `POST /api/cache-bust` on the Node bridge whenever something
 * changes that Node's per-phone settings cache would otherwise serve
 * stale for up to 5 minutes (WABA config save, branding footer change,
 * platform footer change, etc).
 *
 * Best-effort — a failed POST never blocks the user-facing save. The
 * 5-min TTL is the slow-path safety net.
 */
class NodeCacheBuster
{
    /** Flush the cache for one device's phone (or all phones in a workspace). */
    public static function bustWorkspace(int $workspaceId): void
    {
        $phones = Device::query()
            ->where('workspace_id', $workspaceId)
            ->get(['country_code', 'phone_number'])
            ->map(fn ($d) => preg_replace('/\D+/', '', (string) ($d->country_code . $d->phone_number)))
            ->filter()->values()->all();

        if (empty($phones)) {
            // Workspace has no devices yet — still flush platform-only entry.
            self::post(null);
            return;
        }
        foreach ($phones as $phone) {
            self::post($phone);
        }
    }

    /** Flush everything — used on platform-wide config changes. */
    public static function bustAll(): void
    {
        self::post(null);
    }

    /**
     * Tell Node to RE-PULL its global settings now (pacing msg_gap /
     * batches_gap / bw_msg_gap + WABA creds + branding footer) instead of
     * waiting up to the bridge's hourly auto-refresh. This is a different
     * cache from /api/cache-bust (which only flushes the per-phone WABA
     * settings cache) — pacing lives in Node's app.locals.messageSettings,
     * refreshed only by GET /api/refresh-settings or the 1h timer. Called
     * after the admin saves pacing so the change takes effect immediately.
     * Best-effort — a failed ping never blocks the save (the 1h timer is
     * the slow-path safety net).
     */
    public static function refreshNodeSettings(): ?array
    {
        $base = (string) (SystemSetting::get('baileys_server_url', '') ?: env('SERVER_URL', ''));
        if ($base === '') return null;
        try {
            // /api/refresh-settings makes Node re-pull from Laravel synchronously
            // and echoes back the freshly-loaded messageSettings, so the caller
            // can SEE what gap the bridge now actually holds (used by the admin
            // "Update timing" button to confirm the value landed).
            $resp = Http::withHeaders(['X-Node-Token' => node_token()])
                ->timeout(4)
                ->acceptJson()
                ->get(rtrim($base, '/') . '/api/refresh-settings');
            if ($resp->ok()) {
                return $resp->json('messageSettings');
            }
            Log::info('[NodeCacheBuster] settings refresh non-OK: ' . $resp->status());
        } catch (\Throwable $e) {
            Log::info('[NodeCacheBuster] settings refresh ping failed: ' . $e->getMessage());
        }
        return null;
    }

    private static function post(?string $phone): void
    {
        $base = (string) (SystemSetting::get('baileys_server_url', '') ?: env('SERVER_URL', ''));
        if ($base === '') return;
        $token = node_token();
        try {
            Http::withHeaders(['X-Node-Token' => $token])
                ->timeout(2)
                ->acceptJson()
                ->asJson()
                ->post(rtrim($base, '/') . '/api/cache-bust', [
                    'phone' => $phone,
                ]);
        } catch (\Throwable $e) {
            Log::info('[NodeCacheBuster] best-effort failed: ' . $e->getMessage());
        }
    }
}
