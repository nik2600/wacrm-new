<?php

namespace App\Services\Calling;

use App\Models\AiCallAssistant;
use App\Models\AiCallLog;
use App\Models\Conversation;
use App\Models\WaCall;
use App\Models\WaCallEvent;
use App\Models\Workspace;
use Illuminate\Support\Facades\Log;

/**
 * Mirror every WABA `wa_calls` row into `ai_call_logs` so the operator
 * sees ALL calls (AI-handled or operator-handled) in the new /call-logs
 * page. Keeps the WABA-native row as the source of truth; the mirror
 * is a flattened/denormalised view ready for the UI.
 *
 * Idempotent — `meta_call_id` round-trips into `twilio_call_sid` (we
 * reuse that column to avoid a schema fork; the column name is legacy
 * from the early plan). Re-firing a webhook won't dupe.
 */
class WaCallToAiLogBridge
{
    /**
     * Called on `connect` (incoming ring) — creates a placeholder
     * `in-progress` log row so the call shows up in /call-logs even
     * mid-conversation.
     */
    public function onConnect(WaCall $call): ?AiCallLog
    {
        $existing = $this->findExisting($call);
        if ($existing) return $existing;

        try {
            $assistantId = $this->resolveAssistantId($call);
            $log = AiCallLog::create([
                'workspace_id'     => $call->workspace_id,
                'assistant_id'     => $assistantId,
                'conversation_id'  => $call->conversation_id,
                'caller_phone'     => (string) $call->from_phone,
                'callee_phone'     => (string) $call->to_phone,
                // ai_call_logs.direction is a short column using the Twilio
                // vocabulary (inbound/outbound). WaCall stores Meta's long
                // form (BUSINESS_INITIATED/USER_INITIATED) which overflows it,
                // so map it down here.
                'direction'        => strtoupper((string) $call->direction) === 'BUSINESS_INITIATED' ? 'outbound' : 'inbound',
                'started_at'       => $call->started_at ?: now(),
                'duration_seconds' => 0,
                'status'           => 'in-progress',
                'twilio_call_sid'  => (string) $call->meta_call_id,
                'meta_json'        => ['wa_call_id' => $call->id, 'source' => 'waba'],
            ]);
            $call->forceFill(['ai_call_log_id' => $log->id])->save();
            if ($assistantId) $call->forceFill(['assistant_id' => $assistantId])->save();
            return $log;
        } catch (\Throwable $e) {
            Log::warning('[WACallBridge] onConnect failed: ' . $e->getMessage(), ['wa_call_id' => $call->id]);
            return null;
        }
    }

    /**
     * Called on `terminate` — fills in duration, recording, transcript,
     * status. Creates the row if onConnect was skipped (some carriers
     * deliver only the terminate event).
     */
    public function onTerminate(WaCall $call, array $webhookPayload = []): ?AiCallLog
    {
        $log = $this->findExisting($call);
        if (!$log) {
            $log = $this->onConnect($call);
            if (!$log) return null;
        }

        // Recording URL — Meta returns it when recording is enabled at
        // the wa_provider_config level. Different field names appear
        // across Meta API versions; check the common ones.
        $recording = $webhookPayload['recording_url']
            ?? ($webhookPayload['recording']['url'] ?? null)
            ?? $call->recording_path;

        $transcript = $this->buildTranscript($call);

        // Recording URLs — three layers of preference, in order:
        //   1. Meta's signed recording_url (when WABA-side recording was on)
        //   2. Our Node-side PCM dumps wrapped in WAV by CallRecordingController
        //   3. null when neither exists
        // Files on disk:
        //   public/uploads/call-recordings/{meta_call_id}_{user|agent}.pcm
        //
        // We stamp the URLs ONLY when an AI bridge actually handled the
        // call. For operator-handled calls there's no Node-side recorder,
        // so the .pcm files never land — stamping URLs anyway would give
        // the operator a broken <audio> player ("can't load").
        //
        // We stamp eagerly here (no is_file() check) because Node's flush
        // happens in parallel with this webhook handler; checking now
        // would race and stamp NULL even on legitimate AI calls. The
        // CallRecordingController returns 404 if the file genuinely
        // didn't land, so the <audio> degrades gracefully.
        $metaCallId  = $call->meta_call_id;
        $handlerType = (string) ($call->handler_type ?? '');
        $aiHandled   = $handlerType === 'ai_agent' && $call->assistant_id;
        $userUrl    = null;
        $agentUrl   = null;
        $mixedUrl   = $recording ? (string) $recording : null;
        if ($log->id && $metaCallId && $aiHandled) {
            $userUrl  = url("/call-logs/{$log->id}/audio/user");
            $agentUrl = url("/call-logs/{$log->id}/audio/agent");
            if (!$mixedUrl) {
                $mixedUrl = url("/call-logs/{$log->id}/audio/mixed");
            }
        }

        try {
            $log->update([
                'ended_at'        => $call->ended_at ?: now(),
                'duration_seconds'=> (int) ($call->duration_sec ?: 0),
                'status'          => $this->mapStatus($call),
                'failure_reason'  => $call->status === 'failed' ? (string) ($call->end_reason ?? '') : null,
                // Never clobber a URL that's already set — the browser-side
                // recording upload (operator calls) can land before OR after
                // this terminate webhook, and for operator calls the values
                // computed here are null. `?:` keeps whatever already exists.
                'recording_url_user'  => $userUrl  ?: $log->recording_url_user,
                'recording_url_agent' => $agentUrl ?: $log->recording_url_agent,
                'recording_url_mixed' => $mixedUrl ?: $log->recording_url_mixed,
                'transcript_json' => $transcript,
                'tool_calls_json' => $this->buildToolCalls($call),
                'meta_json'       => array_merge((array) $log->meta_json, [
                    'wa_end_reason' => $call->end_reason,
                    'wa_handler_type' => $call->handler_type,
                    'wa_payload'      => $webhookPayload,
                ]),
            ]);
            return $log;
        } catch (\Throwable $e) {
            Log::warning('[WACallBridge] onTerminate failed: ' . $e->getMessage(), ['wa_call_id' => $call->id]);
            return null;
        }
    }

