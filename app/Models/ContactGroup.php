<?php

namespace App\Models;

use App\Models\Concerns\LogsNotifications;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Ported from D:\wadesk_2806\New folder\app\Models\ContactGroups.php.
 *
 * Renamed class to singular `ContactGroup` (Laravel convention), but the
 * underlying table name `contact_groups` matches the new migration.
 *
 * The old project used a string column `user_id` and table `contacts_group`;
 * we keep semantics but normalize naming for the new project.
 */
class ContactGroup extends Model
{
    use HasFactory, LogsNotifications;

    protected $table = 'contact_groups';

    protected $fillable = [
        'user_id',
        'workspace_id',
        'user_group',
        'note',
        'status',
        'color',
    ];

    protected $casts = [
        'status'     => 'boolean',
        'user_group' => 'encrypted',
        'note'       => 'encrypted',
    ];

    /**
     * Count contacts whose JSON `contact_group` array contains this group's id
     * (mirrors the old whereRaw JSON_CONTAINS query, but kept in PHP so it
     * works on both MySQL and SQLite). Scoped to the group's own
     * workspace_id so the count never bleeds across tenants.
     */
    public function getContactsCountAttribute(): int
    {
        return Contact::query()
            ->where('workspace_id', $this->workspace_id)
            ->get(['contact_group'])
            ->filter(fn ($c) => is_array($c->contact_group)
                && in_array((string) $this->id, array_map('strval', $c->contact_group), true))
            ->count();
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
