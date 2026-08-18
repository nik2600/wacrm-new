<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Storefront discount code. Percent or flat, with optional minimum spend,
 * a cap for percent coupons, free-shipping flag, validity window, and a
 * usage limit. Discount is always computed server-side at checkout.
 */
class WaCoupon extends Model
{
    protected $fillable = [
        'workspace_id', 'storefront_id', 'code', 'type', 'amount',
        'min_subtotal_minor', 'max_discount_minor', 'free_shipping',
        'active', 'starts_at', 'expires_at', 'usage_limit', 'used_count',
    ];

    protected $casts = [
        'free_shipping' => 'boolean',
        'active'        => 'boolean',
        'starts_at'     => 'datetime',
        'expires_at'    => 'datetime',
    ];

    /** Is this coupon usable right now for the given subtotal? */
    public function redeemable(int $subtotalMinor): bool
    {
        if (!$this->active) return false;
        if ($this->starts_at && $this->starts_at->isFuture()) return false;
        if ($this->expires_at && $this->expires_at->isPast()) return false;
        if ($this->usage_limit !== null && $this->used_count >= $this->usage_limit) return false;
        if ($this->min_subtotal_minor !== null && $subtotalMinor < $this->min_subtotal_minor) return false;
        return true;
    }

    /** Discount in minor units for a subtotal (0 if not redeemable). */
    public function discountFor(int $subtotalMinor): int
    {
        if (!$this->redeemable($subtotalMinor)) return 0;

        if ($this->type === 'flat') {
            $d = (int) $this->amount;
        } else {
            $d = (int) round($subtotalMinor * min(100, max(0, (int) $this->amount)) / 100);
            if ($this->max_discount_minor !== null) $d = min($d, (int) $this->max_discount_minor);
        }

        return max(0, min($d, $subtotalMinor)); // never exceed the subtotal
    }
}
