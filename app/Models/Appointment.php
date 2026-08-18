<?php

namespace App\Models;

use App\Models\Concerns\HasEngineScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A single booked appointment tied to a workspace + contact (and
 * optionally a conversation). When confirmed, mirrors out to Google
 * Calendar — google_event_id pinpoints the calendar event we wrote.
 */
class Appointment extends Model
{
    use HasEngineScope, SoftDeletes;

    public const STATUSES = ['pending', 'confirmed', 'cancelled', 'completed', 'no_show'];

    /**
     * Auto-stamp `provider` on create from the workspace's active engine.
     */
    protected static function booted(): void
    {
        static::creating(function (self $a) {
            if (empty($a->provider) && !empty($a->workspace_id)) {
                try {
                    $a->provider = \App\Services\WorkspaceEngine::for((int) $a->workspace_id);
                } catch (\Throwable $e) {}
            }
        });
    }

    protected $fillable = [
        'workspace_id', 'provider', 'user_id', 'contact_id', 'conversation_id',
        'title', 'description', 'location',
        'starts_at', 'ends_at', 'timezone',
        'status', 'google_event_id', 'google_calendar_id',
        'meta', 'reminder_sent_at',
    ];

    protected $casts = [
        'starts_at'        => 'datetime',
        'ends_at'          => 'datetime',
        'reminder_sent_at' => 'datetime',
        'meta'             => 'array',
    ];

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function scopeForWorkspace($q, int $wsId)
    {
        return $q->where('workspace_id', $wsId);
    }

    public function scopeUpcoming($q)
    {
        return $q->where('starts_at', '>=', now())
                 ->whereIn('status', ['pending', 'confirmed']);
    }
}
