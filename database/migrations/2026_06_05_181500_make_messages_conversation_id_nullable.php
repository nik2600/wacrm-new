<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Campaign sends go through WhatsAppDispatcher::sendRaw(), which stamps a
 * lightweight `messages` row (provider tracking) — but campaigns have no
 * chat Conversation, so `conversation_id` is null. The column was NOT NULL
 * with no default, so that stamp insert failed:
 *   "Field 'conversation_id' doesn't have a default value"
 * (non-fatal — caught — but it meant campaign sends were never recorded in
 * `messages`, and spammed the log). Make it nullable so the stamp succeeds.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('messages') && Schema::hasColumn('messages', 'conversation_id')) {
            Schema::table('messages', function (Blueprint $table) {
                $table->unsignedBigInteger('conversation_id')->nullable()->change();
            });
        }
    }

    public function down(): void
    {
        // No safe revert — leaving it nullable is harmless. Re-tightening would
        // fail on any existing campaign-stamped rows that have a null value.
    }
};
