<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Shortened/tracked URL — the row backing /r/{token}.
 *
 * Created by LinkTracker::wrap() at send-time, hit by the public
 * redirect route. Holds the send-time context (broadcast, contact,
 * message, template) so the broadcasts page can answer
 * "which recipient clicked which link". Soft-expires after 90 days
 * by default (configurable via `wa_link_tracking_ttl_days`).
 */
class WaLinkClick extends Model
{
    protected $table = 'wa_link_clicks';

    protected $fillable = [
        'token', 'original_url',
        'workspace_id', 'broadcast_id', 'campaign_id', 'message_id', 'contact_id', 'template_id', 'phone',
        'clicks', 'unique_clicks', 'first_click_at', 'last_click_at', 'last_ip', 'last_user_agent',
        'expires_at',
    ];

    protected $casts = [
        'first_click_at' => 'datetime',
        'last_click_at'  => 'datetime',
        'expires_at'     => 'datetime',
    ];
}
