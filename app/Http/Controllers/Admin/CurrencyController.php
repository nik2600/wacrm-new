<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Currency;
use App\Models\SystemSetting;
use App\Support\Audit;
use App\Support\FormatSettings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

/**
 * Admin-only CRUD for the currency catalog.
 *
 *  - GET    /admin/currencies         → list + toggle + default selector
 *  - POST   /admin/currencies         → create
 *  - PATCH  /admin/currencies/{id}    → update
 *  - DELETE /admin/currencies/{id}    → delete (blocked if it's the default)
 *  - POST   /admin/currencies/{id}/toggle    → toggle is_active
 *  - POST   /admin/currencies/fetch-rates    → pull live rates from open.er-api.com
 *  - POST   /admin/currencies/default        → set the global default currency
 */
class CurrencyController extends Controller
{
    public function index(\Illuminate\Http\Request $request)
    {
        $q = trim((string) $request->query('q', ''));

        $query = Currency::query()->orderBy('code');
        if ($q !== '') {
            $query->where(function ($w) use ($q) {
                $w->where('code', 'like', '%' . $q . '%')
                  ->orWhere('name', 'like', '%' . $q . '%')
                  ->orWhere('symbol', 'like', '%' . $q . '%');
            });
        }
        $currencies = $query->paginate(12)->withQueryString();

        $defaultCode = strtoupper((string) SystemSetting::get('default_currency', 'USD'));

        // Dropdown source — not affected by pagination/search.
        $allActive = Currency::where('is_active', true)->orderBy('code')->get();

        $stats = [
            'total'    => Currency::count(),
            'active'   => Currency::where('is_active', true)->count(),
            'default'  => $defaultCode,
            'inactive' => Currency::where('is_active', false)->count(),
        ];

        return view('admin.currencies.index', compact('currencies', 'defaultCode', 'allActive', 'stats', 'q'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'          => ['required', 'string', 'max:120'],
            'code'          => ['required', 'string', 'max:10', 'unique:currencies,code'],
            'symbol'        => ['nullable', 'string', 'max:20'],
            'precision'     => ['required', 'integer', 'min:0', 'max:6'],
            'exchange_rate' => ['required', 'numeric', 'min:0.000001'],
            'is_active'     => ['nullable', 'boolean'],
        ]);
        $data['is_active'] = (bool) ($data['is_active'] ?? true);
        $currency = Currency::create($data);
        FormatSettings::flushCache();
        Audit::log('admin.currency.created', [
            'resource' => $currency,
            'meta'     => ['code' => $currency->code, 'rate' => $currency->exchange_rate],
        ]);
        return back()->with('success', 'Currency added.');
    }

    public function update(Request $request, int $id)
    {
        $currency = Currency::findOrFail($id);
        $data = $request->validate([
            'name'          => ['required', 'string', 'max:120'],
            'code'          => ['required', 'string', 'max:10', 'unique:currencies,code,' . $id],
            'symbol'        => ['nullable', 'string', 'max:20'],
            'precision'     => ['required', 'integer', 'min:0', 'max:6'],
            'exchange_rate' => ['required', 'numeric', 'min:0.000001'],
            'is_active'     => ['nullable', 'boolean'],
        ]);
        $data['is_active'] = (bool) ($data['is_active'] ?? false);
        $before = $currency->only(['code', 'exchange_rate', 'is_active']);
        $currency->update($data);
        FormatSettings::flushCache();
        Audit::log('admin.currency.updated', [
            'resource' => $currency,
            'meta'     => ['before' => $before, 'after' => $currency->only(['code', 'exchange_rate', 'is_active'])],
        ]);
        return back()->with('success', 'Currency updated.');
    }

    public function destroy(int $id)
    {
        $currency = Currency::findOrFail($id);
        $defaultCode = strtoupper((string) SystemSetting::get('default_currency', 'USD'));
        if (strtoupper($currency->code) === $defaultCode) {
            Audit::log('admin.currency.delete_blocked', [
                'resource' => $currency,
                'result'   => 'failure',
                'meta'     => ['reason' => 'is_system_default'],
            ]);
            return back()->with('error', 'Cannot delete the system default currency. Change the default first.');
        }
        $snapshot = $currency->only(['id', 'code', 'name']);
        $currency->delete();
        FormatSettings::flushCache();
        Audit::log('admin.currency.deleted', ['meta' => $snapshot]);
        return back()->with('success', 'Currency removed.');
    }

    public function toggle(int $id)
    {
        $currency = Currency::findOrFail($id);
        $currency->update(['is_active' => !$currency->is_active]);
        FormatSettings::flushCache();
        Audit::log($currency->is_active ? 'admin.currency.activated' : 'admin.currency.deactivated', [
            'resource' => $currency,
        ]);
        return back()->with('success', $currency->is_active ? 'Activated.' : 'Deactivated.');
    }

    /**
     * Hit https://open.er-api.com/v6/latest/USD (free, no API key) and
     * bulk-update every currency we have whose code appears in the
     * response. USD itself stays at 1.0.
     */
    public function fetchRates(Request $request)
    {
        try {
            $r = Http::timeout(12)->get('https://open.er-api.com/v6/latest/USD');
            if (!$r->successful()) {
                return back()->with('error', 'Rate provider returned HTTP ' . $r->status());
            }
            $rates = $r->json('rates') ?? [];
            if (!is_array($rates) || empty($rates)) {
                return back()->with('error', 'Rate provider returned no rates.');
            }
            $updated = 0;
            foreach (Currency::all() as $c) {
                $key = strtoupper($c->code);
                if (isset($rates[$key])) {
                    $c->update(['exchange_rate' => (float) $rates[$key]]);
                    $updated++;
                }
            }
            FormatSettings::flushCache();
            Audit::log('admin.currency.rates_fetched', [
                'meta' => ['provider' => 'open.er-api.com', 'updated' => $updated],
            ]);
            return back()->with('success', "Updated {$updated} currencies from open.er-api.com.");
        } catch (\Throwable $e) {
            Audit::log('admin.currency.rates_fetch_failed', [
                'result' => 'failure',
                'meta'   => ['provider' => 'open.er-api.com', 'error' => $e->getMessage()],
            ]);
            return back()->with('error', 'Fetch failed: ' . $e->getMessage());
        }
    }

    public function setDefault(Request $request)
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:10', 'exists:currencies,code'],
        ]);
        $previous = strtoupper((string) SystemSetting::get('default_currency', 'USD'));
        $next     = strtoupper($data['code']);
        SystemSetting::set('default_currency', $next);
        FormatSettings::flushCache();
        Audit::log('admin.currency.default_changed', [
            'meta' => ['previous' => $previous, 'now' => $next],
        ]);
        return back()->with('success', 'Default currency set to ' . $next . '.');
    }
}
