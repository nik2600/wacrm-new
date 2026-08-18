<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Attribute;
use App\Models\Device;
use App\Models\ExtensionApiToken;
use App\Models\Message;
use App\Models\User;
use App\Models\WaProviderConfig;
use App\Models\WaTemplate;
use App\Services\WhatsAppDispatcher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

/**
 * REST API consumed by the WaDesk browser extension (content.js).
 *
 * Public routes:  appConfig, login
 * Bearer routes:  devices, attributes, templates, messageHistory,
 *                 credits, sendQuickMessage, contactCsv
 *
 * The extension talks to these through its background-worker fetch
 * proxy, so there's no browser CORS to satisfy. Auth on the bearer
 * routes is the ExtensionApiAuth middleware (extension_api_tokens).
 */
class ExtensionApiController extends Controller
{
    /**
     * This controller writes its own InboxMessage rows alongside each
     * Message, so the Message model's inbox mirror must stay off here —
     * otherwise every send lands in the thread twice.
     */
    public function __construct()
    {
        \App\Models\Message::$skipInboxMirror = true;
    }

    // ───────── PUBLIC ─────────

    /** GET /api/ext/app-config — lets the extension discover the canonical app URL. */
    public function appConfig(): JsonResponse
    {
        return response()->json([
            'app_url'  => rtrim((string) config('app.url'), '/'),
            'app_name' => (string) \App\Models\SystemSetting::get('app_name', config('app.name', 'WaDesk')),
        ]);
    }

