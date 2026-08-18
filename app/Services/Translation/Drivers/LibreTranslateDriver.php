<?php

namespace App\Services\Translation\Drivers;

use App\Services\Translation\AbstractTranslatorDriver;

/**
 * LibreTranslate — open-source self-hosted (or paid SaaS at
 * libretranslate.com). Buyers who don't want to send customer data
 * to third-party servers can `docker run libretranslate/libretranslate`
 * and point this driver at it. Quality is decent — based on
 * Argos Translate models.
 *
 * Required credentials: `endpoint` URL (e.g. http://localhost:5000)
 * Optional credentials: `api_key` (for paid libretranslate.com tier)
 */
class LibreTranslateDriver extends AbstractTranslatorDriver
{
    public function translate(string $text, string $from, string $to): ?string
    {
        $text = trim($text);
        if ($text === '' || $from === $to) return $text ?: null;

        $endpoint = $this->resolveEndpoint();
        if (!$endpoint) return null;

        $body = [
            'q'      => $text,
            'source' => $from,
            'target' => $to,
            'format' => 'text',
        ];
        if ($key = $this->cred('api_key')) $body['api_key'] = $key;

        $json = $this->httpPostJson($endpoint . '/translate', $body);
        $out  = $json['translatedText'] ?? null;
        if (!is_string($out) || $out === '') return null;
        if (mb_strtolower(trim($out)) === mb_strtolower($text)) return null;
        return $out;
    }

    public function detectAndTranslate(string $text, string $targetLang): array
    {
        $text = trim($text);
        if ($text === '') return ['language' => null, 'text' => null];

        // LibreTranslate accepts source='auto' on POST /translate and
        // returns the detected language in `detectedLanguage`.
        $endpoint = $this->resolveEndpoint();
        if (!$endpoint) return ['language' => null, 'text' => null];

        $body = ['q' => $text, 'source' => 'auto', 'target' => $targetLang, 'format' => 'text'];
        if ($key = $this->cred('api_key')) $body['api_key'] = $key;

        $json = $this->httpPostJson($endpoint . '/translate', $body);
        if (!is_array($json)) return ['language' => null, 'text' => null];

        return [
            'language' => is_string($json['detectedLanguage']['language'] ?? null)
                ? strtolower($json['detectedLanguage']['language']) : null,
            'text' => is_string($json['translatedText'] ?? null) ? $json['translatedText'] : null,
        ];
    }

    private function resolveEndpoint(): ?string
    {
        $url = trim((string) $this->cred('endpoint'));
        return $url !== '' ? rtrim($url, '/') : null;
    }

    public static function credentialFields(): array
    {
        return [
            'endpoint' => [
                'label'       => 'Endpoint URL',
                'type'        => 'text',
                'required'    => true,
                'placeholder' => 'http://localhost:5000',
                'hint'        => 'URL of your LibreTranslate server. For self-hosted Docker, usually http://localhost:5000.',
            ],
            'api_key' => [
                'label' => 'API key (optional)',
                'type'  => 'password',
                'hint'  => 'Required only for the hosted libretranslate.com tier. Leave blank for self-hosted.',
            ],
        ];
    }
}
