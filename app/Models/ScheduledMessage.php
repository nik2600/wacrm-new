<?php

namespace App\Models;

use App\Models\Concerns\HasEngineScope;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * One scheduled WhatsApp send. Cron + sending lives in /node — Laravel
 * runs no scheduler. `next_run_at` is what we display in the /scheduled
 * UI and what the bot reads when it re-arms after a restart (via
 * /api/scheduled/active). The bot reports back via /api/update-schedule-status
 * and ScheduledController::updateStatus updates total_sent / status, calling
 * advanceRecurring() to roll the next run forward for recurring jobs.
 */
class ScheduledMessage extends Model
{
    use HasEngineScope, SoftDeletes;

    /**
     * Auto-stamp `provider` on create from the workspace's active engine.
     * Defensive cover for callsites that forgot to pass it explicitly.
     */
    protected static function booted(): void
    {
        static::creating(function (self $s) {
            if (empty($s->provider) && !empty($s->workspace_id)) {
                try {
                    $s->provider = \App\Services\WorkspaceEngine::for((int) $s->workspace_id);
                } catch (\Throwable $e) {}
            }
        });
    }

    protected $fillable = [
        'user_id', 'workspace_id', 'provider', 'device_id',
        'schedule_name', 'message_content',
        'template_id', 'template_type',
        // Per-send overrides for the selected template's variables /
        // header media / button URLs. See TemplateOverrideResolver.
        'template_overrides',
        'schedule_type',
        'send_date', 'send_time', 'scheduled_time', 'timezone',
        'repeat_interval', 'repeat_every', 'days_of_week', 'end_date',
        'media_file', 'latitude', 'longitude',
        'recipient_type', 'target_queues', 'target_groups', 'target_numbers',
        'total_recipients',
        'from_number', 'node_schedule_id',
        'status', 'next_run_at', 'last_run_at',
        'send_attempts', 'next_attempt_at', 'last_error',
        'completed_at', 'failed_at', 'failure_reason',
        'total_sent', 'total_delivered', 'total_failed', 'charged_sent',
    ];

    protected $casts = [
        // PII the operator wrote — encrypt at rest, same pattern as
        // Conversation::title and ConversationNote::body.
        'schedule_name'    => 'encrypted',
        'message_content'  => 'encrypted',
        'target_numbers'   => 'encrypted:array',     // phone numbers are PII
        'template_overrides' => 'encrypted:array',   // may embed customer data
        // group / queue IDs are not PII — keep plain JSON so we can query
        'target_queues'    => 'array',
        'target_groups'    => 'array',
        'days_of_week'     => 'array',

        'send_date'        => 'date',
        'end_date'         => 'date',
        'scheduled_time'   => 'datetime',
        'next_run_at'      => 'datetime',
        'last_run_at'      => 'datetime',
        'completed_at'     => 'datetime',
        'failed_at'        => 'datetime',
        'latitude'         => 'decimal:7',
        'longitude'        => 'decimal:7',
        'repeat_every'     => 'integer',
        'total_recipients' => 'integer',
        'total_sent'       => 'integer',
        'total_delivered'  => 'integer',
        'total_failed'     => 'integer',
        'charged_sent'     => 'integer',
    ];

    public const STATUSES = [
        'scheduled', 'running', 'paused',
        'completed', 'failed', 'cancelled',
    ];

    public const TEMPLATE_TYPES = ['text', 'template', 'media', 'location'];
    public const RECIPIENT_TYPES = ['group', 'queue', 'number'];
    public const SCHEDULE_TYPES = ['once', 'recurring'];
    public const REPEAT_INTERVALS = ['daily', 'weekly', 'monthly'];

