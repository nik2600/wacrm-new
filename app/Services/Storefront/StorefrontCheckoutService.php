<?php

namespace App\Services\Storefront;

use App\Models\WaCoupon;
use App\Models\WaOrder;
use App\Models\WaProduct;
use App\Models\WaStorefront;
use App\Services\WhatsAppCatalog\CatalogSyncService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Server-side storefront checkout. Takes a validated cart + customer
 * details and writes a real WaOrder — re-pricing every line from the
 * database so a tampered client price can never be trusted, and clamping
 * quantities to live stock. This replaces the old regex parser that tried
 * to reconstruct the order from free-text WhatsApp messages.
 *
 * Returns the created WaOrder (status=new), or null if the cart contained
 * no purchasable products.
 */
class StorefrontCheckoutService
{
    /**
     * @param array $data {
     *   name:string, phone:string, email?:string, address?:string,
     *   note?:string, items: array<array{id:int, qty:int}>
     * }
     */
    public function placeOrder(WaStorefront $sf, array $data): ?WaOrder
    {
        $wsId = (int) $sf->workspace_id;

        // Collect requested ids → fetch the live, available products once.
        $requested = [];
        foreach (($data['items'] ?? []) as $row) {
            $id  = (int) ($row['id'] ?? 0);
            $qty = max(1, (int) ($row['qty'] ?? 1));
            if ($id > 0) $requested[$id] = ($requested[$id] ?? 0) + $qty;
        }
        if (empty($requested)) return null;

        $products = WaProduct::forWorkspace($wsId)
            ->whereIn('id', array_keys($requested))
            ->available()
            ->get()
            ->keyBy('id');
        if ($products->isEmpty()) return null;

        $items = [];
        $subtotal = 0;
        foreach ($requested as $id => $qty) {
            $p = $products->get($id);
            if (!$p) continue;                              // dropped/unavailable since add-to-cart
            if ($p->stock_qty !== null) $qty = min($qty, max(0, (int) $p->stock_qty));
            if ($qty < 1) continue;                          // sold out
            $line = (int) $p->price_minor * $qty;            // SERVER price — never trust the client
            $subtotal += $line;
            $items[] = [
                'product_id'  => $p->id,
                'name'        => $p->name,
                'qty'         => $qty,
                'price_minor' => (int) $p->price_minor,
                'image'       => $p->image_url,
                'retailer_id' => $p->meta_retailer_id ?: ($p->sku ?: null),
            ];
        }
        if (empty($items)) return null;

        // Coupon (S5): re-validate server-side, discount the subtotal,
        // and let a free-shipping coupon zero the shipping fee.
        $coupon   = $this->resolveCoupon($sf, $data['coupon'] ?? null);
        $discount = $coupon ? $coupon->discountFor($subtotal) : 0;
        $shipping = (int) $sf->shippingFee($subtotal);
        if ($coupon && $coupon->free_shipping && $coupon->redeemable($subtotal)) $shipping = 0;
        $total    = max(0, $subtotal - $discount) + $shipping;

        $phone   = preg_replace('/[^\d+]/', '', (string) ($data['phone'] ?? ''));
        $address = trim((string) ($data['address'] ?? '')) ?: null;
        $note    = trim((string) ($data['note'] ?? '')) ?: null;

        // S4 — payment method + RTO risk (only meaningful for COD).
        $method = (($data['payment_method'] ?? 'prepaid') === 'cod') ? 'cod' : 'prepaid';
        $rto    = $method === 'cod'
            ? app(StorefrontRiskService::class)->score($wsId, $phone, $total)
            : ['score' => null, 'band' => null];

        $order = DB::transaction(function () use ($wsId, $sf, $items, $total, $shipping, $discount, $coupon, $phone, $address, $note, $data, $products, $requested, $method, $rto) {
            $order = WaOrder::create([
                'workspace_id'     => $wsId,
                'source'           => 'storefront',
                'customer_phone'   => $phone,
                'customer_name'    => trim((string) ($data['name'] ?? '')) ?: null,
                'customer_email'   => trim((string) ($data['email'] ?? '')) ?: null,
                'customer_address' => $address,
                'items_json'       => $items,
                'total_minor'      => $total,
                'shipping_minor'   => $shipping,
                'discount_minor'   => $discount,
                'coupon_code'      => ($discount > 0 || ($coupon && $coupon->free_shipping)) ? $coupon->code : null,
                'currency_code'    => $sf->currency_code ?: 'INR',
                'payment_method'   => $method,
                'rto_score'        => $rto['score'],
                'rto_band'         => $rto['band'],
                'status'           => 'new',
                'storefront_id'    => $sf->id,
                'recovery_token'   => Str::random(40),
                // Keep address in notes too so the existing order-detail view
                // surfaces it without a template change.
                'notes'            => $address ? ('Delivery address: ' . $address . ($note ? "\n" . $note : '')) : $note,
                'meta_json'        => ['placed_via' => 'storefront_checkout'],
            ]);

            if ($coupon && ($discount > 0 || $coupon->free_shipping)) {
                $coupon->increment('used_count');
            }

            // Stock decrement (S10): only for tracked products. Wrapped in
            // withoutAutoSync so we don't fire one Meta catalog push per
            // line mid-checkout — the nightly resync settles Meta stock.
            CatalogSyncService::withoutAutoSync(function () use ($products, $requested) {
                foreach ($requested as $id => $qty) {
                    $p = $products->get($id);
                    if ($p && $p->stock_qty !== null) {
                        $p->forceFill(['stock_qty' => max(0, (int) $p->stock_qty - $qty)])->save();
                    }
                }
            });

            return $order;
        });

        // S3 — the cart converted: cancel any pending abandoned-cart nudge.
        try {
            app(StorefrontCartService::class)->cancelOnOrder($sf, $phone);
        } catch (Throwable $e) {
            // never block the order on recovery cleanup
        }

        // WhatsApp Pay — opt-in auto-charge of the new order (best-effort; only
        // fires when the workspace's active config has auto_charge on).
        try {
            app(\App\Services\Waba\WhatsAppPayService::class)->maybeAutoCharge($order);
        } catch (Throwable $e) {
            // never block the order on the pay request
        }

        return $order;
    }

