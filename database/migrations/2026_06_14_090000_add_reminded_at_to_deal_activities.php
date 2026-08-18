<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Task reminders need a "we already nudged the owner" marker so the inline
 * sweep (DealReminderService, run from TeamInboxController::queue() the same
 * way snooze-wake is) never double-notifies. NULL = not yet reminded.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('deal_activities', function (Blueprint $table) {
            $table->timestamp('reminded_at')->nullable()->after('done_at');
        });
    }

    public function down(): void
    {
        Schema::table('deal_activities', function (Blueprint $table) {
            $table->dropColumn('reminded_at');
        });
    }
};
