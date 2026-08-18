<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Pivot row between `broadcasts` and `contacts` — one per
 * recipient, carries the WhatsApp delivery lifecycle (pending →
 * processing → sent → delivered → read, or failed).
 *
 * Treated as a real model (not just a pivot) so the controller
 * can group / count by status without re-running ad-hoc SQL.
 */
class BroadcastContact extends Model
{
    use HasFactory;

    protected $table = 'broadcast_contacts';

    protected $fillable = [
        'broadcast_id',
        'contact_id',
        'status',
        'error_message',
        'whatsapp_message_id',
        'sent_at',
        'delivered_at',
        'read_at',
    ];

    protected $casts = [
        // SafeEncrypted (not plain 'encrypted'): the status webhook writes this
        // via raw DB::table()->update(), bypassing the cast → PLAINTEXT (Meta's
        // 131049 reason). A plain 'encrypted' cast then 500s on read. SafeEncrypted
        // decrypts real ciphertext and returns plaintext unchanged. Node still
        // sometimes puts the recipient phone / template body in the failure detail.
        'error_message' => \App\Casts\SafeEncrypted::class,
        'sent_at'       => 'datetime',
        'delivered_at'  => 'datetime',
        'read_at'       => 'datetime',
    ];

    public function broadcast(): BelongsTo
    {
        return $this->belongsTo(Broadcast::class);
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }
}
