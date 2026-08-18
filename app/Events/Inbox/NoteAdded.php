<?php

namespace App\Events\Inbox;

use App\Models\ConversationNote;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class NoteAdded implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public int $noteId,
        public int $conversationId,
        public int $workspaceId,
        public int $authorUserId,
        public array $mentions = [],
    ) {
    }

    public static function fromModel(ConversationNote $n): self
    {
        return new self(
            $n->id, $n->conversation_id, $n->workspace_id,
            $n->user_id, $n->mentions ?? [],
        );
    }

    public function broadcastOn(): array
    {
        return [new PrivateChannel("conversation.{$this->conversationId}")];
    }

    public function broadcastAs(): string
    {
        return 'note.added';
    }

    public function broadcastWith(): array
    {
        return [
            'note_id'         => $this->noteId,
            'conversation_id' => $this->conversationId,
            'author_user_id'  => $this->authorUserId,
            'mentions'        => $this->mentions,
        ];
    }
}
