<?php

namespace App\Services\Voice\Drivers;

use App\Models\Workspace;
use App\Services\AiKeyResolver;
use App\Services\Voice\Contracts\AsrDriver;
use App\Services\Voice\Dto\TranscriptResult;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * OpenAI Whisper ASR.
 *
 *   POST https://api.openai.com/v1/audio/transcriptions
 *     Authorization: Bearer <key>
 *     multipart: file=<audio>, model=whisper-1, response_format=verbose_json,
 *                language=<iso2 hint, optional>
 *
 * Response (verbose_json):
 *   { text: "...", language: "english", duration: 4.32, ... }
 *
 * We pick verbose_json so we can persist the detected language + audio
 * duration on the message row for analytics + billing without a second
 * round-trip.
 *
 * Key resolution goes through AiKeyResolver, so:
 *   - Default: admin's global OpenAI key (admin_ai_keys)
 *   - BYOK plans: workspace key takes priority when allow_byok_ai_keys = true
 * No workspace ever needs to set its own key for the feature to work —
 * that's what makes it CodeCanyon-friendly out of the box.
 */
class WhisperAsrDriver implements AsrDriver
{
    public function __construct(
        private readonly ?Workspace $workspace,
        private readonly string $model = 'whisper-1',
        private readonly int $timeoutSeconds = 60,
    ) {}

    public function transcribe(string $audioPath, ?string $language = null): TranscriptResult
    {
        if (!is_file($audioPath)) {
            throw new RuntimeException("Audio file not found: $audioPath");
        }

        $apiKey = AiKeyResolver::keyFor($this->workspace, 'openai');
        if (!$apiKey) {
            throw new RuntimeException('No OpenAI key configured — set one in Admin → AI Keys.');
        }

        $req = $this->buildRequest($apiKey)
            ->attach('file', fopen($audioPath, 'r'), basename($audioPath));

        $form = ['model' => $this->model, 'response_format' => 'verbose_json'];
        if ($language) {
            // Whisper accepts ISO 639-1 — already normalised upstream on
            // the AiAgent's voice_language column. Drop blanks silently.
            $form['language'] = $language;
        }

        $res = $req->post('https://api.openai.com/v1/audio/transcriptions', $form);

        if (!$res->successful()) {
            throw new RuntimeException('Whisper ASR failed: ' . $res->status() . ' ' . $res->body());
        }

        $body = $res->json();
        return new TranscriptResult(
            text:        (string) ($body['text'] ?? ''),
            language:    $this->normaliseLang($body['language'] ?? null),
            durationSec: (int) round((float) ($body['duration'] ?? 0)),
            meta:        $body,
        );
    }

    public function name(): string { return 'openai_whisper'; }

    private function buildRequest(string $apiKey): PendingRequest
    {
        return Http::withToken($apiKey)
            ->timeout($this->timeoutSeconds)
            ->asMultipart();
    }

    /**
     * Whisper returns language NAMES ("english"), not ISO codes. Map
     * the common cases so downstream code can match against the
     * AiAgent's voice_language ISO field without normalising again.
     * Unknown languages with a 2-char tag fall through verbatim; longer
     * unknowns return null so callers can decide whether to treat
     * "unknown" as a fallback to the agent's configured language.
     */
    private function normaliseLang(?string $lang): ?string
    {
        if (!$lang) return null;
        $lang = strtolower($lang);
        return match ($lang) {
            'english'    => 'en',
            'hindi'      => 'hi',
            'spanish'    => 'es',
            'french'     => 'fr',
            'german'     => 'de',
            'portuguese' => 'pt',
            'italian'    => 'it',
            'arabic'     => 'ar',
            'russian'    => 'ru',
            'japanese'   => 'ja',
            'korean'     => 'ko',
            'chinese'    => 'zh',
            'turkish'    => 'tr',
            'indonesian' => 'id',
            'dutch'      => 'nl',
            default      => strlen($lang) === 2 ? $lang : null,
        };
    }
}
