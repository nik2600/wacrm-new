<?php

namespace App\Services\Instaflow;

use App\Events\Inbox\MessageReceived;
use App\Models\Conversation;
use App\Models\InboxMessage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Turns one Instaflow IG message payload into a WaDesk unified-inbox row.
 *
 * Shared by BOTH inbound paths so they behave identically:
 *   - PUSH  — Instaflow POSTs to /api/instaflow/inbound (works when WaDesk is
 *             publicly reachable). See InstaflowInboundController.
 *   - PULL  — WaDesk pulls new IG messages from Instaflow over the bridge
 *             (the ONLY path that works when WaDesk runs on a private LAN the
 *             hosted Instaflow can't reach). See InstaflowInboundPuller.
 *
 * IG threads are keyed by the namespaced Instaflow conversation id
 * (raw_jid = "ig:<accountId>_<igsid>", channel = 'instagram'), deduped on the
 * IG message id so the same message can arrive via push AND pull without ever
 * double-rendering.
 */
class InstaflowIngestService
{
    /**
     * @param array $data {event, workspace_id, account{id,username,name,avatar},
     *                      conversation{id,name,handle,avatar},
     *                      message{id,from_me,type,text,media_url,at}}
     * @return InboxMessage|null  the stored (or existing, on dedup) row; null if not a message
     */
    public static function ingest(int $wsId, array $data): ?InboxMessage
    {
        if (($data['event'] ?? 'message') !== 'message') {
            return null;
        }

        $igConvId = (string) data_get($data, 'conversation.id', '');
        $handle   = trim((string) data_get($data, 'conversation.handle', ''));
        $name     = trim((string) data_get($data, 'conversation.name', ''));
        $title    = $name !== '' ? $name : ($handle !== '' ? '@' . ltrim($handle, '@') : 'Instagram');
        $key      = 'ig:' . ($igConvId !== '' ? $igConvId : 'unknown-' . md5(json_encode($data)));

        $convo = Conversation::firstOrCreate(
            ['workspace_id' => $wsId, 'channel' => 'instagram', 'raw_jid' => $key],
            [
                'title'           => $title,
                'provider'        => 'instagram',
                'origin'          => 'instagram',
                'status'          => 'pending',
                'inbox_status'    => 'open',
                'last_message_at' => now(),
                'contact_digits'  => null,
            ]
        );

        // Title refresh: the FIRST inbound webhook often carries only the sender's
        // IGSID (a bare number), so firstOrCreate stored "@913620425089077". Once a
        // later message resolves a real @username / name, update the title in place
        // — but only overwrite a numeric/placeholder title, never a human name an
        // operator may have set on the thread.
        if (! $convo->wasRecentlyCreated) {
            $resolvedHuman       = $name !== '' || ($handle !== '' && ! ctype_digit(ltrim($handle, '@')));
            $storedIsPlaceholder = $convo->title === 'Instagram' || ctype_digit(ltrim((string) $convo->title, '@'));
            if ($resolvedHuman && $storedIsPlaceholder && $convo->title !== $title) {
                $convo->forceFill(['title' => $title])->save();
            }
        }

        $m           = (array) data_get($data, 'message', []);
        $dir         = data_get($m, 'from_me') ? 'out' : 'in';
        $type        = (string) data_get($m, 'type', 'text');
        $mediaUrl    = trim((string) data_get($m, 'media_url', ''));
        $providerMid = trim((string) data_get($m, 'id', ''));

        // Interactive template buttons carried from Instaflow (a quick-reply set
        // rendered as a postback card, or URL/postback buttons). Instaflow ships
        // {title,url?}; the WaDesk inbox renderer reads {text,url} at top-level
        // meta.buttons — normalise here so the button card renders instead of
        // plain text. Shared by BOTH inbound paths (push + pull).
        $normBtns = self::normalizeButtons(data_get($m, 'buttons'));

        // Idempotency: dedup on the IG message id within the thread so a message
        // that arrives via BOTH push and pull is stored only once. Checks BOTH
        // meta keys: inbound/pulled messages store it under
        // meta.instagram.message_id, while a WaDesk-ORIGINATED reply (sent from
        // the composer over the bridge) stores the returned IG id under
        // meta.wa_message_id — so pulling outbound never duplicates our own send.
        if ($providerMid !== '') {
            $existing = InboxMessage::where('conversation_id', $convo->id)
                ->where(function ($q) use ($providerMid) {
                    $q->where('meta->instagram->message_id', $providerMid)
                      ->orWhere('meta->wa_message_id', $providerMid);
                })
                ->first();
            if ($existing) {
                // BACKFILL — a row stored BEFORE the button-mirror fix (or a
                // WaDesk-originated send whose local row never captured the
                // template's buttons) renders as plain text. Now that Instaflow
                // reports the buttons for this same message, graft them onto the
                // existing row when it has none — so historic template bubbles
                // show their button card, no data migration required.
                if ($normBtns) {
                    $meta = is_array($existing->meta) ? $existing->meta : [];
                    if (empty($meta['buttons'])) {
                        $meta['buttons'] = $normBtns;
                        $existing->meta  = $meta;
                        $existing->save();
                        Log::info('[INSTAFLOW] backfilled buttons on existing row', [
                            'row' => $existing->id, 'mid' => $providerMid, 'buttons' => count($normBtns),
                        ]);
                    }
                }
                return $existing;
            }
        }

        // Preserve the original send time when Instaflow provides it (ISO-8601);
        // fall back to now() so ordering never breaks.
        $sentAt = now();
        $rawAt  = (string) data_get($m, 'at', '');
        if ($rawAt !== '') {
            try { $sentAt = \Illuminate\Support\Carbon::parse($rawAt); } catch (\Throwable $e) {}
        }

        $inbox = InboxMessage::create([
            'conversation_id' => $convo->id,
            'provider'        => 'instagram',
            'direction'       => $dir,
            'body'            => (string) data_get($m, 'text', ''),
            'media_type'      => $type !== 'text' ? $type : null,
            'status'          => $dir === 'in' ? 'received' : 'sent',
            'meta'            => array_filter([
                'buttons'   => $normBtns,
                'instagram' => array_filter([
                    'conversation_id' => $igConvId,
                    'message_id'      => $providerMid ?: null,
                    'account'         => data_get($data, 'account.username'),
                    'account_id'      => data_get($data, 'account.id'),
                    'handle'          => $handle,
                    'avatar'          => data_get($data, 'conversation.avatar'),
                    'media_url'       => $mediaUrl ?: null,
                ], fn ($v) => $v !== null && $v !== ''),
            ]),
            'sent_at'         => $sentAt,
            'delivered_at'    => $sentAt,
        ]);

        // Last-message preview — so IG rows show the snippet like WhatsApp rows
        // do (a media message with no caption shows a "[Photo]"-style label).
        $previewText = (string) data_get($m, 'text', '');
        if ($previewText === '' && $type !== 'text') {
            $previewText = '[' . ucfirst($type) . ']';
        }
        $convoUpdate = [
            'last_message_at' => $sentAt,
            'title'           => $title,
            'provider'        => 'instagram',
            'preview'         => mb_substr($previewText, 0, 140),
        ];
        // Stamp the direction timestamp so the 24-hour customer-care window
        // computes for IG threads (window = last_inbound_at + 24h). Without
        // last_inbound_at the header countdown never shows on Instagram.
        if ($dir === 'in' && Schema::hasColumn('conversations', 'last_inbound_at')) {
            $convoUpdate['last_inbound_at'] = $sentAt;
        } elseif ($dir === 'out' && Schema::hasColumn('conversations', 'last_outbound_at')) {
            $convoUpdate['last_outbound_at'] = $sentAt;
        }
        $convo->forceFill($convoUpdate)->save();
        if ($dir === 'in' && Schema::hasColumn('conversations', 'unread_count')) {
            $convo->increment('unread_count');
        }

        event(new MessageReceived($inbox->id, $convo->id, $wsId, $dir, null));

        // Fire the /auto-reply keyword matcher for genuine inbound (mirror the
        // push path). Same guards; reply ships back over the bridge.
        if ($dir === 'in') {
            $body = trim((string) data_get($m, 'text', ''));

            // Instagram-native trigger context set by the IG webhook. A plain DM
            // is 'dm_keyword' (identical to today's path); story replies, story
            // mentions and @mentions carry their own trigger so the dispatcher
            // fires only the matching rule kind. NULL for non-IG ingest.
            $igTrigger = (string) data_get($m, 'ig_trigger', '') ?: 'dm_keyword';
            $igNoKeyword = in_array($igTrigger, \App\Models\KeywordReply::IG_TRIGGERS_NO_KEYWORD, true);

            if ($body !== '' || $igNoKeyword) {
                // NATIVE mode: first give the ported Node IG flow engine a chance
                // to RESUME a parked flow session (a customer answering an Ask /
                // tapping a button). When Node reports the message was consumed we
                // MUST skip the keyword auto-reply so the customer never gets a
                // double reply — exactly how the WhatsApp flow path behaves.
                $consumedByFlow = false;
                if (! $igNoKeyword && (bool) \App\Models\SystemSetting::get('instagram_enabled', false)) {
                    try {
                        $igUserId = (string) data_get($data, 'account.id', '');
                        $igsid    = $igConvId !== '' ? (string) \Illuminate\Support\Str::afterLast($igConvId, ':') : '';
                        $account  = $igUserId !== '' ? \App\Models\InstagramAccount::where('ig_user_id', $igUserId)->first() : null;
                        if ($account && $igsid !== '') {
                            // Resolve a PUBLISHED IG flow bound to this account whose
                            // keyword matches — so a fresh message can START a flow,
                            // not only RESUME a parked one. Node picks resume vs start:
                            // an active session resumes (flow_data ignored); otherwise
                            // the passed flow_data starts a new run. Null flow = the
                            // resume-only behaviour we had before.
                            $startFlow = self::resolveIgKeywordFlow($account, $body);
                            $consumedByFlow = \App\Services\Instagram\IgFlowBridge::handoff(
                                $account,
                                $igsid,
                                $body,
                                $startFlow ? $startFlow->decoded_flow_data : null,
                                $startFlow?->id
                            );
                        }
                    } catch (\Throwable $e) {
                        Log::warning('[INSTAFLOW] IG flow handoff failed: ' . $e->getMessage(), ['convo' => $convo->id]);
                    }
                }

                if (! $consumedByFlow) {
                    try {
                        app(\App\Services\Inbox\KeywordReplyDispatcher::class)
                            ->maybeDispatch($convo->fresh() ?: $convo, $body, (string) $igConvId, null, $igTrigger);
                    } catch (\Throwable $e) {
                        Log::warning('[INSTAFLOW] keyword auto-reply failed: ' . $e->getMessage(), ['convo' => $convo->id]);
                    }
                }
            }
        }

        return $inbox;
    }

    /**
     * Find a PUBLISHED, active Instagram flow bound to this account whose
     * keyword Trigger matches the inbound text — the START path for native IG
     * flows (mirrors how a WhatsApp keyword flow fires). Returns null when
     * nothing matches, in which case the handoff falls back to resume-only.
     *
     * Keyword rules (same semantics as the WhatsApp keyword matcher):
     *   - trigger_keywords is a comma-separated list; ANY token may match.
     *   - a catch-all token ('any', '*', '.*') matches every message.
     *   - otherwise a case-insensitive "contains" test per token.
     */
    private static function resolveIgKeywordFlow(\App\Models\InstagramAccount $account, string $body): ?\App\Models\Flow
    {
        $text = mb_strtolower(trim($body));
        if ($text === '') return null;

        $flows = \App\Models\Flow::query()
            ->where('workspace_id', $account->workspace_id)
            ->where('flow_type', 'instagram')
            ->where('is_published', true)
            ->where('is_active', true)
            ->where('trigger_device_id', $account->id)
            ->orderByDesc('updated_at')
            ->get();

        foreach ($flows as $flow) {
            $raw = trim((string) $flow->trigger_keywords);
            if ($raw === '') continue;
            foreach (preg_split('/\s*,\s*/', mb_strtolower($raw)) as $kw) {
                $kw = trim($kw);
                if ($kw === '') continue;
                if (in_array($kw, ['any', '*', '.*', '.+'], true)) return $flow; // catch-all
                if (str_contains($text, $kw)) return $flow;
            }
        }
        return null;
    }

    /**
     * Instaflow ships interactive buttons as {title, url?}; the WaDesk inbox
     * renderer draws {text, url}. Return the normalised, non-empty list, or null
     * when there are none — so array_filter drops the key entirely and no empty
     * button bar renders.
     *
     * @return array<int, array{text:string, url?:string}>|null
     */
    private static function normalizeButtons($raw): ?array
    {
        if (! is_array($raw) || $raw === []) {
            return null;
        }
        $out = [];
        foreach ($raw as $b) {
            $text = (string) (data_get($b, 'title') ?? data_get($b, 'text') ?? '');
            if ($text === '') continue;
            $btn = ['text' => $text];
            $url = data_get($b, 'url');
            if (is_string($url) && $url !== '') $btn['url'] = $url;
            $out[] = $btn;
        }
        return $out !== [] ? $out : null;
    }
}
