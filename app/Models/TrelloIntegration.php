<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One Trello board watched for a WaDesk workspace. API key/secret/token are
 * encrypted at rest. A registered Trello webhook (webhook_id) POSTs card
 * events to /webhooks/trello. Mirrors HubspotIntegration.
 */
class TrelloIntegration extends Model
{
    protected $fillable = [
        'workspace_id', 'user_id',
        'api_key', 'api_secret', 'token',
        'board_id', 'board_name', 'webhook_id',
        'events', 'notify_mode', 'notify_number', 'member_map',
        'status', 'metadata', 'last_event_at', 'connected_at',
    ];

    protected $casts = [
        'api_key'        => 'encrypted',
        'api_secret'     => 'encrypted',
        'token'          => 'encrypted',
        'notify_number'  => 'encrypted',
        'events'         => 'array',
        'member_map'     => 'array',
        'metadata'       => 'array',
        'last_event_at'  => 'datetime',
        'connected_at'   => 'datetime',
    ];

    protected $hidden = ['api_key', 'api_secret', 'token', 'notify_number'];

    /** Default action types we notify on when `events` is null. */
    public const DEFAULT_EVENTS = ['addMemberToCard', 'createCard', 'updateCard', 'deleteCard'];

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function logs(): HasMany
    {
        return $this->hasMany(TrelloIntegrationLog::class, 'integration_id');
    }

    public function isConnected(): bool
    {
        return $this->status === 'active' && !empty($this->token);
    }

    /**
     * The operator-chosen "send from" channel — a WorkspaceEngine sender key
     * ("engine:id"), stored in metadata. Empty means "workspace default". Lets a
     * multi-device / multi-engine workspace pick which connected number card
     * notifications send through.
     */
    public function sendFrom(): string
    {
        return (string) (is_array($this->metadata) ? ($this->metadata['send_from'] ?? '') : '');
    }

    /** Action types this board should fire WhatsApp notifications for. */
    public function enabledEvents(): array
    {
        $e = is_array($this->events) && $this->events ? $this->events : self::DEFAULT_EVENTS;
        // Assignment is the core promise — always on.
        if (!in_array('addMemberToCard', $e, true)) $e[] = 'addMemberToCard';
        return $e;
    }
}
