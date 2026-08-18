<?php

namespace App\Services\Shopify;

use App\Models\Contact;
use App\Models\ContactGroup;
use App\Models\ShopifyIntegration;
use App\Models\WaOrder;
use App\Models\WaProduct;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Mirrors a connected Shopify store's products / orders / customers into
 * our own tables so every Shopify-CRM surface (dashboard, catalog send,
 * broadcasts, automations, analytics) runs on real local data instead of
 * per-request live API calls.
 *
 * Idempotent: every row is keyed by its Shopify numeric id, so re-running
 * an import updates in place rather than duplicating. v1 pulls the first
 * page (250) of each resource; cursor pagination is a later enhancement.
 */
class ShopifyImporter
{
    public function __construct(private readonly ShopifyService $shopify) {}

    /** Run all three imports + stamp sync state. Returns per-resource counts. */
    public function importAll(ShopifyIntegration $integration): array
    {
        $stats = [
            'products'  => $this->importProducts($integration),
            'orders'    => $this->importOrders($integration),
            'customers' => $this->importCustomers($integration),
        ];

        $integration->forceFill([
            'products_synced_at'  => now(),
            'orders_synced_at'    => now(),
            'customers_synced_at' => now(),
            'sync_stats'          => $stats,
        ])->save();

        return $stats;
    }

    // -----------------------------------------------------------------
    // Products → wa_products
    // -----------------------------------------------------------------

    public function importProducts(ShopifyIntegration $integration): int
    {
        $rows = $this->shopify->getProducts($integration, 250);
        $n = 0;
        // Bulk loop: suppress per-row Meta catalog auto-sync so a large
        // import fires one batched re-sync (catalog UI / catalog:resync)
        // instead of one Meta call per product.
        \App\Services\WhatsAppCatalog\CatalogSyncService::withoutAutoSync(function () use ($rows, $integration, &$n) {
            foreach ($rows as $p) {
                try {
                    $this->upsertProduct($integration, $p);
                    $n++;
                } catch (\Throwable $e) {
                    Log::warning('[ShopifyImporter] product upsert failed', ['id' => $p['id'] ?? null, 'err' => $e->getMessage()]);
                }
            }
        });
        $integration->forceFill(['products_synced_at' => now()])->save();
        return $n;
    }

    public function upsertProduct(ShopifyIntegration $integration, array $p): WaProduct
    {
        $variant   = is_array($p['variants'][0] ?? null) ? $p['variants'][0] : [];
        $images    = is_array($p['images'] ?? null) ? $p['images'] : [];
        $imageUrl  = $images[0]['src'] ?? ($p['image']['src'] ?? null);
        $gallery   = array_values(array_filter(array_map(fn ($i) => $i['src'] ?? null, $images)));
        $statusMap = ['active' => 'active', 'draft' => 'draft', 'archived' => 'archived'];

        return WaProduct::updateOrCreate(
            [
                'workspace_id'       => $integration->workspace_id,
                'shopify_product_id' => (string) ($p['id'] ?? ''),
            ],
            [
                'user_id'             => $integration->user_id,
                'sku'                 => (string) ($variant['sku'] ?? ('SHOPIFY-' . ($p['id'] ?? ''))),
                'name'                => (string) ($p['title'] ?? 'Untitled'),
                'slug'                => (string) ($p['handle'] ?? Str::slug((string) ($p['title'] ?? ''))),
                'description'         => strip_tags((string) ($p['body_html'] ?? '')),
                'body_html'           => (string) ($p['body_html'] ?? ''),
                'price_minor'         => $this->toMinor($variant['price'] ?? null),
                'compare_price_minor' => $this->toMinor($variant['compare_at_price'] ?? null),
                'currency_code'       => $integration->shop_currency ?: 'USD',
                'image_url'           => $imageUrl,
                'gallery_json'        => $gallery,
                'in_stock'            => ShopifyStockService::deriveInStock($p),
                'status'              => $statusMap[strtolower((string) ($p['status'] ?? 'active'))] ?? 'active',
                'category'            => $p['product_type'] ?? null,
                'brand'               => $p['vendor'] ?? null,
                'product_url'         => 'https://' . $integration->store_url . '/products/' . ($p['handle'] ?? ''),
                'tags_json'           => $this->splitTags($p['tags'] ?? null),
            ],
        );
    }

    // -----------------------------------------------------------------
    // Orders → wa_orders
    // -----------------------------------------------------------------

    public function importOrders(ShopifyIntegration $integration): int
    {
        $rows = $this->shopify->getOrders($integration, 250);
        $n = 0;
        foreach ($rows as $o) {
            try {
                $this->upsertOrder($integration, $o);
                $n++;
            } catch (\Throwable $e) {
                Log::warning('[ShopifyImporter] order upsert failed', ['id' => $o['id'] ?? null, 'err' => $e->getMessage()]);
            }
        }
        $integration->forceFill(['orders_synced_at' => now()])->save();
        return $n;
    }

