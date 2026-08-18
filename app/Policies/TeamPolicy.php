<?php

namespace App\Policies;

use App\Models\Team;
use App\Models\User;
use App\Support\WorkspacePermissions;

class TeamPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->current_workspace_id !== null;
    }

    public function view(User $user, Team $team): bool
    {
        if (!$this->inWorkspace($user, $team->workspace_id)) return false;
        return true;
    }

    public function create(User $user): bool
    {
        return WorkspacePermissions::userCan($user, 'team.manage');
    }

    public function update(User $user, Team $team): bool
    {
        if (!$this->inWorkspace($user, $team->workspace_id)) return false;
        return WorkspacePermissions::userCan($user, 'team.manage', $team->workspace_id);
    }

    public function delete(User $user, Team $team): bool
    {
        return $this->update($user, $team);
    }

    private function inWorkspace(User $user, int $workspaceId): bool
    {
        return $user->workspaces()->where('workspaces.id', $workspaceId)->exists();
    }
}
