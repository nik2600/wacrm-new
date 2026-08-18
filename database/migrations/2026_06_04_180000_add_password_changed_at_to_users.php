<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Records when a user last set their password, so the
 * security.password_max_age_days policy can tell when a password is stale.
 * Nullable + backfilled to the account's created_at so existing users get
 * a sensible baseline instead of being flagged stale on day one.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('users', 'password_changed_at')) {
            return;
        }
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('password_changed_at')->nullable()->after('password');
        });

        // Baseline existing rows so a freshly-enabled max-age policy doesn't
        // immediately mark every legacy account's password as expired.
        \Illuminate\Support\Facades\DB::table('users')
            ->whereNull('password_changed_at')
            ->update(['password_changed_at' => \Illuminate\Support\Facades\DB::raw('created_at')]);
    }

    public function down(): void
    {
        if (Schema::hasColumn('users', 'password_changed_at')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('password_changed_at');
            });
        }
    }
};