    public function upsertOrder(ShopifyIntegration $integration, array $o): WaOrder
    {
        $cust  = is_array($o['customer'] ?? null) ? $o['customer'] : [];
        $name  = trim((string) ($cust['first_name'] ?? '') . ' ' . (string) ($cust['last_name'] ?? '')) ?: null;
        $phone = $cust['phone'] ?? $o['phone'] ?? ($o['shipping_address']['phone'] ?? null);

        $items = array_map(fn ($li) => [
            'title'    => $li['title'] ?? '',
            'qty'      => (int) ($li['quantity'] ?? 1),
            'price'    => $li['price'] ?? '0',
            'sku'      => $li['sku'] ?? null,
        ], is_array($o['line_items'] ?? null) ? $o['line_items'] : []);

        return WaOrder::updateOrCreate(
            [
                'workspace_id'     => $integration->workspace_id,
                'shopify_order_id' => (string) ($o['id'] ?? ''),
            ],
            [
                'source'         => 'shopify',
                'customer_phone' => $phone ? preg_replace('/\D+/', '', (string) $phone) : null,
                'customer_name'  => $name,
                'customer_email' => $cust['email'] ?? $o['email'] ?? null,
                'items_json'     => $items,
                'total_minor'    => $this->toMinor($o['total_price'] ?? null),
                'currency_code'  => $o['currency'] ?? $integration->shop_currency ?: 'USD',
                'status'         => $this->mapOrderStatus($o),
                'meta_json'      => [
                    'name'               => $o['name'] ?? null,
                    'order_number'       => $o['order_number'] ?? null,
                    'financial_status'   => $o['financial_status'] ?? null,
                    'fulfillment_status' => $o['fulfillment_status'] ?? null,
                    'created_at'         => $o['created_at'] ?? null,
                ],
            ],
        );
    }

    // -----------------------------------------------------------------
    // Customers → contacts
    // -----------------------------------------------------------------

    public function importCustomers(ShopifyIntegration $integration): int
    {
        $rows = $this->shopify->getCustomers($integration, 250);
        if (!$rows) {
            $integration->forceFill(['customers_synced_at' => now()])->save();
            return 0;
        }

        $groupId = $this->shopifyGroupId($integration);
        $n = 0;
        foreach ($rows as $c) {
            $phone = preg_replace('/\D+/', '', (string) ($c['phone'] ?? ''));
            if ($phone === '') continue; // WhatsApp needs a number
            try {
                $this->upsertContact($integration, $c, $phone, $groupId);
                $n++;
            } catch (\Throwable $e) {
                Log::warning('[ShopifyImporter] customer upsert failed', ['id' => $c['id'] ?? null, 'err' => $e->getMessage()]);
            }
        }
        $integration->forceFill(['customers_synced_at' => now()])->save();
        return $n;
    }

    private function upsertContact(ShopifyIntegration $integration, array $c, string $phone, ?int $groupId): void
    {
        // Contacts encrypt mobile at rest, so we can't WHERE on it. Match
        // within the workspace by decrypting in a bounded recent set; if no
        // match, create. (Good enough for v1 import volumes.)
        $existing = Contact::where('workspace_id', $integration->workspace_id)
            ->get(['id', 'mobile', 'contact_group'])
            ->first(fn ($row) => preg_replace('/\D+/', '', (string) $row->mobile) === $phone);

        $name = trim((string) ($c['first_name'] ?? '') . ' ' . (string) ($c['last_name'] ?? '')) ?: $phone;

        if ($existing) {
            $groups = is_array($existing->contact_group) ? $existing->contact_group : [];
            if ($groupId && !in_array((string) $groupId, array_map('strval', $groups), true)) {
                $groups[] = (string) $groupId;
                $existing->update(['contact_group' => $groups]);
            }
            return;
        }

        Contact::create([
            'workspace_id'   => $integration->workspace_id,
            'user_id'        => $integration->user_id,
            'name'           => $name,
            'mobile'         => $phone,
            'email'          => $c['email'] ?? null,
            'first_name'     => $c['first_name'] ?? null,
            'last_name'      => $c['last_name'] ?? null,
            'contact_group'  => $groupId ? [(string) $groupId] : [],
            'custom_attributes' => [
                'shopify_customer_id' => (string) ($c['id'] ?? ''),
                'orders_count'        => (int) ($c['orders_count'] ?? 0),
                'total_spent'         => (string) ($c['total_spent'] ?? '0'),
            ],
        ]);
    }

    /** Find-or-create the "Shopify Customers" group; cache its id on the integration. */
    private function shopifyGroupId(ShopifyIntegration $integration): ?int
    {
        $meta = $integration->metadata ?? [];
        if (!empty($meta['shopify_group_id']) && ContactGroup::whereKey($meta['shopify_group_id'])->exists()) {
            return (int) $meta['shopify_group_id'];
        }
        try {
            $group = ContactGroup::create([
                'workspace_id' => $integration->workspace_id,
                'user_id'      => $integration->user_id,
                'user_group'   => 'Shopify Customers',
                'status'       => true,
                'color'        => '#95BF47',
            ]);
            $meta['shopify_group_id'] = $group->id;
            $integration->update(['metadata' => $meta]);
            return $group->id;
        } catch (\Throwable $e) {
            Log::warning('[ShopifyImporter] group create failed: ' . $e->getMessage());
            return null;
        }
    }

    // -----------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------

    private function toMinor($price): int
    {
        if ($price === null || $price === '') return 0;
        return (int) round(((float) $price) * 100);
    }

    private function splitTags($tags): array
    {
        if (is_array($tags)) return array_values(array_filter($tags));
        if (is_string($tags) && $tags !== '') {
            return array_values(array_filter(array_map('trim', explode(',', $tags))));
        }
        return [];
    }

    private function mapOrderStatus(array $o): string
    {
        $fin = strtolower((string) ($o['financial_status'] ?? ''));
        $ful = strtolower((string) ($o['fulfillment_status'] ?? ''));
        if (in_array($fin, ['refunded', 'voided'], true) || ($o['cancelled_at'] ?? null)) return 'cancelled';
        if ($ful === 'fulfilled') return 'shipped';
        if ($fin === 'paid') return 'paid';
        if ($fin === 'pending') return 'new';
        return 'new';
    }
}
