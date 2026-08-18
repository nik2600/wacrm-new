<?php

namespace App\Services\Payment\Drivers;

use App\Models\Order;
use App\Services\Payment\AbstractGatewayDriver;
use App\Services\Payment\PaymentResult;
use Illuminate\Support\Facades\Http;

/**
 * Paddle (Billing) payment gateway driver.
 *
 * Creates a one-off transaction with a non-catalog price and redirects
 * to Paddle's hosted checkout.
 *
 * @see https://developer.paddle.com/api-reference/
 */
class PaddleDriver extends AbstractGatewayDriver
{
    private const SANDBOX_BASE = 'https://sandbox-api.paddle.com';
    private const PROD_BASE    = 'https://api.paddle.com';

    public static function credentialFields(): array
    {
        return [
            'api_key'        => ['label' => 'API Key',            'type' => 'password', 'required' => true],
            'product_id'     => ['label' => 'Product ID',         'type' => 'text',     'required' => true],
            'client_token'   => ['label' => 'Client-side Token',  'type' => 'text',     'required' => false, 'hint' => 'Paddle → Developer Tools → Authentication → Client-side tokens (live_… / test_…). Enables the built-in checkout so payment works without a Paddle "default payment link".'],
            'webhook_secret' => ['label' => 'Webhook Secret Key', 'type' => 'password', 'required' => false],
        ];
    }

