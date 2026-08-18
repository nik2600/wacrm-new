<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Two parallel ecommerce features per storefront:
 *   • shipping_json — flat fee + free-above threshold, optional zones
 *   • payment_provider + payment_config_json — generate a payment link
 *     to embed in the WhatsApp order message (no checkout in-app — buyer
 *     pays via the link, seller confirms in chat)
 *
 * Both nullable so existing storefronts keep working unchanged
 * (free shipping, no payment link).
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('wa_storefronts', function (Blueprint $table) {
            $table->json('shipping_json')->nullable()->after('settings_json');
            $table->string('payment_provider', 32)->nullable()->after('shipping_json');
            $table->json('payment_config_json')->nullable()->after('payment_provider');
            $table->string('currency_code', 3)->default('INR')->after('payment_config_json');
        });
    }

    public function down(): void
    {
        Schema::table('wa_storefronts', function (Blueprint $table) {
            $table->dropColumn(['shipping_json', 'payment_provider', 'payment_config_json', 'currency_code']);
        });
    }
};
