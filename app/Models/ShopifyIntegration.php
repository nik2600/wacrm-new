<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One Shopify store linked to a workspace. There can be many per workspace
 * (multi-store), but a given (workspace, store_url) pair is unique.
 *
 * The access token is stored encrypted at rest via Eloquent's `encrypted`
 * cast — never log or surface it raw.
 */
class ShopifyIntegration extends Model
{
    protected $fillable = [
        'workspace_id', 'user_id',
        'store_url', 'store_name', 'shop_id',
        'shop_email', 'shop_owner', 'shop_plan',
        'shop_currency', 'shop_country',
        'access_token', 'scopes',
        'status', 'webhook_secret', 'metadata',
        'last_verified_at', 'connected_at',
        'products_synced_at', 'orders_synced_at', 'customers_synced_at', 'sync_stats',
    ];

    protected $casts = [
        'access_token'        => 'encrypted',
        'metadata'            => 'array',
        'sync_stats'          => 'array',
        'last_verified_at'    => 'datetime',
        'connected_at'        => 'datetime',
        'products_synced_at'  => 'datetime',
        'orders_synced_at'    => 'datetime',
        'customers_synced_at' => 'datetime',
    ];

    // webhook_secret is NOT encrypted: it is the opaque routing token embedded
    // in the public /shopify/webhook/{secret} URL and is looked up by indexed
    // equality (ShopifyController::webhook — WHERE webhook_secret = ?), which a
    // non-deterministic `encrypted` cast would break. Shopify HMAC verification
    // uses the app-level shopify_client_secret (already encrypted), not this
    // value. We keep it out of serialized output as defense-in-depth.
    protected $hidden = ['access_token', 'webhook_secret'];

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(ShopifyIntegrationEvent::class, 'integration_id');
    }

    public function logs(): HasMany
    {
        return $this->hasMany(ShopifyIntegrationLog::class, 'integration_id');
    }

    public function isConnected(): bool
    {
        return $this->status === 'active' && !empty($this->access_token);
    }
}
