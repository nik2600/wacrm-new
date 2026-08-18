<?php

namespace App\Services\WhatsAppCatalog;

use App\Contracts\WhatsAppCatalogProvider;
use App\Exceptions\WhatsAppCatalogException;
use App\Models\WaCatalog;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Shared base for Meta Cloud + 360dialog providers. The JSON
 * payloads are byte-identical across both — only the host and the
 * auth header differ, which the subclasses provide via
 * `baseUrl()` / `authHeader()` on the bound WaCatalog model.
 *
 * Every method returns the raw Meta-shaped response so callers
 * can drive their UI from the same data regardless of which BSP
 * delivered the message.
 */
abstract class AbstractCloudCatalogProvider implements WhatsAppCatalogProvider
{
    public function __construct(protected WaCatalog $bound)
    {
    }

    public function catalog(): WaCatalog { return $this->bound; }

    // ─── HTTP plumbing ───────────────────────────────────────────

    protected function http(): PendingRequest
    {
        // retry(..., throw: false) is REQUIRED: Laravel's retry() defaults to
        // throw=true, which raises a raw Illuminate RequestException on the final
        // failed attempt — BEFORE our decode() runs. That bypassed decode()'s
        // error handler (which wraps Meta's error in a WhatsAppCatalogException
        // the controllers catch) and surfaced Meta 4xx (e.g. "Unsupported get
        // request" from a wrong catalog_id/token) as an unhandled 500. With
        // throw:false the failed Response is returned so decode() can translate
        // it into a clean, user-facing error instead.
        return Http::withHeaders(array_merge(
            ['Accept' => 'application/json'],
            $this->bound->authHeader(),
        ))->timeout(30)->retry(2, 250, throw: false);
    }

    protected function baseUrl(): string { return $this->bound->providerBaseUrl(); }

    /**
     * Where the /messages endpoint lives. Meta needs the phone
     * number ID in the path; 360dialog routes by API key so the
     * path is plain "/messages".
     */
    abstract protected function messagesEndpoint(): string;

    /**
     * Where the catalog CRUD lives. Catalog operations always go
     * to Meta's Graph API (both providers), even on 360dialog —
     * 360dialog doesn't proxy these. We use the catalog row's
     * access_token_enc for both cases.
     */
    protected function graphEndpoint(string $path): string
    {
        // Graph API version is admin-configurable at /admin/settings/catalog
        // (system_settings.catalog_graph_api_version). Default v23.0 —
        // current stable as of May 2026; Meta rolls a new version every
        // ~3 months and keeps the previous one supported for ~2 years.
        $apiVersion = (string) \App\Models\SystemSetting::get('catalog_graph_api_version', 'v23.0');
        $base = 'https://graph.facebook.com/' . ltrim($apiVersion, '/');
        return $base . '/' . ltrim($path, '/');
    }

    /**
     * Standard error parser. Pulls the message + code from Meta's
     * `error` envelope and wraps in our typed exception. Logs full
     * context to laravel.log for post-mortem.
     */
    protected function decode(\Illuminate\Http\Client\Response $res, string $op): array
    {
        $json = $res->json() ?: [];
        if ($res->failed()) {
            $msg = $json['error']['message'] ?? ('HTTP ' . $res->status() . ': ' . $op);
            $code = (int) ($json['error']['code'] ?? $res->status());
            Log::warning('[wa-catalog] ' . $op . ' failed', [
                'status' => $res->status(),
                'body'   => $json,
                'op'     => $op,
            ]);
            throw new WhatsAppCatalogException($msg, $code, $json);
        }
        return $json;
    }

    // ─── Catalog CRUD ────────────────────────────────────────────

    public function verifyCatalog(): array
    {
        $res = $this->http()->get($this->graphEndpoint($this->bound->catalog_id), [
            'fields' => 'id,name,product_count,vertical,is_catalog_segment',
        ]);
        return $this->decode($res, 'verifyCatalog');
    }

    public function listCatalogs(): array
    {
        // List catalogs the WABA can see. Path differs slightly
        // when waba_id is set vs not — we prefer the WABA scope.
        $path = $this->bound->waba_id
            ? $this->bound->waba_id . '/product_catalogs'
            : 'me/businesses';
        $res = $this->http()->get($this->graphEndpoint($path), ['limit' => 50]);
        $json = $this->decode($res, 'listCatalogs');
        return $json['data'] ?? [];
    }

