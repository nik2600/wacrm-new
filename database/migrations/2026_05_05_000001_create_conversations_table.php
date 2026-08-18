<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Conversations table — replaces the old "queue_id" pattern from
 * D:\wadesk_2806\New folder where one queue was just rows in the
 * messages table sharing a queue_id. Here a conversation is a real
 * row, which means clean joins, no MAX()/GROUP BY gymnastics, and
 * scopable Eloquent queries (forUser / notArchived / withStatus).
 *
 * `device_id` and `contact_group_id` are stored as plain
 * unsignedBigInteger (not foreign-key constrained) to mirror the
 * existing convention: contacts.user_id and contact_groups.user_id
 * are nullable + un-indexed pending an auth/devices port.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('conversations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->unsignedBigInteger('device_id')->nullable()->index();
            $table->unsignedBigInteger('contact_group_id')->nullable()->index();

            // title/preview are encrypted-at-rest (`encrypted` cast on the
            // model). Ciphertext is ~3× plaintext + IV/tag overhead, so we
            // use TEXT — VARCHAR(255) would silently truncate.
            $table->text('title');
            $table->text('preview')->nullable();
            $table->string('status', 16)->default('pending');     // pending | sent | failed | scheduled
            $table->string('platform', 8)->default('W');          // W (web) | WB (Meta/cloud) | T (Twilio)
            $table->boolean('archived')->default(false)->index();

            $table->unsignedInteger('recipients_count')->default(0);
            $table->timestamp('last_message_at')->nullable()->index();
            $table->timestamp('scheduled_at')->nullable();

            $table->timestamps();
            $table->index(['user_id', 'archived', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('conversations');
    }
};
