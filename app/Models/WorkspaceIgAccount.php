<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A workspace's link to an Instagram account that lives on the connected
 * Instaflow install. This is a MIRROR only — the real account (token, webhooks,
 * Graph engine) stays on Instaflow. WaDesk keeps the Instaflow account id plus a
 * cached profile snapshot so /devices can render it as a channel and later
 * phases can offer it as a sender (WorkspaceEngine keys it `instagram:<id>`).
 *
 * `instaflow_account_id` is the Instaflow InstagramAccount id (kept as a string
 * so its id namespace never collides with local device / provider config ids).
 */
class WorkspaceIgAccount extends Model
{
    protected $fillable = [
        'workspace_id',
        'instaflow_account_id',
        'username',
        'name',
        'avatar',
        'status',
        'followers',
        'synced_at',
    ];

    protected $casts = [
        'followers' => 'integer',
        'synced_at' => 'datetime',
    ];

    public function scopeForWorkspace($q, int $workspaceId)
    {
        return $q->where('workspace_id', $workspaceId);
    }

    /** Only rows that are still connected (status left NULL counts as connected). */
    public function scopeConnected($q)
    {
        return $q->where(function ($qq) {
            $qq->whereNull('status')->orWhere('status', '!=', 'disconnected');
        });
    }

    /**
     * The single source of truth for "does this workspace have a live Instagram
     * account?". Every Instagram surface — dashboard/analytics cards, the inbox
     * queue, flows, templates and auto-reply — keys off this, so Instagram
     * disappears the moment EITHER:
     *   • an admin cuts the Instaflow connection at /admin/extensions
     *     (Connect Instagram → Disconnect), which flips instaflow_connected off, OR
     *   • the workspace's last Instagram account is unlinked on /devices.
     * Both the platform link AND a connected mirror row must be present. The
     * Instaflow check reads a cached settings flag (no network call), so this is
     * cheap enough to call on every dashboard / inbox render.
     */
    public static function hasConnected(?int $workspaceId): bool
    {
        if (!$workspaceId) return false;
        if (!\App\Services\Instaflow\InstaflowClient::fromSettings()->isConnected()) {
            return false;
        }
        return static::query()->forWorkspace($workspaceId)->connected()->exists();
    }

    /** The @handle for display, without a leading @. */
    public function handle(): string
    {
        return ltrim((string) ($this->username ?: $this->name), '@');
    }
}
