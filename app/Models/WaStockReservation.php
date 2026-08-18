<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A held quantity of stock for an in-flight natural-language order. Created by
 * InventoryService::hold() while the customer is still choosing/confirming, then
 * either committed (stock decremented) on Confirm, or released (freed) on cancel
 * / abandonment / timeout. `ref` groups all holds for one customer's order.
 */
class WaStockReservation extends Model
{
    protected $fillable = [
        'workspace_id', 'product_id', 'order_id', 'ref', 'qty', 'status', 'expires_at',
    ];

    protected $casts = [
        'qty'        => 'integer',
        'expires_at' => 'datetime',
    ];

    public const STATUSES = ['held', 'committed', 'released'];

    public function product(): BelongsTo
    {
        return $this->belongsTo(WaProduct::class, 'product_id');
    }
}
