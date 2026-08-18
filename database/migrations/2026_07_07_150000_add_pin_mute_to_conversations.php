<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * WhatsApp-style list controls for the Team Inbox: pin-on-top + mute.
 * `archived` already exists (create-conversations migration); we only add
 * the two missing flags. Both are nullable timestamps so "when" is kept
 * (useful for ordering pinned rows and for future audit), and NULL = off.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('conversations', function (Blueprint $table) {
            if (!Schema::hasColumn('conversations', 'pinned_at')) {
                // Pinned rows sort above everything else in the queue.
                $table->timestamp('pinned_at')->nullable()->after('archived')->index();
            }
            if (!Schema::hasColumn('conversations', 'muted_at')) {
                // Muted rows never ping / pop a notification.
                $table->timestamp('muted_at')->nullable()->after('pinned_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('conversations', function (Blueprint $table) {
            foreach (['pinned_at', 'muted_at'] as $col) {
                if (Schema::hasColumn('conversations', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
