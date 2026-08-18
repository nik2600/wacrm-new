<?php

namespace App\Services;

use App\Models\AdminAiKey;
use App\Models\AiProviderKey;
use App\Models\Workspace;

/**
 * Picks the AI key to use for a given workspace + provider.
 *
 * Resolution order:
 *   1. If the workspace's plan grants BYOK AND the workspace has its
 *      own active key for this provider → user key wins.
 *   2. Otherwise → admin's global key (admin_ai_keys, is_active=true).
 *   3. Neither → null. Caller should surface a clear error.
 *
 * The resolver also reports WHICH source was used so AiTokenMeter
 * can bill against the right side.
 */
class AiKeyResolver
{
    /**
     * @return array{key: ?string, source: string, model: ?string}
     *   source ∈ {'workspace', 'admin', 'none'}
     */
    public static function resolve(?Workspace $workspace, string $provider): array
    {
        $provider = strtolower(trim($provider));

        if ($workspace) {
            $allowsByok = (bool) $workspace->effectiveLimit('allow_byok_ai_keys', false);
            // Platform-admin-owned workspaces are never plan-gated for BYOK —
            // the install owner can use their own key regardless of plan. Keeps
            // the resolver consistent with the settings page (which also lets
            // admins save a key without the BYOK plan flag).
            if (!$allowsByok) {
                $allowsByok = self::workspaceOwnedByAdmin($workspace);
            }
            if ($allowsByok) {
                $userKey = AiProviderKey::keyFor($workspace->id, $provider);
                if (!empty($userKey)) {
                    return ['key' => $userKey, 'source' => 'workspace', 'model' => null];
                }
            }
        }

        $admin = AdminAiKey::activeFor($provider);
        if ($admin && !empty($admin->api_key)) {
            return [
                'key'    => $admin->api_key,
                'source' => 'admin',
                'model'  => $admin->default_model,
            ];
        }

        return ['key' => null, 'source' => 'none', 'model' => null];
    }

    /**
     * Convenience: just the key string, or null if nothing resolves.
     */
    public static function keyFor(?Workspace $workspace, string $provider): ?string
    {
        return self::resolve($workspace, $provider)['key'];
    }

    /** Is the workspace owned by a platform admin? Cached per request. */
    private static function workspaceOwnedByAdmin(Workspace $workspace): bool
    {
        $ownerId = (int) ($workspace->owner_user_id ?? 0);
        if ($ownerId <= 0) return false;
        static $cache = [];
        if (array_key_exists($ownerId, $cache)) return $cache[$ownerId];
        try {
            $owner = \App\Models\User::find($ownerId);
            return $cache[$ownerId] = (bool) ($owner && $owner->isAdmin());
        } catch (\Throwable) {
            return $cache[$ownerId] = false;
        }
    }
}
