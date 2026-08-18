<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * WooCommerce CRM foundation (Phase 0). Brings WooCommerce to the same
 * data footing as Shopify so the shared commerce engine (CommerceEventNotifier,
 * COD / back-in-stock / cart recovery / offers / win-back) can drive it:
 *
 *  - var_map on woo events (positional template binding, mirrors Shopify)
 *  - woo_product_id / woo_order_id mirror keys on wa_products / wa_orders
 *  - per-resource sync timestamps + stats on the integration
 *  - cod / stock-waitlist / cart-recovery tables (woo-scoped)
 *
 * Short, explicit index names (MySQL 64-char limit).
 */
return new class extends Migration
{
    public function up(): void
    {
        // 1. Positional variable map on woo automations.
        Schema::table('woocommerce_integration_events', function (Blueprint $table) {
            if (! Schema::hasColumn('woocommerce_integration_events', 'var_map')) {
                $table->json('var_map')->nullable()->after('template_id');
            }
        });

        // 2. Mirror keys on the shared product / order tables.
        Schema::table('wa_products', function (Blueprint $table) {
            if (! Schema::hasColumn('wa_products', 'woo_product_id')) {
                $table->string('woo_product_id', 32)->nullable()->after('shopify_product_id');
                $table->index(['workspace_id', 'woo_product_id'], 'wa_products_woo_idx');
            }
        });
        Schema::table('wa_orders', function (Blueprint $table) {
            if (! Schema::hasColumn('wa_orders', 'woo_order_id')) {
                $table->string('woo_order_id', 32)->nullable()->after('shopify_order_id');
                $table->index(['workspace_id', 'woo_order_id'], 'wa_orders_woo_idx');
            }
        });

        // 3. Sync state on the integration.
        Schema::table('woocommerce_integrations', function (Blueprint $table) {
            foreach (['products_synced_at', 'orders_synced_at', 'customers_synced_at'] as $col) {
                if (! Schema::hasColumn('woocommerce_integrations', $col)) {
                    $table->timestamp($col)->nullable();
                }
            }
            if (! Schema::hasColumn('woocommerce_integrations', 'sync_stats')) {
                $table->json('sync_stats')->nullable();
            }
        });

        // 4. COD double-confirmation tracking.
        if (! Schema::hasTable('woocommerce_cod_confirmations')) {
            Schema::create('woocommerce_cod_confirmations', function (Blueprint $table) {
                $table->id();
                $table->foreignId('integration_id')->index();
                $table->unsignedBigInteger('workspace_id')->index();
                $table->string('woo_order_id', 32)->nullable();
                $table->string('order_name', 64)->nullable();
                $table->string('customer_phone', 32);
                $table->string('status', 16)->default('pending'); // pending | confirmed | cancelled
                $table->timestamps();
                $table->index(['workspace_id', 'customer_phone', 'status'], 'wc_cod_ws_phone_status_idx');
            });
        }

        // 5. Back-in-stock waitlist.
        if (! Schema::hasTable('woocommerce_stock_waitlist')) {
            Schema::create('woocommerce_stock_waitlist', function (Blueprint $table) {
                $table->id();
                $table->foreignId('integration_id')->index();
                $table->unsignedBigInteger('workspace_id')->index();
                $table->string('woo_product_id', 32)->nullable()->index();
                $table->string('product_name', 191)->nullable();
                $table->string('customer_phone', 32);
                $table->string('status', 16)->default('waiting'); // waiting | notified
                $table->timestamp('notified_at')->nullable();
                $table->timestamps();
                $table->index(['workspace_id', 'woo_product_id', 'status'], 'wc_stock_ws_prod_status_idx');
            });
        }

        // 6. Abandoned-cart recovery bookkeeping.
        if (! Schema::hasTable('woocommerce_cart_recoveries')) {
            Schema::create('woocommerce_cart_recoveries', function (Blueprint $table) {
                $table->id();
                $table->foreignId('integration_id')->index();
                $table->unsignedBigInteger('workspace_id')->index();
                $table->string('checkout_token', 64)->nullable();
                $table->string('customer_phone', 32)->index();
                $table->string('customer_email', 191)->nullable();
                $table->json('scheduled_ids')->nullable();
                $table->string('status', 16)->default('active'); // active | recovered | done
                $table->timestamps();
                $table->index(['workspace_id', 'customer_phone', 'status'], 'wc_cart_ws_phone_status_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('woocommerce_cart_recoveries');
        Schema::dropIfExists('woocommerce_stock_waitlist');
        Schema::dropIfExists('woocommerce_cod_confirmations');

        Schema::table('woocommerce_integrations', function (Blueprint $table) {
            foreach (['products_synced_at', 'orders_synced_at', 'customers_synced_at', 'sync_stats'] as $col) {
                if (Schema::hasColumn('woocommerce_integrations', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
        Schema::table('wa_orders', function (Blueprint $table) {
            if (Schema::hasColumn('wa_orders', 'woo_order_id')) {
                $table->dropIndex('wa_orders_woo_idx');
                $table->dropColumn('woo_order_id');
            }
        });
        Schema::table('wa_products', function (Blueprint $table) {
            if (Schema::hasColumn('wa_products', 'woo_product_id')) {
                $table->dropIndex('wa_products_woo_idx');
                $table->dropColumn('woo_product_id');
            }
        });
        Schema::table('woocommerce_integration_events', function (Blueprint $table) {
            if (Schema::hasColumn('woocommerce_integration_events', 'var_map')) {
                $table->dropColumn('var_map');
            }
        });
    }
};
