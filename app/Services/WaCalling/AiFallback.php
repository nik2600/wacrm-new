<?php

namespace App\Services\WaCalling;

use App\Models\AiAgent;
use App\Models\Conversation;
use App\Models\InboxMessage;
use App\Models\WaCall;
use App\Models\WaCallEvent;
use App\Services\Voice\VoiceDriverFactory;
use App\Services\Voice\VoiceOutboundDispatcher;
use Illuminate\Support\Facades\Log;

/**
 * AI handoff for missed WhatsApp calls.
 *
 * Phase-4 simplified flow: when no operator answers within
 * auto_pickup_delay_sec, we don't try to hold a live AI conversation
 * (that needs a Pipecat sidecar). Instead we:
 *
 *   1. Reject the call so the customer stops ringing.
 *   2. Send a TTS-synthesised voice note over chat: "Sorry we missed
 *      you — here's how to reach us." The text is the AiAgent's
 *      voicemail_prompt (falls back to a sensible default).
 *   3. Drop an internal note on the conversation so an operator can
 *      see what the AI said.
 *
 * Reuses the Phase-A voice pipeline (TTS + outbound dispatcher +
 * voice notes work on both WABA and Baileys numbers), so the same
 * code path that handles voice-note replies handles the voicemail
 * fallback. Zero net new infrastructure.
 *
 * A future enhancement can replace this with a Pipecat sidecar that
 * actually answers the call; the `handler_type=ai_agent` slot on
 * wa_calls is already wired to take that.
 */
class AiFallback
{
    public function __construct(
        private readonly WaCallingService $svc,
        private readonly VoiceDriverFactory $voiceDrivers,
        private readonly VoiceOutboundDispatcher $voiceOut,
    ) {}

    /**
     * Fire the fallback for a still-ringing call. Idempotent — safe to
     * call twice; the operator's `accept` always wins via the wa_calls
     * status check + DB-level row lock.
     *
     * Race against operator accept:
     *   - Accept POST runs in a transaction that lockForUpdate() the row
     *     and flips status='ringing' → 'connecting' atomically.
     *   - This fallback re-locks the row, re-reads status, and only
     *     proceeds if it's still 'ringing'. If the operator already
     *     flipped it to 'connecting' / 'active', we bail without
     *     overwriting handler_type. No double-fire.
     */
    public function trigger(WaCall $call): void
    {
        // Atomic claim: lock the row, re-check status, only commit if
        // still ringing. Without the lock the operator's accept might
        // beat the status read but lose the handler_type write.
        $claimed = \DB::transaction(function () use ($call) {
            $row = WaCall::where('id', $call->id)->lockForUpdate()->first();
            if (!$row || $row->status !== 'ringing') return false;
            $row->forceFill(['status' => 'ringing'])->save();  // no-op write to touch updated_at; keep status until reject() runs
            return $row;
        });
        if (!$claimed) {
            Log::info('[AI-FALLBACK] skipped — call no longer ringing', ['call_id' => $call->id]);
            return;
        }
        $call = $claimed;

        $agent = $this->pickAgent($call);
        $cfg   = $call->providerConfig;
        if (!$cfg) {
            Log::warning('[AI-FALLBACK] no provider config', ['call_id' => $call->id]);
            return;
        }

        // Honour the AI Call Assistant's Step-5 "no-answer behaviour":
        //   leave_message — speak the greeting as a voicemail (default)
        //   silent_log    — reject + log only, never message the caller
        // Saved on the assistant's meta_json by the wizard. Anything else
        // (incl. legacy "retry" agents) falls through to leave_message so
        // the caller is never ghosted.
        $assistant = $call->assistant_id ? \App\Models\AiCallAssistant::find($call->assistant_id) : null;
        $behavior  = (string) (($assistant?->meta_json['voicemail_behavior'] ?? null) ?: 'leave_message');

        // 1. Reject the call so the customer's phone stops ringing.
        try {
            $this->svc->reject($call, 'NO_OPERATOR');
        } catch (\Throwable $e) {
            Log::warning('[AI-FALLBACK] reject failed: ' . $e->getMessage());
        }

        $call->forceFill([
            'handler_type'     => 'voicemail',
            'handler_agent_id' => $agent?->id,
            'end_reason'       => 'NO_ANSWER',
        ])->save();

        WaCallEvent::create([
            'wa_call_id'  => $call->id,
            'event_type'  => 'ai_voicemail_triggered',
            'payload'     => ['agent_id' => $agent?->id, 'assistant_id' => $assistant?->id, 'behavior' => $behavior],
            'received_at' => now(),
        ]);

        // "Silent log" — the operator wants the call hung up and recorded
        // but the caller left undisturbed. The reject + WaCallEvent above
        // already log the attempt (visible in /call-logs), so we stop here
        // and never send a voicemail.
        if ($behavior === 'silent_log') {
            Log::info('[AI-FALLBACK] silent_log — rejected + logged, no voicemail sent', ['call_id' => $call->id]);
            return;
        }

        // 2. Send the voicemail message over chat as a voice note.
        // No-op gracefully if no AI agent is configured for voice.
        if (!$agent || !$agent->voice_note_enabled) return;

        $convo = $this->resolveConversation($call, $agent);
        if (!$convo) {
            Log::info('[AI-FALLBACK] no conversation to drop voicemail into', ['call_id' => $call->id]);
            return;
        }

        $text = $this->buildVoicemailText($agent, $call, $assistant);

        try {
            $tts = $this->voiceDrivers->ttsFor($agent)->synthesize(
                $text, $agent->voice_id, $agent->voice_language
            );
            // Pass the caller's number explicitly — for a missed call the
            // conversation may be freshly minted with no raw_jid / no prior
            // inbound message, so without this the voice note shipped to a
            // null recipient ("The parameter to is required" → never arrives).
            $this->voiceOut->send($convo, $agent, $text, $tts, (string) $call->from_phone);

            // 3. Internal note for the operator timeline.
            InboxMessage::create([
                'conversation_id' => $convo->id,
                'agent_id'        => $agent->id,
                'contact_id'      => $convo->contact_id,
                'direction'       => 'out',
                'body'            => "[AI voicemail sent — missed call from {$call->from_phone}]",
                'status'          => 'sent',
                'meta'            => [
                    'system_note'         => true,
                    'ai_voicemail'        => true,
                    'wa_call_id'          => $call->id,
                ],
                'sent_at'         => now(),
            ]);

            // "Retry" behaviour: we still leave the message (so the caller
            // isn't ghosted) and flag that a callback was requested. WaDesk
            // can't place an outbound WhatsApp call, so the callback is a
            // human action — surfaced on the call timeline for the operator.
            if ($behavior === 'retry') {
                WaCallEvent::create([
                    'wa_call_id'  => $call->id,
                    'event_type'  => 'ai_callback_requested',
                    'payload'     => ['note' => 'Voicemail left; operator asked for a callback — place it from the call log.'],
                    'received_at' => now(),
                ]);
            }
        } catch (\Throwable $e) {
            Log::error('[AI-FALLBACK] voicemail synth/send failed: ' . $e->getMessage());
        }
    }

