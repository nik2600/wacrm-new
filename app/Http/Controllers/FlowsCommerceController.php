<?php

namespace App\Http\Controllers;

use App\Models\ShopifyIntegration;
use App\Models\WaCatalog;
use App\Models\WaProduct;
use App\Models\WoocommerceIntegration;
use App\Services\Shopify\ShopifyService;
use App\Services\Woocommerce\WoocommerceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Builder-side commerce API — feeds the WhatsApp Shop / WooCommerce /
 * Shopify nodes' store dropdown + product picker.
 *
 * All endpoints are workspace-scoped (workspace.role middleware on the
 * route group). Product responses are normalised to a single shape
 * across providers so the React picker doesn't have to branch:
 *
 *   { id, retailer_id, name, sku, image, price_minor, currency, url, in_stock }
 *
 * The `id` is provider-native (WC product id, Shopify product gid,
 * wa_products.id). The `retailer_id` is what gets stored in the flow
 * JSON + used by Node at send time.
 */
class FlowsCommerceController extends Controller
{
    public function __construct(
        private readonly ShopifyService    $shopify,
        private readonly WoocommerceService $woo,
    ) {}

    /** GET /flows/api/commerce/stores?provider=shopify|woocommerce|whatsapp_shop */
    public function stores(Request $request): JsonResponse
    {
        $provider = $request->string('provider')->toString();
        $wsId     = (int) Auth::user()?->current_workspace_id;
        if (!$wsId) return response()->json(['ok' => false, 'error' => 'no_workspace'], 400);

        $rows = match ($provider) {
            'shopify' => ShopifyIntegration::query()
                ->where('workspace_id', $wsId)->where('status', 'active')
                ->orderByDesc('connected_at')
                ->get(['id', 'store_name', 'store_url', 'shop_currency', 'shop_country'])
                ->map(fn ($s) => [
                    'id'       => $s->id,
                    'name'     => $s->store_name ?: $s->store_url,
                    'url'      => $s->store_url,
                    'currency' => $s->shop_currency,
                    'country'  => $s->shop_country,
                    'status'   => 'active',
                ])
                ->values(),
            'woocommerce' => WoocommerceIntegration::query()
                ->where('workspace_id', $wsId)->where('status', 'active')
                ->orderByDesc('connected_at')
                ->get(['id', 'store_name', 'store_url', 'store_currency', 'store_country'])
                ->map(fn ($s) => [
                    'id'       => $s->id,
                    'name'     => $s->store_name ?: $s->store_url,
                    'url'      => $s->store_url,
                    'currency' => $s->store_currency,
                    'country'  => $s->store_country,
                    'status'   => 'active',
                ])
                ->values(),
            'whatsapp_shop' => WaCatalog::query()
                ->where('workspace_id', $wsId)
                ->get(['id', 'catalog_name', 'catalog_id', 'is_cart_enabled'])
                ->map(fn ($c) => [
                    'id'             => $c->id,
                    'name'           => $c->catalog_name ?: ('Catalog ' . $c->catalog_id),
                    'catalog_id'     => $c->catalog_id,
                    'is_cart_enabled'=> (bool) $c->is_cart_enabled,
                    'currency'       => null, // catalogs are mixed-currency
                    'status'         => 'active',
                ])
                ->values(),
            default => collect(),
        };

        return response()->json(['ok' => true, 'provider' => $provider, 'stores' => $rows]);
    }

    /**
     * GET /flows/api/commerce/stores/{storeId}/products
     *   ?provider=…&q=…&limit=20&offset=0
     *
     * Live-fetches from the provider's API (cached 60 s per store+query).
     */
    public function products(Request $request, int $storeId): JsonResponse
    {
        $provider = $request->string('provider')->toString();
        $q        = trim($request->string('q')->toString());
        $limit    = max(1, min(50, (int) $request->input('limit', 20)));
        $wsId     = (int) Auth::user()?->current_workspace_id;
        if (!$wsId) return response()->json(['ok' => false, 'error' => 'no_workspace'], 400);

        $cacheKey = "flow_commerce.products.{$provider}.{$storeId}.{$wsId}.{$q}.{$limit}";

        try {
            $items = Cache::remember($cacheKey, 60, function () use ($provider, $storeId, $wsId, $q, $limit) {
                return match ($provider) {
                    'shopify'       => $this->shopifyProducts($storeId, $wsId, $q, $limit),
                    'woocommerce'   => $this->woocommerceProducts($storeId, $wsId, $q, $limit),
                    'whatsapp_shop' => $this->catalogProducts($storeId, $wsId, $q, $limit),
                    default         => [],
                };
            });
        } catch (\Throwable $e) {
            Log::warning('flow-commerce products fetch failed: ' . $e->getMessage());
            return response()->json(['ok' => false, 'error' => 'fetch_failed', 'message' => $e->getMessage()], 502);
        }

        return response()->json(['ok' => true, 'provider' => $provider, 'products' => $items]);
    }

