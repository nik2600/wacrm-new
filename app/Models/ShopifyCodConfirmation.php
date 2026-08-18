<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShopifyCodConfirmation extends Model
{
    protected $fillable = [
        'integration_id', 'workspace_id', 'shopify_order_id',
        'order_name', 'customer_phone', 'status',
    ];

    public function integration(): BelongsTo
    {
        return $this->belongsTo(ShopifyIntegration::class, 'integration_id');
    }
}
