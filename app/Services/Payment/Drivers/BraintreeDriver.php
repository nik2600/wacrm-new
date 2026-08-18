<?php

namespace App\Services\Payment\Drivers;

use App\Models\Order;
use App\Services\Payment\AbstractGatewayDriver;
use App\Services\Payment\PaymentResult;
use Illuminate\Support\Facades\Http;

/**
 * Braintree payment gateway driver (XML/HTTP API — no Composer SDK).
 *
 * Generates a client token, renders the Drop-in UI form, then processes
 * the payment nonce returned to our callback URL.
 *
 * @see https://developer.paypal.com/braintree/docs/start/overview
 */
class BraintreeDriver extends AbstractGatewayDriver
{
    private const SANDBOX_BASE = 'https://api.sandbox.braintreegateway.com:443/merchants';
    private const PROD_BASE    = 'https://api.braintreegateway.com:443/merchants';

    public static function credentialFields(): array
    {
        return [
            'merchant_id' => ['label' => 'Merchant ID', 'type' => 'text',     'required' => true],
            'public_key'  => ['label' => 'Public Key',  'type' => 'text',     'required' => true],
            'private_key' => ['label' => 'Private Key', 'type' => 'password', 'required' => true],
            'plan_id'     => ['label' => 'Subscription Plan ID', 'type' => 'text', 'required' => false, 'hint' => 'Create a billing plan in the Braintree Control Panel; its Plan ID enables auto-renew. The plan price is overridden per purchase.'],
        ];
    }

    public function initiate(Order $order, string $callbackUrl): PaymentResult
    {
        $merchantId = (string) $this->cred('merchant_id');
        $publicKey  = (string) $this->cred('public_key');
        $privateKey = (string) $this->cred('private_key');
        if ($merchantId === '' || $publicKey === '' || $privateKey === '') return PaymentResult::failed('braintree_credentials_missing');

        $resp = $this->braintreeRequest('POST', "/{$merchantId}/client_token",
            '<client-token><version>2</version></client-token>',
            $publicKey, $privateKey);

        $clientToken = '';
        if (preg_match('/<value>(.*?)<\/value>/s', $resp['body'], $m)) {
            $clientToken = trim($m[1]);
        }
        if ($clientToken === '') return PaymentResult::failed('braintree_client_token_failed');

        $amount      = number_format((float) $order->amount, 2, '.', '');
        $orderNumber = $order->order_number;

        $jsClientToken = json_encode($clientToken, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT);
        $jsCallbackUrl = json_encode($callbackUrl, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT);
        $jsOrderNumber = json_encode($orderNumber, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT);
        $jsAmount      = json_encode($amount, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT);
        $jsCsrfToken   = json_encode(csrf_token(), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT);

        $html = <<<HTML
        <div id="braintree-dropin-container"></div>
        <button id="braintree-submit-btn" class="btn btn-primary" disabled>Pay Now</button>
        <script src="https://js.braintreegateway.com/web/dropin/1.40.2/js/dropin.min.js"></script>
        <script>
            braintree.dropin.create({
                authorization: {$jsClientToken},
                container: '#braintree-dropin-container',
                card: { cardholderName: { required: true } }
            }, function(err, instance) {
                if (err) { console.error(err); return; }
                var btn = document.getElementById('braintree-submit-btn');
                btn.disabled = false;
                btn.addEventListener('click', function() {
                    btn.disabled = true;
                    instance.requestPaymentMethod(function(requestErr, payload) {
                        if (requestErr) { btn.disabled = false; console.error(requestErr); return; }
                        var form = document.createElement('form');
                        form.method = 'POST';
                        form.action = {$jsCallbackUrl};
                        var fields = {
                            payment_method_nonce: payload.nonce,
                            order_number: {$jsOrderNumber},
                            amount: {$jsAmount},
                            _token: {$jsCsrfToken}
                        };
                        for (var key in fields) {
                            var input = document.createElement('input');
                            input.type = 'hidden'; input.name = key; input.value = fields[key];
                            form.appendChild(input);
                        }
                        document.body.appendChild(form);
                        form.submit();
                    });
                });
            });
        </script>
        HTML;

        return PaymentResult::form($html, null, ['client_token_acquired' => true]);
    }