    /**
     * POST /flows/api/commerce/checkout-link
     * Node hits this from inside a Baileys-path commerce node to mint a
     * pay-link for ONE picked product (or N items as a cart) and shoot
     * it back at the customer. Returns { ok, url, expires_at? }.
     *
     * Body: { provider, store_id, items:[{retailer_id, qty}], session_id?, customer_phone? }
     * The session_id is opaque to us — we pass it back via the webhook
     * so the flow can resume on the matching node.
     */
    public function checkoutLink(Request $request): JsonResponse
    {
        // Node calls this via the existing X-Node-Token shared secret —
        // not a session-auth'd web user.
        // Shared-secret auth. Refuse if NODE_WEBHOOK_TOKEN env is empty,
        // otherwise hash_equals('', '') returns true and the endpoint
        // becomes anonymous in dev installs that forgot the env var.
        $expected = node_token();
        $token    = (string) $request->header('X-Node-Token', '');
        if ($expected === '' || !hash_equals($expected, $token)) {
            return response()->json(['ok' => false, 'error' => 'unauthorized'], 401);
        }
        $data = $request->validate([
            'provider'         => 'required|in:shopify,woocommerce,whatsapp_shop',
            'store_id'         => 'required|integer',
            'workspace_id'     => 'required|integer',
            'items'            => 'required|array|min:1|max:30',
            'items.*.retailer_id' => 'required|string',
            'items.*.qty'      => 'nullable|integer|min:1|max:99',
            'session_id'       => 'nullable|string|max:120',
            'customer_phone'   => 'nullable|string|max:32',
        ]);

        // Workspace scope check — the store_id MUST belong to the
        // workspace_id Node sent. Without this, a malformed flow
        // (or a forged X-Node-Token request) could mint a cart on
        // another workspace's store and leak revenue / orders.
        $storeOwnsWs = match ($data['provider']) {
            'shopify'       => \App\Models\ShopifyIntegration::where('workspace_id', $data['workspace_id'])->where('id', $data['store_id'])->exists(),
            'woocommerce'   => \App\Models\WoocommerceIntegration::where('workspace_id', $data['workspace_id'])->where('id', $data['store_id'])->exists(),
            'whatsapp_shop' => \App\Models\WaCatalog::where('workspace_id', $data['workspace_id'])->where('id', $data['store_id'])->exists(),
        };
        if (!$storeOwnsWs) {
            Log::warning("[commerce] store_id={$data['store_id']} provider={$data['provider']} does not belong to workspace_id={$data['workspace_id']}");
            return response()->json(['ok' => false, 'error' => 'store_workspace_mismatch'], 403);
        }

        $items = collect($data['items'])->map(fn ($i) => [
            'retailer_id' => (string) $i['retailer_id'],
            'qty'         => (int) ($i['qty'] ?? 1),
        ])->all();

        try {
            $result = match ($data['provider']) {
                'shopify'     => app(\App\Services\Commerce\ShopifyCheckoutLinkBuilder::class)
                    ->mint((int) $data['store_id'], $items, $data['session_id'] ?? null),
                'woocommerce' => app(\App\Services\Commerce\WoocommerceCheckoutLinkBuilder::class)
                    ->mint((int) $data['store_id'], $items, $data['session_id'] ?? null),
                'whatsapp_shop' => app(\App\Services\Commerce\WhatsappShopCheckoutLinkBuilder::class)
                    ->mint((int) $data['store_id'], $items, $data['session_id'] ?? null),
            };
            return response()->json(array_merge(['ok' => true], $result));
        } catch (\Throwable $e) {
            Log::warning('flow-commerce checkout-link failed: ' . $e->getMessage());
            return response()->json(['ok' => false, 'error' => 'mint_failed', 'message' => $e->getMessage()], 502);
        }
    }

