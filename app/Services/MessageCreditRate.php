<?php

namespace App\Services;

use App\Models\MessageRate;
use App\Models\SystemSetting;
use App\Support\PhoneCountry;
use Illuminate\Support\Facades\Cache;

/**
 * Resolves how many wallet credits a single outbound message costs, based on
 * the recipient's COUNTRY and the message CATEGORY (marketing / utility /
 * authentication / service) — the fair model that mirrors Meta's own per-
 * country pricing. Falls back to the flat `credits_per_message` setting when
 * the `per_country_credits_enabled` flag is OFF or no rate row matches, so
 * existing installs are unaffected until the admin opts in.
 */
class MessageCreditRate
{
    /** Master switch. OFF → everyone pays the single flat rate (legacy behaviour). */
    public static function enabled(): bool
    {
        return (string) SystemSetting::get('per_country_credits_enabled', '0') === '1';
    }

    /** The flat per-message credit price (admin: /admin/settings/wallet-rules). Floored at 0. */
    public static function flat(): int
    {
        return max(0, (int) SystemSetting::get('credits_per_message', 1));
    }

    /**
     * Credits to charge for one message to $phone in $category.
     *
     * @param  string|null  $phone     Recipient number (any format; digits extracted).
     * @param  string|null  $category  marketing|utility|authentication|service (others → flat).
     */
    public static function creditsFor(?string $phone, ?string $category = null): int
    {
        if (!self::enabled()) {
            return self::flat();
        }

        $iso = PhoneCountry::iso($phone) ?? '';
        $cat = self::normalizeCategory($category);
        $rates = self::table();   // [ "IN|marketing" => 3, "|service" => 0, ... ]

        // Most-specific match wins: country+category → country-any → any+category → any-any.
        foreach (["{$iso}|{$cat}", "{$iso}|", "|{$cat}", "|"] as $key) {
            if (array_key_exists($key, $rates)) {
                return max(0, (int) $rates[$key]);
            }
        }

        // No row at all → the flat setting is the final safety net.
        return self::flat();
    }

    /** marketing|utility|authentication|service; anything else → '' (so it hits the any-category default). */
    private static function normalizeCategory(?string $category): string
    {
        $c = strtolower(trim((string) $category));
        return in_array($c, MessageRate::CATEGORIES, true) ? $c : '';
    }

    /** Active rate rows keyed "COUNTRY|category", cached 5 min (tiny table, rarely changes). */
    private static function table(): array
    {
        return Cache::remember('message_rates_map', 300, function () {
            return MessageRate::query()
                ->where('is_active', true)
                ->get(['country_code', 'category', 'credits'])
                ->mapWithKeys(fn ($r) => [strtoupper($r->country_code) . '|' . strtolower($r->category) => (int) $r->credits])
                ->all();
        });
    }

    /** Drop the cache after an admin edit. */
    public static function forget(): void
    {
        Cache::forget('message_rates_map');
    }
}
