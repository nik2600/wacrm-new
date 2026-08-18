<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Extend wa_products with the fields Meta's Commerce Catalog
 * requires for a complete upload + the observability columns we
 * need to track sync status per row.
 *
 * Meta requires (per Marketing API product-catalog/products):
 *   availability, condition, brand, link (product URL), category
 *
 * We also add meta_batch_handle + meta_last_error so the UI can
 * tell operators WHY a particular product failed to sync — without
 * these the status column is just "failed" with no diagnostic.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('wa_products', function (Blueprint $table) {
            // Meta-required fields
            $table->enum('availability', ['in stock', 'out of stock', 'available for order', 'discontinued'])
                ->default('in stock')
                ->after('status');
            $table->enum('condition', ['new', 'refurbished', 'used'])
                ->default('new')
                ->after('availability');
            $table->string('brand', 100)->nullable()->after('condition');
            $table->string('product_url', 2048)->nullable()->after('brand');
            $table->string('google_product_category', 255)->nullable()->after('product_url');

            // Sync observability
            $table->string('meta_batch_handle', 191)->nullable()->after('meta_synced_at');
            $table->text('meta_last_error')->nullable()->after('meta_batch_handle');

            // Allows us to filter by sync state in the catalog admin
            $table->index(['workspace_id', 'meta_sync_status'], 'wa_products_ws_meta_sync_idx');
        });
    }

    public function down(): void
    {
        Schema::table('wa_products', function (Blueprint $table) {
            $table->dropIndex('wa_products_ws_meta_sync_idx');
            $table->dropColumn([
                'availability', 'condition', 'brand',
                'product_url', 'google_product_category',
                'meta_batch_handle', 'meta_last_error',
            ]);
        });
    }
};