    public function handleCallback(array $payload): PaymentResult
    {
        $nonce = $payload['payment_method_nonce'] ?? null;
        if (!$nonce) return PaymentResult::failed('missing_braintree_nonce');

        $orderNumber = $payload['order_number'] ?? null;
        $order       = $orderNumber ? Order::query()->where('order_number', $orderNumber)->first() : null;
        if (!$order) return PaymentResult::failed('braintree_order_not_found');

        $amount     = number_format((float) $order->amount, 2, '.', '');
        $merchantId = (string) $this->cred('merchant_id');
        $publicKey  = (string) $this->cred('public_key');
        $privateKey = (string) $this->cred('private_key');

        $escapedAmount = htmlspecialchars($amount, ENT_XML1, 'UTF-8');
        $escapedNonce  = htmlspecialchars($nonce, ENT_XML1, 'UTF-8');
        $txnXml = <<<XML
        <transaction>
            <type>sale</type>
            <amount>{$escapedAmount}</amount>
            <payment-method-nonce>{$escapedNonce}</payment-method-nonce>
            <options>
                <submit-for-settlement>true</submit-for-settlement>
            </options>
        </transaction>
        XML;

        $resp = $this->braintreeRequest('POST', "/{$merchantId}/transactions", $txnXml, $publicKey, $privateKey);
        $body = $resp['body'];

        $transactionId = '';
        $status        = '';
        if (preg_match('/<id>(.*?)<\/id>/', $body, $m)) $transactionId = $m[1];
        if (preg_match('/<status>(.*?)<\/status>/', $body, $m)) $status = $m[1];

        if (in_array($status, ['authorized', 'submitted_for_settlement', 'settled', 'settling'], true)) {
            return PaymentResult::paid(
                gatewayPaymentId: $transactionId,
                gatewayOrderId:   $orderNumber,
                payload:          ['status' => $status, 'raw_xml' => $body],
            );
        }
        $errorMsg = '';
        if (preg_match('/<message>(.*?)<\/message>/', $body, $m)) $errorMsg = $m[1];
        return PaymentResult::failed("braintree_status: {$status}. {$errorMsg}");
    }

    public function verifyWebhookSignature(string $rawBody, ?string $signatureHeader): bool
    {
        // Braintree sends bt_signature + bt_payload inside the form body, not a header.
        $privateKey = (string) $this->cred('private_key');
        $publicKey  = (string) $this->cred('public_key');
        if ($privateKey === '' || $publicKey === '') return true;

        parse_str($rawBody, $params);
        $btSig     = $params['bt_signature'] ?? '';
        $btPayload = $params['bt_payload'] ?? '';
        if (!$btSig || !$btPayload) return false;

        $parts = explode('|', $btSig, 2);
        if (count($parts) !== 2) return false;
        $received = $parts[1];

        $hmacKey  = hash('sha256', hash('sha256', $privateKey, true) . $publicKey);
        $expected = hash_hmac('sha1', $btPayload, $hmacKey);
        return hash_equals($expected, $received);
    }

    public function handleWebhook(array $payload): PaymentResult
    {
        $btPayload = $payload['bt_payload'] ?? '';
        if (!$btPayload) return PaymentResult::failed('missing_braintree_payload');
        $xml = base64_decode($btPayload, true);
        if ($xml === false) return PaymentResult::failed('invalid_braintree_payload');

        $kind = $txnId = $status = '';
        if (preg_match('/<kind>(.*?)<\/kind>/s', $xml, $m)) $kind   = trim($m[1]);
        if (preg_match('/<id>(.*?)<\/id>/', $xml, $m))      $txnId  = trim($m[1]);
        if (preg_match('/<status>(.*?)<\/status>/', $xml, $m)) $status = trim($m[1]);

        if ($kind === 'transaction_settled') {
            return PaymentResult::paid(gatewayPaymentId: $txnId, payload: ['kind' => $kind, 'status' => $status]);
        }
        if ($kind === 'transaction_settlement_declined') {
            return PaymentResult::failed("braintree_settlement_declined: {$txnId}");
        }
        if ($kind === 'disbursement') {
            return PaymentResult::paid(gatewayPaymentId: $txnId ?: 'disbursement', payload: ['kind' => $kind]);
        }
        return PaymentResult::failed("unhandled_braintree_webhook: {$kind}");
    }

