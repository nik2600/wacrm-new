<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CreditPackage;
use App\Models\Currency;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Admin CRUD for credit-top-up bundles. Lives in /admin/credit-packages.
 * Each package is a fixed "₹X buys Y credits" offer; users hit them
 * from the checkout flow. Disabling a package leaves existing
 * already-purchased ledger rows alone — only stops it appearing in
 * the user-facing list.
 */
class AdminCreditPackagesController extends Controller
{
    public function index(): View
    {
        $packages = CreditPackage::query()->ordered()->get();
        return view('admin.credit-packages.index', compact('packages'));
    }

    public function create(): View
    {
        return view('admin.credit-packages.create', [
            'currencies' => $this->currencyOptions(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validateData($request);
        $data['price_minor'] = (int) round(((float) $data['price_major']) * 100);
        unset($data['price_major']);

        CreditPackage::create($data);
        return redirect()->route('admin.credit-packages.index')->with('status', 'Credit package created.');
    }

    public function edit(int $id): View
    {
        $package = CreditPackage::findOrFail($id);
        return view('admin.credit-packages.edit', [
            'package'    => $package,
            'currencies' => $this->currencyOptions(),
        ]);
    }

    /** Active currencies from the catalog, with a static fallback if none are seeded. */
    private function currencyOptions(): array
    {
        $rows = Currency::query()->where('is_active', true)->orderBy('code')->get();
        if ($rows->isEmpty()) {
            return ['INR' => '₹ INR', 'USD' => '$ USD', 'EUR' => '€ EUR', 'GBP' => '£ GBP', 'AED' => 'AED'];
        }
        return $rows->mapWithKeys(fn ($c) => [
            $c->code => ($c->symbol ? $c->symbol . ' ' : '') . $c->code,
        ])->all();
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $package = CreditPackage::findOrFail($id);
        $data = $this->validateData($request, $id);
        $data['price_minor'] = (int) round(((float) $data['price_major']) * 100);
        unset($data['price_major']);

        $package->update($data);
        return redirect()->route('admin.credit-packages.index')->with('status', 'Credit package updated.');
    }

    public function destroy(int $id): RedirectResponse
    {
        $package = CreditPackage::findOrFail($id);
        $package->delete();
        return redirect()->route('admin.credit-packages.index')->with('status', 'Credit package deleted.');
    }

    public function toggle(int $id): RedirectResponse
    {
        $package = CreditPackage::findOrFail($id);
        $package->update(['is_active' => !$package->is_active]);
        return redirect()->route('admin.credit-packages.index')
            ->with('status', $package->is_active ? 'Package activated.' : 'Package deactivated.');
    }

    private function validateData(Request $request, ?int $ignoreId = null): array
    {
        // Accept ANY active currency the admin actually offers in the dropdown
        // (e.g. IDR) — not a hardcoded short list that rejected everything else.
        $allowedCurrencies = array_keys($this->currencyOptions());

        return $request->validate([
            'name'          => 'required|string|max:96',
            'slug'          => 'nullable|string|max:96|alpha_dash|unique:credit_packages,slug' . ($ignoreId ? ',' . $ignoreId : ''),
            'price_major'   => 'required|numeric|min:0|max:10000000',
            'currency_code' => ['required', 'string', 'size:3', \Illuminate\Validation\Rule::in($allowedCurrencies)],
            'credits'       => 'required|integer|min:1|max:100000000',
            'badge'         => 'nullable|string|max:32',
            'description'   => 'nullable|string|max:500',
            'is_active'     => 'sometimes|boolean',
            'is_featured'   => 'sometimes|boolean',
            'sort_order'    => 'nullable|integer|min:0|max:9999',
        ]);
    }
}
