<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * A reusable, ordered group of catalog products (a "collection"). Used to
 * fire a saved Multi Product Message without re-picking products each time.
 *
 * @property array|null $product_ids
 */
class WaProductSet extends Model
{
    protected $fillable = [
        'workspace_id', 'user_id', 'name', 'slug', 'description',
        'product_ids', 'is_active', 'meta_set_id',
    ];

    protected $casts = [
        'product_ids' => 'array',
        'is_active'   => 'boolean',
    ];

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    /**
     * Resolve member products that still exist and are active, in the
     * stored order. Cap at 30 — Meta's MPM hard limit.
     */
    public function products(): Collection
    {
        $ids = array_values(array_filter(array_map('intval', $this->product_ids ?? [])));
        if (empty($ids)) return collect();

        $byId = WaProduct::where('workspace_id', $this->workspace_id)
            ->whereIn('id', $ids)
            ->where('status', 'active')
            ->get()
            ->keyBy('id');

        return collect($ids)
            ->map(fn ($id) => $byId->get($id))
            ->filter()
            ->take(30)
            ->values();
    }

    /** Retailer ids for the live member products (MPM section payload). */
    public function retailerIds(): array
    {
        return $this->products()
            ->map(fn ($p) => $p->meta_retailer_id ?: ($p->sku ?: 'wsn-' . $p->id))
            ->all();
    }

    public function getProductCountAttribute(): int
    {
        return count($this->product_ids ?? []);
    }

    public static function uniqueSlug(int $workspaceId, string $name, ?int $ignoreId = null): string
    {
        $base = Str::slug($name) ?: 'set';
        $slug = $base;
        $i = 2;
        while (self::query()
            ->where('workspace_id', $workspaceId)
            ->where('slug', $slug)
            ->when($ignoreId, fn ($q, $id) => $q->where('id', '!=', $id))
            ->exists()) {
            $slug = $base . '-' . $i++;
        }
        return $slug;
    }
}
