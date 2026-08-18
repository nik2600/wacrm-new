<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A browser Web-Push subscription for one agent + device. Created when an agent
 * turns on notifications in the Team-Inbox PWA; consumed by WebPushService to
 * ring their device on new inbound messages even when the app is closed.
 *
 * `endpoint` is stored as TEXT (push endpoints can exceed MySQL's unique-index
 * length limit), and `endpoint_hash` (sha256) carries the unique constraint. The
 * hash is filled automatically whenever `endpoint` is set.
 */
class PushSubscription extends Model
{
    protected $fillable = [
        'user_id', 'workspace_id', 'endpoint', 'endpoint_hash',
        'p256dh', 'auth', 'ua', 'channel', 'last_notified_at',
    ];

    protected $casts = [
        'last_notified_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        // Keep endpoint_hash in lockstep with endpoint so callers never have to
        // set it by hand (updateOrCreate keys on it).
        static::saving(function (self $sub) {
            if ($sub->isDirty('endpoint') && $sub->endpoint) {
                $sub->endpoint_hash = hash('sha256', (string) $sub->endpoint);
            }
        });
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
