<?php

namespace App\Services\Payment\Drivers;

use App\Models\Order;
use App\Services\Payment\AbstractGatewayDriver;
use App\Services\Payment\PaymentResult;
use Illuminate\Support\Facades\Http;

/**
 * PhonePe payment gateway driver (India — UPI / cards).
 *
 * Supports BOTH PhonePe integrations:
 *
 *  • Standard Checkout v2 (OAuth)  — the CURRENT API PhonePe onboards new
 *    merchants on. Credentials are Client ID + Client Secret + Client Version.
 *    Auth is an OAuth `client_credentials` token used as `Authorization:
 *    O-Bearer <token>`. Used automatically when Client ID + Secret are set.
 *
 *  • Legacy Hermes / Salt (X-VERIFY) — the OLD API. Credentials are Merchant
 *    ID + Salt Key + Salt Index; payload is base64 + SHA-256 checksum in the
 *    X-VERIFY header. Kept for merchants still provisioned on it.
 *
 * NOTE: a merchant provisioned on v2 who is entered with the old Merchant ID /
 * Salt fields (or vice-versa) will get PhonePe's "Invalid Merchant: Either not
 * present or blacklisted or disabled" — the credentials must match the API the
 * account was onboarded on. Enter EITHER the v2 (Client) fields OR the legacy
 * (Salt) fields, not both.
 *
 * @see https://developer.phonepe.com/payment-gateway/website-integration/standard-checkout/api-integration/api-reference/authorization
 * @see https://developer.phonepe.com/docs/pg-api-reference/  (legacy)
 */
class PhonepeDriver extends AbstractGatewayDriver
{
    // Legacy (Hermes / Salt) hosts.
    private const SANDBOX_BASE = 'https://api-preprod.phonepe.com/apis/pg-sandbox';
    private const PROD_BASE    = 'https://api.phonepe.com/apis/hermes';

    // Standard Checkout v2 hosts.
    private const V2_SANDBOX_BASE  = 'https://api-preprod.phonepe.com/apis/pg-sandbox';
    private const V2_PROD_BASE     = 'https://api.phonepe.com/apis/pg';
    private const V2_OAUTH_SANDBOX = 'https://api-preprod.phonepe.com/apis/pg-sandbox/v1/oauth/token';
    private const V2_OAUTH_PROD    = 'https://api.phonepe.com/apis/identity-manager/v1/oauth/token';

    public static function credentialFields(): array
    {
        return [
            // ── Standard Checkout v2 (current — new merchants use these) ──
            'client_id'      => ['label' => 'Client ID (Standard Checkout v2)',      'type' => 'text',     'required' => false],
            'client_secret'  => ['label' => 'Client Secret (Standard Checkout v2)',  'type' => 'password', 'required' => false],
            'client_version' => ['label' => 'Client Version (v2, e.g. 1)',            'type' => 'text',     'required' => false],
            // ── Legacy Hermes / Salt (older merchants only) ──
            'merchant_id' => ['label' => 'Merchant ID (legacy)', 'type' => 'text',     'required' => false],
            'salt_key'    => ['label' => 'Salt Key (legacy)',    'type' => 'password', 'required' => false],
            'salt_index'  => ['label' => 'Salt Index (legacy)',  'type' => 'text',     'required' => false],
        ];
    }

    /** V2 when Client ID + Client Secret are configured; else legacy Salt flow. */
    private function useV2(): bool
    {
        return (string) $this->cred('client_id') !== '' && (string) $this->cred('client_secret') !== '';
    }

    public function initiate(Order $order, string $callbackUrl): PaymentResult
    {
        return $this->useV2()
            ? $this->initiateV2($order, $callbackUrl)
            : $this->initiateLegacy($order, $callbackUrl);
    }

    // =================================================================
    // Standard Checkout v2 (OAuth)
    // =================================================================

    /** Fetch an OAuth access token. Memoised per request. */
    private function v2Token(): ?string
    {
        static $tok = null;
        if ($tok !== null) return $tok ?: null;
        try {
            $r = Http::asForm()->timeout(self::HTTP_TIMEOUT_SECONDS)->post(
                $this->isLive() ? self::V2_OAUTH_PROD : self::V2_OAUTH_SANDBOX,
                [
                    'client_id'      => (string) $this->cred('client_id'),
                    'client_version' => (string) ($this->cred('client_version') ?: '1'),
                    'client_secret'  => (string) $this->cred('client_secret'),
                    'grant_type'     => 'client_credentials',
                ]
            );
            $tok = (string) ($r->json('access_token') ?? '');
        } catch (\Throwable $e) {
            $tok = '';
        }
        return $tok ?: null;
    }

