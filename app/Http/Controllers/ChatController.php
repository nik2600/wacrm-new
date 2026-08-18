<?php

namespace App\Http\Controllers;

use App\Models\Contact;
use App\Models\ContactGroup;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\WaTemplate;
use App\Services\WalletService;
use App\Services\WhatsAppDispatcher;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Chat / message-queue surface.
 *
 * Replaces the old D:\wadesk_2806\New folder
 * MessageController — that file mixed `DB::table()` calls with
 * Eloquent, returned multiple shapes per endpoint, and stitched
 * together queues with `MAX(...) ... GROUP BY queue_id` over the
 * messages table. Here:
 *
 *   - Conversation rows are first-class (no GROUP BY).
 *   - Every query goes through Eloquent + scopes (no raw DB).
 *   - JSON responses follow `{ data: ..., meta: ... }` consistently.
 *   - The page-render endpoint hands the JS a small bootstrap
 *     payload (counts, filters, devices) so the first paint
 *     doesn't need a round-trip.
 */
class ChatController extends Controller
{
    /**
     * Outbound provider — Node bridge / Meta Cloud / Twilio. Injected
     * by the container so tests can swap a fake. The dispatcher is
     * env-gated; if no provider is configured it returns a
     * "local-only" result and the message stays as-saved.
     */
    public function __construct(
        private readonly WhatsAppDispatcher $dispatcher,
        private readonly WalletService $wallet,
    )
    {
    }

    // -----------------------------------------------------------------
    // Page render
    // -----------------------------------------------------------------

    public function index(): View
    {
        return view('user.chat.index', [
            'chatState' => $this->initialState(),
        ]);
    }

    // -----------------------------------------------------------------
    // JSON: conversations list
    // -----------------------------------------------------------------

    public function conversations(Request $request): JsonResponse
    {
        $request->validate([
            'filter'    => 'nullable|string|in:all,scheduled,archived,sent,pending,failed',
            'sort'      => 'nullable|string|in:date-desc,date-asc,name-asc,name-desc',
            'q'         => 'nullable|string|max:255',
            'device_id' => 'nullable|integer',
        ]);

        $userId = Auth::id();
        $sort   = $request->string('sort')->toString() ?: 'date-desc';

        // SQL-side: cheap, indexable filters only. Search + name-sort
        // happen in PHP after hydration because title/preview are
        // encrypted-at-rest (ciphertext can't be LIKE'd or ORDER BY'd).
        // Engine filter — show conversations for EVERY engine the workspace
        // has active (multi-engine aware), not just the default one.
        // forCurrentEngine() whereIn's the workspace's enabled engine set, so a
        // workspace running both Unofficial + WABA sees BOTH engines' queues.
        // (Previously forEngine(for()) pinned to the single default engine, so a
        // Baileys queue vanished on a WABA-default workspace even though it sent.)
        $items = Conversation::query()
            ->forCurrentWorkspace()
            ->chatOnly()  // hide campaign-origin convos — they live at /wa-campaigns
            ->forCurrentEngine()
            // /chat is a SEND surface: show ONLY threads we actually sent to from
            // here (each Quick Send writes an outbound `Message` row). Inbound-only
            // conversations (customer messaged us, we never sent — legacy Message
            // rows are direction='in' with no conversation_id, replies live as
            // InboxMessage) must NOT appear here; they belong to Team Inbox.
            ->whereHas('messages', fn ($q) => $q->where('direction', 'out'))
            ->filtered($request->string('filter')->toString() ?: 'all')
            ->when($request->filled('device_id'), fn ($q) => $q->where('device_id', $request->integer('device_id')))
            ->sorted($sort)
            ->limit(500)
            ->get();

        $items = Conversation::filterBySearch($items, $request->string('q')->toString());
        $items = Conversation::sortByKey($items, $sort)->take(200);

        return response()->json([
            'data' => $items->map(fn ($c) => $this->presentConversation($c))->values(),
            'meta' => $this->counts($userId),
        ]);
    }

    // -----------------------------------------------------------------
    // JSON: single conversation + messages
    // -----------------------------------------------------------------

    public function show(int $id): JsonResponse
    {
        $conversation = Conversation::query()
            ->forCurrentWorkspace()
            ->with(['messages'])
            ->findOrFail($id);

        // Re-derive the badge on open. Threads carry a status stamped by
        // whatever rule was in force at their last send, so an existing queue
        // would keep showing a stale 'partial' until someone sent to it again.
        // Recomputing here is one query on a thread the user is already
        // loading, and it makes the badge self-heal on view.
        $rolled = $this->rollupConversationStatus($conversation);
        if ($rolled !== $conversation->status) {
            $conversation->forceFill(['status' => $rolled])->save();
        }

        return response()->json([
            'data' => [
                'conversation' => $this->presentConversation($conversation),
                'messages'     => $conversation->messages->map(fn ($m) => $this->presentMessage($m))->all(),
            ],
        ]);
    }

    // -----------------------------------------------------------------
    // JSON: create a new conversation (queue)
    // -----------------------------------------------------------------

