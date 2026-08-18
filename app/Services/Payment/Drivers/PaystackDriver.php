<?php

namespace App\Services\Payment\Drivers;

use App\Models\Order;
use App\Services\Payment\AbstractGatewayDriver;
use App\Services\Payment\PaymentResult;
use Illuminate\Support\Facades\Http;

/**
 * Paystack payment gateway driver.
 *
 * Initialises a Paystack transaction, redirects to Paystack-hosted
 * checkout, then verifies the transaction reference on callback.
 *
 * @see https://paystack.com/docs/api/transaction/
 */
class PaystackDriver extends AbstractGatewayDriver
{
    private const API_BASE = 'https://api.paystack.co';

    public static function credentialFields(): array
    {
        return [
            'public_key' => ['label' => 'Public Key', 'type' => 'text',     'required' => true],
            'secret_key' => ['label' => 'Secret Key', 'type' => 'password', 'required' => true],
        ];
    }

    public function initiate(Order $order, string $callbackUrl): PaymentResult
    {
        $secretKey = (string) $this->cred('secret_key');
        if ($secretKey === '') return PaymentResult::failed('paystack_secret_key_missing');

        $email = optional($order->user)->email;
        if (!$email) return PaymentResult::failed('paystack_customer_email_missing');

        $body = [
            'email'        => $email,
            'amount'       => (int) round((float) $order->amount * 100),    // kobo
            'currency'     => strtoupper($order->currency ?? 'NGN'),
            'reference'    => $order->order_number . '_' . time(),
            'callback_url' => $callbackUrl,
            'metadata'     => [
                'order_id'     => $order->id,
                'order_number' => $order->order_number,
            ],
        ];

        try {
            $r = Http::withToken($secretKey)->timeout(self::HTTP_TIMEOUT_SECONDS)
                ->post(self::API_BASE . '/transaction/initialize', $body);
            $json = $r->json() ?: [];
            if (($json['status'] ?? false) === true && isset($json['data']['authorization_url'])) {
                return PaymentResult::redirect(
                    $json['data']['authorization_url'],
                    $json['data']['reference'] ?? null,
                    $json,
                );
            }
            return PaymentResult::failed('paystack: ' . ($json['message'] ?? 'init_failed'));
        } catch (\Throwable $e) {
            return PaymentResult::failed('paystack_exception: ' . $e->getMessage());
        }
    }

    public function handleCallback(array $payload): PaymentResult
    {
        $reference = $payload['reference'] ?? $payload['trxref'] ?? null;
        if (!$reference) return PaymentResult::failed('missing_paystack_reference');

        $secretKey = (string) $this->cred('secret_key');
        try {
            $r = Http::withToken($secretKey)->timeout(self::HTTP_TIMEOUT_SECONDS)
                ->get(self::API_BASE . '/transaction/verify/' . urlencode($reference));
            $json = $r->json() ?: [];

            if (($json['status'] ?? false) === true && ($json['data']['status'] ?? '') === 'success') {
                return PaymentResult::paid(
                    gatewayPaymentId: (string) ($json['data']['id'] ?? $reference),
                    gatewayOrderId:   $reference,
                    payload:          $json,
                );
            }
            return PaymentResult::failed('paystack_status: ' . ($json['data']['status'] ?? 'unknown'), $json);
        } catch (\Throwable $e) {
            return PaymentResult::failed('paystack_callback_exception: ' . $e->getMessage());
        }
    }

    public function verifyWebhookSignature(string $rawBody, ?string $signatureHeader): bool
    {
        $secret = (string) $this->cred('secret_key');
        // Fail-OPEN only when no webhook secret is configured (merchant opted
        // out of verification). If a secret IS set but the signature header is
        // missing, REJECT — that is a forged/invalid webhook, not a pass.
        if ($secret === '') return true;
        if ($signatureHeader === null || $signatureHeader === '') return false;
        $expected = hash_hmac('sha512', $rawBody, $secret);
        return hash_equals($expected, $signatureHeader);
    }

    public function handleWebhook(array $payload): PaymentResult
    {
        $event = $payload['event'] ?? '';
        $data  = $payload['data'] ?? [];
        if ($event === 'charge.success' && ($data['status'] ?? '') === 'success') {
            return PaymentResult::paid(
                gatewayPaymentId: (string) ($data['id'] ?? $data['reference'] ?? ''),
                gatewayOrderId:   (string) ($data['reference'] ?? ''),
                payload:          $data,
            );
        }
        return PaymentResult::failed("unhandled_paystack_event: {$event}", $payload);
    }

    public function verify(Order $order): PaymentResult
    {
        $reference = $order->gateway_order_id;
        if (!$reference) return PaymentResult::failed('no_reference');
        return $this->handleCallback(['reference' => $reference]);
    }

    // ── Recurring subscriptions ──────────────────────────────────────
    //
    // Verified: create a Plan, then initialize a transaction WITH that plan —
    // Paystack auto-creates the subscription on first charge and tokenizes the
    // card. Each cycle fires invoice.update (status success). The plan code
    // (PLN_…) is our join key until the subscription_code (SUB_…) arrives via
    // subscription.create.

    public function supportsRecurring(): bool
    {
        return true;
    }

