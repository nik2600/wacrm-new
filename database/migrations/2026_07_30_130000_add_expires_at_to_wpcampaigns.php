<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-campaign END DATE. When set by the operator, the campaign stops sending
 * at this time (a hard stop that overrides the platform default auto-end).
 * Blank = fall back to the admin's "auto-end after N hours" default.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('wpcampaigns')) return;
        if (Schema::hasColumn('wpcampaigns', 'expires_at')) return;
        Schema::table('wpcampaigns', function (Blueprint $table) {
            $table->timestamp('expires_at')->nullable();
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('wpcampaigns', 'expires_at')) {
            Schema::table('wpcampaigns', function (Blueprint $table) {
                $table->dropColumn('expires_at');
            });
        }
    }
};
