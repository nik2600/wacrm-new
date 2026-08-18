<?php

namespace App\Models;

use App\Models\Concerns\HasEngineScope;
use App\Models\Concerns\LogsNotifications;
use App\Models\Concerns\TracksPlanQuota;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Ported from D:\wadesk_2806\New folder\app\Models\WpCampaign.php.
 *
 * The fillable list is preserved verbatim from the old project. Casts gain
 * Laravel-12 native end-to-end encryption for sensitive columns (campaign
 * name, message body, header, footer, buttons, quick replies) on top of
 * the original boolean / date / array casts.
 */
class WpCampaign extends Model
{
    use HasEngineScope, HasFactory, LogsNotifications, TracksPlanQuota;

    protected $table = 'wpcampaigns';

    /**
     * Plan-quota metric — TracksPlanQuota bumps PlanUsage under this key on
     * every create, and WaCampaignsController::store() enforces it via
     * PlanLimitGuard::checkQuota(..., self::PLAN_QUOTA_METRIC). Delete-proof:
     * the counter never decrements, so delete + recreate can't beat the cap.
     */
    public const PLAN_QUOTA_METRIC = 'campaign';

    /**
     * Auto-stamp `provider` on create from the workspace's active engine.
     */
    protected static function booted(): void
    {
        static::creating(function (self $c) {
            if (empty($c->provider) && !empty($c->workspace_id)) {
                try {
                    $c->provider = \App\Services\WorkspaceEngine::for((int) $c->workspace_id);
                } catch (\Throwable $e) {}
            }
        });

        // Webhook: campaign_created / campaign_status_updated. Fired from the
        // model so EVERY status write (store, dispatchCampaignNow, sweeper,
        // Node callbacks) is covered without touching each call site. emit()
        // is deferred + guarded, so it can never break the save.
        static::created(function (self $c) {
            \App\Services\WebhookService::emit('campaign_created', [
                'workspace_id'     => $c->workspace_id,
                'user_id'          => $c->created_by,
                'campaign_id'      => $c->id,
                'campaign_name'    => $c->campaign_name,
                'campaign_type'    => $c->campaign_type,
                'status'           => $c->status,
                'schedule_type'    => $c->schedule_type,
                'total_recipients' => (int) $c->total_recipients,
                'timestamp'        => now()->timestamp,
            ], $c->created_by);
        });

        static::updated(function (self $c) {
            if (!$c->wasChanged('status')) return;
            \App\Services\WebhookService::emit('campaign_status_updated', [
                'workspace_id'     => $c->workspace_id,
                'user_id'          => $c->created_by,
                'campaign_id'      => $c->id,
                'campaign_name'    => $c->campaign_name,
                'status'           => $c->status,
                'previous_status'  => $c->getOriginal('status'),
                'total_recipients' => (int) $c->total_recipients,
                'sent_count'       => (int) $c->sent_count,
                'delivered_count'  => (int) $c->delivered_count,
                'read_count'       => (int) $c->read_count,
                'failed_count'     => (int) $c->failed_count,
                'responded_count'  => (int) $c->responded_count,
                'timestamp'        => now()->timestamp,
            ], $c->created_by);
        });
    }

    protected $fillable = [
        'workspace_id', 'provider',
        'campaign_name', 'device_id', 'campaign_type', 'status',
        'ab_testing', 'ab_split',
        'custom_message', 'custom_message_b', 'custom_header', 'custom_footer',
        'custom_buttons', 'custom_quick_replies', 'custom_variable_map',
        'custom_image', 'custom_video', 'custom_document',
        'template_id', 'template_id_a', 'template_id_b',
        // Per-send overrides for the selected template's variables /
        // header media / button URLs. See TemplateOverrideResolver.
        'template_overrides',
        'template_overrides_b',
        'flow_id', 'flow_id_b', 'use_attributes', 'tracking_enabled',
        'schedule_type', 'send_date', 'send_time', 'timezone',
        'repeat_interval', 'repeat_until', 'last_run_at', 'expires_at',
        // Smart Delivery (anti-ban) — per-campaign pacing overrides. NULL = use
        // the platform-wide admin default (msg_gap / batches_gap / bw_msg_gap).
        'throttle_min_sec', 'throttle_max_sec', 'batch_size', 'batch_pause_min',
        'daily_limit', 'window_start', 'window_end',
        'node_schedule_id',
        'total_recipients', 'sent_count', 'failed_count',
        'delivered_count', 'read_count', 'responded_count', 'clicked_count',
        'completed_at', 'created_by',
    ];

