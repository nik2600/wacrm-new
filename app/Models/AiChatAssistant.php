<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Chat-mode AI persona used by chatbot widgets (and any future text
 * channel). Voice phone-call AI lives in `ai_call_assistants` — kept
 * separate because the column shapes diverge sharply.
 */
class AiChatAssistant extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'ai_chat_assistants';

    protected $fillable = [
        'workspace_id', 'user_id', 'name', 'slug', 'greeting',
        'system_prompt', 'tone', 'language',
        'ai_provider', 'ai_model', 'reply_max_tokens', 'temperature',
        'fallback_message', 'handoff_enabled', 'handoff_keyword', 'handoff_message',
        'status',
    ];

    protected $casts = [
        'reply_max_tokens' => 'integer',
        'temperature'      => 'float',
        'handoff_enabled'  => 'boolean',
    ];

    public function trainingSources(): HasMany
    {
        return $this->hasMany(AiTrainingSource::class, 'assistant_id');
    }

    public function widgets(): HasMany
    {
        return $this->hasMany(ChatbotWidget::class, 'assistant_id');
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }
}
