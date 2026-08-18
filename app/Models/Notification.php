<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Notification extends Model
{
    use HasFactory;

    protected $table = 'notifications';

    protected $fillable = [
        'user_id', 'workspace_id', 'category', 'severity', 'icon',
        'notification_title', 'notification_msg',
        'source_type', 'source_id', 'verb',
        'action_url', 'is_urgent', 'status', 'read_at',
    ];

    protected $casts = [
        'notification_title' => 'encrypted',
        'notification_msg'   => 'encrypted',
        'status'             => 'boolean',
        'is_urgent'          => 'boolean',
        'read_at'            => 'datetime',
    ];

    public function scopeForUser($q, ?int $userId)
    {
        return $q->where(function ($w) use ($userId) {
            $w->where('user_id', $userId)->orWhereNull('user_id');
        });
    }

    /**
     * Notifications visible to the current operator. Two buckets only:
     *
     *   1. Workspace-shared events for THIS workspace (workspace_id
     *      matches) — every member of the workspace sees them.
     *   2. Personal notifications addressed directly to this user
     *      (user_id matches) regardless of which workspace stamped
     *      them — covers "your password was changed" etc.
     *
     * NULL workspace_id is no longer a fallback bucket. Real platform
     * announcements (admin broadcasts) live in the dedicated
     * `announcements` table — the `notifications` table is strictly
     * per-workspace operator activity. Rows with NULL workspace_id
     * are legacy data that pre-dated multi-tenant; we don't want them
     * leaking to every new workspace.
     *
     * NotificationHelper::toUser() now auto-stamps workspace_id at
     * create time, so going forward this scope's strict workspace
     * match catches every legitimate notification.
     */
    public function scopeForCurrentWorkspace($q)
    {
        $user = auth()->user();
        if (!$user) return $q->whereRaw('1=0');
        $uId  = (int) $user->id;
        $wsId = (int) ($user->current_workspace_id ?? 0);
        return $q->where(function ($qq) use ($wsId, $uId) {
            $qq->where('workspace_id', $wsId)
               ->orWhere(function ($personal) use ($uId, $wsId) {
                   // Personal notification — pinned to this user
                   // regardless of workspace. Tightened with
                   // workspace_id IS NULL to prevent legacy cross-
                   // tenant rows from leaking just because they have
                   // the matching user_id stamp.
                   $personal->where('user_id', $uId)->whereNull('workspace_id');
               });
        });
    }

    public function scopeUnread($q) { return $q->where('status', true); }
    public function scopeRead($q)   { return $q->where('status', false); }
    public function scopeUrgent($q) { return $q->where('is_urgent', true); }
    public function scopeCategory($q, string $cat) { return $cat === 'all' ? $q : $q->where('category', $cat); }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
