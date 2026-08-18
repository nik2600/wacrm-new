<?php

namespace App\Http\Controllers;

use App\Models\CreditPackage;
use App\Models\Order;
use App\Models\Package;
use App\Models\PaymentGateway;
use App\Models\Subscription;
use App\Models\User;
use App\Models\Workspace;
use App\Services\WalletService;
use App\Services\Payment\AbstractGatewayDriver;
use App\Services\Payment\PaymentGatewayManager;
use App\Services\Payment\PaymentResult;
use App\Services\Payment\SubscriptionService;
use App\Support\FormatSettings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Public pricing + checkout flow.
 *
 *   GET  /pricing                              → list active packages
 *   GET  /checkout/{package}                   → choose gateway
 *   POST /checkout/{package}                   → kick the gateway, redirect/embed
 *   GET  /payment/callback/{gateway}           → gateway returns user here
 *   POST /payment/webhook/{gateway}            → gateway pings async result
 *   GET  /account?tab=orders                   → user views their orders
 */
class CheckoutController extends Controller
{
    public function __construct(private readonly PaymentGatewayManager $manager) {}

    /**
     * Mark an offline / bank-transfer order paid + activate the plan, reusing
     * the exact same finalizeOrder() path the gateway callbacks use. Called
     * from the admin approval action. Idempotent — already-paid orders no-op.
     */
    public function markPaidManually(\App\Models\Order $order, ?int $reviewerId = null, ?string $reference = null): void
    {
        if ($order->status === 'paid') return;

        $order->forceFill([
            'reviewed_by' => $reviewerId,
            'reviewed_at' => now(),
        ])->save();

        $this->finalizeOrder($order, \App\Services\Payment\PaymentResult::paid(
            $reference ?: ('manual-' . $order->id),
            $order->gateway_order_id,
            ['manual' => true, 'reviewed_by' => $reviewerId],
        ));
    }

    /**
     * Verify a gateway payment for an order and, on success, activate the plan
     * — the SAME `driver->handleCallback() → finalizeOrder()` path the web
     * return/callback() uses, exposed for the MOBILE APP's in-app SDK flow.
     * Native Razorpay/Stripe/etc. SDKs hand the app a payment id + signature;
     * the app posts the whole payload here and the driver verifies it
     * server-side (signature check / gateway API call inside handleCallback),
     * then we fulfil. Returns the PaymentResult so the caller shapes its own
     * response. Idempotent: an already-paid order short-circuits to paid.
     *
     * Fulfilment (plan switch, trial lift, subscription capture) stays private
     * in this controller — this is the single authorised entry point for the
     * app so billing logic is never duplicated.
     */
    public function confirmOrderPayment(Order $order, array $payload): \App\Services\Payment\PaymentResult
    {
        if ($order->status === 'paid') {
            return \App\Services\Payment\PaymentResult::paid(
                (string) ($order->gateway_payment_id ?: $order->id),
                $order->gateway_order_id,
                ['already_paid' => true],
            );
        }

        $gw = $order->gateway_slug
            ? PaymentGateway::query()->where('slug', $order->gateway_slug)->first()
            : ($order->gateway_id ? PaymentGateway::find($order->gateway_id) : null);
        if (! $gw) {
            return \App\Services\Payment\PaymentResult::failed('gateway_not_found');
        }

        try {
            $driver = $this->manager->driverFromModel($gw);
            $result = $driver->handleCallback($payload);
        } catch (\Throwable $e) {
            Log::error('[CHECKOUT] app confirmOrderPayment threw', ['err' => $e->getMessage(), 'order' => $order->id]);
            $result = \App\Services\Payment\PaymentResult::failed('callback_exception: ' . $e->getMessage());
        }

        if ($result->status === 'paid') {
            $this->finalizeOrder($order, $result);
            // Capture any recurring subscription id (no-op for one-time orders),
            // mirroring the web callback so renewal webhooks can find it.
            if (! $order->credit_package_id) {
                $this->activateSubscriptionForOrder($order, $gw->slug, $result);
            }
        } else {
            $order->update([
                'status'          => 'failed',
                'failure_reason'  => $result->error,
                'gateway_payload' => array_merge((array) $order->gateway_payload, (array) $result->payload),
            ]);
        }

        return $result;
    }

    /**
     * Buyer uploads a receipt + reference for an offline / bank-transfer order.
     * Stores the proof + flips proof_submitted_at so it surfaces in the admin
     * approval queue. Does NOT activate the plan — an admin must approve.
     */
    public function submitProof(\Illuminate\Http\Request $request, int $orderId)
    {
        $order = Order::findOrFail($orderId);
        $user  = $request->user();
        // Who may attach proof: the buyer, anyone acting in the order's
        // workspace, or a platform admin. (Every other gateway redirects
        // off-site and never reaches this gate — that's why only the manual /
        // bank-transfer flow was throwing 403.)
        $canSubmit = (int) $order->user_id === (int) $user->id
            || ($order->workspace_id && (int) $order->workspace_id === (int) $user->current_workspace_id)
            || $user->isAdmin();
        if (! $canSubmit) abort(403);





        if ($order->status !== 'pending' || !in_array($order->gateway_slug, ['offline', 'bank_transfer'], true)) {
            return back()->with('error', 'Payment proof can no longer be submitted for this order.');
        }

        $request->validate([
            'proof'             => ['required', 'file', 'mimes:jpeg,jpg,png,webp,pdf', 'max:4096'],
            'payment_reference' => ['nullable', 'string', 'max:120'],
            'proof_note'        => ['nullable', 'string', 'max:1000'],
        ]);

        $path = $request->file('proof')->store('payment-proofs', media_disk());

        $order->update([
            'payment_proof_path' => $path,
            'payment_reference'  => $request->input('payment_reference'),
            'proof_note'         => $request->input('proof_note'),
            'proof_submitted_at' => now(),
        ]);

        return redirect()->route('user.account', ['tab' => 'orders'])
            ->with('status', 'Payment proof submitted. We will verify and activate your plan shortly.');
    }

    /**
     * Currencies the shopper may pay in: admin-enabled currencies that at
     * least one ACTIVE payment gateway accepts. A gateway with an empty
     * supported_currencies whitelist accepts anything → all enabled
     * currencies become available. Returns uppercase ISO codes.
     */
    private function availableCheckoutCurrencies(): array
    {
        $enabled = \App\Models\Currency::query()->where('is_active', true)
            ->orderBy('code')->pluck('code')
            ->map(fn ($c) => strtoupper((string) $c))->filter()->unique()->values()->all();
        if (empty($enabled)) {
            $enabled = [strtoupper((string) \App\Models\SystemSetting::get('default_currency', 'USD'))];
        }

        $gateways  = $this->manager->activeGateways(); // all active, unfiltered
        $acceptAny = false;
        $union     = [];
        foreach ($gateways as $g) {
            $list = $g->supported_currencies ?? [];
            if (empty($list)) { $acceptAny = true; continue; }
            foreach ($list as $c) { $union[strtoupper((string) $c)] = true; }
        }
        if ($acceptAny || empty($union)) {
            return $enabled; // some gateway takes any currency (or none restrict)
        }
        $codes = array_values(array_filter($enabled, fn ($c) => isset($union[$c])));
        return $codes ?: $enabled;
    }

