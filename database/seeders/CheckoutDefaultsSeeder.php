<?php

namespace Database\Seeders;

use App\Models\Coupon;
use App\Models\PricingFaq;
use App\Models\SystemSetting;
use Illuminate\Database\Seeder;

/**
 * Defaults so /pricing and /checkout render meaningful content on
 * a fresh install. Admin can edit any of these from
 *   /admin/coupons         — coupons CRUD
 *   /admin/pricing-faqs    — FAQ CRUD (todo)
 *   /admin/settings/general — tax rate, yearly discount, countries.
 *
 * All SystemSetting entries are upserted (set() is idempotent), so
 * re-running this seeder never overwrites admin edits.
 */
class CheckoutDefaultsSeeder extends Seeder
{
    public function run(): void
    {
        // ── System-wide settings the checkout pages read ──
        SystemSetting::set('pricing.yearly_discount_pct', 20, 'int',
            '% off when the user picks yearly billing on /pricing.');

        SystemSetting::set('checkout.tax_rate', 18, 'int',
            'Default tax rate applied on checkout (jurisdiction-specific — set to 0 for tax-inclusive prices).');

        SystemSetting::set('checkout.tax_label', 'GST', 'string',
            'Label shown next to the tax line on the order summary (GST / VAT / Sales tax / …).');

        SystemSetting::set('checkout.countries', json_encode([
            'India', 'United States', 'United Kingdom', 'UAE', 'Canada',
            'Australia', 'Singapore', 'Germany', 'France', 'Netherlands',
            'Spain', 'Italy', 'Brazil', 'Mexico', 'Japan', 'South Korea',
            'Indonesia', 'Philippines', 'Malaysia', 'Thailand', 'Vietnam',
            'South Africa', 'Nigeria', 'Kenya', 'Other',
        ]), 'json', 'Country dropdown options on the checkout billing form.');

        SystemSetting::set('pricing.refund_days', 7, 'int',
            'Number of days the money-back guarantee is valid for.');

        // ── FAQ defaults — only seeded when table is empty ──
        if (PricingFaq::query()->count() === 0) {
            foreach ([
                ['Can I switch plans any time?',                'Yes. Upgrade or downgrade in one click — we prorate the charge to the day so you only pay for what you use.', 1],
                ['What happens if I exceed my message quota?',  'Extra messages tap your wallet credits. Top up automatically or set a monthly cap to avoid surprises.',          2],
                ['Do you offer a refund?',                      '7-day money-back on all plans. No questions, refund hits within 5 working days.',                                3],
                ['How is yearly billing 20% cheaper?',          'When you commit to a year, our infrastructure costs are predictable — we pass that saving back to you.',         4],
                ['Can I use my own payment gateway?',           'Yes. Admin can activate any of 30 gateways under /admin/payment-gateways — Stripe, Razorpay, PayPal, and more.',  5],
                ['Is there a free tier?',                       'The Starter plan is free forever for solo founders trying us out. Upgrade only when you outgrow it.',            6],
            ] as [$q, $a, $order]) {
                PricingFaq::create(['question' => $q, 'answer' => $a, 'sort_order' => $order, 'is_active' => true]);
            }
        }

        // ── Demo coupons — only when no coupons exist yet ──
        if (Coupon::query()->count() === 0) {
            Coupon::create([
                'code'        => 'WELCOME10',
                'description' => '10% off your first plan purchase',
                'type'        => 'percent',
                'amount'      => 10,
                'is_active'   => true,
            ]);
            Coupon::create([
                'code'        => 'SAVE20',
                'description' => '20% off any plan',
                'type'        => 'percent',
                'amount'      => 20,
                'is_active'   => true,
            ]);
        }
    }
}
