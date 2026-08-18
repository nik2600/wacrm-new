<?php

namespace App\Services;

/**
 * Cheap, dependency-free language detector. Uses Unicode codepoint
 * ranges to classify the dominant script of a message in O(n) without
 * calling any external API. Covers the 18 highest-volume non-Latin
 * scripts; Latin-script messages fall back to a caller-supplied
 * default (typically workspace.default_language).
 *
 * Returns a 2-letter ISO 639-1 code where possible.
 *
 * Limitations:
 *   - Can't disambiguate Latin-script languages (en/es/fr/de/it). For
 *     those, pass a $fallback or accept the default 'en'.
 *   - Mixed-script messages (e.g. "hii 안녕") are classified by the
 *     dominant non-Latin script when one is present.
 */
class LanguageDetector
{
    /**
     * Codepoint ranges per script, ordered roughly by global volume so
     * the early-exit hits the common languages first. Each range is
     * (low, high, lang-code) inclusive.
     */
    private const SCRIPT_RANGES = [
        // Hangul (Korean) — most distinctive, check first
        [0xAC00, 0xD7AF, 'ko'],     // Hangul Syllables
        [0x1100, 0x11FF, 'ko'],     // Hangul Jamo
        [0x3130, 0x318F, 'ko'],     // Hangul Compatibility Jamo

        // Japanese — Hiragana / Katakana
        [0x3040, 0x309F, 'ja'],     // Hiragana
        [0x30A0, 0x30FF, 'ja'],     // Katakana

        // Indic scripts
        [0x0900, 0x097F, 'hi'],     // Devanagari (Hindi, Marathi, Nepali)
        [0x0980, 0x09FF, 'bn'],     // Bengali
        [0x0A00, 0x0A7F, 'pa'],     // Gurmukhi (Punjabi)
        [0x0A80, 0x0AFF, 'gu'],     // Gujarati
        [0x0B00, 0x0B7F, 'or'],     // Oriya
        [0x0B80, 0x0BFF, 'ta'],     // Tamil
        [0x0C00, 0x0C7F, 'te'],     // Telugu
        [0x0C80, 0x0CFF, 'kn'],     // Kannada
        [0x0D00, 0x0D7F, 'ml'],     // Malayalam
        [0x0D80, 0x0DFF, 'si'],     // Sinhala

        // Middle East
        [0x0600, 0x06FF, 'ar'],     // Arabic
        [0x0750, 0x077F, 'ar'],     // Arabic Supplement
        [0x0590, 0x05FF, 'he'],     // Hebrew
        [0x0700, 0x074F, 'ar'],     // Syriac (fold to Arabic)

        // South-east Asia
        [0x0E00, 0x0E7F, 'th'],     // Thai
        [0x0E80, 0x0EFF, 'lo'],     // Lao
        [0x1000, 0x109F, 'my'],     // Myanmar
        [0x1780, 0x17FF, 'km'],     // Khmer
        [0x1A00, 0x1A1F, 'su'],     // Buginese (fold to Sundanese)

        // Cyrillic family
        [0x0400, 0x04FF, 'ru'],     // Cyrillic (Russian, Ukrainian, Serbian)

        // Greek
        [0x0370, 0x03FF, 'el'],

        // Armenian + Georgian
        [0x0530, 0x058F, 'hy'],
        [0x10A0, 0x10FF, 'ka'],

        // Ethiopic (Amharic, Tigrinya)
        [0x1200, 0x137F, 'am'],

        // CJK Unified Ideographs — Chinese (check LAST among CJK since
        // Japanese can use these too; if we got here, no kana was found)
        [0x4E00, 0x9FFF, 'zh'],
        [0x3400, 0x4DBF, 'zh'],     // CJK Extension A
    ];

    /**
     * Detect the dominant language of $text.
     *
     * @param  string  $text     Inbound message body.
     * @param  ?string $fallback ISO code to return when the script is Latin
     *                            or undeterminable. Caller typically passes
     *                            the workspace's default_language.
     * @return string ISO 639-1 code (2 letters).
     */
    public static function detect(string $text, ?string $fallback = 'en'): string
    {
        $text = trim($text);
        if ($text === '') return $fallback ?? 'en';

        $tally = [];
        foreach (mb_str_split($text) as $char) {
            $cp = mb_ord($char, 'UTF-8');
            if ($cp === false) continue;
            // ASCII + Latin Extended-A/B — skip; these are language-ambiguous.
            if ($cp < 0x0100) continue;
            if ($cp >= 0x0100 && $cp <= 0x024F) continue;

            foreach (self::SCRIPT_RANGES as [$lo, $hi, $lang]) {
                if ($cp >= $lo && $cp <= $hi) {
                    $tally[$lang] = ($tally[$lang] ?? 0) + 1;
                    break;
                }
            }
        }

        if (empty($tally)) return $fallback ?? 'en';

        // Return the script with the highest hit count.
        arsort($tally);
        return array_key_first($tally);
    }

    /**
     * True when $text is mostly Latin script (ASCII letters + spaces).
     * Useful for callers that want to know whether the detector defaulted.
     */
    public static function isLatin(string $text): bool
    {
        $text = trim($text);
        if ($text === '') return true;
        return (bool) preg_match('/^[\x00-\x7F\x{0100}-\x{024F}\s]+$/u', $text);
    }

    /**
     * Human-readable display name for a given language code.
     * Used by the UI when listing translation chips.
     */
    public static function displayName(string $code): string
    {
        return self::LANGUAGE_NAMES[strtolower($code)] ?? strtoupper($code);
    }

    public const LANGUAGE_NAMES = [
        'en' => 'English',     'es' => 'Spanish',     'fr' => 'French',
        'de' => 'German',      'it' => 'Italian',     'pt' => 'Portuguese',
        'nl' => 'Dutch',       'pl' => 'Polish',      'ru' => 'Russian',
        'uk' => 'Ukrainian',   'tr' => 'Turkish',     'ar' => 'Arabic',
        'he' => 'Hebrew',      'fa' => 'Persian',     'hi' => 'Hindi',
        'bn' => 'Bengali',     'pa' => 'Punjabi',     'gu' => 'Gujarati',
        'or' => 'Odia',        'ta' => 'Tamil',       'te' => 'Telugu',
        'kn' => 'Kannada',     'ml' => 'Malayalam',   'si' => 'Sinhala',
        'mr' => 'Marathi',     'ne' => 'Nepali',      'ur' => 'Urdu',
        'th' => 'Thai',        'lo' => 'Lao',         'my' => 'Burmese',
        'km' => 'Khmer',       'vi' => 'Vietnamese',  'id' => 'Indonesian',
        'ms' => 'Malay',       'tl' => 'Filipino',    'zh' => 'Chinese',
        'ja' => 'Japanese',    'ko' => 'Korean',      'el' => 'Greek',
        'hy' => 'Armenian',    'ka' => 'Georgian',    'am' => 'Amharic',
        'sw' => 'Swahili',     'yo' => 'Yoruba',      'ha' => 'Hausa',
        'zu' => 'Zulu',        'af' => 'Afrikaans',   'cs' => 'Czech',
        'sk' => 'Slovak',      'hu' => 'Hungarian',   'ro' => 'Romanian',
        'bg' => 'Bulgarian',   'sr' => 'Serbian',     'hr' => 'Croatian',
        'sv' => 'Swedish',     'no' => 'Norwegian',   'da' => 'Danish',
        'fi' => 'Finnish',
    ];
}
