<?php

namespace App\Services\Payment\Drivers;

use App\Models\Order;
use App\Services\Payment\AbstractGatewayDriver;
use App\Services\Payment\PaymentResult;
use Illuminate\Support\Facades\Http;

/**
 * Coinbase Commerce payment gateway driver.
 *
 * Creates a fixed-price charge and redirects to Coinbase-hosted crypto
 * checkout. Confirmation arrives via webhook (charge:confirmed).
 *
 * @see https://docs.cloud.coinbase.com/commerce/reference/createcharge
 */
class CoinbaseDriver extends AbstractGatewayDriver
{
    private const API_BASE = 'https://api.commerce.coinbase.com';

    public static function credentialFields(): array
    {
        return [
            'api_key'        => ['label' => 'API Key',        'type' => 'password', 'required' => true],
            'webhook_secret' => ['label' => 'Webhook Secret', 'type' => 'password', 'required' => false],
        ];
    }

    public function initiate(Order $order, string $callbackUrl): PaymentResult
    {
        $apiKey = (string) $this->cred('api_key');
        if ($apiKey === '') return PaymentResult::failed('coinbase_api_key_missing');

        $body = [
            'name'         => "Order #{$order->order_number}",
            'description'  => "Payment for Order #{$order->order_number}",
            'pricing_type' => 'fixed_price',
            'local_price' => [
                'amount'   => number_format((float) $order->amount, 2, '.', ''),
                'currency' => strtoupper($order->currency ?? 'USD'),
            ],
            'metadata' => [
                'order_id'     => $order->id,
                'order_number' => $order->order_number,
            ],
            'redirect_url' => $callbackUrl . '?status=success',
            'cancel_url'   => $callbackUrl . '?status=cancelled',
        ];

        try {
            $r = Http::withHeaders([
                'X-CC-Api-Key' => $apiKey,
                'X-CC-Version' => '2018-03-22',
            ])->timeout(self::HTTP_TIMEOUT_SECONDS)
              ->post(self::API_BASE . '/charges', $body);
            $json = $r->json() ?: [];
            if (isset($json['data']['hosted_url'])) {
                return PaymentResult::redirect(
                    $json['data']['hosted_url'],
                    $json['data']['code'] ?? $json['data']['id'] ?? null,
                    $json,
                );
            }
            return PaymentResult::failed('coinbase: ' . ($json['error']['message'] ?? 'create_failed'));
        } catch (\Throwable $e) {
            return PaymentResult::failed('coinbase_exception: ' . $e->getMessage());
        }
    }

    public function handleCallback(array $payload): PaymentResult
    {
        $status = $payload['status'] ?? '';
        if ($status === 'cancelled') return PaymentResult::failed('cancelled_by_user');
        return new PaymentResult(status: 'pending', payload: $payload);
    }

    public function verifyWebhookSignature(string $rawBody, ?string $signatureHeader): bool
    {
        $secret = (string) $this->cred('webhook_secret');
        // Skip only when no secret is configured; reject when a secret is set
        // but the X-CC-Webhook-Signature header is absent.
        if ($secret === '') return true;
        if ($signatureHeader === null) return false;
        $expected = hash_hmac('sha256', $rawBody, $secret);
        return hash_equals($expected, $signatureHeader);
    }

    public function handleWebhook(array $payload): PaymentResult
    {
        $event = $payload['event'] ?? [];
        $type  = $event['type'] ?? '';
        $data  = $event['data'] ?? [];
        $chargeId = $data['id'] ?? $data['code'] ?? '';

        if ($type === 'charge:confirmed' || $type === 'charge:completed') {
            return PaymentResult::paid(
                gatewayPaymentId: (string) $chargeId,
                gatewayOrderId:   (string) ($data['code'] ?? $chargeId),
                payload:          $data,
            );
        }
        if ($type === 'charge:pending') {
            return new PaymentResult(status: 'pending', gatewayPaymentId: (string) $chargeId, payload: $data);
        }
        if ($type === 'charge:failed') {
            return PaymentResult::failed('coinbase_charge_failed', $data);
        }
        return PaymentResult::failed("unhandled_coinbase_event: {$type}", $payload);
    }
}
