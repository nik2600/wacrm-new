<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Coupon;
use App\Models\Currency;
use App\Models\Package;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Admin coupons CRUD. Each coupon row drives the resolver at
 * /checkout/{id}/apply-coupon.
 *
 *   GET    /admin/coupons              → list with KPIs + search
 *   GET    /admin/coupons/create       → create form
 *   POST   /admin/coupons              → store
 *   GET    /admin/coupons/{id}/edit    → edit form
 *   PATCH  /admin/coupons/{id}         → update
 *   POST   /admin/coupons/{id}/toggle  → flip is_active
 *   DELETE /admin/coupons/{id}         → delete (code returns to the pool)
 */
class CouponController extends Controller
{
    public function index(Request $request): View
    {
        $q       = trim((string) $request->query('q', ''));
        $statusF = (string) $request->query('status', 'all');

        $query = Coupon::query();
        if ($q !== '') {
            $query->where(function ($w) use ($q) {
                $w->where('code', 'like', "%{$q}%")
                  ->orWhere('description', 'like', "%{$q}%");
            });
        }
        if ($statusF === 'active')   $query->where('is_active', true);
        if ($statusF === 'disabled') $query->where('is_active', false);
        if ($statusF === 'expired')  $query->whereNotNull('expires_at')->where('expires_at', '<', now());

        $coupons = $query->orderByDesc('id')->paginate(12)->withQueryString();

        // KPIs.
        $now    = now();
        $stats  = [
            'total'    => Coupon::query()->count(),
            'active'   => Coupon::query()->where('is_active', true)->count(),
            'expired'  => Coupon::query()->whereNotNull('expires_at')->where('expires_at', '<', $now)->count(),
            'redeemed' => (int) Coupon::query()->sum('uses_count'),
        ];

        return view('admin.coupons.index', [
            'coupons'  => $coupons,
            'stats'    => $stats,
            'q'        => $q,
            'statusF'  => $statusF,
        ]);
    }

    public function create(): View
    {
        return view('admin.coupons.create', [
            'packages'   => Package::query()->where('status', 1)->orderBy('plan_amount')->get(['id', 'pname']),
            'currencies' => $this->currencyOptions(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validateRow($request);
        $data['code']                 = strtoupper($data['code']);
        $data['is_active']            = (bool) $request->input('is_active');
        $data['first_purchase_only']  = (bool) $request->input('first_purchase_only');
        $data['stackable_with_other'] = (bool) $request->input('stackable_with_other');
        Coupon::create($data);
        return redirect()->route('admin.coupons.index')->with('success', 'Coupon created.');
    }

    public function edit(int $id): View
    {
        return view('admin.coupons.edit', [
            'coupon'     => Coupon::findOrFail($id),
            'packages'   => Package::query()->where('status', 1)->orderBy('plan_amount')->get(['id', 'pname']),
            'currencies' => $this->currencyOptions(),
        ]);
    }

    private function currencyOptions(): array
    {
        $rows = Currency::query()->where('is_active', true)->orderBy('code')->get();
        if ($rows->isEmpty()) {
            return ['' => 'Any currency', 'INR' => '₹ INR', 'USD' => '$ USD', 'EUR' => '€ EUR', 'GBP' => '£ GBP', 'AED' => 'AED'];
        }
        $out = ['' => 'Any currency'];
        foreach ($rows as $c) {
            $out[$c->code] = ($c->symbol ? $c->symbol . ' ' : '') . $c->code;
        }
        return $out;
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $coupon = Coupon::findOrFail($id);
        $data   = $this->validateRow($request, $id);
        $data['code']                 = strtoupper($data['code']);
        $data['is_active']            = (bool) $request->input('is_active');
        $data['first_purchase_only']  = (bool) $request->input('first_purchase_only');
        $data['stackable_with_other'] = (bool) $request->input('stackable_with_other');
        $coupon->update($data);
        return back()->with('success', 'Coupon updated.');
    }

    public function toggle(int $id): RedirectResponse
    {
        $coupon = Coupon::findOrFail($id);
        $coupon->update(['is_active' => !$coupon->is_active]);
        return back()->with('success', $coupon->is_active ? 'Activated.' : 'Deactivated.');
    }

    public function destroy(int $id): RedirectResponse
    {
        Coupon::findOrFail($id)->delete();
        return back()->with('success', 'Coupon removed.');
    }

    private function validateRow(Request $request, ?int $id = null): array
    {
        return $request->validate([
            'code'                       => ['required', 'string', 'max:64',
                                              Rule::unique('coupons', 'code')->ignore($id)],
            'description'                => ['nullable', 'string', 'max:255'],
            'admin_note'                 => ['nullable', 'string', 'max:1000'],
            'type'                       => ['required', 'in:percent,fixed'],
            'amount'                     => ['required', 'numeric', 'min:0'],
            'max_discount_amount'        => ['nullable', 'numeric', 'min:0'],
            'currency_code'              => ['nullable', 'string', 'max:8'],
            'min_order_amount'           => ['nullable', 'numeric', 'min:0'],
            'max_uses'                   => ['nullable', 'integer', 'min:1'],
            'per_user_limit'             => ['nullable', 'integer', 'min:1'],
            'applicable_package_ids'     => ['nullable', 'array'],
            'applicable_package_ids.*'   => ['integer', 'exists:packages,id'],
            'starts_at'                  => ['nullable', 'date'],
            'expires_at'                 => ['nullable', 'date', 'after_or_equal:starts_at'],
        ]);
    }
}
