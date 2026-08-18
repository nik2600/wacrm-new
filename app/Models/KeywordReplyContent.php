<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One reply variant (text body, image+caption, video, doc, or template).
 * The bot's lookup picks the first row where is_selected=true ordered by
 * sort_order.
 */
class KeywordReplyContent extends Model
{
    protected $fillable = [
        'keyword_reply_id',
        'content_type', 'content',
        'file_path', 'original_name', 'file_size', 'mime_type',
        'template_id',
        'is_selected', 'sort_order',
        // Sprint 7 — multilingual.
        'content_translations',
        // Auto-responder: 'primary' (normal reply) | 'outside_hours' (the
        // Step-3 "we're closed" message). Without this in fillable, create()
        // silently dropped it and every variant defaulted to 'primary'.
        'variant_role',
    ];

    protected $casts = [
        // content holds operator-authored copy that may include personal
        // greetings / contact handles — encrypt at rest.
        'content'              => 'encrypted',
        'is_selected'          => 'boolean',
        'sort_order'           => 'integer',
        'file_size'            => 'integer',
        // Sprint 7 — per-language reply text overrides.
        // Shape: { "en": "Hi", "hi": "नमस्ते", ... }
        'content_translations' => 'array',
    ];

    public function keywordReply(): BelongsTo
    {
        return $this->belongsTo(KeywordReply::class);
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(WaTemplate::class, 'template_id')->withDefault();
    }
}