    public function createSubscription(Order $order, string $callbackUrl): PaymentResult
    {
        $secretKey = (string) $this->cred('secret_key');
        if ($secretKey === '') return PaymentResult::failed('paystack_secret_key_missing');
        $email = optional($order->user)->email;
        if (!$email) return PaymentResult::failed('paystack_customer_email_missing');

        $plan     = $this->planInterval($order);                     // interval=day/week/month/year, count
        $interval = match ($plan['interval']) {
            'day'   => 'daily',
            'week'  => 'weekly',
            'year'  => 'annually',
            default => 'monthly',
        };

        try {
            // 1. Plan
            $planRes = Http::withToken($secretKey)->timeout(self::HTTP_TIMEOUT_SECONDS)
                ->post(self::API_BASE . '/plan', [
                    'name'     => optional($order->package)->pname ?: (config('app.name') . ' plan'),
                    'interval' => $interval,
                    'amount'   => (int) round((float) $order->amount * 100),
                    'currency' => strtoupper($order->currency ?? 'NGN'),
                ]);
            $planJson = $planRes->json() ?: [];
            if (($planJson['status'] ?? false) !== true || empty($planJson['data']['plan_code'])) {
                return PaymentResult::failed('paystack_plan: ' . ($planJson['message'] ?? 'create_failed'));
            }
            $planCode = (string) $planJson['data']['plan_code'];

            // 2. Initialize the transaction with the plan (auto-subscribes)
            $reference = $order->order_number . '_' . time();
            $initRes = Http::withToken($secretKey)->timeout(self::HTTP_TIMEOUT_SECONDS)
                ->post(self::API_BASE . '/transaction/initialize', [
                    'email'        => $email,
                    'amount'       => (int) round((float) $order->amount * 100),
                    'currency'     => strtoupper($order->currency ?? 'NGN'),
                    'plan'         => $planCode,
                    'reference'    => $reference,
                    'callback_url' => $callbackUrl,
                    'metadata'     => ['order_id' => $order->id, 'order_number' => $order->order_number],
                ]);
            $initJson = $initRes->json() ?: [];
            if (($initJson['status'] ?? false) === true && isset($initJson['data']['authorization_url'])) {
                return PaymentResult::redirect(
                    $initJson['data']['authorization_url'],
                    $initJson['data']['reference'] ?? $reference,
                    $initJson + ['gateway_plan_id' => $planCode],
                );
            }
            return PaymentResult::failed('paystack_subscription: ' . ($initJson['message'] ?? 'init_failed'));
        } catch (\Throwable $e) {
            return PaymentResult::failed('paystack_subscription_exception: ' . $e->getMessage());
        }
    }

    public function parseSubscriptionWebhook(array $payload): ?array
    {
        $event = (string) ($payload['event'] ?? '');
        $data  = $payload['data'] ?? [];
        if (!is_array($data)) return null;

        switch ($event) {
            case 'subscription.create':
                return [
                    'type'            => 'created',
                    'subscription_id' => $data['subscription_code'] ?? null,
                    'gateway_plan_id' => $data['plan']['plan_code'] ?? null,
                    'payment_id'      => null,
                    'period_end'      => $data['next_payment_date'] ?? null,
                    'order_id'        => null,
                ];

            case 'invoice.update':
            case 'invoice.create':
                // A recurring charge. status success = renewal.
                $ok = ($data['status'] ?? '') === 'success' || ($data['paid'] ?? false) === true;
                $subCode = $data['subscription']['subscription_code'] ?? null;
                if (!$subCode) return null;
                return [
                    'type'            => $ok ? 'renewed' : 'payment_failed',
                    'subscription_id' => $subCode,
                    'gateway_plan_id' => $data['plan']['plan_code'] ?? null,
                    'payment_id'      => (string) ($data['transaction']['reference'] ?? ''),
                    'period_end'      => $data['subscription']['next_payment_date'] ?? null,
                    'order_id'        => null,
                ];

            case 'invoice.payment_failed':
                $subCode = $data['subscription']['subscription_code'] ?? null;
                if (!$subCode) return null;
                return ['type' => 'payment_failed', 'subscription_id' => $subCode, 'payment_id' => null, 'period_end' => null];

            case 'subscription.disable':
            case 'subscription.not_renew':
                return ['type' => 'canceled', 'subscription_id' => $data['subscription_code'] ?? null, 'payment_id' => null, 'period_end' => null];

            default:
                return null;
        }
    }

    public function cancelSubscription(string $gatewaySubscriptionId, array $context = []): PaymentResult
    {
        $secretKey = (string) $this->cred('secret_key');
        if ($secretKey === '') return PaymentResult::failed('paystack_secret_key_missing');
        try {
            // Disable requires the subscription's email_token alongside its code.
            $get = Http::withToken($secretKey)->timeout(self::HTTP_TIMEOUT_SECONDS)
                ->get(self::API_BASE . '/subscription/' . urlencode($gatewaySubscriptionId));
            $emailToken = $get->json('data.email_token');
            if (!$emailToken) return PaymentResult::failed('paystack_email_token_missing');

            $r = Http::withToken($secretKey)->timeout(self::HTTP_TIMEOUT_SECONDS)
                ->post(self::API_BASE . '/subscription/disable', ['code' => $gatewaySubscriptionId, 'token' => $emailToken]);
            if (($r->json('status') ?? false) !== true) return PaymentResult::failed('paystack_cancel_failed');
            return PaymentResult::paid(gatewayOrderId: $gatewaySubscriptionId, payload: $r->json());
        } catch (\Throwable $e) {
            return PaymentResult::failed('paystack_cancel_exception: ' . $e->getMessage());
        }
    }
}
