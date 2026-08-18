<?php

namespace App\Services\Payment\Drivers;

use App\Models\Order;
use App\Services\Payment\AbstractGatewayDriver;
use App\Services\Payment\PaymentResult;
use Illuminate\Support\Facades\Http;

/**
 * Stripe via Checkout Sessions API (no Composer SDK).
 *
 * Endpoints used (all REST, key-auth):
 *   POST /v1/checkout/sessions
 *   GET  /v1/checkout/sessions/{id}
 *
 * Flow:
 *   1. initiate() → POST /v1/checkout/sessions → redirect to session.url
 *   2. Stripe redirects back with ?session_id=cs_xxx on success
 *      OR no params on cancel.
 *   3. handleCallback() pulls the session, checks payment_status='paid'.
 */
class StripeDriver extends AbstractGatewayDriver
{
    public static function credentialFields(): array
    {
        return [
            'publishable_key' => ['label' => 'Publishable key', 'type' => 'text',     'required' => false, 'hint' => 'pk_live_… / pk_test_…'],
            'secret_key'      => ['label' => 'Secret key',      'type' => 'password', 'required' => true,  'hint' => 'sk_live_… / sk_test_…'],
            'webhook_secret'  => ['label' => 'Webhook secret',  'type' => 'password', 'required' => false, 'hint' => 'whsec_… for signature verification'],
        ];
    }

    public function initiate(Order $order, string $callbackUrl): PaymentResult
    {
        $secret = (string) $this->cred('secret_key');
        if ($secret === '') return PaymentResult::failed('stripe_secret_key_missing');

        // Stripe wants amount in the smallest currency unit (paise / cents).
        $amountMinor = (int) round((float) $order->amount * 100);

        $appName = \App\Models\SystemSetting::get('app_name', config('app.name', 'WaDesk'));
        $body = [
            'mode'                  => 'payment',
            'success_url'           => $callbackUrl . '?session_id={CHECKOUT_SESSION_ID}',
            'cancel_url'            => $callbackUrl . '?cancelled=1',
            'client_reference_id'   => $order->order_number,
            'line_items[0][quantity]'                            => 1,
            'line_items[0][price_data][currency]'                => strtolower($order->currency),
            'line_items[0][price_data][unit_amount]'             => $amountMinor,
            'line_items[0][price_data][product_data][name]'      => optional($order->package)->pname ?? $appName . ' plan',
            'metadata[order_id]'                                 => (string) $order->id,
            'metadata[workspace_id]'                             => (string) $order->workspace_id,
        ];

        try {
            $r = Http::asForm()->withBasicAuth($secret, '')->timeout(self::HTTP_TIMEOUT_SECONDS)
                ->post('https://api.stripe.com/v1/checkout/sessions', $body);
            if (!$r->successful()) return PaymentResult::failed('stripe: ' . ($r->json('error.message') ?: 'HTTP ' . $r->status()));
            $session = $r->json();
            return PaymentResult::redirect($session['url'], $session['id'] ?? null, $session);
        } catch (\Throwable $e) {
            return PaymentResult::failed('stripe_exception: ' . $e->getMessage());
        }
    }

    public function handleCallback(array $payload): PaymentResult
    {
        if (!empty($payload['cancelled'])) return PaymentResult::failed('cancelled_by_user');
        $sessionId = (string) ($payload['session_id'] ?? '');
        if ($sessionId === '') return PaymentResult::failed('missing_session_id');

        $secret = (string) $this->cred('secret_key');
        try {
            $r = Http::withBasicAuth($secret, '')->timeout(self::HTTP_TIMEOUT_SECONDS)
                ->get('https://api.stripe.com/v1/checkout/sessions/' . $sessionId);
            if (!$r->successful()) return PaymentResult::failed('stripe_fetch_fail');
            $session = $r->json();
            if (($session['payment_status'] ?? '') === 'paid') {
                return PaymentResult::paid(
                    gatewayPaymentId: $session['payment_intent'] ?? null,
                    gatewayOrderId:   $sessionId,
                    payload:          $session,
                );
            }
            return PaymentResult::failed('payment_status: ' . ($session['payment_status'] ?? '?'), $session);
        } catch (\Throwable $e) {
            return PaymentResult::failed('stripe_callback_exception: ' . $e->getMessage());
        }
    }

    public function verifyWebhookSignature(string $rawBody, ?string $signatureHeader): bool
    {
        $secret = (string) $this->cred('webhook_secret');
        if ($secret === '' || $signatureHeader === null) return true; // not configured — accept

        // Stripe-Signature: t=<timestamp>,v1=<sig>[,v0=…]
        $parts = [];
        foreach (explode(',', $signatureHeader) as $p) {
            [$k, $v] = array_pad(explode('=', $p, 2), 2, '');
            $parts[$k] = $v;
        }
        if (empty($parts['t']) || empty($parts['v1'])) return false;
        $signed = $parts['t'] . '.' . $rawBody;
        $expected = hash_hmac('sha256', $signed, $secret);
        return hash_equals($expected, $parts['v1']);
    }

