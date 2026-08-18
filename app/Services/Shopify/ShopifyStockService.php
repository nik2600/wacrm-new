<?php

namespace App\Services\Shopify;

use App\Models\ShopifyIntegration;
use App\Models\ShopifyIntegrationEvent;
use App\Models\ShopifyIntegrationLog;
use App\Models\ShopifyStockWaitlist;
use App\Models\WaProduct;
use App\Models\WaTemplate;
use App\Services\Commerce\CommerceEventNotifier;
use App\Services\WhatsAppDispatcher;
use Illuminate\Support\Facades\Log;

/**
 * Back-in-stock alerts. Customers opt in by messaging about an
 * out-of-stock product; when Shopify restocks it (products/update), the
 * whole waitlist is messaged automatically.
 */
class ShopifyStockService
{
    private const OPT_IN = ['notify', 'stock', 'back in stock', 'available', 'restock', 'in stock'];

    /** Derive in-stock from a Shopify product payload's variant inventory. */
    public static function deriveInStock(array $data): bool
    {
        $variants = is_array($data['variants'] ?? null) ? $data['variants'] : [];
        $tracked = false;
        $qty = 0;
        foreach ($variants as $v) {
            if (array_key_exists('inventory_quantity', $v) && $v['inventory_quantity'] !== null) {
                $tracked = true;
                $qty += (int) $v['inventory_quantity'];
            }
        }
        // Untracked inventory → treat as available; tracked → in stock when qty > 0.
        return $tracked ? $qty > 0 : true;
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
            ->where('in_stock', false)
            ->get(['id', 'name', 'sku', 'shopify_product_id'])
            ->first(function ($p) use ($t) {
                $name = strtolower((string) $p->name);
                $sku  = strtolower((string) $p->sku);
                return ($name && str_contains($t, $name)) || ($sku && str_contains($t, $sku));
            });
        if (!$product) return false;

        $integration = ShopifyIntegration::where('workspace_id', $workspaceId)->latest('id')->first();
        if (!$integration) return false;

        ShopifyStockWaitlist::firstOrCreate(
            [
                'workspace_id'     => $workspaceId,
                'shopify_product_id' => (string) $product->shopify_product_id,
                'customer_phone'   => $phone,
                'status'           => 'waiting',
            ],
            [
                'integration_id' => $integration->id,
                'product_name'   => $product->name,
            ],
        );

        try {
            app(WhatsAppDispatcher::class)->sendRaw([
                'to_number' => $phone,
                'body'      => "Done! We'll message you the moment \"{$product->name}\" is back in stock.",
                'workspace_id' => $workspaceId,
            ], $integration->user_id, 'W');
        } catch (\Throwable $e) {
            Log::debug('[STOCK] opt-in ack failed: ' . $e->getMessage());
        }
        return true;
    }

    /**
     * products/update webhook — if a product just transitioned from
     * out-of-stock to in-stock and the Back-in-stock automation is active,
     * message everyone on its waitlist. Call BEFORE the local mirror upsert
     * so we can read the previous stock state.
     */
    public function handleProductUpdate(ShopifyIntegration $integration, array $data): void
    {
        $pid = (string) ($data['id'] ?? '');
        if ($pid === '') return;

        $existing = WaProduct::where('workspace_id', $integration->workspace_id)
            ->where('shopify_product_id', $pid)->first();
        $wasOut = $existing && !$existing->in_stock;
        $nowIn  = self::deriveInStock($data);
        if (!$wasOut || !$nowIn) return; // only fire on out → in transition

        $event = ShopifyIntegrationEvent::where('integration_id', $integration->id)
            ->where('event_type', 'stock/back')->where('is_active', true)->first();
        if (!$event || !$event->template_id) return;

        $tpl = WaTemplate::where('workspace_id', $integration->workspace_id)->find($event->template_id);
        if (!$tpl) return;

        $waiters = ShopifyStockWaitlist::where('workspace_id', $integration->workspace_id)
            ->where('shopify_product_id', $pid)->where('status', 'waiting')->get();
        if ($waiters->isEmpty()) return;

        $notifier = app(CommerceEventNotifier::class);
        $name  = (string) ($data['title'] ?? $existing->name ?? 'your product');
        $url   = 'https://' . $integration->store_url . '/products/' . ($data['handle'] ?? '');
        $sent  = 0;
        foreach ($waiters as $w) {
            $ctx = [
                'name' => 'there', 'product_name' => $name, 'product_url' => $url,
                'store_name' => (string) ($integration->store_name ?: $integration->store_url),
                '_positional' => ['there', $name, $url],
            ];
            $r = $notifier->notify($integration->workspace_id, $integration->user_id, $w->customer_phone, $tpl, $ctx);
            if ($r['ok'] ?? false) $sent++;
            $w->update(['status' => 'notified', 'notified_at' => now()]);
        }

        ShopifyIntegrationLog::create([
            'integration_id' => $integration->id,
            'event_type'     => 'stock/back',
            'status'         => $sent > 0 ? 'sent' : 'failed',
            'recipient'      => $waiters->count() . ' waiting',
            'payload'        => ['product' => $name, 'notified' => $sent],
            'created_at'     => now(),
        ]);
    }
}
