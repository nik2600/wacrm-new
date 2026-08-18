<?php

namespace App\Services\Payment\Drivers;

use App\Models\Order;
use App\Services\Payment\AbstractGatewayDriver;
use App\Services\Payment\PaymentResult;
use Illuminate\Support\Facades\Http;

/**
 * Tap Payments gateway driver (Middle East).
 *
 * Creates a Charge and redirects to the Tap-hosted payment page.
 *
 * @see https://developers.tap.company/reference/create-a-charge
 */
class TapDriver extends AbstractGatewayDriver
{
    private const API_BASE = 'https://api.tap.company/v2';

    public static function credentialFields(): array
    {
        return [
            'secret_key'      => ['label' => 'Secret Key',      'type' => 'password', 'required' => true],
            'publishable_key' => ['label' => 'Publishable Key', 'type' => 'text',     'required' => true],
        ];
    }

    public function initiate(Order $order, string $callbackUrl): PaymentResult
    {
        $secretKey = (string) $this->cred('secret_key');
        if ($secretKey === '') return PaymentResult::failed('tap_secret_key_missing');

        $email = optional($order->user)->email;
        if (!$email) return PaymentResult::failed('tap_customer_email_missing');

        $body = [
            'amount'   => (float) $order->amount,
            'currency' => strtoupper($order->currency ?? 'KWD'),
            'customer' => [
                'first_name' => optional($order->user)->name ?? 'Customer',
                'email'      => $email,
            ],
            'source'    => ['id' => 'src_all'],
            'redirect'  => ['url' => $callbackUrl],
            'post'      => ['url' => route('payment.webhook', ['gateway' => 'tap'])],
            'reference' => [
                'transaction' => $order->order_number,
                'order'       => $order->order_number,
            ],
            'description' => "Order #{$order->order_number}",
            'metadata'    => [
                'order_id'     => $order->id,
                'order_number' => $order->order_number,
            ],
        ];

        try {
            $r = Http::withToken($secretKey)->timeout(self::HTTP_TIMEOUT_SECONDS)
                ->post(self::API_BASE . '/charges', $body);
            $json = $r->json() ?: [];
            if (isset($json['transaction']['url'])) {
                return PaymentResult::redirect($json['transaction']['url'], $json['id'] ?? null, $json);
            }
            return PaymentResult::failed('tap: ' . ($json['errors'][0]['description'] ?? 'create_failed'));
        } catch (\Throwable $e) {
            return PaymentResult::failed('tap_exception: ' . $e->getMessage());
        }
    }

    public function handleCallback(array $payload): PaymentResult
    {
        $tapId = $payload['tap_id'] ?? $payload['id'] ?? null;
        if (!$tapId) return PaymentResult::failed('missing_tap_charge_id');

        $secretKey = (string) $this->cred('secret_key');
        try {
            $r = Http::withToken($secretKey)->timeout(self::HTTP_TIMEOUT_SECONDS)
                ->get(self::API_BASE . "/charges/{$tapId}");
            $json   = $r->json() ?: [];
            $status = $json['status'] ?? '';
            if ($status === 'CAPTURED') {
                return PaymentResult::paid(
                    gatewayPaymentId: (string) ($json['id'] ?? $tapId),
                    gatewayOrderId:   (string) ($json['reference']['order'] ?? ''),
                    payload:          $json,
                );
            }
            return PaymentResult::failed("tap_status: {$status}", $json);
        } catch (\Throwable $e) {
            return PaymentResult::failed('tap_callback_exception: ' . $e->getMessage());
        }
    }

    public function handleWebhook(array $payload): PaymentResult
    {
        $chargeId = $payload['id'] ?? '';
        if ($chargeId === '') return PaymentResult::failed('missing_tap_charge_id');
        return $this->handleCallback(['id' => $chargeId]);
    }
}
