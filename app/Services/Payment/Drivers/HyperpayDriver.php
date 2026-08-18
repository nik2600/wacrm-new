<?php

namespace App\Services\Payment\Drivers;

use App\Models\Order;
use App\Services\Payment\AbstractGatewayDriver;
use App\Services\Payment\PaymentResult;
use Illuminate\Support\Facades\Http;

/**
 * HyperPay (ACI Worldwide) payment gateway driver (Middle East / Africa).
 *
 * Prepares a checkout via the OPPWA API and renders the HyperPay
 * payment widget. Status is fetched via the resourcePath on callback.
 *
 * @see https://wordpresshyperpay.docs.oppwa.com/
 */
class HyperpayDriver extends AbstractGatewayDriver
{
    private const SANDBOX_BASE = 'https://eu-test.oppwa.com';
    private const PROD_BASE    = 'https://eu-prod.oppwa.com';

    public static function credentialFields(): array
    {
        return [
            'access_token' => ['label' => 'Access Token', 'type' => 'password', 'required' => true],
            'entity_id'    => ['label' => 'Entity ID',    'type' => 'text',     'required' => true],
        ];
    }

    public function initiate(Order $order, string $callbackUrl): PaymentResult
    {
        $accessToken = (string) $this->cred('access_token');
        $entityId    = (string) $this->cred('entity_id');
        if ($accessToken === '' || $entityId === '') return PaymentResult::failed('hyperpay_credentials_missing');

        $body = [
            'entityId'              => $entityId,
            'amount'                => number_format((float) $order->amount, 2, '.', ''),
            'currency'              => strtoupper($order->currency ?? 'SAR'),
            'paymentType'           => 'DB',
            'merchantTransactionId' => $order->order_number,
        ];

        try {
            $r = Http::asForm()->withToken($accessToken)->timeout(self::HTTP_TIMEOUT_SECONDS)
                ->post($this->baseUrl() . '/v1/checkouts', $body);
            $json       = $r->json() ?: [];
            $checkoutId = $json['id'] ?? null;
            if (!$checkoutId) return PaymentResult::failed('hyperpay: ' . ($json['result']['description'] ?? 'checkout_failed'));

            $eScriptUrl   = htmlspecialchars($this->baseUrl() . "/v1/paymentWidgets.js?checkoutId={$checkoutId}", ENT_QUOTES, 'UTF-8');
            $eCallbackUrl = htmlspecialchars($callbackUrl, ENT_QUOTES, 'UTF-8');
            $html = <<<HTML
            <script src="{$eScriptUrl}"></script>
            <form action="{$eCallbackUrl}" class="paymentWidgets" data-brands="VISA MASTER AMEX MADA"></form>
            HTML;
            return PaymentResult::form($html, $checkoutId, $json);
        } catch (\Throwable $e) {
            return PaymentResult::failed('hyperpay_exception: ' . $e->getMessage());
        }
    }

    public function handleCallback(array $payload): PaymentResult
    {
        $resourcePath = $payload['resourcePath'] ?? $payload['id'] ?? null;
        if (!$resourcePath) return PaymentResult::failed('missing_hyperpay_resource_path');

        $accessToken = (string) $this->cred('access_token');
        $entityId    = (string) $this->cred('entity_id');
        try {
            $r = Http::withToken($accessToken)->timeout(self::HTTP_TIMEOUT_SECONDS)
                ->get($this->baseUrl() . $resourcePath, ['entityId' => $entityId]);
            $json       = $r->json() ?: [];
            $resultCode = $json['result']['code'] ?? '';

            // Success codes: 000.000.xxx or 000.100.1xx
            if (preg_match('/^(000\.000\.|000\.100\.1)/', $resultCode)) {
                return PaymentResult::paid(
                    gatewayPaymentId: (string) ($json['id'] ?? ''),
                    gatewayOrderId:   (string) ($json['merchantTransactionId'] ?? ''),
                    payload:          $json,
                );
            }
            // Manual / risk review: 000.3xx / 000.6xx
            if (preg_match('/^000\.[36]/', $resultCode)) {
                return new PaymentResult(status: 'pending', gatewayPaymentId: (string) ($json['id'] ?? ''), payload: $json);
            }
            return PaymentResult::failed('hyperpay: ' . ($json['result']['description'] ?? "code: {$resultCode}"), $json);
        } catch (\Throwable $e) {
            return PaymentResult::failed('hyperpay_callback_exception: ' . $e->getMessage());
        }
    }

    private function baseUrl(): string
    {
        return $this->isLive() ? self::PROD_BASE : self::SANDBOX_BASE;
    }
}
