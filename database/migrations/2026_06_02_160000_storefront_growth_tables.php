<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Storefront growth set: coupons (S5), product reviews (S6), pageview
 * analytics (S9), and the order-level discount columns the checkout needs.
 */
return new class extends Migration {
    public function up(): void
    {
        // S5 — discount fields on the order.
        Schema::table('wa_orders', function (Blueprint $table) {
            if (!Schema::hasColumn('wa_orders', 'discount_minor')) {
                $table->bigInteger('discount_minor')->default(0)->after('shipping_minor');
            }
            if (!Schema::hasColumn('wa_orders', 'coupon_code')) {
                $table->string('coupon_code', 64)->nullable()->after('discount_minor');
            }
        });

        // S5 — coupons.
        if (!Schema::hasTable('wa_coupons')) {
            Schema::create('wa_coupons', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('workspace_id')->index();
                $table->unsignedBigInteger('storefront_id')->nullable();   // null = all shops in workspace
                $table->string('code', 64);
                $table->string('type', 12)->default('percent');            // percent | flat
                $table->bigInteger('amount')->default(0);                  // percent: 1-100 · flat: minor units
                $table->bigInteger('min_subtotal_minor')->nullable();
                $table->bigInteger('max_discount_minor')->nullable();      // cap for percent coupons
                $table->boolean('free_shipping')->default(false);
                $table->boolean('active')->default(true);
                $table->timestamp('starts_at')->nullable();
                $table->timestamp('expires_at')->nullable();
                $table->unsignedInteger('usage_limit')->nullable();        // null = unlimited
                $table->unsignedInteger('used_count')->default(0);
                $table->timestamps();
                $table->unique(['workspace_id', 'code']);
            });
        }

        // S6 — product reviews (moderated).
        if (!Schema::hasTable('wa_product_reviews')) {
            Schema::create('wa_product_reviews', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('workspace_id')->index();
                $table->unsignedBigInteger('storefront_id')->nullable();
                $table->unsignedBigInteger('product_id')->index();
                $table->unsignedBigInteger('order_id')->nullable();        // verified-purchase link
                $table->string('customer_name', 120)->nullable();
                $table->unsignedTinyInteger('rating')->default(5);         // 1-5
                $table->text('body')->nullable();
                $table->string('status', 12)->default('pending');          // pending | approved | rejected
                $table->timestamps();
            });
        }

        // S9 — lightweight pageview counter (one row per storefront per day).
        if (!Schema::hasTable('wa_storefront_views')) {
            Schema::create('wa_storefront_views', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('storefront_id')->index();
                $table->date('day');
                $table->unsignedInteger('views')->default(0);
                $table->unique(['storefront_id', 'day']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('wa_storefront_views');
        Schema::dropIfExists('wa_product_reviews');
        Schema::dropIfExists('wa_coupons');
        Schema::table('wa_orders', function (Blueprint $table) {
            foreach (['discount_minor', 'coupon_code'] as $col) {
                if (Schema::hasColumn('wa_orders', $col)) $table->dropColumn($col);
            }
        });
    }
};
