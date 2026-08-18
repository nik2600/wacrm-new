<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sales Pipeline / Deal Management plan gate.
 *
 * Dedicated flag (never bundled) — the CodeCanyon buyer's upsell lever and
 * consistent with every other access_* gate. Seed ON for the top paid tiers,
 * OFF for Free/Starter, re-assignable from the admin plan-feature editor.
 * `pipelines_limit` NULL = unlimited (existing convention).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('packages', function (Blueprint $table) {
            $table->boolean('access_sales_pipeline')->default(false);
            $table->unsignedInteger('pipelines_limit')->nullable();
        });

        // Seed ON for the top/highlighted tier (the "popular" plan), OFF for
        // the rest. Fully re-assignable afterwards from the admin plan editor.
        try {
            \Illuminate\Support\Facades\DB::table('packages')
                ->where('is_highlighted', true)
                ->update(['access_sales_pipeline' => true]);
        } catch (\Throwable $e) {
            // is_highlighted absent on a custom schema — skip, admin toggles it.
        }
    }

    public function down(): void
    {
        Schema::table('packages', function (Blueprint $table) {
            $table->dropColumn(['access_sales_pipeline', 'pipelines_limit']);
        });
    }
};
