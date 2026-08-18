<?php

namespace App\Services\Commerce;

use App\Models\WoocommerceIntegration;
use Illuminate\Support\Facades\Http;

/**
 * Mint a single-use WooCommerce checkout URL the customer can tap from
 * WhatsApp. Strategy: hit WC's Store API `add-to-cart` per item and
 * append a `cart_session` cookie that the customer keeps when they
 * land on the checkout page.
 *
 * We pass the flow's session id through `customer_note` so the
 * `woocommerce/webhook/{secret}` handler can route order.created back
 * to the right paused flow.
 */
class WoocommerceCheckoutLinkBuilder
{
    public function mint(int $storeId, array $items, ?string $sessionId): array
    {
        $store = WoocommerceIntegration::findOrFail($storeId);

        // store_url must include a protocol; older `connect` paths
        // sometimes stored bare hostnames. Force https:// if missing.
        $rawUrl = (string) $store->store_url;
        if (!preg_match('#^https?://#i', $rawUrl)) {
            $rawUrl = 'https://' . ltrim($rawUrl, '/');
        }
        $base = rtrim($rawUrl, '/') . '/wp-json/wc/v3';

        // WooCommerce REST: create a Draft Order with line items + a
        // meta_data entry tagging the flow session. Draft Orders give
        // us a single payment URL the customer can finish at.
        //
        // WC accepts EITHER `product_id` (numeric) OR `sku` (string).
        // The flow's retailer_id holds whichever was set — fall back
        // to product_id when the retailer_id is purely numeric, since
        // products without SKU populated otherwise crash here.
        $lineItems = array_map(function ($i) {
            $rid = (string) $i['retailer_id'];
            $entry = ['quantity' => max(1, (int) $i['qty'])];
            if (ctype_digit($rid)) {
                $entry['product_id'] = (int) $rid;
            } else {
                $entry['sku'] = $rid;
            }
            return $entry;
        }, $items);

        // meta_data — only attach the session id when set so we don't
        // POST null to WC (which would store as empty and break the
        // webhook resolver). array_values to drop the gap left by
        // null filtering.
        $metaData = array_values(array_filter([
            $sessionId ? ['key' => '_wa_flow_session', 'value' => $sessionId] : null,
            ['key' => '_wa_origin', 'value' => 'wadesk-flow'],
        ]));

        $payload = [
            'status'     => 'pending',
            'line_items' => $lineItems,
            'meta_data'  => $metaData,
        ];

        $resp = Http::withBasicAuth($store->consumer_key, $store->consumer_secret)
            ->acceptJson()
            ->timeout(20)
            ->post($base . '/orders', $payload);
        if (!$resp->successful()) {
            throw new \RuntimeException('WC create-order ' . $resp->status() . ': ' . substr($resp->body(), 0, 200));
        }
        $order = $resp->json();
        $url = (string) ($order['payment_url'] ?? '');
        if ($url === '') {
            // Older WC versions don't include payment_url — synthesize
            // it from the order key. Use the protocol-normalized
            // $rawUrl so the link is always tappable (the raw
            // store_url might be a bare hostname).
            $key = (string) ($order['order_key'] ?? '');
            $id  = (int) ($order['id'] ?? 0);
            if ($id && $key) {
                $url = rtrim($rawUrl, '/') . '/checkout/order-pay/' . $id . '/?pay_for_order=true&key=' . $key;
            }
        }
        return [
            'url'        => $url,
            'order_id'   => $order['id'] ?? null,
            'currency'   => $store->store_currency,
            'expires_at' => null, // WC draft orders don't auto-expire by default
        ];
    }
}
