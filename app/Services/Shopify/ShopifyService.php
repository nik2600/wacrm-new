<?php

namespace App\Services\Shopify;

use App\Models\ShopifyIntegration;
use App\Models\SystemSetting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Thin HTTP client for Shopify's Admin REST API + OAuth flow.
 *
 * Admin-level credentials (client_id, client_secret, default scopes,
 * redirect URI) come from `system_settings` so a single admin owner can
 * issue Shopify app credentials and every workspace re-uses them.
 *
 * Per-integration secrets (access_token, webhook_secret) live on the
 * `shopify_integrations` row.
 */
class ShopifyService
{
    /**
     * Current stable REST Admin API version. Shopify ships a new version
     * every quarter; bump this when the next stable rolls out. The
     * existing version stays supported for 12 months after rollover.
     */
    public const API_VERSION = '2026-04';

    /** Single timeout for every HTTP call. */
    private const HTTP_TIMEOUT_SECONDS = 15;

    public const WEBHOOK_TOPICS = [
        'orders/create',
        'orders/updated',
        'orders/paid',
        'orders/fulfilled',
        'orders/cancelled',
        // Shipment lifecycle — carries shipment_status (in_transit /
        // out_for_delivery / delivered) for the Delivered automation.
        'fulfillments/update',
        'refunds/create',
        'products/update',
        'customers/create',
        'customers/update',
        'checkouts/create',
        // Cleanup hook: Shopify calls this once when the merchant
        // uninstalls the app. We use it to wipe the access_token so
        // we don't keep calling a revoked credential.
        'app/uninstalled',
    ];

    public const DEFAULT_SCOPES = 'read_products,read_orders,write_orders,read_customers,read_checkouts,read_inventory';

    // ---------------------------------------------------------------------
    // Admin settings (from system_settings)
    // ---------------------------------------------------------------------

    public function clientId(): string     { return (string) SystemSetting::get('shopify_client_id', ''); }
    public function clientSecret(): string { return (string) SystemSetting::get('shopify_client_secret', ''); }
    public function scopes(): string       { return (string) SystemSetting::get('shopify_scopes', self::DEFAULT_SCOPES); }
    public function redirectUri(): string  { return (string) (SystemSetting::get('shopify_redirect_uri') ?: url('/shopify/oauth/callback')); }
    public function isEnabled(): bool      { return (bool) SystemSetting::get('shopify_enabled', false); }

    // ---------------------------------------------------------------------
    // OAuth flow
    // ---------------------------------------------------------------------

    /**
     * Build the OAuth authorize URL Shopify documents:
     *   https://{shop}.myshopify.com/admin/oauth/authorize
     * The Unified Admin variant (admin.shopify.com/store/{handle}/…)
     * silently 404s for some sub-region stores; the per-shop host is
     * the spec-compliant path that always works.
     */
    public function authorizeUrl(string $shop, string $state): string
    {
        $shop = $this->normalizeShop($shop);

        return 'https://' . $shop . '/admin/oauth/authorize?' . http_build_query([
            'client_id'    => $this->clientId(),
            'scope'        => $this->scopes(),
            'redirect_uri' => $this->redirectUri(),
            'state'        => $state,
        ]);
    }

    public function verifyOAuthHmac(array $query): bool
    {
        $hmac = (string) ($query['hmac'] ?? '');
        unset($query['hmac'], $query['signature']);
        ksort($query);
        $expected = hash_hmac('sha256', http_build_query($query), $this->clientSecret());
        return $hmac !== '' && hash_equals($expected, $hmac);
    }

