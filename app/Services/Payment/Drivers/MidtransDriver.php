<?php

namespace App\Services\Payment\Drivers;

use App\Models\Order;
use App\Services\Payment\AbstractGatewayDriver;
use App\Services\Payment\PaymentResult;
use Illuminate\Support\Facades\Http;

/**
 * Midtrans payment gateway driver (Indonesia).
 *
 * Uses the Midtrans Snap API to obtain a transaction token, then either
 * redirects to the snap-hosted page or embeds the snap.js popup.
 *
 * @see https://docs.midtrans.com/reference/api-methods
 */
class MidtransDriver extends AbstractGatewayDriver
{
    private const SNAP_SANDBOX = 'https://app.sandbox.midtrans.com/snap/v1';
    private const SNAP_PROD    = 'https://app.midtrans.com/snap/v1';
    private const CORE_SANDBOX = 'https://api.sandbox.midtrans.com/v2';
    private const CORE_PROD    = 'https://api.midtrans.com/v2';

    public static function credentialFields(): array
    {
        return [
            'server_key' => ['label' => 'Server Key', 'type' => 'password', 'required' => true],
            'client_key' => ['label' => 'Client Key', 'type' => 'text',     'required' => true],
        ];
    }

    public function initiate(Order $order, string $callbackUrl): PaymentResult
    {
        $serverKey = (string) $this->cred('server_key');
        if ($serverKey === '') return PaymentResult::failed('midtrans_server_key_missing');

        $email = optional($order->user)->email;
        if (!$email) return PaymentResult::failed('midtrans_customer_email_missing');

        $orderId = 'MID_' . $order->order_number . '_' . time();
        $body = [
            'transaction_details' => [
                'order_id'     => $orderId,
                'gross_amount' => (int) round((float) $order->amount),
            ],
            'customer_details' => [
                'first_name' => optional($order->user)->name ?? 'Customer',
                'email'      => $email,
            ],
            'callbacks' => ['finish' => $callbackUrl . '?order_id=' . $orderId],
        ];

        try {
            $r = Http::withBasicAuth($serverKey, '')->timeout(self::HTTP_TIMEOUT_SECONDS)
                ->post($this->snapBase() . '/transactions', $body);
            $json = $r->json() ?: [];

            if (isset($json['redirect_url'])) {
                return PaymentResult::redirect($json['redirect_url'], $orderId, $json);
            }
            if (isset($json['token'])) {
                $clientKey  = (string) $this->cred('client_key');
                $snapJsUrl  = $this->isLive()
                    ? 'https://app.midtrans.com/snap/snap.js'
                    : 'https://app.sandbox.midtrans.com/snap/snap.js';
                $jsToken    = json_encode($json['token'], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT);
                $jsCallback = json_encode($callbackUrl, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT);
                $jsOrderId  = json_encode($orderId, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT);
                $eSnapUrl   = htmlspecialchars($snapJsUrl, ENT_QUOTES, 'UTF-8');
                $eClientKey = htmlspecialchars($clientKey, ENT_QUOTES, 'UTF-8');

                $html = <<<HTML
                <script src="{$eSnapUrl}" data-client-key="{$eClientKey}"></script>
                <script>
                    snap.pay({$jsToken}, {
                        onSuccess: function(r) { window.location.href = {$jsCallback} + '?order_id=' + {$jsOrderId} + '&transaction_status=settlement'; },
                        onPending: function(r) { window.location.href = {$jsCallback} + '?order_id=' + {$jsOrderId} + '&transaction_status=pending'; },
                        onError:   function(r) { window.location.href = {$jsCallback} + '?order_id=' + {$jsOrderId} + '&transaction_status=deny'; },
                        onClose:   function()  { window.location.href = {$jsCallback} + '?order_id=' + {$jsOrderId} + '&transaction_status=cancelled'; }
                    });
                </script>
                HTML;
                return PaymentResult::form($html, $orderId, $json);
            }
            return PaymentResult::failed('midtrans: ' . ($json['error_messages'][0] ?? 'create_failed'));
        } catch (\Throwable $e) {
            return PaymentResult::failed('midtrans_exception: ' . $e->getMessage());
        }
    }

    public function handleCallback(array $payload): PaymentResult
    {
        $orderId = $payload['order_id'] ?? null;
        if (!$orderId) return PaymentResult::failed('missing_midtrans_order_id');

        $serverKey = (string) $this->cred('server_key');
        try {
            $r = Http::withBasicAuth($serverKey, '')->timeout(self::HTTP_TIMEOUT_SECONDS)
                ->get($this->coreBase() . '/' . urlencode($orderId) . '/status');
            $json = $r->json() ?: [];
            $txStatus   = $json['transaction_status'] ?? '';
            $fraud      = $json['fraud_status'] ?? 'accept';

            if ($txStatus === 'settlement' || ($txStatus === 'capture' && $fraud === 'accept')) {
                return PaymentResult::paid(
                    gatewayPaymentId: (string) ($json['transaction_id'] ?? $orderId),
                    gatewayOrderId:   (string) $orderId,
                    payload:          $json,
                );
            }
            if ($txStatus === 'pending') {
                return new PaymentResult(status: 'pending', gatewayOrderId: (string) $orderId, payload: $json);
            }
            return PaymentResult::failed("midtrans_status: {$txStatus}", $json);
        } catch (\Throwable $e) {
            return PaymentResult::failed('midtrans_callback_exception: ' . $e->getMessage());
        }
    }

    public function handleWebhook(array $payload): PaymentResult
    {
        $serverKey   = (string) $this->cred('server_key');
        $orderId     = $payload['order_id']      ?? '';
        $statusCode  = $payload['status_code']   ?? '';
        $grossAmount = $payload['gross_amount']  ?? '';
        $sigKey      = $payload['signature_key'] ?? '';

        $expected = hash('sha512', $orderId . $statusCode . $grossAmount . $serverKey);
        if (!$sigKey || !hash_equals($expected, $sigKey)) return PaymentResult::failed('midtrans_signature_invalid');

        $txStatus = $payload['transaction_status'] ?? '';
        $fraud    = $payload['fraud_status'] ?? 'accept';
        if ($txStatus === 'settlement' || ($txStatus === 'capture' && $fraud === 'accept')) {
            return PaymentResult::paid(
                gatewayPaymentId: (string) ($payload['transaction_id'] ?? $orderId),
                gatewayOrderId:   (string) $orderId,
                payload:          $payload,
            );
        }
        if ($txStatus === 'pending') {
            return new PaymentResult(status: 'pending', gatewayOrderId: (string) $orderId, payload: $payload);
        }
        return PaymentResult::failed("midtrans_webhook_status: {$txStatus}", $payload);
    }

    private function snapBase(): string
    {
        return $this->isLive() ? self::SNAP_PROD : self::SNAP_SANDBOX;
    }

    private function coreBase(): string
    {
        return $this->isLive() ? self::CORE_PROD : self::CORE_SANDBOX;
    }
}
