<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * An in-flight natural-language order awaiting the customer's Confirm. One open
 * cart per (workspace, customer). Converted to a WaOrder on confirm; its stock
 * holds (keyed by `ref`) commit on confirm / release on cancel/timeout.
 */
class WaPendingOrder extends Model
{
    protected $fillable = [
        'workspace_id', 'customer_phone', 'ref', 'items_json', 'unavailable_json',
        'total_minor', 'currency_code', 'group_code', 'status', 'order_id', 'expires_at',
    ];

    protected $casts = [
        'items_json'       => 'array',
        'unavailable_json' => 'array',
        'total_minor'      => 'integer',
        'expires_at'       => 'datetime',
    ];

    /** Stable hold ref for a customer's in-flight order. */
    public static function refFor(int $workspaceId, string $phone): string
    {
        return 'ord:' . $workspaceId . ':' . preg_replace('/\D+/', '', $phone);
    }
}
