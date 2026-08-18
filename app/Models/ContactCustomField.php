<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContactCustomField extends Model
{
    protected $fillable = [
        'workspace_id', 'key', 'label',
        'type', 'options', 'required', 'show_in_panel', 'sort',
    ];

    protected $casts = [
        'options'       => 'array',
        'required'      => 'boolean',
        'show_in_panel' => 'boolean',
        'sort'          => 'integer',
    ];

    public const TYPES = ['text', 'number', 'date', 'select', 'bool', 'url', 'email'];

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function scopeForWorkspace(Builder $q, int $workspaceId): Builder
    {
        return $q->where('workspace_id', $workspaceId);
    }
}
