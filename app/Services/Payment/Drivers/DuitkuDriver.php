<?php

namespace App\Services\Payment\Drivers;

use App\Models\Order;
use App\Services\Payment\AbstractGatewayDriver;
use App\Services\Payment\PaymentResult;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Duitku payment gateway driver (Indonesia).
 *
 * Implemented against the Duitku POP HTTP API directly (no composer SDK — same
 * as every other driver here). POP = Duitku hosts the payment-method picker,
 * so we send NO paymentMethod (the old v2/inquiry API required one → the
 * "paymentMethod is mandatory" error). Flow:
 *   1. initiate()      → POST /createInvoice (sha256 header signature),
 *                        redirect the user to `paymentUrl` (Duitku's hosted
 *                        payment-method selection page).
 *   2. handleWebhook()  → Duitku POSTs the async callback to callbackUrl;
 *                        verify the md5 signature, resultCode '00' = paid.
 *   3. handleCallback() → the user returns to returnUrl; we re-check the real
 *                        state via /transactionStatus (authoritative — the
 *                        return alone is not trusted).
 *
 * Signatures:
 *   createInvoice = sha256(merchantCode + timestamp-ms + apiKey)  [HEADERS]
 *   callback      = md5(merchantCode + amount + merchantOrderId + apiKey)
 *   status        = md5(merchantCode + merchantOrderId + apiKey)
 *
 * Duitku settles in IDR whole numbers (no decimal sub-units).
 *
 * @see https://docs.duitku.com/api/id/
 */
class DuitkuDriver extends AbstractGatewayDriver
{
    // Duitku POP (Payment Object Popup) — Duitku hosts the payment-method
    // picker, so we send NO paymentMethod. Different host + sha256+timestamp
    // header signature than the old v2/inquiry API (which REQUIRED a
    // paymentMethod → "paymentMethod is mandatory"). transactionStatus lives
    // on the same POP host (md5 body signature, unchanged).
    private const SANDBOX = 'https://api-sandbox.duitku.com/api/merchant';
    private const PROD    = 'https://api-prod.duitku.com/api/merchant';

    public static function credentialFields(): array
    {
        return [
            'merchant_code' => ['label' => 'Merchant Code', 'type' => 'text',     'required' => true],
            'api_key'       => ['label' => 'API Key',       'type' => 'password', 'required' => true],
        ];
    }

    public function initiate(Order $order, string $callbackUrl): PaymentResult
    {
        $merchantCode = trim((string) $this->cred('merchant_code'));
        $apiKey       = (string) $this->cred('api_key');
        if ($merchantCode === '' || $apiKey === '') {
            return PaymentResult::failed('duitku_credentials_missing');
        }

        // Duitku only accepts IDR whole-rupiah integers.
        $amount = (int) round((float) $order->amount);
        if ($amount < 1) return PaymentResult::failed('duitku_invalid_amount');

        // Our merchantOrderId — carries the order number + a nonce so a retry of
        // the same order gets a fresh Duitku transaction (Duitku rejects a
        // duplicate merchantOrderId). We recover the order via order_number.
        $merchantOrderId = 'DTK' . $order->order_number . '-' . substr((string) time(), -6);

        // POP signature = sha256(merchantCode + timestamp-ms + apiKey), sent as
        // request HEADERS (not a body `signature` field).
        $timestamp = (string) round(microtime(true) * 1000);
        $signature = hash('sha256', $merchantCode . $timestamp . $apiKey);

        // Duitku splits the callback (async, server→server) from the returnUrl
        // (browser). We point both at our /payment endpoints; the webhook is the
        // source of truth, the return re-verifies via /transactionStatus.
        $returnUrl = $callbackUrl . (str_contains($callbackUrl, '?') ? '&' : '?')
            . 'merchantOrderId=' . rawurlencode($merchantOrderId);
        $webhookUrl = rtrim((string) url('/payment/webhook/' . $this->gateway->slug), '/');

        $body = [
            'merchantCode'    => $merchantCode,
            'paymentAmount'   => $amount,
            'merchantOrderId' => $merchantOrderId,
            'productDetails'  => 'Order ' . $order->order_number,
            'email'           => (string) (optional($order->user)->email ?: 'customer@example.com'),
            'customerVaName'  => (string) (optional($order->user)->name ?: 'Customer'),
            'phoneNumber'     => (string) (optional($order->user)->phone ?? ''),
            'callbackUrl'     => $webhookUrl,
            'returnUrl'       => $returnUrl,
            'expiryPeriod'    => 60, // minutes
            // No paymentMethod — the POP hosted page shows Duitku's own picker
            // (VA banks, QRIS, OVO, DANA, ShopeePay, retail, cards).
        ];

        try {
            // POP authenticates via headers, not a body `signature` field.
            $r = Http::timeout(self::HTTP_TIMEOUT_SECONDS)->acceptJson()
                ->withHeaders([
                    'x-duitku-signature'    => $signature,
                    'x-duitku-timestamp'    => $timestamp,
                    'x-duitku-merchantcode' => $merchantCode,
                ])
                ->post($this->base() . '/createInvoice', $body);
            $json = $r->json() ?: [];

            if ((string) ($json['statusCode'] ?? '') === '00' && !empty($json['paymentUrl'])) {
                return PaymentResult::redirect((string) $json['paymentUrl'], $merchantOrderId, $json);
            }

            $msg = (string) ($json['statusMessage'] ?? ($json['Message'] ?? 'create_failed'));
            Log::warning('[PAY][duitku] inquiry failed', ['status' => $r->status(), 'body' => $json]);
            return PaymentResult::failed('duitku: ' . $msg, $json);
        } catch (\Throwable $e) {
            return PaymentResult::failed('duitku_exception: ' . $e->getMessage());
        }
    }

