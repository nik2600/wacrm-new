<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Announcement extends Model
{
    use HasFactory;

    protected $fillable = [
        'text', 'link_url', 'link_label',
        'tone', 'is_active', 'dismissible',
        'sort_order', 'starts_at', 'expires_at',
    ];

    protected $casts = [
        'is_active'   => 'boolean',
        'dismissible' => 'boolean',
        'sort_order'  => 'integer',
        'starts_at'   => 'datetime',
        'expires_at'  => 'datetime',
    ];

    /** Rows currently eligible to render in the public marquee. */
    public function scopeActive(Builder $q): Builder
    {
        $now = now();
        return $q->where('is_active', true)
            ->where(function ($w) use ($now) {
                $w->whereNull('starts_at')->orWhere('starts_at', '<=', $now);
            })
            ->where(function ($w) use ($now) {
                $w->whereNull('expires_at')->orWhere('expires_at', '>=', $now);
            })
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    /** Tone → background + text colour classes used by the marquee. */
    public function toneClasses(): array
    {
        return match ($this->tone) {
            'promo'   => ['bg' => '#2A1B0E', 'text' => '#FBFAF6'],
            'warning' => ['bg' => '#E5A04E', 'text' => '#0B1F1C'],
            'success' => ['bg' => '#075E54', 'text' => '#FBFAF6'],
            default   => ['bg' => '#0B1F1C', 'text' => '#EFEBE0'],
        };
    }
}
