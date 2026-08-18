<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One HubSpot portal linked to a workspace. OAuth tokens are encrypted
 * at rest. Mirrors the ShopifyIntegration pattern; the only meaningful
 * difference is HubSpot requires refresh-token rotation on each call.
 */
class HubspotIntegration extends Model
{
    protected $fillable = [
        'workspace_id', 'user_id',
        'portal_id', 'portal_name', 'portal_email',
        'access_token', 'refresh_token', 'access_token_expires_at',
        'scopes', 'status', 'metadata',
        'last_verified_at', 'connected_at',
    ];

    protected $casts = [
        'access_token'             => 'encrypted',
        'refresh_token'            => 'encrypted',
        'metadata'                 => 'array',
        'access_token_expires_at'  => 'datetime',
        'last_verified_at'         => 'datetime',
        'connected_at'             => 'datetime',
    ];

    protected $hidden = ['access_token', 'refresh_token'];

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function logs(): HasMany
    {
        return $this->hasMany(HubspotIntegrationLog::class, 'integration_id');
    }

    public function isConnected(): bool
    {
        return $this->status === 'active' && !empty($this->access_token);
    }
}
