<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Soft-delete users. AccountController::destroyAccount() marks
 * `deleted_at`, scrubs PII (name → "Deleted user", email → unique
 * placeholder, mobile → null, avatar removed) and revokes sessions.
 *
 * Hard-delete is deliberately avoided so we can:
 *  - audit who deleted their account, when
 *  - support GDPR "right to be forgotten" requests via a separate
 *    purge job that only runs after a 30-day cooling-off window
 *  - recover from accidental self-deletes
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
    }
};
