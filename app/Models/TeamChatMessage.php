<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class TeamChatMessage extends Model
{
    use SoftDeletes;

    protected $table = 'team_chat_messages';

    protected $fillable = [
        'workspace_id',
        'user_id',
        'body',
        'mentions',
        'reply_to_id',
        'attachment_path',
        'attachment_mime',
        'attachment_name',
        'edited_at',
    ];

    protected $casts = [
        'body'        => 'encrypted',
        'mentions'    => 'array',
        'edited_at'   => 'datetime',
    ];

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id')->withDefault(['name' => 'Unknown']);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reply_to_id');
    }
}
