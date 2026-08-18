<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * Per-workspace storefront — controls the public site URL, theme,
 * and brand settings. The default subdomain (<slug>.<host>) always
 * works; custom_domain is opt-in and verified via DNS lookup before
 * being marked usable.
 *
 * Themes are referenced by key, resolved at render time to a Blade
 * view at `storefront.themes.<theme_key>`. Adding a new theme = ship
 * a new Blade file, no migration needed.
 */
class WaStorefront extends Model
{
    protected $fillable = [
        'workspace_id', 'device_id', 'shop_name', 'slug', 'custom_domain', 'custom_domain_verified',
        'theme_key', 'enabled', 'settings_json',
        'shipping_json', 'payment_provider', 'payment_config_json', 'currency_code',
    ];

    protected $casts = [
        'custom_domain_verified' => 'boolean',
        'enabled'                => 'boolean',
        'settings_json'          => 'array',
        'shipping_json'          => 'array',
        'payment_config_json'    => 'array',
    ];

    /**
     * Compute the shipping fee for a given subtotal (both in minor
     * units / paise). Returns 0 when shipping_json is empty or the
     * subtotal qualifies for free shipping.
     */
    public function shippingFee(int $subtotalMinor): int
    {
        $cfg = $this->shipping_json ?? [];
        if (empty($cfg)) return 0;

        $freeAbove = (int) (($cfg['free_above_minor'] ?? 0));
        if ($freeAbove > 0 && $subtotalMinor >= $freeAbove) return 0;

        return (int) (($cfg['flat_minor'] ?? 0));
    }

    public const DEFAULT_THEME = 'aurora';

    public const THEMES = [
        'aurora'   => 'Aurora — minimal',
        'meridian' => 'Meridian — magazine',
        'verdure'  => 'Verdure — organic',
        'bazaar'   => 'Bazaar — colorful grid',
        'noir'     => 'Noir — dark luxe',
        'kraft'    => 'Kraft — handmade',
        'mercato'  => 'Mercato — deli / cafe',
        'studio'   => 'Studio — portfolio',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $row) {
            if (empty($row->slug)) {
                $row->slug = self::uniqueSlug($row->workspace_id);
            }
            if (empty($row->theme_key)) {
                $row->theme_key = self::DEFAULT_THEME;
            }
        });
    }

    public static function uniqueSlug(?int $workspaceId): string
    {
        $base = $workspaceId
            ? Str::slug(Workspace::find($workspaceId)?->name ?: 'shop')
            : 'shop';
        $slug = $base ?: 'shop';
        $i = 2;
        while (self::where('slug', $slug)->exists()) {
            $slug = $base . '-' . $i++;
        }
        return $slug;
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(WaOrder::class, 'storefront_id');
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }

    /**
     * Returns the buyer-facing URL. Three modes:
     *   1) custom_domain verified → https://shop.brand.com
     *   2) STOREFRONT_HOST env set to a real public host → https://{slug}.shops.brand.com
     *   3) otherwise (local dev, raw IP, "localhost") → path-based /s/{slug} on whatever
     *      origin is serving THIS request (so users browsing via 192.168.x.x see that,
     *      never a stale "localhost" from a misconfigured APP_URL).
     */
    public function getPublicUrlAttribute(): string
    {
        if ($this->custom_domain && $this->custom_domain_verified) {
            return 'https://' . $this->custom_domain;
        }

        $configuredHost = config('storefront.subdomain_host');
        $appHost = parse_url(config('app.url'), PHP_URL_HOST) ?: 'localhost';

        // A host is "real" enough for subdomain mode only if it was
        // explicitly set AND isn't localhost AND isn't a raw IPv4 — DNS
        // wildcards don't work for either of those in a browser.
        $hostUsable = $configuredHost
            && $configuredHost !== 'localhost'
            && $configuredHost !== $appHost
            && !filter_var(explode(':', $configuredHost)[0], FILTER_VALIDATE_IP);

        if ($hostUsable) {
            return 'https://' . $this->slug . '.' . $configuredHost;
        }

        // Local / IP-based dev fallback. url() resolves against the live
        // request root, so it matches the host the user is actually on AND
        // preserves any sub-path the app is mounted under (e.g. a cPanel
        // deploy served from https://b2sender.com/public/s/...). In console
        // contexts (queue jobs) it falls back to the APP_URL config.
        return url('/s/' . $this->slug);
    }

    public function getHasUsableConnectionAttribute(): bool
    {
        return (bool) $this->device_id;
    }

    public function scopeEnabled(Builder $q): Builder
    {
        return $q->where('enabled', true);
    }
}
