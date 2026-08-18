<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add an `archived` flag to broadcasts so the mobile-app
 * /archive-queue + /all-archive-queue endpoints can persist a real value
 * instead of stubbing back "not supported". Mirrors the `archived` column
 * we already use on conversations for the same UX pattern.
 *
 * Indexed because the list endpoint filters by it on every poll, and the
 * column is also part of the main /queues feed's where-clause when the
 * app wants to hide archived rows from the active list.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('broadcasts', function (Blueprint $table) {
            if (! Schema::hasColumn('broadcasts', 'archived')) {
                $table->boolean('archived')->default(false)->index()->after('pinned');
            }
        });
    }

    public function down(): void
    {
        Schema::table('broadcasts', function (Blueprint $table) {
            if (Schema::hasColumn('broadcasts', 'archived')) {
                $table->dropColumn('archived');
            }
        });
    }
};
