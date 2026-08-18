<?php

namespace App\Events\Inbox;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SlaBreached implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public int $conversationId,
        public int $workspaceId,
        public string $kind,
        public ?string $dueAt,
    ) {
    }

    public function broadcastOn(): array
    {
        return [new PrivateChannel("workspace.{$this->workspaceId}.inbox")];
    }

    public function broadcastAs(): string
    {
        return 'sla.breached';
    }

    public function broadcastWith(): array
    {
        return [
            'conversation_id' => $this->conversationId,
            'kind'            => $this->kind,
            'due_at'          => $this->dueAt,
        ];
    }
}
