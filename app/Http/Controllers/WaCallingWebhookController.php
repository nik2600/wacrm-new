<?php

namespace App\Http\Controllers;

use App\Models\Contact;
use App\Models\Conversation;
use App\Models\SystemSetting;
use App\Models\WaCall;
use App\Models\WaCallEvent;
use App\Models\WaProviderConfig;
use App\Services\WaCalling\WaCallingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Meta → us webhook for WhatsApp Cloud-API calling events.
 *
 *   POST /webhooks/wa-calling
 *     X-Hub-Signature-256: sha256=<hmac of body with app_secret>
 *     body: { object: "whatsapp_business_account", entry: [ { id, changes: [
 *               { field: "calls", value: { messaging_product, metadata, calls: [
 *                   { id, from, to, timestamp, event, direction, session?, ... }
 *               ] } }
 *             ] } ] }
 *
 * Three event types matter:
 *
 *   connect            — incoming user-initiated call OR our business-initiated dial picked up
 *   terminate          — call ended (either side or timeout)
 *   permission_update  — user accepted/declined a call_permission_request
 *
 * The AI handoff is wired via Laravel's app()->terminating() — when a
 * call.connect lands, the controller responds 200 immediately AND
 * registers a delayed callback that re-reads the row after
 * `auto_pickup_delay_sec` seconds. If still ringing, the AI fallback
 * fires (voicemail TTS for now; full Pipecat in a follow-up). No
 * queue worker required.
 */
class WaCallingWebhookController extends Controller
{
    public function __construct(private readonly WaCallingService $svc) {}

    /**
     * GET /webhooks/wa-calling — Meta's verification handshake (same
     * shape as the regular messages webhook).
     */
    public function verify(Request $request): mixed
    {
        $mode      = $request->query('hub_mode');
        $token     = $request->query('hub_verify_token');
        $challenge = $request->query('hub_challenge');
        // Admin stores this at /admin/settings/wadesk-message → "Webhook
        // verify token". Single source of truth, no env fallback so a
        // partial setup doesn't accidentally validate.
        $expected  = (string) SystemSetting::get('waba_webhook_verify_token', '');
        if ($mode === 'subscribe' && $token === $expected && $expected !== '') {
            return response($challenge, 200);
        }
        return response('verification failed', 403);
    }

    public function receive(Request $request): JsonResponse
    {
        // Acknowledge fast — Meta retries aggressively on > 5s response
        // times. Heavy work goes onto terminating().
        $payload = $request->all();
        if (!is_array($payload) || empty($payload['entry'])) {
            return response()->json(['ok' => true, 'noop' => 'empty entry']);
        }

        // Signature verification — Meta signs every webhook with
        // X-Hub-Signature-256. We REQUIRE the secret (no dev-mode pass)
        // because every call to this endpoint mutates state for a
        // workspace and the caller is otherwise unauthenticated.
        //
        // Admin stores this at /admin/settings/wadesk-message → "WABA
        // app secret". DB is the only source of truth.
        $secret = (string) SystemSetting::get('waba_app_secret', '');
        if ($secret === '') {
            Log::error('[WA-CALLING] waba_app_secret not configured — refusing webhook. Set it at /admin/settings/wadesk-message.');
            return response()->json(['ok' => false, 'error' => 'server misconfigured'], 500);
        }
        $given  = (string) $request->header('X-Hub-Signature-256', '');
        $actual = 'sha256=' . hash_hmac('sha256', (string) $request->getContent(), $secret);
        if (!hash_equals($actual, $given)) {
            Log::warning('[WA-CALLING] signature mismatch', ['given' => substr($given, 0, 16)]);
            return response()->json(['ok' => false, 'error' => 'bad signature'], 401);
        }

        foreach ($payload['entry'] as $entry) {
            $wabaId  = (string) ($entry['id'] ?? '');
            $changes = $entry['changes'] ?? [];
            foreach ($changes as $change) {
                if (($change['field'] ?? null) !== 'calls') continue;
                $value = $change['value'] ?? [];
                $this->routeValue($value, $wabaId);
            }
        }

        return response()->json(['ok' => true]);
    }

