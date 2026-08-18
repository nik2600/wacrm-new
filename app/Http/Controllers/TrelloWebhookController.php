<?php

namespace App\Http\Controllers;

use App\Models\Contact;
use App\Models\TrelloIntegration;
use App\Models\TrelloIntegrationLog;
use App\Services\Integrations\ContactResolver;
use App\Services\WhatsAppDispatcher;
use Illuminate\Http\Request;

/**
 * Inbound Trello board webhook.
 *
 *  - HEAD  → 200 (Trello's creation handshake — MUST return 200 or the
 *            webhook is not created).
 *  - POST  → verify X-Trello-Webhook (base64 HMAC-SHA1 of rawBody+callbackURL
 *            with the app secret), then act on the action.type. We always 200
 *            quickly and do the WhatsApp send in app()->terminating().
 */
class TrelloWebhookController extends Controller
{
    public function receive(Request $request)
    {
        if ($request->isMethod('head')) {
            return response('', 200);
        }

        $raw     = $request->getContent();
        $sig     = (string) $request->header('X-Trello-Webhook', '');
        $payload = json_decode($raw, true) ?: [];
        $action  = $payload['action'] ?? null;
        if (!is_array($action)) {
            return response('ignored', 200);
        }

        $boardId = (string) ($action['data']['board']['id'] ?? ($payload['model']['id'] ?? ''));
        $integration = $boardId
            ? TrelloIntegration::where('board_id', $boardId)->where('status', 'active')->first()
            : null;
        if (!$integration) {
            return response('no integration', 200);
        }

        // Verify the signature against the EXACT callbackURL we registered.
        $callbackUrl = url('/webhooks/trello');
        if (!$this->verify((string) $integration->api_secret, $raw, $callbackUrl, $sig)) {
            // Return 200 so Trello doesn't auto-disable the hook, but DON'T act.
            return response('bad signature', 200);
        }

        $type = (string) ($action['type'] ?? '');
        if (!in_array($type, $integration->enabledEvents(), true)) {
            return response('event off', 200);
        }

        // Idempotency — Trello retries a delivery (slow ack / transient
        // disconnect) with the SAME unique action.id. Without a guard, process()
        // would fire a duplicate WhatsApp message. Cache-gate per action.id for
        // 10 min (no migration needed; matches the project's no-scheduler
        // pattern). add() is atomic, so concurrent retries can't both pass.
        $actionId = (string) ($action['id'] ?? '');
        if ($actionId !== '' && !cache()->add('trello:act:' . $actionId, 1, now()->addMinutes(10))) {
            return response('duplicate', 200);
        }

        $wsId = (int) $integration->workspace_id;
        $iid  = (int) $integration->id;
        $uId  = (int) $integration->user_id;
        app()->terminating(function () use ($integration, $action, $type, $wsId, $iid, $uId) {
            $this->process($integration, $action, $type, $wsId, $iid, $uId);
        });

        return response('ok', 200);
    }

    private function process(TrelloIntegration $i, array $action, string $type, int $wsId, int $iid, int $uId): void
    {
        try {
            // Hot-path enforcement: honor the admin global toggle + the
            // workspace's plan flag even after connect.
            if (! \App\Models\SystemSetting::get('trello_enabled', true)) {
                TrelloIntegrationLog::create(['integration_id' => $iid, 'workspace_id' => $wsId, 'event' => $type, 'detail' => 'blocked: Trello disabled by admin', 'status' => 'error']);
                return;
            }
            $ws = \App\Models\Workspace::find($wsId);
            if ($ws && ! app(\App\Services\PlanLimitGuard::class)->hasFeature($ws, 'integration_trello')) {
                TrelloIntegrationLog::create(['integration_id' => $iid, 'workspace_id' => $wsId, 'event' => $type, 'detail' => 'blocked: plan lacks Trello', 'status' => 'error']);
                return;
            }

            $data  = $action['data'] ?? [];
            $card  = $data['card']['name'] ?? __('a card');
            $board = $i->board_name ?: ($data['board']['name'] ?? __('your board'));

            // (recipientNumber, messageBody)
            [$number, $body] = $this->buildNotification($i, $action, $type, $card, $board);
            if (!$number || !$body) {
                return; // nothing to send (e.g. unmatched member, or event with no configured recipient)
            }

            // Pin the operator-chosen sending channel (from_number + engine) so a
            // multi-device workspace notifies from the number it picked. Empty →
            // dispatcher resolves the workspace default (unchanged behaviour).
            $sender = \App\Services\Integrations\SenderResolver::fromKey($wsId, (string) $i->sendFrom());
            $params = [
                'to_number'    => $number,
                'body'         => $body,
                'workspace_id' => $wsId,
            ];
            if ($sender['from_number'] !== '') $params['from_number'] = $sender['from_number'];
            if ($sender['provider'] !== '')    $params['provider']    = $sender['provider'];

            $res = app(WhatsAppDispatcher::class)->sendRaw($params, $uId, 'W');

            $ok = (bool) ($res['ok'] ?? false) && empty($res['local_only']);
            TrelloIntegrationLog::create([
                'integration_id' => $iid, 'workspace_id' => $wsId,
                'event' => $type, 'detail' => substr($body, 0, 480), 'status' => $ok ? 'ok' : 'error',
            ]);
            TrelloIntegration::where('id', $iid)->update(['last_event_at' => now()]);
        } catch (\Throwable $e) {
            TrelloIntegrationLog::create([
                'integration_id' => $iid, 'workspace_id' => $wsId,
                'event' => 'error', 'detail' => substr($e->getMessage(), 0, 480), 'status' => 'error',
            ]);
        }
    }