    /* -------------------- relations -------------------- */

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withDefault();
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(WaTemplate::class, 'template_id')->withDefault();
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class)->withDefault();
    }

    /**
     * Per-recipient outcome rows. Pre-populated at store() time with
     * status=pending, then updated by the Node webhook per send result.
     */
    public function recipients(): HasMany
    {
        return $this->hasMany(ScheduledMessageContact::class);
    }

    /* -------------------- scopes -------------------- */

    public function scopeForUser(Builder $q, ?int $userId): Builder
    {
        return $userId ? $q->where('user_id', $userId) : $q;
    }

    public function scopeForWorkspace(Builder $q, ?int $workspaceId): Builder
    {
        return $workspaceId ? $q->where('workspace_id', $workspaceId) : $q;
    }

    /**
     * Workspace-shared visibility — every member of the current
     * workspace sees every scheduled message in it.
     */
    public function scopeForCurrentWorkspace(Builder $q): Builder
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

    public function scopeWithStatus(Builder $q, string|array $status): Builder
    {
        return is_array($status) ? $q->whereIn('status', $status) : $q->where('status', $status);
    }

    public function scopeReady(Builder $q): Builder
    {
        return $q->whereIn('status', ['scheduled', 'running'])
                 ->whereNotNull('next_run_at')
                 ->where('next_run_at', '<=', now());
    }

    public function scopeUpcoming(Builder $q): Builder
    {
        return $q->where('status', 'scheduled')
                 ->whereNotNull('next_run_at')
                 ->orderBy('next_run_at');
    }

    /* -------------------- helpers -------------------- */

    public function getDeliveryRateAttribute(): float
    {
        $sent = $this->total_sent ?: 0;
        if ($sent === 0) return 0.0;
        return round(($this->total_delivered / $sent) * 100, 1);
    }

    public function getIsRecurringAttribute(): bool
    {
        return $this->schedule_type === 'recurring';
    }

    /**
     * Advance `next_run_at` after a recurring run fires. Returns false when
     * the recurrence has ended (no further runs). Caller should flip the
     * status to 'completed' on false.
     */
    public function advanceRecurring(): bool
    {
        if (!$this->is_recurring || !$this->next_run_at) return false;

        $tz   = $this->timezone ?: 'UTC';
        $next = Carbon::parse($this->next_run_at, $tz);

        $next = match ($this->repeat_interval) {
            'daily'   => $next->copy()->addDays($this->repeat_every ?: 1),
            'weekly'  => $this->nextWeeklySlot($next),
            'monthly' => $next->copy()->addMonthsNoOverflow($this->repeat_every ?: 1),
            default   => null,
        };

        if (!$next) return false;
        if ($this->end_date && $next->isAfter($this->end_date->endOfDay())) return false;

        $this->forceFill(['next_run_at' => $next, 'last_run_at' => now()])->save();
        return true;
    }

    private function nextWeeklySlot(Carbon $from): ?Carbon
    {
        $days = collect($this->days_of_week ?: [])->map(fn ($d) => (int) $d)->sort()->values();
        if ($days->isEmpty()) return $from->copy()->addWeeks($this->repeat_every ?: 1);

        // Walk forward up to 14 days looking for the next allowed weekday.
        // Simpler than weekday math; the schedule is for humans, not HFT.
        for ($i = 1; $i <= 14; $i++) {
            $candidate = $from->copy()->addDays($i);
            if ($days->contains($candidate->dayOfWeek)) return $candidate;
        }
        return null;
    }

    public function markCompleted(): void
    {
        $this->forceFill([
            'status'       => 'completed',
            'completed_at' => now(),
            'last_run_at'  => now(),
        ])->save();
    }

    public function markFailed(?string $reason = null): void
    {
        $this->forceFill([
            'status'         => 'failed',
            'failed_at'      => now(),
            'failure_reason' => $reason,
        ])->save();
    }

    public function markRunComplete(int $sent, int $delivered, int $failed = 0): void
    {
        $this->forceFill([
            'total_sent'      => $this->total_sent + $sent,
            'total_delivered' => $this->total_delivered + $delivered,
            'total_failed'    => $this->total_failed + $failed,
            'last_run_at'     => now(),
        ])->save();

        if ($this->is_recurring) {
            $advanced = $this->advanceRecurring();
            if (!$advanced) $this->markCompleted();
            else $this->forceFill(['status' => 'scheduled'])->save();
        } else {
            $this->markCompleted();
        }
    }
}