    public function verify(Order $order): PaymentResult
    {
        $txnId = $order->gateway_payment_id;
        if (!$txnId) return PaymentResult::failed('no_transaction_id');

        $merchantId = (string) $this->cred('merchant_id');
        $publicKey  = (string) $this->cred('public_key');
        $privateKey = (string) $this->cred('private_key');

        $resp = $this->braintreeRequest('GET', "/{$merchantId}/transactions/{$txnId}", '', $publicKey, $privateKey);
        $status = '';
        if (preg_match('/<status>(.*?)<\/status>/', $resp['body'], $m)) $status = $m[1];

        if (in_array($status, ['authorized', 'submitted_for_settlement', 'settled', 'settling'], true)) {
            return PaymentResult::paid(gatewayPaymentId: (string) $txnId, payload: ['status' => $status]);
        }
        return PaymentResult::failed("braintree_verify_status: {$status}");
    }

    // ── Recurring subscriptions ──────────────────────────────────────
    //
    // Verified: Braintree has no hosted subscription redirect. We reuse the
    // existing Drop-in (collects the card → nonce on OUR page), then at the
    // callback vault the nonce into a payment-method token and create a
    // subscription against the admin's Plan ID, overriding the price. Each
    // cycle Braintree fires subscription_charged_successfully.

    public function supportsRecurring(): bool
    {
        return (string) $this->cred('plan_id') !== '';
    }

    public function createSubscription(Order $order, string $callbackUrl): PaymentResult
    {
        // Same card-collection UI as a one-time charge; the subscription is
        // created server-side at handleSubscriptionCallback().
        return $this->initiate($order, $callbackUrl);
    }

    public function handleSubscriptionCallback(array $payload, Order $order): PaymentResult
    {
        $nonce = $payload['payment_method_nonce'] ?? null;
        if (!$nonce) return PaymentResult::failed('missing_braintree_nonce');
        $planId = (string) $this->cred('plan_id');
        if ($planId === '') return PaymentResult::failed('braintree_plan_id_missing');

        $merchantId = (string) $this->cred('merchant_id');
        $publicKey  = (string) $this->cred('public_key');
        $privateKey = (string) $this->cred('private_key');
        $eNonce     = htmlspecialchars($nonce, ENT_XML1, 'UTF-8');

        // 1. Vault the nonce into a customer + payment-method token.
        $custXml = "<customer><credit-card><payment-method-nonce>{$eNonce}</payment-method-nonce>"
                 . "<options><verify-card>false</verify-card></options></credit-card></customer>";
        $cust = $this->braintreeRequest('POST', "/{$merchantId}/customers", $custXml, $publicKey, $privateKey);
        $token = '';
        if (preg_match('/<token>(.*?)<\/token>/', $cust['body'], $m)) $token = trim($m[1]);
        $customerId = '';
        if (preg_match('/<customer>.*?<id>(.*?)<\/id>/s', $cust['body'], $m)) $customerId = trim($m[1]);
        if ($token === '') return PaymentResult::failed('braintree_vault_failed');

        // 2. Create the subscription, overriding the plan price with our amount.
        $amount   = number_format((float) $order->amount, 2, '.', '');
        $ePlan    = htmlspecialchars($planId, ENT_XML1, 'UTF-8');
        $subXml   = "<subscription><payment-method-token>{$token}</payment-method-token>"
                  . "<plan-id>{$ePlan}</plan-id><price>{$amount}</price></subscription>";
        $sub = $this->braintreeRequest('POST', "/{$merchantId}/subscriptions", $subXml, $publicKey, $privateKey);

        $subId = $status = '';
        if (preg_match('/<subscription>.*?<id>(.*?)<\/id>/s', $sub['body'], $m)) $subId = trim($m[1]);
        if (preg_match('/<status>(.*?)<\/status>/', $sub['body'], $m)) $status = trim($m[1]);

        if (in_array($status, ['Active', 'Pending'], true) && $subId !== '') {
            return PaymentResult::paid(
                gatewayPaymentId: $subId,
                gatewayOrderId:   $subId,
                payload:          ['is_subscription' => true, 'status' => $status, 'customer' => $customerId],
            );
        }
        $err = '';
        if (preg_match('/<message>(.*?)<\/message>/', $sub['body'], $m)) $err = $m[1];
        return PaymentResult::failed("braintree_subscription_status: {$status}. {$err}");
    }

