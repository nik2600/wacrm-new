<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

/**
 * Catalog of installable payment gateways. One row per gateway slug
 * (stripe, razorpay, paypal, bank_transfer, offline, etc.). The
 * admin form at /admin/payment-gateways edits the credentials JSON;
 * the credentials column itself stores a base64 ciphertext.
 *
 * `supported_currencies` is a whitelist — if non-empty, the gateway
 * only appears at /checkout when the order's currency is in the
 * list. Empty / null = accept any currency.
 */
class PaymentGateway extends Model
{
    protected $fillable = [
        'slug', 'name', 'description', 'is_active',
        'credentials', 'mode', 'extra_config',
        'supported_currencies', 'sort_order',
    ];

    protected $casts = [
        'is_active'            => 'boolean',
        'extra_config'         => 'array',
        'supported_currencies' => 'array',
        'sort_order'           => 'integer',
    ];

    /** Hide encrypted credentials from any serialization. */
    protected $hidden = ['credentials'];

    public function scopeActive(Builder $q): Builder { return $q->where('is_active', true); }

    /** Decrypt + decode the credentials JSON. Returns an empty array on miss. */
    public function getDecryptedCredentials(): array
    {
        if (empty($this->credentials)) return [];
        try {
            $json = Crypt::decryptString($this->credentials);
            $arr  = json_decode($json, true);
            return is_array($arr) ? $arr : [];
        } catch (\Throwable $e) {
            return [];
        }
    }

    /** Encrypt + persist the credentials JSON. */
    public function setEncryptedCredentials(array $values): void
    {
        $this->credentials = Crypt::encryptString(json_encode($values, JSON_UNESCAPED_UNICODE));
    }

    /** Read one credential field by key. */
    public function getCredential(string $key, $default = null)
    {
        return $this->getDecryptedCredentials()[$key] ?? $default;
    }

    /** True if this gateway accepts the given currency code. */
    public function acceptsCurrency(string $code): bool
    {
        $list = $this->supported_currencies ?? [];
        if (empty($list)) return true;
        return in_array(strtoupper($code), array_map('strtoupper', $list), true);
    }
}
