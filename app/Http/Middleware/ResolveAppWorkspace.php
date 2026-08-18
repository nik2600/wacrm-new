<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

/**
 * Mobile-app workspace/device scoping.
 *
 * A browser session carries the active workspace in users.current_workspace_id,
 * flipped by the header dropdown. The Flutter app has no session — one Bearer
 * token, many workspaces — so it tells us which workspace (and, optionally,
 * which device) a request is about via headers:
 *
 *   X-Workspace-Id: <id>     (fallback: `workspace_id` in the body/query)
 *   X-Device-Id:    <id>     (fallback: `device_id`   in the body/query)
 *
 * When X-Workspace-Id is present we VERIFY the caller is a joined member of that
 * workspace, then set current_workspace_id IN MEMORY for this request only (no
 * save) so every existing `forCurrentWorkspace()` scope + `current_workspace_id`
 * read resolves to the requested workspace. No header → the token's stored
 * current_workspace_id is used, exactly as before (so /workspaces itself, and
 * every legacy call, keep working). A header naming a workspace the user isn't a
 * member of is a hard 403 — never a silent cross-tenant leak.
 *
 * X-Device-Id is validated to belong to the resolved workspace and stashed on
 * the request (`app_device_id`) for endpoints that want an implicit device; it
 * never hard-fails when absent (most device endpoints take the id in the path).
 */
class ResolveAppWorkspace
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (! $user) {
            return $next($request);   // auth:sanctum handles the 401 before us
        }

        $wsId = (int) ($request->header('X-Workspace-Id') ?: $request->input('workspace_id') ?: 0);
        if ($wsId > 0) {
            $isMember = DB::table('workspace_user')
                ->where('workspace_id', $wsId)
                ->where('user_id', $user->id)
                ->whereNotNull('joined_at')
                ->exists();

            if (! $isMember) {
                return response()->json([
                    'success' => false,
                    'message' => 'You are not a member of that workspace.',
                ], 403);
            }

            // Scope THIS request ONLY. Set it in memory, then immediately sync the
            // attribute's "original" so Eloquent treats it as NOT dirty. Without
            // this, any unrelated $user->save() later in the request (e.g. a
            // profile update) would PERSIST the app's chosen workspace into
            // users.current_workspace_id — the SAME column the WEB dashboard reads
            // — and silently switch the user's web session to another workspace
            // (their devices/number would "vanish" from the web). The app is
            // header-driven and must NEVER write that column.
            $user->current_workspace_id = $wsId;
            $user->syncOriginalAttribute('current_workspace_id');
        }

        $inputDevice  = (int) ($request->input('device_id') ?: 0);
        $headerDevice = (int) ($request->header('X-Device-Id') ?: 0);
        $deviceId     = $inputDevice ?: $headerDevice;
        if ($deviceId > 0) {
            // Only trust a device the resolved workspace actually owns.
            $ok = \App\Models\Device::query()
                ->forWorkspace((int) $user->current_workspace_id, (int) $user->id)
                ->whereKey($deviceId)
                ->exists();
            if ($ok) {
                $request->attributes->set('app_device_id', $deviceId);
                // Selected via header (not already in the body): expose it as the
                // default `device_id` input so EVERY endpoint that reads device_id
                // (quick-message, campaigns, chats, groups, autoreplies) sends from
                // the picked device/engine — the multi-device/multi-engine parity
                // the web gets from its device picker. An explicit body value wins.
                if ($inputDevice === 0) {
                    $request->merge(['device_id' => $deviceId]);
                }
            }
        }

        return $next($request);
    }
}
