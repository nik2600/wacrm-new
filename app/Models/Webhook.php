<?php

namespace App\Models;

use App\Models\Concerns\LogsNotifications;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Webhook extends Model
{
    use HasFactory, LogsNotifications;

    protected $table = 'webhooks';

    protected $fillable = [
        'user_id', 'workspace_id', 'name', 'environment', 'http_method',
        'webhook_url', 'secret', 'events', 'status', 'is_failing',
        'success_count', 'failure_count', 'retry_count',
        'last_status_code', 'last_latency_ms', 'last_fired_at', 'last_error',
        'icon_color',
    ];

    protected $casts = [
        'webhook_url'   => 'encrypted',
        'secret'        => 'encrypted',
        'events'        => 'encrypted:array',
        'status'        => 'boolean',
        'is_failing'    => 'boolean',
        'last_fired_at' => 'datetime',
    ];

    public function deliveries()
    {
        return $this->hasMany(WebhookDelivery::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Legacy per-user scope — kept for backward compatibility. Prefer
     * `scopeForCurrentWorkspace` for any new caller. NULL-owner rows
     * are NOT returned (no admin-seeded global webhooks exist), so a
     * row with NULL user_id can no longer leak to every user.
     */
    public function scopeForUser($q, ?int $uid)
    {
        return $q->where('user_id', $uid);
    }

    /** Workspace-shared visibility — every member sees every webhook. */
    public function scopeForCurrentWorkspace($q)
    {
        $user = auth()->user();
        if (!$user) return $q->whereRaw('1=0');
        $uId  = (int) $user->id;
        $wsId = (int) ($user->current_workspace_id ?? 0);
        return $q->where(function ($qq) use ($wsId, $uId) {
            $qq->where('workspace_id', $wsId)
               ->orWhere(function ($qqq) use ($uId) {
                   $qqq->whereNull('workspace_id')->where('user_id', $uId);
               });
        });
    }

    public function getStateLabelAttribute(): string
    {
        if (!$this->status)        return 'paused';
        if ($this->is_failing)     return 'failing';
        return 'active';
    }

    public function getSuccessRateAttribute(): float
    {
        $t = $this->success_count + $this->failure_count;
        return $t === 0 ? 0.0 : round(($this->success_count / $t) * 100, 1);
    }
}