    private function v2Base(): string
    {
        return $this->isLive() ? self::V2_PROD_BASE : self::V2_SANDBOX_BASE;
    }

    private function initiateV2(Order $order, string $callbackUrl): PaymentResult
    {
        $token = $this->v2Token();
        if (!$token) return PaymentResult::failed('phonepe: auth failed — check Client ID / Secret / Version and mode (live vs test).');

        // merchantOrderId: <=63 chars, [A-Za-z0-9_-].
        $merchantOrderId = substr('PP_' . preg_replace('/[^A-Za-z0-9_-]/', '', (string) $order->order_number) . '_' . time(), 0, 63);

        $body = [
            'merchantOrderId' => $merchantOrderId,
            'amount'          => (int) round((float) $order->amount * 100), // paisa
            'expireAfter'     => 1200,
            'paymentFlow'     => [
                'type'         => 'PG_CHECKOUT',
                'merchantUrls' => ['redirectUrl' => $callbackUrl],
            ],
        ];

        try {
            $r = Http::withToken($token, 'O-Bearer')
                ->timeout(self::HTTP_TIMEOUT_SECONDS)
                ->post($this->v2Base() . '/checkout/v2/pay', $body);
            $json = $r->json() ?: [];
            $url  = $json['redirectUrl'] ?? null;
            if ($url) return PaymentResult::redirect($url, $merchantOrderId, $json);
            return PaymentResult::failed('phonepe: ' . ($json['message'] ?? $json['code'] ?? 'init_failed'));
        } catch (\Throwable $e) {
            return PaymentResult::failed('phonepe_exception: ' . $e->getMessage());
        }
    }

    private function queryStatusV2(string $merchantOrderId): PaymentResult
    {
        $token = $this->v2Token();
        if (!$token) return PaymentResult::failed('phonepe_v2_auth_failed');
        try {
            $r = Http::withToken($token, 'O-Bearer')
                ->timeout(self::HTTP_TIMEOUT_SECONDS)
                ->get($this->v2Base() . '/checkout/v2/order/' . rawurlencode($merchantOrderId) . '/status');
            $json  = $r->json() ?: [];
            $state = strtoupper((string) ($json['state'] ?? ''));
            $txnId = $json['paymentDetails'][0]['transactionId'] ?? $json['orderId'] ?? $merchantOrderId;
            if ($state === 'COMPLETED') {
                return PaymentResult::paid(
                    gatewayPaymentId: (string) $txnId,
                    gatewayOrderId:   $merchantOrderId,
                    payload:          $json,
                );
            }
            if ($state === 'PENDING') {
                return new PaymentResult(status: 'pending', gatewayOrderId: $merchantOrderId, payload: $json);
            }
            return PaymentResult::failed("phonepe_status: {$state}", $json);
        } catch (\Throwable $e) {
            return PaymentResult::failed('phonepe_status_exception: ' . $e->getMessage());
        }
    }

    // =================================================================
    // Legacy Hermes / Salt (X-VERIFY)
    // =================================================================

    private function initiateLegacy(Order $order, string $callbackUrl): PaymentResult
    {
        $merchantId = (string) $this->cred('merchant_id');
        $saltKey    = (string) $this->cred('salt_key');
        $saltIndex  = (string) $this->cred('salt_index');
        if ($merchantId === '' || $saltKey === '' || $saltIndex === '') {
            return PaymentResult::failed('phonepe_credentials_missing — enter Client ID/Secret (v2) or Merchant ID/Salt (legacy).');
        }

        $merchantTxId = 'PP_' . $order->order_number . '_' . time();
        $body = [
            'merchantId'            => $merchantId,
            'merchantTransactionId' => $merchantTxId,
            'merchantUserId'        => 'USER_' . ($order->user_id ?? 'GUEST'),
            'amount'                => (int) round((float) $order->amount * 100), // paisa
            'redirectUrl'           => $callbackUrl,
            'redirectMode'          => 'REDIRECT',
            'callbackUrl'           => route('payment.webhook', ['gateway' => 'phonepe']),
            'paymentInstrument'     => ['type' => 'PAY_PAGE'],
        ];

        $base64Payload = base64_encode(json_encode($body));
        $apiEndpoint   = '/pg/v1/pay';
        $checksum      = hash('sha256', $base64Payload . $apiEndpoint . $saltKey) . '###' . $saltIndex;

        try {
            $r = Http::withHeaders([
                'X-VERIFY' => $checksum,
            ])->timeout(self::HTTP_TIMEOUT_SECONDS)
              ->post($this->baseUrl() . $apiEndpoint, ['request' => $base64Payload]);
            $json = $r->json() ?: [];
            if (($json['success'] ?? false) === true) {
                $url = $json['data']['instrumentResponse']['redirectInfo']['url'] ?? null;
                if ($url) return PaymentResult::redirect($url, $merchantTxId, $json);
            }
            return PaymentResult::failed('phonepe: ' . ($json['message'] ?? 'init_failed'));
        } catch (\Throwable $e) {
            return PaymentResult::failed('phonepe_exception: ' . $e->getMessage());
        }
    }