    /**
     * JSON the checkout page fetches when the shopper switches currency,
     * so the price + gateways update in place WITHOUT a reload (which would
     * wipe the billing fields they already typed). Amounts come pre-formatted
     * in the chosen currency.
     */
    private function pricingJson(string $currency, float $amount, int $taxPct, $gateways)
    {
        $tax   = round($amount * $taxPct / 100, 2);
        $total = round($amount + $tax, 2);
        $fmt   = fn ($n) => \App\Support\FormatSettings::formatIn($n, $currency);
        return response()->json([
            'ok'        => true,
            'currency'  => $currency,
            'amountRaw' => $amount,
            'amountFmt' => $fmt($amount),
            'taxPct'    => $taxPct,
            'taxFmt'    => $fmt($tax),
            'totalRaw'  => $total,
            'totalFmt'  => $fmt($total),
            'gateways'  => collect($gateways)->map(fn ($g) => [
                'id'          => $g->id,
                'name'        => $g->name,
                'description' => $g->description,
                'mode'        => $g->mode,
            ])->values(),
        ]);
    }

    public function pricing(Request $request)
    {
        // Plans = full subscriptions (the grid). Add-ons = à-la-carte feature
        // packs shown in a separate section, bought on top of the active plan.
        $packages = Package::query()->plans()->where('status', 1)->orderBy('sort_order')->orderBy('plan_amount')->get();
        $addons   = Package::query()->addons()->where('status', 1)->orderBy('sort_order')->orderBy('plan_amount')->get();

        $faqs = \App\Models\PricingFaq::query()->active()->get();

        return view('pricing.index', [
            'packages' => $packages,
            'addons'   => $addons,
            'currency' => optional($request->user()?->currentWorkspace)->currency
                ?? strtoupper((string) \App\Models\SystemSetting::get('default_currency', 'USD')),
            // Everything below is admin-editable from /admin/checkout-settings.
            'yearlyEnabled'     => (bool) \App\Models\SystemSetting::get('pricing.yearly_toggle_enabled', true),
            'yearlyDiscountPct' => (int)  \App\Models\SystemSetting::get('pricing.yearly_discount_pct', 20),
            'refundEnabled'     => (bool) \App\Models\SystemSetting::get('pricing.refund_enabled', true),
            'refundDays'        => (int)  \App\Models\SystemSetting::get('pricing.refund_days', 7),
            'faqs'              => $faqs,
        ]);
    }

    public function show(Request $request, int $packageId)
    {
        $package = Package::query()->where('status', 1)->findOrFail($packageId);
        $user    = $request->user();
        if (!$user) return redirect()->route('login')->with('status', 'Log in to continue.');

        $ws        = $user->currentWorkspace;

        // Block downgrade purchases server-side (the /account/plans button is
        // also disabled). You can move UP to a pricier plan, but not buy the
        // current plan or a cheaper one online.
        // Add-ons are bought ON TOP of a plan, so the cheaper-than-current
        // downgrade guard must not apply to them.
        $curPkg = $ws?->billingPackage();
        if ($curPkg
            && $package->type !== Package::TYPE_ADDON
            && (int) $curPkg->id !== (int) $package->id
            && ! $package->isFreePlan() && ! $package->is_custom_quote
            && (float) $package->plan_amount < (float) $curPkg->plan_amount) {
            return redirect()->route('account.plans')
                ->with('error', 'Downgrades are not available online. Contact support to move to a lower plan.');
        }

        // Currency: ?currency= (when supported by an active gateway) wins,
        // else the workspace/plan/USD default. The picker only ever offers
        // currencies an active gateway accepts.
        $available = $this->availableCheckoutCurrencies();
        $requested = strtoupper((string) $request->query('currency', ''));
        $currency  = ($requested && in_array($requested, $available, true))
            ? $requested
            : strtoupper((string) ($package->currency ?: $ws?->currency ?: 'USD'));
        if (!in_array($currency, $available, true)) { array_unshift($available, $currency); }
        $gateways = $this->manager->activeGateways($currency);

        // Convert from the package's own currency into the workspace currency.
        // Use chargeableAmount() so the discounted offer price is honoured —
        // NOT the raw plan_amount (that bug charged full price at checkout).
        $amount = $package->chargeableAmount();
        if ($package->currency && strtoupper($package->currency) !== strtoupper($currency)) {
            $amount = FormatSettings::convert($amount, $package->currency, $currency);
        }

        // Yearly billing charges 12 months up front at the admin's yearly
        // discount. The pricing page advertises that rate, so checkout must
        // honour it — previously it always billed the plain monthly amount.
        $period = $this->billingPeriodFor($request, $package);
        $amount = $this->amountForPeriod((float) $amount, $period, $package);

        // Country list, tax, refund window — all admin-editable.
        $countriesRaw = \App\Models\SystemSetting::get('checkout.countries', null);
        if (is_array($countriesRaw)) {
            $countries = $countriesRaw;
        } else {
            $decoded = json_decode((string) $countriesRaw, true);
            $countries = is_array($decoded) && !empty($decoded) ? $decoded : ['India', 'United States', 'United Kingdom', 'Other'];
        }

        $taxEnabled    = (bool) \App\Models\SystemSetting::get('checkout.tax_enabled', true);
        $refundEnabled = (bool) \App\Models\SystemSetting::get('pricing.refund_enabled', true);
        $taxPct        = $taxEnabled ? (int) \App\Models\SystemSetting::get('checkout.tax_rate', 18) : 0;

        if ($request->boolean('ajax')) {
            return $this->pricingJson(strtoupper($currency), round((float) $amount, 2), $taxPct, $gateways);
        }

        return view('checkout.show', [
            'package'       => $package,
            'gateways'      => $gateways,
            'currency'      => strtoupper($currency),
            'availableCurrencies' => $available,
            'amount'        => round((float) $amount, 2),
            'period'        => $period,
            'isYearly'      => $period === 'yearly',
            'taxEnabled'    => $taxEnabled,
            'taxRate'       => $taxPct,
            'taxLabel'      => (string) \App\Models\SystemSetting::get('checkout.tax_label', 'Tax'),
            'countries'     => $countries,
            'refundEnabled' => $refundEnabled,
            'refundDays'    => (int) \App\Models\SystemSetting::get('pricing.refund_days', 7),
            // Show the "auto-renew" toggle only when this purchase COULD recur:
            // recurring globally on, a paid monthly plan (yearly / free / lifetime
            // / custom never recur). Mirrors shouldRecur() minus the per-gateway
            // check — if the chosen gateway can't recur it's one-time regardless,
            // so the toggle is harmless there.
            'recurringEligible' => (bool) \App\Models\SystemSetting::get('pricing.recurring_enabled', true)
                && $period !== 'yearly'
                && ! $package->free && ! $package->lifetime && ! $package->is_custom_quote
                && (float) $package->plan_amount > 0,
        ]);
    }

