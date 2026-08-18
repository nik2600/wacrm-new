<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * Team-chat phase 2 — multi-channel, per-channel membership, and a
 * member-invite approval flow.
 *
 * Channels: every workspace has at least one channel (#general,
 * auto-seeded on first access). Users with the right role can create
 * more (#support, #dev, …). Channel type controls visibility:
 *
 *   - public  : every workspace member auto-joins, can be browsed
 *   - private : invite-only, members managed via team_chat_channel_members
 *   - dm      : 1-on-1 direct message (a system-managed "private" with
 *                two members, name derived from the other user)
 *
 * Invitations: when a non-admin tries to add a teammate, instead of
 * silently inserting into workspace_user we drop a row into
 * team_chat_invitations with status='pending'. Admins see these on a
 * Pending Approvals view and approve/decline.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('team_chat_channels', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('workspace_id');
            $t->string('name', 64);
            $t->string('slug', 64);
            $t->text('description')->nullable();
            $t->enum('type', ['public', 'private', 'dm'])->default('public');
            $t->unsignedBigInteger('created_by_user_id')->nullable();
            $t->timestamp('last_message_at')->nullable();
            $t->softDeletes();
            $t->timestamps();

            $t->index(['workspace_id', 'type']);
            $t->unique(['workspace_id', 'slug']);
            $t->foreign('workspace_id')->references('id')->on('workspaces')->cascadeOnDelete();
            $t->foreign('created_by_user_id')->references('id')->on('users')->nullOnDelete();
        });

        Schema::create('team_chat_channel_members', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('channel_id');
            $t->unsignedBigInteger('user_id');
            $t->enum('role', ['admin', 'member'])->default('member');
            $t->unsignedBigInteger('last_read_message_id')->default(0);
            $t->timestamp('last_read_at')->nullable();
            $t->timestamp('joined_at')->nullable();
            $t->timestamps();

            $t->unique(['channel_id', 'user_id']);
            $t->foreign('channel_id')->references('id')->on('team_chat_channels')->cascadeOnDelete();
            $t->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });

        // Add channel_id to team_chat_messages so messages live inside a
        // channel. Nullable for backfill; new sends MUST set it.
        Schema::table('team_chat_messages', function (Blueprint $t) {
            $t->unsignedBigInteger('channel_id')->nullable()->after('workspace_id');
            $t->index(['channel_id', 'id']);
            $t->foreign('channel_id')->references('id')->on('team_chat_channels')->cascadeOnDelete();
        });

        // Invitations: requester (non-admin) → approver (admin). When
        // approved, the invitee is added to workspace_user. Used both
        // for net-new workspace invites and for adding existing users
        // to a private channel.
        Schema::create('team_chat_invitations', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('workspace_id');
            $t->unsignedBigInteger('channel_id')->nullable(); // null = invite to workspace only
            $t->unsignedBigInteger('requester_user_id');
            $t->unsignedBigInteger('invitee_user_id')->nullable();
            $t->string('invitee_email', 191)->nullable();
            $t->string('invitee_name', 191)->nullable();
            $t->enum('status', ['pending', 'approved', 'declined', 'expired'])->default('pending');
            $t->text('note')->nullable();
            $t->unsignedBigInteger('decided_by_user_id')->nullable();
            $t->timestamp('decided_at')->nullable();
            $t->timestamps();

            $t->index(['workspace_id', 'status']);
            $t->foreign('workspace_id')->references('id')->on('workspaces')->cascadeOnDelete();
            $t->foreign('channel_id')->references('id')->on('team_chat_channels')->nullOnDelete();
            $t->foreign('requester_user_id')->references('id')->on('users')->cascadeOnDelete();
            $t->foreign('invitee_user_id')->references('id')->on('users')->nullOnDelete();
            $t->foreign('decided_by_user_id')->references('id')->on('users')->nullOnDelete();
        });

        // Seed a #general channel for every existing workspace, and
        // backfill orphan messages onto it so they don't disappear.
        $workspaces = DB::table('workspaces')->pluck('id');
        foreach ($workspaces as $wsId) {
            $channelId = DB::table('team_chat_channels')->insertGetId([
                'workspace_id' => $wsId,
                'name'         => 'general',
                'slug'         => 'general',
                'description'  => 'Everyone in this workspace',
                'type'         => 'public',
                'created_at'   => now(),
                'updated_at'   => now(),
            ]);
            DB::table('team_chat_messages')
                ->where('workspace_id', $wsId)
                ->whereNull('channel_id')
                ->update(['channel_id' => $channelId]);
        }
    }

    public function down(): void
    {
        Schema::table('team_chat_messages', function (Blueprint $t) {
            $t->dropForeign(['channel_id']);
            $t->dropIndex(['channel_id', 'id']);
            $t->dropColumn('channel_id');
        });
        Schema::dropIfExists('team_chat_invitations');
        Schema::dropIfExists('team_chat_channel_members');
        Schema::dropIfExists('team_chat_channels');
    }
};
