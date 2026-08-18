<?php

namespace App\Services\Payment\Drivers;

use App\Models\Order;
use App\Services\Payment\AbstractGatewayDriver;
use App\Services\Payment\PaymentResult;
use Illuminate\Support\Facades\Http;

/**
 * Instamojo payment gateway driver (India).
 *
 * Creates a payment request and redirects the customer to Instamojo's
 * payment page.
 *
 * @see https://docs.instamojo.com/reference/create-a-payment-request
 */
class InstamojoDriver extends AbstractGatewayDriver
{
    private const SANDBOX_BASE = 'https://test.instamojo.com/api/1.1';
    private const PROD_BASE    = 'https://www.instamojo.com/api/1.1';

    public static function credentialFields(): array
    {
        return [
            'api_key'    => ['label' => 'API Key',    'type' => 'text',     'required' => true],
            'auth_token' => ['label' => 'Auth Token', 'type' => 'password', 'required' => true],
            'salt'       => ['label' => 'Salt',       'type' => 'password', 'required' => false, 'hint' => 'For webhook MAC verification.'],
        ];
    }

    public function initiate(Order $order, string $callbackUrl): PaymentResult
    {
        $apiKey    = (string) $this->cred('api_key');
        $authToken = (string) $this->cred('auth_token');
        if ($apiKey === '' || $authToken === '') return PaymentResult::failed('instamojo_credentials_missing');

        $email = optional($order->user)->email;
        if (!$email) return PaymentResult::failed('instamojo_customer_email_missing');

        $body = [
            'purpose'                 => "Order #{$order->order_number}",
            'amount'                  => number_format((float) $order->amount, 2, '.', ''),
            'buyer_name'              => optional($order->user)->name ?? 'Customer',
            'email'                   => $email,
            'redirect_url'            => $callbackUrl,
            'webhook'                 => route('payment.webhook', ['gateway' => 'instamojo']),
            'allow_repeated_payments' => false,
            'send_email'              => false,
        ];

        try {
            $r = Http::asForm()->withHeaders([
                'X-Api-Key'    => $apiKey,
                'X-Auth-Token' => $authToken,
            ])->timeout(self::HTTP_TIMEOUT_SECONDS)
              ->post($this->baseUrl() . '/payment-requests/', $body);
            $json = $r->json() ?: [];
            if (($json['success'] ?? false) === true && isset($json['payment_request']['longurl'])) {
                return PaymentResult::redirect(
                    $json['payment_request']['longurl'],
                    $json['payment_request']['id'] ?? null,
                    $json,
                );
            }
            return PaymentResult::failed('instamojo: ' . (is_array($json['message'] ?? null) ? json_encode($json['message']) : ($json['message'] ?? 'create_failed')));
        } catch (\Throwable $e) {
            return PaymentResult::failed('instamojo_exception: ' . $e->getMessage());
        }
    }

    public function handleCallback(array $payload): PaymentResult
    {
        $paymentId        = $payload['payment_id'] ?? null;
        $paymentRequestId = $payload['payment_request_id'] ?? null;
        $paymentStatus    = $payload['payment_status'] ?? '';
        if (!$paymentId || !$paymentRequestId) return PaymentResult::failed('missing_instamojo_params');

        $apiKey    = (string) $this->cred('api_key');
        $authToken = (string) $this->cred('auth_token');

        try {
            $r = Http::withHeaders([
                'X-Api-Key'    => $apiKey,
                'X-Auth-Token' => $authToken,
            ])->timeout(self::HTTP_TIMEOUT_SECONDS)
              ->get($this->baseUrl() . "/payment-requests/{$paymentRequestId}/{$paymentId}/");
            $json   = $r->json() ?: [];
            $status = $json['payment_request']['payment']['status'] ?? $paymentStatus;
            if ($status === 'Credit') {
                return PaymentResult::paid(
                    gatewayPaymentId: (string) $paymentId,
                    gatewayOrderId:   (string) $paymentRequestId,
                    payload:          $json,
                );
            }
            return PaymentResult::failed("instamojo_status: {$status}", $json);
        } catch (\Throwable $e) {
            return PaymentResult::failed('instamojo_callback_exception: ' . $e->getMessage());
        }
    }

    public function verifyWebhookSignature(string $rawBody, ?string $signatureHeader): bool
    {
        // Instamojo embeds mac=... in the form-encoded body.
        $salt = (string) $this->cred('salt');
        if ($salt === '') return true;
        parse_str($rawBody, $params);
        $mac = $params['mac'] ?? '';
        if (!$mac) return false;
        unset($params['mac']);
        ksort($params);
        $expected = hash_hmac('sha1', implode('|', $params), $salt);
        return hash_equals($expected, $mac);
    }

    public function handleWebhook(array $payload): PaymentResult
    {
        $paymentId = $payload['payment_id'] ?? null;
        $status    = $payload['status'] ?? '';
        if ($status === 'Credit') {
            return PaymentResult::paid(gatewayPaymentId: (string) ($paymentId ?? ''), payload: $payload);
        }
        return PaymentResult::failed("instamojo_webhook_status: {$status}", $payload);
    }

    private function baseUrl(): string
    {
        return $this->isLive() ? self::PROD_BASE : self::SANDBOX_BASE;
    }
}