    /**
     * POST /api/commerce/waba-send-products
     * Node hits this when the customer's conversation is on a WABA
     * device + the workspace has a linked catalog. We build the
     * interactive product_list payload and POST it to Meta's
     * graph.facebook.com/<phone_id>/messages.
     *
     * Body: { session_id, target_phone, items[], header?, body?, footer? }
     */
    public function wabaSendProducts(Request $request): JsonResponse
    {
        // Shared-secret auth. Refuse if NODE_WEBHOOK_TOKEN env is empty,
        // otherwise hash_equals('', '') returns true and the endpoint
        // becomes anonymous in dev installs that forgot the env var.
        $expected = node_token();
        $token    = (string) $request->header('X-Node-Token', '');
        if ($expected === '' || !hash_equals($expected, $token)) {
            return response()->json(['ok' => false, 'error' => 'unauthorized'], 401);
        }
        $data = $request->validate([
            'session_id'   => 'required|string',
            'target_phone' => 'required|string',
            'items'        => 'required|array|min:1|max:30',
            'items.*.retailer_id' => 'required|string',
            'workspace_id' => 'required|integer',
            'header'       => 'nullable|string|max:60',
            'body'         => 'nullable|string|max:1024',
            'footer'       => 'nullable|string|max:60',
        ]);

        // Workspace's WABA cfg + catalog. The native send requires
        // a phone_number_id (the WABA number) + catalog_id (Meta
        // commerce catalog) — both already live on wa_provider_configs
        // / wa_catalogs from earlier setup.
        $cfg = \App\Models\WaProviderConfig::where('workspace_id', $data['workspace_id'])
            ->where('provider', 'waba')
            ->first();
        if (!$cfg) return response()->json(['ok' => false, 'error' => 'no_waba_config'], 422);

        $catalog = \App\Models\WaCatalog::where('workspace_id', $data['workspace_id'])->first();
        if (!$catalog || !$catalog->catalog_id) {
            return response()->json(['ok' => false, 'error' => 'no_catalog'], 422);
        }

        // Build the interactive payload. SPM (single product_message)
        // when items.length===1, MPM (product_list) when >1. WhatsApp
        // requires sections; we put everything under one "Items".
        $items = collect($data['items'])->slice(0, 30)->values();
        if ($items->count() === 1) {
            $interactive = [
                'type' => 'product',
                'body' => ['text' => $data['body'] ?? 'Check this out:'],
                'footer' => $data['footer'] ? ['text' => $data['footer']] : null,
                'action' => [
                    'catalog_id'          => $catalog->catalog_id,
                    'product_retailer_id' => (string) $items[0]['retailer_id'],
                ],
            ];
        } else {
            $interactive = [
                'type' => 'product_list',
                'header' => ['type' => 'text', 'text' => $data['header'] ?? 'Browse our picks'],
                'body'   => ['text' => $data['body'] ?? 'Tap a product to view details'],
                'footer' => $data['footer'] ? ['text' => $data['footer']] : null,
                'action' => [
                    'catalog_id' => $catalog->catalog_id,
                    'sections'   => [[
                        'title'         => 'Items',
                        'product_items' => $items->map(fn ($i) => ['product_retailer_id' => (string) $i['retailer_id']])->all(),
                    ]],
                ],
            ];
        }
        $interactive = array_filter($interactive, fn ($v) => $v !== null);

        // The session_id rides on biz_opaque_callback_data so the
        // catalog orders webhook can map back when the customer
        // completes checkout. Meta passes it through unchanged.
        $payload = [
            'messaging_product' => 'whatsapp',
            'recipient_type'    => 'individual',
            'to'                => preg_replace('/\D+/', '', $data['target_phone']),
            'type'              => 'interactive',
            'interactive'       => $interactive,
            'biz_opaque_callback_data' => $data['session_id'],
        ];

        // Cloud API call. Token + phone_number_id live in the cfg's
        // encrypted credentials_json — read via the model's creds()
        // helper which decrypts. Reading the raw column returns the
        // ciphertext string, not the array.
        $creds = $cfg->creds();
        $phoneId = (string) ($creds['phone_number_id'] ?? '');
        $accessToken = (string) ($creds['access_token'] ?? '');
        if ($phoneId === '' || $accessToken === '') {
            return response()->json(['ok' => false, 'error' => 'waba_creds_missing'], 422);
        }
        $version = (string) (env('META_GRAPH_VERSION') ?: 'v21.0');

        try {
            $resp = \Illuminate\Support\Facades\Http::withToken($accessToken)
                ->acceptJson()
                ->timeout(15)
                ->post("https://graph.facebook.com/{$version}/{$phoneId}/messages", $payload);
            if (!$resp->successful()) {
                Log::warning('[waba-send-products] meta ' . $resp->status() . ': ' . substr($resp->body(), 0, 200));
                return response()->json(['ok' => false, 'error' => 'meta_send_failed', 'status' => $resp->status()], 502);
            }
            return response()->json(['ok' => true, 'meta' => $resp->json()]);
        } catch (\Throwable $e) {
            Log::warning('[waba-send-products] exception: ' . $e->getMessage());
            return response()->json(['ok' => false, 'error' => 'send_exception', 'message' => $e->getMessage()], 502);
        }
    }

