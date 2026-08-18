<?php

namespace App\Services\Inbox;

use App\Models\AgentStatus;
use App\Models\Conversation;
use App\Models\ConversationEvent;
use App\Models\Team;
use App\Models\User;

/**
 * Picks an agent for a conversation given a strategy, then performs the
 * assignment + audit + load-counter bookkeeping in one call. Controllers
 * call this so business rules don't drift across endpoints.
 *
 * Strategies:
 *   - manual       : caller passes an explicit user_id; we just verify
 *   - round_robin  : pick the team member who's gone longest without an assignment
 *   - least_loaded : pick the team member with the fewest open conversations
 *   - sticky       : pick the agent who last replied to this contact (if available)
 *
 * Strategies fall back to least_loaded when their preferred candidate is
 * away/busy/offline or over capacity. Returning null means "leave unassigned"
 * — callers should not crash on that, the conversation simply stays in the
 * team queue.
 */
class AssignmentService
{
    public function assign(Conversation $conv, ?int $userId, ?int $teamId, string $strategy = 'manual', ?int $actorId = null): ?User
    {
        $previousUserId = $conv->assignee_user_id;
        $previousTeamId = $conv->assignee_team_id;

        $resolvedUser = match ($strategy) {
            'manual'       => $userId ? User::find($userId) : null,
            'round_robin'  => $teamId ? $this->pickRoundRobin($teamId)  : null,
            'least_loaded' => $teamId ? $this->pickLeastLoaded($teamId) : null,
            'sticky'       => $this->pickSticky($conv) ?? ($teamId ? $this->pickLeastLoaded($teamId) : null),
            default        => null,
        };

        // When a human (agent or team) takes the conversation, stand the AI bot
        // down so it doesn't keep auto-replying over the operator. Mirrors
        // AiAgentService::triggerHandoff (the AI's own handoff already nulls
        // this). Without it, a flow "Assign agent" node — or a manual/auto
        // assign — hands the chat to a human, but the AI keeps replying because
        // its gate only checks assignee_agent_id. Only clear when a human is
        // actually assigned AND an AI agent was running, so a no-op assign
        // (round-robin with no eligible member) doesn't churn the row.
        $humanAssigned = ($resolvedUser?->id) || $teamId;
        $pauseAi       = $humanAssigned && $conv->assignee_agent_id;

        $conv->forceFill([
            'assignee_user_id' => $resolvedUser?->id,
            'assignee_team_id' => $teamId ?? $conv->assignee_team_id,
            'inbox_status'     => $conv->inbox_status === 'closed' ? 'open' : $conv->inbox_status,
        ] + ($pauseAi ? ['assignee_agent_id' => null] : []))->save();

        $this->recalcLoad($previousUserId, $conv->workspace_id);
        $this->recalcLoad($resolvedUser?->id, $conv->workspace_id);

        $type    = $previousUserId ? 'reassigned' : 'assigned';
        $payload = [
            'from_user_id' => $previousUserId,
            'to_user_id'   => $resolvedUser?->id,
            'from_team_id' => $previousTeamId,
            'to_team_id'   => $teamId ?? $previousTeamId,
            'strategy'     => $strategy,
        ];
        ConversationEvent::record($conv->id, $conv->workspace_id, $actorId, $type, $payload);

        // Timeline marker so operators see the bot paused on handoff.
        if ($pauseAi) {
            ConversationEvent::record($conv->id, $conv->workspace_id, $actorId, 'ai_handoff', [
                'reason'   => 'assigned_to_human',
                'strategy' => $strategy,
            ], $actorId ? 'human' : 'flow');
        }

        return $resolvedUser;
    }

    public function unassign(Conversation $conv, ?int $actorId = null): void
    {
        $previousUserId = $conv->assignee_user_id;
        if (!$previousUserId) return;

        $conv->forceFill(['assignee_user_id' => null])->save();
        $this->recalcLoad($previousUserId, $conv->workspace_id);
        ConversationEvent::record($conv->id, $conv->workspace_id, $actorId, 'unassigned', [
            'from_user_id' => $previousUserId,
        ]);
    }

    private function pickRoundRobin(int $teamId): ?User
    {
        $team = Team::with('members')->find($teamId);
        if (!$team) return null;
        $eligible = $this->eligibleMembers($team);
        if ($eligible->isEmpty()) return null;

        // Pick the member whose last assignment is the oldest (or who has
        // never been assigned). Single ORDER BY on conversations.created_at.
        return $eligible->sortBy(fn ($u) => $this->lastAssignmentAt($u->id, $team->workspace_id) ?? '0000-00-00')->first();
    }

    private function pickLeastLoaded(int $teamId): ?User
    {
        $team = Team::with('members')->find($teamId);
        if (!$team) return null;
        $eligible = $this->eligibleMembers($team);
        if ($eligible->isEmpty()) return null;

        return $eligible->sortBy(fn ($u) => $this->openLoad($u->id, $team->workspace_id))->first();
    }

    private function pickSticky(Conversation $conv): ?User
    {
        // Last outbound message author = the agent who most recently replied
        // to this contact. We don't depend on a relation here because Message
        // is a sibling table; one query is fine.
        $lastAgentId = \DB::table('messages')
            ->where('conversation_id', $conv->id)
            ->where('direction', 'out')
            ->whereNotNull('user_id')
            ->orderByDesc('id')
            ->value('user_id');

        if (!$lastAgentId) return null;

        $agent  = User::find($lastAgentId);
        $status = AgentStatus::where('user_id', $lastAgentId)
            ->where('workspace_id', $conv->workspace_id)
            ->first();

        if (!$agent) return null;
        if ($status && $status->status === 'offline') return null;
        return $agent;
    }

    private function eligibleMembers(Team $team)
    {
        $statuses = AgentStatus::where('workspace_id', $team->workspace_id)
            ->whereIn('user_id', $team->members->pluck('id'))
            ->get()->keyBy('user_id');

        return $team->members->filter(function (User $u) use ($statuses, $team) {
            $s = $statuses->get($u->id);
            if ($s && in_array($s->status, ['offline', 'busy'], true)) return false;
            $cap = $u->pivot?->capacity ?? 20;
            $load = $s?->current_load ?? $this->openLoad($u->id, $team->workspace_id);
            return $load < $cap;
        });
    }

    private function lastAssignmentAt(int $userId, int $workspaceId)
    {
        return Conversation::where('workspace_id', $workspaceId)
            ->where('assignee_user_id', $userId)
            ->max('updated_at');
    }

    private function openLoad(int $userId, int $workspaceId): int
    {
        return Conversation::where('workspace_id', $workspaceId)
            ->where('assignee_user_id', $userId)
            ->whereIn('inbox_status', ['open', 'pending'])
            ->count();
    }

    private function recalcLoad(?int $userId, int $workspaceId): void
    {
        if (!$userId) return;
        $count = $this->openLoad($userId, $workspaceId);
        AgentStatus::where('user_id', $userId)
            ->where('workspace_id', $workspaceId)
            ->update(['current_load' => $count]);
    }
}
