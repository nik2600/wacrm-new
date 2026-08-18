<?php

namespace App\Models\Concerns;

use App\Services\PlanUsage;
use Illuminate\Support\Facades\Log;

/**
 * Auto-count a model against its plan quota on create — the "define once,
 * applies everywhere" half of the plan-limit system. A model opts in with:
 *
 *   use TracksPlanQuota;
 *   const PLAN_QUOTA_METRIC = 'campaign';
 *
 * and every create() (web, API, import, seeder — anywhere the model is made)
 * bumps PlanUsage for the row's workspace. There is deliberately NO deleted()
 * hook: the counter is increment-only, which is what stops "delete + recreate"
 * from bypassing a plan cap. The matching read/enforce side is
 * PlanLimitGuard::checkQuota().
 */
trait TracksPlanQuota
{
    public static function bootTracksPlanQuota(): void
    {
        $metric = defined(static::class . '::PLAN_QUOTA_METRIC')
            ? (string) static::PLAN_QUOTA_METRIC
            : null;
        if (!$metric) return;

        static::created(function ($model) use ($metric) {
            try {
                $wsId = (int) ($model->getAttribute('workspace_id') ?? 0);
                if ($wsId > 0) {
                    PlanUsage::bump($wsId, $metric);
                }
            } catch (\Throwable $e) {
                // Metering must never break a create.
                Log::warning('[PLAN-USAGE] auto-bump failed: ' . $e->getMessage());
            }
        });
    }
}
