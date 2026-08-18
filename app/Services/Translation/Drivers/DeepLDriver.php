<?php

namespace App\Services\Translation\Drivers;

use App\Services\Translation\AbstractTranslatorDriver;

/**
 * DeepL — official paid translation API. Best-in-class quality for
 * European languages. Free tier: 500k chars/month. Paid: ~$5/1M
 * chars after that.
 *
 * Two endpoints depending on plan:
 *   - Free: https://api-free.deepl.com/v2/translate
 *   - Pro:  https://api.deepl.com/v2/translate
 * We pick automatically based on whether the API key ends with ":fx"
 * (DeepL's free-tier suffix convention).
 *
 * Supports ~30 languages — narrower than Google but higher quality.
 */
class DeepLDriver extends AbstractTranslatorDriver
{
    public function translate(string $text, string $from, string $to): ?string
    {
        $text = trim($text);
        if ($text === '' || $from === $to) return $text ?: null;

        $key = $this->cred('api_key');
        if (empty($key)) return null;

        $endpoint = str_ends_with($key, ':fx')
            ? 'https://api-free.deepl.com/v2/translate'
            : 'https://api.deepl.com/v2/translate';

        $json = $this->httpPostJson($endpoint, [
            'text'        => [$text],
            'source_lang' => strtoupper($from),
            'target_lang' => strtoupper($to),
        ], ['Authorization' => 'DeepL-Auth-Key ' . $key]);

        $out = $json['translations'][0]['text'] ?? null;
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

        $endpoint = str_ends_with($key, ':fx')
            ? 'https://api-free.deepl.com/v2/translate'
            : 'https://api.deepl.com/v2/translate';

        // Omitting source_lang triggers DeepL's auto-detect; the
        // detected language is returned on the translation object.
        $json = $this->httpPostJson($endpoint, [
            'text'        => [$text],
            'target_lang' => strtoupper($targetLang),
        ], ['Authorization' => 'DeepL-Auth-Key ' . $key]);

        $first = $json['translations'][0] ?? null;
        if (!is_array($first)) return ['language' => null, 'text' => null];

        return [
            'language' => is_string($first['detected_source_language'] ?? null)
                ? strtolower($first['detected_source_language']) : null,
            'text' => is_string($first['text'] ?? null) ? $first['text'] : null,
        ];
    }

    public static function credentialFields(): array
    {
        return [
            'api_key' => [
                'label'    => 'DeepL API key',
                'type'     => 'password',
                'required' => true,
                'hint'     => 'Free tier: 500k chars/month. Keys ending in :fx are free-tier, others Pro. Get one at deepl.com/pro-api.',
            ],
        ];
    }
}
