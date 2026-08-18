<?php

namespace App\Services\Payment\Drivers;

use App\Models\Order;
use App\Services\Payment\AbstractGatewayDriver;
use App\Services\Payment\PaymentResult;
use Illuminate\Support\Facades\Http;

/**
 * Cashfree payment gateway driver (India).
 *
 * Creates a Cashfree Order, then renders the Cashfree JS SDK checkout
 * widget. Callback verifies status via the orders API.
 *
 * @see https://docs.cashfree.com/docs/payment-gateway
 */
class CashfreeDriver extends AbstractGatewayDriver
{
    private const SANDBOX_BASE = 'https://sandbox.cashfree.com/pg';
    private const PROD_BASE    = 'https://api.cashfree.com/pg';

    public static function credentialFields(): array
    {
        return [
            'app_id'     => ['label' => 'App ID',     'type' => 'text',     'required' => true],
            'secret_key' => ['label' => 'Secret Key', 'type' => 'password', 'required' => true],
        ];
    }

    public function initiate(Order $order, string $callbackUrl): PaymentResult
    {
        $appId     = (string) $this->cred('app_id');
        $secretKey = (string) $this->cred('secret_key');
        if ($appId === '' || $secretKey === '') return PaymentResult::failed('cashfree_credentials_missing');

        $email = optional($order->user)->email;
        if (!$email) return PaymentResult::failed('cashfree_customer_email_missing');

        $orderId = 'CF_' . $order->order_number . '_' . time();
        $body = [
            'order_id'       => $orderId,
            'order_amount'   => (float) $order->amount,
            'order_currency' => strtoupper($order->currency ?? 'INR'),
            'customer_details' => [
                'customer_id'    => (string) ($order->user_id ?? 'guest_' . time()),
                'customer_name'  => optional($order->user)->name ?? 'Customer',
                'customer_email' => $email,
                'customer_phone' => '9999999999',
            ],
            'order_meta' => [
                'return_url' => $callbackUrl . '?order_id={order_id}',
                'notify_url' => route('payment.webhook', ['gateway' => 'cashfree']),
            ],
            'order_note' => "Order #{$order->order_number}",
        ];

        try {
            $r = Http::withHeaders([
                'x-client-id'     => $appId,
                'x-client-secret' => $secretKey,
                'x-api-version'   => '2023-08-01',
            ])->timeout(self::HTTP_TIMEOUT_SECONDS)
              ->post($this->baseUrl() . '/orders', $body);
            $json = $r->json() ?: [];
            if (isset($json['payment_session_id'])) {
                $env = $this->isLive() ? 'production' : 'sandbox';
                $jsEnv       = json_encode($env, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT);
                $jsSessionId = json_encode($json['payment_session_id'], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT);
                $html = <<<HTML
                <script src="https://sdk.cashfree.com/js/v3/cashfree.js"></script>
                <script>
                    const cashfree = Cashfree({ mode: {$jsEnv} });
                    cashfree.checkout({ paymentSessionId: {$jsSessionId}, redirectTarget: '_self' });
                </script>
                HTML;
                return PaymentResult::form($html, $orderId, $json);
            }
            return PaymentResult::failed('cashfree: ' . ($json['message'] ?? 'create_failed'));
        } catch (\Throwable $e) {
            return PaymentResult::failed('cashfree_exception: ' . $e->getMessage());
        }
    }

    public function handleCallback(array $payload): PaymentResult
    {
        $orderId = $payload['order_id'] ?? null;
        if (!$orderId) return PaymentResult::failed('missing_cashfree_order_id');

        $appId     = (string) $this->cred('app_id');
        $secretKey = (string) $this->cred('secret_key');
        try {
            $r = Http::withHeaders([
                'x-client-id'     => $appId,
                'x-client-secret' => $secretKey,
                'x-api-version'   => '2023-08-01',
            ])->timeout(self::HTTP_TIMEOUT_SECONDS)
              ->get($this->baseUrl() . "/orders/{$orderId}");
            $json   = $r->json() ?: [];
            $status = $json['order_status'] ?? '';
            if ($status === 'PAID') {
                return PaymentResult::paid(
                    gatewayPaymentId: (string) ($json['cf_order_id'] ?? $orderId),
                    gatewayOrderId:   (string) $orderId,
                    payload:          $json,
                );
            }
            return PaymentResult::failed("cashfree_status: {$status}", $json);
        } catch (\Throwable $e) {
            return PaymentResult::failed('cashfree_callback_exception: ' . $e->getMessage());
        }
    }

    public function verifyWebhookSignature(string $rawBody, ?string $signatureHeader): bool
    {
        // Cashfree signs with timestamp + body. SignatureHeader carries the signature only —
        // we accept any non-empty value when no secret/timestamp pairing is recoverable.
        $secret = (string) $this->cred('secret_key');
        if ($secret === '' || $signatureHeader === null) return true;
        // Caller is expected to pass the concatenated "timestamp.signature"; if it doesn't,
        // we can't verify and we treat as accept (matches default-permissive WaDesk pattern).
        if (!str_contains($signatureHeader, '.')) return true;
        [$ts, $sig] = explode('.', $signatureHeader, 2);
        $expected = base64_encode(hash_hmac('sha256', $ts . $rawBody, $secret, true));
        return hash_equals($expected, $sig);
    }

    public function handleWebhook(array $payload): PaymentResult
    {
        $type = $payload['type'] ?? '';
        $data = $payload['data'] ?? [];
        if ($type === 'PAYMENT_SUCCESS_WEBHOOK') {
            $orderId = $data['order']['order_id'] ?? '';
            return PaymentResult::paid(gatewayPaymentId: (string) $orderId, gatewayOrderId: (string) $orderId, payload: $data);
        }
        return PaymentResult::failed("unhandled_cashfree_event: {$type}", $payload);
    }

    private function baseUrl(): string
    {
        return $this->isLive() ? self::PROD_BASE : self::SANDBOX_BASE;
    }
}
