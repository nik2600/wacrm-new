<?php

namespace App\Policies;

use App\Models\Conversation;
use App\Models\User;
use App\Support\PlatformPermissions;
use App\Support\WorkspacePermissions;

/**
 * Authorization for inbox actions on a single conversation.
 *
 * Resolution order for every method:
 *   1. Platform read shortcut (cross-workspace 'view' for SaaS staff).
 *   2. Workspace match — if the user isn't a member of the conversation's
 *      workspace at all, deny without further checks. This is the security
 *      boundary; everything below assumes membership.
 *   3. Permission check via WorkspacePermissions (role → permission map).
 *   4. Per-conversation overrides: assignee can always view/reply, even
 *      without view_all_teams. Same for team membership when conversation
 *      is assigned to a team.
 */
class ConversationPolicy
{
    public function view(User $user, Conversation $conv): bool
    {
        if (PlatformPermissions::userCan($user, 'platform.conversation.view_all')) {
            return true;
        }
        if (!$this->inWorkspace($user, $conv)) return false;

        if (WorkspacePermissions::userCan($user, 'inbox.view_all_teams', $conv->workspace_id)) {
            return true;
        }
        if ($conv->assignee_user_id === $user->id) return true;
        if ($conv->assignee_team_id && $this->isInTeam($user, $conv->assignee_team_id)) {
            return WorkspacePermissions::userCan($user, 'inbox.view_team', $conv->workspace_id);
        }
        return WorkspacePermissions::userCan($user, 'inbox.view_assigned', $conv->workspace_id)
            && $conv->assignee_user_id === $user->id;
    }

    public function reply(User $user, Conversation $conv): bool
    {
        if (!$this->inWorkspace($user, $conv)) return false;
        if (!WorkspacePermissions::userCan($user, 'inbox.reply', $conv->workspace_id)) return false;
        return $this->view($user, $conv);
    }

    public function note(User $user, Conversation $conv): bool
    {
        if (!$this->inWorkspace($user, $conv)) return false;
        if (!WorkspacePermissions::userCan($user, 'inbox.note', $conv->workspace_id)) return false;
        return $this->view($user, $conv);
    }

    public function assign(User $user, Conversation $conv): bool
    {
        if (!$this->inWorkspace($user, $conv)) return false;
        return WorkspacePermissions::userCan($user, 'inbox.assign', $conv->workspace_id);
    }

    public function resolve(User $user, Conversation $conv): bool
    {
        if (!$this->inWorkspace($user, $conv)) return false;
        return WorkspacePermissions::userCan($user, 'inbox.resolve', $conv->workspace_id);
    }

    public function snooze(User $user, Conversation $conv): bool
    {
        if (!$this->inWorkspace($user, $conv)) return false;
        return WorkspacePermissions::userCan($user, 'inbox.snooze', $conv->workspace_id);
    }

    public function tag(User $user, Conversation $conv): bool
    {
        if (!$this->inWorkspace($user, $conv)) return false;
        return WorkspacePermissions::userCan($user, 'inbox.tag', $conv->workspace_id);
    }

    public function priority(User $user, Conversation $conv): bool
    {
        if (!$this->inWorkspace($user, $conv)) return false;
        return WorkspacePermissions::userCan($user, 'inbox.priority', $conv->workspace_id);
    }

    public function bulk(User $user, ?Conversation $conv = null): bool
    {
        $wsId = $conv?->workspace_id ?? $user->current_workspace_id;
        if (!$wsId) return false;
        return WorkspacePermissions::userCan($user, 'inbox.bulk', $wsId);
    }

    private function inWorkspace(User $user, Conversation $conv): bool
    {
        return $user->workspaces()->where('workspaces.id', $conv->workspace_id)->exists();
    }

    private function isInTeam(User $user, int $teamId): bool
    {
        return $user->teams()->where('teams.id', $teamId)->exists();
    }
}
