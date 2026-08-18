<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-workspace currency. WaDesk is multi-tenant — different
 * workspaces in the same install can operate in different currencies
 * (one customer's workspace in INR, another in USD, etc.).
 *
 * Falls back to the global default (system_settings.default_currency)
 * when NULL.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workspaces', function (Blueprint $table) {
            $table->string('currency', 10)->nullable()->after('timezone');
        });
    }

    public function down(): void
    {
        Schema::table('workspaces', function (Blueprint $table) {
            $table->dropColumn('currency');
        });
    }
};
