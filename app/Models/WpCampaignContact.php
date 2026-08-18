<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Ported from D:\wadesk_2806\New folder\app\Models\WpCampaignContact.php.
 *
 * Encrypted casts added on PII columns (phone_number, recipient_name,
 * error_message) so the pivot/log table is end-to-end encrypted at rest.
 */
class WpCampaignContact extends Model
{
    use HasFactory;

    protected $table = 'wp_campaign_contacts';

    protected $fillable = [
        'campaign_id', 'contact_id', 'variant', 'status',
        'send_attempts', 'next_attempt_at',
        'phone_number', 'recipient_name',
        'whatsapp_message_id', 'tracking_id',
        'sent_at', 'delivered_at', 'read_at',
        'response', 'responded_at',
        'clicked', 'clicked_at', 'click_count',
        'unsubscribed', 'is_unsubscribed', 'unsubscribed_at',
        'error_message',
    ];

    protected $casts = [
        'phone_number'    => 'encrypted',
        'recipient_name'  => 'encrypted',
        // SafeEncrypted (not plain 'encrypted'): the status webhook writes this
        // column via raw DB::table()->update(), which bypasses the model cast and
        // stores PLAINTEXT (e.g. Meta's "…healthy ecosystem engagement" 131049
        // reason). A plain 'encrypted' cast then throws DecryptException on read
        // and 500s the campaign detail page. SafeEncrypted decrypts real
        // ciphertext (from Eloquent writes like recordSendFailure) and returns
        // raw text unchanged when it isn't encrypted.
        'error_message'   => \App\Casts\SafeEncrypted::class,
        'clicked'         => 'boolean',
        'unsubscribed'    => 'boolean',
        'is_unsubscribed' => 'boolean',
        'sent_at'         => 'datetime',
        'delivered_at'    => 'datetime',
        'read_at'         => 'datetime',
        'responded_at'    => 'datetime',
        'clicked_at'      => 'datetime',
        'unsubscribed_at' => 'datetime',
        'send_attempts'   => 'integer',
        'next_attempt_at' => 'datetime',
    ];

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(WpCampaign::class, 'campaign_id');
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class, 'contact_id');
    }
}
