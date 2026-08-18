<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * An abandoned storefront cart pending a WhatsApp recovery nudge (S3).
 */
class StorefrontCartRecovery extends Model
{
    protected $fillable = [
        'workspace_id', 'storefront_id', 'customer_phone', 'customer_name',
        'items_json', 'subtotal_minor', 'currency_code', 'scheduled_ids', 'status',
    ];

    protected $casts = [
        'items_json'     => 'array',
        'scheduled_ids'  => 'array',
        'subtotal_minor' => 'integer',
    ];
}
