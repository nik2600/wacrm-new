<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConversationEvent extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'conversation_id', 'workspace_id', 'actor_user_id', 'actor_type',
        'type', 'payload', 'created_at',
    ];

    protected $casts = [
        'payload'    => 'array',
        'created_at' => 'datetime',
    ];

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id')->withDefault();
    }

    public static function record(
        int $conversationId,
        int $workspaceId,
        ?int $actorUserId,
        string $type,
        array $payload = [],
        string $actorType = 'user',
    ): self {
        return self::create([
            'conversation_id' => $conversationId,
            'workspace_id'    => $workspaceId,
            'actor_user_id'   => $actorUserId,
            'actor_type'      => $actorType,
            'type'            => $type,
            'payload'         => $payload,
            'created_at'      => now(),
        ]);
    }
}
