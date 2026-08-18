<?php

namespace App\Services\Waba;

use App\Models\SystemSetting;
use App\Models\WaLinkClick;
use Illuminate\Support\Str;

/**
 * URL click tracking. Wraps customer-facing URLs in a short
 * /r/{token} redirect so we can record who clicked what.
 *
 * Meta does NOT send a webhook for URL button clicks (those happen
 * in the user's browser, outside WhatsApp). The only way to know
 * "did the recipient tap the Buy button" is to put yourself on the
 * redirect path. This service does exactly that.
 *
 * Usage:
 *   $short = LinkTracker::wrap('https://shop.example.com/p/123', [
 *       'broadcast_id' => 42,
 *       'contact_id'   => 9001,
 *       'message_id'   => 5550,
 *       'workspace_id' => 7,
 *   ]);
 *   // → 'https://wadesk.io/r/ab12CD34efGH'
 *
 * Guarantees:
 *   - Same (URL, broadcast_id, contact_id) pair gets the SAME token
 *     so retries don't bloat the table.
 *   - Returns the original URL untouched if tracking is disabled,
 *     the URL is already a wadesk.io shortlink (no double-wrapping),
 *     or the URL isn't http/https.
 *   - Token is 12 chars base62 — collision-resistant up to ~10^21.
 */
class LinkTracker
{
    /**
     * Wrap a URL. Returns the original URL if tracking is OFF or the
     * URL isn't trackable (e.g. tel:, mailto:, javascript:, or our
     * own shortlink domain). Otherwise persists a `wa_link_clicks`
     * row and returns the public short URL.
     *
     * @param  array{workspace_id?:int,broadcast_id?:int,campaign_id?:int,message_id?:int,contact_id?:int,template_id?:int,phone?:string}  $context
     */
    public static function wrap(string $url, array $context = []): string
    {
        if (!self::enabled())                  return $url;
        if (!self::isHttpUrl($url))            return $url;
        if (self::isOwnShortlink($url))        return $url;

        // Reuse a token only for the EXACT same send. Every scoping key has
        // to match, including the NULLs — the old lookup only constrained
        // the keys that were present, so a contact who had already received
        // this URL in another campaign got that campaign's token handed
        // back and their click landed on the wrong campaign's counters.
        // campaign_id in particular was never matched at all.
        //
        // Expired rows are skipped so a re-send mints a fresh token instead
        // of handing out a link that answers 410.
        $existing = WaLinkClick::query()
            ->where('original_url', $url)
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()));
        foreach (['broadcast_id', 'campaign_id', 'message_id', 'contact_id'] as $key) {
            $val = $context[$key] ?? null;
            $val ? $existing->where($key, $val) : $existing->whereNull($key);
        }
        $existing = $existing->orderByDesc('id')->first();

        if ($existing) return self::publicUrl($existing->token);

        $ttlDays = (int) SystemSetting::get('wa_link_tracking_ttl_days', 90);
        $token   = self::freshToken();

        WaLinkClick::create(array_merge([
            'token'        => $token,
            'original_url' => $url,
            'expires_at'   => now()->addDays(max(7, $ttlDays)),
        ], array_intersect_key($context, array_flip([
            'workspace_id', 'broadcast_id', 'campaign_id',
            'message_id',   'contact_id',   'template_id', 'phone',
        ]))));

        return self::publicUrl($token);
    }

    public static function enabled(): bool
    {
        return (bool) SystemSetting::get('waba_link_tracking_enabled', true);
    }

    private static function isHttpUrl(string $url): bool
    {
        $scheme = parse_url($url, PHP_URL_SCHEME);
        return in_array($scheme, ['http', 'https'], true);
    }

    private static function isOwnShortlink(string $url): bool
    {
        $base = rtrim((string) (config('app.url') ?: url('/')), '/');
        return str_starts_with($url, $base . '/r/');
    }

    private static function publicUrl(string $token): string
    {
        return url('/r/' . $token);
    }

    private static function freshToken(): string
    {
        // 12 chars base62. Re-try on the off chance of collision.
        for ($i = 0; $i < 4; $i++) {
            $candidate = Str::random(12);
            if (!WaLinkClick::where('token', $candidate)->exists()) return $candidate;
        }
        // Vanishingly unlikely; fall back to a longer token.
        return Str::random(20);
    }

    /**
     * Walk a text body and wrap every http(s) URL inline. Used by
     * the dispatcher's text path so plain message bodies also get
     * tracking when the feature flag is on.
     */
    public static function wrapInText(string $body, array $context = []): string
    {
        if (!self::enabled())     return $body;
        if (trim($body) === '')   return $body;

        return preg_replace_callback(
            '~https?://[^\s<>"\']+~i',
            fn ($m) => self::wrap($m[0], $context),
            $body
        );
    }
}
