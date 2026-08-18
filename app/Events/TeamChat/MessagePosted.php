<?php

namespace App\Events\TeamChat;

use App\Models\TeamChatMessage;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MessagePosted implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public TeamChatMessage $message)
    {
    }

    public function broadcastOn(): array
    {
        return [new PrivateChannel("workspace.{$this->message->workspace_id}.team-chat")];
    }

    public function broadcastAs(): string
    {
        return 'team-chat.posted';
    }

    public function broadcastWith(): array
    {
        return [
            'message_id'   => $this->message->id,
            'user_id'      => $this->message->user_id,
            'reply_to_id'  => $this->message->reply_to_id,
            'mentions'     => is_array($this->message->mentions) ? $this->message->mentions : [],
            'created_at'   => $this->message->created_at?->toIso8601String(),
        ];
    }
}
