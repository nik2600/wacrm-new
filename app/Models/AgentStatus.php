<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AgentStatus extends Model
{
    protected $table = 'agent_statuses';

    protected $fillable = [
        'user_id', 'workspace_id',
        'status', 'status_message', 'last_seen_at',
        'current_load', 'today_replies', 'today_resolutions',
        'counters_date', 'preferences',
    ];

    protected $casts = [
        'last_seen_at'      => 'datetime',
        'counters_date'     => 'date',
        'current_load'      => 'integer',
        'today_replies'     => 'integer',
        'today_resolutions' => 'integer',
        'preferences'       => 'array',
    ];

    public const STATUSES = ['online', 'away', 'busy', 'offline'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function scopeAvailable(Builder $q): Builder
    {
        return $q->where('status', 'online');
    }

    public function scopeForWorkspace(Builder $q, int $workspaceId): Builder
    {
        return $q->where('workspace_id', $workspaceId);
    }

    public function rolloverIfStale(): void
    {
        if ($this->counters_date && $this->counters_date->isToday()) return;
        $this->forceFill([
            'today_replies'     => 0,
            'today_resolutions' => 0,
            'counters_date'     => now()->toDateString(),
        ])->save();
    }
}
