<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * Coupon code applied at checkout. Admin manages from /admin/coupons.
 *
 *   type=percent → amount is 0-100 (e.g. 20 = 20% off)
 *   type=fixed   → amount is a currency value (e.g. 500 = 500 off)
 *
 * `applicable_package_ids` (JSON) restricts the code to specific
 * plans; null means it applies to any plan.
 *
 * Use `Coupon::resolve($code, $package, $amount)` to validate and
 * compute the discount in one call — handles all the edge cases
 * (expired, exhausted, min-order, plan-restricted, inactive).
 */
class Coupon extends Model
{
    protected $fillable = [
        'code', 'description', 'admin_note', 'type', 'amount',
        'max_discount_amount', 'currency_code',
        'min_order_amount', 'max_uses', 'uses_count', 'per_user_limit',
        'applicable_package_ids', 'starts_at', 'expires_at',
        'is_active', 'first_purchase_only', 'stackable_with_other',
    ];

    protected $casts = [
        'amount'                 => 'decimal:4',
        'max_discount_amount'    => 'decimal:4',
        'min_order_amount'       => 'decimal:4',
        'applicable_package_ids' => 'array',
        'starts_at'              => 'datetime',
        'expires_at'             => 'datetime',
        'is_active'              => 'boolean',
        'first_purchase_only'    => 'boolean',
        'stackable_with_other'   => 'boolean',
    ];

    /**
     * Resolve a code string into a discount decision.
     *
     * The optional $user + $currency context unlock the per-customer and
     * currency rules — pass them at checkout. Older 3-arg callers still work
     * (those rules simply don't apply without the context they need).
     *
     * @param  \App\Models\User|null  $user          the buyer (for per-user limit + first-purchase)
     * @param  string|null            $currency      the order currency (for the currency lock)
     * @param  array                  $otherCoupons  other codes already on the order (for non-stackable)
     * @return array{ok: bool, message: string, coupon: ?Coupon, discount: float}
     */
    public static function resolve(string $code, Package $package, float $amount, ?\App\Models\User $user = null, ?string $currency = null, array $otherCoupons = []): array
    {
        $code = trim(strtoupper($code));
        if ($code === '') return self::reject('Enter a coupon code.');

        $coupon = static::query()->whereRaw('UPPER(code) = ?', [$code])->first();
        if (!$coupon) return self::reject('Coupon "' . $code . '" not recognised.');
        if (!$coupon->is_active) return self::reject('This coupon is disabled.');

        $now = Carbon::now();
        if ($coupon->starts_at && $coupon->starts_at->gt($now))   return self::reject('This coupon is not active yet.');
        if ($coupon->expires_at && $coupon->expires_at->lt($now)) return self::reject('This coupon has expired.');
        if ($coupon->max_uses !== null && (int) $coupon->uses_count >= (int) $coupon->max_uses) {
            return self::reject('This coupon is fully used.');
        }

        // Currency lock — coupon is priced for one currency only.
        if ($coupon->currency_code && $currency
            && strtoupper((string) $coupon->currency_code) !== strtoupper($currency)) {
            return self::reject('This coupon is only valid for payments in ' . strtoupper((string) $coupon->currency_code) . '.');
        }

        // Non-stackable — refuse if another (different) coupon is already applied.
        if (!$coupon->stackable_with_other) {
            $others = array_filter(
                array_map(fn ($c) => strtoupper(trim((string) $c)), $otherCoupons),
                fn ($c) => $c !== '' && $c !== $code
            );
            if (!empty($others)) {
                return self::reject('This coupon cannot be combined with another coupon.');
            }
        }

        // Per-customer rules — need a known user + the orders ledger
        // (a paid order with this coupon = one redemption by that user).
        if ($user) {
            if ($coupon->first_purchase_only
                && \App\Models\Order::query()->where('user_id', $user->id)->where('status', 'paid')->exists()) {
                return self::reject('This coupon is valid on your first purchase only.');
            }
            if ($coupon->per_user_limit !== null && (int) $coupon->per_user_limit > 0) {
                $usedByUser = \App\Models\Order::query()
                    ->where('user_id', $user->id)
                    ->where('coupon_id', $coupon->id)
                    ->where('status', 'paid')
                    ->count();
                if ($usedByUser >= (int) $coupon->per_user_limit) {
                    return self::reject('You have already used this coupon the maximum number of times.');
                }
            }
        }

        if ($coupon->min_order_amount !== null && $amount < (float) $coupon->min_order_amount) {
            return self::reject('Order is below the coupon minimum.');
        }
        $applicable = $coupon->applicable_package_ids;
        if (is_array($applicable) && !empty($applicable) && !in_array((int) $package->id, array_map('intval', $applicable), true)) {
            return self::reject('This coupon does not apply to this plan.');
        }

        // Compute discount.
        if ($coupon->type === 'percent') {
            $discount = round($amount * ((float) $coupon->amount) / 100, 2);
            // Cap the percentage discount at max_discount_amount when set.
            if ($coupon->max_discount_amount !== null && (float) $coupon->max_discount_amount > 0) {
                $discount = min($discount, round((float) $coupon->max_discount_amount, 2));
            }
        } else {
            $discount = round((float) $coupon->amount, 2);
        }
        $discount = min($discount, $amount); // never discount more than the order
        if ($discount <= 0) return self::reject('Coupon yielded no discount.');

        return ['ok' => true, 'message' => 'Coupon applied.', 'coupon' => $coupon, 'discount' => (float) $discount];
    }

    /** Uniform rejection shape for resolve(). */
    private static function reject(string $message): array
    {
        return ['ok' => false, 'message' => $message, 'coupon' => null, 'discount' => 0];
    }
}
