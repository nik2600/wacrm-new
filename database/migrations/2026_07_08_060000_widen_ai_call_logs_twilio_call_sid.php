<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Widen ai_call_logs.twilio_call_sid from varchar(64) → varchar(191).
 *
 * The column is reused to store Meta's WABA call id (`wacid.<base64>` — ~78
 * chars) for the WABA calling bridge. At 64 chars the id overflowed and the
 * onConnect mirror insert threw "Data too long for column 'twilio_call_sid'"
 * — which meant the call's assistant_id was never stamped, so the AI voice
 * bridge was never dispatched and inbound AI calls silently fell to voicemail.
 *
 * Raw ALTER (not Schema ->change()) so the existing UNIQUE index is preserved
 * untouched. 191 is the utf8mb4 single-column index-safe max.
 */
return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasColumn('ai_call_logs', 'twilio_call_sid')) return;
        DB::statement('ALTER TABLE `ai_call_logs` MODIFY `twilio_call_sid` VARCHAR(191) NULL');
    }

    public function down(): void
    {
        if (!Schema::hasColumn('ai_call_logs', 'twilio_call_sid')) return;
        DB::statement('ALTER TABLE `ai_call_logs` MODIFY `twilio_call_sid` VARCHAR(64) NULL');
    }
};
