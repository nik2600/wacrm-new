<?php

namespace App\Services\Translation\Drivers;

use App\Services\Translation\AbstractTranslatorDriver;

/**
 * Google Cloud Translation v2 — official paid translation API.
 * The version v2 (rest, single API key) is simpler than v3 which
 * requires OAuth. Pricing: $20 per 1M chars. No free tier beyond
 * Google Cloud's $300 trial credit.
 *
 * Use this when the buyer wants Google-quality translation AND a
 * stable contract — they sign up for a Cloud account, enable
 * Translation API, paste the API key here.
 */
class GoogleCloudDriver extends AbstractTranslatorDriver
{
    private const ENDPOINT = 'https://translation.googleapis.com/language/translate/v2';

    public function translate(string $text, string $from, string $to): ?string
    {
        $text = trim($text);
        if ($text === '' || $from === $to) return $text ?: null;

        $key = $this->cred('api_key');
        if (empty($key)) return null;

        $json = $this->httpPostJson(self::ENDPOINT . '?key=' . urlencode($key), [
            'q'      => $text,
            'source' => $from,
            'target' => $to,
            'format' => 'text',
        ]);
        $out = $json['data']['translations'][0]['translatedText'] ?? null;
        if (!is_string($out) || $out === '') return null;
        if (mb_strtolower(trim($out)) === mb_strtolower($text)) return null;
        return $out;
    }

    public function detectAndTranslate(string $text, string $targetLang): array
    {
        $text = trim($text);
        if ($text === '') return ['language' => null, 'text' => null];

        $key = $this->cred('api_key');
        if (empty($key)) return ['language' => null, 'text' => null];

        // Omitting `source` triggers auto-detect; v2 includes
        // detectedSourceLanguage on the translation object.
        $json = $this->httpPostJson(self::ENDPOINT . '?key=' . urlencode($key), [
            'q'      => $text,
            'target' => $targetLang,
            'format' => 'text',
        ]);
        $first = $json['data']['translations'][0] ?? null;
        if (!is_array($first)) return ['language' => null, 'text' => null];

        return [
            'language' => is_string($first['detectedSourceLanguage'] ?? null)
                ? strtolower($first['detectedSourceLanguage']) : null,
            'text' => is_string($first['translatedText'] ?? null) ? $first['translatedText'] : null,
        ];
    }

    public static function credentialFields(): array
    {
        return [
            'api_key' => [
                'label'    => 'Google Cloud API key',
                'type'     => 'password',
                'required' => true,
                'hint'     => 'Enable "Cloud Translation API" in Google Cloud Console, then create an API key. ~$20 per 1M chars.',
            ],
        ];
    }
}
