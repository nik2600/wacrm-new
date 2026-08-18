<?php

namespace App\Services\WaCalling;

use App\Models\WaCall;
use App\Models\WaCallEvent;
use App\Models\WaCallPermission;
use App\Models\WaProviderConfig;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Client over Meta's WhatsApp Cloud-API calling endpoints + local
 * ledger writes.
 *
 * Endpoints exercised:
 *
 *   POST graph.facebook.com/v21.0/<phone_id>/settings
 *        { calling: { status: ENABLED|DISABLED } }
 *
 *   POST graph.facebook.com/v21.0/<phone_id>/calls
 *        { messaging_product: "whatsapp", action: connect|accept|pre_accept|reject|terminate,
 *          call_id?: "...",                       (omitted on `connect` — Meta mints it)
 *          to?: "<E.164 user phone>",              (only on `connect`)
 *          session?: { sdp_type, sdp },            (on `connect` and `accept`)
 *          biz_opaque_callback_data?: "..." }      (any echo string we want)
 *
 *   POST graph.facebook.com/v21.0/<phone_id>/messages
 *        { messaging_product: "whatsapp", to: "...", type: "interactive",
 *          interactive: { type: "call_permission_request", action: { name: "call_permission_request" }, body: { text } } }
 *
 * Every action POST results in a wa_call_events row regardless of
 * Meta's success/failure so a forensic post-mortem can replay the
 * call's full lifecycle.
 */
class WaCallingService
{
    private const DEFAULT_VERSION = 'v23.0';

    public function __construct(private readonly int $timeoutSeconds = 15) {}

    /* ────────────────────── settings (Phase 1) ───────────────────── */

    public function enable(WaProviderConfig $cfg): array  { return $this->toggleSettings($cfg, 'ENABLED'); }
    public function disable(WaProviderConfig $cfg): array { return $this->toggleSettings($cfg, 'DISABLED'); }

    public function fetchState(WaProviderConfig $cfg): array
    {
        [$version, $phoneId, $token] = $this->resolveCreds($cfg);
        $url = sprintf('https://graph.facebook.com/%s/%s/settings?fields=calling', $version, $phoneId);
        $res = Http::withToken($token)->timeout($this->timeoutSeconds)->acceptJson()->get($url);
        if (!$res->successful()) throw new RuntimeException('Could not read calling state: ' . $res->status() . ' ' . $res->body());
        return $res->json() ?: [];
    }

    /* ─────────────────── action verbs (Phase 2/3) ────────────────── */

    /**
     * Business-initiated dial. Caller supplies the SDP offer generated
     * by their WebRTC peer (browser-side). Meta returns a call_id which
     * becomes the handle for accept/reject/terminate.
     *
     *   action = connect
     *   to     = recipient E.164
     *   session.sdp_type = offer
     */
    public function dialOutbound(WaProviderConfig $cfg, string $to, string $sdpOffer, ?int $contactId = null, ?int $conversationId = null): WaCall
    {
        $this->assertPermission($cfg, $to);

        $correlation = (string) Str::uuid();
        $call = WaCall::create([
            'workspace_id'          => $cfg->workspace_id,
            'wa_provider_config_id' => $cfg->id,
            'correlation_id'        => $correlation,
            'direction'             => 'BUSINESS_INITIATED',
            'from_phone'            => preg_replace('/\D+/', '', (string) $cfg->phone_number),
            'to_phone'              => preg_replace('/\D+/', '', $to),
            'contact_id'            => $contactId,
            'conversation_id'       => $conversationId,
            'status'                => 'ringing',
            'started_at'            => now(),
        ]);

        $body = $this->postCallsAction($cfg, [
            'messaging_product' => 'whatsapp',
            'to'                => preg_replace('/\D+/', '', $to),
            'action'            => 'connect',
            'session'           => ['sdp_type' => 'offer', 'sdp' => $sdpOffer],
            'biz_opaque_callback_data' => $correlation,
        ]);

        $callId = $body['calls'][0]['id'] ?? null;
        if ($callId) {
            $call->forceFill(['meta_call_id' => $callId, 'meta_payload' => $body])->save();
        }
        $this->logEvent($call, 'dial_sent', $body);

        return $call;
    }

