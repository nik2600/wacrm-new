<?php

namespace App\Support;

use App\Models\Currency;
use App\Models\SystemSetting;
use App\Models\Workspace;
use Illuminate\Support\Facades\Cache;

/**
 * Currency display + conversion helpers. Ported from SnapNest's
 * App\Support\FormatSettings — adapted for WaDesk's multi-tenant
 * model (per-workspace currency falls back to global default).
 *
 * Usage:
 *   FormatSettings::currency(199.99)
 *     → "$199.99"  (uses global default)
 *
 *   FormatSettings::currency(199.99, $workspace)
 *     → "₹16,732.50"  (uses workspace's currency, converted from USD)
 *
 *   FormatSettings::convert(100, 'USD', 'INR')
 *     → 8366.25
 */
class FormatSettings
{
    /**
     * Resolve which currency applies to a given workspace (or the
     * global default if no workspace provided / workspace has no
     * preference). Returns a Currency model or null if no currencies
     * configured.
     */
    public static function currencyFor(?Workspace $workspace = null): ?Currency
    {
        $code = null;
        if ($workspace && $workspace->currency) {
            $code = strtoupper($workspace->currency);
        }
        if (!$code) {
            $code = strtoupper((string) SystemSetting::get('default_currency', 'USD'));
        }
        return Cache::remember('currency_by_code_' . $code, 60, function () use ($code) {
            return Currency::where('code', $code)->first();
        });
    }

    /**
     * Format an amount for display. Amount is assumed to be in the
     * target currency already — for cross-currency conversion call
     * `convert()` first or use `display()` below which combines both.
     */
    public static function currency(float|int $amount, ?Workspace $workspace = null): string
    {
        $c = self::currencyFor($workspace);
        if (!$c) return number_format((float) $amount, 2);
        $formatted = number_format((float) $amount, (int) $c->precision, '.', ',');
        return ($c->symbol ?: ($c->code . ' ')) . $formatted;
    }

    /**
     * Convert + format in one call. The canonical pattern for displaying
     * money stored in one currency in another currency:
     *
     *   FormatSettings::display($order->amount, $order->currency)
     *
     * If $fromCode is null we assume the amount is already in the target
     * currency (admin default or workspace override) and skip conversion.
     * If $fromCode equals the target we also skip — saves a DB hit.
     *
     * Use this everywhere on user dashboards so amounts auto-reconvert
     * the moment admin changes the default currency or fetches new rates.
     */
    public static function display(float|int $amount, ?string $fromCode = null, ?Workspace $workspace = null): string
    {
        $target = self::currencyFor($workspace);
        if (!$target) return number_format((float) $amount, 2);

        $fromCode = $fromCode ? strtoupper($fromCode) : null;
        $converted = ($fromCode && $fromCode !== $target->code)
            ? self::convert($amount, $fromCode, $target->code)
            : (float) $amount;

        return self::currency($converted, $workspace);
    }

    /**
     * Format an amount STRICTLY in the given currency code, no conversion.
     * For invoices, receipts, and any other "historical document" where
     * the displayed currency must match what was actually paid — even if
     * the user has since changed their preferred display currency.
     */
    public static function formatIn(float|int $amount, ?string $code): string
    {
        $code = $code ? strtoupper($code) : null;
        if (!$code) return number_format((float) $amount, 2);

        $c = Cache::remember('currency_by_code_' . $code, 60, function () use ($code) {
            return Currency::where('code', $code)->first();
        });
        if (!$c) return number_format((float) $amount, 2);
        $formatted = number_format((float) $amount, (int) $c->precision, '.', ',');
        return ($c->symbol ?: ($c->code . ' ')) . $formatted;
    }

    /**
     * Convert between currencies using stored exchange rates (vs USD).
     *   convert(100, 'USD', 'INR') → INR value of 100 USD
     */
    public static function convert(float|int $amount, string $fromCode, string $toCode): float
    {
        $fromCode = strtoupper($fromCode);
        $toCode   = strtoupper($toCode);
        if ($fromCode === $toCode) return (float) $amount;

        $rates = Cache::remember('currency_rates_map', 60, function () {
            return Currency::query()->active()->pluck('exchange_rate', 'code')->toArray();
        });
        $fromRate = (float) ($rates[$fromCode] ?? 1.0);
        $toRate   = (float) ($rates[$toCode]   ?? 1.0);
        if ($fromRate <= 0) return (float) $amount; // bad config, refuse to divide by zero
        $usd = (float) $amount / $fromRate;
        return round($usd * $toRate, 2);
    }

    /**
     * The active default currency's symbol (or code + space if no symbol),
     * for the global/platform default. Used by admin dashboards + chart
     * axes that show platform-level money already stored in the operating
     * currency (no conversion). Falls back to '$' when nothing configured.
     */
    public static function symbol(?Workspace $workspace = null): string
    {
        $c = self::currencyFor($workspace);
        return $c ? ($c->symbol ?: ($c->code . ' ')) : '$';
    }

    /** Clear the cache after admin saves a currency or fetches rates. */
    public static function flushCache(): void
    {
        Cache::forget('currency_rates_map');
        foreach (Currency::query()->pluck('code') as $code) {
            Cache::forget('currency_by_code_' . strtoupper($code));
        }
    }
}
