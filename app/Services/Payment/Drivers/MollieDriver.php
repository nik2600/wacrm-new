<?php

namespace App\Services\Payment\Drivers;

use App\Models\Order;
use App\Services\Payment\AbstractGatewayDriver;
use App\Services\Payment\PaymentResult;
use Illuminate\Support\Facades\Http;

/**
 * Mollie payment gateway driver.
 *
 * Uses the Mollie REST API v2 (no Composer SDK).
 * Creates a payment, redirects to Mollie-hosted checkout, then verifies
 * the status on callback / webhook.
 *
 * @see https://docs.mollie.com/reference/v2/payments-api/create-payment
 */
class MollieDriver extends AbstractGatewayDriver
{
    private const API_BASE = 'https://api.mollie.com/v2';

    public static function credentialFields(): array
    {
        return [
            'api_key' => ['label' => 'API Key', 'type' => 'password', 'required' => true, 'hint' => 'live_… / test_…'],
        ];
    }

    public function initiate(Order $order, string $callbackUrl): PaymentResult
    {
        $apiKey = (string) $this->cred('api_key');
        if ($apiKey === '') return PaymentResult::failed('mollie_api_key_missing');

        $body = [
            'amount' => [
                'currency' => strtoupper($order->currency ?? 'EUR'),
                'value'    => number_format((float) $order->amount, 2, '.', ''),
            ],
            'description' => "Order #{$order->order_number}",
            'redirectUrl' => $callbackUrl . (str_contains($callbackUrl, '?') ? '&' : '?') . 'order_number=' . urlencode($order->order_number),
            'webhookUrl'  => route('payment.webhook', ['gateway' => 'mollie']),
            'metadata'    => [
                'order_id'     => $order->id,
                'order_number' => $order->order_number,
            ],
        ];

        try {
            $r = Http::withToken($apiKey)->timeout(self::HTTP_TIMEOUT_SECONDS)
                ->post(self::API_BASE . '/payments', $body);
            $json = $r->json() ?: [];
            if (isset($json['_links']['checkout']['href'])) {
                return PaymentResult::redirect($json['_links']['checkout']['href'], $json['id'] ?? null, $json);
            }
            return PaymentResult::failed('mollie: ' . ($json['detail'] ?? $json['title'] ?? 'create_failed'));
        } catch (\Throwable $e) {
            return PaymentResult::failed('mollie_exception: ' . $e->getMessage());
        }
    }

    public function handleCallback(array $payload): PaymentResult
    {
        $paymentId = $payload['id'] ?? $payload['payment_id'] ?? null;
        if (!$paymentId) {
            // Mollie does not include payment data on the redirect — caller should
            // re-verify against the stored gateway_order_id.
            return new PaymentResult(status: 'pending', payload: $payload);
        }
        return $this->checkPaymentStatus((string) $paymentId);
    }

    public function handleWebhook(array $payload): PaymentResult
    {
        $paymentId = $payload['id'] ?? null;
        if (!$paymentId) return PaymentResult::failed('missing_mollie_payment_id');
        return $this->checkPaymentStatus((string) $paymentId);
    }

    public function verify(Order $order): PaymentResult
    {
        $paymentId = $order->gateway_order_id ?? $order->gateway_payment_id;
        if (!$paymentId) return PaymentResult::failed('no_transaction_id');
        return $this->checkPaymentStatus((string) $paymentId);
    }

    private function checkPaymentStatus(string $paymentId): PaymentResult
    {
        $apiKey = (string) $this->cred('api_key');
        try {
            $r = Http::withToken($apiKey)->timeout(self::HTTP_TIMEOUT_SECONDS)
                ->get(self::API_BASE . '/payments/' . $paymentId);
            $json   = $r->json() ?: [];
            $status = (string) ($json['status'] ?? '');

            if ($status === 'paid') {
                return PaymentResult::paid(
                    gatewayPaymentId: $paymentId,
                    gatewayOrderId:   $paymentId,
                    payload:          $json,
                );
            }
            if (in_array($status, ['pending', 'open', 'authorized'], true)) {
                return new PaymentResult(status: 'pending', gatewayOrderId: $paymentId, payload: $json);
            }
            return PaymentResult::failed("mollie_status: {$status}", $json);
        } catch (\Throwable $e) {
            return PaymentResult::failed('mollie_status_exception: ' . $e->getMessage());
        }
    }

