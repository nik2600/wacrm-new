<?php

namespace App\Services\Commerce;

use App\Models\WaCatalog;
use App\Models\WaProduct;
use App\Models\WaStorefront;

/**
 * WA-catalog products on a Baileys device — they can't checkout
 * natively (no WA Pay), so we hand them to the workspace's
 * `wa_storefronts` payment_provider (Razorpay/Stripe configured in
 * the storefront wizard). The returned URL points at the storefront's
 * public landing page (route `/s/{slug}`). The cart-prefill query
 * params (`items=SKU:qty,...&wa=<session>`) are passed through so a
 * future storefront enhancement can auto-add the products to the
 * customer's cart — until that's wired up, the customer lands on the
 * storefront home page and adds the product themselves.
 */
class WhatsappShopCheckoutLinkBuilder
{
    public function mint(int $catalogId, array $items, ?string $sessionId): array
    {
        $catalog = WaCatalog::findOrFail($catalogId);
        $store   = WaStorefront::where('workspace_id', $catalog->workspace_id)->where('enabled', true)->first();
        if (!$store) {
            throw new \RuntimeException('No active storefront for this workspace — set one up in /store/storefront to take payments on Baileys catalog orders.');
        }

        // Storefront base — custom verified domain first, then the
        // canonical `/s/{slug}` short URL the StorefrontPublicController
        // serves. Older code used `/shop/{slug}` which 404s today.
        $base = $store->custom_domain && $store->custom_domain_verified
            ? 'https://' . $store->custom_domain
            : url('/s/' . $store->slug);

        // Query params the storefront can consume to prefill a cart.
        // Today the storefront's public route doesn't yet act on
        // these — the customer lands on the home page and adds the
        // products manually. We still pass them so the upgrade is
        // a server-only change with no link format break.
        $skus  = array_map(fn ($i) => $i['retailer_id'] . ':' . max(1, (int) $i['qty']), $items);
        $query = ['items' => implode(',', $skus)];
        if ($sessionId) $query['wa'] = $sessionId;
        $url   = $base . '?' . http_build_query($query);

        return [
            'url'        => $url,
            'cart_id'    => null,
            'currency'   => $store->currency_code,
            'expires_at' => null,
        ];
    }
}
