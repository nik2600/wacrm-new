<?php

namespace App\Http\Controllers\Api\App;

use App\Http\Controllers\Controller;
use App\Models\Coupon;
use App\Models\Currency;
use App\Models\Order;
use App\Models\Package;
use App\Models\PaymentGateway;
use App\Support\FormatSettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * Mobile-app billing surface. Response shapes are kept byte-compatible with
 * the existing Flutter app (the legacy Api\Main\OrderController /
 * CouponController / ProfileController contracts), but every query runs
 * against OUR current models:
 *
 *   - Plans live in `packages` (App\Models\Package), priced in `plan_amount`
 *     / `offer_price`, period via `plan_unit` + `plan_duration`.
 *   - Orders live in `orders` (App\Models\Order) — workspace-scoped, status
 *     is a string ('pending'|'paid'|'failed'|'refunded') not a 0/1/2 int.
 *   - Gateways live in `payment_gateways` (App\Models\PaymentGateway) with
 *     encrypted credentials; secret keys are NEVER returned.
 *   - Coupons live in `coupons` (App\Models\Coupon) — the active billing
 *     coupon model (WaCoupon is the storefront one, not billing).
 *
 * Multi-tenancy: orders are scoped to BOTH the authed user and their
 * current workspace. Plans / gateways / coupons are GLOBAL (admin-managed),
 * so they are not workspace-scoped.
 */
class BillingController extends Controller
{
    /**
     * GET /plans — active packages.
     * Contract: Api\Main\OrderController::getActivePackages.
     */
    public function plans(Request $request): JsonResponse
    {
        try {
            // Resolve ONE display currency for the whole plan list — exactly
            // like the web pricing page: the workspace currency, else the admin
            // `default_currency`. The old "active currency with the highest
            // exchange_rate" picked VND/IDR (huge rates) as the display currency
            // and, combined with per-package symbols in packageCard(), produced
            // the "some plans INR, some USD" mix the app showed.
            $wsCode   = optional($request->user()?->currentWorkspace)->currency;
            $code     = strtoupper((string) ($wsCode ?: \App\Models\SystemSetting::get('default_currency', 'USD')));
            $currency = Currency::query()->whereRaw('UPPER(code) = ?', [$code])->first()
                ?: Currency::query()->where('is_active', true)->first()
                ?: Currency::query()->first();

            // Match the web pricing page (/account/plans): PLAN packages only.
            // The `plans()` scope excludes add-on packages (type='addon'), which
            // live on their own list. The app previously returned ALL status=true
            // rows — so add-ons leaked into the plan list ("different from web")
            // and dragged in their own currency (part of the INR/USD mix).
            $packages = Package::active()->plans()
                ->orderBy('sort_order')->orderBy('id')->get();

            // Yearly-billing toggle + discount — the SAME admin settings the web
            // pricing page uses. The web shows a per-month figure discounted by
            // `yearly_discount_pct` when the yearly toggle is on (e.g. $11 → $8.80
            // at 20%). Expose both so the app renders identical numbers.
            $yearlyEnabled = (bool) \App\Models\SystemSetting::get('pricing.yearly_toggle_enabled', true);
            $yearlyPct     = (int)  \App\Models\SystemSetting::get('pricing.yearly_discount_pct', 20);

            $transformed = $packages->map(fn (Package $p) => $this->packageCard($p, $currency, $yearlyPct))->values();

            return response()->json([
                'success' => true,
                'message' => 'Active packages retrieved successfully',
                'data' => [
                    'packages' => $transformed,
                    'currency' => $currency,
                    'yearly_enabled' => $yearlyEnabled,
                    'yearly_discount_pct' => $yearlyPct,
                    'package_features' => Package::featureCatalog(),
                    'total_packages' => $packages->count(),
                ],
            ], 200);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve active packages',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * GET /order-data — home plan / current-subscription summary.
     * Contract: Api\Main\OrderController::orderData.
     * Sourced from the user's latest PAID order + their workspace plan window.
     */
    public function orderData(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            $workspace = $user->currentWorkspace;

            $order = Order::query()
                ->where('user_id', $user->id)
                ->when($workspace, fn ($q) => $q->where('workspace_id', $workspace->id))
                ->where('status', 'paid')
                ->with('package')
                ->orderByDesc('paid_at')->orderByDesc('id')
                ->first();

            if (! $order) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'No active payment found',
                ], 404);
            }

