<?php

namespace App\Services\Inbox;

use App\Models\Conversation;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * ONE THREAD PER NUMBER. The single place that answers "which conversation does
 * this phone number belong to?" — every inbound, outbound, webhook, catalog,
 * quick-send, extension and mobile-app path must go through here.
 *
 * ----------------------------------------------------------------------------
 * THE TWO RULES, AND WHY
 * ----------------------------------------------------------------------------
 *
 * 1. MATCH ON THE DIGITS, NEVER ON THE STRING.
 *    The same customer was stored as '919…', '919…@s.whatsapp.net' and
 *    '919…@lid' by different writers. Every lookup used raw string equality, so
 *    a thread written in one shape was invisible to code searching in another
 *    and a duplicate was created. `contact_digits` normalises all of them.
 *
 * 2. NOTHING ELSE MAY PARTITION THE LOOKUP — not `origin`, not `provider`,
 *    not `device_id`.
 *    - `origin` records where a thread STARTED ('inbox', 'chat', 'quick',
 *      'bulk'). Lookups each filtered on a different subset of those values, so
 *      a Quick Send thread ('quick') was invisible to the WhatsApp webhook
 *      (which only looked at 'inbox'/'chatbot') and both existed at once.
 *    - `provider` / `device_id` used to keep engines apart. They no longer do:
 *      the SAME customer number reaching a workspace on WABA and on the
 *      Unofficial API is ONE person and must be ONE thread. The conversation's
 *      provider/device_id follow the most recent message (see stampChannel), so
 *      a reply still goes back out over the channel last used.
 *
 * Anything that needs to distinguish channels does it on the MESSAGE, not by
 * splitting the thread.
 */
class ConversationResolver
{
    /**
     * Normalise any phone / JID / LID into the identity used for matching.
     *
     * Returns null when the value carries no usable identity (widget visitors,
     * empty strings), so callers can tell "no identity" from "some identity".
     */
    public static function digitsFor(?string $jidOrPhone): ?string
    {
        $v = trim((string) $jidOrPhone);
        if ($v === '' || str_starts_with($v, 'widget-')) {
            return null;
        }

        // Namespace groups so a group id can never collide with a phone number.
        $isGroup = str_contains($v, '@g.us');

        // Drop the '@…' suffix BEFORE stripping non-digits, otherwise
        // 's.whatsapp.net' would contribute nothing but '@lid' vs '@g.us'
        // would become indistinguishable.
        $local  = str_contains($v, '@') ? strstr($v, '@', true) : $v;
        $digits = (string) preg_replace('/\D+/', '', $local);

        if ($digits === '') {
            return null;
        }

        return $isGroup ? 'g:' . $digits : $digits;
    }

    /**
     * Every legacy string shape a number could have been stored as, for rows
     * written before `contact_digits` existed (or by a path that bypassed the
     * model). Keeps the resolver correct on un-backfilled data.
     *
     * @return array<int, string>
     */
    public static function legacyShapes(string $digits): array
    {
        return [
            $digits,
            $digits . '@s.whatsapp.net',
            $digits . '@c.us',
            $digits . '@lid',
        ];
    }

    /**
     * Find the ONE conversation for this number in this workspace.
     *
     * Oldest wins: the lowest id is the thread carrying the real history, and
     * keeping it stable means deal links, audit rows and bookmarks stay valid.
     */
    public static function find(int $workspaceId, ?string $jidOrPhone): ?Conversation
    {
        $digits = self::digitsFor($jidOrPhone);
        if ($workspaceId <= 0 || $digits === null) {
            return null;
        }

        return self::matchQuery($workspaceId, $digits)->orderBy('id')->first();
    }

    /**
     * Same as find(), but takes a row lock so two concurrent webhooks for the
     * same number cannot both miss and both create. Call inside a transaction.
     */
    public static function findForUpdate(int $workspaceId, ?string $jidOrPhone): ?Conversation
    {
        $digits = self::digitsFor($jidOrPhone);
        if ($workspaceId <= 0 || $digits === null) {
            return null;
        }

        return self::matchQuery($workspaceId, $digits)->orderBy('id')->lockForUpdate()->first();
    }

