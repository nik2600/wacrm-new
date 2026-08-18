<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TeamChatInvitation extends Model
{
    protected $table = 'team_chat_invitations';

    protected $fillable = [
        'workspace_id',
        'channel_id',
        'requester_user_id',
        'invitee_user_id',
        'invitee_email',
        'invitee_name',
        'status',
        'note',
        'decided_by_user_id',
        'decided_at',
    ];

    protected $casts = [
        'decided_at' => 'datetime',
    ];

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requester_user_id');
    }

    public function invitee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invitee_user_id');
    }

    public function decidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by_user_id');
    }

    public function channel(): BelongsTo
    {
        return $this->belongsTo(TeamChatChannel::class, 'channel_id');
    }
}