    /**
     * Entry point for the MAIN WABA webhook to forward a `calls` change here.
     * Meta delivers ALL webhooks (messages AND calls/permission) to the ONE
     * configured callback URL — which is WaWebhookController, not this route — so
     * without this bridge the `calls` field (incl. permission_update) was dropped
     * and call permission never recorded. Signature is already verified upstream.
     */
    public function ingestCallsValue(array $value, string $wabaId = ''): void
    {
        $this->routeValue($value, $wabaId);
    }

    /* ──────────────────────── routing ────────────────────────── */

    private function routeValue(array $value, string $wabaId): void
    {
        $phoneId = $value['metadata']['phone_number_id'] ?? null;
        // TRACE: every calls-webhook that reaches us, with the events inside.
        Log::info('[WA-CALLING][trace] routeValue', [
            'phone_id' => $phoneId,
            'waba_id'  => $wabaId,
            'events'   => array_map(fn ($c) => ($c['event'] ?? '?') . ':' . ($c['direction'] ?? '?'), (array) ($value['calls'] ?? [])),
        ]);
        if (!$phoneId) { Log::warning('[WA-CALLING][trace] no phone_number_id in calls webhook'); return; }

        $cfg = $this->configByPhoneId($phoneId);
        if (!$cfg) {
            Log::warning('[WA-CALLING][trace] no provider config for phone_id — call dropped', ['phone_id' => $phoneId]);
            return;
        }

        foreach ((array) ($value['calls'] ?? []) as $callPayload) {
            $event = $callPayload['event'] ?? null;
            try {
                match ($event) {
                    'connect'           => $this->handleConnect($cfg, $callPayload, $value),
                    'terminate'         => $this->handleTerminate($cfg, $callPayload),
                    'permission_update' => $this->handlePermissionUpdate($cfg, $callPayload),
                    default             => Log::info('[WA-CALLING] unknown event', ['event' => $event]),
                };
            } catch (\Throwable $e) {
                Log::error('[WA-CALLING] event handler threw', [
                    'event' => $event,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    private function handleConnect(WaProviderConfig $cfg, array $payload, array $value): void
    {
        $metaCallId = $payload['id'] ?? null;
        if (!$metaCallId) return;

        $direction = ($payload['direction'] ?? 'USER_INITIATED');
        $from = preg_replace('/\D+/', '', (string) ($payload['from'] ?? ''));
        $to   = preg_replace('/\D+/', '', (string) ($payload['to'] ?? ''));

        // Reuse an existing row if we minted one client-side (outbound
        // dial). Match on meta_call_id (set after our dial returned the id)
        // OR on biz_opaque_callback_data (our correlation_id echo).
        $call = WaCall::query()
            ->where('meta_call_id', $metaCallId)
            ->orWhere('correlation_id', $payload['biz_opaque_callback_data'] ?? '___none___')
            ->first();

        // Business-initiated (operator dialed from the browser): this
        // connect webhook carries Meta's SDP *answer* to our offer. The
        // operator's browser peer is waiting on GET /wa-calling/calls/{id}
        // /answer to apply it — so we stamp status='active' (not 'ringing')
        // and keep the answer in meta_payload for that poll to hand back.
        // We detect it two ways: the session says sdp_type=answer, OR the
        // row we minted at dial time is already BUSINESS_INITIATED.
        $session = is_array($payload['session'] ?? null) ? $payload['session'] : [];
        $sdpType = strtolower((string) ($session['sdp_type'] ?? ''));
        $isOutboundAnswer = $sdpType === 'answer'
            || strtoupper((string) ($call?->direction ?? $direction)) === 'BUSINESS_INITIATED';

        // Match the inbound caller to a Contact. `mobile` is encrypted
        // at rest so we can't SQL-filter on the ciphertext — hydrate
        // every contact owned by a workspace member and compare the
        // decrypted digits in PHP. Workspaces typically have hundreds
        // of contacts, not millions, so this is cheap. Same pattern
        // AutoReplyController + storefront parser already use.
        $memberIds = $cfg->workspace_id ? $this->workspaceUserIds($cfg->workspace_id) : [];
        $contact = $memberIds
            ? Contact::whereIn('user_id', $memberIds)->get()
                ->first(function ($c) use ($from) {
                    $digits = preg_replace('/\D+/', '', (string) (($c->country_code ?? '') . $c->mobile));
                    $plain  = preg_replace('/\D+/', '', (string) $c->mobile);
                    return $digits === $from || $plain === $from;
                })
            : null;
        // `conversations` has no contact_id FK — chats are matched by
        // their raw_jid (digits + "@s.whatsapp.net" historically; for
        // WABA we store the caller phone). Try the JID form first,
        // then the bare-digit form for forward-compat.
        $conversation = null;
        if ($from !== '') {
            $jid = $from . '@s.whatsapp.net';
            $conversation = Conversation::where('workspace_id', $cfg->workspace_id)
                ->where(function ($q) use ($jid, $from) {
                    $q->where('raw_jid', $jid)->orWhere('raw_jid', $from);
                })
                ->first();
        }

        $data = [
            'workspace_id'          => $cfg->workspace_id,
            'wa_provider_config_id' => $cfg->id,
            'meta_call_id'          => $metaCallId,
            'direction'             => $direction,
            'from_phone'            => $from,
            'to_phone'              => $to,
            'contact_id'            => $contact?->id,
            'conversation_id'       => $conversation?->id,
            'status'                => $isOutboundAnswer ? 'active' : 'ringing',
            'started_at'            => isset($payload['timestamp']) ? \Carbon\Carbon::createFromTimestamp((int) $payload['timestamp']) : now(),
            'meta_payload'          => $payload,
        ];

        if ($call) {
            $call->forceFill($data)->save();
        } else {
            $call = WaCall::create($data);
        }

        WaCallEvent::create([
            'wa_call_id'  => $call->id,
            'event_type'  => 'connect',
            'payload'     => $payload,
            'received_at' => now(),
        ]);

        // Mirror into ai_call_logs so the call is visible in /call-logs
        // from the first ring (status=in-progress). Best-effort — never
        // 500 a Meta webhook because of a mirror failure.
        try {
            app(\App\Services\Calling\WaCallToAiLogBridge::class)->onConnect($call->fresh());
        } catch (\Throwable $e) {
            Log::warning('[WA-CALLING] log mirror onConnect failed: ' . $e->getMessage());
        }

        // Ask Node to spin up the WebRTC bridge for live AI pickup.
        // Node will mint an SDP answer, attach STT → LLM → TTS, and
        // POST back here when the audio loop is live. If Node is
        // down or the assistant isn't configured, we fall through to
        // the existing AiFallback voicemail timer below.
        try {
            $assistantId = $call->fresh()->assistant_id;
            Log::info('[WA-CALLING][trace] handleConnect AI dispatch check', [
                'call'         => $call->id,
                'meta_call_id' => $call->meta_call_id,
                'direction'    => $direction,
                'assistant_id' => $assistantId,
            ]);
            if (!$assistantId) {
                Log::warning('[WA-CALLING][trace] no assistant resolved for this call → AI will NOT answer. Set an assistant Live + as the workspace default call assistant. (Voicemail fallback only.)', ['call' => $call->id]);
            }
            if ($assistantId) {
                // node URL + token + graph version come from
                // /admin/settings/wadesk-message + /admin/settings/general — the URL
                // + token each fall back to the installer's .env (SERVER_URL /
                // NODE_WEBHOOK_TOKEN) through the shared resolvers, so a fresh
                // install where the admin hasn't re-saved the settings page still
                // answers calls instead of silently skipping the bridge.
                $nodeUrl   = wd_node_url();
                // Single shared-secret resolver (DB node_webhook_token →
                // legacy baileys_callback_token → .env). Without it the token
                // is empty on a standard install and the bridge below is
                // silently skipped → AI never answers.
                $nodeToken = node_token();
                $version   = (string) SystemSetting::get('waba_graph_api_version', 'v23.0');
                if ($nodeUrl !== '' && $nodeToken !== '') {
                    // Pull the Meta access token + phone_number_id from the
                    // workspace's WaProviderConfig so Node can hit Meta's
                    // /calls endpoint without any env keys. Per
                    // [[feedback_admin_billing_ux]] all keys are admin-
                    // managed; this call passes them per-session.
                    $creds = $cfg->creds();
                    // phone_number_id lives in meta_json (creds() only carries
                    // the encrypted access_token) — reading it from creds()
                    // shipped an empty id to Node, so the bridge couldn't
                    // pre_accept/accept/terminate at Meta ("missing credentials").
                    $metaCfg = is_array($cfg->meta_json ?? null) ? $cfg->meta_json : [];
                    $phoneNumberId = (string) ($creds['phone_number_id'] ?? ($metaCfg['phone_number_id'] ?? ''));
                    Log::info('[WA-CALLING][trace] dispatching to Node bridge', [
                        'call'           => $call->id,
                        'node_url'       => rtrim($nodeUrl, '/') . '/api/waba-call/answer',
                        'has_meta_token' => !empty($creds['access_token']) ? 'yes' : 'NO',
                        'has_phone_id'   => $phoneNumberId !== '' ? 'yes' : 'NO',
                        'has_sdp_offer'  => !empty($payload['session']['sdp']) ? 'yes' : 'NO',
                    ]);
                    $resp = \Illuminate\Support\Facades\Http::withHeaders([
                            'X-Node-Token' => $nodeToken,
                        ])
                        ->timeout(8)
                        ->acceptJson()
                        ->post(rtrim($nodeUrl, '/') . '/api/waba-call/answer', [
                            'wa_call_id'      => $call->id,
                            'meta_call_id'    => $call->meta_call_id,
                            'workspace_id'    => $call->workspace_id,
                            'assistant_id'    => $assistantId,
                            'caller_phone'    => $call->from_phone,
                            'callee_phone'    => $call->to_phone,
                            'sdp_offer'       => $payload['session']['sdp'] ?? null,
                            'meta_token'      => (string) ($creds['access_token'] ?? ''),
                            'phone_number_id' => $phoneNumberId,
                            'graph_version'   => $version,
                            'node_token'      => $nodeToken,
                        ]);
                    Log::info('[WA-CALLING][trace] Node bridge /answer replied', [
                        'call'   => $call->id,
                        'status' => $resp->status(),
                        'body'   => mb_substr($resp->body(), 0, 300),
                    ]);
                } else {
                    Log::warning('[WA-CALLING][trace] Node bridge skipped: node URL or token missing', [
                        'node_url_set'   => $nodeUrl !== '' ? 'yes' : 'NO',
                        'node_token_set' => $nodeToken !== '' ? 'yes' : 'NO',
                    ]);
                }
            }
        } catch (\Throwable $e) {
            Log::warning('[WA-CALLING] Node bridge call failed: ' . $e->getMessage());
        }

        // AI handoff timer. Operator gets ~auto_pickup_delay_sec to
        // answer (default 15s); if nothing happens we route to the
        // voicemail fallback. Done via terminating() so the webhook
        // response goes out first — Meta sees a 200 in <1s.
        //
        // set_time_limit(0) inside the callback because PHP's default
        // 30s cap counts THIS request's total wall time — including
        // the sleep + the TTS round-trip the fallback may issue.
        // Without it a long fallback could be killed mid-stride.
        $workspace = $cfg->workspace;
        $delay = (int) ($workspace?->auto_pickup_delay_sec ?? 15);
        app()->terminating(function () use ($call, $delay) {
            @set_time_limit(0);
            try {
                sleep(max(1, $delay));
                $fresh = WaCall::find($call->id);
                if (!$fresh || !$fresh->isActive()) return;  // operator answered
                if ($fresh->status !== 'ringing') return;     // already in motion
                app(\App\Services\WaCalling\AiFallback::class)->trigger($fresh);
            } catch (\Throwable $e) {
                Log::error('[WA-CALLING] AI fallback timer threw: ' . $e->getMessage());
            }
        });
    }

    private function handleTerminate(WaProviderConfig $cfg, array $payload): void
    {
        $metaCallId = $payload['id'] ?? null;
        if (!$metaCallId) return;

        $call = WaCall::where('meta_call_id', $metaCallId)->first();
        if (!$call) return;

        $start = $payload['start_time'] ?? null;
        $end   = $payload['end_time']   ?? null;
        $duration = (int) ($payload['duration'] ?? 0);

        $call->forceFill([
            'status'       => 'ended',
            'end_reason'   => (string) ($payload['status'] ?? 'COMPLETED'),
            'ended_at'     => $end ? \Carbon\Carbon::createFromTimestamp((int) $end) : now(),
            'duration_sec' => $duration,
            'error_payload'=> $payload['errors'] ?? null,
        ])->save();

        WaCallEvent::create([
            'wa_call_id'  => $call->id,
            'event_type'  => 'terminate',
            'payload'     => $payload,
            'received_at' => now(),
        ]);

        // Drop a proper "call" entry into the chat thread (like WhatsApp shows
        // "Voice call · 4 min" / "Missed voice call") so the operator sees the
        // call in the timeline instead of a blank "Message unavailable" row.
        try { $this->mirrorCallToInbox($cfg, $call->fresh(), $payload); }
        catch (\Throwable $e) { Log::warning('[WA-CALLING] call→inbox mirror failed: ' . $e->getMessage()); }

        // Final mirror — fill duration, status, recording URL,
        // transcript into the /call-logs row so the operator can
        // replay + read after the call ends.
        try {
            app(\App\Services\Calling\WaCallToAiLogBridge::class)->onTerminate($call->fresh(), $payload);
        } catch (\Throwable $e) {
            Log::warning('[WA-CALLING] log mirror onTerminate failed: ' . $e->getMessage());
        }
    }

    /**
     * Write ONE "call" InboxMessage into the contact's conversation so the call
     * appears in the team-inbox thread like WhatsApp's own chat entry
     * ("Voice call · 4 min" / "Missed voice call"). Idempotent per meta_call_id.
     */
    private function mirrorCallToInbox(WaProviderConfig $cfg, WaCall $call, array $payload): void
    {
        // Resolve the conversation this call belongs to (connect usually stamped
        // it; fall back to matching the contact number in this workspace).
        $conversationId = $call->conversation_id;
        if (!$conversationId) {
            $from = preg_replace('/\D+/', '', (string) $call->from_phone);
            if ($from === '') return;
            $convo = Conversation::where('workspace_id', $cfg->workspace_id)
                ->where(function ($q) use ($from) {
                    $q->where('raw_jid', $from)->orWhere('raw_jid', $from . '@s.whatsapp.net');
                })
                ->latest('id')->first();
            $conversationId = $convo?->id;
        }
        if (!$conversationId) return;

        $callId = (string) $call->meta_call_id;
        // A retried terminate webhook must NOT add a second bubble.
        $exists = \App\Models\InboxMessage::query()
            ->where('conversation_id', $conversationId)
            ->whereJsonContains('meta->call_id', $callId)
            ->exists();
        if ($exists) return;

        $dir      = strtoupper((string) $call->direction) === 'BUSINESS_INITIATED' ? 'out' : 'in';
        $duration = (int) ($call->duration_sec ?? 0);
        $answered = $duration > 0;
        $reason   = strtoupper((string) ($call->end_reason ?? ($payload['status'] ?? '')));
        $status   = $answered
            ? 'answered'
            : (in_array($reason, ['DECLINED', 'REJECTED'], true)
                ? 'declined'
                : ($dir === 'out' ? 'no_answer' : 'missed'));

        // Plain-text label doubles as the fallback body + conversation preview.
        $label = $answered
            ? 'Voice call · ' . $this->humanCallDuration($duration)
            : ($status === 'declined' ? 'Voice call declined'
                : ($dir === 'out' ? 'Voice call · no answer' : 'Missed voice call'));

        \App\Models\InboxMessage::create([
            'conversation_id' => $conversationId,
            'user_id'         => optional(Conversation::find($conversationId))->user_id,
            'direction'       => $dir,
            'from_number'     => $dir === 'in'  ? (string) $call->from_phone : null,
            'to_number'       => $dir === 'out' ? (string) $call->from_phone : null,
            'body'            => $label,
            'media_type'      => 'call',
            'meta'            => [
                'event'          => 'call',
                'call_id'        => $callId,
                'call_status'    => $status,        // answered | missed | no_answer | declined
                'call_direction' => $dir,           // in | out
                'duration_sec'   => $duration,
            ],
            'status'          => 'received',
            'sent_at'         => $call->ended_at ?: now(),
            'delivered_at'    => now(),
        ]);

        // Keep the conversation list preview + sort order fresh.
        try {
            Conversation::whereKey($conversationId)->update([
                'preview'         => mb_substr($label, 0, 191),
                'last_message_at' => now(),
            ]);
        } catch (\Throwable $e) { /* preview is best-effort */ }
    }

    /** Seconds → "4 min" / "1 min 12 s" / "45 s" for the call bubble label. */
    private function humanCallDuration(int $sec): string
    {
        if ($sec <= 0) return '0 s';
        $m = intdiv($sec, 60);
        $s = $sec % 60;
        if ($m > 0) return $s > 0 ? "{$m} min {$s} s" : "{$m} min";
        return "{$s} s";
    }

    private function handlePermissionUpdate(WaProviderConfig $cfg, array $payload): void
    {
        $from   = (string) ($payload['from'] ?? '');
        $resp   = (string) ($payload['response'] ?? '');
        $expTs  = isset($payload['expiration_timestamp']) ? (int) $payload['expiration_timestamp'] : null;
        if ($from === '' || $resp === '') return;

        $this->svc->recordPermissionUpdate($cfg, $from, $resp, $expTs);

        // Forensic log — no wa_calls row exists for permission events
        // (they precede any actual call). Stored under a synthetic
        // wa_call_id=0 row would break FK; we just log to file.
        Log::info('[WA-CALLING] permission_update', [
            'cfg_id' => $cfg->id, 'phone' => $from, 'response' => $resp,
        ]);
    }

    /* ─────────────────────── helpers ─────────────────────────── */

    private function configByPhoneId(string $phoneId): ?WaProviderConfig
    {
        // phone_number_id lives inside credentials_json. We can't query
        // it directly (encrypted), so we load active WABA configs and
        // decrypt. Configs per install are few — typically 1, max ~3.
        // phone_number_id lives in meta_json (creds() only carries the encrypted
        // access token) — reading it from creds() alone always returned null, so
        // NO config matched and permission_update webhooks were silently dropped.
        return WaProviderConfig::where('provider', 'waba')->get()
            ->first(function ($c) use ($phoneId) {
                $creds = $c->creds();
                $meta  = is_array($c->meta_json) ? $c->meta_json : [];
                $pnid  = $creds['phone_number_id'] ?? ($meta['phone_number_id'] ?? null);
                return (string) $pnid === (string) $phoneId;
            });
    }

    private function workspaceUserIds(int $workspaceId): array
    {
        return \DB::table('workspace_user')->where('workspace_id', $workspaceId)->pluck('user_id')->all();
    }
}
