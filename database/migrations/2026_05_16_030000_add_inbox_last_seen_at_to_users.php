<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-user "I last opened the team inbox" timestamp.
 *
 * The global notification widget uses this to count ONLY messages that
 * arrived after the user last visited the inbox — so the operator isn't
 * seeing a perpetual count of every historical unread.
 *
 * Updated on every GET /team-inbox or /team-inbox/kanban visit. NULL on
 * a fresh account; the inbox controller initialises it to now() on first
 * load so brand-new users don't get welcomed with a 100-message backlog
 * count in their notification pill.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('inbox_last_seen_at')->nullable()->after('remember_token');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('inbox_last_seen_at');
        });
    }
};