    public function createConversation(Request $request): JsonResponse
    {
        $data = $request->validate([
            'title'            => 'required|string|max:191',
            // Multi-engine: the compose modal posts composite `engine:id`
            // sender keys via `sender` (single) / `sender[]` (multi). The
            // legacy bare-int `device_id` / `device_ids[]` fields stay
            // accepted so single-engine forms + un-migrated callers are
            // byte-identical.
            'sender'           => 'nullable|string|max:64',
            'sender_keys'      => 'nullable|array|max:20',
            'sender_keys.*'    => 'string|max:64|distinct',
            'device_id'        => 'nullable|integer',
            // Multi-device (plan-gated). When the workspace's plan has
            // `multipledevice` ON, the compose modal submits device_ids[]
            // and the server splits recipients round-robin across them.
            'device_ids'       => 'nullable|array|max:20',
            // No DB-existence rule — WABA / Twilio workspaces submit
            // wa_provider_configs ids here, not devices.id. The
            // intersect block below scopes to the workspace's engine
            // pool, so a tampered id can't slip through.
            'device_ids.*'     => 'integer|distinct',
            'recipient_type'   => 'required|in:manual,group',
            'recipients'       => 'required_if:recipient_type,manual|string',
            'contact_group_id' => 'required_if:recipient_type,group|integer|exists:contact_groups,id',
            'body'             => 'required|string|max:4096',
            'scheduled_at'     => 'nullable|string',
            'timezone'         => ['nullable', 'string', \Illuminate\Validation\Rule::in(\DateTimeZone::listIdentifiers())],
        ]);

        // Resolve the user's timezone for parsing — picker first, then
        // workspace default, then app config.
        $tz = $data['timezone']
            ?? optional(\Illuminate\Support\Facades\Auth::user()?->currentWorkspace)->timezone
            ?? auth()->user()?->timezone
            ?? config('app.timezone', 'UTC');

        // Parse the local datetime IN the user's timezone so it's
        // converted to UTC correctly before the DB stores it.
        if (!empty($data['scheduled_at'])) {
            try {
                $parsed = Carbon::parse($data['scheduled_at'], $tz);
                if ($parsed->isPast() || $parsed->lt(now()->addMinute())) {
                    return response()->json([
                        'message' => 'Scheduled time must be at least 1 minute in the future (in ' . $tz . ').',
                    ], 422);
                }
                $data['scheduled_at'] = $parsed->setTimezone('UTC')->toDateTimeString();
                $data['_tz'] = $tz; // preserved so dispatcher can pass it to Node
            } catch (\Throwable $e) {
                return response()->json(['message' => 'Could not parse scheduled date.'], 422);
            }
        }

        // Resolve recipient list. Manual = comma/newline-separated phone
        // numbers. Group = pull mobiles from contacts whose
        // contact_group JSON array contains the group id (the same
        // pattern ContactGroup::getContactsCountAttribute uses, since
        // that column is encrypted-array-cast).
        $numbers = $this->resolveRecipients($data);
        if (!count($numbers)) {
            return response()->json([
                'message' => 'No recipients resolved. Add at least one number or pick a non-empty group.',
            ], 422);
        }

        $isScheduled = !empty($data['scheduled_at']);

        // Resolve which devices send this queue. Two inputs are
        // accepted — the legacy single `device_id` and the new
        // `device_ids[]` array used by multi-device-capable plans.
        // The plan-gate is the source of truth: if the workspace
        // can't multi-device, we silently collapse device_ids to its
        // first entry so a tampered request can't bypass the plan.
        $canMultiDevice = \App\Services\PlanLimitGuard::hasFeature(
            Auth::user()?->currentWorkspace,
            'multipledevice'
        );

        $wsId = Auth::user()?->current_workspace_id;

        // Auto-save manually-typed numbers to the Contacts table (dedup by
        // phone hash) so a one-off Quick Send / Chat recipient is never lost.
        // Group sends already come from saved contacts, so skip those.
        if ($wsId && ($data['recipient_type'] ?? null) === 'manual') {
            $uid = Auth::id();
            foreach ($numbers as $num) {
                Contact::rememberPhone((int) $wsId, $uid, (string) $num);
            }
        }

        // Multi-engine: the compose modal posts composite `engine:id` sender
        // keys (`sender` single / `sender_keys[]` multi). Resolve them via
        // senderForKey() so a forged/stale key for a sender this workspace
        // can't use is rejected, then collapse to (engine, ids). The CHOSEN
        // engine — not WorkspaceEngine::for() — drives both the device-table
        // lookup and the conversation provider stamp, so a workspace running
        // several engines at once routes this queue over the channel the
        // operator actually picked. Mixed-engine picks aren't supported by
        // the round-robin from_number logic, so the first pick's engine wins
        // and only same-engine senders are kept.
        $pickedEngine = null;
        $pickedIds    = [];
        $senderKeys = [];
        if (!empty($data['sender_keys']) && is_array($data['sender_keys'])) {
            $senderKeys = $data['sender_keys'];
        } elseif (!empty($data['sender'])) {
            $senderKeys = [$data['sender']];
        }
        foreach ($senderKeys as $key) {
            $picked = \App\Services\WorkspaceEngine::senderForKey($wsId, $key);
            if (!$picked) continue;
            if ($pickedEngine === null) $pickedEngine = (string) $picked['engine'];
            if ((string) $picked['engine'] !== $pickedEngine) continue; // ignore cross-engine picks
            $pickedIds[] = (int) $picked['id'];
        }
        $pickedIds = array_values(array_unique($pickedIds));

        $requestedIds = [];
        if (!empty($pickedIds)) {
            // New unified picker path.
            $requestedIds = $pickedIds;
        } else {
            // Legacy fallback: bare-int device fields (single-engine forms /
            // un-migrated callers). Identical to the pre-multi-engine path.
            if (!empty($data['device_ids']) && is_array($data['device_ids'])) {
                $requestedIds = array_values(array_unique(array_map('intval', $data['device_ids'])));
            }
            if (!empty($data['device_id'])) {
                // Legacy single-device path still wins when device_ids[] is empty.
                $requestedIds = $requestedIds ?: [(int) $data['device_id']];
            }
        }
        if (!$canMultiDevice && count($requestedIds) > 1) {
            $requestedIds = [$requestedIds[0]];
        }

        // Intersect with the workspace's engine-appropriate sender pool
        // so a forged id (from another workspace, or a wrong-engine
        // pseudo-device) can't end up in the queue. Baileys workspaces
        // intersect with `devices`; WABA / Twilio with their
        // `wa_provider_configs` rows surfaced as pseudo-devices. The engine
        // is the operator's pick when the unified picker was used, else the
        // workspace default (legacy single-engine behaviour, unchanged).
        $composeEngine = $pickedEngine ?: \App\Services\WorkspaceEngine::for($wsId);
        if ($composeEngine === \App\Services\WorkspaceEngine::ENGINE_BAILEYS) {
            $devices = $requestedIds
                ? \App\Models\Device::query()->forCurrentWorkspace()->whereIn('id', $requestedIds)->get()->keyBy('id')
                : collect();
        } else {
            $devices = $requestedIds
                ? \App\Models\WaProviderConfig::query()
                    ->where('workspace_id', Auth::user()?->current_workspace_id)
                    ->where('provider', $composeEngine)
                    ->whereIn('id', $requestedIds)
                    ->get()
                    ->map(fn ($cfg) => (object) [
                        'id'           => $cfg->id,
                        'country_code' => '',
                        'phone_number' => (string) $cfg->phone_number,
                        'device_name'  => $cfg->display_label ?: (strtoupper($composeEngine) . ' #' . $cfg->id),
                    ])
                    ->keyBy('id')
                : collect();
        }

        // Preserve the operator's pick order — keyBy() above doesn't.
        $orderedDevices = collect($requestedIds)
            ->map(fn ($id) => $devices->get($id))
            ->filter()
            ->values();

        // Per-device phone digits (already non-PII; safe to keep in plain arrays).
        $devicePhones = $orderedDevices->map(fn ($d) =>
            preg_replace('/\D+/', '', (string) ($d->country_code . $d->phone_number)) ?: null
        )->all();

        $primaryDeviceId    = $orderedDevices->first()?->id;
        $primaryDevicePhone = $devicePhones[0] ?? null;

        // Stamp the conversation's provider + legacy platform code
        // ('W'/'WB'/'T') from the engine resolved above — the operator's
        // CHOSEN engine when the unified picker was used, else the workspace
        // default ($composeEngine === for() in the legacy single-engine
        // path). Without an engine stamp, every new compose stamped 'W' even
        // on WABA workspaces and the engine-aware list filter immediately
        // hid the just-created conversation.
        $engine  = $composeEngine;
        $legacy  = \App\Enums\WaProvider::tryFrom($engine)?->legacyCode() ?? 'W';

        [$conversation, $messages, $allConvos] = DB::transaction(function () use ($data, $numbers, $isScheduled, $primaryDeviceId, $devicePhones, $primaryDevicePhone, $legacy, $engine) {
            $wsId = (int) (Auth::user()->current_workspace_id ?? 0);
            $deviceCount = count($devicePhones);
            $rows       = [];
            $byNumber   = [];     // raw_jid → convo (dedupe within this send)
            $firstConvo = null;

            // ONE conversation PER NUMBER, shared with Team Inbox. Find-or-create
            // each on the SAME key the inbound webhook matches (workspace + engine
            // + origin='inbox' + raw_jid), so a reply from this number lands in
            // THIS thread instead of spawning a duplicate, and these Quick Send
            // messages appear in /team-inbox. Sending to N numbers = N threads,
            // not one blind queue → no more "multiple convos for the same number".
            foreach (array_values($numbers) as $i => $number) {
                $rawJid  = preg_replace('/\D+/', '', (string) $number) ?: (string) $number;
                $fromNum = $deviceCount > 0 ? ($devicePhones[$i % $deviceCount] ?? $primaryDevicePhone) : $primaryDevicePhone;

                // The inbound webhook (WaInboundController) stores raw_jid in the
                // JID shape ('919…@s.whatsapp.net' for Baileys) while Quick Send
                // historically matched digits-only ('919…'). So a Quick Send to a
                // number that already had an inbound thread FORKED a second, blank
                // team-inbox conversation — the fromMe echo mirror landed on one
                // row and this Quick Send on the other. Match BOTH shapes across
                // both jid columns (same widening the webhook uses) so we reuse the
                // exact thread the team inbox shows → one thread per number.
                $convo = $byNumber[$rawJid]
                    ?? \App\Services\Inbox\ConversationResolver::find($wsId, $rawJid);

                if (!$convo) {
                    $convo = Conversation::create([
                        'user_id'            => Auth::id(),
                        'workspace_id'       => $wsId,
                        'device_id'          => $primaryDeviceId,
                        'contact_group_id'   => $data['contact_group_id'] ?? null,
                        'title'              => $rawJid,   // inbound reply backfills the real name
                        'preview'            => $data['body'],
                        'status'             => $isScheduled ? 'scheduled' : 'pending',
                        'platform'           => $legacy,
                        'provider'           => $engine,
                        'origin'             => 'inbox',
                        'raw_jid'            => $rawJid,
                        'recipients_count'   => 1,
                        'last_message_at'    => now(),
                        'scheduled_at'       => $isScheduled ? Carbon::parse($data['scheduled_at']) : null,
                        'scheduled_timezone' => $isScheduled ? ($data['_tz'] ?? null) : null,
                    ]);
                } else {
                    $convo->update([
                        'preview'         => $data['body'],
                        'last_message_at' => now(),
                        'status'          => $isScheduled ? 'scheduled' : ($convo->status === 'scheduled' ? 'pending' : $convo->status),
                    ]);
                }

                $byNumber[$rawJid] = $convo;
                $firstConvo        = $firstConvo ?: $convo;

                $rows[] = Message::create([
                    'conversation_id' => $convo->id,
                    'user_id'         => Auth::id(),
                    'workspace_id'    => $wsId,
                    'direction'       => 'out',
                    'from_number'     => $fromNum,
                    'to_number'       => $number,
                    'body'            => $data['body'],
                    'status'          => $isScheduled ? 'scheduled' : 'pending',
                    'scheduled_at'    => $isScheduled ? Carbon::parse($data['scheduled_at']) : null,
                ]);
            }

            return [$firstConvo, $rows, array_values($byNumber)];
        });

        // Hand each message off to the provider so it actually leaves the
        // box. Without this loop the rows would sit in 'pending' forever
        // — the previous version blindly stamped them 'sent' in the DB
        // even though no dispatcher call had been made.
        \Illuminate\Support\Facades\Log::info('[CHAT] createConversation → dispatching', [
            'conv_id'             => $conversation->id,
            'primary_device_id'   => $primaryDeviceId,
            'primary_device_phone'=> $primaryDevicePhone,
            'device_ids'          => $orderedDevices->pluck('id')->all(),
            'multi_device'        => $orderedDevices->count() > 1,
            'recipients'          => count($numbers),
            'scheduled'           => $isScheduled,
        ]);
        if (!$isScheduled) {
            // Billing is plan-first via OverflowBilling inside the
            // dispatcher (free under the workspace's monthly_messages_limit,
            // 1 wallet credit only on overflow). No wallet pre-gate / charge
            // / refund here — that was wallet-first and double-billed on top
            // of the dispatcher's meter, and blocked an active plan at
            // wallet=0.
            foreach ($messages as $message) {
                try {
                    $result = $this->dispatcher->send($message, $conversation->platform ?: $legacy);
                    $this->applyDispatchResult($message, $result, false);
                } catch (\App\Exceptions\PlanLimitReachedException $e) {
                    \Illuminate\Support\Facades\Log::warning('[CHAT] createConversation plan limit reached — dispatch skipped', [
                        'user_id' => Auth::id(),
                        'msg_id'  => $message->id,
                        'reason'  => $e->getMessage(),
                    ]);
                    $message->update(['status' => 'failed', 'failure_reason' => mb_substr($e->getMessage(), 0, 191)]);
                }
            }
        } else {
            foreach ($messages as $message) {
                $result = $this->dispatcher->schedule($message, $conversation->platform ?: $legacy);
                $this->applyDispatchResult($message, $result, true);
            }
        }

        // Roll up EACH per-number conversation's status from its own messages
        // so every thread's UI pill reflects reality (sent / failed / scheduled).
        foreach ($allConvos as $c) {
            $last = collect($messages)->where('conversation_id', $c->id)->last();
            $this->refreshConversationAfterSend($c->refresh(), $last);
        }

        return response()->json([
            'data' => ['conversation' => $this->presentConversation($conversation->refresh())],
            'meta' => $this->counts(Auth::id()),
        ], 201);
    }

    // -----------------------------------------------------------------
    // JSON: rich details panel — feeds the right-side drawer
    // -----------------------------------------------------------------

    /**
     * Same conversation as show(), plus the breakdowns the drawer
     * panels render: per-recipient roll-up, attachments list, and
     * a status histogram. Each is one Eloquent pass — no GROUP BY
     * SQL because to_number is encrypted-at-rest.
     */
    public function details(int $id): JsonResponse
    {
        $c = Conversation::query()
            ->forCurrentWorkspace()
            ->with(['messages'])
            ->findOrFail($id);

        $messages = $c->messages;

        // Roll up by recipient. Encrypted to_number values are
        // grouped in PHP after Eloquent decrypts (the DB can't
        // GROUP BY ciphertext).
        $byRecipient = $messages
            ->filter(fn ($m) => $m->direction === 'out' && $m->to_number)
            ->groupBy('to_number')
            ->map(function ($group, $number) {
                $latest = $group->sortByDesc('id')->first();
                return [
                    'to_number'      => $number,
                    'last_status'    => $latest->status,
                    'last_at'        => $latest->sent_at?->toIso8601String() ?? $latest->created_at?->toIso8601String(),
                    'last_at_lbl'    => optional($latest->sent_at ?: $latest->created_at)->format('M d, H:i'),
                    'message_count'  => $group->count(),
                    'failure_reason' => $latest->failure_reason,
                ];
            })
            ->values();

        $attachments = $messages
            ->filter(fn ($m) => $m->media_path)
            ->map(function ($m) {
                $base = basename($m->media_path);
                $name = str_contains($base, '__') ? substr($base, strpos($base, '__') + 2) : $base;
                // Size/mime from the active media disk (cloud or local).
                $d = media_storage();
                $has = false;
                try { $has = $d->exists($m->media_path); } catch (\Throwable $e) {}
                return [
                    'id'         => $m->id,
                    'body'       => $m->body,
                    'media_url'  => media_url($m->media_path),
                    'media_type' => $m->media_type,
                    'media_name' => $name,
                    'media_size' => $has ? $d->size($m->media_path) : null,
                    'media_mime' => $has ? ($d->mimeType($m->media_path) ?: null) : null,
                    'time'       => $m->display_time,
                ];
            })
            ->values();

        // Funnel-cumulative counts so the KPI cards read like WhatsApp: a
        // delivered message was also "sent", a read one was also "delivered".
        // (Exact-status counts made "Sent" drop to 0 the moment a receipt moved
        // the row to "delivered" — confusing. Now Sent >= Delivered >= Read.)
        $out    = $messages->where('direction', 'out');
        $cnt    = fn ($s) => $out->where('status', $s)->count();
        $sentX  = $cnt('sent');
        $delivX = $cnt('delivered');
        $readX  = $cnt('read');
        $stats = [
            'pending'   => $cnt('pending'),
            'scheduled' => $cnt('scheduled'),
            'failed'    => $cnt('failed'),
            'sent'      => $sentX + $delivX + $readX,
            'delivered' => $delivX + $readX,
            'read'      => $readX,
        ];

        return response()->json([
            'data' => [
                'conversation' => $this->presentConversation($c),
                'messages'     => $messages->map(fn ($m) => $this->presentMessage($m))->values(),
                'recipients'   => $byRecipient,
                'attachments'  => $attachments,
                'stats'        => array_merge($stats, [
                    'total_messages'   => $messages->count(),
                    'recipients_count' => $byRecipient->count(),
                ]),
            ],
        ]);
    }

