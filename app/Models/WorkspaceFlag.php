<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkspaceFlag extends Model
{
    protected $fillable = [
        'workspace_id', 'flag', 'reason',
        'flagged_by_user_id', 'cleared_at', 'cleared_by_user_id',
    ];

    protected $casts = [
        'cleared_at' => 'datetime',
    ];

    public const FLAGS = ['spam', 'abuse', 'fraud', 'billing_overdue', 'tos_violation', 'frozen'];

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function flagger(): BelongsTo
    {
        return $this->belongsTo(User::class, 'flagged_by_user_id')->withDefault();
    }

    public function clearer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cleared_by_user_id')->withDefault();
    }

    public function scopeActive(Builder $q): Builder
    {
        return $q->whereNull('cleared_at');
    }
}
