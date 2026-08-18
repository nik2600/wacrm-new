<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One WooCommerce store linked to a workspace.
 *
 * Consumer key + secret are encrypted at rest. Unlike Shopify there is
 * no central app — every store generates its own keys in their WC
 * admin (WooCommerce → Settings → Advanced → REST API).
 */
class WoocommerceIntegration extends Model
{
    protected $fillable = [
        'workspace_id', 'user_id',
        'store_url', 'store_name',
        'store_currency', 'store_country', 'store_version',
        'consumer_key', 'consumer_secret',
        'status', 'webhook_secret', 'metadata',
        'last_verified_at', 'connected_at',
        'products_synced_at', 'orders_synced_at', 'customers_synced_at', 'sync_stats',
    ];

    protected $casts = [
        'consumer_key'        => 'encrypted',
        'consumer_secret'     => 'encrypted',
        'metadata'            => 'array',
        'last_verified_at'    => 'datetime',
        'connected_at'        => 'datetime',
        'products_synced_at'  => 'datetime',
        'orders_synced_at'    => 'datetime',
        'customers_synced_at' => 'datetime',
        'sync_stats'          => 'array',
    ];

    protected $hidden = ['consumer_key', 'consumer_secret'];

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
        return $this->hasMany(WoocommerceIntegrationEvent::class, 'integration_id');
    }

    public function logs(): HasMany
    {
        return $this->hasMany(WoocommerceIntegrationLog::class, 'integration_id');
    }

    public function isConnected(): bool
    {
        return $this->status === 'active'
            && !empty($this->consumer_key)
            && !empty($this->consumer_secret);
    }
}
