<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SystemSetting;
use App\Support\Audit;
use Illuminate\Http\Request;

/**
 * Admin /checkout-settings — one screen to control every "soft" value
 * that drives /pricing and /checkout:
 *   - tax rate + label  (or turn tax off entirely)
 *   - refund guarantee days  (or turn the guarantee off)
 *   - yearly billing discount %
 *   - the country dropdown options
 *
 * Each toggle / number flips a SystemSetting K/V row. The pricing +
 * checkout views read from these on every render so changes go live
 * the moment admin clicks Save — no cache invalidation needed.
 */
class CheckoutSettingsController extends Controller
{
    public function index()
    {
        $countriesRaw = SystemSetting::get('checkout.countries', null);
        if (is_array($countriesRaw)) {
            $countries = $countriesRaw;
        } else {
            $decoded   = json_decode((string) $countriesRaw, true);
            $countries = is_array($decoded) ? $decoded : [];
        }

        return view('admin.checkout-settings.index', [
            'taxEnabled'         => (bool) SystemSetting::get('checkout.tax_enabled', true),
            'taxRate'            => (int)  SystemSetting::get('checkout.tax_rate', 18),
            'taxLabel'           => (string) SystemSetting::get('checkout.tax_label', 'GST'),
            'refundEnabled'      => (bool) SystemSetting::get('pricing.refund_enabled', true),
            'refundDays'         => (int)  SystemSetting::get('pricing.refund_days', 7),
            'yearlyEnabled'      => (bool) SystemSetting::get('pricing.yearly_toggle_enabled', true),
            'yearlyDiscountPct'  => (int)  SystemSetting::get('pricing.yearly_discount_pct', 20),
            // Auto-renewing subscriptions (Stripe / Razorpay / PayPal). When on,
            // timed paid plans bought through those gateways recur automatically;
            // every other gateway stays a one-time charge.
            'recurringEnabled'   => (bool) SystemSetting::get('pricing.recurring_enabled', true),
            'countries'          => implode("\n", $countries),
            // Company / billing identity — printed on every invoice.
            'companyName'        => (string) SystemSetting::get('billing.company', ''),
            'companyAddress'     => (string) SystemSetting::get('billing.address', ''),
            'companyTaxId'       => (string) SystemSetting::get('billing.tax_id', ''),
            'companyRegNo'       => (string) SystemSetting::get('billing.reg_no', ''),
            'companyEmail'       => (string) SystemSetting::get('billing.email', ''),
            'companyPhone'       => (string) SystemSetting::get('billing.phone', ''),
        ]);
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'tax_enabled'           => ['nullable', 'in:0,1,on'],
            'tax_rate'              => ['required', 'integer', 'min:0', 'max:100'],
            'tax_label'             => ['required', 'string', 'max:32'],
            'refund_enabled'        => ['nullable', 'in:0,1,on'],
            'refund_days'           => ['required', 'integer', 'min:0', 'max:365'],
            'yearly_toggle_enabled' => ['nullable', 'in:0,1,on'],
            'yearly_discount_pct'   => ['required', 'integer', 'min:0', 'max:100'],
            'recurring_enabled'     => ['nullable', 'in:0,1,on'],
            'countries'             => ['nullable', 'string'],
            'company_name'          => ['nullable', 'string', 'max:160'],
            'company_address'       => ['nullable', 'string', 'max:500'],
            'company_tax_id'        => ['nullable', 'string', 'max:64'],
            'company_reg_no'        => ['nullable', 'string', 'max:64'],
            'company_email'         => ['nullable', 'email', 'max:160'],
            'company_phone'         => ['nullable', 'string', 'max:40'],
        ]);

        // Boolean toggles. Unchecked checkboxes don't POST, so a
        // missing key means OFF — and the hidden input fallback in
        // the form sends '0' explicitly.
        SystemSetting::set('checkout.tax_enabled',          !empty($data['tax_enabled']),    'bool');
        SystemSetting::set('checkout.tax_rate',             (int) $data['tax_rate'],         'int');
        SystemSetting::set('checkout.tax_label',            $data['tax_label'],              'string');
        SystemSetting::set('pricing.refund_enabled',        !empty($data['refund_enabled']), 'bool');
        SystemSetting::set('pricing.refund_days',           (int) $data['refund_days'],      'int');
        SystemSetting::set('pricing.yearly_toggle_enabled', !empty($data['yearly_toggle_enabled']), 'bool');
        SystemSetting::set('pricing.yearly_discount_pct',   (int) $data['yearly_discount_pct'], 'int');
        SystemSetting::set('pricing.recurring_enabled',     !empty($data['recurring_enabled']), 'bool');

        // Countries: one per line, trimmed, deduped.
        $countries = collect(preg_split('/[\r\n]+/', (string) ($data['countries'] ?? '')))
            ->map(fn ($c) => trim($c))
            ->filter()
            ->unique()
            ->values()
            ->all();
        SystemSetting::set('checkout.countries', json_encode($countries), 'json');

        // Company / billing identity — printed on invoices.
        SystemSetting::set('billing.company', (string) ($data['company_name'] ?? ''),    'string');
        SystemSetting::set('billing.address', (string) ($data['company_address'] ?? ''), 'string');
        SystemSetting::set('billing.tax_id',  (string) ($data['company_tax_id'] ?? ''),  'string');
        SystemSetting::set('billing.reg_no',  (string) ($data['company_reg_no'] ?? ''),  'string');
        SystemSetting::set('billing.email',   (string) ($data['company_email'] ?? ''),   'string');
        SystemSetting::set('billing.phone',   (string) ($data['company_phone'] ?? ''),   'string');

        Audit::log('admin.checkout_settings.updated', [
            'meta' => [
                'tax_enabled'           => !empty($data['tax_enabled']),
                'tax_rate'              => (int) $data['tax_rate'],
                'tax_label'             => $data['tax_label'],
                'refund_enabled'        => !empty($data['refund_enabled']),
                'refund_days'           => (int) $data['refund_days'],
                'yearly_toggle_enabled' => !empty($data['yearly_toggle_enabled']),
                'yearly_discount_pct'   => (int) $data['yearly_discount_pct'],
                'recurring_enabled'     => !empty($data['recurring_enabled']),
                'country_count'         => count($countries),
            ],
        ]);

        return back()->with('success', 'Checkout settings saved.');
    }
}
