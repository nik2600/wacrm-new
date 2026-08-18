<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Shopify / WooCommerce orders can arrive with NO phone number (guest
     * checkout, POS, draft orders, or a customer who only left an email). The
     * importer already stores `customer_phone => null` in that case, but the
     * column was created NOT NULL — so the entire wa_orders mirror insert threw
     * "Column 'customer_phone' cannot be null" and the order never synced into
     * the CRM at all (logged as "[ShopifyImporter] order upsert failed"). Making
     * the column nullable lets phone-less orders mirror normally; they just
     * can't be messaged on WhatsApp until a phone is captured.
     */
    public function up(): void
    {
        Schema::table('wa_orders', function (Blueprint $table) {
            $table->string('customer_phone', 32)->nullable()->change();
        });
    }

    public function down(): void
    {
        // Note: reverting will fail if any phone-less rows exist — clear them
        // first if you ever need to roll back.
        Schema::table('wa_orders', function (Blueprint $table) {
            $table->string('customer_phone', 32)->nullable(false)->change();
        });
    }
};
