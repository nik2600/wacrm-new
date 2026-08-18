<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Bounces suspended / unverified users away from the user dashboard.
 *
 * Trashed accounts: SoftDeletes already hides them from Auth::user(),
 * so their session token resolves to NULL and the auth middleware sends
 * them to /login. No interception needed here.
 *
 * Suspended (role === 'suspended'): redirect to /account/suspended.
 * Unverified (email_verified_at IS NULL): redirect to /account/verify-email.
 *
 * Admins are exempt — they can still operate the console.
 */
class EnsureUserActive
{
    /**
     * Routes a locked user is still allowed to hit. Register-flow
     * continuation routes (workspace + plan steps) are listed here
     * too — a brand-new account has email_verified_at=NULL right after
     * the /register POST, and without this allowlist the middleware
     * would yank the user straight to /account/verify-email after step 1,
     * skipping the workspace + plan onboarding steps entirely.
     */
    private const ALLOWED_ROUTES = [
        'account.suspended', 'account.verify-email',
        'logout', 'verification.send', 'verification.verify',
        'register.workspace', 'register.workspace.store',
        'register.plan', 'register.plan.skip',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        // No DB before install — skip (resolving the user hits `users`).
        if (! is_file(storage_path('installed'))) return $next($request);

        $user = Auth::user();
        if (!$user) return $next($request);

        if ($this->isAdmin($user)) return $next($request);
        if ($request->routeIs(...self::ALLOWED_ROUTES)) return $next($request);

        if (($user->role ?? null) === 'suspended') {
            return redirect()->route('account.suspended');
        }
        if (!$user->email_verified_at) {
            // Honour the platform-wide auto-verify toggle (admin sets it
            // at /admin/settings/general → "Auto-verify email on signup").
            // When ON we stamp the row in passing so future requests skip
            // the lookup entirely — saves one SystemSetting hit per page
            // and turns the flag into a permanent fix for legacy users.
            try {
                if ((bool) \App\Models\SystemSetting::get('auto_verify_email', true)) {
                    $user->forceFill(['email_verified_at' => now()])->save();
                    return $next($request);
                }
            } catch (\Throwable $e) { /* fall through to verify screen */ }
            return redirect()->route('account.verify-email');
        }
        return $next($request);
    }

    private function isAdmin($user): bool
    {
        return in_array((string) ($user->role ?? ''), ['admin', 'super-admin', 'platform-admin'], true);
    }
}
