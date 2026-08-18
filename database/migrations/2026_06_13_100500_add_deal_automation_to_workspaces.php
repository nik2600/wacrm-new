<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-workspace auto-deal-from-orders settings (locked decision: opt-in,
 * OFF by default, with an optional minimum-order-value threshold so a
 * high-volume store doesn't flood the pipeline). Both re-toggleable from
 * the /deals board settings.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workspaces', function (Blueprint $table) {
            $table->boolean('deals_auto_from_orders')->default(false);
            $table->bigInteger('deals_auto_min_minor')->nullable(); // minor units; NULL = no floor
        });
    }

    public function down(): void
    {
        Schema::table('workspaces', function (Blueprint $table) {
            $table->dropColumn(['deals_auto_from_orders', 'deals_auto_min_minor']);
        });
    }
};