    public function initiate(Order $order, string $callbackUrl): PaymentResult
    {
        $apiKey    = $this->apiKey();
        $productId = trim((string) $this->cred('product_id'));
        if ($apiKey === '' || $productId === '') return PaymentResult::failed('paddle_credentials_missing');

        // Preflight: a Paddle API key is EXACTLY 69 chars
        // (pdl_{live|sdbx}_apikey_<26>_<22>_<3>); legacy keys (pre-2025-05) are 50.
        // A pdl_ key of ANY other length is TRUNCATED — the operator copied only
        // part of it (double-clicking a key selects up to an underscore, grabbing
        // a fragment). Catch it here with a precise message instead of a round-trip
        // that returns Paddle's cryptic `authentication_malformed`.
        if (str_starts_with($apiKey, 'pdl_') && !in_array(strlen($apiKey), [69, 50], true)) {
            \Illuminate\Support\Facades\Log::warning('[PADDLE] API key wrong length — likely truncated (copied only part of it)', [
                'order' => $order->order_number, 'len' => strlen($apiKey), 'expected' => 69,
            ]);
            return PaymentResult::failed('paddle: Your Paddle API Key is incomplete — it is ' . strlen($apiKey)
                . ' characters, but a Paddle key is 69. You copied only part of it. In Paddle → Developer Tools →'
                . ' Authentication → API keys, use the COPY button (or select the whole field, not a double-click)'
                . ' to copy the ENTIRE key, then paste it again.');
        }

        $body = [
            'items' => [[
                'price' => [
                    'description'   => "Order #{$order->order_number}",
                    'product_id'    => $productId,
                    'billing_cycle' => null,
                    'unit_price' => [
                        'amount'        => (string) ((int) round((float) $order->amount * 100)),
                        'currency_code' => strtoupper($order->currency ?? 'USD'),
                    ],
                ],
                'quantity' => 1,
            ]],
            'custom_data' => [
                'order_id'     => $order->id,
                'order_number' => $order->order_number,
            ],
        ];

        // Trace the attempt BEFORE the call so a hang/timeout is still visible.
        // We MASK both credentials (never log a full secret — logs get shared):
        // showing the first 10 + last 4 chars + length is enough to SEE what was
        // pasted (pdl_live_… vs live_… vs cs_…) and diagnose a wrong/swapped key,
        // without leaking the usable key.
        $mask = function (?string $s): string {
            $s = (string) $s;
            $len = strlen($s);
            if ($len === 0) return '(empty)';
            if ($len <= 8)  return substr($s, 0, 2) . '…(len ' . $len . ')';
            return substr($s, 0, 10) . '…' . substr($s, -4) . ' (len ' . $len . ')';
        };
        \Illuminate\Support\Facades\Log::info('[PADDLE] initiate → create transaction', [
            'order'       => $order->order_number,
            'base'        => $this->baseUrl(),
            'key_env'     => str_starts_with($apiKey, 'pdl_sdbx_') ? 'sandbox'
                           : (str_starts_with($apiKey, 'pdl_live_') ? 'live' : 'unrecognised-prefix'),
            'key_preview' => $mask($apiKey),                                        // masked, CLEANED API key
            // Raw byte length straight from storage. If this is BIGGER than the
            // "(len …)" in key_preview, hidden/invisible chars were stripped —
            // i.e. THAT was the malformed-header cause (now fixed).
            'key_raw_len' => strlen((string) $this->cred('api_key')),
            'client_token_preview' => $mask($this->clientToken()),                  // masked client-side token
            'live_tog'    => $this->isLive() ? 'live' : 'test',
            'product'     => $productId,
            'amount'      => (string) ((int) round((float) $order->amount * 100)),
            'currency'    => strtoupper($order->currency ?? 'USD'),
        ]);

        try {
            $r = Http::withToken($apiKey)->timeout(self::HTTP_TIMEOUT_SECONDS)
                ->post($this->baseUrl() . '/transactions', $body);
            $json  = $r->json() ?: [];
            $txnId = (string) ($json['data']['id'] ?? '');
            // Prefer OUR OWN Paddle.js checkout overlay when a client-side token is
            // configured. It opens the checkout for THIS transaction on a page we
            // control, so the customer can actually pay — regardless of the Paddle
            // dashboard "default payment link" (which is often just the homepage,
            // where no checkout renders and the transaction sits at 'draft'
            // forever). This is what makes Paddle work reliably.
            if ($txnId !== '' && trim((string) $this->cred('client_token')) !== '') {
                $overlayUrl = route('payment.paddle.checkout', ['order' => $order->id, 'txn' => $txnId]);
                \Illuminate\Support\Facades\Log::info('[PADDLE] initiate ✓ → built-in checkout overlay', [
                    'order' => $order->order_number, 'txn' => $txnId,
                ]);
                return PaymentResult::redirect($overlayUrl, $txnId, $json);
            }
            // No client token → fall back to Paddle's returned checkout url (the
            // dashboard default payment link). Works only if that link is a real
            // checkout page and not a plain marketing page.
            if (isset($json['data']['checkout']['url'])) {
                \Illuminate\Support\Facades\Log::info('[PADDLE] initiate ✓ transaction created + checkout url issued (no client_token → Paddle default link)', [
                    'order' => $order->order_number,
                    'txn'   => $txnId ?: null,
                    'url'   => $json['data']['checkout']['url'],
                ]);
                return PaymentResult::redirect(
                    $json['data']['checkout']['url'],
                    $txnId ?: null,
                    $json,
                );
            }
                // Surface Paddle's real error (code + detail) so an auth/setup
                // problem reads as e.g. "invalid_token: API key not valid" instead
                // of a generic "create_failed" — the client can then fix the key.
                $err    = is_array($json['error'] ?? null) ? $json['error'] : [];
                $reason = trim(((string) ($err['code'] ?? '')) . ' ' . ((string) ($err['detail'] ?? '')));
                // Wrong VALUE in the API Key field is the usual cause of
                // authentication_malformed: a real Paddle key always starts with
                // pdl_live_ / pdl_sdbx_. If it doesn't, the operator almost certainly
                // pasted the Client-side token (live_…) into the API Key box. Say so.
                $prefixOk = str_starts_with($apiKey, 'pdl_live_') || str_starts_with($apiKey, 'pdl_sdbx_');
                if (!$prefixOk && (($err['code'] ?? '') === 'authentication_malformed' || ($err['code'] ?? '') === 'invalid_token' || $err === [])) {
                    $reason = 'The API Key is not a Paddle API key (it must start with pdl_live_ or pdl_sdbx_). You likely pasted the Client-side token (live_…) into the API Key field. In the Paddle gateway settings: API Key = the pdl_live_… key from Paddle → Developer Tools → Authentication → API keys; Client-side Token = the separate live_… token.';
                }
            // No checkout url means Paddle rejected the create OR returned a txn
            // with no hosted checkout (no default payment link configured). Log
            // the FULL response so the exact cause is in the client's log.
            \Illuminate\Support\Facades\Log::warning('[PADDLE] initiate ✗ no checkout url returned', [
                'order'        => $order->order_number,
                'http_status'  => $r->status(),
                'error_code'   => $err['code']   ?? null,
                'error_detail' => $err['detail'] ?? null,
                'has_txn'      => isset($json['data']['id']),
                'has_checkout' => isset($json['data']['checkout']),
                'body'         => mb_substr($r->body(), 0, 1000),
            ]);
            return PaymentResult::failed('paddle: ' . ($reason !== '' ? $reason : ('HTTP ' . $r->status())));
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('[PADDLE] initiate EXCEPTION (network/timeout?)', [
                'order' => $order->order_number,
                'base'  => $this->baseUrl(),
                'error' => $e->getMessage(),
            ]);
            return PaymentResult::failed('paddle_exception: ' . $e->getMessage());
        }
    }

