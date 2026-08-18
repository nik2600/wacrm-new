<?php

namespace App\Http\Controllers;

use App\Models\Device;
use App\Models\WaOrder;
use App\Models\WaProduct;
use App\Models\WaStorefront;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Handles the /connect?platform=wa-store onboarding wizard:
 * one form, one POST that creates (or updates) the storefront
 * record so the user lands on /store ready to use. Also exposes
 * a workspace-scoped reset so they can wipe and start over.
 */
class WaStoreWizardController extends Controller
{
    public function save(Request $request): RedirectResponse
    {
        $user = Auth::user();
        $wsId = $user?->current_workspace_id;
        if (!$wsId) {
            return back()->withErrors(['shop_name' => 'No active workspace.']);
        }

        $data = $request->validate([
            'shop_id'       => ['nullable', 'integer'],
            'shop_name'     => ['required', 'string', 'max:191'],
            'custom_domain' => ['nullable', 'string', 'max:191', 'regex:/^[a-z0-9.-]+\.[a-z]{2,}$/'],
            'device_id'     => ['nullable', 'integer', 'exists:devices,id'],
        ], [
            'custom_domain.regex' => 'That doesn\'t look like a valid domain (e.g. shop.yourbiz.com).',
        ]);

        // Resolve which shop we're saving. Three paths:
        //   1) shop_id present → edit THAT shop (scoped to workspace)
        //   2) shop_id missing → create a new shop
        // Multi-shop: we no longer try to find "the" storefront for a
        // workspace — there can be many, so the form must tell us
        // which one (or none, for create).
        $existing = null;
        if (!empty($data['shop_id'])) {
            $existing = WaStorefront::where('workspace_id', $wsId)
                ->where('id', $data['shop_id'])
                ->first();
            if (!$existing) {
                return back()->withErrors(['shop_id' => 'Shop not found in this workspace.'])->withInput();
            }
        }

        // Slug is auto-derived from shop name. Editing the shop name
        // on an existing storefront does NOT rotate the slug — that
        // would silently break already-shared links. New storefronts
        // get a fresh, unique slug derived from the name.
        if ($existing) {
            $slug = $existing->slug;
        } else {
            $base = Str::slug($data['shop_name']) ?: 'shop';
            $slug = $base;
            $i = 2;
            while (WaStorefront::where('slug', $slug)->exists()) {
                $slug = $base . '-' . $i++;
            }
        }

        // Guard device pick — must be one of THIS user's connected devices.
        if (!empty($data['device_id'])) {
            $owns = Device::query()
                ->forCurrentWorkspace()
                ->where('id', $data['device_id'])
                ->where('status', 'connected')
                ->exists();
            if (!$owns) {
                return back()->withErrors(['device_id' => 'Pick one of your connected devices.'])->withInput();
            }
        }

        $sf = $existing ?: new WaStorefront(['workspace_id' => $wsId]);
        $sf->workspace_id = $wsId;
        $sf->shop_name    = $data['shop_name'];
        $sf->slug         = $slug;
        $sf->device_id    = $data['device_id'] ?? null;

        // Custom domain handling — reset verification if it changed.
        $newDomain = $data['custom_domain'] ?? null;
        if ($newDomain !== $sf->custom_domain) {
            $sf->custom_domain          = $newDomain;
            $sf->custom_domain_verified = false;
        }
        if (empty($sf->theme_key)) {
            $sf->theme_key = WaStorefront::DEFAULT_THEME;
        }
        $sf->enabled = true;
        $sf->save();

        // After save, send the operator to the multi-shop list so they
        // can see what they just created in context and add another or
        // jump into managing this one.
        return redirect('/connect?platform=wa-store')->with('status', $existing
            ? 'Shop "' . $sf->shop_name . '" updated.'
            : 'Shop "' . $sf->shop_name . '" created.');
    }

    public function reset(Request $request): RedirectResponse
    {
        $user = Auth::user();
        $wsId = $user?->current_workspace_id;
        if (!$wsId) {
            return back();
        }

        // Confirm gate — the form sends a hidden `confirm` field; bail
        // if it's missing so a stray reload can't nuke shop data by
        // accident on a stray reload.
        if ($request->input('confirm') !== 'yes') {
            return back()->withErrors(['confirm' => 'Reset not confirmed.']);
        }

        $shopId = (int) $request->input('shop_id');
        if ($shopId) {
            // Delete a single shop's data. Orders are scoped to the
            // storefront via storefront_id; products stay workspace-
            // scoped for now (a future migration can move them to per-
            // shop ownership). Note: we DON'T wipe workspace-wide
            // products when only one shop is being deleted.
            DB::transaction(function () use ($wsId, $shopId) {
                $shop = WaStorefront::where('workspace_id', $wsId)->where('id', $shopId)->first();
                if (!$shop) return;
                WaOrder::where('workspace_id', $wsId)->where('storefront_id', $shop->id)->delete();
                $shop->delete();
            });
            return redirect('/connect?platform=wa-store')->with('status', 'Shop deleted.');
        }

        // No shop_id → nuclear option: wipe ALL store data for this
        // workspace. Keep as the explicit "Reset everything" path.
        DB::transaction(function () use ($wsId) {
            WaOrder::where('workspace_id', $wsId)->delete();
            WaProduct::where('workspace_id', $wsId)->forceDelete();
            WaStorefront::where('workspace_id', $wsId)->delete();
        });

        return redirect('/connect?platform=wa-store')->with('status', 'All store data cleared for this workspace.');
    }
}
