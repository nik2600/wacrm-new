<?php

namespace App\Services\Payment\Drivers;

use App\Models\Order;
use App\Services\Payment\AbstractGatewayDriver;
use App\Services\Payment\PaymentResult;
use Illuminate\Support\Facades\Http;

/**
 * Mercado Pago payment gateway driver.
 *
 * Creates a Checkout Pro preference and redirects to Mercado Pago.
 *
 * @see https://www.mercadopago.com/developers/en/docs/checkout-pro/integrate-preferences
 */
class MercadopagoDriver extends AbstractGatewayDriver
{
    private const API_BASE = 'https://api.mercadopago.com';

    public static function credentialFields(): array
    {
        return [
            'access_token'   => ['label' => 'Access Token',   'type' => 'password', 'required' => true],
            'public_key'     => ['label' => 'Public Key',     'type' => 'text',     'required' => true],
            'webhook_secret' => ['label' => 'Webhook Secret', 'type' => 'password', 'required' => false],
        ];
    }

    public function initiate(Order $order, string $callbackUrl): PaymentResult
    {
        $accessToken = (string) $this->cred('access_token');
        if ($accessToken === '') return PaymentResult::failed('mercadopago_access_token_missing');

        $body = [
            'items' => [[
                'title'       => "Order #{$order->order_number}",
                'quantity'    => 1,
                'unit_price'  => (float) $order->amount,
                'currency_id' => strtoupper($order->currency ?? 'BRL'),
            ]],
            'back_urls' => [
                'success' => $callbackUrl . '?status=approved',
                'failure' => $callbackUrl . '?status=rejected',
                'pending' => $callbackUrl . '?status=pending',
            ],
            'auto_return'        => 'approved',
            'external_reference' => $order->order_number,
            'notification_url'   => route('payment.webhook', ['gateway' => 'mercadopago']),
        ];

        try {
            $r = Http::withToken($accessToken)->timeout(self::HTTP_TIMEOUT_SECONDS)
                ->post(self::API_BASE . '/checkout/preferences', $body);
            $json = $r->json() ?: [];
            $key  = $this->isLive() ? 'init_point' : 'sandbox_init_point';
            if (isset($json[$key])) {
                return PaymentResult::redirect($json[$key], $json['id'] ?? null, $json);
            }
            return PaymentResult::failed('mercadopago: ' . ($json['message'] ?? 'create_failed'));
        } catch (\Throwable $e) {
            return PaymentResult::failed('mercadopago_exception: ' . $e->getMessage());
        }
    }

    public function handleCallback(array $payload): PaymentResult
    {
        $status    = $payload['status'] ?? $payload['collection_status'] ?? '';
        $paymentId = $payload['payment_id'] ?? $payload['collection_id'] ?? null;

        if ($status === 'approved' && $paymentId) {
            return PaymentResult::paid(
                gatewayPaymentId: (string) $paymentId,
                gatewayOrderId:   (string) ($payload['external_reference'] ?? ''),
                payload:          $payload,
            );
        }
        if ($status === 'pending') {
            return new PaymentResult(status: 'pending', gatewayPaymentId: (string) ($paymentId ?? ''), payload: $payload);
        }
        return PaymentResult::failed("mercadopago_status: {$status}", $payload);
    }

    public function verifyWebhookSignature(string $rawBody, ?string $signatureHeader): bool
    {
        // MercadoPago uses x-signature (ts=...,v1=...) + x-request-id; we only get one.
        // If a webhook_secret is set but no header passed, accept (best-effort).
        $secret = (string) $this->cred('webhook_secret');
        if ($secret === '' || $signatureHeader === null) return true;

        $parts = [];
        foreach (explode(',', $signatureHeader) as $p) {
            $kv = explode('=', trim($p), 2);
            if (count($kv) === 2) $parts[$kv[0]] = $kv[1];
        }
        $ts = $parts['ts'] ?? '';
        $v1 = $parts['v1'] ?? '';
        if ($ts === '' || $v1 === '') return false;

        $bodyData = json_decode($rawBody, true) ?: [];
        $dataId   = $bodyData['data']['id'] ?? '';
        // Without the request-id header we can only build the partial manifest;
        // many setups omit the request-id from the manifest entirely.
        $manifest = "id:{$dataId};ts:{$ts};";
        $expected = hash_hmac('sha256', $manifest, $secret);
        return hash_equals($expected, $v1);
    }

    public function handleWebhook(array $payload): PaymentResult
    {
        $type   = $payload['type'] ?? $payload['topic'] ?? '';
        $dataId = $payload['data']['id'] ?? $payload['id'] ?? null;
        if ($type !== 'payment' || !$dataId) return PaymentResult::failed("unhandled_mercadopago_event: {$type}", $payload);

        $accessToken = (string) $this->cred('access_token');
        try {
            $r = Http::withToken($accessToken)->timeout(self::HTTP_TIMEOUT_SECONDS)
                ->get(self::API_BASE . "/v1/payments/{$dataId}");
            $json   = $r->json() ?: [];
            $status = $json['status'] ?? '';
            if ($status === 'approved') {
                return PaymentResult::paid(
                    gatewayPaymentId: (string) $dataId,
                    gatewayOrderId:   (string) ($json['external_reference'] ?? ''),
                    payload:          $json,
                );
            }
            if (in_array($status, ['pending', 'in_process', 'authorized', 'in_mediation'], true)) {
                return new PaymentResult(status: 'pending', gatewayPaymentId: (string) $dataId, payload: $json);
            }
            return PaymentResult::failed("mercadopago_webhook_status: {$status}", $json);
        } catch (\Throwable $e) {
            return PaymentResult::failed('mercadopago_webhook_exception: ' . $e->getMessage());
        }
    }
}
