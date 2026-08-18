<?php

namespace App\Services\Payment\Drivers;

use App\Models\Order;
use App\Services\Payment\AbstractGatewayDriver;
use App\Services\Payment\PaymentResult;
use Illuminate\Support\Facades\Http;

/**
 * Razorpay via the Standard Checkout flow (no Composer SDK).
 *
 * Endpoints:
 *   POST /v1/orders           — create an order
 *   GET  /v1/payments/{id}    — fetch payment to verify
 *
 * Flow:
 *   1. initiate() → POST /v1/orders → returns Razorpay order_id
 *   2. We render an inline form with Razorpay's hosted checkout.js
 *      that opens the modal — on success it submits razorpay_payment_id +
 *      razorpay_order_id + razorpay_signature back to our callback URL.
 *   3. handleCallback() verifies the HMAC-SHA256 signature.
 */
class RazorpayDriver extends AbstractGatewayDriver
{
    public static function credentialFields(): array
    {
        return [
            'key_id'         => ['label' => 'Key ID',         'type' => 'text',     'required' => true,  'hint' => 'rzp_live_… / rzp_test_…'],
            'key_secret'     => ['label' => 'Key secret',     'type' => 'password', 'required' => true],
            'webhook_secret' => ['label' => 'Webhook secret', 'type' => 'password', 'required' => false, 'hint' => 'Optional — for X-Razorpay-Signature verification'],
        ];
    }

    public function initiate(Order $order, string $callbackUrl): PaymentResult
    {
        $keyId     = (string) $this->cred('key_id');
        $keySecret = (string) $this->cred('key_secret');
        if ($keyId === '' || $keySecret === '') return PaymentResult::failed('razorpay_credentials_missing');

        $amountMinor = (int) round((float) $order->amount * 100);
        try {
            $r = Http::withBasicAuth($keyId, $keySecret)->timeout(self::HTTP_TIMEOUT_SECONDS)
                ->post('https://api.razorpay.com/v1/orders', [
                    'amount'   => $amountMinor,
                    'currency' => strtoupper($order->currency),
                    'receipt'  => $order->order_number,
                    'notes'    => ['order_id' => (string) $order->id, 'workspace_id' => (string) $order->workspace_id],
                ]);
            if (!$r->successful()) return PaymentResult::failed('razorpay: ' . ($r->json('error.description') ?: 'HTTP ' . $r->status()));
            $rzpOrder = $r->json();

            // Build the inline HTML form that opens checkout.js.
            $html = view('checkout.razorpay-form', [
                'key_id'    => $keyId,
                'order'     => $order,
                'rzpOrder'  => $rzpOrder,
                'callback'  => $callbackUrl,
            ])->render();
            return PaymentResult::form($html, $rzpOrder['id'] ?? null, $rzpOrder);
        } catch (\Throwable $e) {
            return PaymentResult::failed('razorpay_exception: ' . $e->getMessage());
        }
    }

    public function handleCallback(array $payload): PaymentResult
    {
        // Subscription checkout returns a subscription id + a DIFFERENT
        // signature basis (payment_id|subscription_id). Branch on it.
        if (!empty($payload['razorpay_subscription_id'])) {
            $subId     = (string) $payload['razorpay_subscription_id'];
            $paymentId = (string) ($payload['razorpay_payment_id'] ?? '');
            $signature = (string) ($payload['razorpay_signature']  ?? '');
            if ($paymentId === '' || $signature === '') return PaymentResult::failed('missing_razorpay_sub_params');

            $expected = hash_hmac('sha256', $paymentId . '|' . $subId, (string) $this->cred('key_secret'));
            if (!hash_equals($expected, $signature)) return PaymentResult::failed('razorpay_sub_signature_mismatch');

            return PaymentResult::paid(
                gatewayPaymentId: $paymentId,
                gatewayOrderId:   $subId,                              // sub_… is our join key
                payload:          $payload + ['is_subscription' => true],
            );
        }

        $orderId   = (string) ($payload['razorpay_order_id']   ?? '');
        $paymentId = (string) ($payload['razorpay_payment_id'] ?? '');
        $signature = (string) ($payload['razorpay_signature']  ?? '');
        if ($orderId === '' || $paymentId === '' || $signature === '') return PaymentResult::failed('missing_razorpay_params');

        $expected = hash_hmac('sha256', $orderId . '|' . $paymentId, (string) $this->cred('key_secret'));
        if (!hash_equals($expected, $signature)) return PaymentResult::failed('razorpay_signature_mismatch');

        return PaymentResult::paid(
            gatewayPaymentId: $paymentId,
            gatewayOrderId:   $orderId,
            payload:          $payload,
        );
    }

    public function verifyWebhookSignature(string $rawBody, ?string $signatureHeader): bool
    {
        $secret = (string) $this->cred('webhook_secret');
        if ($secret === '' || $signatureHeader === null) return true;
        return hash_equals(hash_hmac('sha256', $rawBody, $secret), $signatureHeader);
    }

    // ── Recurring subscriptions ──────────────────────────────────────
    //
    // Verified against Razorpay Subscriptions API:
    //   POST /v1/plans         (period + interval + item{name,amount,currency})
    //   POST /v1/subscriptions (plan_id, total_count, customer_notify, notes)
    // We open the subscription with checkout.js (subscription_id) so the first
    // charge returns to our callback with a signature; renewals arrive as
    // subscription.charged webhooks.

