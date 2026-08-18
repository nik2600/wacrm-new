<?php

namespace App\Services\Commerce;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Closes the loop on commerce-flow orders. WooCommerce + Shopify
 * (and later WABA-catalog) send order.created webhooks back to
 * Laravel. We sniff the cart metadata for a `wadesk-flow:<sessionKey>`
 * tag and ping Node's /api/flow/resume-port so the paused commerce
 * node advances through the `purchased` port.
 *
 * Idempotent — Node de-dups by checking `waitingForInput.nextNodeType`
 * so duplicate webhook deliveries don't fire `purchased` twice.
 */
class FlowSessionResolver
{
    public static function resumeFromWoocommerceOrder(array $order): void
    {
        // WC stashes our session id in line-items / meta_data with
        // key `_wa_flow_session`. Walk meta_data first; fall back to
        // order.customer_note.
        $sessionKey = null;
        foreach ((array) ($order['meta_data'] ?? []) as $m) {
            if (($m['key'] ?? '') === '_wa_flow_session' && !empty($m['value'])) {
                $sessionKey = (string) $m['value'];
                break;
            }
        }
        if (!$sessionKey && !empty($order['customer_note'])) {
            $sessionKey = self::extractFromNote((string) $order['customer_note']);
        }
        if (!$sessionKey) return;

        self::ping($sessionKey, [
            'id'       => $order['id'] ?? null,
            'total'    => $order['total'] ?? null,
            'currency' => $order['currency'] ?? null,
            'provider' => 'woocommerce',
        ]);
    }

    public static function resumeFromShopifyOrder(array $order): void
    {
        // Shopify carries our tag in `note` (we set it on cartCreate
        // / draft_order). Format: `wadesk-flow:<sessionKey>`.
        $sessionKey = self::extractFromNote((string) ($order['note'] ?? ''));
        if (!$sessionKey) return;

        self::ping($sessionKey, [
            'id'       => $order['id'] ?? $order['name'] ?? null,
            'total'    => $order['total_price'] ?? null,
            'currency' => $order['currency'] ?? null,
            'provider' => 'shopify',
        ]);
    }

    /**
     * WABA-catalog `orders` webhook payload — Meta delivers product
     * items + cart total. The flow session id rides on the
     * `biz_opaque_callback_data` field we set when sending the MPM.
     */
    public static function resumeFromWabaCatalogOrder(array $order, ?string $callbackData = null): void
    {
        $sessionKey = $callbackData ? self::extractFromNote('wadesk-flow:' . $callbackData) : null;
        if (!$sessionKey) return;

        self::ping($sessionKey, [
            'id'       => $order['order_id'] ?? null,
            'total'    => $order['total_amount']['value'] ?? null,
            'currency' => $order['total_amount']['currency_code'] ?? null,
            'provider' => 'whatsapp_shop',
        ]);
    }

    /**
     * Inbound WABA `type:order` messages DO NOT carry the
     * `biz_opaque_callback_data` field we set on the outbound MPM —
     * Meta only echoes it on status callbacks (sent/delivered/read),
     * not on the customer's order message. So we can't recover the
     * session key from the payload; instead we ask Node to scan its
     * own activeFlowSessions for a paused CommerceShop session
     * matching this customer's phone.
     *
     * One active commerce session per (workspace, customer phone) is
     * the working assumption — fine in practice because Node only
     * starts one flow per (sender_device, target_phone) anyway.
     */
    public static function resumeFromWabaCatalogOrderByPhone(int $workspaceId, string $customerPhone, array $orderMeta): void
    {
        $base = (string) (\App\Models\SystemSetting::get('baileys_server_url', '') ?: env('SERVER_URL', ''));
        if ($base === '') {
            Log::warning('[CommerceFlow] WABA resume skipped — no node bridge url');
            return;
        }
        $phone = preg_replace('/\D+/', '', $customerPhone);
        if ($phone === '') return;
        try {
            Http::withHeaders(['X-Node-Token' => node_token()])
                ->timeout(8)
                ->acceptJson()
                ->post(rtrim($base, '/') . '/api/flow/resume-by-phone/' . $workspaceId . '/' . rawurlencode($phone), [
                    'port'      => 1, // 1 = purchased
                    'orderMeta' => $orderMeta,
                ]);
        } catch (\Throwable $e) {
            Log::warning('[CommerceFlow] WABA resume-by-phone failed: ' . $e->getMessage());
        }
    }

    private static function extractFromNote(string $note): ?string
    {
        // sessionKey is "<sender>_<target>" — usually digit-only after
        // normalisation, but tolerate `+` and other phone characters
        // in case the upstream forgot to strip them. Greedy non-space
        // match after the `wadesk-flow:` tag.
        if (preg_match('/wadesk-flow:(\S+)/', $note, $m)) {
            return rtrim($m[1], ".,;)\"'");  // strip trailing punctuation
        }
        // Plain sessionKey (sender_target) without the prefix.
        if (preg_match('/^([+\d]+_[+\d]+)$/', trim($note), $m)) {
            return $m[1];
        }
        return null;
    }

    private static function ping(string $sessionKey, array $orderMeta): void
    {
        $base = (string) (\App\Models\SystemSetting::get('baileys_server_url', '') ?: env('SERVER_URL', ''));
        if ($base === '') {
            Log::warning('[CommerceFlow] resume skipped — no node bridge url');
            return;
        }
        try {
            Http::withHeaders(['X-Node-Token' => node_token()])
                ->timeout(8)
                ->acceptJson()
                ->post(rtrim($base, '/') . '/api/flow/resume-port/' . rawurlencode($sessionKey), [
                    'port'      => 1, // 1 = purchased
                    'orderMeta' => $orderMeta,
                ]);
        } catch (\Throwable $e) {
            Log::warning('[CommerceFlow] resume ping failed: ' . $e->getMessage());
        }
    }
}
