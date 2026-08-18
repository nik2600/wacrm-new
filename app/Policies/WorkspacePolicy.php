<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Workspace;
use App\Support\PlatformPermissions;
use App\Support\WorkspacePermissions;

class WorkspacePolicy
{
    public function view(User $user, Workspace $workspace): bool
    {
        if (PlatformPermissions::userCan($user, 'platform.workspace.view_all')) return true;
        return $user->workspaces()->where('workspaces.id', $workspace->id)->exists();
    }

    public function update(User $user, Workspace $workspace): bool
    {
        return WorkspacePermissions::userCan($user, 'workspace.settings', $workspace->id);
    }

    public function manageMembers(User $user, Workspace $workspace): bool
    {
        return WorkspacePermissions::userCan($user, 'member.invite', $workspace->id);
    }

    public function impersonate(User $user, Workspace $workspace): bool
    {
        return PlatformPermissions::userCan($user, 'platform.workspace.impersonate');
    }

    public function suspend(User $user, Workspace $workspace): bool
    {
        return PlatformPermissions::userCan($user, 'platform.workspace.suspend');
    }

    public function flag(User $user, Workspace $workspace): bool
    {
        return PlatformPermissions::userCan($user, 'platform.workspace.flag');
    }
}