    /**
     * Async server→server callback from Duitku (form POST). This is the
     * authoritative paid signal. Verify the md5 signature, then resultCode
     * '00' = success; anything else = pending/failed.
     */
    public function handleWebhook(array $payload): PaymentResult
    {
        $merchantCode    = trim((string) $this->cred('merchant_code'));
        $apiKey          = (string) $this->cred('api_key');
        $amount          = (string) ($payload['amount'] ?? '');
        $merchantOrderId = (string) ($payload['merchantOrderId'] ?? '');
        $sig             = (string) ($payload['signature'] ?? '');
        $resultCode      = (string) ($payload['resultCode'] ?? '');

        if ($merchantOrderId === '' || $sig === '') return PaymentResult::failed('duitku_callback_missing_params');

        $expected = md5($merchantCode . $amount . $merchantOrderId . $apiKey);
        if (!hash_equals($expected, $sig)) return PaymentResult::failed('duitku_signature_invalid');

        if ($resultCode === '00') {
            return PaymentResult::paid(
                gatewayPaymentId: (string) ($payload['reference'] ?? $merchantOrderId),
                gatewayOrderId:   $merchantOrderId,
                payload:          $payload,
            );
        }
        // resultCode '01' = pending/failed on Duitku's side.
        return PaymentResult::failed('duitku_result_code: ' . $resultCode, $payload);
    }

    /**
     * The user returned from the Duitku hosted page. The return is NOT trusted
     * on its own — re-check the real transaction state via /transactionStatus.
     */
    public function handleCallback(array $payload): PaymentResult
    {
        $merchantOrderId = (string) ($payload['merchantOrderId'] ?? '');
        if ($merchantOrderId === '') return PaymentResult::failed('duitku_missing_order_id');
        return $this->statusCheck($merchantOrderId);
    }

    /** Poll the real state (used by the return handler + the late-webhook verify path). */
    public function verify(Order $order): PaymentResult
    {
        $moid = (string) ($order->gateway_order_id ?? '');
        if ($moid === '') return PaymentResult::failed('duitku_no_gateway_order_id');
        return $this->statusCheck($moid);
    }

    private function statusCheck(string $merchantOrderId): PaymentResult
    {
        $merchantCode = trim((string) $this->cred('merchant_code'));
        $apiKey       = (string) $this->cred('api_key');
        $signature    = md5($merchantCode . $merchantOrderId . $apiKey);

        try {
            $r = Http::timeout(self::HTTP_TIMEOUT_SECONDS)->acceptJson()
                ->post($this->base() . '/transactionStatus', [
                    'merchantCode'    => $merchantCode,
                    'merchantOrderId' => $merchantOrderId,
                    'signature'       => $signature,
                ]);
            $json = $r->json() ?: [];
            $code = (string) ($json['statusCode'] ?? '');

            // 00 = success, 01 = pending, 02 = canceled/failed.
            if ($code === '00') {
                return PaymentResult::paid(
                    gatewayPaymentId: (string) ($json['reference'] ?? $merchantOrderId),
                    gatewayOrderId:   $merchantOrderId,
                    payload:          $json,
                );
            }
            if ($code === '01') {
                return new PaymentResult(status: 'pending', gatewayOrderId: $merchantOrderId, payload: $json);
            }
            return PaymentResult::failed('duitku_status: ' . ($json['statusMessage'] ?? $code), $json);
        } catch (\Throwable $e) {
            return PaymentResult::failed('duitku_status_exception: ' . $e->getMessage());
        }
    }

    private function base(): string
    {
        return $this->isLive() ? self::PROD : self::SANDBOX;
    }
}
