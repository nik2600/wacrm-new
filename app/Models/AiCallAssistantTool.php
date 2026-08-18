<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One Function/Tool exposed to the AI mid-call. The runtime tells the
 * model "you can call this function if the caller asks about X" — when
 * the model returns a tool_call payload, Node hits http_url with the
 * configured method + headers, passes parameter values extracted from
 * the conversation, and folds the response back into the chat.
 */
class AiCallAssistantTool extends Model
{
    use HasFactory;

    protected $fillable = [
        'assistant_id', 'function_name', 'trigger_keywords_json',
        'http_method', 'http_url', 'headers_json', 'parameters_json',
        'sort_order',
    ];

    protected $casts = [
        'trigger_keywords_json' => 'array',
        'headers_json'          => 'array',
        'parameters_json'       => 'array',
    ];

    public function assistant(): BelongsTo
    {
        return $this->belongsTo(AiCallAssistant::class, 'assistant_id');
    }
}