    public function handleCallback(array $payload): PaymentResult
    {
        $txnId  = $payload['transaction_id'] ?? $payload['_ptxn'] ?? null;
        $status = $payload['status'] ?? '';
        \Illuminate\Support\Facades\Log::info('[PADDLE] handleCallback ← return hit', [
            'txn'    => $txnId,
            'status' => $status !== '' ? $status : '(none — hosted return)',
            'keys'   => implode(',', array_keys($payload)),
        ]);
        if (!$txnId) {
            \Illuminate\Support\Facades\Log::warning('[PADDLE] handleCallback — NO transaction id in return; cannot confirm payment', [
                'keys' => implode(',', array_keys($payload)),
            ]);
            return new PaymentResult(status: 'pending', payload: $payload);
        }

        if ($status === 'completed' || $status === 'paid') {
            \Illuminate\Support\Facades\Log::info('[PADDLE] handleCallback ✓ paid (status in return)', ['txn' => $txnId, 'status' => $status]);
            return PaymentResult::paid(gatewayPaymentId: (string) $txnId, payload: $payload);
        }

        // The hosted-checkout return carries ONLY `_ptxn` (the transaction id) —
        // no status — so we must VERIFY it against the API to know whether the
        // buyer actually paid. Without this a completed payment sat "pending"
        // (order never activated) until the async webhook eventually arrived. And
        // when Paddle's "default payment link" is a plain page (e.g. the homepage)
        // the redirect is the only signal the buyer sees, so confirming here is
        // what lands them on the success page. GET /transactions/{id}.
        $apiKey = $this->apiKey();
        if ($apiKey !== '') {
            try {
                \Illuminate\Support\Facades\Log::info('[PADDLE] handleCallback → verifying txn against API', [
                    'txn' => $txnId, 'base' => $this->baseUrl(),
                ]);
                $r  = Http::withToken($apiKey)->timeout(self::HTTP_TIMEOUT_SECONDS)
                    ->get($this->baseUrl() . '/transactions/' . $txnId);
                $st = (string) ($r->json('data.status') ?? '');
                if (in_array($st, ['completed', 'paid'], true)) {
                    \Illuminate\Support\Facades\Log::info('[PADDLE] handleCallback ✓ verified paid', ['txn' => $txnId, 'status' => $st]);
                    return PaymentResult::paid(gatewayPaymentId: (string) $txnId, payload: $r->json('data') ?: $payload);
                }
                // Not paid. Surface the REAL Paddle status instead of a blank
                // "unknown". A status of `ready`/`draft`/`billed` on the return
                // means the buyer never actually completed payment — almost always
                // because the Paddle dashboard "Default payment link" points at a
                // plain page (e.g. the homepage) that carries no Paddle.js checkout,
                // so no payment UI ever rendered and the buyer just bounced back.
                \Illuminate\Support\Facades\Log::warning('[PADDLE] transaction not completed on return', [
                    'txn'           => $txnId,
                    'paddle_status' => $st !== '' ? $st : ('HTTP ' . $r->status()),
                    'body'          => mb_substr((string) $r->body(), 0, 400),
                ]);
                $reason = $st !== ''
                    ? ('the payment was not completed (Paddle status: ' . $st . '). Open the Paddle overlay checkout and pay before returning.')
                    : ('could not verify the payment with Paddle (HTTP ' . $r->status() . ').');
                return new PaymentResult(status: 'pending', gatewayPaymentId: (string) $txnId, error: $reason, payload: $payload);
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('[PADDLE] verify threw', ['txn' => $txnId, 'err' => $e->getMessage()]);
                return new PaymentResult(status: 'pending', gatewayPaymentId: (string) $txnId, error: 'paddle_verify_exception: ' . $e->getMessage(), payload: $payload);
            }
        }
        \Illuminate\Support\Facades\Log::warning('[PADDLE] handleCallback — API key missing, cannot verify payment', ['txn' => $txnId]);
        return new PaymentResult(status: 'pending', gatewayPaymentId: (string) $txnId, error: 'paddle_api_key_missing', payload: $payload);
    }

