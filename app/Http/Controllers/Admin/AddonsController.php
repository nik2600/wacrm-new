<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\AdminPagesController;
use App\Http\Controllers\Controller;
use App\Models\Currency;
use App\Models\Package;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Dedicated admin CRUD for ADD-ONS — its OWN create/edit form, separate from the
 * plan (packages) form.
 *
 * An add-on grants EXACTLY what the admin declares, stored in packages.grants_json:
 *   { "features": ["access_campaigns", …], "limits": { "device_limit": 2, … } }
 *
 * Blank = not part of the add-on. This kills the old bug where the shared plan
 * form coerced every blank limit to 0 (= "unlimited"), so a Campaigns add-on
 * silently granted unlimited caps it never meant to. The numeric limit / feature
 * COLUMNS are irrelevant for add-ons now — Workspace::effectiveLimit reads
 * grants_json (Package::grantsFeature / grantLimit).
 */
class AddonsController extends Controller
{
    public function index(): View
    {
        $addons = Package::query()->addons()
            ->orderBy('sort_order')->orderBy('plan_amount')->get();

        return view('admin.addons.index', ['addons' => $addons]);
    }

    public function create(): View
    {
        return $this->form(null);
    }

    public function edit(int $id): View
    {
        return $this->form(Package::query()->addons()->findOrFail($id));
    }

    private function form(?Package $addon): View
    {
        return view('admin.addons.form', [
            'addon'          => $addon,
            'featureToggles' => AdminPagesController::planFeatureToggles(),
            'limitColumns'   => AdminPagesController::planLimitColumns(),
            'labels'         => $this->labels(),
            'currencies'     => Currency::query()->where('is_active', true)
                ->orderBy('code')->get(['code', 'name', 'symbol']),
            // Pre-selected grants for the edit form.
            'grantFeatures'  => is_array($addon?->grants_json['features'] ?? null) ? $addon->grants_json['features'] : [],
            'grantLimits'    => is_array($addon?->grants_json['limits'] ?? null) ? $addon->grants_json['limits'] : [],
        ]);
    }

    /** Create (POST) or update (PUT /{id}) — one path builds grants_json. */
    public function store(Request $request, ?int $id = null): RedirectResponse
    {
        $addon = $id ? Package::query()->addons()->findOrFail($id) : null;

        $data = $request->validate([
            'pname'         => ['required', 'string', 'max:120'],
            'subtitle'      => ['nullable', 'string', 'max:191'],
            'detail'        => ['nullable', 'string', 'max:2000'],
            'plan_amount'   => ['required', 'numeric', 'min:0'],
            'offer_price'   => ['nullable', 'numeric', 'min:0', 'lt:plan_amount'],
            'currency'      => ['required', 'string', 'max:8'],
            'plan_unit'     => ['required', 'in:days,weeks,months,years'],
            'plan_duration' => ['required', 'integer', 'min:1'],
            'lifetime'      => ['nullable', 'boolean'],
            'status'        => ['nullable', 'boolean'],
            'sort_order'    => ['nullable', 'integer', 'min:0'],
            // Grants — the whole point of the add-on.
            'features'      => ['nullable', 'array'],
            'features.*'    => ['string'],
            'limits'        => ['nullable', 'array'],
            'limits.*'      => ['nullable', 'integer', 'min:-1'],
        ], [
            'offer_price.lt' => 'The offer price must be lower than the price.',
        ]);

        // Whitelist the submitted grants against the real column lists so a
        // forged key can't smuggle in a bogus feature.
        $validFeatures = AdminPagesController::planFeatureToggles();
        $validLimits   = AdminPagesController::planLimitColumns();

        $grantFeatures = array_values(array_intersect(
            array_map('strval', (array) ($data['features'] ?? [])),
            $validFeatures
        ));
        $grantLimits = [];
        foreach ((array) ($data['limits'] ?? []) as $key => $val) {
            // Blank = not granted. Only keep explicit numeric deltas (-1 = grant
            // unlimited, >0 = add N). 0 is treated as "blank" so it never leaks.
            if (!in_array($key, $validLimits, true)) continue;
            if ($val === '' || $val === null) continue;
            $n = (int) $val;
            if ($n === 0) continue;
            $grantLimits[$key] = $n;
        }

        // plan_id slug — keep on edit, derive+uniquify on create.
        if ($addon) {
            $planId = $addon->plan_id;
        } else {
            $slug = Str::slug($data['pname'] ?: 'addon', '_') ?: 'addon';
            $base = $slug; $i = 2;
            while (Package::where('plan_id', $slug)->exists()) $slug = $base . '_' . $i++;
            $planId = $slug;
        }

        $payload = [
            'type'          => Package::TYPE_ADDON,
            'plan_id'       => $planId,
            'pname'         => $data['pname'],
            'subtitle'      => $data['subtitle'] ?? null,
            'detail'        => $data['detail'] ?? null,
            'plan_amount'   => $data['plan_amount'],
            'offer_price'   => $data['offer_price'] ?? null,
            'currency'      => $data['currency'],
            'plan_unit'     => $data['plan_unit'],
            'plan_duration' => (int) $data['plan_duration'],
            'lifetime'      => $request->boolean('lifetime'),
            'status'        => $request->boolean('status'),
            'sort_order'    => (int) ($data['sort_order'] ?? 0),
            'grants_json'   => ['features' => $grantFeatures, 'limits' => $grantLimits],
        ];
        // Mirror granted feature FLAGS onto their raw columns too (harmless — any
        // code that reads $addon->access_campaigns directly still sees the grant).
        // Limit columns stay 0; grants_json holds the real deltas.
        foreach ($validFeatures as $f) {
            $payload[$f] = in_array($f, $grantFeatures, true);
        }

        if ($addon) {
            $addon->update($payload);
            return redirect()->route('admin.addons.index')->with('success', 'Add-on updated.');
        }
        Package::create($payload);
        return redirect()->route('admin.addons.index')->with('success', 'Add-on created.');
    }

    public function toggle(int $id): RedirectResponse
    {
        $addon = Package::query()->addons()->findOrFail($id);
        $addon->update(['status' => !$addon->status]);

        return back()->with('success', 'Add-on ' . ($addon->status ? 'enabled' : 'disabled') . '.');
    }

    public function destroy(int $id): RedirectResponse
    {
        $addon = Package::query()->addons()->findOrFail($id);
        $addon->delete();

        return redirect()->route('admin.addons.index')->with('success', 'Add-on deleted.');
    }

    /** Humanised labels for feature/limit keys shown on the form. */
    private function labels(): array
    {
        $pretty = function (string $k): string {
            $k = preg_replace('/^(access_|integration_)/', '', $k);
            $k = str_replace(['_limit', '_monthly'], ['', ' / month'], $k);
            return ucwords(str_replace('_', ' ', $k));
        };
        $out = [];
        foreach (array_merge(
            AdminPagesController::planFeatureToggles(),
            AdminPagesController::planLimitColumns()
        ) as $k) {
            $out[$k] = $pretty($k);
        }
        return $out;
    }
}