    // ── Recurring subscriptions ──────────────────────────────────────
    //
    // Verified: Mollie is mandate-first. A "first" payment (hosted redirect)
    // establishes a mandate against a customer; only then can a subscription be
    // created. So: createSubscription opens the first payment; once it's paid
    // (callback) we create the actual subscription. Renewals arrive as ordinary
    // payment webhooks that carry a subscriptionId — there is no dedicated
    // event, so parseSubscriptionWebhook fetches the payment to decide.

    public function supportsRecurring(): bool
    {
        return true;
    }

    public function createSubscription(Order $order, string $callbackUrl): PaymentResult
    {
        $apiKey = (string) $this->cred('api_key');
        if ($apiKey === '') return PaymentResult::failed('mollie_api_key_missing');

        try {
            // 1. Customer
            $custRes = Http::withToken($apiKey)->timeout(self::HTTP_TIMEOUT_SECONDS)
                ->post(self::API_BASE . '/customers', [
                    'name'  => optional($order->user)->name ?: ('Order ' . $order->order_number),
                    'email' => optional($order->user)->email ?: null,
                ]);
            $customerId = (string) ($custRes->json('id') ?? '');
            if ($customerId === '') return PaymentResult::failed('mollie_customer_failed');

            // 2. First payment (establishes the mandate)
            $payRes = Http::withToken($apiKey)->timeout(self::HTTP_TIMEOUT_SECONDS)
                ->post(self::API_BASE . '/payments', [
                    'amount'       => ['currency' => strtoupper($order->currency ?? 'EUR'), 'value' => number_format((float) $order->amount, 2, '.', '')],
                    'customerId'   => $customerId,
                    'sequenceType' => 'first',
                    'description'  => "Order #{$order->order_number}",
                    'redirectUrl'  => $callbackUrl,
                    'webhookUrl'   => route('payment.webhook', ['gateway' => 'mollie']),
                    'metadata'     => ['order_id' => (string) $order->id, 'workspace_id' => (string) $order->workspace_id, 'recurring' => '1'],
                ]);
            $payJson = $payRes->json() ?: [];
            if (isset($payJson['_links']['checkout']['href'])) {
                return PaymentResult::redirect($payJson['_links']['checkout']['href'], $payJson['id'] ?? null, $payJson + [
                    'gateway_customer_id' => $customerId,
                ]);
            }
            return PaymentResult::failed('mollie_subscription: ' . ($payJson['detail'] ?? 'first_payment_failed'));
        } catch (\Throwable $e) {
            return PaymentResult::failed('mollie_subscription_exception: ' . $e->getMessage());
        }
    }

