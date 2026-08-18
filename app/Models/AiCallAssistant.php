<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Voice/phone-call AI assistant configuration. One row per assistant,
 * each row owned by a workspace. The Node runtime reads this when a
 * Twilio call lands and the assistant is the routing target.
 *
 * Secrets (AI key, voice key) are encrypted at rest. Both are OPTIONAL
 * overrides — when blank, AiKeyResolver picks up the admin's global
 * key from `admin_ai_keys` / `admin_voice_keys`. This matches the
 * project's admin-billing policy [[feedback_admin_billing_ux]].
 */
class AiCallAssistant extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'workspace_id', 'user_id', 'name', 'slug',
        'greeting_text', 'status', 'is_active',
        'ai_provider', 'ai_model', 'ai_api_key_encrypted',
        'ai_system_prompt', 'knowledge_base_url', 'natural_conciseness',
        'voice_provider', 'voice_api_key_encrypted', 'voice_id', 'voice_settings_json',
        'stt_provider', 'stt_settings_json',
        'record_agent', 'record_user', 'auto_logging',
        'exit_keywords_json', 'last_greeting', 'meta_json',
        // Numbers this assistant answers. Empty = workspace catch-all.
        'device_ids',
    ];

    protected $casts = [
        'is_active'           => 'boolean',
        'natural_conciseness' => 'boolean',
        'record_agent'        => 'boolean',
        'record_user'         => 'boolean',
        'auto_logging'        => 'boolean',
        'voice_settings_json' => 'array',
        'stt_settings_json'   => 'array',
        'exit_keywords_json'  => 'array',
        'meta_json'           => 'array',
        'device_ids'          => 'array',
        // Per-assistant BYOK secrets — encrypted at rest. Reading the
        // attribute yields plaintext.
        'ai_api_key_encrypted'    => 'encrypted',
        'voice_api_key_encrypted' => 'encrypted',
    ];

    protected $hidden = ['ai_api_key_encrypted', 'voice_api_key_encrypted'];

    public function tools(): HasMany
    {
        return $this->hasMany(AiCallAssistantTool::class, 'assistant_id')->orderBy('sort_order');
    }

    public function logs(): HasMany
    {
        return $this->hasMany(AiCallLog::class, 'assistant_id');
    }

    /** Quick boolean — assistant is configured + on. */
    public function isLive(): bool
    {
        return $this->is_active && $this->status === 'live';
    }
}
