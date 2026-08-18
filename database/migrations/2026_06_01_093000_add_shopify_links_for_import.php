<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 0 of the Shopify CRM: let us mirror Shopify products / orders /
 * customers into our own tables (wa_products, wa_orders, contacts) so the
 * dashboard, catalog send, broadcasts and automations all run on real
 * local data instead of per-request live API calls.
 *
 * Adds dedupe keys (the Shopify numeric id) so re-imports upsert rather
 * than duplicate, plus per-resource sync timestamps on the integration.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('wa_products', function (Blueprint $table) {
            if (! Schema::hasColumn('wa_products', 'shopify_product_id')) {
                $table->string('shopify_product_id', 32)->nullable()->after('storefront_id');
                $table->index(['workspace_id', 'shopify_product_id'], 'wa_products_shopify_idx');
            }
        });

        Schema::table('wa_orders', function (Blueprint $table) {
            if (! Schema::hasColumn('wa_orders', 'shopify_order_id')) {
                $table->string('shopify_order_id', 32)->nullable()->after('storefront_id');
                $table->index(['workspace_id', 'shopify_order_id'], 'wa_orders_shopify_idx');
            }
        });

        Schema::table('shopify_integrations', function (Blueprint $table) {
            foreach (['products_synced_at', 'orders_synced_at', 'customers_synced_at'] as $col) {
                if (! Schema::hasColumn('shopify_integrations', $col)) {
                    $table->timestamp($col)->nullable();
                }
            }
            if (! Schema::hasColumn('shopify_integrations', 'sync_stats')) {
                $table->json('sync_stats')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('wa_products', function (Blueprint $table) {
            if (Schema::hasColumn('wa_products', 'shopify_product_id')) {
                $table->dropIndex('wa_products_shopify_idx');
                $table->dropColumn('shopify_product_id');
            }
        });
        Schema::table('wa_orders', function (Blueprint $table) {
            if (Schema::hasColumn('wa_orders', 'shopify_order_id')) {
                $table->dropIndex('wa_orders_shopify_idx');
                $table->dropColumn('shopify_order_id');
            }
        });
        Schema::table('shopify_integrations', function (Blueprint $table) {
            foreach (['products_synced_at', 'orders_synced_at', 'customers_synced_at', 'sync_stats'] as $col) {
                if (Schema::hasColumn('shopify_integrations', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