    // ── Recurring subscriptions ──────────────────────────────────────
    //
    // Verified against Stripe Checkout Sessions (mode=subscription). Inline
    // price_data[recurring] lets us auto-renew WITHOUT pre-creating a Price —
    // same SDK-free, key-auth shape as the one-time initiate() above.
    //   1. createSubscription() → Checkout Session (subscription) → redirect
    //   2. first invoice paid → handleCallback() returns paid (carries sub_…)
    //   3. every cycle Stripe charges + fires invoice.paid → parseSubscriptionWebhook

    public function supportsRecurring(): bool
    {
        return true;
    }

    public function createSubscription(Order $order, string $callbackUrl): PaymentResult
    {
        $secret = (string) $this->cred('secret_key');
        if ($secret === '') return PaymentResult::failed('stripe_secret_key_missing');

        $amountMinor = (int) round((float) $order->amount * 100);
        $plan        = $this->planInterval($order);           // ['interval'=>'month','count'=>1,...]
        $appName     = \App\Models\SystemSetting::get('app_name', config('app.name', 'WaDesk'));

        $body = [
            'mode'                => 'subscription',
            'success_url'         => $callbackUrl . '?session_id={CHECKOUT_SESSION_ID}',
            'cancel_url'          => $callbackUrl . '?cancelled=1',
            'client_reference_id' => $order->order_number,
            'line_items[0][quantity]'                                  => 1,
            'line_items[0][price_data][currency]'                      => strtolower($order->currency),
            'line_items[0][price_data][unit_amount]'                   => $amountMinor,
            'line_items[0][price_data][product_data][name]'            => optional($order->package)->pname ?? $appName . ' plan',
            'line_items[0][price_data][recurring][interval]'           => $plan['interval'],
            'line_items[0][price_data][recurring][interval_count]'     => $plan['count'],
            'metadata[order_id]'                                       => (string) $order->id,
            'metadata[workspace_id]'                                   => (string) $order->workspace_id,
            // Stamp the subscription itself so renewal webhooks carry our ids.
            'subscription_data[metadata][order_id]'                    => (string) $order->id,
            'subscription_data[metadata][workspace_id]'                => (string) $order->workspace_id,
        ];

        try {
            $r = Http::asForm()->withBasicAuth($secret, '')->timeout(self::HTTP_TIMEOUT_SECONDS)
                ->post('https://api.stripe.com/v1/checkout/sessions', $body);
            if (!$r->successful()) return PaymentResult::failed('stripe: ' . ($r->json('error.message') ?: 'HTTP ' . $r->status()));
            $session = $r->json();
            return PaymentResult::redirect($session['url'], $session['id'] ?? null, $session);
        } catch (\Throwable $e) {
            return PaymentResult::failed('stripe_subscription_exception: ' . $e->getMessage());
        }
    }

    public function parseSubscriptionWebhook(array $payload): ?array
    {
        $type = (string) ($payload['type'] ?? '');
        $obj  = $payload['data']['object'] ?? [];
        if (!is_array($obj)) return null;

        switch ($type) {
            case 'invoice.paid':
            case 'invoice.payment_succeeded':
                $subId = $obj['subscription'] ?? null;
                if (!$subId) return null;
                $reason    = (string) ($obj['billing_reason'] ?? '');
                $periodEnd = $obj['lines']['data'][0]['period']['end'] ?? ($obj['period_end'] ?? null);
                return [
                    // The very first invoice is the activation; later cycles are renewals.
                    'type'            => $reason === 'subscription_create' ? 'created' : 'renewed',
                    'subscription_id' => $subId,
                    'payment_id'      => $obj['payment_intent'] ?? ($obj['id'] ?? null),
                    'period_end'      => $periodEnd,
                    'order_id'        => $obj['subscription_details']['metadata']['order_id'] ?? ($obj['lines']['data'][0]['metadata']['order_id'] ?? null),
                ];

            case 'invoice.payment_failed':
                $subId = $obj['subscription'] ?? null;
                if (!$subId) return null;
                return ['type' => 'payment_failed', 'subscription_id' => $subId, 'payment_id' => $obj['id'] ?? null, 'period_end' => null];

            case 'customer.subscription.deleted':
                return ['type' => 'canceled', 'subscription_id' => $obj['id'] ?? null, 'payment_id' => null, 'period_end' => null];

            case 'checkout.session.completed':
                if (($obj['mode'] ?? '') !== 'subscription') return null;
                return ['type' => 'created', 'subscription_id' => $obj['subscription'] ?? null, 'payment_id' => $obj['payment_intent'] ?? null, 'period_end' => null, 'order_id' => $obj['metadata']['order_id'] ?? null];

            default:
                return null;
        }
    }

    public function cancelSubscription(string $gatewaySubscriptionId, array $context = []): PaymentResult
    {
        $secret = (string) $this->cred('secret_key');
        if ($secret === '') return PaymentResult::failed('stripe_secret_key_missing');
        try {
            $r = Http::withBasicAuth($secret, '')->timeout(self::HTTP_TIMEOUT_SECONDS)
                ->delete('https://api.stripe.com/v1/subscriptions/' . $gatewaySubscriptionId);
            if (!$r->successful()) return PaymentResult::failed('stripe_cancel: ' . ($r->json('error.message') ?: 'HTTP ' . $r->status()));
            return PaymentResult::paid(gatewayOrderId: $gatewaySubscriptionId, payload: $r->json());
        } catch (\Throwable $e) {
            return PaymentResult::failed('stripe_cancel_exception: ' . $e->getMessage());
        }
    }
}
