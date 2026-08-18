<?php

namespace App\Services\Inbox;

use App\Models\Conversation;
use App\Models\InboxMessage;
use Illuminate\Support\Facades\Log;

/**
 * Mirror OUTBOUND bulk sends (broadcasts / campaigns / scheduled) into the
 * team-inbox conversation thread — but ONLY when a conversation already
 * exists for that recipient in the workspace.
 *
 * Why the guard: a broadcast can hit thousands of contacts. Creating a
 * conversation row per recipient would flood the team inbox with junk. But
 * when the recipient is ALREADY an open thread (they replied earlier, an
 * agent is chatting with them), the template/broadcast we just sent them
 * should appear in that thread so the agent sees the full history instead of
 * a confusing gap (agent's reply, then the customer's answer, but the message
 * they were answering is invisible). That was the "we sent a template but it's
 * not showing in team inbox" report.
 */
class InboxMirror
{
    /**
     * Append an outbound message to an EXISTING conversation for $toPhone in
     * $workspaceId. No-op (returns null) when no conversation exists, so bulk
     * sends to cold contacts never spawn inbox threads. Best-effort: any
     * failure is logged and swallowed so it never breaks the send.
     *
     * @param  string       $body         Human-readable message text for the bubble.
     * @param  string|null  $waMessageId  wamid if already known (lets status ticks match).
     * @param  string|null  $provider     'waba' | 'baileys' | 'twilio'.
     * @param  array        $meta         Extra meta (e.g. type:'template', template_name).
     *                                    Pass allow_create=true ONLY from a genuine
     *                                    1:1 send to open a thread when none exists;
     *                                    bulk callers must leave it unset.
     * @return int|null     Created InboxMessage id, or null when skipped.
     */
    public function appendOutboundToOpenConversation(
        int $workspaceId,
        string $toPhone,
        string $body,
        ?string $waMessageId = null,
        ?string $provider = null,
        array $meta = []
    ): ?int {
        try {
            if ($workspaceId <= 0) return null;
            $digits = preg_replace('/\D+/', '', $toPhone);
            if ($digits === '') return null;

            $conv = self::findConversation($workspaceId, $toPhone);

            // No thread yet → do NOT create one for a bulk send. A 100k
            // campaign would otherwise write 100k conversation rows, burying
            // real replies and growing the table by the size of every contact
            // list. Suppressing the unread badge (the previous mitigation) hid
            // the noise but still wrote every row.
            //
            // This also matches WhatsApp's own model: a template send does NOT
            // open the 24h customer-service window, so an agent cannot type in
            // such a thread anyway. The reply is what makes a conversation
            // real, and the inbound path creates it there.
            //
            // A genuine 1:1 send (not a bulk tool) can opt in with
            // $meta['allow_create'] = true — it stays off by default so no
            // bulk caller can regress into flooding the inbox.
            if (!$conv) {
                if (empty($meta['allow_create'])) return null;
                $conv = $this->openConversation($workspaceId, $toPhone, $digits, $provider, $meta);
                if (!$conv) return null;
            }

            $mergedMeta = array_merge(['source' => 'bulk'], $meta);
            if ($waMessageId) $mergedMeta['wa_message_id'] = $waMessageId;

            // De-dupe on the provider's own message id. The three engines
            // report a send back through different routes — WABA mirrors
            // inline here, while Unofficial/Twilio round-trip through Node,
            // which also writes a Message (and the Message observer mirrors
            // that). Without this guard the same send would appear twice in
            // the thread on those engines. Keyed on wamid because that is the
            // one identifier every engine agrees on.
            if ($waMessageId) {
                $already = InboxMessage::where('conversation_id', $conv->id)
                    ->where('meta->wa_message_id', $waMessageId)
                    ->exists();
                if ($already) return null;
            }

            // Second de-dupe key, independent of the provider id. The send loop
            // mirrors the instant a message goes out — at which point the
            // provider id may still be null — and the status callback mirrors
            // again later WITH an id. Matching on wamid alone would let those
            // two produce a duplicate bubble. src_key is deterministic per
            // (campaign|broadcast, contact), so whichever arrives first wins.
            $srcKey = $mergedMeta['src_key'] ?? null;
            if ($srcKey) {
                $seen = InboxMessage::where('conversation_id', $conv->id)
                    ->where('meta->src_key', $srcKey)
                    ->exists();
                if ($seen) return null;
            }

            $msg = InboxMessage::create([
                'conversation_id' => $conv->id,
                'direction'       => 'out',
                'to_number'       => $digits,
                'body'            => $body,
                'status'          => $waMessageId ? 'sent' : 'pending',
                'provider'        => $provider ?: ($conv->provider ?: null),
                'meta'            => $mergedMeta,
            ]);

            // Deliberately does NOT touch unread_count. Outbound messages are
            // not unread — only the customer's replies are. This is what keeps
            // a 5,000-recipient campaign from turning the inbox into 5,000
            // bold rows demanding a response.
            $conv->forceFill([
                'last_message_at'  => now(),
                'last_outbound_at' => now(),
                'preview'          => mb_substr($body, 0, 200),
            ])->save();

            return $msg->id;
        } catch (\Throwable $e) {
            Log::warning('[InboxMirror] append failed', [
                'workspace_id' => $workspaceId,
                'to'           => $toPhone,
                'error'        => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * The single conversation for a phone number in a workspace, whichever
     * surface created it.
     *
     * There is deliberately ONE thread per (workspace, number). Before this,
     * /chat and the team inbox each looked up conversations their own way and
     * each created their own row, so number 919521881152 ended up with
     * conversation #12 (origin=inbox) AND #13 (origin=chat) — the operator saw
     * the same customer twice, with the history split between them.
     *
     * Matches on raw_jid / alt_jid in both bare-digit and JID form, and
     * ignores `origin` entirely — origin records where a thread STARTED, it
     * must never partition the lookup. Oldest wins so the thread with the
     * real history is the survivor.
     */
    public static function findConversation(int $workspaceId, string $phone): ?Conversation
    {
        // Delegates to ConversationResolver, which every other create path now
        // shares. This method's own matching was already the correct one — it
        // is kept as a named entry point so existing callers keep working.
        return ConversationResolver::find($workspaceId, $phone);
    }

    /**
     * Create the thread for a number that has never been messaged before.
     * `origin` records provenance only — it is not a partition key.
     */
    private function openConversation(
        int $workspaceId,
        string $toPhone,
        string $digits,
        ?string $provider,
        array $meta
    ): ?Conversation {
        // Re-check under the same normalisation the finder uses: two campaign
        // workers hitting the same number concurrently must not both create.
        $existing = self::findConversation($workspaceId, $digits);
        if ($existing) return $existing;

        // DO NOT "optimise" this into a SQL WHERE on `mobile`. That column is
        // ENCRYPTED at rest (Laravel encrypted cast — the raw row holds an
        // eyJpdiI6… payload), so no LIKE/= can ever match it; the model only
        // decrypts on read. Filtering in PHP after hydrating is the only way to
        // match a number, and a SQL predicate here would silently find nothing
        // and title every new thread with a bare phone number instead of the
        // saved contact name.
        //
        // The cost is bounded because this runs only when a thread is being
        // opened — bulk sends now return before reaching here (see the
        // allow_create guard above), so it is no longer per-recipient.
        $contact = \App\Models\Contact::query()
            ->where('workspace_id', $workspaceId)
            ->get(['id', 'name', 'mobile'])
            ->first(fn ($c) => preg_replace('/\D+/', '', (string) $c->mobile) === $digits);

        return Conversation::create([
            'workspace_id'     => $workspaceId,
            'raw_jid'          => $digits,
            'title'            => $contact?->name ?: $digits,
            'origin'           => $meta['source'] ?? 'bulk',
            'provider'         => $provider,
            'status'           => 'open',
            'inbox_status'     => 'open',
            'channel'          => 'whatsapp',
            'last_message_at'  => now(),
            'last_outbound_at' => now(),
            // Never unread on creation — nobody has written to US yet.
            'unread_count'     => 0,
        ]);
    }

    /**
     * Compose a readable bubble body from a TemplateDataBuilder array
     * (header + body + footer), so the thread shows the same text the
     * customer saw on WhatsApp rather than raw {{1}} placeholders.
     */
    public static function readableTemplateBody(?array $templateData): string
    {
        if (!$templateData) return '';
        $parts = array_filter([
            trim((string) ($templateData['header'] ?? $templateData['title_text'] ?? '')),
            trim((string) ($templateData['template_body'] ?? '')),
            trim((string) ($templateData['footer'] ?? '')),
        ], fn ($s) => $s !== '');
        return implode("\n\n", $parts);
    }
}
