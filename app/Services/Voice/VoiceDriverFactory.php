<?php

namespace App\Services\Voice;

use App\Models\AiAgent;
use App\Models\Workspace;
use App\Services\Voice\Contracts\AsrDriver;
use App\Services\Voice\Contracts\TtsDriver;
use App\Services\Voice\Drivers\ElevenLabsTtsDriver;
use App\Services\Voice\Drivers\OpenAiTtsDriver;
use App\Services\Voice\Drivers\WhisperAsrDriver;
use InvalidArgumentException;

/**
 * Resolves AsrDriver / TtsDriver instances per AiAgent. Centralises
 * the provider switch so the pipeline service never branches on
 * `$agent->voice_provider` directly — add a new provider by adding a
 * new driver class and a case here.
 *
 * Drivers receive the AiAgent's Workspace so they can route key
 * lookups through AiKeyResolver (admin key default, workspace BYOK
 * only when the plan grants it).
 *
 * Sensible defaults:
 *   - ASR  → OpenAI Whisper (admin already configures OpenAI for chat)
 *   - TTS  → OpenAI alloy if voice_provider is null
 */
class VoiceDriverFactory
{
    public function asrFor(AiAgent $agent): AsrDriver
    {
        $provider = $agent->asr_provider ?: 'openai';
        $workspace = $this->workspaceFor($agent);
        return match ($provider) {
            'openai', 'whisper' => new WhisperAsrDriver(workspace: $workspace),
            default => throw new InvalidArgumentException("Unsupported ASR provider: $provider"),
        };
    }

    public function ttsFor(AiAgent $agent): TtsDriver
    {
        $provider = $agent->voice_provider ?: 'openai';
        $workspace = $this->workspaceFor($agent);
        return match ($provider) {
            'openai'     => new OpenAiTtsDriver(workspace: $workspace),
            'elevenlabs' => new ElevenLabsTtsDriver(workspace: $workspace),
            default      => throw new InvalidArgumentException("Unsupported TTS provider: $provider"),
        };
    }

    /**
     * Hydrate the agent's workspace once per driver build. Returns null
     * when the agent is orphaned (shouldn't happen in production but
     * keeps tests + edge cases sane) — AiKeyResolver handles a null
     * workspace by falling straight to the admin key.
     */
    private function workspaceFor(AiAgent $agent): ?Workspace
    {
        return $agent->workspace_id ? Workspace::find($agent->workspace_id) : null;
    }
}
