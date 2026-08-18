<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Marks when a workspace's data was auto-wiped after its plan expired beyond
 * the plan's data_retention_days window — so the wipe runs ONCE and the
 * eligibility sweep skips already-wiped workspaces.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('workspaces', 'data_wiped_at')) {
            Schema::table('workspaces', function (Blueprint $table) {
                $table->timestamp('data_wiped_at')->nullable()->index();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('workspaces', 'data_wiped_at')) {
            Schema::table('workspaces', function (Blueprint $table) {
                $table->dropColumn('data_wiped_at');
            });
        }
    }
};
