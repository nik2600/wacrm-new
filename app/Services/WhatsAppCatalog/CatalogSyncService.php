<?php

namespace App\Services\WhatsAppCatalog;

use App\Models\WaCatalog;
use App\Models\WaProduct;
use App\Models\WaStorefront;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Keeps a workspace's Meta Commerce catalog in step with its products
 * WITHOUT a manual "Sync to Meta" click. When a product's price, stock,
 * image, name (etc.) changes — from a storefront edit, a Shopify/Woo
 * webhook, anywhere — the WaProduct model observer (see WaProduct::booted)
 * calls pushOne() and the delta lands on Meta immediately.
 *
 * Meta's items_batch `CREATE` verb is an upsert keyed on retailer_id, so
 * the same call that first creates a product also updates it — no separate
 * UPDATE path needed.
 *
 * Two safety rails:
 *   • Every push is wrapped so a Meta hiccup can NEVER break the product
 *     save that triggered it (catch-all → mark failed + log, never throw).
 *   • Bulk loops (full imports) wrap themselves in withoutAutoSync() so a
 *     5000-product import fires ONE batched sync (via the catalog UI or the
 *     `catalog:resync` command) instead of 5000 individual Meta calls.
 */
class CatalogSyncService
{
    /** When true, observer auto-pushes are skipped (set by bulk importers). */
    private static bool $suppressed = false;

    /**
     * Catalog-relevant attributes. A change to any of these is worth a
     * re-push; a change to only the meta_* bookkeeping columns is not
     * (which is also what stops pushOne()'s own status write from looping).
     */
    public const SYNC_FIELDS = [
        'name', 'description', 'price_minor', 'compare_price_minor',
        'currency_code', 'image_url', 'availability', 'in_stock',
        'stock_qty', 'brand', 'category', 'status',
    ];

    /** Run $fn with observer auto-sync suppressed. Restores the prior state. */
    public static function withoutAutoSync(callable $fn): mixed
    {
        $prev = self::$suppressed;
        self::$suppressed = true;
        try {
            return $fn();
        } finally {
            self::$suppressed = $prev;
        }
    }

    public static function isSuppressed(): bool
    {
        return self::$suppressed;
    }

    /**
     * Push one product's current state to the workspace's connected Meta
     * catalog. No-op when suppressed, when no catalog is linked, or when
     * the product isn't active. Marks the row `pending`; the existing
     * batch poll (CatalogController::pollBatches / `catalog:resync`) flips
     * it to synced.
     */
    public function pushOne(WaProduct $product): void
    {
        if (self::$suppressed) return;

        try {
            $catalog = WaCatalog::where('workspace_id', $product->workspace_id)->first();
            if (!$catalog || !$catalog->catalog_id) return;          // nothing linked → nothing to do
            if (($product->status ?? 'active') !== 'active') return;  // drafts/archived don't belong on Meta

            $shop    = WaStorefront::where('workspace_id', $product->workspace_id)->orderByDesc('id')->first();
            $shopUrl = $shop?->public_url ?? '';

            $result = WhatsAppCatalogFactory::forCatalog($catalog)->upsertProductsBatch([$product], $shopUrl);
            $handle = $result['handles'][0] ?? null;

            self::withoutAutoSync(fn () => $product->forceFill([
                'meta_sync_status'  => 'pending',
                'meta_batch_handle' => $handle,
                'meta_retailer_id'  => $product->meta_retailer_id ?: ($product->sku ?: 'wsn-' . $product->id),
                'meta_last_error'   => null,
            ])->save());
        } catch (Throwable $e) {
            try {
                self::withoutAutoSync(fn () => $product->forceFill([
                    'meta_sync_status' => 'failed',
                    'meta_last_error'  => mb_substr($e->getMessage(), 0, 500),
                ])->save());
            } catch (Throwable) {
                // swallow — a logging/DB failure here must not bubble up
            }
            Log::warning('[CATALOG-SYNC] auto-push failed for product ' . $product->id . ': ' . $e->getMessage());
        }
    }

