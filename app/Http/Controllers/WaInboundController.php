<?php

namespace App\Http\Controllers;

use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Device;
use App\Models\InboxMessage;
use App\Models\Message;
use App\Services\Inbox\ConversationResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Receives inbound WhatsApp messages from the Node Baileys bridge.
 *
 * Node calls POST /api/inbound-message for every customer message the
 * paired device receives. We resolve which Device this belongs to via
 * the device's phone (the Baileys side that received the message),
 * upsert a 1-on-1 Conversation keyed by the canonical Baileys JID for
 * the remote party, and create a Message row with direction='in'. The
 * /team-inbox + /chat pages then surface the thread.
 */
class WaInboundController extends Controller
{
    public function baileys(Request $request): JsonResponse
    {
        // X-Node-Token gate — the Baileys bridge is the only legit
        // caller. Without this anyone on the public internet could
        // POST forged inbound messages keyed by a device_phone and
        // write into that workspace's inbox.
        $expected = node_token();
        $token    = (string) $request->header('X-Node-Token', '');
        if ($expected === '' || !hash_equals($expected, $token)) {
            return response()->json(['ok' => false, 'error' => 'unauthorized'], 401);
        }

        $data = $request->validate([
            'device_phone'   => 'required|string|max:32',
            'sender_phone'   => 'required|string|max:32',
            'sender_name'    => 'nullable|string|max:191',
            'body'           => 'nullable|string|max:8192',
            'media_url'      => 'nullable|string|max:1024',
            'media_type'     => 'nullable|string|max:32',
            'media_mime'     => 'nullable|string|max:191',
            'media_filename' => 'nullable|string|max:255',
            'media_base64'   => 'nullable|string',
            'wa_message_id'  => 'nullable|string|max:191',
            // WhatsApp-style interactive card: buttons/header/footer rendered as a
            // green button-card in the team inbox (used by the wa_form flow node
            // mirror so "Open form" shows as a tappable-looking button, not text).
            'buttons'        => 'nullable|array',
            'header'         => 'nullable|string|max:191',
            'footer'         => 'nullable|string|max:191',
            'is_lid'         => 'nullable|boolean',
            'raw_jid'        => 'nullable|string|max:191',
            'lid_jid'        => 'nullable|string|max:191',
            'timestamp'      => 'nullable|integer',
            // Contact-card (vCard) sharing — Baileys / WABA send a structured
            // contact payload separate from media. We accept either a raw
            // vcard string or a parsed { name, phone } object. Stored on
            // Message::meta so the renderer can show a contact-card UI.
            'contact_vcard'  => 'nullable|string|max:8192',
            'contact_name'   => 'nullable|string|max:191',
            'contact_phone'  => 'nullable|string|max:64',
            // Forwarded-message metadata. WhatsApp marks messages as
            // forwarded (single arrow) or "frequently forwarded" (double
            // arrow / forwardingScore >= 5). Baileys exposes
            // contextInfo.isForwarded + contextInfo.forwardingScore.
            'is_forwarded'   => 'nullable|boolean',
            'forward_score'  => 'nullable|integer|min:0|max:1000',
            // Reaction-message — Baileys / WABA send a `reactionMessage`
            // payload that targets a previously-delivered WA message id
            // with an emoji (or empty string to clear). We stamp the
            // reaction onto the target Message row so the thread bubble
            // shows the round-emoji pip the team-inbox renderer expects.
            'reaction_emoji'    => 'nullable|string|max:32',
            'reaction_target'   => 'nullable|string|max:191', // wa_message_id of the message being reacted to
            // Location-share. Baileys `locationMessage` + WABA `location`
            // both carry lat/lng plus optional name/address. Persist on
            // meta.location so the renderer can show a map link / pin.
            'location_latitude'  => 'nullable|numeric|between:-90,90',
            'location_longitude' => 'nullable|numeric|between:-180,180',
            'location_name'      => 'nullable|string|max:191',
            'location_address'   => 'nullable|string|max:255',
            // Direction. Defaults to 'in' (customer → operator). When
            // Node sees a message with key.fromMe = true (operator sent
            // it from their phone or another linked device), it forwards
            // it here with direction='out' so the team inbox stays in
            // sync with what the phone-side WhatsApp client shows.
            'direction'         => 'nullable|in:in,out',
        ]);

        // ── Reaction-only payload ────────────────────────────────────
        // Reactions are not normal messages — they MUTATE an existing one.
        // If the inbound is a reaction, update that target row's
        // `reaction` column and return early; we don't want a phantom
        // empty message in the thread.
        if (!empty($data['reaction_emoji']) || !empty($data['reaction_target'])) {
            $targetWaId = (string) ($data['reaction_target'] ?? '');
            if ($targetWaId !== '') {
                // Reverse-lookup the Message by its WhatsApp id stored in
                // meta.wa_message_id (we already record it on outbound +
                // inbound).
                $devicePhoneDigits = preg_replace('/\D+/', '', (string) $data['device_phone']);
                $device = \App\Models\Device::query()->get(['id', 'user_id', 'workspace_id', 'country_code', 'phone_number'])
                    ->first(fn ($d) => preg_replace('/\D+/', '', (string) ($d->country_code . $d->phone_number)) === $devicePhoneDigits);
                $userId = $device?->user_id;
                $wsId   = $device?->workspace_id;
                if ($userId || $wsId) {
                    // Reactions land on inbox bubbles now — search the new
                    // table via direct JSON match so we don't cap at the
                    // last N messages (operators can react to weeks-old
                    // bubbles, especially in slow B2B threads).
                    $msg = InboxMessage::query()
                        ->whereJsonContains('meta->wa_message_id', $targetWaId)
                        ->whereHas('conversation', function ($q) use ($wsId, $userId) {
                            if ($wsId) {
                                $q->where('workspace_id', $wsId);
                            } else {
                                $q->where('user_id', $userId);
                            }
                        })
                        ->orderByDesc('id')
                        ->first();
                    if ($msg) {
                        $msg->update(['reaction' => $data['reaction_emoji'] ?? null]);
                        return response()->json(['ok' => true, 'kind' => 'reaction', 'message_id' => $msg->id]);
                    }
                }
            }
            // Nothing to attach to — silently accept so the bridge doesn't retry.
            return response()->json(['ok' => true, 'kind' => 'reaction', 'message_id' => null]);
        }

        $devicePhoneDigits = preg_replace('/\D+/', '', (string) $data['device_phone']);
        $senderPhoneDigits = preg_replace('/\D+/', '', (string) $data['sender_phone']);

        // The engine this mirror is landing on. Defaults to 'baileys' (this
        // endpoint's historical channel), overridden to the real provider when
        // the number turns out to live in wa_provider_configs (WABA / Twilio).
        // Critical: the WABA webhook stamps its thread provider='waba', so the
        // mirror MUST match + create with the same provider or it forks a
        // separate provider='baileys' twin the operator never sees.
        $deviceProvider = 'baileys';

        // Match the device by digits-only phone — phone_number is
        // encrypted-at-rest so we can't SQL-WHERE on it, we scan in PHP.
        $device = Device::query()
            ->get(['id', 'user_id', 'country_code', 'phone_number'])
            ->first(function ($d) use ($devicePhoneDigits) {
                $full = preg_replace('/\D+/', '', (string) ($d->country_code . $d->phone_number));
                return $full === $devicePhoneDigits;
            });
        // WABA/Twilio numbers aren't in the Baileys `devices` table — resolve
        // from wa_provider_configs so a flow / auto-reply sent on an official
        // number still mirrors into the Team Inbox thread (otherwise the bridge
        // gets 404 "device NOT FOUND" and the outbound bubble is dropped). A
        // lightweight stand-in carrying id + user_id + workspace_id is all the
        // downstream conversation/message writes read.
        if (!$device) {
            $cfg = \App\Models\WaProviderConfig::query()
                ->whereIn('provider', ['waba', 'twilio'])
                ->get()
                ->first(fn ($c) => preg_replace('/\D+/', '', (string) $c->phone_number) === $devicePhoneDigits);
            if ($cfg) {
                $ownerId = (int) ($cfg->user_id ?: (\App\Models\Workspace::whereKey($cfg->workspace_id)->value('owner_user_id') ?: 0));
                $device = (object) [
                    'id'           => (int) $cfg->id,
                    'user_id'      => $ownerId ?: null,
                    'workspace_id' => $cfg->workspace_id,
                    'country_code' => '',
                    'phone_number' => $cfg->phone_number,
                ];
                // This number is an OFFICIAL channel — carry its real provider
                // so the conversation match/create below aligns with the WABA
                // webhook's provider='waba'/'twilio' thread instead of forking
                // a provider='baileys' twin.
                $deviceProvider = (string) ($cfg->provider ?: 'baileys');
            }
        }
        if (!$device) {
            Log::warning('[AI-INBOX-TRACE] inbound-message device NOT FOUND — message dropped', [
                'device_phone' => $devicePhoneDigits,
                'direction'    => (string) ($data['direction'] ?? 'in'),
                'sender'       => $senderPhoneDigits,
            ]);
            return response()->json(['ok' => false, 'error' => 'device_not_found'], 404);
        }

        $userId = $device->user_id;

        // Server-side LID detection. Node sends is_lid when it could
        // tell, but in case the old Node code is still running (before
        // the LID fix) we also treat anything longer than 13 digits
        // as a LID. Real E.164 phones max out at 13.
        $isLid = (bool) ($data['is_lid'] ?? false)
              || strlen($senderPhoneDigits) > 13
              || strlen($senderPhoneDigits) < 8;

        // Canonical JID for outbound routing — preferred when we have
        // a real phone. The `lid_jid` is the alternate identifier
        // Baileys uses for the same conversation; we store both so
        // later inbounds match no matter which form Baileys hands us.
        $rawJid = (string) ($data['raw_jid'] ?? '');
        if ($rawJid === '') {
            $rawJid = $isLid ? ($senderPhoneDigits . '@lid') : ($senderPhoneDigits . '@s.whatsapp.net');
        }
        $lidJid = (string) ($data['lid_jid'] ?? '');

        $displayName = trim((string) ($data['sender_name'] ?? ''));
        $cleanName = ltrim($displayName, '~ '); // WhatsApp prefixes some names with "~"

        // Title: always include the phone when we have a real one (not
        // a LID). Format "<Name> · +<phone>" if both, "+<phone>" if no
        // name, just "<Name>" only when it's a LID-only chat.
        if ($isLid) {
            $title = $cleanName !== '' ? $cleanName : 'WhatsApp contact';
        } else {
            // Prefer the WhatsApp push name; if the sender never set one, fall back
            // to the customer's SAVED contact name so the inbox shows "John" instead
            // of a bare (later masked) number. Fixes threads that displayed
            // xxxxxx456 for a contact the operator had saved — and makes name search work.
            $resolvedName = $cleanName !== ''
                ? $cleanName
                : (string) (\App\Models\Contact::nameForPhone((int) $device->workspace_id, $senderPhoneDigits) ?? '');
            $title = $resolvedName !== ''
                ? $resolvedName . ' · +' . $senderPhoneDigits
                : '+' . $senderPhoneDigits;
        }

        Log::info('[INBOUND] received', [
            'device_phone' => $devicePhoneDigits,
            'sender'       => $senderPhoneDigits,
            'is_lid'       => $isLid,
            'raw_jid'      => $rawJid,
            'lid_jid'      => $lidJid,
            'media_type'   => $data['media_type'] ?? null,
            'has_media'    => !empty($data['media_base64']),
        ]);

        // ONE THREAD PER NUMBER.
        //
        // This used to be four hand-rolled lookup tiers, each filtering on a
        // different combination of origin / provider / device_id and each
        // comparing raw JID STRINGS. That is precisely what forked threads: a
        // number stored as '919…' was invisible to a search for
        // '919…@s.whatsapp.net', and a thread stamped origin='quick' was
        // invisible to a lookup that only accepted 'inbox'.
        //
        // ConversationResolver matches the customer's DIGITS across every
        // stored shape and partitions on nothing but the workspace, so this
        // path can no longer create a second thread for someone who already
        // has one — whichever engine or surface opened it.
        $wsId = (int) ($device->workspace_id
            ?: \App\Models\User::query()->whereKey($userId)->value('current_workspace_id'));

        // The customer's identity, in whichever shape Baileys handed it to us.
        // The resolver reduces all of them to the same digits, so the phone
        // form and the LID form both land on the one existing thread.
        $lookupJid = $rawJid !== '' ? $rawJid : ($senderPhoneDigits ?: $lidJid);

        $convo = $wsId
            ? ConversationResolver::find($wsId, $lookupJid)
            : null;

        // LID-only inbound: the digits of a @lid are NOT the phone number, so a
        // brand-new LID cannot resolve by itself. alt_jid carries the bridge
        // once we have seen both forms together, and the phone leg covers the
        // rest.
        if (!$convo && $wsId && $lidJid !== '' && $lidJid !== $lookupJid) {
            $convo = ConversationResolver::find($wsId, $lidJid);
        }

        $isNewConversation = !$convo;

        if (!$convo) {
            $convo = Conversation::create([
                'user_id'          => $userId,
                'workspace_id'     => $wsId ?: null,
                'device_id'        => $device->id,
                'contact_group_id' => null,
                'title'            => $title,
                'raw_jid'          => $rawJid,
                'alt_jid'          => $lidJid !== '' && $lidJid !== $rawJid ? $lidJid : null,
                'preview'          => $data['body'] ?? '',
                'status'           => 'pending',
                'platform'         => 'W',
                'provider'         => $deviceProvider,
                'origin'           => 'inbox',
                'recipients_count' => 1,
                'last_message_at'  => now(),
            ]);
        } else {
            // The thread is shared across engines now, so it has to remember
            // which channel is CURRENT — an operator reply goes back out the
            // way the customer last came in.
            ConversationResolver::stampChannel($convo, $deviceProvider, $device->id);
            // Backfill / upgrade fields when we learn something new:
            //   - raw_jid: prefer the phone form for outbound routing
            //   - alt_jid: stash whichever JID we just learned and
            //              didn't already have on file
            //   - title:   upgrade LID-only title once we know the phone
            $updates = [];
            $currentRaw = (string) $convo->raw_jid;
            $currentAlt = (string) $convo->alt_jid;

            if (!$currentRaw) {
                $updates['raw_jid'] = $rawJid;
                $currentRaw = $rawJid;
            } elseif (!$isLid && str_ends_with($currentRaw, '@lid')) {
                // Upgrade: keep the phone-form on raw_jid, push the LID
                // onto alt_jid (if not already there).
                $oldLid = $currentRaw;
                $updates['raw_jid'] = $rawJid;
                $currentRaw = $rawJid;
                if (!$currentAlt && $oldLid !== $rawJid) {
                    $updates['alt_jid'] = $oldLid;
                    $currentAlt = $oldLid;
                }
            }

            // Stash the LID form on alt_jid when we just learned it.
            if (!$currentAlt && $lidJid !== '' && $lidJid !== $currentRaw) {
                $updates['alt_jid'] = $lidJid;
            }

            $currentTitle = (string) $convo->title;
            $currentHasDigits = preg_match('/\d{8,}/', $currentTitle) === 1;
            if (!$isLid && !$currentHasDigits) {
                $updates['title'] = $title;
            }
            if (!empty($updates)) {
                $convo->update($updates);
            }
        }

        // Decode + save inline media to storage. Node sends the file
        // as base64 in the request body (capped at 16 MB upstream);
        // we drop it on disk so the team-inbox renderer can serve it
        // back like any chat-uploaded asset.
        $mediaPath = null;
        if (!empty($data['media_base64'])) {
            try {
                $bin = base64_decode((string) $data['media_base64'], true);
                if ($bin !== false && strlen($bin) > 0) {
                    $ext = $this->guessExtension(
                        (string) ($data['media_mime'] ?? ''),
                        (string) ($data['media_filename'] ?? ''),
                        (string) ($data['media_type'] ?? '')
                    );
                    $base = Str::random(24);
                    $orig = trim((string) ($data['media_filename'] ?? ''));
                    // Mirror chat-page convention: "chat-media/<random>__<original>".
                    $name = $orig !== ''
                        ? ($base . '__' . preg_replace('/[^A-Za-z0-9._-]+/', '_', $orig))
                        : ($base . '.' . $ext);
                    $mediaPath = 'chat-media/' . $name;
                    media_storage()->put($mediaPath, $bin);
                }
            } catch (\Throwable $e) {
                Log::warning('[INBOUND] media save failed', ['err' => $e->getMessage()]);
            }
        }

        // Build the message meta dict. Different feature flags merge in:
        //   - contact card (vCard) details, if shared
        //   - forwarded indicator + score
        //   - wa_message_id so future reactions / forwards can find this row
        $msgMeta = [];
        if (!empty($data['wa_message_id'])) {
            $msgMeta['wa_message_id'] = (string) $data['wa_message_id'];
        }
        if (!empty($data['contact_vcard']) || !empty($data['contact_name']) || !empty($data['contact_phone'])) {
            $msgMeta['contact'] = array_filter([
                'name'  => $data['contact_name']  ?? null,
                'phone' => $data['contact_phone'] ?? null,
                'vcard' => $data['contact_vcard'] ?? null,
            ]);
        }
        if (!empty($data['is_forwarded'])) {
            $msgMeta['forwarded'] = true;
            $score = (int) ($data['forward_score'] ?? 0);
            if ($score >= 5) $msgMeta['frequently_forwarded'] = true;
            if ($score > 0)  $msgMeta['forward_score'] = $score;
        }
        $hasLocation = isset($data['location_latitude']) && isset($data['location_longitude']);
        if ($hasLocation) {
            $msgMeta['location_latitude']  = (float) $data['location_latitude'];
            $msgMeta['location_longitude'] = (float) $data['location_longitude'];
            if (!empty($data['location_name']))    $msgMeta['location_name']    = (string) $data['location_name'];
            if (!empty($data['location_address'])) $msgMeta['location_address'] = (string) $data['location_address'];
        }
        $contactMeta = !empty($msgMeta) ? $msgMeta : null;

        $resolvedMediaType = $hasLocation
            ? 'location'
            : (!empty($msgMeta['contact']) ? 'contact' : ($data['media_type'] ?? null));

        $direction = (string) ($data['direction'] ?? 'in');

        // Idempotency by wa_message_id — for BOTH directions.
        //  - outbound: Node forwards a fromMe sync for a message the operator
        //    just sent via the web UI (we already have the row).
        //  - inbound: Baileys re-delivers the SAME message on reconnect /
        //    history-replay / a double messages.upsert, which was creating
        //    duplicate bubbles (the client's "one sent, many appeared").
        // Either way, if this wa_message_id is already on a row in this
        // conversation, skip silently so no phantom duplicate is written.
        $incomingWaId = (string) ($data['wa_message_id'] ?? '');
        if ($incomingWaId !== '') {
            $existing = InboxMessage::query()
                ->where('conversation_id', $convo->id)
                ->orderByDesc('id')->limit(80)->get()
                ->first(fn ($m) => is_array($m->meta) && (string) ($m->meta['wa_message_id'] ?? '') === $incomingWaId);
            if ($existing) {
                Log::info('[INBOUND] wa_message_id dedup hit', ['conv_id' => $convo->id, 'direction' => $direction, 'wa_message_id' => $incomingWaId, 'msg_id' => $existing->id]);
                return response()->json(['ok' => true, 'kind' => 'dedup', 'message_id' => $existing->id]);
            }
        }

        // For outbound syncs, the device IS the sender and the customer
        // is the recipient. Swap from/to so the row matches a normal
        // operator-sent message.
        if ($direction === 'out') {
            $fromNumber = $devicePhoneDigits;
            $toNumber   = $senderPhoneDigits;
            $status     = 'delivered';
        } else {
            $fromNumber = $senderPhoneDigits;
            $toNumber   = $devicePhoneDigits;
            $status     = 'delivered';
        }

        if ($incomingWaId !== '') {
            $msgMeta['wa_message_id'] = $incomingWaId;
        }
        // Stamp phone_sync ONLY on outbound syncs — not a generic key
        // that pollutes inbound rows.
        if ($direction === 'out') {
            $msgMeta['phone_sync'] = true;
        }
        // Interactive card fields → serializeMessage passes these to the inbox
        // renderer, which draws a WhatsApp-style header/body/footer + button rows.
        if (!empty($data['buttons']) && is_array($data['buttons'])) $msgMeta['buttons'] = array_values($data['buttons']);
        if (!empty($data['header'])) $msgMeta['header'] = (string) $data['header'];
        if (!empty($data['footer'])) $msgMeta['footer'] = (string) $data['footer'];
        $contactMeta = !empty($msgMeta) ? $msgMeta : null;

        $inboundMsg = InboxMessage::create([
            'conversation_id' => $convo->id,
            'user_id'         => $userId,
            'direction'       => $direction,
            'from_number'     => $fromNumber,
            'to_number'       => $toNumber,
            'body'            => $data['body'] ?? '',
            'media_path'      => $mediaPath,
            'media_type'      => $resolvedMediaType,
            'latitude'        => $hasLocation ? (float) $data['location_latitude']  : null,
            'longitude'       => $hasLocation ? (float) $data['location_longitude'] : null,
            'meta'            => $contactMeta,
            'status'          => $status,
            'sent_at'         => isset($data['timestamp']) ? \Carbon\Carbon::createFromTimestamp($data['timestamp']) : now(),
            'delivered_at'    => now(),
        ]);

        if ($direction === 'out') {
            Log::info('[AI-INBOX-TRACE] outbound mirror recorded in team inbox', [
                'inbox_message_id' => $inboundMsg->id,
                'conversation_id'  => $convo->id,
                // device_provider + is_new confirm the provider-aware match
                // landed on the WABA webhook's thread (same conversation_id as
                // [WABA-INBOUND-TRACE]) instead of forking a baileys twin.
                'device_provider'  => $deviceProvider,
                'is_new_convo'     => $isNewConversation,
                'device_phone'     => $devicePhoneDigits,
                'to'               => $toNumber,
                'wa_message_id'    => $incomingWaId,
            ]);
        }

        $previewText = (string) ($data['body'] ?? '');
        if ($previewText === '' && $hasLocation) {
            $previewText = '📍 ' . ($data['location_name'] ?? 'Location');
        } elseif ($previewText === '' && !empty($resolvedMediaType)) {
            $previewText = '📎 ' . ucfirst((string) $resolvedMediaType);
        }

        // Outbound syncs DON'T bump unread (operator already saw it on
        // their phone). They also don't move status → pending. They DO
        // touch last_outbound_at + last_message_at so the queue ordering
        // and "Last reply was at" still work.
        if ($direction === 'out') {
            $convo->update([
                'preview'          => mb_substr($previewText, 0, 191),
                'last_message_at'  => now(),
                'last_outbound_at' => now(),
            ]);
        } else {
            $convo->update([
                'preview'         => mb_substr($previewText, 0, 191),
                'last_message_at' => now(),
                'last_inbound_at' => now(),
                // A new customer message = an ACTIVE conversation → 'open'
                // (was 'pending', which made every inbound thread read
                // "PENDING"). Both land in the Open queue; 'open' just labels
                // it correctly. A customer reply to a resolved/snoozed thread
                // still reopens it (open ⊂ the Open scope, same as before).
                'inbox_status'    => 'open',
            ]);
            $convo->increment('unread_count');
        }

        // Auto-capture the customer as a Contact. A NEW number — whether the
        // customer just messaged in, OR the operator messaged THEM first from the
        // linked phone (the direction='out' fromMe mirror) — must land in
        // Contacts, not only once they reply. This bridge mirror never created a
        // Contact (only the WABA webhook did), so a number reached from the phone
        // stayed missing from the contact list until an inbound arrived —
        // exactly "new no from mobile reflects but no contact created". The
        // customer's digits are $senderPhoneDigits in BOTH directions (see the
        // from/to swap above). Skip LID-only chats — a @lid id is not a dialable
        // phone. rememberPhone dedups by phone hash (existing contact reused, no
        // duplicates); stamp contact_id on the thread when the column exists.
        // Best-effort — never break the mirror.
        if (!$isLid && $wsId && $senderPhoneDigits !== '') {
            try {
                $capturedContact = \App\Models\Contact::rememberPhone(
                    $wsId,
                    $userId,
                    $senderPhoneDigits,
                    $cleanName !== '' ? $cleanName : null,
                );
                if ($capturedContact && empty($convo->contact_id)) {
                    static $convoHasContactId = null;
                    if ($convoHasContactId === null) {
                        try {
                            $convoHasContactId = \Illuminate\Support\Facades\Schema::hasColumn('conversations', 'contact_id');
                        } catch (\Throwable $e) {
                            $convoHasContactId = false;
                        }
                    }
                    if ($convoHasContactId) {
                        $convo->forceFill(['contact_id' => $capturedContact->id])->save();
                    }
                }
            } catch (\Throwable $e) {
                Log::warning('[INBOUND] contact capture failed: ' . $e->getMessage());
            }
        }

        Log::info('[INBOUND] stored', [
            'conv_id'    => $convo->id,
            'device_id'  => $device->id,
            'user_id'    => $userId,
            'media_path' => $mediaPath,
        ]);

        // Outbound syncs (operator messaged from their phone) just need
        // the row written + last_outbound_at touched — no routing, no
        // auto-reply, no drip enrollment, no inbound webhook fires.
        // The customer wasn't the actor here; we're just keeping the
        // team-inbox UI consistent with what the phone shows.
        if ($direction === 'out') {
            return response()->json(['ok' => true, 'kind' => 'outbound_sync', 'conv_id' => $convo->id]);
        }

        // Broadcast on inbound so connected Echo clients see the new
        // customer message live (without the existing 3s polling).
        // Wrapped — broadcasting backend failures must NOT break the
        // webhook response.
        try {
            broadcast(new \App\Events\Inbox\MessageReceived(
                $inboundMsg->id, $convo->id, (int) $convo->workspace_id, 'in', null
            ))->toOthers();
        } catch (\Throwable $e) {
            Log::warning('[INBOUND] broadcast failed: ' . $e->getMessage());
        }

        // Fire webhook subscribers so CRM integrations get notified of every
        // inbound message + every brand-new conversation. Wrapped in
        // try/catch via the dispatcher; can't break the inbound write.
        try {
            $whd = app(\App\Services\Inbox\OutboundWebhookDispatcher::class);
            if ($isNewConversation) {
                $whd->fire('conversation.created', $convo->fresh(), [
                    'channel'      => 'whatsapp',
                    'sender_phone' => $senderPhoneDigits,
                    'sender_name'  => $cleanName,
                ]);
            }
            $whd->fire('conversation.received', $convo->fresh(), [
                'body'         => $data['body'] ?? '',
                'media_type'   => $resolvedMediaType,
                'sender_phone' => $senderPhoneDigits,
            ]);
        } catch (\Throwable $e) {
            Log::warning('[INBOUND] webhook fire failed: ' . $e->getMessage());
        }

        // Shopify COD double-confirmation — if this customer has a pending
        // cash-on-delivery order and just replied Yes/No, flip the order +
        // tracking row and ack before generic routing/auto-reply runs.
        try {
            app(\App\Services\Shopify\ShopifyCodService::class)->handleInboundReply(
                (int) $convo->workspace_id,
                $senderPhoneDigits,
                (string) ($data['body'] ?? ''),
            );
        } catch (\Throwable $e) {
            Log::warning('[INBOUND] COD reply check failed: ' . $e->getMessage());
        }

        // Shopify back-in-stock opt-in — if the customer asked to be
        // notified about a sold-out product, add them to its waitlist.
        try {
            app(\App\Services\Shopify\ShopifyStockService::class)->handleInboundOptIn(
                (int) $convo->workspace_id,
                $senderPhoneDigits,
                (string) ($data['body'] ?? ''),
            );
        } catch (\Throwable $e) {
            Log::warning('[INBOUND] stock opt-in check failed: ' . $e->getMessage());
        }

        // WooCommerce COD double-confirmation — same Yes/No flip for a pending
        // WooCommerce cash-on-delivery order (flips the order via Woo REST).
        try {
            app(\App\Services\Woocommerce\WoocommerceCodService::class)->handleInboundReply(
                (int) $convo->workspace_id,
                $senderPhoneDigits,
                (string) ($data['body'] ?? ''),
            );
        } catch (\Throwable $e) {
            Log::warning('[INBOUND] WC COD reply check failed: ' . $e->getMessage());
        }

        // WooCommerce back-in-stock opt-in.
        try {
            app(\App\Services\Woocommerce\WoocommerceStockService::class)->handleInboundOptIn(
                (int) $convo->workspace_id,
                $senderPhoneDigits,
                (string) ($data['body'] ?? ''),
            );
        } catch (\Throwable $e) {
            Log::warning('[INBOUND] WC stock opt-in check failed: ' . $e->getMessage());
        }

        // WooCommerce concierge — one-tap reorder, loyalty/points query, and
        // review-gated coupon. Keyword/rating driven; tightly gated so it only
        // acts for known WooCommerce customers.
        try {
            app(\App\Services\Woocommerce\WoocommerceConciergeService::class)->handleInbound(
                (int) $convo->workspace_id,
                $senderPhoneDigits,
                (string) ($data['body'] ?? ''),
            );
        } catch (\Throwable $e) {
            Log::warning('[INBOUND] WC concierge check failed: ' . $e->getMessage());
        }

        // Catalog concierge — turns a free-text product query ("red shoes
        // under 2000") into an instant Multi-Product Message of matches.
        // OFF unless the merchant enabled it on the catalog; self-cooldowns
        // and stays silent on no match, so it never disrupts normal inbound.
        try {
            app(\App\Services\WhatsAppCatalog\CatalogConciergeService::class)->handleInbound(
                (int) $convo->workspace_id,
                $senderPhoneDigits,
                (string) ($data['body'] ?? ''),
            );
        } catch (\Throwable $e) {
            Log::warning('[INBOUND] catalog concierge check failed: ' . $e->getMessage());
        }

        // Routing runs on EVERY inbound — new conversations get the full
        // action set, follow-ups get only per-message actions (add_tag /
        // auto_reply / trigger_flow). One-shot actions (assign_team /
        // assign_user / set_priority) are skipped on follow-ups so we
        // don't trample operator-driven state changes. The split lives
        // inside RoutingEngine; we just tell it which mode we're in.
        try {
            app(\App\Services\Inbox\RoutingEngine::class)->applyToInbound(
                $convo->fresh(),
                [
                    'message_text'  => $data['body'] ?? '',
                    'contact_phone' => $senderPhoneDigits,
                ],
                isFollowUp: !$isNewConversation,
            );
            $convo = $convo->fresh(); // re-fetch: routing may have set assignee_agent_id / new tags
        } catch (\Throwable $e) {
            Log::warning('[INBOUND] routing engine failed: ' . $e->getMessage());
        }

        // Trigger AI agent auto-response if one is assigned. Audio
        // messages take the voice-AI path (ASR → LLM → TTS → voice
        // reply) when the agent has voice_note_enabled — otherwise
        // they fall through to the existing text path.
        //
        // The voice pipeline takes 5–15s end-to-end which is too long
        // to inline into the webhook response, but we deliberately
        // avoid queue workers / cron / scheduler. Instead we use
        // VoiceReplyDeferred which registers the work as a Laravel
        // `terminating()` callback — runs AFTER the response is sent
        // (PHP-FPM's fastcgi_finish_request) inside the SAME request,
        // so no worker process is needed.
        // STOP / START opt-out. Placed AFTER the message is stored (the
        // operator should still see that the customer said STOP) but BEFORE
        // the AI agent, the voice assistant and the outside-hours reply — a
        // customer asking to be left alone must not be answered by a bot.
        // Returning here ends the handler for this inbound.
        try {
            if (app(\App\Services\Inbox\OptOutService::class)
                ->handle($convo->fresh(), $data['body'] ?? null, $data['sender_phone'] ?? null)) {
                return response()->json([
                    'ok'      => true,
                    'kind'    => 'opt_out',
                    'conv_id' => $convo->id,
                ]);
            }
        } catch (\Throwable $e) {
            Log::warning('[INBOUND] opt-out check failed: ' . $e->getMessage());
        }

        if ($convo->assignee_agent_id) {
            try {
                $agent = \App\Models\AiAgent::find($convo->assignee_agent_id);
                $isAudio = $resolvedMediaType === 'audio' && $inboundMsg && $inboundMsg->media_path;
                $voiceService = app(\App\Services\Voice\AiVoiceReplyService::class);

                if ($isAudio && $voiceService->canVoiceReply($agent, $convo->fresh())) {
                    app(\App\Services\Voice\VoiceReplyDeferred::class)->run($inboundMsg->id);
                } else if ($isAudio && empty($data['body'])) {
                    // Audio inbound + voice reply NOT configured + no
                    // transcript yet. Skipping prevents the LLM from
                    // hallucinating against an empty user turn (per
                    // 2026-05-24 audit issue #15).
                    Log::info('[INBOUND] AI text-respond skipped — audio-only with no transcript', ['conv_id' => $convo->id]);
                } else {
                    // Default: existing text path.
                    app(\App\Services\AiAgentService::class)->respondIfAssigned($convo->fresh());
                }
            } catch (\Throwable $e) {
                Log::warning('[INBOUND] AI agent respond failed: ' . $e->getMessage());
            }
        }

        // AI Voice Assistant assignment (separate from the AiAgent slot).
        // When the operator picks a Voice Assistant in /team-inbox, we
        // store the id under `routing_meta.voice_assistant_id`. The
        // assistant's system_prompt + provider/model run through the
        // SAME AiAgentService::callProvider plumbing, so admin keys +
        // BYOK + rate-limits all apply uniformly.
        $voiceAssistantId = (int) ($convo->fresh()->routing_meta['voice_assistant_id'] ?? 0);
        if ($voiceAssistantId > 0) {
            try {
                app(\App\Services\AiAgentService::class)->respondAsVoiceAssistant($convo->fresh(), $voiceAssistantId);
            } catch (\Throwable $e) {
                Log::warning('[INBOUND] Voice assistant respond failed: ' . $e->getMessage());
            }
        }

        // Outside-business-hours template reply. Fires on EVERY inbound
        // (not just new conversations) so customers who message after
        // 5pm always get the "we are closed" template — but the
        // AutoReplyGuard's per-contact cooldown + flood detector keeps
        // it from spamming back when the customer sends 30 messages
        // in a row.
        try {
            $this->maybeSendOutsideHoursReply($convo->fresh(), $device);
        } catch (\Throwable $e) {
            Log::warning('[INBOUND] outside-hours auto-reply failed: ' . $e->getMessage());
        }

        // Surface any routing-rule side-effects back to the Node bot. The
        // bot already calls this endpoint per inbound, so we ride on the
        // same request — no separate polling endpoint, no 429 risk.
        // Currently the only side-effect Node needs to act on is
        // trigger_flow → start a flow session for this conversation.
        // Consume the slot here so we don't replay the trigger if the
        // customer keeps messaging the same conversation.
        $routingActions = [];
        $convoFresh = $convo->fresh();
        $meta = $convoFresh->routing_meta ?? [];
        if (!empty($meta['pending_flow_id'])) {
            $routingActions['flow_id'] = (int) $meta['pending_flow_id'];
            unset($meta['pending_flow_id'], $meta['pending_flow_at']);
            $convoFresh->forceFill(['routing_meta' => $meta])->save();
        }

        // Unofficial API (Baileys) VOICE / PHOTO ordering — Jessica build.
        // A voice note or a photo carries no text, so it can never match a
        // keyword trigger — the order flow would never start and the AI would
        // never get to hear / see the order. Here, when the customer sends a
        // voice/photo with NO text and is NOT already mid-order, we start the
        // workspace's order flow. It launches from the Node `.then()` that runs
        // AFTER this request has saved the media, so OrderMediaResolver finds
        // the file (no race). A voice/photo sent DURING an order is the delivery
        // address — the active flow session handles that, and the pending-cart
        // guard below stops us from restarting the order over it.
        if (empty($routingActions['flow_id'])
            && in_array((string) $resolvedMediaType, ['audio', 'image'], true)
            && trim((string) ($data['body'] ?? '')) === ''
            && $direction === 'in') {
            $custDigits = preg_replace('/\D+/', '', (string) ($data['sender_phone'] ?? ''));
            $midOrder = $custDigits !== '' && \App\Models\WaPendingOrder::query()
                ->where('workspace_id', $convo->workspace_id)
                ->where('customer_phone', $custDigits)
                ->where('status', 'pending')
                ->exists();
            if (!$midOrder) {
                $orderFlowId = $this->orderFlowIdForWorkspace((int) $convo->workspace_id);
                if ($orderFlowId) {
                    $routingActions['flow_id'] = $orderFlowId;
                    Log::info('[ORDER-FLOW] 0 · media-only inbound → start order flow', [
                        'ws' => $convo->workspace_id, 'flow_id' => $orderFlowId, 'media' => $resolvedMediaType,
                    ]);
                }
            }
        }

        return response()->json([
            'ok'              => true,
            'conversation_id' => $convo->id,
            'routing_actions' => $routingActions,
        ]);
    }

