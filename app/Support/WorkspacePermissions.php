<?php

namespace App\Support;

use App\Models\User;

/**
 * Workspace-scoped permission map. Lives outside Spatie's `permissions` table
 * because Spatie roles are global (one role definition per app); we want
 * "Manager in Acme" not to imply "Manager in Globex".
 *
 * Source of truth for the role → permissions mapping. Read by:
 *   - WorkspacePolicy / ConversationPolicy / TeamPolicy
 *   - blade @canWorkspace directive (registered in AppServiceProvider)
 *   - the front-end (the controllers expose `permissions` per workspace
 *     so the UI can hide actions the user can't perform).
 */
class WorkspacePermissions
{
    public const ROLE_OWNER   = 'owner';
    public const ROLE_ADMIN   = 'admin';
    public const ROLE_MANAGER = 'manager';
    public const ROLE_AGENT   = 'agent';
    public const ROLE_VIEWER  = 'viewer';

    public const ROLES = [
        self::ROLE_OWNER, self::ROLE_ADMIN,
        self::ROLE_MANAGER, self::ROLE_AGENT, self::ROLE_VIEWER,
    ];

    /** ordered most → least privileged; useful for hierarchy checks */
    public const HIERARCHY = [
        self::ROLE_OWNER   => 100,
        self::ROLE_ADMIN   => 80,
        self::ROLE_MANAGER => 60,
        self::ROLE_AGENT   => 40,
        self::ROLE_VIEWER  => 20,
    ];

    public const PERMISSIONS = [
        // inbox
        'inbox.view_all_teams',     // see queues for teams I'm not in
        'inbox.view_team',          // see queue for my team(s)
        'inbox.view_assigned',      // see only my assigned conversations
        'inbox.reply',
        'inbox.note',               // post internal note
        'inbox.assign',             // assign / reassign / unassign
        'inbox.resolve',
        'inbox.snooze',
        'inbox.bulk',
        'inbox.tag',
        'inbox.priority',           // change priority

        // workspace settings
        'team.manage',
        'tag.manage',
        'routing.manage',
        'sla.manage',
        'savedreply.manage',
        'member.invite',
        'member.role.assign',
        'workspace.settings',
        'workspace.billing',
        'analytics.view',
        'integration.manage',
    ];

    /** role → list of permissions */
    private const MATRIX = [
        self::ROLE_OWNER => [
            'inbox.view_all_teams','inbox.view_team','inbox.view_assigned',
            'inbox.reply','inbox.note','inbox.assign','inbox.resolve',
            'inbox.snooze','inbox.bulk','inbox.tag','inbox.priority',
            'team.manage','tag.manage','routing.manage','sla.manage','savedreply.manage',
            'member.invite','member.role.assign',
            'workspace.settings','workspace.billing','analytics.view','integration.manage',
        ],
        self::ROLE_ADMIN => [
            'inbox.view_all_teams','inbox.view_team','inbox.view_assigned',
            'inbox.reply','inbox.note','inbox.assign','inbox.resolve',
            'inbox.snooze','inbox.bulk','inbox.tag','inbox.priority',
            'team.manage','tag.manage','routing.manage','sla.manage','savedreply.manage',
            'member.invite','member.role.assign',
            'workspace.settings','analytics.view','integration.manage',
        ],
        self::ROLE_MANAGER => [
            'inbox.view_all_teams','inbox.view_team','inbox.view_assigned',
            'inbox.reply','inbox.note','inbox.assign','inbox.resolve',
            'inbox.snooze','inbox.bulk','inbox.tag','inbox.priority',
            'tag.manage','savedreply.manage','analytics.view',
        ],
        self::ROLE_AGENT => [
            'inbox.view_team','inbox.view_assigned',
            'inbox.reply','inbox.note','inbox.resolve','inbox.snooze',
            'inbox.tag',
        ],
        self::ROLE_VIEWER => [
            'inbox.view_team','inbox.view_assigned',
        ],
    ];

    public static function permissionsFor(string $role): array
    {
        // The original `workspace_user` migration shipped 'member' as the
        // default role string — alias it to Agent so existing rows keep
        // working without a data migration.
        if ($role === 'member') $role = self::ROLE_AGENT;
        return self::MATRIX[$role] ?? [];
    }

    public static function roleHasPermission(string $role, string $permission): bool
    {
        return in_array($permission, self::permissionsFor($role), true);
    }

    public static function userCan(?User $user, string $permission, ?int $workspaceId = null): bool
    {
        if (!$user) return false;
        $workspaceId ??= $user->current_workspace_id;
        $role = $user->workspaceRole($workspaceId);
        return $role !== null && self::roleHasPermission($role, $permission);
    }

    public static function compareRoles(string $a, string $b): int
    {
        return (self::HIERARCHY[$a] ?? 0) <=> (self::HIERARCHY[$b] ?? 0);
    }

    /**
     * Can $actor grant `$targetRole` to someone else?
     *
     * Rules:
     *   - Owner role: only Owner can grant (prevents accidental founder-bypass)
     *   - Admin role: only Owner can grant (prevents Admin proliferation /
     *     privilege escalation chains)
     *   - Anything else: anyone with `member.role.assign` (or `member.invite`)
     *     can grant, as long as they're at least as senior as the target.
     */
    public static function canGrantRole(?User $actor, string $targetRole, ?int $workspaceId = null): bool
    {
        if (!$actor) return false;
        $workspaceId ??= $actor->current_workspace_id;
        $actorRole = $actor->workspaceRole($workspaceId);
        if (!$actorRole) return false;

        // Only Owner can grant Owner.
        if ($targetRole === self::ROLE_OWNER) {
            return $actorRole === self::ROLE_OWNER;
        }
        // Only Owner can grant Admin (no Admin → Admin chains).
        if ($targetRole === self::ROLE_ADMIN) {
            return $actorRole === self::ROLE_OWNER;
        }
        // Other roles: actor must have grant capability AND be senior enough.
        if (!self::roleHasPermission($actorRole, 'member.role.assign')
         && !self::roleHasPermission($actorRole, 'member.invite')) {
            return false;
        }
        return self::compareRoles($actorRole, $targetRole) >= 0;
    }

    /** Returns the list of roles `$actor` is allowed to grant. */
    public static function grantableRolesFor(?User $actor, ?int $workspaceId = null): array
    {
        return array_values(array_filter(self::ROLES, fn ($r) => self::canGrantRole($actor, $r, $workspaceId)));
    }
}
