<?php

namespace App\Services\Payment\Drivers;

use App\Models\Order;
use App\Services\Payment\AbstractGatewayDriver;
use App\Services\Payment\PaymentResult;
use Illuminate\Support\Facades\Http;

/**
 * Flutterwave (Rave) payment gateway driver.
 *
 * Creates a Standard Payment link, redirects the customer, then verifies
 * the transaction on callback.
 *
 * @see https://developer.flutterwave.com/docs/collecting-payments/standard/
 */
class FlutterwaveDriver extends AbstractGatewayDriver
{
    private const API_BASE = 'https://api.flutterwave.com/v3';

    public static function credentialFields(): array
    {
        return [
            'public_key'     => ['label' => 'Public Key',     'type' => 'text',     'required' => true],
            'secret_key'     => ['label' => 'Secret Key',     'type' => 'password', 'required' => true],
            'encryption_key' => ['label' => 'Encryption Key', 'type' => 'password', 'required' => false, 'hint' => 'Optional — inline/encrypted flows.'],
            'secret_hash'    => ['label' => 'Webhook Secret Hash', 'type' => 'password', 'required' => false, 'hint' => 'Optional — webhook verif-hash header.'],
        ];
    }

    public function initiate(Order $order, string $callbackUrl): PaymentResult
    {
        $secretKey = (string) $this->cred('secret_key');
        if ($secretKey === '') return PaymentResult::failed('flutterwave_secret_key_missing');

        $email = optional($order->user)->email;
        if (!$email) return PaymentResult::failed('flutterwave_customer_email_missing');

        $txRef = 'FLW_' . $order->order_number . '_' . time();
        $body = [
            'tx_ref'          => $txRef,
            'amount'          => (float) $order->amount,
            'currency'        => strtoupper($order->currency ?? 'NGN'),
            'redirect_url'    => $callbackUrl,
            'payment_options' => 'card,mobilemoney,ussd,banktransfer',
            'customer' => [
                'email' => $email,
                'name'  => optional($order->user)->name ?? 'Customer',
            ],
            'customizations' => [
                'title'       => config('app.name'),
                'description' => "Order #{$order->order_number}",
            ],
            'meta' => [
                'order_id'     => $order->id,
                'order_number' => $order->order_number,
            ],
        ];

        try {
            // acceptJson() + an explicit User-Agent matter: without an
            // `Accept: application/json` header (and with the bare "GuzzleHttp/7"
            // UA) Flutterwave's Cloudflare edge sometimes serves its bot/Browser-
            // Integrity CHALLENGE page — an HTML 403 that never reaches the API —
            // instead of a JSON response. Presenting as a JSON API client is the
            // standard way past that managed challenge.
            $r = Http::withToken($secretKey)
                ->acceptJson()
                ->withHeaders(['User-Agent' => 'WaDesk-Payments/1.0 (+api-client)'])
                ->timeout(self::HTTP_TIMEOUT_SECONDS)
                ->post(self::API_BASE . '/payments', $body);
            $json = $r->json() ?: [];
            if (($json['status'] ?? '') === 'success' && isset($json['data']['link'])) {
                return PaymentResult::redirect($json['data']['link'], $txRef, $json);
            }
            // Surface Flutterwave's ACTUAL reason — a bare "create_failed" hid the
            // real cause. The #1 cause is a bad Secret Key: FLW authenticates the
            // /payments call with the SECRET key (FLWSECK-…), NOT the public key
            // (FLWPUBK-…). If the two were pasted into the wrong fields, this call
            // 401s. Log the status + body and put the reason in the message.
            $rawBody = (string) $r->body();
            $isEdgeBlock = $json === [] && (
                stripos($rawBody, '<html') !== false || stripos($rawBody, '<!doctype') !== false
            );
            \Illuminate\Support\Facades\Log::warning('[FLUTTERWAVE] payment create failed', [
                'http_status' => $r->status(),
                'fw_status'   => $json['status']  ?? null,
                'fw_message'  => $json['message'] ?? null,
                'edge_block'  => $isEdgeBlock,
                'body'        => mb_substr($rawBody, 0, 500),
                'order'       => $order->order_number,
                'currency'    => strtoupper($order->currency ?? 'NGN'),
            ]);
            $reason = trim((string) ($json['message'] ?? ''));
            if ($reason === '' && $r->status() === 401) {
                $reason = 'Authorization failed — the Secret Key is wrong. Paste the FLWSECK- secret into the Secret Key field (the FLWPUBK- public key is not accepted here).';
            }
            // An HTML body = Flutterwave's edge (Cloudflare) blocked the request
            // BEFORE it reached your account. Never dump the raw HTML page into the
            // checkout box — say plainly what it is and what to check.
            if ($reason === '' && $isEdgeBlock) {
                $reason = 'Flutterwave blocked the request at its edge (HTTP ' . $r->status()
                    . '). This is a network/firewall block, not a card error — the API call was rejected'
                    . ' before it reached your Flutterwave account. Check that the Secret Key (FLWSECK-…)'
                    . ' is correct and that your server can reach api.flutterwave.com (some hosting IPs or'
                    . ' regions are blocked by Flutterwave — contact Flutterwave support to whitelist it).';
            }
            if ($reason === '') {
                $reason = 'create_failed (HTTP ' . $r->status() . ')';
            }
            return PaymentResult::failed('flutterwave: ' . $reason, $json);
        } catch (\Throwable $e) {
            return PaymentResult::failed('flutterwave_exception: ' . $e->getMessage());
        }
    }

