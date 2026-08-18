<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Seeds the Spatie roles + permissions used by the admin console.
 *
 * Permission naming follows `<category>.<action>` to match the prototype
 * blade views in resources/views/admin/roles/* and the canonical category
 * list specified by the project (users, roles, permissions, workspaces,
 * devices, packages, campaigns, templates, flows, webhooks, integrations,
 * contacts, analytics, settings, support, plus the prototype-extra
 * metaads / inbox / billing / audit / security buckets).
 */
class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        // Reset cached roles + permissions so re-seeding sees the new state.
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        // -----------------------------------------------------------------
        // 1. Canonical permission set
        // -----------------------------------------------------------------
        // Matches the strings emitted by resources/views/admin/roles/create.blade.php
        // and resources/views/admin/roles/edit.blade.php — keeping the names in
        // sync means the prototype checkboxes save correctly without redesign.
        $crud = ['view', 'create', 'edit', 'delete'];

        $permissions = [
            // Users — extra import / login_as flags from the prototype.
            'users.view', 'users.create', 'users.edit', 'users.delete',
            'users.import', 'users.login_as',

            // Roles & permissions.
            'roles.view', 'roles.create', 'roles.edit', 'roles.delete',
            'permissions.view', 'permissions.create', 'permissions.edit',
            'permissions.delete', 'permissions.manage',

            // Workspaces.
            'workspaces.view', 'workspaces.create', 'workspaces.edit',
            'workspaces.delete', 'workspaces.suspend',

            // Devices.
            'devices.view', 'devices.create', 'devices.edit', 'devices.delete',
            'devices.pair', 'devices.disconnect',

            // Packages.
            'packages.view', 'packages.create', 'packages.edit', 'packages.delete',

            // Campaigns.
            'campaigns.view', 'campaigns.create', 'campaigns.edit', 'campaigns.delete',
            'campaigns.send',

            // Meta Ads (separate prototype module).
            'metaads.view', 'metaads.create', 'metaads.edit', 'metaads.delete',
            'metaads.approve', 'metaads.refund',

            // Templates / flows / webhooks / integrations.
            'templates.view', 'templates.create', 'templates.edit', 'templates.delete',
            'flows.view', 'flows.create', 'flows.edit', 'flows.delete',
            'webhooks.view', 'webhooks.create', 'webhooks.edit', 'webhooks.delete',
            'integrations.view', 'integrations.create', 'integrations.edit', 'integrations.delete',

            // Contacts / analytics.
            'contacts.view', 'contacts.create', 'contacts.edit', 'contacts.delete',
            'analytics.view', 'analytics.create', 'analytics.edit', 'analytics.delete',

            // Inbox (prototype extra).
            'inbox.view', 'inbox.reply', 'inbox.assign', 'inbox.export',

            // Platform tier (team-inbox SaaS-operator perms).
            // Granted directly to "Super Admin" / "Platform Support" / "Auditor".
            // These are the only permissions checked by the EnsurePlatformRole
            // middleware and ImpersonationController — adding here keeps a
            // single source of truth.
            'platform.workspace.view_all', 'platform.workspace.suspend',
            'platform.workspace.unsuspend', 'platform.workspace.impersonate',
            'platform.workspace.flag', 'platform.workspace.unflag',
            'platform.conversation.view_all', 'platform.conversation.flag_spam',
            'platform.note.write', 'platform.audit.view',
            'platform.user.view_all', 'platform.user.suspend',
            'platform.role.manage', 'platform.permission.manage',
            'platform.billing.view', 'platform.system.settings',

            // Billing (prototype extra).
            'billing.view', 'billing.invoices', 'billing.refund', 'billing.cards',

            // Audit + security (prototype extra).
            'audit.view', 'audit.export',
            'security.view', 'security.edit',

            // Settings.
            'settings.view', 'settings.create', 'settings.edit', 'settings.delete',

            // Support.
            'support.view', 'support.create', 'support.edit', 'support.delete',
        ];

        foreach ($permissions as $name) {
            Permission::firstOrCreate(
                ['name' => $name, 'guard_name' => 'web']
            );
        }

        // -----------------------------------------------------------------
        // 2. Roles
        // -----------------------------------------------------------------
        $superAdmin       = Role::firstOrCreate(['name' => 'Super Admin',       'guard_name' => 'web']);
        $admin            = Role::firstOrCreate(['name' => 'Admin',             'guard_name' => 'web']);
        $manager          = Role::firstOrCreate(['name' => 'Manager',           'guard_name' => 'web']);
        $userRole         = Role::firstOrCreate(['name' => 'User',              'guard_name' => 'web']);
        $platformSupport  = Role::firstOrCreate(['name' => 'Platform Support',  'guard_name' => 'web']);
        $auditor          = Role::firstOrCreate(['name' => 'Auditor',           'guard_name' => 'web']);

        // Reload the freshly-seeded permissions for accurate assignment.
        $allPermissions = Permission::where('guard_name', 'web')->get();

        // -----------------------------------------------------------------
        // 3. Super Admin + Admin → all permissions.
        // -----------------------------------------------------------------
        $superAdmin->syncPermissions($allPermissions);
        $admin->syncPermissions($allPermissions);

        // -----------------------------------------------------------------
        // 4. Manager → everything view + edit (no create/delete by default).
        //    Keeps the role useful but non-destructive.
        // -----------------------------------------------------------------
        $managerPerms = $allPermissions->filter(function ($p) {
            $action = Str::after($p->name, '.');
            return in_array($action, ['view', 'edit', 'reply', 'assign', 'invoices', 'export'], true);
        });
        $manager->syncPermissions($managerPerms);

        // -----------------------------------------------------------------
        // 5. User → only `*.view` permissions.
        // -----------------------------------------------------------------
        $viewPerms = $allPermissions->filter(fn ($p) => Str::endsWith($p->name, '.view'));
        $userRole->syncPermissions($viewPerms);

        // -----------------------------------------------------------------
        // 5b. Platform Support → operate the SaaS without destructive auth.
        //     Read all workspaces, impersonate, flag/note, view audit log,
        //     but no role/permission management or billing settings.
        // -----------------------------------------------------------------
        $platformSupport->syncPermissions($allPermissions->filter(fn ($p) => in_array($p->name, [
            'platform.workspace.view_all', 'platform.workspace.impersonate',
            'platform.workspace.flag', 'platform.workspace.unflag',
            'platform.conversation.view_all', 'platform.conversation.flag_spam',
            'platform.note.write', 'platform.audit.view',
            'platform.user.view_all',
        ], true)));

        // -----------------------------------------------------------------
        // 5c. Auditor → read-only across the platform. No impersonation.
        //     For compliance reviews and external audits.
        // -----------------------------------------------------------------
        $auditor->syncPermissions($allPermissions->filter(fn ($p) => in_array($p->name, [
            'platform.workspace.view_all',
            'platform.conversation.view_all',
            'platform.audit.view',
            'platform.user.view_all',
        ], true)));

        // -----------------------------------------------------------------
        // 6. Promote the seeded admin user to Super Admin.
        // -----------------------------------------------------------------
        $testUser = User::where('email', 'test@example.com')->first();
        if ($testUser) {
            $testUser->syncRoles(['Super Admin']);
        }

        // Force a fresh registrar cache so subsequent calls see new state.
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->command?->info(sprintf(
            'RolePermissionSeeder: %d permissions, %d roles seeded.',
            Permission::count(),
            Role::count()
        ));
    }
}
