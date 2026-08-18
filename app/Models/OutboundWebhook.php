<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Customer-configured webhook endpoint for CRM / automation integrations.
 * Each workspace can register multiple URLs that subscribe to a subset of
 * conversation lifecycle events. The OutboundWebhookDispatcher service
 * fires POSTs (HMAC-signed if `secret` is set) when those events fire.
 *
 * Events emitted:
 *   - conversation.created
 *   - conversation.assigned
 *   - conversation.resolved
 *   - conversation.reopened
 *   - conversation.replied      (outbound message sent)
 *   - conversation.received     (inbound message)
 *   - note.added
 *
 * Payload shape lives in OutboundWebhookDispatcher::buildPayload().
 */
class OutboundWebhook extends Model
{
    protected $fillable = [
        'workspace_id', 'name', 'url',
        'events', 'secret',
        'is_active', 'fired_count', 'failed_count',
        'last_fired_at', 'last_error',
    ];

    protected $casts = [
        'events'        => 'array',
        'secret'        => 'encrypted',   // HMAC signing key — encrypted at rest, decrypts transparently on read
        'is_active'     => 'boolean',
        'fired_count'   => 'integer',
        'failed_count'  => 'integer',
        'last_fired_at' => 'datetime',
    ];

    protected $hidden = ['secret'];

    public function scopeForWorkspace(Builder $q, int $workspaceId): Builder
    {
        return $q->where('workspace_id', $workspaceId);
    }

    public function scopeActive(Builder $q): Builder
    {
        return $q->where('is_active', true);
    }

    /** Does this hook subscribe to the given event name? */
    public function subscribes(string $event): bool
    {
        $events = $this->events ?? [];
        if (!is_array($events) || empty($events)) return false;
        return in_array($event, $events, true) || in_array('*', $events, true);
    }
}
