<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SavedReply extends Model
{
    protected $fillable = [
        'workspace_id', 'user_id',
        'shortcut', 'title', 'body',
        'attachments', 'category', 'used_count',
    ];

    protected $casts = [
        // body holds operator-authored copy that may include customer
        // first names / personal phrasing — encrypt to match notes & messages.
        'body'        => 'encrypted',
        'attachments' => 'array',
        'used_count'  => 'integer',
    ];

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withDefault();
    }

    public function scopeForWorkspace(Builder $q, int $workspaceId): Builder
    {
        return $q->where('workspace_id', $workspaceId);
    }

    public function scopeAccessibleBy(Builder $q, int $userId): Builder
    {
        return $q->where(function ($w) use ($userId) {
            $w->whereNull('user_id')->orWhere('user_id', $userId);
        });
    }
}