    /** @return array{0: ?string, 1: ?string} [number, body] */
    private function buildNotification(TrelloIntegration $i, array $action, string $type, string $card, string $board): array
    {
        $data = $action['data'] ?? [];

        // Assignment is the core promise — notify the assigned member.
        if ($type === 'addMemberToCard') {
            $member = $data['member'] ?? ($action['member'] ?? []);
            return [
                $this->numberForMember($i, $member),
                "You've been assigned a Trello card: \"{$card}\" on {$board}.",
            ];
        }

        // Other events go to a configured fixed number (no spam by default).
        $recipient = $i->notify_mode === 'fixed' ? preg_replace('/\D+/', '', (string) $i->notify_number) : '';
        if ($recipient === '') {
            return [null, null];
        }

        $body = match ($type) {
            'createCard' => "New Trello card added: \"{$card}\" on {$board}.",
            'deleteCard' => "A Trello card was deleted on {$board}.",
            'updateCard' => $this->updateMessage($data, $card, $board),
            default      => "Trello update on {$board}.",
        };

        return [$recipient, $body];
    }

    private function updateMessage(array $data, string $card, string $board): string
    {
        $old = $data['old'] ?? [];
        if (isset($data['listAfter']['name'])) {
            $from = $data['listBefore']['name'] ?? '?';
            $to   = $data['listAfter']['name'];
            return "Card \"{$card}\" moved from {$from} to {$to} on {$board}.";
        }
        if (array_key_exists('closed', $old)) {
            $closed = (bool) ($data['card']['closed'] ?? false);
            return $closed ? "Card \"{$card}\" was archived on {$board}." : "Card \"{$card}\" was restored on {$board}.";
        }
        if (array_key_exists('due', $old)) {
            return "Due date updated on card \"{$card}\" ({$board}).";
        }
        if (array_key_exists('name', $old)) {
            return "A card was renamed to \"{$card}\" on {$board}.";
        }
        return "Card \"{$card}\" was updated on {$board}.";
    }

    /** Map a Trello member → WhatsApp number (manual override first, then by name). */
    private function numberForMember(TrelloIntegration $i, array $member): ?string
    {
        $map = is_array($i->member_map) ? $i->member_map : [];
        $mid = (string) ($member['id'] ?? '');
        if ($mid !== '' && !empty($map[$mid])) {
            $c = Contact::where('id', (int) $map[$mid])->where('workspace_id', $i->workspace_id)->first();
            if ($c && $c->mobile) {
                $num = preg_replace('/\D+/', '', (string) $c->mobile);
                if ($num !== '') return $num;
            }
        }
        $name = (string) ($member['fullName'] ?? $member['username'] ?? '');
        if ($name === '') return null;
        $r = app(ContactResolver::class)->resolve((int) $i->workspace_id, $name);
        return $r['number'] ?? null;
    }

    private function verify(string $secret, string $raw, string $callbackUrl, string $sig): bool
    {
        if ($secret === '' || $sig === '') return false;
        $calc = base64_encode(hash_hmac('sha1', $raw . $callbackUrl, $secret, true));
        return hash_equals($calc, $sig);
    }
}
