<?php

namespace App\Http\Controllers;

use App\Models\Conversation;
use App\Models\ConversationEvent;
use App\Models\Tag;
use App\Services\Inbox\AssignmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Endpoints the Node flow runtime calls when a TagContact / AssignAgent
 * node fires. Auth: X-Node-Token shared secret (no session). All routes
 * resolve a Conversation by workspace_id + customer phone, then mutate.
 *
 * These were silently missing from the executor's switch — flows
 * containing tag / assign nodes used to log "Unknown node type" and
 * advance without doing anything.
 */
class FlowNodeActionsController extends Controller
{
    private const TOKEN_HEADER = 'X-Node-Token';

    public function __construct(private AssignmentService $assignment) {}

    /** POST /api/flow-node/tag */
    public function tag(Request $request): JsonResponse
    {
        if (!$this->authed($request)) {
            return response()->json(['ok' => false, 'error' => 'unauthorized'], 401);
        }
        $data = $request->validate([
            'workspace_id'   => 'required|integer',
            'customer_phone' => 'required|string|max:32',
            'action'         => 'required|string|in:add,remove',
            'tag_id'         => 'nullable|integer',
            'tag_name'       => 'nullable|string|max:64',
        ]);

        $conv = $this->findConversation((int) $data['workspace_id'], (string) $data['customer_phone']);
        if (!$conv) {
            return response()->json(['ok' => false, 'error' => 'conversation_not_found'], 404);
        }

        // Resolve the tag by id first, then by name (create-on-the-fly
        // so flow authors don't have to pre-seed every tag they'll use).
        $tag = null;
        if (!empty($data['tag_id'])) {
            $tag = Tag::where('workspace_id', $conv->workspace_id)->find($data['tag_id']);
        }
        if (!$tag && !empty($data['tag_name'])) {
            $name = trim($data['tag_name']);
            $tag = Tag::firstOrCreate(
                ['workspace_id' => $conv->workspace_id, 'slug' => Str::slug($name)],
                ['name' => $name, 'color' => '#075E54'],
            );
        }
        if (!$tag) {
            return response()->json(['ok' => false, 'error' => 'tag_unresolved'], 422);
        }

        try {
            if ($data['action'] === 'add') {
                $conv->tags()->syncWithoutDetaching([$tag->id => ['added_by' => null]]);
                ConversationEvent::record($conv->id, $conv->workspace_id, null, 'tag_added', [
                    'tag_id'   => $tag->id,
                    'tag_name' => $tag->name,
                    'source'   => 'flow',
                ], 'flow');
            } else {
                $conv->tags()->detach($tag->id);
                ConversationEvent::record($conv->id, $conv->workspace_id, null, 'tag_removed', [
                    'tag_id'   => $tag->id,
                    'tag_name' => $tag->name,
                    'source'   => 'flow',
                ], 'flow');
            }
        } catch (\Throwable $e) {
            Log::warning('[flow-node/tag] ' . $e->getMessage());
            return response()->json(['ok' => false, 'error' => 'tag_failed', 'message' => $e->getMessage()], 500);
        }

        return response()->json(['ok' => true, 'tag_id' => $tag->id, 'action' => $data['action']]);
    }

    /**
     * POST /api/flow-node/google-meet
     * Node calls this from inside a GoogleMeet flow node. Creates a
     * Calendar event with conferenceData.createRequest so Google
     * generates a Meet URL, then returns it to Node. Node substitutes
     * the URL into the configured message template and ships it to the
     * customer via Baileys.
     */
    public function googleMeet(Request $request): JsonResponse
    {
        if (!$this->authed($request)) {
            return response()->json(['ok' => false, 'error' => 'unauthorized'], 401);
        }
        $data = $request->validate([
            'workspace_id'    => 'required|integer',
            'title'           => 'required|string|max:200',
            'start_at'        => 'required|string',  // ISO 8601
            'end_at'          => 'required|string',
            'description'     => 'nullable|string|max:1500',
            'attendee_emails' => 'nullable|array',
            'attendee_emails.*' => 'email',
            'time_zone'       => 'nullable|string|max:60',
            'send_invites'    => 'nullable|boolean',
            'calendar_id'     => 'nullable|string|max:200',
        ]);
        return $this->mintMeet($data);
    }

