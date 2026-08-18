<?php

namespace App\Services\Commerce;

use App\Models\ShopifyIntegration;
use Illuminate\Support\Facades\Http;

/**
 * Mint a Shopify cart `checkoutUrl` via the Storefront API
 * `cartCreate` mutation. The URL takes the customer straight to
 * Shopify-hosted checkout (supports Shop Pay, Apple Pay, etc.).
 *
 * We tuck the flow session id into the cart's `note` so the
 * `orders/create` webhook can route the resulting order back to the
 * paused flow node.
 */
class ShopifyCheckoutLinkBuilder
{
    public function mint(int $storeId, array $items, ?string $sessionId): array
    {
        $store = ShopifyIntegration::findOrFail($storeId);

        // Resolve product retailer_id (which we stored as SKU in the
        // flow JSON) → Storefront variant gid. We hit the Admin API
        // `products.json?fields=variants` since we already have an
        // admin access_token; Storefront API would need a separate
        // token.
        $variantGids = [];
        foreach ($items as $i) {
            $sku = (string) $i['retailer_id'];
            $gid = $this->resolveVariantGid($store, $sku);
            if ($gid) {
                $variantGids[] = [
                    'merchandiseId' => $gid,
                    'quantity'      => max(1, (int) $i['qty']),
                ];
            }
        }
        if (empty($variantGids)) {
            throw new \RuntimeException('No matching Shopify variants found for the picked products.');
        }

        // Storefront API GraphQL — public unauthenticated against
        // the shop's domain. Falls back to admin draft-order if the
        // Storefront access token isn't configured.
        // Strip protocol if accidentally stored on store_url; Shopify
        // expects a bare myshopify.com hostname here.
        $shopHost = preg_replace('#^https?://#i', '', (string) $store->store_url);
        $shopHost = rtrim($shopHost, '/');

        $storefrontToken = $store->metadata['storefront_access_token'] ?? null;
        if ($storefrontToken) {
            $url = 'https://' . $shopHost . '/api/2024-04/graphql.json';
            // Only attach the note when we have a sessionId — passing
            // null would land in Shopify as the literal string "null"
            // on some API versions and break webhook routing.
            $cartInput = ['lines' => $variantGids];
            if ($sessionId) $cartInput['note'] = 'wadesk-flow:' . $sessionId;
            $resp = Http::withHeaders(['X-Shopify-Storefront-Access-Token' => $storefrontToken])
                ->acceptJson()
                ->timeout(20)
                ->post($url, [
                    'query' => 'mutation create($input: CartInput!){ cartCreate(input:$input){ cart{ id checkoutUrl } userErrors{ message } } }',
                    'variables' => [ 'input' => $cartInput ],
                ]);
            if ($resp->successful()) {
                $cart = $resp->json('data.cartCreate.cart');
                if (!empty($cart['checkoutUrl'])) {
                    return [
                        'url'        => $cart['checkoutUrl'],
                        'cart_id'    => $cart['id'] ?? null,
                        'currency'   => $store->shop_currency,
                        'expires_at' => now()->addMinutes(10)->toIso8601String(),
                    ];
                }
            }
        }

        // Fallback: admin Draft Order — slower checkout but works
        // without a Storefront token.
        $draftPayload = [
            'line_items' => array_map(fn ($v) => ['variant_id' => (int) preg_replace('/\D+/', '', $v['merchandiseId']), 'quantity' => $v['quantity']], $variantGids),
            'tags'       => 'wadesk-flow',
        ];
        if ($sessionId) $draftPayload['note'] = 'wadesk-flow:' . $sessionId;

        $adminResp = Http::withHeaders(['X-Shopify-Access-Token' => $store->access_token])
            ->acceptJson()
            ->timeout(20)
            ->post('https://' . $shopHost . '/admin/api/2024-04/draft_orders.json', [
                'draft_order' => $draftPayload,
            ]);
        if (!$adminResp->successful()) {
            throw new \RuntimeException('Shopify draft-order ' . $adminResp->status() . ': ' . substr($adminResp->body(), 0, 200));
        }
        $draft = $adminResp->json('draft_order');
        return [
            'url'        => (string) ($draft['invoice_url'] ?? ''),
            'cart_id'    => $draft['id'] ?? null,
            'currency'   => $store->shop_currency,
            'expires_at' => null,
        ];
    }

    /**
     * Find the variant GraphQL ID for a given SKU. Uses the admin
     * products endpoint; results are tiny so no cache here — the
     * controller's 60-s cache covers the parent product list.
     */
    private function resolveVariantGid(ShopifyIntegration $store, string $sku): ?string
    {
        $shopHost = rtrim(preg_replace('#^https?://#i', '', (string) $store->store_url), '/');
        $resp = Http::withHeaders(['X-Shopify-Access-Token' => $store->access_token])
            ->acceptJson()
            ->timeout(15)
            ->get('https://' . $shopHost . '/admin/api/2024-04/variants.json', ['limit' => 1, 'sku' => $sku]);
        if (!$resp->successful()) return null;
        $variants = $resp->json('variants') ?: [];
        if (empty($variants)) return null;
        $v = $variants[0];
        $id = (int) ($v['id'] ?? 0);
        if ($id <= 0) return null;
        return 'gid://shopify/ProductVariant/' . $id;
    }
}
