<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * wa_order_items — line items for catalog-driven orders.
 *
 * The existing wa_orders table stashes items in `items_json` which
 * works for old/manual orders but blocks SQL joins on wa_products.
 * For WABA-driven orders we want proper joins:
 *   • report "top-selling products this month" via JOIN
 *   • show product images on the order detail page (LEFT JOIN)
 *   • feed analytics tables without parsing JSON
 *
 * Old orders keep using items_json (we don't migrate); new
 * catalog orders write to both for backwards-compat with the
 * existing /store/orders rendering.
 *
 * price_minor is the per-unit price in MINOR units. Catalog
 * order webhooks arrive as decimal MAJOR units (item_price=10.99)
 * — the inbound handler converts them on save.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('wa_order_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('order_id');
            $table->unsignedBigInteger('product_id')->nullable(); // null if buyer ordered an SKU we don't recognise
            $table->string('retailer_id', 100); // SKU as Meta sees it
            $table->string('name', 191);          // denormalised at order time
            $table->string('image_url', 1024)->nullable();
            $table->unsignedInteger('quantity')->default(1);
            $table->unsignedBigInteger('price_minor')->default(0);
            $table->string('currency_code', 3)->default('INR');
            $table->json('meta_json')->nullable();
            $table->timestamps();

            $table->index('order_id');
            $table->index('product_id');
            $table->index('retailer_id');
            $table->foreign('order_id')->references('id')->on('wa_orders')->cascadeOnDelete();
            $table->foreign('product_id')->references('id')->on('wa_products')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wa_order_items');
    }
};
