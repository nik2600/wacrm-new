<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * WhatsApp Pay (in-chat `order_details` payments) — WP-0 foundation.
 *
 * Region-locked by Meta: native in-chat pay is LIVE only in **India** (UPI /
 * Razorpay / PayU); Brazil = Pix only; Indonesia/Mexico = limited testing;
 * everywhere else unavailable (use a payment link instead). So this ships as a
 * per-workspace, REGION-GATED payment method — the UI only offers it when the
 * workspace's WABA country is supported, and it's labelled "India only".
 *
 *  - workspace_payment_configs : the merchant's WhatsApp-Manager "Direct Pay
 *    Method" name. We can't create it via API (it's Meta-side) — we only store
 *    + reference it in every order_details send.
 *  - wa_orders                 : payment-method + reference/status/txn columns.
 *  - packages.access_whatsapp_pay : plan gate (off by default — breaks nothing).
 *
 * Idempotent (hasTable/hasColumn guards) so it's safe on every install.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('workspace_payment_configs')) {
            Schema::create('workspace_payment_configs', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('workspace_id')->index();
                $table->unsignedBigInteger('provider_config_id')->nullable()->index(); // the WABA (wa_provider_configs)
                $table->string('config_name');                 // WhatsApp Manager Direct-Pay-Method name (sent on every charge)
                $table->string('payment_type', 32)->default('upi'); // upi | razorpay | payu
                $table->string('country', 2)->default('IN');
                $table->string('currency', 8)->default('INR');
                $table->string('merchant_category')->nullable();
                $table->boolean('is_active')->default(true);
                $table->text('meta_json')->nullable();          // encrypted cast in the model
                $table->timestamps();
                $table->unique(['workspace_id', 'config_name']);
            });
        }

        if (Schema::hasTable('wa_orders')) {
            Schema::table('wa_orders', function (Blueprint $table) {
                if (!Schema::hasColumn('wa_orders', 'payment_method'))         $table->string('payment_method', 32)->nullable();          // whatsapp_pay | razorpay_link | cod
                if (!Schema::hasColumn('wa_orders', 'wa_payment_reference_id')) $table->string('wa_payment_reference_id', 40)->nullable()->index(); // join key for webhook + lookup (Meta caps ~35)
                if (!Schema::hasColumn('wa_orders', 'wa_payment_status'))       $table->string('wa_payment_status', 16)->nullable();       // pending | captured | failed | refunded
                if (!Schema::hasColumn('wa_orders', 'wa_payment_txn_id'))       $table->string('wa_payment_txn_id')->nullable();
                if (!Schema::hasColumn('wa_orders', 'wa_payment_config_id'))    $table->unsignedBigInteger('wa_payment_config_id')->nullable();
            });
        }

        if (Schema::hasTable('packages') && !Schema::hasColumn('packages', 'access_whatsapp_pay')) {
            Schema::table('packages', function (Blueprint $table) {
                $table->boolean('access_whatsapp_pay')->default(false);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('workspace_payment_configs');

        if (Schema::hasTable('wa_orders')) {
            Schema::table('wa_orders', function (Blueprint $table) {
                foreach (['payment_method', 'wa_payment_reference_id', 'wa_payment_status', 'wa_payment_txn_id', 'wa_payment_config_id'] as $c) {
                    if (Schema::hasColumn('wa_orders', $c)) $table->dropColumn($c);
                }
            });
        }

        if (Schema::hasTable('packages') && Schema::hasColumn('packages', 'access_whatsapp_pay')) {
            Schema::table('packages', function (Blueprint $table) {
                $table->dropColumn('access_whatsapp_pay');
            });
        }
    }
};
