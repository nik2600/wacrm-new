<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TeamChatRead extends Model
{
    protected $table = 'team_chat_reads';

    protected $fillable = [
        'workspace_id',
        'user_id',
        'last_read_message_id',
        'last_read_at',
    ];

    protected $casts = [
        'last_read_at' => 'datetime',
    ];
}
