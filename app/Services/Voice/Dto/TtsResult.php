<?php

namespace App\Services\Voice\Dto;

/**
 * Immutable result of a TTS call. The driver writes synthesised audio
 * to a local path under storage/app/public/voice-replies/ and hands the
 * caller the path + transport metadata.
 *
 *   localPath    Absolute filesystem path the audio was written to.
 *                Caller is responsible for cleaning it up (or letting
 *                the periodic cleanup job catch it).
 *   mimetype     Format of the file at localPath. Baileys cares about
 *                this for proper PTT handling (audio/ogg; codecs=opus).
 *   charCount    Characters synthesised — billed to the workspace per
 *                provider rates.
 *   durationSec  Approximate audio length; 0 when the driver doesn't
 *                report it.
 *   meta         Free-form provider metadata for forensics.
 */
final class TtsResult
{
    public function __construct(
        public readonly string $localPath,
        public readonly string $mimetype,
        public readonly int $charCount = 0,
        public readonly int $durationSec = 0,
        public readonly array $meta = [],
    ) {}
}
