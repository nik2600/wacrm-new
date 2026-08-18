<?php

namespace App\Http\Middleware;

use App\Support\WorkspacePermissions;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gates a route by minimum workspace role. Usage:
 *
 *   ->middleware('workspace.role:manager')   // manager, admin, owner pass
 *   ->middleware('workspace.role:admin')     // admin, owner pass
 *
 * Hierarchy: owner > admin > manager > agent > viewer.
 *
 * For Agent/Viewer roles we redirect (not 403) to /team-inbox — their
 * actual home — so a stray bookmark or top-nav click lands them on the
 * surface they're supposed to use instead of a dead-end error page.
 *
 * For Manager+ we throw 403 because the failure is genuinely a permission
 * problem (they have most things; the missing route is the exception).
 */
class EnsureWorkspaceRole
{
    public function handle(Request $request, Closure $next, string $minRole = 'agent'): Response
    {
        $user = $request->user();
        if (!$user) {
            return redirect()->route('login');
        }

        // Platform admins (Spatie "Super Admin" / "Admin" OR legacy
        // users.role = 'admin') bypass workspace-role gates entirely.
        // They own the product; they should never be redirected away
        // from /dashboard or /chat just because they're not a member
        // of the workspace they happen to be viewing. Matches the
        // PlanLimitGuard::bypass() pattern used elsewhere.
        try {
            if (method_exists($user, 'isAdmin') && $user->isAdmin()) {
                return $next($request);
            }
        } catch (\Throwable $e) {}
        if (in_array($user->role ?? null, ['admin', 'super-admin', 'super_admin', 'platform-admin'], true)) {
            return $next($request);
        }

        $role = $user->workspaceRole();
        if (!$role) {
            // Brand-new account that hasn't created its first workspace yet
            // (mid-registration) — send them to finish onboarding (step 2)
            // instead of stranding them on /team-inbox.
            if ((int) $user->workspaces()->count() === 0) {
                return redirect()->route('register.workspace');
            }
            // Has a workspace but no role on the CURRENT one (e.g. switched
            // into one they aren't a member of) — team-inbox empty state.
            return redirect('/team-inbox');
        }

        $userRank   = WorkspacePermissions::HIERARCHY[$role]     ?? 0;
        $minRank    = WorkspacePermissions::HIERARCHY[$minRole]  ?? 0;

        if ($userRank >= $minRank) {
            return $next($request);
        }

        // Below the bar. Agents/Viewers get redirected, others get 403.
        if (in_array($role, [WorkspacePermissions::ROLE_AGENT, WorkspacePermissions::ROLE_VIEWER], true)) {
            return redirect('/team-inbox')->with('warning', "Your role ({$role}) doesn't have access to that page.");
        }

        abort(403, "Your role ({$role}) doesn't have access to that page.");
    }
}
