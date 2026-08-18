<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A workspace-generated inbound webhook endpoint. The public URL is
 * /hooks/in/{token}; every request to it lands in incoming_webhook_events
 * and (optionally) is relayed to forward_url.
 */
class IncomingWebhook extends Model
{
    protected $fillable = [
        'workspace_id', 'user_id', 'name', 'token',
        'forward_url', 'forward_enabled', 'is_active',
        'received_count', 'last_received_at',
    ];

    protected $casts = [
        'forward_url'      => 'encrypted',
        'forward_enabled'  => 'boolean',
        'is_active'        => 'boolean',
        'received_count'   => 'integer',
        'last_received_at' => 'datetime',
    ];

    public function events(): HasMany
    {
        return $this->hasMany(IncomingWebhookEvent::class)->orderByDesc('id');
    }

    public function scopeForCurrentWorkspace(Builder $q): Builder
    {
        $wsId = (int) (auth()->user()?->current_workspace_id ?? 0);
        return $wsId ? $q->where('workspace_id', $wsId) : $q->whereRaw('1=0');
    }

    /** Full public URL the operator gives to the external service. */
    public function publicUrl(): string
    {
        return url('/hooks/in/' . $this->token);
    }
}
