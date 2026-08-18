<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class AiTrainingSource extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'ai_training_sources';

    protected $fillable = [
        'workspace_id', 'assistant_id', 'user_id',
        'kind', 'label', 'url', 'file_path',
        'content', 'question', 'answer',
        'status', 'tokens_estimate', 'error',
    ];

    public function assistant(): BelongsTo
    {
        return $this->belongsTo(AiChatAssistant::class, 'assistant_id');
    }

    /**
     * The text we feed into the model. For Q&A pairs we synthesise a
     * "Q: ... A: ..." block so the AI sees both sides; for URL/file/
     * text we just hand back the extracted body.
     */
    public function renderedText(): string
    {
        if ($this->kind === 'qa') {
            return "Q: " . trim((string) $this->question) . "\nA: " . trim((string) $this->answer);
        }
        return (string) ($this->content ?? '');
    }
}