    /**
     * AJAX endpoint the checkout page calls when the user types a
     * coupon code + clicks "Apply". Returns the resolved discount or
     * a human-readable rejection. The real coupon application still
     * happens server-side during `process()` — this is preview only.
     */
    public function applyCoupon(Request $request, int $packageId)
    {
        $package = Package::query()->where('status', 1)->findOrFail($packageId);
        $user    = $request->user();
        if (!$user) return response()->json(['ok' => false, 'message' => 'Log in first.'], 401);

        $data = $request->validate([
            'code'   => ['required', 'string', 'max:64'],
            'amount' => ['required', 'numeric', 'min:0'],
        ]);

        // Currency for the preview's currency-lock check — mirror what
        // process() will bill in (workspace currency, then plan, then USD).
        $previewCurrency = strtoupper((string) (optional($user->currentWorkspace)->currency ?: $package->currency ?: 'USD'));
        $result = \App\Models\Coupon::resolve($data['code'], $package, (float) $data['amount'], $user, $previewCurrency);

        return response()->json([
            'ok'       => (bool) $result['ok'],
            'message'  => $result['message'],
            'discount' => $result['discount'],
            'code'     => $result['coupon']?->code,
        ]);
    }

    public function process(Request $request, int $packageId)
    {
        $package = Package::query()->where('status', 1)->findOrFail($packageId);
        $user    = $request->user();
        if (!$user) abort(401);
        $ws = $user->currentWorkspace;
        if (!$ws) return back()->with('error', 'No workspace context.');

        $data = $request->validate([
            'gateway_id'      => ['required', 'integer', 'exists:payment_gateways,id'],
            'currency'        => ['nullable', 'string', 'max:10'],
            // End-to-end billing snapshot.
            'customer_name'   => ['nullable', 'string', 'max:191'],
            'customer_email'  => ['nullable', 'email', 'max:191'],
            'billing_company' => ['nullable', 'string', 'max:191'],
            'billing_address' => ['nullable', 'string', 'max:255'],
            'billing_city'    => ['nullable', 'string', 'max:120'],
            'billing_postal'  => ['nullable', 'string', 'max:32'],
            'billing_country' => ['nullable', 'string', 'max:80'],
            'billing_tax_id'  => ['nullable', 'string', 'max:64'],
            'coupon'          => ['nullable', 'string', 'max:64'],
            'period'          => ['nullable', 'string', 'max:16'],
            // Per-purchase auto-renew choice. Unchecked box submits nothing, so
            // absence = OFF; anything truthy = ON. Defaulting the *absence* case
            // to OFF is safe because the checkout form always renders the box
            // (checked by default) whenever the plan is recurring-eligible — see
            // show()'s $recurringEligible. Non-form callers that omit it get the
            // one-time path, which is the safer default for silent integrations.
            'auto_renew'      => ['nullable'],
        ]);
        $autoRenew = $request->boolean('auto_renew');

        $gateway = PaymentGateway::findOrFail($data['gateway_id']);
        if (!$gateway->is_active) return back()->with('error', 'That gateway is not currently available.');
        // Charge in the PACKAGE's own currency (what the admin priced it in) —
        // an IDR plan is billed in IDR, never converted to the workspace/USD
        // currency. Only an explicit ?currency= (gateway-supported) overrides.
        $currency = strtoupper($data['currency'] ?: ($package->currency ?: $ws->currency ?: 'USD'));
        if (!$gateway->acceptsCurrency($currency)) return back()->with('error', $gateway->name . ' does not support ' . $currency . '.');

        // Convert package price into the order's currency. chargeableAmount()
        // honours the discounted offer price — the raw plan_amount overcharged.
        $amount = $package->chargeableAmount();
        if ($package->currency && strtoupper($package->currency) !== $currency) {
            $amount = FormatSettings::convert($amount, $package->currency, $currency);
        }
        // Yearly = 12 months up front at the yearly discount. Recompute the
        // authoritative amount here — never trust the client — using the same
        // maths as show() and the pricing page.
        $period        = $this->billingPeriodFor($request, $package);
        $amount        = $this->amountForPeriod((float) $amount, $period, $package);
        $baseAmountUsd = round((float) FormatSettings::convert($amount, $currency, 'USD'), 2);

        // ── Coupon — re-validate server-side. The AJAX preview is
        //   advisory only; this is the binding check.
        $coupon       = null;
        $discount     = 0.0;
        if (!empty($data['coupon'])) {
            $resolved = \App\Models\Coupon::resolve($data['coupon'], $package, $amount, $user, $currency);
            if ($resolved['ok']) {
                $coupon   = $resolved['coupon'];
                $discount = (float) $resolved['discount'];
            } else {
                return back()->withInput()->with('error', $resolved['message']);
            }
        }

        // ── Tax — admin's on/off switch + rate from SystemSetting.
        $taxEnabled = (bool) \App\Models\SystemSetting::get('checkout.tax_enabled', true);
        $taxRate    = $taxEnabled ? (float) \App\Models\SystemSetting::get('checkout.tax_rate', 0) : 0.0;
        $taxBase    = max(0, $amount - $discount);
        $taxAmount  = round($taxBase * $taxRate / 100, 2);
        $total      = round($taxBase + $taxAmount, 2);

        // Gateway drivers charge `$order->amount` — that MUST be the
        // final billed total (post-discount + tax). We persist the
        // pre-discount subtotal + breakdown so accounting / receipts
        // can reconstruct the math, but the wire-transferred amount
        // is `$total`.
        $totalForUsdConvert = round($total > 0 ? $total : $amount, 2);
        $baseAmountUsdTotal = round((float) FormatSettings::convert($totalForUsdConvert, $currency, 'USD'), 2);

        $order = Order::create([
            'order_number'    => Order::generateOrderNumber(),
            'workspace_id'    => $ws->id,
            'user_id'         => $user->id,
            'package_id'      => $package->id,
            'billing_period'  => $period,
            'auto_renew'      => $autoRenew,
            'gateway_id'      => $gateway->id,
            'gateway_slug'    => $gateway->slug,
            'currency'        => $currency,
            'amount'          => $total,          // what the gateway charges
            'discount_amount' => $discount,
            'tax_rate'        => $taxRate,
            'tax_amount'      => $taxAmount,
            'total_amount'    => $total,
            'base_amount_usd' => $baseAmountUsdTotal,
            'status'          => 'pending',
            'coupon_id'       => $coupon?->id,
            'coupon_code'     => $coupon?->code,
            'customer_name'   => $data['customer_name']   ?? $user->name,
            'customer_email'  => $data['customer_email']  ?? $user->email,
            'billing_company' => $data['billing_company'] ?? $ws->name,
            'billing_address' => $data['billing_address'] ?? null,
            'billing_city'    => $data['billing_city']    ?? null,
            'billing_postal'  => $data['billing_postal']  ?? null,
            'billing_country' => $data['billing_country'] ?? null,
            'billing_tax_id'  => $data['billing_tax_id']  ?? null,
        ]);

        $callbackUrl = route('payment.callback', ['gateway' => $gateway->slug]);
        $driver = $this->manager->driverFromModel($gateway);

        // Auto-renewing plan? Route to the subscription flow when the gateway
        // supports it, the admin has it enabled, and this is a timed paid plan
        // (free / lifetime / custom-quote never recur). Every other case falls
        // through to the unchanged one-time charge.
        // A yearly plan is a one-time 12-month purchase — never enrol it in a
        // monthly auto-renew subscription (that would re-bill a year's price
        // every month). Yearly always takes the plain one-time charge path.
        // Customer opted OUT of auto-renew → force the one-time charge path even
        // when the plan/gateway would otherwise recur. This is the whole point of
        // the toggle: buy one month, don't get re-billed next cycle.
        $recurring = $autoRenew && $period !== 'yearly' && $this->shouldRecur($package, $driver);
        try {
            $result = $recurring
                ? $driver->createSubscription($order, $callbackUrl)
                : $driver->initiate($order, $callbackUrl);
        } catch (\Throwable $e) {
            Log::error('[CHECKOUT] driver initiate threw', ['err' => $e->getMessage(), 'order' => $order->id, 'recurring' => $recurring]);
            $order->update(['status' => 'failed', 'failure_reason' => $e->getMessage()]);
            return back()->with('error', 'Payment provider error. Try again or pick a different gateway.');
        }

        if ($result->gatewayOrderId) {
            $order->update(['gateway_order_id' => $result->gatewayOrderId, 'gateway_payload' => $result->payload]);
        }

        if ($recurring && $result->status !== 'failed') {
            // Track the pending subscription so the callback + renewal webhooks
            // can find it. Razorpay/PayPal already know the gateway sub id here;
            // Stripe fills it in when the first charge confirms.
            try {
                $rp = is_array($result->payload) ? $result->payload : [];
                app(SubscriptionService::class)->recordPending($order, $gateway->slug, [
                    'gateway_subscription_id' => $this->extractSubscriptionId($result),
                    'gateway_plan_id'         => $rp['gateway_plan_id'] ?? null,
                    'gateway_customer_id'     => $rp['gateway_customer_id'] ?? ($rp['customer'] ?? null),
                ]);
            } catch (\Throwable $e) {
                Log::error('[CHECKOUT] recordPending subscription failed', ['err' => $e->getMessage(), 'order' => $order->id]);
            }
        }

        if ($result->status === 'paid') {
            $this->finalizeOrder($order, $result);
            if ($recurring) $this->activateSubscriptionForOrder($order, $gateway->slug, $result);
            return redirect()->route('user.account', ['tab' => 'orders'])->with('success', 'Payment received. Your plan is now active.');
        }
        if ($result->status === 'failed') {
            $order->update(['status' => 'failed', 'failure_reason' => $result->error, 'gateway_payload' => $result->payload]);
            return back()->with('error', 'Payment failed: ' . ($result->error ?: 'unknown'));
        }

        // Pending — either redirect off-site or render an inline form.
        session(['checkout_order_id' => $order->id]); // for the callback lookup
        if ($result->redirectUrl) return redirect()->away($result->redirectUrl);
        if ($result->html) return response($result->html);
        return back()->with('error', 'Payment provider did not return a usable response.');
    }

