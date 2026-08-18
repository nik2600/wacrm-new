<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WebhookDelivery extends Model
{
    use HasFactory;

    protected $table = 'webhook_deliveries';

    protected $fillable = [
        'webhook_id', 'event_name', 'status_code', 'latency_ms',
        'is_retry', 'attempts', 'payload', 'response_body', 'error', 'fired_at',
    ];

    protected $casts = [
        'is_retry'      => 'boolean',
        'attempts'      => 'integer',
        'fired_at'      => 'datetime',
        'payload'       => 'encrypted',
        'response_body' => 'encrypted',
    ];

    public function webhook()
    {
        return $this->belongsTo(Webhook::class);
    }

    public function getIsSuccessAttribute(): bool
    {
        return $this->status_code !== null && $this->status_code >= 200 && $this->status_code < 300;
    }
}