    public function parseSubscriptionWebhook(array $payload): ?array
    {
        $btPayload = $payload['bt_payload'] ?? '';
        if (!$btPayload) return null;
        $xml = base64_decode($btPayload, true);
        if ($xml === false) return null;

        $kind = '';
        if (preg_match('/<kind>(.*?)<\/kind>/s', $xml, $m)) $kind = trim($m[1]);
        $subId = '';
        if (preg_match('/<subscription>.*?<id>(.*?)<\/id>/s', $xml, $m)) $subId = trim($m[1]);
        if ($subId === '') return null;

        switch ($kind) {
            case 'subscription_charged_successfully':
                return ['type' => 'renewed', 'subscription_id' => $subId, 'payment_id' => null, 'period_end' => null];
            case 'subscription_charged_unsuccessfully':
                return ['type' => 'payment_failed', 'subscription_id' => $subId, 'payment_id' => null, 'period_end' => null];
            case 'subscription_canceled':
            case 'subscription_expired':
                return ['type' => 'canceled', 'subscription_id' => $subId, 'payment_id' => null, 'period_end' => null];
            default:
                return null;
        }
    }

    public function cancelSubscription(string $gatewaySubscriptionId, array $context = []): PaymentResult
    {
        $merchantId = (string) $this->cred('merchant_id');
        $publicKey  = (string) $this->cred('public_key');
        $privateKey = (string) $this->cred('private_key');
        try {
            $resp = $this->braintreeRequest('PUT', "/{$merchantId}/subscriptions/{$gatewaySubscriptionId}/cancel", ' ', $publicKey, $privateKey);
            if (preg_match('/<status>(.*?)<\/status>/', $resp['body'], $m) && $m[1] === 'Canceled') {
                return PaymentResult::paid(gatewayOrderId: $gatewaySubscriptionId, payload: ['status' => 'Canceled']);
            }
            return PaymentResult::failed('braintree_cancel_failed');
        } catch (\Throwable $e) {
            return PaymentResult::failed('braintree_cancel_exception: ' . $e->getMessage());
        }
    }

    private function braintreeRequest(string $method, string $path, string $xmlBody, string $publicKey, string $privateKey): array
    {
        $url = $this->baseUrl() . $path;
        $ch  = curl_init();
        $headers = [
            'Content-Type: application/xml',
            'Accept: application/xml',
            'X-ApiVersion: 6',
            'User-Agent: Braintree PHP',
        ];
        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_USERPWD        => $publicKey . ':' . $privateKey,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ]);
        if ($method === 'POST' && $xmlBody !== '') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $xmlBody);
        } elseif ($method === 'PUT' && $xmlBody !== '') {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
            curl_setopt($ch, CURLOPT_POSTFIELDS, $xmlBody);
        }
        $body  = curl_exec($ch);
        $error = curl_error($ch);
        curl_close($ch);
        if ($body === false) throw new \RuntimeException("Braintree cURL error: {$error}");
        return ['body' => $body];
    }

    private function baseUrl(): string
    {
        return $this->isLive() ? self::PROD_BASE : self::SANDBOX_BASE;
    }
}