    /**
     * Accept an incoming user-initiated call. SDP answer comes from the
     * operator's browser WebRTC peer (after setRemoteDescription on the
     * offer + createAnswer).
     */
    public function acceptIncoming(WaCall $call, string $sdpAnswer): array
    {
        $cfg = $call->providerConfig;
        if (!$cfg) throw new RuntimeException('Call has no provider config.');
        if (!$call->meta_call_id) throw new RuntimeException('Call has no Meta id — cannot accept.');

        $body = $this->postCallsAction($cfg, [
            'messaging_product' => 'whatsapp',
            'call_id'           => $call->meta_call_id,
            'action'            => 'accept',
            'session'           => ['sdp_type' => 'answer', 'sdp' => $sdpAnswer],
        ]);

        $call->forceFill([
            'status'      => 'active',
            'answered_at' => now(),
        ])->save();
        $this->logEvent($call, 'accept_sent', $body);

        return $body;
    }

    /**
     * Soft commit — stops the customer's ringer but doesn't bridge
     * audio yet. Used by the AI handoff to buy time while a Pipecat
     * session spins up. Customer still hears their own ringtone-like
     * tone but Meta won't auto-terminate.
     */
    public function preAccept(WaCall $call, ?string $sdpAnswer = null): array
    {
        $cfg = $call->providerConfig;
        if (!$cfg) throw new RuntimeException('Call has no provider config.');

        $payload = [
            'messaging_product' => 'whatsapp',
            'call_id'           => $call->meta_call_id,
            'action'            => 'pre_accept',
        ];
        // When the operator/browser already has its SDP answer, carry it in
        // pre_accept too. Meta establishes the media path on pre_accept, and
        // the SDP in pre_accept + accept MUST be byte-identical or the call
        // is rejected — so the caller passes the same answer string to both
        // (mirrors the Node AI bridge in node/services/wabaCallBridge.js).
        if ($sdpAnswer !== null && $sdpAnswer !== '') {
            $payload['session'] = ['sdp_type' => 'answer', 'sdp' => $sdpAnswer];
        }

        $body = $this->postCallsAction($cfg, $payload);

        $call->forceFill(['status' => 'connecting'])->save();
        $this->logEvent($call, 'pre_accept_sent', $body);
        return $body;
    }

    public function reject(WaCall $call, ?string $reason = null): array
    {
        $cfg = $call->providerConfig;
        if (!$cfg) throw new RuntimeException('Call has no provider config.');

        $payload = [
            'messaging_product' => 'whatsapp',
            'call_id'           => $call->meta_call_id,
            'action'            => 'reject',
        ];
        if ($reason) $payload['reason'] = $reason;

        $body = $this->postCallsAction($cfg, $payload);

        $call->forceFill([
            'status'     => 'ended',
            'end_reason' => 'REJECTED',
            'ended_at'   => now(),
        ])->save();
        $this->logEvent($call, 'reject_sent', $body);
        return $body;
    }

    public function terminate(WaCall $call): array
    {
        $cfg = $call->providerConfig;
        if (!$cfg) throw new RuntimeException('Call has no provider config.');

        $body = $this->postCallsAction($cfg, [
            'messaging_product' => 'whatsapp',
            'call_id'           => $call->meta_call_id,
            'action'            => 'terminate',
        ]);

        // Don't mark `ended` yet — Meta will send a terminate webhook
        // with the authoritative end_reason + duration. We just record
        // that we issued the terminate so duplicate webhooks can
        // correlate.
        $this->logEvent($call, 'terminate_sent', $body);
        return $body;
    }

