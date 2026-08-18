<?php

namespace App\Services\Woocommerce;

use App\Models\SystemSetting;
use App\Models\WoocommerceIntegration;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * HTTP client for the WooCommerce REST API + webhook helpers.
 *
 * Auth: Basic Auth with consumer key (username) + consumer secret (password).
 * Per the May 2026 WC developer docs HTTPS is recommended and that's what
 * we expect — we don't fall back to OAuth1 query-param signing, which the
 * docs only suggest for HTTP sites.
 *
 * The "admin settings" surface is intentionally tiny: WooCommerce has no
 * central app to register, so the admin just toggles the feature on or
 * off and customers paste per-store credentials themselves.
 */
class WoocommerceService
{
    public const API_VERSION = 'v3';

    /** Single timeout for every HTTP call — keeps test/list/sync consistent. */
    private const HTTP_TIMEOUT_SECONDS = 15;

    /**
     * Webhook topics we expose in the user UI. The WooCommerce docs list
     * `coupon.*`, `order.*`, `customer.*`, `product.*` plus `restored`
     * variants — we pick the ones with the highest messaging value.
     */
    public const WEBHOOK_TOPICS = [
        'order.created',
        'order.updated',
        'order.deleted',
        'customer.created',
        'customer.updated',
        'product.created',
        'product.updated',
    ];

    // ---------------------------------------------------------------------
    // Admin settings (from system_settings)
    // ---------------------------------------------------------------------

    public function isEnabled(): bool
    {
        return (bool) SystemSetting::get('woocommerce_enabled', false);
    }

    // ---------------------------------------------------------------------
    // Connection lifecycle
    // ---------------------------------------------------------------------

