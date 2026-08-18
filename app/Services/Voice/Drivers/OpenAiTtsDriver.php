<?php

namespace App\Services\Voice\Drivers;

use App\Models\Workspace;
use App\Services\AiKeyResolver;
use App\Services\Voice\Contracts\TtsDriver;
use App\Services\Voice\Dto\TtsResult;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * OpenAI text-to-speech.
 *
 *   POST https://api.openai.com/v1/audio/speech
 *     Authorization: Bearer <key>
 *     JSON: { model, voice, input, response_format }
 *
 * We request `opus` so the output is wrapped in OGG/Opus — the exact
 * codec WhatsApp PTT voice notes use, so no ffmpeg transcode is needed
 * before handing the file to Baileys / WABA.
 *
 * Available voices (verified against OpenAI's TTS catalogue as of
 * May 2026): alloy, echo, fable, onyx, nova, shimmer, ash, sage, coral.
 *
 * Key resolution goes through AiKeyResolver: admin's global OpenAI key
 * (admin_ai_keys) is the default; workspace BYOK overrides ONLY when
 * the plan grants allow_byok_ai_keys. Means a CodeCanyon installer
 * needs to configure exactly one place (admin panel) to turn voice
 * replies on for every workspace.
 */
class OpenAiTtsDriver implements TtsDriver
{
    private const DEFAULT_VOICE = 'alloy';

    public function __construct(
        private readonly ?Workspace $workspace,
        private readonly string $model = 'tts-1',
        private readonly int $timeoutSeconds = 60,
    ) {}

    public function synthesize(string $text, ?string $voiceId = null, ?string $language = null): TtsResult
    {
        $text = trim($text);
        if ($text === '') {
            throw new RuntimeException('TTS called with empty text.');
        }

        $apiKey = AiKeyResolver::keyFor($this->workspace, 'openai');
        if (!$apiKey) {
            throw new RuntimeException('No OpenAI key configured — set one in Admin → AI Keys.');
        }

        $voice = $voiceId ?: self::DEFAULT_VOICE;

        $res = Http::withToken($apiKey)
            ->timeout($this->timeoutSeconds)
            ->asJson()
            ->withHeaders(['Accept' => 'audio/ogg'])
            ->post('https://api.openai.com/v1/audio/speech', [
                'model'           => $this->model,
                'voice'           => $voice,
                'input'           => $text,
                // `opus` returns OGG/Opus — directly playable as a
                // WhatsApp PTT voice note. `mp3` (the default) would
                // need an ffmpeg transcode hop.
                'response_format' => 'opus',
            ]);

        if (!$res->successful()) {
            throw new RuntimeException('OpenAI TTS failed: ' . $res->status() . ' ' . $res->body());
        }

        $outDir = $this->ensureOutputDir();
        $path   = $outDir . DIRECTORY_SEPARATOR . 'tts_' . uniqid() . '.ogg';
        if (file_put_contents($path, $res->body()) === false) {
            throw new RuntimeException('Could not write TTS output to ' . $path);
        }

        return new TtsResult(
            localPath:   $path,
            mimetype:    'audio/ogg; codecs=opus',
            charCount:   mb_strlen($text),
            durationSec: 0, // OpenAI doesn't return duration; we estimate downstream if needed.
            meta:        ['voice' => $voice, 'model' => $this->model],
        );
    }

    public function name(): string { return 'openai_tts'; }

    public function producesOgg(): bool { return true; }

    /**
     * Lives under `storage/app/public/voice-replies/<YYYY-MM>/` —
     * the SAME disk inbound chat-media writes to. That alignment is
     * load-bearing: InboxDispatcher::buildNodeRequest reads media via
     * `storage_path('app/public/' . media_path)` when it builds the
     * Node bridge request, so a voice reply that ended up under
     * public_path() would never be found.
     */
    private function ensureOutputDir(): string
    {
        $dir = storage_path('app' . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . 'voice-replies' . DIRECTORY_SEPARATOR . now()->format('Y-m'));
        if (!is_dir($dir)) @mkdir($dir, 0755, true);
        return $dir;
    }
}
