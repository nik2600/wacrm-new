<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Back-in-stock waitlist. A customer who messages asking to be notified
 * about an out-of-stock product lands here; when the product is restocked
 * (products/update webhook), everyone waiting is messaged automatically.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shopify_stock_waitlist', function (Blueprint $table) {
            $table->id();
            $table->foreignId('integration_id')->index();
            $table->unsignedBigInteger('workspace_id')->index();
            $table->string('shopify_product_id', 32)->nullable()->index();
            $table->string('product_name', 191)->nullable();
            $table->string('customer_phone', 32);
            $table->string('status', 16)->default('waiting'); // waiting | notified
            $table->timestamp('notified_at')->nullable();
            $table->timestamps();
            $table->index(['workspace_id', 'shopify_product_id', 'status'], 'stock_wl_ws_prod_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shopify_stock_waitlist');
    }
};
