<?php

namespace App\Support;

use App\Models\User;

/**
 * Platform (Spatie) permissions. Unlike WorkspacePermissions these ARE backed
 * by the spatie/laravel-permission tables — they're global, granted directly
 * to the User model. Three named roles: SuperAdmin, PlatformSupport, Auditor.
 */
class PlatformPermissions
{
    // Names match the existing RolePermissionSeeder strings ("Super Admin"
    // is already seeded). Adding the team-inbox-specific platform roles.
    public const ROLE_SUPER_ADMIN      = 'Super Admin';
    public const ROLE_PLATFORM_SUPPORT = 'Platform Support';
    public const ROLE_AUDITOR          = 'Auditor';

    public const ROLES = [
        self::ROLE_SUPER_ADMIN,
        self::ROLE_PLATFORM_SUPPORT,
        self::ROLE_AUDITOR,
    ];

    public const PERMISSIONS = [
        'platform.workspace.view_all',
        'platform.workspace.suspend',
        'platform.workspace.unsuspend',
        'platform.workspace.impersonate',
        'platform.workspace.flag',
        'platform.workspace.unflag',
        'platform.conversation.view_all',
        'platform.conversation.flag_spam',
        'platform.note.write',
        'platform.audit.view',
        'platform.user.view_all',
        'platform.user.suspend',
        'platform.role.manage',
        'platform.permission.manage',
        'platform.billing.view',
        'platform.system.settings',
    ];

    public const MATRIX = [
        self::ROLE_SUPER_ADMIN => [
            // everything (granted via syncPermissions(all))
        ],
        self::ROLE_PLATFORM_SUPPORT => [
            'platform.workspace.view_all',
            'platform.workspace.impersonate',
            'platform.workspace.flag',
            'platform.conversation.view_all',
            'platform.conversation.flag_spam',
            'platform.note.write',
            'platform.audit.view',
            'platform.user.view_all',
        ],
        self::ROLE_AUDITOR => [
            'platform.workspace.view_all',
            'platform.conversation.view_all',
            'platform.audit.view',
            'platform.user.view_all',
        ],
    ];

    public static function permissionsFor(string $role): array
    {
        if ($role === self::ROLE_SUPER_ADMIN) return self::PERMISSIONS;
        return self::MATRIX[$role] ?? [];
    }

    public static function userHasPlatformAccess(?User $user): bool
    {
        if (!$user) return false;
        try {
            return $user->hasAnyRole(self::ROLES);
        } catch (\Throwable $e) {
            return false;
        }
    }

    public static function userCan(?User $user, string $permission): bool
    {
        if (!$user) return false;
        try {
            return $user->hasPermissionTo($permission);
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Gate for nuclear options — revoke ALL sessions, emergency send halt,
     * mass password reset, rotate every webhook secret. These have
     * platform-wide impact and should only be reachable by a Super Admin.
     *
     * Allows EITHER:
     *   - Spatie role "Super Admin" (the canonical RBAC), OR
     *   - Legacy `users.role` column ∈ {super_admin, super-admin, platform-admin}
     *     (backwards-compat for installs that don't seed Spatie roles).
     *
     * Plain `admin` role users are intentionally NOT enough — those can
     * still manage workspaces / users / settings, but can't pull the
     * emergency brake without explicit promotion.
     */
    public static function isSuperAdmin(?User $user): bool
    {
        if (!$user) return false;
        $roleCol = (string) ($user->role ?? '');
        if (in_array($roleCol, ['super_admin', 'super-admin', 'platform-admin'], true)) {
            return true;
        }
        try {
            return $user->hasRole(self::ROLE_SUPER_ADMIN);
        } catch (\Throwable $e) {
            return false;
        }
    }
}
