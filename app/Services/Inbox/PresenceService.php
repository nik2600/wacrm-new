<?php

namespace App\Services\Inbox;

use Illuminate\Support\Facades\Cache;

/**
 * Cache-backed presence/typing tracker for the team inbox.
 *
 * Why cache and not Pusher/Reverb? The project ships with
 * `BROADCAST_CONNECTION=log` by default — there's no WebSocket
 * infrastructure assumed. The team-inbox queue() endpoint already
 * polls every ~5s, so we layer collision detection on top of that
 * existing poll: the client heartbeats while a conversation is open,
 * the server tracks freshness in cache, and queue() returns the
 * current viewers/typists for the active conversation.
 *
 * Two TTLs:
 *   VIEWING_TTL  — 30s. Client pings every 10s while open; entry
 *                  drops after 3 missed pings (tab closed / lost net).
 *   TYPING_TTL   —  5s. Client pings on each keystroke (debounced);
 *                  entry drops quickly so "is typing" disappears the
 *                  moment the operator stops.
 *
 * Storage shape (per conversation):
 *   inbox:presence:{conversationId} = [
 *     'viewers' => [userId => ['name'=>..,'avatar'=>..,'until'=>unix]],
 *     'typists' => [userId => ['name'=>..,'until'=>unix]],
 *   ]
 *
 * Reads filter out expired entries on the way out so a stale cache
 * row can't lie about presence.
 */
class PresenceService
{
    public const VIEWING_TTL = 30;
    public const TYPING_TTL  = 5;

    private static function key(int $conversationId): string
    {
        return "inbox:presence:{$conversationId}";
    }

    /**
     * Ping that user U is viewing conversation C. Optional `typing`
     * flag refreshes the short-lived typing entry alongside the
     * longer-lived viewing entry. Called from the client every 10s
     * while a conversation is open, and on every keystroke (typing).
     */
    public function ping(int $conversationId, int $userId, string $name, ?string $avatar = null, bool $typing = false): void
    {
        $store = $this->raw($conversationId);
        $now   = time();

        $store['viewers'][$userId] = [
            'name'   => $name,
            'avatar' => $avatar,
            'until'  => $now + self::VIEWING_TTL,
        ];
        if ($typing) {
            $store['typists'][$userId] = [
                'name'  => $name,
                'until' => $now + self::TYPING_TTL,
            ];
        } elseif (isset($store['typists'][$userId]) && $store['typists'][$userId]['until'] < $now) {
            unset($store['typists'][$userId]);
        }

        Cache::put(self::key($conversationId), $store, now()->addSeconds(self::VIEWING_TTL + 5));
    }

    /**
     * Explicitly drop user U from conversation C — fired by the
     * client's beforeunload + when switching to a different
     * conversation. Without this, the entry persists for up to
     * VIEWING_TTL even though the operator already left.
     */
    public function leave(int $conversationId, int $userId): void
    {
        $store = $this->raw($conversationId);
        unset($store['viewers'][$userId], $store['typists'][$userId]);
        if (empty($store['viewers']) && empty($store['typists'])) {
            Cache::forget(self::key($conversationId));
            return;
        }
        Cache::put(self::key($conversationId), $store, now()->addSeconds(self::VIEWING_TTL + 5));
    }

    /**
     * Snapshot of who's currently viewing/typing, with expired rows
     * filtered out. Excludes $selfUserId from the result so the
     * caller doesn't see themselves listed.
     *
     * @return array{viewers: array<int,array>, typists: array<int,array>}
     */
    public function snapshot(int $conversationId, ?int $selfUserId = null): array
    {
        $store = $this->raw($conversationId);
        $now   = time();
        $out   = ['viewers' => [], 'typists' => []];

        foreach (($store['viewers'] ?? []) as $uid => $entry) {
            if (($entry['until'] ?? 0) < $now)  continue;
            if ($selfUserId && (int) $uid === $selfUserId) continue;
            $out['viewers'][] = ['user_id' => (int) $uid] + $entry;
        }
        foreach (($store['typists'] ?? []) as $uid => $entry) {
            if (($entry['until'] ?? 0) < $now)  continue;
            if ($selfUserId && (int) $uid === $selfUserId) continue;
            $out['typists'][] = ['user_id' => (int) $uid] + $entry;
        }
        return $out;
    }

    private function raw(int $conversationId): array
    {
        $v = Cache::get(self::key($conversationId));
        return is_array($v) ? $v : ['viewers' => [], 'typists' => []];
    }
}
