<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WoocommerceCartRecovery extends Model
{
    protected $fillable = [
        'integration_id', 'workspace_id', 'checkout_token',
        'customer_phone', 'customer_email', 'scheduled_ids', 'status',
    ];

    protected $casts = ['scheduled_ids' => 'array'];
}
