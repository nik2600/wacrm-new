<?php

namespace App\Services\Payment\Drivers;

use App\Models\Order;
use App\Services\Payment\AbstractGatewayDriver;
use App\Services\Payment\PaymentResult;

/**
 * Offline / Cash / "Contact sales" — used for enterprise quotes where
 * the customer pays out-of-band (PO, wire, cheque). No online flow,
 * no credentials. Order sits pending until admin marks paid.
 */
class OfflineDriver extends AbstractGatewayDriver
{
    public static function credentialFields(): array
    {
        return [
            'instructions' => ['label' => 'Instructions to show customer', 'type' => 'textarea', 'required' => true, 'hint' => 'e.g. "Reply to your account manager to arrange payment."'],
        ];
    }

    public function initiate(Order $order, string $callbackUrl): PaymentResult
    {
        $html = view('checkout.offline', [
            'order'        => $order,
            'instructions' => (string) $this->cred('instructions', 'Contact us to arrange payment.'),
            'callback'     => $callbackUrl,
        ])->render();
        return PaymentResult::form($html, $order->order_number);
    }

    public function handleCallback(array $payload): PaymentResult
    {
        return PaymentResult::failed('awaiting_admin_verification');
    }
}
