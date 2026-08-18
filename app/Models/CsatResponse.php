<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CsatResponse extends Model
{
    protected $fillable = [
        'conversation_id', 'workspace_id', 'agent_user_id',
        'rating', 'comment', 'sent_at', 'responded_at',
    ];

    protected $casts = [
        'rating'       => 'integer',
        // free-text rating comment from a customer — encrypt at rest.
        'comment'      => 'encrypted',
        'sent_at'      => 'datetime',
        'responded_at' => 'datetime',
    ];

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'agent_user_id')->withDefault();
    }

    public function scopeResponded(Builder $q): Builder
    {
        return $q->whereNotNull('rating');
    }

    public function scopeForWorkspace(Builder $q, int $workspaceId): Builder
    {
        return $q->where('workspace_id', $workspaceId);
    }
}
