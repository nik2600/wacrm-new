<?php

namespace App\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;

/**
 * Drop-in replacement for Laravel's `'encrypted'` cast that does NOT crash the
 * whole request when a column holds a value it can't decrypt.
 *
 * Why: rows written under a DIFFERENT APP_KEY (a copied DB, a key rotation, a
 * staging clone) or legacy plaintext leftovers make the stock `encrypted` cast
 * throw DecryptException("The payload is invalid.") on READ — which 500s every
 * listing that touches the column (e.g. /team-inbox/api/queue reading a
 * message body / from_number). Encryption format is identical to the built-in
 * cast (Crypt::encryptString/decryptString), so existing valid ciphertext reads
 * and new writes are byte-for-byte the same; only the failure path differs:
 * return the raw stored value instead of throwing.
 */
class SafeEncrypted implements CastsAttributes
{
    public function get($model, string $key, $value, array $attributes)
    {
        if ($value === null || $value === '') {
            return $value;
        }
        try {
            return Crypt::decryptString($value);
        } catch (DecryptException $e) {
            // Plaintext leftover or data from another APP_KEY — surface it raw
            // rather than crash. Better a possibly-stale value than a 500.
            return $value;
        }
    }

    public function set($model, string $key, $value, array $attributes)
    {
        if ($value === null) {
            return [$key => null];
        }
        return [$key => Crypt::encryptString((string) $value)];
    }
}
