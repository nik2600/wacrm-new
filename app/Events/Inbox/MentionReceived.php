<?php

namespace App\Events\Inbox;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MentionReceived implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public int $mentionedUserId,
        public int $conversationId,
        public int $workspaceId,
        public ?int $byUserId,
        public string $excerpt,
    ) {
    }

    public function broadcastOn(): array
    {
        return [new PrivateChannel("user.{$this->mentionedUserId}.notifications")];
    }

    public function broadcastAs(): string
    {
        return 'mention.received';
    }

    public function broadcastWith(): array
    {
        return [
            'conversation_id' => $this->conversationId,
            'by_user_id'      => $this->byUserId,
            'excerpt'         => $this->excerpt,
        ];
    }
}
