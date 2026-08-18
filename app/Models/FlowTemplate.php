<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Admin-curated Bot-Flow blueprint tenants can clone. See the
 * 2026_07_08_000001_create_flow_templates_table migration for the why.
 */
class FlowTemplate extends Model
{
    protected $fillable = [
        'name', 'description', 'flow_type', 'category',
        'flow_data', 'is_active', 'sort_order', 'created_by', 'clone_count',
    ];

    protected $casts = [
        // flow_data is stored as plain JSON (global/shareable) — cast to array
        // so callers get the {flowNodes, flowEdges, vars} structure directly.
        'flow_data'  => 'array',
        'is_active'  => 'boolean',
        'sort_order' => 'integer',
        'clone_count'=> 'integer',
    ];

    public const FLOW_TYPES = ['chat', 'call', 'instagram'];

    public function scopeActive(Builder $q): Builder
    {
        return $q->where('is_active', true);
    }

    /** Gallery order: admin sort_order first, then newest. */
    public function scopeOrdered(Builder $q): Builder
    {
        return $q->orderBy('sort_order')->orderByDesc('id');
    }

    /** Node count for the gallery card ("12 steps"). Cheap, no decrypt. */
    public function getNodeCountAttribute(): int
    {
        $d = $this->flow_data;
        return is_array($d) && isset($d['flowNodes']) && is_array($d['flowNodes'])
            ? count($d['flowNodes'])
            : 0;
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