    /**
     * Credit-package checkout — renders the SAME gateway-driven checkout the
     * plans use (reusing checkout.show via overrides), so a wallet top-up goes
     * through Razorpay/Stripe/etc. instead of the old fake self-confirm path.
     */
    public function creditShow(Request $request, string $slug)
    {
        $package = CreditPackage::where('slug', $slug)->where('is_active', true)->firstOrFail();
        $user    = $request->user();
        if (!$user) return redirect()->route('login')->with('status', 'Log in to continue.');
        $ws = $user->currentWorkspace;

        $available = $this->availableCheckoutCurrencies();
        $baseCur   = strtoupper($package->currency_code ?: ($ws?->currency ?: 'USD'));
        $requested = strtoupper((string) $request->query('currency', ''));
        $currency  = ($requested && in_array($requested, $available, true)) ? $requested : $baseCur;
        if (!in_array($currency, $available, true)) { array_unshift($available, $currency); }
        $gateways  = $this->manager->activeGateways($currency);
        $amount    = round($package->price_minor / 100, 2);
        if ($baseCur !== $currency) {
            $amount = round((float) FormatSettings::convert($amount, $baseCur, $currency), 2);
        }

        $countriesRaw = \App\Models\SystemSetting::get('checkout.countries', null);
        if (is_array($countriesRaw)) {
            $countries = $countriesRaw;
        } else {
            $decoded   = json_decode((string) $countriesRaw, true);
            $countries = is_array($decoded) && !empty($decoded) ? $decoded : ['India', 'United States', 'United Kingdom', 'Other'];
        }
        $taxEnabled = (bool) \App\Models\SystemSetting::get('checkout.tax_enabled', true);
        $taxPct     = $taxEnabled ? (int) \App\Models\SystemSetting::get('checkout.tax_rate', 18) : 0;

        if ($request->boolean('ajax')) {
            return $this->pricingJson($currency, round((float) $amount, 2), $taxPct, $gateways);
        }

        return view('checkout.show', [
            'package'       => $package,
            'gateways'      => $gateways,
            'currency'      => $currency,
            'availableCurrencies' => $available,
            'amount'        => $amount,
            'taxEnabled'    => $taxEnabled,
            'taxRate'       => $taxPct,
            'taxLabel'      => (string) \App\Models\SystemSetting::get('checkout.tax_label', 'Tax'),
            'countries'     => $countries,
            'refundEnabled' => (bool) \App\Models\SystemSetting::get('pricing.refund_enabled', true),
            'refundDays'    => (int) \App\Models\SystemSetting::get('pricing.refund_days', 7),
            // Overrides that re-point the shared blade at the credit flow.
            'processUrl'    => route('user.checkout.credits.process', $package->slug),
            'couponUrl'     => '#',
            'allowCoupon'   => false,
            'itemName'      => $package->name,
            'itemSub'       => number_format($package->credits) . ' message credits',
            'changeUrl'     => url('/account?tab=wallet'),
            // Wallet top-ups are always a one-time charge — no auto-renew toggle.
            'recurringEligible' => false,
        ]);
    }

