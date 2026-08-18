<?php

namespace App\Services\Payment\Drivers;

use App\Models\Order;
use App\Services\Payment\AbstractGatewayDriver;
use App\Services\Payment\PaymentResult;
use Illuminate\Support\Facades\Http;

/**
 * 2Checkout (Verifone) payment gateway driver.
 *
 * Generates a signed buy-link to the 2Checkout-hosted checkout page,
 * then verifies the order on callback through the REST API.
 *
 * @see https://verifone.cloud/docs/2checkout/API-Integration/
 */
class TwocheckoutDriver extends AbstractGatewayDriver
{
    private const API_BASE     = 'https://api.2checkout.com/rest/6.0';
    private const CHECKOUT_URL = 'https://secure.2checkout.com/checkout/buy';

    public static function credentialFields(): array
    {
        return [
            'merchant_code'   => ['label' => 'Merchant Code',    'type' => 'text',     'required' => true],
            'secret_key'      => ['label' => 'Secret Key',       'type' => 'password', 'required' => true],
            'buy_link_secret' => ['label' => 'Buy Link Secret',  'type' => 'password', 'required' => true],
        ];
    }

    public function initiate(Order $order, string $callbackUrl): PaymentResult
    {
        $merchantCode  = (string) $this->cred('merchant_code');
        $buyLinkSecret = (string) $this->cred('buy_link_secret');
        if ($merchantCode === '' || $buyLinkSecret === '') return PaymentResult::failed('twocheckout_credentials_missing');

        $currency    = strtoupper($order->currency ?? 'USD');
        $amount      = number_format((float) $order->amount, 2, '.', '');
        $orderNumber = $order->order_number;
        $now         = gmdate('Y-m-d H:i:s');

        // Length-prefixed signature concat
        $signParams = [
            strlen($merchantCode) . $merchantCode,
            strlen($now) . $now,
            strlen($amount) . $amount,
            strlen($currency) . $currency,
            strlen($orderNumber) . $orderNumber,
        ];
        $signature = hash_hmac('sha256', implode('', $signParams), $buyLinkSecret);

        $params = [
            'merchant'      => $merchantCode,
            'dynamic'       => 1,
            'order-ext-ref' => $orderNumber,
            'item-ext-ref'  => 'ORDER_' . $orderNumber,
            'prod'          => "Order #{$order->order_number}",
            'price'         => $amount,
            'qty'           => 1,
            'type'          => 'PRODUCT',
            'currency'      => $currency,
            'return-url'    => $callbackUrl,
            'return-type'   => 'redirect',
            'expiration'    => gmdate('Y-m-d H:i:s', time() + 3600),
            'order-date'    => $now,
            'signature'     => $signature,
        ];
        return PaymentResult::redirect(self::CHECKOUT_URL . '?' . http_build_query($params), $orderNumber, $params);
    }

    public function handleCallback(array $payload): PaymentResult
    {
        $refNo   = $payload['refno'] ?? $payload['REFNO'] ?? null;
        $orderno = $payload['order-ext-ref'] ?? $payload['REFNOEXT'] ?? null;
        if (!$refNo) return PaymentResult::failed('missing_twocheckout_refno');

        $merchantCode = (string) $this->cred('merchant_code');
        $secretKey    = (string) $this->cred('secret_key');
        $now  = gmdate('Y-m-d H:i:s');
        $hash = hash_hmac('md5', strlen($merchantCode) . $merchantCode . strlen($now) . $now, $secretKey);

        try {
            $r = Http::withHeaders([
                'X-Avangate-Authentication' => 'code="' . $merchantCode . '" date="' . $now . '" hash="' . $hash . '"',
            ])->timeout(self::HTTP_TIMEOUT_SECONDS)
              ->get(self::API_BASE . "/orders/{$refNo}/");
            $json   = $r->json() ?: [];
            $status = $json['Status'] ?? $json['status'] ?? '';

            if (in_array($status, ['COMPLETE', 'AUTHRECEIVED'], true)) {
                return PaymentResult::paid(
                    gatewayPaymentId: (string) $refNo,
                    gatewayOrderId:   (string) ($orderno ?? $refNo),
                    payload:          $json,
                );
            }
            if ($status === 'PENDING') {
                return new PaymentResult(status: 'pending', gatewayPaymentId: (string) $refNo, payload: $json);
            }
            return PaymentResult::failed("twocheckout_status: {$status}", $json);
        } catch (\Throwable $e) {
            return PaymentResult::failed('twocheckout_exception: ' . $e->getMessage());
        }
    }

    public function verifyWebhookSignature(string $rawBody, ?string $signatureHeader): bool
    {
        // 2Checkout IPNs embed HASH inside the body; signatureHeader is unused.
        $secretKey = (string) $this->cred('secret_key');
        if ($secretKey === '') return true;
        parse_str($rawBody, $params);
        $received = $params['HASH'] ?? '';
        // HASH is the ONLY integrity check for a 2Checkout IPN. When a secret
        // key is configured we MUST require it — accepting a HASH-less body
        // would let anyone forge a "paid" IPN simply by omitting the field.
        if ($received === '') return false;
        unset($params['HASH']);
        $str = '';
        foreach ($params as $v) { $str .= strlen((string) $v) . (string) $v; }
        $expected = hash_hmac('md5', $str, $secretKey);
        return hash_equals($expected, $received);
    }

    public function handleWebhook(array $payload): PaymentResult
    {
        $type  = $payload['ORDERSTATUS'] ?? $payload['IPN_PID'] ?? '';
        $refNo = $payload['REFNO'] ?? '';
        if (in_array($type, ['COMPLETE', 'AUTHRECEIVED'], true)) {
            return PaymentResult::paid(gatewayPaymentId: (string) $refNo, payload: $payload);
        }
        return PaymentResult::failed("unhandled_twocheckout_ipn: {$type}", $payload);
    }

    public function verify(Order $order): PaymentResult
    {
        $refNo = $order->gateway_payment_id;
        if (!$refNo) return PaymentResult::failed('no_refno');
        return $this->handleCallback(['refno' => $refNo]);
    }
}
