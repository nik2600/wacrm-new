<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Internal notes — visible inside the customer's workspace only.
        // body is encrypted at rest (model casts), so column is TEXT.
        Schema::create('conversation_notes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('conversation_id')->index();
            $table->unsignedBigInteger('workspace_id')->index();
            $table->unsignedBigInteger('user_id');
            $table->text('body');
            $table->json('mentions')->nullable();
            // [{ user_id: 12, name: "Riya", offset: 4 }, ...]
            $table->boolean('is_pinned')->default(false);
            $table->timestamp('edited_at')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->index(['conversation_id', 'created_at']);
        });

        // Append-only audit trail of state changes on a conversation.
        // Drives the "history" timeline in the UI and the manager analytics.
        Schema::create('conversation_events', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('conversation_id')->index();
            $table->unsignedBigInteger('workspace_id')->index();
            $table->unsignedBigInteger('actor_user_id')->nullable();
            $table->string('actor_type', 24)->default('user');
            // user | system | rule | platform_admin
            $table->string('type', 32)->index();
            // assigned | reassigned | unassigned | resolved | reopened |
            // snoozed | unsnoozed | tag_added | tag_removed | note_added |
            // mentioned | priority_changed | status_changed | sla_breach |
            // routing_rule_fired | first_response | spam_flagged | csat_sent
            $table->json('payload')->nullable();
            $table->timestamp('created_at')->useCurrent()->index();
        });

        // Per-user read state for unread badge + mention bells.
        // One row per (conversation, user) pair where the user has any interest.
        Schema::create('conversation_participants', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('conversation_id')->index();
            $table->unsignedBigInteger('workspace_id')->index();
            $table->unsignedBigInteger('user_id')->index();
            $table->string('role', 16)->default('watcher');
            // assignee | watcher | mentioned | follower
            $table->timestamp('last_read_at')->nullable();
            $table->unsignedInteger('unread_messages')->default(0);
            $table->unsignedInteger('unread_mentions')->default(0);
            $table->timestamps();

            $table->unique(['conversation_id', 'user_id']);
        });

        // CSAT — sent after resolution, recorded when customer replies with rating.
        Schema::create('csat_responses', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('conversation_id')->index();
            $table->unsignedBigInteger('workspace_id')->index();
            $table->unsignedBigInteger('agent_user_id')->nullable()->index();
            $table->unsignedTinyInteger('rating')->nullable();
            // 1..5
            $table->text('comment')->nullable();
            // encrypted at rest
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('responded_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('csat_responses');
        Schema::dropIfExists('conversation_participants');
        Schema::dropIfExists('conversation_events');
        Schema::dropIfExists('conversation_notes');
    }
};