    /** @return array{success:bool, access_token?:string, scope?:string, error?:string} */
    public function exchangeCode(string $shop, string $code): array
    {
        $shop = $this->normalizeShop($shop);
        try {
            $r = Http::timeout(self::HTTP_TIMEOUT_SECONDS)
                ->post("https://{$shop}/admin/oauth/access_token", [
                    'client_id'     => $this->clientId(),
                    'client_secret' => $this->clientSecret(),
                    'code'          => $code,
                ]);
            if ($r->successful()) {
                return ['success' => true, 'access_token' => $r->json('access_token'), 'scope' => $r->json('scope', '')];
            }
            return ['success' => false, 'error' => $r->json('error_description') ?: $r->json('error') ?: ('HTTP ' . $r->status())];
        } catch (\Throwable $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    // ---------------------------------------------------------------------
    // Shop info
    // ---------------------------------------------------------------------

    /** @return array{success:bool, shop?:array, error?:string} */
    public function getShop(string $shop, string $accessToken): array
    {
        try {
            $r = $this->client($shop, $accessToken)->get($this->base($shop) . '/shop.json');
            if ($r->successful()) return ['success' => true, 'shop' => $r->json('shop', [])];
            return ['success' => false, 'error' => 'HTTP ' . $r->status()];
        } catch (\Throwable $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Workspace-wide counts. Each resource has its own /count.json endpoint
     * on the Shopify Admin REST API. `orders/count.json` requires an
     * explicit status filter — the others don't.
     */
    public function getStoreCounts(ShopifyIntegration $integration): array
    {
        $resources = [
            'products'  => [],
            'orders'    => ['status' => 'any'],
            'customers' => [],
        ];
        $counts = [];
        foreach ($resources as $resource => $params) {
            $counts[$resource] = 0;
            try {
                $r = $this->client($integration->store_url, $integration->access_token)
                    ->get($this->base($integration->store_url) . "/{$resource}/count.json", $params);
                if ($r->successful()) {
                    $counts[$resource] = (int) $r->json('count', 0);
                }
            } catch (\Throwable $e) {
                // best-effort — leave at 0
            }
        }
        return $counts;
    }

    public function getOrders(ShopifyIntegration $integration, int $limit = 20): array
    {
        try {
            $r = $this->client($integration->store_url, $integration->access_token)
                ->get($this->base($integration->store_url) . '/orders.json', [
                    'limit'  => max(1, min(250, $limit)),
                    'status' => 'any',
                    'fields' => 'id,name,email,phone,created_at,cancelled_at,total_price,currency,financial_status,fulfillment_status,line_items,customer,shipping_address,billing_address,order_number',
                ]);
            return $r->successful() ? $r->json('orders', []) : [];
        } catch (\Throwable $e) {
            return [];
        }
    }

    public function getProducts(ShopifyIntegration $integration, int $limit = 20): array
    {
        try {
            $r = $this->client($integration->store_url, $integration->access_token)
                ->get($this->base($integration->store_url) . '/products.json', [
                    'limit'  => max(1, min(250, $limit)),
                    'fields' => 'id,title,handle,body_html,tags,vendor,product_type,status,images,image,variants,created_at',
                ]);
            return $r->successful() ? $r->json('products', []) : [];
        } catch (\Throwable $e) {
            return [];
        }
    }

    public function getCustomers(ShopifyIntegration $integration, int $limit = 20): array
    {
        try {
            $r = $this->client($integration->store_url, $integration->access_token)
                ->get($this->base($integration->store_url) . '/customers.json', [
                    'limit'  => max(1, min(250, $limit)),
                    'fields' => 'id,first_name,last_name,email,phone,orders_count,total_spent,created_at',
                ]);
            return $r->successful() ? $r->json('customers', []) : [];
        } catch (\Throwable $e) {
            return [];
        }
    }

    // ---------------------------------------------------------------------
    // Webhooks
    // ---------------------------------------------------------------------

    public function registerWebhooks(ShopifyIntegration $integration): array
    {
        $registered = [];
        $address    = url('/shopify/webhook/' . $integration->webhook_secret);

        foreach (self::WEBHOOK_TOPICS as $topic) {
            try {
                $r = $this->client($integration->store_url, $integration->access_token)
                    ->post($this->base($integration->store_url) . '/webhooks.json', [
                        'webhook' => ['topic' => $topic, 'address' => $address, 'format' => 'json'],
                    ]);
                $id = (string) ($r->json('webhook.id') ?? '');
                if ($r->successful() && $id !== '') {
                    $registered[$topic] = $id;
                }
            } catch (\Throwable $e) {
                Log::warning('[SHOPIFY] register webhook failed', ['topic' => $topic, 'error' => $e->getMessage()]);
            }
        }

        $meta = $integration->metadata ?? [];
        $meta['webhook_ids'] = $registered;
        $integration->update(['metadata' => $meta]);

        return $registered;
    }

    public function deleteWebhooks(ShopifyIntegration $integration): void
    {
        $ids = $integration->metadata['webhook_ids'] ?? [];
        foreach ($ids as $id) {
            if (!$id) continue;
            try {
                $this->client($integration->store_url, $integration->access_token)
                    ->delete($this->base($integration->store_url) . "/webhooks/{$id}.json");
            } catch (\Throwable $e) {
                // best-effort
            }
        }
        $meta = $integration->metadata ?? [];
        $meta['webhook_ids'] = [];
        $integration->update(['metadata' => $meta]);
    }

    public function verifyWebhookSignature(string $payload, string $headerHmac): bool
    {
        $expected = base64_encode(hash_hmac('sha256', $payload, $this->clientSecret(), true));
        return $headerHmac !== '' && hash_equals($expected, $headerHmac);
    }

    // ---------------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------------

    private function client(string $shop, string $accessToken)
    {
        return Http::withHeaders(['X-Shopify-Access-Token' => $accessToken])
            ->timeout(self::HTTP_TIMEOUT_SECONDS)
            ->acceptJson();
    }

    private function base(string $shop): string
    {
        return 'https://' . $this->normalizeShop($shop) . '/admin/api/' . self::API_VERSION;
    }

    public function normalizeShop(string $shop): string
    {
        // `i` flag so HTTPS:// / Http:// also strip cleanly.
        $shop = preg_replace('#^https?://#i', '', trim($shop));
        $shop = rtrim($shop, '/');
        if (str_contains($shop, '/')) $shop = explode('/', $shop, 2)[0];
        $shop = strtolower($shop);
        // The UI accepts JUST the store handle (the field shows a ".myshopify.com"
        // suffix and says "just the store handle"). Append the domain so a bare
        // handle like "not-just-vanilla-web" validates instead of erroring until
        // the user pastes the full URL. A handle has no dot; a full domain does.
        if ($shop !== '' && !str_contains($shop, '.')) {
            $shop .= '.myshopify.com';
        }
        return $shop;
    }

    public function isValidShop(string $shop): bool
    {
        return (bool) preg_match('/^[a-z0-9][a-z0-9\-]*\.myshopify\.com$/i', $this->normalizeShop($shop));
    }
}
