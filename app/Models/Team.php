<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Team extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'workspace_id', 'name', 'slug', 'color', 'description',
        'default_assignee_user_id', 'assignment_strategy',
        'business_hours', 'timezone', 'is_default', 'sort',
        // Multi-device scope. null/empty = any device (default).
        'device_ids',
    ];

    protected $casts = [
        'business_hours' => 'array',
        'is_default'     => 'boolean',
        'sort'           => 'integer',
        'device_ids'     => 'array',
    ];

    public const STRATEGIES = ['manual', 'round_robin', 'least_loaded', 'sticky'];

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'team_user')
            ->withPivot('is_lead', 'capacity')
            ->withTimestamps();
    }

    public function conversations(): HasMany
    {
        return $this->hasMany(Conversation::class, 'assignee_team_id');
    }

    public function defaultAssignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'default_assignee_user_id')->withDefault();
    }

    public function scopeForWorkspace(Builder $q, int $workspaceId): Builder
    {
        return $q->where('workspace_id', $workspaceId);
    }

    /**
     * Multi-device gate. Mirrors AiAgent::handlesDevice:
     *   - device_ids null or empty → any device (default).
     *   - non-empty array → only conversations on listed devices.
     * Routing/assignment paths call this before auto-assigning a
     * conversation to this team, so a marketing team scoped to the
     * sales number never gets dropped support tickets from the
     * other paired number.
     */
    public function handlesDevice(?int $deviceId): bool
    {
        $ids = is_array($this->device_ids) ? $this->device_ids : [];
        if (empty($ids)) return true;
        if ($deviceId === null) return false;
        return in_array((int) $deviceId, array_map('intval', $ids), true);
    }
}
