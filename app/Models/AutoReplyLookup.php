<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Audit row for every inbound the bot asked us to match. Powers the
 * conversion funnel and the latency KPI on the keyword analytics page.
 *
 * One row per /api/keyword-replies hit — matched or not.
 */
class AutoReplyLookup extends Model
{
    public $timestamps = false; // only created_at, no updates

    protected $fillable = [
        'device_id', 'matched_keyword_reply_id',
        'contact_phone', 'query_text', 'latency_ms',
        'created_at',
    ];

    protected $casts = [
        'contact_phone' => 'encrypted',
        'query_text'    => 'encrypted',
        'created_at'    => 'datetime',
        'latency_ms'    => 'integer',
    ];

    public function scopeForDevice(Builder $q, int $deviceId): Builder
    {
        return $q->where('device_id', $deviceId);
    }

    public function scopeMatched(Builder $q): Builder
    {
        return $q->whereNotNull('matched_keyword_reply_id');
    }

    public function scopeForRule(Builder $q, int $keywordReplyId): Builder
    {
        return $q->where('matched_keyword_reply_id', $keywordReplyId);
    }

    public function scopeRecent(Builder $q, int $days = 30): Builder
    {
        return $q->where('created_at', '>=', now()->subDays($days));
    }
}