    /**
     * Ask the user to grant calling permission. Sent as a normal
     * interactive WhatsApp message via /messages. Meta replies with a
     * call_permission_update webhook on Accept/Decline.
     */
    public function requestPermission(WaProviderConfig $cfg, string $to, string $bodyText = "Can we give you a quick call?"): array
    {
        [$version, $phoneId, $token] = $this->resolveCreds($cfg);
        $url = sprintf('https://graph.facebook.com/%s/%s/messages', $version, $phoneId);

        $payload = [
            'messaging_product' => 'whatsapp',
            'to'                => preg_replace('/\D+/', '', $to),
            'type'              => 'interactive',
            'interactive'       => [
                'type'   => 'call_permission_request',
                // Meta requires the `action` object (name === the interactive type),
                // else it 400s "missing 'action'". Verified against the official
                // Obtain-User-Call-Permissions doc.
                'action' => ['name' => 'call_permission_request'],
                'body'   => ['text' => $bodyText],
            ],
        ];

        $res = Http::withToken($token)->timeout($this->timeoutSeconds)->acceptJson()->asJson()->post($url, $payload);
        if (!$res->successful()) {
            $code = (int) ($res->json('error.code') ?? 0);
            $err  = $res->json('error.message') ?? $res->body();

            // (#138017) "Unable to send call permission request as the business
            // account can already call this consumer." This is NOT a failure —
            // the customer has ALREADY granted calling permission (or is inside
            // the valid window), so there is nothing to request. Record the
            // permission locally as granted and tell the caller to just PLACE
            // THE CALL instead of surfacing a red error. expires_at=null →
            // WaCallPermission::isValid() treats it as "dial freely".
            if ($code === 138017) {
                WaCallPermission::updateOrCreate(
                    ['wa_provider_config_id' => $cfg->id, 'contact_phone' => preg_replace('/\D+/', '', $to)],
                    [
                        'workspace_id' => $cfg->workspace_id,
                        'status'       => 'granted',
                        'granted_at'   => now(),
                        'expires_at'   => null,
                    ],
                );
                return ['already_permitted' => true];
            }

            throw new RuntimeException("Meta refused permission_request ({$res->status()}): $err");
        }
        $body = $res->json() ?: [];

        // Reset the local cache so the UI re-checks after the user replies.
        WaCallPermission::updateOrCreate(
            ['wa_provider_config_id' => $cfg->id, 'contact_phone' => preg_replace('/\D+/', '', $to)],
            ['workspace_id' => $cfg->workspace_id, 'status' => 'expired'],
        );

        return $body;
    }

    /**
     * Persist the user's response to a permission request — called
     * from the webhook receiver when call_permission_update arrives.
     */
    public function recordPermissionUpdate(WaProviderConfig $cfg, string $contactPhone, string $response, ?int $expirationTs): void
    {
        $phone = preg_replace('/\D+/', '', $contactPhone);
        $status = $response === 'accept' ? 'granted' : 'declined';
        $expiresAt = $expirationTs ? \Carbon\Carbon::createFromTimestamp($expirationTs) : now()->addDays(7);

        WaCallPermission::updateOrCreate(
            ['wa_provider_config_id' => $cfg->id, 'contact_phone' => $phone],
            [
                'workspace_id' => $cfg->workspace_id,
                'status'       => $status,
                'granted_at'   => $status === 'granted' ? now() : null,
                'expires_at'   => $status === 'granted' ? $expiresAt : null,
            ],
        );
    }

    /* ───────────────────────── internals ─────────────────────────── */

