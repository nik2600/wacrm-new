<?php

namespace Database\Seeders;

use App\Models\PaymentGateway;
use Illuminate\Database\Seeder;

/**
 * Pre-populates the `payment_gateways` table with every gateway the
 * Strategy registry supports (PaymentGatewayManager::DRIVER_MAP). Admin
 * sees all 30 directly at /admin/payment-gateways — no install step.
 * They just paste keys + flip Active.
 *
 * Idempotent: firstOrCreate by slug, never overwrites credentials.
 */
class PaymentGatewaySeeder extends Seeder
{
    public function run(): void
    {
        $gateways = [
            ['slug' => 'stripe',         'name' => 'Stripe',              'description' => 'Global cards + Apple Pay + Google Pay across 46+ countries.',                        'sort_order' => 1],
            ['slug' => 'paypal',         'name' => 'PayPal',              'description' => 'Worldwide PayPal balance + cards via PayPal Checkout.',                            'sort_order' => 2],
            ['slug' => 'razorpay',       'name' => 'Razorpay',            'description' => 'India — cards, UPI, netbanking, wallets, EMI.',                                    'sort_order' => 3],
            ['slug' => 'paytm',          'name' => 'Paytm',               'description' => 'India — Paytm wallet, UPI, cards, netbanking.',                                    'sort_order' => 4],
            ['slug' => 'mollie',         'name' => 'Mollie',              'description' => 'European cards, iDEAL, Bancontact, SEPA, Klarna.',                                 'sort_order' => 5],
            ['slug' => 'paystack',       'name' => 'Paystack',            'description' => 'Nigeria/Ghana/South Africa — cards, bank, USSD, mobile money.',                    'sort_order' => 6],
            ['slug' => 'flutterwave',    'name' => 'Flutterwave',         'description' => 'Africa-wide — cards, mobile money, USSD, bank transfer.',                          'sort_order' => 7],
            ['slug' => 'square',         'name' => 'Square',              'description' => 'US/CA/UK/AU/JP cards via Square Online Checkout.',                                 'sort_order' => 8],
            ['slug' => 'braintree',      'name' => 'Braintree',           'description' => 'PayPal-owned: cards, PayPal, Venmo, Apple Pay, Google Pay.',                       'sort_order' => 9],
            ['slug' => 'twocheckout',    'name' => '2Checkout (Verifone)','description' => '200+ countries — global cards + local payment methods.',                          'sort_order' => 10],
            ['slug' => 'coinbase',       'name' => 'Coinbase Commerce',   'description' => 'Crypto — BTC, ETH, USDC, DAI, LTC, BCH.',                                          'sort_order' => 11],
            ['slug' => 'mercadopago',    'name' => 'Mercado Pago',        'description' => 'Latin America — cards, Pix, OXXO, Boleto, wallet.',                               'sort_order' => 12],
            ['slug' => 'iyzico',         'name' => 'iyzico',              'description' => 'Turkey — cards with 3D Secure + installments.',                                    'sort_order' => 13],
            ['slug' => 'paddle',         'name' => 'Paddle',              'description' => 'Merchant of record for SaaS — cards + global tax handling.',                       'sort_order' => 14],
            ['slug' => 'lemonsqueezy',   'name' => 'Lemon Squeezy',       'description' => 'Merchant of record for SaaS — hosted checkout, cards, global tax + subscriptions.', 'sort_order' => 31],
            ['slug' => 'authorize_net',  'name' => 'Authorize.Net',       'description' => 'US/CA cards via Accept Hosted payment page.',                                      'sort_order' => 15],
            ['slug' => 'sslcommerz',     'name' => 'SSLCommerz',          'description' => 'Bangladesh — cards, bKash, Nagad, Rocket.',                                        'sort_order' => 16],
            ['slug' => 'instamojo',      'name' => 'Instamojo',           'description' => 'India — cards, UPI, netbanking, wallets via payment links.',                       'sort_order' => 17],
            ['slug' => 'phonepe',        'name' => 'PhonePe',             'description' => 'India — UPI, cards, wallets via PhonePe Business.',                                'sort_order' => 18],
            ['slug' => 'cashfree',       'name' => 'Cashfree',            'description' => 'India — UPI, cards, netbanking, wallets, BNPL.',                                   'sort_order' => 19],
            ['slug' => 'payu',           'name' => 'PayU',                'description' => 'India / LatAm — cards, UPI, netbanking, wallets.',                                 'sort_order' => 20],
            ['slug' => 'midtrans',       'name' => 'Midtrans',            'description' => 'Indonesia — cards, GoPay, OVO, ShopeePay, bank transfer.',                         'sort_order' => 21],
            ['slug' => 'duitku',         'name' => 'Duitku',              'description' => 'Indonesia — VA banks, QRIS, OVO, DANA, ShopeePay, retail (Alfamart/Indomaret), cards.', 'sort_order' => 22],
            ['slug' => 'xendit',         'name' => 'Xendit',              'description' => 'SE Asia — cards, e-wallets, virtual accounts, OTC retail.',                        'sort_order' => 23],
            ['slug' => 'tap',            'name' => 'Tap Payments',        'description' => 'Middle East — KNET, mada, Benefit, cards in AED/SAR/KWD.',                         'sort_order' => 23],
            ['slug' => 'hyperpay',       'name' => 'HyperPay',            'description' => 'Middle East / Africa — cards (mada, Visa/MC/Amex) + STC Pay.',                     'sort_order' => 24],
            ['slug' => 'paytr',          'name' => 'PayTR',               'description' => 'Turkey — cards with installments via secure iframe.',                              'sort_order' => 25],
            ['slug' => 'fondy',          'name' => 'Fondy',               'description' => 'Eastern Europe — cards, Apple Pay, Google Pay, SEPA.',                             'sort_order' => 26],
            ['slug' => 'skrill',         'name' => 'Skrill',              'description' => 'Skrill wallet + 100+ local methods worldwide.',                                    'sort_order' => 27],
            ['slug' => 'cinetpay',       'name' => 'CinetPay',            'description' => 'Francophone West Africa — Orange Money, MTN, Moov, cards.',                        'sort_order' => 28],
            ['slug' => 'bank_transfer',  'name' => 'Bank Transfer',       'description' => 'Show your bank details; mark order paid manually.',                                'sort_order' => 29],
            ['slug' => 'offline',        'name' => 'Offline / Cash',      'description' => 'For enterprise quotes paid out-of-band.',                                          'sort_order' => 30],
        ];

        // Per-gateway currency whitelists (sourced from each provider's
        // official docs). Empty array = accepts any currency (bank
        // transfer + offline cover that case).
        $currencyMap = [
            'stripe'        => ['USD','EUR','GBP','JPY','CAD','AUD','CHF','INR','BRL','MXN','SGD','HKD','NOK','SEK','DKK','PLN','CZK','RON','HUF','NZD','MYR','THB','PHP','IDR','TRY','ZAR','KES','NGN','AED','SAR','EGP','ILS','KRW','TWD','CNY'],
            'paypal'        => ['USD','EUR','GBP','JPY','CAD','AUD','CHF','INR','BRL','MXN','SGD','HKD','NOK','SEK','DKK','PLN','CZK','HUF','NZD','PHP','THB','TRY','ILS','TWD','CNY','RUB'],
            'razorpay'      => ['INR'],
            'paytm'         => ['INR'],
            'mollie'        => ['EUR','USD','GBP','CHF','SEK','NOK','DKK','PLN','CZK','HUF','RON'],
            'paystack'      => ['NGN','GHS','ZAR','USD','KES'],
            'flutterwave'   => ['NGN','USD','KES','EUR','GBP','GHS','ZAR','TZS','UGX','RWF','XOF','XAF'],
            'square'        => ['USD','CAD','GBP','AUD','JPY','EUR'],
            'braintree'     => ['USD','EUR','GBP','CAD','AUD','JPY','CHF','NOK','SEK','DKK','HKD','SGD','NZD','BRL','MXN','PLN','CZK','HUF'],
            'twocheckout'   => ['USD','EUR','GBP','JPY','CAD','AUD','CHF','BRL','MXN','SGD','HKD','NOK','SEK','DKK','PLN','CZK','HUF','RON','TRY','ZAR','INR','NZD','ILS'],
            'coinbase'      => ['USD','EUR','GBP','BTC','ETH','USDC'],
            'mercadopago'   => ['BRL','ARS','MXN','CLP','COP','PEN','UYU'],
            'iyzico'        => ['TRY','USD','EUR','GBP'],
            'paddle'        => ['USD','EUR','GBP','AUD','CAD','CHF','SEK','NOK','DKK','PLN','CZK','HUF','BRL','SGD','HKD','NZD','MXN','JPY','KRW','TWD','TRY','ZAR','INR','THB','ARS'],
            'authorize_net' => ['USD','CAD','EUR','GBP','AUD'],
            'sslcommerz'    => ['BDT'],
            'instamojo'     => ['INR'],
            'phonepe'       => ['INR'],
            'cashfree'      => ['INR'],
            'payu'          => ['INR'],
            'midtrans'      => ['IDR'],
            'duitku'        => ['IDR'],
            'xendit'        => ['IDR','PHP','USD','VND','MYR','THB'],
            'tap'           => ['KWD','SAR','AED','BHD','QAR','OMR','JOD','EGP','USD','EUR','GBP'],
            'hyperpay'      => ['SAR','AED','USD','EUR','GBP','BHD','KWD','QAR','OMR','JOD','EGP'],
            'paytr'         => ['TRY','USD','EUR','GBP'],
            'fondy'         => ['EUR','USD','GBP','UAH','PLN','CZK','RON','HUF','RUB'],
            'skrill'        => ['EUR','USD','GBP','CAD','AUD','CHF','SEK','NOK','DKK','PLN','CZK','HUF','RON','TRY','BRL'],
            'cinetpay'      => ['XOF','XAF','CDF','GNF','USD'],
            'bank_transfer' => [],
            'offline'       => [],
        ];

        foreach ($gateways as $row) {
            PaymentGateway::firstOrCreate(
                ['slug' => $row['slug']],
                [
                    'name'                 => $row['name'],
                    'description'          => $row['description'],
                    'is_active'            => false,
                    'mode'                 => 'sandbox',
                    'sort_order'           => $row['sort_order'],
                    'supported_currencies' => $currencyMap[$row['slug']] ?? [],
                ]
            );
        }

        // Backfill supported_currencies on rows that pre-existed
        // (the 5 gateways installed during phase-4 testing didn't have
        // them set). Never touches `credentials`.
        foreach ($currencyMap as $slug => $currencies) {
            PaymentGateway::where('slug', $slug)
                ->whereNull('supported_currencies')
                ->update(['supported_currencies' => json_encode($currencies)]);
        }
    }
}
