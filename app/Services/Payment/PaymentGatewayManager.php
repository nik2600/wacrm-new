<?php

namespace App\Services\Payment;

use App\Models\PaymentGateway;
use App\Services\Payment\Drivers\AuthorizeNetDriver;
use App\Services\Payment\Drivers\BankTransferDriver;
use App\Services\Payment\Drivers\BraintreeDriver;
use App\Services\Payment\Drivers\CashfreeDriver;
use App\Services\Payment\Drivers\CinetpayDriver;
use App\Services\Payment\Drivers\CoinbaseDriver;
use App\Services\Payment\Drivers\DuitkuDriver;
use App\Services\Payment\Drivers\FlutterwaveDriver;
use App\Services\Payment\Drivers\FondyDriver;
use App\Services\Payment\Drivers\HyperpayDriver;
use App\Services\Payment\Drivers\InstamojoDriver;
use App\Services\Payment\Drivers\IyzicoDriver;
use App\Services\Payment\Drivers\MercadopagoDriver;
use App\Services\Payment\Drivers\MidtransDriver;
use App\Services\Payment\Drivers\MollieDriver;
use App\Services\Payment\Drivers\OfflineDriver;
use App\Services\Payment\Drivers\PaddleDriver;
use App\Services\Payment\Drivers\LemonSqueezyDriver;
use App\Services\Payment\Drivers\PayPalDriver;
use App\Services\Payment\Drivers\PaystackDriver;
use App\Services\Payment\Drivers\PaytmDriver;
use App\Services\Payment\Drivers\PaytrDriver;
use App\Services\Payment\Drivers\PayuDriver;
use App\Services\Payment\Drivers\PhonepeDriver;
use App\Services\Payment\Drivers\RazorpayDriver;
use App\Services\Payment\Drivers\SkrillDriver;
use App\Services\Payment\Drivers\SquareDriver;
use App\Services\Payment\Drivers\SslcommerzDriver;
use App\Services\Payment\Drivers\StripeDriver;
use App\Services\Payment\Drivers\TapDriver;
use App\Services\Payment\Drivers\TwocheckoutDriver;
use App\Services\Payment\Drivers\XenditDriver;
use RuntimeException;

/**
 * Strategy registry: slug → driver class. CheckoutController +
 * admin pages resolve drivers through here.
 *
 * Slugs match SnapNest's gateway naming so a future merge is a
 * copy-paste. All 30 drivers from the SnapNest catalog are now
 * registered (top-5 fully implemented, remaining 25 ported from
 * SnapNest's Composer-SDK-free implementations).
 */
class PaymentGatewayManager
{
    /**
     * Authoritative gateway registry. Drivers not in this map can't
     * be activated (the admin UI hides them).
     *
     * Slug → fully-qualified driver class.
     */
    public const DRIVER_MAP = [
        // Top-5: live drivers (fully tested in WaDesk).
        'stripe'         => StripeDriver::class,
        'razorpay'       => RazorpayDriver::class,
        'paypal'         => PayPalDriver::class,
        'bank_transfer'  => BankTransferDriver::class,
        'offline'        => OfflineDriver::class,

        // Ported from SnapNest — global / multi-region card processors.
        'mollie'         => MollieDriver::class,
        'paystack'       => PaystackDriver::class,
        'flutterwave'    => FlutterwaveDriver::class,
        'paytm'          => PaytmDriver::class,
        'square'         => SquareDriver::class,
        'braintree'      => BraintreeDriver::class,
        'twocheckout'    => TwocheckoutDriver::class,
        'coinbase'       => CoinbaseDriver::class,
        'mercadopago'    => MercadopagoDriver::class,
        'iyzico'         => IyzicoDriver::class,
        'paddle'         => PaddleDriver::class,
        'lemonsqueezy'   => LemonSqueezyDriver::class,
        'authorize_net'  => AuthorizeNetDriver::class,
        'sslcommerz'     => SslcommerzDriver::class,
        'instamojo'      => InstamojoDriver::class,
        'phonepe'        => PhonepeDriver::class,
        'cashfree'       => CashfreeDriver::class,
        'payu'           => PayuDriver::class,
        'midtrans'       => MidtransDriver::class,
        'duitku'         => DuitkuDriver::class,
        'xendit'         => XenditDriver::class,
        'tap'            => TapDriver::class,
        'hyperpay'       => HyperpayDriver::class,
        'paytr'          => PaytrDriver::class,
        'fondy'          => FondyDriver::class,
        'skrill'         => SkrillDriver::class,
        'cinetpay'       => CinetpayDriver::class,
    ];

