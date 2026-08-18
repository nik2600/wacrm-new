<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class SupportTicket extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'support_tickets';

    protected $fillable = [
        'user_id', 'workspace_id',
        'ticket_number', 'reason',
        'name', 'email', 'subject', 'message',
        'status', 'last_reply_at', 'resolved_at',
    ];

    protected $casts = [
        'last_reply_at' => 'datetime',
        'resolved_at'   => 'datetime',
    ];

    /**
     * Generate an unguessable but human-readable ticket number. We try
     * a few times in case of an unlikely collision; the DB unique
     * constraint catches anything we miss.
     */
    public static function freshTicketNumber(): string
    {
        for ($i = 0; $i < 5; $i++) {
            $candidate = 'TKT-' . strtoupper(Str::random(6));
            if (! static::where('ticket_number', $candidate)->exists()) {
                return $candidate;
            }
        }
        // Fallback — timestamp suffix is collision-proof.
        return 'TKT-' . strtoupper(Str::random(4)) . '-' . substr((string) time(), -4);
    }

    /**
     * Display-friendly status label for the UI pill. Mirrors the
     * legacy hardcoded labels on /account?tab=support so the visual
     * doesn't change.
     */
    public function statusLabel(): string
    {
        return match ($this->status) {
            'awaiting_user'    => 'your turn',
            'awaiting_support' => 'awaiting reply',
            'resolved'         => 'resolved',
            default            => 'open',
        };
    }

    public function user(): BelongsTo       { return $this->belongsTo(User::class); }
    public function workspace(): BelongsTo  { return $this->belongsTo(Workspace::class); }

    public function isOpen(): bool
    {
        return $this->status !== 'resolved';
    }
}
