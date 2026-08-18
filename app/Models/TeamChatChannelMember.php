<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TeamChatChannelMember extends Model
{
    protected $table = 'team_chat_channel_members';

    protected $fillable = [
        'channel_id',
        'user_id',
        'role',
        'last_read_message_id',
        'last_read_at',
        'joined_at',
    ];

    protected $casts = [
        'last_read_at' => 'datetime',
        'joined_at'    => 'datetime',
    ];
}
