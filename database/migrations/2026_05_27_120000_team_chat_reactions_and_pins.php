<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Team-chat polish: reactions table + pin column on messages.
 *
 * - `team_chat_reactions` — one row per (message, user, emoji) so the
 *   same user can stack multiple reactions on the same message
 *   (Slack/Discord behavior). UNIQUE constraint prevents double-tap.
 * - `team_chat_messages.pinned_at` — null = unpinned, set = pinned.
 *   No separate pin table because we only need single-bit state +
 *   when, not pin author. The `pinned_by_user_id` column captures
 *   who pinned it for the "pinned by X" hover tooltip.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('team_chat_reactions', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('message_id');
            $t->unsignedBigInteger('user_id');
            $t->unsignedBigInteger('workspace_id');
            $t->string('emoji', 16);
            $t->timestamps();
            $t->foreign('message_id')->references('id')->on('team_chat_messages')->cascadeOnDelete();
            $t->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $t->unique(['message_id', 'user_id', 'emoji'], 'tcr_unique_per_user_per_emoji');
            $t->index(['workspace_id', 'message_id']);
        });

        Schema::table('team_chat_messages', function (Blueprint $t) {
            if (!Schema::hasColumn('team_chat_messages', 'pinned_at')) {
                $t->timestamp('pinned_at')->nullable()->after('edited_at');
            }
            if (!Schema::hasColumn('team_chat_messages', 'pinned_by_user_id')) {
                $t->unsignedBigInteger('pinned_by_user_id')->nullable()->after('pinned_at');
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('team_chat_reactions');
        Schema::table('team_chat_messages', function (Blueprint $t) {
            if (Schema::hasColumn('team_chat_messages', 'pinned_by_user_id')) $t->dropColumn('pinned_by_user_id');
            if (Schema::hasColumn('team_chat_messages', 'pinned_at')) $t->dropColumn('pinned_at');
        });
    }
};
