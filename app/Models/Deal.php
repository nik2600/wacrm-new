<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Deal = one opportunity on a pipeline. Money in minor units (wa_orders
 * pattern). Dropping a card on a terminal stage flips status + stamps
 * won_at/lost_at automatically (see booted()).
 */
class Deal extends Model
{
    protected $fillable = [
        'workspace_id', 'pipeline_id', 'stage_id',
        'contact_id', 'conversation_id',
        'title', 'value_minor', 'currency',
        'owner_user_id', 'owner_team_id',
        'expected_close_date', 'status', 'lost_reason', 'source',
        'sort_order', 'notes', 'meta', 'won_at', 'lost_at',
    ];

    protected $casts = [
        'value_minor'         => 'integer',
        'sort_order'          => 'integer',
        'meta'                => 'array',
        'expected_close_date' => 'date',
        'won_at'              => 'datetime',
        'lost_at'             => 'datetime',
    ];

    public const STATUSES = ['open', 'won', 'lost'];
    public const SOURCES  = ['manual', 'inbox', 'order', 'shopify', 'woo', 'form', 'api'];

    protected static function booted(): void
    {
        // Keep status / won_at / lost_at in lock-step with the stage. Runs on
        // create AND update (stage_id dirty), so a deal dropped on Won/Lost —
        // or created straight into one — is consistent in the SAME write.
        static::saving(function (Deal $deal) {
            if (!$deal->isDirty('stage_id')) return;
            $stage = PipelineStage::find($deal->stage_id);
            if (!$stage) return;

            if ($stage->is_won) {
                $deal->status  = 'won';
                $deal->won_at  = $deal->won_at ?: now();
                $deal->lost_at = null;
            } elseif ($stage->is_lost) {
                $deal->status  = 'lost';
                $deal->lost_at = $deal->lost_at ?: now();
                $deal->won_at  = null;
            } else {
                $deal->status      = 'open';
                $deal->won_at      = null;
                $deal->lost_at     = null;
                $deal->lost_reason = null;
            }
        });

        // Log the move on the timeline, and (P4) fire the deal_stage_changed
        // flow trigger — the bridge between Sales Pipeline and the flow builder.
        static::updated(function (Deal $deal) {
            if (!$deal->wasChanged('stage_id')) return;
            try {
                DealActivity::create([
                    'deal_id'      => $deal->id,
                    'workspace_id' => $deal->workspace_id,
                    'user_id'      => auth()->id(),
                    'type'         => 'stage_change',
                    'meta'         => [
                        'from_stage_id' => $deal->getOriginal('stage_id'),
                        'to_stage_id'   => $deal->stage_id,
                    ],
                ]);
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('[DEAL] stage_change log failed: ' . $e->getMessage());
            }
            // Sales Pipeline ↔ flow builder bridge: fire the deal_stage_changed
            // trigger so a chatbot flow can run when a deal enters a stage.
            try {
                app(\App\Services\Flow\FlowEnrollmentService::class)->onDealStageChanged($deal);
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('[DEAL] stage flow-trigger failed: ' . $e->getMessage());
            }
        });

        // In-app notifications — keep the pipeline from being a silo nobody
        // watches. A won/lost transition pings the owner; an ownership change
        // pings the new owner. Best-effort: a notify failure never blocks the
        // save (same guarantee as the flow trigger above).
        static::created(function (Deal $deal) {
            try {
                $disp = app(\App\Services\Inbox\NotificationDispatcher::class);
                // Auto-created (order/flow/inbox) deals land on an owner who may
                // not be the actor — tell them. Manual self-created deals (owner
                // == the logged-in creator) stay quiet.
                if ((int) $deal->owner_user_id > 0 && (int) $deal->owner_user_id !== (int) auth()->id()) {
                    $disp->notifyDealAssigned($deal, auth()->id());
                }
                // A deal created straight into a Won/Lost stage only fires
                // created() (not updated()), so surface the outcome here too.
                if (in_array($deal->status, ['won', 'lost'], true)) {
                    $disp->notifyDealOutcome($deal, auth()->id());
                }
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('[DEAL] create-notify failed: ' . $e->getMessage());
            }
        });

        static::updated(function (Deal $deal) {
            try {
                $disp = app(\App\Services\Inbox\NotificationDispatcher::class);
                if ($deal->wasChanged('status') && in_array($deal->status, ['won', 'lost'], true)) {
                    $disp->notifyDealOutcome($deal, auth()->id());
                }
                if ($deal->wasChanged('owner_user_id') && (int) $deal->owner_user_id !== (int) auth()->id()) {
                    $disp->notifyDealAssigned($deal, auth()->id());
                }
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('[DEAL] outcome/assign-notify failed: ' . $e->getMessage());
            }
        });
    }

    /* -------------------- relations -------------------- */

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function pipeline(): BelongsTo
    {
        return $this->belongsTo(Pipeline::class);
    }

    public function stage(): BelongsTo
    {
        return $this->belongsTo(PipelineStage::class, 'stage_id');
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class)->withDefault();
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id')->withDefault();
    }

    public function activities(): HasMany
    {
        return $this->hasMany(DealActivity::class)->latest();
    }

    /* -------------------- money -------------------- */

    public function getValueMajorAttribute(): float
    {
        return (int) $this->value_minor / 100;
    }

    public function getValueDisplayAttribute(): string
    {
        // Symbol resolves DYNAMICALLY from the admin-managed currencies table —
        // no hardcoded per-currency map (see Currency::symbolFor).
        return \App\Models\Currency::symbolFor($this->currency) . number_format($this->value_major, 2);
    }

    /* -------------------- scopes -------------------- */

    public function scopeForCurrentWorkspace(Builder $q): Builder
    {
        $user = auth()->user();
        if (!$user) return $q->whereRaw('1=0');
        return $q->where('workspace_id', (int) ($user->current_workspace_id ?? 0));
    }

    public function scopeOpen(Builder $q): Builder
    {
        return $q->where('status', 'open');
    }
}
