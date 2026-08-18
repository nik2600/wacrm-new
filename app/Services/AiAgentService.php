<?php

namespace App\Services;

use App\Models\AiAgent;
use App\Models\AiProviderKey;
use App\Models\Conversation;
use App\Models\Device;
use App\Models\InboxMessage;
use App\Models\Message;
use App\Models\User;
use App\Models\Workspace;
use App\Services\WalletService;
use App\Services\InboxDispatcher;
use App\Services\PlanLimitGuard;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AiAgentService
{
    public function __construct(private InboxDispatcher $dispatcher, private WalletService $wallet)
    {
    }

    /**
     * If the conversation has an AI agent assigned and auto_respond is on,
     * generate a reply and store + dispatch it. Returns the Message or null.
     */
    public function respondIfAssigned(Conversation $convo): ?InboxMessage
    {
        if (!$convo->assignee_agent_id) {
            return null;
        }

        // Plan gate — workspaces whose package has access_ai_agents = false
        // can have a legacy assignee_agent_id row but must not auto-reply.
        // Otherwise a downgrade silently keeps the bot running.
        if ($convo->workspace_id) {
            $ws = Workspace::find($convo->workspace_id);
            if ($ws && !PlanLimitGuard::hasFeature($ws, 'access_ai_agents')) {
                Log::info('[AI-AGENT] skipped — plan does not include access_ai_agents', [
                    'workspace_id' => $ws->id, 'conv_id' => $convo->id,
                ]);
                return null;
            }
            // Meta Business Agent coexistence — if Meta's own agent is fronting
            // this workspace's WhatsApp, stand down so the customer never gets
            // two replies (one from Meta's agent, one from ours).
            if ($ws && $ws->suppressesOurAutoReply()) {
                Log::info('[AI-AGENT] skipped — Meta Business Agent is fronting this workspace', [
                    'workspace_id' => $ws->id, 'conv_id' => $convo->id, 'mode' => $ws->ai_responder_mode,
                ]);
                return null;
            }
        }

        // Defence-in-depth: only accept the assigned agent if it
        // belongs to the same workspace as the conversation. Without
        // this, an `assignee_agent_id` set to another workspace's agent
        // (data error, manual fixture, race during a workspace move)
        // would silently make the AI from one tenant reply to the
        // wrong tenant's customer.
        $agent = AiAgent::where('id', $convo->assignee_agent_id)
            ->when($convo->workspace_id, fn ($q) => $q->where('workspace_id', $convo->workspace_id))
            ->first();
        if (!$agent || !$agent->is_active || !$agent->auto_respond) {
            return null;
        }

        // Multi-device guard. If this agent is scoped to one or more
        // specific devices, skip the conversation when its inbound
        // device doesn't match. Lets one workspace run distinct AI
        // personas per paired number (e.g. "Sales bot" only on the
        // marketing line, "Support bot" only on the helpdesk line)
        // without cross-talk. Agents with no device_ids (the default)
        // handle every device — same behavior single-device installs
        // saw before this feature shipped.
        if (!$agent->handlesDevice($convo->device_id)) {
            Log::info('[AI-AGENT] skipped — device out of scope', [
                'agent_id'      => $agent->id,
                'conv_id'       => $convo->id,
                'conv_device'   => $convo->device_id,
                'agent_devices' => is_array($agent->device_ids) ? $agent->device_ids : [],
            ]);
            return null;
        }

        // Handoff check — run BEFORE generating a reply. If any condition
        // fires we hand off to humans instead of spinning more tokens.
        if ($agent->handoff_enabled ?? true) {
            $reason = $this->shouldHandoff($agent, $convo);
            if ($reason) {
                $this->triggerHandoff($agent, $convo, $reason);
                return null;
            }
        }

        // Billing is plan-first via OverflowBilling inside InboxDispatcher::send()
        // — free under the workspace's monthly_messages_limit, 1 wallet credit only
        // on overflow. No wallet pre-gate here: an active plan must not be blocked
        // (and the LLM call already spent) just because the wallet sits at 0. When
        // the workspace is genuinely over-cap AND out of wallet credits the
        // dispatcher throws PlanLimitReachedException, which we catch below to hand
        // off (same intent as the old out-of-credits gate, but plan-aware).
        $reply = $this->generateReply($agent, $convo);
        if (!$reply) {
            Log::warning('[AI-AGENT] empty reply from LLM', [
                'agent_id'   => $agent->id,
                'conv_id'    => $convo->id,
                'provider'   => $agent->provider,
                'model'      => $agent->model,
            ]);
            return null;
        }

        // Resolve device phone for outbound routing — same pattern as
        // TeamInboxController::reply(). The from_number is what Node uses
        // to look up the paired WhatsApp session.
        $device = Device::find($convo->device_id);
        $fromNumber = $device
            ? preg_replace('/\D+/', '', (string) ($device->country_code . $device->phone_number))
            : '';

        // Resolve target — prefer raw_jid so LID-routed convos go to the
        // right number (same logic as TeamInboxController reply).
        $meta = [];
        if ($convo->raw_jid) {
            $meta['target_jid'] = $convo->raw_jid;
        }

        // Resolve the recipient phone the SAME robust way human replies do
        // (TeamInboxController::reply): the most recent inbound message's
        // from_number is the real customer number. raw_jid digits are only a
        // last resort — they're EMPTY when raw_jid is null and a fabricated
        // number for LID-routed convos, which is why AI replies were dying at
        // dispatch with "no recipient" while human replies went through.
        $toNumber = InboxMessage::query()
            ->where('conversation_id', $convo->id)
            ->where('direction', 'in')
            ->orderByDesc('id')
            ->value('from_number');
        if (!$toNumber && $convo->raw_jid) {
            $toNumber = preg_replace('/\D+/', '', (string) $convo->raw_jid);
        }
        if (!$toNumber && $convo->title && preg_match('/\+?(\d{8,15})/', (string) $convo->title, $m)) {
            $toNumber = $m[1];
        }

        // Create the outbound message row first so it's visible in the thread
        // even if the Baileys dispatch fails.
        $msg = InboxMessage::create([
            'conversation_id' => $convo->id,
            'user_id'         => $convo->user_id,
            'agent_id'        => $agent->id,
            'direction'       => 'out',
            'from_number'     => $fromNumber,
            'to_number'       => (string) ($toNumber ?: ''),
            'body'            => $reply,
            'meta'            => !empty($meta) ? $meta : null,
            'status'          => 'pending',
            'sent_at'         => now(),
        ]);

        // Update conversation preview + timestamps.
        $convo->update([
            'preview'          => mb_substr($reply, 0, 191),
            'last_message_at'  => now(),
            'last_outbound_at' => now(),
        ]);

        $agent->increment('messages_sent');

        // Dispatch via the same dispatcher used by human replies. This handles
        // provider resolution (Baileys/WABA/Twilio), auth, URL building, logging
        // AND plan-first billing (OverflowBilling) — we get all that for free.
        try {
            $result = $this->dispatcher->send($msg, $convo->platform ?? 'W');
            if (!($result['ok'] ?? false)) {
                Log::warning('[AI-AGENT] dispatch failed', [
                    'agent_id' => $agent->id,
                    'conv_id'  => $convo->id,
                    'error'    => $result['error'] ?? 'unknown',
                ]);
                $msg->update(['status' => 'failed', 'failure_reason' => $result['error'] ?? 'dispatch failed']);
            } else {
                // Stash the provider's wa_message_id so future pin/star/
                // react actions on the AI's reply can target it.
                $update = ['status' => 'sent', 'sent_at' => now()];
                if (!empty($result['provider_id'])) {
                    $existingMeta = is_array($msg->meta) ? $msg->meta : [];
                    $update['meta'] = array_merge($existingMeta, ['wa_message_id' => (string) $result['provider_id']]);
                }
                $msg->update($update);
            }
        } catch (\App\Exceptions\PlanLimitReachedException $e) {
            // Over plan cap AND wallet empty — hand off to a human rather than
            // keep burning LLM spend on undeliverable replies (mirrors the old
            // out-of-credits handoff, now plan-aware).
            Log::warning('[AI-AGENT] plan cap reached — handing off', [
                'agent_id' => $agent->id, 'conv_id' => $convo->id,
            ]);
            $msg->update(['status' => 'failed', 'failure_reason' => mb_substr($e->getMessage(), 0, 191)]);
            $this->triggerHandoff($agent, $convo, 'out_of_credits');
        } catch (\Throwable $e) {
            Log::error('[AI-AGENT] dispatch exception: ' . $e->getMessage(), ['agent_id' => $agent->id]);
            $msg->update(['status' => 'failed', 'failure_reason' => $e->getMessage()]);
        }

        // Self-rate the reply (best-effort, non-blocking). The agent
        // scores its own answer 1–10 and writes a one-line rationale.
        // Operator-visible only — never sent to the customer.
        try {
            $this->selfRate($agent, $convo, $msg, $reply);
        } catch (\Throwable $e) {
            Log::info('[AI-AGENT] self-rate skipped: ' . $e->getMessage());
        }

        return $msg;
    }

    /**
     * Voice-assistant-driven text reply. When a conversation has been
     * assigned to an AiCallAssistant from /team-inbox we land here on
     * every inbound. The Voice Assistant's configuration (system_prompt
     * + ai_provider/model) drives the same callProvider path used by
     * AiAgent — we just don't have an AiAgent row to bill or self-rate
     * against, so this is a slimmer version of respondIfAssigned.
     *
     * Returns the outbound InboxMessage on successful send, null otherwise.
     */
    public function respondAsVoiceAssistant(Conversation $convo, int $assistantId): ?InboxMessage
    {
        // Plan gate — voice-assistant text replies share the AI agents
        // feature flag. A workspace downgraded off `access_ai_agents`
        // must stop both AiAgent and AiCallAssistant auto-replies.
        if ($convo->workspace_id) {
            $ws = Workspace::find($convo->workspace_id);
            if ($ws && !PlanLimitGuard::hasFeature($ws, 'access_ai_agents')) {
                Log::info('[AI-VOICE] skipped — plan does not include access_ai_agents', [
                    'workspace_id' => $ws->id, 'conv_id' => $convo->id,
                ]);
                return null;
            }
        }

        $assistant = \App\Models\AiCallAssistant::find($assistantId);
        if (!$assistant || !$assistant->is_active) {
            return null;
        }
        // Status flag is the operator-controlled go/no-go.
        if ($assistant->status !== 'live') {
            return null;
        }

        // Build prompt context — last N inbound messages so the model
        // has the conversation history, same as generateReply does for
        // AiAgent. Use the latest inbound as the immediate user prompt.
        $history = InboxMessage::query()
            ->where('conversation_id', $convo->id)
            ->orderByDesc('id')
            ->limit(15)
            ->get()
            ->reverse()
            ->values();
        $transcript = $history->map(fn ($m) =>
            ($m->direction === 'in' ? 'Customer' : 'Agent') . ': ' . ($m->body ?? '')
        )->implode("\n");

        $systemPrompt = trim((string) $assistant->ai_system_prompt) ?:
            'You are a helpful WhatsApp assistant. Reply only with the message text — no prefix, no signature.';

        $reply = $this->callProvider(
            provider:     (string) $assistant->ai_provider,
            model:        (string) $assistant->ai_model,
            workspaceId:  (int) $convo->workspace_id,
            systemPrompt: $systemPrompt,
            userPrompt:   "Conversation so far:\n" . $transcript . "\n\nWrite the next reply to the customer. Keep it WhatsApp-natural — short, no 'Agent:' prefix.",
            maxTokens:    400,
            temperature:  0.7,
        );

        if (!$reply || trim($reply) === '') {
            Log::warning('[VOICE-AS] empty reply', ['assistant_id' => $assistant->id, 'conv_id' => $convo->id]);
            return null;
        }

        // Billing is plan-first via OverflowBilling inside InboxDispatcher::send()
        // — free under monthly_messages_limit, wallet credit only on overflow.
        // No wallet pre-gate / charge / refund here.
        $device = Device::find($convo->device_id);
        $fromNumber = $device
            ? preg_replace('/\D+/', '', (string) ($device->country_code . $device->phone_number))
            : '';

        $meta = ['voice_assistant_id' => $assistant->id, 'voice_assistant_name' => $assistant->name];
        if ($convo->raw_jid) $meta['target_jid'] = $convo->raw_jid;

        $msg = InboxMessage::create([
            'conversation_id' => $convo->id,
            'user_id'         => $convo->user_id,
            'direction'       => 'out',
            'from_number'     => $fromNumber,
            'to_number'       => $convo->raw_jid ? preg_replace('/\D+/', '', $convo->raw_jid) : '',
            'body'            => $reply,
            'meta'            => $meta,
            'status'          => 'pending',
            'sent_at'         => now(),
        ]);
        $convo->update([
            'preview'          => mb_substr($reply, 0, 191),
            'last_message_at'  => now(),
            'last_outbound_at' => now(),
        ]);

        try {
            $result = $this->dispatcher->send($msg, $convo->platform ?? 'W');
            if (!($result['ok'] ?? false)) {
                $msg->update(['status' => 'failed', 'failure_reason' => $result['error'] ?? 'dispatch failed']);
            } else {
                $update = ['status' => 'sent', 'sent_at' => now()];
                if (!empty($result['provider_id'])) {
                    $update['meta'] = array_merge($meta, ['wa_message_id' => (string) $result['provider_id']]);
                }
                $msg->update($update);
            }
        } catch (\App\Exceptions\PlanLimitReachedException $e) {
            Log::warning('[VOICE-AS] plan cap reached', ['assistant_id' => $assistant->id, 'conv_id' => $convo->id]);
            $msg->update(['status' => 'failed', 'failure_reason' => mb_substr($e->getMessage(), 0, 191)]);
        } catch (\Throwable $e) {
            Log::error('[VOICE-AS] dispatch exception: ' . $e->getMessage(), ['assistant_id' => $assistant->id]);
            $msg->update(['status' => 'failed', 'failure_reason' => $e->getMessage()]);
        }
        return $msg;
    }

    /**
     * Ask the same model to score its own reply on 1–10 with a one-line
     * reason. Stored on Message::quality_score + quality_note. Operator
     * sees it in /team-inbox/analytics/ai-agents — never visible to the
     * customer. Best-effort; failures are swallowed.
     */
    public function selfRate(AiAgent $agent, Conversation $convo, InboxMessage $msg, string $reply): void
    {
        $lastInbound = InboxMessage::query()
            ->where('conversation_id', $convo->id)
            ->where('direction', 'in')
            ->orderByDesc('id')
            ->value('body');
        if (!$lastInbound) $lastInbound = '(no prior customer message)';

        $rubric = "You are auditing your OWN response to a customer message. " .
                  "Score the response on a scale of 1 to 10 (10 = perfect, on-topic, helpful, polite). " .
                  "Reply with ONE line in EXACTLY this JSON format: {\"score\": <int 1-10>, \"note\": \"<one short sentence>\"}\n\n" .
                  "Customer asked:\n" . mb_substr($lastInbound, 0, 800) . "\n\n" .
                  "Your response was:\n" . mb_substr($reply, 0, 800);

        $raw = $this->callProvider(
            provider:     $agent->provider,
            model:        $agent->model,
            workspaceId:  $convo->workspace_id,
            systemPrompt: 'You are a strict quality auditor. Return ONLY valid JSON, no prose.',
            userPrompt:   $rubric,
            maxTokens:    100,
            temperature:  0.0,
        );
        if (!$raw) return;

        // Strip code fences if the model wrapped its JSON.
        $clean = trim(preg_replace('/^```(?:json)?\s*|\s*```$/m', '', $raw));
        $data  = json_decode($clean, true);
        if (!is_array($data)) return;
        $score = isset($data['score']) ? (int) $data['score'] : 0;
        $note  = isset($data['note'])  ? mb_substr((string) $data['note'], 0, 191) : null;
        if ($score < 1 || $score > 10) return;

        $msg->update([
            'quality_score' => $score,
            'quality_note'  => $note,
        ]);
    }

    /**
     * Generate a reply text using the agent's LLM config.
     * Called by respondIfAssigned() and by the inline test endpoint.
     */
    public function generateReply(AiAgent $agent, Conversation $convo): ?string
    {
        $history = InboxMessage::query()
            ->where('conversation_id', $convo->id)
            ->orderByDesc('id')
            ->limit(20)
            ->get()
            ->reverse()
            ->values();

        $transcript = $history->map(function ($m) {
            $who = $m->direction === 'in' ? 'Customer' : 'Agent';
            return $who . ': ' . ($m->body ?? '');
        })->implode("\n");

        $toneHint = match ($agent->tone) {
            'friendly'   => 'Be warm, friendly, and conversational. Use the customer\'s name if known.',
            'concise'    => 'Be extremely brief — 1-2 sentences max. No pleasantries.',
            'empathetic' => 'Show genuine empathy and understanding. Acknowledge feelings first.',
            default      => 'Be professional, clear, and helpful.',
        };

        $systemPrompt = $agent->system_prompt
            ? trim($agent->system_prompt) . "\n\n" . $toneHint
            : "You are a helpful WhatsApp business assistant. " . $toneHint
              . "\n\nImportant: Reply only with the message text. Do NOT prefix with 'Agent:' or your name. Keep responses concise and natural for WhatsApp.";

        // Real-time translation — when the conversation's customer language is
        // pinned (and translation is on for the workspace), tell the model to
        // reply natively in that language. This is higher quality than
        // round-tripping an English reply, and the outbound translator then
        // correctly no-ops (the reply is already in the customer's language).
        try {
            $custLang = strtolower(trim((string) ($convo->customer_language ?? '')));
            if ($custLang !== '') {
                $cfg = app(\App\Services\Inbox\ConversationTranslationService::class)->config($convo->workspace_id);
                if ($cfg['enabled'] && $custLang !== $cfg['lang']) {
                    $systemPrompt .= "\n\nThe customer is writing in language code \"{$custLang}\". Reply ENTIRELY in that same language, naturally.";
                }
            }
        } catch (\Throwable $e) { /* language hint is best-effort */ }

        // Inject the workspace's saved replies into the system prompt
        // when the agent opted in. Caps at 15 (by used_count desc) so
        // token usage stays predictable — operators get the bulk of the
        // signal from their most-used responses anyway.
        if ($agent->use_saved_replies) {
            $cannedBlock = $this->cannedRepliesBlock((int) ($convo->workspace_id ?? 0));
            if ($cannedBlock !== '') {
                $systemPrompt .= "\n\n" . $cannedBlock;
            }
        }

        // Vision — if the customer's latest inbound message is an image,
        // attach it so the agent can actually SEE and answer about it
        // (product photo, screenshot, receipt, damaged item, etc.). When
        // there's no image this is null and the call is unchanged.
        $image = $this->resolveInboundImage($convo);

        $userPrompt = "Conversation history:\n" . $transcript
            . ($image ? "\n\nThe customer's latest message includes an image (attached). Look at it and reply about what it shows." : "")
            . "\n\nWrite the next reply to the customer. Match their language. Reply only with the message — no prefix, no signature.";

        $reply = $this->callProvider(
            provider:     $agent->provider,
            model:        $agent->model,
            workspaceId:  (int) ($convo->workspace_id ?? 0),
            systemPrompt: $systemPrompt,
            userPrompt:   $userPrompt,
            maxTokens:    $agent->max_tokens,
            temperature:  $agent->temperatureFloat(),
            image:        $image,
        );

        // Graceful fallback: if we attached an image but got nothing back
        // (most likely the agent's configured model isn't vision-capable
        // and the provider rejected the image block), retry once text-only
        // so the customer still gets a reply from any caption / context.
        if ($image && ($reply === null || trim($reply) === '')) {
            Log::info('[AI-AGENT] vision reply empty — retrying text-only', [
                'model' => $agent->model, 'conv_id' => $convo->id,
            ]);
            $reply = $this->callProvider(
                provider:     $agent->provider,
                model:        $agent->model,
                workspaceId:  (int) ($convo->workspace_id ?? 0),
                systemPrompt: $systemPrompt,
                userPrompt:   $userPrompt,
                maxTokens:    $agent->max_tokens,
                temperature:  $agent->temperatureFloat(),
            );
        }

        return $reply;
    }

    /**
     * If the customer's MOST RECENT inbound message is an image, return
     * ['mime' => ..., 'b64' => ...] so the LLM call can include it as a
     * vision block. Returns null when there's no fresh inbound image, the
     * file can't be resolved on disk, or it exceeds the size cap — which
     * keeps the text path (and non-vision models) working unchanged.
     *
     * Scoped to "still the latest inbound" so a long text follow-up after
     * the picture doesn't re-upload (and re-bill) the same image on every
     * subsequent reply.
     */
    private function resolveInboundImage(Conversation $convo): ?array
    {
        $msg = InboxMessage::query()
            ->where('conversation_id', $convo->id)
            ->where('direction', 'in')
            ->where('media_type', 'image')
            ->whereNotNull('media_path')
            ->orderByDesc('id')
            ->first();
        if (!$msg) return null;

        // Only "see" the image while it's the customer's latest turn — bail
        // if any newer inbound text/media has arrived since.
        $hasNewerInbound = InboxMessage::query()
            ->where('conversation_id', $convo->id)
            ->where('direction', 'in')
            ->where('id', '>', $msg->id)
            ->exists();
        if ($hasNewerInbound) return null;

        $path = ltrim((string) $msg->media_path, '/\\');
        if ($path === '') return null;

        // Inbound media lives on the active media disk (cloud when enabled,
        // else the local `public` disk WaInboundController writes to). Read
        // from that disk so vision works whether media is local or cloud.
        $disk = media_storage();
        try {
            if (!$disk->exists($path)) return null;

            // Size cap — 4MB raw stays under every provider's per-image base64
            // limit (Anthropic's ~5MB is the tightest) and avoids HTTP timeouts.
            $size = $disk->size($path);
            if ($size === null || $size <= 0 || $size > 4_000_000) return null;

            $bytes = $disk->get($path);
        } catch (\Throwable $e) {
            return null;
        }
        if ($bytes === null || $bytes === '') return null;

        return [
            'mime' => $this->imageMimeFromPath($path),
            'b64'  => base64_encode($bytes),
        ];
    }

    /** Map a file extension to an image MIME accepted by all 3 providers. */
    private function imageMimeFromPath(string $path): string
    {
        return match (strtolower(pathinfo($path, PATHINFO_EXTENSION))) {
            'png'         => 'image/png',
            'webp'        => 'image/webp',
            'gif'         => 'image/gif',
            'jpg', 'jpeg' => 'image/jpeg',
            default       => 'image/jpeg',
        };
    }

    /**
     * Pulls the workspace's most-used saved replies (capped at 15) and
     * formats them as a "canned responses" block for the AI's system
     * prompt. The LLM is told to USE them when a customer question is
     * close — keeps the agent on-brand without hand-coding every FAQ
     * into the system prompt.
     */
    private function cannedRepliesBlock(int $workspaceId): string
    {
        if ($workspaceId === 0) return '';
        $replies = \App\Models\SavedReply::query()
            ->forWorkspace($workspaceId)
            ->whereNull('user_id') // only workspace-wide (not personal)
            ->orderByDesc('used_count')
            ->orderBy('id')
            ->limit(15)
            ->get(['title', 'shortcut', 'body']);
        if ($replies->isEmpty()) return '';

        $lines = $replies->map(function ($r) {
            $title = trim((string) $r->title);
            $body  = trim((string) $r->body);
            // Keep each entry compact — title as label, body as the
            // actual response text.
            return '- ' . $title . ' → "' . mb_substr($body, 0, 280) . '"';
        })->implode("\n");

        return "Canned responses available for this workspace. When a customer's question matches one of these scenarios, reply with the response text below (you may lightly adapt for tone, but keep the meaning). Otherwise, answer freely.\n\n" . $lines;
    }

    /**
     * Direct LLM call — used for test-agent endpoint and by generateReply().
     * Workspace key takes priority over env fallback.
     */
    public function callProvider(
        string $provider,
        string $model,
        int $workspaceId,
        string $systemPrompt,
        string $userPrompt,
        int $maxTokens = 512,
        float $temperature = 0.7,
        ?array $image = null,   // {mime, b64} — optional vision attachment
        bool $jsonMode = false, // force a strict JSON object reply (extraction)
    ): ?string {
        // Unified resolver: workspace BYOK (if plan allows) → admin's
        // active global key from admin_ai_keys. No env fallback — admin
        // is the single source of truth, otherwise a stale .env key could
        // silently override a deliberately deactivated admin row.
        $workspace = $workspaceId > 0 ? \App\Models\Workspace::find($workspaceId) : null;
        $apiKey = \App\Services\AiKeyResolver::keyFor($workspace, $provider);

        if (!$apiKey) {
            Log::warning("[AI-AGENT] No API key for provider={$provider} workspace={$workspaceId}");
            return null;
        }

        // Platform hard ceiling on output tokens per request — admin sets it on
        // /admin/api-keys (extra_config.max_tokens) so a single AI call can never
        // burn more than this, regardless of what the assistant/node requested.
        // Blank/0 = no extra cap (use whatever was requested). Prevents runaway
        // token spend on top of the per-plan monthly cap (AiTokenMeter).
        try {
            $adminRow = \App\Models\AdminAiKey::where('provider', $provider)->first();
            $adminCap = (int) ($adminRow?->extra_config_array['max_tokens'] ?? 0);
            if ($adminCap > 0 && $maxTokens > $adminCap) {
                $maxTokens = $adminCap;
            }
        } catch (\Throwable $e) { /* never block a send on the cap lookup */ }

        try {
            $reply = match ($provider) {
                'openai'    => $this->callOpenAI($apiKey, $model, $systemPrompt, $userPrompt, $maxTokens, $temperature, $image, $jsonMode),
                'anthropic' => $this->callAnthropic($apiKey, $model, $systemPrompt, $userPrompt, $maxTokens, $temperature, $image, $jsonMode),
                'gemini'    => $this->callGemini($apiKey, $model, $systemPrompt, $userPrompt, $maxTokens, $temperature, $image, $jsonMode),
                'mistral'   => $this->callMistral($apiKey, $model, $systemPrompt, $userPrompt, $maxTokens, $temperature, $jsonMode),
                default     => null,
            };

            // Meter token usage so the AI dashboard + monthly cap see this call.
            // Counts are estimated (~4 chars/token) since the per-provider
            // helpers return only the reply text; billed_against follows whether
            // the resolved key was the workspace's own (BYOK) or a platform key.
            // Wrapped — metering must never block or fail a live reply.
            if ($reply !== null && $workspace) {
                try {
                    $byokKey = \App\Models\AiProviderKey::keyFor($workspaceId, $provider);
                    $billed  = ($byokKey && $byokKey === $apiKey) ? 'workspace' : 'admin';
                    $promptT = (int) ceil((mb_strlen($systemPrompt) + mb_strlen($userPrompt)) / 4) + ($image ? 700 : 0);
                    $compT   = (int) ceil(mb_strlen((string) $reply) / 4);
                    \App\Services\AiTokenMeter::record($workspace, $provider, $model, $promptT, $compT, $billed);
                } catch (\Throwable $e) { /* metering is best-effort */ }
            }

            return $reply;
        } catch (\Throwable $e) {
            Log::error("[AI-AGENT] provider={$provider} model={$model} error: " . $e->getMessage());
            return null;
        }
    }

    private function envKey(string $provider): ?string
    {
        return match ($provider) {
            'openai'    => env('OPENAI_API_KEY')    ?: null,
            'anthropic' => env('ANTHROPIC_API_KEY') ?: null,
            'gemini'    => env('GEMINI_API_KEY')    ?: null,
            'mistral'   => env('MISTRAL_API_KEY')   ?: null,
            default     => null,
        };
    }

    /**
     * Mistral chat completions — OpenAI-compatible JSON API
     * (https://api.mistral.ai/v1/chat/completions). Bearer auth, same
     * messages shape, supports response_format:json_object on the large
     * models for our structured-extraction (order parse / farm record)
     * flows. No vision here — Mistral's image support differs, and our
     * commerce/extraction flows are text-only.
     */
    private function callMistral(string $key, string $model, string $system, string $user, int $maxTokens, float $temp, bool $jsonMode = false): ?string
    {
        $payload = [
            'model'       => $model,
            'messages'    => [
                ['role' => 'system', 'content' => $system],
                ['role' => 'user',   'content' => $user],
            ],
            'max_tokens'  => $maxTokens,
            'temperature' => $temp,
        ];
        if ($jsonMode) {
            $payload['response_format'] = ['type' => 'json_object'];
        }

        $res = Http::withToken($key)
            ->acceptJson()
            ->timeout(30)
            ->post('https://api.mistral.ai/v1/chat/completions', $payload);
        if ($res->ok()) {
            return trim((string) ($res->json('choices.0.message.content') ?? '')) ?: null;
        }
        Log::warning('[AI-AGENT] Mistral non-200', ['status' => $res->status(), 'body' => substr($res->body(), 0, 300)]);
        return null;
    }

    private function callOpenAI(string $key, string $model, string $system, string $user, int $maxTokens, float $temp, ?array $image = null, bool $jsonMode = false): ?string
    {
        // Multimodal content array when an image is attached, else the
        // plain string the text path always sent (Chat Completions vision).
        $userContent = $image
            ? [
                ['type' => 'text',      'text' => $user],
                ['type' => 'image_url', 'image_url' => ['url' => 'data:' . $image['mime'] . ';base64,' . $image['b64']]],
              ]
            : $user;
        // OpenAI parameter compatibility (verified against the OpenAI API docs,
        // 2026-06): GPT-5+ and the o-series reasoning models REJECT `max_tokens`
        // in Chat Completions — they require `max_completion_tokens`. The older
        // gpt-4.x / gpt-4o line still takes the classic `max_tokens`. Because the
        // default OpenAI model is a GPT-5 model, sending `max_tokens` 400s every
        // call, so the token-limit field must be chosen per model.
        $m   = strtolower($model);
        $new = (bool) preg_match('/^(gpt-5|gpt-6|o[1-9])/', $m);
        // GPT-5 / GPT-6 / o-series ALSO reject a CUSTOM `temperature` — they
        // accept ONLY the default (1). This is NOT limited to the o-series
        // reasoning models: the gpt-5.x chat models (e.g. gpt-5.4-mini) 400 with
        // "'temperature' does not support 0.7 with this model. Only the default
        // (1) value is supported." So omit `temperature` for the whole `$new`
        // family and let the model default to 1; only the classic gpt-4.x /
        // gpt-4o line accepts a custom temperature. (Silent no-reply otherwise.)

        $payload = [
            'model'    => $model,
            'messages' => [
                ['role' => 'system', 'content' => $system],
                ['role' => 'user',   'content' => $userContent],
            ],
        ];
        $payload[$new ? 'max_completion_tokens' : 'max_tokens'] = $maxTokens;
        if (!$new) {
            $payload['temperature'] = $temp;
        }
        // Structured-extraction mode — force a strict JSON object response
        // (gpt-4o / gpt-4.1 / gpt-5 all support json_object). The caller's
        // prompt already instructs which keys to emit.
        if ($jsonMode) {
            $payload['response_format'] = ['type' => 'json_object'];
        }

        $res = Http::withToken($key)
            ->timeout($image ? 60 : 30)
            ->post('https://api.openai.com/v1/chat/completions', $payload);
        if ($res->ok()) {
            return trim((string) ($res->json('choices.0.message.content') ?? '')) ?: null;
        }
        Log::warning('[AI-AGENT] OpenAI non-200', ['status' => $res->status(), 'body' => substr($res->body(), 0, 300)]);
        return null;
    }

    private function callAnthropic(string $key, string $model, string $system, string $user, int $maxTokens, float $temp, ?array $image = null, bool $jsonMode = false): ?string
    {
        // Anthropic vision: content blocks array with a base64 image source.
        $content = $image
            ? [
                ['type' => 'text',  'text' => $user],
                ['type' => 'image', 'source' => ['type' => 'base64', 'media_type' => $image['mime'], 'data' => $image['b64']]],
              ]
            : $user;
        $messages = [['role' => 'user', 'content' => $content]];
        // Structured-extraction mode — Anthropic has no json_object flag. We used
        // to PREFILL the assistant turn with "{" to force a JSON continuation, but
        // last-assistant-turn prefills now return HTTP 400 on EVERY current Claude
        // model except Haiku (Sonnet 4.6, Opus 4.6/4.7/4.8 and Fable 5 all reject
        // them). That 400 was silently swallowed and surfaced to users as "AI
        // provider returned no content" — i.e. Claude appeared to "only work on
        // Haiku". Instead we instruct JSON-only via the system prompt, which every
        // model honours, and strip any stray code fence from the reply below.
        if ($jsonMode) {
            $system = trim($system)
                . "\n\nIMPORTANT: Respond with ONLY a single valid JSON object. "
                . "No prose, no explanation, no markdown code fences.";
        }
        $res = Http::withHeaders([
            'x-api-key'         => $key,
            'anthropic-version' => '2023-06-01',
        ])->timeout($image ? 60 : 30)->post('https://api.anthropic.com/v1/messages', [
            'model'      => $model,
            'max_tokens' => $maxTokens,
            'system'     => $system,
            'messages'   => $messages,
        ]);
        if ($res->ok()) {
            $text = trim((string) ($res->json('content.0.text') ?? ''));
            if ($jsonMode && $text !== '') {
                // Some models still wrap the object in ```json ... ``` despite the
                // instruction — strip a leading/trailing fence so callers can decode.
                $text = trim(preg_replace('/^```(?:json)?\s*|\s*```$/i', '', $text));
            }
            return $text ?: null;
        }
        // Log the actual error BODY (bad model id, unsupported param, billing,
        // etc.) — not just the status — so "no content" is diagnosable.
        Log::warning('[AI-AGENT] Anthropic non-200', [
            'status' => $res->status(),
            'model'  => $model,
            'body'   => mb_substr($res->body(), 0, 800),
        ]);
        return null;
    }

    private function callGemini(string $key, string $model, string $system, string $user, int $maxTokens, float $temp, ?array $image = null, bool $jsonMode = false): ?string
    {
        $prompt = $system . "\n\n" . $user;
        // Gemini vision: an extra inline_data part alongside the text part.
        $parts = [['text' => $prompt]];
        if ($image) {
            $parts[] = ['inline_data' => ['mime_type' => $image['mime'], 'data' => $image['b64']]];
        }
        $generationConfig = ['maxOutputTokens' => $maxTokens, 'temperature' => $temp];
        // Structured-extraction mode — Gemini's native JSON output.
        if ($jsonMode) {
            $generationConfig['responseMimeType'] = 'application/json';
        }
        $res = Http::timeout($image ? 60 : 30)->post(
            "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$key}",
            [
                'contents'         => [['parts' => $parts]],
                'generationConfig' => $generationConfig,
            ]
        );
        if ($res->ok()) {
            return trim((string) ($res->json('candidates.0.content.parts.0.text') ?? '')) ?: null;
        }
        Log::warning('[AI-AGENT] Gemini non-200', ['status' => $res->status()]);
        return null;
    }

    // ----------------------------------------------------------------
    // Handoff — stop AI looping, call in a human.
    // ----------------------------------------------------------------

    /**
     * Returns a short string reason if the AI should NOT respond and the
     * conversation should be handed off to a human, or null to keep going.
     *
     * Three triggers:
     *   - "reply_limit"  → AI has replied >= max_replies_per_conversation times
     *   - "keyword"      → customer's last message contains a handoff keyword
     *   - "low_quality"  → last N self-rated replies all scored <= threshold
     */
    public function shouldHandoff(AiAgent $agent, Conversation $convo): ?string
    {
        $maxReplies = (int) ($agent->max_replies_per_conversation ?? 10);
        if ($maxReplies > 0) {
            $aiReplies = InboxMessage::query()
                ->where('conversation_id', $convo->id)
                ->where('agent_id', $agent->id)
                ->count();
            if ($aiReplies >= $maxReplies) {
                Log::info('[AI-AGENT] handoff: reply_limit', [
                    'agent_id' => $agent->id, 'conv_id' => $convo->id,
                    'replies'  => $aiReplies, 'max' => $maxReplies,
                ]);
                return 'reply_limit';
            }
        }

        $keywords = is_array($agent->handoff_keywords) && !empty($agent->handoff_keywords)
            ? $agent->handoff_keywords
            : ['human', 'real person', 'speak to someone', 'agent', 'representative', 'manager', 'support team'];

        $lastInbound = InboxMessage::query()
            ->where('conversation_id', $convo->id)
            ->where('direction', 'in')
            ->orderByDesc('id')
            ->value('body');
        if ($lastInbound) {
            $lc = mb_strtolower($lastInbound);
            foreach ($keywords as $kw) {
                $needle = mb_strtolower((string) $kw);
                if ($needle !== '' && str_contains($lc, $needle)) {
                    Log::info('[AI-AGENT] handoff: keyword', [
                        'agent_id' => $agent->id, 'conv_id' => $convo->id, 'kw' => $kw,
                    ]);
                    return 'keyword:' . $kw;
                }
            }
        }

        $lowScoreThreshold = (int) ($agent->handoff_low_score_threshold ?? 0);
        $window            = max(1, (int) ($agent->handoff_low_score_window ?? 3));
        if ($lowScoreThreshold > 0) {
            $recent = InboxMessage::query()
                ->where('conversation_id', $convo->id)
                ->where('agent_id', $agent->id)
                ->whereNotNull('quality_score')
                ->orderByDesc('id')
                ->limit($window)
                ->pluck('quality_score')
                ->all();
            if (count($recent) >= $window) {
                $allLow = true;
                foreach ($recent as $s) {
                    if ((int) $s > $lowScoreThreshold) { $allLow = false; break; }
                }
                if ($allLow) {
                    Log::info('[AI-AGENT] handoff: low_quality', [
                        'agent_id' => $agent->id, 'conv_id' => $convo->id,
                        'recent'   => $recent, 'threshold' => $lowScoreThreshold,
                    ]);
                    return 'low_quality';
                }
            }
        }

        return null;
    }

    /**
     * Hand a conversation back to humans:
     *   - clear assignee_agent_id (AI stops responding)
     *   - bump priority to high so it surfaces in the queue
     *   - tag "Needs human" so it shows up in filtered views
     *   - notify all online workspace members (so someone picks it up)
     *   - record a ConversationEvent for the audit log
     */
    public function triggerHandoff(AiAgent $agent, Conversation $convo, string $reason): void
    {
        // Clear the AI assignment + bump priority.
        $convo->forceFill([
            'assignee_agent_id' => null,
            'priority'          => 'high',
        ])->save();

        // Tag the conversation so it's filterable as "needs human".
        try {
            $tag = \App\Models\Tag::firstOrCreate(
                ['workspace_id' => $convo->workspace_id, 'slug' => 'needs-human'],
                ['name' => 'Needs human', 'color' => '#A1431F'],
            );
            $convo->tags()->syncWithoutDetaching([$tag->id]);
        } catch (\Throwable $e) {
            Log::warning('[AI-AGENT] handoff tag failed: ' . $e->getMessage());
        }

        // Audit log — operator sees this in the conversation timeline.
        try {
            \App\Models\ConversationEvent::record(
                $convo->id, $convo->workspace_id, null,
                'ai_handoff',
                ['agent_id' => $agent->id, 'agent_name' => $agent->name, 'reason' => $reason],
                'rule'
            );
        } catch (\Throwable $e) {
            Log::warning('[AI-AGENT] handoff event failed: ' . $e->getMessage());
        }

        // Notify all workspace members (operators) so someone picks it up.
        try {
            $userIds = \DB::table('workspace_user')
                ->where('workspace_id', $convo->workspace_id)
                ->whereIn('role', ['owner', 'admin', 'manager', 'agent'])
                ->pluck('user_id');
            $reasonLabel = match (true) {
                $reason === 'reply_limit'    => 'AI hit reply limit',
                $reason === 'low_quality'    => 'AI replies are getting worse',
                $reason === 'out_of_credits' => 'Workspace ran out of credits',
                str_starts_with($reason, 'keyword:') => 'Customer asked for a human ("' . substr($reason, 8) . '")',
                default                      => 'AI requested human takeover',
            };
            foreach ($userIds as $uid) {
                \App\Models\InboxNotification::create([
                    'user_id'         => $uid,
                    'workspace_id'    => $convo->workspace_id,
                    'conversation_id' => $convo->id,
                    'type'            => 'ai_handoff',
                    'severity'        => 'warning',
                    'title'           => 'AI handed off — please take over',
                    'message'         => $reasonLabel . '. Conversation #' . $convo->id . ' needs a human.',
                    'action_url'      => '/team-inbox',
                ]);
            }
        } catch (\Throwable $e) {
            Log::warning('[AI-AGENT] handoff notify failed: ' . $e->getMessage());
        }

        Log::info('[AI-AGENT] ✓ handoff complete', [
            'agent_id' => $agent->id, 'conv_id' => $convo->id, 'reason' => $reason,
        ]);
    }
}
