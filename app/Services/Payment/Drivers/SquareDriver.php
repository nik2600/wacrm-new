<?php

namespace App\Services\Payment\Drivers;

use App\Models\Order;
use App\Services\Payment\AbstractGatewayDriver;
use App\Services\Payment\PaymentResult;
use Illuminate\Support\Facades\Http;

/**
 * Square payment gateway driver.
 *
 * Uses the Square Online Checkout API to create a hosted payment link.
 *
 * @see https://developer.squareup.com/docs/checkout-api/
 */
class SquareDriver extends AbstractGatewayDriver
{
    private const SANDBOX_BASE = 'https://connect.squareupsandbox.com/v2';
    private const PROD_BASE    = 'https://connect.squareup.com/v2';

    public static function credentialFields(): array
    {
        return [
            'application_id'        => ['label' => 'Application ID',         'type' => 'text',     'required' => true],
            'access_token'          => ['label' => 'Access Token',           'type' => 'password', 'required' => true],
            'location_id'           => ['label' => 'Location ID',            'type' => 'text',     'required' => true],
            'webhook_signature_key' => ['label' => 'Webhook Signature Key', 'type' => 'password', 'required' => false],
        ];
    }

    public function initiate(Order $order, string $callbackUrl): PaymentResult
    {
        $accessToken = (string) $this->cred('access_token');
        $locationId  = (string) $this->cred('location_id');
        if ($accessToken === '' || $locationId === '') return PaymentResult::failed('square_credentials_missing');

        $body = [
            'idempotency_key' => 'sq_' . $order->order_number . '_' . time(),
            'order' => [
                'location_id' => $locationId,
                'line_items' => [[
                    'name'     => "Order #{$order->order_number}",
                    'quantity' => '1',
                    'base_price_money' => [
                        'amount'   => (int) round((float) $order->amount * 100),
                        'currency' => strtoupper($order->currency ?? 'USD'),
                    ],
                ]],
                'reference_id' => $order->order_number,
            ],
            'checkout_options' => [
                'redirect_url'             => $callbackUrl,
                'ask_for_shipping_address' => false,
                'allow_tipping'            => false,
            ],
        ];

        try {
            $r = Http::withToken($accessToken)
                ->withHeaders(['Square-Version' => '2024-01-18'])
                ->timeout(self::HTTP_TIMEOUT_SECONDS)
                ->post($this->baseUrl() . '/online-checkout/payment-links', $body);
            $json = $r->json() ?: [];
            if (isset($json['payment_link']['url'])) {
                return PaymentResult::redirect(
                    $json['payment_link']['url'],
                    $json['payment_link']['order_id'] ?? null,
                    $json,
                );
            }
            return PaymentResult::failed('square: ' . ($json['errors'][0]['detail'] ?? 'create_failed'));
        } catch (\Throwable $e) {
            return PaymentResult::failed('square_exception: ' . $e->getMessage());
        }
    }

    public function handleCallback(array $payload): PaymentResult
    {
        $orderId = $payload['orderId'] ?? $payload['order_id'] ?? null;
        $txnId   = $payload['transactionId'] ?? $payload['checkoutId'] ?? null;
        if (!$orderId && !$txnId) return PaymentResult::failed('missing_square_ids');

        if ($orderId) {
            $accessToken = (string) $this->cred('access_token');
            try {
                $r = Http::withToken($accessToken)
                    ->withHeaders(['Square-Version' => '2024-01-18'])
                    ->timeout(self::HTTP_TIMEOUT_SECONDS)
                    ->get($this->baseUrl() . "/orders/{$orderId}");
                $json    = $r->json() ?: [];
                $state   = $json['order']['state'] ?? '';
                $tenders = $json['order']['tenders'] ?? [];
                if ($state === 'COMPLETED') {
                    return PaymentResult::paid(
                        gatewayPaymentId: (string) ($tenders[0]['id'] ?? $orderId),
                        gatewayOrderId:   (string) $orderId,
                        payload:          $json,
                    );
                }
                return PaymentResult::failed("square_state: {$state}", $json);
            } catch (\Throwable $e) {
                return PaymentResult::failed('square_callback_exception: ' . $e->getMessage());
            }
        }

        return new PaymentResult(status: 'pending', gatewayPaymentId: (string) $txnId);
    }

    public function verifyWebhookSignature(string $rawBody, ?string $signatureHeader): bool
    {
        $key = (string) $this->cred('webhook_signature_key');
        // Skip only when no signature key is configured; reject when a key is
        // set but the x-square-hmacsha256-signature header is absent.
        if ($key === '') return true;
        if ($signatureHeader === null) return false;
        $notificationUrl = route('payment.webhook', ['gateway' => 'square']);
        $expected = base64_encode(hash_hmac('sha256', $notificationUrl . $rawBody, $key, true));
        return hash_equals($expected, $signatureHeader);
    }

