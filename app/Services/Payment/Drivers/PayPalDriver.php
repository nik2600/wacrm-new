<?php

namespace App\Services\Payment\Drivers;

use App\Models\Order;
use App\Services\Payment\AbstractGatewayDriver;
use App\Services\Payment\PaymentResult;
use Illuminate\Support\Facades\Http;

/**
 * PayPal Orders v2 (REST, no Composer SDK).
 *
 * Sandbox base:  https://api-m.sandbox.paypal.com
 * Live base:     https://api-m.paypal.com
 *
 * Flow:
 *   1. OAuth token: POST /v1/oauth2/token (grant_type=client_credentials)
 *   2. Create order: POST /v2/checkout/orders → returns links[] including
 *      rel:'approve' which is the user redirect URL.
 *   3. User pays, gets redirected to our return_url with ?token=<order_id>.
 *   4. handleCallback() POSTs /v2/checkout/orders/{id}/capture to actually
 *      capture the money, checks status='COMPLETED'.
 */
class PayPalDriver extends AbstractGatewayDriver
{
    public static function credentialFields(): array
    {
        return [
            'client_id'     => ['label' => 'Client ID',     'type' => 'text',     'required' => true],
            'client_secret' => ['label' => 'Client secret', 'type' => 'password', 'required' => true],
        ];
    }

    private function apiBase(): string
    {
        return $this->isLive() ? 'https://api-m.paypal.com' : 'https://api-m.sandbox.paypal.com';
    }

    private function accessToken(): ?string
    {
        $clientId = (string) $this->cred('client_id');
        $secret   = (string) $this->cred('client_secret');
        if ($clientId === '' || $secret === '') return null;
        try {
            $r = Http::asForm()->withBasicAuth($clientId, $secret)->timeout(self::HTTP_TIMEOUT_SECONDS)
                ->post($this->apiBase() . '/v1/oauth2/token', ['grant_type' => 'client_credentials']);
            if (!$r->successful()) return null;
            return (string) $r->json('access_token');
        } catch (\Throwable $e) {
            return null;
        }
    }

    public function initiate(Order $order, string $callbackUrl): PaymentResult
    {
        $token = $this->accessToken();
        if (!$token) return PaymentResult::failed('paypal_token_failed');

        $appName = \App\Models\SystemSetting::get('app_name', config('app.name', 'WaDesk'));
        $body = [
            'intent' => 'CAPTURE',
            'purchase_units' => [[
                'reference_id' => $order->order_number,
                'amount'       => ['currency_code' => strtoupper($order->currency), 'value' => number_format((float) $order->amount, 2, '.', '')],
                'description'  => optional($order->package)->pname ?: $appName . ' plan',
            ]],
            'application_context' => [
                'return_url' => $callbackUrl,
                'cancel_url' => $callbackUrl . '?cancelled=1',
                'shipping_preference' => 'NO_SHIPPING',
                'user_action' => 'PAY_NOW',
            ],
        ];

        try {
            $r = Http::withToken($token)->timeout(self::HTTP_TIMEOUT_SECONDS)
                ->post($this->apiBase() . '/v2/checkout/orders', $body);
            if (!$r->successful()) return PaymentResult::failed('paypal: ' . ($r->json('message') ?: 'HTTP ' . $r->status()));
            $pp = $r->json();
            $approveUrl = collect($pp['links'] ?? [])->firstWhere('rel', 'approve')['href'] ?? null;
            if (!$approveUrl) return PaymentResult::failed('paypal_no_approve_link');
            return PaymentResult::redirect($approveUrl, $pp['id'] ?? null, $pp);
        } catch (\Throwable $e) {
            return PaymentResult::failed('paypal_exception: ' . $e->getMessage());
        }
    }

