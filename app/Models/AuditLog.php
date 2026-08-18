<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'layer', 'workspace_id', 'actor_user_id',
        'action', 'subject_type', 'subject_id',
        'payload', 'ip', 'user_agent', 'result', 'created_at',
    ];

    protected $casts = [
        'payload'    => 'array',
        'created_at' => 'datetime',
    ];

    /**
     * UI tone hex/text colours per result and event family.
     */
    public function resultTone(): array
    {
        return match ($this->result) {
            'failure' => ['bg' => '#FDE8E4', 'fg' => '#9C2A1A', 'label' => 'Failure'],
            'warning' => ['bg' => '#FFF2CC', 'fg' => '#7A4F00', 'label' => 'Warning'],
            default   => ['bg' => '#D4F4E6', 'fg' => '#0F5B3E', 'label' => 'Success'],
        };
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id')->withDefault();
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class)->withDefault();
    }

    public function scopePlatform(Builder $q): Builder
    {
        return $q->where('layer', 'platform');
    }

    public function scopeWorkspaceLayer(Builder $q): Builder
    {
        return $q->where('layer', 'workspace');
    }

    public function scopeForWorkspace(Builder $q, int $workspaceId): Builder
    {
        return $q->where('workspace_id', $workspaceId);
    }

    public function scopeByAction(Builder $q, string $action): Builder
    {
        return $q->where('action', $action);
    }

    public function scopeByResult(Builder $q, string $result): Builder
    {
        return $q->where('result', $result);
    }

    public function scopeRecentDays(Builder $q, int $days): Builder
    {
        return $q->where('created_at', '>=', now()->subDays($days));
    }

    public function scopeByActor(Builder $q, int $userId): Builder
    {
        return $q->where('actor_user_id', $userId);
    }

    public function scopeFailures(Builder $q): Builder
    {
        return $q->whereIn('result', ['failure', 'warning']);
    }
}
