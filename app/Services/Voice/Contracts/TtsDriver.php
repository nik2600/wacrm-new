<?php

namespace App\Services\Voice\Contracts;

use App\Services\Voice\Dto\TtsResult;

/**
 * Text-to-speech driver. Implementations talk to one vendor (OpenAI TTS,
 * ElevenLabs, Cartesia, Azure) and return a normalised TtsResult pointing
 * at a local audio file.
 *
 *   $text       The reply body to synthesise.
 *   $voiceId    Provider-specific voice handle (e.g. ElevenLabs voice id,
 *               OpenAI voice name "alloy" / "nova"). Drivers fall back
 *               to a sensible default when null.
 *   $language   ISO 639-1 hint. Some drivers ignore it; others pick the
 *               regional model based on it.
 *
 * Drivers throw on transport / auth failure so the queue job can retry.
 */
interface TtsDriver
{
    public function synthesize(string $text, ?string $voiceId = null, ?string $language = null): TtsResult;

    public function name(): string;

    /**
     * Whether the driver natively produces an OGG/Opus stream Baileys can
     * forward without a transcode step. WhatsApp PTT voice notes must be
     * OGG/Opus; non-Opus output goes through ffmpeg before send.
     */
    public function producesOgg(): bool;
}
