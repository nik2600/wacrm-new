<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ConversationNote extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'conversation_id', 'workspace_id', 'user_id',
        'body', 'mentions', 'is_pinned', 'edited_at',
    ];

    protected $casts = [
        // body holds operator commentary about a customer — encrypt at rest
        // alongside the rest of the inbox PII.
        'body'      => 'encrypted',
        'mentions'  => 'array',
        'is_pinned' => 'boolean',
        'edited_at' => 'datetime',
    ];

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id')->withDefault();
    }
}
