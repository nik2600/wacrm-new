<?php

namespace App\Models;

use App\Models\Concerns\LogsNotifications;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Attribute extends Model
{
    use HasFactory, LogsNotifications;

    protected $fillable = [
        'user_id', 'workspace_id', 'attribute_name', 'attribute_key',
        'attribute_value', 'description', 'type', 'status',
    ];

    protected $casts = [
        'status'          => 'boolean',
        'attribute_value' => 'encrypted',
        'description'     => 'encrypted',
    ];

    public function scopeForUser($q, ?int $userId)
    {
        return $q->where('user_id', $userId);
    }

    public function scopeForWorkspace($q, ?int $workspaceId)
    {
        return $q->where('workspace_id', $workspaceId);
    }

    public function scopeForCurrentWorkspace(Builder $q): Builder
    {
        $user = auth()->user();
        if (!$user) return $q->whereRaw('1=0');
        $uId  = (int) $user->id;
        $wsId = (int) ($user->current_workspace_id ?? 0);
        return $q->where(function ($qq) use ($wsId, $uId) {
            $qq->where('workspace_id', $wsId)
               ->orWhere(function ($qqq) use ($uId) {
                   $qqq->whereNull('workspace_id')->where('user_id', $uId);
               });
        });
    }
}
