<?php

namespace App\Http\Controllers;

use App\Models\WaCustomerProfile;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Shop dashboard → Customers. Merchant pre-sets a customer's Name / Company /
 * delivery Address by phone, so the WhatsApp ordering flow shows it automatically
 * and the customer just replies YES (no re-typing). Read by
 * OrderingService::shippingFor().
 */
class WaCustomerProfileController extends Controller
{
    public function index(Request $request): View
    {
        $wsId = (int) Auth::user()->current_workspace_id;
        $q    = trim((string) $request->string('q')->toString());
        // Escape LIKE metacharacters so an attacker-supplied % or _ can't
        // broaden matching (defense-in-depth — mirrors DealsController).
        $like = str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $q);

        $rows = WaCustomerProfile::forWorkspace($wsId)
            ->when($q !== '', function ($w) use ($like) {
                $w->where(function ($x) use ($like) {
                    $x->where('name', 'like', "%{$like}%")
                      ->orWhere('phone', 'like', "%{$like}%")
                      ->orWhere('company', 'like', "%{$like}%");
                });
            })
            ->orderBy('name')->orderBy('phone')
            ->paginate(20)->withQueryString();

        return view('user.store.customers.index', compact('rows', 'q'));
    }

    /** Create OR update (keyed by phone) — one form does both. */
    public function store(Request $request): RedirectResponse
    {
        $wsId = (int) Auth::user()->current_workspace_id;
        $data = $request->validate([
            'phone'   => 'required|string|max:32',
            'name'    => 'nullable|string|max:191',
            'company' => 'nullable|string|max:191',
            'address' => 'nullable|string|max:2000',
        ]);

        $digits = WaCustomerProfile::digits($data['phone']);
        if ($digits === '') {
            return back()->with('error', __('Enter a valid phone number (with country code).'));
        }

        WaCustomerProfile::updateOrCreate(
            ['workspace_id' => $wsId, 'phone' => $digits],
            [
                'name'    => trim((string) ($data['name'] ?? '')) ?: null,
                'company' => trim((string) ($data['company'] ?? '')) ?: null,
                'address' => trim((string) ($data['address'] ?? '')) ?: null,
            ]
        );

        return back()->with('success', __('Customer saved. Their address will auto-fill when they order on WhatsApp.'));
    }

    public function destroy(int $id): RedirectResponse
    {
        $wsId = (int) Auth::user()->current_workspace_id;
        WaCustomerProfile::forWorkspace($wsId)->whereKey($id)->delete();
        return back()->with('success', __('Customer removed.'));
    }
}