    public function handleCallback(array $payload): PaymentResult
    {
        if (!empty($payload['cancelled'])) return PaymentResult::failed('cancelled_by_user');

        // Subscription approval returns ?subscription_id=I-… instead of ?token=.
        if (!empty($payload['subscription_id'])) {
            return $this->confirmSubscription((string) $payload['subscription_id']);
        }

        $orderId = (string) ($payload['token'] ?? '');
        if ($orderId === '') return PaymentResult::failed('missing_paypal_order_id');

        $token = $this->accessToken();
        if (!$token) return PaymentResult::failed('paypal_token_failed');

        try {
            $r = Http::withToken($token)->timeout(self::HTTP_TIMEOUT_SECONDS)
                ->post($this->apiBase() . '/v2/checkout/orders/' . $orderId . '/capture');
            if (!$r->successful()) return PaymentResult::failed('paypal_capture: ' . $r->status());
            $body = $r->json();
            if (($body['status'] ?? '') === 'COMPLETED') {
                $captureId = $body['purchase_units'][0]['payments']['captures'][0]['id'] ?? null;
                return PaymentResult::paid(gatewayPaymentId: $captureId, gatewayOrderId: $orderId, payload: $body);
            }
            return PaymentResult::failed('paypal_status: ' . ($body['status'] ?? '?'), $body);
        } catch (\Throwable $e) {
            return PaymentResult::failed('paypal_capture_exception: ' . $e->getMessage());
        }
    }

    // ── Recurring subscriptions ──────────────────────────────────────
    //
    // Verified against PayPal Subscriptions v1:
    //   POST /v1/catalogs/products    (one product to hang plans off)
    //   POST /v1/billing/plans        (billing_cycles + pricing_scheme)
    //   POST /v1/billing/subscriptions(plan_id, application_context.return_url)
    // User approves at the rel:"approve" link; first payment + every renewal
    // fire PAYMENT.SALE.COMPLETED (resource.billing_agreement_id = I-…).

    public function supportsRecurring(): bool
    {
        return true;
    }

    public function createSubscription(Order $order, string $callbackUrl): PaymentResult
    {
        $token = $this->accessToken();
        if (!$token) return PaymentResult::failed('paypal_token_failed');

        $plan    = $this->planInterval($order);                      // interval=day/week/month/year, count
        $appName = \App\Models\SystemSetting::get('app_name', config('app.name', 'WaDesk'));
        $name    = optional($order->package)->pname ?: $appName . ' plan';
        $base    = $this->apiBase();

        try {
            // 1. Product
            $prodRes = Http::withToken($token)->timeout(self::HTTP_TIMEOUT_SECONDS)
                ->post($base . '/v1/catalogs/products', [
                    'name'     => $appName . ' subscription',
                    'type'     => 'SERVICE',
                    'category' => 'SOFTWARE',
                ]);
            if (!$prodRes->successful()) return PaymentResult::failed('paypal_product: ' . ($prodRes->json('message') ?: 'HTTP ' . $prodRes->status()));
            $productId = (string) $prodRes->json('id');

            // 2. Plan
            $planRes = Http::withToken($token)->timeout(self::HTTP_TIMEOUT_SECONDS)
                ->post($base . '/v1/billing/plans', [
                    'product_id'   => $productId,
                    'name'         => $name,
                    'status'       => 'ACTIVE',
                    'billing_cycles' => [[
                        'frequency'   => ['interval_unit' => strtoupper($plan['interval']), 'interval_count' => $plan['count']],
                        'tenure_type' => 'REGULAR',
                        'sequence'    => 1,
                        'total_cycles' => 0,                          // 0 = infinite (runs until cancelled)
                        'pricing_scheme' => ['fixed_price' => [
                            'value'         => number_format((float) $order->amount, 2, '.', ''),
                            'currency_code' => strtoupper($order->currency),
                        ]],
                    ]],
                    'payment_preferences' => [
                        'auto_bill_outstanding'     => true,
                        'setup_fee_failure_action'  => 'CONTINUE',
                        'payment_failure_threshold' => 1,
                    ],
                ]);
            if (!$planRes->successful()) return PaymentResult::failed('paypal_plan: ' . ($planRes->json('message') ?: 'HTTP ' . $planRes->status()));
            $planId = (string) $planRes->json('id');

            // 3. Subscription
            $subRes = Http::withToken($token)->timeout(self::HTTP_TIMEOUT_SECONDS)
                ->post($base . '/v1/billing/subscriptions', [
                    'plan_id'   => $planId,
                    'custom_id' => (string) $order->id,
                    'application_context' => [
                        'brand_name'          => $appName,
                        'return_url'          => $callbackUrl,
                        'cancel_url'          => $callbackUrl . '?cancelled=1',
                        'user_action'         => 'SUBSCRIBE_NOW',
                        'shipping_preference' => 'NO_SHIPPING',
                    ],
                ]);
            if (!$subRes->successful()) return PaymentResult::failed('paypal_subscription: ' . ($subRes->json('message') ?: 'HTTP ' . $subRes->status()));
            $sub = $subRes->json();
            $approveUrl = collect($sub['links'] ?? [])->firstWhere('rel', 'approve')['href'] ?? null;
            if (!$approveUrl) return PaymentResult::failed('paypal_no_approve_link');

            return PaymentResult::redirect($approveUrl, $sub['id'] ?? null, $sub + ['gateway_plan_id' => $planId]);
        } catch (\Throwable $e) {
            return PaymentResult::failed('paypal_subscription_exception: ' . $e->getMessage());
        }
    }

