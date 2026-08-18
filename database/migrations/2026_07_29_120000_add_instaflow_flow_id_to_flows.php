<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Links a WaDesk flow to the Instaflow flow it was imported from, so re-opening
 * "Edit flow" reuses the same local flow (no duplicates) and a WaDesk save
 * round-trips back to the ORIGINAL Instaflow flow.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('flows', 'instaflow_flow_id')) {
            Schema::table('flows', function (Blueprint $table) {
                $table->unsignedBigInteger('instaflow_flow_id')->nullable()->after('flow_type')->index();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('flows', 'instaflow_flow_id')) {
            Schema::table('flows', function (Blueprint $table) {
                $table->dropColumn('instaflow_flow_id');
            });
        }
    }
};
