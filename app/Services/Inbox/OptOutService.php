<?php

namespace App\Services\Inbox;

use App\Models\Contact;
use App\Models\Conversation;
use App\Models\InboxMessage;
use App\Models\WpCampaignContact;
use App\Services\InboxDispatcher;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * STOP / START handling for inbound customer replies.
 *
 * Every campaign and broadcast footer promises "reply STOP to unsubscribe",
 * and the SEND side has always honoured it — WaCampaignsController and
 * BroadcastsController both drop `contacts.is_unsubscribed` rows before
 * sending. What never existed was the CAPTURE side: nothing read an inbound
 * STOP, so the flag was only ever set by hand in the contacts UI. A customer
 * could reply STOP a hundred times and still receive the next campaign.
 * (`POST /api/campaigns/unsubscribe` was built for this and documented as
 * "Node detected a STOP/UNSUB keyword" — but nothing ever called it.)
 *
 * This service closes that loop for all three engines by running from the
 * inbound paths in Laravel rather than inside the Baileys bridge, which would
 * only have covered Unofficial.
 *
 * Scope: opting out stops BULK messaging (campaigns, broadcasts, scheduled).
 * It deliberately does NOT gag a human agent replying in Team Inbox, nor
 * order/OTP messages — STOP means "stop marketing me", not "never speak to
 * me again", and silently swallowing a support reply would be worse for the
 * customer than the marketing they opted out of. This matches how Twilio and
 * Meta define STOP.
 */
class OptOutService
{
    /**
     * Opt-out keywords. Matched against the WHOLE normalised message, never
     * as a substring — "stop by the shop tomorrow" and "I can't cancel my
     * order" are ordinary customer messages, and unsubscribing them would be
     * both wrong and invisible to the operator.
     */
    private const STOP_WORDS = [
        'stop', 'stop all', 'stopall',
        'unsubscribe', 'unsub',
        'cancel', 'end', 'quit',
        'optout', 'opt out', 'opt-out',
        'remove me', 'no more messages',
    ];

    /** Re-subscribe keywords. */
    private const START_WORDS = [
        'start', 'unstop', 'subscribe', 'resubscribe', 'resume',
        'optin', 'opt in', 'opt-in',
    ];

    /**
     * Inspect an inbound message and apply the opt-out / opt-in if it is one.
     *
     * Returns true when the message WAS an opt-out/opt-in instruction, so the
     * caller can stop processing it — a STOP must not also trip a keyword
     * auto-reply, drive a flow, or wake the AI agent. Answering "STOP" with a
     * chatbot menu is exactly the behaviour that gets numbers reported.
     */
    public function handle(Conversation $convo, ?string $body, ?string $fromPhone): bool
    {
        try {
            $intent = $this->classify($body);
            if ($intent === null) return false;

            $workspaceId = (int) ($convo->workspace_id ?? 0);
            $digits = preg_replace('/\D+/', '', (string) $fromPhone);
            if ($workspaceId === 0 || $digits === '') return false;

            $optingOut = $intent === 'stop';

            $contact = $this->resolveContact($workspaceId, $digits);
            if ($contact) {
                // Only write when it actually changes, so the Contact model's
                // opt-in (false→true) flow trigger fires once rather than on
                // every repeated STOP.
                if ((bool) $contact->is_unsubscribed !== $optingOut) {
                    $contact->is_unsubscribed = $optingOut;
                    // Timestamp only if the column is present. Writing a
                    // missing column would throw, the outer catch would
                    // swallow it, and the opt-out would be lost silently —
                    // which is the exact bug this service exists to fix. The
                    // flag is what stops the sending; the date is evidence.
                    if (Schema::hasColumn('contacts', 'unsubscribed_at')) {
                        $contact->unsubscribed_at = $optingOut ? now() : null;
                    }
                    $contact->save();
                }
            }

            // Pass the resolved contact id too. The pivot's own phone_number is
            // often EMPTY (campaigns built from a contact list write contact_id
            // and leave the denormalised phone blank), and matching on phone
            // alone silently skipped those rows — the contact was correctly
            // unsubscribed and future sends did skip them, but the campaign's
            // Opt-outs tab reported "nobody opted out", which reads like the
            // STOP was ignored.
            $this->syncCampaignRows($workspaceId, $digits, $optingOut, $contact?->id);

            Log::info('[OPT-OUT] ' . ($optingOut ? 'unsubscribed' : 'resubscribed'), [
                'workspace_id'    => $workspaceId,
                'conversation_id' => $convo->id,
                'contact_id'      => $contact?->id,
                'matched'         => $this->normalise($body),
            ]);

            $this->confirm($convo, $digits, $optingOut);

            return true;
        } catch (\Throwable $e) {
            // Never let opt-out bookkeeping break inbound processing — a
            // thrown exception here would drop the customer's message
            // entirely. Log and let the normal pipeline continue.
            Log::warning('[OPT-OUT] handling failed: ' . $e->getMessage(), [
                'conversation_id' => $convo->id ?? null,
            ]);
            return false;
        }
    }

