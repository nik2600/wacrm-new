<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiAgent extends Model
{
    protected $fillable = [
        'workspace_id', 'name', 'provider', 'model',
        'system_prompt', 'tone', 'avatar_color',
        'auto_respond', 'max_tokens', 'temperature', 'is_active',
        'messages_sent',
        // Handoff settings.
        'max_replies_per_conversation',
        'handoff_keywords',
        'handoff_low_score_threshold',
        'handoff_low_score_window',
        'handoff_enabled',
        'use_saved_replies',
        // Multi-device — null/empty array = any device (default).
        'device_ids',
        // Voice-AI channels (Phase A: voice notes on both stacks;
        // Phase D: voice calls on WABA). Off by default — operator
        // explicitly opts each AiAgent into each voice channel.
        'voice_note_enabled', 'voice_call_enabled',
        'voice_provider', 'voice_id', 'voice_language',
        'asr_provider', 'asr_language',
        'max_voice_notes_per_day',
    ];

    protected $casts = [
        'auto_respond'  => 'boolean',
        'is_active'     => 'boolean',
        'max_tokens'    => 'integer',
        'temperature'   => 'integer',
        'messages_sent' => 'integer',
        'max_replies_per_conversation' => 'integer',
        'handoff_keywords'             => 'array',
        'handoff_low_score_threshold'  => 'integer',
        'handoff_low_score_window'     => 'integer',
        'handoff_enabled'              => 'boolean',
        'use_saved_replies'            => 'boolean',
        'device_ids'                   => 'array',
        'voice_note_enabled'           => 'boolean',
        'voice_call_enabled'           => 'boolean',
        'max_voice_notes_per_day'      => 'integer',
    ];

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function scopeForWorkspace(Builder $q, int $workspaceId): Builder
    {
        return $q->where('workspace_id', $workspaceId);
    }

    public function scopeActive(Builder $q): Builder
    {
        return $q->where('is_active', true);
    }

    /** Temperature stored as integer * 10; return float for LLM calls. */
    public function temperatureFloat(): float
    {
        return round($this->temperature / 10, 1);
    }

    /**
     * Multi-device gate. Returns true when this agent is allowed to
     * auto-respond on the given paired device.
     *
     *   - device_ids null or empty → any device (default; preserves
     *     pre-multi-device behavior so old rows aren't surprised)
     *   - device_ids non-empty array → only those device ids
     *
     * The auto-respond pipeline calls this before composing a reply,
     * so a scoped agent simply skips conversations on devices it
     * doesn't handle — no error, no log spam.
     */
    public function handlesDevice(?int $deviceId): bool
    {
        $ids = is_array($this->device_ids) ? $this->device_ids : [];
        if (empty($ids)) return true;
        if ($deviceId === null) return false;
        return in_array((int) $deviceId, array_map('intval', $ids), true);
    }

    public function toCard(): array
    {
        return [
            'id'            => $this->id,
            'name'          => $this->name,
            'provider'      => $this->provider,
            'model'         => $this->model,
            'system_prompt' => (string) $this->system_prompt,
            'tone'          => $this->tone,
            'avatar_color'  => $this->avatar_color,
            'auto_respond'  => $this->auto_respond,
            'max_tokens'    => $this->max_tokens,
            'temperature'   => $this->temperature,
            'is_active'     => $this->is_active,
            'messages_sent' => $this->messages_sent,
            // Handoff config so the AI-agent form can rehydrate on edit.
            'max_replies_per_conversation' => (int) ($this->max_replies_per_conversation ?? 10),
            'handoff_keywords'             => is_array($this->handoff_keywords) ? $this->handoff_keywords : [],
            'handoff_low_score_threshold'  => (int) ($this->handoff_low_score_threshold ?? 0),
            'handoff_low_score_window'     => (int) ($this->handoff_low_score_window ?? 3),
            'handoff_enabled'              => (bool) ($this->handoff_enabled ?? true),
            'use_saved_replies'            => (bool) ($this->use_saved_replies ?? false),
            'device_ids'                   => is_array($this->device_ids) ? array_values(array_map('intval', $this->device_ids)) : [],
            // Voice-AI config so the Voice tab on the edit form can rehydrate.
            'voice_note_enabled'      => (bool) ($this->voice_note_enabled ?? false),
            'voice_call_enabled'      => (bool) ($this->voice_call_enabled ?? false),
            'voice_provider'          => $this->voice_provider,
            'voice_id'                => $this->voice_id,
            'voice_language'          => $this->voice_language ?? 'en',
            'asr_provider'            => $this->asr_provider,
            'asr_language'            => $this->asr_language,
            'max_voice_notes_per_day' => (int) ($this->max_voice_notes_per_day ?? 200),
        ];
    }
}
