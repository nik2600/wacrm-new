<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShopifyStockWaitlist extends Model
{
    protected $table = 'shopify_stock_waitlist';

    protected $fillable = [
        'integration_id', 'workspace_id', 'shopify_product_id',
        'product_name', 'customer_phone', 'status', 'notified_at',
    ];

    protected $casts = ['notified_at' => 'datetime'];

    public function integration(): BelongsTo
    {
        return $this->belongsTo(ShopifyIntegration::class, 'integration_id');
    }
}
