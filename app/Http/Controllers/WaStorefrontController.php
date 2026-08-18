<?php

namespace App\Http\Controllers;

use App\Models\WaStorefront;
use Illuminate\Contracts\Support\Renderable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class WaStorefrontController extends Controller
{
    public function edit(): Renderable|RedirectResponse
    {
        $wsId = Auth::user()->current_workspace_id;
        $sf = $wsId ? WaStorefront::where('workspace_id', $wsId)->first() : null;
        if (!$sf) {
            // Same rule as /store — no storefront yet means the user
            // hasn't completed the onboarding wizard. Send them there
            // instead of silently creating an empty row.
            return redirect('/connect?platform=wa-store');
        }
        $themes = WaStorefront::THEMES;
        return view('user.store.storefront.edit', compact('sf', 'themes'));
    }

    public function update(Request $request): RedirectResponse
    {
        $wsId = Auth::user()->current_workspace_id;
        $sf = WaStorefront::firstOrCreate(['workspace_id' => $wsId], ['theme_key' => WaStorefront::DEFAULT_THEME]);

        $data = $request->validate([
            'slug'         => ['required', 'string', 'max:64', 'regex:/^[a-z0-9-]+$/', 'unique:wa_storefronts,slug,' . $sf->id],
            'theme_key'    => ['required', 'string', 'in:' . implode(',', array_keys(WaStorefront::THEMES))],
            'enabled'      => ['sometimes', 'boolean'],
            'custom_domain'=> ['nullable', 'string', 'max:191', 'regex:/^[a-z0-9.-]+\.[a-z]{2,}$/'],
            'logo_url'     => ['nullable', 'url', 'max:1024'],
            'brand_color'  => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'hero_text'    => ['nullable', 'string', 'max:280'],
            'footer_text'  => ['nullable', 'string', 'max:280'],
            // S3 — abandoned-cart recovery
            'cart_recovery_enabled'   => ['nullable', 'boolean'],
            'cart_recovery_delay_min' => ['nullable', 'integer', 'min:5', 'max:1440'],
            'cart_recovery_message'   => ['nullable', 'string', 'max:1024'],
            'currency_code'=> ['nullable', 'string', 'size:3', 'in:INR,USD,EUR,GBP,AED,KES,NGN,ZAR,BRL,MXN,CRC,PHP,IDR,SGD,MYR,THB,VND,EGP,PKR,BDT,LKR'],
            // Shipping
            'shipping_flat'       => ['nullable', 'numeric', 'min:0', 'max:1000000'],
            'shipping_free_above' => ['nullable', 'numeric', 'min:0', 'max:1000000'],
            'shipping_note'       => ['nullable', 'string', 'max:160'],
            // Payment
            'payment_provider'    => ['nullable', 'string', 'in:upi,razorpay_link,razorpay_api,stripe_link,paypal_me,bank_transfer'],
            'payment_handle'      => ['nullable', 'string', 'max:255'],
            // Razorpay API (auto payment links + webhook auto-confirm)
            'rzp_key_id'          => ['nullable', 'string', 'max:191'],
            'rzp_key_secret'      => ['nullable', 'string', 'max:191'],
            'rzp_webhook_secret'  => ['nullable', 'string', 'max:191'],
        ]);

        $domainChanged = ($data['custom_domain'] ?? null) !== $sf->custom_domain;

        // Build shipping_json — null when both fields blank so the
        // accessor reports "no shipping config" cleanly.
        $shipping = array_filter([
            'flat_minor'        => !empty($data['shipping_flat'])       ? (int) round(((float) $data['shipping_flat']) * 100) : 0,
            'free_above_minor'  => !empty($data['shipping_free_above']) ? (int) round(((float) $data['shipping_free_above']) * 100) : 0,
            'note'              => $data['shipping_note'] ?? null,
        ], fn ($v) => $v !== null && $v !== 0 && $v !== '');

        if (($data['payment_provider'] ?? null) === 'razorpay_api') {
            // Secrets: encrypt at rest; keep the existing value when the field
            // is left blank (so re-saving the form doesn't wipe stored keys).
            $existing = is_array($sf->payment_config_json) ? $sf->payment_config_json : [];
            $paymentCfg = array_filter([
                'key_id'         => trim((string) ($data['rzp_key_id'] ?? '')) ?: ($existing['key_id'] ?? null),
                'key_secret'     => !empty($data['rzp_key_secret'])
                    ? \App\Services\Storefront\StorefrontPaymentService::encryptSecret($data['rzp_key_secret'])
                    : ($existing['key_secret'] ?? null),
                'webhook_secret' => !empty($data['rzp_webhook_secret'])
                    ? \App\Services\Storefront\StorefrontPaymentService::encryptSecret($data['rzp_webhook_secret'])
                    : ($existing['webhook_secret'] ?? null),
            ], fn ($v) => $v !== null && $v !== '');
        } else {
            $paymentCfg = !empty($data['payment_handle'])
                ? ['handle' => $data['payment_handle']]
                : null;
        }

        $sf->fill([
            'slug'      => Str::slug($data['slug']),
            'theme_key' => $data['theme_key'],
            'enabled'   => (bool) ($data['enabled'] ?? true),
            'custom_domain' => $data['custom_domain'] ?? null,
            'currency_code' => $data['currency_code'] ?? ($sf->currency_code ?? 'INR'),
            'shipping_json' => $shipping ?: null,
            'payment_provider' => $data['payment_provider'] ?? null,
            'payment_config_json' => $paymentCfg,
            'settings_json' => array_filter([
                'logo_url'    => $data['logo_url']    ?? null,
                'brand_color' => $data['brand_color'] ?? null,
                'hero_text'   => $data['hero_text']   ?? null,
                'footer_text' => $data['footer_text'] ?? null,
            ]) + [
                // Recovery keys kept even when falsey (so delay/message persist).
                'cart_recovery_enabled'   => (bool) ($data['cart_recovery_enabled'] ?? false),
                'cart_recovery_delay_min' => (int) ($data['cart_recovery_delay_min'] ?? 30),
                'cart_recovery_message'   => trim((string) ($data['cart_recovery_message'] ?? '')) ?: null,
            ],
        ]);
        if ($domainChanged) {
            $sf->custom_domain_verified = false;
        }
        $sf->save();

        return redirect()->route('user.store.storefront.edit')->with('status', 'Storefront updated.');
    }

    public function verifyDomain(Request $request): JsonResponse
    {
        $wsId = Auth::user()->current_workspace_id;
        $sf = WaStorefront::where('workspace_id', $wsId)->firstOrFail();

        if (!$sf->custom_domain) {
            return response()->json(['ok' => false, 'message' => 'No custom domain set.'], 422);
        }

        $expected = config('storefront.cname_target', parse_url(config('app.url'), PHP_URL_HOST) ?: 'localhost');
        $records = @dns_get_record($sf->custom_domain, DNS_CNAME) ?: [];
        $matched = false;
        foreach ($records as $r) {
            if (isset($r['target']) && stripos($r['target'], $expected) !== false) {
                $matched = true;
                break;
            }
        }
        $sf->custom_domain_verified = $matched;
        $sf->save();

        return response()->json([
            'ok'       => $matched,
            'expected' => $expected,
            'records'  => $records,
            'message'  => $matched ? 'Domain verified ✓' : 'CNAME not pointing to ' . $expected,
        ]);
    }
}
