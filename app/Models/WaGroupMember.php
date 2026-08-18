<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A participant of a WhatsApp group (digits-only phone). Powers the reverse
 * lookup "which group(s) is this customer in" used when an order is confirmed
 * in a private chat and we need to notify their group.
 */
class WaGroupMember extends Model
{
    protected $fillable = [
        'workspace_id', 'group_jid', 'phone', 'is_admin', 'synced_at',
    ];

    protected $casts = [
        'is_admin'  => 'boolean',
        'synced_at' => 'datetime',
    ];
}