    /**
     * The workspace's natural-language order flow (Unofficial API / Baileys),
     * identified by its order-parse webhook node — so it needs NO extra column
     * or flag: any published+active flow the user built with the order-parse
     * step is the order flow. flow_data is encrypted at rest, so we decrypt +
     * scan in PHP (only this workspace's published+active flows, newest first).
     * Cached per request. Returns null when the workspace has no order flow,
     * which means a stray voice/photo simply does nothing (feature is opt-in by
     * virtue of having built an order flow at all).
     */
    private function orderFlowIdForWorkspace(int $wsId): ?int
    {
        static $cache = [];
        if (array_key_exists($wsId, $cache)) {
            return $cache[$wsId];
        }

        $flow = \App\Models\Flow::query()
            ->where('workspace_id', $wsId)
            ->where('is_published', true)
            ->where('is_active', true)
            ->orderByDesc('id')
            ->get(['id', 'flow_data'])
            ->first(fn ($f) => str_contains((string) $f->flow_data, '/api/flow-node/order-parse'));

        return $cache[$wsId] = $flow?->id;
    }

    /**
     * Inline outside-hours auto-reply. Reads the workspace's
     * business_hours config; if the current time is outside hours AND
     * an outside_action='template' is configured, sends that template
     * back to the customer. The AutoReplyGuard handles cooldown + spam
     * detection so this method is safe to call on every inbound.
     */
    private function maybeSendOutsideHoursReply(\App\Models\Conversation $convo, \App\Models\Device $device): void
    {
        $ws = $convo->workspace_id ? \App\Models\Workspace::find($convo->workspace_id) : null;
        if (!$ws || !$ws->isOutsideBusinessHours()) return;

        $cfg = is_array($ws->business_hours) ? $ws->business_hours : [];
        $action = (string) ($cfg['outside_action'] ?? 'none');
        $tplId  = (int) ($cfg['outside_template_id'] ?? 0);
        if ($action !== 'template' || $tplId <= 0) return;

        if (!app(\App\Services\Inbox\AutoReplyGuard::class)->canAutoReply($convo)) return;

        // Workspace-scoped template lookup, same defensive check as the
        // RoutingEngine auto_reply action.
        $tpl = \App\Models\WaTemplate::query()
            ->where('id', $tplId)
            ->where('workspace_id', $convo->workspace_id)
            ->first();
        if (!$tpl) return;

        $body = (string) ($tpl->template_body ?? '');
        if (trim($body) === '') return;

        $fromNumber = preg_replace('/\D+/', '', (string) ($device->country_code . $device->phone_number));
        $toNumber   = preg_replace('/\D+/', '', (string) $convo->raw_jid);
        if ($toNumber === '') return;

        $msg = \App\Models\InboxMessage::create([
            'conversation_id' => $convo->id,
            'user_id'         => $convo->user_id,
            'direction'       => 'out',
            'from_number'     => $fromNumber,
            'to_number'       => $toNumber,
            'body'            => $body,
            'meta'            => ['target_jid' => $convo->raw_jid, 'origin' => 'outside_business_hours'],
            'status'          => 'pending',
            'sent_at'         => now(),
        ]);

        $convo->update([
            'preview'          => mb_substr($body, 0, 191),
            'last_message_at'  => now(),
            'last_outbound_at' => now(),
        ]);

        // Mark cooldown BEFORE dispatch so a Node-side failure
        // doesn't allow an immediate retry on the next inbound.
        app(\App\Services\Inbox\AutoReplyGuard::class)->markReplied($convo);
        app(\App\Services\InboxDispatcher::class)->send($msg, $convo->platform ?? 'W');
    }

    /**
     * Best-effort extension chooser for the saved media file. Prefers
     * the original filename's extension, falls back to mime parsing,
     * then to the broad media_type bucket (image/video/audio/doc).
     */
    private function guessExtension(string $mime, string $filename, string $mediaType): string
    {
        if ($filename !== '' && preg_match('/\.([A-Za-z0-9]{1,8})$/', $filename, $m)) {
            return strtolower($m[1]);
        }
        if ($mime !== '') {
            // Strip mime PARAMETERS first — "audio/ogg; codecs=opus" must yield
            // "ogg", not "oggcodecsopus" truncated to the invalid "oggcod" that
            // made Whisper 400 "Invalid file format" on every voice-note order.
            $bare = strtolower(trim(explode(';', $mime)[0]));
            $sub  = preg_replace('/[^a-z0-9]/', '', explode('/', $bare)[1] ?? '');
            if ($sub === 'jpeg') return 'jpg';
            if ($sub === 'svgxml') return 'svg';
            if ($sub !== '') return substr($sub, 0, 6);
        }
        return match ($mediaType) {
            'image'    => 'jpg',
            'video'    => 'mp4',
            'audio'    => 'ogg',
            'document' => 'bin',
            default    => 'bin',
        };
    }
}
