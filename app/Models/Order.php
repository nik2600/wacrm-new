<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Plan-purchase order. Created at /checkout/{package_id}, mutated by
 * the gateway driver during initiate(), and marked paid/failed by
 * the callback or webhook.
 *
 * Statuses: pending | paid | failed | refunded
 */
class Order extends Model
{
    protected $fillable = [
        'order_number', 'workspace_id', 'user_id', 'package_id', 'credit_package_id',
        'billing_period', 'auto_renew',
        'gateway_id', 'gateway_slug',
        'currency', 'amount', 'base_amount_usd', 'exchange_rate',
        'status', 'gateway_order_id', 'gateway_payment_id', 'gateway_payload',
        'failure_reason', 'paid_at',
        // End-to-end checkout fields (sprint 9 — admin-controlled
        // pricing / tax / coupons make their way into the order row).
        'coupon_id', 'coupon_code',
        'discount_amount', 'tax_rate', 'tax_amount', 'total_amount',
        'customer_name', 'customer_email',
        'billing_company', 'billing_address', 'billing_city',
        'billing_postal', 'billing_country', 'billing_tax_id',
        // Offline / bank-transfer payment-proof + admin review.
        'payment_proof_path', 'payment_reference', 'proof_note', 'proof_submitted_at',
        'reviewed_by', 'reviewed_at', 'review_note',
    ];

    protected $casts = [
        'amount'          => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'tax_rate'        => 'decimal:2',
        'tax_amount'      => 'decimal:2',
        'total_amount'    => 'decimal:2',
        'base_amount_usd' => 'decimal:2',
        'exchange_rate'   => 'decimal:6',
        'gateway_payload' => 'array',
        'auto_renew'      => 'boolean',
        'paid_at'         => 'datetime',
        'proof_submitted_at' => 'datetime',
        'reviewed_at'        => 'datetime',
    ];

    public const STATUSES = ['pending', 'paid', 'failed', 'refunded'];

    public function workspace(): BelongsTo { return $this->belongsTo(Workspace::class); }
    public function user(): BelongsTo      { return $this->belongsTo(User::class); }
    public function package(): BelongsTo   { return $this->belongsTo(Package::class); }
    public function gateway(): BelongsTo   { return $this->belongsTo(PaymentGateway::class, 'gateway_id'); }
    public function coupon(): BelongsTo    { return $this->belongsTo(Coupon::class); }

    public function scopePaid(Builder $q): Builder    { return $q->where('status', 'paid'); }
    public function scopePending(Builder $q): Builder { return $q->where('status', 'pending'); }

    /** True when the buyer has uploaded payment proof and we're still pending review. */
    public function awaitingApproval(): bool
    {
        return $this->status === 'pending' && $this->proof_submitted_at !== null;
    }

    /**
     * Generate a human-readable order number. Format: WSN-{ymd}-{6char}.
     */
    public static function generateOrderNumber(): string
    {
        do {
            $candidate = 'WSN-' . now()->format('ymd') . '-' . strtoupper(\Illuminate\Support\Str::random(6));
        } while (self::query()->where('order_number', $candidate)->exists());
        return $candidate;
    }
}