    protected $casts = [
        'campaign_name'        => 'encrypted',
        'custom_message'       => 'encrypted',
        'custom_message_b'     => 'encrypted',
        'custom_header'        => 'encrypted',
        'custom_footer'        => 'encrypted',
        'custom_buttons'       => 'encrypted:array',
        'custom_quick_replies' => 'encrypted:array',
        'custom_variable_map'  => 'encrypted',
        'template_overrides'   => 'encrypted:array',
        // Variant B's own send-time mapping. Same cast as A so both
        // decode to arrays for the send loop.
        'template_overrides_b' => 'encrypted:array',
        'ab_testing'           => 'boolean',
        'use_attributes'       => 'boolean',
        'tracking_enabled'     => 'boolean',
        'send_date'            => 'date',
        'repeat_until'         => 'date',
        'last_run_at'          => 'datetime',
        'completed_at'         => 'datetime',
        'expires_at'           => 'datetime',
        'throttle_min_sec'     => 'integer',
        'throttle_max_sec'     => 'integer',
        'batch_size'           => 'integer',
        'batch_pause_min'      => 'integer',
        'daily_limit'          => 'integer',
    ];

    /**
     * When this campaign is due to fire, in UTC — from send_date + send_time
     * interpreted in its own `timezone`. Null if it has no schedule.
     */
    public function dueAtUtc(): ?\Illuminate\Support\Carbon
    {
        if (!$this->send_date) return null;
        try {
            $dateStr = $this->send_date instanceof \Carbon\CarbonInterface ? $this->send_date->toDateString() : (string) $this->send_date;
            $timeStr = (string) ($this->send_time ?: '00:00:00');
            $tz      = $this->timezone ?: 'UTC';
            return \Illuminate\Support\Carbon::parse($dateStr . ' ' . $timeStr, $tz)->utc();
        } catch (\Throwable $e) {
            return null;
        }
    }

    /** Has the scheduled fire time passed? */
    public function isDue(): bool
    {
        $due = $this->dueAtUtc();
        return $due !== null && $due->lte(\Illuminate\Support\Carbon::now('UTC'));
    }

    /**
     * Re-arm a recurring campaign: advance send_date/send_time one cadence
     * forward. Returns false when there's no cadence or the next run is past
     * repeat_until (caller should then complete the campaign).
     */
    public function advanceRecurring(): bool
    {
        if ($this->schedule_type !== 'recurring' || !$this->repeat_interval) return false;

        $tz = $this->timezone ?: 'UTC';
        try {
            $dateStr = $this->send_date instanceof \Carbon\CarbonInterface ? $this->send_date->toDateString() : (string) $this->send_date;
            $local   = \Illuminate\Support\Carbon::parse($dateStr . ' ' . ($this->send_time ?: '00:00:00'), $tz);
        } catch (\Throwable $e) {
            return false;
        }

        // Advance in the campaign's OWN timezone so the wall-clock hour (e.g.
        // 09:00) is preserved across daylight-saving transitions — i.e. "every
        // week at 9am their time", never drifting to 8am/10am after a DST flip.
        $next = match ($this->repeat_interval) {
            'daily'   => $local->copy()->addDay(),
            'weekly'  => $local->copy()->addWeek(),
            'monthly' => $local->copy()->addMonthNoOverflow(),
            default   => null,
        };
        if (!$next) return false;

        if ($this->repeat_until && $next->toDateString() > \Illuminate\Support\Carbon::parse($this->repeat_until)->toDateString()) {
            return false;
        }

        $this->send_date = $next->toDateString();
        $this->send_time = $next->format('H:i:s');
        return true;
    }

    public function contacts(): HasMany
    {
        return $this->hasMany(WpCampaignContact::class, 'campaign_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by')->withDefault();
    }

