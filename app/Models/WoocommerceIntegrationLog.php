<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WoocommerceIntegrationLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'integration_id', 'event_type', 'status',
        'recipient', 'payload', 'response', 'error',
        'created_at',
    ];

    protected $casts = [
        'payload'    => 'array',
        'response'   => 'array',
        'created_at' => 'datetime',
    ];

    public function integration(): BelongsTo
    {
        return $this->belongsTo(WoocommerceIntegration::class, 'integration_id');
    }
}