    public function verifyWebhookSignature(string $rawBody, ?string $signatureHeader): bool
    {
        $secret = (string) $this->cred('webhook_secret');
        if ($secret === '' || $signatureHeader === null) return true;

        $parts = [];
        foreach (explode(';', $signatureHeader) as $item) {
            [$k, $v] = explode('=', $item, 2) + [1 => ''];
            $parts[$k] = $v;
        }
        $ts = $parts['ts'] ?? '';
        $h1 = $parts['h1'] ?? '';
        if ($ts === '' || $h1 === '') return false;
        $expected = hash_hmac('sha256', $ts . ':' . $rawBody, $secret);
        return hash_equals($expected, $h1);
    }

    public function handleWebhook(array $payload): PaymentResult
    {
        $event = $payload['event_type'] ?? '';
        $data  = $payload['data'] ?? [];
        if ($event === 'transaction.completed') {
            return PaymentResult::paid(
                gatewayPaymentId: (string) ($data['id'] ?? ''),
                payload:          $data,
            );
        }
        return PaymentResult::failed("unhandled_paddle_event: {$event}", $payload);
    }

    // ── Recurring subscriptions ──────────────────────────────────────
    //
    // Verified: Paddle has no "create subscription" call. You create a
    // transaction for a RECURRING price (billing_cycle set) and Paddle creates
    // the subscription itself once the customer pays the hosted checkout. Each
    // cycle fires transaction.completed (data.subscription_id).

    public function supportsRecurring(): bool
    {
        return true;
    }

    public function createSubscription(Order $order, string $callbackUrl): PaymentResult
    {
        $apiKey    = $this->apiKey();
        $productId = trim((string) $this->cred('product_id'));
        if ($apiKey === '' || $productId === '') return PaymentResult::failed('paddle_credentials_missing');

        $plan = $this->planInterval($order);                         // interval=day/week/month/year, count
        $body = [
            'items' => [[
                'price' => [
                    'description'   => "Order #{$order->order_number}",
                    'product_id'    => $productId,
                    // Setting billing_cycle is what makes Paddle treat this as a
                    // subscription instead of a one-off.
                    'billing_cycle' => ['interval' => $plan['interval'], 'frequency' => $plan['count']],
                    'unit_price' => [
                        'amount'        => (string) ((int) round((float) $order->amount * 100)),
                        'currency_code' => strtoupper($order->currency ?? 'USD'),
                    ],
                ],
                'quantity' => 1,
            ]],
            'collection_mode' => 'automatic',
            'custom_data' => [
                'order_id'     => (string) $order->id,
                'order_number' => $order->order_number,
                'workspace_id' => (string) $order->workspace_id,
            ],
        ];

        try {
            $r = Http::withToken($apiKey)->timeout(self::HTTP_TIMEOUT_SECONDS)
                ->post($this->baseUrl() . '/transactions', $body);
            $json = $r->json() ?: [];
            if (isset($json['data']['checkout']['url'])) {
                return PaymentResult::redirect($json['data']['checkout']['url'], $json['data']['id'] ?? null, $json);
            }
            $err    = is_array($json['error'] ?? null) ? $json['error'] : [];
            $reason = trim(((string) ($err['code'] ?? '')) . ' ' . ((string) ($err['detail'] ?? '')));
            return PaymentResult::failed('paddle_subscription: ' . ($reason !== '' ? $reason : ('HTTP ' . $r->status())));
        } catch (\Throwable $e) {
            return PaymentResult::failed('paddle_subscription_exception: ' . $e->getMessage());
        }
    }

    public function parseSubscriptionWebhook(array $payload): ?array
    {
        $event = (string) ($payload['event_type'] ?? '');
        $data  = $payload['data'] ?? [];
        if (!is_array($data)) return null;
        $orderId = $data['custom_data']['order_id'] ?? null;

        switch ($event) {
            case 'transaction.completed':
                // Only subscription transactions carry a subscription_id.
                $subId = $data['subscription_id'] ?? null;
                if (!$subId) return null;
                return ['type' => 'renewed', 'subscription_id' => $subId, 'payment_id' => $data['id'] ?? null, 'period_end' => null, 'order_id' => $orderId];

            case 'subscription.created':
            case 'subscription.activated':
                $periodEnd = $data['current_billing_period']['ends_at'] ?? ($data['next_billed_at'] ?? null);
                return ['type' => 'created', 'subscription_id' => $data['id'] ?? null, 'payment_id' => null, 'period_end' => $periodEnd, 'order_id' => $orderId];

            case 'subscription.canceled':
                return ['type' => 'canceled', 'subscription_id' => $data['id'] ?? null, 'payment_id' => null, 'period_end' => null, 'order_id' => $orderId];

            case 'transaction.payment_failed':
                $subId = $data['subscription_id'] ?? null;
                if (!$subId) return null;
                return ['type' => 'payment_failed', 'subscription_id' => $subId, 'payment_id' => $data['id'] ?? null, 'period_end' => null, 'order_id' => $orderId];

            default:
                return null;
        }
    }

