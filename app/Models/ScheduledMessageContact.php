<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One row per (scheduled_message, recipient) — the per-recipient
 * outcome the /scheduled/{id} detail page reads. The bot updates
 * status as each Baileys send result comes back; aggregate counters
 * on the parent ScheduledMessage stay in sync via the same webhook.
 */
class ScheduledMessageContact extends Model
{
    protected $fillable = [
        'scheduled_message_id', 'contact_id', 'phone',
        'status', 'error_message', 'wa_message_id',
        'sent_at', 'delivered_at', 'read_at', 'failed_at',
    ];

    protected $casts = [
        'sent_at'      => 'datetime',
        'delivered_at' => 'datetime',
        'read_at'      => 'datetime',
        'failed_at'    => 'datetime',
    ];

    public const STATUSES = ['pending', 'sent', 'delivered', 'read', 'failed'];

    public function scheduledMessage(): BelongsTo
    {
        return $this->belongsTo(ScheduledMessage::class);
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class)->withDefault();
    }
}
