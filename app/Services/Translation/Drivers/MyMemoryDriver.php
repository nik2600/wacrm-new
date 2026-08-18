<?php

namespace App\Services\Translation\Drivers;

use App\Services\Translation\AbstractTranslatorDriver;
use Illuminate\Support\Facades\Cache;

/**
 * MyMemory (translated.net) — free public translation API.
 * No key required (anonymous tier: 5k req/day per IP). Registering
 * an admin email via the `de` query param raises the quota to 50k.
 *
 * Stable since 2009. Quality varies — community-submitted translation
 * memory means short phrases sometimes resolve to weird literal
 * translations. We accept that tradeoff for "out-of-box works".
 *
 * Default driver shipped activated in TranslationProviderSeeder so
 * a fresh Envato install translates from minute zero with zero setup.
 *
 * Circuit breaker: once we get a 429 / "MYMEMORY WARNING" body we
 * mark the driver as tripped for 1 hour and the chain skips it
 * instantly. Avoids paying ~3-4s per failed call to a throttled
 * endpoint while the rest of the chain still works.
 */
class MyMemoryDriver extends AbstractTranslatorDriver
{
    private const ENDPOINT     = 'https://api.mymemory.translated.net/get';
    private const BREAKER_KEY  = 'translate:mymemory:breaker';
    private const BREAKER_TTL  = 60 * 60;   // 1 hour

    public function translate(string $text, string $from, string $to): ?string
    {
        $text = trim($text);
        if ($text === '' || $from === $to) return $text ?: null;
        if (Cache::get(self::BREAKER_KEY)) return null;   // circuit open
        return $this->call($text, $from, $to);
    }

    /**
     * MyMemory's "Autodetect" pseudo-language lets us combine detect
     * + translate into one call. The response's `responseData` field
     * includes a `detectedSourceLang` we mirror into our return shape.
     */
    public function detectAndTranslate(string $text, string $targetLang): array
    {
        $text = trim($text);
        if ($text === '') return ['language' => null, 'text' => null];
        if (Cache::get(self::BREAKER_KEY)) return ['language' => null, 'text' => null];

        $params = ['q' => $text, 'langpair' => 'Autodetect|' . $targetLang];
        if ($email = $this->cred('admin_email')) $params['de'] = $email;

        $json = $this->callRaw($params);
        if (!is_array($json)) return ['language' => null, 'text' => null];

        $translated = $json['responseData']['translatedText'] ?? null;
        // MyMemory exposes the detected language in `matches[0].source`
        // (e.g. "es-ES") — fold to 2-letter code.
        $detected = null;
        // MyMemory sometimes returns `matches` as a string ("" or a message)
        // instead of an array when there are no matches — guard so foreach
        // doesn't fatal on a string.
        $matches = $json['matches'] ?? [];
        if (!is_array($matches)) $matches = [];
        foreach ($matches as $m) {
            if (is_array($m) && !empty($m['source']) && is_string($m['source'])) {
                $detected = strtolower(explode('-', $m['source'])[0]);
                break;
            }
        }
        if (!is_string($translated) || $translated === '') {
            return ['language' => $detected, 'text' => null];
        }
        if (mb_strtolower(trim($translated)) === mb_strtolower($text)) {
            return ['language' => $detected, 'text' => null];
        }
        return ['language' => $detected, 'text' => $translated];
    }

    private function call(string $text, string $from, string $to): ?string
    {
        $params = ['q' => $text, 'langpair' => $from . '|' . $to];
        if ($email = $this->cred('admin_email')) $params['de'] = $email;
        $json = $this->callRaw($params);
        if (!is_array($json)) return null;

        $result = $json['responseData']['translatedText'] ?? null;
        if (!is_string($result) || $result === '') return null;
        if (mb_strtolower(trim($result)) === mb_strtolower($text)) return null;
        return $result;
    }

    /**
     * Direct HTTP call that returns the parsed JSON regardless of
     * status code. MyMemory returns 429 with a JSON warning body
     * when quota is exhausted — we need to see that body to trip
     * the circuit breaker, which the AbstractTranslatorDriver's
     * `httpGet` helper would silently drop.
     */
    private function callRaw(array $params): ?array
    {
        try {
            $resp = \Illuminate\Support\Facades\Http::timeout(self::HTTP_TIMEOUT)
                ->acceptJson()
                ->get(self::ENDPOINT, $params);
            $json = $resp->json();
            if (!is_array($json)) return null;
            if ($this->detectQuotaExhausted($json) || $resp->status() === 429) {
                Cache::put(self::BREAKER_KEY, 1, self::BREAKER_TTL);
                \Illuminate\Support\Facades\Log::info('[MyMemory] quota exhausted — circuit tripped for 1h');
                return null;
            }
            return $resp->successful() ? $json : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Recognise MyMemory's quota-exhausted response shape — they
     * return HTTP 200 with the translated-text field set to a
     * warning string. Trip the circuit breaker so the chain skips
     * this driver for an hour.
     */
    private function detectQuotaExhausted(array $json): bool
    {
        $text   = $json['responseData']['translatedText'] ?? '';
        $status = (int) ($json['responseStatus'] ?? 200);
        if ($status === 429) return true;
        return is_string($text) && stripos($text, 'MYMEMORY WARNING') === 0;
    }

    public static function credentialFields(): array
    {
        return [
            'admin_email' => [
                'label'       => 'Admin email (optional)',
                'type'        => 'text',
                'placeholder' => 'you@example.com',
                'hint'        => 'Optional. Raises the daily quota from 5k to 50k requests per IP.',
            ],
        ];
    }
}
