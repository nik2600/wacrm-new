<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Admin-side CRUD for Spatie roles.
 *
 * Permissions are referenced by their string `name` in the form payload —
 * matches the prototype blade views in resources/views/admin/roles/* which
 * already render `<input name="permissions[]" value="users.view">` etc.
 */
class RoleController extends Controller
{
    public function index(Request $request)
    {
        $roles = Role::with('permissions')
            ->withCount(['permissions', 'users'])
            ->orderBy('id')
            ->get();

        return view('admin.roles.index', compact('roles'));
    }

    public function create()
    {
        $permissions = Permission::orderBy('name')->get();

        // Group by the prefix before the first "." so the blade view can render
        // each module's permissions together (e.g. "users", "campaigns" ...).
        $grouped = $permissions->groupBy(fn ($p) => Str::before($p->name, '.'));

        return view('admin.roles.create', [
            'permissions'        => $permissions,
            'permissionsGrouped' => $grouped,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'          => 'required|string|max:191|unique:roles,name',
            'permissions'   => 'array',
            'permissions.*' => 'string',
        ]);

        $role = Role::create([
            'name'       => $validated['name'],
            'guard_name' => 'web',
        ]);

        if (!empty($validated['permissions'])) {
            // Resolve permission names → Permission models, ignore unknowns.
            $perms = Permission::whereIn('name', $validated['permissions'])->get();
            $role->syncPermissions($perms);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        if ($request->wantsJson()) {
            return response()->json([
                'ok'      => true,
                'message' => 'Role created.',
                'role'    => [
                    'id'          => $role->id,
                    'name'        => $role->name,
                    'permissions' => $role->permissions->pluck('name'),
                ],
            ]);
        }

        return redirect()
            ->route('admin.roles.index')
            ->with('status', 'Role "' . $role->name . '" created.');
    }

    public function edit(string $id)
    {
        $role         = Role::with('permissions')->findOrFail($id);
        $permissions  = Permission::orderBy('name')->get();
        $grouped      = $permissions->groupBy(fn ($p) => Str::before($p->name, '.'));
        // Selected permissions as a flat array of names so the blade
        // can do `in_array($perm->name, $rolePermissions)`.
        $rolePermissions = $role->permissions->pluck('name')->all();

        return view('admin.roles.edit', [
            'role'              => $role,
            'permissions'       => $permissions,
            'permissionsGrouped' => $grouped,
            'rolePermissions'   => $rolePermissions,
            // Targets for the "Reassign all users" action — every role but this one.
            'otherRoles'        => Role::where('id', '!=', $role->id)->orderBy('name')->get(),
        ]);
    }

    /**
     * Clone a role (name + its full permission set) into a new "(copy)"
     * role and drop the admin on the copy's edit screen to tweak it.
     */
    public function duplicate(Request $request, string $id)
    {
        $role = Role::with('permissions')->findOrFail($id);

        $name = $role->name . ' (copy)';
        $n = 2;
        while (Role::where('name', $name)->exists()) {
            $name = $role->name . ' (copy ' . $n++ . ')';
        }

        $copy = Role::create(['name' => $name, 'guard_name' => $role->guard_name ?: 'web']);
        $copy->syncPermissions($role->permissions);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        \App\Support\Audit::log('admin.role.duplicated', ['layer' => 'platform', 'meta' => ['from' => $role->name, 'to' => $name]]);

        return redirect()->route('admin.roles.edit', $copy->id)
            ->with('status', 'Role duplicated as "' . $name . '". Edit the copy below.');
    }

    /**
     * Move every user currently on this role onto a different role, then
     * leave this role empty (e.g. before deleting it).
     */
    public function reassign(Request $request, string $id)
    {
        $role = Role::findOrFail($id);
        $data = $request->validate([
            'target_role_id' => 'required|integer|exists:roles,id',
        ]);
        if ((int) $data['target_role_id'] === (int) $id) {
            return back()->withErrors(['target_role_id' => 'Pick a different role to move users into.']);
        }

        $target = Role::findOrFail($data['target_role_id']);
        $moved = 0;
        foreach ($role->users()->get() as $user) {
            $user->removeRole($role);
            $user->assignRole($target);
            $moved++;
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        \App\Support\Audit::log('admin.role.reassigned', [
            'layer' => 'platform', 'result' => 'warning',
            'meta'  => ['from' => $role->name, 'to' => $target->name, 'users' => $moved],
        ]);

        return back()->with('status', $moved . ' user(s) moved from "' . $role->name . '" to "' . $target->name . '".');
    }

    public function update(Request $request, string $id)
    {
        $role = Role::findOrFail($id);

        $validated = $request->validate([
            'name'          => 'required|string|max:191|unique:roles,name,' . $id,
            'permissions'   => 'array',
            'permissions.*' => 'string',
        ]);

        $role->name = $validated['name'];
        $role->save();

        $perms = !empty($validated['permissions'])
            ? Permission::whereIn('name', $validated['permissions'])->get()
            : collect();

        $role->syncPermissions($perms);

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        if ($request->wantsJson()) {
            return response()->json([
                'ok'      => true,
                'message' => 'Role updated.',
                'role'    => [
                    'id'          => $role->id,
                    'name'        => $role->name,
                    'permissions' => $role->permissions->pluck('name'),
                ],
            ]);
        }

        return redirect()
            ->route('admin.roles.index')
            ->with('status', 'Role "' . $role->name . '" updated.');
    }

    public function destroy(Request $request, string $id)
    {
        $role = Role::findOrFail($id);

        // Block destructive deletes on the all-powerful role so an admin
        // can never accidentally lock everyone out of the platform.
        if ($role->name === 'Super Admin') {
            $message = 'Super Admin role is system-locked and cannot be deleted.';
            if ($request->wantsJson()) {
                return response()->json(['ok' => false, 'message' => $message], 422);
            }
            return back()->withErrors(['role' => $message]);
        }

        $name = $role->name;
        $role->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        if ($request->wantsJson()) {
            return response()->json([
                'ok'      => true,
                'message' => 'Role "' . $name . '" deleted.',
                'id'      => (int) $id,
            ]);
        }

        return redirect()
            ->route('admin.roles.index')
            ->with('status', 'Role "' . $name . '" deleted.');
    }
}
