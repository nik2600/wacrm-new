<?php

namespace App\Services;

use App\Enums\WaProvider;
use App\Models\Message;
use App\Models\SystemSetting;
use App\Models\WaProviderConfig;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Sends messages to whichever WhatsApp provider the workspace is
 * configured for. Three paths, mirroring the old project's
 * D:\wadesk_2806\New folder\app\Http\Controllers\MessageController.php
 * platform_type column ('W' / 'WB' / 'T'):
 *
 *   W  → Custom Node WhatsApp Web bridge      (env('SERVER_URL'))
 *   WB → Meta WhatsApp Cloud / Business API   (env('FACEBOOK_API_TOKEN') + FACEBOOK_WP_ID)
 *   T  → Twilio WhatsApp Sandbox / Production (env('TWILIO_*'))
 *
 * Every path is env-gated. If credentials aren't set the dispatcher
 * returns a "local-only" result so dev environments still work and
 * messages persist locally without trying to call out.
 *
 * Returned shape:
 *   ['ok' => bool, 'provider_id' => ?string, 'error' => ?string,
 *    'platform' => string, 'local_only' => bool]
 */
class WhatsAppDispatcher
{
    /**
     * Send a Message right now. The Message must already exist
     * (have an id) — the dispatcher just hands it off to the
     * provider and reports back. Caller decides what to update on
     * the row (status / sent_at / failure_reason).
     */
    public function send(Message $msg, string $platform = 'W'): array
    {
        // Plan limit on outbound message volume. Applies to /chat sends,
        // campaign batch sends, broadcasts, scheduled fires — everything
        // routed through this dispatcher.
        $this->guardMonthlyMessagesLimit($msg);
        return $this->dispatch($msg, $platform, scheduled: false);
    }

    /**
     * Schedule a message for later delivery. For the Node bridge
     * this hits /api/schedule-message; Meta Cloud doesn't expose
     * native scheduling, so we mark local-only and rely on a cron
     * worker to call send() at the scheduled time.
     */
    public function schedule(Message $msg, string $platform = 'W'): array
    {
        return $this->dispatch($msg, $platform, scheduled: true);
    }

    /**
     * Send a message WITHOUT writing rows into the `messages` /
     * `conversations` tables. Callers that own their own log tables
     * (campaigns → wp_campaign_contacts, scheduled → scheduled_messages,
     * broadcasts → broadcast_contacts) should use this so they don't
     * duplicate sends into the chat tables.
     *
     * Builds a transient Message instance (`new Message(...)`, NOT
     * saved) and runs it through the existing dispatcher pipeline so
     * provider resolution + Node payload assembly stay identical to
     * the chat path. Returns the same result shape as send().
     *
     * Supported params:
     *   from_number, to_number, body, media_path, media_type,
     *   latitude, longitude, meta (buttons/footer/header), scheduled_at
     */
    public function sendRaw(array $params, ?int $userId = null, string $platform = 'W'): array
    {
        $msg = new Message([
            'user_id'     => $userId ?? auth()->id(),
            'from_number' => $params['from_number'] ?? null,
            'to_number'   => $params['to_number']   ?? null,
            'body'        => $params['body']        ?? null,
            'media_path'  => $params['media_path']  ?? null,
            'media_type'  => $params['media_type']  ?? null,
            'latitude'    => $params['latitude']    ?? null,
            'longitude'   => $params['longitude']   ?? null,
            'meta'        => $params['meta']        ?? null,
            // Optional — lets event-driven senders (Shopify/WooCommerce
            // commerce notifications) route a Twilio send through the
            // ContentSid template path and resolve per-workspace creds.
            'template_id' => $params['template_id'] ?? null,
            'workspace_id'=> $params['workspace_id'] ?? null,
        ]);
        // Phase 4 — let the campaign / scheduled / broadcast paths forward the
        // operator-chosen engine for this row (wpcampaigns.provider etc.).
        // Set explicitly (not via mass-assignment) so it survives even if
        // `provider` isn't fillable on the Message model. Empty => unchanged
        // legacy resolution downstream.
        if (!empty($params['provider'])) {
            $msg->provider = (string) $params['provider'];
        }
        if (!empty($params['scheduled_at'])) {
            $msg->scheduled_at = $params['scheduled_at'];
            return $this->schedule($msg, $platform);
        }
        return $this->send($msg, $platform);
    }