            $package = $order->package;

            // Plan window: prefer the workspace plan_ends_at (source of truth),
            // fall back to deriving from the package duration off the paid date.
            $endDate = null;
            if ($workspace && $workspace->plan_ends_at) {
                $endDate = $workspace->plan_ends_at;
            } elseif ($package) {
                $start = $order->paid_at ?: $order->created_at;
                $duration = (int) ($package->plan_duration ?: 1);
                $unit = strtolower((string) $package->plan_unit);
                $endDate = match (true) {
                    str_contains($unit, 'year') => $start->copy()->addYears($duration),
                    str_contains($unit, 'day') => $start->copy()->addDays($duration),
                    str_contains($unit, 'week') => $start->copy()->addWeeks($duration),
                    default => $start->copy()->addMonths($duration),
                };
            }

            // Progress remaining: messages limit vs sent-this-cycle.
            $remaining = 100;
            try {
                $limit = $workspace ? (int) $workspace->effectiveLimit('monthly_messages_limit', 0) : 0;
                if ($limit > 0) {
                    $sent = \App\Models\Message::query()
                        ->when($workspace, fn ($q) => $q->where('workspace_id', $workspace->id))
                        ->where('created_at', '>=', $order->paid_at ?: $order->created_at)
                        ->count();
                    $used = round(($sent / $limit) * 100, 2);
                    $remaining = max(0, 100 - $used);
                }
            } catch (\Throwable $e) {
                $remaining = 100;
            }