    public function cancelSubscription(string $gatewaySubscriptionId, array $context = []): PaymentResult
    {
        $apiKey = $this->apiKey();
        if ($apiKey === '') return PaymentResult::failed('paddle_api_key_missing');
        try {
            $r = Http::withToken($apiKey)->timeout(self::HTTP_TIMEOUT_SECONDS)
                ->post($this->baseUrl() . '/subscriptions/' . $gatewaySubscriptionId . '/cancel', ['effective_from' => 'next_billing_period']);
            if (!$r->successful()) return PaymentResult::failed('paddle_cancel: HTTP ' . $r->status());
            return PaymentResult::paid(gatewayOrderId: $gatewaySubscriptionId, payload: $r->json());
        } catch (\Throwable $e) {
            return PaymentResult::failed('paddle_cancel_exception: ' . $e->getMessage());
        }
    }

    /**
     * The Paddle API key, cleaned. Copy-pasting a key (from the Paddle dashboard,
     * an email, a PDF) sneaks in characters that make the `Authorization: Bearer …`
     * header malformed → Paddle returns `authentication_malformed`:
     *   - a leftover "Bearer " prefix or wrapping quotes,
     *   - a NEWLINE (the key wrapped across two lines),
     *   - INVISIBLE Unicode — a non-breaking space (U+00A0), zero-width space
     *     (U+200B), or a BOM (U+FEFF). PHP's `\s` matches ASCII whitespace ONLY,
     *     so those survive a plain \s strip and keep the header broken.
     * A Paddle key is printable ASCII, so we drop every byte outside printable
     * ASCII (0x21–0x7E) — that removes ALL of the above, visible and invisible.
     */
    private function apiKey(): string
    {
        $key = trim((string) $this->cred('api_key'));
        $key = preg_replace('/^bearer\s+/i', '', $key);   // stray "Bearer " prefix
        $key = trim($key, "\"'");                          // wrapping quotes
        $key = preg_replace('/[^\x21-\x7E]/', '', $key);   // drop ALL non-printable-ASCII (NBSP/zero-width/BOM/whitespace)
        return (string) $key;
    }

    /**
     * Base URL is decided by the API KEY prefix, not the admin's live/test
     * toggle. A sandbox key (pdl_sdbx_…) sent to api.paddle.com — or a live
     * key sent to sandbox-api.paddle.com — returns a 403 `invalid_token`
     * ("authentication error"), the #1 Paddle setup mistake. Trusting the
     * key means the two can never disagree; we fall back to the toggle only
     * when the prefix is unrecognised (e.g. a legacy/custom key).
     */
    private function baseUrl(): string
    {
        $key = $this->apiKey();
        if (str_starts_with($key, 'pdl_sdbx_')) return self::SANDBOX_BASE;
        if (str_starts_with($key, 'pdl_live_')) return self::PROD_BASE;
        return $this->isLive() ? self::PROD_BASE : self::SANDBOX_BASE;
    }

    /**
     * Paddle.js client-side token (public, safe to embed in the page). Used by
     * the built-in checkout overlay. Cleaned the same way as the API key — a
     * pasted newline/space would break `Paddle.Initialize({ token })` and the
     * overlay would silently fail to open.
     */
    public function clientToken(): string
    {
        $t = trim((string) $this->cred('client_token'));
        $t = trim($t, "\"'");
        $t = preg_replace('/[^\x21-\x7E]/', '', $t);   // same non-printable strip as apiKey()
        return (string) $t;
    }

    /**
     * Paddle.js Environment: 'sandbox' or 'production'. Mirror the same
     * key-prefix logic baseUrl() uses so the overlay and the API never disagree
     * (sandbox token on the production environment silently shows no checkout).
     */
    public function environment(): string
    {
        return $this->baseUrl() === self::SANDBOX_BASE ? 'sandbox' : 'production';
    }
}
