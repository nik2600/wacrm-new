<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Recurring subscriptions. One row per active gateway subscription on a
 * workspace. The gateway charges the customer automatically every cycle and
 * fires a renewal webhook → we extend the workspace's plan_ends_at by one more
 * period (see SubscriptionService + the per-driver renewal webhooks).
 *
 * gateway_subscription_id is the id the gateway returns (Stripe sub_…, PayPal
 * I-…, Razorpay sub_…, etc.) — the join key for renewal webhooks.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('subscriptions')) {
            return;
        }
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->index();
            $table->foreignId('user_id')->nullable()->index();
            $table->foreignId('package_id')->nullable();
            $table->string('plan_id')->nullable();              // plan slug stored on the workspace
            $table->string('gateway');                          // stripe / paypal / razorpay / …
            $table->string('gateway_subscription_id')->nullable()->index();
            $table->string('gateway_plan_id')->nullable();      // price/plan id created on the gateway
            $table->string('gateway_customer_id')->nullable();
            $table->string('billing_cycle')->default('monthly'); // monthly | yearly
            $table->string('status')->default('pending');        // pending|active|past_due|canceled|expired
            $table->decimal('amount', 12, 2)->default(0);
            $table->string('currency', 8)->default('USD');
            $table->timestamp('current_period_end')->nullable();
            $table->unsignedInteger('renewals_count')->default(0);
            $table->json('meta')->nullable();
            $table->timestamp('canceled_at')->nullable();
            $table->timestamps();

            $table->index(['gateway', 'gateway_subscription_id']);
            $table->index(['workspace_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscriptions');
    }
};
