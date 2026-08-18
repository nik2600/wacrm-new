<?php

namespace App\Http\Middleware;

use App\Models\SystemSetting;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Bounces an authenticated user to the 2FA setup screen when policy
 * requires it AND the user hasn't confirmed 2FA yet.
 *
 * Triggered policies (any TRUE bounces the user):
 *   security.require_2fa_for_all
 *   security.require_2fa_for_admins  (and user->role is admin/super-admin/support-admin)
 *   security.require_2fa_for_owners  (and user owns a workspace)
 *
 * Bypassed paths: /login, /logout, /2fa/*, /settings/2fa/*, asset/webhook URLs.
 * Only enforces during web (session) requests — APIs are token-auth and out
 * of scope here.
 */
class Force2FA
{
    public function handle(Request $request, Closure $next): Response
    {
        // No DB before install — skip (resolving the user hits `users`).
        if (! is_file(storage_path('installed'))) return $next($request);

        $user = $request->user();
        if (! $user || $user->two_factor_confirmed_at) {
            return $next($request);
        }

        // Don't bounce while user is mid-setup or signing out.
        $path = '/' . ltrim($request->path(), '/');
        $bypass = ['/login', '/logout', '/register', '/password', '/email'];
        foreach ($bypass as $p) {
            if (str_starts_with($path, $p)) return $next($request);
        }
        if (str_contains($path, '/2fa') || str_contains($path, '/two-factor')) {
            return $next($request);
        }

        // Policy lookup.
        $forAll    = (bool) SystemSetting::get('security.require_2fa_for_all', false);
        $forAdmins = (bool) SystemSetting::get('security.require_2fa_for_admins', false);
        $forOwners = (bool) SystemSetting::get('security.require_2fa_for_owners', false);

        $role     = strtolower((string) ($user->role ?? ''));
        $isAdmin  = in_array($role, ['admin', 'super_admin', 'super-admin', 'support_admin', 'support-admin'], true);
        $isOwner  = false;
        if (method_exists($user, 'ownedWorkspaces')) {
            try { $isOwner = $user->ownedWorkspaces()->exists(); } catch (\Throwable) {}
        }

        $shouldEnforce = $forAll || ($forAdmins && $isAdmin) || ($forOwners && $isOwner);
        if (! $shouldEnforce) {
            return $next($request);
        }

        // Bounce to the existing user-side 2FA settings tab — that's where
        // the enableTwoFactor() flow already lives.
        return redirect()->to('/settings?tab=security')
            ->with('warning', __('Two-factor authentication is required by your administrator. Please enable it to continue.'));
    }
}
