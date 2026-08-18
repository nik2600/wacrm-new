<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Broadcasts + broadcast_contacts pivot — ported from
 * D:\wadesk_2806\New folder\app\Http\Controllers\BroadcastController.php
 *
 * The old project tracked recipient-level status (sent / delivered
 * / read / failed) on the pivot row, which the controller's
 * updateMessageStatus() webhook updated as Node fired callbacks.
 * Same shape here, with `name` + `timezone` + `error_message`
 * encrypted-at-rest because they're operator-authored / contain
 * recipient context.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('broadcasts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->unsignedBigInteger('template_id')->nullable()->index();

            // Encrypted-at-rest — operator picks the broadcast name
            // and the timezone string (may include a city/region).
            $table->text('name');
            $table->text('timezone')->nullable();

            // Plain categorical columns — used in WHERE / GROUP BY.
            $table->string('status', 32)->default('scheduled')->index();

            $table->timestamp('scheduled_at')->nullable()->index();
            $table->timestamp('completed_at')->nullable();
            $table->string('node_schedule_id')->nullable();

            // Aggregate counters refreshed by the Node webhook.
            $table->unsignedInteger('total_recipients')->default(0);
            $table->unsignedInteger('success_count')->default(0);
            $table->unsignedInteger('fail_count')->default(0);

            $table->timestamps();
            $table->index(['user_id', 'status']);
        });

        Schema::create('broadcast_contacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('broadcast_id')->constrained('broadcasts')->cascadeOnDelete();
            $table->unsignedBigInteger('contact_id')->index();

            // Per-recipient delivery state. Same enum the old
            // updateMessageStatus webhook fed.
            $table->string('status', 16)->default('pending')->index();
            $table->text('error_message')->nullable();      // encrypted (carries recipient/device info)
            $table->string('whatsapp_message_id')->nullable();

            $table->timestamp('sent_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('read_at')->nullable();

            $table->timestamps();
            $table->index(['broadcast_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('broadcast_contacts');
        Schema::dropIfExists('broadcasts');
    }
};
