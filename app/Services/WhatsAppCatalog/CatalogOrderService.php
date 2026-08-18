<?php

namespace App\Services\WhatsAppCatalog;

use App\Models\WaCatalog;
use App\Models\WaOrder;
use App\Models\WaTemplate;
use App\Services\Commerce\CommerceEventNotifier;
use App\Services\WhatsAppDispatcher;
use Illuminate\Support\Facades\Log;

/**
 * Closes the WhatsApp-cart loop. When a customer sends a catalog `order`
 * (their cart), Meta records it but replies with nothing — the buyer is left
 * in silence, which kills conversion. This service sends an immediate
 * order-received acknowledgement (summary + total + optional pay link),
 * engine-aware, so the sale actually progresses.
 *
 * Config lives on the workspace's WaCatalog row under `meta_json`:
 *   order_ack_enabled    bool   (default true)
 *   order_ack_template_id int?  (an approved template — overrides the built-in text)
 *   order_ack_pay_url     string? (a base pay URL; the order id is appended)
 */
class CatalogOrderService
{
    /** Send the order-received acknowledgement for a freshly-captured catalog order. */
    public function acknowledge(WaOrder $order): void
    {
        $phone = preg_replace('/\D+/', '', (string) $order->customer_phone);
        if ($phone === '') return;

        $catalog = WaCatalog::where('workspace_id', $order->workspace_id)->first();
        $meta    = ($catalog && is_array($catalog->meta_json)) ? $catalog->meta_json : [];
        if (($meta['order_ack_enabled'] ?? true) === false) return;

        $items   = is_array($order->items_json) ? $order->items_json : [];
        $count   = array_sum(array_map(fn ($i) => (int) ($i['qty'] ?? 1), $items));
        $cur     = (string) ($order->currency_code ?: '');
        $totalFmt = trim($cur . ' ' . number_format(((int) $order->total_minor) / 100, 2));
        $name    = trim((string) ($order->customer_name ?: '')) ?: 'there';
        $orderNo = '#' . $order->id;

        $payUrl = '';
        if (!empty($meta['order_ack_pay_url'])) {
            $base   = rtrim((string) $meta['order_ack_pay_url'], '/');
            $payUrl = $base . (str_contains($base, '?') ? '&' : '?') . 'order=' . $order->id;
        }

        // Optional approved template wins over the built-in text (engine-aware).
        if (!empty($meta['order_ack_template_id'])) {
            $tpl = WaTemplate::where('workspace_id', $order->workspace_id)->find($meta['order_ack_template_id']);
            if ($tpl) {
                $ctx = [
                    'name' => $name, 'first_name' => $name, 'order_name' => $orderNo,
                    'order_number' => (string) $order->id, 'total' => $totalFmt,
                    'item_count' => (string) $count, 'pay_url' => $payUrl,
                    '_positional' => [$name, $orderNo, $totalFmt],
                ];
                try {
                    app(CommerceEventNotifier::class)->notify($order->workspace_id, null, $phone, $tpl, $ctx);
                } catch (\Throwable $e) {
                    Log::warning('[CATALOG-ORDER] ack template send failed: ' . $e->getMessage());
                }
                return;
            }
        }

        // Built-in plain-text acknowledgement.
        $lines = [
            "Thank you, {$name}! We've received your order {$orderNo}.",
            $count . ' item' . ($count === 1 ? '' : 's') . ' · Total: ' . $totalFmt,
            $payUrl !== ''
                ? 'Complete your payment securely here: ' . $payUrl
                : "We'll confirm your order and share payment details shortly.",
        ];
        try {
            app(WhatsAppDispatcher::class)->sendRaw(
                ['to_number' => $phone, 'body' => implode("\n", $lines), 'workspace_id' => $order->workspace_id],
                null, 'W'
            );
        } catch (\Throwable $e) {
            Log::warning('[CATALOG-ORDER] ack send failed: ' . $e->getMessage());
        }
    }
}
