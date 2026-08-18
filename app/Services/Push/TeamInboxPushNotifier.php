<?php

namespace App\Services\Push;

use App\Models\Conversation;
use App\Models\InboxMessage;
use App\Models\PushSubscription;
use App\Models\SystemSetting;

/**
 * Turns a newly-received inbound team-inbox message into a Web-Push to the
 * right agents' devices, so the inbox rings even when the app is closed.
 *
 * Recipients:
 *   - the ASSIGNED agent, if the conversation is assigned;
 *   - otherwise every agent in the workspace who OPTED IN (has a team-inbox
 *     push subscription).
 *
 * Entirely best-effort + no-op when the feature is off / not configured, so it
 * can be called from the InboxMessage `created` hook without risk.
 */
class TeamInboxPushNotifier
{
    public function __construct(private readonly WebPushService $push) {}

    public function notifyInbound(InboxMessage $m): void
    {
        if (($m->direction ?? '') !== 'in') return;
        if (!SystemSetting::get('ti_pwa_enabled', false)) return;
        if (!$this->push->isConfigured()) return;

        $conv = Conversation::query()->find($m->conversation_id);
        if (!$conv || empty($conv->workspace_id)) return;

        $userIds = !empty($conv->assignee_user_id)
            ? [(int) $conv->assignee_user_id]
            : PushSubscription::query()
                ->where('workspace_id', $conv->workspace_id)
                ->where('channel', 'team-inbox')
                ->distinct()->pluck('user_id')->map(fn ($v) => (int) $v)->all();

        $userIds = array_values(array_unique(array_filter($userIds)));
        if (empty($userIds)) return;

        // Conversation `title` is the queue label the operators see (contact name
        // or number, SafeEncrypted — decrypts on read). It's the agent's OWN
        // inbox, so no masking. `contact_digits` is a phone-number fallback.
        $title = trim((string) ($conv->title ?? ''));
        if ($title === '') $title = trim((string) ($conv->contact_digits ?? ''));
        if ($title === '') $title = 'New message';

        $icon = (string) (SystemSetting::get('ti_pwa_icon_192') ?: SystemSetting::get('pwa_icon_192') ?: '');

        $payload = [
            'title' => $title,
            'body'  => $this->preview($m),
            // The SW navigates the open inbox tab here (or opens one).
            'url'   => '/team-inbox?c=' . $conv->id,
            'tag'   => 'ti-conv-' . $conv->id,
            'icon'  => $icon !== '' ? $icon : null,
        ];

        foreach ($userIds as $uid) {
            $this->push->sendToUser($uid, $payload);
        }
    }

    /** Short body preview — the customer's own text, else a neutral label. */
    private function preview(InboxMessage $m): string
    {
        $body = trim((string) ($m->body ?? ''));
        return $body !== '' ? mb_substr($body, 0, 140) : 'Sent a message';
    }
}
