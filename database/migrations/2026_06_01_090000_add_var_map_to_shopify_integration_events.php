<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-event variable mapping. Stores the ordered list of order fields a
 * merchant maps onto a template's positional {{1}}/{{2}}… placeholders,
 * e.g. ['name','order_name','total'] → {{1}}=name, {{2}}=order #, {{3}}=total.
 * Null = use the notifier's sensible default order.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shopify_integration_events', function (Blueprint $table) {
            if (! Schema::hasColumn('shopify_integration_events', 'var_map')) {
                $table->json('var_map')->nullable()->after('template_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('shopify_integration_events', function (Blueprint $table) {
            if (Schema::hasColumn('shopify_integration_events', 'var_map')) {
                $table->dropColumn('var_map');
            }
        });
    }
};
