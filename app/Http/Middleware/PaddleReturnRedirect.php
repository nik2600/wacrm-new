<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Paddle hosted-checkout return catcher.
 *
 * Paddle Billing sends the buyer back to the seller's "default payment link"
 * (configured in the Paddle dashboard) with the completed transaction appended
 * as `?_ptxn=txn_…`. Our Paddle driver never gets to set a per-transaction
 * return URL for the hosted flow, so when that default link is a plain page —
 * e.g. the marketing homepage, as on this install — the buyer lands THERE and
 * the order is never confirmed: the `?_ptxn=` return never reaches our
 * /payment/callback/paddle handler.
 *
 * This catches `_ptxn` on ANY web GET and forwards it to the Paddle callback,
 * which verifies the transaction against Paddle's API and finalises the order,
 * then lands the buyer on the orders/success page. No-op for every request that
 * doesn't carry `_ptxn`. Excludes the callback/webhook routes so it can't loop
 * on itself. `_ptxn` is Paddle-specific, so no other gateway is affected.
 */
class PaddleReturnRedirect
{
    public function handle(Request $request, Closure $next): Response
    {
        $ptxn = trim((string) $request->query('_ptxn', ''));
        if ($ptxn !== ''
            && $request->isMethod('GET')
            && ! $request->is('payment/callback/*')
            && ! $request->is('payment/webhook/*')) {
            \Illuminate\Support\Facades\Log::info('[PADDLE] return caught on ' . $request->path() . ' — forwarding _ptxn to callback', [
                'ptxn' => $ptxn,
                'path' => $request->path(),
            ]);
            return redirect()->route('payment.callback', ['gateway' => 'paddle', '_ptxn' => $ptxn]);
        }

        return $next($request);
    }
}