    /** Kick a credit-package purchase through the chosen gateway (always one-time). */
    public function creditProcess(Request $request, string $slug)
    {
        $package = CreditPackage::where('slug', $slug)->where('is_active', true)->firstOrFail();
        $user    = $request->user();
        if (!$user) abort(401);
        $ws = $user->currentWorkspace;
        if (!$ws) return back()->with('error', 'No workspace context.');

        $data = $request->validate([
            'gateway_id'      => ['required', 'integer', 'exists:payment_gateways,id'],
            'currency'        => ['nullable', 'string', 'max:10'],
            'customer_name'   => ['nullable', 'string', 'max:191'],
            'customer_email'  => ['nullable', 'email', 'max:191'],
            'billing_company' => ['nullable', 'string', 'max:191'],
            'billing_address' => ['nullable', 'string', 'max:255'],
            'billing_city'    => ['nullable', 'string', 'max:120'],
            'billing_postal'  => ['nullable', 'string', 'max:32'],
            'billing_country' => ['nullable', 'string', 'max:80'],
            'billing_tax_id'  => ['nullable', 'string', 'max:64'],
        ]);

        $gateway = PaymentGateway::findOrFail($data['gateway_id']);
        if (!$gateway->is_active) return back()->with('error', 'That gateway is not currently available.');
        // Honour the shopper's chosen currency (from the checkout dropdown);
        // fall back to the package's own currency. The amount is converted
        // from the package's base currency into the billed currency.
        $baseCur  = strtoupper($package->currency_code ?: ($ws->currency ?: 'USD'));
        $currency = strtoupper((string) ($data['currency'] ?? '')) ?: $baseCur;
        if (!$gateway->acceptsCurrency($currency)) return back()->with('error', $gateway->name . ' does not support ' . $currency . '.');

        $amount     = round($package->price_minor / 100, 2);
        if ($baseCur !== $currency) {
            $amount = round((float) FormatSettings::convert($amount, $baseCur, $currency), 2);
        }
        $taxEnabled = (bool) \App\Models\SystemSetting::get('checkout.tax_enabled', true);
        $taxRate    = $taxEnabled ? (float) \App\Models\SystemSetting::get('checkout.tax_rate', 0) : 0.0;
        $taxAmount  = round($amount * $taxRate / 100, 2);
        $total      = round($amount + $taxAmount, 2);
        $baseUsd    = round((float) FormatSettings::convert($total, $currency, 'USD'), 2);

        $order = Order::create([
            'order_number'      => Order::generateOrderNumber(),
            'workspace_id'      => $ws->id,
            'user_id'           => $user->id,
            'package_id'        => null,
            'credit_package_id' => $package->id,
            'gateway_id'        => $gateway->id,
            'gateway_slug'      => $gateway->slug,
            'currency'          => $currency,
            'amount'            => $total,
            'tax_rate'          => $taxRate,
            'tax_amount'        => $taxAmount,
            'total_amount'      => $total,
            'base_amount_usd'   => $baseUsd,
            'status'            => 'pending',
            'customer_name'     => $data['customer_name']   ?? $user->name,
            'customer_email'    => $data['customer_email']  ?? $user->email,
            'billing_company'   => $data['billing_company'] ?? $ws->name,
            'billing_address'   => $data['billing_address'] ?? null,
            'billing_city'      => $data['billing_city']    ?? null,
            'billing_postal'    => $data['billing_postal']  ?? null,
            'billing_country'   => $data['billing_country'] ?? null,
            'billing_tax_id'    => $data['billing_tax_id']  ?? null,
        ]);

        $callbackUrl = route('payment.callback', ['gateway' => $gateway->slug]);
        $driver = $this->manager->driverFromModel($gateway);
        try {
            // Credit top-ups are always one-time — never a subscription.
            $result = $driver->initiate($order, $callbackUrl);
        } catch (\Throwable $e) {
            Log::error('[CHECKOUT] credit initiate threw', ['err' => $e->getMessage(), 'order' => $order->id]);
            $order->update(['status' => 'failed', 'failure_reason' => $e->getMessage()]);
            return back()->with('error', 'Payment provider error. Try again or pick a different gateway.');
        }

        if ($result->gatewayOrderId) {
            $order->update(['gateway_order_id' => $result->gatewayOrderId, 'gateway_payload' => $result->payload]);
        }
        if ($result->status === 'paid') {
            $this->finalizeOrder($order, $result);
            return redirect()->route('user.account', ['tab' => 'wallet'])->with('success', 'Payment received. Credits added to your wallet.');
        }
        if ($result->status === 'failed') {
            $order->update(['status' => 'failed', 'failure_reason' => $result->error, 'gateway_payload' => $result->payload]);
            return back()->with('error', 'Payment failed: ' . ($result->error ?: 'unknown'));
        }

        session(['checkout_order_id' => $order->id]);
        if ($result->redirectUrl) return redirect()->away($result->redirectUrl);
        if ($result->html) return response($result->html);
        return back()->with('error', 'Payment provider did not return a usable response.');
    }

    public function callback(Request $request, string $gateway)
    {
        $gw = PaymentGateway::query()->where('slug', $gateway)->firstOrFail();
        $driver = $this->manager->driverFromModel($gw);
        $payload = array_merge($request->query() ?: [], $request->post() ?: []);

        // Resolve the order — prefer gateway_order_id, fall back to session.
        // Subscription returns carry a subscription id (Razorpay sub_…, PayPal
        // I-…) instead of an order/token, so include those in the hint.
        $order = null;
        $hint = $payload['session_id']
            ?? $payload['razorpay_order_id']
            ?? $payload['razorpay_subscription_id']
            ?? $payload['subscription_id']
            ?? $payload['token']
            ?? $payload['txnid']            // PayU returns our txn id (= gateway_order_id)
            ?? $payload['merchantTransactionId'] // PhonePe
            ?? $payload['merchantOrderId']  // Duitku (= our gateway_order_id)
            ?? $payload['_ptxn']            // Paddle txn id (= gateway_order_id); works session-less
            ?? null;
        if ($hint) $order = Order::where('gateway_order_id', $hint)->first();
        // PayU echoes our OWN order id back in udf2. This is the most reliable
        // match and — crucially — works even though PayU posts the return as a
        // cross-site POST, which drops the Laravel session cookie (so the
        // session fallback below is null and we'd otherwise show "Order not
        // found" on a perfectly successful payment).
        if (!$order && isset($payload['udf2']) && ctype_digit((string) $payload['udf2'])) {
            $order = Order::find((int) $payload['udf2']);
        }
        if (!$order && session('checkout_order_id')) $order = Order::find(session('checkout_order_id'));
        if (!$order) return redirect()->route('account.plans')->with('error', 'Order not found.');

        // If a pending subscription was opened for this order, let the driver
        // run its recurring return path (some gateways create the subscription
        // server-side here from a card collected on our page / a mandate).
        $pendingSub = Subscription::query()
            ->where('gateway', $gw->slug)
            ->where('meta->order_id', (string) $order->id)
            ->whereIn('status', [Subscription::STATUS_PENDING, Subscription::STATUS_ACTIVE])
            ->latest('id')->first();

        try {
            $result = $pendingSub
                ? $driver->handleSubscriptionCallback($payload, $order)
                : $driver->handleCallback($payload);
        } catch (\Throwable $e) {
            Log::error('[CHECKOUT] callback threw', ['err' => $e->getMessage(), 'order' => $order->id]);
            $result = \App\Services\Payment\PaymentResult::failed('callback_exception: ' . $e->getMessage());
        }

        if ($result->status === 'paid') {
            $this->finalizeOrder($order, $result);
            // Credit-package top-up → land on the wallet with a credits message.
            if ($order->credit_package_id) {
                return redirect()->route('user.account', ['tab' => 'wallet'])->with('success', 'Payment received. Credits added to your wallet.');
            }
            // If this checkout opened a recurring subscription, capture the
            // gateway subscription id now so renewal webhooks can find it.
            $this->activateSubscriptionForOrder($order, $gw->slug, $result);
            return redirect()->route('user.account', ['tab' => 'orders'])->with('success', 'Payment received. Your plan is now active.');
        }
        $order->update([
            'status'          => 'failed',
            'failure_reason'  => $result->error,
            'gateway_payload' => array_merge((array) $order->gateway_payload, (array) $result->payload),
        ]);
        return redirect()->route('user.account', ['tab' => 'orders'])->with('error', 'Payment did not complete: ' . ($result->error ?: 'unknown'));
    }

