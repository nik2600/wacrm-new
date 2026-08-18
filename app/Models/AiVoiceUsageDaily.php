<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One row per (workspace, ai_agent, date). Accumulated by the voice-AI
 * pipeline as it runs; read by admin/billing dashboards.
 */
class AiVoiceUsageDaily extends Model
{
    protected $table = 'ai_voice_usage_daily';

    protected $fillable = [
        'workspace_id', 'ai_agent_id', 'date',
        'voice_notes_processed', 'call_seconds',
        'asr_seconds', 'tts_chars',
        'llm_input_tokens', 'llm_output_tokens',
    ];

    protected $casts = [
        'date'                  => 'date',
        'voice_notes_processed' => 'integer',
        'call_seconds'          => 'integer',
        'asr_seconds'           => 'integer',
        'tts_chars'             => 'integer',
        'llm_input_tokens'      => 'integer',
        'llm_output_tokens'     => 'integer',
    ];

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(AiAgent::class, 'ai_agent_id');
    }
}
