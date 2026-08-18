<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A WhatsApp group the bot (Baileys number) is a member of. Mirrored from
 * sock.groupFetchAllParticipating() so the order flow can post into the right
 * group with an @mention. `group_code` lets a wa.me link pin a specific group.
 */
class WaGroup extends Model
{
    protected $fillable = [
        'workspace_id', 'device_phone', 'group_jid', 'subject', 'size',
        'group_code', 'meta_json', 'synced_at',
    ];

    protected $casts = [
        'size'      => 'integer',
        'meta_json' => 'array',
        'synced_at' => 'datetime',
    ];

    public function members(): HasMany
    {
        return $this->hasMany(WaGroupMember::class, 'group_jid', 'group_jid')
            ->where('wa_group_members.workspace_id', $this->workspace_id);
    }
}
