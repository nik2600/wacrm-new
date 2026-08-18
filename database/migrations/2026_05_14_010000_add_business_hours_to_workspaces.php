<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Adds workspace-level business hours config. Stored as a JSON blob to
     * keep schema flexible — shape is:
     *   {
     *     "days": {
     *       "mon": { "enabled": true, "from": "09:00", "to": "18:00" },
     *       "tue": ...,
     *       ...,
     *       "sun": { "enabled": false, ... }
     *     },
     *     "outside_action": "none"|"template",
     *     "outside_template_id": null|int   // WaTemplate id used when outside_action='template'
     *   }
     * Default `null` means "always open" — the RoutingEngine treats a
     * missing config as the legacy 24/7 behaviour.
     */
    public function up(): void
    {
        Schema::table('workspaces', function (Blueprint $table) {
            $table->json('business_hours')->nullable()->after('timezone');
        });
    }

    public function down(): void
    {
        Schema::table('workspaces', function (Blueprint $table) {
            $table->dropColumn('business_hours');
        });
    }
};
