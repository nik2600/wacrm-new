<?php

namespace App\Services\Payment\Drivers;

use App\Models\Order;
use App\Services\Payment\AbstractGatewayDriver;
use App\Services\Payment\PaymentResult;
use Illuminate\Support\Facades\Http;

/**
 * Authorize.Net payment gateway driver.
 *
 * Uses the Accept Hosted API: we fetch a one-time token then auto-POST
 * the customer to Authorize.Net's hosted page. Returns are verified via
 * getTransactionDetailsRequest.
 *
 * @see https://developer.authorize.net/api/reference/
 */
class AuthorizeNetDriver extends AbstractGatewayDriver
{
    private const SANDBOX_BASE = 'https://apitest.authorize.net/xml/v1/request.api';
    private const PROD_BASE    = 'https://api.authorize.net/xml/v1/request.api';
    private const SANDBOX_HOSTED = 'https://test.authorize.net/payment/payment';
    private const PROD_HOSTED    = 'https://accept.authorize.net/payment/payment';

    public static function credentialFields(): array
    {
        return [
            'api_login_id'    => ['label' => 'API Login ID',    'type' => 'text',     'required' => true],
            'transaction_key' => ['label' => 'Transaction Key', 'type' => 'password', 'required' => true],
            'signature_key'   => ['label' => 'Signature Key',   'type' => 'password', 'required' => false, 'hint' => 'For webhook X-ANET-Signature.'],
        ];
    }

    public function initiate(Order $order, string $callbackUrl): PaymentResult
    {
        $apiLoginId     = (string) $this->cred('api_login_id');
        $transactionKey = (string) $this->cred('transaction_key');
        if ($apiLoginId === '' || $transactionKey === '') return PaymentResult::failed('authorize_net_credentials_missing');

        $body = [
            'getHostedPaymentPageRequest' => [
                'merchantAuthentication' => ['name' => $apiLoginId, 'transactionKey' => $transactionKey],
                'transactionRequest' => [
                    'transactionType' => 'authCaptureTransaction',
                    'amount'          => number_format((float) $order->amount, 2, '.', ''),
                    'order'           => [
                        'invoiceNumber' => $order->order_number,
                        'description'   => "Order #{$order->order_number}",
                    ],
                ],
                'hostedPaymentSettings' => [
                    'setting' => [
                        [
                            'settingName'  => 'hostedPaymentReturnOptions',
                            'settingValue' => json_encode([
                                'showReceipt' => false,
                                'url'         => $callbackUrl,
                                'urlText'     => 'Return',
                                'cancelUrl'   => $callbackUrl . '?cancelled=1',
                            ]),
                        ],
                        ['settingName' => 'hostedPaymentButtonOptions', 'settingValue' => json_encode(['text' => 'Pay Now'])],
                    ],
                ],
            ],
        ];

        $json  = $this->authorizeNetRequest($this->apiUrl(), $body);
        $token = $json['token'] ?? null;
        if (!$token) return PaymentResult::failed('authorize_net: ' . ($json['messages']['message'][0]['text'] ?? 'token_failed'));

        $hostedUrl  = htmlspecialchars($this->hostedUrl(), ENT_QUOTES, 'UTF-8');
        $eToken     = htmlspecialchars($token, ENT_QUOTES, 'UTF-8');
        $html = <<<HTML
        <form id="authnet-form" method="POST" action="{$hostedUrl}">
            <input type="hidden" name="token" value="{$eToken}" />
        </form>
        <script>document.getElementById('authnet-form').submit();</script>
        HTML;

        return PaymentResult::form($html, $order->order_number, ['token_acquired' => true]);
    }

    public function handleCallback(array $payload): PaymentResult
    {
        if (isset($payload['cancelled'])) return PaymentResult::failed('cancelled_by_user');

        $transId  = $payload['transId'] ?? $payload['x_trans_id'] ?? null;
        $response = $payload['x_response_code'] ?? $payload['responseCode'] ?? null;

        if ($transId && (string) $response === '1') {
            $verify = $this->getTransactionDetails((string) $transId);
            if ($verify !== null) return $verify;
            return PaymentResult::paid(gatewayPaymentId: (string) $transId, payload: $payload);
        }
        if ($transId) {
            return new PaymentResult(status: 'pending', gatewayPaymentId: (string) $transId, payload: $payload);
        }
        return PaymentResult::failed('authorize_net_failed', $payload);
    }

    public function verifyWebhookSignature(string $rawBody, ?string $signatureHeader): bool
    {
        $key = (string) $this->cred('signature_key');
        if ($key === '' || $signatureHeader === null) return true;
        $sig = str_ireplace('sha512=', '', $signatureHeader);
        $expected = strtoupper(hash_hmac('sha512', $rawBody, hex2bin($key)));
        return hash_equals($expected, strtoupper($sig));
    }