    public function handleWebhook(array $payload): PaymentResult
    {
        $type    = $payload['type'] ?? '';
        $data    = $payload['data']['object'] ?? $payload['data'] ?? [];
        $payment = $data['payment'] ?? $data;
        if (in_array($type, ['payment.created', 'payment.updated'], true) && ($payment['status'] ?? '') === 'COMPLETED') {
            return PaymentResult::paid(
                gatewayPaymentId: (string) ($payment['id'] ?? ''),
                gatewayOrderId:   (string) ($payment['order_id'] ?? ''),
                payload:          $payment,
            );
        }
        return PaymentResult::failed("unhandled_square_event: {$type}", $payload);
    }

    public function verify(Order $order): PaymentResult
    {
        $txnId = $order->gateway_payment_id;
        if (!$txnId) return PaymentResult::failed('no_transaction_id');
        $accessToken = (string) $this->cred('access_token');
        try {
            $r = Http::withToken($accessToken)
                ->withHeaders(['Square-Version' => '2024-01-18'])
                ->timeout(self::HTTP_TIMEOUT_SECONDS)
                ->get($this->baseUrl() . "/payments/{$txnId}");
            $json    = $r->json() ?: [];
            $payment = $json['payment'] ?? [];
            if (($payment['status'] ?? '') === 'COMPLETED') {
                return PaymentResult::paid(
                    gatewayPaymentId: (string) $txnId,
                    gatewayOrderId:   (string) ($payment['order_id'] ?? ''),
                    payload:          $json,
                );
            }
            return PaymentResult::failed("square_verify_status: " . ($payment['status'] ?? 'unknown'), $json);
        } catch (\Throwable $e) {
            return PaymentResult::failed('square_verify_exception: ' . $e->getMessage());
        }
    }

    // ── Recurring subscriptions ──────────────────────────────────────
    //
    // Verified: Square can only auto-charge a saved card (needs the Web
    // Payments SDK, client-side JS). The SDK-free path is a card-less
    // subscription: Square emails an invoice each cycle and the customer pays
    // it. So we create a catalog plan + variation + customer + subscription;
    // the first invoice is emailed, and invoice.payment_made (each cycle)
    // renews the plan. The subscription id is known immediately = our join key.

    public function supportsRecurring(): bool
    {
        return true;
    }

