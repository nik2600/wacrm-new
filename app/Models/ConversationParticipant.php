<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConversationParticipant extends Model
{
    protected $fillable = [
        'conversation_id', 'workspace_id', 'user_id',
        'role', 'last_read_at', 'unread_messages', 'unread_mentions',
    ];

    protected $casts = [
        'last_read_at'    => 'datetime',
        'unread_messages' => 'integer',
        'unread_mentions' => 'integer',
    ];

    public const ROLES = ['assignee', 'watcher', 'mentioned', 'follower'];

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withDefault();
    }

    public function scopeForUser(Builder $q, int $userId): Builder
    {
        return $q->where('user_id', $userId);
    }

    public function scopeForCurrentWorkspace(Builder $q): Builder
    {
        $user = auth()->user();
        if (!$user) return $q->whereRaw('1=0');
        $wsId = (int) ($user->current_workspace_id ?? 0);
        return $q->where('workspace_id', $wsId);
    }

    public function scopeUnread(Builder $q): Builder
    {
        return $q->where(function ($w) {
            $w->where('unread_messages', '>', 0)
              ->orWhere('unread_mentions', '>', 0);
        });
    }
}
