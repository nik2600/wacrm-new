<?php

namespace App\Support;

/**
 * Resolve an ISO-3166 alpha-2 country code from a phone number's dialing
 * prefix — no external library. Longest-prefix-wins so 3-digit codes (e.g.
 * 971 UAE, 880 Bangladesh) beat their 1–2 digit neighbours.
 *
 * Used by MessageCreditRate to bill the correct per-country credit rate.
 * Not exhaustive — every code maps to ONE representative ISO (e.g. +1 → US
 * for the whole NANP); billing tiers are country-grouped so that's fine, and
 * any unmatched number falls through to the admin's default rate.
 */
class PhoneCountry
{
    /** Dialing code (without +) → ISO-3166 alpha-2. Order doesn't matter; we sort by length at match time. */
    private const CODES = [
        // 3-digit (checked first via longest-prefix)
        '971' => 'AE', '966' => 'SA', '974' => 'QA', '973' => 'BH', '968' => 'OM',
        '965' => 'KW', '962' => 'JO', '961' => 'LB', '972' => 'IL', '970' => 'PS',
        '963' => 'SY', '964' => 'IQ', '967' => 'YE', '212' => 'MA', '213' => 'DZ',
        '216' => 'TN', '218' => 'LY', '220' => 'GM', '221' => 'SN', '233' => 'GH',
        '234' => 'NG', '254' => 'KE', '255' => 'TZ', '256' => 'UG', '251' => 'ET',
        '260' => 'ZM', '263' => 'ZW', '880' => 'BD', '977' => 'NP', '975' => 'BT',
        '960' => 'MV', '855' => 'KH', '856' => 'LA', '852' => 'HK', '853' => 'MO',
        '886' => 'TW', '351' => 'PT', '353' => 'IE', '352' => 'LU', '358' => 'FI',
        '359' => 'BG', '370' => 'LT', '371' => 'LV', '372' => 'EE', '380' => 'UA',
        '381' => 'RS', '385' => 'HR', '386' => 'SI', '387' => 'BA', '420' => 'CZ',
        '421' => 'SK', '353' => 'IE', '598' => 'UY', '595' => 'PY', '591' => 'BO',
        '593' => 'EC', '592' => 'GY', '507' => 'PA', '506' => 'CR', '502' => 'GT',
        '503' => 'SV', '504' => 'HN', '505' => 'NI', '509' => 'HT',
        // 2-digit
        '20' => 'EG', '27' => 'ZA', '30' => 'GR', '31' => 'NL', '32' => 'BE',
        '33' => 'FR', '34' => 'ES', '36' => 'HU', '39' => 'IT', '40' => 'RO',
        '41' => 'CH', '43' => 'AT', '44' => 'GB', '45' => 'DK', '46' => 'SE',
        '47' => 'NO', '48' => 'PL', '49' => 'DE', '51' => 'PE', '52' => 'MX',
        '53' => 'CU', '54' => 'AR', '55' => 'BR', '56' => 'CL', '57' => 'CO',
        '58' => 'VE', '60' => 'MY', '61' => 'AU', '62' => 'ID', '63' => 'PH',
        '64' => 'NZ', '65' => 'SG', '66' => 'TH', '81' => 'JP', '82' => 'KR',
        '84' => 'VN', '86' => 'CN', '90' => 'TR', '91' => 'IN', '92' => 'PK',
        '93' => 'AF', '94' => 'LK', '95' => 'MM', '98' => 'IR',
        // 1-digit
        '1' => 'US', '7' => 'RU',
    ];

    /** @return string|null ISO-3166 alpha-2, or null when the prefix isn't recognised. */
    public static function iso(?string $phone): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $phone);
        if ($digits === '') return null;

        // Longest dialing code first so 3-digit codes win over their prefixes.
        for ($len = 3; $len >= 1; $len--) {
            $prefix = substr($digits, 0, $len);
            if (isset(self::CODES[$prefix])) {
                return self::CODES[$prefix];
            }
        }
        return null;
    }
}
