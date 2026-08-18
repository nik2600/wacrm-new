<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * S3 — storefront abandoned-cart recovery. When a buyer enters their phone
 * at checkout but doesn't place the order, we record the cart here and
 * schedule a WhatsApp nudge (via the existing Node scheduler). If they
 * complete the order, the pending nudge is cancelled.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('storefront_cart_recoveries', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('workspace_id')->index();
            $table->unsignedBigInteger('storefront_id')->index();
            $table->string('customer_phone', 32)->index();
            $table->string('customer_name', 120)->nullable();
            $table->json('items_json')->nullable();
            $table->bigInteger('subtotal_minor')->default(0);
            $table->string('currency_code', 3)->default('INR');
            $table->json('scheduled_ids')->nullable();      // ScheduledMessage ids registered
            $table->string('status', 12)->default('active'); // active | recovered | expired
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('storefront_cart_recoveries');
    }
};