    /**
     * Send a Baileys reaction (emoji) for a message that was already
     * delivered. Pass an empty string to clear the reaction. We can
     * only do this on the Baileys path — Meta Cloud + Twilio don't
     * expose reactions, so we no-op silently for those.
     */
    public function reaction(Message $msg, string $emoji): array
    {
        $platform = optional($msg->conversation)->platform ?? 'W';
        $resolved = $this->resolveProvider($msg, $platform);
        if ($resolved !== WaProvider::Baileys) {
            return ['ok' => true, 'platform' => $resolved->value, 'local_only' => true, 'error' => null];
        }

        // Resolve the sender phone — same logic as messageAction(). The
        // key fix: don't blindly use $msg->from_number, since that's the
        // CUSTOMER phone on inbound messages. Use the conversation's
        // device_id, or fall back to any connected device on this user.
        $serverUrl = '';
        $from      = '';
        $workspaceId = $msg->workspace_id
            ?: ($msg->user_id ? \App\Models\User::query()->whereKey($msg->user_id)->value('current_workspace_id') : null);
        if ($workspaceId) {
            $cfg = WaProviderConfig::query()->primaryForWorkspace($workspaceId)->first();
            if ($cfg) {
                $creds = $cfg->creds();
                $serverUrl = (string) ($creds['server_url'] ?? '');
                $from = (string) ($cfg->phone_number ?: ($creds['phone_number'] ?? ''));
            }
        }
        if ($serverUrl === '') $serverUrl = (string) (\App\Models\SystemSetting::get('baileys_server_url') ?: env('SERVER_URL', ''));
        if ($from === '' && $msg->direction === 'out' && $msg->from_number) {
            $from = $msg->from_number;
        }
        if ($from === '') {
            $conv = $msg->conversation ?: ($msg->conversation_id
                ? \App\Models\Conversation::query()->find($msg->conversation_id)
                : null);
            $deviceId = $conv?->device_id;
            if ($deviceId) {
                $device = \App\Models\Device::query()->find($deviceId);
                if ($device) $from = preg_replace('/\D+/', '', (string) ($device->country_code . $device->phone_number));
            }
        }
        if ($from === '' && $msg->user_id) {
            // Queue context — no authed user. Pass workspace_id from
            // the message; fall back to user_id for legacy rows.
            $device = \App\Models\Device::query()->forWorkspace($msg->workspace_id ?? null, $msg->user_id)->where('status', 'connected')->orderByDesc('id')->first();
            if ($device) $from = preg_replace('/\D+/', '', (string) ($device->country_code . $device->phone_number));
        }
        if ($from === '' || $serverUrl === '') {
            return ['ok' => false, 'error' => 'No connected device or server url'];
        }

        $fromEnc = rawurlencode(preg_replace('/\D+/', '', $from) ?: $from);
        $url = rtrim($serverUrl, '/') . '/api/send-reaction/' . $fromEnc;

        // Tell Node EXACTLY which message to react to. The bridge's
        // sendReaction handler defaults to "the most recent fromMe
        // message" if we don't pass anything — which is wrong when the
        // operator reacts to an inbound (customer) bubble. Pull the
        // wa_message_id we stored on Message::meta (set by WaInboundController
        // for inbound, and by TeamInboxController::reply() for outbound).
        $meta = is_array($msg->meta) ? $msg->meta : [];
        $waMessageId = (string) ($meta['wa_message_id'] ?? '');
        // For inbound messages we're targeting, we also need to flag
        // fromMe=false so Node builds the right reaction key.
        $isInbound = $msg->direction === 'in';

        $targetJid = (string) ($meta['target_jid'] ?? '');
        // For an inbound message, the "target" of the reaction is the
        // sender of the original — i.e. the conversation's customer.
        // That's already what $msg->to_number / raw_jid points at on
        // outbound, but for inbound we need the from_number.
        $reactTo = $isInbound ? ($msg->from_number ?: null) : $msg->to_number;

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
     * Pin / unpin a WhatsApp message on the recipient's side. Baileys
     * only — Meta Cloud + Twilio don't expose pin so we silently no-op.
     * `$pin` is true to pin, false to unpin. `$durationSeconds` is the
     * WhatsApp-required pin lifetime (24h / 7d / 30d).
     */
    public function pin(Message $msg, bool $pin = true, int $durationSeconds = 86400): array
    {
        return $this->messageAction($msg, '/api/pin-message/', [
            'pin'      => $pin,
            'duration' => $durationSeconds,
        ], 'PIN');
    }

    /**
     * Star / unstar a WhatsApp message on the operator's own account.
     * Per the Baileys docs this is a chatModify call — it doesn't
     * notify the recipient, just syncs to the operator's WhatsApp Web
     * starred-messages list.
     */
    public function star(Message $msg, bool $star = true): array
    {
        return $this->messageAction($msg, '/api/star-message/', [
            'star' => $star,
        ], 'STAR');
    }

    /**
     * Shared plumbing for pin/star — both share the same Node payload
     * shape (target message id + jid + fromMe). Baileys only; WABA +
     * Twilio silently no-op.
     */
    private function messageAction(Message $msg, string $path, array $extra, string $tag): array
    {
        $platform = optional($msg->conversation)->platform ?? 'W';
        $resolved = $this->resolveProvider($msg, $platform);
        if ($resolved !== WaProvider::Baileys) {
            Log::info("[{$tag}] skip — non-baileys provider", ['provider' => $resolved->value, 'msg_id' => $msg->id]);
            return ['ok' => true, 'platform' => $resolved->value, 'local_only' => true];
        }

        $serverUrl = '';
        $from = '';
        $workspaceId = $msg->workspace_id
            ?: ($msg->user_id ? \App\Models\User::query()->whereKey($msg->user_id)->value('current_workspace_id') : null);
        if ($workspaceId) {
            $cfg = WaProviderConfig::query()->primaryForWorkspace($workspaceId)->first();
            if ($cfg) {
                $creds = $cfg->creds();
                $serverUrl = (string) ($creds['server_url'] ?? '');
                $from = (string) ($cfg->phone_number ?: ($creds['phone_number'] ?? ''));
            }
        }
        if ($serverUrl === '') $serverUrl = (string) (\App\Models\SystemSetting::get('baileys_server_url') ?: env('SERVER_URL', ''));

        // Pick the DEVICE phone (NOT the customer phone). Node keys its
        // clients dict by device phone, so passing the customer phone
        // (which is what $msg->from_number is on inbound messages) gives
        // "sock=false / CLIENT NOT READY".
        //   - outbound msg: $msg->from_number IS the device phone ✓
        //   - inbound msg:  $msg->from_number is the CUSTOMER → must
        //                   resolve via conversation.device_id instead.
        if ($from === '') {
            if ($msg->direction === 'out' && $msg->from_number) {
                $from = $msg->from_number;
            }
        }
        // Try the conversation's device first — most reliable since the
        // conversation row carries device_id explicitly.
        if ($from === '') {
            $conv = $msg->conversation ?: ($msg->conversation_id
                ? \App\Models\Conversation::query()->find($msg->conversation_id)
                : null);
            $deviceId = $conv?->device_id;
            if ($deviceId) {
                $device = \App\Models\Device::query()->find($deviceId);
                if ($device) {
                    $from = preg_replace('/\D+/', '', (string) ($device->country_code . $device->phone_number));
                }
            }
        }
        // Last-ditch fallback: any connected device the user owns.
        if ($from === '' && $msg->user_id) {
            // Queue context — no authed user. Pass workspace_id from
            // the message; fall back to user_id for legacy rows.
            $device = \App\Models\Device::query()->forWorkspace($msg->workspace_id ?? null, $msg->user_id)->where('status', 'connected')->orderByDesc('id')->first();
            if ($device) $from = preg_replace('/\D+/', '', (string) ($device->country_code . $device->phone_number));
        }
        if ($from === '' || $serverUrl === '') {
            Log::warning("[{$tag}] no from/server", ['from' => $from, 'server' => $serverUrl, 'msg_id' => $msg->id]);
            return ['ok' => false, 'error' => 'No connected device or server url'];
        }

        $meta = is_array($msg->meta) ? $msg->meta : [];
        $waMessageId = (string) ($meta['wa_message_id'] ?? '');
        $targetJid   = (string) ($meta['target_jid']    ?? '');
        $isInbound   = $msg->direction === 'in';
        $reactTo     = $isInbound ? ($msg->from_number ?: null) : $msg->to_number;

        // Hard fail with a clear error if this message doesn't have a WA
        // message id stored. Older messages saved before the wa_message_id
        // backfill won't have one; Node can't pin/star/react without it.
        if ($waMessageId === '') {
            Log::warning("[{$tag}] no wa_message_id on message — cannot target it on WhatsApp", [
                'msg_id'    => $msg->id,
                'direction' => $msg->direction,
                'meta_keys' => array_keys($meta),
            ]);
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
            $okFlag  = $res->successful();
            $errBody = $okFlag ? null : $res->body();
            Log::info("[{$tag}] ← " . $res->status() . ($okFlag ? ' OK' : ' FAIL'), [
                'msg_id' => $msg->id,
                'status' => $res->status(),
                'body'   => mb_substr((string) $res->body(), 0, 500),
            ]);
            return ['ok' => $okFlag, 'error' => $errBody];
        } catch (\Throwable $e) {
            Log::warning("[{$tag}] threw", ['err' => $e->getMessage(), 'msg_id' => $msg->id]);
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Resolution order:
     *   1. Workspace's wa_provider_configs row (the new clean way)
     *   2. Caller-supplied $platform argument (legacy 'W' / 'WB' / 'T')
     *   3. Fallback to env credentials inside each dispatch* method
     *
     * Why this order: once a workspace has connected via /connect, its
     * config row is the source of truth. The $platform arg only
     * matters for legacy scheduled messages that pre-date the config
     * table. The env fallback keeps dev environments working.
     */
    private function dispatch(Message $msg, string $platform, bool $scheduled): array
    {
        // Platform-wide emergency halt gate. When admin flips
        // `platform.emergency_send_halt` from /admin/security → Danger zone,
        // EVERY outbound send through this dispatcher refuses. The flag is
        // memoised per request so the lookup is one DB read per HTTP cycle.
        if (\App\Support\SendGate::halted()) {
            Log::warning('[DISPATCH] refused — emergency halt engaged', [
                'msg_id'      => $msg->id,
                'to_number'   => $msg->to_number,
            ]);
            return [
                'ok'        => false,
                'platform'  => 'halted',
                'local_only'=> true,
                'error'     => 'Platform emergency halt is engaged. Resume sends from /admin/security → Danger zone.',
            ];
        }

        // WhatsApp guardrails (admin's /admin/security rate caps + content
        // filters). No-op unless the admin set the master mode to monitor/
        // enforce; monitor only logs. Fail-open: a broken check never blocks.
        try {
            \App\Support\SendGate::screen(
                ((int) ($msg->workspace_id ?? 0)) ?: null,
                (string) $msg->body,
                ['msg_id' => $msg->id, 'to' => $msg->to_number]
            );
        } catch (\Throwable $e) {
            Log::warning('[DISPATCH] refused by security guardrail', ['msg_id' => $msg->id, 'reason' => $e->getMessage()]);
            return [
                'ok'         => false,
                'platform'   => 'blocked',
                'local_only' => true,
                'error'      => $e->getMessage(),
            ];
        }

        $resolved = $this->resolveProvider($msg, $platform);
        Log::info('[DISPATCH] entry', [
            'msg_id'      => $msg->id,
            'user_id'     => $msg->user_id,
            'from_number' => $msg->from_number,
            'to_number'   => $msg->to_number,
            'platform'    => $platform,
            'resolved'    => $resolved->value,
            'scheduled'   => $scheduled,
        ]);

        // Stamp the resolved engine on the message row so dashboards +
        // /devices KPI cards can filter by `provider` directly. Without
        // this, the WABA tab can't tell Baileys-sent messages from
        // WABA-sent ones, and the workspace switch leaves stale analytics.
        if (empty($msg->provider) && \Illuminate\Support\Facades\Schema::hasColumn('messages', 'provider')) {
            try {
                $msg->forceFill(['provider' => $resolved->value])->saveQuietly();
            } catch (\Throwable $e) {
                Log::debug('[DISPATCH] provider stamp failed: ' . $e->getMessage());
            }
        }

        // Align the in-memory provider to the engine we ACTUALLY resolved JUST
        // for the duration of the dispatch — dispatchNode() reads $msg->provider
        // to tell Node which channel to use, and resolveProvider() may have
        // fallen THROUGH a disabled/disconnected per-record pin to a different
        // engine. We RESTORE it immediately after: the attribute is backed by a
        // persisted column, and callers flush the model after dispatch
        // (ChatController::applyDispatchResult save(), TeamInboxController
        // update()), so leaving it dirty would overwrite the operator's stored
        // pin with the fallback engine and corrupt the next send + analytics.
        $providerBeforeDispatch = $msg->provider;
        $msg->provider = $resolved->value;

        try {
            // Single dispatch path: ALL sends go through Node. Node calls
            // GET /api/whatsapp-settings to read the workspace's engine +
            // credentials (Baileys session or WABA access_token + phone_id),
            // then routes the payload to either sock.sendMessage() (Baileys)
            // or sendMessageViaFacebookApi (Meta Cloud). PHP never holds
            // Meta credentials in memory during a send — they live in
            // wa_provider_configs (encrypted) and are handed to Node
            // on the per-phone settings request.
            //
            // Twilio still has its own PHP-direct path because Node has no
            // Twilio client; switch this once Node gains one.
            $result = match ($resolved) {
                WaProvider::Twilio  => $this->dispatchTwilio($msg, $scheduled),
                default             => $this->dispatchNode($msg, $scheduled),  // baileys + waba
            };
        } finally {
            // Restore the original (DB-synced) provider so no later save()
            // persists the transient alignment over the record's stored pin —
            // in finally so the restore holds even if a future dispatch refactor
            // lets an exception escape.
            $msg->provider = $providerBeforeDispatch;
        }

        Log::info('[DISPATCH] result', [
            'msg_id'      => $msg->id,
            'ok'          => $result['ok'] ?? null,
            'platform'    => $result['platform'] ?? null,
            'provider_id' => $result['provider_id'] ?? null,
            'local_only'  => $result['local_only'] ?? null,
            'error'       => $result['error'] ?? null,
        ]);
        return $result;
    }

    private function resolveProvider(Message $msg, string $platformHint): WaProvider
    {
        // Resolve workspace via 3 paths in order — relation, conversation
        // foreign key, or user foreign key — so the resolver works for
        // both saved messages with eager-loaded relations and
        // freshly-built models that just have user_id set.
        $workspaceId = $msg->workspace_id ?: optional($msg->conversation)->workspace_id;
        if (!$workspaceId && $msg->conversation_id) {
            $workspaceId = \App\Models\Conversation::query()->whereKey($msg->conversation_id)->value('workspace_id');
        }
        if (!$workspaceId && $msg->user_id) {
            $workspaceId = \App\Models\User::query()->whereKey($msg->user_id)->value('current_workspace_id');
        }
        if (!$workspaceId) {
            $workspaceId = optional(auth()->user())->current_workspace_id;
        }

        // Admin gate first — what providers are *allowed* on this
        // platform. Single-engine mode means allowed is e.g. ["baileys"]
        // and any stale wa_provider_configs row pointing at a different
        // provider must be ignored, otherwise the resolver would route
        // to Twilio (or another disabled provider) and return local_only.
        $allowed = SystemSetting::get('allowed_send_methods', ['baileys', 'waba', 'twilio']);
        $allowed = is_array($allowed) ? $allowed : [$allowed];

        // Phase 4 — per-record provider pin WINS. When Phase 3 already
        // stamped the operator-chosen engine onto this row (messages.provider),
        // honour it directly so a Baileys-picked send isn't silently
        // re-routed through a WABA/Twilio config that also exists in the same
        // multi-engine workspace. Only when admin-allowed AND actually
        // enabled (connected) on this workspace — otherwise fall through to
        // the existing primaryForWorkspace/default resolution so single-engine
        // (empty provider) workspaces stay byte-identical.
        if (!empty($msg->provider)) {
            $pinned = WaProvider::tryFrom((string) $msg->provider);
            // An EXPLICIT per-record provider (the operator chose this engine
            // for this campaign / broadcast / reply) is honoured whenever it's
            // admin-allowed — even if that engine is momentarily DISCONNECTED.
            // We must NEVER silently re-route a Baileys-pinned send through a
            // Twilio/WABA config that also exists in the same workspace: that
            // would deliver from the wrong number, or fail on another engine's
            // creds (e.g. a Twilio 401) for a send the operator pinned to
            // Baileys. If the pinned engine is genuinely down the send fails AS
            // that engine — truthful + retryable — instead of misrouting.
            // (Empty provider == legacy/single-engine → skips this block and
            // resolves via primaryForWorkspace/default exactly as before.)
            if ($pinned && in_array($pinned->value, $allowed, true)) {
                return $pinned;
            }
        }

        if ($workspaceId) {
            $cfg = WaProviderConfig::query()->primaryForWorkspace($workspaceId)->first();
            if ($cfg && $cfg->isConnected() && in_array($cfg->provider, $allowed, true)) {
                return $cfg->providerEnum();
            }
        }
        // Fall back to legacy hint (only respect non-default values),
        // then admin default.
        if ($platformHint !== '' && strtoupper($platformHint) !== 'W') {
            $hinted = WaProvider::fromLegacyCode($platformHint);
            if (in_array($hinted->value, $allowed, true)) {
                return $hinted;
            }
        }
        $adminDefault = SystemSetting::get('default_send_method', 'baileys');
        $resolved = WaProvider::tryFrom((string) $adminDefault) ?? WaProvider::Baileys;
        // If the admin default itself isn't in the allowed list (mis-config),
        // pick the first allowed provider rather than failing silently.
        if (!in_array($resolved->value, $allowed, true) && !empty($allowed)) {
            $resolved = WaProvider::tryFrom((string) $allowed[0]) ?? WaProvider::Baileys;
        }
        return $resolved;
    }

    // -----------------------------------------------------------------
    // W — Custom Node bridge (express + whatsapp-web.js etc.)
    // -----------------------------------------------------------------

    private function dispatchNode(Message $msg, bool $scheduled): array
    {
        // Resolve Node URL + sender phone from the workspace's
        // wa_provider_configs row first; fall back to env so dev
        // environments without a config row still work.
        $serverUrl = '';
        $from = '';
        // Prefer an explicitly-pinned workspace_id (webhook-triggered sends —
        // Slack/Trello/commerce notifiers — run with no auth user and the
        // connecting user may have since switched their active workspace).
        // Only fall back to the user's current workspace when none was pinned.
        $workspaceId = $msg->workspace_id
            ?: ($msg->user_id ? \App\Models\User::query()->whereKey($msg->user_id)->value('current_workspace_id') : null);
        $cfg = $workspaceId ? WaProviderConfig::query()->primaryForWorkspace($workspaceId)->first() : null;
        if ($cfg) {
            $creds = $cfg->creds();
            $serverUrl = (string) ($creds['server_url'] ?? '');
        }
        if ($serverUrl === '') {
            $serverUrl = (string) (\App\Models\SystemSetting::get('baileys_server_url') ?: env('SERVER_URL', ''));
        }

        // Per-message device wins (chat queues stamp from_number per-row
        // so multi-device sends route to the right Node session). Only
        // fall through to the workspace-level WaProviderConfig phone or
        // a generic "last connected device" lookup when the message
        // itself doesn't pin a device — e.g. legacy sendRaw() callers,
        // or queues created before multi-device went live.
        if ($msg->from_number) {
            $from = (string) $msg->from_number;
        }
        // Only seed the sender from the workspace's PRIMARY config when that
        // config is itself a Baileys engine. In a multi-engine workspace the
        // primary config can be Twilio/WABA — using its phone here would leak
        // that engine's number onto a Baileys send and Node, which keys its
        // Baileys sockets by the device phone, replies "CLIENT NOT FOUND".
        if ($from === '' && $cfg && $cfg->providerEnum() === WaProvider::Baileys) {
            $creds = $cfg->creds();
            $from = (string) ($cfg->phone_number ?: ($creds['phone_number'] ?? ''));
        }
        // Last resort: any connected Baileys device for this workspace. Gate on
        // workspace_id OR user_id (not user_id alone) so auth-less queue/sweeper
        // sends still resolve — campaign rows frequently carry a NULL user_id.
        if ($from === '' && ($msg->workspace_id || $msg->user_id)) {
            $device = \App\Models\Device::query()
                ->forWorkspace($msg->workspace_id ?? null, $msg->user_id)
                ->where('status', 'connected')
                ->orderByDesc('id')
                ->first();
            if ($device) {
                $from = preg_replace('/\D+/', '', (string) ($device->country_code . $device->phone_number));
            }
        }

        Log::info('[BAILEYS] resolved', [
            'msg_id'      => $msg->id,
            'workspace'   => $workspaceId,
            'server_url'  => $serverUrl,
            'from_number' => $from,
        ]);

        $base = rtrim($serverUrl, '/');
        if ($base === '') {
            Log::warning('[BAILEYS] no server url');
            return $this->localOnly('W', 'SERVER_URL not set and workspace has no Baileys config');
        }
        if ($from === '') {
            Log::warning('[BAILEYS] no from number');
            return ['ok' => false, 'platform' => 'W', 'provider_id' => null, 'local_only' => false, 'error' => 'No connected device — pair one at /devices first.'];
        }

        // Pick the right Node endpoint based on message shape.
        // Node has separate endpoints — sending {messageType: 'media'}
        // to the wrong one silently no-ops.
        [$path, $payload] = $this->buildNodeRequest($msg, $from, $scheduled);
        // Phase 4 — forward the resolved per-message engine so Node routes by
        // it (scheduleService-style: explicit provider wins, absent falls back
        // to the legacy use_facebook_api/isTwilioSettings heuristic). Omitted
        // when empty so single-engine workspaces keep Node's old behaviour.
        if (!empty($msg->provider)) {
            $payload['provider'] = (string) $msg->provider;
        }
        $url = $base . $path;

        Log::info('[BAILEYS] →', [
            'msg_id'  => $msg->id,
            'url'     => $url,
            'from'    => $from,
            'to'      => $payload['targetPhoneNumber'] ?? null,
            'payload' => $payload,
        ]);

        try {
            $started = microtime(true);
            // Larger timeout for media — inline base64 means body can
            // be a few MB and Node's WhatsApp upload step adds latency.
            $hasMedia = isset($payload['file_base64']) && $payload['file_base64'];
            $res = Http::timeout($hasMedia ? 60 : 20)->acceptJson()->asJson()->post($url, $payload);
            $tookMs = (int) ((microtime(true) - $started) * 1000);

            Log::info('[BAILEYS] ←', [
                'msg_id' => $msg->id,
                'status' => $res->status(),
                'ms'     => $tookMs,
                'body'   => mb_substr($res->body(), 0, 500),
            ]);

            if ($res->successful()) {
                return [
                    'ok'          => true,
                    'platform'    => 'W',
                    'provider_id' => $res->json('messageId') ?? $res->json('scheduleId') ?? $res->json('id'),
                    'local_only'  => false,
                    'error'       => null,
                ];
            }
            $err = $res->json('error') ?? $res->json('details') ?? $res->json('message') ?? ('HTTP ' . $res->status());
            return ['ok' => false, 'platform' => 'W', 'provider_id' => null, 'local_only' => false, 'error' => is_string($err) ? $err : json_encode($err)];
        } catch (\Throwable $e) {
            Log::warning('[BAILEYS] ✗ threw', ['msg_id' => $msg->id, 'url' => $url, 'error' => $e->getMessage()]);
            return ['ok' => false, 'platform' => 'W', 'provider_id' => null, 'local_only' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Map a Message to the correct Baileys Node endpoint.
     * Node exposes:
     *   POST /api/send-message/:phone        — text (with optional buttons)
     *   POST /api/send-media-message/:phone  — media + caption + optional buttons
     *   POST /api/send-media-only/:phone     — media without caption
     *   POST /api/send-location/:phone       — location
     *   POST /api/schedule-message/:phone    — scheduled (legacy single)
     */
    private function buildNodeRequest(Message $msg, string $from, bool $scheduled): array
    {
        $fromEnc = rawurlencode(preg_replace('/\D+/', '', $from) ?: $from);

        if ($scheduled && $msg->scheduled_at) {
            // Prefer the timezone the user picked when creating the queue
            // (stored on the conversation). Fall back to workspace tz, then
            // app default. Pass the wall-clock time IN that tz so Node's
            // moment.tz cron parser fires at the moment the user expects.
            $tz = optional($msg->conversation)->scheduled_timezone
                ?: ($msg->conversation_id ? \App\Models\Conversation::query()->whereKey($msg->conversation_id)->value('scheduled_timezone') : null)
                ?: optional(\App\Models\User::find($msg->user_id)?->currentWorkspace)->timezone
                ?: config('app.timezone', 'UTC');

            $localScheduled = $msg->scheduled_at->copy()->setTimezone($tz)->format('Y-m-d H:i:s');

            $schedulePayload = [
                'targetPhoneNumber' => $msg->to_number,
                'message'           => (string) $msg->body,
                'scheduleDateTime'  => $localScheduled,
                'timezone'          => $tz,
                'messageType'       => $this->resolveMessageType($msg),
                'mediaUrl'          => $msg->media_path ? media_url($msg->media_path) : null,
                'latitude'          => $msg->latitude,
                'longitude'         => $msg->longitude,
            ];
            // Group-targeted scheduled sends need the raw @g.us JID so Node
            // doesn't wrap the digits as @s.whatsapp.net and silently land
            // the message on a fabricated user account. The team-inbox path
            // stamps `meta.target_jid` for every group reply.
            $sMeta = is_array($msg->meta) ? $msg->meta : [];
            if (!empty($sMeta['target_jid'])) {
                $schedulePayload['targetJid'] = (string) $sMeta['target_jid'];
            }
            return ['/api/schedule-message/' . $fromEnc, $schedulePayload];
        }

        // Location — ONLY for a pure location pin (no buttons). The
        // /api/send-location endpoint sends just the pin, so a message that
        // also carries buttons (or a template whose location rides in
        // meta.header_location) must fall through to /api/send-message, which
        // renders the buttons and then ships the location pin afterwards.
        $hasButtons = is_array($msg->meta) && !empty($msg->meta['buttons']);
        if ($msg->latitude !== null && $msg->longitude !== null && !$hasButtons) {
            $locPayload = [
                'targetPhoneNumber' => $msg->to_number,
                'message'           => (string) ($msg->body ?: ''),
                'latitude'          => (float) $msg->latitude,
                'longitude'         => (float) $msg->longitude,
            ];
            // Group location pins need the raw @g.us JID — same reason as
            // /api/send-message / /api/send-media-message (otherwise Node
            // wraps the digits as @s.whatsapp.net and the pin lands on a
            // bogus user account).
            $locMeta = is_array($msg->meta) ? $msg->meta : [];
            if (!empty($locMeta['target_jid'])) {
                $locPayload['targetJid'] = (string) $locMeta['target_jid'];
            }
            return ['/api/send-location/' . $fromEnc, $locPayload];
        }

        // Media (with or without caption). We inline the file as base64
        // in the request body instead of pointing Node at the asset URL.
        // Two reasons:
        //   1. The PHP dev server is single-threaded — if Node tries to
        //      pull /storage/<path> while this controller is still
        //      blocking on the Node call, we deadlock for 20s.
        //   2. We can also pass the real mimetype + original filename
        //      so Baileys sends the doc with the correct extension and
        //      the recipient sees "foo.docx" not "file.bin".
        // Carousel templates carry their images inside each card, not as a
        // single top-level attachment — so even if a media_path is set, route
        // them through the TEXT path below which forwards carousel_data (the
        // media branch would drop the cards entirely).
        $isCarousel = is_array($msg->meta) && !empty($msg->meta['carousel_data']);

        if ($msg->media_path && !$isCarousel) {
            // Read from the active media disk (cloud when enabled, else local)
            // so base64-inline + real mime keep working from the bucket too.
            $mediaDisk = media_storage();
            // media_path is normally a RELATIVE disk key. It can ALSO be an
            // absolute URL — a per-send header-media OVERRIDE the operator
            // uploaded/pasted in the "Fill in this send" panel. When that URL is
            // OUR OWN storage (the usual case), map it back to the disk key and
            // base64-inline it so Node never has to FETCH it. That matters on a
            // LAN / private-IP install: otherwise Node's SSRF guard rejects our
            // own http://192.168.x.x/storage/... link ("Blocked internal media
            // host") and the send fails. Only a GENUINELY external URL (a file
            // not on our disk) is passed through as a plain link.
            $isUrl   = is_string($msg->media_path) && preg_match('#^https?://#i', $msg->media_path);
            $diskKey = $msg->media_path;
            if ($isUrl) {
                $urlPath = (string) (parse_url($msg->media_path, PHP_URL_PATH) ?: '');
                // Local "public" disk serves under /storage/<key>; cloud disks
                // map the whole path to the key. Try the /storage/ split, else
                // the raw path — accept it only if the file really exists on us.
                $cand = ($p = stripos($urlPath, '/storage/')) !== false
                    ? ltrim(substr($urlPath, $p + 9), '/')
                    : ltrim($urlPath, '/');
                $diskKey = ($cand !== '' && $mediaDisk->exists($cand)) ? $cand : null; // null → truly external
            }
            $hasFile   = $diskKey !== null && $mediaDisk->exists($diskKey);
            $b64       = $hasFile ? base64_encode($mediaDisk->get($diskKey)) : null;
            $url       = $hasFile ? media_url($diskKey) : $msg->media_path; // external URL passes through
            $mimeReal  = $hasFile ? ($mediaDisk->mimeType($diskKey) ?: 'application/octet-stream') : 'application/octet-stream';
            // Recover the original filename — we stored as
            // "chat-media/<random>__<original-name>". For a URL, use its path's
            // basename (drop any query string) so Node gets a sane fileName.
            $base     = basename((string) (parse_url($msg->media_path, PHP_URL_PATH) ?: $msg->media_path));
            $origName = str_contains($base, '__') ? substr($base, strpos($base, '__') + 2) : $base;

            $shared = [
                'targetPhoneNumber' => $msg->to_number,
                'file'              => $url,           // legacy URL fallback
                'file_base64'       => $b64,           // inline content (preferred)
                'filetype'          => $mimeReal,      // real mime, not 'document'
                'fileName'          => $origName,
            ];
            // Voice-note flag — when Message::meta.ptt is true, ask Node
            // to send the audio as a push-to-talk message so WhatsApp
            // renders the round play-button voice-note bubble instead of
            // a generic audio file with filename.
            $metaArr = is_array($msg->meta) ? $msg->meta : [];
            if (!empty($metaArr['ptt'])) {
                $shared['ptt'] = true;
                $shared['voice'] = true; // alias for older bridge builds
                if ($msg->media_type === 'audio') {
                    // Force the opus/ogg mimetype that WhatsApp expects for
                    // voice notes — browsers can record as webm/opus but
                    // Baileys is happiest when we declare ogg here.
                    $shared['filetype'] = 'audio/ogg; codecs=opus';
                }
            }
            // Carry the raw JID through to Node so LID-routed chats land
            // on the right bubble (same as the text path).
            if (!empty($metaArr['target_jid'])) {
                $shared['targetJid'] = (string) $metaArr['target_jid'];
            }
            // Media WITH caption — inject buttons + footer from meta so a rich
            // product card (image on top, caption body, "Order Now" / "Add to
            // Cart" CTAs) reaches /api/send-media-message intact. Previously an
            // earlier body-only return dropped buttons/footer for captioned
            // media; build the extras first and use them here.
            $sharedWithExtras = $this->mergeButtonsFooter($shared, $msg);
            if ($msg->body) {
                return ['/api/send-media-message/' . $fromEnc, $sharedWithExtras + [
                    'caption' => (string) $msg->body,
                ]];
            }
            // Media WITHOUT caption — still carry buttons/footer/header so a
            // media template's CTAs aren't dropped.
            return ['/api/send-media-only/' . $fromEnc, $sharedWithExtras];
        }

        // Plain text — include buttons + footer from $msg->meta when
        // present so Node /api/send-message renders interactive Baileys
        // buttons rather than plain text.
        //
        // If meta carries a `target_jid` (set by team-inbox replies for
        // LID-routed chats), send it through so Node can call
        // `sock.sendMessage(<jid>, …)` directly instead of building a
        // JID from digits — which truncates LIDs to 12 chars and ends
        // up sending to the wrong number.
        $payload = [
            'targetPhoneNumber' => $msg->to_number,
            'message'           => (string) $msg->body,
        ];
        $meta = $msg->meta;
        if (is_array($meta)) {
            if (!empty($meta['target_jid'])) {
                $payload['targetJid'] = (string) $meta['target_jid'];
            }
            if (!empty($meta['template_type'])) {
                $payload['template_type'] = $meta['template_type'];
            }
            if (!empty($meta['carousel_data'])) {
                $payload['carousel_data'] = $meta['carousel_data'];
            }
        }
        return ['/api/send-message/' . $fromEnc, $this->mergeButtonsFooter($payload, $msg)];
    }

    /**
     * Inject `buttons` + `footer` + `title` from Message::meta into the
     * outgoing Node payload. Buttons come from the campaign builder's
     * `custom_buttons` array (each row is {type, text, value, url}).
     *
     * Per Itsukichan/Baileys README "Buttons Interactive Message":
     *   { text, title, subtitle, footer, interactiveButtons }
     *
     * We map:
     *   meta.header → title  (bold heading above body)
     *   meta.footer → footer (small italic line under buttons)
     *   meta.buttons → interactiveButtons (Baileys' native CTA buttons)
     */
    private function mergeButtonsFooter(array $payload, Message $msg): array
    {
        $meta = $msg->meta;
        // Defensive: meta can arrive as a JSON string (e.g. when the row was
        // hydrated without the array cast). Decode so buttons aren't silently
        // dropped by the is_array() guard below.
        if (is_string($meta)) {
            $decoded = json_decode($meta, true);
            $meta = is_array($decoded) ? $decoded : [];
        }

        \Illuminate\Support\Facades\Log::info('[TPL-BTN] mergeButtonsFooter', [
            'msg_id'        => $msg->id,
            'meta_type'     => gettype($msg->meta),
            'meta_buttons'  => $meta['buttons'] ?? null,
            'meta_keys'     => is_array($meta) ? array_keys($meta) : null,
        ]);

        if (!is_array($meta) || empty($meta)) {
            \Illuminate\Support\Facades\Log::warning('[TPL-BTN] meta empty/non-array — NO buttons sent', ['msg_id' => $msg->id]);
            return $payload;
        }

        if (!empty($meta['buttons']) && is_array($meta['buttons'])) {
            // Normalize each button into the shape Node's
            // formatInteractiveButtonsForBaileys expects. Templates
            // store all the type-specific data in `value`, while Node
            // reads `url` for visit_website / `value` for call_phone &
            // copy_code. We mirror the value into both fields so Node
            // doesn't need to know about the storage convention.
            $payload['buttons'] = array_values(array_filter(array_map(function ($b) {
                if (!is_array($b)) return null;
                // Read across EVERY key alias the app / template editor / web
                // form might use, so a CTA's URL/phone/code or its label is
                // never blanked here before Node's (tolerant) formatter runs.
                // The mobile app may send url/link, phone/phone_number, code/
                // copy_code, and label/display_text/title — match all of them.
                $type  = (string) ($b['type'] ?? $b['button_type'] ?? 'quick_reply');
                $text  = (string) ($b['text'] ?? $b['display_text'] ?? $b['title'] ?? $b['label'] ?? '');
                $value = (string) ($b['value'] ?? $b['url'] ?? $b['link'] ?? $b['phone_number'] ?? $b['phone'] ?? $b['copy_code'] ?? $b['code'] ?? '');
                // Keep the original url default (url/link only). The web sends
                // the URL in `value`, handled by the visit_website fallback
                // below — exactly as before — so web output is unchanged.
                $url   = (string) ($b['url'] ?? $b['link'] ?? '');
                // For visit_website templates the URL often rides in `value`.
                if (in_array($type, ['visit_website', 'cta_url', 'url'], true) && $url === '') $url = $value;
                // For call_phone with country_code, prefix it.
                if (in_array($type, ['call_phone', 'cta_call', 'call', 'phone'], true) && !empty($b['country_code'])) {
                    $cc = preg_replace('/\D+/', '', (string) $b['country_code']);
                    $vd = preg_replace('/\D+/', '', $value);
                    if ($cc && $vd && !str_starts_with($vd, $cc)) $value = $cc . $vd;
                }
                // Preserve the original keys (array_merge) and ADD the
                // normalized type/text/value/url on top — nothing the app
                // sent is lost, and Node always gets a usable shape.
                return array_merge($b, [
                    'type'  => $type,
                    'text'  => $text,
                    'value' => $value,
                    'url'   => $url,
                ]);
            }, $meta['buttons'])));
        }
        if (!empty($meta['footer'])) {
            $payload['footer'] = (string) $meta['footer'];
        }
        if (!empty($meta['header'])) {
            $payload['title'] = (string) $meta['header'];
        }
        // LOCATION header — pass the {latitude, longitude, name, address}
        // through so Node can ship a location pin after the message.
        if (!empty($meta['header_location']) && is_array($meta['header_location'])) {
            $payload['location'] = $meta['header_location'];
        }
        \Illuminate\Support\Facades\Log::info('[TPL-BTN] mergeButtonsFooter OUT', [
            'msg_id'         => $msg->id,
            'payload_buttons'=> $payload['buttons'] ?? null,
            'count'          => isset($payload['buttons']) ? count($payload['buttons']) : 0,
        ]);
        return $payload;
    }

    private function guessFileType(string $path): string
    {
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        return match ($ext) {
            'jpg','jpeg','png','gif','webp' => 'image/' . ($ext === 'jpg' ? 'jpeg' : $ext),
            'mp4','mov','webm'              => 'video/' . $ext,
            'mp3','wav','ogg','m4a'         => 'audio/' . $ext,
            'pdf'                           => 'application/pdf',
            default                         => 'application/octet-stream',
        };
    }

    // -----------------------------------------------------------------
    // WB — Meta WhatsApp Cloud / Business API (DEAD CODE — see note)
    // -----------------------------------------------------------------

    /**
     * NOT WIRED. `dispatch()` routes WABA through `dispatchNode()` so
     * Node — which already has the workspace's per-phone WABA creds
     * cached — owns the Graph API call. Keeping this method here as a
     * documented reference for the PHP-direct path in case we ever
     * need to bypass Node (e.g. Node-down failover).
     *
     * If you reach this comment from a stack trace, something has
     * re-wired `dispatch()` to call this directly — that's a regression.
     */
    private function dispatchMetaCloud(Message $msg, bool $scheduled): array
    {
        // Per-workspace path is the only path now. Env-based tokens
        // (FACEBOOK_API_TOKEN / FACEBOOK_WP_ID) are no longer used —
        // every send pulls creds from wa_provider_configs.credentials_json
        // (encrypted at rest, decrypted via $cfg->creds()).
        $cfg = $this->resolveWabaConfig($msg);
        if (! $cfg) {
            return $this->localOnly('WB', 'No connected WABA account for this workspace. Connect one at /devices.');
        }
        $creds   = $cfg->creds();
        $meta    = is_array($cfg->meta_json) ? $cfg->meta_json : [];
        $token   = (string) ($creds['access_token'] ?? '');
        $phoneId = (string) ($meta['phone_number_id'] ?? '');
        if ($token === '' || $phoneId === '') {
            return ['ok' => false, 'platform' => 'WB', 'provider_id' => null, 'local_only' => false,
                    'error' => 'WABA account is missing access_token or phone_number_id. Re-connect at /devices.'];
        }

        // Admin-configurable Graph API version. Default v23.0 — current
        // stable with ~12 months runway. Latest is v25.0 (Feb 2026) but
        // we don't auto-jump until backwards-compat is verified.
        $version = (string) \App\Models\SystemSetting::get('waba_graph_api_version', 'v23.0');
        $base    = 'https://graph.facebook.com/' . ltrim($version, '/');

        if ($scheduled) {
            return ['ok' => true, 'platform' => 'WB', 'provider_id' => null, 'local_only' => true, 'error' => null];
        }

        $to = preg_replace('/\D+/', '', (string) $msg->to_number);
        $body = $this->buildCloudPayload($msg, $to);

        try {
            $res = Http::withToken($token)
                ->acceptJson()
                ->timeout(10)
                ->post("{$base}/{$phoneId}/messages", $body);

            if ($res->successful()) {
                return [
                    'ok'          => true,
                    'platform'    => 'WB',
                    'provider_id' => $res->json('messages.0.id'),
                    'local_only'  => false,
                    'error'       => null,
                ];
            }

            $err     = (array) ($res->json('error') ?? []);
            $errCode = (int) ($err['code'] ?? 0);
            // Meta's REAL error words (error_user_msg / error_data.details + code
            // + trace), not a paraphrase — so the operator sees the exact reason.
            $errMsg  = \App\Services\Waba\MetaError::describe($err) ?: ('HTTP ' . $res->status());

            // Typed hints so chat UI can react (e.g. show "Send template"
            // CTA when the 24h customer-service window has expired).
            $hint = match (true) {
                $errCode === 131047 => 'requires_template',   // 24h customer-service window closed
                $errCode === 132001 => 'template_missing',    // template not found / wrong language
                $errCode === 130429,
                $errCode === 80007  => 'rate_limited',        // throughput cap / WABA tier
                $errCode === 131056 => 'pair_throttled',      // sender↔recipient pair throttled
                $errCode === 131026 => 'undeliverable',       // not WA user / ToS
                default             => null,
            };

            Log::warning('Meta Cloud dispatch failed', [
                'status'   => $res->status(),
                'code'     => $errCode,
                'hint'     => $hint,
                'msg_id'   => $msg->id,
                'pnid'     => $phoneId,
                'to'       => $to,
                'body'     => mb_substr($res->body(), 0, 500),
            ]);
            return ['ok' => false, 'platform' => 'WB', 'provider_id' => null, 'local_only' => false,
                    'error' => $errMsg, 'hint' => $hint, 'error_code' => $errCode];
        } catch (\Throwable $e) {
            Log::warning('Meta Cloud dispatch threw', ['error' => $e->getMessage()]);
            return ['ok' => false, 'platform' => 'WB', 'provider_id' => null, 'local_only' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Multi-WABA resolver. Order:
     *   1. If message has a from_number, find the row whose
     *      display_phone_number (in meta_json) or phone_number column matches.
     *   2. Otherwise return the workspace's primary WABA row.
     *   3. null if neither — caller treats as misconfig.
     */
    private function resolveWabaConfig(Message $msg): ?\App\Models\WaProviderConfig
    {
        $wsId = $msg->workspace_id;
        if (! $wsId) return null;

        if (! empty($msg->from_number)) {
            $row = \App\Models\WaProviderConfig::query()
                ->matchingPhoneNumber($wsId, (string) $msg->from_number)
                ->where('provider', 'waba')
                ->connected()
                ->first();
            if ($row) return $row;
        }
        return \App\Models\WaProviderConfig::query()
            ->primaryForWorkspace($wsId)
            ->where('provider', 'waba')
            ->connected()
            ->first();
    }

    /**
     * Build a Cloud API outbound payload. Verified envelope per Meta
     * docs (May 2026): {messaging_product, recipient_type:'individual',
     * to, type, ...}. Threaded replies use top-level
     * `context.message_id`. All sourced from $msg->meta where multiple
     * shapes are possible per type.
     */
    private function buildCloudPayload(Message $msg, string $to): array
    {
        $meta = is_array($msg->meta) ? $msg->meta : (json_decode((string) $msg->meta, true) ?: []);
        $envelope = [
            'messaging_product' => 'whatsapp',
            'recipient_type'    => 'individual',
            'to'                => $to,
        ];
        if (! empty($meta['reply_to_wamid'])) {
            $envelope['context'] = ['message_id' => (string) $meta['reply_to_wamid']];
        }

        // ── Reaction ───────────────────────────────────────────────
        // Empty emoji un-reacts. reaction_target_wamid required.
        if (! empty($meta['reaction_target_wamid'])) {
            return $envelope + [
                'type'     => 'reaction',
                'reaction' => [
                    'message_id' => (string) $meta['reaction_target_wamid'],
                    'emoji'      => (string) ($msg->reaction ?? ''),
                ],
            ];
        }

        // ── Interactive button (quick-reply) ───────────────────────
        // $meta['buttons'] = [{ id, title (≤20 chars) }, ...max 3]
        if (! empty($meta['buttons']) && is_array($meta['buttons'])) {
            $btns = array_slice($meta['buttons'], 0, 3);
            return $envelope + [
                'type'        => 'interactive',
                'interactive' => [
                    'type' => 'button',
                    'body' => ['text' => (string) $msg->body],
                    'action' => [
                        'buttons' => array_map(fn ($b, $i) => [
                            'type'  => 'reply',
                            'reply' => [
                                'id'    => (string) ($b['id'] ?? ('btn_' . $i)),
                                'title' => mb_substr((string) ($b['title'] ?? 'Reply'), 0, 20),
                            ],
                        ], $btns, array_keys($btns)),
                    ],
                ],
            ];
        }

        // ── Media ──────────────────────────────────────────────────
        if ($msg->media_path) {
            $mediaType = $msg->media_type ?: 'document';
            $url       = media_url($msg->media_path);
            $mediaPayload = ['link' => $url];
            if ($mediaType === 'document') {
                $mediaPayload['filename'] = basename((string) $msg->media_path);
            }
            // Audio renders as a voice note when source is OGG/opus —
            // no separate ptt flag exists. Caption isn't supported on audio.
            if ($mediaType !== 'audio' && $msg->body) {
                $mediaPayload['caption'] = $msg->body;
            }
            return $envelope + [
                'type'     => $mediaType,
                $mediaType => array_filter($mediaPayload),
            ];
        }

        // ── Location ───────────────────────────────────────────────
        if ($msg->latitude !== null && $msg->longitude !== null) {
            return $envelope + [
                'type'     => 'location',
                'location' => [
                    'latitude'  => (float) $msg->latitude,
                    'longitude' => (float) $msg->longitude,
                ],
            ];
        }

        // ── Plain text (fallback) ─────────────────────────────────
        return $envelope + [
            'type' => 'text',
            'text' => [
                'body'        => (string) $msg->body,
                'preview_url' => (bool) ($meta['preview_url'] ?? false),
            ],
        ];
    }

    // -----------------------------------------------------------------
    // T — Twilio
    // -----------------------------------------------------------------

    private function dispatchTwilio(Message $msg, bool $scheduled): array
    {
        // Resolve creds per-workspace from WaProviderConfig (where
        // /connect → Twilio saves them as encrypted creds_json). Falls
        // back to admin SystemSetting `twilio_*` rows, then env vars
        // for legacy installs. Without this resolver every workspace's
        // Twilio sends were attributed to platform env vars — meaning
        // multi-tenant Twilio was effectively single-tenant.
        $sid = $token = $from = '';
        $sandbox = false;

        if ($msg->workspace_id) {
            $cfg = WaProviderConfig::query()
                ->where('workspace_id', $msg->workspace_id)
                ->where('provider', WaProvider::Twilio->value)
                ->where('status', WaProviderConfig::STATUS_CONNECTED)
                ->first();
            if ($cfg) {
                $creds   = $cfg->creds();
                $sid     = (string) ($creds['account_sid']  ?? '');
                $token   = (string) ($creds['auth_token']   ?? '');
                $from    = (string) ($creds['from_number']  ?? $cfg->phone_number ?? '');
                $sandbox = (bool)   ($creds['sandbox']      ?? ($cfg->meta_json['sandbox'] ?? false));
            }
        }
        if ($sid === '')   $sid   = (string) SystemSetting::get('twilio_account_sid', env('TWILIO_ACCOUNT_SID', ''));
        if ($token === '') $token = (string) SystemSetting::get('twilio_auth_token', env('TWILIO_AUTH_TOKEN', ''));
        if ($from === '')  $from  = (string) SystemSetting::get('twilio_whatsapp_number', env('TWILIO_WHATSAPP_NUMBER', ''));

        if ($sid === '' || $token === '' || $from === '') {
            return $this->localOnly('T', 'Twilio creds missing — connect Twilio at /connect or set admin defaults at /admin/settings.');
        }

        // Sandbox mode: Twilio's free WhatsApp sandbox only accepts the
        // shared sender `whatsapp:+14155238886`. When the workspace flag
        // is set, route through that From regardless of the configured
        // production number so test sends actually deliver. Previously
        // this flag was read but never used — toggling the UI radio had
        // no effect on the actual send.
        if ($sandbox) {
            $from = '14155238886';
        }

        // Twilio's WhatsApp transport requires `whatsapp:+E164` — the `+`
        // is part of the spec, not optional. Without it Twilio still
        // accepts most sends but flags as "E.164 format violation" and
        // can silently drop on certain carriers. Always emit the `+`.
        $to       = 'whatsapp:+' . preg_replace('/\D+/', '', (string) $msg->to_number);
        $fromBare = preg_replace('/\D+/', '', (string) $from);
        $payload  = ['From' => 'whatsapp:+' . $fromBare, 'To' => $to];

        // StatusCallback — Twilio only POSTs `delivered`/`read`/`failed`
        // events when we register a public URL on the send. Without
        // this, every Twilio-sent message stays frozen at `sent` forever
        // and the broadcasts/messages tables never receive delivery
        // ticks. The /api/twilio/status receiver validates the
        // X-Twilio-Signature header so the public URL is safe to ship.
        $appBase = rtrim((string) config('app.url'), '/');
        if ($appBase !== '') {
            $payload['StatusCallback'] = $appBase . '/api/twilio/status';
        }

        // Twilio Content Templates: when the message references a template
        // that has a registered `twilio_content_sid` in our DB, switch from
        // the plain-Body path to Twilio's ContentSid+ContentVariables path.
        // This is what keeps MARKETING/UTILITY/AUTHENTICATION compliant —
        // sending those categories as free-text Body outside the 24h
        // session window risks Twilio number suspension.
        $contentSid = null;
        if ($msg->template_id) {
            $tpl = \App\Models\WaTemplate::find($msg->template_id);
            $contentSid = $tpl?->twilio_content_sid ? trim((string) $tpl->twilio_content_sid) : null;
        }

        if ($contentSid) {
            $payload['ContentSid']       = $contentSid;
            $payload['ContentVariables'] = $this->buildTwilioContentVariables($msg);
        } elseif ($msg->media_path) {
            $payload['MediaUrl'] = media_url($msg->media_path);
            if ($msg->body) $payload['Body'] = $msg->body;
        } elseif ($msg->latitude !== null && $msg->longitude !== null) {
            $payload['PersistentAction'] = ['geo:' . $msg->latitude . ',' . $msg->longitude];
            // No-emoji fallback per project style: a plain string survives
            // every Twilio renderer and matches the SVG-only UI policy.
            $payload['Body'] = $msg->body ?: 'Location';
        } else {
            $payload['Body'] = (string) $msg->body;
        }

        try {
            $res = Http::withBasicAuth($sid, $token)
                ->asForm()
                ->timeout(10)
                ->post("https://api.twilio.com/2010-04-01/Accounts/{$sid}/Messages.json", $payload);

            if ($res->successful()) {
                return [
                    'ok'          => true,
                    'platform'    => 'T',
                    'provider_id' => $res->json('sid'),
                    'local_only'  => false,
                    'error'       => null,
                ];
            }
            $err = $res->json('message') ?? ('HTTP ' . $res->status());
            Log::warning('Twilio dispatch failed', ['status' => $res->status(), 'body' => $res->body()]);
            return ['ok' => false, 'platform' => 'T', 'provider_id' => null, 'local_only' => false, 'error' => $err];
        } catch (\Throwable $e) {
            Log::warning('Twilio dispatch threw', ['error' => $e->getMessage()]);
            return ['ok' => false, 'platform' => 'T', 'provider_id' => null, 'local_only' => false, 'error' => $e->getMessage()];
        }
    }

    // -----------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------

    /**
     * Build Twilio's ContentVariables JSON for a template send. Twilio
     * expects a stringified JSON object keyed by POSITIONAL index — e.g.
     * `{"1":"John","2":"ABC123"}` — that maps onto the `{{1}}` / `{{2}}`
     * placeholders in the Content Builder template body. Named keys like
     * `"name"` are NOT supported by Twilio's substitution engine.
     *
     * We source the variable values from:
     *  1. `Message::meta['template_vars']` — set by the sender (broadcasts /
     *     campaigns build a per-recipient map before dispatch)
     *  2. The OTP code from `Message::meta['otp_code']` for AUTHENTICATION
     *     templates (slot "1" by convention)
     *  3. The template's `variable_map['body']` keys resolved against the
     *     Message's contact attributes when meta.template_vars is absent
     */
    private function buildTwilioContentVariables(Message $msg): string
    {
        $meta  = is_array($msg->meta) ? $msg->meta : [];
        $vars  = is_array($meta['template_vars'] ?? null) ? $meta['template_vars'] : [];

        // Auth template: OTP always lives at position "1".
        if (!empty($meta['otp_code']) && !isset($vars['1'])) {
            $vars['1'] = (string) $meta['otp_code'];
        }

        // Normalize keys to strings (Twilio rejects integer keys when the
        // payload is rebuilt from JSON on their side).
        $out = [];
        foreach ($vars as $k => $v) {
            $out[(string) $k] = is_scalar($v) ? (string) $v : json_encode($v);
        }

        // Encode without escaping forward slashes so URLs in variables
        // (button parameters etc.) ship clean.
        return json_encode($out, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '{}';
    }

    private function resolveMessageType(Message $msg): string
    {
        if ($msg->latitude !== null && $msg->longitude !== null) return 'location';
        if (!$msg->media_path) return 'text';
        return $msg->body ? 'media_with_caption' : 'media';
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
     * Resolve the workspace owning this send and enforce the
     * monthly_messages_limit with credit-overflow:
     *   - Under cap → free
     *   - Over cap + wallet credits → deduct 1, allow
     *   - Over cap + wallet empty → throw
     *
     * Counts outbound messages across `messages` and `inbox_messages` —
     * one quota shared across every send surface in the app.
     */
    private function guardMonthlyMessagesLimit(Message $msg): void
    {
        // Prefer the pinned workspace_id (Slack/Trello/commerce webhooks set it
        // explicitly — they run with no auth user, and the connecting user may
        // have since switched their active workspace). Only fall back to the
        // user's current/first workspace for ordinary authenticated sends.
        // Without this, a Slack/Trello send could meter the monthly quota
        // against the wrong workspace. Mirrors dispatchNode's resolution.
        $workspaceId = $msg->workspace_id ?: null;
        if (!$workspaceId && $msg->user_id) {
            $workspaceId = \DB::table('users')->where('id', $msg->user_id)->value('current_workspace_id');
            if (!$workspaceId) {
                $workspaceId = \DB::table('workspace_user')->where('user_id', $msg->user_id)->value('workspace_id');
            }
        }
        if (!$workspaceId) return; // no workspace = unmetered (legacy / system sends)

        $workspace = \App\Models\Workspace::find($workspaceId);
        if (!$workspace) return;

        $monthStart = now()->startOfMonth();
        $userIds = \DB::table('workspace_user')->where('workspace_id', $workspaceId)->pluck('user_id');
        if ($userIds->isEmpty()) $userIds = collect([$msg->user_id]);

        $used = \DB::table('messages')
            ->whereIn('user_id', $userIds)
            ->where('direction', 'out')
            ->where('created_at', '>=', $monthStart)
            ->count();
        $used += \App\Models\InboxMessage::query()
            ->whereHas('conversation', fn ($q) => $q->where('workspace_id', $workspaceId))
            ->where('direction', 'out')
            ->where('created_at', '>=', $monthStart)
            ->count();

        // Per-country pricing inputs: recipient number + message category. A
        // template send bills at its Meta category (marketing/utility/auth);
        // a free-form session reply bills as 'service' (often free). The
        // resolver no-ops to the flat rate when per-country pricing is OFF.
        $billCategory = $msg->template_id
            ? (optional(\App\Models\WaTemplate::find($msg->template_id))->category ?: null)
            : 'service';
        \App\Services\OverflowBilling::consumeOne($workspace, $used, $msg->to_number, $billCategory);
    }
}
