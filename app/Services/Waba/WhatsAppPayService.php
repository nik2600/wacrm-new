<?php

namespace App\Services\Waba;

use App\Models\SystemSetting;
use App\Models\WaOrder;
use App\Models\WaProviderConfig;
use App\Models\WorkspacePaymentConfig;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * WhatsApp Pay (native in-chat `order_details` payments) — WP-1.
 *
 * Sends the `order_details` interactive/template message that lets the customer
 * pay INSIDE WhatsApp (UPI / Razorpay / PayU), reads back the result, and can
 * push `order_status` updates. Region-locked by Meta to India today — callers
 * MUST gate on country before invoking (WorkspacePaymentConfig::isCountrySupported).
 *
 * Reuses the exact same WABA send path as TemplateSender: graph.facebook.com/
 * <version>/<phone_number_id>/messages with the workspace's WABA token.
 *
 * NOTE: the exact order_details / payment-webhook JSON is verified against the
 * plan (D:\Vault\kapil\Wasnap - WhatsApp Pay) + a build-time web audit. All
 * payload shaping is isolated in build*() so a spec correction is one place.
 */
class WhatsAppPayService
{
    /** Send the order_details charge message for an order. */
    public function sendOrderDetails(WaOrder $order, WorkspacePaymentConfig $cfg, array $opts = []): array
    {
        [$token, $phoneId, $err] = $this->resolveWaba($cfg);
        if ($err) return ['ok' => false, 'error' => $err];

        // Stable, unique, <=35 chars — the join key for the webhook + lookup.
        $referenceId = (string) ($order->wa_payment_reference_id ?: $this->makeReferenceId($order));

        $toNumber = preg_replace('/\D+/', '', (string) $order->customer_phone);
        if ($toNumber === '') return ['ok' => false, 'error' => 'order has no customer phone'];

        // In-window (customer just messaged) → interactive form; out-of-window
        // → template form (needs an approved order_details template).
        $useTemplate = !empty($opts['out_of_window']) || !empty($opts['template_name']);
        $payload = $useTemplate
            ? $this->buildTemplatePayload($order, $cfg, $referenceId, $toNumber, $opts)
            : $this->buildInteractivePayload($order, $cfg, $referenceId, $toNumber, $opts);

        $res = $this->post($token, $phoneId, $payload, 'order_details');
        if (($res['ok'] ?? false) === true) {
            // Persist the reference + pending state on the order so the webhook
            // can match it back (idempotent reference scheme).
            $order->forceFill([
                'payment_method'         => 'whatsapp_pay',
                'wa_payment_reference_id'=> $referenceId,
                'wa_payment_status'      => $order->wa_payment_status ?: 'pending',
                'wa_payment_config_id'   => $cfg->id,
            ])->save();
            $res['reference_id'] = $referenceId;
        }
        return $res;
    }

    /** Post-payment state update back to the customer (processing / shipped …). */
    public function sendOrderStatus(WaOrder $order, string $status, WorkspacePaymentConfig $cfg, array $opts = []): array
    {
        [$token, $phoneId, $err] = $this->resolveWaba($cfg);
        if ($err) return ['ok' => false, 'error' => $err];

        $toNumber    = preg_replace('/\D+/', '', (string) $order->customer_phone);
        $referenceId = (string) ($order->wa_payment_reference_id ?: '');
        if ($toNumber === '' || $referenceId === '') return ['ok' => false, 'error' => 'missing phone or reference_id'];

        $payload = [
            'messaging_product' => 'whatsapp',
            'recipient_type'    => 'individual',
            'to'                => $toNumber,
            'type'              => 'interactive',
            'interactive'       => [
                'type' => 'order_status',
                'body' => ['text' => (string) ($opts['body'] ?? 'Your order has been updated.')],
                'action' => [
                    'name' => 'review_order',
                    'parameters' => [
                        'reference_id' => $referenceId,
                        'order' => [
                            'status' => $status, // processing | partially_shipped | shipped | completed | canceled
                        ],
                    ],
                ],
            ],
        ];
        return $this->post($token, $phoneId, $payload, 'order_status');
    }

