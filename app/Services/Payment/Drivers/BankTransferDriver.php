<?php

namespace App\Services\Payment\Drivers;

use App\Models\Order;
use App\Services\Payment\AbstractGatewayDriver;
use App\Services\Payment\PaymentResult;

/**
 * Bank Transfer — admin displays bank details on the checkout page;
 * customer wires the money offline; admin marks the Order paid manually
 * (or via webhook from accounting if integrated later).
 *
 * No network calls. initiate() just renders the bank details view
 * with the order number as the reference.
 */
class BankTransferDriver extends AbstractGatewayDriver
{
    public static function credentialFields(): array
    {
        return [
            'beneficiary_name' => ['label' => 'Beneficiary name', 'type' => 'text',     'required' => true],
            'bank_name'        => ['label' => 'Bank name',        'type' => 'text',     'required' => true],
            'account_number'   => ['label' => 'Account number',   'type' => 'text',     'required' => true],
            'ifsc_or_swift'    => ['label' => 'IFSC / SWIFT',     'type' => 'text',     'required' => true],
            'branch'           => ['label' => 'Branch',           'type' => 'text',     'required' => false],
            'notes'            => ['label' => 'Extra notes',      'type' => 'textarea', 'required' => false, 'hint' => 'Shown to customer below the bank details'],
        ];
    }

    public function initiate(Order $order, string $callbackUrl): PaymentResult
    {
        $html = view('checkout.bank-transfer', [
            'order'    => $order,
            'gateway'  => $this->gateway,
            'creds'    => $this->gateway->getDecryptedCredentials(),
            'callback' => $callbackUrl,
        ])->render();
        return PaymentResult::form($html, $order->order_number);
    }

    public function handleCallback(array $payload): PaymentResult
    {
        // No automatic verification — the customer clicks "I've sent the
        // payment" and the order stays pending until an admin marks it
        // paid. Surface that state explicitly.
        return PaymentResult::failed('awaiting_admin_verification');
    }
}