    /**
     * Flush every dirty (never-synced or failed) active product for a
     * workspace to Meta in one batch, up to $limit. Returns counts. Used
     * by the `catalog:resync` command for a scheduled delta sweep and by
     * importers to push a freshly-imported catalog in one shot.
     *
     * @return array{skipped:bool,pushed:int,error?:string}
     */
    public function flushWorkspace(int $workspaceId, int $limit = 1000): array
    {
        $catalog = WaCatalog::where('workspace_id', $workspaceId)->first();
        if (!$catalog || !$catalog->catalog_id) return ['skipped' => true, 'pushed' => 0];

        $chunk = WaProduct::where('workspace_id', $workspaceId)
            ->where('status', 'active')
            ->where(fn ($q) => $q->whereNull('meta_sync_status')->orWhere('meta_sync_status', 'failed'))
            ->orderBy('id')
            ->limit($limit)
            ->get();
        if ($chunk->isEmpty()) return ['skipped' => false, 'pushed' => 0];

        $shop    = WaStorefront::where('workspace_id', $workspaceId)->orderByDesc('id')->first();
        $shopUrl = $shop?->public_url ?? '';

        try {
            $result  = WhatsAppCatalogFactory::forCatalog($catalog)->upsertProductsBatch($chunk, $shopUrl);
            $handles = $result['handles'] ?? [];
            self::withoutAutoSync(function () use ($chunk, $handles) {
                foreach ($chunk as $i => $p) {
                    $p->forceFill([
                        'meta_sync_status'  => 'pending',
                        'meta_batch_handle' => $handles[$i] ?? null,
                        'meta_retailer_id'  => $p->meta_retailer_id ?: ($p->sku ?: 'wsn-' . $p->id),
                        'meta_last_error'   => null,
                    ])->save();
                }
            });
            return ['skipped' => false, 'pushed' => $chunk->count()];
        } catch (Throwable $e) {
            self::withoutAutoSync(function () use ($chunk, $e) {
                foreach ($chunk as $p) {
                    $p->forceFill([
                        'meta_sync_status' => 'failed',
                        'meta_last_error'  => mb_substr($e->getMessage(), 0, 500),
                    ])->save();
                }
            });
            return ['skipped' => false, 'pushed' => 0, 'error' => $e->getMessage()];
        }
    }

    /**
     * Poll Meta for batch results and flip pending rows to synced/failed
     * for a workspace. Headless twin of CatalogController::pollBatches so
     * the `catalog:resync` command can settle statuses without the UI.
     *
     * @return array{synced:int,failed:int,pending:int}
     */
    public function pollWorkspace(int $workspaceId): array
    {
        $out = ['synced' => 0, 'failed' => 0, 'pending' => 0];
        $catalog = WaCatalog::where('workspace_id', $workspaceId)->first();
        if (!$catalog || !$catalog->catalog_id) return $out;

        $pending = WaProduct::where('workspace_id', $workspaceId)
            ->whereNotNull('meta_batch_handle')
            ->where('meta_sync_status', 'pending')
            ->get();
        if ($pending->isEmpty()) return $out;

        try {
            $res = WhatsAppCatalogFactory::forCatalog($catalog)
                ->checkBatchStatus($pending->pluck('meta_batch_handle')->unique()->values()->all());
        } catch (Throwable $e) {
            Log::warning('[CATALOG-SYNC] poll failed for workspace ' . $workspaceId . ': ' . $e->getMessage());
            return $out;
        }

        $byHandle = collect($res['data'] ?? [])->keyBy('handle');
        self::withoutAutoSync(function () use ($pending, $byHandle, &$out) {
            foreach ($pending as $p) {
                $row = $byHandle->get($p->meta_batch_handle);
                if (!$row) { $out['pending']++; continue; }
                $status = strtolower($row['status'] ?? '');
                if (in_array($status, ['finished', 'success', 'completed'], true)) {
                    $p->forceFill([
                        'meta_sync_status'  => 'synced',
                        'meta_synced_at'    => now(),
                        'meta_batch_handle' => null,
                        'meta_last_error'   => null,
                    ])->save();
                    $out['synced']++;
                } elseif (in_array($status, ['error', 'failed'], true)) {
                    $p->forceFill([
                        'meta_sync_status' => 'failed',
                        'meta_last_error'  => mb_substr($row['errors'][0]['message'] ?? 'unknown', 0, 500),
                    ])->save();
                    $out['failed']++;
                } else {
                    $out['pending']++;
                }
            }
        });

        return $out;
    }
}
