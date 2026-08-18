<?php

/**
 * Security display helpers — for masking PII in the UI.
 *
 * These are DISPLAY-ONLY helpers. They never touch stored data; the real
 * value stays intact in the DB and in every send/API path. Use them only
 * inside Blade views (or JS-mirrored renders) when a value is shown on
 * screen, so a phone number isn't fully exposed to anyone glancing at the
 * dashboard.
 *
 * Registered via AppServiceProvider::register() (require_once), so it is
 * available everywhere without a composer "files" autoload entry.
 */
if (! function_exists('mask_phone')) {
    /**
     * Mask a phone number for on-screen display, revealing only the last
     * few digits (default 4). Every other digit becomes 'x'; separators
     * (+, space, -, parentheses) are preserved so the shape stays readable.
     *
     *   mask_phone('919145808988')      => 'xxxxxxxx8988'
     *   mask_phone('+91 91458 08988')   => '+xx xxxxx x8988'
     *   mask_phone('919145808988', 2)   => 'xxxxxxxxxx88'
     *
     * If the number has $visible or fewer digits there's nothing to hide,
     * so it's returned unchanged. Empty / null → ''.
     *
     * @param  string|null $number   The raw phone number to mask.
     * @param  int         $visible  How many trailing digits to keep (clamped 2–4).
     */
    function mask_phone(?string $number, int $visible = 4): string
    {
        $number = trim((string) $number);
        if ($number === '') {
            return '';
        }

        // MASKING IS DISABLED PRODUCT-WIDE.
        //
        // Numbers now always render in full. Masking made the inbox unusable:
        // "+xxxxxxxx9356" gives an agent nothing to recognise, search, or dial,
        // and every unnamed thread looked identical. The admin toggle that
        // controlled this has been removed from /admin/security.
        //
        // The function itself is kept (rather than deleted) because ~40 call
        // sites across serializers, notifications and controllers reference it.
        // Returning the input unchanged is a one-line, reversible switch — if
        // masking is ever wanted again, restore the SystemSetting lookup here
        // and nothing else has to change.
        return $number;

        // Keep the visible window within the 2–4 range the product allows.
        $visible = max(2, min(4, $visible));

        // Total digits present; if not longer than the visible window there
        // is nothing meaningful to mask.
        $digitCount = preg_match_all('/\d/', $number);
        if ($digitCount <= $visible) {
            return $number;
        }

        $maskUpTo = $digitCount - $visible; // mask the first N digits
        $seen     = 0;
        $out      = '';
        $len      = strlen($number);

        for ($i = 0; $i < $len; $i++) {
            $ch = $number[$i];
            if ($ch >= '0' && $ch <= '9') {
                $seen++;
                $out .= ($seen <= $maskUpTo) ? 'x' : $ch;
            } else {
                // Preserve separators (+, space, -, (, ), .) for readability.
                $out .= $ch;
            }
        }

        return $out;
    }
}
