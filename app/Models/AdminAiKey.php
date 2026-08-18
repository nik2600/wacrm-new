<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Admin-owned global AI provider keys. Used by AiKeyResolver as the
 * fallback when a workspace's plan doesn't grant BYOK, or when the
 * workspace has BYOK but hasn't set a key for the requested provider.
 *
 * One row per provider — `provider` is unique. Encrypted at rest via
 * Laravel's `encrypted` cast on `api_key`.
 */
class AdminAiKey extends Model
{
    protected $table = 'admin_ai_keys';

    protected $fillable = [
        'provider', 'name', 'api_key', 'default_model',
        'extra_config', 'is_active', 'sort_order',
    ];

    protected $casts = [
        // NOTE: we intentionally don't use the 'encrypted' cast here so the
        // index page can render even when ONE row has a stale-encrypted blob
        // (different APP_KEY). Decryption happens via getApiKeyAttribute()
        // below with a safe fallback to null.
        'is_active'    => 'boolean',
        'sort_order'   => 'integer',
    ];

    /**
     * Decrypt on read. Returns null if the stored blob can't be decrypted
     * (usually because APP_KEY was rotated since the row was saved).
     * Returning null here lets the admin page render so the operator can
     * re-paste a fresh key, instead of throwing a 500 for the whole page.
     */
    public function getApiKeyAttribute($value): ?string
    {
        if ($value === null || $value === '') return null;
        try {
            return \Crypt::decryptString($value);
        } catch (\Throwable) {
            return null;
        }
    }

    /** Encrypt on write. Empty string clears the key. */
    public function setApiKeyAttribute($value): void
    {
        $this->attributes['api_key'] = ($value === null || $value === '')
            ? null
            : \Crypt::encryptString((string) $value);
    }

    /** Decode extra_config JSON to an array. Never throws. */
    public function getExtraConfigArrayAttribute(): array
    {
        if (empty($this->extra_config)) return [];
        $arr = json_decode($this->extra_config, true);
        return is_array($arr) ? $arr : [];
    }

    public function setExtraConfigArrayAttribute(array $value): void
    {
        $this->extra_config = json_encode($value, JSON_UNESCAPED_UNICODE);
    }

    /** Read one extra_config key. */
    public function getExtra(string $key, $default = null)
    {
        return $this->extra_config_array[$key] ?? $default;
    }

    /** True if this provider is fully configured (active + has key). */
    public function isReady(): bool
    {
        return $this->is_active && !empty($this->api_key);
    }

    public static function activeFor(string $provider): ?self
    {
        return static::query()
            ->where('provider', $provider)
            ->where('is_active', true)
            ->first();
    }
}
