<?php

namespace App\Http\Controllers\Api\App;

use App\Http\Controllers\Controller;
use App\Models\Device;
use App\Models\Order;
use App\Models\Workspace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Mobile-app Workspaces.
 *
 * After login the app calls GET /workspaces to learn which workspaces the user
 * belongs to, each with its plan/account summary and its connected devices —
 * the same information the WaDesk web header dropdown + devices page show. The
 * app then sends `X-Workspace-Id` (and optionally `X-Device-Id`) on every other
 * request so it's scoped to the chosen workspace (see ResolveAppWorkspace).
 */
class WorkspaceController extends Controller
{
    /** GET /workspaces — all workspaces the user is a joined member of. */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $workspaces = $user->workspaces()
            ->wherePivotNotNull('joined_at')
            ->orderBy('workspaces.name')
            ->get()
            ->map(fn (Workspace $ws) => $this->workspacePayload($ws, $user))
            ->values();

        return response()->json([
            'success' => true,
            'data'    => [
                'current_workspace_id' => (int) $user->current_workspace_id ?: null,
                'workspaces'           => $workspaces,
            ],
        ], 200);
    }

    /** GET /workspaces/{id} — one workspace's detail (membership enforced). */
    public function show(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $ws   = $this->memberWorkspace($user, $id);
        if (! $ws) {
            return response()->json(['success' => false, 'message' => 'Workspace not found.'], 404);
        }

        return response()->json([
            'success' => true,
            'data'    => $this->workspacePayload($ws, $user, withDevices: true),
        ], 200);
    }

    /**
     * POST /workspaces — create a new workspace owned by the caller. Reuses the
     * registration provisioner (same plan + trial + owner membership), then
     * applies the chosen name/details. The new workspace becomes active.
     */
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();
        $data = $request->validate([
            'name'       => 'required|string|max:120',
            'timezone'   => 'nullable|string|max:64',
            'industry'   => 'nullable|string|max:64',
            'size_range' => 'nullable|string|max:32',
        ]);

        // Plan cap — workspaces per owner (same gate the web onboarding uses).
        $limit = null;
        try { $limit = $user->currentWorkspace?->effectiveLimit('workspaces_per_owner_limit'); } catch (\Throwable $e) {}
        if (is_numeric($limit) && (int) $limit > 0
            && Workspace::where('owner_user_id', $user->id)->count() >= (int) $limit) {
            return response()->json([
                'success' => false,
                'message' => 'You have reached the workspace limit for your plan.',
            ], 403);
        }

        // The WEB and the app share users.current_workspace_id. provision() calls
        // switchWorkspace() (persists the new ws as current) — that would yank the
        // user's WEB session onto the new workspace. Capture what the web had, so
        // we can put it back after.
        $webCurrent = (int) \Illuminate\Support\Facades\DB::table('users')
            ->where('id', $user->id)->value('current_workspace_id');

        // provision() = consistent plan/trial + owner membership, but auto-names it
        // "<name>'s Workspace" — override with the chosen name.
        $ws = app(\App\Services\WorkspaceProvisioner::class)->provision($user);
        $ws->update([
            'name'       => $data['name'],
            'slug'       => Workspace::generateSlug($data['name']),
            'timezone'   => $data['timezone']   ?? $ws->timezone,
            'industry'   => $data['industry']   ?? null,
            'size_range' => $data['size_range'] ?? null,
        ]);

        // Undo provision's web-facing switch: the persisted column goes back to
        // what the web had, so the web session is untouched. The app just points
        // its X-Workspace-Id header at the new id when it wants to use it.
        \Illuminate\Support\Facades\DB::table('users')
            ->where('id', $user->id)
            ->update(['current_workspace_id' => $webCurrent ?: null]);
        // For THIS response only, show the new workspace as the app's current
        // (in memory, not persisted — same non-dirty trick as the middleware).
        $user->current_workspace_id = $ws->id;
        $user->syncOriginalAttribute('current_workspace_id');

        // Re-fetch through the membership relation so the pivot (role) is loaded.
        $fresh = $user->workspaces()->whereKey($ws->id)->first() ?? $ws;

        return response()->json([
            'success' => true,
            'message' => 'Workspace created.',
            'data'    => $this->workspacePayload($fresh, $user),
        ], 201);
    }

    /**
     * PUT/PATCH /workspaces/{id} — edit workspace details. Owner/admin only.
     * The slug is intentionally NOT changed on rename so existing links stay
     * stable.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $ws   = $this->memberWorkspace($user, $id);
        if (! $ws) {
            return response()->json(['success' => false, 'message' => 'Workspace not found.'], 404);
        }

        $role = $ws->pivot->role ?? null;
        if ((int) $ws->owner_user_id !== (int) $user->id && ! in_array($role, ['owner', 'admin'], true)) {
            return response()->json([
                'success' => false,
                'message' => 'Only the workspace owner or an admin can edit it.',
            ], 403);
        }

        $data = $request->validate([
            'name'       => 'sometimes|required|string|max:120',
            'timezone'   => 'sometimes|nullable|string|max:64',
            'industry'   => 'sometimes|nullable|string|max:64',
            'size_range' => 'sometimes|nullable|string|max:32',
        ]);

        foreach (['name', 'timezone', 'industry', 'size_range'] as $k) {
            if (array_key_exists($k, $data)) {
                $ws->{$k} = $data[$k];
            }
        }
        $ws->save();

        return response()->json([
            'success' => true,
            'message' => 'Workspace updated.',
            'data'    => $this->workspacePayload($ws, $user),
        ], 200);
    }

    /** Resolve a workspace the user is a joined member of, else null. */
    private function memberWorkspace($user, int $id): ?Workspace
    {
        return $user->workspaces()
            ->wherePivotNotNull('joined_at')
            ->whereKey($id)
            ->first();
    }

    /** One workspace → account/plan summary + (optionally full) device list. */
    private function workspacePayload(Workspace $ws, $user, bool $withDevices = true): array
    {
        $deviceQuery = Device::query()
            ->forWorkspace($ws->id, (int) $user->id)
            ->orderByDesc('active')
            ->orderByDesc('id');

        $devices = $withDevices
            ? $deviceQuery->get()->map(fn (Device $d) => $this->devicePayload($d))->values()
            : collect();

        return [
            'id'           => $ws->id,
            'name'         => $ws->name,
            'slug'         => $ws->slug,
            // The caller's role in THIS workspace (owner / admin / agent / …).
            'role'         => $ws->pivot->role ?? null,
            'is_current'   => (int) $user->current_workspace_id === (int) $ws->id,
            'timezone'     => $ws->timezone ?? null,
            'plan'         => $this->planSummary($ws, $user),
            'device_count' => (clone $deviceQuery)->count(),
            'devices'      => $devices,
        ];
    }

    /** Plan / account summary for a workspace — mirrors the app's `order` block. */
    private function planSummary(Workspace $ws, $user): ?array
    {
        try {
            $package = method_exists($ws, 'package') ? $ws->package() : null;
            if (! $package) {
                return null;
            }
            $end   = $ws->trial_ends_at ?? ($ws->plan_ends_at ?? null);
            $limit = 0;
            try { $limit = (int) $ws->effectiveLimit('monthly_messages_limit'); } catch (\Throwable $e) {}
            $order = Order::where('user_id', $user->id)->latest()->first();

            return [
                'plan_name'        => $package->pname ?? null,
                'end_date'         => $end ? $end->format('d M Y') : null,
                'messages_limit'   => $limit ?: null,
                'is_active'        => method_exists($ws, 'planIsActive') ? $ws->planIsActive() : null,
                'amount'           => $order->amount ?? null,
                'currency'         => $order->currency ?? null,
                'symbol'           => $order->currency_symbol ?? ($order->symbol ?? null),
            ];
        } catch (\Throwable $e) {
            return null;
        }
    }

    /** Compact device summary — enough for the picker; /get-devices has the full shape. */
    private function devicePayload(Device $d): array
    {
        $full = preg_replace('/\D+/', '', (string) ($d->country_code . $d->phone_number));

        return [
            'id'            => $d->id,
            'name'          => $d->device_name,
            'phone_number'  => $full ?: null,
            'region'        => $d->region,
            'status'        => $d->status,
            'active'        => (bool) $d->active,
            'last_seen_at'  => $d->last_seen_at?->toIso8601String(),
        ];
    }
}
