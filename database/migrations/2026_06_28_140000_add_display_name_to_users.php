<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Profile "Display name" was validated by the account form but had nowhere to
 * live (no column) — so it silently never saved. Give it a real home.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'display_name')) {
                $table->string('display_name', 120)->nullable()->after('name');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'display_name')) {
                $table->dropColumn('display_name');
            }
        });
    }
};
