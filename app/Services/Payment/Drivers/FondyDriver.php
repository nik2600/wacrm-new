<?php

namespace App\Services\Payment\Drivers;

use App\Models\Order;
use App\Services\Payment\AbstractGatewayDriver;
use App\Services\Payment\PaymentResult;
use Illuminate\Support\Facades\Http;

/**
 * Fondy (CloudIPSP) payment gateway driver (Eastern Europe).
 *
 * Creates a checkout URL via the Fondy API. Signature uses sha1 over
 * pipe-separated, sorted, non-empty values with the payment key prefix.
 *
 * @see https://docs.fondy.eu/
 */
class FondyDriver extends AbstractGatewayDriver
{
    private const API_BASE = 'https://pay.fondy.eu/api';

    public static function credentialFields(): array
    {
        return [
            'merchant_id' => ['label' => 'Merchant ID', 'type' => 'text',     'required' => true],
            'payment_key' => ['label' => 'Payment Key', 'type' => 'password', 'required' => true],
        ];
    }

    public function initiate(Order $order, string $callbackUrl): PaymentResult
    {
        $merchantId = (string) $this->cred('merchant_id');
        $paymentKey = (string) $this->cred('payment_key');
        if ($merchantId === '' || $paymentKey === '') return PaymentResult::failed('fondy_credentials_missing');

        $orderId = 'FONDY_' . $order->order_number . '_' . time();
        $req = [
            'order_id'            => $orderId,
            'merchant_id'         => $merchantId,
            'order_desc'          => "Order #{$order->order_number}",
            'amount'              => (int) round((float) $order->amount * 100),
            'currency'            => strtoupper($order->currency ?? 'USD'),
            'response_url'        => $callbackUrl,
            'server_callback_url' => route('payment.webhook', ['gateway' => 'fondy']),
        ];

        $sigData = array_filter($req, fn($v) => $v !== '' && $v !== null);
        ksort($sigData);
        $req['signature'] = sha1($paymentKey . '|' . implode('|', $sigData));

        try {
            $r = Http::asJson()->acceptJson()->timeout(self::HTTP_TIMEOUT_SECONDS)
                ->post(self::API_BASE . '/checkout/url/', ['request' => $req]);
            $json = $r->json() ?: [];
            $resp = $json['response'] ?? [];
            if (($resp['response_status'] ?? '') === 'success' && isset($resp['checkout_url'])) {
                return PaymentResult::redirect($resp['checkout_url'], $orderId, $resp);
            }
            return PaymentResult::failed('fondy: ' . ($resp['error_message'] ?? 'create_failed'));
        } catch (\Throwable $e) {
            return PaymentResult::failed('fondy_exception: ' . $e->getMessage());
        }
    }

    public function handleCallback(array $payload): PaymentResult
    {
        $orderStatus = $payload['order_status'] ?? '';
        $orderId     = $payload['order_id']     ?? '';
        $paymentId   = $payload['payment_id']   ?? '';

        $paymentKey = (string) $this->cred('payment_key');
        $sig        = $payload['signature'] ?? '';
        $sigData    = $payload;
        unset($sigData['signature'], $sigData['response_signature_string']);
        $sigData = array_filter($sigData, fn($v) => $v !== '' && $v !== null);
        ksort($sigData);
        $expected = sha1($paymentKey . '|' . implode('|', $sigData));
        if (!$sig || !hash_equals($expected, $sig)) return PaymentResult::failed('fondy_signature_invalid');

        if ($orderStatus === 'approved') {
            return PaymentResult::paid(
                gatewayPaymentId: (string) ($paymentId ?: $orderId),
                gatewayOrderId:   (string) $orderId,
                payload:          $payload,
            );
        }
        return PaymentResult::failed("fondy_status: {$orderStatus}", $payload);
    }
}