    public function supportsRecurring(): bool
    {
        return true;
    }

    public function createSubscription(Order $order, string $callbackUrl): PaymentResult
    {
        $keyId     = (string) $this->cred('key_id');
        $keySecret = (string) $this->cred('key_secret');
        if ($keyId === '' || $keySecret === '') return PaymentResult::failed('razorpay_credentials_missing');

        $plan        = $this->planInterval($order);                  // interval=day/week/month/year, count
        $period      = match ($plan['interval']) {
            'day'   => 'daily',
            'week'  => 'weekly',
            'year'  => 'yearly',
            default => 'monthly',
        };
        // Razorpay needs a fixed cycle count; pick a long horizon per period so
        // it effectively runs until cancelled.
        $totalCount  = match ($period) {
            'yearly'  => 10,
            'weekly'  => 520,
            'daily'   => 3650,
            default   => 120,
        };
        $amountMinor = (int) round((float) $order->amount * 100);
        $appName     = \App\Models\SystemSetting::get('app_name', config('app.name', 'WaDesk'));

        try {
            // 1. Plan
            $planRes = Http::withBasicAuth($keyId, $keySecret)->timeout(self::HTTP_TIMEOUT_SECONDS)
                ->post('https://api.razorpay.com/v1/plans', [
                    'period'   => $period,
                    'interval' => $plan['count'],
                    'item'     => [
                        'name'     => optional($order->package)->pname ?: $appName . ' plan',
                        'amount'   => $amountMinor,
                        'currency' => strtoupper($order->currency),
                    ],
                    'notes'    => ['order_id' => (string) $order->id, 'workspace_id' => (string) $order->workspace_id],
                ]);
            if (!$planRes->successful()) return PaymentResult::failed('razorpay_plan: ' . ($planRes->json('error.description') ?: 'HTTP ' . $planRes->status()));
            $planId = (string) $planRes->json('id');

            // 2. Subscription
            $subRes = Http::withBasicAuth($keyId, $keySecret)->timeout(self::HTTP_TIMEOUT_SECONDS)
                ->post('https://api.razorpay.com/v1/subscriptions', [
                    'plan_id'         => $planId,
                    'total_count'     => $totalCount,
                    'customer_notify' => 1,
                    'notes'           => ['order_id' => (string) $order->id, 'workspace_id' => (string) $order->workspace_id],
                ]);
            if (!$subRes->successful()) return PaymentResult::failed('razorpay_subscription: ' . ($subRes->json('error.description') ?: 'HTTP ' . $subRes->status()));
            $subscription = $subRes->json();

            $html = view('checkout.razorpay-subscription-form', [
                'key_id'       => $keyId,
                'order'        => $order,
                'subscription' => $subscription,
                'callback'     => $callbackUrl,
            ])->render();

            // gatewayOrderId = sub_… so recordPending() can store it immediately.
            return PaymentResult::form($html, $subscription['id'] ?? null, $subscription + ['gateway_plan_id' => $planId]);
        } catch (\Throwable $e) {
            return PaymentResult::failed('razorpay_subscription_exception: ' . $e->getMessage());
        }
    }

    public function parseSubscriptionWebhook(array $payload): ?array
    {
        $event     = (string) ($payload['event'] ?? '');
        $subEntity = $payload['payload']['subscription']['entity'] ?? [];
        if (!is_array($subEntity)) return null;
        $subId     = $subEntity['id'] ?? null;
        if (!$subId) return null;

        switch ($event) {
            case 'subscription.charged':
                // Fires on the first charge AND every renewal — the controller
                // distinguishes by the local subscription status.
                return [
                    'type'            => 'renewed',
                    'subscription_id' => $subId,
                    'payment_id'      => $payload['payload']['payment']['entity']['id'] ?? null,
                    'period_end'      => $subEntity['current_end'] ?? null,
                    'order_id'        => $subEntity['notes']['order_id'] ?? null,
                ];
            case 'subscription.cancelled':
            case 'subscription.completed':
                return ['type' => 'canceled', 'subscription_id' => $subId, 'payment_id' => null, 'period_end' => null];
            case 'subscription.halted':
            case 'subscription.pending':
                return ['type' => 'payment_failed', 'subscription_id' => $subId, 'payment_id' => null, 'period_end' => null];
            default:
                return null;
        }
    }

    public function cancelSubscription(string $gatewaySubscriptionId, array $context = []): PaymentResult
    {
        $keyId     = (string) $this->cred('key_id');
        $keySecret = (string) $this->cred('key_secret');
        if ($keyId === '' || $keySecret === '') return PaymentResult::failed('razorpay_credentials_missing');
        try {
            $r = Http::withBasicAuth($keyId, $keySecret)->timeout(self::HTTP_TIMEOUT_SECONDS)
                ->post('https://api.razorpay.com/v1/subscriptions/' . $gatewaySubscriptionId . '/cancel', ['cancel_at_cycle_end' => 0]);
            if (!$r->successful()) return PaymentResult::failed('razorpay_cancel: ' . ($r->json('error.description') ?: 'HTTP ' . $r->status()));
            return PaymentResult::paid(gatewayOrderId: $gatewaySubscriptionId, payload: $r->json());
        } catch (\Throwable $e) {
            return PaymentResult::failed('razorpay_cancel_exception: ' . $e->getMessage());
        }
    }
}
