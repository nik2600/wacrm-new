<?php

namespace App\Services\Translation\Drivers;

use App\Services\Translation\AbstractTranslatorDriver;

/**
 * Google `gtx` client — the same translation engine that powers
 * translate.google.com, accessed through the unofficial endpoint
 * used by browser extensions and bookmarklets.
 *
 *   ⚠️ NOT an officially supported API. Google can rate-limit or
 *   shut this endpoint off at any time without notice. Best quality
 *   in the free tier, but you do NOT want to ship a paid product
 *   that depends ONLY on this.
 *
 * Strategy: ship as an optional driver buyer can flip on for the
 * "premium quality, free" tier — with a clear disclaimer in the
 * admin UI hint. Default driver stays MyMemory.
 */
class GoogleGtxDriver extends AbstractTranslatorDriver
{
    private const ENDPOINT = 'https://translate.googleapis.com/translate_a/single';

    public function translate(string $text, string $from, string $to): ?string
    {
        $text = trim($text);
        if ($text === '' || $from === $to) return $text ?: null;
        $json = $this->call($text, $from, $to);
        return $this->extractTranslated($json, $text);
    }

    public function detectAndTranslate(string $text, string $targetLang): array
    {
        $text = trim($text);
        if ($text === '') return ['language' => null, 'text' => null];

        $json = $this->call($text, 'auto', $targetLang);
        if (!is_array($json)) return ['language' => null, 'text' => null];

        $lang = is_string($json[2] ?? null) ? strtolower($json[2]) : null;
        $text = $this->extractTranslated($json, $text);
        return ['language' => $lang, 'text' => $text];
    }

    private function call(string $text, string $from, string $to): ?array
    {
        return $this->httpGet(self::ENDPOINT, [
            'client' => 'gtx', 'sl' => $from, 'tl' => $to, 'dt' => 't', 'q' => $text,
        ], ['User-Agent' => 'Mozilla/5.0']);
    }

    private function extractTranslated(?array $json, string $original): ?string
    {
        if (!is_array($json) || !isset($json[0]) || !is_array($json[0])) return null;
        $buf = '';
        foreach ($json[0] as $seg) {
            if (is_array($seg) && isset($seg[0]) && is_string($seg[0])) $buf .= $seg[0];
        }
        $buf = trim($buf);
        if ($buf === '' || mb_strtolower($buf) === mb_strtolower($original)) return null;
        return $buf;
    }

    public static function credentialFields(): array
    {
        // No credentials — the endpoint is anonymous.
        return [];
    }
}