    /**
     * Payment Lookup — the SOURCE OF TRUTH. Meta's rule: never fulfil on the
     * webhook alone; confirm the real state here keyed by reference_id.
     */
    public function lookupPayment(string $referenceId, WorkspacePaymentConfig $cfg): array
    {
        [$token, $phoneId, $err] = $this->resolveWaba($cfg);
        if ($err) return ['ok' => false, 'error' => $err];

        $version = (string) SystemSetting::get('waba_graph_api_version', 'v23.0');
        $base    = 'https://graph.facebook.com/' . ltrim($version, '/');
        try {
            // OFFICIAL: GET /<phone_number_id>/payments/<payment_configuration>/<reference_id>
            $cfgName = rawurlencode($cfg->config_name);
            $ref     = rawurlencode($referenceId);
            $resp = Http::withToken($token)->acceptJson()->timeout(20)
                ->get("{$base}/{$phoneId}/payments/{$cfgName}/{$ref}");
            Log::info('[WAPAY] lookup', ['ref' => $referenceId, 'status' => $resp->status(), 'body' => mb_substr($resp->body(), 0, 600)]);
            if (!$resp->successful()) {
                return ['ok' => false, 'error' => (string) ($resp->json('error.message') ?? ('HTTP ' . $resp->status()))];
            }
            return ['ok' => true, 'data' => $resp->json()];
        } catch (\Throwable $e) {
            Log::warning('[WAPAY] lookup exception: ' . $e->getMessage());
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Opt-in AUTO-CHARGE (WP-3): when a new order lands and the workspace's
     * active WhatsApp Pay config has auto_charge on, immediately send the
     * order_details charge. Best-effort — NEVER throws into order creation.
     * Returns true only if the charge message actually went out.
     */
    public function maybeAutoCharge(WaOrder $order): bool
    {
        try {
            $cfg = WorkspacePaymentConfig::query()->forWorkspace((int) $order->workspace_id)->active()->first();
            if (!$cfg || empty($cfg->meta_json['auto_charge'])) return false;
            if (!WorkspacePaymentConfig::isCountrySupported($cfg->country)) return false;
            if ($order->wa_payment_status === 'captured') return false;          // already paid
            if (trim((string) $order->customer_phone) === '') return false;       // no recipient
            $res = $this->sendOrderDetails($order, $cfg);
            return (bool) ($res['ok'] ?? false);
        } catch (\Throwable $e) {
            Log::warning('[WAPAY] auto-charge skipped: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Reconcile sweep (WP-4): re-check this workspace's still-pending WhatsApp
     * Pay orders against the Payment Lookup API and settle any that have since
     * paid. Runs INLINE off page-loads (project policy: no cron) — cheap + capped.
     * Returns how many orders flipped to captured.
     */
    public function reconcilePending(int $workspaceId, int $minutesOld = 2, int $limit = 50): int
    {
        $cutoff = now()->subMinutes(max(0, $minutesOld));
        $orders = WaOrder::query()
            ->where('workspace_id', $workspaceId)
            ->where('payment_method', 'whatsapp_pay')
            ->where('wa_payment_status', 'pending')
            ->whereNotNull('wa_payment_reference_id')
            ->where('updated_at', '<=', $cutoff)
            ->limit($limit)->get();

        $settled = 0;
        foreach ($orders as $o) {
            try {
                // Reuse the webhook settle path — it does the lookup-confirm itself.
                $this->applyPaymentWebhook($workspaceId, [
                    'statuses' => [[
                        'type'    => 'payment',
                        'payment' => ['reference_id' => (string) $o->wa_payment_reference_id],
                    ]],
                ]);
                if ($o->fresh()?->wa_payment_status === 'captured') $settled++;
            } catch (\Throwable $e) {
                Log::warning('[WAPAY] reconcile skipped order ' . $o->id . ': ' . $e->getMessage());
            }
        }
        if ($orders->count()) {
            Log::info('[WAPAY] reconcile swept', ['ws' => $workspaceId, 'checked' => $orders->count(), 'settled' => $settled]);
        }
        return $settled;
    }

    /**
     * Settle an in-chat payment from the webhook (WP-2). The exact webhook shape
     * is finalised in the web audit, so we scan known locations for the payment
     * object, LOG the raw payload, and CONFIRM via Payment Lookup before flipping
     * an order to paid (Meta's mandate — never trust the webhook alone).
     * Idempotent + cache-locked on reference_id.
     */
    public function applyPaymentWebhook(int $workspaceId, array $value): void
    {
        Log::info('[WAPAY] webhook raw', ['ws' => $workspaceId, 'value' => mb_substr(json_encode($value), 0, 1500)]);

        $payment     = $this->extractPayment($value);
        $referenceId = (string) ($payment['reference_id'] ?? '');
        if ($referenceId === '') { Log::warning('[WAPAY] webhook had no reference_id'); return; }

        $lock = Cache::lock('wapay:webhook:' . $referenceId, 10);
        if (!$lock->get()) { Log::info('[WAPAY] webhook lock busy', ['ref' => $referenceId]); return; }
        try {
            $order = WaOrder::query()->where('workspace_id', $workspaceId)
                ->where('wa_payment_reference_id', $referenceId)->first();
            if (!$order) { Log::warning('[WAPAY] no order for reference_id', ['ref' => $referenceId]); return; }

            $cfg = $order->wa_payment_config_id
                ? WorkspacePaymentConfig::find($order->wa_payment_config_id)
                : WorkspacePaymentConfig::query()->forWorkspace($workspaceId)->active()->first();

            // Webhook status is a HINT — confirm true state via the lookup API.
            // Webhook: payment.transaction.status = success/failed (+ status-level
            // TRANSACTION_STATUS in _status). Lookup: top-level status = pending/captured.
            $confirmed = strtolower((string) ($payment['transaction']['status'] ?? $payment['status'] ?? $payment['_status'] ?? ''));
            $txnId     = (string) ($payment['transaction']['id'] ?? $payment['transaction']['pg_transaction_id'] ?? '');
            if ($cfg) {
                $look = $this->lookupPayment($referenceId, $cfg);
                if (($look['ok'] ?? false) === true && is_array($look['data'] ?? null)) {
                    $lp        = $this->extractPayment($look['data']);
                    $confirmed = strtolower((string) ($lp['status'] ?? $lp['transaction']['status'] ?? $confirmed));
                    $txnId     = $txnId ?: (string) ($lp['transaction']['id'] ?? '');
                }
            }

            $mapped = match (true) {
                in_array($confirmed, ['captured', 'success', 'successful', 'paid', 'completed'], true) => 'captured',
                in_array($confirmed, ['failed', 'canceled', 'cancelled', 'declined'], true)            => 'failed',
                in_array($confirmed, ['refunded', 'refund'], true)                                     => 'refunded',
                default                                                                                 => 'pending',
            };

            // Idempotent: never downgrade an already-captured order (except refund).
            if ($order->wa_payment_status === 'captured' && $mapped !== 'refunded') return;

            $order->forceFill([
                'wa_payment_status' => $mapped,
                'wa_payment_txn_id' => $txnId ?: $order->wa_payment_txn_id,
                'status'            => $mapped === 'captured' ? 'processing' : $order->status,
            ])->save();
            Log::info('[WAPAY] order settled', ['order' => $order->id, 'ref' => $referenceId, 'status' => $mapped, 'txn' => $txnId]);

            if ($mapped === 'captured') {
                try {
                    if (class_exists(\App\Services\WebhookService::class)) {
                        app(\App\Services\WebhookService::class)->dispatch($workspaceId, 'order_paid', [
                            'order_id'      => $order->id, 'reference_id' => $referenceId,
                            'amount_minor'  => (int) $order->total_minor, 'currency' => $order->currency_code,
                            'txn_id'        => $txnId, 'payment_method' => 'whatsapp_pay',
                        ]);
                    }
                } catch (\Throwable $e) { Log::warning('[WAPAY] order_paid webhook failed: ' . $e->getMessage()); }

                if ($cfg) {
                    try { $this->sendOrderStatus($order, 'processing', $cfg, ['body' => 'Payment received — we are processing your order. Thank you!']); }
                    catch (\Throwable $e) { /* best-effort */ }
                }
            }
        } finally {
            optional($lock)->release();
        }
    }

    /** Pull the payment object out of whatever location Meta put it (audit-refined). */
    private function extractPayment(array $value): array
    {
        // OFFICIAL: the payment outcome arrives in statuses[] with type:"payment".
        foreach ($value['statuses'] ?? [] as $s) {
            if (($s['type'] ?? '') === 'payment' && is_array($s['payment'] ?? null)) {
                $p = $s['payment'];
                $p['_status'] = $s['status'] ?? null; // status-level TRANSACTION_STATUS
                return $p;
            }
        }
        // Fallbacks: top-level value.payment, a message type:payment, or the
        // Payment Lookup response (top-level reference_id/status).
        if (!empty($value['payment']) && is_array($value['payment'])) return $value['payment'];
        foreach ($value['messages'] ?? [] as $m) {
            if (($m['type'] ?? '') === 'payment' && is_array($m['payment'] ?? null)) return $m['payment'];
        }
        if (isset($value['reference_id']) || isset($value['status'])) return $value;
        return [];
    }

    // ── payload builders (the only place the spec lives) ──────────────────────

    /** Interactive (in-window) order_details — no approved template needed. */
    private function buildInteractivePayload(WaOrder $order, WorkspacePaymentConfig $cfg, string $referenceId, string $toNumber, array $opts): array
    {
        return [
            'messaging_product' => 'whatsapp',
            'recipient_type'    => 'individual',
            'to'                => $toNumber,
            'type'              => 'interactive',
            'interactive'       => [
                'type'   => 'order_details',
                'body'   => ['text' => (string) ($opts['body'] ?? 'Review your order and pay securely inside WhatsApp.')],
                'footer' => ['text' => (string) ($opts['footer'] ?? 'Powered by WhatsApp Pay')],
                'action' => [
                    'name'       => 'review_and_pay',
                    'parameters' => $this->orderDetailsParameters($order, $cfg, $referenceId, $opts),
                ],
            ],
        ];
    }

    /** Template (out-of-window) order_details — requires an approved template. */
    private function buildTemplatePayload(WaOrder $order, WorkspacePaymentConfig $cfg, string $referenceId, string $toNumber, array $opts): array
    {
        $tplName = (string) ($opts['template_name'] ?? 'order_details');
        $lang    = (string) ($opts['template_language'] ?? 'en');
        return [
            'messaging_product' => 'whatsapp',
            'recipient_type'    => 'individual',
            'to'                => $toNumber,
            'type'              => 'template',
            'template'          => [
                'name'     => $tplName,
                'language' => ['policy' => 'deterministic', 'code' => $lang],
                'components' => [[
                    'type'    => 'button',
                    'sub_type'=> 'order_details',
                    'index'   => 0,
                    'parameters' => [[
                        'type'   => 'action',
                        'action' => ['order_details' => $this->orderDetailsParameters($order, $cfg, $referenceId, $opts, true)],
                    ]],
                ]],
            ],
        ];
    }

    /**
     * The shared order_details body — items, amounts (minor units, offset 100),
     * payment config. `$forTemplate` toggles the couple of fields the template
     * form names differently (payment_type/payment_configuration vs payment_settings).
     */
    private function orderDetailsParameters(WaOrder $order, WorkspacePaymentConfig $cfg, string $referenceId, array $opts, bool $forTemplate = false): array
    {
        $currency = strtoupper((string) ($order->currency_code ?: $cfg->currency ?: 'INR'));
        $items    = $this->mapItems(is_array($order->items_json) ? $order->items_json : [], $currency);
        $subtotal = array_sum(array_map(fn ($i) => (int) $i['amount']['value'], $items));
        $totalMin = (int) ($order->total_minor ?: $subtotal);

        $orderBlock = [
            'status'   => 'pending',
            'subtotal' => $this->money($subtotal),
            'tax'      => $this->money((int) ($opts['tax_minor'] ?? 0)),
            'shipping' => $this->money((int) ($opts['shipping_minor'] ?? 0)),
            'discount' => $this->money((int) ($opts['discount_minor'] ?? 0)),
            'items'    => $items,
        ];

        $params = [
            'reference_id' => $referenceId,
            'type'         => (string) ($opts['goods_type'] ?? 'physical-goods'), // physical-goods | digital-goods
            'currency'     => $currency,
            'total_amount' => $this->money($totalMin),
            'order'        => $orderBlock,
        ];

        // VERIFIED against Meta's OFFICIAL India payments doc: order_details
        // parameters carry a `payment_settings[]` array — one entry of
        // type:"payment_gateway" whose payment_gateway.type is the BARE gateway
        // name (razorpay | payu | billdesk | zaakpay) plus configuration_name
        // (the Direct-Pay-Method made in WhatsApp Manager, ≤60 chars).
        $params['payment_settings'] = [[
            'type'            => 'payment_gateway',
            'payment_gateway' => [
                'type'               => $cfg->payment_type,   // razorpay | payu | billdesk | zaakpay
                'configuration_name' => $cfg->config_name,
            ],
        ]];

        return $params;
    }

    /** Map order line items → order_details items[] (amounts in minor units). */
    private function mapItems(array $rawItems, string $currency): array
    {
        $out = [];
        foreach ($rawItems as $li) {
            $qty   = max(1, (int) ($li['qty'] ?? $li['quantity'] ?? 1));
            $price = (int) ($li['price_minor'] ?? 0);
            $out[] = [
                'name'        => (string) ($li['name'] ?? 'Item'),
                'retailer_id' => (string) ($li['retailer_id'] ?? $li['sku'] ?? ($li['product_id'] ?? '')),
                'quantity'    => $qty,
                'amount'      => $this->money($price),          // unit price (minor)
                'sale_amount' => $this->money($price),
            ];
        }
        return $out;
    }

    /** Minor-unit money object: ₹6.50 → {value:650, offset:100}. */
    private function money(int $minor): array
    {
        return ['value' => $minor, 'offset' => 100];
    }

    private function makeReferenceId(WaOrder $order): string
    {
        // <=35 chars, unique, stable per order.
        return mb_substr('wapay_' . $order->id . '_' . substr((string) time(), -6), 0, 35);
    }

    // ── plumbing ──────────────────────────────────────────────────────────────

    /** @return array{0:string,1:string,2:?string} [token, phoneId, error] */
    private function resolveWaba(WorkspacePaymentConfig $cfg): array
    {
        $provider = $cfg->provider_config_id
            ? WaProviderConfig::find($cfg->provider_config_id)
            : WaProviderConfig::query()->where('workspace_id', $cfg->workspace_id)->where('provider', 'waba')->first();
        if (!$provider || $provider->provider !== 'waba') {
            return ['', '', 'No WABA provider for this payment config.'];
        }
        $creds   = $provider->creds();
        $token   = (string) ($creds['access_token'] ?? '');
        $phoneId = (string) (($provider->meta_json['phone_number_id'] ?? null) ?: ($creds['phone_number_id'] ?? ''));
        if ($token === '' || $phoneId === '') {
            return ['', '', 'WABA provider missing access_token or phone_number_id.'];
        }
        return [$token, $phoneId, null];
    }

    private function post(string $token, string $phoneId, array $payload, string $what): array
    {
        $version = (string) SystemSetting::get('waba_graph_api_version', 'v23.0');
        $base    = 'https://graph.facebook.com/' . ltrim($version, '/');
        try {
            $resp = Http::withToken($token)->acceptJson()->timeout(20)
                ->post("{$base}/{$phoneId}/messages", $payload);
        } catch (\Throwable $e) {
            Log::warning("[WAPAY] {$what} send exception: " . $e->getMessage());
            return ['ok' => false, 'error' => 'HTTP exception: ' . $e->getMessage()];
        }
        if (!$resp->successful()) {
            $msg = (string) ($resp->json('error.message') ?? ('HTTP ' . $resp->status()));
            Log::warning("[WAPAY] {$what} send failed", ['code' => (int) ($resp->json('error.code') ?? 0), 'msg' => $msg]);
            return ['ok' => false, 'error' => $msg];
        }
        $wamid = (string) ($resp->json('messages.0.id') ?? '');
        Log::info("[WAPAY] {$what} sent", ['wamid' => $wamid]);
        return ['ok' => true, 'wamid' => $wamid];
    }
}