    /**
     * Pull products FROM the Meta catalog (inverse of upsertProductsBatch) so a
     * catalog built directly in Meta Commerce Manager can be imported into
     * WaDesk — WaDesk stores products locally and only PUSHES up, so a Meta-side
     * catalog otherwise shows 0 here. Follows paging up to $max; returns
     * normalized rows keyed by retailer_id.
     *
     * @return array<int, array<string,mixed>>
     */
    public function listProducts(int $max = 2000): array
    {
        $out    = [];
        $params = [
            'fields' => 'id,retailer_id,name,description,price,currency,availability,image_url,url',
            'limit'  => 200,
        ];
        $guard = 0;
        do {
            $res  = $this->http()->get($this->graphEndpoint($this->bound->catalog_id . '/products'), $params);
            $json = $this->decode($res, 'listProducts');
            foreach ((array) ($json['data'] ?? []) as $p) {
                $out[] = [
                    'meta_id'      => (string) ($p['id'] ?? ''),
                    'retailer_id'  => (string) ($p['retailer_id'] ?? ''),
                    'name'         => (string) ($p['name'] ?? ''),
                    'description'  => isset($p['description']) ? (string) $p['description'] : null,
                    'price'        => $p['price'] ?? null,
                    'currency'     => isset($p['currency']) ? (string) $p['currency'] : null,
                    'availability' => isset($p['availability']) ? (string) $p['availability'] : null,
                    'image_url'    => isset($p['image_url']) ? (string) $p['image_url'] : null,
                    'url'          => isset($p['url']) ? (string) $p['url'] : null,
                ];
                if (count($out) >= $max) break 2;
            }
            $after = data_get($json, 'paging.cursors.after');
            $params['after'] = $after;
        } while (!empty(data_get($json, 'paging.next')) && !empty($after) && ++$guard < 50);

        return $out;
    }

    public function upsertProductsBatch(iterable $products, string $shopUrl = ''): array
    {
        $requests = [];
        foreach ($products as $p) {
            $requests[] = [
                'method'      => 'CREATE',
                'retailer_id' => $p->meta_retailer_id ?: ($p->sku ?: 'wsn-' . $p->id),
                'data'        => $p->toMetaCatalogPayload($shopUrl),
            ];
        }
        if (empty($requests)) {
            return ['handles' => []];
        }

        // Trace the outbound sync so a stalled / rejected push is diagnosable
        // from laravel.log without guessing (decode() only logs FAILURES).
        $retailerIds = array_map(fn ($r) => $r['retailer_id'], $requests);
        Log::info('[wa-catalog] items_batch → Meta', [
            'workspace_id' => $this->bound->workspace_id,
            'catalog_id'   => $this->bound->catalog_id,
            'count'        => count($requests),
            'retailer_ids' => array_slice($retailerIds, 0, 20),
        ]);

        $res = $this->http()->post($this->graphEndpoint($this->bound->catalog_id . '/items_batch'), [
            'item_type' => 'PRODUCT_ITEM',
            'requests'  => $requests,
        ]);
        $json    = $this->decode($res, 'upsertProductsBatch');
        $handles = $json['handles'] ?? [];

        // Meta returns one handle per request entry (in order). A short count
        // (handles < sent) means Meta silently dropped some rows — surface it.
        Log::info('[wa-catalog] items_batch accepted', [
            'workspace_id' => $this->bound->workspace_id,
            'catalog_id'   => $this->bound->catalog_id,
            'sent'         => count($requests),
            'handles'      => count($handles),
        ]);

        return ['handles' => $handles];
    }

    public function checkBatchStatus(array $handles): array
    {
        if (empty($handles)) return ['data' => []];
        Log::info('[wa-catalog] check_batch_status → Meta', [
            'workspace_id' => $this->bound->workspace_id,
            'catalog_id'   => $this->bound->catalog_id,
            'handles'      => count($handles),
        ]);
        $res  = $this->http()->get(
            $this->graphEndpoint($this->bound->catalog_id . '/check_batch_request_status'),
            ['handles' => json_encode(array_values($handles))],
        );
        $json = $this->decode($res, 'checkBatchStatus');

        // Roll the per-handle results up by status so the log shows at a glance
        // where the batch is (finished / in-progress / errored) instead of a
        // wall of handle ids.
        $byStatus = [];
        foreach (($json['data'] ?? []) as $row) {
            $s = strtolower((string) ($row['status'] ?? 'unknown'));
            $byStatus[$s] = ($byStatus[$s] ?? 0) + 1;
        }
        Log::info('[wa-catalog] check_batch_status result', [
            'workspace_id' => $this->bound->workspace_id,
            'catalog_id'   => $this->bound->catalog_id,
            'rows'         => count($json['data'] ?? []),
            'by_status'    => $byStatus,
        ]);

        return $json;
    }

