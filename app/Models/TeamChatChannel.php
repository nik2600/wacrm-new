<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class TeamChatChannel extends Model
{
    use SoftDeletes;

    protected $table = 'team_chat_channels';

    protected $fillable = [
        'workspace_id',
        'name',
        'slug',
        'description',
        'type',
        'created_by_user_id',
        'last_message_at',
    ];

    protected $casts = [
        'last_message_at' => 'datetime',
    ];

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function members(): HasMany
    {
        return $this->hasMany(TeamChatChannelMember::class, 'channel_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(TeamChatMessage::class, 'channel_id');
    }
}
