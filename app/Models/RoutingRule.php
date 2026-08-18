<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RoutingRule extends Model
{
    protected $fillable = [
        'workspace_id', 'name', 'conditions', 'actions',
        'stop_on_match', 'is_active', 'is_fallback', 'sort',
        'fired_count', 'last_fired_at',
    ];

    protected $casts = [
        'conditions'    => 'array',
        'actions'       => 'array',
        'stop_on_match' => 'boolean',
        'is_active'     => 'boolean',
        'is_fallback'   => 'boolean',
        'sort'          => 'integer',
        'fired_count'   => 'integer',
        'last_fired_at' => 'datetime',
    ];

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function scopeActive(Builder $q): Builder
    {
        return $q->where('is_active', true);
    }

    public function scopeForWorkspace(Builder $q, int $workspaceId): Builder
    {
        return $q->where('workspace_id', $workspaceId);
    }
}