    public function handleWebhook(array $payload): PaymentResult
    {
        $event     = $payload['eventType'] ?? '';
        $webhookId = $payload['payload']['id'] ?? '';
        if ($event === '' || $webhookId === '') return PaymentResult::failed('invalid_authorize_net_webhook');

        if (in_array($event, [
            'net.authorize.payment.authcapture.created',
            'net.authorize.payment.capture.created',
            'net.authorize.payment.priorAuthCapture.created',
        ], true)) {
            $verify = $this->getTransactionDetails((string) $webhookId);
            if ($verify !== null) return $verify;
            return PaymentResult::paid(gatewayPaymentId: (string) $webhookId, payload: $payload);
        }
        if ($event === 'net.authorize.payment.void.created') {
            return PaymentResult::failed("authorize_net_voided: {$webhookId}");
        }
        if ($event === 'net.authorize.payment.refund.created') {
            return PaymentResult::failed("authorize_net_refunded: {$webhookId}");
        }
        return PaymentResult::failed("unhandled_authorize_net_event: {$event}");
    }

    public function verify(Order $order): PaymentResult
    {
        $txnId = $order->gateway_payment_id;
        if (!$txnId) return PaymentResult::failed('no_transaction_id');
        return $this->getTransactionDetails((string) $txnId) ?? PaymentResult::failed('authorize_net_verify_failed');
    }

    // ── Recurring subscriptions (ARB) ────────────────────────────────
    //
    // Verified: Authorize.Net ARB has no hosted subscription redirect. We reuse
    // the existing Accept Hosted page for the FIRST charge, then at the callback
    // build a customer payment profile FROM that transaction and create an ARB
    // subscription against the profile (no raw card / PCI scope). Each cycle
    // fires net.authorize.payment.authcapture.created.

    public function supportsRecurring(): bool
    {
        return true;
    }

    public function createSubscription(Order $order, string $callbackUrl): PaymentResult
    {
        // Same hosted card page as one-time; the ARB subscription is created
        // server-side from the resulting transaction at the callback.
        return $this->initiate($order, $callbackUrl);
    }

    public function handleSubscriptionCallback(array $payload, Order $order): PaymentResult
    {
        if (isset($payload['cancelled'])) return PaymentResult::failed('cancelled_by_user');
        $transId = $payload['transId'] ?? $payload['x_trans_id'] ?? null;
        if (!$transId) return new PaymentResult(status: 'pending', payload: $payload);

        // 1. Build a reusable customer/payment profile from the first charge.
        $profileReq = $this->authorizeNetRequest($this->apiUrl(), [
            'createCustomerProfileFromTransactionRequest' => [
                'merchantAuthentication' => ['name' => (string) $this->cred('api_login_id'), 'transactionKey' => (string) $this->cred('transaction_key')],
                'transId' => (string) $transId,
            ],
        ]);
        if (($profileReq['messages']['resultCode'] ?? '') !== 'Ok') {
            // Profile creation failed — still honour the one-time charge.
            return PaymentResult::paid(gatewayPaymentId: (string) $transId, payload: $payload + ['recurring_setup' => 'failed']);
        }
        $customerProfileId = $profileReq['customerProfileId'] ?? null;
        $paymentProfileId  = $profileReq['customerPaymentProfileIdList'][0]
            ?? ($profileReq['customerPaymentProfileIdList']['numericString'][0] ?? null);
        if (!$customerProfileId || !$paymentProfileId) {
            return PaymentResult::paid(gatewayPaymentId: (string) $transId, payload: $payload + ['recurring_setup' => 'no_profile']);
        }

        // 2. Schedule + start date (next cycle, since the first charge is done).
        $plan = $this->planInterval($order);
        [$length, $unit, $start] = match ($plan['interval']) {
            'year'  => [12, 'months', now()->addYears(max(1, $plan['count']))],
            'week'  => [min(365, 7 * max(1, $plan['count'])), 'days', now()->addWeeks(max(1, $plan['count']))],
            'day'   => [min(365, max(1, $plan['count'])), 'days', now()->addDays(max(1, $plan['count']))],
            default => [min(12, max(1, $plan['count'])), 'months', now()->addMonths(max(1, $plan['count']))],
        };

        $arb = $this->authorizeNetRequest($this->apiUrl(), [
            'ARBCreateSubscriptionRequest' => [
                'merchantAuthentication' => ['name' => (string) $this->cred('api_login_id'), 'transactionKey' => (string) $this->cred('transaction_key')],
                'subscription' => [
                    'name' => 'Order ' . $order->order_number,
                    'paymentSchedule' => [
                        'interval'         => ['length' => $length, 'unit' => $unit],
                        'startDate'        => $start->format('Y-m-d'),
                        'totalOccurrences' => 9999,                  // "until cancelled"
                    ],
                    'amount'  => number_format((float) $order->amount, 2, '.', ''),
                    'profile' => [
                        'customerProfileId'        => (string) $customerProfileId,
                        'customerPaymentProfileId' => (string) $paymentProfileId,
                    ],
                ],
            ],
        ]);
        $subId = $arb['subscriptionId'] ?? null;
        if (($arb['messages']['resultCode'] ?? '') === 'Ok' && $subId) {
            return PaymentResult::paid(
                gatewayPaymentId: (string) $transId,
                gatewayOrderId:   (string) $subId,
                payload:          ['is_subscription' => true, 'current_period_end' => $start->format('Y-m-d'), 'first_transaction' => $transId],
            );
        }
        // ARB failed but the first charge succeeded — treat as a paid one-off.
        return PaymentResult::paid(gatewayPaymentId: (string) $transId, payload: $payload + ['recurring_setup' => 'arb_failed']);
    }

