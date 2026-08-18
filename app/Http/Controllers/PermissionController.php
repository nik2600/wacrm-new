<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * Admin-side CRUD for Spatie permissions.
 *
 * Permissions are usually only created or deleted (no edit screen) — once a
 * permission name is in use across roles, renaming it would silently
 * detach every role that references it, so we don't expose that surface.
 */
class PermissionController extends Controller
{
    public function index(Request $request)
    {
        $permissions = Permission::orderBy('name')->get();

        // Group by `<category>.*` prefix so the blade view can render each
        // module's permissions in its own card.
        $grouped = $permissions->groupBy(fn ($p) => Str::before($p->name, '.'));

        return view('admin.permissions.index', [
            'permissions'        => $permissions,
            'permissionsGrouped' => $grouped,
        ]);
    }

    public function create()
    {
        return view('admin.permissions.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'   => 'required|string|max:191',
            'module' => 'nullable|string|max:64',
            'action' => 'nullable|string|max:64',
        ]);

        // Allow either: (a) full name in `name`, or (b) module + action.
        $name = $validated['name'];
        if (!str_contains($name, '.') && !empty($validated['module']) && !empty($validated['action'])) {
            $name = trim($validated['module']) . '.' . trim($validated['action']);
        }

        $request->validate([
            'name' => 'unique:permissions,name',
        ], [], ['name' => 'permission name']);

        $permission = Permission::create([
            'name'       => $name,
            'guard_name' => 'web',
        ]);

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        if ($request->wantsJson()) {
            return response()->json([
                'ok'         => true,
                'message'    => 'Permission created.',
                'permission' => [
                    'id'   => $permission->id,
                    'name' => $permission->name,
                ],
            ]);
        }

        return redirect()
            ->route('admin.permissions.index')
            ->with('status', 'Permission "' . $permission->name . '" created.');
    }

    public function destroy(Request $request, string $id)
    {
        $permission = Permission::findOrFail($id);
        $name       = $permission->name;

        $permission->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        if ($request->wantsJson()) {
            return response()->json([
                'ok'      => true,
                'message' => 'Permission "' . $name . '" deleted.',
                'id'      => (int) $id,
            ]);
        }

        return redirect()
            ->route('admin.permissions.index')
            ->with('status', 'Permission "' . $name . '" deleted.');
    }
}