    // =================================================================
    // Callback / webhook / verify (shared entry points)
    // =================================================================

    public function handleCallback(array $payload): PaymentResult
    {
        // V2 webhook/redirect carries the order id in the JSON payload rather
        // than a base64 `response`. Recover it and confirm via Order Status.
        if ($this->useV2()) {
            $inner = is_array($payload['payload'] ?? null) ? $payload['payload'] : $payload;
            $moid  = $inner['merchantOrderId'] ?? $payload['merchantOrderId'] ?? null;
            if ($moid) return $this->queryStatusV2((string) $moid);
            return new PaymentResult(status: 'pending', payload: $payload);
        }

        // Legacy: base64 `response` + X-VERIFY.
        $responseData = $payload['response'] ?? null;
        if (!$responseData) return new PaymentResult(status: 'pending', payload: $payload);

        $saltKey   = (string) $this->cred('salt_key');
        $saltIndex = (string) $this->cred('salt_index');
        $xVerify   = $payload['x-verify'] ?? $payload['X-VERIFY'] ?? null;
        if ($xVerify) {
            $expected = hash('sha256', $responseData . $saltKey) . '###' . $saltIndex;
            if (!hash_equals($expected, $xVerify)) return PaymentResult::failed('phonepe_checksum_invalid');
        }

        $decoded      = json_decode(base64_decode($responseData), true) ?: [];
        $merchantTxId = $decoded['data']['merchantTransactionId'] ?? null;
        if (!$merchantTxId) return PaymentResult::failed('missing_phonepe_merchant_tx');

        return $this->queryStatus((string) $merchantTxId);
    }

    public function handleWebhook(array $payload): PaymentResult
    {
        return $this->handleCallback($payload);
    }

    public function verify(Order $order): PaymentResult
    {
        $txnId = $order->gateway_order_id ?? $order->gateway_payment_id;
        if (!$txnId) return PaymentResult::failed('no_transaction_id');
        return $this->queryStatus((string) $txnId);
    }

    /** Status lookup — routes to v2 or legacy based on configured credentials. */
    private function queryStatus(string $merchantTxId): PaymentResult
    {
        if ($this->useV2()) return $this->queryStatusV2($merchantTxId);

        $merchantId = (string) $this->cred('merchant_id');
        $saltKey    = (string) $this->cred('salt_key');
        $saltIndex  = (string) $this->cred('salt_index');
        $endpoint   = "/pg/v1/status/{$merchantId}/{$merchantTxId}";
        $checksum   = hash('sha256', $endpoint . $saltKey) . '###' . $saltIndex;

        try {
            $r = Http::withHeaders([
                'X-VERIFY'      => $checksum,
                'X-MERCHANT-ID' => $merchantId,
            ])->timeout(self::HTTP_TIMEOUT_SECONDS)
              ->get($this->baseUrl() . $endpoint);
            $json = $r->json() ?: [];
            $code = $json['code'] ?? '';
            $data = $json['data'] ?? [];
            if ($code === 'PAYMENT_SUCCESS') {
                return PaymentResult::paid(
                    gatewayPaymentId: (string) ($data['transactionId'] ?? $merchantTxId),
                    gatewayOrderId:   $merchantTxId,
                    payload:          $json,
                );
            }
            if ($code === 'PAYMENT_PENDING') {
                return new PaymentResult(status: 'pending', gatewayOrderId: $merchantTxId, payload: $json);
            }
            return PaymentResult::failed("phonepe_status: {$code}", $json);
        } catch (\Throwable $e) {
            return PaymentResult::failed('phonepe_status_exception: ' . $e->getMessage());
        }
    }

    private function baseUrl(): string
    {
        return $this->isLive() ? self::PROD_BASE : self::SANDBOX_BASE;
    }
}
