<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A live recurring subscription on a workspace. The gateway charges the
 * customer automatically each cycle and fires a renewal webhook; we then
 * extend the workspace's plan_ends_at by one more period (see
 * SubscriptionService). This is how paid plans auto-renew WITHOUT a Laravel
 * scheduler — the gateway is the clock, the webhook is the tick.
 *
 * gateway_subscription_id is the join key for renewal webhooks (Stripe sub_…,
 * PayPal I-…, Razorpay sub_…). It's captured on the first successful charge.
 */
class Subscription extends Model
{
    protected $fillable = [
        'workspace_id', 'user_id', 'package_id', 'plan_id',
        'gateway', 'gateway_subscription_id', 'gateway_plan_id', 'gateway_customer_id',
        'billing_cycle', 'status', 'amount', 'currency',
        'current_period_end', 'renewals_count', 'meta', 'canceled_at',
    ];

    protected $casts = [
        'amount'             => 'decimal:2',
        'renewals_count'     => 'integer',
        'current_period_end' => 'datetime',
        'canceled_at'        => 'datetime',
        'meta'               => 'array',
    ];

    public const STATUS_PENDING  = 'pending';
    public const STATUS_ACTIVE   = 'active';
    public const STATUS_PAST_DUE = 'past_due';
    public const STATUS_CANCELED = 'canceled';
    public const STATUS_EXPIRED  = 'expired';

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function package(): BelongsTo
    {
        return $this->belongsTo(Package::class);
    }

    public function isActive(): bool
    {
        return in_array($this->status, [self::STATUS_ACTIVE, self::STATUS_PAST_DUE], true);
    }

    public function scopeActive($q)
    {
        return $q->whereIn('status', [self::STATUS_ACTIVE, self::STATUS_PAST_DUE]);
    }

    /** Find the row a gateway renewal webhook refers to. */
    public static function findForGateway(string $gateway, ?string $subscriptionId)
    {
        if (! $subscriptionId) {
            return null;
        }
        return static::query()
            ->where('gateway', $gateway)
            ->where('gateway_subscription_id', $subscriptionId)
            ->latest('id')
            ->first();
    }
}