    /**
     * POST /api/commerce/check-inventory
     * Filters the items list down to in-stock products at send time.
     * Provider-aware: WhatsApp Shop checks wa_products.in_stock;
     * WooCommerce/Shopify check the live API.
     */
    public function checkInventory(Request $request): JsonResponse
    {
        // Shared-secret auth. Refuse if NODE_WEBHOOK_TOKEN env is empty,
        // otherwise hash_equals('', '') returns true and the endpoint
        // becomes anonymous in dev installs that forgot the env var.
        $expected = node_token();
        $token    = (string) $request->header('X-Node-Token', '');
        if ($expected === '' || !hash_equals($expected, $token)) {
            return response()->json(['ok' => false, 'error' => 'unauthorized'], 401);
        }
        $data = $request->validate([
            'provider'        => 'required|in:shopify,woocommerce,whatsapp_shop',
            'store_id'        => 'required|integer',
            'workspace_id'    => 'required|integer',
            'retailer_ids'    => 'required|array|min:1|max:50',
            'retailer_ids.*'  => 'string',
        ]);

        $ids = $data['retailer_ids'];
        $live = [];

        try {
            if ($data['provider'] === 'whatsapp_shop') {
                // Match retailer_id against EITHER meta_retailer_id
                // (canonical Meta catalog id) OR sku (merchant local id)
                // — the flow saves whichever is set. Wrap the OR in a
                // closure so workspace_id + in_stock stay outside (any
                // looser scoping would leak rows across workspaces).
                $rows = \App\Models\WaProduct::query()
                    ->where('workspace_id', $data['workspace_id'])
                    ->where('in_stock', true)
                    ->where(function ($q) use ($ids) {
                        $q->whereIn('meta_retailer_id', $ids)->orWhereIn('sku', $ids);
                    })
                    ->get(['meta_retailer_id', 'sku']);
                $live = [];
                foreach ($rows as $p) {
                    $live[] = (string) ($p->meta_retailer_id ?: $p->sku);
                }
                $live = array_values(array_unique(array_filter($live)));
            } elseif ($data['provider'] === 'woocommerce') {
                $store = \App\Models\WoocommerceIntegration::where('workspace_id', $data['workspace_id'])->find($data['store_id']);
                if ($store) {
                    $rows = $this->woo->getProducts($store, 50) ?: [];
                    foreach ($rows as $r) {
                        // ?? before ?: — `$r['sku'] ?:` on an unset key
                        // emits a PHP notice. `??` is silent.
                        $rid = (string) (($r['sku'] ?? '') ?: ($r['id'] ?? ''));
                        if (in_array($rid, $ids, true) && (($r['stock_status'] ?? 'instock') === 'instock')) {
                            $live[] = $rid;
                        }
                    }
                }
            } else { // shopify
                $store = \App\Models\ShopifyIntegration::where('workspace_id', $data['workspace_id'])->find($data['store_id']);
                if ($store) {
                    $rows = $this->shopify->getProducts($store, 50) ?: [];
                    foreach ($rows as $r) {
                        $variant = $r['variants'][0] ?? [];
                        $rid = (string) (($variant['sku'] ?? '') ?: ($r['id'] ?? ''));
                        $inStock = !isset($variant['inventory_quantity']) || ((int) $variant['inventory_quantity']) > 0;
                        if (in_array($rid, $ids, true) && $inStock) {
                            $live[] = $rid;
                        }
                    }
                }
            }
        } catch (\Throwable $e) {
            Log::warning('[check-inventory] exception: ' . $e->getMessage());
            // Fail-open — if the live check breaks we still allow the
            // send (better than a dead flow on a transient API error).
            return response()->json(['ok' => true, 'in_stock' => $ids, 'fallback' => true]);
        }

        return response()->json(['ok' => true, 'in_stock' => array_values(array_unique($live))]);
    }

