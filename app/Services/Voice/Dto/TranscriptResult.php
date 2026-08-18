<?php

namespace App\Services\Voice\Dto;

/**
 * Immutable result of an ASR call. Drivers return this regardless of
 * provider so the AiVoiceReplyService never reaches into a provider-
 * specific shape.
 *
 *   text          The transcribed body. Empty string if the audio was
 *                 silent / unintelligible — callers should treat empty
 *                 as a non-fatal "skip this voice note".
 *   language      ISO 639-1 code the driver detected, or null when the
 *                 driver couldn't determine it.
 *   durationSec   Audio length in whole seconds, billed to the workspace.
 *                 0 when the driver doesn't report it (we fall back to a
 *                 file-size estimate in the caller).
 *   meta          Free-form provider metadata for forensics; never read
 *                 by core logic.
 */
final class TranscriptResult
{
    public function __construct(
        public readonly string $text,
        public readonly ?string $language = null,
        public readonly int $durationSec = 0,
        public readonly array $meta = [],
    ) {}

    public function isEmpty(): bool
    {
        return trim($this->text) === '';
    }
}
