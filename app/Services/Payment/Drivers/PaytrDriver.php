<?php

namespace App\Services\Payment\Drivers;

use App\Models\Order;
use App\Services\Payment\AbstractGatewayDriver;
use App\Services\Payment\PaymentResult;
use Illuminate\Support\Facades\Http;

/**
 * PayTR payment gateway driver (Turkey).
 *
 * Generates a token via PayTR's iframe-API and embeds the secure iframe
 * for card input. Callback hash is HMAC-SHA256.
 *
 * @see https://dev.paytr.com/en/iframe-api
 */
class PaytrDriver extends AbstractGatewayDriver
{
    private const API_URL = 'https://www.paytr.com/odeme/api/get-token';

    public static function credentialFields(): array
    {
        return [
            'merchant_id'   => ['label' => 'Merchant ID',   'type' => 'text',     'required' => true],
            'merchant_key'  => ['label' => 'Merchant Key',  'type' => 'password', 'required' => true],
            'merchant_salt' => ['label' => 'Merchant Salt', 'type' => 'password', 'required' => true],
        ];
    }

    public function initiate(Order $order, string $callbackUrl): PaymentResult
    {
        $merchantId   = (string) $this->cred('merchant_id');
        $merchantKey  = (string) $this->cred('merchant_key');
        $merchantSalt = (string) $this->cred('merchant_salt');
        if ($merchantId === '' || $merchantKey === '' || $merchantSalt === '') return PaymentResult::failed('paytr_credentials_missing');

        $email = optional($order->user)->email;
        if (!$email) return PaymentResult::failed('paytr_customer_email_missing');

        $merchantOid    = 'PAYTR_' . $order->order_number . '_' . time();
        $amount         = (int) round((float) $order->amount * 100); // kuruş
        $currency       = strtoupper($order->currency ?? 'TL');
        $testMode       = $this->isLive() ? '0' : '1';
        $noInstallment  = '1';
        $maxInstallment = '0';
        $userName       = optional($order->user)->name ?? 'Customer';
        $userAddress    = 'N/A';
        $userPhone      = '0000000000';
        $userIp         = request()->ip() ?? '127.0.0.1';

        $basket = base64_encode(json_encode([
            ["Order #{$order->order_number}", $amount, 1],
        ]));

        $hashStr = $merchantId . $userIp . $merchantOid . $email . $amount . $basket
            . $noInstallment . $maxInstallment . $currency . $testMode;
        $paytrToken = base64_encode(hash_hmac('sha256', $hashStr . $merchantSalt, $merchantKey, true));

        $body = [
            'merchant_id'       => $merchantId,
            'user_ip'           => $userIp,
            'merchant_oid'      => $merchantOid,
            'email'             => $email,
            'payment_amount'    => $amount,
            'paytr_token'       => $paytrToken,
            'user_basket'       => $basket,
            'debug_on'          => $testMode,
            'no_installment'    => $noInstallment,
            'max_installment'   => $maxInstallment,
            'user_name'         => $userName,
            'user_address'      => $userAddress,
            'user_phone'        => $userPhone,
            'merchant_ok_url'   => $callbackUrl . '?status=success',
            'merchant_fail_url' => $callbackUrl . '?status=failed',
            'timeout_limit'     => '30',
            'currency'          => $currency,
            'test_mode'         => $testMode,
        ];

        try {
            $r = Http::asForm()->timeout(self::HTTP_TIMEOUT_SECONDS)->post(self::API_URL, $body);
            $json = $r->json() ?: [];
            if (($json['status'] ?? '') === 'success' && isset($json['token'])) {
                $eIframeSrc = htmlspecialchars('https://www.paytr.com/odeme/guvenli/' . $json['token'], ENT_QUOTES, 'UTF-8');
                $html = <<<HTML
                <iframe src="{$eIframeSrc}" id="paytriframe" frameborder="0" scrolling="no" style="width:100%;min-height:600px;"></iframe>
                HTML;
                return PaymentResult::form($html, $merchantOid, $json);
            }
            return PaymentResult::failed('paytr: ' . ($json['reason'] ?? 'token_failed'));
        } catch (\Throwable $e) {
            return PaymentResult::failed('paytr_exception: ' . $e->getMessage());
        }
    }

    public function handleCallback(array $payload): PaymentResult
    {
        $status      = $payload['status'] ?? '';
        $merchantOid = $payload['merchant_oid'] ?? null;
        $totalAmount = $payload['total_amount'] ?? '';
        $hash        = $payload['hash'] ?? '';
        if ($status === 'failed') return PaymentResult::failed('paytr_failed');

        $merchantKey  = (string) $this->cred('merchant_key');
        $merchantSalt = (string) $this->cred('merchant_salt');
        $expectedHash = base64_encode(hash_hmac('sha256', $merchantOid . $merchantSalt . $status . $totalAmount, $merchantKey, true));
        if (!$hash || !hash_equals($expectedHash, $hash)) return PaymentResult::failed('paytr_hash_invalid');

        if ($status === 'success') {
            return PaymentResult::paid(
                gatewayPaymentId: (string) ($merchantOid ?? ''),
                gatewayOrderId:   (string) ($merchantOid ?? ''),
                payload:          $payload,
            );
        }
        return PaymentResult::failed("paytr_status: {$status}", $payload);
    }
}
