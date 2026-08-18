<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SlaPolicy extends Model
{
    protected $table = 'sla_policies';

    protected $fillable = [
        'workspace_id', 'name',
        'first_response_minutes', 'resolution_minutes',
        'pause_when_waiting_on_customer', 'respect_business_hours',
        'priority_overrides', 'is_default',
        // Multi-device. null/empty = applies to every device.
        'device_ids',
    ];

    protected $casts = [
        'first_response_minutes'         => 'integer',
        'resolution_minutes'             => 'integer',
        'pause_when_waiting_on_customer' => 'boolean',
        'respect_business_hours'         => 'boolean',
        'priority_overrides'             => 'array',
        'is_default'                     => 'boolean',
        'device_ids'                     => 'array',
    ];

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function conversations(): HasMany
    {
        return $this->hasMany(Conversation::class, 'sla_policy_id');
    }

    public function scopeForWorkspace(Builder $q, int $workspaceId): Builder
    {
        return $q->where('workspace_id', $workspaceId);
    }

    public function targetsFor(string $priority): array
    {
        $overrides = $this->priority_overrides[$priority] ?? [];
        return [
            'first_response_minutes' => $overrides['first_response_minutes'] ?? $this->first_response_minutes,
            'resolution_minutes'     => $overrides['resolution_minutes']     ?? $this->resolution_minutes,
        ];
    }

    /**
     * Multi-device gate. Mirrors AiAgent/Team handlesDevice:
     *   - device_ids null or empty → applies to every device.
     *   - non-empty array → only conversations on listed devices.
     */
    public function appliesToDevice(?int $deviceId): bool
    {
        $ids = is_array($this->device_ids) ? $this->device_ids : [];
        if (empty($ids)) return true;
        if ($deviceId === null) return false;
        return in_array((int) $deviceId, array_map('intval', $ids), true);
    }

    /**
     * Pick the most-specific SLA policy that applies to a conversation.
     * Device-scoped policies win over workspace-wide ones — e.g. a
     * stricter "Support number" policy applies even when a generic
     * workspace-default policy also exists. Falls back to the
     * workspace default when no device-scoped policy matches.
     */
    public static function bestFor(int $workspaceId, ?int $deviceId): ?self
    {
        $candidates = self::forWorkspace($workspaceId)->get();
        // Prefer device-scoped match, then default, then any.
        $scoped = $candidates->first(fn ($p) =>
            is_array($p->device_ids) && !empty($p->device_ids) && $p->appliesToDevice($deviceId)
        );
        if ($scoped) return $scoped;
        $default = $candidates->first(fn ($p) => (bool) $p->is_default);
        if ($default) return $default;
        return $candidates->first();
    }
}
