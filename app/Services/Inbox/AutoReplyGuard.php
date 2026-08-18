<?php

namespace App\Services\Inbox;

use App\Models\Conversation;

/**
 * Auto-reply gate for the routing engine + keyword dispatcher paths.
 *
 * DISABLED by request — auto-replies fire on EVERY inbound message.
 *
 * The old version imposed a hardcoded 12-hour per-contact cooldown (plus a
 * flood/spam gate). Those hardcoded defaults OVERRODE each auto-reply rule's
 * OWN cooldown + reply-delay settings and silently swallowed repeat messages
 * (a customer sending "hi" again got nothing for 12h).
 *
 * The per-rule cooldown (`keyword_replies.cooldown`, in seconds) and the
 * per-rule reply delay (`keyword_replies.timeout`) are still enforced where
 * they belong — in KeywordReplyDispatcher — so removing this blanket gate is
 * exactly what lets THOSE rule settings actually govern.
 */
class AutoReplyGuard
{
    /**
     * Always allow. Per-rule cooldown + delay are enforced by the dispatcher,
     * so this blanket workspace-level gate is intentionally a no-op.
     */
    public function canAutoReply(Conversation $conv): bool
    {
        return true;
    }

    /**
     * Stamp the last auto-reply time on the conversation. No longer gates
     * anything (kept so existing callers keep working); the value is only
     * surfaced for display/diagnostics now.
     */
    public function markReplied(Conversation $conv): void
    {
        $meta = is_array($conv->routing_meta) ? $conv->routing_meta : [];
        $meta['last_auto_reply_at'] = now()->toIso8601String();
        $conv->forceFill(['routing_meta' => $meta])->save();
    }
}
