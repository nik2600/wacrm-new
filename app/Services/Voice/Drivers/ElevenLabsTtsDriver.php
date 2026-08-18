<?php

namespace App\Services\Voice\Drivers;

use App\Models\Workspace;
use App\Services\AiKeyResolver;
use App\Services\Voice\Contracts\TtsDriver;
use App\Services\Voice\Dto\TtsResult;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * ElevenLabs text-to-speech.
 *
 *   POST https://api.elevenlabs.io/v1/text-to-speech/{voice_id}
 *        ?output_format=opus_48000_64
 *     xi-api-key: <key>
 *     JSON: { text, model_id, voice_settings }
 *
 * `opus_48000_64` returns OGG/Opus at 64 kbps — the sweet spot for
 * WhatsApp PTT (small file, fully native codec, no transcode).
 *
 * `voice_id` is REQUIRED by ElevenLabs (unlike OpenAI's named voices).
 * The AiAgent row's `voice_id` field carries it. We throw rather than
 * pick a default — silently sending the wrong brand voice would be
 * worse than failing the reply.
 *
 * Key resolution goes through AiKeyResolver: admin's global ElevenLabs
 * key is the default; workspace BYOK overrides only when the plan
 * grants allow_byok_ai_keys.
 */
class ElevenLabsTtsDriver implements TtsDriver
{
    private const DEFAULT_MODEL = 'eleven_turbo_v2_5';

    public function __construct(
        private readonly ?Workspace $workspace,
        private readonly string $modelId = self::DEFAULT_MODEL,
        private readonly int $timeoutSeconds = 60,
    ) {}

    public function synthesize(string $text, ?string $voiceId = null, ?string $language = null): TtsResult
    {
        $text = trim($text);
        if ($text === '') {
            throw new RuntimeException('TTS called with empty text.');
        }
        if (!$voiceId) {
            throw new RuntimeException('ElevenLabs requires a voice_id on the AiAgent. Configure one in Voice settings.');
        }

        $apiKey = AiKeyResolver::keyFor($this->workspace, 'elevenlabs');
        if (!$apiKey) {
            throw new RuntimeException('No ElevenLabs key configured — set one in Admin → AI Keys.');
        }

        $endpoint = sprintf(
            'https://api.elevenlabs.io/v1/text-to-speech/%s?output_format=opus_48000_64',
            urlencode($voiceId),
        );

        $res = Http::withHeaders([
                'xi-api-key' => $apiKey,
                'Accept'     => 'audio/ogg',
            ])
            ->timeout($this->timeoutSeconds)
            ->asJson()
            ->post($endpoint, [
                'text'     => $text,
                'model_id' => $this->modelId,
                'voice_settings' => [
                    'stability'         => 0.5,
                    'similarity_boost'  => 0.75,
                    'style'             => 0.0,
                    'use_speaker_boost' => true,
                ],
            ]);

        if (!$res->successful()) {
            throw new RuntimeException('ElevenLabs TTS failed: ' . $res->status() . ' ' . $res->body());
        }

        $outDir = $this->ensureOutputDir();
        $path   = $outDir . DIRECTORY_SEPARATOR . 'tts_el_' . uniqid() . '.ogg';
        if (file_put_contents($path, $res->body()) === false) {
            throw new RuntimeException('Could not write TTS output to ' . $path);
        }

        return new TtsResult(
            localPath:   $path,
            mimetype:    'audio/ogg; codecs=opus',
            charCount:   mb_strlen($text),
            durationSec: 0,
            meta:        ['voice_id' => $voiceId, 'model_id' => $this->modelId],
        );
    }

    public function name(): string { return 'elevenlabs'; }

    public function producesOgg(): bool { return true; }

    /**
     * Same disk as inbound chat-media (storage/app/public/...) so
     * InboxDispatcher's `storage_path('app/public/' . media_path)`
     * resolves the file when the outbound row hits the Node bridge.
     */
    private function ensureOutputDir(): string
    {
        $dir = storage_path('app' . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . 'voice-replies' . DIRECTORY_SEPARATOR . now()->format('Y-m'));
        if (!is_dir($dir)) @mkdir($dir, 0755, true);
        return $dir;
    }
}
