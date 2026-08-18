<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One entry on a deal's timeline: a note, a stage change, a logged call /
 * message, or a task (with a due date). `body` is encrypted at rest.
 */
class DealActivity extends Model
{
    protected $fillable = [
        'deal_id', 'workspace_id', 'user_id',
        'type', 'body', 'meta', 'due_at', 'done_at', 'reminded_at',
    ];

    protected $casts = [
        'body'        => 'encrypted',
        'meta'        => 'array',
        'due_at'      => 'datetime',
        'done_at'     => 'datetime',
        'reminded_at' => 'datetime',
    ];

    public const TYPES = ['note', 'stage_change', 'call', 'message', 'task'];

    public function deal(): BelongsTo
    {
        return $this->belongsTo(Deal::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withDefault();
    }

    public function scopeForCurrentWorkspace(Builder $q): Builder
    {
        $user = auth()->user();
        if (!$user) return $q->whereRaw('1=0');
        return $q->where('workspace_id', (int) ($user->current_workspace_id ?? 0));
    }
}
