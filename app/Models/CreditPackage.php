<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Admin-curated credit-top-up bundle. Each package is a fixed
 * "this-much-money buys this-many-credits" offer. Distinct from
 * the SaaS subscription `packages` table — this is one-time, no
 * feature gates.
 */
class CreditPackage extends Model
{
    protected $fillable = [
        'name', 'slug', 'price_minor', 'currency_code', 'credits',
        'badge', 'description', 'is_active', 'is_featured', 'sort_order',
    ];

    protected $casts = [
        'price_minor' => 'integer',
        'credits'     => 'integer',
        'is_active'   => 'boolean',
        'is_featured' => 'boolean',
        'sort_order'  => 'integer',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $row) {
            if (empty($row->slug) && !empty($row->name)) {
                $base = Str::slug($row->name);
                $slug = $base ?: 'package';
                $i = 2;
                while (self::where('slug', $slug)->where('id', '!=', $row->id ?? 0)->exists()) {
                    $slug = $base . '-' . $i++;
                }
                $row->slug = $slug;
            }
        });
    }

    public function scopeActive(Builder $q): Builder
    {
        return $q->where('is_active', true);
    }

    public function scopeOrdered(Builder $q): Builder
    {
        return $q->orderBy('sort_order')->orderBy('id');
    }

    /**
     * Display the package price using FormatSettings — auto-converts to
     * the current workspace/default currency at the live exchange rate.
     * Routes through the same helper every other money display uses, so
     * admin changing default_currency cascades here without any code
     * changes downstream.
     */
    public function getPriceDisplayAttribute(): string
    {
        return \App\Support\FormatSettings::display(
            $this->price_minor / 100,
            $this->currency_code
        );
    }

    /** Effective rate: how many credits the user gets per minor unit. */
    public function getRateAttribute(): float
    {
        return $this->price_minor > 0 ? $this->credits / $this->price_minor : 0;
    }

    public function getCreditsPerMajorAttribute(): float
    {
        return $this->rate * 100; // credits per 1 major unit of the package currency
    }
}
