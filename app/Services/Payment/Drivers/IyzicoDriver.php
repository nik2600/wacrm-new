<?php

namespace App\Services\Payment\Drivers;

use App\Models\Order;
use App\Services\Payment\AbstractGatewayDriver;
use App\Services\Payment\PaymentResult;
use Illuminate\Support\Facades\Http;

/**
 * iyzico payment gateway driver (Turkey).
 *
 * Initialises a Checkout Form session and redirects (or embeds the
 * hosted form). Auth uses a PKI-string + HMAC-SHA1 signature.
 *
 * @see https://dev.iyzipay.com/en/checkout-form
 */
class IyzicoDriver extends AbstractGatewayDriver
{
    private const SANDBOX_BASE = 'https://sandbox-api.iyzipay.com';
    private const PROD_BASE    = 'https://api.iyzipay.com';

    public static function credentialFields(): array
    {
        return [
            'api_key'    => ['label' => 'API Key',    'type' => 'text',     'required' => true],
            'secret_key' => ['label' => 'Secret Key', 'type' => 'password', 'required' => true],
        ];
    }

    public function initiate(Order $order, string $callbackUrl): PaymentResult
    {
        $apiKey    = (string) $this->cred('api_key');
        $secretKey = (string) $this->cred('secret_key');
        if ($apiKey === '' || $secretKey === '') return PaymentResult::failed('iyzico_credentials_missing');

        $email = optional($order->user)->email;
        if (!$email) return PaymentResult::failed('iyzico_customer_email_missing');

        $price = number_format((float) $order->amount, 2, '.', '');
        $body = [
            'locale'              => 'en',
            'conversationId'      => 'iyz_' . $order->order_number,
            'price'               => $price,
            'paidPrice'           => $price,
            'currency'            => strtoupper($order->currency ?? 'TRY'),
            'basketId'            => $order->order_number,
            'paymentGroup'        => 'PRODUCT',
            'callbackUrl'         => $callbackUrl,
            'enabledInstallments' => [1],
            'buyer' => [
                'id'                  => (string) ($order->user_id ?? 'GUEST'),
                'name'                => optional($order->user)->name ?? 'Customer',
                'surname'             => 'User',
                'email'               => $email,
                'identityNumber'      => '11111111111',
                'registrationAddress' => 'N/A',
                'city'                => 'N/A',
                'country'             => 'N/A',
                'ip'                  => request()->ip() ?? '127.0.0.1',
            ],
            'shippingAddress' => [
                'contactName' => optional($order->user)->name ?? 'Customer',
                'city'        => 'N/A',
                'country'     => 'N/A',
                'address'     => 'N/A',
            ],
            'billingAddress' => [
                'contactName' => optional($order->user)->name ?? 'Customer',
                'city'        => 'N/A',
                'country'     => 'N/A',
                'address'     => 'N/A',
            ],
            'basketItems' => [[
                'id'        => 'ITEM_' . $order->order_number,
                'name'      => "Order #{$order->order_number}",
                'category1' => 'Subscription',
                'itemType'  => 'VIRTUAL',
                'price'     => $price,
            ]],
        ];

        $headers = $this->iyzicoAuthHeaders($apiKey, $secretKey, $body);

        try {
            $r = Http::withHeaders($headers)->timeout(self::HTTP_TIMEOUT_SECONDS)
                ->post($this->baseUrl() . '/payment/iyzipos/checkoutform/initialize/auth/ecom', $body);
            $json = $r->json() ?: [];

            if (($json['status'] ?? '') === 'success' && isset($json['paymentPageUrl'])) {
                return PaymentResult::redirect($json['paymentPageUrl'], $json['token'] ?? null, $json);
            }
            if (isset($json['checkoutFormContent'])) {
                return PaymentResult::form($json['checkoutFormContent'], $json['token'] ?? null, $json);
            }
            return PaymentResult::failed('iyzico: ' . ($json['errorMessage'] ?? 'init_failed'));
        } catch (\Throwable $e) {
            return PaymentResult::failed('iyzico_exception: ' . $e->getMessage());
        }
    }

    public function handleCallback(array $payload): PaymentResult
    {
        $token = $payload['token'] ?? null;
        if (!$token) return PaymentResult::failed('missing_iyzico_token');

        $apiKey    = (string) $this->cred('api_key');
        $secretKey = (string) $this->cred('secret_key');
        $body      = ['locale' => 'en', 'token' => $token];
        $headers   = $this->iyzicoAuthHeaders($apiKey, $secretKey, $body);

        try {
            $r = Http::withHeaders($headers)->timeout(self::HTTP_TIMEOUT_SECONDS)
                ->post($this->baseUrl() . '/payment/iyzipos/checkoutform/auth/ecom/detail', $body);
            $json = $r->json() ?: [];
            $paymentStatus = $json['paymentStatus'] ?? $json['status'] ?? '';

            if ($paymentStatus === 'SUCCESS' || ($json['status'] ?? '') === 'success') {
                return PaymentResult::paid(
                    gatewayPaymentId: (string) ($json['paymentId'] ?? $token),
                    gatewayOrderId:   (string) $token,
                    payload:          $json,
                );
            }
            return PaymentResult::failed('iyzico: ' . ($json['errorMessage'] ?? "status: {$paymentStatus}"), $json);
        } catch (\Throwable $e) {
            return PaymentResult::failed('iyzico_callback_exception: ' . $e->getMessage());
        }
    }

    // -------- PKI-string + HMAC-SHA1 auth header (port of iyzico PHP SDK) --------

    private function iyzicoAuthHeaders(string $apiKey, string $secretKey, array $payload): array
    {
        $pkiString = $this->buildPkiString($payload);
        $randomKey = (string) (microtime(true) . mt_rand(1, 999999));
        $hash      = base64_encode(hash('sha1', $apiKey . $randomKey . $secretKey . $pkiString, true));
        return [
            'Authorization' => 'IYZWS ' . $apiKey . ':' . $hash,
            'x-iyzi-rnd'    => $randomKey,
        ];
    }

    private function buildPkiString(array $data): string
    {
        $parts = [];
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                if ($this->isIndexedArray($value)) {
                    $sub = [];
                    foreach ($value as $item) {
                        $sub[] = is_array($item) ? '[' . $this->buildPkiPairs($item) . ']' : (string) $item;
                    }
                    $parts[] = $key . '=[' . implode(', ', $sub) . ']';
                } else {
                    $parts[] = $key . '=[' . $this->buildPkiPairs($value) . ']';
                }
            } else {
                $parts[] = $key . '=' . (string) $value;
            }
        }
        return '[' . implode(', ', $parts) . ']';
    }

    private function buildPkiPairs(array $data): string
    {
        $pairs = [];
        foreach ($data as $k => $v) {
            if (is_array($v)) {
                if ($this->isIndexedArray($v)) {
                    $sub = [];
                    foreach ($v as $item) {
                        $sub[] = is_array($item) ? '[' . $this->buildPkiPairs($item) . ']' : (string) $item;
                    }
                    $pairs[] = $k . '=[' . implode(', ', $sub) . ']';
                } else {
                    $pairs[] = $k . '=[' . $this->buildPkiPairs($v) . ']';
                }
            } else {
                $pairs[] = $k . '=' . (string) $v;
            }
        }
        return implode(', ', $pairs);
    }

    private function isIndexedArray(array $arr): bool
    {
        return array_keys($arr) === range(0, count($arr) - 1);
    }

    private function baseUrl(): string
    {
        return $this->isLive() ? self::PROD_BASE : self::SANDBOX_BASE;
    }
}
