<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Batch J / finding #39 — give browser-extension bearer tokens an optional
 * expiry so a stolen token's blast radius is time-bounded.
 *
 * The column is NULLABLE and defaults to NULL. ExtensionApiToken::resolveUser()
 * treats a NULL expires_at as "never expires", so every pre-existing (legacy)
 * token keeps working exactly as before — no active session is invalidated.
 * Callers may pass a TTL to ExtensionApiToken::issue() to opt new tokens in.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('extension_api_tokens', 'expires_at')) {
            Schema::table('extension_api_tokens', function (Blueprint $table) {
                $table->timestamp('expires_at')->nullable()->after('last_used_at');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('extension_api_tokens', 'expires_at')) {
            Schema::table('extension_api_tokens', function (Blueprint $table) {
                $table->dropColumn('expires_at');
            });
        }
    }
};
