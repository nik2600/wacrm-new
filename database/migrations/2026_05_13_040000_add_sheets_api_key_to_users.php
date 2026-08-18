<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Personal API key for the Google Sheets add-on. Token format
 * `wsn_live_<32 hex>` is what the user pastes into the add-on
 * sidebar. Stored hashed so the database compromise doesn't leak
 * usable tokens — we keep the last 8 chars as a "label" for the
 * UI ("ends in …abcd1234") and the hash for lookup.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('sheets_api_key_hash', 64)->nullable()->index('users_sheets_api_key_hash_idx');
            $table->string('sheets_api_key_suffix', 12)->nullable();
            $table->timestamp('sheets_api_key_created_at')->nullable();
            $table->timestamp('sheets_api_key_last_used_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex('users_sheets_api_key_hash_idx');
            $table->dropColumn(['sheets_api_key_hash', 'sheets_api_key_suffix', 'sheets_api_key_created_at', 'sheets_api_key_last_used_at']);
        });
    }
};
