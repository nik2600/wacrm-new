<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Bind products to a specific storefront so multi-shop workspaces
 * can have different catalogs per shop. Previously products were
 * workspace-scoped — every shop in a workspace shared the same
 * product list, which broke the catalog-send UX where the
 * operator picks a shop and expects ONLY that shop's products.
 *
 * Backfill: assign every existing product to the workspace's
 * most-recently-updated storefront. Single-shop workspaces are
 * unaffected; multi-shop workspaces will need to redistribute
 * manually in the product editor.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('wa_products', function (Blueprint $table) {
            $table->unsignedBigInteger('storefront_id')->nullable()->after('workspace_id');
            $table->index(['workspace_id', 'storefront_id'], 'wa_products_ws_sf_idx');
            $table->foreign('storefront_id')->references('id')->on('wa_storefronts')->nullOnDelete();
        });

        // Backfill — group products by workspace, assign each batch to
        // that workspace's newest storefront. Workspaces with no
        // storefront leave storefront_id NULL (legacy behaviour).
        $workspaces = DB::table('wa_products')->distinct()->pluck('workspace_id');
        foreach ($workspaces as $wsId) {
            $sfId = DB::table('wa_storefronts')
                ->where('workspace_id', $wsId)
                ->orderByDesc('updated_at')
                ->value('id');
            if ($sfId) {
                DB::table('wa_products')
                    ->where('workspace_id', $wsId)
                    ->whereNull('storefront_id')
                    ->update(['storefront_id' => $sfId]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('wa_products', function (Blueprint $table) {
            $table->dropForeign(['storefront_id']);
            $table->dropIndex('wa_products_ws_sf_idx');
            $table->dropColumn('storefront_id');
        });
    }
};