    /**
     * Workspace scope — see Broadcast::scopeForCurrentWorkspace.
     * Pre-migration rows with NULL workspace_id fall back to the
     * legacy created_by → user_id path so older campaigns stay
     * visible to whoever created them.
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
                   $qqq->whereNull('workspace_id')->where('created_by', $uId);
               });
        });
    }

    /**
     * The raw COUNT(*) CASE expressions that derive delivered / read /
     * responded / clicked from the per-recipient wp_campaign_contacts log.
     * Shared so the model recompute and the index bulk-rollup stay identical.
     * (read implies delivered; a reply implies both.)
     */
    public static function aggregateSelectSql(): string
    {
        return "
            SUM(CASE WHEN status IN ('sent','delivered','read','responded') OR sent_at IS NOT NULL OR delivered_at IS NOT NULL OR read_at IS NOT NULL OR responded_at IS NOT NULL THEN 1 ELSE 0 END) as sent,
            SUM(CASE WHEN status IN ('delivered','read','responded') OR delivered_at IS NOT NULL OR read_at IS NOT NULL OR responded_at IS NOT NULL THEN 1 ELSE 0 END) as delivered,
            SUM(CASE WHEN status IN ('read','responded') OR read_at IS NOT NULL THEN 1 ELSE 0 END) as read_c,
            SUM(CASE WHEN status = 'responded' OR responded_at IS NOT NULL THEN 1 ELSE 0 END) as responded,
            SUM(CASE WHEN clicked = 1 OR clicked_at IS NOT NULL THEN 1 ELSE 0 END) as clicked,
            SUM(CASE WHEN status IN ('sent','delivered','read','responded','failed','unsubscribed','skipped') OR sent_at IS NOT NULL OR delivered_at IS NOT NULL OR read_at IS NOT NULL OR responded_at IS NOT NULL THEN 1 ELSE 0 END) as terminal,
            COUNT(*) as total
        ";
    }

    /**
     * Recompute this campaign's delivered/read/responded/clicked counters from
     * its wp_campaign_contacts log and persist them. Meta delivery/read
     * webhooks patch the per-recipient rows but never these aggregate columns,
     * so without this call the analytics KPI cards (which read the columns)
     * stay at 0 while the funnel (which reads the log) shows real numbers.
     * Idempotent — safe to call repeatedly.
     */
    public function recomputeAggregates(): self
    {
        $agg = \DB::table('wp_campaign_contacts')
            ->where('campaign_id', $this->id)
            ->selectRaw(self::aggregateSelectSql())
            ->first();
        if (!$agg) return $this;

        $this->forceFill([
            'sent_count'      => (int) ($agg->sent      ?? 0),
            'delivered_count' => (int) ($agg->delivered ?? 0),
            'read_count'      => (int) ($agg->read_c    ?? 0),
            'responded_count' => (int) ($agg->responded ?? 0),
            'clicked_count'   => (int) ($agg->clicked   ?? 0),
        ])->saveQuietly();

        return $this;
    }

    /**
     * Flip a finished campaign out of scheduled/running.
     *
     * The Node finaliser only marks a campaign completed when its send loop
     * runs to the end. A recipient that is never attempted — unsubscribed,
     * or otherwise skipped — leaves the campaign sitting in `scheduled`
     * forever, so the card keeps saying "Waiting for send window" long after
     * the last message went out.
     *
     * A campaign is done when no recipient is still waiting: every row is
     * sent/delivered/read/responded, failed, unsubscribed or skipped. Rows
     * still `pending`/`queued` mean there is genuinely more to send, so an
     * un-started campaign is left alone.
     *
     * Recurring campaigns ('active') are deliberately excluded — they finish
     * a cycle and legitimately wait for the next one.
     *
     * @param object|null $agg Row from aggregateSelectSql(), to avoid a second
     *                         query when the caller already has one.
     */
    public function reconcileStatus(?object $agg = null): self
    {
        if (! in_array($this->status, ['scheduled', 'running'], true)) {
            return $this;
        }

        if (! $agg) {
            $agg = \DB::table('wp_campaign_contacts')
                ->where('campaign_id', $this->id)
                ->selectRaw(self::aggregateSelectSql())
                ->first();
        }

        $total    = (int) ($agg->total    ?? 0);
        $terminal = (int) ($agg->terminal ?? 0);

        if ($total > 0 && $terminal >= $total) {
            $this->status = (int) ($agg->sent ?? 0) === 0 ? 'failed' : 'completed';
            $this->forceFill([
                'status'       => $this->status,
                'completed_at' => $this->completed_at ?: now(),
            ])->saveQuietly();
        }

        return $this;
    }
}