    private function pickAgent(WaCall $call): ?AiAgent
    {
        // Hierarchy: conversation pin > workspace default voice agent > any active voice-enabled agent.
        if ($call->conversation_id) {
            $convo = Conversation::find($call->conversation_id);
            if ($convo?->assignee_agent_id) {
                $a = AiAgent::find($convo->assignee_agent_id);
                if ($a && $a->is_active && $a->voice_note_enabled) return $a;
            }
        }
        $workspace = $call->workspace;
        if ($workspace?->default_voice_ai_agent_id) {
            $a = AiAgent::find($workspace->default_voice_ai_agent_id);
            if ($a && $a->is_active && $a->voice_note_enabled) return $a;
        }
        return AiAgent::query()
            ->where('workspace_id', $call->workspace_id)
            ->where('is_active', true)
            ->where('voice_note_enabled', true)
            ->orderBy('id')
            ->first();
    }

    private function resolveConversation(WaCall $call, AiAgent $agent): ?Conversation
    {
        if ($call->conversation_id) return Conversation::find($call->conversation_id);
        if (!$call->contact_id) return null;

        $digits = preg_replace('/\D+/', '', (string) $call->from_phone);

        // REUSE the caller's existing thread instead of forking a duplicate —
        // a message thread OR a prior call thread for the same number. Match on
        // the digits form across raw_jid/alt_jid (and the historical
        // "digits@s.whatsapp.net" JID form), workspace-scoped, no origin/
        // provider filter so we adopt a message thread too. Without this, a
        // missed call that arrives BEFORE the customer ever texted spawned a
        // second "Missed call from …" conversation that the WABA message webhook
        // (which filters provider + origin IN inbox/chatbot) could never see →
        // the same number showed as two inbox rows.
        if ($digits !== '') {
            $existing = \App\Services\Inbox\ConversationResolver::find(
                (int) $call->workspace_id,
                $digits
            );
            if ($existing) {
                $call->forceFill(['conversation_id' => $existing->id])->save();
                return $existing;
            }
        }

        // No existing thread — mint one, stamped platform='W' + origin='inbox'
        // so the WABA MESSAGE webhook ADOPTS it for future texts (it filters on
        // provider + origin IN [inbox,chatbot]) instead of opening a second thread.
        $convo = Conversation::create([
            'workspace_id'       => $call->workspace_id,
            'contact_id'         => $call->contact_id,
            'channel'            => 'whatsapp',
            'platform'           => 'W',
            'origin'             => 'inbox',
            // Stamp the caller's number as the canonical JID so any reply
            // (voice note or text) into this thread has a real recipient.
            'raw_jid'            => $digits ?: null,
            'title'              => 'Missed call from ' . $call->from_phone,
            'inbox_status'       => 'open',
            'priority'           => 'normal',
            'assignee_agent_id'  => $agent->id,
            'device_id'          => null,
            'provider'           => \App\Services\WorkspaceEngine::for($call->workspace_id),
            'last_message_at'    => now(),
        ]);
        $call->forceFill(['conversation_id' => $convo->id])->save();

        return $convo;
    }

    private function buildVoicemailText(AiAgent $agent, WaCall $call, ?\App\Models\AiCallAssistant $assistant = null): string
    {
        // Prefer the AI Call Assistant's own greeting ("Speaks the greeting
        // and hangs up") when one is configured — that's the voicemail the
        // operator wrote in the wizard. Falls back to the chat agent's
        // system_prompt template, then a sensible default.
        $greeting = trim((string) ($assistant?->greeting_text ?? ''));
        if ($greeting !== '') return $greeting;

        // Operator can override per-agent via system_prompt; default is
        // a short, friendly callback message. No emojis (project rule).
        $template = trim((string) $agent->system_prompt);
        if ($template && str_contains($template, '{{voicemail}}')) {
            return str_replace('{{voicemail}}', '', $template);
        }
        $brand = $agent->name ?: 'our team';
        return "Hi, sorry we missed your call. This is " . $brand .
               ". Please reply to this message with your question and we'll get back to you shortly. Thanks for reaching out.";
    }
}
