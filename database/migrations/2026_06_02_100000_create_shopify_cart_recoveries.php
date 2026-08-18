<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tracks an abandoned-cart recovery sequence so its scheduled follow-up
 * steps can be cancelled the moment the customer completes the order.
 * Scheduling itself rides the existing ScheduledMessage + Node scheduler
 * (no Laravel cron) — we just remember which scheduled rows belong to a cart.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shopify_cart_recoveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('integration_id')->index();
            $table->unsignedBigInteger('workspace_id')->index();
            $table->string('checkout_token', 64)->nullable();
            $table->string('customer_phone', 32)->index();
            $table->string('customer_email', 191)->nullable();
            $table->json('scheduled_ids')->nullable();   // ScheduledMessage ids for steps 2..n
            $table->string('status', 16)->default('active'); // active | recovered | done
            $table->timestamps();
            $table->index(['workspace_id', 'customer_phone', 'status'], 'cart_rec_ws_phone_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shopify_cart_recoveries');
    }
};