            return response()->json([
                'status' => 'success',
                'data' => [
                    'plan_name' => $package?->pname,
                    'end_date' => $endDate ? $endDate->format('d M Y') : null,
                    'progress_remaining' => $remaining . '%',
                    'amount' => (float) ($order->total_amount ?: $order->amount),
                    'currency' => $order->currency,
                    'symbol' => $this->symbolFor($order->currency),
                    'id' => $order->id,
                ],
            ], 200);
        } catch (\Throwable $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve order data',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * GET /orders/history — the user's order history (workspace-scoped).
     * Contract: Api\Main\OrderController::getUserOrderHistory.
     */
    public function orderHistory(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            $workspace = $user->currentWorkspace;

            $orders = Order::query()
                ->where('user_id', $user->id)
                ->when($workspace, fn ($q) => $q->where('workspace_id', $workspace->id))
                ->with('package')
                ->orderByDesc('id')
                ->get();

            $transformed = $orders->map(fn (Order $o) => [
                'id' => $o->id,
                'order_id' => $o->order_number,
                'plan_name' => $o->package?->pname,
                'user_name' => $o->customer_name ?: $user->name,
                'amount' => (float) ($o->total_amount ?: $o->amount),
                'symbol' => $this->symbolFor($o->currency),
                'payment_status' => $o->status,
                'payment_status_text' => $this->statusText($o->status),
                'payment_mode' => $o->gateway_slug ? ucfirst($o->gateway_slug) : null,
                'transaction_id' => $o->gateway_payment_id,
                'created_at' => $o->created_at,
                'updated_at' => $o->updated_at,
            ])->values();

            return response()->json([
                'success' => true,
                'message' => 'Order history retrieved successfully',
                'data' => $transformed,
            ], 200);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve order history',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * GET /orders/invoice/{id}/download — invoice as printable HTML.
     * Contract: Api\Main\OrderController::downloadInvoice + getInvoiceData.
     * We return self-contained HTML (the app renders / shares / prints it).
     */
    public function downloadInvoice(Request $request, int $id)
    {
        try {
            $user = $request->user();
            $workspace = $user->currentWorkspace;

            $order = Order::query()
                ->where('id', $id)
                ->where('user_id', $user->id)
                ->when($workspace, fn ($q) => $q->where('workspace_id', $workspace->id))
                ->with('package')
                ->first();

            if (! $order) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invoice not found or access denied',
                ], 404);
            }

            $appName = (string) \App\Models\SystemSetting::get('app_name', config('app.name', 'WaDesk'));
            $supportEmail = (string) \App\Models\SystemSetting::get('site.contact_email', '');
            $symbol = $this->symbolFor($order->currency);

            $rows = [
                ['Order #', e($order->order_number)],
                ['Plan', e($order->package?->pname ?? '—')],
                ['Date', e(optional($order->created_at)->format('d M Y'))],
                ['Status', e($this->statusText($order->status))],
                ['Payment Mode', e($order->gateway_slug ? ucfirst($order->gateway_slug) : '—')],
                ['Transaction ID', e($order->gateway_payment_id ?: '—')],
            ];
            $rowsHtml = '';
            foreach ($rows as [$k, $v]) {
                $rowsHtml .= "<tr><td style=\"padding:6px 12px;color:#555\">{$k}</td><td style=\"padding:6px 12px;font-weight:600\">{$v}</td></tr>";
            }

            $sub = number_format((float) $order->amount, 2);
            $disc = number_format((float) $order->discount_amount, 2);
            $tax = number_format((float) $order->tax_amount, 2);
            $total = number_format((float) ($order->total_amount ?: $order->amount), 2);

            $html = <<<HTML
<!doctype html><html><head><meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Invoice {$order->order_number}</title></head>
<body style="font-family:-apple-system,Segoe UI,Roboto,Arial,sans-serif;color:#1a1a1a;max-width:640px;margin:0 auto;padding:24px">
  <div style="display:flex;justify-content:space-between;align-items:flex-start;border-bottom:2px solid #075E54;padding-bottom:12px">
    <div><h1 style="margin:0;font-size:22px;color:#075E54">{$appName}</h1>
    <div style="color:#777;font-size:13px">Invoice</div></div>
    <div style="text-align:right;font-size:13px;color:#555">{$supportEmail}</div>
  </div>
  <div style="margin:16px 0;font-size:14px">
    <strong>Billed to:</strong> {$order->customer_name}<br>{$order->customer_email}
  </div>
  <table style="width:100%;border-collapse:collapse;font-size:14px;border:1px solid #eee">{$rowsHtml}</table>
  <table style="width:100%;border-collapse:collapse;font-size:14px;margin-top:16px">
    <tr><td style="padding:4px 12px;text-align:right;color:#555">Subtotal</td><td style="padding:4px 12px;text-align:right;width:120px">{$symbol}{$sub}</td></tr>
    <tr><td style="padding:4px 12px;text-align:right;color:#555">Discount</td><td style="padding:4px 12px;text-align:right">-{$symbol}{$disc}</td></tr>
    <tr><td style="padding:4px 12px;text-align:right;color:#555">Tax</td><td style="padding:4px 12px;text-align:right">{$symbol}{$tax}</td></tr>
    <tr><td style="padding:8px 12px;text-align:right;font-weight:700;border-top:1px solid #ddd">Total</td><td style="padding:8px 12px;text-align:right;font-weight:700;border-top:1px solid #ddd">{$symbol}{$total}</td></tr>
  </table>
  <p style="color:#999;font-size:12px;margin-top:24px">Thank you for your business.</p>
</body></html>
HTML;

            return response($html, 200, [
                'Content-Type' => 'text/html; charset=UTF-8',
                'Content-Disposition' => 'inline; filename="invoice-' . $order->order_number . '.html"',
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve invoice',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * GET /payment-gateway-settings — enabled gateways + ALL credential keys
     * + supported currencies per gateway.
     *
     * SECURITY WARNING (set by product owner request, 2026-06-24):
     * This endpoint NOW RETURNS SECRET KEYS (key_secret, api_secret,
     * webhook_secret, etc.) alongside the public ones. Anyone with a valid
     * Sanctum token on this workspace can read every gateway's secrets.
     * Implications:
     *   - APK decompile / network sniff / token compromise = full gateway
     *     secret disclosure for every workspace this user belongs to.
     *   - Stripe / Razorpay / PayPal TOS prohibit shipping secret keys to
     *     client devices — merchant accounts can be suspended.
     *   - PCI-DSS / SOC2 audit will flag this as a finding.
     * Mitigations to ship before going to prod:
     *   - Gate this route behind an admin-role middleware (so only admin
     *     mobile clients can read secrets; regular users still get the
     *     gateway list for SDK init).
     *   - Add cert pinning + token-binding on the device side.
     *   - Rotate every gateway secret after any token loss event.
     *
     * Contract (response keys preserved for back-compat):
     *   data     — legacy flat map ("razorpay_key_id" → value) — public + secret.
     *   gateways — per-gateway block with credentials_plain (full map),
     *              supported_currencies, mode, slug, name.
     */
    public function paymentGatewaySettings(Request $request): JsonResponse
    {
        try {
            $gateways = PaymentGateway::query()->where('is_active', true)
                ->orderBy('sort_order')->orderBy('id')->get();

            $data = [];
            $list = [];

            foreach ($gateways as $gw) {
                $creds = $gw->getDecryptedCredentials(); // ALL keys, including secrets.

                // Flat legacy shape — every credential key prefixed with the
                // gateway slug so older clients (which expected `razorpay_key_id`
                // etc.) keep working without a new release.
                foreach ($creds as $k => $v) {
                    $data[$gw->slug . '_' . $k] = $v;
                }

                $list[] = [
                    'slug' => $gw->slug,
                    'name' => $gw->name,
                    'mode' => (string) ($gw->mode ?: 'sandbox'),
                    'supported_currencies' => $gw->supported_currencies ?? [],
                    // Full credential map. SECRETS INCLUDED — see warning above.
                    'credentials' => $creds,
                    // Public-only subset kept for clients that only need
                    // publishable identifiers for SDK init.
                    'public_keys' => $this->extractPublicKeys($creds),
                ];
            }

            // Audit-log every call so secret-exposure events leave a trail
            // (matches admin-payment-gateway-API behaviour).
            try {
                \App\Support\Audit::log('billing.payment_gateway_settings_with_secrets', [
                    'meta' => [
                        'count' => count($list),
                        'gateway_slugs' => collect($list)->pluck('slug')->all(),
                        'user_id' => $request->user()?->id,
                    ],
                ]);
            } catch (\Throwable $_) {
                // audit failure must never break the response
            }

            return response()->json([
                'status' => true,
                'data' => $data,
                'gateways' => $list,
            ], 200);
        } catch (\Throwable $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to retrieve payment gateway settings',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /** Pull only the publishable / client-id identifiers out of the full key map. */
    private function extractPublicKeys(array $creds): array
    {
        $publicKeys = [
            'key', 'key_id', 'public_key', 'publishable_key', 'client_id',
            'merchant_id', 'app_id', 'account_id', 'mode', 'environment',
        ];
        $out = [];
        foreach ($creds as $k => $v) {
            $lk = strtolower((string) $k);
            $isSecret = str_contains($lk, 'secret') || str_contains($lk, 'private')
                || str_contains($lk, 'password') || str_contains($lk, 'webhook')
                || str_contains($lk, 'token');
            if (! $isSecret && (in_array($lk, $publicKeys, true) || $this->looksPublic($lk))) {
                $out[$k] = $v;
            }
        }
        return $out;
    }

    /**
     * POST /create-order — create a pending order (payment intent).
     * Contract: Api\Main\OrderController::storePaymentGatewayData.
     * Mirrors web CheckoutController::process (workspace-scoped, currency
     * conversion + server-side coupon), but returns the app's intent shape.
     */
    public function createOrder(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'gateway' => 'nullable|string|max:64',
            'gateway_id' => 'nullable|integer|exists:payment_gateways,id',
            'package_id' => 'required|integer|exists:packages,id',
            'currency' => 'nullable|string|max:10',
            'coupon' => 'nullable|string|max:64',
            'description' => 'nullable|string|max:255',
        ]);
        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            $user = $request->user();
            $workspace = $user->currentWorkspace;
            if (! $workspace) {
                return response()->json([
                    'status' => false,
                    'message' => 'No workspace context.',
                ], 422);
            }

            $package = Package::query()->where('status', true)->find($request->package_id);
            if (! $package) {
                return response()->json([
                    'status' => false,
                    'message' => 'Plan not available.',
                ], 404);
            }

            // Resolve gateway by id OR slug (both accepted).
            $gateway = null;
            if ($request->filled('gateway_id')) {
                $gateway = PaymentGateway::find($request->gateway_id);
            } elseif ($request->filled('gateway')) {
                $gateway = PaymentGateway::where('slug', strtolower($request->gateway))->first();
            }
            if ($gateway && ! $gateway->is_active) {
                return response()->json(['status' => false, 'message' => 'That gateway is not available.'], 422);
            }

            $currency = strtoupper((string) ($request->currency ?: $workspace->currency ?: $package->currency ?: 'USD'));
            if ($gateway && ! $gateway->acceptsCurrency($currency)) {
                return response()->json([
                    'status' => false,
                    'message' => $gateway->name . ' does not support ' . $currency . '.',
                ], 422);
            }

            // Convert package price into the order currency. Use
            // chargeableAmount() so the discounted offer price is charged —
            // NOT the raw plan_amount (mobile app was overcharging full price).
            $amount = $package->chargeableAmount();
            if ($package->currency && strtoupper($package->currency) !== $currency) {
                $amount = FormatSettings::convert($amount, $package->currency, $currency);
            }
            $amount = round($amount, 2);

            // Server-side coupon re-validation.
            $coupon = null;
            $discount = 0.0;
            if ($request->filled('coupon')) {
                $resolved = Coupon::resolve($request->coupon, $package, $amount, $user, $currency);
                if (! $resolved['ok']) {
                    return response()->json(['status' => false, 'message' => $resolved['message']], 422);
                }
                $coupon = $resolved['coupon'];
                $discount = (float) $resolved['discount'];
            }

            // Tax (admin switch + rate).
            $taxEnabled = (bool) \App\Models\SystemSetting::get('checkout.tax_enabled', true);
            $taxRate = $taxEnabled ? (float) \App\Models\SystemSetting::get('checkout.tax_rate', 0) : 0.0;
            $taxBase = max(0, $amount - $discount);
            $taxAmount = round($taxBase * $taxRate / 100, 2);
            $total = round($taxBase + $taxAmount, 2);
            $baseUsd = round((float) FormatSettings::convert($total > 0 ? $total : $amount, $currency, 'USD'), 2);

            $order = Order::create([
                'order_number' => Order::generateOrderNumber(),
                'workspace_id' => $workspace->id,
                'user_id' => $user->id,
                'package_id' => $package->id,
                'gateway_id' => $gateway?->id,
                'gateway_slug' => $gateway?->slug,
                'currency' => $currency,
                'amount' => $total,
                'discount_amount' => $discount,
                'tax_rate' => $taxRate,
                'tax_amount' => $taxAmount,
                'total_amount' => $total,
                'base_amount_usd' => $baseUsd,
                'status' => 'pending',
                'coupon_id' => $coupon?->id,
                'coupon_code' => $coupon?->code,
                'customer_name' => $user->name,
                'customer_email' => $user->email,
                'billing_company' => $workspace->name,
            ]);

            // ---- Approve the order immediately + activate the plan -----------
            // No gateway redirect / no URL. The app handles payment on its own
            // side; create-order is the server-side record + activation. Flip the
            // order pending → PAID and switch the workspace onto the plan (lift
            // the trial) via the standard fulfilment path (markPaidManually →
            // finalizeOrder). So it never stays "pending" and the plan assigns.
            $statusPaid = false;
            try {
                app(\App\Http\Controllers\CheckoutController::class)
                    ->markPaidManually($order, $user->id, 'app-order-' . $order->id);
                $statusPaid = true;
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::error('[App\Billing] createOrder activate threw', ['order' => $order->id, 'err' => $e->getMessage()]);
            }
            $order->refresh();

            return response()->json([
                'status' => true,
                'data' => [
                    'order_id' => $order->order_number,
                    'payment_id' => $order->id,
                    'amount' => $total,
                    'currency' => $currency,
                    'symbol' => $this->symbolFor($currency),
                    'gateway' => $gateway?->slug,
                    // Activated server-side immediately — no redirect. status_paid
                    // true / payment_status 'paid' = plan is live; the app just
                    // refreshes /api/app/credits.
                    'status_paid'      => $statusPaid || $order->status === 'paid',
                    'payment_status'   => $order->status,
                    'description' => $request->description ?: ($package->pname . ' subscription'),
                    'user' => [
                        'name' => $user->name,
                        'email' => $user->email,
                        'id' => $user->id,
                    ],
                ],
            ], 200);
        } catch (\Throwable $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to create order: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * GET /coupon/available — available billing coupons.
     * Contract: Api\main\CouponController::getAvailableCoupons.
     * Our Coupon model is the active billing coupon (WaCoupon = storefront).
     */
    public function availableCoupons(Request $request): JsonResponse
    {
        try {
            $now = now();
            $coupons = Coupon::query()
                ->where('is_active', true)
                ->where(fn ($q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', $now))
                ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>=', $now))
                ->where(function ($q) {
                    $q->whereNull('max_uses')->orWhereColumn('uses_count', '<', 'max_uses');
                })
                ->get()
                ->map(fn (Coupon $c) => [
                    'id' => $c->id,
                    'code' => $c->code,
                    'coupon_code' => $c->code,
                    'description' => $c->description,
                    'discount_type' => $c->type === 'percent' ? 'percentage' : 'fixed',
                    'amount' => (float) $c->amount,
                    'currency_code' => $c->currency_code,
                    'min_amount' => (float) $c->min_order_amount,
                    'max_discount' => $c->max_discount_amount !== null ? (float) $c->max_discount_amount : null,
                    'expiry_date' => optional($c->expires_at)->toDateTimeString(),
                ])->values();

            return response()->json([
                'success' => true,
                'status' => 200,
                'message' => 'Available coupons retrieved successfully',
                'coupons' => $coupons,
            ], 200);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'status' => 500,
                'message' => 'Failed to retrieve available coupons',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * GET /packages/{id} — one package's details.
     * Contract: ProfileController::getPackageDetails.
     */
    public function packageDetails(Request $request, int $id): JsonResponse
    {
        try {
            $package = Package::query()->where('id', $id)->where('status', true)->first();
            if (! $package) {
                return response()->json(['success' => false, 'error' => 'Package not found'], 404);
            }

            $currency = Currency::query()->where('is_active', true)->first();
            $payload = $this->packageCard($package, $currency);
            // Feature titles as the legacy `pfeatures` array of labels.
            // Prefer linked feature rows; our seeded plans store the bullet
            // list in `detail` (newline-separated), so fall back to that.
            $feat = $package->features()->pluck('title')->all();
            $payload['pfeatures'] = ! empty($feat)
                ? $feat
                : array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', (string) $package->detail))));

            return response()->json([
                'success' => true,
                'package' => $payload,
            ], 200);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve package',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    // ── Helpers ──────────────────────────────────────────────────────────

    /** Shape one package onto the app's plan-card contract. */
    private function packageCard(Package $p, ?Currency $currency, int $yearlyPct = 20): array
    {
        $hasDiscount = $p->offer_price !== null
            && (float) $p->offer_price > 0
            && (float) $p->offer_price < (float) $p->plan_amount;

        // Convert every plan into ONE display currency (the resolved workspace /
        // default currency) so the list is uniform — same as the web pricing
        // page. Packages are stored in their OWN currency; showing each raw with
        // its own symbol was the "some INR, some USD" mix. Falls back to the
        // package's own currency when no target could be resolved.
        $targetCode = strtoupper((string) ($currency?->code ?: $p->currency ?: 'USD'));
        $convert = function ($amount) use ($p, $targetCode) {
            $amount = (float) $amount;
            if ($p->currency && strtoupper((string) $p->currency) !== $targetCode) {
                return round((float) FormatSettings::convert($amount, $p->currency, $targetCode), 2);
            }
            return round($amount, 2);
        };
        $planAmount = $convert($p->plan_amount);
        $offerPrice = $p->offer_price !== null ? $convert($p->offer_price) : null;
        $payAmount  = $hasDiscount ? (float) $offerPrice : (float) $planAmount;
        $symbol     = $this->symbolFor($targetCode);

        $isFree   = (bool) $p->free || $planAmount <= 0.0;
        $isCustom = (bool) $p->is_custom_quote;

        // Yearly billing — IDENTICAL math to the web pricing blade: pay 12 months
        // up front at `yearlyPct` off, shown as a cheaper PER-MONTH figure
        // ($11 → $8.80 at 20%). The web always renders "/ month" (monthly-
        // equivalent) regardless of the package's stored plan_unit, so a plan
        // mis-configured as "days" still shows "/ month" — mirror that here so
        // the app matches the web exactly (was showing "/day").
        // Only multiply a price quoted PER MONTH. A package the admin authored
        // with plan_unit = years already holds the yearly figure, so x12 would
        // advertise ~9.6x here while checkout charges the correct amount — the
        // app and the web would disagree on price.
        $yearlyTotal = ($isFree || $isCustom)
            ? 0.0
            : ($p->isNativelyYearly()
                ? round($payAmount, 2)
                : round($payAmount * 12 * (1 - $yearlyPct / 100), 2));
        $yearlyPerMonth = ($isFree || $isCustom) ? 0.0 : round($yearlyTotal / 12, 2);
        $periodLabel    = $isCustom ? '' : ($isFree ? '' : '/ month');

        return [
            'id' => $p->id,
            'name' => $p->pname,
            'detail' => $p->detail,
            'subtitle' => $p->subtitle,
            'plan_amount' => $planAmount,
            'offer_price' => $offerPrice,
            'currency' => $targetCode,
            'plan_unit' => $p->plan_unit,
            'plan_duration' => (int) $p->plan_duration,
            'period_label' => $periodLabel,
            'free' => (bool) $p->free,
            'lifetime' => (bool) $p->lifetime,
            'is_highlighted' => (bool) $p->is_highlighted,
            'is_custom_quote' => (bool) $p->is_custom_quote,
            'formatted_price' => [
                'symbol' => $symbol,
                // Monthly (offer) price — e.g. 11. `original_amount` is the
                // pre-discount plan_amount for the strike-through.
                'amount' => $payAmount,
                'original_amount' => $hasDiscount ? $planAmount : null,
                'has_discount' => $hasDiscount,
                // Yearly billing (matches the web toggle): pay yearly_total up
                // front, effective yearly_per_month per month.
                'yearly_per_month' => $yearlyPerMonth,
                'yearly_total' => $yearlyTotal,
            ],
            'limits' => [
                'monthly_messages_limit' => $this->limitLabel($p->monthly_messages_limit),
                'contacts_limit' => $this->limitLabel($p->contacts_limit),
                'group_limit' => $this->limitLabel($p->groups_limit),
                'broadcast_limit' => $this->limitLabel($p->broadcast_limit),
                'template_limit' => $this->limitLabel($p->template_limit),
                'device_limit' => $this->limitLabel($p->device_limit),
            ],
            'status' => (bool) $p->status,
            'created_at' => $p->created_at,
            'updated_at' => $p->updated_at,
        ];
    }

    /** 0 → "Unlimited", otherwise the integer value (legacy convention). */
    private function limitLabel($value): int|string
    {
        return (int) $value === 0 ? 'Unlimited' : (int) $value;
    }

    /** Map our string order status onto the app's status text. */
    private function statusText(string $status): string
    {
        return match ($status) {
            'pending' => 'PENDING',
            'paid' => 'PAID',
            'failed' => 'FAILED',
            'refunded' => 'REFUNDED',
            default => strtoupper($status),
        };
    }

    /** Currency symbol for an ISO code (falls back to the code itself). */
    private function symbolFor(?string $code): string
    {
        if (empty($code)) return '$';
        $c = Currency::query()->where('code', strtoupper($code))->first();
        return $c?->symbol ?: strtoupper($code) . ' ';
    }

    /** Heuristic: a credential key that is safe to expose publicly. */
    private function looksPublic(string $lowerKey): bool
    {
        return str_contains($lowerKey, 'public')
            || str_contains($lowerKey, 'publishable')
            || str_ends_with($lowerKey, '_id')
            || $lowerKey === 'key';
    }
}
