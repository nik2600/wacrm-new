<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * One editable value on the public marketing site, keyed by a dotted
 * `ckey` (e.g. home.hero.headline, theme.wa.deep). See the migration
 * for the data model. All reads go through App\Services\Frontend\
 * FrontendContentStore, which caches the whole (small) table.
 *
 * Writing any row busts that cache so the public site updates on the
 * next request.
 */
class FrontendContent extends Model
{
    protected $table = 'frontend_content';

    protected $fillable = ['ckey', 'type', 'draft', 'published', 'updated_by'];

    public const CACHE_KEY = 'frontend_content:all';

    protected static function booted(): void
    {
        $bust = fn () => Cache::forget(self::CACHE_KEY);
        static::saved($bust);
        static::deleted($bust);
    }
}
