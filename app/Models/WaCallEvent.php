<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Forensic ledger for one wa_call. Every webhook from Meta, every
 * action POST we issue, and every internal state transition writes
 * exactly one row here so a post-mortem can replay the call's full
 * lifecycle without re-reading server logs.
 */
class WaCallEvent extends Model
{
    public $timestamps = false;

    protected $fillable = ['wa_call_id', 'event_type', 'payload', 'received_at'];

    protected $casts = [
        'payload'     => 'array',
        'received_at' => 'datetime',
    ];

    public function call(): BelongsTo
    {
        return $this->belongsTo(WaCall::class, 'wa_call_id');
    }
}