    /**
     * Paddle built-in checkout page. `initiate()` redirects the buyer here after
     * creating the transaction (instead of Paddle's dashboard "default payment
     * link", which is often just the homepage where no checkout renders). This
     * page loads Paddle.js and opens the overlay for THIS transaction, so the
     * customer actually pays. On success Paddle sends them to our callback, which
     * verifies the transaction and finalises the order.
     */
    public function paddleCheckout(Request $request, int $order)
    {
        $orderModel = Order::find($order);
        if (!$orderModel) return redirect()->route('account.plans')->with('error', 'Order not found.');
        if ($orderModel->status === 'paid') {
            return redirect()->route('user.account', ['tab' => 'orders'])->with('success', 'This order is already paid.');
        }

        $gw = $orderModel->gateway_slug
            ? PaymentGateway::query()->where('slug', $orderModel->gateway_slug)->first()
            : ($orderModel->gateway_id ? PaymentGateway::find($orderModel->gateway_id) : null);
        $gw = $gw ?: PaymentGateway::query()->where('slug', 'paddle')->first();
        if (!$gw) return redirect()->route('account.plans')->with('error', 'Paddle is not configured.');

        $driver = $this->manager->driverFromModel($gw);
        if (!$driver instanceof \App\Services\Payment\Drivers\PaddleDriver) {
            return redirect()->route('account.plans')->with('error', 'This order is not a Paddle payment.');
        }

        // Transaction id — from the URL, else the one stored at initiate.
        $txnId = trim((string) $request->query('txn', '')) ?: (string) $orderModel->gateway_order_id;
        $clientToken = $driver->clientToken();
        if ($txnId === '' || $clientToken === '') {
            Log::warning('[PADDLE] checkout page missing txn/token', [
                'order' => $orderModel->order_number, 'has_txn' => $txnId !== '', 'has_token' => $clientToken !== '',
            ]);
            return redirect()->route('user.account', ['tab' => 'orders'])
                ->with('error', 'Paddle checkout is not fully configured (client-side token missing). Contact support.');
        }

        // Keep the callback able to resolve the order even in incognito.
        session(['checkout_order_id' => $orderModel->id]);

        // On successful payment Paddle returns the buyer here — our callback
        // verifies the txn and completes the order.
        $successUrl = route('payment.callback', ['gateway' => 'paddle', '_ptxn' => $txnId]);

        return view('payment.paddle-checkout', [
            'txnId'       => $txnId,
            'clientToken' => $clientToken,
            'environment' => $driver->environment(), // 'sandbox' | 'production'
            'successUrl'  => $successUrl,
            'cancelUrl'   => route('user.account', ['tab' => 'orders']),
            'orderNumber' => $orderModel->order_number,
        ]);
    }

    public function webhook(Request $request, string $gateway)
    {
        $gw = PaymentGateway::query()->where('slug', $gateway)->first();
        if (!$gw) return response()->json(['ok' => false], 404);
        $driver = $this->manager->driverFromModel($gw);

        // Fail CLOSED when this gateway can take live payments but has no
        // webhook secret configured. Most drivers' verifyWebhookSignature()
        // returns true when their (optional) signing secret is blank, so an
        // unconfigured gateway would accept ANY forged "paid" webhook and
        // provision a plan / wallet credit for free. Require the secret.
        if ($this->webhookSecretMissing($gw)) {
            Log::warning('[CHECKOUT] webhook rejected — gateway has no webhook secret configured; cannot verify authenticity', ['gateway' => $gw->slug]);
            return response()->json(['ok' => false, 'error' => 'webhook_secret_not_configured'], 403);
        }

        $rawBody = (string) $request->getContent();
        // Pass through whichever signature header THIS gateway sends. Each
        // driver's verifyWebhookSignature() reads the one it expects; the
        // others are simply null for it. Without the per-gateway headers
        // below, drivers like Paystack/Coinbase/Square/Xendit/Flutterwave
        // received null and silently skipped verification. (header() is
        // case-insensitive.)
        $sigHeader = $request->header('Stripe-Signature')
            ?? $request->header('X-Razorpay-Signature')
            ?? $request->header('Paypal-Transmission-Sig')
            ?? $request->header('x-paystack-signature')           // Paystack
            ?? $request->header('X-CC-Webhook-Signature')         // Coinbase Commerce
            ?? $request->header('x-callback-token')               // Xendit
            ?? $request->header('x-square-hmacsha256-signature')  // Square
            ?? $request->header('verif-hash');                    // Flutterwave

        if (!$driver->verifyWebhookSignature($rawBody, $sigHeader)) {
            return response()->json(['ok' => false, 'error' => 'signature_mismatch'], 403);
        }

        $payload = $request->all();

        // Subscription lifecycle first: renewals/cancels/failures extend or
        // wind down the workspace plan. The gateway is the clock — there is no
        // Laravel scheduler, so these webhooks ARE the renewal mechanism.
        $evt = $driver->parseSubscriptionWebhook($payload);
        if ($evt) {
            try {
                $this->handleSubscriptionEvent($gw, $evt);
            } catch (\Throwable $e) {
                Log::error('[CHECKOUT] subscription webhook failed', ['err' => $e->getMessage(), 'gateway' => $gw->slug, 'evt' => $evt['type'] ?? '?']);
            }
            return response()->json(['ok' => true]);
        }

        $result = $driver->handleWebhook($payload);

        // Best-effort match: webhook payloads carry a gateway order id. Include
        // the flat keys form-POST gateways use (Duitku merchantOrderId, Midtrans
        // order_id) — without these their async callback can't find the order and
        // the plan never activates off the webhook.
        $hint = $payload['data']['object']['id']
            ?? $payload['payment']['order_id']
            ?? $payload['resource']['id']
            ?? $payload['merchantOrderId']   // Duitku
            ?? $payload['order_id']          // Midtrans + generic flat form-POST
            ?? null;
        $order = $hint ? Order::where('gateway_order_id', $hint)->first() : null;
        if ($order && $result->status === 'paid' && $order->status !== 'paid') {
            $this->finalizeOrder($order, $result);
        }
        return response()->json(['ok' => true]);
    }

    /**
     * True when strict verification is on (default) AND this gateway declares a
     * webhook-signing credential that has been left blank — i.e. its webhook
     * cannot be authenticated and must be rejected. Admins who deliberately run
     * a secret-less gateway can opt out via the `payment.require_webhook_secret`
     * setting (defaults to the secure ON).
     */
    private function webhookSecretMissing(PaymentGateway $gw): bool
    {
        if (! (bool) \App\Models\SystemSetting::get('payment.require_webhook_secret', true)) {
            return false;
        }
        return $this->webhookSecretState($gw) === false;
    }

    /**
     * Introspect a gateway's declared credential fields for a webhook-signing
     * secret (matched by key/label/hint mentioning webhook / IPN / signing /
     * signature-key). Returns:
     *   true  — such a field exists AND is populated (webhook is verifiable)
     *   false — such a field exists but every one is blank (NOT verifiable)
     *   null  — the driver declares no webhook-signing field (nothing to require;
     *           these gateways verify out-of-band or via a required API secret)
     */
    private function webhookSecretState(PaymentGateway $gw): ?bool
    {
        $fields = $this->manager->credentialFieldsFor($gw->slug);
        $secretKeys = [];
        foreach ($fields as $key => $def) {
            $hay = strtolower(((string) $key) . ' ' . ((string) ($def['label'] ?? '')) . ' ' . ((string) ($def['hint'] ?? '')));
            if (str_contains($hay, 'webhook')
                || str_contains($hay, 'ipn')
                || str_contains($hay, 'signing')
                || (str_contains($hay, 'signature') && str_contains($hay, 'key'))) {
                $secretKeys[] = $key;
            }
        }
        if (empty($secretKeys)) return null;
        foreach ($secretKeys as $k) {
            if (trim((string) $gw->getCredential($k, '')) !== '') return true;
        }
        return false;
    }

