<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('routing_rules', function (Blueprint $table) {
            // Fallback rules only fire when *no* non-fallback rule matched.
            // Useful for catch-all "if nothing else matched, assign to
            // Unassigned-Triage team" patterns.
            $table->boolean('is_fallback')->default(false)->after('is_active')->index();
        });
    }

    public function down(): void
    {
        Schema::table('routing_rules', function (Blueprint $table) {
            $table->dropColumn('is_fallback');
        });
    }
};