    /**
     * Node → Laravel: delivery/read receipt for a NON-broadcast send (Quick
     * Send, /chat, team-inbox). The Baileys receipt handler used to forward
     * ONLY broadcast receipts, so these messages were stuck at "sent" forever
     * (Quick Send showed Delivered 0 even after the double/blue ticks). We match
     * the row by its stored WhatsApp message id (messages.meta->wa_message_id)
     * and advance the status — never downgrading (read > delivered > sent).
     */
    public function nodeChatMessageStatus(Request $request): JsonResponse
    {
        $expected = node_token();
        $given    = (string) $request->header('X-Node-Token');
        if ($expected === '' || !hash_equals($expected, $given)) {
            return response()->json(['ok' => false, 'message' => 'forbidden'], 403);
        }
        $data = $request->validate([
            'whatsapp_message_id' => 'required|string|max:191',
            'status'              => 'required|in:delivered,read',
            'delivered_at'        => 'nullable|date',
            'read_at'             => 'nullable|date',
        ]);

        $rank = ['pending' => 0, 'scheduled' => 0, 'sent' => 1, 'delivered' => 2, 'read' => 3];
        $newRank = $rank[$data['status']] ?? 0;
        $when = static function (?string $v): ?string {
            if (empty($v)) return null;
            try { return \Illuminate\Support\Carbon::parse($v)->toDateTimeString(); }
            catch (\Throwable $e) { return now()->toDateTimeString(); }
        };
        $deliveredAt = $when($data['delivered_at'] ?? null);
        $readAt      = $when($data['read_at'] ?? null);
        $updated = 0;

        // messages table (Quick Send / /chat) — matched by meta->wa_message_id.
        \App\Models\Message::query()
            ->where('meta->wa_message_id', $data['whatsapp_message_id'])
            ->where('direction', 'out')
            ->get()
            ->each(function ($m) use ($data, $rank, $newRank, $deliveredAt, $readAt, &$updated) {
                if ($newRank <= ($rank[$m->status] ?? 0)) return;   // never downgrade
                $upd = ['status' => $data['status']];
                if ($deliveredAt) $upd['delivered_at'] = $deliveredAt;
                if ($readAt)      $upd['read_at']      = $readAt;
                // A read implies delivered — backfill the delivered stamp/tick.
                if ($data['status'] === 'read' && empty($m->delivered_at)) {
                    $upd['delivered_at'] = $readAt ?: now()->toDateTimeString();
                }
                $m->forceFill($upd)->save();
                $updated++;
            });

        // Mirror onto the team-inbox row (same wa id) so its ticks match.
        \App\Models\InboxMessage::query()
            ->where('meta->wa_message_id', $data['whatsapp_message_id'])
            ->where('direction', 'out')
            ->get()
            ->each(function ($m) use ($data, $rank, $newRank, $deliveredAt, $readAt) {
                if ($newRank <= ($rank[$m->status] ?? 0)) return;
                $upd = ['status' => $data['status']];
                if ($deliveredAt) $upd['delivered_at'] = $deliveredAt;
                if ($readAt)      $upd['read_at']      = $readAt;
                if ($data['status'] === 'read' && empty($m->delivered_at)) {
                    $upd['delivered_at'] = $readAt ?: now()->toDateTimeString();
                }
                $m->forceFill($upd)->save();
            });

        return response()->json(['ok' => true, 'updated' => $updated]);
    }

    // -----------------------------------------------------------------
    // JSON: send a freeform message into a conversation
    // -----------------------------------------------------------------

    /**
     * Resolve the sending number for a conversation's pinned channel.
     *
     * WABA / Twilio conversations stamp `device_id` = a wa_provider_configs id
     * (see createConversation), NOT a devices.id. The old per-method code looked
     * it up in the `devices` table, which returned null or a WRONG Baileys
     * number — so a follow-up send left from the workspace's Baileys primary
     * (e.g. 919783969401), which Node has no WABA config for, fell back to
     * Baileys, and FAILED ("no WABA config matched phone — returning Baileys
     * defaults"). The NEW-queue path (createConversation) already resolves the
     * right number via senderForKey, which is why the first send worked but the
     * reply didn't. Resolve from the correct table per provider so every reply
     * leaves on the same number the queue was created with.
     */
    private function conversationSenderPhone(Conversation $conversation): ?string
    {
        if (!$conversation->device_id) {
            return null;
        }
        $prov = strtolower((string) ($conversation->provider ?? ''));
        if ($prov === 'waba' || $prov === 'twilio') {
            $cfg = \App\Models\WaProviderConfig::query()
                ->where('workspace_id', $conversation->workspace_id)
                ->where('id', $conversation->device_id)
                ->first();
            return $cfg ? (preg_replace('/\D+/', '', (string) $cfg->phone_number) ?: null) : null;
        }
        $device = \App\Models\Device::query()->forCurrentWorkspace()->find($conversation->device_id);
        return $device
            ? (preg_replace('/\D+/', '', (string) ($device->country_code . $device->phone_number)) ?: null)
            : null;
    }

