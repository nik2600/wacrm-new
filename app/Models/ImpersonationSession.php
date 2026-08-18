<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ImpersonationSession extends Model
{
    protected $fillable = [
        'admin_user_id', 'target_workspace_id', 'original_workspace_id',
        'reason', 'ip', 'user_agent',
        'started_at', 'ended_at',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'ended_at'   => 'datetime',
    ];

    public function admin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admin_user_id')->withDefault();
    }

    public function targetWorkspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class, 'target_workspace_id')->withDefault();
    }

    public function scopeActive(Builder $q): Builder
    {
        return $q->whereNull('ended_at');
    }

    public function scopeForAdmin(Builder $q, int $adminUserId): Builder
    {
        return $q->where('admin_user_id', $adminUserId);
    }

    public function isActive(): bool
    {
        return $this->ended_at === null;
    }
}
