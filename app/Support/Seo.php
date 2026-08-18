<?php

namespace App\Support;

use App\Models\SystemSetting;

/**
 * Resolves the SEO meta block emitted by every layout.
 *
 * Pulls each field from the system_settings table that
 * /admin/settings/seo writes — never hardcodes a value here so the
 * admin form is the single source of truth.
 *
 * Per-page overrides are supported via the second arg: any layout can
 * pass `['title' => ..., 'description' => ...]` and those win over
 * the saved defaults. Helpful for guidebook articles and storefront
 * product pages where each item wants its own preview card.
 */
class Seo
{
    /** Single getter — reads the SystemSetting cache. */
    public static function get(string $key, $default = null)
    {
        return SystemSetting::get('seo_' . $key, $default);
    }

    /**
     * Full meta block for a given page. The optional `$overrides`
     * argument lets a view emit a page-specific title/description
     * without touching the saved defaults.
     */
    public static function meta(array $overrides = []): array
    {
        $brand = (string) SystemSetting::get('app_name', config('app.name', 'WaDesk'));

        $title       = $overrides['title']       ?? self::get('meta_title')       ?? $brand;
        $description = $overrides['description'] ?? self::get('meta_description') ?? '';
        $keywords    = $overrides['keywords']    ?? self::get('meta_keywords')    ?? '';
        $robots      = $overrides['robots']      ?? self::get('robots', 'index, follow');

        $ogTitle       = $overrides['og_title']       ?? self::get('og_title')       ?? $title;
        $ogDescription = $overrides['og_description'] ?? self::get('og_description') ?? $description;
        $ogImage       = $overrides['og_image']       ?? self::get('og_image')       ?? '';
        $ogType        = $overrides['og_type']        ?? self::get('og_type', 'website');
        $ogUrl         = $overrides['og_url']         ?? self::get('og_url')         ?? request()->url();
        $canonical     = $overrides['canonical']      ?? self::get('canonical')      ?? request()->url();

        $twitterCard        = self::get('twitter_card', 'summary_large_image');
        $twitterSite        = self::get('twitter_site', '');
        $twitterCreator     = self::get('twitter_creator', '');
        $googleVerification = self::get('google_verification', '');
        $bingVerification   = self::get('bing_verification', '');
        $author             = self::get('author', $brand);

        return [
            'brand'               => $brand,
            'title'               => $title,
            'description'         => $description,
            'keywords'            => $keywords,
            'robots'              => $robots,
            'canonical'           => $canonical,
            'author'              => $author,
            'og_title'            => $ogTitle,
            'og_description'      => $ogDescription,
            'og_image'            => $ogImage,
            'og_type'             => $ogType,
            'og_url'              => $ogUrl,
            'twitter_card'        => $twitterCard,
            'twitter_site'        => $twitterSite,
            'twitter_creator'     => $twitterCreator,
            'google_verification' => $googleVerification,
            'bing_verification'   => $bingVerification,
        ];
    }
}
