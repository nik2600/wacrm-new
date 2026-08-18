<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * team_chat_messages — Slack-style internal chat for workspace teammates.
 *
 * This is NOT the customer team-inbox (which lives in `conversations` +
 * `inbox_messages`). It's a separate workspace-wide channel where
 * operators talk to each other in-app:
 *
 *   - workspace_id : the chat is scoped to one workspace
 *   - user_id      : author (always a workspace member)
 *   - body         : message text (encrypted at rest like InboxMessage.body)
 *   - mentions     : JSON array of user_ids @-mentioned in the body
 *   - reply_to_id  : if this is a thread reply, points to the parent row
 *   - attachment_* : optional image/file (stored on disk like inbox media)
 *
 * Read state lives in a sibling pivot `team_chat_reads` (one row per
 * user, tracking last read message id) so each teammate has their own
 * unread count without polluting the message rows.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('team_chat_messages', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('workspace_id');
            $t->unsignedBigInteger('user_id');
            $t->text('body')->nullable();
            $t->json('mentions')->nullable();
            $t->unsignedBigInteger('reply_to_id')->nullable();
            $t->string('attachment_path', 255)->nullable();
            $t->string('attachment_mime', 96)->nullable();
            $t->string('attachment_name', 191)->nullable();
            $t->timestamp('edited_at')->nullable();
            $t->softDeletes();
            $t->timestamps();

            $t->index(['workspace_id', 'id']);
            $t->index(['workspace_id', 'created_at']);
            $t->foreign('workspace_id')->references('id')->on('workspaces')->cascadeOnDelete();
            $t->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $t->foreign('reply_to_id')->references('id')->on('team_chat_messages')->nullOnDelete();
        });

        Schema::create('team_chat_reads', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('workspace_id');
            $t->unsignedBigInteger('user_id');
            $t->unsignedBigInteger('last_read_message_id')->default(0);
            $t->timestamp('last_read_at')->nullable();
            $t->timestamps();

            $t->unique(['workspace_id', 'user_id']);
            $t->foreign('workspace_id')->references('id')->on('workspaces')->cascadeOnDelete();
            $t->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('team_chat_reads');
        Schema::dropIfExists('team_chat_messages');
    }
};