    public function handleCallback(array $payload): PaymentResult
    {
        $status        = $payload['status'] ?? '';
        $transactionId = $payload['transaction_id'] ?? null;
        $txRef         = $payload['tx_ref'] ?? null;

        if ($status === 'cancelled') return PaymentResult::failed('cancelled_by_user');
        if (!$transactionId && !$txRef) return PaymentResult::failed('missing_flutterwave_tx');

        $secretKey = (string) $this->cred('secret_key');
        try {
            if ($transactionId) {
                $r = Http::withToken($secretKey)->timeout(self::HTTP_TIMEOUT_SECONDS)
                    ->get(self::API_BASE . "/transactions/{$transactionId}/verify");
            } else {
                $r = Http::withToken($secretKey)->timeout(self::HTTP_TIMEOUT_SECONDS)
                    ->get(self::API_BASE . '/transactions/verify_by_reference', ['tx_ref' => $txRef]);
            }
            $json = $r->json() ?: [];

            if (($json['status'] ?? '') === 'success' && ($json['data']['status'] ?? '') === 'successful') {
                return PaymentResult::paid(
                    gatewayPaymentId: (string) ($json['data']['id'] ?? $transactionId ?? $txRef),
                    gatewayOrderId:   (string) ($json['data']['tx_ref'] ?? $txRef ?? ''),
                    payload:          $json,
                );
            }
            return PaymentResult::failed('flutterwave_status: ' . ($json['data']['status'] ?? 'unknown'), $json);
        } catch (\Throwable $e) {
            return PaymentResult::failed('flutterwave_callback_exception: ' . $e->getMessage());
        }
    }

    public function verifyWebhookSignature(string $rawBody, ?string $signatureHeader): bool
    {
        $hash = (string) $this->cred('secret_hash');
        // Flutterwave v3 sends the configured secret hash verbatim in the
        // `verif-hash` header — a direct equality check is correct (NOT HMAC).
        // Skip only when no secret hash is configured; reject when one is set
        // but the header is absent.
        if ($hash === '') return true;
        if ($signatureHeader === null) return false;
        return hash_equals($hash, $signatureHeader);
    }

    public function handleWebhook(array $payload): PaymentResult
    {
        $event = $payload['event'] ?? '';
        $data  = $payload['data'] ?? [];
        if ($event === 'charge.completed' && ($data['status'] ?? '') === 'successful') {
            return PaymentResult::paid(
                gatewayPaymentId: (string) ($data['id'] ?? ''),
                gatewayOrderId:   (string) ($data['tx_ref'] ?? ''),
                payload:          $data,
            );
        }
        return PaymentResult::failed("unhandled_flutterwave_event: {$event}", $payload);
    }

    public function verify(Order $order): PaymentResult
    {
        $txnId = $order->gateway_payment_id;
        if (!$txnId) return PaymentResult::failed('no_transaction_id');
        return $this->handleCallback(['transaction_id' => $txnId]);
    }

    // ── Recurring subscriptions ──────────────────────────────────────
    //
    // Verified: create a Payment Plan, then attach it to the first hosted
    // charge (payment_plan id in the /payments body). Flutterwave tokenizes the
    // card during that charge and auto-rebills each cycle, firing
    // charge.completed (status successful). The plan id is our join key.

    public function supportsRecurring(): bool
    {
        return true;
    }