    /** POST /api/ext/login — email + password → bearer token. */
    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email'    => 'required|email',
            'password' => 'required|string',
        ]);

        $user = User::where('email', $data['email'])->first();
        if (!$user || !Hash::check($data['password'], $user->password)) {
            return response()->json(['status' => 'error', 'message' => 'Invalid email or password.'], 401);
        }
        if (method_exists($user, 'trashed') && $user->trashed()) {
            return response()->json(['status' => 'error', 'message' => 'Account is disabled.'], 403);
        }

        $token = ExtensionApiToken::issue($user->id, 'browser-extension');

        return response()->json([
            'status'       => 'success',
            'access_token' => $token,
            'user'         => [
                'id'    => $user->id,
                'name'  => $user->name,
                'email' => $user->email,
            ],
        ]);
    }

    /** POST /api/ext/logout — revoke the presented token. */
    public function logout(Request $request): JsonResponse
    {
        $bearer = (string) $request->bearerToken();
        if ($bearer !== '') {
            ExtensionApiToken::where('token_hash', hash('sha256', $bearer))->delete();
        }
        return response()->json(['status' => 'success']);
    }

    // ───────── BEARER ─────────

    /**
     * GET /api/ext/devices — sender numbers for the workspace's ACTIVE
     * engine only. Mirrors WorkspaceEngine: baileys → paired phones;
     * waba → WABA numbers; twilio → the Twilio number. We never mix
     * engines, because the dispatcher routes by the workspace engine —
     * showing a WABA number while the workspace is on Baileys would let
     * the operator pick a sender that silently can't send.
     */
    public function devices(Request $request): JsonResponse
    {
        $user = $request->user();
        $wsId = (int) ($user->current_workspace_id ?? 0);

        // EVERY connected sender across ALL the workspace's enabled engines
        // (Unofficial + WABA + Twilio) — NOT just the single active engine. A
        // multi-engine workspace (e.g. an Unofficial phone + a WABA number + a
        // Twilio number all connected) used to see only ONE number in the
        // extension picker. The send path (sendQuickMessage) validates
        // from_number against BOTH stores and stamps the PICKED number's engine
        // on the message, so any connected channel is a safe sender. Mirrors the
        // /chat multi-engine picker fix (devices() → senders()).
        $out = \App\Services\WorkspaceEngine::senders($wsId)
            ->filter(fn ($s) => !empty($s['phone']))
            ->map(fn ($s) => [
                'device_name'  => $s['label'],
                'phone_number' => $s['phone'],
                'status'       => 'connected',
                'engine'       => $s['engine'],
            ])->values()->all();

        return response()->json(['devices' => $out, 'engine' => \App\Services\WorkspaceEngine::for($wsId)]);
    }

    /** GET /api/ext/attributes — workspace custom merge fields. */
    public function attributes(Request $request): JsonResponse
    {
        $user = $request->user();
        $rows = Attribute::query()
            ->where(function ($q) use ($user) {
                $q->where('workspace_id', $user->current_workspace_id)
                  ->orWhere('user_id', $user->id);
            })
            ->get(['attribute_key', 'attribute_name'])
            ->map(fn ($a) => [
                'attribute_key'  => $a->attribute_key,
                'attribute_name' => $a->attribute_name,
            ])
            ->values();

        return response()->json(['custom_attributes' => $rows]);
    }

    /**
     * GET /api/ext/templates — workspace message templates, filtered by
     * the active engine like the web app: on WABA only Meta-approved
     * (or public) templates can be sent, so we only surface those; on
     * Baileys / Twilio any saved template works, so we return all.
     */
    public function templates(Request $request): JsonResponse
    {
        $user   = $request->user();
        $wsId   = $user->current_workspace_id;
        $engine = \App\Services\WorkspaceEngine::for($wsId);

        // Mirror the web app exactly. forCurrentWorkspace() = this
        // workspace's rows + admin-seeded globals; approved() is
        // engine-aware: on WABA it requires a real Meta approval
        // (meta_template_id + meta_status=APPROVED), because the local
        // `status` column is SYNTHETIC on WABA (the Baileys flow stamps
        // every row 'approved'). Filtering on `status` here let non-Meta
        // templates leak into a WABA workspace's picker.
        $rows = WaTemplate::query()
            ->forCurrentWorkspace()
            ->approved()
            ->orderByDesc('id')
            ->get()
            ->map(function ($t) {
                $row = [
                    'id'            => (int) $t->id,
                    'template_name' => $t->template_name,
                    'template_body' => $t->template_body,
                    'template_type' => $t->template_type ?? 'text',
                    'status'        => $t->status,
                ];

                // sendMeta() is the SAME per-template shape the web override
                // panel consumes (<x-template-live-mapping>). Handing the
                // extension the identical structure is what lets it render the
                // same mapping UI instead of growing a second, divergent one.
                try {
                    $row['send_meta'] = $t->sendMeta();
                } catch (\Throwable $e) {
                    // A malformed row must not blank the whole picker — it just
                    // gets no mapping panel, exactly as on the web.
                    report($e);
                    $row['send_meta'] = null;
                }

                return $row;
            })->values();

        // The attribute catalog the mapping dropdowns offer. Same source as
        // every web send screen, so the two can't drift.
        $attributes = $wsId ? app(\App\Services\SendAttributes::class)->catalog((int) $wsId) : [];

        return response()->json([
            'templates'  => $rows,
            'engine'     => $engine,
            'attributes' => $attributes,
        ]);
    }

    /**
     * Resolve `{{tokens}}` in an extension send against THIS recipient.
     *
     * Deliberately routes through the same two pieces the web send screens use
     * — BroadcastsController::varsForRecipient() (template variable_map +
     * send-time overrides + contact lookup) and TemplateOverrideResolver — so
     * the extension cannot drift into its own substitution rules.
     *
     * The extension sends TEXT, not a Meta components payload, so the resolved
     * slot values are folded back into the body string here. Fails open: any
     * problem returns the original body rather than blocking the send.
     */
    private function personalizeForRecipient(
        string $body,
        string $toDigits,
        int $wsId,
        $templateId = null,
        ?array $overrides = null
    ): string {
        if (trim($body) === '' || $wsId <= 0) {
            return $body;
        }

        try {
            $resolver = app(\App\Services\TemplateOverrideResolver::class);

            // The recipient row varsForRecipient() expects. `mobile` is
            // encrypted at rest so it can't be matched in SQL — hydrate the
            // workspace's contacts and compare digits in PHP, the same way
            // InboxMirror::openConversation() does.
            $contactModel = \App\Models\Contact::query()
                ->where('workspace_id', $wsId)
                ->get()
                ->first(fn ($c) => preg_replace('/\D+/', '', (string) $c->mobile) === $toDigits);

            $contact = $contactModel
                ? array_merge(
                    $contactModel->toArray(),
                    ['phone' => $toDigits, 'mobile' => $toDigits]
                )
                : ['phone' => $toDigits, 'mobile' => $toDigits];

            // Scope to the RESOLVED $wsId, not forCurrentWorkspace().
            //
            // That scope reads auth()->user()->current_workspace_id — and
            // extension-token users frequently have none set, which is the
            // entire reason sendQuickMessage() resolves a workspace by hand
            // above. Using it here silently returned NO template, the
            // positional slots stayed unresolved, and `render()` then wiped
            // "{{1}}" to an empty string: the customer got "Hi , welcome to !".
            $tpl = $templateId
                ? \App\Models\WaTemplate::query()
                    ->where(function ($q) use ($wsId) {
                        $q->where('workspace_id', $wsId)
                          // Admin-seeded globals are visible to every workspace.
                          ->orWhere(fn ($g) => $g->whereNull('workspace_id')->whereNull('user_id'));
                    })
                    ->find((int) $templateId)
                : null;

            if ($tpl) {
                // Identical semantics to a broadcast: variable_map → contact
                // attribute → send-time override.
                $vars = app(\App\Http\Controllers\BroadcastsController::class)
                    ->varsForRecipient($tpl, $contact, $wsId, $overrides);

                $slots = array_values((array) ($vars['body'] ?? []));
                $i = 0;
                // Positional {{1}}, {{2}} … in template order.
                $body = preg_replace_callback(
                    '/\{\{\s*\d+\s*\}\}/u',
                    function () use ($slots, &$i) {
                        $v = $slots[$i] ?? '';
                        $i++;
                        return $v !== '' ? $v : '';
                    },
                    $body
                );
            }

            // Named tokens — {{name}}, {{business_name}}, custom attributes.
            // Also covers a plain message written with the `/` picker, which
            // had exactly the same unresolved-token bug.
            return $resolver->render($body, $contact, $wsId);
        } catch (\Throwable $e) {
            \Log::warning('[EXT-SEND] personalisation failed, sending raw: ' . $e->getMessage());
            return $body;
        }
    }

    /** GET /api/ext/message-history?page=N — paginated sends for this user. */
    public function messageHistory(Request $request): JsonResponse
    {
        $user = $request->user();
        $wsId = (int) ($user->current_workspace_id ?? 0);

        // Extension sends now live as outbound InboxMessages (routed through the
        // Team Inbox), so read those — their status reflects reality (sent /
        // delivered / read via InboxDispatcher + status webhooks). Reading the
        // old `messages` table showed a stale 'pending' forever.
        $paginator = \App\Models\InboxMessage::query()
            ->where('direction', 'out')
            ->where('meta->source', 'extension')
            ->when($wsId, fn ($q) => $q->whereHas('conversation', fn ($c) => $c->where('workspace_id', $wsId)))
            ->orderByDesc('id')
            ->paginate(20, ['*'], 'page', (int) $request->query('page', 1));

        $paginator->getCollection()->transform(function ($m) {
            return [
                'to_number'  => $m->to_number,
                'message'    => $m->body,
                'status'     => $this->statusCode($m->status),
                'created_at' => optional($m->created_at)->toIso8601String(),
            ];
        });

        return response()->json(['data' => $paginator]);
    }

    /** GET /api/ext/credits — plan + usage snapshot. */
    public function credits(Request $request): JsonResponse
    {
        $user    = $request->user();
        $isAdmin = method_exists($user, 'isAdmin') ? (bool) $user->isAdmin() : false;
        $ws      = $user->currentWorkspace ?? null;

        $delivered = Message::query()
            ->where('user_id', $user->id)
            ->whereIn('status', ['sent', 'delivered', 'read'])
            ->count();

        return response()->json([
            'success' => true,
            'data'    => [
                'plan_name'              => $ws->plan_name ?? ($isAdmin ? 'Admin' : 'Workspace'),
                'is_admin'               => $isAdmin,
                'unlimited_access'       => $isAdmin,
                'monthly_messages_limit' => (int) ($user->wallet_credits ?? 0),
                'delivered_count'        => $delivered,
                'plan_expiry'            => optional($ws->plan_expires_at ?? null)?->toIso8601String(),
            ],
        ]);
    }

    /** POST /api/ext/send-quick-message — send one message (text and/or media). */
    public function sendQuickMessage(Request $request, WhatsAppDispatcher $dispatcher): JsonResponse
    {
        $data = $request->validate([
            'to_number'    => 'required|string|max:32',
            'from_number'  => 'required|string|max:32',
            'message_text' => 'nullable|string',
            'image_file'   => 'nullable|file|max:8192',  // 8 MB
            // Template mapping, mirroring the web send screens. `template_id`
            // names the template being sent; `template_overrides` is the same
            // JSON the <x-template-live-mapping> panel posts.
            'template_id'        => 'nullable|integer',
            'template_overrides' => 'nullable',
        ]);

        $to   = preg_replace('/\D+/', '', $data['to_number']);
        $from = preg_replace('/\D+/', '', $data['from_number']);
        $body = $data['message_text'] ?? '';

        if ($to === '' || strlen($to) < 8) {
            return response()->json(['status' => 'error', 'message' => 'Invalid destination number.'], 422);
        }
        if ($body === '' && !$request->hasFile('image_file')) {
            return response()->json(['status' => 'error', 'message' => 'Nothing to send.'], 422);
        }

        $user = $request->user();

        // Resolve a workspace so the send ALWAYS routes through the Team Inbox
        // (InboxDispatcher) and NOT the legacy chat-history sendRaw fallback.
        // Extension-token users frequently have NO current_workspace_id set, so
        // without this fallback $wsId=0 → the code silently drops to sendRaw and
        // the message never appears in /team-inbox. We fall back to the workspace
        // of the SENDING device ($from), then to any workspace the user belongs
        // to — both scoped to the user's own workspaces for tenant safety.
        $memberWsIds = \Illuminate\Support\Facades\DB::table('workspace_user')
            ->where('user_id', $user->id)->pluck('workspace_id');

        $wsId = (int) ($user->current_workspace_id ?? 0);
        if ((!$wsId || !$memberWsIds->contains($wsId)) && $from !== '') {
            $wsId = (int) (\App\Models\Device::query()
                ->whereIn('workspace_id', $memberWsIds)
                ->where('active', true)
                ->get(['workspace_id', 'country_code', 'phone_number'])
                ->first(fn ($d) => preg_replace('/\D+/', '', (string) ($d->country_code . $d->phone_number)) === $from)
                ?->workspace_id ?? 0);
        }
        if (!$wsId) {
            $wsId = (int) ($memberWsIds->first() ?? 0);
        }
        $engine = $wsId ? \App\Services\WorkspaceEngine::for($wsId) : 'baileys';

        // PERSONALISE. Until now $body went out verbatim, so a template (or a
        // message written with the `/` attribute picker) shipped its literal
        // "{{name}}" / "{{1}}" braces to the customer — the extension had no
        // variable resolution at all, unlike every web send screen.
        $body = $this->personalizeForRecipient(
            $body,
            $to,
            $wsId,
            $data['template_id'] ?? null,
            \App\Services\TemplateOverrideResolver::sanitize($data['template_overrides'] ?? null)
        );

        // DIAGNOSTIC — trace exactly why a send lands on team-inbox vs the legacy
        // sendRaw fallback. candidate_devices + their normalized phone show why
        // $from did/didn't match a device (empty list = user has no devices in
        // any of their workspaces; phone mismatch = CC/format differs from $from).
        \Log::info('[EXT-SEND] resolved', [
            'user_id'      => $user->id,
            'current_ws'   => $user->current_workspace_id,
            'member_ws'    => $memberWsIds->values()->all(),
            'from'         => $from,
            'resolved_ws'  => $wsId,
            'engine'       => $engine,
            'route'        => $wsId ? 'TEAM-INBOX (InboxDispatcher)' : 'LEGACY sendRaw (no workspace resolved)',
            'candidate_devices' => \App\Models\Device::query()
                ->where(fn ($q) => $q->where('user_id', $user->id)->orWhereIn('workspace_id', $memberWsIds))
                ->where('active', true)
                ->get(['id', 'workspace_id', 'user_id', 'country_code', 'phone_number'])
                ->map(fn ($d) => [
                    'id'    => $d->id,
                    'ws'    => $d->workspace_id,
                    'user'  => $d->user_id,
                    'phone' => preg_replace('/\D+/', '', (string) ($d->country_code . $d->phone_number)),
                ])->all(),
        ]);

        // Store media once — keep the disk PATH (media_path) + broad bucket
        // (media_type) the inbox dispatcher expects.
        $mediaPath = null; $mediaType = null;
        if ($request->hasFile('image_file')) {
            $file      = $request->file('image_file');
            $mime      = $file->getMimeType() ?: 'application/octet-stream';
            $mediaType = match (true) {
                str_starts_with($mime, 'image/') => 'image',
                str_starts_with($mime, 'video/') => 'video',
                str_starts_with($mime, 'audio/') => 'audio',
                default                          => 'document',
            };
            $orig      = preg_replace('/[^A-Za-z0-9._-]+/', '_', (string) $file->getClientOriginalName()) ?: 'file';
            $mediaPath = $file->storeAs('chat-media', \Illuminate\Support\Str::random(10) . '__' . $orig, media_disk());
        }

        // Route the send through the Team Inbox so it appears in the
        // conversation thread — extension sends previously went through
        // WhatsAppDispatcher::sendRaw (message history only), so they never
        // showed in /team-inbox. Find-or-create the conversation on the SAME
        // key the inbound webhook + /chat Quick Send use (workspace + engine +
        // origin inbox/chatbot + raw_jid), then send via InboxDispatcher.
        if ($wsId) {
            // Use the SAME JID form the inbound webhook + /chat store
            // (number@s.whatsapp.net), so this send MERGES into the customer's
            // existing thread instead of creating a duplicate with a bare-number
            // raw_jid that the team-inbox device filter can't line up.
            $toJid = str_contains($to, '@') ? $to : $to . '@s.whatsapp.net';

            // SENDER OWNERSHIP. `$from` arrives from the extension client and is
            // written straight onto the row below, where resolveDevicePhone()
            // gives from_number the HIGHEST priority (InboxDispatcher:437) and
            // it becomes the Node bridge URL segment. Unvalidated, a caller
            // could name any number and the bridge would try to send from that
            // paired socket — a cross-workspace send when workspaces share a
            // node server. So a supplied number must belong to THIS workspace.
            //
            // Checked against both stores: `devices` (Unofficial) and
            // `wa_provider_configs` (WABA/Twilio). Phone columns are encrypted
            // at rest, so the comparison happens in PHP, not SQL.
            $deviceId = null;
            $pickedEngine = null;   // engine of the number the operator chose
            if ($from !== '') {
                $device = \App\Models\Device::query()
                    ->where('workspace_id', $wsId)->where('active', true)->get()
                    ->first(fn ($d) => preg_replace('/\D+/', '', (string) ($d->country_code . $d->phone_number)) === $from);
                if ($device) { $deviceId = $device->id; $pickedEngine = 'baileys'; }

                // Not a paired phone → check WABA/Twilio provider configs and
                // capture WHICH engine owns the number, so the send leaves on it.
                $cfg = $device ? null : \App\Models\WaProviderConfig::query()
                    ->where('workspace_id', $wsId)
                    ->get(['id', 'provider', 'phone_number'])
                    ->first(fn ($c) => preg_replace('/\D+/', '', (string) $c->phone_number) === $from);
                if ($cfg) $pickedEngine = (string) $cfg->provider;
                $ownsOfficial = (bool) $cfg;

                if (!$device && !$ownsOfficial) {
                    // DROP the untrusted value rather than reject the send.
                    // Clearing $from is what actually closes the hole — the
                    // number never reaches the row, so it can never become the
                    // Node bridge URL segment. Resolution then falls through to
                    // the conversation's own device / the workspace primary,
                    // which is by definition a number this workspace owns.
                    //
                    // Failing the request instead would punish the common
                    // innocent case (a stale sender cached in the extension
                    // after a device was removed or re-paired) for what is
                    // almost never an attack, and a 422 there tells a genuine
                    // attacker exactly which numbers exist. Silently sending
                    // from our own number is both safe and correct.
                    \Log::warning('[EXT] from_number not owned by workspace — ignored, falling back to workspace sender', [
                        'ws' => $wsId, 'user' => $user->id, 'from' => $from,
                    ]);
                    $from = '';
                }
            }

            // Route on the PICKED number's engine — a multi-engine workspace can
            // send from any connected channel, so the send must leave on the
            // engine that owns from_number, NOT the workspace's single "active"
            // engine. Stamped onto inbox_messages.provider below, which
            // InboxDispatcher::resolveProvider() honours with highest priority.
            // Falls back to the active engine when from_number was empty/dropped.
            $sendEngine = $pickedEngine ?: $engine;

            // ONE THREAD PER NUMBER — see ConversationResolver. The origin
            // allow-list this replaces had to be extended by hand every time a
            // new surface invented an origin value; the resolver ignores origin
            // entirely, so it cannot fall behind again.
            $conv = \App\Services\Inbox\ConversationResolver::find($wsId, $to);

            if (!$conv) {
                $conv = \App\Models\Conversation::create([
                    'user_id'          => $user->id,
                    'workspace_id'     => $wsId,
                    'device_id'        => $deviceId,
                    'title'            => $to,
                    'preview'          => $body !== '' ? $body : '[media]',
                    'status'           => 'pending',
                    'platform'         => 'W',
                    'provider'         => $sendEngine,
                    'origin'           => 'inbox',
                    'raw_jid'          => $toJid,
                    'recipients_count' => 1,
                    'last_message_at'  => now(),
                ]);
            } else {
                $patch = ['preview' => $body !== '' ? $body : '[media]', 'last_message_at' => now()];
                // Re-home onto the LIVE sending device when the stored device_id
                // is empty OR points to a DELETED device row (the number was
                // re-paired to a new device id). Otherwise deviceAlive() hides the
                // thread (dead device) and the device filter never matches it.
                $staleDevice = $conv->device_id
                    && !\App\Models\Device::whereKey($conv->device_id)->exists();
                if ($deviceId && (!$conv->device_id || $staleDevice)) {
                    $patch['device_id'] = $deviceId;
                }
                // A new outbound reopens a closed/resolved/spam thread so it
                // returns to the active queue — re-pairing a device auto-closes
                // its conversations, which would otherwise stay hidden forever.
                if (in_array((string) $conv->inbox_status, ['closed', 'resolved', 'spam'], true)) {
                    $patch['inbox_status'] = 'open';
                }
                // Normalise a legacy bare-number raw_jid to the JID form.
                if ($conv->raw_jid === $to && $toJid !== $to) $patch['raw_jid'] = $toJid;
                $conv->update($patch);
            }

            $msg = \App\Models\InboxMessage::create([
                'conversation_id' => $conv->id,
                'user_id'         => $user->id,
                'direction'       => 'out',
                'from_number'     => $from ?: null,
                'to_number'       => $to,
                'body'            => $body !== '' ? $body : null,
                'media_path'      => $mediaPath,
                'media_type'      => $mediaType,
                'status'          => 'pending',
                // Pin the picked number's engine so InboxDispatcher sends on it
                // (highest-priority signal) regardless of the thread's default.
                'provider'        => $sendEngine,
                'meta'            => ['source' => 'extension'] + ($to !== '' ? ['target_jid' => $to] : []),
            ]);

            \Log::info('[EXT-SEND] → InboxDispatcher (TEAM-INBOX path)', [
                'ws' => $wsId, 'conv_id' => $conv->id, 'inbox_msg_id' => $msg->id, 'device_id' => $deviceId, 'engine' => $sendEngine,
            ]);
            try {
                $result = app(\App\Services\InboxDispatcher::class)->send($msg, $conv->platform ?? 'W');
            } catch (\Throwable $e) {
                $msg->update(['status' => 'failed', 'failure_reason' => mb_substr($e->getMessage(), 0, 190)]);
                return response()->json(['status' => 'error', 'message' => $e->getMessage()], 422);
            }
            // InboxDispatcher::send() returns the result but leaves the row
            // 'pending' — the CALLER stamps the outcome (mirrors TeamInbox
            // reply). Without this the send succeeds but "Recent sends" shows
            // Pending forever.
            if (($result['ok'] ?? false) === true) {
                $fields = ['status' => 'sent', 'sent_at' => now()];
                if (!empty($result['provider_id'])) {
                    $fields['meta'] = array_merge(is_array($msg->meta) ? $msg->meta : [], ['wa_message_id' => (string) $result['provider_id']]);
                }
                $msg->update($fields);
                $conv->forceFill(['last_message_at' => now(), 'last_outbound_at' => now(), 'preview' => mb_substr($body !== '' ? $body : '[media]', 0, 200)])->save();
                return response()->json(['status' => 'success', 'result' => $result, 'conversation_id' => $conv->id]);
            }
            $err = (string) ($result['error'] ?? $result['message'] ?? 'Send failed.');
            $msg->update(['status' => 'failed', 'failure_reason' => mb_substr($err, 0, 190)]);
            return response()->json(['status' => 'error', 'message' => $err], 422);
        }

        // Fallback (no workspace context) — legacy raw send, unchanged.
        \Log::warning('[EXT-SEND] FALLBACK → sendRaw (LEGACY chat path) — no workspace resolved for this user; message will NOT appear in /team-inbox', [
            'user_id' => $user->id, 'from' => $from, 'to' => $to,
        ]);
        // Same sender-ownership rule as the workspace path above, which lives
        // inside `if ($wsId)` and therefore never runs here. Without this the
        // legacy branch remained a way to name any number and have the bridge
        // send from it. No workspace context exists on this path, so the scope
        // that applies is the user's OWN devices.
        if ($from !== '') {
            $ownDevice = Device::query()
                ->where('user_id', $user->id)
                ->where('active', true)
                ->get(['id', 'country_code', 'phone_number', 'status'])
                ->first(fn ($d) => preg_replace('/\D+/', '', (string) ($d->country_code . $d->phone_number)) === $from);

            if (!$ownDevice) {
                // Substitute the user's own connected device rather than fail:
                // sendRaw resolves the socket FROM this number, so blanking it
                // would just break the send. If they have no connected device
                // there is nothing legitimate to send from at all.
                $fallback = Device::query()
                    ->where('user_id', $user->id)
                    ->where('active', true)
                    ->where('status', 'connected')
                    ->get(['id', 'country_code', 'phone_number'])
                    ->first();

                \Log::warning('[EXT-SEND] from_number not owned by user — substituting own device', [
                    'user_id' => $user->id, 'requested' => $from,
                    'substituted' => $fallback ? 'yes' : 'none-available',
                ]);

                if (!$fallback) {
                    return response()->json([
                        'status'  => 'error',
                        'message' => 'No connected device available to send from.',
                    ], 422);
                }
                $from = preg_replace('/\D+/', '', (string) ($fallback->country_code . $fallback->phone_number));
            }
        }

        $params = ['from_number' => $from, 'to_number' => $to, 'body' => $body];
        if ($mediaPath) { $params['media_path'] = media_url($mediaPath); $params['media_type'] = $mediaType; }

        try {
            $result = $dispatcher->sendRaw($params, $user->id, 'W');
        } catch (\Throwable $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 422);
        }

        $ok = !isset($result['success']) || $result['success'] !== false;
        if (!$ok) {
            return response()->json([
                'status'  => 'error',
                'message' => $result['message'] ?? $result['error'] ?? 'Send failed.',
            ], 422);
        }

        return response()->json(['status' => 'success', 'result' => $result]);
    }

    /** GET /api/ext/contact-csv — export this user's send history as CSV. */
    public function contactCsv(Request $request)
    {
        $user = $request->user();
        $rows = Message::query()
            ->where('user_id', $user->id)
            ->orderByDesc('id')
            ->limit(5000)
            ->get(['to_number', 'body', 'status', 'created_at']);

        $sanitize = function ($v) {
            $v = (string) ($v ?? '');
            // CSV formula-injection guard.
            if ($v !== '' && in_array($v[0], ['=', '+', '-', '@', "\t", "\r"], true)) {
                $v = "'" . $v;
            }
            return '"' . str_replace('"', '""', $v) . '"';
        };

        $lines = ['phone_number,message,status,sent_at'];
        foreach ($rows as $m) {
            $lines[] = implode(',', [
                $sanitize($m->to_number),
                $sanitize(mb_substr((string) $m->body, 0, 200)),
                $sanitize($m->status),
                $sanitize(optional($m->created_at)->toDateTimeString()),
            ]);
        }

        return response(implode("\n", $lines), 200, [
            'Content-Type'        => 'text/csv',
            'Content-Disposition' => 'attachment; filename="history.csv"',
        ]);
    }

    /** Map our string message status to the 1/2/0 the extension expects. */
    private function statusCode($status): int
    {
        $s = strtolower((string) $status);
        if (in_array($s, ['sent', 'delivered', 'read', '1'], true)) return 1;
        if (in_array($s, ['failed', 'error', '2'], true)) return 2;
        return 0;
    }
}
