<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A merchant-managed customer profile (Name / Company / delivery Address) keyed
 * by phone. Set from the shop dashboard so a known customer's address auto-fills
 * in the WhatsApp ordering flow. See OrderingService::shippingFor().
 */
class WaCustomerProfile extends Model
{
    protected $fillable = ['workspace_id', 'phone', 'name', 'company', 'address'];

    /** Canonical digits-only phone for matching against orders/groups. */
    public static function digits(string $phone): string
    {
        return preg_replace('/\D+/', '', $phone);
    }

    public function scopeForWorkspace($q, int $wsId)
    {
        return $q->where('workspace_id', $wsId);
    }
}