    public function setCommerceSettings(bool $catalogVisible, bool $cartEnabled): array
    {
        if (!$this->bound->phone_number_id) {
            throw new WhatsAppCatalogException('Catalog has no phone_number_id bound — set commerce settings against a specific phone number.');
        }
        $res = $this->http()->post(
            $this->messagesEndpoint() . '/whatsapp_commerce_settings',
            ['is_cart_enabled' => $cartEnabled, 'is_catalog_visible' => $catalogVisible],
        );
        $json = $this->decode($res, 'setCommerceSettings');
        $this->bound->forceFill([
            'is_cart_enabled'    => $cartEnabled,
            'is_catalog_visible' => $catalogVisible,
        ])->save();
        return $json;
    }

    // ─── Message senders ─────────────────────────────────────────

    public function sendSPM(string $toWaId, string $retailerId, ?string $bodyText = null, ?string $footer = null): array
    {
        $payload = [
            'messaging_product' => 'whatsapp',
            'recipient_type'    => 'individual',
            'to'                => $toWaId,
            'type'              => 'interactive',
            'interactive' => array_filter([
                'type'   => 'product',
                'body'   => $bodyText ? ['text' => mb_substr($bodyText, 0, 1024)] : null,
                'footer' => $footer   ? ['text' => mb_substr($footer, 0, 60)] : null,
                'action' => [
                    'catalog_id'          => $this->bound->catalog_id,
                    'product_retailer_id' => $retailerId,
                ],
            ]),
        ];
        $res = $this->http()->post($this->messagesEndpoint() . '/messages', $payload);
        return $this->decode($res, 'sendSPM');
    }

    public function sendMPM(string $toWaId, string $header, string $body, array $sections, ?string $footer = null): array
    {
        // Meta caps: 30 products total across max 10 sections; section title ≤24 chars;
        // header text ≤60, body ≤1024, footer ≤60. Enforce here so the API call
        // doesn't fail with a cryptic Meta error.
        $totalProducts = 0;
        $cleanSections = [];
        foreach (array_slice($sections, 0, 10) as $s) {
            $items = [];
            foreach ($s['product_retailer_ids'] ?? [] as $rid) {
                if ($totalProducts >= 30) break 2;
                $items[] = ['product_retailer_id' => $rid];
                $totalProducts++;
            }
            if (!empty($items)) {
                $cleanSections[] = [
                    'title'         => mb_substr($s['title'] ?? '', 0, 24),
                    'product_items' => $items,
                ];
            }
        }

        $payload = [
            'messaging_product' => 'whatsapp',
            'recipient_type'    => 'individual',
            'to'                => $toWaId,
            'type'              => 'interactive',
            'interactive' => array_filter([
                'type'   => 'product_list',
                'header' => ['type' => 'text', 'text' => mb_substr($header, 0, 60)],
                'body'   => ['text' => mb_substr($body, 0, 1024)],
                'footer' => $footer ? ['text' => mb_substr($footer, 0, 60)] : null,
                'action' => [
                    'catalog_id' => $this->bound->catalog_id,
                    'sections'   => $cleanSections,
                ],
            ]),
        ];

        $res = $this->http()->post($this->messagesEndpoint() . '/messages', $payload);
        return $this->decode($res, 'sendMPM');
    }

    public function sendCatalogMessage(string $toWaId, string $body, string $thumbnailRetailerId, ?string $footer = null): array
    {
        $payload = [
            'messaging_product' => 'whatsapp',
            'recipient_type'    => 'individual',
            'to'                => $toWaId,
            'type'              => 'interactive',
            'interactive' => array_filter([
                'type'   => 'catalog_message',
                'body'   => ['text' => mb_substr($body, 0, 1024)],
                'footer' => $footer ? ['text' => mb_substr($footer, 0, 60)] : null,
                'action' => [
                    'name'       => 'catalog_message',
                    'parameters' => ['thumbnail_product_retailer_id' => $thumbnailRetailerId],
                ],
            ]),
        ];
        $res = $this->http()->post($this->messagesEndpoint() . '/messages', $payload);
        return $this->decode($res, 'sendCatalogMessage');
    }

    public function sendCatalogLink(string $toWaId, string $body): array
    {
        // Plain text message — Meta auto-detects the wa.me/c/... URL
        // and renders the catalog preview client-side.
        $payload = [
            'messaging_product' => 'whatsapp',
            'recipient_type'    => 'individual',
            'to'                => $toWaId,
            'type'              => 'text',
            'text'              => ['body' => $body, 'preview_url' => true],
        ];
        $res = $this->http()->post($this->messagesEndpoint() . '/messages', $payload);
        return $this->decode($res, 'sendCatalogLink');
    }
}
