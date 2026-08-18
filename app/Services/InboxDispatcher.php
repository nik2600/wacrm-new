<?php

namespace App\Services;

use App\Enums\WaProvider;
use App\Models\InboxMessage;
use App\Models\SystemSetting;
use App\Models\WaProviderConfig;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Dedicated dispatcher for team-inbox bubbles (`inbox_messages` table).
 * Separate from WhatsAppDispatcher so the inbox surface and the
 * campaign / broadcast surface can evolve independently without
 * stepping on each other.
 *
 * What this handles:
 *   - send($msg)               — text or media outbound (Baileys/WABA/Twilio)
 *   - reaction($msg, $emoji)   — emoji reaction on a bubble
 *   - pin($msg, $pin, $time)   — pin / unpin (Baileys only)
 *   - star($msg, $star)        — star / unstar (Baileys only)
 *
 * What this DOESN'T do (use WhatsAppDispatcher for those):
 *   - scheduled sends (campaigns, broadcasts, drips)
 *   - bulk campaign delivery
 *
 * Internally calls the same Node bridge endpoints as the legacy
 * dispatcher — no Node-side duplication required.
 */
class InboxDispatcher
{
    public function send(InboxMessage $msg, string $platform = 'W'): array
    {
        // Platform-wide emergency halt gate — admin can pause ALL outbound
        // sends from /admin/security → Danger zone. Reads the cached
        // platform.emergency_send_halt flag (one DB read per request).
        if (\App\Support\SendGate::halted()) {
            Log::warning('[INBOX-DISPATCH] refused — emergency halt engaged', [
                'msg_id'    => $msg->id,
                'to_number' => $msg->to_number,
            ]);
            return [
                'ok'        => false,
                'platform'  => 'halted',
                'local_only'=> true,
                'error'     => 'Platform emergency halt is engaged. Resume sends from /admin/security → Danger zone.',
            ];
        }

        // WhatsApp guardrails (admin's /admin/security rate caps + content
        // filters). No-op unless mode is monitor/enforce; monitor only logs.
        // Fail-open. Workspace id resolves via the conversation (InboxMessage
        // has no direct workspace_id column).
        try {
            $wsId = (int) \DB::table('conversations')->where('id', $msg->conversation_id)->value('workspace_id');
            \App\Support\SendGate::screen($wsId ?: null, (string) $msg->body, ['msg_id' => $msg->id, 'to' => $msg->to_number]);
        } catch (\Throwable $e) {
            Log::warning('[INBOX-DISPATCH] refused by security guardrail', ['msg_id' => $msg->id, 'reason' => $e->getMessage()]);
            return [
                'ok'         => false,
                'platform'   => 'blocked',
                'local_only' => true,
                'error'      => $e->getMessage(),
            ];
        }

        // Plan limit — block outbound sends if the workspace has hit
        // its monthly_messages_limit. Counts messages already dispatched
        // this calendar month for this workspace (both inbox replies
        // and campaign/broadcast/flow sends — anything that landed an
        // outbound row).
        $this->guardMonthlyMessagesLimit($msg);

        // Central recipient backfill. Some producers mint an OUTBOUND row without
        // to_number (e.g. AI voice replies via VoiceOutboundDispatcher). Every
        // engine path below — Baileys buildNodeRequest, WABA, Twilio — reads
        // $msg->to_number with NO fallback, so a missing value silently ships to
        // a null recipient. Resolve it ONCE here (before provider dispatch) so
        // ALL engines + producers are protected uniformly. No-op when to_number
        // is already set → the team-inbox reply path stays exactly as-is.
        $this->ensureRecipient($msg);

        $resolved = $this->resolveProvider($msg, $platform);
        Log::info('[INBOX-DISPATCH] entry', [
            'msg_id'      => $msg->id,
            'user_id'     => $msg->user_id,
            'from_number' => $msg->from_number,
            'to_number'   => $msg->to_number,
            'platform'    => $platform,
            'resolved'    => $resolved->value,
        ]);

        // Stamp the resolved engine on the message row so analytics +
        // /devices KPI cards can filter by `provider` directly instead
        // of joining through device→workspace→engine. Idempotent — only
        // sets it if currently null.
        if (empty($msg->provider) && \Illuminate\Support\Facades\Schema::hasColumn('inbox_messages', 'provider')) {
            try {
                $msg->forceFill(['provider' => $resolved->value])->saveQuietly();
            } catch (\Throwable $e) {
                Log::debug('[INBOX-DISPATCH] provider stamp failed: ' . $e->getMessage());
            }
        }

        // Plan-gated outbound footer. Operator-typed replies + their
        // attached media captions get the footer appended in-place
        // before transport dispatch — keeps WABA, Baileys, and Twilio
        // identical without changing each transport's payload builder.
        // Templates from the inbox composer's template picker have
        // their own template_id flow and never reach this method.
        //
        // Real-time translation FIRST — translate an operator-typed reply into
        // the customer's pinned language before the footer + transport. No-op
        // when translation is off, no language is pinned, or the body is already
        // in the customer's language (AI/keyword replies generated natively in
        // it). Mutates $msg->body in-memory; the agent's original is preserved
        // in translated_body for the thread view.
        try {
            app(\App\Services\Inbox\ConversationTranslationService::class)->translateOutbound($msg);
        } catch (\Throwable $e) { /* never block a send */ }

        $this->applyBrandingFooter($msg);

        // Engine-agnostic channels (the embedded chat widget) have NO WhatsApp
        // send engine — their outbound reply is delivered by the widget polling
        // /history for direction='out' rows (see ChatbotWidgetPublicController).
        // Without this, resolveProvider() falls through to Baileys, dispatchNode()
        // finds no paired device, and the operator's reply is marked FAILED with
        // "No connected device — pair one at /devices first." Short-circuit to a
        // clean local success: the reply row is already stored, so the widget
        // picks it up on its next poll and the thread shows it as sent.
        $convChannel = (string) \DB::table('conversations')->where('id', $msg->conversation_id)->value('channel');

        // Instagram threads are delivered by the SEPARATE Instaflow deployment,
        // not a WhatsApp engine. Route the operator's reply through Instaflow's
        // /api/wadesk reply endpoint (it performs the Graph send + returns the IG
        // message id). This MUST be checked BEFORE the engine-agnostic
        // short-circuit below: 'instagram' is in ENGINE_AGNOSTIC_CHANNELS (so IG
        // threads always show in the inbox regardless of the WA engine filter),
        // but unlike the chat widget IG genuinely HAS an outbound channel, so it
        // must not fall into the "local delivery, no send" path.
        if ($convChannel === 'instagram' || $msg->provider === 'instagram') {
            return $this->dispatchInstaflow($msg);
        }

        if (in_array($convChannel, \App\Models\Conversation::ENGINE_AGNOSTIC_CHANNELS, true)) {
            Log::info('[INBOX-DISPATCH] engine-agnostic channel — local widget delivery (no WhatsApp send)', [
                'msg_id' => $msg->id, 'channel' => $convChannel,
            ]);
            return ['ok' => true, 'platform' => 'WIDGET', 'provider_id' => null, 'local_only' => false, 'error' => null];
        }

        // Align the in-memory provider to the engine we ACTUALLY resolved JUST
        // for the duration of the dispatch — dispatchNode() reads $msg->provider
        // to tell Node which channel to use, and resolveProvider() may have
        // fallen THROUGH a disabled/disconnected pin (msg.provider or the
        // conversation.provider reply-on-same-channel fallback) to a different
        // engine. We RESTORE it immediately after: the attribute is backed by a
        // persisted column and inbox callers flush the model after dispatch
        // (TeamInboxController update()), so leaving it dirty would overwrite the
        // operator's stored pin with the fallback engine + corrupt analytics.
        $providerBeforeDispatch = $msg->provider;
        $msg->provider = $resolved->value;

        try {
            // Per-engine transport:
            //   - WABA  → Meta Graph API DIRECT from PHP (workspace token +
            //     phone_number_id from wa_provider_configs) — same path
            //     /chat, broadcasts + templates already use. No Node bridge
            //     needed, so a WABA-only workspace works even without a
            //     reachable Node server. (Routing WABA through Node caused
            //     404s when server_url didn't resolve to the Node process.)
            //   - Baileys → Node bridge (the sock lives there).
            //   - Twilio  → Twilio REST direct from PHP.
            $result = match ($resolved) {
                WaProvider::Twilio  => $this->dispatchTwilio($msg),
                WaProvider::Waba    => $this->dispatchMetaCloud($msg),
                default             => $this->dispatchNode($msg),  // baileys
            };
        } finally {
            // Restore the original (DB-synced) provider so no later save()
            // persists the transient alignment over the record's stored pin —
            // in finally so the restore holds even if a future dispatch refactor
            // lets an exception escape.
            $msg->provider = $providerBeforeDispatch;
        }

        Log::info('[INBOX-DISPATCH] result', [
            'msg_id'      => $msg->id,
            'ok'          => $result['ok'] ?? null,
            'platform'    => $result['platform'] ?? null,
            'provider_id' => $result['provider_id'] ?? null,
            'local_only'  => $result['local_only'] ?? null,
            'error'       => $result['error'] ?? null,
        ]);
        return $result;
    }

