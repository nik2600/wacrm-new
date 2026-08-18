<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A merchant's WhatsApp Pay "Direct Pay Method" (created by them in WhatsApp
 * Manager — Meta-side, we can't make it via API). We store the config NAME and
 * reference it in every `order_details` send. Region-gated: native in-chat pay
 * is only usable from a WABA in a supported country (India today).
 */
class WorkspacePaymentConfig extends Model
{
    protected $fillable = [
        'workspace_id', 'provider_config_id', 'config_name', 'payment_type',
        'country', 'currency', 'merchant_category', 'is_active', 'meta_json',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'meta_json' => 'encrypted:array',
    ];

    /** ISO-2 countries where native WhatsApp Pay order_details is usable today. */
    public const SUPPORTED_COUNTRIES = ['IN']; // India (full). Brazil=Pix-only, ID/MX=testing → excluded.

    // Bare payment_gateway.type values Meta accepts in order_details.payment_settings.
    public const PAYMENT_TYPES = ['razorpay', 'payu', 'billdesk', 'zaakpay'];

    public static function isCountrySupported(?string $iso2): bool
    {
        return $iso2 !== null && in_array(strtoupper($iso2), self::SUPPORTED_COUNTRIES, true);
    }

    public function providerConfig(): BelongsTo
    {
        return $this->belongsTo(WaProviderConfig::class, 'provider_config_id');
    }

    public function scopeForWorkspace($q, ?int $workspaceId)
    {
        return $workspaceId ? $q->where('workspace_id', $workspaceId) : $q;
    }

    public function scopeActive($q)
    {
        return $q->where('is_active', true);
    }
}
