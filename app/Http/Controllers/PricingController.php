<?php

namespace App\Http\Controllers;

use App\Models\Package;
use App\Models\PaymentGateway;
use App\Models\PricingFaq;
use App\Models\Subscription;
use App\Models\SystemSetting;
use App\Services\Payment\PaymentGatewayManager;
use App\Services\Payment\SubscriptionService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Auth dashboard plan picker — mounted at /account/plans.
 * Renders the existing pricing/index.blade.php with live $packages,
 * yearly-toggle config from SystemSetting, currency from the workspace,
 * and the admin-curated PricingFaq rows.
 *
 * The public marketing /pricing page is served by FrontendController.
 */
class PricingController extends Controller
{
    public function index(Request $request): View
    {
        // Plans = the subscription grid. Add-ons = à-la-carte feature packs
        // shown in a separate section, bought ON TOP of the active plan.
        $packages = Package::active()->plans()
            ->orderBy('sort_order')
            ->orderBy('plan_amount')
            ->get();
        $addons = Package::active()->addons()
            ->orderBy('sort_order')
            ->orderBy('plan_amount')
            ->get();

        // Current plan badge / relabelled CTA. Tolerant resolve handles plan
        // stored as slug (registration) or numeric id (checkout/admin).
        $currentPackageId = auth()->user()?->currentWorkspace?->billingPackage()?->id;

        // Price of the workspace's current plan — drives the upgrade/downgrade
        // gating on the cards: you can move UP to a pricier plan, but the
        // current plan and any cheaper plan are not directly purchasable.
        $currentPlanAmount = $currentPackageId
            ? (float) (Package::find($currentPackageId)?->plan_amount ?? 0)
            : null;

        // Plan validity shown to the user. plan_ends_at is set on checkout from
        // the package's admin-configured duration (plan_duration × plan_unit);
        // null = no expiry (free / lifetime / enterprise). Also surface trial.
        $ws                = auth()->user()?->currentWorkspace;
        $currentPlanEndsAt = $ws?->plan_ends_at;          // Carbon|null
        $planExpired       = (bool) $ws?->planExpired();
        $trialEndsAt       = ($ws && $ws->onTrial()) ? $ws->trial_ends_at : null;

        // Active auto-renewing subscription (if any) — drives the "auto-renews
        // on …" banner + the Cancel auto-renew button.
        $activeSubscription = $ws
            ? Subscription::query()->where('workspace_id', $ws->id)->active()->latest('id')->first()
            : null;

        // Workspace currency, falling back to the platform default.
        $currency = optional(auth()->user()?->currentWorkspace)->currency
            ?? strtoupper((string) SystemSetting::get('default_currency', 'USD'));

        // All admin-editable from /admin/checkout-settings and /admin/pricing-faqs.
        $yearlyEnabled     = (bool) SystemSetting::get('pricing.yearly_toggle_enabled', true);
        $yearlyDiscountPct = (int)  SystemSetting::get('pricing.yearly_discount_pct', 20);
        $refundEnabled     = (bool) SystemSetting::get('pricing.refund_enabled', true);
        $refundDays        = (int)  SystemSetting::get('pricing.refund_days', 7);

        $faqs = PricingFaq::query()->active()->get();

        return view('pricing.index', compact(
            'packages',
            'addons',
            'currentPackageId',
            'currentPlanAmount',
            'currency',
            'currentPlanEndsAt',
            'planExpired',
            'trialEndsAt',
            'activeSubscription',
            'yearlyEnabled',
            'yearlyDiscountPct',
            'refundEnabled',
            'refundDays',
            'faqs',
        ));
    }

    /**
     * Cancel the workspace's auto-renewing subscription. Tells the gateway to
     * stop charging; the current paid period keeps running until plan_ends_at
     * (Workspace::package() expires it naturally), so the user doesn't lose the
     * time they've already paid for.
     */
    public function cancelSubscription(Request $request, PaymentGatewayManager $manager): RedirectResponse
    {
        $ws = auth()->user()?->currentWorkspace;
        if (! $ws) {
            return back()->with('error', __('No workspace context.'));
        }

        $sub = Subscription::query()->where('workspace_id', $ws->id)->active()->latest('id')->first();
        if (! $sub) {
            return back()->with('error', __('No active subscription to cancel.'));
        }

        // Best-effort gateway cancel. Even if the remote call fails, we still
        // mark it canceled locally so it won't be treated as renewing — the
        // admin can reconcile in the gateway dashboard.
        try {
            $gateway = PaymentGateway::query()->where('slug', $sub->gateway)->first();
            if ($gateway && $sub->gateway_subscription_id) {
                $driver = $manager->driverFromModel($gateway);
                $result = $driver->cancelSubscription($sub->gateway_subscription_id, [
                    'gateway_customer_id' => $sub->gateway_customer_id,
                    'gateway_plan_id'     => $sub->gateway_plan_id,
                ]);
                if ($result->status === 'failed') {
                    Log::warning('[SUBSCRIPTION] gateway cancel failed', ['sub' => $sub->id, 'err' => $result->error]);
                }
            }
        } catch (\Throwable $e) {
            Log::error('[SUBSCRIPTION] cancel threw', ['sub' => $sub->id, 'err' => $e->getMessage()]);
        }

        app(SubscriptionService::class)->cancel($sub);

        return back()->with('success', __('Auto-renew cancelled. Your plan stays active until it expires.'));
    }
}
