<?php

namespace App\Services\Payment\Drivers;

use App\Models\Order;
use App\Services\Payment\AbstractGatewayDriver;
use App\Services\Payment\PaymentResult;
use Illuminate\Support\Facades\Http;

/**
 * Paytm payment gateway driver.
 *
 * Creates a transaction token via the Paytm Business API, then redirects
 * to the Paytm-hosted payment page. Callback uses AES-128-CBC checksum
 * verification.
 *
 * @see https://business.paytm.com/docs/api/initiate-transaction-api/
 */
class PaytmDriver extends AbstractGatewayDriver
{
    private const STAGING_BASE = 'https://securegw-stage.paytm.in';
    private const PROD_BASE    = 'https://securegw.paytm.in';

    public static function credentialFields(): array
    {
        return [
            'merchant_id'  => ['label' => 'Merchant ID',  'type' => 'text',     'required' => true],
            'merchant_key' => ['label' => 'Merchant Key', 'type' => 'password', 'required' => true],
            'website'      => ['label' => 'Website',      'type' => 'text',     'required' => true],
        ];
    }

    public function initiate(Order $order, string $callbackUrl): PaymentResult
    {
        $merchantId  = (string) $this->cred('merchant_id');
        $merchantKey = (string) $this->cred('merchant_key');
        $website     = (string) $this->cred('website');
        if ($merchantId === '' || $merchantKey === '' || $website === '') {
            return PaymentResult::failed('paytm_credentials_missing');
        }

        $orderId = 'PAYTM_' . $order->order_number . '_' . time();
        $body = [
            'requestType' => 'Payment',
            'mid'         => $merchantId,
            'websiteName' => $website,
            'orderId'     => $orderId,
            'callbackUrl' => $callbackUrl,
            'txnAmount'   => [
                'value'    => number_format((float) $order->amount, 2, '.', ''),
                'currency' => strtoupper($order->currency ?? 'INR'),
            ],
            'userInfo' => [
                'custId' => (string) ($order->user_id ?? 'GUEST'),
            ],
        ];

        $bodyJson = json_encode($body);
        $checksum = $this->generateChecksum($bodyJson, $merchantKey);

        $envelope = ['body' => $body, 'head' => ['signature' => $checksum]];
        $url = $this->baseUrl() . "/theia/api/v1/initiateTransaction?mid={$merchantId}&orderId={$orderId}";

        try {
            $r = Http::timeout(self::HTTP_TIMEOUT_SECONDS)
                ->acceptJson()->asJson()
                ->post($url, $envelope);
            $json = $r->json() ?: [];
            $resultCode = $json['body']['resultInfo']['resultCode'] ?? '';
            $txnToken   = $json['body']['txnToken'] ?? null;

            if ($resultCode === 'S' && $txnToken) {
                $redirectUrl = $this->baseUrl() . "/theia/api/v1/showPaymentPage?mid={$merchantId}&orderId={$orderId}&txnToken={$txnToken}";
                return PaymentResult::redirect($redirectUrl, $orderId, $json);
            }
            return PaymentResult::failed('paytm: ' . ($json['body']['resultInfo']['resultMsg'] ?? 'init_failed'));
        } catch (\Throwable $e) {
            return PaymentResult::failed('paytm_exception: ' . $e->getMessage());
        }
    }

    public function handleCallback(array $payload): PaymentResult
    {
        $orderId     = $payload['ORDERID'] ?? null;
        $bankTxnId   = $payload['BANKTXNID'] ?? null;
        $status      = $payload['STATUS'] ?? '';
        $checksumStr = $payload['CHECKSUMHASH'] ?? '';

        if (!$orderId) return PaymentResult::failed('missing_paytm_order_id');

        $merchantKey = (string) $this->cred('merchant_key');
        $paramsToVerify = $payload;
        unset($paramsToVerify['CHECKSUMHASH']);

        if (!$checksumStr || !$this->verifyChecksum($paramsToVerify, $merchantKey, $checksumStr)) {
            return PaymentResult::failed('paytm_checksum_invalid');
        }

        // Cross-verify via Transaction Status API
        $result = $this->queryTransactionStatus($orderId);
        if ($result !== null) return $result;

        if ($status === 'TXN_SUCCESS') {
            return PaymentResult::paid(
                gatewayPaymentId: (string) ($bankTxnId ?? $orderId),
                gatewayOrderId:   (string) $orderId,
                payload:          $payload,
            );
        }
        return PaymentResult::failed('paytm: ' . ($payload['RESPMSG'] ?? "status: {$status}"), $payload);
    }