    /**
     * Human-friendly metadata for the admin "Add gateway" picker.
     * Same slug keys as DRIVER_MAP.
     */
    public const GATEWAY_META = [
        // Top-5
        'stripe'         => ['name' => 'Stripe',          'desc' => 'Cards (Visa/MC/Amex), Apple Pay, Google Pay — global.'],
        'razorpay'       => ['name' => 'Razorpay',        'desc' => 'Indian cards, UPI, netbanking, wallets, EMI.'],
        'paypal'         => ['name' => 'PayPal',          'desc' => 'Worldwide PayPal balance + cards via PayPal Checkout.'],
        'bank_transfer'  => ['name' => 'Bank Transfer',   'desc' => 'Show your bank details; mark order paid manually.'],
        'offline'        => ['name' => 'Offline / Cash',  'desc' => 'For enterprise quotes paid out-of-band.'],

        // Ported from SnapNest
        'mollie'         => ['name' => 'Mollie',          'desc' => 'European cards, iDEAL, Bancontact, SEPA, Klarna.'],
        'paystack'       => ['name' => 'Paystack',        'desc' => 'Nigeria/Ghana/South Africa — cards, bank, USSD, mobile money.'],
        'flutterwave'    => ['name' => 'Flutterwave',     'desc' => 'Africa-wide — cards, mobile money, USSD, bank transfer.'],
        'paytm'          => ['name' => 'Paytm',           'desc' => 'India — Paytm wallet, UPI, cards, netbanking.'],
        'square'         => ['name' => 'Square',          'desc' => 'US/CA/UK/AU/JP cards via Square Online Checkout.'],
        'braintree'      => ['name' => 'Braintree',       'desc' => 'PayPal-owned: cards, PayPal, Venmo, Apple Pay, Google Pay.'],
        'twocheckout'    => ['name' => '2Checkout',       'desc' => '200+ countries — global cards + local payment methods.'],
        'coinbase'       => ['name' => 'Coinbase Commerce','desc' => 'Crypto — BTC, ETH, USDC, DAI, LTC, BCH.'],
        'mercadopago'    => ['name' => 'Mercado Pago',    'desc' => 'Latin America — cards, Pix, OXXO, Boleto, wallet.'],
        'iyzico'         => ['name' => 'iyzico',          'desc' => 'Turkey — cards with 3D Secure + installments.'],
        'paddle'         => ['name' => 'Paddle',          'desc' => 'Merchant-of-record for SaaS — cards + global tax handling.'],
        'lemonsqueezy'   => ['name' => 'Lemon Squeezy',   'desc' => 'Merchant-of-record for SaaS — hosted checkout, cards, global tax + subscriptions.'],
        'authorize_net'  => ['name' => 'Authorize.Net',   'desc' => 'US/CA cards via Accept Hosted payment page.'],
        'sslcommerz'     => ['name' => 'SSLCommerz',      'desc' => 'Bangladesh — cards, mobile banking (bKash, Nagad, Rocket).'],
        'instamojo'      => ['name' => 'Instamojo',       'desc' => 'India — cards, UPI, netbanking, wallets via payment links.'],
        'phonepe'        => ['name' => 'PhonePe',         'desc' => 'India — UPI, cards, wallets via PhonePe Business.'],
        'cashfree'       => ['name' => 'Cashfree',        'desc' => 'India — UPI, cards, netbanking, wallets, BNPL.'],
        'payu'           => ['name' => 'PayU',            'desc' => 'India / LatAm — cards, UPI, netbanking, wallets.'],
        'midtrans'       => ['name' => 'Midtrans',        'desc' => 'Indonesia — cards, GoPay, OVO, ShopeePay, bank transfer.'],
        'duitku'         => ['name' => 'Duitku',          'desc' => 'Indonesia — VA banks, QRIS, OVO, DANA, ShopeePay, retail (Alfamart/Indomaret), cards.'],
        'xendit'         => ['name' => 'Xendit',          'desc' => 'SE Asia — cards, e-wallets, virtual accounts, OTC retail.'],
        'tap'            => ['name' => 'Tap Payments',    'desc' => 'Middle East — KNET, mada, Benefit, cards in AED/SAR/KWD.'],
        'hyperpay'       => ['name' => 'HyperPay',        'desc' => 'Middle East / Africa — cards (mada, VISA/MC/AMEX) + STC Pay.'],
        'paytr'          => ['name' => 'PayTR',           'desc' => 'Turkey — cards with installments via secure iframe.'],
        'fondy'          => ['name' => 'Fondy',           'desc' => 'Eastern Europe — cards, Apple Pay, Google Pay, SEPA.'],
        'skrill'         => ['name' => 'Skrill',          'desc' => 'Skrill wallet + 100+ local methods worldwide.'],
        'cinetpay'       => ['name' => 'CinetPay',        'desc' => 'Francophone West Africa — Orange Money, MTN, Moov, cards.'],
    ];

