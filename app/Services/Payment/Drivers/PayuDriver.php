<?php

namespace App\Services\Payment\Drivers;

use App\Models\Order;
use App\Services\Payment\AbstractGatewayDriver;
use App\Services\Payment\PaymentResult;

/**
 * PayU payment gateway driver (India / LatAm).
 *
 * Builds a SHA-512 signed form and auto-POSTs the user to PayU's hosted
 * payment page. Callback verifies the reverse hash.
 *
 * @see https://devguide.payu.in/docs/payu-checkout-integration/
 */
class PayuDriver extends AbstractGatewayDriver
{
    private const SANDBOX_URL = 'https://sandboxsecure.payu.in/_payment';
    private const PROD_URL    = 'https://secure.payu.in/_payment';

    public static function credentialFields(): array
    {
        return [
            'merchant_key'  => ['label' => 'Merchant Key',  'type' => 'text',     'required' => true],
            'merchant_salt' => ['label' => 'Merchant Salt', 'type' => 'password', 'required' => true],
        ];
    }

    public function initiate(Order $order, string $callbackUrl): PaymentResult
    {
        $merchantKey  = (string) $this->cred('merchant_key');
        $merchantSalt = (string) $this->cred('merchant_salt');
        if ($merchantKey === '' || $merchantSalt === '') return PaymentResult::failed('payu_credentials_missing');

        $email = optional($order->user)->email;
        if (!$email) return PaymentResult::failed('payu_customer_email_missing');

        $txnId       = 'PAYU_' . $order->order_number . '_' . time();
        $amount      = number_format((float) $order->amount, 2, '.', '');
        $productInfo = "Order #{$order->order_number}";
        $firstName   = optional($order->user)->name ?? 'Customer';
        $phone       = '9999999999';
        $udf1        = $order->order_number;
        $udf2        = (string) $order->id;

        // PayU request hash (SHA-512). PayU fixes the field order and it MUST
        // match the posted form fields exactly. Verified against the official
        // doc (https://docs.payu.in/docs/generate-hash-payu-hosted):
        //   key|txnid|amount|productinfo|firstname|email|udf1|udf2|udf3|udf4|udf5||||||SALT
        // = 17 fields / 16 pipes. udf3..udf10 are unused here, but their EMPTY
        // separators must still be present. The previous hand-written string
        // had ONE pipe too few (udf2 followed by 8 pipes, not 9 → only 16
        // fields), so PayU rejected every payment with "Transaction failed due
        // to incorrectly calculated hash parameter". Build from an ordered
        // array so the field count can never drift again.
        $hashString = implode('|', [
            $merchantKey, $txnId, $amount, $productInfo, $firstName, $email,
            $udf1, $udf2, '', '', '', '', '', '', '', '', // udf3..udf10 (unused, empty)
            $merchantSalt,
        ]);
        $hash       = strtolower(hash('sha512', $hashString));

        $paymentUrl = $this->isLive() ? self::PROD_URL : self::SANDBOX_URL;
        $e = fn($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');

        $html = <<<HTML
        <form id="payu-form" method="POST" action="{$e($paymentUrl)}">
            <input type="hidden" name="key" value="{$e($merchantKey)}" />
            <input type="hidden" name="txnid" value="{$e($txnId)}" />
            <input type="hidden" name="amount" value="{$e($amount)}" />
            <input type="hidden" name="productinfo" value="{$e($productInfo)}" />
            <input type="hidden" name="firstname" value="{$e($firstName)}" />
            <input type="hidden" name="email" value="{$e($email)}" />
            <input type="hidden" name="phone" value="{$e($phone)}" />
            <input type="hidden" name="surl" value="{$e($callbackUrl)}" />
            <input type="hidden" name="furl" value="{$e($callbackUrl . '?status=failed')}" />
            <input type="hidden" name="hash" value="{$e($hash)}" />
            <input type="hidden" name="udf1" value="{$e($order->order_number)}" />
            <input type="hidden" name="udf2" value="{$e($order->id)}" />
        </form>
        <script>document.getElementById('payu-form').submit();</script>
        HTML;

        return PaymentResult::form($html, $txnId, ['txnid' => $txnId]);
    }

    public function handleCallback(array $payload): PaymentResult
    {
        $status       = $payload['status'] ?? '';
        $txnId        = $payload['txnid'] ?? null;
        $mihpayid     = $payload['mihpayid'] ?? null;
        $responseHash = $payload['hash'] ?? '';
        if (!$txnId) return PaymentResult::failed('missing_payu_txnid');

        $merchantKey  = (string) $this->cred('merchant_key');
        $merchantSalt = (string) $this->cred('merchant_salt');
        $amount       = $payload['amount']      ?? '';
        $productInfo  = $payload['productinfo'] ?? '';
        $firstName    = $payload['firstname']   ?? '';
        $email        = $payload['email']       ?? '';
        $udf1         = $payload['udf1']        ?? '';
        $udf2         = $payload['udf2']        ?? '';
        $udf3         = $payload['udf3']        ?? '';
        $udf4         = $payload['udf4']        ?? '';
        $udf5         = $payload['udf5']        ?? '';

        // sha512(SALT|status||||||udf5|udf4|udf3|udf2|udf1|email|firstname|productinfo|amount|txnid|key)
        $reverseHashString = "{$merchantSalt}|{$status}||||||{$udf5}|{$udf4}|{$udf3}|{$udf2}|{$udf1}|{$email}|{$firstName}|{$productInfo}|{$amount}|{$txnId}|{$merchantKey}";
        $expectedHash = strtolower(hash('sha512', $reverseHashString));
        if (!$responseHash) return PaymentResult::failed('missing_payu_hash');
        if (!hash_equals($expectedHash, $responseHash)) return PaymentResult::failed('payu_hash_mismatch');

        if ($status === 'success') {
            return PaymentResult::paid(
                gatewayPaymentId: (string) ($mihpayid ?? $txnId),
                gatewayOrderId:   (string) $txnId,
                payload:          $payload,
            );
        }
        return PaymentResult::failed('payu: ' . ($payload['error_Message'] ?? "status: {$status}"), $payload);
    }
}