    /** GET the subscription after approval and confirm it's live. */
    private function confirmSubscription(string $subscriptionId): PaymentResult
    {
        $token = $this->accessToken();
        if (!$token) return PaymentResult::failed('paypal_token_failed');
        try {
            $r = Http::withToken($token)->timeout(self::HTTP_TIMEOUT_SECONDS)
                ->get($this->apiBase() . '/v1/billing/subscriptions/' . $subscriptionId);
            if (!$r->successful()) return PaymentResult::failed('paypal_subscription_fetch: ' . $r->status());
            $body   = $r->json();
            $status = (string) ($body['status'] ?? '');
            if (in_array($status, ['ACTIVE', 'APPROVED'], true)) {
                $periodEnd = $body['billing_info']['next_billing_time'] ?? null;
                return PaymentResult::paid(
                    gatewayPaymentId: $body['id'] ?? $subscriptionId,
                    gatewayOrderId:   $subscriptionId,
                    payload:          $body + ['is_subscription' => true, 'current_period_end' => $periodEnd],
                );
            }
            return PaymentResult::failed('paypal_subscription_status: ' . $status, $body);
        } catch (\Throwable $e) {
            return PaymentResult::failed('paypal_subscription_confirm_exception: ' . $e->getMessage());
        }
    }

    public function parseSubscriptionWebhook(array $payload): ?array
    {
        $event = (string) ($payload['event_type'] ?? '');
        $res   = $payload['resource'] ?? [];
        if (!is_array($res)) return null;

        switch ($event) {
            case 'PAYMENT.SALE.COMPLETED':
                // A subscription cycle was charged. billing_agreement_id is the
                // I-… subscription id; absent on one-off sales (return null).
                $subId = $res['billing_agreement_id'] ?? null;
                if (!$subId) return null;
                return ['type' => 'renewed', 'subscription_id' => $subId, 'payment_id' => $res['id'] ?? null, 'period_end' => null];

            case 'BILLING.SUBSCRIPTION.ACTIVATED':
                $periodEnd = $res['billing_info']['next_billing_time'] ?? null;
                return ['type' => 'created', 'subscription_id' => $res['id'] ?? null, 'payment_id' => null, 'period_end' => $periodEnd, 'order_id' => $res['custom_id'] ?? null];

            case 'BILLING.SUBSCRIPTION.CANCELLED':
            case 'BILLING.SUBSCRIPTION.EXPIRED':
                return ['type' => 'canceled', 'subscription_id' => $res['id'] ?? null, 'payment_id' => null, 'period_end' => null];

            case 'BILLING.SUBSCRIPTION.PAYMENT.FAILED':
            case 'BILLING.SUBSCRIPTION.SUSPENDED':
                return ['type' => 'payment_failed', 'subscription_id' => $res['id'] ?? null, 'payment_id' => null, 'period_end' => null];

            default:
                return null;
        }
    }

    public function cancelSubscription(string $gatewaySubscriptionId, array $context = []): PaymentResult
    {
        $token = $this->accessToken();
        if (!$token) return PaymentResult::failed('paypal_token_failed');
        try {
            $r = Http::withToken($token)->timeout(self::HTTP_TIMEOUT_SECONDS)
                ->post($this->apiBase() . '/v1/billing/subscriptions/' . $gatewaySubscriptionId . '/cancel', ['reason' => 'Cancelled by user']);
            // 204 No Content on success.
            if ($r->status() === 204 || $r->successful()) {
                return PaymentResult::paid(gatewayOrderId: $gatewaySubscriptionId, payload: ['cancelled' => true]);
            }
            return PaymentResult::failed('paypal_cancel: ' . ($r->json('message') ?: 'HTTP ' . $r->status()));
        } catch (\Throwable $e) {
            return PaymentResult::failed('paypal_cancel_exception: ' . $e->getMessage());
        }
    }
}
