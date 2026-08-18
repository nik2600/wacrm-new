<?php

namespace App\Http\Controllers;

use App\Models\WaStorefront;
use App\Services\Storefront\StorefrontPaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * S2 — Razorpay webhook for storefront payment links. Razorpay POSTs the
 * raw JSON with an `X-Razorpay-Signature` header (HMAC-SHA256 of the raw
 * body with the merchant's webhook secret). We resolve the storefront from
 * the payment-link notes, verify with THAT merchant's secret, then flip the
 * order to paid. Public + CSRF-exempt; the HMAC is the auth.
 */
class StorefrontPaymentController extends Controller
{
    public function razorpayWebhook(Request $request, StorefrontPaymentService $pay): JsonResponse
    {
        $raw  = $request->getContent();
        $sig  = $request->header('X-Razorpay-Signature');
        $json = json_decode($raw, true);
        if (!is_array($json)) return response()->json(['ok' => false], 400);

        // Resolve which merchant/storefront this event belongs to (from notes).
        $entity = $json['payload']['payment_link']['entity']
            ?? $json['payload']['payment']['entity']
            ?? [];
        $sfId = (int) ($entity['notes']['storefront_id'] ?? 0);
        $sf   = $sfId ? WaStorefront::find($sfId) : null;
        if (!$sf) return response()->json(['ok' => false, 'error' => 'storefront_unknown'], 404);

        if (!$pay->verifyWebhook($sf, $raw, $sig)) {
            Log::warning('[STOREFRONT-PAY] webhook signature mismatch (sf ' . $sfId . ')');
            return response()->json(['ok' => false, 'error' => 'bad_signature'], 400);
        }

        $event = (string) ($json['event'] ?? '');
        if (in_array($event, ['payment_link.paid', 'payment.captured', 'order.paid'], true)) {
            $order = $pay->applyPaidEvent($json);
            return response()->json(['ok' => true, 'order' => $order?->id]);
        }

        // Acknowledge other events so Razorpay doesn't retry.
        return response()->json(['ok' => true, 'ignored' => $event]);
    }
}
