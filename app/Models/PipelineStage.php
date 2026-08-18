<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One Kanban column. `is_won` / `is_lost` mark the terminal columns; the
 * Deal observer reads them to flip a deal's status + stamp won_at/lost_at.
 */
class PipelineStage extends Model
{
    protected $fillable = [
        'pipeline_id', 'workspace_id', 'name', 'sort_order',
        'color', 'is_won', 'is_lost', 'probability',
    ];

    protected $casts = [
        'is_won'      => 'boolean',
        'is_lost'     => 'boolean',
        'sort_order'  => 'integer',
        'probability' => 'integer',
    ];

    public function pipeline(): BelongsTo
    {
        return $this->belongsTo(Pipeline::class);
    }

    public function deals(): HasMany
    {
        return $this->hasMany(Deal::class, 'stage_id');
    }

    public function scopeForCurrentWorkspace(Builder $q): Builder
    {
        $user = auth()->user();
        if (!$user) return $q->whereRaw('1=0');
        return $q->where('workspace_id', (int) ($user->current_workspace_id ?? 0));
    }
}