    /**
     * Should this purchase open an auto-renewing subscription instead of a
     * one-time charge? Only when the admin enabled recurring, the chosen
     * gateway supports it, and the plan is a timed paid plan (free / lifetime /
     * custom-quote / zero-amount never recur).
     */
    /**
     * Resolve the billing cycle the buyer picked on the pricing page.
     * 'yearly' only applies when the admin's yearly toggle is enabled AND
     * the package is a timed paid plan — free / lifetime / custom-quote /
     * add-ons always bill at their own one-time price. Everything else is
     * 'monthly'. Reads from query (GET show) or body (POST process).
     */
    private function billingPeriodFor(Request $request, Package $package): string
    {
        // Addons, free, lifetime and custom-quote plans are never billed on a
        // yearly cycle. Checked FIRST — an earlier version of this fix put the
        // native-yearly check above these and a yearly-priced ADDON slipped
        // past every one of them.
        if ($package->type === Package::TYPE_ADDON
            || $package->free || $package->lifetime
            || $package->is_custom_quote || $package->isFreePlan()) {
            return 'monthly';
        }

        // A package the admin configured in YEARS is already a yearly plan —
        // its plan_amount is the yearly price. It must be treated as yearly
        // even when no ?period=yearly reached us, otherwise a dropped query
        // string (a bookmark, a reload, a link from /account/plans) silently
        // bills it as monthly. That is the reported bug.
        //
        // Deliberately NOT gated on pricing.yearly_toggle_enabled: that switch
        // governs whether buyers may CONVERT a monthly plan to annual. A plan
        // authored as yearly is yearly whether or not that UI is offered.
        if ($package->isNativelyYearly()) {
            return 'yearly';
        }

        if (strtolower((string) $request->input('period', 'monthly')) !== 'yearly') {
            return 'monthly';
        }
        if (! (bool) \App\Models\SystemSetting::get('pricing.yearly_toggle_enabled', true)) {
            return 'monthly';
        }
        return 'yearly';
    }

    /**
     * Scale a per-month amount to the chosen billing period. Yearly = 12
     * months at the admin's yearly discount — mirrors the pricing page maths
     * ($amount * 12 * (1 - pct/100)) EXACTLY so what's shown is what's billed.
     * Monthly returns the amount unchanged.
     */
    private function amountForPeriod(float $amount, string $period, ?Package $package = null): float
    {
        if ($period !== 'yearly') {
            return round($amount, 2);
        }

        // CRITICAL: only multiply a price that is quoted PER MONTH.
        //
        // When plan_unit is years the admin already entered the yearly figure,
        // so x12 would bill roughly 9.6x the intended price (12 x 0.8). The
        // global yearly discount belongs to the monthly->yearly conversion, not
        // to a plan that was authored as yearly in the first place.
        if ($package && $package->isNativelyYearly()) {
            return round($amount, 2);
        }

        $pct = (int) \App\Models\SystemSetting::get('pricing.yearly_discount_pct', 20);
        return round($amount * 12 * (1 - $pct / 100), 2);
    }

    /**
     * Whether this purchase should be enrolled as an auto-renewing subscription.
     *
     * This is the PLATFORM/plan/gateway eligibility only. The CUSTOMER'S per-
     * purchase choice (the checkout "Auto-renew" toggle → order.auto_renew) is
     * applied by the caller in process(): `$autoRenew && shouldRecur(...)`. So a
     * customer can always opt OUT of an otherwise-eligible recurring plan.
     */
    private function shouldRecur(Package $package, AbstractGatewayDriver $driver): bool
    {
        if (! (bool) \App\Models\SystemSetting::get('pricing.recurring_enabled', true)) {
            return false;
        }
        if (! $driver->supportsRecurring()) {
            return false;
        }
        if ($package->free || $package->lifetime || $package->is_custom_quote) {
            return false;
        }
        return (float) $package->plan_amount > 0;
    }

    /** Pull the gateway's subscription id out of a PaymentResult, if present. */
    private function extractSubscriptionId(PaymentResult $result): ?string
    {
        $p = is_array($result->payload) ? $result->payload : [];
        // A driver may hand us the id outright (Flutterwave/Paystack use the
        // plan code as the join key and set this at createSubscription time).
        if (! empty($p['gateway_subscription_id'])) {
            return (string) $p['gateway_subscription_id'];
        }
        // Stripe Checkout: session.subscription is null until the charge
        // completes, so this stays null at initiate (filled at callback).
        if (! empty($p['subscription'])) {
            return (string) $p['subscription'];
        }
        // Razorpay (sub_…) / PayPal (I-…) return the subscription object now.
        $id = (string) ($p['id'] ?? '');
        if (str_starts_with($id, 'sub_') || str_starts_with($id, 'I-')) {
            return $id;
        }
        if (! empty($p['is_subscription']) && $result->gatewayOrderId) {
            return (string) $result->gatewayOrderId;
        }
        return null;
    }

    /**
     * Mark the pending Subscription row for this order active once the first
     * charge confirms, storing the gateway subscription id + customer + period
     * end so renewal webhooks can match it.
     */
    private function activateSubscriptionForOrder(Order $order, string $slug, PaymentResult $result): void
    {
        $sub = Subscription::query()
            ->where('gateway', $slug)
            ->where('meta->order_id', (string) $order->id)
            ->whereIn('status', [Subscription::STATUS_PENDING, Subscription::STATUS_ACTIVE])
            ->latest('id')->first();
        if (! $sub) {
            return; // not a subscription checkout
        }

        $p     = is_array($result->payload) ? $result->payload : [];
        $subId = $p['subscription']                                  // Stripe
            ?? ((($p['is_subscription'] ?? false) && $result->gatewayOrderId) ? $result->gatewayOrderId : $sub->gateway_subscription_id);

        try {
            app(SubscriptionService::class)->activate($sub, [
                'gateway_subscription_id' => $subId,
                'gateway_customer_id'     => $p['customer'] ?? null,
                'current_period_end'      => $p['current_period_end'] ?? ($p['billing_info']['next_billing_time'] ?? null),
            ]);
        } catch (\Throwable $e) {
            Log::error('[CHECKOUT] subscription activate failed', ['err' => $e->getMessage(), 'order' => $order->id]);
        }
    }

