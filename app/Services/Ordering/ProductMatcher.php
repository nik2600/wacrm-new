<?php

namespace App\Services\Ordering;

use App\Models\WaProduct;
use Illuminate\Support\Collection;

/**
 * Resolve a free-text item name (from the AI order-parser) to a catalog
 * product. Matching ladder, best-first:
 *   1. exact SKU
 *   2. exact product name (normalised)
 *   3. exact alias (any language, normalised)  ← Chinese / Malay / English
 *   4. fuzzy: substring containment, then similar_text score over a threshold
 *
 * Per-workspace catalogs are small, so we load them once and match in PHP
 * (no pg_trgm needed on MySQL). Products are cached per workspace per request.
 */
class ProductMatcher
{
    /** @var array<int, Collection<int,WaProduct>> */
    private array $cache = [];

    private const FUZZY_THRESHOLD = 0.62; // 0..1 similar_text ratio

    /** @return Collection<int,WaProduct> */
    private function catalog(int $workspaceId): Collection
    {
        return $this->cache[$workspaceId] ??= WaProduct::query()
            ->where('workspace_id', $workspaceId)
            ->where('status', 'active')
            ->get(['id', 'workspace_id', 'sku', 'name', 'aliases_json', 'price_minor', 'currency_code', 'stock_qty', 'reserved_qty', 'image_url']);
    }

    private static function norm(string $s): string
    {
        $s = mb_strtolower(trim($s));
        $s = preg_replace('/\s+/u', ' ', $s);
        return trim($s);
    }

    /**
     * @return array{product: ?WaProduct, matched_on: string, confidence: float}
     */
    public function resolve(int $workspaceId, string $text): array
    {
        $needle = self::norm($text);
        if ($needle === '') return ['product' => null, 'matched_on' => 'empty', 'confidence' => 0.0];

        $products = $this->catalog($workspaceId);

        // 1) exact SKU
        foreach ($products as $p) {
            if ($p->sku && self::norm((string) $p->sku) === $needle) {
                return ['product' => $p, 'matched_on' => 'sku', 'confidence' => 1.0];
            }
        }
        // 2) exact name
        foreach ($products as $p) {
            if (self::norm((string) $p->name) === $needle) {
                return ['product' => $p, 'matched_on' => 'name', 'confidence' => 1.0];
            }
        }
        // 3) exact alias (any language)
        foreach ($products as $p) {
            if (in_array($needle, $p->aliasStrings(), true)) {
                return ['product' => $p, 'matched_on' => 'alias', 'confidence' => 0.98];
            }
        }
        // 4) fuzzy — containment first, then similar_text best score
        $best = null; $bestScore = 0.0; $bestOn = 'fuzzy';
        foreach ($products as $p) {
            $candidates = array_merge([self::norm((string) $p->name)], $p->aliasStrings());
            foreach ($candidates as $cand) {
                if ($cand === '') continue;
                // containment (e.g. "chicken drumsticks" contains "drumstick")
                if (str_contains($needle, $cand) || str_contains($cand, $needle)) {
                    $score = 0.85;
                    if ($score > $bestScore) { $bestScore = $score; $best = $p; $bestOn = 'contains'; }
                    continue;
                }
                similar_text($needle, $cand, $pct);
                $ratio = $pct / 100;
                if ($ratio > $bestScore) { $bestScore = $ratio; $best = $p; $bestOn = 'fuzzy'; }
            }
        }
        if ($best && $bestScore >= self::FUZZY_THRESHOLD) {
            return ['product' => $best, 'matched_on' => $bestOn, 'confidence' => round($bestScore, 2)];
        }

        return ['product' => null, 'matched_on' => 'none', 'confidence' => 0.0];
    }
}
