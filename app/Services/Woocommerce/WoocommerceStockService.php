<?php

namespace App\Services\Woocommerce;

use App\Models\WaProduct;
use App\Models\WaTemplate;
use App\Models\WoocommerceIntegration;
use App\Models\WoocommerceIntegrationEvent;
use App\Models\WoocommerceIntegrationLog;
use App\Models\WoocommerceStockWaitlist;
use App\Services\Commerce\CommerceEventNotifier;
use App\Services\WhatsAppDispatcher;
use Illuminate\Support\Facades\Log;

/**
 * Back-in-stock alerts for WooCommerce. Customers opt in by messaging about
 * an out-of-stock product; when WooCommerce restocks it (product.updated),
 * the whole waitlist is messaged automatically. WooCommerce twin of
 * ShopifyStockService.
 */
class WoocommerceStockService
{
    private const OPT_IN = ['notify', 'stock', 'back in stock', 'available', 'restock', 'in stock'];

    /**
     * Derive in-stock from a WooCommerce product payload. Woo exposes
     * `stock_status` directly, plus managed `stock_quantity` when tracked.
     * Backorder + instock = available; only explicit outofstock (or managed
     * stock at 0) is unavailable.
     */
    public static function deriveInStock(array $data): bool
    {
        $status = strtolower((string) ($data['stock_status'] ?? ''));
        if ($status === 'outofstock') {
            return false;
        }
        if ($status === 'instock' || $status === 'onbackorder') {
            return true;
        }
        if (!empty($data['manage_stock']) && array_key_exists('stock_quantity', $data) && $data['stock_quantity'] !== null) {
            return (int) $data['stock_quantity'] > 0;
        }
        return true;
    }

    /**
     * Inbound chokepoint — if the customer asked to be notified about a
     * product that's out of stock, add them to the waitlist. Returns true
     * if handled.
     */
    public function handleInboundOptIn(int $workspaceId, string $phone, string $text): bool
    {
        $phone = preg_replace('/\D+/', '', $phone);
        if ($phone === '') return false;

        $t = strtolower(trim($text));
        $looksLikeOptIn = false;
        foreach (self::OPT_IN as $kw) {
            if (str_contains($t, $kw)) { $looksLikeOptIn = true; break; }
        }
        if (!$looksLikeOptIn) return false;

        // Match an out-of-stock product by SKU or name appearing in the text.
        $product = WaProduct::where('workspace_id', $workspaceId)
            ->whereNotNull('woo_product_id')
            ->where('in_stock', false)
            ->get(['id', 'name', 'sku', 'woo_product_id'])
            ->first(function ($p) use ($t) {
                $name = strtolower((string) $p->name);
                $sku  = strtolower((string) $p->sku);
                return ($name && str_contains($t, $name)) || ($sku && str_contains($t, $sku));
            });
        if (!$product) return false;

        $integration = WoocommerceIntegration::where('workspace_id', $workspaceId)->latest('id')->first();
        if (!$integration) return false;

        WoocommerceStockWaitlist::firstOrCreate(
            [
                'workspace_id'   => $workspaceId,
                'woo_product_id' => (string) $product->woo_product_id,
                'customer_phone' => $phone,
                'status'         => 'waiting',
            ],
            [
                'integration_id' => $integration->id,
                'product_name'   => $product->name,
            ],
        );

        try {
            app(WhatsAppDispatcher::class)->sendRaw([
                'to_number'    => $phone,
                'body'         => "Done! We'll message you the moment \"{$product->name}\" is back in stock.",
                'workspace_id' => $workspaceId,
            ], $integration->user_id, 'W');
        } catch (\Throwable $e) {
            Log::debug('[WC-STOCK] opt-in ack failed: ' . $e->getMessage());
        }
        return true;
    }

    /**
     * product.updated webhook — if a product just transitioned from
     * out-of-stock to in-stock and the Back-in-stock automation is active,
     * message everyone on its waitlist. Call BEFORE the local mirror upsert
     * so we can read the previous stock state.
     */
    public function handleProductUpdate(WoocommerceIntegration $integration, array $data): void
    {
        $pid = (string) ($data['id'] ?? '');
        if ($pid === '') return;

        $existing = WaProduct::where('workspace_id', $integration->workspace_id)
            ->where('woo_product_id', $pid)->first();
        $wasOut = $existing && !$existing->in_stock;
        $nowIn  = self::deriveInStock($data);
        if (!$wasOut || !$nowIn) return; // only fire on out → in transition

        $event = WoocommerceIntegrationEvent::where('integration_id', $integration->id)
            ->where('event_type', 'stock/back')->where('is_active', true)->first();
        if (!$event || !$event->template_id) return;

        $tpl = WaTemplate::where('workspace_id', $integration->workspace_id)->find($event->template_id);
        if (!$tpl) return;

        $waiters = WoocommerceStockWaitlist::where('workspace_id', $integration->workspace_id)
            ->where('woo_product_id', $pid)->where('status', 'waiting')->get();
        if ($waiters->isEmpty()) return;

        $notifier = app(CommerceEventNotifier::class);
        $name = (string) ($data['name'] ?? $existing->name ?? 'your product');
        $url  = (string) ($data['permalink'] ?? ($existing->product_url ?? ''));
        $sent = 0;
        foreach ($waiters as $w) {
            $ctx = [
                'name' => 'there', 'product_name' => $name, 'product_url' => $url,
                'store_name' => (string) ($integration->store_name ?: $integration->store_url),
                '_positional' => ['there', $name, $url],
            ];
            // Waitlist rows can hold a raw local number — normalise before send
            // (already-international numbers pass through unchanged).
            $to = \App\Support\Woo\WooPhone::e164($w->customer_phone) ?: $w->customer_phone;
            $r  = $notifier->notify($integration->workspace_id, $integration->user_id, $to, $tpl, $ctx);
            if ($r['ok'] ?? false) $sent++;
            $w->update(['status' => 'notified', 'notified_at' => now()]);
        }

        WoocommerceIntegrationLog::create([
            'integration_id' => $integration->id,
            'event_type'     => 'stock/back',
            'status'         => $sent > 0 ? 'sent' : 'failed',
            'recipient'      => $waiters->count() . ' waiting',
            'payload'        => ['product' => $name, 'notified' => $sent],
            'created_at'     => now(),
        ]);
    }
}