    public function handleSubscriptionCallback(array $payload, Order $order): PaymentResult
    {
        $apiKey    = (string) $this->cred('api_key');
        $paymentId = (string) ($order->gateway_order_id ?: ($payload['id'] ?? ''));
        if ($paymentId === '') return new PaymentResult(status: 'pending', payload: $payload);

        try {
            $pay    = Http::withToken($apiKey)->timeout(self::HTTP_TIMEOUT_SECONDS)->get(self::API_BASE . '/payments/' . $paymentId)->json() ?: [];
            $status = (string) ($pay['status'] ?? '');
            if ($status !== 'paid') {
                if (in_array($status, ['pending', 'open', 'authorized'], true)) return new PaymentResult(status: 'pending', gatewayOrderId: $paymentId, payload: $pay);
                return PaymentResult::failed("mollie_status: {$status}", $pay);
            }

            $customerId = (string) ($pay['customerId'] ?? '');
            if ($customerId === '') return PaymentResult::paid(gatewayPaymentId: $paymentId, gatewayOrderId: $paymentId, payload: $pay);

            // Mandate now valid → create the recurring subscription.
            $plan     = $this->planInterval($order);
            $interval = match ($plan['interval']) {
                'day'   => $plan['count'] . ' days',
                'week'  => $plan['count'] . ' weeks',
                'year'  => '12 months',                              // Mollie max interval is 1 year
                default => $plan['count'] . ' months',
            };
            $subRes = Http::withToken($apiKey)->timeout(self::HTTP_TIMEOUT_SECONDS)
                ->post(self::API_BASE . "/customers/{$customerId}/subscriptions", [
                    'amount'      => ['currency' => strtoupper($order->currency ?? 'EUR'), 'value' => number_format((float) $order->amount, 2, '.', '')],
                    'interval'    => $interval,
                    'description' => 'Sub ' . $order->order_number,   // must be unique per customer
                    'webhookUrl'  => route('payment.webhook', ['gateway' => 'mollie']),
                    'metadata'    => ['order_id' => (string) $order->id],
                ]);
            $sub = $subRes->json() ?: [];

            return PaymentResult::paid(
                gatewayPaymentId: $paymentId,
                gatewayOrderId:   $sub['id'] ?? $paymentId,
                payload:          $pay + [
                    'is_subscription'    => true,
                    'customer'           => $customerId,
                    'current_period_end' => $sub['nextPaymentDate'] ?? null,
                ],
            );
        } catch (\Throwable $e) {
            return PaymentResult::failed('mollie_subscription_callback_exception: ' . $e->getMessage());
        }
    }

    public function parseSubscriptionWebhook(array $payload): ?array
    {
        // Mollie webhooks carry only { id: tr_… } — fetch the payment to see if
        // it belongs to a subscription (recurring charge) vs a one-off.
        $paymentId = (string) ($payload['id'] ?? '');
        if ($paymentId === '' || !str_starts_with($paymentId, 'tr_')) return null;

        $apiKey = (string) $this->cred('api_key');
        try {
            $pay = Http::withToken($apiKey)->timeout(self::HTTP_TIMEOUT_SECONDS)->get(self::API_BASE . '/payments/' . $paymentId)->json() ?: [];
        } catch (\Throwable $e) {
            return null;
        }
        $subId = $pay['subscriptionId'] ?? null;
        if (!$subId) return null;                                    // first/one-off payment → one-time path

        $status = (string) ($pay['status'] ?? '');
        $ok     = $status === 'paid';
        return [
            'type'            => $ok ? 'renewed' : 'payment_failed',
            'subscription_id' => $subId,
            'payment_id'      => $paymentId,
            'period_end'      => null,
            'order_id'        => $pay['metadata']['order_id'] ?? null,
        ];
    }

    public function cancelSubscription(string $gatewaySubscriptionId, array $context = []): PaymentResult
    {
        $apiKey     = (string) $this->cred('api_key');
        $customerId = (string) ($context['gateway_customer_id'] ?? '');
        if ($apiKey === '' || $customerId === '') return PaymentResult::failed('mollie_cancel_context_missing');
        try {
            $r = Http::withToken($apiKey)->timeout(self::HTTP_TIMEOUT_SECONDS)
                ->delete(self::API_BASE . "/customers/{$customerId}/subscriptions/{$gatewaySubscriptionId}");
            if (!$r->successful()) return PaymentResult::failed('mollie_cancel: HTTP ' . $r->status());
            return PaymentResult::paid(gatewayOrderId: $gatewaySubscriptionId, payload: $r->json());
        } catch (\Throwable $e) {
            return PaymentResult::failed('mollie_cancel_exception: ' . $e->getMessage());
        }
    }
}
