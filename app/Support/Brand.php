<?php

namespace App\Support;

use App\Models\SystemSetting;
use Illuminate\Support\Facades\Cache;

/**
 * Brand assets resolver. Reads the favicon + per-theme logo paths the
 * admin uploaded on /admin/settings/general and returns absolute URLs.
 *
 * Theme resolution:
 *   - Pass `$theme` explicitly if you know it (e.g. on a server-rendered
 *     page that knows the user's preference)
 *   - Otherwise the caller can leave it null and we fall back to 'paper'
 *
 * Per-theme logo missing → falls back to paper logo (the default look)
 * Paper logo missing too  → returns null so the caller can render its
 * own fallback (text wordmark, original SVG icon, etc.).
 */
class Brand
{
    public const DEFAULT_THEME = 'paper';

    /** Every theme the UI ships (incl. doodle, which the DB column can't store). */
    public const THEMES = ['paper', 'bright', 'dark', 'doodle'];

    /**
     * The theme the browser is ACTUALLY showing right now.
     *
     * The live theme switcher (wadesk.js) keeps the choice in the
     * `wa-theme` cookie (and localStorage). The DB `theme_preference`
     * column is a stale, lossy fallback — it can't even hold 'doodle'.
     * Server-rendering the logo from the cookie means the initial <img>
     * src already matches the active theme, so the brand logo no longer
     * flashes/swaps on reload (e.g. paper → doodle).
     */
    public static function activeTheme(): string
    {
        $cookie = $_COOKIE['wa-theme'] ?? null;
        if (is_string($cookie) && in_array($cookie, self::THEMES, true)) {
            return $cookie;
        }
        $pref = auth()->user()->theme_preference ?? null;
        return in_array($pref, self::THEMES, true) ? $pref : self::DEFAULT_THEME;
    }

    /**
     * The platform's display name — admin-configured at
     * /admin/settings/general. Single source of truth for the product
     * name shown anywhere a user can see (auth, nav, footer, legal,
     * marketing, app chrome). When the admin hasn't set a name it falls
     * back to the shipped default "WaDesk" — NEVER to config('app.name') /
     * the APP_NAME .env value (white-label clients rename the product only
     * in General Settings, not the environment file).
     */
    public static function name(): string
    {
        try {
            $v = (string) SystemSetting::get('app_name', '');
        } catch (\Throwable $e) {
            $v = '';
        }
        return $v !== '' ? $v : 'WaDesk';
    }

    public static function faviconUrl(): ?string
    {
        return self::resolveUrl((string) SystemSetting::get('brand.favicon', ''));
    }

    /**
     * Company / billing identity printed on invoices. Configured by the
     * admin at /admin/checkout-settings. The company name falls back to
     * the platform app name so an invoice is never blank-headed.
     */
    public static function billing(): array
    {
        $appName = self::name();
        return [
            'company'     => (string) (SystemSetting::get('billing.company', '') ?: $appName),
            'address'     => (string) SystemSetting::get('billing.address', ''),
            'tax_id'      => (string) SystemSetting::get('billing.tax_id', ''),
            'reg_no'      => (string) SystemSetting::get('billing.reg_no', ''),
            'email'       => (string) SystemSetting::get('billing.email', ''),
            'phone'       => (string) SystemSetting::get('billing.phone', ''),
            'tax_enabled' => (bool)   SystemSetting::get('checkout.tax_enabled', true),
            'tax_label'   => (string) SystemSetting::get('checkout.tax_label', 'GST'),
        ];
    }

    /**
     * Resolve a logo URL for a given theme.
     *
     *   Brand::logoUrl('dark')  → uploaded dark-theme logo, OR paper, OR null
     *   Brand::logoUrl()        → defaults to 'paper'
     */
    public static function logoUrl(?string $theme = null): ?string
    {
        $theme = $theme ?: self::DEFAULT_THEME;
        $path = (string) SystemSetting::get('brand.logo.' . $theme, '');
        if ($path === '' && $theme !== self::DEFAULT_THEME) {
            // Fall back to the default theme's logo.
            $path = (string) SystemSetting::get('brand.logo.' . self::DEFAULT_THEME, '');
        }
        return self::resolveUrl($path);
    }

    /** True if at least one logo has been uploaded. Used to decide
     * whether to render the wordmark fallback or the image. */
    public static function hasAnyLogo(): bool
    {
        foreach (['paper', 'bright', 'dark', 'doodle'] as $theme) {
            if (SystemSetting::get('brand.logo.' . $theme, '')) return true;
        }
        return false;
    }

    /**
     * Token-safe brand for outbound-webhook HTTP header NAMES. Every webhook
     * delivery is signed/labelled with brand-prefixed headers so a white-label
     * deployment shows the operator's name, not "WaDesk". Header names must be
     * a single token, so spaces/punctuation are stripped; falls back to
     * "WaDesk". Used by WebhookService + OutboundWebhookDispatcher AND the API
     * docs, so the documented header always matches what is actually sent.
     *   "Acme Corp" → "X-AcmeCorp"
     */
    public static function webhookHeaderPrefix(): string
    {
        $token = preg_replace('/[^A-Za-z0-9]/', '', self::name());
        return 'X-' . (($token ?? '') !== '' ? $token : 'WaDesk');
    }

    public static function webhookSignatureHeader(): string { return self::webhookHeaderPrefix() . '-Signature'; }
    public static function webhookEventHeader(): string     { return self::webhookHeaderPrefix() . '-Event'; }
    public static function webhookHookIdHeader(): string    { return self::webhookHeaderPrefix() . '-Hook-Id'; }

    private static function resolveUrl(string $path): ?string
    {
        if ($path === '') return null;
        // Already absolute? (admin pasted an external URL) — pass through.
        if (preg_match('#^https?://#i', $path)) return $path;
        return asset('storage/' . ltrim($path, '/'));
    }
}