    // ----------------------------------------------------------------
    // Per-provider product fetchers (normalised return shape)
    // ----------------------------------------------------------------

    private function shopifyProducts(int $storeId, int $wsId, string $q, int $limit): array
    {
        $store = ShopifyIntegration::where('workspace_id', $wsId)->find($storeId);
        if (!$store) return [];
        $rows = $this->shopify->getProducts($store, $limit);
        if (!is_array($rows)) return [];
        $needle = mb_strtolower($q);
        // Normalise host once — store_url may include scheme or not;
        // prepending `https://` blindly to an already-fully-qualified
        // URL produces `https://https://...` which won't tap-open.
        $shopHost = rtrim(preg_replace('#^https?://#i', '', (string) $store->store_url), '/');
        $items = collect($rows)
            ->filter(fn ($r) => $q === '' || str_contains(mb_strtolower((string) ($r['title'] ?? '')), $needle))
            ->take($limit)
            ->map(function ($r) use ($store, $shopHost) {
                $variant = $r['variants'][0] ?? [];
                $image   = $r['image']['src'] ?? ($r['images'][0]['src'] ?? null);
                return [
                    'id'          => (string) ($r['id'] ?? ''),
                    'retailer_id' => (string) ($variant['sku'] ?? $r['id'] ?? ''),
                    'name'        => (string) ($r['title'] ?? 'Untitled'),
                    'sku'         => (string) ($variant['sku'] ?? ''),
                    'image'       => $image,
                    'price_minor' => isset($variant['price']) ? (int) round(((float) $variant['price']) * 100) : 0,
                    'currency'    => $store->shop_currency ?: 'USD',
                    'url'         => 'https://' . $shopHost . '/products/' . ($r['handle'] ?? ''),
                    'in_stock'    => isset($variant['inventory_quantity']) ? ((int) $variant['inventory_quantity']) > 0 : true,
                ];
            })
            ->values()
            ->all();
        return $items;
    }

    private function woocommerceProducts(int $storeId, int $wsId, string $q, int $limit): array
    {
        $store = WoocommerceIntegration::where('workspace_id', $wsId)->find($storeId);
        if (!$store) return [];
        $rows = $this->woo->getProducts($store, $limit);
        if (!is_array($rows)) return [];
        $needle = mb_strtolower($q);
        $items = collect($rows)
            ->filter(fn ($r) => $q === '' || str_contains(mb_strtolower((string) ($r['name'] ?? '')), $needle))
            ->take($limit)
            ->map(function ($r) use ($store) {
                return [
                    'id'          => (string) ($r['id'] ?? ''),
                    'retailer_id' => (string) (($r['sku'] ?? '') ?: ($r['id'] ?? '')),
                    'name'        => (string) ($r['name'] ?? 'Untitled'),
                    'sku'         => (string) ($r['sku'] ?? ''),
                    'image'       => $r['images'][0]['src'] ?? null,
                    'price_minor' => isset($r['price']) && $r['price'] !== '' ? (int) round(((float) $r['price']) * 100) : 0,
                    'currency'    => $store->store_currency ?: 'USD',
                    'url'         => (string) ($r['permalink'] ?? ''),
                    'in_stock'    => ($r['stock_status'] ?? 'instock') === 'instock',
                ];
            })
            ->values()
            ->all();
        return $items;
    }

    private function catalogProducts(int $catalogId, int $wsId, string $q, int $limit): array
    {
        $catalog = WaCatalog::where('workspace_id', $wsId)->find($catalogId);
        if (!$catalog) return [];
        $query = WaProduct::query()
            ->where('workspace_id', $wsId)
            ->whereNull('deleted_at')
            ->orderByDesc('id');
        if ($q !== '') $query->where('name', 'like', '%' . $q . '%');
        return $query->limit($limit)->get()->map(fn (WaProduct $p) => [
            'id'          => (string) $p->id,
            'retailer_id' => (string) ($p->meta_retailer_id ?: $p->sku ?: $p->id),
            'name'        => (string) $p->name,
            'sku'         => (string) $p->sku,
            'image'       => (string) $p->image_url,
            'price_minor' => (int) $p->price_minor,
            'currency'    => (string) ($p->currency_code ?: 'USD'),
            'url'         => (string) $p->product_url,
            'in_stock'    => (bool) $p->in_stock,
        ])->all();
    }
}
