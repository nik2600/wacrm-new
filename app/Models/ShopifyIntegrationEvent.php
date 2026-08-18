<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShopifyIntegrationEvent extends Model
{
    protected $fillable = [
        'integration_id', 'event_type', 'is_active',
        'template_id', 'var_map', 'send_to', 'admin_number', 'delay_seconds',
    ];

    protected $casts = [
        'is_active'     => 'boolean',
        'delay_seconds' => 'integer',
        'var_map'       => 'array',
    ];

    public function integration(): BelongsTo
    {
        return $this->belongsTo(ShopifyIntegration::class, 'integration_id');
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(WaTemplate::class, 'template_id');
    }
}
