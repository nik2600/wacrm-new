<?php

namespace App\Http\Controllers;

use App\Models\WaCoupon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

/**
 * Merchant management of storefront discount codes (S5). Workspace-scoped.
 */
class WaCouponController extends Controller
{
    public function index()
    {
        $wsId = Auth::user()?->current_workspace_id;
        abort_unless($wsId, 403);

        return view('user.store.coupons.index', [
            'coupons' => WaCoupon::where('workspace_id', $wsId)->orderByDesc('id')->paginate(30),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $wsId = Auth::user()?->current_workspace_id;
        abort_unless($wsId, 403);
        $data = $this->validateCoupon($request);

        $code = Str::upper(trim($data['code']));
        if (WaCoupon::where('workspace_id', $wsId)->whereRaw('LOWER(code) = ?', [Str::lower($code)])->exists()) {
            return back()->withErrors(['code' => 'A coupon with that code already exists.'])->withInput();
        }

        WaCoupon::create($this->payload($wsId, $code, $data));

        return back()->with('status', 'Coupon created.');
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $wsId = Auth::user()?->current_workspace_id;
        abort_unless($wsId, 403);
        $coupon = WaCoupon::where('workspace_id', $wsId)->findOrFail($id);

        // Quick toggle path (active on/off) from the list.
        if ($request->has('toggle')) {
            $coupon->forceFill(['active' => !$coupon->active])->save();
            return back()->with('status', 'Coupon ' . ($coupon->active ? 'enabled' : 'disabled') . '.');
        }

        $data = $this->validateCoupon($request);
        $code = Str::upper(trim($data['code']));
        $coupon->forceFill($this->payload($wsId, $code, $data))->save();

        return back()->with('status', 'Coupon updated.');
    }

    public function destroy(int $id): RedirectResponse
    {
        $wsId = Auth::user()?->current_workspace_id;
        abort_unless($wsId, 403);
        WaCoupon::where('workspace_id', $wsId)->where('id', $id)->delete();

        return back()->with('status', 'Coupon deleted.');
    }

    private function validateCoupon(Request $request): array
    {
        return $request->validate([
            'code'               => ['required', 'string', 'max:64', 'regex:/^[A-Za-z0-9_-]+$/'],
            'type'               => ['required', 'in:percent,flat'],
            'amount'             => ['required', 'numeric', 'min:0'],
            'min_subtotal'       => ['nullable', 'numeric', 'min:0'],
            'max_discount'       => ['nullable', 'numeric', 'min:0'],
            'free_shipping'      => ['nullable', 'boolean'],
            'active'             => ['nullable', 'boolean'],
            'expires_at'         => ['nullable', 'date'],
            'usage_limit'        => ['nullable', 'integer', 'min:1'],
        ]);
    }

    /** Build the persisted attributes; money fields convert major → minor. */
    private function payload(int $wsId, string $code, array $data): array
    {
        $toMinor = fn ($v) => $v === null || $v === '' ? null : (int) round((float) $v * 100);

        return [
            'workspace_id'       => $wsId,
            'code'               => $code,
            'type'               => $data['type'],
            // percent → integer 1-100; flat → minor units.
            'amount'             => $data['type'] === 'percent'
                ? min(100, max(0, (int) round((float) $data['amount'])))
                : (int) round((float) $data['amount'] * 100),
            'min_subtotal_minor' => $toMinor($data['min_subtotal'] ?? null),
            'max_discount_minor' => $toMinor($data['max_discount'] ?? null),
            'free_shipping'      => (bool) ($data['free_shipping'] ?? false),
            'active'             => (bool) ($data['active'] ?? true),
            'expires_at'         => $data['expires_at'] ?? null,
            'usage_limit'        => $data['usage_limit'] ?? null,
        ];
    }
}
