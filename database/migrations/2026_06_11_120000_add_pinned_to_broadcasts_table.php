<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds `pinned` to broadcasts so the mobile app's "pin a queue" action has a
 * column to flip (POST /api/app/queue/toggle-pin + GET /api/app/queues/pinned).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('broadcasts', 'pinned')) {
            Schema::table('broadcasts', function (Blueprint $table) {
                $table->boolean('pinned')->default(false)->after('status');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('broadcasts', 'pinned')) {
            Schema::table('broadcasts', function (Blueprint $table) {
                $table->dropColumn('pinned');
            });
        }
    }
};
