<?php

namespace App\Services\Integrations;

use App\Services\WorkspaceEngine;

/**
 * Resolves an integration's chosen "send from" channel (a WorkspaceEngine
 * sender key, "engine:id") into the concrete from_number + provider the
 * WhatsAppDispatcher pins a send to.
 *
 * Used by the Slack + Trello webhooks so a multi-device / multi-engine
 * workspace can pick WHICH connected number the integration sends through,
 * instead of always falling back to the workspace's primary device.
 *
 * Robust by design: senders() only lists CONNECTED senders, so a key whose
 * device was later removed / disconnected simply won't match and we return
 * empty strings — the dispatcher then resolves the workspace default exactly
 * as before. Never throws.
 */
class SenderResolver
{
    /**
     * @return array{from_number: string, provider: string}
     *   Both empty when no/invalid key → dispatcher uses the workspace default.
     */
    public static function fromKey(?int $workspaceId, ?string $key): array
    {
        $blank = ['from_number' => '', 'provider' => ''];
        $key   = trim((string) $key);
        if ($key === '' || !$workspaceId) {
            return $blank;
        }

        try {
            $sender = WorkspaceEngine::senders($workspaceId)->firstWhere('key', $key);
        } catch (\Throwable $e) {
            return $blank;
        }
        if (!$sender) {
            return $blank;
        }

        return [
            'from_number' => (string) ($sender['phone']  ?? ''),
            'provider'    => (string) ($sender['engine'] ?? ''),
        ];
    }

    /**
     * Merge a chosen sender key into an integration's metadata array without
     * clobbering its other keys. Returns the new metadata array to persist.
     */
    public static function stampMetadata(?array $metadata, ?string $key): array
    {
        $md = is_array($metadata) ? $metadata : [];
        $key = trim((string) $key);
        if ($key === '') {
            unset($md['send_from']);   // "workspace default" clears the pin
        } else {
            $md['send_from'] = $key;
        }
        return $md;
    }
}
