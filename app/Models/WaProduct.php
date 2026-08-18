<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * Catalog product owned by a workspace. Mode-independent — the same
 * row works whether the workspace sends via WABA / Baileys / Twilio.
 *
 * Mirrored to Meta's catalog only when the workspace's provider is
 * WABA and the user has toggled "Sync to Meta". The mirror writes
 * back `meta_retailer_id` + `meta_sync_status` to this row.
 *
 * Slug is auto-generated from `name` on save, scoped per workspace
 * so two workspaces can both have a "spring-tee" slug.
 */
class WaProduct extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'workspace_id', 'user_id', 'storefront_id', 'shopify_product_id', 'woo_product_id',
        'sku', 'name', 'slug', 'description', 'body_html',
        'price_minor', 'compare_price_minor', 'currency_code',
        'image_url', 'gallery_json',
        'in_stock', 'stock_qty', 'reserved_qty', 'sort_order', 'status',
        'weight_grams', 'category', 'tags_json', 'aliases_json',
        'availability', 'condition', 'brand', 'product_url', 'google_product_category',
        'meta_retailer_id', 'meta_sync_status', 'meta_synced_at',
        'meta_batch_handle', 'meta_last_error',
    ];

    protected $casts = [
        'price_minor'         => 'integer',
        'compare_price_minor' => 'integer',
        'gallery_json'        => 'array',
        'tags_json'           => 'array',
        'aliases_json'        => 'array',
        'in_stock'            => 'boolean',
        'stock_qty'           => 'integer',
        'reserved_qty'        => 'integer',
        'sort_order'          => 'integer',
        'weight_grams'        => 'integer',
        'meta_synced_at'      => 'datetime',
    ];

    public function getComparePriceMajorAttribute(): ?float
    {
        return $this->compare_price_minor !== null ? $this->compare_price_minor / 100 : null;
    }

    public function getComparePriceDisplayAttribute(): ?string
    {
        if ($this->compare_price_minor === null) return null;
        return self::formatCurrency($this->compare_price_minor, $this->currency_code);
    }

    public function getOnSaleAttribute(): bool
    {
        return $this->compare_price_minor !== null && $this->compare_price_minor > $this->price_minor;
    }

    /**
     * Compute the availability value Meta wants based on our stored
     * status + stock. Operator can override by setting `availability`
     * directly, but most rows derive it: status=active + stock>0 →
     * "in stock", everything else → "out of stock".
     */
    public function getEffectiveAvailabilityAttribute(): string
    {
        if (!empty($this->availability)) return $this->availability;
        if ($this->status !== 'active') return 'discontinued';
        if ($this->stock_qty !== null && $this->stock_qty <= 0) return 'out of stock';
        return 'in stock';
    }

    /**
     * Build the JSON body Meta's /{catalog_id}/products endpoint
     * expects. Used by the batch sync job. All keys/values per Meta's
     * Marketing API product-catalog/products reference.
     *
     * The merchant `retailer_id` is our SKU when set, falling back to
     * a deterministic synthetic ID derived from our PK so products
     * without SKUs still sync. Important: once a retailer_id is used
     * Meta treats it as the stable identity for that product —
     * NEVER rotate this value.
     */
    public function toMetaCatalogPayload(string $shopUrl = ''): array
    {
        $retailerId = $this->meta_retailer_id ?: ($this->sku ?: 'wsn-' . $this->id);
        return array_filter([
            'retailer_id'              => $retailerId,
            'name'                     => $this->name,
            'description'              => mb_substr($this->description ?? $this->name, 0, 9999),
            'availability'             => $this->effective_availability,
            'condition'                => $this->condition ?: 'new',
            'price'                    => $this->price_minor,           // minor units integer
            'currency'                 => $this->currency_code ?: 'INR',
            'image_url'                => $this->image_url,
            'additional_image_urls'    => array_values(array_filter($this->gallery_json ?? [])) ?: null,
            'url'                      => $this->product_url
                ?: ($shopUrl ? rtrim($shopUrl, '/') . '/p/' . $this->slug : null),
            'brand'                    => $this->brand,
            'category'                 => $this->category,
            'google_product_category'  => $this->google_product_category,
            'inventory'                => $this->stock_qty,
        ], fn ($v) => $v !== null && $v !== '');
    }

    protected static function booted(): void
    {
        static::saving(function (self $row) {
            if (empty($row->slug) && !empty($row->name)) {
                $row->slug = self::uniqueSlug($row->workspace_id, $row->name, $row->id);
            }
        });

        // Auto-sync to a connected Meta Commerce catalog. A new or changed
        // product is pushed immediately so the WhatsApp catalog never drifts
        // from the storefront. Bulk imports suppress this (one batched sync
        // instead). pushOne() is fully self-contained and never throws.
        static::created(function (self $row) {
            if (\App\Services\WhatsAppCatalog\CatalogSyncService::isSuppressed()) return;
            app(\App\Services\WhatsAppCatalog\CatalogSyncService::class)->pushOne($row);
        });

        static::updated(function (self $row) {
            if (\App\Services\WhatsAppCatalog\CatalogSyncService::isSuppressed()) return;
            // Only re-push on a real catalog-relevant change. This also stops
            // pushOne()'s own meta_* status write from re-triggering us.
            if (!$row->wasChanged(\App\Services\WhatsAppCatalog\CatalogSyncService::SYNC_FIELDS)) return;
            app(\App\Services\WhatsAppCatalog\CatalogSyncService::class)->pushOne($row);
        });
    }

    public static function uniqueSlug(int $workspaceId, string $name, ?int $ignoreId = null): string
    {
        $base = Str::slug($name) ?: 'product';
        $slug = $base;
        $i = 2;
        while (self::query()
            ->where('workspace_id', $workspaceId)
            ->where('slug', $slug)
            ->when($ignoreId, fn ($q, $id) => $q->where('id', '!=', $id))
            ->exists()) {
            $slug = $base . '-' . $i++;
        }
        return $slug;
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id')->withDefault();
    }

    public function getPriceMajorAttribute(): float
    {
        return $this->price_minor / 100;
    }

    public function getPriceDisplayAttribute(): string
    {
        return self::formatCurrency($this->price_minor, $this->currency_code);
    }

    /**
     * Currency symbol lookup. Falls back to the 3-letter code so a
     * currency we haven't added a symbol for still renders sanely.
     * Used by the model accessors AND the WhatsApp order text.
     */
    public static function currencySymbol(?string $code): string
    {
        return [
            'INR' => '₹', 'USD' => '$', 'EUR' => '€', 'GBP' => '£', 'AED' => 'د.إ',
            'KES' => 'KSh', 'NGN' => '₦', 'CRC' => '₡', 'BRL' => 'R$', 'ZAR' => 'R',
            'PHP' => '₱', 'IDR' => 'Rp', 'MXN' => '$', 'SGD' => 'S$', 'MYR' => 'RM',
            'THB' => '฿', 'VND' => '₫', 'EGP' => 'E£', 'PKR' => '₨', 'BDT' => '৳', 'LKR' => 'Rs',
        ][$code] ?? ((string) $code);
    }

    public static function formatCurrency(int $minor, ?string $code): string
    {
        $sym = self::currencySymbol($code);
        $major = $minor / 100;
        return $sym . ' ' . number_format($major, $major == (int) $major ? 0 : 2);
    }

    public function scopeForWorkspace(Builder $q, ?int $workspaceId): Builder
    {
        return $workspaceId ? $q->where('workspace_id', $workspaceId) : $q;
    }

    public function scopeAvailable(Builder $q): Builder
    {
        return $q->where('in_stock', true);
    }

    /**
     * Sellable quantity right now = stock_qty − reserved_qty. NULL stock_qty
     * means "unlimited" (returns null). Used by InventoryService / the
     * natural-language ordering flow for anti-sellout checks.
     */
    public function availableQty(): ?int
    {
        if ($this->stock_qty === null) return null; // unlimited
        return max(0, (int) $this->stock_qty - (int) ($this->reserved_qty ?? 0));
    }

    /** All language aliases (lowercased) the order-parser can match on. */
    public function aliasStrings(): array
    {
        $out = [];
        foreach ((array) ($this->aliases_json ?? []) as $a) {
            $val = is_array($a) ? ($a['alias'] ?? $a['name'] ?? '') : $a;
            $val = trim(mb_strtolower((string) $val));
            if ($val !== '') $out[] = $val;
        }
        return $out;
    }

    public function reservations(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(WaStockReservation::class, 'product_id');
    }

    public function scopeOrdered(Builder $q): Builder
    {
        return $q->orderBy('sort_order')->orderByDesc('id');
    }
}
