<?php

namespace App\Services\Payment;

use App\Models\Order;
use App\Models\PaymentGateway;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Base class every gateway driver extends. Drivers stay deliberately
 * Composer-SDK-free: they all use the built-in Http client (or pure
 * cURL via the helpers below) so a fresh install doesn't have to
 * pull 31 vendor packages.
 *
 * Each driver MUST implement:
 *   - credentialFields()   (static; declares the admin form fields)
 *   - initiate(Order, callbackUrl) → PaymentResult
 *   - handleCallback(Request payload) → PaymentResult
 *
 * Optional overrides:
 *   - handleWebhook(payload) → PaymentResult
 *   - verifyWebhookSignature(payload, signature) → bool
 *   - verify(Order) → PaymentResult (manual verification path)
 */
abstract class AbstractGatewayDriver
{
    // Stripe/PayPal/Razorpay API calls (and especially Checkout-Session
    // creation) can be slow from some hosts; 20s was tripping intermittent
    // "timeout" failures on otherwise-valid checkouts. 45s is well under any
    // user-facing redirect budget but gives the gateway room to respond.
    protected const HTTP_TIMEOUT_SECONDS = 45;

    public function __construct(protected PaymentGateway $gateway) {}

    /**
     * Declares the credential fields the admin form should render.
     * Return shape:
     *   [
     *     'publishable_key' => ['label' => 'Publishable key', 'type' => 'text', 'required' => true],
     *     'secret_key'      => ['label' => 'Secret key',      'type' => 'password', 'required' => true],
     *     'webhook_secret'  => ['label' => 'Webhook secret',  'type' => 'password', 'required' => false],
     *   ]
     */
    abstract public static function credentialFields(): array;

    /**
     * Start the payment flow. Builds the gateway-specific request,
     * returns a PaymentResult that's either a redirect URL or inline
     * HTML form for the user.
     */
    abstract public function initiate(Order $order, string $callbackUrl): PaymentResult;

    /**
     * The gateway redirected the user back to /payment/callback/{slug}.
     * Verify the signature, look up the order, return a terminal
     * PaymentResult (paid / failed).
     */
    abstract public function handleCallback(array $payload): PaymentResult;

    /**
     * Async webhook from the gateway. Default: just call handleCallback.
     * Drivers with separate webhook shapes override.
     */
    public function handleWebhook(array $payload): PaymentResult
    {
        return $this->handleCallback($payload);
    }

    // ── Recurring / subscriptions (opt-in) ───────────────────────────
    //
    // A driver advertises auto-renew support by returning true from
    // supportsRecurring() and implementing createSubscription() +
    // parseSubscriptionWebhook(). Drivers that don't (the default) are
    // simply charged one-time as before — nothing breaks.

    /** Does this gateway support server-driven auto-renewing subscriptions? */
    public function supportsRecurring(): bool
    {
        return false;
    }

    /**
     * Start a recurring subscription instead of a one-time charge. Same
     * return contract as initiate(): a redirect/inline-form PaymentResult for
     * the first authorization, or a terminal paid/failed. The gateway then
     * charges every cycle on its own and fires a renewal webhook that
     * parseSubscriptionWebhook() decodes.
     */
    public function createSubscription(Order $order, string $callbackUrl): PaymentResult
    {
        return PaymentResult::failed('recurring_not_supported');
    }

    /**
     * Decode a subscription-lifecycle webhook into a normalized event so the
     * controller can act gateway-agnostically. Return null when the payload
     * isn't a subscription event (so the caller falls through to one-time
     * handling). Shape:
     *   [
     *     'type'            => 'renewed' | 'canceled' | 'payment_failed' | 'created',
     *     'subscription_id' => '<gateway subscription id>',
     *     'payment_id'      => '<gateway payment/invoice id>'  (nullable),
     *     'period_end'      => <unix ts of new period end>     (nullable),
     *   ]
     */
    public function parseSubscriptionWebhook(array $payload): ?array
    {
        return null;
    }

    /**
     * Cancel an auto-renewing subscription at the gateway so it stops
     * charging. Default: not supported (the admin can cancel in the gateway
     * dashboard). Drivers override with the real API call.
     */
    public function cancelSubscription(string $gatewaySubscriptionId, array $context = []): PaymentResult
    {
        return PaymentResult::failed('cancel_not_supported');
    }