    /**
     * POST /team-inbox/api/google-meet
     * Same logic as the flow-node endpoint, but session-auth'd for the
     * team-inbox composer button (no shared secret needed since the
     * operator is already logged in).
     */
    public function googleMeetForInbox(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user || !$user->current_workspace_id) {
            return response()->json(['ok' => false, 'error' => 'no_workspace'], 401);
        }
        $data = $request->validate([
            'title'           => 'required|string|max:200',
            'start_at'        => 'required|string',
            'end_at'          => 'required|string',
            'description'     => 'nullable|string|max:1500',
            'attendee_emails' => 'nullable|array',
            'attendee_emails.*' => 'email',
            'time_zone'       => 'nullable|string|max:60',
            'send_invites'    => 'nullable|boolean',
            'calendar_id'     => 'nullable|string|max:200',
        ]);
        $data['workspace_id'] = $user->current_workspace_id;
        return $this->mintMeet($data);
    }

    /**
     * Shared helper for both Meet endpoints. Resolves the workspace's
     * connected calendar, validates the OAuth state, creates the event.
     */
    private function mintMeet(array $data): JsonResponse
    {
        $workspace = \App\Models\Workspace::find($data['workspace_id']);
        if (!$workspace) {
            return response()->json(['ok' => false, 'error' => 'workspace_not_found'], 404);
        }

        try {
            $start = \Illuminate\Support\Carbon::parse($data['start_at']);
            $end   = \Illuminate\Support\Carbon::parse($data['end_at']);
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'error' => 'bad_datetime'], 422);
        }
        if ($end->lte($start)) {
            return response()->json(['ok' => false, 'error' => 'end_before_start'], 422);
        }

        $gcal = app(\App\Services\GoogleCalendar\GoogleCalendarService::class);
        // Master toggle — admin can flip `google_calendar_enabled` off at
        // /admin/settings/google-calendar to disable Google integration
        // platform-wide WITHOUT having to wait for tokens to expire.
        // Without this gate, every previously-connected workspace would
        // keep running Meet/Calendar nodes for hours after the toggle.
        if (!$gcal->isEnabled()) {
            return response()->json([
                'ok'      => false,
                'error'   => 'integration_disabled',
                'message' => 'Google integration is disabled platform-wide. Ask your admin to re-enable it at /admin/settings/google-calendar.',
            ], 503);
        }
        $token = $gcal->ensureFreshToken($workspace);
        if (!$token) {
            return response()->json([
                'ok'      => false,
                'error'   => 'not_connected',
                'message' => 'Connect Google Calendar in Settings → Integrations first.',
            ], 422);
        }

        // Calendar id + timezone live INSIDE the google_oauth slot of
        // appointment_settings (same place /appointments/settings writes
        // them), not at the appointment_settings root. The previous
        // path was reading the wrong key and silently falling through
        // to 'primary' / 'UTC' every time.
        $oauthSettings = $workspace->appointment_settings['google_oauth'] ?? [];
        $calendarId = (string) ($data['calendar_id']
            ?? ($oauthSettings['calendar_id'] ?? 'primary'));
        $tz = (string) ($data['time_zone']
            ?? ($oauthSettings['calendar_timezone']
            ?? ($workspace->timezone ?: 'UTC')));

        $event = $gcal->createMeetEvent(
            workspace:   $workspace,
            calendarId:  $calendarId,
            summary:     $data['title'],
            start:       $start,
            end:         $end,
            attendees:   array_map(fn ($e) => ['email' => $e], $data['attendee_emails'] ?? []),
            description: $data['description'] ?? null,
            timeZone:    $tz,
            sendInvites: (bool) ($data['send_invites'] ?? false),
        );

        if (!$event || empty($event['hangoutLink'])) {
            return response()->json([
                'ok'      => false,
                'error'   => 'meet_create_failed',
                'message' => 'Google returned an event without a Meet link. Check workspace calendar scope.',
            ], 502);
        }

        return response()->json([
            'ok'        => true,
            'meet_url'  => (string) $event['hangoutLink'],
            'event_id'  => (string) ($event['id'] ?? ''),
            'start'     => $start->toIso8601String(),
            'end'       => $end->toIso8601String(),
            'time_zone' => $tz,
        ]);
    }

    /**
     * POST /api/flow-node/wa-form-send
     * Node calls this when a `wa_form` flow node fires. We mint a flow
     * token (carries the session_key so the submission webhook can
     * resume the paused flow), build the Meta interactive payload, and
     * POST to /{phone_id}/messages.
     */
    public function waFormSend(Request $request): JsonResponse
    {
        if (!$this->authed($request)) {
            return response()->json(['ok' => false, 'error' => 'unauthorized'], 401);
        }
        $data = $request->validate([
            'workspace_id'  => 'required|integer',
            'form_id'       => 'required|integer',
            'session_key'   => 'required|string|max:120',
            'target_phone'  => 'required|string|max:32',
            'body_text'     => 'nullable|string|max:1024',
            'cta_label'     => 'nullable|string|max:30',
        ]);

        $form = \App\Models\WaForm::where('workspace_id', $data['workspace_id'])->find($data['form_id']);
        if (!$form) return response()->json(['ok' => false, 'error' => 'form_not_found'], 404);
        if (!$form->isLive()) {
            return response()->json(['ok' => false, 'error' => 'form_not_published',
                'message' => 'Form must be published to Meta before it can be sent.'], 422);
        }

        $cfg = \App\Models\WaProviderConfig::where('workspace_id', $data['workspace_id'])
            ->where('provider', 'waba')->first();
        if (!$cfg) return response()->json(['ok' => false, 'error' => 'no_waba_config'], 422);
        $creds = $cfg->creds();
        $token = (string) ($creds['access_token'] ?? '');
        $phoneId = (string) ($creds['phone_number_id'] ?? '');
        if ($token === '' || $phoneId === '') {
            return response()->json(['ok' => false, 'error' => 'waba_creds_missing'], 422);
        }

        // flow_token format: form-<form_id>-<session_key>
        // The submission service parses this to find the form + the
        // paused session.
        $flowToken = 'form-' . $form->id . '-' . $data['session_key'];

        // Meta spec for navigate-mode flows: parameters MUST include
        // `flow_action_payload.screen` pointing at the FIRST screen
        // of the form. Without it Meta opens to a blank state and
        // recipients see "Flow couldn't load". Derive the screen id
        // from the form's screens array (we generate "SCREEN_0",
        // "SCREEN_1", … in buildMetaFlowJson — the first is always
        // SCREEN_0 unless the operator deleted everything).
        $defJson = is_array($form->definition_json) ? $form->definition_json : [];
        $screens = $defJson['screens'] ?? [];
        $firstScreenId = (count($screens) > 0) ? 'SCREEN_0' : 'SCREEN_0';

        $payload = [
            'messaging_product' => 'whatsapp',
            'recipient_type'    => 'individual',
            'to'                => preg_replace('/\D+/', '', $data['target_phone']),
            'type'              => 'interactive',
            'interactive'       => [
                'type' => 'flow',
                'body' => ['text' => (string) ($data['body_text'] ?? 'Please fill out our form.')],
                'action' => [
                    'name' => 'flow',
                    'parameters' => [
                        'flow_message_version' => '3',
                        'flow_token'           => $flowToken,
                        'flow_id'              => $form->meta_flow_id,
                        'flow_cta'             => (string) ($data['cta_label'] ?? 'Open form'),
                        'flow_action'          => 'navigate',
                        'mode'                 => 'published',
                        'flow_action_payload'  => [
                            'screen' => $firstScreenId,
                        ],
                    ],
                ],
            ],
        ];

        $version = (string) (env('META_GRAPH_VERSION') ?: 'v21.0');
        try {
            $resp = \Illuminate\Support\Facades\Http::withToken($token)
                ->acceptJson()->timeout(15)
                ->post("https://graph.facebook.com/{$version}/{$phoneId}/messages", $payload);
            if (!$resp->successful()) {
                \Log::warning('[waform-send] meta ' . $resp->status() . ': ' . substr($resp->body(), 0, 200));
                return response()->json(['ok' => false, 'error' => 'meta_send_failed',
                    'status' => $resp->status(), 'body' => $resp->json()], 502);
            }
            return response()->json([
                'ok' => true,
                'flow_token' => $flowToken,
                'meta' => $resp->json(),
            ]);
        } catch (\Throwable $e) {
            \Log::warning('[waform-send] exception: ' . $e->getMessage());
            return response()->json(['ok' => false, 'error' => 'send_exception',
                'message' => $e->getMessage()], 502);
        }
    }

    /**
     * GET /api/waba-creds?workspace_id=N
     * Returns the workspace's resolved WABA credentials (access_token +
     * phone_number_id) so Node can hit Meta's /messages endpoint
     * directly without round-tripping through Laravel. Auth: X-Node-Token.
     * The credentials_json column is encrypted; this endpoint is the
     * single trusted spot that decrypts + ships them to Node over the
     * shared-secret-authed channel.
     */
    public function wabaCreds(Request $request): JsonResponse
    {
        if (!$this->authed($request)) {
            return response()->json(['ok' => false, 'error' => 'unauthorized'], 401);
        }
        $request->validate(['workspace_id' => 'required|integer']);

        // A workspace can hold several WABA config rows (an old/half-set-up one
        // plus the real connected number). Plain ->first() returned the lowest-id
        // row, which was often a stale/empty one → Node got blank creds and the
        // wa_form / template nodes skipped ("no WABA creds"). Prefer connected +
        // primary + most-recently-connected, and — critically — return the first
        // config that ACTUALLY has both an access_token and a phone_number_id.
        $configs = \App\Models\WaProviderConfig::where('workspace_id', $request->integer('workspace_id'))
            ->where('provider', 'waba')
            ->orderByRaw("CASE WHEN status = '" . \App\Models\WaProviderConfig::STATUS_CONNECTED . "' THEN 0 ELSE 1 END")
            ->orderByDesc('is_primary')
            ->orderByDesc('connected_at')
            ->get();
        if ($configs->isEmpty()) return response()->json(['ok' => false, 'error' => 'no_waba_config'], 404);

        // The phone_number_id / waba_id are stored in meta_json (that's what the
        // inbound webhook matches on — WaWebhookController resolves the config via
        // meta_json['phone_number_id']), NOT necessarily in the encrypted
        // credentials_json. Read from creds first, fall back to meta_json — else a
        // number connected via embedded-signup (token in creds, ids in meta_json)
        // reports a blank phone_number_id and the send node skips.
        $pidOf = fn ($c) => (string) ($c->creds()['phone_number_id'] ?? '')
            ?: (string) (((array) ($c->meta_json ?? []))['phone_number_id'] ?? '');

        $cfg = null;
        foreach ($configs as $c) {
            if (!empty($c->creds()['access_token']) && $pidOf($c) !== '') { $cfg = $c; break; }
        }
        $cfg  = $cfg ?: $configs->first();
        $creds = $cfg->creds();
        $meta  = (array) ($cfg->meta_json ?? []);
        $phoneNumberId = (string) ($creds['phone_number_id'] ?? '') ?: (string) ($meta['phone_number_id'] ?? '');
        $wabaId        = (string) ($creds['waba_id']         ?? '') ?: (string) ($meta['waba_id']         ?? '');

        // DIAGNOSTIC — which config + which credential is present (from creds OR meta_json).
        \Log::info('[WABA-CREDS] resolved', [
            'workspace_id'        => $request->integer('workspace_id'),
            'config_count'        => $configs->count(),
            'config_ids'          => $configs->pluck('id')->all(),
            'picked_cfg_id'       => $cfg->id,
            'picked_status'       => $cfg->status,
            'has_access_token'    => !empty($creds['access_token']),
            'access_token_len'    => strlen((string) ($creds['access_token'] ?? '')),
            'phone_number_id'     => $phoneNumberId,
            'pid_source'          => !empty($creds['phone_number_id']) ? 'creds' : (!empty($meta['phone_number_id']) ? 'meta_json' : 'none'),
        ]);

        return response()->json([
            'ok'              => true,
            'access_token'    => (string) ($creds['access_token'] ?? ''),
            'phone_number_id' => $phoneNumberId,
            'waba_id'         => $wabaId,
            'graph_version'   => (string) (env('META_GRAPH_VERSION') ?: 'v21.0'),
        ]);
    }

    /**
     * GET /api/waba-call/voice-keys
     * Returns the workspace-scoped resolved keys for STT (Deepgram) +
     * TTS (ElevenLabs) so Node doesn't need them in its own env. Same
     * BYOK-then-admin-then-env resolution policy as text models.
     */
    public function wabaCallVoiceKeys(Request $request): JsonResponse
    {
        if (!$this->authed($request)) {
            return response()->json(['ok' => false, 'error' => 'unauthorized'], 401);
        }
        $request->validate(['workspace_id' => 'required|integer']);
        $wsId      = $request->integer('workspace_id');
        $workspace = \App\Models\Workspace::find($wsId);

        // Resolve each provider AND capture its source (workspace BYOK / admin /
        // none) so we can trace exactly which key is being sent to each vendor.
        // Never log the raw key — only a masked fingerprint (first3…last3 + len)
        // that's enough to tell "wrong key" from "right key" without leaking it.
        $mask = fn (?string $k) => ($k !== null && $k !== '')
            ? substr($k, 0, 3) . '…' . substr($k, -3) . ' len' . strlen($k)
            : 'ABSENT';
        $out  = [];
        $diag = [];
        foreach (['deepgram', 'openai', 'elevenlabs'] as $p) {
            $r = \App\Services\AiKeyResolver::resolve($workspace, $p);
            $out[$p]  = (string) ($r['key'] ?? '');
            $diag[$p] = ($r['source'] ?? 'none') . ':' . $mask($r['key'] ?? null);
        }
        \Illuminate\Support\Facades\Log::info(
            "[WABA-CALL][voice-keys] ws={$wsId} " . json_encode($diag)
        );

        // Keys flow through AiKeyResolver only (workspace BYOK → admin DB row).
        // STT: Deepgram is the low-latency default, but OpenAI realtime STT works
        // too — Node uses whichever key exists.
        return response()->json(['ok' => true] + $out);
    }

    /**
     * GET /api/waba-call/assistant/{id}
     * Node fetches the full assistant config (system prompt, model,
     * tools, voice id) when a WABA call lands and it needs to spin up
     * the audio loop. Auth: X-Node-Token (same as the rest).
     */
    public function wabaCallAssistant(Request $request, int $id): JsonResponse
    {
        if (!$this->authed($request)) {
            return response()->json(['ok' => false, 'error' => 'unauthorized'], 401);
        }
        // Workspace-scope the lookup. Node passes `workspace_id` in
        // either the query string (?workspace_id=) or the JSON body
        // depending on the call path — accept both. Without this, a
        // valid Node token could fetch any workspace's assistant
        // config by guessing the assistant id.
        $wsId = (int) ($request->query('workspace_id', $request->input('workspace_id', 0)));
        if ($wsId <= 0) {
            return response()->json(['ok' => false, 'error' => 'workspace_required'], 400);
        }
        $a = \App\Models\AiCallAssistant::with('tools')
            ->where('workspace_id', $wsId)
            ->find($id);
        if (!$a) {
            return response()->json(['ok' => false, 'error' => 'not_found'], 404);
        }
        // Workspace plan gates — caller MUST have access_call_recording
        // to record either side. Without the gate, Node would happily
        // dump PCM to disk for a free-tier workspace.
        $ws = \App\Models\Workspace::find($wsId);
        $canRecord = \App\Services\PlanLimitGuard::hasFeature($ws, 'access_call_recording');

        return response()->json([
            'ok' => true,
            'assistant' => [
                'id'                   => $a->id,
                'name'                 => $a->name,
                'ai_provider'          => $a->ai_provider,
                'ai_model'             => $a->ai_model,
                'ai_system_prompt'     => $a->ai_system_prompt,
                'voice_provider'       => $a->voice_provider,
                'voice_id'             => $a->voice_id,
                'stt_provider'         => $a->stt_provider,
                'natural_conciseness'  => $a->natural_conciseness,
                'exit_keywords'        => $a->exit_keywords_json ?? [],
                'last_greeting'        => $a->last_greeting,
                'greeting_text'        => $a->greeting_text ?? '',
                // Per-side recording flags, ANDed with the plan gate so
                // Node never writes PCM for a workspace whose plan
                // doesn't allow recording.
                'record_agent'         => $canRecord && (bool) ($a->record_agent ?? true),
                'record_user'          => $canRecord && (bool) ($a->record_user  ?? true),
                'meta'                 => $a->meta_json ?? [],
                'tools'                => $a->tools->map(fn ($t) => [
                    'function_name'    => $t->function_name,
                    'trigger_keywords' => $t->trigger_keywords_json ?? [],
                    'http_method'      => $t->http_method,
                    'http_url'         => $t->http_url,
                    'headers'          => $t->headers_json ?? [],
                    'parameters'       => $t->parameters_json ?? [],
                ])->values(),
            ],
        ]);
    }

    /**
     * POST /api/waba-call/bridge-accepted
     * Node calls this immediately after the AI bridge successfully
     * POSTs `accept` to Meta. Flips wa_calls.status='ringing' → 'active'
     * atomically (lockForUpdate) so the AI-fallback terminating timer
     * scheduled in handleConnect sees status='active' on its retry and
     * bails instead of dropping a voicemail on top of a live AI call.
     *
     * Idempotent — second call no-ops because status no longer matches.
     * Loses cleanly to an operator's accept (which is also lockForUpdate
     * and bumps status='connecting' first).
     */
    public function wabaCallBridgeAccepted(Request $request): JsonResponse
    {
        if (!$this->authed($request)) {
            return response()->json(['ok' => false, 'error' => 'unauthorized'], 401);
        }
        $data = $request->validate([
            'wa_call_id'   => 'required|integer',
            'meta_call_id' => 'nullable|string|max:128',
            'assistant_id' => 'nullable|integer',
        ]);

        $applied = \Illuminate\Support\Facades\DB::transaction(function () use ($data) {
            $row = \App\Models\WaCall::where('id', $data['wa_call_id'])->lockForUpdate()->first();
            if (!$row || $row->status !== 'ringing') return null;
            $row->forceFill([
                'status'           => 'active',
                'answered_at'      => now(),
                'handler_type'     => 'ai_agent',
                'handler_agent_id' => $data['assistant_id'] ?? $row->handler_agent_id,
            ])->save();
            return $row;
        });

        if ($applied) {
            \App\Models\WaCallEvent::create([
                'wa_call_id'  => $applied->id,
                'event_type'  => 'bridge_accepted',
                'payload'     => ['assistant_id' => $data['assistant_id'] ?? null],
                'received_at' => now(),
            ]);
        }
        return response()->json(['ok' => true, 'claimed' => (bool) $applied]);
    }

    /**
     * POST /api/waba-call/bridge-error
     * Node calls this when the WebRTC bridge crashes or fails to open
     * (missing keys, peer connection failure, etc). Flips the wa_calls
     * row to status=failed so the operator doesn't see a phantom
     * "active" call. The AI fallback timer (set in handleConnect) will
     * still fire after auto_pickup_delay_sec to send a voicemail —
     * status=failed doesn't block that since the fallback re-locks the
     * row and uses its own status check.
     */
    public function wabaCallBridgeError(Request $request): JsonResponse
    {
        if (!$this->authed($request)) {
            return response()->json(['ok' => false, 'error' => 'unauthorized'], 401);
        }
        $data = $request->validate([
            'wa_call_id'   => 'required|integer',
            'meta_call_id' => 'nullable|string|max:128',
            'reason'       => 'nullable|string|max:500',
        ]);
        $call = \App\Models\WaCall::find($data['wa_call_id']);
        if (!$call) {
            return response()->json(['ok' => false, 'error' => 'call_not_found'], 404);
        }
        // Only flip from in-flight states — don't overwrite a row that
        // was already accepted by an operator or already terminated.
        if (in_array($call->status, ['ringing', 'connecting'], true)) {
            $call->forceFill([
                'status'        => 'failed',
                'end_reason'    => 'BRIDGE_ERROR',
                'error_payload' => ['reason' => $data['reason'] ?? '', 'source' => 'node_bridge'],
                'ended_at'      => now(),
            ])->save();
        }
        \App\Models\WaCallEvent::create([
            'wa_call_id'  => $call->id,
            'event_type'  => 'bridge_error',
            'payload'     => ['reason' => $data['reason'] ?? ''],
            'received_at' => now(),
        ]);
        \Illuminate\Support\Facades\Log::warning('[WA-CALLING] bridge error reported', [
            'wa_call_id' => $call->id,
            'reason'     => $data['reason'] ?? '',
        ]);
        return response()->json(['ok' => true]);
    }

    /**
     * POST /api/waba-call/transcript-turn
     * Node calls this each time the STT or TTS finalises a turn so the
     * /call-logs UI shows transcripts live (and the wa_call_events row
     * is the source of truth for the bridge's transcript builder).
     */
    public function wabaCallTranscriptTurn(Request $request): JsonResponse
    {
        if (!$this->authed($request)) {
            return response()->json(['ok' => false, 'error' => 'unauthorized'], 401);
        }
        $data = $request->validate([
            'wa_call_id' => 'required|integer',
            'role'       => 'required|in:agent,user',
            'text'       => 'required|string|max:6000',
            't_ms'       => 'nullable|integer',
        ]);
        $call = \App\Models\WaCall::find($data['wa_call_id']);
        if (!$call) {
            return response()->json(['ok' => false, 'error' => 'call_not_found'], 404);
        }
        \App\Models\WaCallEvent::create([
            'wa_call_id'  => $call->id,
            'event_type'  => 'transcript_turn',
            'payload'     => [
                'role' => $data['role'],
                'text' => $data['text'],
                't_ms' => (int) ($data['t_ms'] ?? 0),
            ],
            'received_at' => now(),
        ]);
        return response()->json(['ok' => true]);
    }

    /**
     * POST /api/flow-node/ai-agent-reply
     * The flow "Chatbot" node hands the conversation to a Team-Inbox AI Agent.
     * We assign that agent to the conversation and fire the FIRST reply through
     * AiAgentService::respondIfAssigned — the exact path Team Inbox uses — so the
     * agent's full persona + knowledge base apply. Because the agent stays
     * assigned, it then answers every following message on the normal inbound
     * path (no per-turn flow round-trip). This is why the builder's "Chatbot"
     * node previously did nothing: its Node handler only ran sub-flows and never
     * invoked the selected agent.
     */
    public function aiAgentReply(Request $request): JsonResponse
    {
        if (!$this->authed($request)) {
            return response()->json(['ok' => false, 'error' => 'unauthorized'], 401);
        }
        $data = $request->validate([
            'workspace_id'   => 'required|integer',
            'agent_id'       => 'required|integer',
            'customer_phone' => 'required|string|max:32',
        ]);

        $wsId    = (int) $data['workspace_id'];
        $agentId = (int) $data['agent_id'];

        // Load the agent, workspace-scoped — never let a flow assign another
        // tenant's agent (respondIfAssigned re-checks this too, defence in depth).
        $agent = \App\Models\AiAgent::where('id', $agentId)
            ->where('workspace_id', $wsId)
            ->where('is_active', true)
            ->first();
        if (!$agent) {
            return response()->json(['ok' => false, 'error' => 'agent_not_found'], 404);
        }

        // Resolve the conversation by the customer's number. Inbound already
        // created/updated it before the flow ran, so we match on raw_jid digits
        // (with and without the @s.whatsapp.net suffix) and take the newest.
        $jid = preg_replace('/\D+/', '', (string) $data['customer_phone']);
        $convo = Conversation::where('workspace_id', $wsId)
            ->where(function ($q) use ($jid) {
                $q->where('raw_jid', $jid . '@s.whatsapp.net')
                  ->orWhere('raw_jid', $jid);
            })
            ->orderByDesc('id')
            ->first();
        if (!$convo) {
            return response()->json(['ok' => false, 'error' => 'conversation_not_found'], 404);
        }

        // Assign the agent so (a) respondIfAssigned generates the first reply now
        // and (b) every future inbound auto-replies via the same agent. All the
        // guards (plan gate, device scope, handoff, Meta-agent coexistence) live
        // inside respondIfAssigned, so we don't duplicate them here.
        $convo->forceFill(['assignee_agent_id' => $agent->id])->save();

        $reply = null;
        try {
            $reply = app(\App\Services\AiAgentService::class)->respondIfAssigned($convo->fresh());
        } catch (\Throwable $e) {
            Log::warning('[FLOW-CHATBOT] agent reply failed: ' . $e->getMessage(), [
                'agent_id' => $agentId,
                'conv_id'  => $convo->id,
            ]);
        }

        return response()->json([
            'ok'       => true,
            'agent'    => $agent->name,
            'assigned' => true,
            'replied'  => (bool) $reply,
        ]);
    }

    /**
     * POST /api/flow-node/ai-call
     * The Node AI executor delegates the LLM HTTP call here so the API
     * keys stay server-side and per-project policy [[admin_billing_ux]]
     * is enforced. Workspace BYOK wins when the plan allows it,
     * otherwise the admin's global key applies, otherwise env fallback.
     *
     * Provider is inferred from the model string (gpt* → openai,
     * claude* → anthropic, gemini* → gemini) so the flow node only
     * needs the model name + prompt.
     */
    public function aiCall(Request $request): JsonResponse
    {
        if (!$this->authed($request)) {
            return response()->json(['ok' => false, 'error' => 'unauthorized'], 401);
        }
        $data = $request->validate([
            'workspace_id'  => 'nullable|integer',
            'model'         => 'required|string|max:120',
            // Optional AI-Training assistant — its knowledge base is
            // stitched into the system prompt below when present.
            'assistant_id'  => 'nullable|integer',
            // Optional VOICE (call) assistant — the Call Flow "AI Assistant" node
            // (cf_assistant) passes this so the reply uses that assistant's own
            // persona (system prompt) + model, not the node's generic prompt.
            'call_assistant_id' => 'nullable|integer',
            'system_prompt' => 'nullable|string|max:6000',
            'user_prompt'   => 'nullable|string|max:6000',
            'max_tokens'    => 'nullable|integer|min:1|max:4000',
            'temperature'   => 'nullable|numeric|min:0|max:2',
            // Structured-extraction mode (the ordering + farm-record flows).
            // When `json` is true the provider is forced to return a single
            // JSON object; `fields` lists the exact keys to emit.
            'json'          => 'nullable|boolean',
            'fields'        => 'nullable|array',
            'fields.*'      => 'string|max:120',
        ]);

        // Voice-assistant handoff (Call Flow "AI Assistant" node): adopt the
        // chosen assistant's persona + model BEFORE we infer the provider, so a
        // call flow can route to a specific voice agent's brain. Workspace-scoped.
        if ((int) ($data['call_assistant_id'] ?? 0) > 0) {
            $ca = \App\Models\AiCallAssistant::where('workspace_id', (int) ($data['workspace_id'] ?? 0))
                ->find((int) $data['call_assistant_id']);
            if ($ca) {
                if (trim((string) $ca->ai_system_prompt) !== '') {
                    // The assistant persona leads; any node-level extra prompt is
                    // appended as additional context.
                    $extra = trim((string) ($data['system_prompt'] ?? ''));
                    $data['system_prompt'] = trim((string) $ca->ai_system_prompt) . ($extra !== '' ? "\n\n" . $extra : '');
                }
                if (trim((string) $ca->ai_model) !== '') {
                    $data['model'] = (string) $ca->ai_model;
                }
            }
        }

        $model = strtolower($data['model']);
        $provider = str_starts_with($model, 'claude') ? 'anthropic'
                  : (str_starts_with($model, 'gemini') ? 'gemini'
                  : ((str_starts_with($model, 'mistral') || str_starts_with($model, 'ministral') || str_starts_with($model, 'open-mistral') || str_starts_with($model, 'open-mixtral')) ? 'mistral'
                  : 'openai'));

        $jsonMode = (bool) ($data['json'] ?? false);
        $system   = (string) ($data['system_prompt'] ?? 'You are a helpful WhatsApp assistant.');
        if ($jsonMode) {
            $fields    = array_values(array_filter(array_map('trim', array_map('strval', $data['fields'] ?? []))));
            $keyList   = $fields ? ('each with exactly these keys: ' . implode(', ', $fields) . '. ') : '';
            // The word "json" MUST appear for OpenAI's json_object mode.
            // Multi-record support: ONE WhatsApp message can carry SEVERAL
            // records (e.g. a block of farm rows). json_object mode can't return
            // a top-level array, so we ask for {"rows":[ {...}, {...} ]}. The
            // Node side promotes `.rows` to `<var>.__rows` and the Google Sheets
            // node then appends ONE row per record. A single-record message
            // still works: the model returns one object (or rows with one item),
            // both of which downstream handles unchanged.
            $system .= "\n\nIMPORTANT: Respond with ONLY valid JSON — no markdown, no code fences, no commentary. "
                     . "Read the ENTIRE message and extract EVERY record it contains. "
                     . "If the message contains a SINGLE record, return one JSON object " . $keyList
                     . "If it contains MULTIPLE records, return {\"rows\": [ ... ]} where each array element is one such object " . ($fields ? '(same keys). ' : '. ')
                     . "Use an empty string \"\" for any value you cannot determine. Do not invent data.";
        }

        // AI-Training knowledge base — when the flow's AI node has an
        // assistant attached, pull that assistant's READY training sources
        // (URLs / text / Q&A, assistant-scoped + workspace-wide) and stitch
        // them into the system prompt. Same injection AiChatService does for
        // the chatbot widget, so flow replies answer from trained content.
        $assistantId = (int) ($data['assistant_id'] ?? 0);
        if ($assistantId > 0) {
            $assistant = \App\Models\AiChatAssistant::where('workspace_id', (int) ($data['workspace_id'] ?? 0))
                ->where('id', $assistantId)->first();
            if ($assistant) {
                try {
                    $kb = app(\App\Services\AiChat\AiChatService::class)->contextFor($assistant);
                    if (trim($kb) !== '') {
                        $system .= "\n\n--- Knowledge base ---\n" . $kb . "\n--- End knowledge base ---";
                    }
                } catch (\Throwable $e) {
                    \Log::warning('[FLOW-AI] knowledge-base inject failed: ' . $e->getMessage(), ['assistant_id' => $assistantId]);
                }
            }
        }

        // ── TRACE: the message reached the AI node + which provider/key path ──
        $wsForLog = (int) ($data['workspace_id'] ?? 0);
        $keyRow   = \App\Models\AdminAiKey::activeFor($provider);
        \Log::info('[FLOWTRACE] ai-call IN', [
            'workspace_id' => $wsForLog,
            'provider'     => $provider,
            'model'        => $data['model'],
            'json_mode'    => $jsonMode,
            'fields'       => array_values((array) ($data['fields'] ?? [])),
            'msg_len'      => mb_strlen((string) ($data['user_prompt'] ?? '')),
            'msg_preview'  => mb_substr((string) ($data['user_prompt'] ?? ''), 0, 200),
            'admin_ai_key' => $keyRow ? 'present (id ' . $keyRow->id . ')' : 'MISSING — no active admin AI key for ' . $provider,
        ]);

        $reply = app(\App\Services\AiAgentService::class)->callProvider(
            provider:     $provider,
            model:        $data['model'],
            workspaceId:  $wsForLog,
            systemPrompt: $system,
            userPrompt:   (string) ($data['user_prompt'] ?? ''),
            maxTokens:    (int) ($data['max_tokens'] ?? 350),
            temperature:  (float) ($data['temperature'] ?? 0.7),
            jsonMode:     $jsonMode,
        );

        // ── TRACE: did the provider reply (key valid?) and what came back ──
        \Log::info('[FLOWTRACE] ai-call OUT', [
            'provider'      => $provider,
            'reply_empty'   => ($reply === null || $reply === ''),
            'reply_len'     => mb_strlen((string) $reply),
            'reply_preview' => mb_substr((string) $reply, 0, 400),
        ]);

        if ($reply === null || $reply === '') {
            \Log::warning('[FLOWTRACE] ai-call FAILED — provider returned nothing. Check the admin AI key for ' . $provider . ' (and that the model is valid).');
            return response()->json([
                'ok'      => false,
                'error'   => 'provider_failed',
                'message' => 'AI provider returned no content — check admin AI key for ' . $provider,
            ], 502);
        }
        return response()->json(['ok' => true, 'reply' => $reply, 'provider' => $provider]);
    }

    /**
     * POST /api/flow-node/web-search — the Call Flow "Search web" node.
     * Provider-agnostic: admin sets web_search_provider (tavily|serpapi|brave,
     * default tavily) + web_search_key (encrypted). Returns a short text blob
     * the AI Respond node can read. Never throws into the call — on any
     * failure it returns empty text so the flow continues gracefully.
     */
    public function webSearch(Request $request): JsonResponse
    {
        if (!$this->authed($request)) {
            return response()->json(['ok' => false, 'error' => 'unauthorized'], 401);
        }
        $data = $request->validate([
            'query'       => 'required|string|max:500',
            'max_results' => 'nullable|integer|min:1|max:10',
        ]);
        $provider = strtolower((string) \App\Models\SystemSetting::get('web_search_provider', 'tavily'));
        $key      = (string) \App\Models\SystemSetting::get('web_search_key', '');
        $q        = trim($data['query']);
        $n        = (int) ($data['max_results'] ?? 5);
        if ($key === '' || $q === '') {
            return response()->json(['ok' => true, 'text' => '', 'note' => 'no_key_or_query']);
        }
        try {
            $http = \Illuminate\Support\Facades\Http::timeout(8);
            if ($provider === 'serpapi') {
                $r = $http->get('https://serpapi.com/search.json', ['q' => $q, 'api_key' => $key, 'num' => $n]);
                $items = array_slice((array) ($r->json('organic_results') ?? []), 0, $n);
                $text  = collect($items)->map(fn ($i) => ($i['title'] ?? '') . ' — ' . ($i['snippet'] ?? ''))->implode("\n");
            } elseif ($provider === 'brave') {
                $r = \Illuminate\Support\Facades\Http::withHeaders(['X-Subscription-Token' => $key, 'Accept' => 'application/json'])
                    ->timeout(8)->get('https://api.search.brave.com/res/v1/web/search', ['q' => $q, 'count' => $n]);
                $items = array_slice((array) ($r->json('web.results') ?? []), 0, $n);
                $text  = collect($items)->map(fn ($i) => ($i['title'] ?? '') . ' — ' . ($i['description'] ?? ''))->implode("\n");
            } else { // tavily (default — built for AI agents)
                $r = $http->post('https://api.tavily.com/search', [
                    'api_key' => $key, 'query' => $q, 'max_results' => $n, 'include_answer' => true,
                ]);
                $answer = (string) $r->json('answer', '');
                $items  = array_slice((array) ($r->json('results') ?? []), 0, $n);
                $text   = trim($answer . "\n" . collect($items)->map(fn ($i) => ($i['title'] ?? '') . ' — ' . ($i['content'] ?? ''))->implode("\n"));
            }
            return response()->json(['ok' => true, 'text' => mb_substr((string) $text, 0, 3000), 'provider' => $provider]);
        } catch (\Throwable $e) {
            \Log::warning('[WEB-SEARCH] ' . $e->getMessage());
            return response()->json(['ok' => true, 'text' => '', 'note' => 'search_failed']);
        }
    }

    /**
     * POST /api/call-flow/active — the WABA call bridge asks "does this
     * workspace have a call flow to run on inbound calls?" Returns the
     * decoded flow JSON of the workspace's latest PUBLISHED + active call
     * flow, or null (→ bridge falls back to the single AiCallAssistant).
     */
    public function activeCallFlow(Request $request): JsonResponse
    {
        if (!$this->authed($request)) {
            return response()->json(['ok' => false, 'error' => 'unauthorized'], 401);
        }
        $wsId = (int) $request->integer('workspace_id');
        if (!$wsId) return response()->json(['ok' => true, 'flow' => null]);

        $flow = \App\Models\Flow::where('workspace_id', $wsId)
            ->where('flow_type', 'call')
            ->where('is_published', true)
            ->where('is_active', true)
            ->latest('id')->first();

        if (!$flow) return response()->json(['ok' => true, 'flow' => null]);
        return response()->json(['ok' => true, 'flow' => $flow->decoded_flow_data, 'flow_id' => $flow->id]);
    }

    /**
     * POST /api/flow-node/order-parse — natural-language ordering (P3).
     * Customer free-text → AI items → match → HOLD stock → summary + cart.
     * Returns { ok, has_items, summary, items, unavailable, total_minor, currency }.
     */
    public function orderParse(Request $request): JsonResponse
    {
        if (!$this->authed($request)) {
            return response()->json(['ok' => false, 'error' => 'unauthorized'], 401);
        }
        $data = $request->validate([
            'workspace_id'   => 'required|integer',
            'customer_phone' => 'required|string|max:32',
            // Nullable: a pure VOICE note / PHOTO order carries no typed text —
            // OrderingService resolves the media (transcript / vision) instead.
            'text'           => 'nullable|string|max:4000',
            'group_code'     => 'nullable|string|max:48',
            'model'          => 'nullable|string|max:120',
        ]);

        \Log::info('[ORDER-FLOW] 1 · parse IN', [
            'ws'    => (int) $data['workspace_id'],
            'phone' => $data['customer_phone'],
            'text'  => mb_substr((string) ($data['text'] ?? ''), 0, 200),
        ]);

        $result = app(\App\Services\Ordering\OrderingService::class)->parseAndHold(
            (int) $data['workspace_id'],
            (string) $data['customer_phone'],
            (string) ($data['text'] ?? ''),
            $data['group_code'] ?? null,
            (string) ($data['model'] ?? 'gpt-4o-mini'),
        );

        \Log::info('[ORDER-FLOW] 1 · parse OUT', [
            'has_items' => $result['has_items'] ?? null,
            'order_ok'  => $result['order_ok'] ?? null,
            'matched'   => count($result['items'] ?? []),
        ]);
        return response()->json(['ok' => true] + $result);
    }

    /**
     * POST /api/flow-node/order-confirm — finalise the held cart into a real
     * order, commit the stock, and (P5) post the order into the customer's
     * WhatsApp group with an @mention. The group post is best-effort and
     * deferred so it never blocks the customer's confirmation reply.
     */
    public function orderShipping(Request $request): JsonResponse
    {
        if (!$this->authed($request)) {
            return response()->json(['ok' => false, 'error' => 'unauthorized'], 401);
        }
        $data = $request->validate([
            'workspace_id'   => 'required|integer',
            'customer_phone' => 'required|string|max:32',
            'text'           => 'nullable|string|max:2000',
            'use_saved'      => 'nullable|boolean',
            'model'          => 'nullable|string|max:120',
        ]);
        $wsId  = (int) $data['workspace_id'];
        $phone = (string) $data['customer_phone'];
        $model = (string) ($data['model'] ?? 'gpt-4o-mini');
        $svc   = app(\App\Services\Ordering\OrderingService::class);

        $text = trim((string) ($data['text'] ?? ''));

        // Explicit use_saved=true (a structured flag the flow can pass — NOT a
        // guessed word) short-circuits to the saved address.
        if (!empty($data['use_saved'])) {
            $s = $svc->shippingFor($wsId, $phone);
            if (!$s) return response()->json(['ok' => false, 'error' => 'no_saved_address'], 422);
            $svc->setPendingShipping($wsId, $phone, $s['name'], $s['company'], $s['address']);
            return response()->json(['ok' => true, 'shipping' => $s['text']]);
        }

        // Otherwise the AI interprets the reply (any language, no word lists):
        // "confirm my saved address" vs a NEW address. Voice replies handled inside.
        $res = $svc->setPendingShippingFromText($wsId, $phone, $text, $model);
        if (!$res['ok']) return response()->json(['ok' => false, 'error' => 'empty_address'], 422);
        return response()->json(['ok' => true, 'shipping' => $res['text']]);
    }

    public function orderConfirm(Request $request): JsonResponse
    {
        if (!$this->authed($request)) {
            return response()->json(['ok' => false, 'error' => 'unauthorized'], 401);
        }
        $data = $request->validate([
            'workspace_id'   => 'required|integer',
            'customer_phone' => 'required|string|max:32',
            'ship_text'      => 'nullable|string|max:2000',
            'notify_group'   => 'nullable|boolean',
            'model'          => 'nullable|string|max:120',
        ]);

        $wsId  = (int) $data['workspace_id'];
        $phone = (string) $data['customer_phone'];
        $model = (string) ($data['model'] ?? 'gpt-4o-mini');
        $svc   = app(\App\Services\Ordering\OrderingService::class);

        // If the flow passed a delivery address (ship_text), save it onto the cart
        // BEFORE finalizing — so ONE confirm node captures the address AND places
        // the order (no separate shipping node needed in the flow). "SAME"/"sama"
        // reuses the customer's last saved address.
        $ship = trim((string) ($data['ship_text'] ?? ''));
        \Log::info('[ORDER-FLOW] 3 · confirm IN', [
            'ws'        => $wsId,
            'phone'     => $phone,
            'ship_text' => mb_substr($ship, 0, 200),
            'has_ship'  => $ship !== '',
        ]);
        // ONE AI call interprets the reply (any language, NO hardcoded words):
        // confirm the saved address, or a NEW address (split + voice handled
        // inside). $ship may be empty when the address came as a voice note.
        $svc->setPendingShippingFromText($wsId, $phone, $ship, $model);

        $res   = $svc->confirm($wsId, $phone);
        \Log::info('[ORDER-FLOW] 3 · confirm RESULT', [
            'ok'       => $res['ok'] ?? false,
            'order_id' => $res['order_id'] ?? null,
            'error'    => $res['error'] ?? null,
        ]);

        if (!($res['ok'] ?? false)) {
            // Return 200 + a LOCALIZED message so the flow's {{reply.summary}} is
            // never an empty bubble (cart empty / expired / product not found).
            return response()->json([
                'ok'      => false,
                'error'   => $res['error'] ?? 'confirm_failed',
                'summary' => $svc->localizeForCustomer('Sorry, we could not place your order — the item may be unavailable or your cart expired. Please send your order again.', $ship),
            ]);
        }

        // P5 — auto-find the customer's WhatsApp group and post the order there
        // with an @mention. This is Jessica's KEYSTONE feature (Use case B,
        // step 6), so it is ON by default; a flow may turn it off with
        // notify_group=false.
        //
        // Per the spec (G4 / risk note): "auto-find is only as accurate as the
        // membership map; if a number is in SEVERAL groups, disambiguate" — so
        // we only post when the group is UNAMBIGUOUS: an explicit group_code
        // match, or the customer belongs to exactly ONE group. When the number
        // is in multiple groups (reason='ambiguous') we DO NOT guess/post to a
        // random one — that was the bug where an order hit an unrelated group.
        // Cleanest disambiguation = bake a group code into the wa.me link.
        if (($data['notify_group'] ?? true) !== false) {
            $summary   = (string) ($res['summary'] ?? ('Order #' . ($res['order_id'] ?? '')));
            $groupCode = $res['group_code'] ?? null;
            $custLang  = $res['customer_lang'] ?? null;   // Jessica #1 — group post in customer's language
            app()->terminating(function () use ($wsId, $phone, $summary, $groupCode, $custLang) {
                try {
                    $dir = app(\App\Services\Ordering\GroupDirectory::class)->resolveForCustomer($wsId, $phone, $groupCode);
                    $reason = $dir['reason'] ?? '';
                    Log::info('[ORDER-FLOW] 4 · group resolve', [
                        'reason'     => $reason,
                        'candidates' => $dir['candidates'] ?? 0,
                        'group'      => optional($dir['group'] ?? null)->name,
                        'group_code' => $groupCode,
                    ]);
                    // Post when we have ANY resolved group. 'ambiguous' (the
                    // customer is in several groups the business also belongs to)
                    // now POSTS to the freshest shared group instead of staying
                    // silent — the merchant asked for the @mention to always fire.
                    // Only 'not_member' / 'none' (no shared group at all) skip.
                    if (!empty($dir['group']) && in_array($reason, ['code', 'single', 'ambiguous'], true)) {
                        if ($reason === 'ambiguous') {
                            Log::info('[ORDER-FLOW] 4 · ambiguous (' . ($dir['candidates'] ?? 0) . ' groups) → posting to freshest', ['group' => optional($dir['group'])->name]);
                        }
                        Log::info('[ORDER-FLOW] 4 · POSTING to group', ['group' => optional($dir['group'])->name]);
                        app(\App\Services\Ordering\GroupNotifier::class)
                            ->notifyCustomerInGroup($dir['group'], $phone, $summary, $custLang);
                        Log::info('[ORDER-FLOW] 4 · group post DONE');
                    } else {
                        // not_member (phone in no synced group) / none — nothing to post to.
                        Log::info('[ORDER-FLOW] 4 · NO group posted — reason=' . $reason . ', candidates=' . ($dir['candidates'] ?? 0) . ', phone=' . $phone);
                    }
                } catch (\Throwable $e) {
                    Log::warning('[ORDER-FLOW] 4 · group notify FAILED: ' . $e->getMessage());
                }
            });
        }

        return response()->json([
            'ok'          => true,
            'order_id'    => $res['order_id'] ?? null,
            'total_minor' => $res['total_minor'] ?? 0,
            'currency'    => $res['currency'] ?? null,
            'summary'     => $res['summary'] ?? '',
        ]);
    }

    /** POST /api/flow-node/order-cancel — drop the cart + release held stock. */
    public function orderCancel(Request $request): JsonResponse
    {
        if (!$this->authed($request)) {
            return response()->json(['ok' => false, 'error' => 'unauthorized'], 401);
        }
        $data = $request->validate([
            'workspace_id'   => 'required|integer',
            'customer_phone' => 'required|string|max:32',
        ]);
        $res = app(\App\Services\Ordering\OrderingService::class)
            ->cancel((int) $data['workspace_id'], (string) $data['customer_phone']);
        return response()->json(['ok' => true] + $res);
    }

    /**
     * POST /api/flow-node/order-reply — ONE endpoint for the customer's reply
     * to the order summary. Translates the reply to English (any language),
     * decides confirm / cancel / unclear, then confirms, cancels, or re-asks —
     * and ALWAYS returns a message localized into the customer's language.
     * Replaces the old Node-side English-only "contains confirm" condition.
     */
    public function orderReply(Request $request): JsonResponse
    {
        if (!$this->authed($request)) {
            return response()->json(['ok' => false, 'error' => 'unauthorized'], 401);
        }
        $data = $request->validate([
            'workspace_id'   => 'required|integer',
            'customer_phone' => 'required|string|max:32',
            'text'           => 'nullable|string|max:2000',
            'notify_group'   => 'nullable|boolean',
        ]);
        $wsId  = (int) $data['workspace_id'];
        $phone = (string) $data['customer_phone'];
        $text  = (string) ($data['text'] ?? '');
        $svc   = app(\App\Services\Ordering\OrderingService::class);
        $L     = fn (string $en) => $svc->localizeForCustomer($en, $text);

        $intent = $svc->decideReply($text)['intent'];
        Log::info('[ORDER-TRACE] order-reply', ['ws' => $wsId, 'phone' => preg_replace('/\D+/', '', $phone), 'intent' => $intent]);

        if ($intent === 'cancel') {
            $svc->cancel($wsId, $phone);
            return response()->json(['ok' => true, 'intent' => 'cancel',
                'summary' => $L('No problem — your order was cancelled. Send *order* any time to start again.')]);
        }

        // Unclear → re-ask in their language (do NOT cancel — that was the bug).
        if ($intent !== 'confirm') {
            return response()->json(['ok' => true, 'intent' => 'unclear',
                'summary' => $L("Sorry, I didn't catch that. Reply *CONFIRM* to place your order, or *CANCEL* to stop.")]);
        }

        $res = $svc->confirm($wsId, $phone);
        if (!($res['ok'] ?? false)) {
            return response()->json(['ok' => true, 'intent' => 'confirm', 'order_id' => null,
                'summary' => $L('Your cart has expired or was already placed. Please send your order again to start over.')]);
        }

        // Group notify — same rule as order-confirm: only an UNAMBIGUOUS group.
        if (($data['notify_group'] ?? true) !== false) {
            $gSummary  = (string) ($res['summary'] ?? ('Order #' . ($res['order_id'] ?? '')));
            $groupCode = $res['group_code'] ?? null;
            $custLang  = $res['customer_lang'] ?? null;   // Jessica #1 — group post in customer's language
            app()->terminating(function () use ($wsId, $phone, $gSummary, $groupCode, $custLang) {
                try {
                    $dir = app(\App\Services\Ordering\GroupDirectory::class)->resolveForCustomer($wsId, $phone, $groupCode);
                    if (!empty($dir['group']) && in_array($dir['reason'] ?? '', ['code', 'single'], true)) {
                        app(\App\Services\Ordering\GroupNotifier::class)->notifyCustomerInGroup($dir['group'], $phone, $gSummary, $custLang);
                    }
                } catch (\Throwable $e) {
                    Log::warning('[ORDERING] group notify failed: ' . $e->getMessage());
                }
            });
        }

        return response()->json([
            'ok' => true, 'intent' => 'confirm', 'order_id' => $res['order_id'] ?? null,
            'summary' => $L((string) ($res['summary'] ?? 'Order placed.')),
        ]);
    }

    /**
     * POST /api/flow-node/mysql-query — run a READ-ONLY SQL query against an
     * external MySQL/MariaDB on behalf of a flow's MySQL node. Auth:
     * X-Node-Token. Security: SELECT/WITH only, single statement, no
     * INTO OUTFILE/DUMPFILE/LOAD_FILE, 8s timeout, 100-row cap.
     * Returns { ok, rows: [...] }.
     */
    public function mysqlQuery(Request $request): JsonResponse
    {
        if (!$this->authed($request)) {
            return response()->json(['ok' => false, 'error' => 'unauthorized'], 401);
        }
        $data = $request->validate([
            'host'     => 'required|string|max:255',
            'port'     => 'nullable|integer|min:1|max:65535',
            'database' => 'required|string|max:128',
            'username' => 'nullable|string|max:128',
            'password' => 'nullable|string|max:255',
            'sql'      => 'required|string|max:8000',
        ]);

        // Read-only guard — single SELECT/WITH statement, no file ops.
        $sql = rtrim(trim($data['sql']), "; \t\n\r");
        if (str_contains($sql, ';')) {
            return response()->json(['ok' => false, 'error' => 'Only a single statement is allowed.'], 422);
        }
        if (!preg_match('/^\s*(SELECT|WITH)\b/i', $sql)) {
            return response()->json(['ok' => false, 'error' => 'Only SELECT queries are allowed.'], 422);
        }
        if (preg_match('/\b(INTO\s+(OUTFILE|DUMPFILE)|LOAD_FILE)\b/i', $sql)) {
            return response()->json(['ok' => false, 'error' => 'File operations are not allowed.'], 422);
        }

        $cap  = 100;
        $port = (int) ($data['port'] ?? 3306);
        try {
            $dsn = "mysql:host={$data['host']};port={$port};dbname={$data['database']};charset=utf8mb4";
            $pdo = new \PDO($dsn, (string) ($data['username'] ?? ''), (string) ($data['password'] ?? ''), [
                \PDO::ATTR_ERRMODE             => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_TIMEOUT             => 8,
                \PDO::ATTR_DEFAULT_FETCH_MODE  => \PDO::FETCH_ASSOC,
                \PDO::ATTR_EMULATE_PREPARES    => false,
            ]);
            $stmt = $pdo->query($sql);
            $rows = [];
            while (count($rows) < $cap && ($row = $stmt->fetch()) !== false) {
                $rows[] = $row;
            }

            return response()->json(['ok' => true, 'rows' => $rows, 'count' => count($rows)]);
        } catch (\Throwable $e) {
            Log::warning('[flow-node mysql] query failed', ['error' => $e->getMessage()]);

            return response()->json(['ok' => false, 'error' => 'Query failed: ' . $e->getMessage()], 502);
        }
    }

    /** POST /api/flow-node/assign */
    public function assign(Request $request): JsonResponse
    {
        if (!$this->authed($request)) {
            return response()->json(['ok' => false, 'error' => 'unauthorized'], 401);
        }
        $data = $request->validate([
            'workspace_id'   => 'required|integer',
            'customer_phone' => 'required|string|max:32',
            'team_id'        => 'nullable|integer',
            'user_id'        => 'nullable|integer',
            'note'           => 'nullable|string|max:1024',
        ]);

        $conv = $this->findConversation((int) $data['workspace_id'], (string) $data['customer_phone']);
        if (!$conv) {
            return response()->json(['ok' => false, 'error' => 'conversation_not_found'], 404);
        }

        // Pick a strategy: explicit user → manual; team only → round_robin
        // (matches what TeamInboxController defaults to when an operator
        // picks a team without specifying a user).
        $strategy = !empty($data['user_id']) ? 'manual' : 'round_robin';

        try {
            $assigned = $this->assignment->assign(
                $conv,
                $data['user_id'] ?? null,
                $data['team_id'] ?? null,
                $strategy,
                null, // actorId — no human acted; came from flow
            );
            if (!empty($data['note'])) {
                ConversationEvent::record($conv->id, $conv->workspace_id, null, 'note_added', [
                    'note'   => $data['note'],
                    'source' => 'flow',
                ], 'flow');
            }
            return response()->json([
                'ok'             => true,
                'conversation_id' => $conv->id,
                'assignee_user_id' => $assigned?->id,
            ]);
        } catch (\Throwable $e) {
            Log::warning('[flow-node/assign] ' . $e->getMessage());
            return response()->json(['ok' => false, 'error' => 'assign_failed', 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * POST /api/flow-node/deal-action
     * Fires from a `deal` flow node — creates a CRM deal or moves an
     * existing one to a stage, so a chatbot can qualify a lead and push
     * it up the pipeline (the flow → deal direction). Auth: X-Node-Token.
     *
     * `stage_id` carries the pipeline (a stage knows its pipeline). For
     * `move` we find the contact's most-recent open deal and re-stage it;
     * if they have none yet we create one (so the flow never dead-ends).
     * Returns { ok, deal_id, status, created }.
     */
    public function dealAction(Request $request): JsonResponse
    {
        if (!$this->authed($request)) {
            return response()->json(['ok' => false, 'error' => 'unauthorized'], 401);
        }
        $data = $request->validate([
            'workspace_id'  => 'required|integer',
            'action'        => 'required|string|in:create,move',
            'stage_id'      => 'required|integer',
            'deal_name'     => 'nullable|string|max:191',
            'value'         => 'nullable|numeric|min:0|max:99999999',
            'owner_user_id' => 'nullable|integer',
            'contact_phone' => 'nullable|string|max:32',
            'deal_id'       => 'nullable|integer',
        ]);

        $wsId = (int) $data['workspace_id'];
        $ws   = \App\Models\Workspace::find($wsId);
        if (!$ws) {
            return response()->json(['ok' => false, 'error' => 'workspace_not_found'], 404);
        }
        // Plan gate — same flag as the /deals board + REST API.
        if (!\App\Services\PlanLimitGuard::hasFeature($ws, 'access_sales_pipeline')) {
            return response()->json(['ok' => false, 'error' => 'plan_not_allowed',
                'message' => 'Sales Pipeline is not enabled on this workspace plan.'], 422);
        }

        // The destination stage must belong to this workspace. It also
        // tells us which pipeline to create the deal in.
        $stage = \App\Models\PipelineStage::where('workspace_id', $wsId)->find((int) $data['stage_id']);
        if (!$stage) {
            return response()->json(['ok' => false, 'error' => 'stage_not_found'], 422);
        }
        $pipeline = \App\Models\Pipeline::where('workspace_id', $wsId)->find($stage->pipeline_id)
            ?: \App\Models\Pipeline::ensureDefaultForWorkspace($wsId);

        $contactId = $this->resolveContactIdByPhone($wsId, (string) ($data['contact_phone'] ?? ''));
        $ownerId   = (int) ($data['owner_user_id'] ?? 0) ?: null;
        $title     = trim((string) ($data['deal_name'] ?? '')) ?: 'New deal';

        try {
            $created = false;
            $deal    = null;

            if ($data['action'] === 'move') {
                // Find the deal to move: explicit id wins, then the
                // contact's most-recent OPEN deal in this pipeline.
                if (!empty($data['deal_id'])) {
                    $deal = \App\Models\Deal::where('workspace_id', $wsId)->find((int) $data['deal_id']);
                }
                if (!$deal && $contactId) {
                    $deal = \App\Models\Deal::where('workspace_id', $wsId)
                        ->where('contact_id', $contactId)
                        ->where('status', 'open')
                        ->orderByDesc('id')
                        ->first();
                }
                if ($deal) {
                    $deal->update(['stage_id' => $stage->id]); // observer syncs status/won_at/lost_at + fires deal_stage_changed
                }
            }

            // create action, OR move that found no existing deal → create one.
            if (!$deal) {
                $deal = \App\Models\Deal::create([
                    'workspace_id' => $wsId,
                    'pipeline_id'  => $pipeline->id,
                    'stage_id'     => $stage->id,
                    'contact_id'   => $contactId,
                    'title'        => mb_substr($title, 0, 191),
                    'value_minor'  => (int) round((float) ($data['value'] ?? 0) * 100),
                    'currency'     => $pipeline->currency,
                    'owner_user_id'=> $ownerId,
                    'source'       => 'flow',
                    'sort_order'   => 0,
                ]);
                $created = true;
            }

            return response()->json([
                'ok'      => true,
                'deal_id' => $deal->id,
                'status'  => $deal->status,
                'created' => $created,
            ]);
        } catch (\Throwable $e) {
            Log::warning('[flow-node/deal] ' . $e->getMessage());
            return response()->json(['ok' => false, 'error' => 'deal_failed', 'message' => $e->getMessage()], 500);
        }
    }

    // ----------------------------------------------------------------

    /**
     * Resolve a workspace Contact by phone (digits-only compare), mirroring
     * OrderDealService::resolveContactId so flow-created deals link to the
     * same contact the rest of the CRM uses.
     */
    private function resolveContactIdByPhone(int $workspaceId, string $phone): ?int
    {
        $digits = preg_replace('/\D+/', '', $phone);
        if ($digits === '' || !$workspaceId) return null;
        $contact = \App\Models\Contact::where('workspace_id', $workspaceId)->get()->first(function ($c) use ($digits) {
            $stored = preg_replace('/\D+/', '', (string) ($c->country_code . $c->mobile));
            return $stored !== '' && $stored === $digits;
        });
        return $contact?->id;
    }

    private function authed(Request $request): bool
    {
        $expected = node_token();
        $token    = (string) $request->header(self::TOKEN_HEADER, '');
        return $expected !== '' && hash_equals($expected, $token);
    }

    /**
     * Resolve a Conversation by workspace + phone. We strip non-digits
     * from the customer phone and search both `raw_jid` (the bare phone)
     * and the WhatsApp JID format `<digits>@s.whatsapp.net`. Picks the
     * most-recent conversation when several match.
     */
    private function findConversation(int $workspaceId, string $customerPhone): ?Conversation
    {
        $digits = preg_replace('/\D+/', '', $customerPhone);
        if ($digits === '') return null;

        // ONE THREAD PER NUMBER - see ConversationResolver. Also covers the
        // '@lid' and '@c.us' shapes this hand-rolled list missed.
        return \App\Services\Inbox\ConversationResolver::find($workspaceId, $digits);
    }
}