    public function parseSubscriptionWebhook(array $payload): ?array
    {
        $event  = (string) ($payload['eventType'] ?? '');
        $entity = (string) ($payload['payload']['entityName'] ?? '');
        $id     = (string) ($payload['payload']['id'] ?? '');

        // Per-cycle ARB charge: the id is a TRANSACTION id; resolve it to its
        // subscription (returns null for non-subscription one-off charges).
        if (in_array($event, [
            'net.authorize.payment.authcapture.created',
            'net.authorize.payment.priorAuthCapture.created',
        ], true)) {
            $subId = $this->getTxnSubscriptionId($id);
            if (!$subId) return null;
            return ['type' => 'renewed', 'subscription_id' => $subId, 'payment_id' => $id, 'period_end' => null];
        }

        // Subscription-entity lifecycle events carry the subscription id directly.
        if (str_starts_with($event, 'net.authorize.customer.subscription.')) {
            if (str_contains($event, 'cancelled') || str_contains($event, 'expired') || str_contains($event, 'terminated')) {
                return ['type' => 'canceled', 'subscription_id' => $id, 'payment_id' => null, 'period_end' => null];
            }
            if (str_contains($event, 'failed') || str_contains($event, 'suspended')) {
                return ['type' => 'payment_failed', 'subscription_id' => $id, 'payment_id' => null, 'period_end' => null];
            }
        }
        return null;
    }

    public function cancelSubscription(string $gatewaySubscriptionId, array $context = []): PaymentResult
    {
        $r = $this->authorizeNetRequest($this->apiUrl(), [
            'ARBCancelSubscriptionRequest' => [
                'merchantAuthentication' => ['name' => (string) $this->cred('api_login_id'), 'transactionKey' => (string) $this->cred('transaction_key')],
                'subscriptionId' => $gatewaySubscriptionId,
            ],
        ]);
        if (($r['messages']['resultCode'] ?? '') === 'Ok') {
            return PaymentResult::paid(gatewayOrderId: $gatewaySubscriptionId, payload: $r);
        }
        return PaymentResult::failed('authorize_net_cancel: ' . ($r['messages']['message'][0]['text'] ?? 'failed'));
    }

    /** Resolve a transaction id to the ARB subscription that produced it, if any. */
    private function getTxnSubscriptionId(string $transactionId): ?string
    {
        if ($transactionId === '') return null;
        try {
            $json = $this->authorizeNetRequest($this->apiUrl(), [
                'getTransactionDetailsRequest' => [
                    'merchantAuthentication' => ['name' => (string) $this->cred('api_login_id'), 'transactionKey' => (string) $this->cred('transaction_key')],
                    'transId' => $transactionId,
                ],
            ]);
            return $json['transaction']['subscription']['id'] ?? null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function getTransactionDetails(string $transactionId): ?PaymentResult
    {
        $body = [
            'getTransactionDetailsRequest' => [
                'merchantAuthentication' => [
                    'name'           => (string) $this->cred('api_login_id'),
                    'transactionKey' => (string) $this->cred('transaction_key'),
                ],
                'transId' => $transactionId,
            ],
        ];
        try {
            $json = $this->authorizeNetRequest($this->apiUrl(), $body);
            $resultCode = $json['messages']['resultCode'] ?? '';
            if ($resultCode !== 'Ok') return null;
            $txn    = $json['transaction'] ?? [];
            $status = $txn['transactionStatus'] ?? '';
            if (in_array($status, ['settledSuccessfully', 'capturedPendingSettlement', 'authorizedPendingCapture'], true)) {
                return PaymentResult::paid(gatewayPaymentId: $transactionId, payload: $txn);
            }
            if (in_array($status, ['voided', 'declined', 'expired', 'failedReview'], true)) {
                return PaymentResult::failed("authorize_net_status: {$status}", $txn);
            }
            if ($status !== '') {
                return new PaymentResult(status: 'pending', gatewayPaymentId: $transactionId, payload: $txn);
            }
        } catch (\Throwable $e) {
            return null;
        }
        return null;
    }

    private function authorizeNetRequest(string $url, array $body): array
    {
        $r = Http::asJson()->acceptJson()->timeout(self::HTTP_TIMEOUT_SECONDS)->post($url, $body);
        // Authorize.Net responses may include a UTF-8 BOM
        $raw = preg_replace('/^\xEF\xBB\xBF/', '', $r->body());
        return json_decode($raw, true) ?: [];
    }

    private function apiUrl(): string
    {
        return $this->isLive() ? self::PROD_BASE : self::SANDBOX_BASE;
    }

    private function hostedUrl(): string
    {
        return $this->isLive() ? self::PROD_HOSTED : self::SANDBOX_HOSTED;
    }
}
