<?php

namespace App\Http\Middleware;

use App\Models\ImpersonationSession;
use App\Support\PlatformPermissions;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * If the authenticated user has an open impersonation session, share the
 * banner data with all views (so layouts can render the yellow stop-strip)
 * and ensure their `current_workspace_id` is pointed at the impersonation
 * target — that way every workspace-scoped query naturally returns the
 * target workspace's data without any per-controller branching.
 */
class ImpersonationBanner
{
    public function handle(Request $request, Closure $next): Response
    {
        // Before the app is installed there is no database, so resolving the
        // user (a `select * from users` via the session guard) would throw and
        // crash the installer itself. Skip entirely until install completes.
        if (! is_file(storage_path('installed'))) return $next($request);

        $user = $request->user();
        if (!$user) return $next($request);

        // Impersonation is bound to the BROWSER SESSION that started it (the id
        // stashed by ImpersonationController::start), NOT the admin account. So
        // a second admin — or a second tab/device on the SAME account — is not
        // pulled into it: with no key in THEIR session, the override below never
        // runs and their Team Inbox stays on their own workspace. This is what
        // stops the "someone else impersonated and my inbox suddenly changed to
        // that customer" hijack.
        $sessId = $request->hasSession() ? $request->session()->get('impersonation_session_id') : null;
        if (!$sessId) {
            view()->share('impersonation', null);
            return $next($request);
        }

        $session = ImpersonationSession::active()
            ->forAdmin($user->id)
            ->whereKey($sessId)
            ->first();

        if (!$session) {
            // Key points at a closed / unknown session — clear it so we don't
            // re-check every request, and fall through as a normal session.
            if ($request->hasSession()) $request->session()->forget('impersonation_session_id');
            view()->share('impersonation', null);
            return $next($request);
        }

        // Re-verify LIVE platform access on every request. ImpersonationController
        // gates only at start(), so an admin whose platform role was revoked mid
        // session would otherwise keep the target-workspace override until the row
        // is ended. If they no longer hold the impersonate capability, force-close
        // the session immediately and fall through as a normal (own-workspace) user.
        if (!PlatformPermissions::userCan($user, 'platform.workspace.impersonate')) {
            $session->forceFill(['ended_at' => now()])->save();
            if ($request->hasSession()) $request->session()->forget('impersonation_session_id');
            view()->share('impersonation', null);
            return $next($request);
        }

        // Force the in-memory user state to point at the target workspace so
        // every workspace-scoped query returns the target's data — WITHOUT
        // ever persisting it. setAttribute() alone marks the column dirty, so
        // ANY incidental $user->save() later in the request (e.g. the
        // email-verify heal in EnsureUserActive, presence touches) would flush
        // the target id onto the admin's own row — the "impersonated workspace
        // got assigned to me" leak. syncOriginalAttribute() re-baselines the
        // column as clean, so reads see the target but no save writes it back.
        if ($user->current_workspace_id !== (int) $session->target_workspace_id) {
            $user->setAttribute('current_workspace_id', (int) $session->target_workspace_id);
            $user->syncOriginalAttribute('current_workspace_id');
        }

        view()->share('impersonation', [
            'active'                => true,
            'admin_user_id'         => $session->admin_user_id,
            'target_workspace_id'   => $session->target_workspace_id,
            'target_workspace_name' => optional($session->targetWorkspace)->name,
            'reason'                => $session->reason,
            'started_at'            => $session->started_at,
            'session_id'            => $session->id,
        ]);

        return $next($request);
    }
}