    public function reaction(InboxMessage $msg, string $emoji): array
    {
        $platform = optional($msg->conversation)->platform ?? 'W';
        $resolved = $this->resolveProvider($msg, $platform);
        // WhatsApp Cloud API (WABA) supports reaction messages natively — push
        // the emoji to Meta so the customer actually sees it (was local-only).
        if ($resolved === WaProvider::Waba) {
            return $this->wabaReaction($msg, $emoji);
        }
        if ($resolved !== WaProvider::Baileys) {
            // Twilio WhatsApp has no reaction API — keep it local-only.
            return ['ok' => true, 'platform' => $resolved->value, 'local_only' => true, 'error' => null];
        }
        [$serverUrl, $from] = $this->resolveDevicePhone($msg);
        if ($serverUrl === '' || $from === '') {
            return ['ok' => false, 'error' => 'No connected device or server url'];
        }

        $meta        = is_array($msg->meta) ? $msg->meta : [];
        $waMessageId = (string) ($meta['wa_message_id'] ?? '');
        $targetJid   = (string) ($meta['target_jid']    ?? '');
        $isInbound   = $msg->direction === 'in';
        $reactTo     = $isInbound ? ($msg->from_number ?: null) : $msg->to_number;

        $fromEnc = rawurlencode(preg_replace('/\D+/', '', $from) ?: $from);
        $url = rtrim($serverUrl, '/') . '/api/send-reaction/' . $fromEnc;

        try {
            $res = Http::timeout(10)->acceptJson()->asJson()->post($url, [
                'targetPhoneNumber' => $reactTo,
                'targetJid'         => $targetJid !== '' ? $targetJid : null,
                'targetMessageId'   => $waMessageId !== '' ? $waMessageId : null,
                'fromMe'            => !$isInbound,
                'emoji'             => $emoji,
            ]);
            return ['ok' => $res->successful(), 'error' => $res->successful() ? null : $res->body()];
        } catch (\Throwable $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Resolve WHICH WABA number this message should send FROM.
     *
     * A workspace can have several WABA numbers connected. A reply MUST
     * leave on the SAME number the customer messaged — not the workspace
     * "primary". The conversation stores that number's wa_provider_configs
     * id in `device_id` (device unification), so prefer it. Fall back to
     * the primary / most-recently-connected WABA config so single-number
     * workspaces and rows with no conversation behave exactly as before.
     */
    private function resolveWabaConfig(int $workspaceId, ?InboxMessage $msg = null): ?WaProviderConfig
    {
        if ($workspaceId <= 0) return null;

        // 1) The conversation's OWN WABA number — the fix for multi-WABA
        //    workspaces where the reply was going out from the wrong number.
        $conv = $msg?->conversation;
        if ($conv && (string) $conv->provider === 'waba' && $conv->device_id) {
            $own = WaProviderConfig::query()
                ->where('workspace_id', $workspaceId)
                ->where('provider', 'waba')
                ->whereKey($conv->device_id)
                ->first();
            if ($own) return $own;
        }

        // 2) Fallback — unchanged from the original resolution order.
        return WaProviderConfig::query()->primaryForWorkspace($workspaceId)->first()
            ?? WaProviderConfig::query()->where('workspace_id', $workspaceId)
                ->where('provider', 'waba')->orderByDesc('connected_at')->first();
    }

    /** Resolve this workspace's WABA [access_token, phone_number_id, graph_version]. */
    private function wabaCreds(int $workspaceId, ?InboxMessage $msg = null): array
    {
        $token = ''; $phoneId = '';
        $cfg = $this->resolveWabaConfig($workspaceId, $msg);
        if ($cfg) {
            $creds   = $cfg->creds();
            $meta    = is_array($cfg->meta_json) ? $cfg->meta_json : [];
            $token   = (string) ($creds['access_token'] ?? '');
            $phoneId = (string) ($meta['phone_number_id'] ?? '');
        }
        $version = (string) \App\Models\SystemSetting::get('waba_graph_api_version', 'v23.0');
        return [$token, $phoneId, $version];
    }

    /**
     * Send an emoji reaction (or clear it with an empty emoji) via the WhatsApp
     * Cloud API — POST /{phone_number_id}/messages with type=reaction, targeting
     * the customer's wamid. Requires the reacted message to carry meta.wa_message_id.
     */
    private function wabaReaction(InboxMessage $msg, string $emoji): array
    {
        $workspaceId = (int) (optional($msg->conversation)->workspace_id ?? 0);
        // Pass $msg so the reaction leaves on the conversation's OWN WABA
        // number (not the workspace primary) on multi-WABA workspaces.
        [$token, $phoneId, $version] = $this->wabaCreds($workspaceId, $msg);
        if ($token === '' || $phoneId === '') {
            return ['ok' => false, 'platform' => 'waba', 'error' => 'No WABA config for this workspace — connect a Meta number at /devices.'];
        }
        $meta  = is_array($msg->meta) ? $msg->meta : [];
        $wamid = (string) ($meta['wa_message_id'] ?? '');
        if ($wamid === '') {
            return ['ok' => false, 'platform' => 'waba', 'error' => 'This message has no WhatsApp id to react to yet.'];
        }
        // Reaction is sent TO the customer (the other party in the conversation).
        $to = preg_replace('/\D+/', '', (string) ($msg->direction === 'in' ? $msg->from_number : $msg->to_number));
        if ($to === '') {
            return ['ok' => false, 'platform' => 'waba', 'error' => 'No recipient number on this conversation.'];
        }
        try {
            $res = Http::withToken($token)->acceptJson()->timeout(20)->retry(2, 400, null, false)
                ->post("https://graph.facebook.com/{$version}/{$phoneId}/messages", [
                    'messaging_product' => 'whatsapp',
                    'recipient_type'    => 'individual',
                    'to'                => $to,
                    'type'              => 'reaction',
                    'reaction'          => ['message_id' => $wamid, 'emoji' => $emoji],
                ]);
            return ['ok' => $res->successful(), 'platform' => 'waba', 'error' => $res->successful() ? null : $res->body()];
        } catch (\Throwable $e) {
            return ['ok' => false, 'platform' => 'waba', 'error' => $e->getMessage()];
        }
    }

    public function pin(InboxMessage $msg, bool $pin = true, int $durationSeconds = 86400): array
    {
        return $this->messageAction($msg, '/api/pin-message/', [
            'pin'      => $pin,
            'duration' => $durationSeconds,
        ], 'INBOX-PIN');
    }

    public function star(InboxMessage $msg, bool $star = true): array
    {
        return $this->messageAction($msg, '/api/star-message/', [
            'star' => $star,
        ], 'INBOX-STAR');
    }

    /**
     * Delete-for-everyone (revoke). Per itsukichan/baileys README §
     * "Deleting Messages (for everyone)":
     *
     *   await sock.sendMessage(jid, { delete: msg.key })
     *
     * which surfaces a "This message was deleted" placeholder on the
     * recipient's WhatsApp. We POST the same shape to the Node bridge
     * which builds the {id, remoteJid, fromMe} key from the args.
     */
    public function deleteForEveryone(InboxMessage $msg): array
    {
        return $this->messageAction($msg, '/api/delete-message/', [
            // no extra fields — messageAction already passes
            // targetMessageId, targetJid, fromMe. Node does the rest.
        ], 'INBOX-DELETE');
    }

    /**
     * Edit-for-everyone. Per itsukichan/baileys README § "Editing Messages":
     *
     *   await sock.sendMessage(jid, { text: 'updated text', edit: msg.key })
     *
     * The recipient's WhatsApp shows the new text with an "Edited" tag.
     * WhatsApp enforces a 15-minute window on their end; the caller is
     * expected to validate the window before invoking this.
     */
    public function editForEveryone(InboxMessage $msg, string $newText): array
    {
        return $this->messageAction($msg, '/api/edit-message/', [
            'newText' => $newText,
        ], 'INBOX-EDIT');
    }

    // -----------------------------------------------------------------
    // Internals — provider routing + per-provider builders.
    // -----------------------------------------------------------------

    private function resolveProvider(InboxMessage $msg, string $platformHint): WaProvider
    {
        $workspaceId = optional($msg->conversation)->workspace_id;
        if (!$workspaceId && $msg->conversation_id) {
            $workspaceId = \App\Models\Conversation::query()->whereKey($msg->conversation_id)->value('workspace_id');
        }
        if (!$workspaceId && $msg->user_id) {
            $workspaceId = \App\Models\User::query()->whereKey($msg->user_id)->value('current_workspace_id');
        }
        if (!$workspaceId) {
            $workspaceId = optional(auth()->user())->current_workspace_id;
        }

        $allowed = SystemSetting::get('allowed_send_methods', ['baileys', 'waba', 'twilio']);
        $allowed = is_array($allowed) ? $allowed : [$allowed];

        // Phase 4 — per-record provider pin WINS. If Phase 3 already stamped
        // inbox_messages.provider, honour it (admin-allowed + actually enabled
        // on this workspace) so a multi-engine workspace's reply leaves on the
        // exact engine the operator picked rather than the workspace default.
        if (!empty($msg->provider)) {
            $pinned = WaProvider::tryFrom((string) $msg->provider);
            if ($pinned
                && in_array($pinned->value, $allowed, true)
                && \App\Services\WorkspaceEngine::isEngineEnabled($workspaceId, $pinned->value)
            ) {
                return $pinned;
            }
        }

        // Reply-on-same-channel — when this message has no explicit provider,
        // an inbox reply should still leave on the CONVERSATION'S channel
        // (the engine the inbound arrived on), which Phase 3 stamped into
        // conversations.provider. Prefer that over the workspace default.
        // Legacy conversations have an empty provider => skipped, so
        // single-engine inboxes fall through unchanged.
        $convProvider = optional($msg->conversation)->provider;
        if (empty($convProvider) && $msg->conversation_id) {
            $convProvider = \App\Models\Conversation::query()->whereKey($msg->conversation_id)->value('provider');
        }
        if (!empty($convProvider)) {
            $convPinned = WaProvider::tryFrom((string) $convProvider);
            if ($convPinned
                && in_array($convPinned->value, $allowed, true)
                && \App\Services\WorkspaceEngine::isEngineEnabled($workspaceId, $convPinned->value)
            ) {
                return $convPinned;
            }
        }

        if ($workspaceId) {
            $cfg = WaProviderConfig::query()->primaryForWorkspace($workspaceId)->first();
            if ($cfg && $cfg->isConnected() && in_array($cfg->provider, $allowed, true)) {
                return $cfg->providerEnum();
            }
        }
        if ($platformHint !== '' && strtoupper($platformHint) !== 'W') {
            $hinted = WaProvider::fromLegacyCode($platformHint);
            if (in_array($hinted->value, $allowed, true)) return $hinted;
        }
        $adminDefault = SystemSetting::get('default_send_method', 'baileys');
        $resolved = WaProvider::tryFrom((string) $adminDefault) ?? WaProvider::Baileys;
        if (!in_array($resolved->value, $allowed, true) && !empty($allowed)) {
            $resolved = WaProvider::tryFrom((string) $allowed[0]) ?? WaProvider::Baileys;
        }
        return $resolved;
    }

    /**
     * Returns [$serverUrl, $devicePhone]. Picks the device the conversation
     * belongs to (NOT the customer phone — that would crash Node, whose
     * clients dict is keyed by device phone).
     */
    private function resolveDevicePhone(InboxMessage $msg): array
    {
        $serverUrl = '';
        $from = '';
        $workspaceId = $msg->user_id ? \App\Models\User::query()->whereKey($msg->user_id)->value('current_workspace_id') : null;
        $cfg = null;
        if ($workspaceId) {
            $cfg = WaProviderConfig::query()->primaryForWorkspace($workspaceId)->first();
            if ($cfg) {
                $creds = $cfg->creds();
                $serverUrl = (string) ($creds['server_url'] ?? '');
                // NOTE: do NOT seed $from from the workspace PRIMARY config here.
                // On a multi-device / multi-engine workspace the primary is
                // often a DIFFERENT number (or engine, e.g. WABA) than the
                // device THIS conversation is paired to. Seeding it caused
                // Node to be told "Baileys send from the primary number",
                // find no ready socket for it, and return CLIENT NOT READY.
                // The conversation's own device (below) wins; the primary is
                // only a last-resort fallback.
            }
        }
        if ($serverUrl === '') $serverUrl = (string) (SystemSetting::get('baileys_server_url') ?: env('SERVER_URL', ''));

        // 1) Outbound: from_number IS the device this chat is paired to —
        //    set by TeamInboxController::reply / AiAgentService from the
        //    conversation's device. This MUST win so the send leaves on the
        //    same number the customer is talking to.
        if ($msg->direction === 'out' && $msg->from_number) {
            $from = preg_replace('/\D+/', '', (string) $msg->from_number) ?: '';
        }
        // 2) Else resolve via the conversation's device_id.
        if ($from === '') {
            $conv = $msg->conversation ?: ($msg->conversation_id
                ? \App\Models\Conversation::query()->find($msg->conversation_id)
                : null);
            if ($conv && $conv->device_id) {
                $device = \App\Models\Device::query()->find($conv->device_id);
                if ($device) $from = preg_replace('/\D+/', '', (string) ($device->country_code . $device->phone_number));
            }
        }
        // 3) Fallback: the workspace primary config phone (single-engine
        //    workspaces land here and behave exactly as before).
        if ($from === '' && $cfg) {
            $creds = $cfg->creds();
            $from = (string) ($cfg->phone_number ?: ($creds['phone_number'] ?? ''));
        }
        if ($from === '' && $msg->user_id) {
            // Queue/service context — no authed user, so we pass the
            // message's workspace_id explicitly. Falls back to user_id
            // for legacy NULL-workspace rows.
            $device = \App\Models\Device::query()->forWorkspace($msg->workspace_id ?? null, $msg->user_id)->where('status', 'connected')->orderByDesc('id')->first();
            if ($device) $from = preg_replace('/\D+/', '', (string) ($device->country_code . $device->phone_number));
        }
        return [$serverUrl, $from];
    }

    /**
     * Instagram outbound — delegate to the separate Instaflow deployment.
     *
     * IG conversations carry NO WhatsApp engine; Instaflow owns the Graph send.
     * We hand it the IG conversation id (stored as the thread's raw_jid,
     * namespaced "ig:<id>") plus the operator's text / media URL, and surface
     * its result in the dispatcher's standard shape so TeamInboxController marks
     * the reply row sent/failed exactly like a WhatsApp send.
     */
    private function dispatchInstaflow(InboxMessage $msg): array
    {
        $rawJid   = (string) \DB::table('conversations')->where('id', $msg->conversation_id)->value('raw_jid');
        $igConvId = \Illuminate\Support\Str::startsWith($rawJid, 'ig:') ? substr($rawJid, 3) : $rawJid;
        if ($igConvId === '') {
            return ['ok' => false, 'platform' => 'instagram', 'provider_id' => null, 'local_only' => true,
                'error' => 'This Instagram thread has no Instaflow conversation id.'];
        }

        $client = \App\Services\Instaflow\InstaflowClient::fromSettings();
        if (! $client->isConfigured()) {
            return ['ok' => false, 'platform' => 'instagram', 'provider_id' => null, 'local_only' => true,
                'error' => 'Instagram is not connected. Connect ' . ig_brand_name() . ' in Admin → Add-ons.'];
        }

        // Media replies ship a public URL for Instaflow to fetch (Graph needs a
        // reachable URL). Own-storage keys are resolved to their public URL; an
        // already-absolute URL is passed through untouched.
        $mediaUrl = null;
        $type     = 'text';
        if (! empty($msg->media_path)) {
            $mediaUrl = preg_match('#^https?://#i', (string) $msg->media_path)
                ? (string) $msg->media_path
                : media_url((string) $msg->media_path);
            $type = $msg->media_type ?: 'image';
        }

        // Template buttons — carry the WaTemplate's buttons (synced from Instaflow,
        // shape {type:URL|QUICK_REPLY, text, url}) through to Instagram. Without
        // this an IG template reply shipped as PLAIN TEXT and every button — both
        // quick-reply chips AND link buttons — was silently dropped. IG supports
        // EITHER quick-reply chips (≤13) OR a generic button card (web_url +
        // postback, ≤3), one shape per send:
        //   • any link/URL button  → generic button card (a chip can't carry a link)
        //   • only quick-replies   → quick-reply chips
        // A per-send override (reply() writes buttons[i].value) wins over the url.
        $qrArg = [];
        $buttonsArg = [];
        $metaArr = is_array($msg->meta) ? $msg->meta : [];
        if ($mediaUrl === null && ! empty($metaArr['buttons']) && is_array($metaArr['buttons'])) {
            $quickReplies = [];
            $genericButtons = [];
            $hasUrl = false;
            foreach ($metaArr['buttons'] as $b) {
                if (! is_array($b)) continue;
                $label = trim((string) ($b['text'] ?? $b['title'] ?? ''));
                if ($label === '') continue;
                // Effective URL: the per-send override (value) first, then the
                // template's own url. A URL/link button is one flagged type:URL
                // or carrying a non-empty url/value.
                $url = trim((string) ($b['value'] ?? $b['url'] ?? ''));
                $isUrl = ((string) ($b['type'] ?? '') === 'URL') || $url !== '';
                if ($isUrl && $url !== '') {
                    $genericButtons[] = ['type' => 'web_url', 'title' => $label, 'url' => $url];
                    $hasUrl = true;
                } else {
                    $quickReplies[]   = ['title' => $label, 'payload' => $label];
                    $genericButtons[] = ['type' => 'postback', 'title' => $label, 'payload' => $label];
                }
            }
            if ($hasUrl && $genericButtons) {
                $type       = 'buttons';
                $buttonsArg = array_slice($genericButtons, 0, 3);   // IG generic-template cap
            } elseif ($quickReplies) {
                // WaDesk just FORWARDS the intent — Instagram-side rendering
                // (chips vs. card) is decided in the Instaflow InstagramService.
                $type       = 'quick_replies';
                $qrArg      = array_slice($quickReplies, 0, 13);
            }
        }

        $res = $client->reply(
            $igConvId,
            $type,
            ((string) $msg->body) !== '' ? (string) $msg->body : null,
            $mediaUrl,
            $qrArg,
            $buttonsArg
        );
        $ok  = (bool) ($res['ok'] ?? false);
        Log::info('[INBOX-DISPATCH] instaflow reply', [
            'msg_id' => $msg->id, 'ig_conversation' => $igConvId, 'ok' => $ok, 'error' => $res['error'] ?? null,
        ]);

        return [
            'ok'          => $ok,
            'platform'    => 'instagram',
            'provider_id' => $res['message_id'] ?? null,
            'local_only'  => false,
            'error'       => $ok ? null : ($res['error'] ?? 'Instaflow send failed'),
        ];
    }

    private function dispatchNode(InboxMessage $msg): array
    {
        [$serverUrl, $from] = $this->resolveDevicePhone($msg);
        Log::info('[INBOX-BAILEYS] resolved', ['msg_id' => $msg->id, 'server_url' => $serverUrl, 'from_number' => $from]);
        if (rtrim($serverUrl, '/') === '') {
            return $this->localOnly('W', 'SERVER_URL not set and workspace has no Baileys config');
        }
        if ($from === '') {
            return ['ok' => false, 'platform' => 'W', 'provider_id' => null, 'local_only' => false, 'error' => 'No connected device — pair one at /devices first.'];
        }

        [$path, $payload] = $this->buildNodeRequest($msg, $from);
        // Phase 4 — forward the resolved per-message engine so Node routes by
        // it (explicit provider wins; absent => Node's legacy heuristic).
        // Omitted when empty so single-engine inboxes keep Node's old path.
        if (!empty($msg->provider)) {
            $payload['provider'] = (string) $msg->provider;
        }
        $url = rtrim($serverUrl, '/') . $path;
        Log::info('[INBOX-BAILEYS] →', ['msg_id' => $msg->id, 'url' => $url, 'to' => $payload['targetPhoneNumber'] ?? null]);

        try {
            $hasMedia = isset($payload['file_base64']) && $payload['file_base64'];
            $res = Http::timeout($hasMedia ? 60 : 20)->acceptJson()->asJson()->post($url, $payload);
            Log::info('[INBOX-BAILEYS] ←', ['msg_id' => $msg->id, 'status' => $res->status(), 'body' => mb_substr($res->body(), 0, 500)]);
            if ($res->successful()) {
                return [
                    'ok'          => true,
                    'platform'    => 'W',
                    'provider_id' => $res->json('messageId') ?? $res->json('id'),
                    'local_only'  => false,
                    'error'       => null,
                ];
            }
            $err = $res->json('error') ?? $res->json('details') ?? $res->json('message') ?? ('HTTP ' . $res->status());
            return ['ok' => false, 'platform' => 'W', 'provider_id' => null, 'local_only' => false, 'error' => is_string($err) ? $err : json_encode($err)];
        } catch (\Throwable $e) {
            return ['ok' => false, 'platform' => 'W', 'provider_id' => null, 'local_only' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Pick the Node endpoint + payload based on message shape. The inbox
     * surface uses three endpoints:
     *   - text only  → /api/send-message/<phone>
     *   - media+caption → /api/send-media-message/<phone>
     *   - media only → /api/send-media-only/<phone>
     *
     * We don't use /api/schedule-message or /api/send-location here —
     * inbox bubbles are always immediate, and location bubbles arrive as
     * inbound only (operator can't send a location).
     */
    /**
     * Backfill the recipient for an OUTBOUND row that was created without a
     * to_number, so buildNodeRequest / WABA / Twilio (all of which read
     * $msg->to_number with no fallback) never ship to a null recipient.
     * Called once from send(); no-op when to_number is already set, so every
     * existing producer (team-inbox reply, campaigns, flows) is untouched.
     *
     * Engine-correct resolution order:
     *   1. A real PHONE jid on the conversation (…@s.whatsapp.net / …@c.us) →
     *      its digits. @lid / @g.us jids carry no phone, so they're skipped
     *      here and routed via targetJid instead (see meta.target_jid below).
     *   2. The customer's last inbound number on this thread (covers WABA +
     *      Twilio + Baileys where raw_jid isn't stored).
     *   3. The linked contact's mobile.
     * The raw jid is always carried in meta.target_jid so LID-routed Baileys
     * chats send to the real jid (the Node bridge prefers targetJid).
     */
    private function ensureRecipient(InboxMessage $msg): void
    {
        if ($msg->direction !== 'out') return;                     // inbound rows need no recipient
        if (trim((string) $msg->to_number) !== '') return;         // already set → leave existing paths exactly as-is

        $convo = \App\Models\Conversation::find($msg->conversation_id);
        if (!$convo) return;

        $rawJid = (string) ($convo->raw_jid ?? '');
        $altJid = (string) ($convo->alt_jid ?? '');

        $to = null;
        foreach ([$rawJid, $altJid] as $j) {
            if ($j !== '' && preg_match('/^(\d+)@(?:s\.whatsapp\.net|c\.us)$/', $j, $mm)) { $to = $mm[1]; break; }
        }
        if (!$to) {
            $lastIn = InboxMessage::query()
                ->where('conversation_id', $convo->id)->where('direction', 'in')
                ->orderByDesc('id')->value('from_number');
            if ($lastIn) $to = preg_replace('/\D+/', '', (string) $lastIn) ?: null;
        }
        if (!$to && $convo->contact_id) {
            $mobile = optional(\App\Models\Contact::find($convo->contact_id))->mobile;
            if ($mobile) $to = preg_replace('/\D+/', '', (string) $mobile) ?: null;
        }

        $meta = is_array($msg->meta) ? $msg->meta : [];
        if (empty($meta['target_jid']) && $rawJid !== '') $meta['target_jid'] = $rawJid;

        if (!$to && empty($meta['target_jid'])) {
            Log::warning('[INBOX-DISPATCH] could not resolve a recipient for outbound row', [
                'msg_id' => $msg->id, 'convo_id' => $convo->id,
            ]);
            return;
        }

        $msg->forceFill(['to_number' => $to, 'meta' => $meta])->saveQuietly();
        Log::info('[INBOX-DISPATCH] recipient backfilled from conversation', [
            'msg_id' => $msg->id, 'to' => $to, 'via_jid' => !empty($meta['target_jid']),
        ]);
    }

    private function buildNodeRequest(InboxMessage $msg, string $from): array
    {
        $fromEnc = rawurlencode(preg_replace('/\D+/', '', $from) ?: $from);
        $meta    = is_array($msg->meta) ? $msg->meta : [];

        if ($msg->media_path) {
            // Read from the active media disk (cloud when enabled, else local),
            // OR from public/ when the producer wrote there (e.g. /auto-reply
            // media uses public_path). Otherwise Baileys media auto-replies
            // ship a null blob + a wrong /storage link.
            $mediaDisk = media_storage();
            $hasFile   = $mediaDisk->exists($msg->media_path);
            $publicAbs = public_path((string) $msg->media_path);
            $inPublic  = !$hasFile && is_file($publicAbs);
            $b64       = $hasFile
                ? base64_encode($mediaDisk->get($msg->media_path))
                : ($inPublic ? base64_encode((string) @file_get_contents($publicAbs)) : null);
            $url       = $this->publicMediaUrl((string) $msg->media_path);
            $mimeReal  = $hasFile
                ? ($mediaDisk->mimeType($msg->media_path) ?: 'application/octet-stream')
                : ($inPublic && function_exists('mime_content_type')
                    ? (@mime_content_type($publicAbs) ?: 'application/octet-stream')
                    : 'application/octet-stream');
            $base     = basename($msg->media_path);
            $origName = str_contains($base, '__') ? substr($base, strpos($base, '__') + 2) : $base;

            $shared = [
                'targetPhoneNumber' => $msg->to_number,
                'file'              => $url,
                'file_base64'       => $b64,
                'filetype'          => $mimeReal,
                'fileName'          => $origName,
            ];
            // Voice-note flag — when meta.ptt is true, ask Node to send as
            // push-to-talk so WhatsApp renders the round play-button bubble.
            if (!empty($meta['ptt'])) {
                $shared['ptt']   = true;
                $shared['voice'] = true; // alias for older bridge builds
                if ($msg->media_type === 'audio') {
                    $shared['filetype'] = 'audio/ogg; codecs=opus';
                }
            }
            if (!empty($meta['target_jid'])) {
                $shared['targetJid'] = (string) $meta['target_jid'];
            }
            // Voice notes (meta.ptt) MUST go to /api/send-media-only. The
            // send-media-message (caption) handler on the Node side never reads
            // the `ptt` flag, so an AI/ElevenLabs voice reply that carries a
            // transcript in `body` was sent as a PLAIN audio file — never a
            // round voice-note bubble (and WhatsApp can't caption audio anyway).
            // send-media-only DOES honour ptt. The transcript stays on the inbox
            // row for the operator's readable text next to the play button.
            if (!empty($meta['ptt'])) {
                return ['/api/send-media-only/' . $fromEnc, $shared];
            }
            if ($msg->body) {
                return ['/api/send-media-message/' . $fromEnc, $shared + ['caption' => (string) $msg->body]];
            }
            return ['/api/send-media-only/' . $fromEnc, $shared];
        }

        $payload = [
            'targetPhoneNumber' => $msg->to_number,
            'message'           => (string) $msg->body,
        ];
        if (!empty($meta['target_jid'])) {
            $payload['targetJid'] = (string) $meta['target_jid'];
        }
        // Plumb template-shaped meta (buttons / header / footer) through to
        // Node so interactive sends from the team-inbox composer survive.
        // Without this, an operator picking an approved template from the
        // composer dropdown lost quick-reply / CTA buttons silently — only
        // the body text reached the customer.
        return ['/api/send-message/' . $fromEnc, $this->mergeButtonsFooterMeta($payload, $meta)];
    }

    /**
     * Mirror of WhatsAppDispatcher::mergeButtonsFooter for the team-inbox
     * path so template replies from the inbox composer carry their buttons
     * / header / footer into Node's interactive-message branch. Kept local
     * to InboxDispatcher to avoid a cross-class call dependency.
     */
    private function mergeButtonsFooterMeta(array $payload, array $meta): array
    {
        if (empty($meta)) return $payload;

        if (!empty($meta['buttons']) && is_array($meta['buttons'])) {
            $payload['buttons'] = array_values(array_filter(array_map(function ($b) {
                if (!is_array($b)) return null;
                $type  = (string) ($b['type']  ?? 'quick_reply');
                $text  = (string) ($b['text']  ?? '');
                $value = (string) ($b['value'] ?? '');
                $url   = (string) ($b['url']   ?? '');
                if ($type === 'visit_website' && $url === '') $url = $value;
                if ($type === 'call_phone' && !empty($b['country_code'])) {
                    $cc = preg_replace('/\D+/', '', (string) $b['country_code']);
                    $vd = preg_replace('/\D+/', '', $value);
                    if ($cc && $vd && !str_starts_with($vd, $cc)) $value = $cc . $vd;
                }
                return ['type' => $type, 'text' => $text, 'value' => $value, 'url' => $url];
            }, $meta['buttons'])));
        }
        if (!empty($meta['footer'])) $payload['footer'] = (string) $meta['footer'];
        if (!empty($meta['header'])) $payload['title']  = (string) $meta['header'];
        return $payload;
    }

    private function dispatchMetaCloud(InboxMessage $msg): array
    {
        // Multi-tenant: resolve THIS workspace's WABA config first
        // (per-workspace access_token + phone_number_id). Without this
        // every workspace's inbox reply went out from the same global
        // env-configured Meta number, which collapses multi-tenant.
        $workspaceId = (int) (optional($msg->conversation)->workspace_id ?? 0);
        $token = '';
        $phoneId = '';
        // Resolve the SEND-FROM WABA number for THIS conversation. On a
        // multi-WABA workspace the reply must leave on the same number the
        // customer messaged (conversation.device_id → wa_provider_configs),
        // not the workspace primary — which is why inbox replies to the
        // non-primary WABA number silently failed while /chat (explicit
        // sender pick) worked.
        $cfg = $this->resolveWabaConfig($workspaceId, $msg);
        if ($cfg) {
            $creds   = $cfg->creds();
            $meta    = is_array($cfg->meta_json) ? $cfg->meta_json : [];
            $token   = (string) ($creds['access_token'] ?? '');
            $phoneId = (string) ($meta['phone_number_id'] ?? '');
        }
        // No env fallback — credentials live in DB only (per-workspace
        // wa_provider_configs). If a workspace has no WABA config yet,
        // the send fails loudly via `localOnly` below; admin must
        // connect a Meta number at /devices first. This keeps secrets
        // out of process env where they could leak via phpinfo / error
        // pages / shell exports.
        $version = (string) \App\Models\SystemSetting::get('waba_graph_api_version', 'v23.0');
        if ($token === '' || $phoneId === '') {
            return $this->localOnly('WB', 'No WABA token + phone_number_id for this workspace. Connect a WABA account at /devices.');
        }

        $to = preg_replace('/\D+/', '', (string) $msg->to_number);

        // Upload media to Meta FIRST and send by media_id — so delivery does
        // NOT depend on the file URL being publicly fetchable by Meta (which
        // breaks on private/local storage). Falls back to a link send only if
        // the upload fails.
        $mediaId    = null;
        $audioBytes = null;   // set when we remuxed a voice note to ogg/opus
        $audioMime  = null;
        if ($msg->media_path) {
            // Audio compatibility gate — browser MediaRecorder (Chrome/Edge)
            // records voice notes as webm/opus, which WhatsApp Cloud API does
            // NOT accept. The /media upload + /messages send both return 200
            // + a wamid, so the operator sees "sent", but Meta's async
            // delivery pipeline then rejects the webm container and the
            // recipient never gets it (the "audio not received" bug). We remux
            // webm→ogg (pure-PHP, no ffmpeg) so it sends as a REAL voice note.
            // Non-audio media passes straight through.
            $audioPrep = $this->prepareWabaAudio($msg);
            if (isset($audioPrep['error'])) {
                return ['ok' => false, 'platform' => 'WB', 'provider_id' => null,
                        'local_only' => false, 'error' => $audioPrep['error']];
            }
            $audioBytes = $audioPrep['bytes'] ?? null;   // remuxed ogg bytes when converted
            $audioMime  = $audioPrep['mime']  ?? null;
            $mediaId    = $this->uploadMediaToMeta($token, $phoneId, $version, $msg, $audioBytes, $audioMime);
        }
        $body = $this->buildCloudPayload($msg, $to, $mediaId);

        try {
            // 10s was too tight — the first (cold) connection to graph.facebook.com
            // frequently hit "cURL error 28: timed out after 10001ms" and the
            // reply silently failed until a manual re-send. 25s + one automatic
            // retry rides through the slow cold-connect / transient DNS blip.
            $res = Http::withToken($token)
                ->acceptJson()
                ->timeout(25)
                ->retry(2, 500, null, false)
                ->post("https://graph.facebook.com/{$version}/{$phoneId}/messages", $body);
            if ($res->successful()) {
                return [
                    'ok'          => true,
                    'platform'    => 'WB',
                    'provider_id' => $res->json('messages.0.id'),
                    'local_only'  => false,
                    'error'       => null,
                ];
            }
            $err = $res->json('error.message') ?? ('HTTP ' . $res->status());
            return ['ok' => false, 'platform' => 'WB', 'provider_id' => null, 'local_only' => false, 'error' => $err];
        } catch (\Throwable $e) {
            return ['ok' => false, 'platform' => 'WB', 'provider_id' => null, 'local_only' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Upload an inbox media file to Meta's /media endpoint and return the
     * resulting media_id, so the send can reference it by id (no public URL
     * needed). Returns null on any failure → caller falls back to a link send.
     */
    /**
     * Public URL for an outbound media path. Files on the media disk
     * (storage/app/public or cloud) resolve via media_url(); files written
     * under public/ (e.g. /auto-reply media via public_path) resolve via
     * url() so the link Meta/Twilio fetches actually exists (media_url would
     * point at /storage/... and 404). Falls back to media_url() best-effort.
     */
    private function publicMediaUrl(string $path): string
    {
        if ($path === '') return '';
        try {
            if (media_storage()->exists($path)) return media_url($path);
        } catch (\Throwable $e) {}
        if (is_file(public_path($path))) return url($path);
        return media_url($path);
    }

    private function uploadMediaToMeta(string $token, string $phoneId, string $version, InboxMessage $msg, ?string $overrideBytes = null, ?string $overrideMime = null): ?string
    {
        try {
            $disk = media_storage();
            // Some producers (e.g. /auto-reply media) store the file under
            // public/uploads/... (public_path) rather than on the media disk
            // (storage/app/public). Read from whichever holds it so the
            // upload-by-id path works for BOTH — otherwise auto-reply media
            // "didn't exist" here → upload skipped → wrong /storage/ link → Meta 404.
            $onMediaDisk = $disk->exists($msg->media_path);
            $publicAbs   = public_path((string) $msg->media_path);
            if ($overrideBytes === null && !$onMediaDisk && !is_file($publicAbs)) {
                return null;
            }
            // When the audio was remuxed to ogg/opus upstream, use those bytes
            // + mime instead of the original (webm) file on disk.
            $bytes = $overrideBytes
                ?? ($onMediaDisk ? $disk->get($msg->media_path) : @file_get_contents($publicAbs));
            if ($bytes === false || $bytes === null || $bytes === '') {
                return null;
            }
            $meta  = is_array($msg->meta) ? $msg->meta : [];
            $mime  = $overrideMime ?? ($onMediaDisk
                ? ($disk->mimeType($msg->media_path) ?: 'application/octet-stream')
                : ((function_exists('mime_content_type') ? @mime_content_type($publicAbs) : null)
                    ?: ((string) ($msg->mime_type ?? '') ?: 'application/octet-stream')));
            // WhatsApp voice notes must be audio/ogg (opus) to render as a
            // round voice-note bubble rather than a generic audio file.
            if ($overrideMime === null && ($msg->media_type ?? '') === 'audio' && !empty($meta['ptt'])) {
                $mime = 'audio/ogg';
            }
            // Name the multipart file with a matching extension so Meta's
            // content sniffing agrees with the declared Content-Type — a
            // .webm name on audio/ogg bytes can trip its validator.
            $uploadName = $overrideMime === 'audio/ogg'
                ? (pathinfo(basename((string) $msg->media_path), PATHINFO_FILENAME) . '.ogg')
                : basename((string) $msg->media_path);
            $res = \Illuminate\Support\Facades\Http::withToken($token)
                ->attach('file', $bytes, $uploadName, ['Content-Type' => $mime])
                ->timeout(60)
                ->post("https://graph.facebook.com/{$version}/{$phoneId}/media", [
                    'messaging_product' => 'whatsapp',
                ]);
            if ($res->successful() && $res->json('id')) {
                return (string) $res->json('id');
            }
            \Log::warning('[INBOX-WABA] media upload failed — falling back to link', [
                'msg_id' => $msg->id, 'status' => $res->status(), 'body' => mb_substr($res->body(), 0, 300),
            ]);
        } catch (\Throwable $e) {
            \Log::warning('[INBOX-WABA] media upload threw: ' . $e->getMessage(), ['msg_id' => $msg->id]);
        }
        return null;
    }

    /**
     * Ensure an audio message is in a WhatsApp-Cloud-supported container
     * before we upload it to Meta.
     *
     * Browser MediaRecorder (Chrome/Edge) records voice notes as webm/opus.
     * WhatsApp Cloud API's supported audio list is: aac, amr, mp3 (mpeg),
     * mp4/m4a, ogg (opus only) — webm is NOT on it. Meta's /media upload +
     * /messages send BOTH return 200 + a wamid for webm, so the operator
     * sees "sent", but the async delivery pipeline then rejects the webm
     * container and the recipient never receives it — the "audio message
     * not received" bug. Firefox records ogg/opus and Safari records mp4;
     * both pass through untouched. Only webm (and unknown containers) need
     * a remux — the opus stream is already inside the webm, so this is a
     * fast, near-lossless container swap, not a heavy re-encode.
     *
     * Returns:
     *   []                                  — not audio, or already compatible (pass-through)
     *   ['bytes'=>ogg, 'mime'=>'audio/ogg'] — remuxed, ready to upload (voice-note bubble)
     *   ['as_document'=>true, 'mime'=>…]    — ffmpeg unavailable → send as a
     *                                         document (still delivers, as a
     *                                         playable file not a voice bubble)
     *   ['error'=>'…']                      — hard failure (file missing) →
     *                                         caller marks the message failed
     *                                         with a real reason.
     */
    private function prepareWabaAudio(InboxMessage $msg): array
    {
        if (($msg->media_type ?? '') !== 'audio') return [];

        $ext = strtolower(pathinfo((string) $msg->media_path, PATHINFO_EXTENSION));
        // Containers WhatsApp Cloud accepts as-is.
        if (in_array($ext, ['ogg', 'oga', 'mp3', 'm4a', 'aac', 'amr', 'mp4'], true)) {
            return [];
        }

        $disk = media_storage();
        if (!$disk->exists($msg->media_path)) {
            return ['error' => 'Voice note file is missing from storage — cannot send.'];
        }
        $bytes = $disk->get($msg->media_path);

        // 1) Pure-PHP WebM→Ogg REMUX — no ffmpeg, no external binary. WebM and
        //    Ogg both wrap the same Opus codec, so we extract the Opus packets
        //    from the WebM container and repackage them into an Ogg container.
        //    Lossless + dependency-free → a real ogg/opus voice note. This is
        //    the primary path on servers without ffmpeg.
        $ogg = \App\Support\Audio\WebmOpusToOgg::convert($bytes);
        if ($ogg !== null) {
            return ['bytes' => $ogg, 'mime' => 'audio/ogg'];
        }

        // 2) ffmpeg — only reached if the pure-PHP remux couldn't parse the
        //    input (a weird/non-webm container). Handles anything, if present.
        $ogg = $this->transcodeToOggOpus($bytes, $ext ?: 'webm');
        if ($ogg !== null) {
            return ['bytes' => $ogg, 'mime' => 'audio/ogg'];
        }

        // Neither path produced ogg. Per requirement we do NOT fall back to a
        // document — fail loudly (message marked failed with a reason) so the
        // operator sees it instead of a silent drop.
        \Log::warning('[INBOX-WABA] voice note could not be converted to ogg/opus', [
            'msg_id' => $msg->id, 'ext' => $ext, 'size' => strlen($bytes),
        ]);
        return ['error' => 'Could not convert this voice recording to a WhatsApp-compatible format (unexpected audio container). Try re-recording, or install ffmpeg on the server as a fallback converter.'];
    }

    /**
     * Remux/transcode arbitrary audio bytes to ogg/opus using ffmpeg.
     * libopus 32k mono @ 48kHz is the WhatsApp voice-note profile — small
     * and clear. Returns the ogg bytes, or null when ffmpeg isn't available
     * or the conversion fails (caller degrades to a clear error).
     */
    private function transcodeToOggOpus(string $bytes, string $sourceExt): ?string
    {
        if (!function_exists('exec')) return null;   // shared host with exec disabled
        $ffmpeg = $this->resolveFfmpegBinary();
        if ($ffmpeg === null) return null;

        $dir = sys_get_temp_dir();
        $in  = tempnam($dir, 'wa_aud_in_');
        $out = tempnam($dir, 'wa_aud_out_');
        try {
            file_put_contents($in, $bytes);
            // -vn drops any video track a webm may carry; input format is
            // sniffed from content, output is forced to ogg via -f.
            $cmd = escapeshellarg($ffmpeg)
                . ' -y -hide_banner -loglevel error'
                . ' -i ' . escapeshellarg($in)
                . ' -vn -c:a libopus -b:a 32k -ar 48000 -ac 1 -f ogg '
                . escapeshellarg($out) . ' 2>&1';
            $lines = [];
            $code  = 1;
            @exec($cmd, $lines, $code);
            if ($code === 0 && is_file($out) && filesize($out) > 0) {
                return file_get_contents($out);
            }
            \Log::warning('[INBOX-WABA] ffmpeg audio transcode failed', [
                'code' => $code,
                'src'  => $sourceExt,
                'tail' => implode(' | ', array_slice($lines, -4)),
            ]);
            return null;
        } finally {
            @unlink($in);
            @unlink($out);
        }
    }

    /**
     * Locate a working ffmpeg binary. Admin can pin an explicit path via
     * the `ffmpeg_path` system setting; otherwise probe PATH + the usual
     * install locations. Returns the invocable command, or null if none
     * responds to `-version`.
     */
    private function resolveFfmpegBinary(): ?string
    {
        if (!function_exists('exec')) return null;
        $configured = trim((string) \App\Models\SystemSetting::get('ffmpeg_path', ''));
        $candidates = array_filter([
            $configured ?: null,
            'ffmpeg',
            '/usr/bin/ffmpeg',
            '/usr/local/bin/ffmpeg',
            '/opt/homebrew/bin/ffmpeg',
        ]);
        foreach ($candidates as $bin) {
            $lines = [];
            $code  = 1;
            @exec(escapeshellarg($bin) . ' -version 2>&1', $lines, $code);
            if ($code === 0) return $bin;
        }
        return null;
    }

    private function buildCloudPayload(InboxMessage $msg, string $to, ?string $mediaId = null): array
    {
        $meta = is_array($msg->meta) ? $msg->meta : [];

        // Template path — the inbox composer set template_id, so the
        // outbound MUST be a type:template payload. Without this Meta
        // rejects with error 131047 ("re-engagement message") any time
        // the recipient hasn't messaged us in 24h. This was the #1 WABA
        // blocker per the 2026-05-24 audit.
        if (!empty($meta['template_id']) && !empty($meta['template_name'])) {
            $components = [];
            // Body component — pull positional vars out of the resolved
            // body. We have `$msg->body` already substituted; for Meta
            // we send the substituted strings as parameters (Meta also
            // substitutes server-side using the approved template's
            // `{{1}}, {{2}}…` slots — sending pre-substituted text in
            // type=text params works for both behaviors).
            $body = (string) $msg->body;
            // Tokenise on whitespace if the template has placeholders —
            // for now we send the whole body as a single `text` param
            // when the approved template has one positional slot; if
            // the template has multiple, this falls back to {{1}} = body.
            // Templates without placeholders need empty components.
            if (preg_match_all('/\{\{\s*\d+\s*\}\}/', $body, $matches) && count($matches[0]) > 0) {
                $components[] = [
                    'type' => 'body',
                    'parameters' => array_map(
                        fn () => ['type' => 'text', 'text' => $body],
                        $matches[0]
                    ),
                ];
            }
            return [
                'messaging_product' => 'whatsapp',
                'to'                => $to,
                'type'              => 'template',
                'template'          => array_filter([
                    'name'       => (string) $meta['template_name'],
                    'language'   => ['code' => (string) ($meta['template_language'] ?? 'en')],
                    'components' => $components,
                ], fn ($v) => $v !== null && $v !== []),
            ];
        }

        if ($msg->media_path) {
            $mediaType = $msg->media_type ?: 'document';
            // Prefer the uploaded media_id (id-based send, no public URL needed).
            // Fall back to a link send only when the upload failed.
            $mediaPayload = $mediaId
                ? ['id' => $mediaId]
                : ['link' => $this->publicMediaUrl((string) $msg->media_path)];
            // WABA audio is auto-rendered as voice-note for opus; caption
            // not supported on audio.
            if ($mediaType !== 'audio' && $msg->body) {
                $mediaPayload['caption'] = $msg->body;
            }
            // Documents keep a filename so the recipient sees a real name.
            if ($mediaType === 'document') {
                $mediaPayload['filename'] = basename((string) $msg->media_path);
            }
            return [
                'messaging_product' => 'whatsapp',
                'to'                => $to,
                'type'              => $mediaType,
                $mediaType          => array_filter($mediaPayload),
            ];
        }

        // Plain text with optional buttons (interactive). Interactive
        // is used when the operator composed quick-reply buttons inline.
        if (!empty($meta['buttons']) && is_array($meta['buttons'])) {
            $buttons = array_slice($meta['buttons'], 0, 3);
            $interactive = [
                'type'   => 'button',
                'body'   => ['text' => mb_substr((string) $msg->body, 0, 1024)],
                'action' => [
                    'buttons' => array_map(function ($b, $i) {
                        return [
                            'type'  => 'reply',
                            'reply' => [
                                'id'    => mb_substr((string) ($b['id']    ?? "btn_$i"), 0, 256),
                                'title' => mb_substr((string) ($b['title'] ?? $b['text'] ?? "Option ".($i + 1)), 0, 20),
                            ],
                        ];
                    }, $buttons, array_keys($buttons)),
                ],
            ];
            if (!empty($meta['header'])) $interactive['header'] = ['type' => 'text', 'text' => mb_substr((string) $meta['header'], 0, 60)];
            if (!empty($meta['footer'])) $interactive['footer'] = ['text' => mb_substr((string) $meta['footer'], 0, 60)];
            return [
                'messaging_product' => 'whatsapp',
                'to'                => $to,
                'type'              => 'interactive',
                'interactive'       => $interactive,
            ];
        }

        return [
            'messaging_product' => 'whatsapp',
            'to'                => $to,
            'type'              => 'text',
            'text'              => ['body' => (string) $msg->body],
        ];
    }

    private function dispatchTwilio(InboxMessage $msg): array
    {
        // Resolve creds per-workspace from WaProviderConfig (mirrors the
        // chat-path dispatcher). Previously this method read `env()` only,
        // collapsing multi-tenant Twilio: every workspace's inbox reply
        // billed the platform's Twilio account. Now each workspace's
        // saved Twilio number/token actually drives its inbox sends.
        $sid = $token = $from = '';
        $sandbox = false;
        $workspaceId = (int) (optional($msg->conversation)->workspace_id ?? 0);
        if ($workspaceId > 0) {
            $cfg = WaProviderConfig::query()
                ->where('workspace_id', $workspaceId)
                ->where('provider', 'twilio')
                ->where('status', WaProviderConfig::STATUS_CONNECTED)
                ->first();
            if ($cfg) {
                $creds   = $cfg->creds();
                $sid     = (string) ($creds['account_sid']  ?? '');
                $token   = (string) ($creds['auth_token']   ?? '');
                $from    = (string) ($creds['from_number']  ?? $cfg->phone_number ?? '');
                $sandbox = (bool)   ($creds['sandbox']      ?? false);
            }
        }
        if ($sid === '')   $sid   = (string) \App\Models\SystemSetting::get('twilio_account_sid', env('TWILIO_ACCOUNT_SID', ''));
        if ($token === '') $token = (string) \App\Models\SystemSetting::get('twilio_auth_token', env('TWILIO_AUTH_TOKEN', ''));
        if ($from === '')  $from  = (string) \App\Models\SystemSetting::get('twilio_whatsapp_number', env('TWILIO_WHATSAPP_NUMBER', ''));
        if ($sid === '' || $token === '' || $from === '') {
            return $this->localOnly('T', 'Twilio creds missing for this workspace — connect Twilio at /devices.');
        }
        if ($sandbox) $from = '14155238886';

        // Twilio's `whatsapp:+E164` format — `+` is required by spec.
        $to       = 'whatsapp:+' . preg_replace('/\D+/', '', (string) $msg->to_number);
        $fromBare = preg_replace('/\D+/', '', (string) $from);
        $payload  = ['From' => 'whatsapp:+' . $fromBare, 'To' => $to];

        // StatusCallback — see WhatsAppDispatcher::dispatchTwilio note.
        // Without this Twilio never POSTs delivered/read events back.
        $appBase = rtrim((string) config('app.url'), '/');
        if ($appBase !== '') {
            $payload['StatusCallback'] = $appBase . '/api/twilio/status';
        }

        // Template reply: if the operator picked a template that has a
        // registered Twilio ContentSid, ship it as a ContentSid send so
        // marketing/utility/auth categories remain compliant. The meta
        // shape mirrors what BroadcastsController / ChatController build
        // (`meta.template_vars` + `meta.otp_code`).
        $meta = is_array($msg->meta) ? $msg->meta : [];
        $contentSid = null;
        if (!empty($meta['template_id'])) {
            $tpl = \App\Models\WaTemplate::find((int) $meta['template_id']);
            $contentSid = $tpl?->twilio_content_sid ? trim((string) $tpl->twilio_content_sid) : null;
        }

        if ($contentSid) {
            $payload['ContentSid']       = $contentSid;
            $payload['ContentVariables'] = $this->buildTwilioContentVariablesFromMeta($meta);
        } elseif ($msg->media_path) {
            $payload['MediaUrl'] = $this->publicMediaUrl((string) $msg->media_path);
            if ($msg->body) $payload['Body'] = $msg->body;
        } else {
            $payload['Body'] = (string) $msg->body;
        }

        try {
            $res = Http::withBasicAuth($sid, $token)
                ->asForm()
                ->timeout(10)
                ->post("https://api.twilio.com/2010-04-01/Accounts/{$sid}/Messages.json", $payload);
            if ($res->successful()) {
                return ['ok' => true, 'platform' => 'T', 'provider_id' => $res->json('sid'), 'local_only' => false, 'error' => null];
            }
            $err = $res->json('message') ?? ('HTTP ' . $res->status());
            return ['ok' => false, 'platform' => 'T', 'provider_id' => null, 'local_only' => false, 'error' => $err];
        } catch (\Throwable $e) {
            return ['ok' => false, 'platform' => 'T', 'provider_id' => null, 'local_only' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Build Twilio ContentVariables JSON from a Message::meta array.
     * Positional keys only — Twilio's substitution engine doesn't read
     * named keys. See WhatsAppDispatcher::buildTwilioContentVariables for
     * the matching implementation on the user-side chat path.
     */
    private function buildTwilioContentVariablesFromMeta(array $meta): string
    {
        $vars = is_array($meta['template_vars'] ?? null) ? $meta['template_vars'] : [];
        if (!empty($meta['otp_code']) && !isset($vars['1'])) {
            $vars['1'] = (string) $meta['otp_code'];
        }
        $out = [];
        foreach ($vars as $k => $v) {
            $out[(string) $k] = is_scalar($v) ? (string) $v : json_encode($v);
        }
        return json_encode($out, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '{}';
    }

    /**
     * Shared plumbing for pin/star — both go to Baileys via the same payload
     * shape (target message id + jid + fromMe). WABA + Twilio silently no-op.
     */
    private function messageAction(InboxMessage $msg, string $path, array $extra, string $tag): array
    {
        $platform = optional($msg->conversation)->platform ?? 'W';
        $resolved = $this->resolveProvider($msg, $platform);
        if ($resolved !== WaProvider::Baileys) {
            return ['ok' => true, 'platform' => $resolved->value, 'local_only' => true];
        }

        [$serverUrl, $from] = $this->resolveDevicePhone($msg);
        if ($from === '' || $serverUrl === '') {
            Log::warning("[{$tag}] no from/server", ['from' => $from, 'server' => $serverUrl, 'msg_id' => $msg->id]);
            return ['ok' => false, 'error' => 'No connected device or server url'];
        }

        $meta        = is_array($msg->meta) ? $msg->meta : [];
        $waMessageId = (string) ($meta['wa_message_id'] ?? '');
        $targetJid   = (string) ($meta['target_jid']    ?? '');
        $isInbound   = $msg->direction === 'in';
        $reactTo     = $isInbound ? ($msg->from_number ?: null) : $msg->to_number;

        if ($waMessageId === '') {
            return [
                'ok'    => false,
                'error' => 'This message was saved before WhatsApp-id tracking was enabled. Receive a new message and try again.',
            ];
        }

        $fromEnc = rawurlencode(preg_replace('/\D+/', '', $from) ?: $from);
        $url     = rtrim($serverUrl, '/') . $path . $fromEnc;

        $payload = array_merge([
            'targetPhoneNumber' => $reactTo,
            'targetJid'         => $targetJid !== '' ? $targetJid : null,
            'targetMessageId'   => $waMessageId,
            'fromMe'            => !$isInbound,
        ], $extra);

        Log::info("[{$tag}] → POST {$url}", $payload + ['msg_id' => $msg->id]);

        try {
            $res = Http::timeout(10)->acceptJson()->asJson()->post($url, $payload);
            $ok  = $res->successful();
            Log::info("[{$tag}] ← " . $res->status() . ($ok ? ' OK' : ' FAIL'), [
                'msg_id' => $msg->id, 'status' => $res->status(),
                'body'   => mb_substr((string) $res->body(), 0, 500),
            ]);
            return ['ok' => $ok, 'error' => $ok ? null : $res->body()];
        } catch (\Throwable $e) {
            Log::warning("[{$tag}] threw", ['err' => $e->getMessage(), 'msg_id' => $msg->id]);
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Append the plan-gated outbound footer to the operator's reply body
     * (or to a media caption if there's no body). Mutates $msg->body in
     * place — every transport reads from there so we apply once at the
     * top of send() and the three dispatch paths all pick it up.
     * Skipped when:
     *   - there's no body AND no media caption (e.g. pure media bubble)
     *   - the workspace's plan grants remove_branding AND they cleared
     *     the footer (BrandingFooterService::resolve returns null)
     */
    private function applyBrandingFooter(InboxMessage $msg): void
    {
        $workspace = null;
        if ($msg->workspace_id) {
            $workspace = \App\Models\Workspace::find($msg->workspace_id);
        }
        if (!$workspace) return;

        $body = (string) ($msg->body ?? '');
        // Skip pure-media sends with no caption — no body to append to.
        // Media WITH a caption is fine; we'll attach the footer to the caption.
        if ($body === '') return;

        $newBody = \App\Services\BrandingFooterService::appendToBody($body, $workspace);
        if ($newBody !== $body) {
            $msg->body = $newBody;
            // Note: not saved — the dispatcher reads in-memory; the DB
            // row already shows what the operator actually typed.
        }
    }

    private function localOnly(string $platform, string $reason): array
    {
        return [
            'ok'          => true,
            'platform'    => $platform,
            'provider_id' => null,
            'local_only'  => true,
            'error'       => null,
            'reason'      => $reason,
        ];
    }

    /**
     * Resolve the workspace that owns this message and enforce the
     * monthly_messages_limit cap with credit-overflow:
     *   - Under cap → free, no deduction
     *   - Over cap + wallet has credits → deduct 1 credit, allow
     *   - Over cap + wallet at 0 → throw PlanLimitReachedException
     *
     * Counts outbound messages this calendar month across BOTH tables:
     *   - inbox_messages (team-inbox replies, AI-agent, flow sends)
     *   - messages (legacy /chat surface + campaigns + broadcasts)
     */
    private function guardMonthlyMessagesLimit(InboxMessage $msg): void
    {
        $workspaceId = optional($msg->conversation)->workspace_id;
        if (!$workspaceId && $msg->conversation_id) {
            $workspaceId = \App\Models\Conversation::query()->whereKey($msg->conversation_id)->value('workspace_id');
        }
        if (!$workspaceId && $msg->user_id) {
            $workspaceId = \App\Models\User::query()->whereKey($msg->user_id)->value('current_workspace_id');
        }
        if (!$workspaceId) return; // can't enforce without a workspace

        $workspace = \App\Models\Workspace::find($workspaceId);
        if (!$workspace) return;

        $monthStart = now()->startOfMonth();
        $used = \App\Models\InboxMessage::query()
            ->whereHas('conversation', fn ($q) => $q->where('workspace_id', $workspaceId))
            ->where('direction', 'out')
            ->where('created_at', '>=', $monthStart)
            ->count();
        $userIds = \DB::table('workspace_user')->where('workspace_id', $workspaceId)->pluck('user_id');
        if ($userIds->isNotEmpty()) {
            $used += \DB::table('messages')
                ->whereIn('user_id', $userIds)
                ->where('direction', 'out')
                ->where('created_at', '>=', $monthStart)
                ->count();
        }

        // Operator inbox replies are inside the 24h customer-service window →
        // bill as 'service' (priced free by default under per-country pricing,
        // mirroring Meta). No-ops to the flat rate when the flag is OFF.
        \App\Services\OverflowBilling::consumeOne($workspace, $used, null, 'service');
    }
}
