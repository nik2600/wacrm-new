<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GuidebookArticle extends Model
{
    protected $fillable = [
        'slug', 'title', 'category', 'excerpt', 'body', 'is_published',
        'sort_order', 'views_count', 'helpful_count', 'not_helpful_count',
        'published_at',
    ];

    protected $casts = [
        'is_published'      => 'boolean',
        'sort_order'        => 'integer',
        'views_count'       => 'integer',
        'helpful_count'     => 'integer',
        'not_helpful_count' => 'integer',
        'published_at'      => 'datetime',
    ];

    public function scopePublished($q)  { return $q->where('is_published', true); }
}
