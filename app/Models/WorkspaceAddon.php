<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A purchased add-on attached to a workspace. The add-on's feature flags +
 * limits (it's a `packages` row with type='addon') are merged onto the
 * workspace's base plan in Workspace::effectiveLimit() while this row is
 * active and unexpired.
 */
class WorkspaceAddon extends Model
{
    protected $table = 'workspace_addons';

    protected $fillable = [
        'workspace_id', 'package_id', 'order_id',
        'status', 'starts_at', 'ends_at',
    ];

    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at'   => 'datetime',
    ];

    public const STATUS_ACTIVE = 'active';

    public function package(): BelongsTo
    {
        return $this->belongsTo(Package::class, 'package_id');
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class, 'workspace_id');
    }

    /** Active = status active AND (no end date OR end date still in the future). */
    public function scopeActive($q)
    {
        return $q->where('status', self::STATUS_ACTIVE)
                 ->where(function ($w) {
                     $w->whereNull('ends_at')->orWhere('ends_at', '>', now());
                 });
    }
}
