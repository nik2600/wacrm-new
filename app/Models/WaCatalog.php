<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Per-workspace binding to a Meta Commerce Catalog.
 *
 * One row per (workspace, provider). The access_token_enc field is
 * Laravel-encrypted at rest — never returned in JSON, never logged.
 */
class WaCatalog extends Model
{
    public const PROVIDER_META_CLOUD = 'meta_cloud';
    public const PROVIDER_DIALOG_360 = 'dialog_360';

    protected $fillable = [
        'workspace_id', 'provider',
        'catalog_id', 'catalog_name',
        'waba_id', 'phone_number_id',
        'access_token_enc',
        'is_cart_enabled', 'is_catalog_visible',
        'last_synced_at', 'meta_json',
    ];

    protected $casts = [
        'access_token_enc'    => 'encrypted',
        'is_cart_enabled'     => 'boolean',
        'is_catalog_visible'  => 'boolean',
        'last_synced_at'      => 'datetime',
        'meta_json'           => 'array',
    ];

    protected $hidden = ['access_token_enc'];

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    /**
     * Base URL the catalog provider talks to.
     * Centralised so swapping API versions / hosts is one place.
     */
    public function providerBaseUrl(): string
    {
        return match ($this->provider) {
            self::PROVIDER_DIALOG_360 => 'https://waba-v2.360dialog.io',
            default                    => 'https://graph.facebook.com/v22.0',
        };
    }

    /**
     * Auth header the provider expects. 360dialog uses a custom
     * header instead of Bearer auth.
     */
    public function authHeader(): array
    {
        $token = $this->access_token_enc;
        return match ($this->provider) {
            self::PROVIDER_DIALOG_360 => ['D360-API-KEY' => $token],
            default                    => ['Authorization' => 'Bearer ' . $token],
        };
    }
}
