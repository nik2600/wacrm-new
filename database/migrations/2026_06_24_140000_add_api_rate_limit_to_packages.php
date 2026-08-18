<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-plan API rate limit (requests / minute) for the customer REST API
 * (/api/v1). 0 = inherit the global default (security.api_rate_limit_per_minute).
 * Enforced by App\Http\Middleware\ApiPlanRateLimit.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('packages', function (Blueprint $table) {
            if (!Schema::hasColumn('packages', 'api_rate_limit_per_minute')) {
                $table->unsignedInteger('api_rate_limit_per_minute')
                    ->default(0)
                    ->after('webhooks_limit')
                    ->comment('Customer REST API requests/min for this plan. 0 = use global default.');
            }
        });
    }

    public function down(): void
    {
        Schema::table('packages', function (Blueprint $table) {
            if (Schema::hasColumn('packages', 'api_rate_limit_per_minute')) {
                $table->dropColumn('api_rate_limit_per_minute');
            }
        });
    }
};