    public function createSubscription(Order $order, string $callbackUrl): PaymentResult
    {
        $secretKey = (string) $this->cred('secret_key');
        if ($secretKey === '') return PaymentResult::failed('flutterwave_secret_key_missing');
        $email = optional($order->user)->email;
        if (!$email) return PaymentResult::failed('flutterwave_customer_email_missing');

        $plan     = $this->planInterval($order);                     // interval=day/week/month/year, count
        $interval = match ($plan['interval']) {
            'day'   => 'daily',
            'week'  => 'weekly',
            'year'  => 'yearly',
            default => 'monthly',
        };

        try {
            // 1. Payment plan
            $planRes = Http::withToken($secretKey)->timeout(self::HTTP_TIMEOUT_SECONDS)
                ->post(self::API_BASE . '/payment-plans', [
                    'name'     => optional($order->package)->pname ?: (config('app.name') . ' plan'),
                    'amount'   => (float) $order->amount,
                    'interval' => $interval,
                    'currency' => strtoupper($order->currency ?? 'NGN'),
                ]);
            $planJson = $planRes->json() ?: [];
            if (($planJson['status'] ?? '') !== 'success' || empty($planJson['data']['id'])) {
                return PaymentResult::failed('flutterwave_plan: ' . ($planJson['message'] ?? 'create_failed'));
            }
            $planId = (string) $planJson['data']['id'];

            // 2. Hosted charge with the plan attached
            $txRef = 'FLWSUB_' . $order->order_number . '_' . time();
            $payRes = Http::withToken($secretKey)->timeout(self::HTTP_TIMEOUT_SECONDS)
                ->post(self::API_BASE . '/payments', [
                    'tx_ref'       => $txRef,
                    'amount'       => (float) $order->amount,
                    'currency'     => strtoupper($order->currency ?? 'NGN'),
                    'redirect_url' => $callbackUrl,
                    'payment_plan' => $planId,
                    'customer'     => ['email' => $email, 'name' => optional($order->user)->name ?? 'Customer'],
                    'customizations' => ['title' => config('app.name'), 'description' => "Order #{$order->order_number}"],
                    'meta'         => ['order_id' => (string) $order->id, 'workspace_id' => (string) $order->workspace_id],
                ]);
            $payJson = $payRes->json() ?: [];
            if (($payJson['status'] ?? '') === 'success' && isset($payJson['data']['link'])) {
                // plan id doubles as our subscription join key for FLW.
                return PaymentResult::redirect($payJson['data']['link'], $txRef, $payJson + [
                    'gateway_subscription_id' => $planId,
                    'gateway_plan_id'         => $planId,
                ]);
            }
            return PaymentResult::failed('flutterwave_subscription: ' . ($payJson['message'] ?? 'create_failed'));
        } catch (\Throwable $e) {
            return PaymentResult::failed('flutterwave_subscription_exception: ' . $e->getMessage());
        }
    }

    public function parseSubscriptionWebhook(array $payload): ?array
    {
        $event = (string) ($payload['event'] ?? '');
        $data  = $payload['data'] ?? [];
        if (!is_array($data)) return null;

        if ($event === 'charge.completed') {
            // Only subscription rebills carry a payment_plan; one-offs don't.
            $planId = $data['payment_plan'] ?? null;
            if (!$planId) return null;
            $ok = ($data['status'] ?? '') === 'successful';
            return [
                'type'            => $ok ? 'renewed' : 'payment_failed',
                'subscription_id' => (string) $planId,
                'gateway_plan_id' => (string) $planId,
                'payment_id'      => (string) ($data['id'] ?? ''),
                'period_end'      => null,
                'order_id'        => $data['meta']['order_id'] ?? null,
            ];
        }

        if ($event === 'subscription.cancelled') {
            $planId = $data['plan']['id'] ?? ($data['plan_id'] ?? null);
            if (!$planId) return null;
            return ['type' => 'canceled', 'subscription_id' => (string) $planId, 'gateway_plan_id' => (string) $planId, 'payment_id' => null, 'period_end' => null];
        }

        return null;
    }

    public function cancelSubscription(string $gatewaySubscriptionId, array $context = []): PaymentResult
    {
        // FLW cancels by subscription record, not plan id. Look the active
        // subscription up by plan id, then deactivate it.
        $secretKey = (string) $this->cred('secret_key');
        if ($secretKey === '') return PaymentResult::failed('flutterwave_secret_key_missing');
        $planId = $context['gateway_plan_id'] ?: $gatewaySubscriptionId;
        try {
            $list = Http::withToken($secretKey)->timeout(self::HTTP_TIMEOUT_SECONDS)
                ->get(self::API_BASE . '/subscriptions', ['plan' => $planId]);
            $subs = $list->json('data') ?: [];
            $subId = $subs[0]['id'] ?? null;
            if (!$subId) return PaymentResult::failed('flutterwave_subscription_not_found');
            $r = Http::withToken($secretKey)->timeout(self::HTTP_TIMEOUT_SECONDS)
                ->put(self::API_BASE . "/subscriptions/{$subId}/cancel");
            if (($r->json('status') ?? '') !== 'success') return PaymentResult::failed('flutterwave_cancel_failed');
            return PaymentResult::paid(gatewayOrderId: (string) $subId, payload: $r->json());
        } catch (\Throwable $e) {
            return PaymentResult::failed('flutterwave_cancel_exception: ' . $e->getMessage());
        }
    }
}