    /**
     * Find, or create with $attributes. Race-safe: the existence check is
     * re-run under a row lock inside the transaction, so a burst of concurrent
     * messages for a new number yields exactly one thread.
     *
     * @param  array<string, mixed>  $attributes  used only when creating
     */
    public static function findOrCreate(int $workspaceId, ?string $jidOrPhone, array $attributes = []): ?Conversation
    {
        $digits = self::digitsFor($jidOrPhone);
        if ($workspaceId <= 0 || $digits === null) {
            return null;
        }

        // Fast path — no transaction for the overwhelmingly common case of an
        // existing thread.
        if ($found = self::find($workspaceId, $jidOrPhone)) {
            return $found;
        }

        return DB::transaction(function () use ($workspaceId, $jidOrPhone, $digits, $attributes) {
            if ($found = self::findForUpdate($workspaceId, $jidOrPhone)) {
                return $found;
            }

            return Conversation::create(array_merge([
                'workspace_id'    => $workspaceId,
                'raw_jid'         => (string) $jidOrPhone,
                // `title` is NOT NULL with no DB default — a caller that omits
                // it would fatal on insert. Fall back to the saved contact name
                // for this number, then the bare number, so the queue never
                // shows a blank row.
                'title'           => self::defaultTitle($workspaceId, $digits),
                'origin'          => 'inbox',
                'status'          => 'pending',
                'inbox_status'    => 'open',
                'last_message_at' => now(),
            ], $attributes, [
                // Always authoritative — never let a caller pass a stale value.
                'workspace_id'   => $workspaceId,
                'contact_digits' => $digits,
            ]));
        });
    }

    /**
     * A display label for a brand-new thread.
     *
     * `Contact.mobile` is encrypted at rest, so it cannot be matched with a SQL
     * WHERE — the rows have to be hydrated and compared in PHP. That is only
     * acceptable because this runs exclusively when a thread is being OPENED,
     * never on the per-message hot path.
     */
    private static function defaultTitle(int $workspaceId, string $digits): string
    {
        try {
            $name = \App\Models\Contact::nameForPhone($workspaceId, $digits);
            if ($name) {
                return $name . ' · +' . $digits;
            }
        } catch (\Throwable $e) {
            // Never let a contact-lookup problem block opening the thread.
        }

        return '+' . $digits;
    }

    /**
     * Point the thread at the channel that most recently carried a message, so
     * an operator reply goes back the way the customer came in.
     *
     * This is what makes rule 2 safe: threads are no longer split per engine,
     * so the thread itself has to remember which engine is current. Only moves
     * forward on real traffic — never on a passive read.
     */
    public static function stampChannel(Conversation $convo, ?string $provider, $deviceId = null): void
    {
        $changes = [];

        if ($provider && (string) $convo->provider !== (string) $provider) {
            $changes['provider'] = $provider;
        }
        if ($deviceId && (int) $convo->device_id !== (int) $deviceId) {
            $changes['device_id'] = (int) $deviceId;
        }

        if ($changes) {
            $convo->forceFill($changes)->save();
        }
    }

    /**
     * Learn a JID shape we had not seen for this thread. Keeps `alt_jid`
     * carrying the OTHER identifier (typically the @lid twin of a phone), which
     * is what lets a LID-only inbound resolve to the same thread.
     */
    public static function rememberJid(Conversation $convo, ?string $jid): void
    {
        $jid = trim((string) $jid);
        if ($jid === '') {
            return;
        }

        $known = array_filter([(string) $convo->raw_jid, (string) $convo->alt_jid]);
        if (in_array($jid, $known, true)) {
            return;
        }

        // Prefer the phone form on raw_jid — outbound routing reads it.
        if ((string) $convo->raw_jid === '') {
            $convo->forceFill(['raw_jid' => $jid])->save();
        } elseif ((string) $convo->alt_jid === '') {
            $convo->forceFill(['alt_jid' => $jid])->save();
        }
    }

    /**
     * The match predicate. `contact_digits` is the indexed fast path; the
     * legacy string legs cover rows written before the column existed and are
     * harmless once every row is backfilled.
     *
     * Widget threads are excluded outright — they are keyed by visitor id, not
     * by a phone number, and must never be reachable by a numeric lookup.
     */
    private static function matchQuery(int $workspaceId, string $digits): Builder
    {
        $shapes = self::legacyShapes($digits);

        return Conversation::query()
            ->where('workspace_id', $workspaceId)
            ->whereNotIn('channel', Conversation::ENGINE_AGNOSTIC_CHANNELS)
            ->where(function (Builder $q) use ($digits, $shapes) {
                $q->where('contact_digits', $digits)
                  ->orWhereIn('raw_jid', $shapes)
                  ->orWhereIn('alt_jid', $shapes);
            });
    }
}
