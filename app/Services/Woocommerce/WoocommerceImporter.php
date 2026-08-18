<?php

namespace App\Services\Woocommerce;

use App\Models\Contact;
use App\Models\ContactGroup;
use App\Models\WaOrder;
use App\Models\WaProduct;
use App\Models\WoocommerceIntegration;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Mirrors a connected WooCommerce store's products / orders / customers into
 * our own tables (wa_products, wa_orders, contacts) so every CRM surface —
 * dashboard, catalog send, broadcasts, automations, analytics — runs on real
 * local data instead of per-request live API calls. The WooCommerce twin of
 * ShopifyImporter; the only differences are the WooCommerce REST field shapes.
 *
 * Idempotent: every row is keyed by its WooCommerce numeric id, so re-running
 * an import upserts in place rather than duplicating.
 */
class WoocommerceImporter
{
    public function __construct(private readonly WoocommerceService $woo) {}

    /** Run all three imports + stamp sync state. Returns per-resource counts. */
    public function importAll(WoocommerceIntegration $integration): array
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

    public function importProducts(WoocommerceIntegration $integration): int
    {
        $rows = $this->woo->getProducts($integration, 100);
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
                    Log::warning('[WoocommerceImporter] product upsert failed', ['id' => $p['id'] ?? null, 'err' => $e->getMessage()]);
                }
            }
        });
        $integration->forceFill(['products_synced_at' => now()])->save();
        return $n;
    }

    public function upsertProduct(WoocommerceIntegration $integration, array $p): WaProduct
    {
        $images   = is_array($p['images'] ?? null) ? $p['images'] : [];
        $imageUrl = $images[0]['src'] ?? null;
        $gallery  = array_values(array_filter(array_map(fn ($i) => $i['src'] ?? null, $images)));

        // Woo: `price` = effective selling price; `regular_price` = list price.
        // Show a strike-through compare price only when actually discounted.
        $price   = $p['price'] ?? $p['regular_price'] ?? null;
        $regular = $p['regular_price'] ?? null;
        $priceMinor   = $this->toMinor($price);
        $compareMinor = ($regular !== null && $this->toMinor($regular) > $priceMinor) ? $this->toMinor($regular) : 0;

        $statusMap = ['publish' => 'active', 'draft' => 'draft', 'pending' => 'draft', 'private' => 'draft', 'trash' => 'archived'];

        return WaProduct::updateOrCreate(
            [
                'workspace_id'   => $integration->workspace_id,
                'woo_product_id' => (string) ($p['id'] ?? ''),
            ],
            [
                'user_id'             => $integration->user_id,
                'sku'                 => (string) (($p['sku'] ?? '') ?: ('WOO-' . ($p['id'] ?? ''))),
                'name'                => (string) ($p['name'] ?? 'Untitled'),
                'slug'                => (string) ($p['slug'] ?? Str::slug((string) ($p['name'] ?? ''))),
                'description'         => trim(strip_tags((string) ($p['short_description'] ?? $p['description'] ?? ''))),
                'body_html'           => (string) ($p['description'] ?? ''),
                'price_minor'         => $priceMinor,
                'compare_price_minor' => $compareMinor,
                'currency_code'       => $integration->store_currency ?: 'USD',
                'image_url'           => $imageUrl,
                'gallery_json'        => $gallery,
                'in_stock'            => WoocommerceStockService::deriveInStock($p),
                'status'              => $statusMap[strtolower((string) ($p['status'] ?? 'publish'))] ?? 'active',
                'category'            => $this->firstName($p['categories'] ?? null),
                'brand'               => $this->firstName($p['brands'] ?? null),
                'product_url'         => (string) ($p['permalink'] ?? ($integration->store_url . '/?p=' . ($p['id'] ?? ''))),
                'tags_json'           => $this->tagNames($p['tags'] ?? null),
            ],
        );
    }

    // -----------------------------------------------------------------
    // Orders → wa_orders
    // -----------------------------------------------------------------

    public function importOrders(WoocommerceIntegration $integration): int
    {
        $rows = $this->woo->getOrders($integration, 100);
        $n = 0;
        foreach ($rows as $o) {
            try {
                $this->upsertOrder($integration, $o);
                $n++;
            } catch (\Throwable $e) {
                Log::warning('[WoocommerceImporter] order upsert failed', ['id' => $o['id'] ?? null, 'err' => $e->getMessage()]);
            }
        }
        $integration->forceFill(['orders_synced_at' => now()])->save();
        return $n;
    }

    public function upsertOrder(WoocommerceIntegration $integration, array $o): WaOrder
    {
        $billing = is_array($o['billing'] ?? null) ? $o['billing'] : [];
        $name    = trim((string) ($billing['first_name'] ?? '') . ' ' . (string) ($billing['last_name'] ?? '')) ?: null;
        // Store the order's phone already E.164-normalised (via billing country)
        // so the dashboard, offers, win-back and stock waitlist all read a
        // reachable number. See App\Support\Woo\WooPhone.
        $phone   = \App\Support\Woo\WooPhone::fromOrder($o);

        $items = array_map(fn ($li) => [
            'title' => $li['name'] ?? '',
            'qty'   => (int) ($li['quantity'] ?? 1),
            'price' => $li['price'] ?? ($li['total'] ?? '0'),
            'sku'   => $li['sku'] ?? null,
        ], is_array($o['line_items'] ?? null) ? $o['line_items'] : []);

        return WaOrder::updateOrCreate(
            [
                'workspace_id' => $integration->workspace_id,
                'woo_order_id' => (string) ($o['id'] ?? ''),
            ],
            [
                'source'         => 'woocommerce',
                'customer_phone' => $phone ? preg_replace('/\D+/', '', (string) $phone) : null,
                'customer_name'  => $name,
                'customer_email' => $billing['email'] ?? null,
                'items_json'     => $items,
                'total_minor'    => $this->toMinor($o['total'] ?? null),
                'currency_code'  => $o['currency'] ?? $integration->store_currency ?: 'USD',
                'status'         => $this->mapOrderStatus($o),
                'meta_json'      => [
                    'name'                 => '#' . ($o['number'] ?? $o['id'] ?? ''),
                    'number'               => $o['number'] ?? null,
                    'order_number'         => $o['number'] ?? $o['id'] ?? null,
                    'woo_status'           => $o['status'] ?? null,
                    'payment_method'       => $o['payment_method'] ?? null,
                    'payment_method_title' => $o['payment_method_title'] ?? null,
                    'customer_id'          => $o['customer_id'] ?? null,
                    'created_at'           => $o['date_created'] ?? null,
                    // Invoice PDF from the store's invoice plugin (forwarded field
                    // or common meta keys), so an order-placed FLOW can send it via
                    // {{invoice_url}}. Empty when the store has no PDF plugin.
                    'invoice_url'          => $this->wooInvoiceUrl($o),
                ],
            ],
        );
    }

    /**
     * Invoice PDF URL for a Woo order — from a forwarded field the companion
     * plugin adds, or a common PDF-invoice plugin's stored order meta key.
     * https-only (WhatsApp document sends need a public https URL). Empty when
     * the store has no PDF plugin, in which case the flow/event uses the order
     * page instead. (Mirror of WoocommerceController::invoiceUrlFromOrder.)
     */
    private function wooInvoiceUrl(array $o): string
    {
        $pick = fn ($v) => (is_string($v) && str_starts_with(trim($v), 'https://')) ? trim($v) : '';
        foreach (['invoice_url', 'invoice_pdf_url', 'pdf_invoice_url'] as $k) {
            if ($u = $pick($o[$k] ?? '')) return $u;
        }
        $wanted = [
            '_wcpdf_invoice_url', '_wcpdf_document_link', '_wpo_wcpdf_invoice_url',
            '_invoice_url', '_pdf_invoice_url', 'invoice_url', 'ywpi_invoice_url',
        ];
        foreach ((is_array($o['meta_data'] ?? null) ? $o['meta_data'] : []) as $m) {
            if (in_array((string) ($m['key'] ?? ''), $wanted, true) && ($u = $pick($m['value'] ?? ''))) {
                return $u;
            }
        }
        return '';
    }

    // -----------------------------------------------------------------
    // Customers → contacts
    // -----------------------------------------------------------------

    public function importCustomers(WoocommerceIntegration $integration): int
    {
        $rows = $this->woo->getCustomers($integration, 100);
        if (!$rows) {
            $integration->forceFill(['customers_synced_at' => now()])->save();
            return 0;
        }

        $groupId = $this->wooGroupId($integration);
        $n = 0;
        foreach ($rows as $c) {
            $phone = \App\Support\Woo\WooPhone::fromOrder($c) ?? ''; // E.164 via billing country
            if ($phone === '') continue; // WhatsApp needs a number
            try {
                $this->upsertContact($integration, $c, $phone, $groupId);
                $n++;
            } catch (\Throwable $e) {
                Log::warning('[WoocommerceImporter] customer upsert failed', ['id' => $c['id'] ?? null, 'err' => $e->getMessage()]);
            }
        }
        $integration->forceFill(['customers_synced_at' => now()])->save();
        return $n;
    }

    private function upsertContact(WoocommerceIntegration $integration, array $c, string $phone, ?int $groupId): void
    {
        // Contacts encrypt mobile at rest → can't WHERE on it; match within
        // the workspace by decrypting a bounded set, else create.
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
                'woo_customer_id' => (string) ($c['id'] ?? ''),
                'orders_count'    => (int) ($c['orders_count'] ?? 0),
                'total_spent'     => (string) ($c['total_spent'] ?? '0'),
            ],
        ]);
    }

    /** Find-or-create the "WooCommerce Customers" group; cache its id. */
    private function wooGroupId(WoocommerceIntegration $integration): ?int
    {
        $meta = $integration->metadata ?? [];
        if (!empty($meta['woo_group_id']) && ContactGroup::whereKey($meta['woo_group_id'])->exists()) {
            return (int) $meta['woo_group_id'];
        }
        try {
            $group = ContactGroup::create([
                'workspace_id' => $integration->workspace_id,
                'user_id'      => $integration->user_id,
                'user_group'   => 'WooCommerce Customers',
                'status'       => true,
                'color'        => '#7F54B3', // WooCommerce purple
            ]);
            $meta['woo_group_id'] = $group->id;
            $integration->update(['metadata' => $meta]);
            return $group->id;
        } catch (\Throwable $e) {
            Log::warning('[WoocommerceImporter] group create failed: ' . $e->getMessage());
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

    /** Woo taxonomies come back as [{id,name,slug}, ...]. */
    private function firstName($list): ?string
    {
        if (is_array($list) && isset($list[0]['name'])) return (string) $list[0]['name'];
        return null;
    }

    private function tagNames($tags): array
    {
        if (is_array($tags)) {
            return array_values(array_filter(array_map(fn ($t) => is_array($t) ? ($t['name'] ?? null) : (string) $t, $tags)));
        }
        return [];
    }

    private function mapOrderStatus(array $o): string
    {
        $s = strtolower((string) ($o['status'] ?? ''));
        if (in_array($s, ['cancelled', 'refunded', 'failed'], true)) return 'cancelled';
        if ($s === 'completed') return 'shipped';
        if ($s === 'processing') return 'paid';
        // pending, on-hold, checkout-draft, and unknown custom statuses → new
        return 'new';
    }
}
