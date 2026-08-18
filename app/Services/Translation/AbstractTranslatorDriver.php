<?php

namespace App\Services\Translation;

use App\Models\TranslationProvider;
use Illuminate\Support\Facades\Http;

/**
 * Base class every translation driver inherits from.
 *
 * Each driver must implement:
 *   - translate($text, $from, $to): ?string
 *   - detectAndTranslate($text, $to): ['language' => ?string, 'text' => ?string]
 *   - public static credentialFields(): array  (admin form schema)
 *
 * The default detectAndTranslate is a 2-call composition (detect →
 * translate); drivers that have a single-call auto-detect endpoint
 * (Google) override it for efficiency.
 *
 * The base class hands the driver its TranslationProvider row at
 * construction so it can pull decrypted credentials + extra_config
 * without each driver re-implementing that boilerplate.
 */
abstract class AbstractTranslatorDriver
{
    protected const HTTP_TIMEOUT = 4;

    public function __construct(protected readonly TranslationProvider $row) {}

    /**
     * Translate one string. Return null on failure.
     */
    abstract public function translate(string $text, string $from, string $to): ?string;

    /**
     * Detect the source language AND translate to target in one shot
     * if the driver supports it; otherwise fall back to compose two
     * calls. The auto-detect fast path is overridden by Google /
     * Azure / DeepL where their API supports it.
     *
     * @return array{language: ?string, text: ?string}
     */
    public function detectAndTranslate(string $text, string $targetLang): array
    {
        $lang = $this->detectLanguage($text);
        if (!$lang) return ['language' => null, 'text' => null];
        if (strtolower($lang) === strtolower($targetLang)) {
            return ['language' => $lang, 'text' => $text];
        }
        $translated = $this->translate($text, $lang, $targetLang);
        return ['language' => $lang, 'text' => $translated];
    }

    /**
     * Best-effort language detection. Default impl uses the
     * Unicode-range detector — works without any API call. Drivers
     * with native detection may override.
     */
    protected function detectLanguage(string $text): ?string
    {
        return \App\Services\LanguageDetector::detect($text, null) ?: null;
    }

    /**
     * Form schema the admin sees when configuring this driver.
     * Same shape as the payment-gateway driver convention.
     *
     * @return array<string,array{label:string,type:string,required?:bool,hint?:string,placeholder?:string}>
     */
    public static function credentialFields(): array
    {
        return [];
    }

    /* ────────── helpers ────────── */

    /** Read one decrypted credential by key. */
    protected function cred(string $key, $default = null)
    {
        $creds = $this->row->getDecryptedCredentials();
        return $creds[$key] ?? $default;
    }

    /** Helper: do an HTTP GET, return decoded JSON or null on any failure. */
    protected function httpGet(string $url, array $query, array $headers = []): ?array
    {
        try {
            $resp = Http::timeout(self::HTTP_TIMEOUT)->withHeaders($headers)->get($url, $query);
            return $resp->successful() ? $resp->json() : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /** Helper: do an HTTP POST with JSON body. */
    protected function httpPostJson(string $url, array $body, array $headers = []): ?array
    {
        try {
            $resp = Http::timeout(self::HTTP_TIMEOUT)
                ->withHeaders(array_merge(['Accept' => 'application/json'], $headers))
                ->asJson()
                ->post($url, $body);
            return $resp->successful() ? $resp->json() : null;
        } catch (\Throwable $e) {
            return null;
        }
    }
}
