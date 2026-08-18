<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One row per (flow, contact) — the unified replacement for DripSubscriber.
 * Tracks which contacts are currently inside which flow, regardless of how
 * they got enrolled (keyword trigger, tag added, group join, manual).
 *
 * UNIQUE(flow_id, contact_id) — re-enrollment is idempotent. Subscribers
 * stay on the row even after the flow ends so an operator can re-enroll.
 */
class FlowSubscriber extends Model
{
    public const STATUSES = ['active', 'paused', 'completed', 'failed'];

    protected $fillable = [
        'flow_id', 'contact_id',
        'enrolled_at', 'completed_at', 'failed_at',
        'failure_reason', 'status',
    ];

    protected $casts = [
        'enrolled_at'   => 'datetime',
        'completed_at'  => 'datetime',
        'failed_at'     => 'datetime',
    ];

    public function flow(): BelongsTo    { return $this->belongsTo(Flow::class); }
    public function contact(): BelongsTo { return $this->belongsTo(Contact::class); }
}