    public function createSubscription(Order $order, string $callbackUrl): PaymentResult
    {
        $accessToken = (string) $this->cred('access_token');
        $locationId  = (string) $this->cred('location_id');
        if ($accessToken === '' || $locationId === '') return PaymentResult::failed('square_credentials_missing');
        $email = optional($order->user)->email;
        if (!$email) return PaymentResult::failed('square_customer_email_missing');

        $plan    = $this->planInterval($order);
        $cadence = match ($plan['interval']) {
            'day'   => 'DAILY',
            'week'  => 'WEEKLY',
            'year'  => 'ANNUAL',
            default => 'MONTHLY',
        };
        $headers = ['Square-Version' => '2024-01-18'];

        try {
            // 1. Catalog plan + variation (one batch).
            $catalog = Http::withToken($accessToken)->withHeaders($headers)->timeout(self::HTTP_TIMEOUT_SECONDS)
                ->post($this->baseUrl() . '/catalog/batch-upsert', [
                    'idempotency_key' => 'sqcat_' . $order->order_number . '_' . time(),
                    'batches' => [[
                        'objects' => [[
                            'type' => 'SUBSCRIPTION_PLAN',
                            'id'   => '#plan',
                            'subscription_plan_data' => [
                                'name' => optional($order->package)->pname ?: (config('app.name') . ' plan'),
                                'subscription_plan_variations' => [[
                                    'type' => 'SUBSCRIPTION_PLAN_VARIATION',
                                    'id'   => '#variation',
                                    'subscription_plan_variation_data' => [
                                        'name'   => $cadence . ' plan',
                                        'phases' => [[
                                            'cadence' => $cadence,
                                            'pricing' => [
                                                'type'        => 'STATIC',
                                                'price_money' => ['amount' => (int) round((float) $order->amount * 100), 'currency' => strtoupper($order->currency ?? 'USD')],
                                            ],
                                        ]],
                                    ],
                                ]],
                            ],
                        ]],
                    ]],
                ]);
            $catJson = $catalog->json() ?: [];
            $variationId = null;
            foreach (($catJson['id_mappings'] ?? []) as $map) {
                if (($map['client_object_id'] ?? '') === '#variation') { $variationId = $map['object_id']; break; }
            }
            if (!$variationId) return PaymentResult::failed('square_plan_variation_failed: ' . ($catJson['errors'][0]['detail'] ?? 'unknown'));

            // 2. Customer.
            $custRes = Http::withToken($accessToken)->withHeaders($headers)->timeout(self::HTTP_TIMEOUT_SECONDS)
                ->post($this->baseUrl() . '/customers', [
                    'idempotency_key' => 'sqcust_' . $order->order_number . '_' . time(),
                    'email_address'   => $email,
                    'given_name'      => optional($order->user)->name ?: 'Customer',
                    'reference_id'    => (string) $order->id,
                ]);
            $customerId = $custRes->json('customer.id');
            if (!$customerId) return PaymentResult::failed('square_customer_failed');

            // 3. Subscription (no card_id → Square emails the invoice each cycle).
            $subRes = Http::withToken($accessToken)->withHeaders($headers)->timeout(self::HTTP_TIMEOUT_SECONDS)
                ->post($this->baseUrl() . '/subscriptions', [
                    'idempotency_key'   => 'sqsub_' . $order->order_number . '_' . time(),
                    'location_id'       => $locationId,
                    'plan_variation_id' => $variationId,
                    'customer_id'       => $customerId,
                ]);
            $sub = $subRes->json('subscription');
            if (!$sub || empty($sub['id'])) return PaymentResult::failed('square_subscription_failed: ' . ($subRes->json('errors.0.detail') ?? 'unknown'));

            // The first invoice is emailed by Square; the order stays pending
            // until invoice.payment_made arrives. Show a clear interstitial.
            $appName = e(\App\Models\SystemSetting::get('app_name', config('app.name', 'WaDesk')));
            $eEmail  = e($email);
            $html = "<!doctype html><html><head><meta charset='utf-8'><title>{$appName}</title></head>"
                  . "<body style='font-family:system-ui,sans-serif;max-width:520px;margin:60px auto;text-align:center;color:#16333a'>"
                  . "<h2 style='font-weight:600'>Subscription created</h2>"
                  . "<p>Square has emailed your first invoice to <strong>{$eEmail}</strong>. Pay it to activate your plan — it will then renew automatically each cycle.</p>"
                  . "<p style='margin-top:24px'><a href='" . e($callbackUrl) . "' style='color:#075E54'>Return to your account</a></p>"
                  . "</body></html>";

            return PaymentResult::form($html, $sub['id'], [
                'is_subscription'         => true,
                'gateway_subscription_id' => $sub['id'],
                'gateway_customer_id'     => $customerId,
                'gateway_plan_id'         => $variationId,
                'current_period_end'      => $sub['charged_through_date'] ?? null,
            ]);
        } catch (\Throwable $e) {
            return PaymentResult::failed('square_subscription_exception: ' . $e->getMessage());
        }
    }

    public function parseSubscriptionWebhook(array $payload): ?array
    {
        $type = (string) ($payload['type'] ?? '');
        $obj  = $payload['data']['object'] ?? [];
        if (!is_array($obj)) return null;

        if ($type === 'invoice.payment_made') {
            $invoice = $obj['invoice'] ?? $obj;
            $subId   = $invoice['subscription_id'] ?? null;
            if (!$subId) return null;
            return ['type' => 'renewed', 'subscription_id' => $subId, 'payment_id' => $invoice['id'] ?? null, 'period_end' => null];
        }

        if ($type === 'subscription.updated') {
            $sub    = $obj['subscription'] ?? $obj;
            $status = strtoupper((string) ($sub['status'] ?? ''));
            if (in_array($status, ['CANCELED', 'DEACTIVATED'], true)) {
                return ['type' => 'canceled', 'subscription_id' => $sub['id'] ?? null, 'payment_id' => null, 'period_end' => null];
            }
        }
        return null;
    }

    public function cancelSubscription(string $gatewaySubscriptionId, array $context = []): PaymentResult
    {
        $accessToken = (string) $this->cred('access_token');
        if ($accessToken === '') return PaymentResult::failed('square_access_token_missing');
        try {
            $r = Http::withToken($accessToken)->withHeaders(['Square-Version' => '2024-01-18'])->timeout(self::HTTP_TIMEOUT_SECONDS)
                ->post($this->baseUrl() . "/subscriptions/{$gatewaySubscriptionId}/cancel");
            if (!$r->successful()) return PaymentResult::failed('square_cancel: ' . ($r->json('errors.0.detail') ?? 'HTTP ' . $r->status()));
            return PaymentResult::paid(gatewayOrderId: $gatewaySubscriptionId, payload: $r->json());
        } catch (\Throwable $e) {
            return PaymentResult::failed('square_cancel_exception: ' . $e->getMessage());
        }
    }

    private function baseUrl(): string
    {
        return $this->isLive() ? self::PROD_BASE : self::SANDBOX_BASE;
    }
}