    /**
     * The user returned from a RECURRING checkout. Default: same as a one-time
     * return (handleCallback) — correct for gateways whose hosted page already
     * created the subscription (Paddle / Paystack / Flutterwave / Stripe /
     * PayPal / Razorpay).
     *
     * Gateways that collect the card on OUR page and must create the
     * subscription server-side at this point (Braintree Drop-in, Authorize.Net
     * hosted, Mollie mandate-first) override this to vault the card / mandate
     * and call the subscription API. A successful return should carry the
     * gateway subscription id so the controller can store it:
     *   PaymentResult::paid(gatewayOrderId: <sub id>, payload: ['is_subscription'=>true, 'current_period_end'=>…])
     */
    public function handleSubscriptionCallback(array $payload, Order $order): PaymentResult
    {
        return $this->handleCallback($payload);
    }

    /**
     * Normalize a package's billing cycle (plan_unit + plan_duration) into the
     * interval vocabulary every gateway shares. WaDesk packages store plan_unit
     * as days/weeks/months/years; gateways want day/week/month/year + a count.
     *
     * @return array{interval:string,count:int,cycle:string}
     */
    protected function planInterval(Order $order): array
    {
        $pkg   = $order->package;
        $unit  = strtolower((string) ($pkg->plan_unit ?? 'months'));
        $count = max(1, (int) ($pkg->plan_duration ?? 1));

        $interval = match ($unit) {
            'days', 'day'     => 'day',
            'weeks', 'week'   => 'week',
            'years', 'year'   => 'year',
            default           => 'month',
        };

        // A coarse monthly/yearly label for storage + UI.
        $cycle = in_array($interval, ['year'], true) ? 'yearly' : 'monthly';

        return ['interval' => $interval, 'count' => $count, 'cycle' => $cycle];
    }

    /** HMAC / signature check. Default = no signature, accept everything (admin sandbox). */
    public function verifyWebhookSignature(string $rawBody, ?string $signatureHeader): bool
    {
        return true;
    }

    /**
     * Optional "is this order actually paid?" call. The CheckoutController
     * can hit this if the callback dropped or the webhook is late.
     */
    public function verify(Order $order): PaymentResult
    {
        return PaymentResult::failed('verify_not_implemented');
    }

    // ── HTTP helpers (shared by every driver) ────────────────────────

    /**
     * One-shot JSON request. Returns [success, body_array, status, error].
     * Drivers can also use the Laravel `Http` facade directly — this is
     * just a convenience for the simple POST-JSON-get-JSON case.
     */
    protected function httpJson(string $method, string $url, array $body = [], array $headers = []): array
    {
        try {
            $r = Http::timeout(self::HTTP_TIMEOUT_SECONDS)
                ->withHeaders($headers + ['Accept' => 'application/json'])
                ->{$method}($url, $body);
            return [
                'success' => $r->successful(),
                'body'    => $r->json() ?: [],
                'status'  => $r->status(),
                'error'   => $r->successful() ? null : ($r->json('message') ?: $r->body()),
            ];
        } catch (\Throwable $e) {
            Log::warning('[PAY] http request failed: ' . $e->getMessage());
            return ['success' => false, 'body' => [], 'status' => 0, 'error' => $e->getMessage()];
        }
    }

    /** Form-POST helper for OAuth-style token endpoints. */
    protected function httpForm(string $url, array $form, array $headers = []): array
    {
        try {
            $r = Http::asForm()->timeout(self::HTTP_TIMEOUT_SECONDS)->withHeaders($headers)->post($url, $form);
            return [
                'success' => $r->successful(),
                'body'    => $r->json() ?: [],
                'status'  => $r->status(),
                'error'   => $r->successful() ? null : $r->body(),
            ];
        } catch (\Throwable $e) {
            return ['success' => false, 'body' => [], 'status' => 0, 'error' => $e->getMessage()];
        }
    }

    protected function isLive(): bool
    {
        return $this->gateway->mode === 'live';
    }

    protected function cred(string $key, $default = null)
    {
        return $this->gateway->getCredential($key, $default);
    }
}
