<?php

namespace App\Services\Shopify;

use App\Models\ShopifyCodConfirmation;
use App\Models\ShopifyIntegration;
use App\Models\ShopifyIntegrationEvent;
use App\Models\ShopifyIntegrationLog;
use App\Models\WaOrder;
use App\Models\WaTemplate;
use App\Services\Commerce\CommerceEventNotifier;
use App\Services\WhatsAppDispatcher;
use Illuminate\Support\Facades\Log;

/**
 * COD (cash-on-delivery) double-confirmation — the headline RTO-saver.
 *
 * On a COD order we message the customer asking them to confirm; we track
 * a pending row and, when they reply Yes/No on WhatsApp, flip the order
 * status. A customer who never confirms stays `pending` so the merchant
 * can hold the shipment.
 */
class ShopifyCodService
{
    private const YES = ['yes', 'y', 'confirm', 'confirmed', 'ok', 'okay', 'yep', 'sure', 'haan', 'ha', 'haa', '1', '✅', '👍'];
    private const NO  = ['no', 'n', 'cancel', 'cancelled', 'cancel order', 'nope', 'nahi', 'nai', '2', '❌'];

    /** Is this Shopify order paying by cash on delivery? */
    public static function isCodOrder(array $data): bool
    {
        $gateways = $data['payment_gateway_names'] ?? [];
        if (is_array($gateways)) {
            foreach ($gateways as $g) {
                $g = strtolower((string) $g);
                if (str_contains($g, 'cash on delivery') || str_contains($g, 'cod') || str_contains($g, 'cash_on_delivery')) {
                    return true;
                }
            }
        }
        $gw = strtolower((string) ($data['gateway'] ?? ''));
        if (str_contains($gw, 'cash on delivery') || str_contains($gw, 'cod')) return true;
        // Common fallback: unpaid order tagged COD.
        $tags = strtolower((string) ($data['tags'] ?? ''));
        return str_contains($tags, 'cod') && strtolower((string) ($data['financial_status'] ?? '')) === 'pending';
    }

    /** Send the confirmation template + open a pending tracking row. */
    public function sendConfirmation(ShopifyIntegration $integration, ShopifyIntegrationEvent $event, array $data): void
    {
        $phone = preg_replace('/\D+/', '', (string) (
            $data['customer']['phone'] ?? $data['phone'] ?? ($data['shipping_address']['phone'] ?? '')
        ));
        if ($phone === '') return;

        $tpl = WaTemplate::where('workspace_id', $integration->workspace_id)->find($event->template_id);
        if (!$tpl) return;

        $cust      = is_array($data['customer'] ?? null) ? $data['customer'] : [];
        $name      = trim((string) ($cust['first_name'] ?? '') . ' ' . (string) ($cust['last_name'] ?? '')) ?: 'there';
        $orderName = (string) ($data['name'] ?? ('#' . ($data['order_number'] ?? $data['id'] ?? '')));
        $total     = trim((string) ($data['total_price'] ?? '') . ' ' . (string) ($data['currency'] ?? $integration->shop_currency));

        $ctx = [
            'name' => $name, 'first_name' => $name, 'order_name' => $orderName,
            'order_number' => (string) ($data['order_number'] ?? ''), 'total' => $total,
            'store_name' => (string) ($integration->store_name ?: $integration->store_url),
            '_positional' => [$name, $orderName, $total],
        ];

        $r = app(CommerceEventNotifier::class)->notify($integration->workspace_id, $integration->user_id, $phone, $tpl, $ctx);

        ShopifyCodConfirmation::create([
            'integration_id' => $integration->id,
            'workspace_id'   => $integration->workspace_id,
            'shopify_order_id' => (string) ($data['id'] ?? ''),
            'order_name'     => $orderName,
            'customer_phone' => $phone,
            'status'         => 'pending',
        ]);

        ShopifyIntegrationLog::create([
            'integration_id' => $integration->id,
            'event_type'     => 'cod/confirm',
            'status'         => ($r['ok'] ?? false) ? 'sent' : 'failed',
            'recipient'      => $phone,
            'payload'        => ['order' => $orderName],
            'response'       => $r,
            'error'          => ($r['ok'] ?? false) ? null : ($r['error'] ?? null),
            'created_at'     => now(),
        ]);
    }

    /**
     * Inbound chokepoint — called on every customer reply. If this phone
     * has a pending COD confirmation and the text reads Yes/No, flip the
     * order + the tracking row and ack. Returns true if it handled the
     * message (so the caller can skip generic auto-reply).
     */
    public function handleInboundReply(int $workspaceId, string $phone, string $text): bool
    {
        $phone = preg_replace('/\D+/', '', $phone);
        if ($phone === '') return false;

        $pending = ShopifyCodConfirmation::where('workspace_id', $workspaceId)
            ->where('customer_phone', $phone)
            ->where('status', 'pending')
            ->latest('id')
            ->first();
        if (!$pending) return false;

        $t = strtolower(trim($text));
        $isYes = in_array($t, self::YES, true) || str_starts_with($t, 'yes') || str_starts_with($t, 'confirm');
        $isNo  = in_array($t, self::NO, true)  || str_starts_with($t, 'no') || str_starts_with($t, 'cancel');
        if (!$isYes && !$isNo) return false;

        $pending->update(['status' => $isYes ? 'confirmed' : 'cancelled']);

        // Flip the mirrored order if we have it.
        if ($pending->shopify_order_id) {
            try {
                WaOrder::where('workspace_id', $workspaceId)
                    ->where('shopify_order_id', $pending->shopify_order_id)
                    ->update(['status' => $isYes ? 'confirmed' : 'cancelled']);
            } catch (\Throwable $e) {
                Log::debug('[COD] order status update failed: ' . $e->getMessage());
            }
        }

        ShopifyIntegrationLog::create([
            'integration_id' => $pending->integration_id,
            'event_type'     => 'cod/reply',
            'status'         => 'processed',
            'recipient'      => $phone,
            'payload'        => ['order' => $pending->order_name, 'reply' => $text, 'result' => $isYes ? 'confirmed' : 'cancelled'],
            'created_at'     => now(),
        ]);

        // Acknowledge back to the customer (engine-aware, best-effort).
        try {
            $ack = $isYes
                ? "Thank you! Your COD order {$pending->order_name} is confirmed. We'll ship it shortly."
                : "Your COD order {$pending->order_name} has been cancelled. Reply if this was a mistake.";
            app(WhatsAppDispatcher::class)->sendRaw([
                'to_number' => $phone, 'body' => $ack, 'workspace_id' => $workspaceId,
            ], $pending->integration?->user_id, 'W');
        } catch (\Throwable $e) {
            Log::debug('[COD] ack send failed: ' . $e->getMessage());
        }

        return true;
    }
}