    private function assertPermission(WaProviderConfig $cfg, string $to): void
    {
        $phone = preg_replace('/\D+/', '', $to);
        $perm = WaCallPermission::where('wa_provider_config_id', $cfg->id)
            ->where('contact_phone', $phone)
            ->first();
        // SOFT check — Meta is the final authority: its /calls `connect` rejects
        // the dial if the user hasn't allowed calls, with a clear error. Our local
        // WaCallPermission cache can be stale (e.g. a permission_update webhook was
        // missed before the webhook-wiring fix), so we must NOT hard-block here —
        // a customer who tapped "Allow" would otherwise be un-callable forever. Just
        // log when there's no usable local record and let the dial proceed.
        if (!$perm || !$perm->isUsable()) {
            \Log::info('[WA-CALLING] dialing without a usable local permission record — Meta will enforce', [
                'cfg_id' => $cfg->id, 'phone' => $phone,
            ]);
        }
    }

    private function postCallsAction(WaProviderConfig $cfg, array $payload): array
    {
        [$version, $phoneId, $token] = $this->resolveCreds($cfg);
        $url = sprintf('https://graph.facebook.com/%s/%s/calls', $version, $phoneId);

        $res = Http::withToken($token)->timeout($this->timeoutSeconds)->acceptJson()->asJson()->post($url, $payload);

        if (!$res->successful()) {
            $err = $res->json('error.message') ?? $res->body();
            Log::warning('[WA-CALLING] action POST failed', [
                'cfg_id'  => $cfg->id,
                'action'  => $payload['action'] ?? '?',
                'http'    => $res->status(),
                'err'     => $err,
            ]);
            throw new RuntimeException("Meta refused {$payload['action']} ({$res->status()}): $err");
        }
        return $res->json() ?: [];
    }

    private function toggleSettings(WaProviderConfig $cfg, string $status): array
    {
        if ($cfg->provider !== 'waba') throw new RuntimeException('Calling is only available on WABA numbers.');

        [$version, $phoneId, $token] = $this->resolveCreds($cfg);
        $url = sprintf('https://graph.facebook.com/%s/%s/settings', $version, $phoneId);

        $res = Http::withToken($token)->timeout($this->timeoutSeconds)->acceptJson()->asJson()
            ->post($url, ['calling' => ['status' => $status]]);

        if (!$res->successful()) {
            $err = $res->json('error.message') ?? $res->body();
            throw new RuntimeException("Meta refused the call toggle ({$res->status()}): $err");
        }
        $body = $res->json() ?: [];
        $cfg->forceFill([
            'calling_enabled'      => $status === 'ENABLED',
            'calling_enabled_at'   => $status === 'ENABLED' ? now() : null,
            'calling_enabled_meta' => $body,
        ])->save();
        return $body;
    }

    /** @return array{0:string,1:string,2:string} [version, phoneId, accessToken] */
    private function resolveCreds(WaProviderConfig $cfg): array
    {
        $creds = $cfg->creds();
        $meta  = is_array($cfg->meta_json) ? $cfg->meta_json : [];
        // phone_number_id lives in meta_json (that's where WabaHealthService +
        // the webhook read it); creds() only carries the encrypted access_token.
        // Reading it from creds() alone always came back null → the false
        // "Missing phone_number_id" error even though the number is fully set up.
        $phoneId = $creds['phone_number_id'] ?? ($meta['phone_number_id'] ?? null);
        $token   = $creds['access_token']    ?? ($meta['access_token']    ?? null);
        if (!$phoneId || !$token) {
            throw new RuntimeException('Missing phone_number_id / access_token on this WABA config.');
        }
        // Admin sets the Graph API version at /admin/settings/wadesk-message.
        // Single source of truth; no env fallback.
        $version = (string) \App\Models\SystemSetting::get('waba_graph_api_version', self::DEFAULT_VERSION);
        return [$version, $phoneId, $token];
    }

    private function logEvent(WaCall $call, string $type, array $payload = []): void
    {
        try {
            WaCallEvent::create([
                'wa_call_id'  => $call->id,
                'event_type'  => $type,
                'payload'     => $payload,
                'received_at' => now(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('[WA-CALLING] event log failed: ' . $e->getMessage());
        }
    }
}
