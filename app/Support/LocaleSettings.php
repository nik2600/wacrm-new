<?php

namespace App\Support;

use App\Models\Language;
use App\Models\SystemSetting;
use Illuminate\Support\Facades\Cache;

/**
 * Locale resolution + available-language registry.
 *
 * Single source of truth for the language switcher in user + admin
 * headers and the SetLocale middleware.
 */
class LocaleSettings
{
    /** Cached for 10 min — admin/languages toggle busts via Cache::forget */
    private const CACHE_KEY = 'locale:active_languages';
    private const CACHE_TTL = 600;

    /** Full list of active languages, ordered for the dropdown. */
    public static function active(): array
    {
        // The outer try guards the cache store itself: with CACHE_STORE=database
        // the Cache::remember lookup queries the DB, which doesn't exist yet
        // during /install — so a fresh install must not hard-crash here.
        try {
            return Cache::remember(self::CACHE_KEY, self::CACHE_TTL, function () {
                try {
                    return Language::query()
                        ->where('is_active', true)
                        ->orderBy('sort_order')
                        ->orderBy('name')
                        ->get(['code', 'name', 'native_name', 'direction'])
                        ->toArray();
                } catch (\Throwable $e) {
                    return [];
                }
            });
        } catch (\Throwable $e) {
            return [];
        }
    }

    /** Bust the active-languages cache. Call from the admin controller. */
    public static function flush(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /** Platform default locale (admin-set via /admin/languages → "set default"). */
    public static function defaultLocale(): string
    {
        $code = (string) SystemSetting::get('default_language', config('app.locale', 'en'));
        return $code !== '' ? $code : 'en';
    }

    /** True if a language with the given code is active. */
    public static function isAvailable(string $code): bool
    {
        foreach (self::active() as $row) {
            if (($row['code'] ?? '') === $code) return true;
        }
        return false;
    }

    /** Direction (ltr|rtl) for a code, defaulting to ltr. */
    public static function directionFor(string $code): string
    {
        foreach (self::active() as $row) {
            if (($row['code'] ?? '') === $code) {
                return (string) ($row['direction'] ?? 'ltr');
            }
        }
        return 'ltr';
    }
}
