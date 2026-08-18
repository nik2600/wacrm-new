<?php

namespace App\Services\Waba;

use App\Models\SystemSetting;
use App\Models\WaProviderConfig;
use App\Models\WaTemplate;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Opportunistic inline sweep of PENDING WABA templates.
 *
 * Runs as part of regular AJAX requests (`refresh` endpoint and
 * the templates index AJAX). NOT a scheduled job — we deliberately
 * avoid requiring `php artisan schedule:run` on the host, matching
 * the pattern used by the team-inbox escalation sweep.
 *
 * Two methods:
 *   - forWorkspace($wsId)  : called on index AJAX. Sweeps every
 *                            stale PENDING row owned by the workspace.
 *                            Cache-locked to once per 10 min/workspace.
 *   - one($tpl)            : called by the detail-page refresh button
 *                            for a single row. No lock — that endpoint
 *                            already has its own 15 s cache lock.
 *
 * Webhook is still primary; this sweeper exists only because Meta's
 * webhooks can be silently dropped (network blips, 5xx retries that
 * eventually give up). For templates that have been PENDING > 1 h
 * with no webhook, we explicitly GET Meta to refresh state.
 */
class TemplateSyncSweeper
{
    /** How recently the workspace must have been swept before we skip. */
    private const SWEEP_DEBOUNCE_MIN = 10;

    /** Max templates per sweep — protects the Meta GET quota on big tenants. */
    private const SWEEP_BATCH = 25;

    /**
     * Sweep all stale PENDING templates for one workspace.
     * Cache-locked so a tab refresh storm can't re-hit Meta.
     */
    public function forWorkspace(int $workspaceId): int
    {
        if (!SystemSetting::get('waba_templates_v2_enabled', false)) return 0;

        $lockKey = "waba_tpl_sweep:ws:{$workspaceId}";
        if (!Cache::add($lockKey, 1, now()->addMinutes(self::SWEEP_DEBOUNCE_MIN))) {
            return 0; // Already swept recently — no-op.
        }

        $targets = WaTemplate::staleSweepTargets()
            ->where('workspace_id', $workspaceId)
            ->limit(self::SWEEP_BATCH)
            ->get();
        if ($targets->isEmpty()) return 0;

        return $this->runBatch($targets);
    }

    /**
     * Refresh a single template — used by the per-row "Refresh now"
     * button on the detail page. Caller is responsible for its own
     * rate limiting (the refresh route has a 15s cache lock).
     */
    public function one(WaTemplate $tpl): bool
    {
        if (!$tpl->meta_template_id || !$tpl->provider_config_id) return false;
        $cfg = WaProviderConfig::find($tpl->provider_config_id);
        if (!$cfg) return false;

        try {
            $body = (new TemplateClient($cfg))->fetch($tpl->meta_template_id);
            $this->applyMetaStateToRow($tpl, $body);
            return true;
        } catch (\Throwable $e) {
            $tpl->update(['last_synced_at' => now()]);
            Log::warning('[WABA-template-sweep] fetch failed', [
                'tpl' => $tpl->id, 'meta' => $tpl->meta_template_id, 'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    // -----------------------------------------------------------------
    // Internals
    // -----------------------------------------------------------------

    private function runBatch(\Illuminate\Support\Collection $targets): int
    {
        $count = 0;
        // Group by provider_config_id — one client per WABA, not per template.
        foreach ($targets->groupBy('provider_config_id') as $cfgId => $rows) {
            $cfg = WaProviderConfig::find($cfgId);
            if (!$cfg) continue;
            try {
                $client = new TemplateClient($cfg);
            } catch (\Throwable $e) {
                Log::warning('[WABA-template-sweep] client init', ['cfg' => $cfgId, 'error' => $e->getMessage()]);
                continue;
            }
            foreach ($rows as $tpl) {
                try {
                    $body = $client->fetch($tpl->meta_template_id);
                    $this->applyMetaStateToRow($tpl, $body);
                    $count++;
                } catch (\Throwable $e) {
                    $tpl->update(['last_synced_at' => now()]);
                    Log::warning('[WABA-template-sweep] fetch', [
                        'tpl' => $tpl->id, 'meta' => $tpl->meta_template_id, 'error' => $e->getMessage(),
                    ]);
                }
            }
        }
        return $count;
    }

    private function applyMetaStateToRow(WaTemplate $tpl, array $body): void
    {
        $newStatus  = strtoupper((string) ($body['status'] ?? $tpl->meta_status));
        $newQuality = self::normalizeQualityScore($body['quality_score'] ?? null, $tpl->quality_score);

        $patch = [
            'meta_status'           => $newStatus,
            'meta_category'         => (string) ($body['category'] ?? $tpl->meta_category),
            'quality_score'         => $newQuality,
            'rejection_reason_code' => (string) ($body['rejection_reason'] ?? $tpl->rejection_reason_code),
            'last_synced_at'        => now(),
        ];

        $patch['status'] = match ($newStatus) {
            'APPROVED'                 => 'approved',
            'REJECTED'                 => 'rejected',
            'PENDING', 'IN_APPEAL'     => 'pending',
            default                    => $tpl->status,
        };
        if ($newStatus === 'APPROVED' && !$tpl->approved_at) {
            $patch['approved_at'] = now();
        }

        // Ban-prevention rail — auto-pause non-AUTH templates if Meta
        // dropped the quality to RED. Daily re-check via the same sweep.
        if ($newQuality === 'RED' && $tpl->template_type !== 'auth') {
            $patch['paused_until'] = now()->addDay();
        }

        $tpl->update($patch);
    }

    /**
     * Meta's `quality_score` field has historically returned in TWO
     * shapes — sometimes a bare string (`"GREEN"`), sometimes a nested
     * object (`{"score": "GREEN", "date": 1716470000}`). A naive
     * `$body['quality_score']['score']` on a bare string would fall
     * into PHP's string-offset behaviour and return `"G"` — silently
     * corrupting the quality column. This helper handles both shapes
     * defensively. Reused by the webhook handler and the refresh
     * endpoint to keep parsing identical.
     */
    public static function normalizeQualityScore($raw, ?string $fallback = null): string
    {
        if (is_array($raw)) {
            return strtoupper((string) ($raw['score'] ?? ($fallback ?: 'UNKNOWN')));
        }
        if (is_string($raw) && $raw !== '') {
            return strtoupper($raw);
        }
        return strtoupper((string) ($fallback ?: 'UNKNOWN'));
    }
}