    /**
     * Test a candidate (url, key, secret) triple by hitting /system_status.
     * If the call succeeds we return the store metadata so the caller can
     * persist it on the integration row.
     *
     * @return array{ok:bool, error?:string, store?:array}
     */
    public function testConnection(string $storeUrl, string $consumerKey, string $consumerSecret): array
    {
        $url = $this->base($storeUrl) . '/system_status';
        try {
            $r = Http::withBasicAuth($consumerKey, $consumerSecret)
                ->timeout(self::HTTP_TIMEOUT_SECONDS)
                ->acceptJson()
                ->get($url);
            if ($r->successful()) {
                $env      = $r->json('environment', []);
                $settings = $r->json('settings', []);
                // The WC system_status `environment` object doesn't carry
                // a site_title in all versions — fall back to the host
                // from home_url so the store always has *some* label.
                $homeHost = parse_url((string) ($env['home_url'] ?? ''), PHP_URL_HOST);
                return [
                    'ok'    => true,
                    'store' => [
                        'name'       => $env['site_title'] ?? $homeHost,
                        'currency'   => $settings['currency'] ?? null,
                        'country'    => $env['default_country'] ?? null,
                        'wc_version' => $env['version'] ?? null,
                    ],
                ];
            }
            $errMsg = $r->json('message') ?: \Illuminate\Support\Str::limit($r->body(), 200);
            return ['ok' => false, 'error' => 'HTTP ' . $r->status() . ': ' . $errMsg];
        } catch (\Throwable $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    // ---------------------------------------------------------------------
    // Resources
    // ---------------------------------------------------------------------

    public function getOrders(WoocommerceIntegration $integration, int $limit = 20): array
    {
        try {
            $r = $this->client($integration)
                ->get($this->base($integration->store_url) . '/orders', [
                    'per_page' => max(1, min(100, $limit)),
                    'orderby'  => 'date',
                    'order'    => 'desc',
                ]);
            return $r->successful() ? ($r->json() ?: []) : [];
        } catch (\Throwable $e) {
            return [];
        }
    }

    public function getProducts(WoocommerceIntegration $integration, int $limit = 20): array
    {
        try {
            $r = $this->client($integration)
                ->get($this->base($integration->store_url) . '/products', [
                    'per_page' => max(1, min(100, $limit)),
                    'orderby'  => 'date',
                    'order'    => 'desc',
                ]);
            return $r->successful() ? ($r->json() ?: []) : [];
        } catch (\Throwable $e) {
            return [];
        }
    }

    public function getCustomers(WoocommerceIntegration $integration, int $limit = 20): array
    {
        try {
            $r = $this->client($integration)
                ->get($this->base($integration->store_url) . '/customers', [
                    'per_page' => max(1, min(100, $limit)),
                    'orderby'  => 'registered_date',
                    'order'    => 'desc',
                ]);
            return $r->successful() ? ($r->json() ?: []) : [];
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * WC has no /count.json — it returns totals in the X-WP-Total header
     * on a per_page=1 request, which is the canonical way to get a count.
     */
    public function getStoreCounts(WoocommerceIntegration $integration): array
    {
        $counts = ['products' => 0, 'orders' => 0, 'customers' => 0];
        foreach (array_keys($counts) as $resource) {
            try {
                $r = $this->client($integration)
                    ->get($this->base($integration->store_url) . "/{$resource}", ['per_page' => 1]);
                if ($r->successful()) {
                    $counts[$resource] = (int) $r->header('X-WP-Total', 0);
                }
            } catch (\Throwable $e) {
                // best-effort
            }
        }
        return $counts;
    }

    /** Last-30-day sales report. Returns the raw row or [] on failure. */
    public function getSalesReport(WoocommerceIntegration $integration, int $days = 30): array
    {
        try {
            $r = $this->client($integration)
                ->get($this->base($integration->store_url) . '/reports/sales', [
                    'date_min' => now()->subDays($days)->toDateString(),
                    'date_max' => now()->toDateString(),
                ]);
            $rows = $r->successful() ? ($r->json() ?: []) : [];
            return $rows[0] ?? [];
        } catch (\Throwable $e) {
            return [];
        }
    }

    // ---------------------------------------------------------------------
    // Webhooks
    // ---------------------------------------------------------------------

    /**
     * Flip a WooCommerce order's status via the REST API (PUT /orders/{id}).
     * Used by COD confirmation (processing on Yes, cancelled on No) and the
     * COD→prepaid flow. Returns true on success.
     */
    public function updateOrderStatus(WoocommerceIntegration $integration, $orderId, string $status): bool
    {
        $orderId = preg_replace('/\D+/', '', (string) $orderId);
        if ($orderId === '') return false;
        try {
            $r = $this->client($integration)
                ->put($this->base($integration->store_url) . '/orders/' . $orderId, ['status' => $status]);
            return $r->successful();
        } catch (\Throwable $e) {
            Log::warning('[WC] update order status failed', ['order' => $orderId, 'status' => $status, 'error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Patch arbitrary (safe) fields on a WooCommerce order via PUT /orders/{id}.
     * Used by the chat concierge's guided address edit. Returns true on success.
     */
    public function updateOrder(WoocommerceIntegration $integration, $orderId, array $payload): bool
    {
        $orderId = preg_replace('/\D+/', '', (string) $orderId);
        if ($orderId === '' || empty($payload)) return false;
        try {
            $r = $this->client($integration)
                ->put($this->base($integration->store_url) . '/orders/' . $orderId, $payload);
            return $r->successful();
        } catch (\Throwable $e) {
            Log::warning('[WC] update order failed', ['order' => $orderId, 'error' => $e->getMessage()]);
            return false;
        }
    }

    public function registerWebhooks(WoocommerceIntegration $integration): array
    {
        $deliveryUrl = url('/woocommerce/webhook/' . $integration->webhook_secret);
        $registered  = [];
        $appName     = \App\Models\SystemSetting::get('app_name', config('app.name', 'WaDesk'));

        foreach (self::WEBHOOK_TOPICS as $topic) {
            try {
                $r = $this->client($integration)
                    ->post($this->base($integration->store_url) . '/webhooks', [
                        'name'         => $appName . ' ' . $topic,
                        'topic'        => $topic,
                        'delivery_url' => $deliveryUrl,
                        'secret'       => $integration->webhook_secret,
                        'status'       => 'active',
                    ]);
                $id = (string) ($r->json('id') ?? '');
                if ($r->successful() && $id !== '') {
                    $registered[$topic] = $id;
                }
            } catch (\Throwable $e) {
                Log::warning('[WC] register webhook failed', ['topic' => $topic, 'error' => $e->getMessage()]);
            }
        }

        $meta = $integration->metadata ?? [];
        $meta['webhook_ids'] = $registered;
        $integration->update(['metadata' => $meta]);

        return $registered;
    }

    public function deleteWebhooks(WoocommerceIntegration $integration): void
    {
        $ids = $integration->metadata['webhook_ids'] ?? [];
        foreach ($ids as $id) {
            if (!$id) continue;
            try {
                $this->client($integration)
                    ->delete($this->base($integration->store_url) . "/webhooks/{$id}", ['force' => true]);
            } catch (\Throwable $e) {
                // best-effort
            }
        }
        $meta = $integration->metadata ?? [];
        $meta['webhook_ids'] = [];
        $integration->update(['metadata' => $meta]);
    }

    /**
     * Verify the WC webhook signature.
     *
     * WC sends `X-WC-Webhook-Signature` containing the base64-encoded
     * HMAC-SHA256 of the raw JSON payload, signed with the per-webhook
     * secret (which we set ourselves on registration to
     * `$integration->webhook_secret`).
     */
    public function verifyWebhookSignature(string $payload, string $headerSig, string $secret): bool
    {
        $expected = base64_encode(hash_hmac('sha256', $payload, $secret, true));
        return $headerSig !== '' && hash_equals($expected, $headerSig);
    }

    // ---------------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------------

    private function client(WoocommerceIntegration $integration)
    {
        return Http::withBasicAuth($integration->consumer_key, $integration->consumer_secret)
            ->timeout(self::HTTP_TIMEOUT_SECONDS)
            ->acceptJson();
    }

    public function base(string $storeUrl): string
    {
        return rtrim($this->normalizeUrl($storeUrl), '/') . '/wp-json/wc/' . self::API_VERSION;
    }

    /**
     * Accept https://example.com, example.com, https://example.com/wp-json
     * etc. and return a clean origin like `https://example.com`.
     */
    public function normalizeUrl(string $url): string
    {
        $url = trim($url);
        if (!preg_match('#^https?://#i', $url)) {
            $url = 'https://' . $url;
        }
        $parts = parse_url($url);
        if (!isset($parts['host'])) return '';
        $scheme = $parts['scheme'] ?? 'https';
        $host   = strtolower($parts['host']);
        $port   = isset($parts['port']) ? ':' . $parts['port'] : '';
        return $scheme . '://' . $host . $port;
    }

    public function isValidUrl(string $url): bool
    {
        $normalised = $this->normalizeUrl($url);
        return $normalised !== '' && filter_var($normalised, FILTER_VALIDATE_URL) !== false;
    }
}
