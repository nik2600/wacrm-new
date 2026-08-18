<?php

namespace App\Http\Controllers;

use App\Models\CreditPackage;
use App\Services\WalletService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CheckoutPagesController extends Controller
{
    public function __construct(private readonly WalletService $wallet) {}

    public function index(Request $request): View
    {
        $credits = (int) (auth()->user()?->wallet_credits ?? 0);

        // Resolve `?package=<slug>` to a real CreditPackage so the
        // checkout page knows what it's selling. Falls back to the
        // first featured/active package if the slug is missing or
        // unknown — keeps the page usable without a deep-link.
        $slug = $request->query('package');
        $package = null;
        if (is_string($slug) && $slug !== '') {
            $package = CreditPackage::where('slug', $slug)->where('is_active', true)->first();
        }
        if (!$package) {
            $package = CreditPackage::query()->active()->ordered()->first();
        }

        return view('checkout.index', compact('credits', 'package'));
    }

    /**
     * Completes the purchase. For now this is a "self-confirm" path —
     * the user clicks Pay, we credit their wallet immediately. When
     * Razorpay (or another PSP) is wired in, the real callback should
     * flow through here too: validate the payment ref + signature,
     * THEN call $this->wallet->topupViaPackage().
     */
    public function complete(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'package_slug' => 'required|string|max:96',
            'payment_ref'  => 'nullable|string|max:191',
        ]);

        $package = CreditPackage::where('slug', $data['package_slug'])
            ->where('is_active', true)
            ->firstOrFail();

        $user = Auth::user();
        $tx = $this->wallet->topupViaPackage($user, $package, $data['payment_ref'] ?? null);

        return redirect()->route('checkout.success')->with([
            'status'         => 'Top-up complete · ' . number_format($package->credits) . ' credits added.',
            'topup_credits'  => $package->credits,
            'topup_package'  => $package->name,
        ]);
    }

    public function success(): View
    {
        // Pull the user's most-recent paid order so the success card can
        // show the real amount + currency. Without this the page was a
        // hardcoded "₹ 14,400" placeholder regardless of plan/currency.
        $order = null;
        if ($u = auth()->user()) {
            $order = \App\Models\Order::query()
                ->where('user_id', $u->id)
                ->where('status', 'paid')
                ->latest('id')
                ->first();
        }
        return view('checkout.success', ['order' => $order]);
    }

    public function failed(): View
    {
        // Most-recent FAILED (or any non-paid) order for the user.
        $order = null;
        if ($u = auth()->user()) {
            $order = \App\Models\Order::query()
                ->where('user_id', $u->id)
                ->whereIn('status', ['failed', 'pending', 'cancelled'])
                ->latest('id')
                ->first();
        }
        return view('checkout.failed', ['order' => $order]);
    }
}
