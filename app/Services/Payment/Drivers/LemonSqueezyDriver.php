<?php

namespace App\Services\Payment\Drivers;

use App\Models\Order;
use App\Services\Payment\AbstractGatewayDriver;
use App\Services\Payment\PaymentResult;
use Illuminate\Support\Facades\Http;

/**
 * Lemon Squeezy payment gateway driver.
 *
 * Merchant-of-record (like Paddle) — creates a hosted checkout via the JSON:API
 * and redirects the customer there; the webhook (HMAC-SHA256 X-Signature) is the
 * source of truth for payment + subscription state.
 *
 * Verified against the official docs:
 *   - POST https://api.lemonsqueezy.com/v1/checkouts  → data.attributes.url
 *   - webhook: X-Signature (hmac sha256 hex of raw body), meta.event_name,
 *     meta.custom_data; order status "paid"; subscription_* events.
 * @see https://docs.lemonsqueezy.com/api/checkouts/create-checkout
 * @see https://docs.lemonsqueezy.com/help/webhooks/signing-requests
 */
class LemonSqueezyDriver extends AbstractGatewayDriver
{
    private const API_BASE = 'https://api.lemonsqueezy.com/v1';
    private const JSON_API = 'application/vnd.api+json';

    public static function credentialFields(): array
    {
        return [
            'api_key'        => ['label' => 'API Key',                 'type' => 'password', 'required' => true],
            'store_id'       => ['label' => 'Store ID',                'type' => 'text',     'required' => true],
            'variant_id'     => ['label' => 'Variant ID',             'type' => 'text',     'required' => true],
            'signing_secret' => ['label' => 'Webhook Signing Secret', 'type' => 'password', 'required' => false],
        ];
    }

    public function initiate(Order $order, string $callbackUrl): PaymentResult
    {
        return $this->createCheckout($order, $callbackUrl, 'lemonsqueezy');
    }

    public function handleCallback(array $payload): PaymentResult
    {
        // Lemon Squeezy just redirects to redirect_url with no reliable status —
        // the webhook is authoritative. Treat the return as pending.
        return new PaymentResult(status: 'pending', payload: $payload);
    }

    public function verifyWebhookSignature(string $rawBody, ?string $signatureHeader): bool
    {
        $secret = (string) $this->cred('signing_secret');
        if ($secret === '') return true;                  // not configured → don't block (mirrors Paddle)
        if ($signatureHeader === null || $signatureHeader === '') return false;
        $expected = hash_hmac('sha256', $rawBody, $secret);
        return hash_equals($expected, $signatureHeader);
    }

    public function handleWebhook(array $payload): PaymentResult
    {
        $event = (string) ($payload['meta']['event_name'] ?? '');
        $data  = $payload['data'] ?? [];
        $attrs = $data['attributes'] ?? [];

        if ($event === 'order_created') {
            $status = strtolower((string) ($attrs['status'] ?? ''));
            if ($status === 'paid') {
                return PaymentResult::paid(gatewayPaymentId: (string) ($data['id'] ?? ''), payload: $payload);
            }
            return new PaymentResult(status: 'pending', gatewayPaymentId: (string) ($data['id'] ?? ''), payload: $payload);
        }

        return PaymentResult::failed("unhandled_lemonsqueezy_event: {$event}", $payload);
    }

    // ── Recurring subscriptions ──────────────────────────────────────────────
    //
    // Lemon Squeezy has no "create subscription" call — checking out a
    // SUBSCRIPTION variant creates the subscription once the customer pays.
    // So the configured Variant ID must be a subscription variant for recurring.

    public function supportsRecurring(): bool
    {
        return true;
    }

    public function createSubscription(Order $order, string $callbackUrl): PaymentResult
    {
        return $this->createCheckout($order, $callbackUrl, 'lemonsqueezy');
    }

