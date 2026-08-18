<?php

namespace App\Support\Woo;

/**
 * Normalise a WooCommerce phone to E.164 digits (no leading '+').
 *
 * WooCommerce stores whatever the customer typed at checkout — very often a
 * LOCAL number with no country code ("08012345678", "9876543210"). Sending
 * that to WhatsApp targets a non-existent international address, so the
 * notification silently never arrives (it can even log as "sent" on the
 * Unofficial API while the customer receives nothing). We derive the country
 * from the order's billing/shipping country — an ISO-3166 alpha-2 code that
 * WooCommerce always includes — and prepend its dialing code.
 *
 * ISO → dialing code comes from config/countries.php (the same list that
 * powers the admin country picker), so no external library is needed.
 */
class WooPhone
{
    /**
     * Extract the recipient phone from a WC order / customer / checkout
     * payload and return it as E.164 digits (or null when there's no phone).
     */
    public static function fromOrder(array $data): ?string
    {
        $billing  = is_array($data['billing'] ?? null)  ? $data['billing']  : [];
        $shipping = is_array($data['shipping'] ?? null) ? $data['shipping'] : [];

        $phone = $billing['phone']
            ?? $shipping['phone']
            ?? ($data['phone'] ?? null)
            ?? ($data['customer']['phone'] ?? null);

        $iso = $billing['country']
            ?? $shipping['country']
            ?? ($data['customer']['billing']['country'] ?? null);

        return self::e164($phone, $iso);
    }

    /**
     * @param string|null $rawPhone   The number exactly as WooCommerce stored it.
     * @param string|null $countryIso ISO-3166 alpha-2 (e.g. "NG", "IN"); optional.
     * @return string|null            E.164 digits with no '+', or null if empty.
     */
    public static function e164(?string $rawPhone, ?string $countryIso = null): ?string
    {
        $raw = trim((string) $rawPhone);
        if ($raw === '') return null;

        $hadPlus = str_starts_with($raw, '+');
        // Strip a "00" international prefix so it's handled like a '+'.
        $digits  = preg_replace('/\D+/', '', $raw);
        if ($digits === '') return null;
        if (!$hadPlus && str_starts_with($digits, '00')) {
            $digits  = ltrim(substr($digits, 2), '0') ?: $digits;
            $hadPlus = true;
        }

        // A leading '+' (or 00) means the customer already gave a full
        // international number — trust it verbatim (minus the symbols).
        if ($hadPlus) return $digits;

        $dial = self::dialFor($countryIso);

        if ($dial !== null) {
            // Already carries this country's code (typed without a '+'): keep
            // it, but only when it's long enough to be a real international
            // number — otherwise a local number that merely starts with the
            // same digits would be mistaken for one.
            if (str_starts_with($digits, $dial) && strlen($digits) >= strlen($dial) + 8) {
                return $digits;
            }
            // Local format — drop the national trunk '0', then prepend the code.
            return $dial . ltrim($digits, '0');
        }

        // No country context and no '+': best effort — return the digits as-is
        // (a genuinely local number can't be repaired without knowing the
        // country, but an already-international one passes straight through).
        return $digits;
    }

    /** ISO-3166 alpha-2 → dialing-code digits (no '+'), from config/countries.php. */
    private static function dialFor(?string $iso): ?string
    {
        $iso = strtolower(trim((string) $iso));
        if (strlen($iso) !== 2) return null;

        static $map = null;
        if ($map === null) {
            $map = [];
            foreach ((array) config('countries', []) as $c) {
                $ci = strtolower((string) ($c['iso']  ?? ''));
                $cd = preg_replace('/\D+/', '', (string) ($c['code'] ?? ''));
                if ($ci !== '' && $cd !== '' && !isset($map[$ci])) {
                    $map[$ci] = $cd;
                }
            }
        }
        return $map[$iso] ?? null;
    }
}
