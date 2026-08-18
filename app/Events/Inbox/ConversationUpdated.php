<?php

namespace App\Events\Inbox;

use App\Models\Conversation;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ConversationUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public int $conversationId,
        public int $workspaceId,
        public string $type,
        public array $changes = [],
    ) {
    }

    public static function fromModel(Conversation $c, string $type, array $changes = []): self
    {
        return new self($c->id, $c->workspace_id, $type, $changes);
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
        return "conversation.{$this->type}";
    }

    public function broadcastWith(): array
    {
        return [
            'conversation_id' => $this->conversationId,
            'type'            => $this->type,
            'changes'         => $this->changes,
        ];
    }
}
