<?php

namespace App\Support;

use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Platform admins must own a private workspace so their session has a
 * scope that doesn't bleed customer data into the admin view.
 *
 * Without this, an admin whose `current_workspace_id` happens to point
 * at the first workspace in the table (default behavior when their own
 * pivot is empty) ends up seeing customer flows, devices, chats, etc.
 * Worse: anything they do on that page (creating a flow, sending a
 * message) implicitly modifies a tenant's data.
 *
 * Call ensureFor($user) on:
 *   - Login event (AdminAuthListener)
 *   - User::role('Admin') / User::role('Super Admin') changes
 *   - Manual `php artisan admin:provision` if you script it
 */
class AdminWorkspaceProvisioner
{
    /**
     * Guarantee the admin owns at least one workspace AND has it set as
     * their current_workspace_id. Idempotent — safe to call on every login.
     *
     * Returns the workspace (existing or freshly created).
     */
    public static function ensureFor(User $user): ?Workspace
    {
        // Not an admin? Nothing to do.
        $isAdmin = false;
        try {
            $isAdmin = method_exists($user, 'isAdmin') && $user->isAdmin();
        } catch (\Throwable $e) {}
        if (!$isAdmin) {
            $isAdmin = in_array(
                $user->role ?? null,
                ['admin', 'super-admin', 'super_admin', 'platform-admin'],
                true
            );
        }
        if (!$isAdmin) return null;

        $ws = Workspace::where('owner_user_id', $user->id)->first();
        if (!$ws) {
            $ws = Workspace::create([
                'name'          => trim($user->name . ' Admin'),
                'slug'          => Str::slug($user->name . '-admin') . '-' . substr(md5((string) $user->id), 0, 6),
                'owner_user_id' => $user->id,
                'status'        => 1,
                'currency'      => (string) \App\Models\SystemSetting::get('default_currency', 'USD'),
                'timezone'      => (string) \App\Models\SystemSetting::get('default_timezone', config('app.timezone', 'Asia/Kolkata')),
                'industry'      => 'Internal',
                'admin_note'    => 'Auto-created private workspace for platform admin. Keeps admin data isolated from customer workspaces.',
            ]);
        }

        // Ensure pivot row (owner). updateOrInsert is the only safe upsert
        // because workspace_user has no unique constraint on (ws_id,user_id).
        DB::table('workspace_user')->updateOrInsert(
            ['workspace_id' => $ws->id, 'user_id' => $user->id],
            ['role' => 'owner', 'joined_at' => now(), 'updated_at' => now(), 'created_at' => now()]
        );

        // Point admin at their own workspace if they're currently on
        // someone else's. Don't overwrite a deliberate workspace switch.
        if (! $user->current_workspace_id || $user->current_workspace_id === null) {
            $user->forceFill(['current_workspace_id' => $ws->id])->save();
        } else {
            // Was on another workspace owned by someone else? Bring them
            // home. Skip if they're already on one they own.
            $currentOwned = Workspace::where('id', $user->current_workspace_id)
                ->where('owner_user_id', $user->id)
                ->exists();
            if (!$currentOwned) {
                $user->forceFill(['current_workspace_id' => $ws->id])->save();
            }
        }

        return $ws;
    }
}
