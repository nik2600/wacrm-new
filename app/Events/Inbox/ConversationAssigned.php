<?php

namespace App\Events\Inbox;

use App\Models\Conversation;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ConversationAssigned implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public int $conversationId,
        public int $workspaceId,
        public ?int $previousUserId,
        public ?int $newUserId,
        public ?int $teamId,
        public ?int $byUserId,
    ) {
    }

    public static function fromModel(Conversation $c, ?int $previousUserId, ?int $byUserId): self
    {
        return new self($c->id, $c->workspace_id, $previousUserId, $c->assignee_user_id, $c->assignee_team_id, $byUserId);
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("workspace.{$this->workspaceId}.inbox"),
            new PrivateChannel("conversation.{$this->conversationId}"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'conversation.assigned';
    }

    public function broadcastWith(): array
    {
        return [
            'conversation_id' => $this->conversationId,
            'previous_user_id' => $this->previousUserId,
            'new_user_id'     => $this->newUserId,
            'team_id'         => $this->teamId,
            'by_user_id'      => $this->byUserId,
        ];
    }
}
