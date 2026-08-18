<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Multi-device scoping for Teams and SLA policies.
 *
 * Same NULL-means-"any-device" semantics as ai_agents.device_ids,
 * so existing rows keep working without backfill. Adds two JSON
 * columns side-by-side in one migration since they always ship
 * together (both surface the same multi-device picker pattern).
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('teams', function (Blueprint $table) {
            $table->json('device_ids')->nullable()->after('timezone');
        });
        Schema::table('sla_policies', function (Blueprint $table) {
            $table->json('device_ids')->nullable()->after('respect_business_hours');
        });
    }

    public function down(): void
    {
        Schema::table('teams', function (Blueprint $table) {
            $table->dropColumn('device_ids');
        });
        Schema::table('sla_policies', function (Blueprint $table) {
            $table->dropColumn('device_ids');
        });
    }
};
