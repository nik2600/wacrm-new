<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Order = one plan purchase attempt.
 *
 * Lifecycle:
 *   pending  → user landed on /checkout/{package_id}, order row written
 *   paid     → gateway callback verified, workspace.plan updated
 *   failed   → gateway said no
 *   refunded → admin marked it refunded (manual for v1)
 *
 * `currency` + `amount` capture what the customer ACTUALLY agreed to
 * pay (after currency conversion at checkout time). Source of truth
 * for invoices and refunds.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_number', 32)->unique();
            $table->unsignedBigInteger('workspace_id');
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('package_id');
            $table->unsignedBigInteger('gateway_id')->nullable();
            $table->string('gateway_slug', 64)->nullable();
            $table->string('currency', 10);
            $table->decimal('amount', 12, 2);
            $table->decimal('base_amount_usd', 12, 2)->nullable(); // amount in USD at order time
            $table->decimal('exchange_rate', 16, 6)->nullable();
            $table->string('status', 16)->default('pending');     // pending | paid | failed | refunded
            $table->string('gateway_order_id', 191)->nullable();  // gateway's own id (Razorpay order_id, Stripe session_id, etc.)
            $table->string('gateway_payment_id', 191)->nullable();// the payment id post-success
            $table->json('gateway_payload')->nullable();          // full last response from the gateway
            $table->text('failure_reason')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->index(['workspace_id', 'status']);
            $table->index('package_id');
            $table->index('gateway_order_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
