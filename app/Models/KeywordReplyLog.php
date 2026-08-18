<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One row per auto-reply fire — feeds the analytics page.
 * Phone + matched text are encrypted at rest (same pattern as Message::body).
 */
class KeywordReplyLog extends Model
{
    protected $fillable = [
        'keyword_reply_id', 'content_id',
        'contact_phone', 'matched_text', 'matched_variant',
        'fired_at',
    ];

    protected $casts = [
        'contact_phone' => 'encrypted',
        'matched_text'  => 'encrypted',
        'fired_at'      => 'datetime',
    ];

    public function keywordReply(): BelongsTo
    {
        return $this->belongsTo(KeywordReply::class);
    }

    public function content(): BelongsTo
    {
        return $this->belongsTo(KeywordReplyContent::class, 'content_id')->withDefault();
    }

    public function scopeForKeywordReply(Builder $q, int $id): Builder
    {
        return $q->where('keyword_reply_id', $id);
    }

    public function scopeRecent(Builder $q, int $hours = 24): Builder
    {
        return $q->where('fired_at', '>=', now()->subHours($hours));
    }
}
