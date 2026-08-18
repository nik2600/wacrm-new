<?php

namespace App\Models;

use App\Models\Concerns\HasEngineScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One row per WhatsApp call, regardless of direction. Created by
 * the webhook receiver on call.connect (incoming) or by
 * WaCallingService::dialOutbound (outgoing). Lifecycle:
 *
 *   ringing → connecting → active → ended | failed
 *
 * The handler_type column records who actually picked up — an
 * operator (browser WebRTC), the AI voicemail responder, or
 * nobody. Powers the post-call audit + the /calls history tab.
 */
class WaCall extends Model
{
    use HasEngineScope;

    /**
     * Auto-stamp `provider` on create. Calls are WABA-only today, but
     * future Twilio voice integration would land here too.
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
    }

    protected $fillable = [
        'workspace_id', 'provider', 'wa_provider_config_id',
        'meta_call_id', 'correlation_id',
        'direction', 'from_phone', 'to_phone',
        'contact_id', 'conversation_id',
        'handler_type', 'handler_user_id', 'handler_agent_id',
        'status', 'end_reason',
        'started_at', 'answered_at', 'ended_at', 'duration_sec',
        'recording_path', 'transcript',
        'error_payload', 'meta_payload',
    ];

    protected $casts = [
        'started_at'    => 'datetime',
        'answered_at'   => 'datetime',
        'ended_at'      => 'datetime',
        'duration_sec'  => 'integer',
        'error_payload' => 'array',
        'meta_payload'  => 'array',
    ];

    public const DIRECTIONS    = ['USER_INITIATED', 'BUSINESS_INITIATED'];
    public const STATUSES      = ['ringing', 'connecting', 'active', 'ended', 'failed'];
    public const HANDLERS      = ['operator', 'ai_agent', 'voicemail', 'none'];
    public const END_REASONS   = ['COMPLETED', 'REJECTED', 'BUSY', 'NO_ANSWER', 'FAILED', 'MISSED', 'CANCELLED'];

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function providerConfig(): BelongsTo
    {
        return $this->belongsTo(WaProviderConfig::class, 'wa_provider_config_id');
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class)->withDefault();
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class)->withDefault();
    }

    public function events(): HasMany
    {
        return $this->hasMany(WaCallEvent::class)->orderBy('received_at');
    }

    public function isActive(): bool
    {
        return in_array($this->status, ['ringing', 'connecting', 'active'], true);
    }
}
