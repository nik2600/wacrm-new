<?php

namespace App\Services;

/**
 * Bundled offline phrase dictionary — first stop in Translator.
 *
 * Lookup is O(1) by phrase key. Misses fall through to the cache +
 * API path. The file lives at database/data/common-phrases.json and
 * is loaded into a static array on first call.
 *
 * Why ship this?
 *   - Most auto-reply keywords are short common words (`hello`, `card`,
 *     `payment`, `info`, …). Unofficial / paid translation APIs disagree
 *     on these (e.g. MyMemory returned "My name is Azlan" for "hello"→ko
 *     in one of our smoke tests).
 *   - Hand-curating ~12 phrases × 30 languages = ~360 strings, ~10 KB.
 *     For ~1% of the script size it covers the 80% case at zero latency
 *     and zero quota cost.
 *
 * Edit `database/data/common-phrases.json` to extend.
 */
class CommonPhrasesDictionary
{
    /** @var array<string,array<string,string>>|null */
    private static ?array $cache = null;

    /**
     * Translate a phrase via the bundled dictionary.
     *
     * Returns null on miss — caller continues with cache/API path.
     */
    public static function lookup(string $phrase, string $from, string $to): ?string
    {
        $phrase = mb_strtolower(trim($phrase));
        if ($phrase === '') return null;
        $from = strtolower(trim($from));
        $to   = strtolower(trim($to));
        if ($from === $to) return $phrase;

        $dict = self::load();
        if (empty($dict)) return null;

        // Build a reverse-lookup view once per request: each value in
        // any language maps back to a canonical English key. Lets the
        // dictionary handle e.g. "कार्ड (hi)" → "tarjeta (es)" by going
        // hi→en (canonical) →es.
        $canonical = self::resolveCanonicalKey($phrase, $from, $dict);
        if ($canonical === null) return null;

        $row = $dict[$canonical] ?? null;
        if (!is_array($row)) return null;
        return $row[$to] ?? null;
    }

    /**
     * Walk the dictionary to find the canonical English key whose
     * $from-language form matches $phrase.
     */
    private static function resolveCanonicalKey(string $phrase, string $from, array $dict): ?string
    {
        // Fast path: $from === 'en' and the phrase IS a canonical key.
        if ($from === 'en' && isset($dict[$phrase])) return $phrase;

        foreach ($dict as $key => $variants) {
            $val = $variants[$from] ?? null;
            if (!is_string($val)) continue;
            if (mb_strtolower($val) === $phrase) return $key;
        }
        return null;
    }

    /**
     * Lazy-load the JSON file. Returns an empty array on any error so
     * a missing file never breaks translation — the API path still works.
     */
    private static function load(): array
    {
        if (self::$cache !== null) return self::$cache;
        $path = database_path('data/common-phrases.json');
        if (!is_file($path)) return self::$cache = [];
        try {
            $raw = file_get_contents($path);
            $parsed = json_decode($raw, true);
            self::$cache = is_array($parsed) ? $parsed : [];
        } catch (\Throwable $e) {
            self::$cache = [];
        }
        return self::$cache;
    }

    /** Clear the in-memory cache — used by tests when the file changes. */
    public static function flush(): void
    {
        self::$cache = null;
    }
}
