<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One captured request to an incoming webhook URL. */
class IncomingWebhookEvent extends Model
{
    protected $fillable = [
        'incoming_webhook_id', 'method', 'source_ip', 'content_type',
        'headers', 'payload', 'forwarded', 'forward_status', 'forward_error',
        'received_at',
    ];

    protected $casts = [
        'headers'        => 'array',
        'forwarded'      => 'boolean',
        'forward_status' => 'integer',
        'received_at'    => 'datetime',
    ];

    public function webhook(): BelongsTo
    {
        return $this->belongsTo(IncomingWebhook::class, 'incoming_webhook_id');
    }
}
