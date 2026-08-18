<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * One FAQ row on /pricing. Admin manages from /admin/pricing-faqs
 * (todo: still a stub controller). The pricing view fetches active
 * rows ordered by sort_order; if the table is empty the view falls
 * back to a baked-in default set.
 */
class PricingFaq extends Model
{
    protected $table = 'pricing_faqs';

    protected $fillable = ['question', 'answer', 'sort_order', 'is_active', 'placement'];

    protected $casts = [
        'sort_order' => 'integer',
        'is_active'  => 'boolean',
    ];

    public function scopeActive(Builder $q): Builder
    {
        return $q->where('is_active', true)->orderBy('sort_order')->orderBy('id');
    }

    /** FAQs shown in a given placement ('pricing' | 'home'), including 'both'. */
    public function scopePlacement(Builder $q, string $placement): Builder
    {
        return $q->whereIn('placement', [$placement, 'both']);
    }
}
