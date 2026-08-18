<?php

namespace App\Http\Middleware;

use App\Models\SystemSetting;
use App\Models\Workspace;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

/**
 * Per-plan throttle for the customer REST API (/api/v1).
 *
 * Runs AFTER `auth.apikey` (which resolves the key + workspace). Resolves the
 * effective requests-per-minute for the caller's workspace plan:
 *   1. the plan's `api_rate_limit_per_minute` (add-ons stacked via effectiveLimit)
 *   2. else the global default `security.api_rate_limit_per_minute` (600)
 *   3. a value of 0 anywhere up the chain = unlimited (no throttle)
 *
 * Tiered limits are configured per package in /admin/packages; the global
 * default lives in /admin/security → API and webhooks.
 */
class ApiPlanRateLimit
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var \App\Models\ApiKey|null $key */
        $key = $request->attributes->get('api_key');
        if (!$key) {
            // auth.apikey already rejected the request — nothing to throttle.
            return $next($request);
        }

        $rpm = $this->resolveLimit($key->workspace_id);
        if ($rpm <= 0) {
            return $next($request); // 0 = unlimited
        }

        $bucket = 'apiv1:' . $key->id;

        if (RateLimiter::tooManyAttempts($bucket, $rpm)) {
            $retry = RateLimiter::availableIn($bucket);
            return response()->json([
                'error' => [
                    'code'    => 'rate_limited',
                    'message' => "Rate limit of {$rpm} requests/minute for your plan exceeded. Retry in {$retry}s.",
                ],
            ], 429)->withHeaders([
                'Retry-After'           => $retry,
                'X-RateLimit-Limit'     => $rpm,
                'X-RateLimit-Remaining' => 0,
            ]);
        }

        RateLimiter::hit($bucket, 60); // 60-second sliding window

        $response = $next($request);
        if (method_exists($response, 'headers')) {
            $response->headers->set('X-RateLimit-Limit', (string) $rpm);
            $response->headers->set('X-RateLimit-Remaining', (string) max(0, RateLimiter::remaining($bucket, $rpm)));
        }

        return $response;
    }

    /**
     * Effective RPM for a workspace. Returns 0 to mean "no throttle" (unlimited).
     *   • effectiveLimit() returns -1 for unlimited (platform admins / -1 add-on)
     *     → 0 (skip throttle).
     *   • a positive plan value (add-ons stacked) → that limit.
     *   • a 0/blank plan value → the global default
     *     (security.api_rate_limit_per_minute, 600 if unset); global 0 = unlimited.
     */
    private function resolveLimit(?int $workspaceId): int
    {
        if ($workspaceId) {
            $ws = Workspace::find($workspaceId);
            if ($ws) {
                $planRpm = (int) $ws->effectiveLimit('api_rate_limit_per_minute', 0);
                if ($planRpm < 0) return 0;          // -1 = unlimited
                if ($planRpm > 0) return $planRpm;   // explicit per-plan limit
            }
        }

        $global = (int) (SystemSetting::get('security.api_rate_limit_per_minute') ?? 600);
        return max(0, $global);
    }
}
