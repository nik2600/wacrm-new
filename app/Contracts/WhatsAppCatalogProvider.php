<?php

namespace App\Contracts;

use App\Models\WaCatalog;
use App\Models\WaProduct;

/**
 * Provider-agnostic contract for WhatsApp Commerce Catalog
 * operations. Two concrete implementations:
 *
 *   • MetaCloudCatalogProvider  — graph.facebook.com direct
 *   • Dialog360CatalogProvider  — waba-v2.360dialog.io
 *
 * Both share the same JSON payloads (Meta's spec); they only differ
 * in base URL + auth header, so the abstract base class does 95% of
 * the work and the concrete classes are tiny.
 *
 * Returned arrays follow Meta's response shape — Laravel callers
 * receive raw decoded JSON so they can pluck what they need without
 * a leaky DTO layer.
 */
interface WhatsAppCatalogProvider
{
    /**
     * Verify the catalog binding (catalog_id + access token) is
     * usable by fetching the catalog's metadata. Returns the catalog
     * payload from Meta, or throws on failure.
     *
     * @throws \App\Exceptions\WhatsAppCatalogException
     */
    public function verifyCatalog(): array;

    /**
     * List the catalogs the WABA can access. Used in the
     * /store/catalog setup flow to let operators pick one.
     *
     * @return array<int, array{id:string, name:string, product_count?:int}>
     */
    public function listCatalogs(): array;

    /**
     * Upsert N products via Meta's async batch API. Returns the
     * Meta-issued `handles` array — caller persists those on each
     * wa_product row and polls checkBatchStatus() later.
     *
     * @param  iterable<WaProduct> $products
     * @return array{handles: array<int,string>}
     */
    public function upsertProductsBatch(iterable $products, string $shopUrl = ''): array;

    /**
     * Poll the status of one or more batch handles. Returns the raw
     * Meta response — caller flips wa_products.meta_sync_status
     * based on the per-handle result.
     *
     * @param  array<int,string> $handles
     */
    public function checkBatchStatus(array $handles): array;

    /**
     * Toggle the catalog's visibility + cart on the bound phone
     * number's commerce settings. Meta defaults both to false, so
     * we flip them on the moment a catalog is linked.
     */
    public function setCommerceSettings(bool $catalogVisible, bool $cartEnabled): array;

    // ─── Send messages ───────────────────────────────────────────

    /**
     * Send a Single Product Message (SPM). Use when promoting ONE
     * specific item inside the 24-hour customer service window.
     */
    public function sendSPM(string $toWaId, string $retailerId, ?string $bodyText = null, ?string $footer = null): array;

    /**
     * Send a Multi-Product Message (MPM) — the WATI-style list of
     * up to 30 products across up to 10 sections. ONLY works
     * inside the 24-hour window.
     *
     * @param array<int, array{title:string, product_retailer_ids:array<int,string>}> $sections
     */
    public function sendMPM(string $toWaId, string $header, string $body, array $sections, ?string $footer = null): array;

    /**
     * Send a generic Catalog Message with a "View catalog" CTA.
     * Buyer sees one thumbnail + button that opens the full
     * storefront.
     */
    public function sendCatalogMessage(string $toWaId, string $body, string $thumbnailRetailerId, ?string $footer = null): array;

    /**
     * Catalog link message — just a wa.me/c/{phone} URL pasted
     * into a text. No special API; works outside the 24-h window
     * (it's a plain text message).
     */
    public function sendCatalogLink(string $toWaId, string $body): array;

    /**
     * Get the bound catalog row.
     */
    public function catalog(): WaCatalog;
}