    /**
     * Apply a decoded subscription webhook event (created / renewed / canceled
     * / payment_failed) to the local Subscription + workspace plan window.
     */
    private function handleSubscriptionEvent(PaymentGateway $gw, array $evt): void
    {
        $svc = app(SubscriptionService::class);
        $sub = Subscription::findForGateway($gw->slug, $evt['subscription_id'] ?? null);

        // First event can arrive before the callback stored the gateway sub id.
        // Recover by matching the pending row via the order id the gateway echoed.
        if (! $sub && ! empty($evt['order_id'])) {
            $sub = Subscription::query()
                ->where('gateway', $gw->slug)
                ->where('meta->order_id', (string) $evt['order_id'])
                ->latest('id')->first();
        }

        // Some gateways (Paystack, Flutterwave) key the first webhook by the
        // plan code they minted, not an order id — match on gateway_plan_id.
        if (! $sub && ! empty($evt['gateway_plan_id'])) {
            $sub = Subscription::query()
                ->where('gateway', $gw->slug)
                ->where('gateway_plan_id', (string) $evt['gateway_plan_id'])
                ->latest('id')->first();
        }

        if ($sub && empty($sub->gateway_subscription_id) && ! empty($evt['subscription_id'])) {
            $sub->gateway_subscription_id = $evt['subscription_id'];
            $sub->save();
        }

        if (! $sub) {
            Log::info('[CHECKOUT] subscription webhook had no matching local row', ['gateway' => $gw->slug, 'evt' => $evt]);
            return;
        }

        switch ($evt['type']) {
            case 'created':
                $svc->activate($sub, [
                    'gateway_subscription_id' => $evt['subscription_id'] ?? $sub->gateway_subscription_id,
                    'current_period_end'      => $evt['period_end'] ?? null,
                ]);
                $this->finalizePendingOrderFor($sub);
                break;

            case 'renewed':
                // Some gateways (Razorpay) fire the same event for the first
                // charge and every renewal — treat a still-pending row as the
                // activation, an active row as a true renewal.
                if ($sub->status === Subscription::STATUS_PENDING) {
                    $svc->activate($sub, [
                        'gateway_subscription_id' => $evt['subscription_id'] ?? $sub->gateway_subscription_id,
                        'current_period_end'      => $evt['period_end'] ?? null,
                    ]);
                    $this->finalizePendingOrderFor($sub);
                } else {
                    $svc->renew($sub, $evt['period_end'] ?? null);
                }
                break;

            case 'canceled':
                $svc->cancel($sub);
                break;

            case 'payment_failed':
                $svc->markPastDue($sub);
                break;
        }
    }

    /** Finalize the order a subscription was opened against (first charge via
     *  webhook), so the plan applies even if the buyer never returned to us. */
    private function finalizePendingOrderFor(Subscription $sub): void
    {
        $orderId = is_array($sub->meta) ? ($sub->meta['order_id'] ?? null) : null;
        if (! $orderId) {
            return;
        }
        $order = Order::find($orderId);
        if ($order && $order->status !== 'paid') {
            $this->finalizeOrder($order, PaymentResult::paid(
                gatewayPaymentId: $sub->gateway_subscription_id,
                gatewayOrderId:   $sub->gateway_subscription_id,
            ));
        }
    }

    /**
     * Mark an order paid + apply the new plan to the workspace. Idempotent
     * — re-calling with the same order won't double-apply.
     */
    private function finalizeOrder(Order $order, \App\Services\Payment\PaymentResult $result): void
    {
        if ($order->status === 'paid') return;

        DB::transaction(function () use ($order, $result) {
            $order->update([
                'status'             => 'paid',
                'gateway_payment_id' => $result->gatewayPaymentId,
                'gateway_payload'    => array_merge((array) $order->gateway_payload, (array) $result->payload),
                'paid_at'            => now(),
                'failure_reason'     => null,
            ]);

            // Wallet top-up order (a credit package, not a plan): grant the
            // credits via the same WalletService the old self-confirm path
            // used — but now only AFTER a real gateway payment confirms.
            if ($order->credit_package_id) {
                $cp    = CreditPackage::find($order->credit_package_id);
                $buyer = User::find($order->user_id);
                if ($cp && $buyer) {
                    app(WalletService::class)->topupViaPackage($buyer, $cp, $order->gateway_payment_id);
                }
            }

            // Apply the purchase to the buying workspace.
            if ($order->workspace_id && $order->package_id) {
                $ws = Workspace::find($order->workspace_id);
                if ($ws) {
                    $pkg = \App\Models\Package::where('plan_id', (string) $order->package_id)->first()
                        ?? \App\Models\Package::find($order->package_id);

                    if ($pkg && $pkg->type === \App\Models\Package::TYPE_ADDON) {
                        // ADD-ON purchase: grant it ON TOP of the current plan.
                        // Do NOT switch the plan or clear the trial — its feature
                        // flags/limits merge in via Workspace::effectiveLimit().
                        // Window from the package's billing cycle (free / lifetime
                        // / custom-quote = no expiry → lifetime add-on).
                        $addonEnds = null;
                        if (! $pkg->free && ! $pkg->lifetime && ! $pkg->is_custom_quote) {
                            $dur = max(1, (int) ($pkg->plan_duration ?: 1));
                            $addonEnds = match ($pkg->plan_unit) {
                                'days'  => now()->addDays($dur),
                                'weeks' => now()->addWeeks($dur),
                                'years' => now()->addYears($dur),
                                default => now()->addMonths($dur),
                            };
                        }
                        \App\Models\WorkspaceAddon::create([
                            'workspace_id' => $ws->id,
                            'package_id'   => $pkg->id,
                            'order_id'     => $order->id,
                            'status'       => \App\Models\WorkspaceAddon::STATUS_ACTIVE,
                            'starts_at'    => now(),
                            'ends_at'      => $addonEnds,
                        ]);
                    } else {
                        // PLAN purchase: switch the plan + lift the trial window
                        // (free-trial bar + EnsureTrialActive gate) immediately.
                        // Expiry from the billing cycle; free/lifetime/custom =
                        // none. Renewals extend from max(now, current ends) so
                        // paying early never loses unused days.
                        $endsAt = null;
                        if ($pkg && ! $pkg->free && ! $pkg->lifetime && ! $pkg->is_custom_quote) {
                            $dur  = max(1, (int) ($pkg->plan_duration ?: 1));
                            $base = ($ws->plan_ends_at && $ws->plan_ends_at->isFuture())
                                ? $ws->plan_ends_at->copy()
                                : now();
                            // Yearly checkout billed 12 months up front → a full
                            // year of validity, whatever the plan's own cycle.
                            $endsAt = ($order->billing_period === 'yearly')
                                ? $base->addYear()
                                : match ($pkg->plan_unit) {
                                    'days'  => $base->addDays($dur),
                                    'weeks' => $base->addWeeks($dur),
                                    'years' => $base->addYears($dur),
                                    default => $base->addMonths($dur),
                                };
                        }
                        $ws->forceFill([
                            'plan'          => $order->package_id,
                            'trial_ends_at' => null,
                            'plan_ends_at'  => $endsAt,
                        ])->save();
                    }
                }
            }

            // Increment the coupon's uses_count so max_uses caps work.
            // Atomic increment so concurrent paid callbacks can't
            // overshoot the cap.
            if ($order->coupon_id) {
                \App\Models\Coupon::where('id', $order->coupon_id)->increment('uses_count');
            }
        });

        Log::info('[CHECKOUT] order paid + plan applied', [
            'order_id'     => $order->id,
            'workspace_id' => $order->workspace_id,
            'package_id'   => $order->package_id,
        ]);
    }
}
