<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Free-trial hard gate.
 *
 * When the current workspace is on a FREE plan whose trial window has
 * elapsed (Workspace::trialExpired()), the operator can no longer USE
 * any feature — every feature route bounces to /account/plans until they
 * buy a plan. The features stay visible in the nav (so they can see what
 * they'd unlock), but opening one or POSTing to it is blocked.
 *
 * Deliberately NOT gated (so the user can actually recover):
 *   - the plan picker + checkout + payment callbacks (to buy a plan)
 *   - account settings, invoices, support, logout, workspace switching
 *   - the registration onboarding steps
 *
 * Platform admins bypass entirely (same rule as PlanLimitGuard). Paid
 * plans, and free plans with no expiry, are never "expired" so this is a
 * no-op for them. Fails OPEN: any error lets the request through so a
 * bug here can never lock the whole app.
 */
class EnsureTrialActive
{
    /**
     * Route-name patterns (Str::is globs) that stay reachable while a
     * trial is expired. Kept broad on purpose — better to leave one extra
     * billing/account route open than to trap a user who's trying to pay.
     */
    private const ALLOWED = [
        'account*',          // /account, /account/plans, profile, billing, invoices
        '*checkout*',        // user.checkout.* and checkout.* (pages controller)
        '*payment*',         // payment.callback / payment.webhook
        '*invoice*',         // invoice download
        '*support*',         // raise/answer support tickets
        'register*',         // onboarding continuation
        'verification*',     // email verification
        '*workspaces*',      // switch to another (non-expired) workspace
        'logout',
        'frontend.*',        // public marketing/pricing pages
        'home',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        // No DB before install — skip the trial gate (reads user/workspace/plan).
        if (! is_file(storage_path('installed'))) return $next($request);

        try {
            $user = Auth::user();
            if (!$user) return $next($request);
            if ($this->isAdmin($user)) return $next($request);
            if ($request->routeIs(...self::ALLOWED)) return $next($request);

            $ws = $user->currentWorkspace;
            if (!$ws || !$ws->trialExpired()) return $next($request);

            // Trial is over — block the feature.
            $plansUrl = route('account.plans');
            $message  = __('Your free trial has ended. Choose a plan to keep using your workspace.');

            if ($request->expectsJson() || $request->ajax()) {
                return response()->json([
                    'message'     => $message,
                    'upgrade_url' => $plansUrl,
                    'trial'       => 'expired',
                ], 402); // Payment Required
            }

            return redirect()->to($plansUrl)->with('warning', $message);
        } catch (\Throwable $e) {
            // Fail open — never let a gate bug break the app.
            return $next($request);
        }
    }

    /** Mirror PlanLimitGuard::bypass so the gate and the limit bypass agree. */
    private function isAdmin($user): bool
    {
        try {
            if ($user->hasRole('Super Admin') || $user->hasRole('Admin')) return true;
        } catch (\Throwable $e) {}
        return in_array($user->role ?? null, ['admin', 'A', 'super-admin', 'platform-admin'], true);
    }
}
