<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A moderated product review. Submitted from the public storefront
 * (status=pending) and surfaced on the product page once a merchant
 * approves it.
 */
class WaProductReview extends Model
{
    protected $fillable = [
        'workspace_id', 'storefront_id', 'product_id', 'order_id',
        'customer_name', 'rating', 'body', 'status',
    ];

    protected $casts = [
        'rating' => 'integer',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(WaProduct::class, 'product_id');
    }

    public function scopeApproved($q)
    {
        return $q->where('status', 'approved');
    }
}
