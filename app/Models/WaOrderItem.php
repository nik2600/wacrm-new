<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Line item on a wa_order. New catalog-driven orders write proper
 * rows here so we can SQL-join against wa_products. Old/manual
 * orders keep using wa_orders.items_json — both rendering paths
 * coexist (WaOrder::lineItems() falls back to items_json when no
 * order_item rows exist).
 *
 * price_minor is per-unit in MINOR units. The webhook handler
 * converts decimal major (Meta) → integer minor when saving.
 */
class WaOrderItem extends Model
{
    protected $fillable = [
        'order_id', 'product_id', 'retailer_id',
        'name', 'image_url',
        'quantity', 'price_minor', 'currency_code',
        'meta_json',
    ];

    protected $casts = [
        'quantity'    => 'integer',
        'price_minor' => 'integer',
        'meta_json'   => 'array',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(WaOrder::class, 'order_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(WaProduct::class, 'product_id')->withDefault();
    }

    public function getLineTotalMinorAttribute(): int
    {
        return $this->price_minor * $this->quantity;
    }

    public function getPriceDisplayAttribute(): string
    {
        return WaProduct::formatCurrency($this->price_minor, $this->currency_code);
    }

    public function getLineTotalDisplayAttribute(): string
    {
        return WaProduct::formatCurrency($this->line_total_minor, $this->currency_code);
    }
}
