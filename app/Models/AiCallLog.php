<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One row per voice call handled by an AI assistant. Written by the
 * Node runtime when a Twilio call closes (status webhook), or in
 * real-time as transcript turns accumulate during the call. The
 * `/call-logs` page reads these for the operator timeline.
 */
class AiCallLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'workspace_id', 'assistant_id', 'conversation_id',
        'caller_phone', 'callee_phone', 'direction',
        'started_at', 'ended_at', 'duration_seconds',
        'status', 'failure_reason',
        'recording_url_agent', 'recording_url_user', 'recording_url_mixed',
        'transcript_json', 'tool_calls_json',
        'ai_tokens_in', 'ai_tokens_out', 'stt_seconds', 'tts_chars',
        'cost_minor', 'currency_code',
        'twilio_call_sid', 'meta_json',
    ];

    protected $casts = [
        'started_at'       => 'datetime',
        'ended_at'         => 'datetime',
        'transcript_json'  => 'array',
        'tool_calls_json'  => 'array',
        'meta_json'        => 'array',
    ];

    public function assistant(): BelongsTo
    {
        return $this->belongsTo(AiCallAssistant::class, 'assistant_id');
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class, 'conversation_id');
    }

    /** Cost in major units for display. */
    public function getCostDisplayAttribute(): string
    {
        return number_format(($this->cost_minor ?? 0) / 100, 2) . ' ' . ($this->currency_code ?: 'USD');
    }

    /** Duration formatted as M:SS for the list page. */
    public function getDurationDisplayAttribute(): string
    {
        $s = (int) ($this->duration_seconds ?? 0);
        return sprintf('%d:%02d', intdiv($s, 60), $s % 60);
    }
}
