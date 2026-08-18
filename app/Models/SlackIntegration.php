<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One Slack workspace linked to a WaDesk workspace. The bot token +
 * signing secret are encrypted at rest. Inbound slash commands are matched
 * back to this row by team_id. Mirrors HubspotIntegration.
 */
class SlackIntegration extends Model
{
    protected $fillable = [
        'workspace_id', 'user_id',
        'team_id', 'team_name', 'bot_user_id',
        'bot_token', 'signing_secret', 'slash_command',
        'status', 'metadata', 'last_used_at', 'connected_at',
    ];

    protected $casts = [
        'bot_token'      => 'encrypted',
        'signing_secret' => 'encrypted',
        'metadata'       => 'array',
        'last_used_at'   => 'datetime',
        'connected_at'   => 'datetime',
    ];

    protected $hidden = ['bot_token', 'signing_secret'];

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function logs(): HasMany
    {
        return $this->hasMany(SlackIntegrationLog::class, 'integration_id');
    }

    public function isConnected(): bool
    {
        return $this->status === 'active' && !empty($this->bot_token);
    }

    /**
     * The operator-chosen "send from" channel — a WorkspaceEngine sender key
     * ("engine:id"), stored in metadata. Empty string means "workspace default"
     * (the dispatcher resolves the primary device). Lets a multi-device / multi-
     * engine workspace pick which connected number the /wa command sends through.
     */
    public function sendFrom(): string
    {
        return (string) (is_array($this->metadata) ? ($this->metadata['send_from'] ?? '') : '');
    }
}
