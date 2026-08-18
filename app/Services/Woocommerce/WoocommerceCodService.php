<?php

namespace App\Services\Woocommerce;

use App\Models\WaOrder;
use App\Models\WaTemplate;
use App\Models\WoocommerceCodConfirmation;
use App\Models\WoocommerceIntegration;
use App\Models\WoocommerceIntegrationEvent;
use App\Models\WoocommerceIntegrationLog;
use App\Services\Commerce\CommerceEventNotifier;
use App\Services\WhatsAppDispatcher;
use Illuminate\Support\Facades\Log;

/**
 * COD (cash-on-delivery) double-confirmation for WooCommerce — the headline
 * RTO-saver. On a COD order we ask the customer to confirm on WhatsApp; their
 * Yes/No reply flips the WooCommerce order itself (processing / cancelled) via
 * the REST API plus our mirrored copy. WooCommerce twin of ShopifyCodService.
 */
class WoocommerceCodService
{
    private const YES = ['yes', 'y', 'confirm', 'confirmed', 'ok', 'okay', 'yep', 'sure', 'haan', 'ha', 'haa', '1', '✅', '👍'];
    private const NO  = ['no', 'n', 'cancel', 'cancelled', 'cancel order', 'nope', 'nahi', 'nai', '2', '❌'];

    public function __construct(private readonly WoocommerceService $woo) {}

    /** Is this WooCommerce order paying by cash on delivery? */
    public static function isCodOrder(array $data): bool
    {
        $method = strtolower((string) ($data['payment_method'] ?? ''));
        if ($method === 'cod' || str_contains($method, 'cash_on_delivery') || str_contains($method, 'cashondelivery')) {
            return true;
        }
        $title = strtolower((string) ($data['payment_method_title'] ?? ''));
        return str_contains($title, 'cash on delivery') || str_contains($title, 'cod');
    }

    /** Send the confirmation template + open a pending tracking row. */
    public function sendConfirmation(WoocommerceIntegration $integration, WoocommerceIntegrationEvent $event, array $data): void
    {
        $billing = is_array($data['billing'] ?? null) ? $data['billing'] : [];
        // E.164-normalise via the order's billing country (a local COD number
        // would otherwise never reach the customer). See App\Support\Woo\WooPhone.
        $phone   = \App\Support\Woo\WooPhone::fromOrder($data) ?? '';
        if ($phone === '') return;

        $tpl = WaTemplate::where('workspace_id', $integration->workspace_id)->find($event->template_id);
        if (!$tpl) return;

        $name      = trim((string) ($billing['first_name'] ?? '') . ' ' . (string) ($billing['last_name'] ?? '')) ?: 'there';
        $orderName = '#' . ($data['number'] ?? $data['id'] ?? '');
        $total     = trim((string) ($data['total'] ?? '') . ' ' . (string) ($data['currency'] ?? $integration->store_currency));

        $ctx = [
            'name' => $name, 'first_name' => $name, 'order_name' => $orderName,
            'order_number' => (string) ($data['number'] ?? ''), 'total' => $total,
            'store_name' => (string) ($integration->store_name ?: $integration->store_url),
            '_positional' => [$name, $orderName, $total],
        ];

        $r = app(CommerceEventNotifier::class)->notify($integration->workspace_id, $integration->user_id, $phone, $tpl, $ctx);

        WoocommerceCodConfirmation::create([
            'integration_id' => $integration->id,
            'workspace_id'   => $integration->workspace_id,
            'woo_order_id'   => (string) ($data['id'] ?? ''),
            'order_name'     => $orderName,
            'customer_phone' => $phone,
            'status'         => 'pending',
        ]);

        WoocommerceIntegrationLog::create([
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
     * Inbound chokepoint — if this phone has a pending COD confirmation and
     * the text reads Yes/No, flip the WooCommerce order (REST) + the mirror +
     * the tracking row, then ack. Returns true if it handled the message.
     */
    public function handleInboundReply(int $workspaceId, string $phone, string $text): bool
    {
        $phone = preg_replace('/\D+/', '', $phone);
        if ($phone === '') return false;

        $pending = WoocommerceCodConfirmation::where('workspace_id', $workspaceId)
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

        // Flip the real WooCommerce order + our mirror.
        $integration = WoocommerceIntegration::find($pending->integration_id);
        if ($integration && $pending->woo_order_id) {
            try {
                $this->woo->updateOrderStatus($integration, $pending->woo_order_id, $isYes ? 'processing' : 'cancelled');
            } catch (\Throwable $e) {
                Log::debug('[WC-COD] remote status flip failed: ' . $e->getMessage());
            }
        }
        if ($pending->woo_order_id) {
            try {
                WaOrder::where('workspace_id', $workspaceId)
                    ->where('woo_order_id', $pending->woo_order_id)
                    ->update(['status' => $isYes ? 'confirmed' : 'cancelled']);
            } catch (\Throwable $e) {
                Log::debug('[WC-COD] mirror status update failed: ' . $e->getMessage());
            }
        }

        WoocommerceIntegrationLog::create([
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
            ], $integration?->user_id, 'W');
        } catch (\Throwable $e) {
            Log::debug('[WC-COD] ack send failed: ' . $e->getMessage());
        }

        return true;
    }
}
