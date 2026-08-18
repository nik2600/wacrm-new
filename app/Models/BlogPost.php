<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class BlogPost extends Model
{
    protected $fillable = [
        'title', 'slug', 'excerpt', 'body', 'featured_image', 'category_id',
        'tags', 'author_name', 'status', 'published_at', 'views', 'is_featured',
        'meta_title', 'meta_description', 'meta_keywords', 'og_image',
        'canonical_url', 'noindex',
    ];

    protected $casts = [
        'tags'         => 'array',
        'published_at' => 'datetime',
        'is_featured'  => 'boolean',
        'noindex'      => 'boolean',
        'views'        => 'integer',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(BlogCategory::class, 'category_id');
    }

    /** Published + past its publish time. */
    public function scopePublished(Builder $q): Builder
    {
        return $q->where('status', 'published')
            ->where(function ($w) {
                $w->whereNull('published_at')->orWhere('published_at', '<=', now());
            });
    }

    public function getIsLiveAttribute(): bool
    {
        return $this->status === 'published'
            && (!$this->published_at || $this->published_at->lte(now()));
    }

    /** Public URL of the post. */
    public function getUrlAttribute(): string
    {
        return url('/blog/' . $this->slug);
    }

    /** Featured image URL via the active media disk (cloud or local). */
    public function getImageUrlAttribute(): ?string
    {
        return $this->featured_image ? media_url($this->featured_image) : null;
    }

    public function getOgImageUrlAttribute(): ?string
    {
        if ($this->og_image) {
            return media_url($this->og_image);
        }
        return $this->image_url;
    }

    /** Effective <title> for the post page. */
    public function seoTitle(): string
    {
        return $this->meta_title ?: $this->title;
    }

    /** Effective meta description (falls back to excerpt, then body snippet). */
    public function seoDescription(): string
    {
        $d = $this->meta_description ?: $this->excerpt;
        if (!$d) {
            $d = Str::limit(trim(strip_tags((string) $this->body)), 155);
        }
        return (string) $d;
    }

    public function readingTimeMinutes(): int
    {
        $words = str_word_count(strip_tags((string) $this->body));
        return max(1, (int) ceil($words / 200));
    }

    /** Slugify a title, keeping it unique (excludes $ignoreId on edit). */
    public static function uniqueSlug(string $title, ?int $ignoreId = null): string
    {
        $base = Str::slug($title) ?: 'post';
        $slug = $base;
        $i = 2;
        while (static::where('slug', $slug)->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))->exists()) {
            $slug = $base . '-' . $i++;
        }
        return $slug;
    }
}
