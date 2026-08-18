<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Scheduled messages — replaces the messy `scheduled_messages` table from the
 * D:\wadesk_2806\New folder install. Cleaned up to:
 *   - workspace-scope (was user-scope only)
 *   - encrypt PII at rest (name, body, target numbers — same pattern as
 *     conversations/messages/notes)
 *   - drop Node.js coupling (`node_schedule_id` etc.) — Laravel's own
 *     scheduler+queue picks rows with next_run_at <= now() and dispatches
 *   - drop legacy text status enum strings into a tight enum-via-string
 *
 * Lifecycle: scheduled → running → completed | failed | cancelled | paused
 *   - scheduled: queued for the future
 *   - running: dispatcher has handed it to the worker
 *   - paused: human paused, won't run until resumed
 *   - completed: all recipients sent
 *   - failed: hard error
 *   - cancelled: user destroyed it (kept around for audit if soft-deleted)
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('scheduled_messages', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->index();          // creator
            $table->unsignedBigInteger('workspace_id')->index();
            $table->unsignedBigInteger('device_id')->nullable()->index();

            // operator-authored label + body — encrypted at rest the same way
            // Conversation::title, ConversationNote::body etc. are. Ciphertext
            // ~3× plaintext + IV/tag, so TEXT not VARCHAR.
            $table->text('schedule_name');
            $table->text('message_content')->nullable();

            // template binding (optional — message_type=template path)
            $table->unsignedBigInteger('template_id')->nullable();
            $table->string('template_type', 12)->default('text');     // text|template|media|location

            // scheduling shape
            $table->string('schedule_type', 12)->default('once');     // once|recurring
            $table->date('send_date');
            $table->string('send_time', 16);                          // 24h "HH:MM"
            $table->timestamp('scheduled_time');                      // computed first-run UTC
            $table->string('timezone', 64);

            // recurrence (only used when schedule_type=recurring)
            $table->string('repeat_interval', 12)->nullable();        // daily|weekly|monthly
            $table->unsignedInteger('repeat_every')->default(1);
            $table->json('days_of_week')->nullable();                 // [0..6] Sunday=0
            $table->date('end_date')->nullable();

            // media + location
            $table->string('media_file', 500)->nullable();
            $table->decimal('latitude',  10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();

            // recipient targeting — exactly one of these is populated
            $table->string('recipient_type', 12);                     // group|queue|number
            $table->json('target_queues')->nullable();                // [queue_id, ...]
            $table->json('target_groups')->nullable();                // [contact_group_id, ...]
            $table->text('target_numbers')->nullable();               // encrypted JSON of phones (PII)
            $table->unsignedInteger('total_recipients')->default(0);

            // dispatch state
            $table->string('from_number', 32)->nullable();            // sender WA number
            $table->string('status', 16)->default('scheduled')->index();
            $table->timestamp('next_run_at')->nullable()->index();
            $table->timestamp('last_run_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->text('failure_reason')->nullable();
            $table->unsignedInteger('total_sent')->default(0);
            $table->unsignedInteger('total_delivered')->default(0);
            $table->unsignedInteger('total_failed')->default(0);

            $table->softDeletes();
            $table->timestamps();

            $table->index(['workspace_id', 'status', 'next_run_at']);
            $table->index(['workspace_id', 'schedule_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scheduled_messages');
    }
};
