<?php

namespace App\Services\Payment;

/**
 * DTO returned by gateway drivers' initiate() / handleCallback() /
 * handleWebhook(). Tells the CheckoutController what to do next:
 *
 *   - redirect_url → send the user there (Stripe Checkout, PayPal
 *                    consent screen, Razorpay redirect, etc.)
 *   - html         → render inline (gateways that POST a form to
 *                    their domain — typical of 3DS-enabled flows)
 *   - status       → 'pending' | 'paid' | 'failed' (terminal state
 *                    when called from handleCallback)
 */
class PaymentResult
{
    public function __construct(
        public readonly string $status = 'pending',
        public readonly ?string $redirectUrl = null,
        public readonly ?string $html = null,
        public readonly ?string $gatewayOrderId = null,
        public readonly ?string $gatewayPaymentId = null,
        public readonly ?array $payload = null,
        public readonly ?string $error = null,
    ) {}

    public static function redirect(string $url, ?string $gatewayOrderId = null, ?array $payload = null): self
    {
        return new self(
            status:         'pending',
            redirectUrl:    $url,
            gatewayOrderId: $gatewayOrderId,
            payload:        $payload,
        );
    }

    public static function form(string $html, ?string $gatewayOrderId = null, ?array $payload = null): self
    {
        return new self(
            status:         'pending',
            html:           $html,
            gatewayOrderId: $gatewayOrderId,
            payload:        $payload,
        );
    }

    public static function paid(?string $gatewayPaymentId = null, ?string $gatewayOrderId = null, ?array $payload = null): self
    {
        return new self(
            status:           'paid',
            gatewayPaymentId: $gatewayPaymentId,
            gatewayOrderId:   $gatewayOrderId,
            payload:          $payload,
        );
    }

    public static function failed(string $reason, ?array $payload = null): self
    {
        return new self(
            status:  'failed',
            error:   $reason,
            payload: $payload,
        );
    }
}