    public function sendMessage(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'body'         => 'required_without_all:media,latitude|nullable|string|max:4096',
            'media'        => 'nullable|file|max:10240|mimes:jpg,jpeg,png,gif,webp,mp4,webm,mp3,wav,pdf,doc,docx',
            'latitude'     => 'nullable|numeric|between:-90,90',
            'longitude'    => 'nullable|numeric|between:-180,180',
            'scheduled_at' => 'nullable|string',
            'timezone'     => ['nullable', 'string', \Illuminate\Validation\Rule::in(\DateTimeZone::listIdentifiers())],
        ]);

        $conversation = Conversation::query()->forCurrentWorkspace()->findOrFail($id);

        // Resolve timezone exactly like createConversation: explicit
        // picker > workspace > user > app default.
        $tz = $data['timezone']
            ?? $conversation->scheduled_timezone
            ?? optional(\Illuminate\Support\Facades\Auth::user()?->currentWorkspace)->timezone
            ?? auth()->user()?->timezone
            ?? config('app.timezone', 'UTC');

        if (!empty($data['scheduled_at'])) {
            try {
                $parsed = Carbon::parse($data['scheduled_at'], $tz);
                if ($parsed->lt(now()->addMinute())) {
                    return response()->json([
                        'message' => 'Scheduled time must be at least 1 minute in the future (in ' . $tz . ').',
                    ], 422);
                }
                $data['scheduled_at'] = $parsed->setTimezone('UTC')->toDateTimeString();
                // Stash on the conversation so the dispatcher can read it
                // when this message gets handed off to Node.
                $conversation->update(['scheduled_timezone' => $tz]);
            } catch (\Throwable $e) {
                return response()->json(['message' => 'Could not parse scheduled date.'], 422);
            }
        }

        $mediaPath = null;
        $mediaType = null;
        if ($request->hasFile('media')) {
            $file       = $request->file('media');
            // Keep the original filename inside the stored path so the
            // dispatcher can recover it later for Baileys document
            // sends. Prefix with a 10-char random token so two users
            // can upload the same name without collision.
            $origName   = $file->getClientOriginalName();
            $safeName   = preg_replace('/[^A-Za-z0-9._-]+/', '_', $origName) ?: 'file';
            $mediaPath  = $file->storeAs('chat-media', \Illuminate\Support\Str::random(10) . '__' . $safeName, media_disk());
            $mediaType  = $this->resolveMediaType($file->getClientOriginalExtension());
        } elseif (!empty($data['latitude']) && !empty($data['longitude'])) {
            $mediaType = 'location';
        }

        $isScheduled = !empty($data['scheduled_at']);

        // Billing is plan-first via OverflowBilling inside the dispatcher
        // (free under the workspace's monthly_messages_limit, 1 wallet
        // credit only on overflow). Real (non-scheduled) sends meter at
        // dispatch time; scheduled sends meter when the scheduler later
        // fires send() — so cancelled queues never lock up the balance.
        // No wallet pre-gate / charge / refund here.

        // Save the row locally first as `pending`, then hand off to
        // the provider. The dispatcher updates status (sent / failed
        // / scheduled) based on the provider response. This way the
        // message is durable even if the provider call throws.
        // Resolve the sending device's actual phone number — the
        // dispatcher uses this to call Node /api/send-message/<phone>.
        // Storing the device PK here would make Node 404 because it
        // keys clients by phone, not by device id.
        // Engine-aware: WABA/Twilio conversations pin a wa_provider_configs id,
        // not a devices id — resolve the FROM number from the right table so the
        // reply leaves on the queue's OWN number, not the Baileys primary (which
        // had no WABA config → Baileys fallback → "failed").
        $devicePhone = $this->conversationSenderPhone($conversation);

        // Pull the recipient phone for the reply. Lookup order:
        //   1. Most recent outbound message's `to_number` (operator's
        //      established recipient on this thread).
        //   2. Most recent inbound message's `from_number` (the
        //      customer who messaged us — needed when this is the
        //      first reply on an inbound-only thread).
        //   3. The conversation's `raw_jid` digits (canonical when
        //      neither message direction has a phone on file yet).
        // Without (2) and (3) the composer would silently fail with
        // "No recipient on this conversation" for any thread the
        // customer started, and the JS would prompt to recreate the
        // queue — which the operator saw as "send made a new chat".
        $toNumber = Message::query()
            ->where('conversation_id', $conversation->id)
            ->where('direction', 'out')
            ->whereNotNull('to_number')
            ->orderByDesc('id')
            ->value('to_number');
        if (!$toNumber) {
            $toNumber = Message::query()
                ->where('conversation_id', $conversation->id)
                ->where('direction', 'in')
                ->whereNotNull('from_number')
                ->orderByDesc('id')
                ->value('from_number');
        }
        if (!$toNumber && !empty($conversation->raw_jid)) {
            // raw_jid is "<digits>@s.whatsapp.net" or "<digits>@lid" —
            // strip everything after @ and any non-digits.
            $digits = preg_replace('/\D+/', '', explode('@', (string) $conversation->raw_jid)[0]);
            if ($digits !== '') $toNumber = $digits;
        }

        $message = Message::create([
            'conversation_id' => $conversation->id,
            'user_id'         => Auth::id(),
            'workspace_id'    => $conversation->workspace_id,
            'direction'       => 'out',
            'from_number'     => $devicePhone,
            'to_number'       => $toNumber,
            'body'            => $data['body'] ?? null,
            'media_path'      => $mediaPath,
            'media_type'      => $mediaType,
            'latitude'        => $data['latitude']  ?? null,
            'longitude'       => $data['longitude'] ?? null,
            'status'          => 'pending',
            'scheduled_at'    => $isScheduled ? Carbon::parse($data['scheduled_at']) : null,
        ]);

        \Illuminate\Support\Facades\Log::info('[CHAT] sendMessage → dispatching', [
            'conv_id'      => $conversation->id,
            'msg_id'       => $message->id,
            'device_id'    => $conversation->device_id,
            'device_phone' => $devicePhone,
            'to'           => $message->to_number,
            'scheduled'    => $isScheduled,
        ]);

        if (!$toNumber) {
            \Illuminate\Support\Facades\Log::warning('[CHAT] sendMessage no recipient resolved', ['conv_id' => $conversation->id]);
            $result = ['ok' => false, 'platform' => 'W', 'provider_id' => null, 'local_only' => false, 'error' => 'No recipient on this conversation. Recreate the queue.'];
            $this->applyDispatchResult($message, $result, $isScheduled);
        } else {
            // Engine fallback — older conversations may have NULL or
            // stale 'W' platform from before the workspace engine was
            // set. Resolve via WorkspaceEngine so the dispatcher picks
            // the right transport (Meta Cloud / Baileys / Twilio).
            $engineFallback = \App\Enums\WaProvider::tryFrom(
                \App\Services\WorkspaceEngine::for($conversation->workspace_id)
            )?->legacyCode() ?? 'W';
            $platformHint = $conversation->platform ?: $engineFallback;
            try {
                $result = $isScheduled
                    ? $this->dispatcher->schedule($message, $platformHint)
                    : $this->dispatcher->send($message,     $platformHint);
                $this->applyDispatchResult($message, $result, $isScheduled);
            } catch (\App\Exceptions\PlanLimitReachedException $e) {
                // Plan-first billing tripped the monthly limit (and wallet
                // overflow is exhausted). Mark the row failed and surface
                // the same out-of-credits shape the JS already handles.
                \Illuminate\Support\Facades\Log::warning('[CHAT] sendMessage plan limit reached', [
                    'user_id' => Auth::id(),
                    'msg_id'  => $message->id,
                    'reason'  => $e->getMessage(),
                ]);
                $message->update(['status' => 'failed', 'failure_reason' => mb_substr($e->getMessage(), 0, 191)]);
                $this->refreshConversationAfterSend($conversation, $message);
                return response()->json([
                    'ok' => false,
                    'error' => 'out_of_credits',
                    'message' => $e->getMessage() ?: 'Out of message credits. Top up your wallet or earn credits via the affiliate program.',
                ], 402);
            }
        }

        $this->refreshConversationAfterSend($conversation, $message);

        return response()->json([
            'data' => [
                'message'      => $this->presentMessage($message),
                'conversation' => $this->presentConversation($conversation->refresh()),
                'dispatch'     => $result,   // surfaces local_only / provider_id / error to the JS
            ],
            'meta' => $this->counts(Auth::id()),
        ], 201);
    }

    /**
     * Persist the dispatcher's result back to the Message:
     *   ok=true, local_only=true   → mark sent (or scheduled) — no provider exists
     *   ok=true, provider_id set   → mark sent + remember provider id
     *   ok=false                   → mark failed + record reason
     *
     * The provider id is reused as `from_number` when none was set
     * — so the next reply can be matched back to the same row.
     */
    private function applyDispatchResult(Message $message, array $result, bool $scheduled): void
    {
        if ($result['ok'] ?? false) {
            $message->status  = $scheduled ? 'scheduled' : 'sent';
            $message->sent_at = $scheduled ? null : now();
            if (!empty($result['provider_id']) && empty($message->from_number)) {
                $message->from_number = $result['provider_id'];
            }
        } else {
            $message->status         = 'failed';
            $message->failure_reason = (string) ($result['error'] ?? 'unknown error');
            // Billing is plan-first via OverflowBilling inside the
            // dispatcher — nothing to refund here. A failed send simply
            // never consumed the monthly counter / wallet overflow.
        }
        $message->save();
    }

    // -----------------------------------------------------------------
    // JSON: send a saved template into a conversation
    // -----------------------------------------------------------------

    public function sendTemplate(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'template_id' => 'required|integer|exists:wa_templates,id',
            // Send-time mapping — variable values, header media, and button
            // links the operator filled in for THIS send only. JSON string.
            'template_overrides' => 'nullable|string|max:20000',
        ]);

        $conversation = Conversation::query()->forCurrentWorkspace()->findOrFail($id);
        // SECURITY: scope the template lookup to the caller's workspace.
        // Validation only enforces global existence (exists:wa_templates,id)
        // and approved() carries no tenant predicate, so an unscoped lookup
        // would let a tenant send — and thereby read — another workspace's
        // confidential template body. forCurrentWorkspace() makes a foreign id
        // 404 like any other missing template.
        $template     = WaTemplate::query()->forCurrentWorkspace()->approved()->findOrFail($data['template_id']);

        // Billing is plan-first via OverflowBilling inside the dispatcher
        // (free under monthly_messages_limit, 1 wallet credit only on
        // overflow). No wallet pre-gate / charge / refund here.

        // Resolve the conversation's contact so we can substitute
        // `{{name}}` / `{{phone}}` / `{{email}}` / `{{1}}` placeholders.
        // Conversations don't have a hard FK to contacts (they pre-date
        // CRM contacts), so we match on the workspace + e164 jid.
        // `mobile` is encrypted (non-deterministic) so it can't be queried in
        // SQL — hydrate the workspace's contacts and match the decrypted number
        // in PHP (same pattern as ScheduledController / WaCampaignsController).
        $jidDigits = preg_replace('/\D+/', '', (string) ($conversation->raw_jid ?? ''));
        if ($jidDigits === '') {
            // Bulk-queue conversations have no raw_jid — fall back to the
            // last known recipient number so contact attributes ({{First
            // Name}} etc.) still resolve instead of rendering empty.
            $fallbackNum = Message::query()->where('conversation_id', $conversation->id)
                    ->where('direction', 'out')->whereNotNull('to_number')->orderByDesc('id')->value('to_number')
                ?: Message::query()->where('conversation_id', $conversation->id)
                    ->where('direction', 'in')->whereNotNull('from_number')->orderByDesc('id')->value('from_number');
            $jidDigits = preg_replace('/\D+/', '', (string) $fallbackNum);
        }
        $contact   = null;
        if ($jidDigits !== '') {
            $last10  = substr($jidDigits, -10);
            $contact = \App\Models\Contact::query()
                ->where('workspace_id', $conversation->workspace_id)
                ->whereNotNull('mobile')
                ->get()
                ->first(function ($c) use ($jidDigits, $last10) {
                    $d = preg_replace('/\D+/', '', (string) $c->mobile);
                    return $d !== '' && ($d === $jidDigits || str_ends_with($d, $jidDigits)
                        || ($last10 !== '' && str_ends_with($d, $last10)));
                });
        }

        $category = strtolower((string) ($template->meta_category ?? $template->category ?? ''));
        $isAuth   = $category === 'authentication';

        // Auth-template OTP minting — broadcasts use this same pattern at
        // BroadcastsController::varsForRecipient. For 1:1 chat sends we
        // generate a fresh 6-digit code per send and use it everywhere
        // the template references `{{1}}` / `{{otp}}`.
        $otpCode = $isAuth ? (string) random_int(100000, 999999) : null;

        // Build the per-recipient variable map: positional `{{1}}` resolves
        // via the template's variable_map to a contact attribute, named
        // `{{name}}` resolves directly off the contact, and auth OTP
        // overrides position 1 / `{{otp}}`.
        // variable_map persists in the NESTED stored shape
        // ['header'=>[{num,key}], 'body'=>[{num,key}]]. Flatten the body
        // section to a positional {slot => key} map so {{1}} → key lookups
        // below work. Tolerate a legacy flat {"1":"name"} body too. Mirrors
        // App\Services\AttributeResolver::normalizeVariableMap.
        $variableMap = is_array($template->variable_map) ? $template->variable_map : [];
        $rawBodyMap  = is_array($variableMap['body'] ?? null) ? $variableMap['body'] : [];
        $bodyMap     = [];
        foreach ($rawBodyMap as $slot => $entry) {
            if (is_array($entry) && isset($entry['num'], $entry['key']) && $entry['key'] !== '') {
                $bodyMap[(string) $entry['num']] = (string) $entry['key'];      // nested {num,key}
            } elseif (is_string($entry) && $entry !== '') {
                $bodyMap[(string) $slot] = $entry;                              // legacy flat slot=>key
            }
        }

        $contactAttr = function (string $key) use ($contact, $jidDigits): string {
            if (!$contact) {
                return match ($key) {
                    'phone', 'mobile', 'number' => $jidDigits,
                    default => '',
                };
            }
            $aliases = [
                'name'  => 'name',
                'first_name' => 'name',
                'phone' => 'phone_number',
                'mobile'=> 'phone_number',
                'email' => 'email',
                'company' => 'company',
            ];
            // Normalise "First Name" → "first_name" so spaced/named tokens hit
            // the alias map and the saved custom attributes.
            $norm = str_replace([' ', '-'], '_', strtolower($key));
            $col  = $aliases[$norm] ?? $aliases[strtolower($key)] ?? null;
            if ($col && isset($contact->{$col})) return (string) $contact->{$col};
            $custom = is_array($contact->custom_attributes ?? null) ? $contact->custom_attributes : [];
            // Try the raw key, then the normalised key, then a Title-Case match.
            return (string) ($custom[$key] ?? $custom[$norm] ?? $custom[ucwords(str_replace('_', ' ', $norm))] ?? '');
        };

        // Send-time overrides. Resolved through the SHARED resolver that
        // campaigns / broadcasts / scheduled / team-inbox all use, so a value
        // typed here means exactly what it means on every other send screen.
        // Consulted first in $resolveToken below; an absent or blank slot
        // falls through to this controller's template-driven path untouched,
        // which is why a send with no overrides is byte-identical to before.
        $overrideVars = [];
        $overrides    = \App\Services\TemplateOverrideResolver::sanitize($data['template_overrides'] ?? null);
        if ($overrides) {
            try {
                $contactRow = $contact ? $contact->toArray() : [];
                $contactRow['phone'] = (string) ($contact->mobile ?? $jidDigits);
                // Flatten section-keyed -> positional so $resolveToken's
                // numeric {{1}} / {{2}} lookups below actually hit.
                $overrideVars = \App\Services\TemplateOverrideResolver::positional(
                    app(BroadcastsController::class)->varsForRecipient(
                        $template, $contactRow, (int) $conversation->workspace_id, $overrides
                    )
                );
            } catch (\Throwable $e) {
                // Never block a send on mapping — fall through to the
                // template as written rather than 500-ing.
                \Illuminate\Support\Facades\Log::warning('[CHAT-TPL] override resolve failed: ' . $e->getMessage());
            }
        }

        $resolveToken = function (string $key) use ($bodyMap, $otpCode, $contactAttr, $overrideVars): string {
            $lower = strtolower($key);
            if ($otpCode !== null && in_array($lower, ['1', 'otp', 'code'], true)) {
                return $otpCode;
            }
            // An operator-typed value wins over the template's variable_map.
            if (isset($overrideVars[$key]) && trim((string) $overrideVars[$key]) !== '') {
                return (string) $overrideVars[$key];
            }
            if (ctype_digit($key)) {
                $named = $bodyMap[$key] ?? null;
                if ($named !== null && $named !== '') return $contactAttr((string) $named);
                return '';
            }
            return $contactAttr($key);
        };

        $substitute = function (string $text) use ($resolveToken): string {
            // `[^{}]+?` (not `[^\s{}]`) so NAMED placeholders containing spaces
            // — e.g. {{First Name}}, {{Promo Code}} — also match and resolve,
            // instead of shipping literally. Key is trimmed in $resolveToken.
            return preg_replace_callback('/\{\{\s*([^{}]+?)\s*\}\}/', fn ($m) => $resolveToken(trim((string) $m[1])), $text);
        };

        $resolvedBody   = $substitute((string) $template->template_body);
        $resolvedHeader = $template->header ? $substitute((string) $template->header) : '';
        $resolvedFooter = (string) ($template->footer ?? '');

        // Resolve each template button — placeholder substitution applies
        // to URL values + copy_code values so dynamic links / coupon codes
        // ship the real recipient-specific values, not literal `{{N}}`.
        // Button link / copy-code overrides, keyed by the button's position in
        // the template. Applied BEFORE substitution so an override may itself
        // contain tokens, e.g. "https://shop/order/{{order_id}}".
        $buttonOverrides = [];
        foreach (($overrides['buttons'] ?? []) as $b) {
            $i = (int) ($b['index'] ?? -1);
            $v = trim((string) ($b['value'] ?? ''));
            if ($i >= 0 && $v !== '') $buttonOverrides[$i] = $v;
        }

        $resolvedButtons = [];
        foreach ((is_array($template->buttons) ? $template->buttons : []) as $bi => $b) {
            if (!is_array($b)) continue;
            if (isset($buttonOverrides[$bi])) $b['value'] = $buttonOverrides[$bi];
            $b['value'] = isset($b['value']) ? $substitute((string) $b['value']) : '';
            $b['text']  = isset($b['text'])  ? $substitute((string) $b['text'])  : '';
            // Auth OTP button — value MUST be the minted code so when the
            // customer taps "Copy code" they get the digits, not `{{1}}`.
            if ($isAuth && in_array(($b['type'] ?? ''), ['copy_code', 'otp_copy', 'otp_one_tap'], true)) {
                $b['value'] = $otpCode ?? $b['value'];
            }
            // Drop structurally-invalid buttons — an action button (URL / call
            // / copy) with NO value AND no url makes WhatsApp strip the ENTIRE
            // button set, so the recipient sees no buttons (or no message).
            // Covers every action type (visit_website/url, call/call_phone/
            // phone_number, copy_code/copy_text); only quick-replies may be
            // value-less (they fall back to their label).
            $btype = strtolower((string) ($b['type'] ?? ''));
            $bval  = trim((string) ($b['value'] ?? ''));
            $burl  = trim((string) ($b['url'] ?? ''));
            $isQuickReply = $btype === '' || in_array($btype, ['quick_reply', 'reply', 'quick reply'], true);
            if (!$isQuickReply && $bval === '' && $burl === '') continue;
            $resolvedButtons[] = $b;
        }

        // Trace: raw template buttons vs what survives the resolve/drop, so
        // "buttons not showing" can be diagnosed end-to-end (pair with Node's
        // [BTN-FMT] line). Search the Laravel log for [CHAT-TPL-BTN].
        \Illuminate\Support\Facades\Log::info('[CHAT-TPL-BTN]', [
            'template_id' => $template->id,
            'raw'         => is_array($template->buttons) ? $template->buttons : $template->buttons,
            'resolved'    => $resolvedButtons,
            'dropped'     => max(0, count(is_array($template->buttons) ? $template->buttons : []) - count($resolvedButtons)),
        ]);

        // Pre-build positional `template_vars` so the Twilio ContentSid
        // branch (WhatsAppDispatcher::buildTwilioContentVariables) and any
        // future WABA-direct send have ready-made `{1: 'Sudhir', 2: 'INV-1'}`
        // values, matching Twilio Content Builder's positional slot model.
        $templateVars = [];
        foreach ($bodyMap as $pos => $named) {
            if (!is_string($named) && !is_numeric($named)) continue;
            $templateVars[(string) $pos] = $resolveToken((string) $named);
        }
        if ($otpCode !== null && !isset($templateVars['1'])) {
            $templateVars['1'] = $otpCode;
        }

        $meta = array_filter([
            'template_id'   => $template->id,
            'template_name' => $template->template_name,
            'category'      => $category ?: null,
            'template_type' => $template->template_type ?: null,
            // Carousel cards must ride along or the dispatcher sends only the
            // body text and drops every card (the dispatcher forwards
            // meta.carousel_data on the text path).
            'carousel_data' => ($template->template_type === 'carousel' && !empty($template->carousel_data)) ? $template->carousel_data : null,
            'buttons'       => $resolvedButtons ?: null,
            'header'        => $resolvedHeader ?: null,
            // LOCATION header — ships as a separate location pin (Unofficial
            // API) or Meta's location header param (WABA), handled downstream.
            'header_location' => (is_array($template->header_location) && !empty($template->header_location)) ? $template->header_location : null,
            'footer'        => $resolvedFooter ?: null,
            'otp_code'      => $otpCode,
            'template_vars' => $templateVars ?: null,
            // Header media swapped for this send only. TemplateSender uploads
            // and caches the Meta media id off this URL; the Unofficial path
            // sends it directly.
            'header_media_url' => $overrideVars['header_media_url'] ?? null,
        ], fn ($v) => $v !== null && $v !== '');

        // Resolve the sending device phone + the recipient number — WITHOUT
        // these the template dispatched to a NULL number and WhatsApp never
        // received it (while plain replies worked because they set them).
        // Same lookup order as the reply composer.
        // Engine-aware sender resolution (WABA/Twilio pin a config id, not a
        // devices id) — same fix as the reply composer.
        $tplDevicePhone = $this->conversationSenderPhone($conversation);
        $tplToNumber = Message::query()
            ->where('conversation_id', $conversation->id)->where('direction', 'out')
            ->whereNotNull('to_number')->orderByDesc('id')->value('to_number');
        if (!$tplToNumber) {
            $tplToNumber = Message::query()
                ->where('conversation_id', $conversation->id)->where('direction', 'in')
                ->whereNotNull('from_number')->orderByDesc('id')->value('from_number');
        }
        if (!$tplToNumber && !empty($conversation->raw_jid)) {
            $digits = preg_replace('/\D+/', '', explode('@', (string) $conversation->raw_jid)[0]);
            if ($digits !== '') $tplToNumber = $digits;
        }

        $message = Message::create([
            'conversation_id' => $conversation->id,
            'user_id'         => Auth::id(),
            'workspace_id'    => $conversation->workspace_id,
            'template_id'     => $template->id,
            'direction'       => 'out',
            'device_id'       => $conversation->device_id,
            'from_number'     => $tplDevicePhone,
            'to_number'       => $tplToNumber,
            'body'            => $resolvedBody,
            'media_path'      => $template->attachment_file ?: null,
            'media_type'      => $template->attachment_type ?: null,
            'status'          => 'pending',
            'meta'            => $meta ?: null,
        ]);

        // Engine fallback so templates route to the workspace's active
        // engine even when the conversation row has stale platform.
        $engineFallback = \App\Enums\WaProvider::tryFrom(
            \App\Services\WorkspaceEngine::for($conversation->workspace_id)
        )?->legacyCode() ?? 'W';
        try {
            $result = $this->dispatcher->send($message, $conversation->platform ?: $engineFallback);
            $this->applyDispatchResult($message, $result, scheduled: false);
        } catch (\App\Exceptions\PlanLimitReachedException $e) {
            \Illuminate\Support\Facades\Log::warning('[CHAT] sendTemplate plan limit reached', [
                'user_id' => Auth::id(),
                'msg_id'  => $message->id,
                'reason'  => $e->getMessage(),
            ]);
            $message->update(['status' => 'failed', 'failure_reason' => mb_substr($e->getMessage(), 0, 191)]);
            $this->refreshConversationAfterSend($conversation, $message);
            return response()->json([
                'ok' => false,
                'error' => 'out_of_credits',
                'message' => $e->getMessage() ?: 'Out of message credits. Top up your wallet or earn credits via the affiliate program.',
            ], 402);
        }

        $this->refreshConversationAfterSend($conversation, $message);

        return response()->json([
            'data' => [
                'message'      => $this->presentMessage($message),
                'conversation' => $this->presentConversation($conversation->refresh()),
            ],
            'meta' => $this->counts(Auth::id()),
        ], 201);
    }

    // -----------------------------------------------------------------
    // JSON: archive / unarchive / delete
    // -----------------------------------------------------------------

    public function archive(int $id): JsonResponse
    {
        $c = Conversation::query()->forCurrentWorkspace()->findOrFail($id);
        $c->update(['archived' => true]);
        return response()->json(['data' => $this->presentConversation($c), 'meta' => $this->counts(Auth::id())]);
    }

    public function unarchive(int $id): JsonResponse
    {
        $c = Conversation::query()->forCurrentWorkspace()->findOrFail($id);
        $c->update(['archived' => false]);
        return response()->json(['data' => $this->presentConversation($c), 'meta' => $this->counts(Auth::id())]);
    }

    public function destroy(int $id): JsonResponse
    {
        Conversation::query()->forCurrentWorkspace()->findOrFail($id)->delete();
        return response()->json(['data' => ['id' => $id], 'meta' => $this->counts(Auth::id())]);
    }

    // -----------------------------------------------------------------
    // Per-message actions — backing the WhatsApp-style hover menu.
    // Every method scopes the message via the conversation→user chain
    // so users can never operate on someone else's row.
    // -----------------------------------------------------------------

    private function findOwnedMessage(int $conversationId, int $messageId): Message
    {
        $convo = Conversation::query()->forCurrentWorkspace()->findOrFail($conversationId);
        return Message::query()->where('conversation_id', $convo->id)->findOrFail($messageId);
    }

    public function messageInfo(int $c, int $m): JsonResponse
    {
        $msg = $this->findOwnedMessage($c, $m);
        return response()->json([
            'data' => [
                'id'             => $msg->id,
                'direction'      => $msg->direction,
                'status'         => $msg->status,
                'from_number'    => $msg->from_number,
                'to_number'      => $msg->to_number,
                'created_at'     => $msg->created_at?->toIso8601String(),
                'scheduled_at'   => $msg->scheduled_at?->toIso8601String(),
                'sent_at'        => $msg->sent_at?->toIso8601String(),
                'delivered_at'   => $msg->delivered_at?->toIso8601String(),
                'read_at'        => $msg->read_at?->toIso8601String(),
                'failure_reason' => $msg->failure_reason,
                'pinned'         => (bool) $msg->pinned,
                'starred'        => (bool) $msg->starred,
                'reaction'       => $msg->reaction,
                'media_type'     => $msg->media_type,
                'media_name'     => $msg->media_path ? (str_contains(basename($msg->media_path), '__') ? substr(basename($msg->media_path), strpos(basename($msg->media_path), '__') + 2) : basename($msg->media_path)) : null,
            ],
        ]);
    }

    public function messageReact(Request $request, int $c, int $m): JsonResponse
    {
        $data = $request->validate(['emoji' => 'nullable|string|max:8']);
        $msg = $this->findOwnedMessage($c, $m);
        $emoji = $data['emoji'] ?? '';
        // Empty string clears the reaction (WhatsApp parity).
        $msg->update(['reaction' => $emoji ?: null]);
        // Best-effort fire-and-forget Baileys reaction so the recipient sees it.
        // We don't fail the whole call if Node is unreachable — the local
        // record stays correct either way.
        try {
            $this->dispatcher->reaction($msg, $emoji);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('reaction dispatch threw', ['err' => $e->getMessage()]);
        }
        return response()->json(['data' => ['id' => $msg->id, 'reaction' => $msg->reaction]]);
    }

    public function messageTogglePin(int $c, int $m): JsonResponse
    {
        $msg = $this->findOwnedMessage($c, $m);
        $wantPin = !$msg->pinned;
        $msg->update(['pinned' => $wantPin]);
        // Push the pin through Baileys so the recipient's WhatsApp shows
        // the pinned-bubble indicator too (24h–30d). Local DB stays
        // correct even if Node is unreachable.
        try {
            $this->dispatcher->pin($msg, $wantPin, 604800);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('pin dispatch threw', ['err' => $e->getMessage()]);
        }
        return response()->json(['data' => ['id' => $msg->id, 'pinned' => $msg->pinned]]);
    }

    public function messageToggleStar(int $c, int $m): JsonResponse
    {
        $msg = $this->findOwnedMessage($c, $m);
        $wantStar = !$msg->starred;
        $msg->update(['starred' => $wantStar]);
        // Star is operator-only — syncs to the user's WhatsApp Web
        // starred list but doesn't notify the recipient. Still worth
        // firing so the operator sees it on every device.
        try {
            $this->dispatcher->star($msg, $wantStar);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('star dispatch threw', ['err' => $e->getMessage()]);
        }
        return response()->json(['data' => ['id' => $msg->id, 'starred' => $msg->starred]]);
    }

    public function messageDelete(int $c, int $m): JsonResponse
    {
        $msg = $this->findOwnedMessage($c, $m);
        $msg->delete(); // SoftDeletes — row remains, deleted_at set.
        return response()->json(['data' => ['id' => $msg->id, 'deleted' => true]]);
    }

    public function messageForward(Request $request, int $c, int $m): JsonResponse
    {
        $data = $request->validate([
            'target_conversation_id' => 'required|integer',
        ]);
        $src    = $this->findOwnedMessage($c, $m);
        $target = Conversation::query()->forCurrentWorkspace()->findOrFail($data['target_conversation_id']);

        // Pull the target conversation's recipient phone from its most
        // recent outbound row (same pattern as the composer reply).
        $toNumber = Message::query()
            ->where('conversation_id', $target->id)
            ->where('direction', 'out')
            ->whereNotNull('to_number')
            ->orderByDesc('id')
            ->value('to_number');

        // Engine-aware sender resolution for the forward target (WABA/Twilio
        // pin a config id, not a devices id).
        $devicePhone = $this->conversationSenderPhone($target);

        if (!$toNumber) {
            return response()->json(['message' => 'Target conversation has no recipient.'], 422);
        }

        $copy = Message::create([
            'conversation_id' => $target->id,
            'user_id'         => Auth::id(),
            'workspace_id'    => $target->workspace_id,
            'direction'       => 'out',
            'from_number'     => $devicePhone,
            'to_number'       => $toNumber,
            'body'            => $src->body,
            'media_path'      => $src->media_path,
            'media_type'      => $src->media_type,
            'latitude'        => $src->latitude,
            'longitude'       => $src->longitude,
            'status'          => 'pending',
        ]);

        // Plan-first billing via OverflowBilling inside the dispatcher —
        // no wallet pre-gate / charge / refund here.
        try {
            $result = $this->dispatcher->send($copy, $target->platform ?? 'W');
            $this->applyDispatchResult($copy, $result, false);
        } catch (\App\Exceptions\PlanLimitReachedException $e) {
            \Illuminate\Support\Facades\Log::warning('[CHAT] messageForward plan limit reached', [
                'user_id' => Auth::id(),
                'msg_id'  => $copy->id,
                'reason'  => $e->getMessage(),
            ]);
            $copy->update(['status' => 'failed', 'failure_reason' => mb_substr($e->getMessage(), 0, 191)]);
            $this->refreshConversationAfterSend($target->refresh(), $copy);
            return response()->json(['message' => $e->getMessage() ?: 'Out of credits.'], 402);
        }
        $this->refreshConversationAfterSend($target->refresh(), $copy);

        return response()->json([
            'data' => [
                'forwarded_to' => $target->id,
                'message'      => $this->presentMessage($copy),
                'dispatch'     => $result,
            ],
        ]);
    }

    // -----------------------------------------------------------------
    // JSON: AI assist tools — Summarize / Suggest / Rewrite / Translate
    // / Extract / Tone, all working off the last 30 decrypted messages
    // on the conversation. The actual model call goes through whichever
    // provider is wired up (OPENAI_API_KEY / ANTHROPIC_API_KEY /
    // GEMINI_API_KEY) — when none is set we return a deterministic
    // fallback so the UI can still be tested.
    // -----------------------------------------------------------------

    public function aiAssist(Request $request, int $id): JsonResponse
    {
        $tool  = $request->string('tool')->toString() ?: 'summary';
        $extra = $request->string('input')->toString();
        $model = $request->string('model')->toString() ?: 'gpt-4o-mini';

        $c = Conversation::query()->forCurrentWorkspace()->findOrFail($id);
        $msgs = Message::query()
            ->where('conversation_id', $c->id)
            ->orderByDesc('id')
            ->limit(30)
            ->get()
            ->reverse()
            ->values();

        $transcript = $msgs->map(function ($m) {
            $who = ($m->direction === 'in') ? 'CUSTOMER' : 'AGENT';
            return $who . ': ' . ($m->body ?? '');
        })->implode("\n");

        $prompt = match ($tool) {
            'summary'   => "Summarize the conversation below in 4-6 short bullet points. Highlight intent, key facts, blockers, and next step.\n\n" . $transcript,
            'suggest'   => "You are a helpful WhatsApp business agent. Draft a single short, friendly reply (2-3 sentences) the agent could send next. Reply in the same language as the customer.\n\n" . $transcript,
            'rewrite'   => "Rewrite the agent's last reply in a {$extra} tone. Keep meaning and length similar.\n\nLast reply: " . ($msgs->last()->body ?? ''),
            'translate' => "Translate the customer's last message into " . ($extra ?: 'English') . ".\n\n" . ($msgs->last(fn ($m) => $m->direction === 'in')?->body ?? ''),
            'extract'   => "Extract structured info from the conversation as JSON: { name, phone, email, intent, products_mentioned, dates, order_id }. Use null for missing values.\n\n" . $transcript,
            'tone'      => "Analyze the customer's tone. Return JSON: { sentiment, urgency, frustration_score (0-10), summary }.\n\n" . $transcript,
            default     => "Summarize:\n\n" . $transcript,
        };

        $response = $this->callAiProvider($prompt, $model);

        return response()->json([
            'ok'         => true,
            'tool'       => $tool,
            'model'      => $model,
            'output'     => $response,
            'message_count' => $msgs->count(),
        ]);
    }

    private function callAiProvider(string $prompt, string $model): string
    {
        // Route through AiAgentService so the BYOK → AdminAiKey resolution
        // chain + plan gate apply. Pre-2026-05 we read env() directly,
        // which bypassed BOTH the workspace's own key AND the admin's
        // configured key. AiAgentService::callProvider handles all three.
        $provider = match (true) {
            str_starts_with($model, 'gpt-')    => 'openai',
            str_starts_with($model, 'claude-') => 'anthropic',
            str_starts_with($model, 'gemini-') => 'gemini',
            default                            => null,
        };
        if (! $provider) {
            return "[demo response — unknown model prefix]\n\n" . $this->fakeResponseFor($prompt);
        }

        $workspaceId = (int) (auth()->user()?->current_workspace_id ?? 0);
        try {
            $out = app(\App\Services\AiAgentService::class)->callProvider(
                $provider,
                $model,
                $workspaceId,
                'You are a helpful assistant.',
                $prompt,
                1024,
                0.4,
            );
            if (is_string($out) && $out !== '') return $out;
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('[ChatController] AI call failed: ' . $e->getMessage());
        }

        // Fallback stub so the UI works without an API key.
        return "[demo response — no AI key configured]\n\n" . $this->fakeResponseFor($prompt);
    }

    private function fakeResponseFor(string $prompt): string
    {
        if (str_contains($prompt, 'Summarize')) {
            return "- Customer reached out about an issue with a recent order\n- Agent acknowledged and asked for the order number\n- Customer is waiting on a response and follow-up shipping update\n- Next step: agent to check order status and reply within the hour";
        }
        if (str_contains($prompt, 'Suggest reply') || str_contains($prompt, 'Draft a single')) {
            return "Hi! Thanks for reaching out. I've pulled up your order — let me check the latest status with our shipping team and I'll be right back with an update. Could you confirm the delivery address while I do that?";
        }
        if (str_contains($prompt, 'Rewrite')) {
            return "Sure thing — let me take a look and circle back shortly with everything you need.";
        }
        if (str_contains($prompt, 'Translate')) {
            return "[translation placeholder]";
        }
        if (str_contains($prompt, 'Extract structured info')) {
            return "{\n  \"name\": null,\n  \"phone\": null,\n  \"email\": null,\n  \"intent\": \"order_status\",\n  \"products_mentioned\": [],\n  \"dates\": [],\n  \"order_id\": null\n}";
        }
        if (str_contains($prompt, 'Analyze the customer')) {
            return "{\n  \"sentiment\": \"neutral\",\n  \"urgency\": \"medium\",\n  \"frustration_score\": 4,\n  \"summary\": \"Customer is patient but expecting a timely follow-up.\"\n}";
        }
        return "OK.";
    }

    // -----------------------------------------------------------------
    // JSON: status-flip actions used by the queue gear menu
    // -----------------------------------------------------------------

    /**
     * Flip every scheduled message in the conversation to `sent`
     * with sent_at = now. Used by the gear menu's "Send now"
     * shortcut on a scheduled queue.
     */
    public function sendNow(int $id): JsonResponse
    {
        $c = Conversation::query()->forCurrentWorkspace()->findOrFail($id);
        $now = now();

        Message::query()
            ->where('conversation_id', $c->id)
            ->where('status', 'scheduled')
            ->update(['status' => 'sent', 'sent_at' => $now, 'scheduled_at' => null]);

        $c->update([
            'status'          => 'sent',
            'scheduled_at'    => null,
            'last_message_at' => $now,
        ]);

        return response()->json([
            'data' => ['conversation' => $this->presentConversation($c->refresh())],
            'meta' => $this->counts(Auth::id()),
        ]);
    }

    /**
     * Retry every failed message in the conversation. Marks them
     * `sent` again and clears failure_reason (in a real send path
     * this would re-enqueue against the WhatsApp provider — we
     * simulate the success here so the UI flow is testable).
     */
    public function retry(int $id): JsonResponse
    {
        $c = Conversation::query()->forCurrentWorkspace()->findOrFail($id);
        $now = now();

        Message::query()
            ->where('conversation_id', $c->id)
            ->where('status', 'failed')
            ->update(['status' => 'sent', 'sent_at' => $now, 'failure_reason' => null]);

        $stillFailed = Message::query()
            ->where('conversation_id', $c->id)
            ->where('status', 'failed')
            ->exists();

        $c->update([
            'status'          => $stillFailed ? 'failed' : 'sent',
            'last_message_at' => $now,
        ]);

        return response()->json([
            'data' => ['conversation' => $this->presentConversation($c->refresh())],
            'meta' => $this->counts(Auth::id()),
        ]);
    }

    /**
     * Duplicate the most recent outgoing message and "send" it
     * again. Lets the operator hit the "Send again" gear-menu
     * action on a previously-sent queue without retyping.
     */
    public function resend(int $id): JsonResponse
    {
        $c = Conversation::query()->forCurrentWorkspace()->findOrFail($id);
        $latest = Message::query()
            ->where('conversation_id', $c->id)
            ->where('direction', 'out')
            ->latest('id')
            ->first();
        if (!$latest) {
            return response()->json(['message' => 'Nothing to resend'], 422);
        }

        $copy = $latest->replicate([
            'sent_at', 'delivered_at', 'read_at', 'scheduled_at', 'failure_reason',
        ]);
        $copy->status  = 'sent';
        $copy->sent_at = now();
        $copy->save();

        $c->update([
            'status'          => 'sent',
            'preview'         => $latest->body ?: $c->preview,
            'last_message_at' => now(),
        ]);

        return response()->json([
            'data' => [
                'conversation' => $this->presentConversation($c->refresh()),
                'message'      => $this->presentMessage($copy),
            ],
            'meta' => $this->counts(Auth::id()),
        ]);
    }

    // -----------------------------------------------------------------
    // JSON: templates
    // -----------------------------------------------------------------

    public function templates(Request $request): JsonResponse
    {
        $request->validate([
            'category' => 'nullable|string|in:all,marketing,utility,authentication',
        ]);

        $category = $request->string('category')->toString() ?: 'all';

        // Templates here are the same approved library that powers
        // /templates and /wa-campaigns/create (wa_templates table). The
        // table's text columns (template_name, template_body) are
        // encrypted-at-rest, so sorting happens in PHP after Eloquent
        // hydrates. Category matching goes through effectiveCategory() —
        // see the model for why meta_category alone is not enough.
        $items = WaTemplate::query()
            ->forCurrentWorkspace()
            ->approved()
            ->orderByDesc('id')
            ->get();

        $items = $items
            ->filter(function ($t) use ($category) {
                if ($category === 'all') return true;
                return $t->effectiveCategory() === $category;
            })
            ->sortBy(fn ($t) => mb_strtolower((string) $t->template_name))
            ->values();

        return response()->json([
            'data' => $items->map(fn ($t) => [
                'id'            => $t->id,
                'title'         => (string) $t->template_name,
                'category'      => $t->effectiveCategory(),
                'tone'          => ucfirst($t->effectiveCategory()),
                'body'          => (string) $t->template_body,
                // Rich fields so the picker PREVIEW can show the full template
                // (header media + footer + buttons + carousel cards), not just
                // the body text.
                'template_type' => (string) ($t->template_type ?: 'standard'),
                'header'        => (string) ($t->header ?? ''),
                'footer'        => (string) ($t->footer ?? ''),
                'buttons'       => is_array($t->buttons) ? array_values($t->buttons) : [],
                'carousel_data' => is_array($t->carousel_data) ? array_values($t->carousel_data) : [],
                'media_url'     => $t->attachment_file ? media_url($t->attachment_file) : null,
                'media_type'    => (string) ($t->attachment_type ?? ''),
                // Slot/media/button shape the shared send-time mapping panel
                // renders from — same payload the other send screens stamp.
                'send_meta'     => $t->sendMeta(),
            ])->values()->all(),
        ]);
    }

    // -----------------------------------------------------------------
    // JSON: search known phone numbers (composer autocomplete)
    // -----------------------------------------------------------------

    public function searchNumbers(Request $request): JsonResponse
    {
        $request->validate(['q' => 'required|string|min:2|max:32']);
        $needle = mb_strtolower($request->string('q')->toString());

        // `to_number` is encrypted-at-rest, so we can't `LIKE` on
        // ciphertext. Pull a bounded recent set and match in PHP
        // after Eloquent decrypts. Scales fine for autocomplete
        // (8 results, ~500 candidate window); revisit with a
        // hashed sidecar column if usage grows.
        $numbers = Message::query()
            ->forCurrentWorkspace()
            ->whereNotNull('to_number')
            ->latest('id')
            ->limit(500)
            ->get(['id', 'to_number'])
            ->pluck('to_number')
            ->filter(fn ($n) => str_contains(mb_strtolower((string) $n), $needle))
            ->unique()
            ->values()
            ->take(8);

        return response()->json(['data' => $numbers]);
    }

    // -----------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------

    /**
     * One-shot initial payload handed to the blade view. Lets the
     * JS render counts / filters / devices on first paint without
     * a round-trip. Pure PHP array — nothing to do with the
     * Bootstrap CSS framework (we're on Tailwind).
     */
    private function initialState(): array
    {
        return [
            'counts'          => $this->counts(Auth::id()),
            'devices'         => $this->devices(),
            // Multi-engine: every connected sender across ALL enabled
            // engines, surfaced to the compose modal so a workspace running
            // Unofficial API + WABA at once can pick which channel sends this
            // queue. Each entry carries a composite `engine:id` key the modal
            // posts as `sender` / `sender[]`. The single-engine $devices list
            // above is kept for the empty-state copy and any legacy fallback.
            'senders'         => $this->composeSenders(),
            'groups'          => $this->groups(),
            'csrfToken'       => csrf_token(),
            'apiBase'         => url('/chat/api'),
            // Plan-gated: when the workspace's plan has multipledevice ON,
            // the compose modal renders a multi-select device picker and
            // the server splits recipients across the picked devices.
            'canMultiDevice'  => \App\Services\PlanLimitGuard::hasFeature(
                Auth::user()?->currentWorkspace,
                'multipledevice'
            ),
        ];
    }

    /**
     * Aggregate counts for the left-rail filter pills. Cheap query
     * (one row per conversation), no GROUP BY across messages.
     */
    private function counts(?int $userId): array
    {
        // Chat counts must not include campaign-origin rows — those have
        // their own dashboard at /wa-campaigns and would inflate the
        // sidebar filter badges if mixed in. Engine-scope to the ENABLED SET
        // (forCurrentEngine = whereIn(enginesFor)) so a multi-engine workspace
        // counts every enabled engine's chats; single-engine is identical.
        // Same SEND-only scope as the list (conversations()): only threads we
        // actually sent to from /chat, so the sidebar badges match the rows.
        $q = Conversation::query()->forCurrentWorkspace()->chatOnly()->forCurrentEngine()
            ->whereHas('messages', fn ($qq) => $qq->where('direction', 'out'));

        $byStatus = (clone $q)
            ->where('archived', false)
            ->selectRaw('status, COUNT(*) as c')
            ->groupBy('status')
            ->pluck('c', 'status');

        // Tallied in PHP rather than a SQL GROUP BY because the bucket key
        // comes from effectiveCategory(), which falls back across two
        // columns. Bounded (one row per approved template), so it stays cheap.
        //
        // forCurrentWorkspace() is NOT optional here — without it these
        // counts summed every workspace's templates on the platform and
        // showed the total to whoever opened the page.
        $tplBuckets = ['marketing' => 0, 'utility' => 0, 'authentication' => 0];
        WaTemplate::query()->forCurrentWorkspace()->approved()
            ->get(['id', 'meta_category', 'category'])
            ->each(function ($t) use (&$tplBuckets) {
                $key = $t->effectiveCategory();
                if (isset($tplBuckets[$key])) $tplBuckets[$key]++;
            });

        return [
            'all'       => (clone $q)->where('archived', false)->count(),
            'scheduled' => (int) ($byStatus['scheduled'] ?? 0),
            'archived'  => (clone $q)->where('archived', true)->count(),
            'sent'      => (int) ($byStatus['sent']      ?? 0),
            'pending'   => (int) ($byStatus['pending']   ?? 0),
            'failed'    => (int) ($byStatus['failed']    ?? 0),
            'templates' => $tplBuckets,
        ];
    }

    /**
     * Multi-engine sender list for the compose modal — every connected
     * sender across ALL of the workspace's enabled engines, mapped to the
     * shape the JS picker renders. The composite `key` (engine:id)
     * disambiguates the overlapping devices.id / wa_provider_configs.id
     * namespaces and is what the modal posts back. `engineLabel` uses the
     * descriptor ("Unofficial API" for baileys — never "Baileys") so the
     * grouped picker headers read correctly. Senders from senders() are all
     * connected, so `online` is always true here (kept for shape parity
     * with devices()). Default-engine senders sort first.
     */
    private function composeSenders(): array
    {
        $wsId = Auth::user()?->current_workspace_id;

        return \App\Services\WorkspaceEngine::senders($wsId)
            ->map(function ($s) {
                $phone = $s['phone'] ? '+' . ltrim((string) $s['phone'], '+') : '';
                $label = $phone !== '' && !str_contains((string) $s['label'], $phone)
                    ? $s['label'] . ' / ' . $phone
                    : $s['label'];
                return [
                    'key'         => $s['key'],            // composite "engine:id" — posted as sender / sender[]
                    'id'          => $s['id'],
                    'engine'      => $s['engine'],
                    'engineLabel' => $s['descriptor']['label'] ?? 'Unofficial API',
                    'label'       => $label,
                    'phone'       => $phone,
                    'online'      => true,
                    'is_default'  => (bool) ($s['is_default'] ?? false),
                ];
            })
            ->values()
            ->all();
    }

    private function devices(): array
    {
        // Multi-engine aware: a workspace running several engines at once
        // (Unofficial + WABA + Twilio) must show EVERY connected sender in the
        // queue filter — not just the default engine's. This previously
        // branched on WorkspaceEngine::for() (a SINGLE default engine), so a
        // workspace with BOTH a connected Unofficial phone and a WABA number
        // listed only one of them (the connected Baileys device went missing
        // when the default resolved to WABA). Now mirrors composeSenders()
        // (which feeds the compose modal) via senders() = every connected
        // sender across all of the workspace's active engines.
        $wsId = Auth::user()?->current_workspace_id;

        return \App\Services\WorkspaceEngine::senders($wsId)
            ->map(function ($s) {
                $phone = $s['phone'] ? '+' . ltrim((string) $s['phone'], '+') : '';
                $label = ($phone !== '' && !str_contains((string) $s['label'], $phone))
                    ? $s['label'] . ' / ' . $phone
                    : $s['label'];
                return [
                    'id'     => $s['id'],
                    'label'  => $label,
                    'online' => true,
                    'engine' => $s['engine'],
                ];
            })
            ->values()
            ->all();
    }

    /**
     * Lightweight group list for the "create queue" dialog. Pulled
     * fresh on page load so the modal doesn't need its own fetch.
     * The count is derived in PHP because contact_group is an
     * encrypted JSON array — see ContactGroup::contactsCountAttribute.
     */
    private function groups(): array
    {
        return ContactGroup::query()
            ->orderBy('id')
            ->get()
            ->map(fn ($g) => [
                'id'    => $g->id,
                'label' => $g->user_group ?: 'Group #' . $g->id,
                'count' => $g->contacts_count,
                'color' => $g->color,
            ])
            ->all();
    }

    /**
     * Resolve a list of phone numbers from the create-queue form.
     * Manual mode parses a CSV/newline string; group mode pulls
     * mobiles from contacts that reference the group id in their
     * (encrypted-array) contact_group column.
     */
    private function resolveRecipients(array $data): array
    {
        if (($data['recipient_type'] ?? null) === 'manual') {
            return collect(preg_split('/[,\n]+/', (string) ($data['recipients'] ?? '')))
                ->map(fn ($x) => trim((string) $x))
                ->filter(fn ($x) => $x !== '')
                ->unique()
                ->values()
                ->all();
        }

        $groupId = (string) ($data['contact_group_id'] ?? '');
        if ($groupId === '') return [];

        return Contact::query()
            ->whereNotNull('mobile')
            ->get(['id', 'mobile', 'contact_group'])
            ->filter(function (Contact $c) use ($groupId) {
                $list = is_array($c->contact_group) ? $c->contact_group : [];
                return in_array($groupId, array_map('strval', $list), true);
            })
            ->pluck('mobile')
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    private function presentConversation(Conversation $c): array
    {
        return [
            'id'               => $c->id,
            'title'            => $c->title,
            'preview'          => $c->preview,
            'status'           => $c->status,
            'archived'         => $c->archived,
            'platform'         => $c->platform,
            'recipients_count' => $c->recipients_count,
            'last_message_at'  => $c->last_message_at?->toIso8601String(),
            'last_message_ts'  => $c->last_message_at?->getTimestamp(),
            'last_message_lbl' => $c->last_message_at?->format('M d'),
            // `category` is what filter tab the row lives under (so
            // archived items show on the archived tab). `status` is
            // the underlying state (sent / failed / scheduled / …)
            // and the JS shows BOTH so archived failures are still
            // visible at a glance.
            'category'         => $this->categoryFor($c),
        ];
    }

    private function categoryFor(Conversation $c): string
    {
        if ($c->archived) return 'archived';
        return $c->status;
    }

    private function presentMessage(Message $m): array
    {
        $mediaName = null;
        $mediaSize = null;
        $mediaMime = null;
        if ($m->media_path) {
            $base = basename($m->media_path);
            $mediaName = str_contains($base, '__') ? substr($base, strpos($base, '__') + 2) : $base;
            // Size/mime from the active media disk (cloud or local).
            $d = media_storage();
            try {
                if ($d->exists($m->media_path)) {
                    $mediaSize = $d->size($m->media_path);
                    $mediaMime = $d->mimeType($m->media_path) ?: null;
                }
            } catch (\Throwable $e) { /* leave size/mime null */ }
        }
        // Template sends store their header / footer / buttons on the message
        // meta. Without passing them through, /chat rendered a template as a
        // bare line of body text — no header, no footer, no buttons — so the
        // operator could not see what the customer actually received.
        $meta = is_array($m->meta) ? $m->meta : [];
        $tplButtons = (isset($meta['buttons']) && is_array($meta['buttons']))
            ? array_values(array_filter($meta['buttons'], 'is_array'))
            : [];

        return [
            'id'         => $m->id,
            'template_name' => $meta['template_name'] ?? null,
            'header'        => $meta['header'] ?? null,
            'footer'        => $meta['footer'] ?? null,
            'buttons'       => $tplButtons ?: null,
            'direction'  => $m->direction,
            // Recipient of this outbound row — Quick Send is a bulk send-out
            // tool, so the thread header lists every recipient number.
            'to_number'  => $m->direction === 'out' ? $m->to_number : null,
            'body'       => $m->body,
            'media_url'  => $m->media_path ? media_url($m->media_path) : null,
            'media_type' => $m->media_type,
            'media_name' => $mediaName,
            'media_size' => $mediaSize,
            'media_mime' => $mediaMime,
            'latitude'   => $m->latitude !== null ? (float) $m->latitude : null,
            'longitude'  => $m->longitude !== null ? (float) $m->longitude : null,
            'status'     => $m->status,
            'pinned'     => (bool) $m->pinned,
            'starred'    => (bool) $m->starred,
            'reaction'   => $m->reaction,
            'time'       => $m->display_time,
            'created_at' => $m->created_at?->toIso8601String(),
        ];
    }

    /**
     * After appending a message, bump the conversation's preview /
     * last_message_at / status so the queue list re-sorts and
     * shows the new tail. Done in a single update() to avoid
     * pulling the model twice.
     */
    private function refreshConversationAfterSend(Conversation $conversation, Message $message): void
    {
        $conversation->forceFill([
            'preview'         => $message?->body ?: ($message?->media_type ? '['.$message->media_type.']' : $conversation->preview),
            'last_message_at' => $message?->sent_at ?: $message?->created_at ?: now(),
            'status'          => $this->rollupConversationStatus($conversation),
        ])->save();
    }

    /**
     * The queue's status, derived from its outbound messages.
     */
    private function rollupConversationStatus(Conversation $conversation): string
    {
        // Roll the conversation status up from its actual messages.
        // 'sent' is a lie if every row is 'failed' — show that instead.
        //
        // Only the LATEST message per recipient counts. A queue keeps its
        // whole send history, so folding in every row ever sent meant one
        // old failure branded the thread 'partial' forever — resend to that
        // same number, watch it succeed, and the badge still said 'partial'
        // because a week-old row was still in the tally. Collapsing to the
        // newest row per number answers the question the badge is actually
        // asking: how did the last send to each contact go? A genuine
        // mixed-outcome bulk send still reports 'partial' — those rows are
        // each the newest for their own number.
        $rows = Message::query()
            ->where('conversation_id', $conversation->id)
            ->where('direction', 'out')
            ->orderByDesc('id')
            ->get(['id', 'to_number', 'status']);

        $latestPerRecipient = [];
        foreach ($rows as $row) {
            // Rows with no number share one bucket — otherwise every stray
            // null-number failure would count as its own "recipient".
            $key = preg_replace('/\D+/', '', (string) $row->to_number) ?: '__unknown__';
            if (array_key_exists($key, $latestPerRecipient)) continue; // desc order → first hit is newest
            $latestPerRecipient[$key] = (string) $row->status;
        }
        $statuses = collect(array_values($latestPerRecipient));

        // 'sent' is only one of three success states — a message that reached
        // the handset reads 'delivered', and an opened one reads 'read'.
        // Testing for 'sent' alone made a mixed batch whose survivors had
        // already progressed to delivered/read report a clean 'sent' and hide
        // the failures.
        $delivered = fn () => $statuses->contains('sent')
            || $statuses->contains('delivered')
            || $statuses->contains('read');

        return match (true) {
            $statuses->contains('scheduled') && !$delivered() && !$statuses->contains('failed') => 'scheduled',
            $statuses->isNotEmpty() && $statuses->every(fn ($s) => $s === 'failed') => 'failed',
            $statuses->contains('failed') && $delivered() => 'partial',
            $delivered() => 'sent',
            default => $conversation->status ?: 'pending',
        };
    }

    private function resolveMediaType(string $extension): string
    {
        $extension = strtolower($extension);
        return match (true) {
            in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'webp'], true) => 'image',
            in_array($extension, ['mp4', 'webm', 'mov'],                true) => 'video',
            in_array($extension, ['mp3', 'wav', 'm4a', 'ogg'],          true) => 'audio',
            default                                                             => 'document',
        };
    }
}
