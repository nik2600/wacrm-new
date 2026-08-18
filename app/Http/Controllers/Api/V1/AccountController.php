<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Workspace;
use App\Services\PlanUsage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Account — read the authenticated workspace, its plan, limits and usage.
 *
 * Reads the workspace's plan via Workspace::package()/billingPackage() and the
 * live usage meters via PlanUsage::summary() (the same source the dashboard
 * plan card uses, so the API and UI always agree). Responses use the public
 * { data } envelope; no Resource is needed — the shapes are documented below.
 */
class AccountController extends V1Controller
{
    /**
     * GET /api/v1/me — the current workspace, its plan and numeric limits.
     *
     * data: {
     *   account: { name, email },
     *   workspace: { id, name, slug, timezone, currency },
     *   plan: { id, name, is_free },
     *   limits: { <limit_key>: int|null },   // null = unlimited
     *   features: { <feature_key>: bool }    // unlocked capabilities
     * }
     */
    public function me(Request $request): JsonResponse
    {
        $workspace = $this->workspace();
        if (!$workspace) {
            return $this->fail('not_found', 'Workspace not found.', 404);
        }

        $summary = PlanUsage::summary($workspace);
        $user    = $request->user();

        // Numeric allowances — the curated meter set, resolved via
        // effectiveLimit (respects admin plan_overrides). 0/blank = unlimited,
        // surfaced as null.
        $limits = [];
        foreach (array_keys(PlanUsage::LIMIT_METERS) as $key) {
            $value = $workspace->effectiveLimit($key, 0);
            $value = is_numeric($value) ? (int) $value : 0;
            $limits[$key] = $value > 0 ? $value : null;
        }

        return $this->ok([
            'account'   => [
                'name'  => $user?->name,
                'email' => $user?->email,
            ],
            'workspace' => [
                'id'       => $workspace->id,
                'name'     => $workspace->name,
                'slug'     => $workspace->slug,
                'timezone' => $workspace->timezone,
                'currency' => $workspace->currency,
            ],
            'plan'      => [
                'id'      => $summary['plan_id'],
                'name'    => $summary['plan_name'],
                'is_free' => $summary['is_free'],
            ],
            'limits'    => $limits,
            'features'  => array_fill_keys(array_keys($summary['unlocked']), true),
        ]);
    }

    /**
     * GET /api/v1/usage — message sends used and remaining this billing month.
     *
     * data: {
     *   period: { label, resets_on, days_left },
     *   messages: { used, limit, unlimited, remaining, percent },
     *   credits: int,                         // owner wallet credits
     *   meters: { <key>: { label, used, limit, unlimited, percent } }
     * }
     */
    public function usage(Request $request): JsonResponse
    {
        $workspace = $this->workspace();
        if (!$workspace) {
            return $this->fail('not_found', 'Workspace not found.', 404);
        }

        $summary = PlanUsage::summary($workspace);

        // Normalise the per-meter shape to the public contract (percent, not pct).
        $meters = [];
        foreach ($summary['meters'] as $key => $meter) {
            $meters[$key] = [
                'label'     => $meter['label'],
                'used'      => $meter['used'],
                'limit'     => $meter['unlimited'] ? null : $meter['limit'],
                'unlimited' => $meter['unlimited'],
                'percent'   => $meter['pct'],
            ];
        }

        return $this->ok([
            'period'   => [
                'label'     => $summary['month_label'],
                'resets_on' => $summary['cycle_reset'],
                'days_left' => $summary['days_left'],
            ],
            // Plan VALIDITY (subscription expiry) — distinct from the monthly
            // message cycle above; a year out for yearly plans. null = no expiry.
            'plan'     => [
                'name'             => $summary['plan_name'],
                'valid_until'      => $summary['valid_until'],
                'valid_until_days' => $summary['valid_until_days'],
            ],
            'messages' => [
                'used'      => $summary['messages_used'],
                'limit'     => $summary['messages_unlimited'] ? null : $summary['messages_limit'],
                'unlimited' => $summary['messages_unlimited'],
                'remaining' => $summary['messages_remaining'],
                'percent'   => $summary['messages_pct'],
            ],
            'credits'  => $summary['credits'],
            'meters'   => $meters,
        ]);
    }

    /** Resolve the authenticated workspace from current_workspace_id. */
    private function workspace(): ?Workspace
    {
        return Workspace::find($this->workspaceId());
    }
}
