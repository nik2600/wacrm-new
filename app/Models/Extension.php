<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

/**
 * An installed EXTENSION — code shipped into this install (Instagram is the first).
 *
 * Not to be confused with the BILLING add-ons at /admin/addons, which are
 * à-la-carte feature packs on the `packages` table. Different concept, hence
 * a different noun and a different table.
 *
 * See the migration for why extension presence and plan features are separate
 * facts. The short version: this answers "is the code here", the plan answers
 * "may this tenant use it".
 */
class Extension extends Model
{
    protected $fillable = [
        'slug', 'name', 'version', 'purchase_code',
        'status', 'files', 'manifest', 'installed_at', 'installed_by',
    ];

    protected $casts = [
        // The licence key that unlocked this extension — encrypted at rest, same
        // treatment as every other credential in the app.
        'purchase_code' => 'encrypted',
        'files'         => 'array',
        'manifest'      => 'array',
        'installed_at'  => 'datetime',
    ];

    public const STATUS_ACTIVE   = 'active';
    public const STATUS_DISABLED = 'disabled';

    /**
     * Is this extension installed AND switched on?
     *
     * Called from route registration, so it runs on effectively every request
     * — hence the cache. It also has to survive the table not existing yet
     * (fresh install, mid-migration, or a client who never ran the migration),
     * because a 500 on the whole app would be a far worse failure than an
     * extension quietly reporting "not installed".
     */
    public static function enabled(string $slug): bool
    {
        return (bool) Cache::remember("extension.enabled.{$slug}", 300, function () use ($slug) {
            try {
                if (!Schema::hasTable('extensions')) {
                    return false;
                }
                return static::query()
                    ->where('slug', $slug)
                    ->where('status', self::STATUS_ACTIVE)
                    ->exists();
            } catch (\Throwable $e) {
                return false;
            }
        });
    }

    /** Drop the cached answer — call after any install / enable / disable. */
    public static function forget(?string $slug = null): void
    {
        if ($slug) {
            Cache::forget("extension.enabled.{$slug}");
        } else {
            foreach (static::query()->pluck('slug') as $s) {
                Cache::forget("extension.enabled.{$s}");
            }
        }

        // Nav entries and plan fields are derived from the same rows, so they
        // go stale at exactly the same moments. Forgetting one without the
        // other leaves a disabled extension still showing in the sidebar.
        \App\Services\ExtensionRegistry::forget();
    }

    public function installer()
    {
        return $this->belongsTo(User::class, 'installed_by');
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }
}