    /** Resolve a redeemable-by-code coupon for this storefront (or null). */
    public function resolveCoupon(WaStorefront $sf, ?string $code): ?WaCoupon
    {
        $code = trim((string) $code);
        if ($code === '') return null;

        return WaCoupon::where('workspace_id', $sf->workspace_id)
            ->whereRaw('LOWER(code) = ?', [Str::lower($code)])
            ->where('active', true)
            ->where(fn ($q) => $q->whereNull('storefront_id')->orWhere('storefront_id', $sf->id))
            ->first();
    }

    /**
     * Price a cart for a quote (coupon-preview / display) without writing
     * an order. Returns the same breakdown the order would get.
     *
     * @return array{subtotal:int,discount:int,shipping:int,total:int,coupon:?string,coupon_valid:bool,free_shipping:bool}
     */
    public function quote(WaStorefront $sf, array $items, ?string $code = null): array
    {
        [$lines, $subtotal] = $this->priceItems($sf, $items);
        $coupon   = $this->resolveCoupon($sf, $code);
        $valid    = $coupon ? $coupon->redeemable($subtotal) : false;
        $discount = $valid ? $coupon->discountFor($subtotal) : 0;
        $shipping = (int) $sf->shippingFee($subtotal);
        if ($valid && $coupon->free_shipping) $shipping = 0;

        return [
            'subtotal'      => $subtotal,
            'discount'      => $discount,
            'shipping'      => $shipping,
            'total'         => max(0, $subtotal - $discount) + $shipping,
            'coupon'        => $coupon?->code,
            'coupon_valid'  => $valid,
            'free_shipping' => (bool) ($valid && $coupon->free_shipping),
        ];
    }

    /**
     * Re-price requested items from the DB. Shared by placeOrder + quote.
     *
     * @return array{0: array, 1: int}  [lineItems, subtotalMinor]
     */
    private function priceItems(WaStorefront $sf, array $items): array
    {
        $requested = [];
        foreach ($items as $row) {
            $id  = (int) ($row['id'] ?? 0);
            $qty = max(1, (int) ($row['qty'] ?? 1));
            if ($id > 0) $requested[$id] = ($requested[$id] ?? 0) + $qty;
        }
        if (empty($requested)) return [[], 0];

        $products = WaProduct::forWorkspace($sf->workspace_id)
            ->whereIn('id', array_keys($requested))
            ->available()
            ->get()
            ->keyBy('id');

        $lines = [];
        $subtotal = 0;
        foreach ($requested as $id => $qty) {
            $p = $products->get($id);
            if (!$p) continue;
            if ($p->stock_qty !== null) $qty = min($qty, max(0, (int) $p->stock_qty));
            if ($qty < 1) continue;
            $subtotal += (int) $p->price_minor * $qty;
            $lines[] = ['id' => $p->id, 'qty' => $qty, 'price_minor' => (int) $p->price_minor];
        }

        return [$lines, $subtotal];
    }
}