    public function verify(Order $order): PaymentResult
    {
        $orderId = $order->gateway_order_id;
        if (!$orderId) return PaymentResult::failed('no_order_id');
        return $this->queryTransactionStatus((string) $orderId) ?? PaymentResult::failed('paytm_verify_failed');
    }

    private function queryTransactionStatus(string $orderId): ?PaymentResult
    {
        $merchantId  = (string) $this->cred('merchant_id');
        $merchantKey = (string) $this->cred('merchant_key');

        $body     = ['mid' => $merchantId, 'orderId' => $orderId];
        $checksum = $this->generateChecksum(json_encode($body), $merchantKey);
        $envelope = ['body' => $body, 'head' => ['signature' => $checksum]];

        try {
            $r = Http::timeout(self::HTTP_TIMEOUT_SECONDS)->acceptJson()->asJson()
                ->post($this->baseUrl() . '/v3/order/status', $envelope);
            $json = $r->json() ?: [];
            $resultCode   = $json['body']['resultInfo']['resultCode'] ?? '';
            $resultStatus = $json['body']['resultInfo']['resultStatus'] ?? '';

            if ($resultCode === '01' || $resultStatus === 'TXN_SUCCESS') {
                return PaymentResult::paid(
                    gatewayPaymentId: (string) ($json['body']['txnId'] ?? $orderId),
                    gatewayOrderId:   $orderId,
                    payload:          $json,
                );
            }
            if ($resultStatus === 'PENDING') {
                return new PaymentResult(status: 'pending', gatewayOrderId: $orderId, payload: $json);
            }
            return null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function baseUrl(): string
    {
        return $this->isLive() ? self::PROD_BASE : self::STAGING_BASE;
    }

    /**
     * Paytm checksum algorithm (port of Paytm PHP SDK):
     *   sha256(body|salt) + salt, then AES-128-CBC encrypt with fixed IV.
     */
    private function generateChecksum(string $body, string $key): string
    {
        $salt       = substr(bin2hex(random_bytes(4)), 0, 4);
        $hash       = hash('sha256', $body . '|' . $salt);
        $hashString = $hash . $salt;
        $iv         = '@@@@&&&&####$$$$';
        $encrypted  = openssl_encrypt($hashString, 'AES-128-CBC', $key, 0, $iv);
        if ($encrypted === false) throw new \RuntimeException('paytm_aes_failed');
        return $encrypted;
    }

    private function verifyChecksum(array $params, string $key, string $checksum): bool
    {
        $iv = '@@@@&&&&####$$$$';
        $decrypted = openssl_decrypt($checksum, 'AES-128-CBC', $key, 0, $iv);
        if ($decrypted === false || strlen($decrypted) < 68) return false;

        $providedHash = substr($decrypted, 0, 64);
        $salt         = substr($decrypted, 64);

        ksort($params);
        // Official Paytm SDK coerces null and the literal string "null" to ''
        // before joining the params — without this, any callback field that
        // arrives null shifts the joined string and verification fails for a
        // legitimate response. Mirror that behaviour exactly.
        $params = array_map(
            fn ($v) => ($v !== null && strtolower((string) $v) !== 'null') ? $v : '',
            $params
        );
        $expectedHash = hash('sha256', implode('|', $params) . '|' . $salt);
        return hash_equals($expectedHash, $providedHash);
    }
}
