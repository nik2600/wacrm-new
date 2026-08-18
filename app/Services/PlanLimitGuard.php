<?php

namespace App\Services;

use App\Exceptions\PlanLimitReachedException;
use App\Models\Workspace;

/**
 * Single entry-point for plan enforcement. Controllers call:
 *
 *   PlanLimitGuard::check($workspace, 'flow_limit', $currentFlowCount);
 *   PlanLimitGuard::feature($workspace, 'access_kanban_view');
 *
 * Both throw PlanLimitReachedException on failure — the exception's
 * render() method returns 422 JSON or back-with-error depending on
 * request type.
 *
 * Bypass: workspace owners with the "Super Admin" or "Admin" Spatie
 * role bypass every limit (matches SnapNest's pattern — admins are
 * never blocked by their own product).
 */
class PlanLimitGuard
{
    /**
     * Throw if the workspace's count for $limitKey is already at or
     * above its package limit. NULL limit = unlimited (no throw).
     *
     * Pass the count of CURRENT rows (not "rows after this create").
     * The check is `>=` so it triggers when the count equals the cap
     * — about to create row N+1.
     */
    public static function check(?Workspace $workspace, string $limitKey, int $used): void
    {
        if (!$workspace || self::bypass($workspace)) return;

        $limit = $workspace->effectiveLimit($limitKey, null);
        if ($limit === null) return;            // unlimited
        if ((int) $limit <= 0) return;           // 0 / negative = unlimited too (defensive)
        if ($used < (int) $limit) return;

        throw new PlanLimitReachedException(
            limitKey: $limitKey,
            used:     $used,
            limit:    (int) $limit,
        );
    }

    /**
     * Quota-aware sibling of check(): enforce a numeric plan cap against the
     * INCREMENT-ONLY usage ledger (PlanUsage) instead of a live row count.
     *
     * Why: counting live rows let a customer create N resources, delete them,
     * and create N more — the row count never grew, so the cap never bit. The
     * ledger only ever goes up on create, so that bypass is impossible; and it
     * is keyed by billing period, so buying / renewing a plan re-opens the
     * allowance automatically.
     *
     * @param  string   $limitKey    the plan limit column (e.g. total_campaigns_limit)
     * @param  string   $metric      the PlanUsage metric (e.g. 'campaign')
     * @param  int|null $seedFromLive current live count, used ONCE to adopt
     *                                pre-existing rows the first time this
     *                                workspace+metric is metered (null = don't seed)
     */
    public static function checkQuota(?Workspace $workspace, string $limitKey, string $metric, ?int $seedFromLive = null): void
    {
        if (!$workspace || self::bypass($workspace)) return;

        $limit = $workspace->effectiveLimit($limitKey, null);
        if ($limit === null) return;            // unlimited
        if ((int) $limit <= 0) return;           // 0 / negative = unlimited too (defensive)

        // First-ever meter for this workspace+metric adopts the current live
        // count so an existing install starts at its true usage, not zero. Does
        // nothing on later periods, so a new plan still starts fresh at 0.
        if ($seedFromLive !== null) {
            \App\Services\PlanUsage::seedIfFirstEver($workspace, $metric, $seedFromLive);
        }

        $used = \App\Services\PlanUsage::usage($workspace, $metric);
        if ($used < (int) $limit) return;

        // 'quota_reached' (not 'limit_reached') so the message never tells the
        // user to "delete to free up space" — this is a delete-proof ledger, so
        // deleting a row does NOT free the allowance; only upgrading does.
        throw new PlanLimitReachedException(
            limitKey: $limitKey,
            used:     $used,
            limit:    (int) $limit,
            reason:   'quota_reached',
        );
    }

    /**
     * Throw if a feature toggle is off on the workspace's package.
     * The package's column for $featureKey is a boolean — true =
     * feature available, false = blocked.
     *
     * Per-workspace plan_overrides can flip a feature on / off
     * regardless of the underlying package (admin override).
     */
    public static function feature(?Workspace $workspace, string $featureKey): void
    {
        if (!$workspace || self::bypass($workspace)) return;

        $enabled = $workspace->effectiveLimit($featureKey, true);
        if ($enabled) return;

        throw new PlanLimitReachedException(
            limitKey: $featureKey,
            reason:   'feature_disabled',
        );
    }

    /** Non-throwing version of feature() — returns bool. */
    public static function hasFeature(?Workspace $workspace, string $featureKey): bool
    {
        if (!$workspace) return false;
        if (self::bypass($workspace)) return true;
        return (bool) $workspace->effectiveLimit($featureKey, true);
    }

    /**
     * Bypass for platform admins. Spatie's hasRole is the source of
     * truth; falls back to the legacy `users.role` column.
     */
    private static function bypass(Workspace $workspace): bool
    {
        $user = auth()->user();
        if (!$user) return false;
        try {
            if ($user->hasRole('Super Admin') || $user->hasRole('Admin')) return true;
        } catch (\Throwable $e) {}
        return in_array($user->role ?? null, ['admin', 'A'], true);
    }
}