    /** Idempotency — already-mirrored row? */
    private function findExisting(WaCall $call): ?AiCallLog
    {
        if ($call->ai_call_log_id) {
            return AiCallLog::find($call->ai_call_log_id);
        }
        if ($call->meta_call_id) {
            $hit = AiCallLog::where('twilio_call_sid', $call->meta_call_id)->first();
            if ($hit) {
                $call->forceFill(['ai_call_log_id' => $hit->id])->save();
                return $hit;
            }
        }
        return null;
    }

    /**
     * Picks the AI Voice Assistant for this call:
     *   1. Conversation's `routing_meta.voice_assistant_id` (operator
     *      pinned it via /team-inbox)
     *   2. Workspace default (`appointment_settings.default_call_assistant_id`)
     *   3. First active 'live' assistant in workspace
     *   4. null — call routes to human / voicemail
     */
    private function resolveAssistantId(WaCall $call): ?int
    {
        // 1) Operator manually attached an assistant to this conversation.
        if ($call->conversation_id) {
            $convo = Conversation::find($call->conversation_id);
            $convId = (int) ($convo?->routing_meta['voice_assistant_id'] ?? 0);
            if ($convId > 0) return $convId;
        }

        // Live assistants for this workspace (loaded once — used by the
        // device-binding + catch-all passes below).
        $assistants = AiCallAssistant::query()
            ->where('workspace_id', $call->workspace_id)
            ->where('is_active', true)
            ->where('status', 'live')
            ->orderBy('id')
            ->get();

        // 2) Per-number binding — the assistant that explicitly owns the WABA
        //    number this call came in on (call.wa_provider_config_id). This is
        //    the whole point of per-device routing: number A → its assistant.
        $cfgId = (int) ($call->wa_provider_config_id ?? 0);
        if ($cfgId > 0) {
            $bound = $assistants->first(function ($a) use ($cfgId) {
                $ids = is_array($a->device_ids) ? array_map('intval', $a->device_ids) : [];
                return in_array($cfgId, $ids, true);
            });
            if ($bound) return $bound->id;
        }

        // 3) Legacy explicit workspace default.
        $workspace = Workspace::find($call->workspace_id);
        $defaultId = (int) ($workspace?->appointment_settings['default_call_assistant_id'] ?? 0);
        if ($defaultId > 0) return $defaultId;

        // 4) Catch-all — an assistant with NO device binding answers any number
        //    that isn't explicitly assigned (this is how single-assistant
        //    workspaces keep working: their one assistant has empty device_ids).
        //    An assistant that IS bound to specific numbers deliberately does NOT
        //    leak onto other numbers here.
        $catchAll = $assistants->first(function ($a) {
            return empty($a->device_ids);
        });
        return $catchAll?->id;
    }

    private function mapStatus(WaCall $call): string
    {
        return match (true) {
            in_array($call->status, ['failed', 'error'], true) => 'failed',
            $call->status === 'rejected'                       => 'declined',
            $call->status === 'ended' && ($call->duration_sec ?? 0) > 0 => 'completed',
            $call->status === 'ended'                          => 'no-answer',
            default                                            => 'in-progress',
        };
    }

    /**
     * Pull the transcript from wa_call_events when the live AI bridge
     * has been writing turns there. For voicemail-only calls (today's
     * default) this is empty.
     */
    private function buildTranscript(WaCall $call): array
    {
        $events = WaCallEvent::query()
            ->where('wa_call_id', $call->id)
            ->where('event_type', 'transcript_turn')
            ->orderBy('id')
            ->get();
        return $events->map(fn ($e) => [
            'role' => $e->payload['role'] ?? 'agent',
            'text' => (string) ($e->payload['text'] ?? ''),
            't'    => (int) ($e->payload['t_ms'] ?? 0),
        ])->all();
    }

    private function buildToolCalls(WaCall $call): array
    {
        $events = WaCallEvent::query()
            ->where('wa_call_id', $call->id)
            ->where('event_type', 'tool_call')
            ->orderBy('id')
            ->get();
        return $events->map(fn ($e) => [
            'name'     => $e->payload['name'] ?? '',
            'args'     => $e->payload['args'] ?? [],
            'response' => $e->payload['response'] ?? null,
            't'        => (int) ($e->payload['t_ms'] ?? 0),
        ])->all();
    }
}
