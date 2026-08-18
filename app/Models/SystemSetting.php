<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;

/**
 * Tiny key/value store for admin-tunable values that don't deserve
 * their own table — affiliate credit reward, credits-per-message,
 * credits-per-rupee, etc.
 *
 * Reads are cache-warm because they're called on every send (the
 * credit-deduction path hits `credits_per_message` for every message
 * created). Cache key is invalidated automatically on save() via
 * the saving event.
 */
class SystemSetting extends Model
{
    protected $fillable = ['key', 'type', 'value', 'description'];

    public const CACHE_PREFIX = 'system_setting:';

    /**
     * Keys whose values are secrets — transparently encrypted at rest
     * via Laravel's Crypt facade. Any key listed here is encrypted on
     * `set()` and decrypted on `get()`. Plain-text legacy rows still
     * read fine; first save flips them to ciphertext.
     */
    public const ENCRYPTED_KEYS = [
        'waba_app_secret',
        'waba_webhook_verify_token',
        'baileys_callback_token',
        'node_webhook_token',
        'meta_app_secret',
        'twilio_auth_token',
        'meta_system_user_token',
        'meta_ads.token',   // admin global Meta Ads (CTWA) fallback access token
        'shopify_client_secret',          // Shopify app secret (OAuth + webhook HMAC)
        'hubspot_client_secret',          // HubSpot OAuth app secret
        'google_calendar_client_secret',  // Google OAuth client secret
        'social_google_client_secret',    // Google social sign-in secret
        'social_facebook_client_secret',  // Facebook social sign-in app secret
        'recaptcha_secret',               // Google reCAPTCHA server secret
        'web_search_key',                 // Call-flow web search API key (Tavily/SerpAPI/Brave)
        'instagram_app_secret',           // Meta app secret for Instagram automation
        'instagram_webhook_verify_token', // Meta webhook subscription verify token (IG)
        'instagram_giphy_key',            // GIPHY API key for the inbox GIF picker
        'instaflow_secret',               // shared secret for the Instaflow deployment handshake
        'ti_vapid_private',               // Team-Inbox PWA Web-Push VAPID private key
    ];

    private static function isEncryptedKey(string $key): bool
    {
        return in_array($key, self::ENCRYPTED_KEYS, true);
    }

    protected static function booted(): void
    {
        static::saved(function (self $row) {
            Cache::forget(self::CACHE_PREFIX . $row->key);
        });
        static::deleted(function (self $row) {
            Cache::forget(self::CACHE_PREFIX . $row->key);
        });
    }

    /**
     * Read a setting value, type-cast. Cached for 30 minutes.
     *
     * Why a default param: a missing row should not crash the send
     * path. If the admin hasn't set `credits_per_message` we default
     * to 1 — that matches the seeded value in the migration but
     * defends against accidental DELETEs in the admin UI.
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        // Resilient against a not-yet-provisioned database. The installer
        // (/install) and any pre-migration request boot the framework
        // before .env has DB credentials, yet boot-time code (locale,
        // branding) reads settings. With CACHE_STORE=database even the
        // cache lookup queries the DB and would hard-crash the request, so
        // we fall back to the caller's default when the store is unreachable.
        try {
            return Cache::remember(self::CACHE_PREFIX . $key, 1800, function () use ($key, $default) {
                $row = self::where('key', $key)->first();
                if (!$row) return $default;
                $raw = $row->value;
                // Decrypt-on-read for secret keys. Legacy plain rows still
                // pass through — Crypt::decrypt throws on non-ciphertext so
                // we silently fall back to the raw value, which is the
                // unencrypted legacy migration state.
                if (self::isEncryptedKey($key) && is_string($raw) && $raw !== '') {
                    try { $raw = Crypt::decrypt($raw); }
                    catch (\Throwable $e) { /* plain legacy value */ }
                }
                return self::cast($raw, $row->type);
            });
        } catch (\Throwable $e) {
            return $default;
        }
    }

    public static function set(string $key, mixed $value, string $type = 'int', ?string $description = null): self
    {
        $row = self::firstOrNew(['key' => $key]);
        $row->type = $type;
        $stored = self::stringify($value, $type);
        // Encrypt-on-write for secret keys so the DB never holds
        // plaintext app_secret / webhook_verify_token / etc. First
        // save after this code ships flips any legacy plain row.
        if (self::isEncryptedKey($key) && is_string($stored) && $stored !== '') {
            $stored = Crypt::encrypt($stored);
        }
        $row->value = $stored;
        if ($description !== null) $row->description = $description;
        $row->save();
        return $row;
    }

    private static function cast(?string $raw, string $type): mixed
    {
        if ($raw === null) return null;
        return match ($type) {
            'int'    => (int) $raw,
            'float'  => (float) $raw,
            'bool'   => filter_var($raw, FILTER_VALIDATE_BOOLEAN),
            'json'   => json_decode($raw, true),
            default  => $raw,
        };
    }

    private static function stringify(mixed $value, string $type): string
    {
        return match ($type) {
            'json' => json_encode($value),
            'bool' => $value ? '1' : '0',
            default => (string) $value,
        };
    }
}