    public function parseSubscriptionWebhook(array $payload): ?array
    {
        $event   = (string) ($payload['meta']['event_name'] ?? '');
        $custom  = $payload['meta']['custom_data'] ?? [];
        $orderId = $custom['order_id'] ?? null;
        $data    = $payload['data'] ?? [];
        $attrs   = $data['attributes'] ?? [];
        if (!is_array($attrs)) return null;

        switch ($event) {
            case 'subscription_created':
                return ['type' => 'created', 'subscription_id' => (string) ($data['id'] ?? ''), 'payment_id' => null, 'period_end' => $attrs['renews_at'] ?? null, 'order_id' => $orderId];

            case 'subscription_payment_success':
            case 'subscription_payment_recovered':
                // data here is a subscription-invoice; the subscription id is in attributes.
                return ['type' => 'renewed', 'subscription_id' => (string) ($attrs['subscription_id'] ?? ''), 'payment_id' => (string) ($data['id'] ?? ''), 'period_end' => null, 'order_id' => $orderId];

            case 'subscription_cancelled':
            case 'subscription_expired':
                return ['type' => 'canceled', 'subscription_id' => (string) ($data['id'] ?? ($attrs['subscription_id'] ?? '')), 'payment_id' => null, 'period_end' => null, 'order_id' => $orderId];

            case 'subscription_payment_failed':
                return ['type' => 'payment_failed', 'subscription_id' => (string) ($attrs['subscription_id'] ?? ''), 'payment_id' => (string) ($data['id'] ?? ''), 'period_end' => null, 'order_id' => $orderId];

            default:
                return null;
        }
    }

    public function cancelSubscription(string $gatewaySubscriptionId, array $context = []): PaymentResult
    {
        $apiKey = (string) $this->cred('api_key');
        if ($apiKey === '') return PaymentResult::failed('lemonsqueezy_api_key_missing');
        try {
            // LS cancels at the end of the current period via DELETE.
            $r = Http::withToken($apiKey)
                ->withHeaders(['Accept' => self::JSON_API])
                ->timeout(self::HTTP_TIMEOUT_SECONDS)
                ->delete(self::API_BASE . '/subscriptions/' . $gatewaySubscriptionId);
            if (!$r->successful()) return PaymentResult::failed('lemonsqueezy_cancel: HTTP ' . $r->status());
            return PaymentResult::paid(gatewayOrderId: $gatewaySubscriptionId, payload: $r->json() ?: []);
        } catch (\Throwable $e) {
            return PaymentResult::failed('lemonsqueezy_cancel_exception: ' . $e->getMessage());
        }
    }

    // ── shared checkout creation ──────────────────────────────────────────────

    private function createCheckout(Order $order, string $callbackUrl, string $logTag): PaymentResult
    {
        $apiKey    = (string) $this->cred('api_key');
        $storeId   = (string) $this->cred('store_id');
        $variantId = (string) $this->cred('variant_id');
        if ($apiKey === '' || $storeId === '' || $variantId === '') {
            return PaymentResult::failed('lemonsqueezy_credentials_missing');
        }

        $body = [
            'data' => [
                'type'       => 'checkouts',
                'attributes' => [
                    // Amount in CENTS (integer). Overrides the variant price per order.
                    'custom_price'    => (int) round((float) $order->amount * 100),
                    'product_options' => [
                        'redirect_url' => $callbackUrl,
                    ],
                    'checkout_data' => [
                        'email'  => (string) ($order->email ?? optional($order->user)->email ?? ''),
                        'custom' => [
                            'order_id'     => (string) $order->id,
                            'order_number' => (string) $order->order_number,
                            'workspace_id' => (string) ($order->workspace_id ?? ''),
                        ],
                    ],
                ],
                'relationships' => [
                    'store'   => ['data' => ['type' => 'stores',   'id' => $storeId]],
                    'variant' => ['data' => ['type' => 'variants', 'id' => $variantId]],
                ],
            ],
        ];

        try {
            $r = Http::withToken($apiKey)
                ->withHeaders(['Accept' => self::JSON_API, 'Content-Type' => self::JSON_API])
                ->timeout(self::HTTP_TIMEOUT_SECONDS)
                ->post(self::API_BASE . '/checkouts', $body);
            $json = $r->json() ?: [];
            $url  = $json['data']['attributes']['url'] ?? null;
            if ($url) {
                return PaymentResult::redirect($url, $json['data']['id'] ?? null, $json);
            }
            $err = $json['errors'][0]['detail'] ?? ($json['errors'][0]['title'] ?? 'create_failed');
            return PaymentResult::failed("{$logTag}: " . $err);
        } catch (\Throwable $e) {
            return PaymentResult::failed("{$logTag}_exception: " . $e->getMessage());
        }
    }
}
