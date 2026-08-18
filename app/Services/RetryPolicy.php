<?php

namespace App\Services;

use App\Models\SystemSetting;
use App\Models\Workspace;

/**
 * PER-FEATURE retry policy. Each send feature (scheduled / broadcast /
 * campaign) has its own "max attempts" + "retry delay (backoff seconds)".
 *
 * Resolution order (most specific wins):
 *   1) per-workspace override  → workspaces.plan_overrides["{feature}_retry_attempts"]
 *   2) platform admin default  → SystemSetting "{feature}_retry_attempts"
 *   3) hard-coded default      → DEFAULTS below
 *
 * Keys are "{feature}_retry_attempts" and "{feature}_retry_backoff_sec" — the
 * campaign path already used campaign_retry_attempts / campaign_retry_backoff_sec,
 * so this stays consistent and just generalises it to scheduled + broadcast.
 */
class RetryPolicy
{
    /** [maxAttempts, backoffBaseSeconds] per feature. */
    private const DEFAULTS = [
        'scheduled' => [3, 60],
        'broadcast' => [3, 60],
        'campaign'  => [3, 60],
    ];

    public static function attempts(?Workspace $ws, string $feature): int
    {
        return max(1, self::resolve($ws, $feature, 'attempts'));
    }

    public static function backoff(?Workspace $ws, string $feature): int
    {
        return max(0, self::resolve($ws, $feature, 'backoff'));
    }

    /**
     * Delay (seconds) before the Nth retry (1-based): exponential backoff
     * base * 2^(n-1), capped at 1 hour. backoff 0 = retry immediately.
     */
    public static function delayForAttempt(?Workspace $ws, string $feature, int $attempt): int
    {
        $base = self::backoff($ws, $feature);
        if ($base <= 0) return 0;
        return (int) min(3600, $base * (2 ** max(0, $attempt - 1)));
    }

    private static function resolve(?Workspace $ws, string $feature, string $kind): int
    {
        [$defAttempts, $defBackoff] = self::DEFAULTS[$feature] ?? self::DEFAULTS['scheduled'];
        $default = $kind === 'attempts' ? $defAttempts : $defBackoff;
        $key = $feature . '_retry_' . ($kind === 'attempts' ? 'attempts' : 'backoff_sec');

        // 1) per-workspace override (reuses the plan_overrides JSON bag).
        if ($ws) {
            $ov = is_array($ws->plan_overrides ?? null) ? $ws->plan_overrides : [];
            if (array_key_exists($key, $ov) && $ov[$key] !== null && $ov[$key] !== '') {
                return (int) $ov[$key];
            }
        }
        // 2) platform admin default.
        $sys = SystemSetting::get($key, null);
        if ($sys !== null && $sys !== '') return (int) $sys;
        // 3) code default.
        return (int) $default;
    }
}
