<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Plan feature flags for the new Slack + Trello integrations, mirroring the
 * existing integration_* boolean flags on packages.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('packages', function (Blueprint $table) {
            if (!Schema::hasColumn('packages', 'integration_slack')) {
                $table->boolean('integration_slack')->default(false)->after('integration_hubspot');
            }
            if (!Schema::hasColumn('packages', 'integration_trello')) {
                $table->boolean('integration_trello')->default(false)->after('integration_slack');
            }
        });
    }

    public function down(): void
    {
        Schema::table('packages', function (Blueprint $table) {
            foreach (['integration_slack', 'integration_trello'] as $col) {
                if (Schema::hasColumn('packages', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