    /** Resolve a driver instance by slug. */
    public function driver(string $slug): AbstractGatewayDriver
    {
        $gateway = PaymentGateway::query()->where('slug', $slug)->first();
        if (!$gateway) throw new RuntimeException('Unknown gateway: ' . $slug);
        return $this->driverFromModel($gateway);
    }

    public function driverFromModel(PaymentGateway $gateway): AbstractGatewayDriver
    {
        $cls = self::DRIVER_MAP[$gateway->slug] ?? null;
        if (!$cls) throw new RuntimeException('No driver registered for slug: ' . $gateway->slug);
        return new $cls($gateway);
    }

    /** All active gateways, ordered by sort_order. Used at /checkout. */
    public function activeGateways(?string $currency = null)
    {
        $q = PaymentGateway::query()->active()->orderBy('sort_order')->orderBy('id');
        $gateways = $q->get();
        if ($currency) {
            $gateways = $gateways->filter(fn ($g) => $g->acceptsCurrency($currency))->values();
        }
        return $gateways;
    }

    /** Static `credentialFields()` for a slug — driving the admin form. */
    public function credentialFieldsFor(string $slug): array
    {
        $cls = self::DRIVER_MAP[$slug] ?? null;
        if (!$cls) return [];
        return $cls::credentialFields();
    }

    /** Slugs registered AND not yet a PaymentGateway row (for "Add" picker). */
    public function installableSlugs(): array
    {
        $existing = PaymentGateway::query()->pluck('slug')->all();
        return array_diff(array_keys(self::DRIVER_MAP), $existing);
    }

    /**
     * Self-heal: create a PaymentGateway row for every catalog gateway that has
     * a driver but no DB row yet. Lets a newly-added driver (e.g. Lemon Squeezy
     * added after the initial seed) appear on /admin/payment-gateways without a
     * re-seed. Idempotent — never touches existing rows or their credentials.
     * New rows start INACTIVE in sandbox so nothing goes live by accident.
     */
    public function ensureCatalogRows(): int
    {
        $existing = PaymentGateway::query()->pluck('slug')->all();
        $missing  = array_diff(array_keys(self::DRIVER_MAP), $existing);
        if (empty($missing)) return 0;

        $sort = (int) PaymentGateway::max('sort_order');
        $created = 0;
        foreach ($missing as $slug) {
            $meta = self::GATEWAY_META[$slug] ?? ['name' => ucfirst($slug), 'desc' => null];
            PaymentGateway::create([
                'slug'        => $slug,
                'name'        => $meta['name'],
                'description' => $meta['desc'] ?? null,
                'is_active'   => false,
                'mode'        => 'sandbox',
                'sort_order'  => ++$sort,
            ]);
            $created++;
        }
        return $created;
    }
}
