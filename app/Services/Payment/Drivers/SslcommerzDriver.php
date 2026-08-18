<?php

namespace App\Services\Payment\Drivers;

use App\Models\Order;
use App\Services\Payment\AbstractGatewayDriver;
use App\Services\Payment\PaymentResult;
use Illuminate\Support\Facades\Http;

/**
 * SSLCommerz payment gateway driver (Bangladesh).
 *
 * Initialises a session and redirects to the SSLCommerz gateway page.
 * Callback validates against the SSLCommerz validator API.
 *
 * @see https://developer.sslcommerz.com/doc/v4/
 */
class SslcommerzDriver extends AbstractGatewayDriver
{
    private const SANDBOX_BASE = 'https://sandbox.sslcommerz.com';
    private const PROD_BASE    = 'https://securepay.sslcommerz.com';

    public static function credentialFields(): array
    {
        return [
            'store_id'       => ['label' => 'Store ID',       'type' => 'text',     'required' => true],
            'store_password' => ['label' => 'Store Password', 'type' => 'password', 'required' => true],
        ];
    }

    public function initiate(Order $order, string $callbackUrl): PaymentResult
    {
        $storeId   = (string) $this->cred('store_id');
        $storePass = (string) $this->cred('store_password');
        if ($storeId === '' || $storePass === '') return PaymentResult::failed('sslcommerz_credentials_missing');

        $email = optional($order->user)->email;
        if (!$email) return PaymentResult::failed('sslcommerz_customer_email_missing');

        $tranId = 'SSL_' . $order->order_number . '_' . time();
        $body = [
            'store_id'         => $storeId,
            'store_passwd'     => $storePass,
            'total_amount'     => number_format((float) $order->amount, 2, '.', ''),
            'currency'         => strtoupper($order->currency ?? 'BDT'),
            'tran_id'          => $tranId,
            'success_url'      => $callbackUrl,
            'fail_url'         => $callbackUrl . '?status=failed',
            'cancel_url'       => $callbackUrl . '?status=cancelled',
            'ipn_url'          => route('payment.webhook', ['gateway' => 'sslcommerz']),
            'cus_name'         => optional($order->user)->name ?? 'Customer',
            'cus_email'        => $email,
            'cus_phone'        => '0000000000',
            'cus_add1'         => 'N/A',
            'cus_city'         => 'N/A',
            'cus_country'      => 'Bangladesh',
            'shipping_method'  => 'NO',
            'product_name'     => "Order #{$order->order_number}",
            'product_category' => 'Subscription',
            'product_profile'  => 'non-physical-goods',
            'value_a'          => $order->order_number,
            'value_b'          => $order->id,
        ];

        try {
            $r = Http::asForm()->timeout(self::HTTP_TIMEOUT_SECONDS)
                ->post($this->baseUrl() . '/gwprocess/v4/api.php', $body);
            $json = $r->json() ?: [];
            if (($json['status'] ?? '') === 'SUCCESS' && isset($json['GatewayPageURL'])) {
                return PaymentResult::redirect($json['GatewayPageURL'], $tranId, $json);
            }
            return PaymentResult::failed('sslcommerz: ' . ($json['failedreason'] ?? 'init_failed'));
        } catch (\Throwable $e) {
            return PaymentResult::failed('sslcommerz_exception: ' . $e->getMessage());
        }
    }

    public function handleCallback(array $payload): PaymentResult
    {
        $status = $payload['status'] ?? '';
        if ($status === 'cancelled') return PaymentResult::failed('cancelled_by_user');
        if ($status === 'failed')    return PaymentResult::failed('sslcommerz_failed');

        $valId = $payload['val_id'] ?? null;
        if (!$valId) return PaymentResult::failed('missing_sslcommerz_val_id');

        $storeId   = (string) $this->cred('store_id');
        $storePass = (string) $this->cred('store_password');

        try {
            $r = Http::timeout(self::HTTP_TIMEOUT_SECONDS)
                ->get($this->baseUrl() . '/validator/api/validationserverAPI.php', [
                    'val_id'       => $valId,
                    'store_id'     => $storeId,
                    'store_passwd' => $storePass,
                    'format'       => 'json',
                ]);
            $json      = $r->json() ?: [];
            $valStatus = $json['status'] ?? '';
            if ($valStatus === 'VALID' || $valStatus === 'VALIDATED') {
                return PaymentResult::paid(
                    gatewayPaymentId: (string) $valId,
                    gatewayOrderId:   (string) ($payload['tran_id'] ?? ''),
                    payload:          $json,
                );
            }
            return PaymentResult::failed("sslcommerz_validation: {$valStatus}", $json);
        } catch (\Throwable $e) {
            return PaymentResult::failed('sslcommerz_callback_exception: ' . $e->getMessage());
        }
    }

    public function verifyWebhookSignature(string $rawBody, ?string $signatureHeader): bool
    {
        // SSLCommerz IPN embeds verify_sign + verify_key in the body itself.
        parse_str($rawBody, $params);
        $verifySign = $params['verify_sign'] ?? '';
        $verifyKey  = $params['verify_key'] ?? '';
        if (!$verifySign || !$verifyKey) return false;

        $storePass = (string) $this->cred('store_password');
        $keys      = explode(',', $verifyKey);
        $data      = [];
        foreach ($keys as $key) {
            if (isset($params[$key])) $data[$key] = $params[$key];
        }
        ksort($data);
        $hashString = '';
        foreach ($data as $k => $v) $hashString .= $k . '=' . $v . '&';
        $hashString .= 'store_passwd=' . md5($storePass);
        return hash_equals(md5($hashString), $verifySign);
    }

    public function handleWebhook(array $payload): PaymentResult
    {
        $status = $payload['status'] ?? '';
        $valId  = $payload['val_id'] ?? '';
        $tranId = $payload['tran_id'] ?? '';
        if ($status === 'VALID' || $status === 'VALIDATED') {
            return PaymentResult::paid(
                gatewayPaymentId: (string) ($valId ?: $tranId),
                gatewayOrderId:   (string) $tranId,
                payload:          $payload,
            );
        }
        return PaymentResult::failed("sslcommerz_ipn_status: {$status}", $payload);
    }

    private function baseUrl(): string
    {
        return $this->isLive() ? self::PROD_BASE : self::SANDBOX_BASE;
    }
}
