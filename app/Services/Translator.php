<?php

namespace App\Services;

use App\Services\Translation\AbstractTranslatorDriver;
use App\Services\Translation\TranslationProviderManager;
use Illuminate\Support\Facades\Cache;

/**
 * Sprint 8 — façade in front of TranslationProviderManager.
 *
 * Translator IS the public API every caller in the codebase uses.
 * Internally it delegates to whichever driver the admin has activated
 * via /admin/translation-providers — MyMemory by default, with
 * LibreTranslate / DeepL / Google Cloud / Google `gtx` as options.
 *
 * Layered lookup at every call:
 *
 *   1. Common-phrase offline dictionary (zero API call, ~50 phrases ×
 *      ~30 langs hand-curated for the words customers most often
 *      type into auto-replies).
 *
 *   2. 24h cache keyed by (text, from, to). Repeat hits in the same
 *      language from the same phrase are free.
 *
 *   3. Active driver. If it returns null, the manager's fallback
 *      chain retries the next is_active=true driver in sort order
 *      before giving up.
 *
 * `Translator::fanOut` runs N translations in parallel via Http::pool
 * inside the driver (when applicable) — only Google `gtx` overrides
 * it for true concurrency; others run sequentially.
 */
class Translator
{
    private const CACHE_TTL = 60 * 60 * 24;          // 24h

    public static function translate(string $text, string $from, string $to): ?string
    {
        $text = trim($text);
        if ($text === '') return $text;
        $from = strtolower(trim($from));
        $to   = strtolower(trim($to));
        if ($from === $to) return $text;

        // 1. Offline dictionary
        $dict = CommonPhrasesDictionary::lookup($text, $from, $to);
        if ($dict !== null) return $dict;

        // 2. Cache
        $cacheKey = 'translate:' . $from . ':' . $to . ':' . md5($text);
        $cached = Cache::get($cacheKey);
        if ($cached !== null) return $cached === '__null__' ? null : $cached;

        // 3. Driver chain. Track which driver actually served the
        // request + how many drivers we tried so the usage ledger
        // (sprint 9) knows whether it was a fallback hit.
        $result      = null;
        $servedBy    = null;
        $tried       = 0;
        foreach (app(TranslationProviderManager::class)->fallbackChain() as $driver) {
            $tried++;
            $candidate = $driver->translate($text, $from, $to);
            if ($candidate !== null) {
                $result   = $candidate;
                $servedBy = self::driverSlug($driver);
                break;
            }
        }

        Cache::put($cacheKey, $result ?? '__null__', self::CACHE_TTL);

        // Ledger: only log when we actually hit a remote API
        // (dictionary + cache short-circuited above). Failures still
        // log so the dashboard shows the volume of attempted calls.
        self::recordUsage($servedBy, $from, $to, $text, $result, $tried > 1);

        return $result;
    }

    /**
     * Detect the source language AND translate to target in one shot.
     * Same dictionary + cache + driver-chain layering as translate().
     *
     * @return array{language: ?string, text: ?string}
     */
    public static function detectAndTranslate(string $text, string $targetLang): array
    {
        $text = trim($text);
        $targetLang = strtolower(trim($targetLang));
        if ($text === '') return ['language' => null, 'text' => null];

        // 1. Offline dictionary — only useful if we already know the
        //    source language. Skipped here; falls through to driver
        //    which has its own detection.

        // 2. Cache
        $cacheKey = 'translate:auto:' . $targetLang . ':' . md5($text);
        $cached = Cache::get($cacheKey);
        if (is_array($cached)) return $cached;

        // 3. Driver chain
        $result   = ['language' => null, 'text' => null];
        $servedBy = null;
        $tried    = 0;
        foreach (app(TranslationProviderManager::class)->fallbackChain() as $driver) {
            $tried++;
            $out = $driver->detectAndTranslate($text, $targetLang);
            if (!empty($out['language']) && !empty($out['text'])) {
                $result   = $out;
                $servedBy = self::driverSlug($driver);
                break;
            }
            // Partial success — keep the best so far and try next.
            if (!empty($out['language']) && empty($result['language'])) {
                $result['language'] = $out['language'];
                if (!$servedBy) $servedBy = self::driverSlug($driver);
            }
            if (!empty($out['text']) && empty($result['text'])) $result['text'] = $out['text'];
        }

        Cache::put($cacheKey, $result, self::CACHE_TTL);

        // Ledger entry — detect+translate is one billable call.
        self::recordUsage(
            $servedBy,
            $result['language'] ?? 'auto',
            $targetLang,
            $text,
            $result['text'] ?? null,
            $tried > 1,
        );

        return $result;
    }

    /**
     * Detect-only convenience. Delegates to detectAndTranslate (to en)
     * so we benefit from the same cache key when the caller later
     * also wants the translation.
     */
    public static function detect(string $text): ?string
    {
        return self::detectAndTranslate($text, 'en')['language'];
    }

    /**
     * Persist one row to translation_usage. No-op when nothing actually
     * hit the wire (slug is null). Errors swallowed — losing a ledger
     * row should never break the translation path.
     */
    private static function recordUsage(?string $providerSlug, string $from, string $to, string $textIn, ?string $textOut, bool $wasFallback): void
    {
        if (!$providerSlug) return;
        try {
            $charsIn  = mb_strlen($textIn);
            $charsOut = $textOut !== null ? mb_strlen($textOut) : 0;
            \App\Models\TranslationUsage::create([
                'workspace_id'  => optional(auth()->user())->currentWorkspace?->id,
                'provider_slug' => $providerSlug,
                'source_lang'   => $from,
                'target_lang'   => $to,
                'chars_in'      => $charsIn,
                'chars_out'     => $charsOut,
                'cost_micros'   => \App\Models\TranslationUsage::estimateCostMicros($providerSlug, $charsIn),
                'was_fallback'  => $wasFallback,
                'called_at'     => now(),
            ]);
        } catch (\Throwable $e) {
            // Never propagate ledger failures.
        }
    }

    /** Reverse-lookup the slug for a driver instance from the registry. */
    private static function driverSlug($driver): ?string
    {
        $cls = get_class($driver);
        foreach (TranslationProviderManager::DRIVER_MAP as $slug => $registeredClass) {
            if ($registeredClass === $cls) return $slug;
        }
        return null;
    }

    /**
     * Translate one source string into many target languages. Used
     * by the eager `auto-reply:translate-existing` artisan command —
     * not on the hot inbound path.
     *
     * @param  string[] $targets ISO codes (excludes $from).
     * @return array<string,string> map of ISO code → translated string.
     */
    public static function fanOut(string $text, string $from, array $targets): array
    {
        $out = [];
        foreach ($targets as $to) {
            $translated = self::translate($text, $from, $to);
            if ($translated !== null) $out[$to] = $translated;
        }
        return $out;
    }
}
