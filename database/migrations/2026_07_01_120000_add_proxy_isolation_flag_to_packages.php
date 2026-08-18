<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The `access_proxy_isolation` plan flag was declared on the Package model
 * (fillable + cast) and consumed by PlanLimitGuard + the /devices proxy panel,
 * but its packages column was never created — the proxy-isolation build only
 * added columns to `devices`. Result: the toggle couldn't render/save on the
 * package form and the gate always read null. This adds the missing column.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('packages', function (Blueprint $t) {
            if (!Schema::hasColumn('packages', 'access_proxy_isolation')) {
                $t->boolean('access_proxy_isolation')->default(false);
            }
        });
    }

    public function down(): void
    {
        Schema::table('packages', function (Blueprint $t) {
            if (Schema::hasColumn('packages', 'access_proxy_isolation')) {
                $t->dropColumn('access_proxy_isolation');
            }
        });
    }
};
