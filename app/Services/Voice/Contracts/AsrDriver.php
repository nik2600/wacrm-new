<?php

namespace App\Services\Voice\Contracts;

use App\Services\Voice\Dto\TranscriptResult;

/**
 * Speech-to-text driver. Implementations talk to one vendor (OpenAI
 * Whisper, Deepgram, Azure, etc.) and return a normalised TranscriptResult.
 *
 * Drivers must:
 *   - Read the audio file at $audioPath (may be any format the vendor
 *     accepts; the calling pipeline doesn't transcode for ASR).
 *   - Honour the $language hint when supplied (some vendors auto-detect
 *     regardless; pass it as a hint, not a hard filter).
 *   - Throw any exception on transport / auth failure so the queue job's
 *     retry mechanism can take over. Empty-transcript is NOT an error —
 *     return TranscriptResult with empty text.
 */
interface AsrDriver
{
    public function transcribe(string $audioPath, ?string $language = null): TranscriptResult;

    /** Human label for logs / billing rows. */
    public function name(): string;
}
