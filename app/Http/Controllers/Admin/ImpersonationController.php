<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\ImpersonationSession;
use App\Models\Workspace;
use App\Services\Inbox\AuditLogger;
use App\Support\PlatformPermissions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Start / stop an impersonation session. Activating one closes any other
 * open session for this admin first — only one workspace can be in scope
 * at a time.
 *
 * Stopping doesn't delete the row; we keep the audit trail. The
 * ImpersonationBanner middleware looks for `ended_at IS NULL`.
 */
class ImpersonationController extends Controller
{
    public function start(Request $request, int $workspaceId): RedirectResponse
    {
        if (!PlatformPermissions::userCan($request->user(), 'platform.workspace.impersonate')) abort(403);

        $data = $request->validate([
            'reason' => 'required|string|min:8|max:500',
        ]);

        $ws = Workspace::findOrFail($workspaceId);

        // Close any prior open session for this admin.
        ImpersonationSession::active()->forAdmin($request->user()->id)
            ->update(['ended_at' => now()]);

        $session = ImpersonationSession::create([
            'admin_user_id'         => $request->user()->id,
            'target_workspace_id'   => $ws->id,
            'original_workspace_id' => $request->user()->current_workspace_id,
            'reason'                => $data['reason'],
            'ip'                    => $request->ip(),
            'user_agent'            => mb_substr($request->userAgent() ?? '', 0, 500),
            'started_at'            => now(),
        ]);

        AuditLogger::platform('impersonation.started', $request->user()->id, $ws->id, 'workspace', $ws->id, [
            'reason' => $data['reason'], 'session_id' => $session->id,
        ]);

        // Bind the impersonation to THIS browser session only. The banner
        // middleware applies the workspace override solely when this key is
        // present, so a second admin (or a second tab/device signed into the
        // SAME account) is NOT dragged into the impersonation — their Team
        // Inbox keeps showing their own workspace. Without this, impersonation
        // was keyed to the admin *account*, so one person clicking "impersonate"
        // flipped everyone else's screen to the target mid-work.
        $request->session()->put('impersonation_session_id', $session->id);

        // Switch session workspace context so /team-inbox queries naturally
        // hit the target workspace's data. We don't persist this on the user
        // row — the banner middleware re-applies it on every request.
        return redirect('/team-inbox')->with('success', "Now viewing as {$ws->name}.");
    }

    public function stop(Request $request): RedirectResponse
    {
        // End the session THIS browser started (falls back to the latest open
        // one if the key is missing). Clearing the key first guarantees the
        // banner stops overriding this session on the very next request.
        $sessId  = $request->session()->pull('impersonation_session_id');
        $session = ImpersonationSession::active()->forAdmin($request->user()->id)
            ->when($sessId, fn ($q) => $q->whereKey($sessId))
            ->latest('started_at')->first();
        if (!$session) {
            return redirect('/admin/team-inbox');
        }

        $session->forceFill(['ended_at' => now()])->save();

        // Activity summary — counts every audit row this admin generated
        // inside the impersonation window, broken down by action prefix
        // ("workspace.*" → workspace, "conversation.*" → conversation).
        // Lets a Super Admin reviewing the audit trail see at a glance
        // what an impersonator actually did, without re-querying the
        // window themselves.
        $activity = AuditLog::query()
            ->byActor($request->user()->id)
            ->where('workspace_id', $session->target_workspace_id)
            ->where('created_at', '>=', $session->started_at)
            ->where('created_at', '<=', $session->ended_at)
            ->selectRaw('action, COUNT(*) as n')
            ->groupBy('action')
            ->orderByDesc('n')
            ->limit(50)
            ->pluck('n', 'action')
            ->all();

        $totalEvents = array_sum($activity);
        $byModule = [];
        foreach ($activity as $action => $n) {
            $module = strtolower(explode('.', $action)[0] ?? 'other');
            $byModule[$module] = ($byModule[$module] ?? 0) + (int) $n;
        }
        arsort($byModule);

        AuditLogger::platform('impersonation.stopped', $request->user()->id, $session->target_workspace_id, 'workspace', $session->target_workspace_id, [
            'session_id'        => $session->id,
            'duration_seconds'  => $session->started_at->diffInSeconds($session->ended_at),
            'total_events'      => $totalEvents,
            'events_by_action'  => $activity,
            'events_by_module'  => $byModule,
        ]);

        // Restore the admin's OWN workspace. The banner override is in-memory
        // only now, so nothing should have leaked — but heal any row that DID
        // leak before that safeguard existed: put current_workspace_id back to
        // where the admin was when they started (if they still belong to it),
        // else fall back to any workspace they actually own. Guards against the
        // admin being left "assigned" to the impersonated workspace.
        $admin  = $request->user();
        $origId = $session->original_workspace_id;
        if ($origId && $admin->workspaces()->where('workspaces.id', $origId)->exists()) {
            $admin->forceFill(['current_workspace_id' => (int) $origId])->save();
        } elseif ((int) $admin->current_workspace_id === (int) $session->target_workspace_id) {
            $fallback = $admin->workspaces()->first();
            $admin->forceFill(['current_workspace_id' => $fallback?->id])->save();
        }

        return redirect('/admin/team-inbox')->with('success', 'Impersonation stopped.');
    }
}