    /** 'stop' | 'start' | null. */
    public function classify(?string $body): ?string
    {
        $text = $this->normalise($body);
        if ($text === '') return null;
        if (in_array($text, self::STOP_WORDS, true)) return 'stop';
        if (in_array($text, self::START_WORDS, true)) return 'start';
        return null;
    }

    /**
     * Lower-case, strip everything that isn't a letter/digit/space, collapse
     * whitespace. Customers send "STOP.", "stop!", "*STOP*" and "Stop 🙏" —
     * all of which are unambiguous opt-outs that a naive equality check on
     * the raw body would miss.
     */
    private function normalise(?string $body): string
    {
        $text = mb_strtolower(trim((string) $body));
        if ($text === '') return '';
        $text = preg_replace('/[^\p{L}\p{N}\s-]+/u', ' ', $text) ?? '';
        $text = preg_replace('/\s+/u', ' ', $text) ?? '';
        return trim($text);
    }

    /** Indexed lookup via mobile_hash — `contacts.mobile` is encrypted. */
    private function resolveContact(int $workspaceId, string $digits): ?Contact
    {
        $hash = Contact::hashPhone(null, $digits);
        if ($hash) {
            $hit = Contact::query()
                ->where('workspace_id', $workspaceId)
                ->where('mobile_hash', $hash)
                ->first();
            if ($hit) return $hit;
        }

        // Fall back to a decrypt-and-compare over the workspace's contacts.
        // The hash is built from a canonical form, so a contact saved with a
        // different country-code split than the inbound number carries won't
        // match on hash alone — and failing to find them would mean silently
        // ignoring a lawful opt-out.
        $last10 = substr($digits, -10);
        if ($last10 === '') return null;

        return Contact::query()
            ->where('workspace_id', $workspaceId)
            ->get(['id', 'workspace_id', 'mobile', 'country_code', 'is_unsubscribed'])
            ->first(function ($c) use ($digits, $last10) {
                $d = preg_replace('/\D+/', '', (string) ($c->country_code . $c->mobile))
                    ?: preg_replace('/\D+/', '', (string) $c->mobile);
                return $d !== '' && ($d === $digits || str_ends_with($d, $last10));
            });
    }

    /**
     * Mirror the state onto this workspace's campaign pivot rows so per-campaign
     * reporting shows the opt-out, matching what nodeUnsubscribe() writes.
     */
    private function syncCampaignRows(int $workspaceId, string $digits, bool $optingOut, ?int $contactId = null): void
    {
        $last10 = substr($digits, -10);

        // phone_number is encrypted, so it can't be matched in SQL. Bound the
        // scan to this workspace's campaigns rather than the whole table.
        WpCampaignContact::query()
            ->whereHas('campaign', fn ($q) => $q->where('workspace_id', $workspaceId))
            ->get()
            ->filter(function ($r) use ($digits, $last10, $contactId) {
                // contact_id FIRST. A campaign built from a contact list stores
                // contact_id but frequently leaves the denormalised
                // phone_number blank, so a phone-only match dropped exactly the
                // rows we most need to flag. The id is the reliable key when we
                // have it; the phone comparison below stays as the fallback for
                // rows created from a pasted number with no contact behind it.
                if ($contactId && (int) $r->contact_id === $contactId) {
                    return true;
                }
                $d = preg_replace('/\D+/', '', (string) $r->phone_number);
                return $d !== '' && ($d === $digits || ($last10 !== '' && str_ends_with($d, $last10)));
            })
            ->each(function ($r) use ($optingOut) {
                $r->update([
                    'is_unsubscribed' => $optingOut,
                    'unsubscribed'    => $optingOut,
                    'unsubscribed_at' => $optingOut ? now() : null,
                    // Don't rewrite a delivered/read row's status on opt-in —
                    // the send outcome is history and stays true.
                    'status'          => $optingOut ? 'unsubscribed' : $r->status,
                ]);
            });
    }

    /**
     * One confirmation back to the customer. Their STOP opens the 24-hour
     * customer-service window, so a free-form reply is allowed even on WABA.
     */
    private function confirm(Conversation $convo, string $digits, bool $optedOut): void
    {
        $text = $optedOut
            ? __("You've been unsubscribed and won't receive further promotional messages. Reply START to resubscribe.")
            : __("You're subscribed again and will receive our updates. Reply STOP at any time to unsubscribe.");

        try {
            $im = new InboxMessage();
            $im->conversation_id = $convo->id;
            $im->user_id         = $convo->user_id ?? null;
            $im->to_number       = $digits;
            $im->direction       = 'out';
            $im->status          = 'pending';
            $im->body            = $text;
            $im->meta            = ['source' => 'opt_out', 'opt_out' => $optedOut];
            $im->save();

            $result = app(InboxDispatcher::class)->send($im, (string) ($convo->platform ?: 'W'));
            $im->status = ($result['ok'] ?? false) ? 'sent' : 'failed';
            if (!empty($result['provider_id'])) $im->wa_message_id = $result['provider_id'];
            $im->save();
        } catch (\Throwable $e) {
            // The opt-out itself is already recorded — that is the part that
            // matters legally. A failed confirmation must not undo it.
            Log::warning('[OPT-OUT] confirmation send failed: ' . $e->getMessage(), [
                'conversation_id' => $convo->id,
            ]);
        }
    }
}
