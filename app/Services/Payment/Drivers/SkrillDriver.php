<?php

namespace App\Services\Payment\Drivers;

use App\Models\Order;
use App\Services\Payment\AbstractGatewayDriver;
use App\Services\Payment\PaymentResult;

/**
 * Skrill (Moneybookers) payment gateway driver.
 *
 * Auto-POSTs the customer to Skrill Quick Checkout. Confirmation arrives
 * via the status_url webhook (md5 of merchant params + secret).
 *
 * @see https://www.skrill.com/fileadmin/content/pdf/Skrill_Quick_Checkout_Guide.pdf
 */
class SkrillDriver extends AbstractGatewayDriver
{
    private const CHECKOUT_URL = 'https://pay.skrill.com';

    public static function credentialFields(): array
    {
        return [
            'merchant_id' => ['label' => 'Merchant ID (Email)', 'type' => 'text',     'required' => true],
            'secret_word' => ['label' => 'Secret Word',         'type' => 'password', 'required' => true],
        ];
    }

    public function initiate(Order $order, string $callbackUrl): PaymentResult
    {
        $merchantId = (string) $this->cred('merchant_id');
        if ($merchantId === '') return PaymentResult::failed('skrill_merchant_id_missing');

        $txnId = $order->order_number . '_' . time();
        $body = [
            'pay_to_email'        => $merchantId,
            'transaction_id'      => $txnId,
            'return_url'          => $callbackUrl . '?status=success',
            'cancel_url'          => $callbackUrl . '?status=cancelled',
            'status_url'          => route('payment.webhook', ['gateway' => 'skrill']),
            'language'            => 'EN',
            'amount'              => number_format((float) $order->amount, 2, '.', ''),
            'currency'            => strtoupper($order->currency ?? 'USD'),
            'detail1_description' => 'Order Number',
            'detail1_text'        => $order->order_number,
            'merchant_fields'     => 'order_id,order_number',
            'order_id'            => $order->id,
            'order_number'        => $order->order_number,
        ];

        $html = '<form id="skrill-form" method="POST" action="' . self::CHECKOUT_URL . '">';
        foreach ($body as $k => $v) {
            $html .= '<input type="hidden" name="' . $k . '" value="' . htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8') . '" />';
        }
        $html .= '</form><script>document.getElementById("skrill-form").submit();</script>';
        return PaymentResult::form($html, $txnId, ['txn_id' => $txnId]);
    }

    public function handleCallback(array $payload): PaymentResult
    {
        $status = $payload['status'] ?? '';
        if ($status === 'cancelled') return PaymentResult::failed('cancelled_by_user');
        return new PaymentResult(status: 'pending', payload: $payload);
    }

    public function handleWebhook(array $payload): PaymentResult
    {
        $status        = $payload['status'] ?? '';
        $transactionId = $payload['mb_transaction_id'] ?? '';
        $merchantTxId  = $payload['transaction_id'] ?? '';
        $md5sig        = $payload['md5sig'] ?? '';

        $secretWord = (string) $this->cred('secret_word');
        $merchantId = (string) $this->cred('merchant_id');
        $mbAmount   = $payload['mb_amount']   ?? '';
        $mbCurrency = $payload['mb_currency'] ?? '';

        $secretWordUpper = strtoupper(md5($secretWord));
        $sigString       = $merchantId . $merchantTxId . $secretWordUpper . $mbAmount . $mbCurrency . $status;
        $expected        = strtoupper(md5($sigString));
        if (!$md5sig || !hash_equals($expected, strtoupper($md5sig))) return PaymentResult::failed('skrill_signature_invalid');

        if ($status === '2') {   // processed
            return PaymentResult::paid(
                gatewayPaymentId: (string) ($transactionId ?: $merchantTxId),
                gatewayOrderId:   (string) $merchantTxId,
                payload:          $payload,
            );
        }
        if ($status === '0') {   // pending
            return new PaymentResult(status: 'pending', gatewayPaymentId: (string) $transactionId, payload: $payload);
        }
        return PaymentResult::failed("skrill_status: {$status}", $payload);
    }
}
