<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('conversations', function (Blueprint $table) {
            $table->unsignedBigInteger('workspace_id')->nullable()->after('user_id')->index();

            // inbox lifecycle is *separate* from the existing send-queue `status`
            // (pending/sent/failed/scheduled). Don't reuse that column — it means
            // something else and downstream code depends on it.
            $table->string('inbox_status', 16)->default('open')->index();
            // open | pending | snoozed | resolved | closed | spam

            $table->string('priority', 12)->default('normal')->index();
            // low | normal | high | urgent

            $table->unsignedBigInteger('assignee_user_id')->nullable()->index();
            $table->unsignedBigInteger('assignee_team_id')->nullable()->index();

            $table->timestamp('first_response_at')->nullable();
            $table->timestamp('snoozed_until')->nullable()->index();
            $table->timestamp('resolved_at')->nullable();
            $table->unsignedBigInteger('resolved_by')->nullable();
            $table->unsignedBigInteger('sla_policy_id')->nullable();
            $table->timestamp('sla_first_response_due')->nullable()->index();
            $table->timestamp('sla_resolution_due')->nullable()->index();
            $table->boolean('sla_breached')->default(false)->index();

            $table->string('channel', 16)->default('whatsapp')->index();
            // whatsapp | wa_cloud | sms | rcs | future

            $table->boolean('is_spam')->default(false)->index();
            $table->json('routing_meta')->nullable();
            // last rule fired, score, etc. — debug breadcrumb

            $table->timestamp('last_inbound_at')->nullable()->index();
            $table->timestamp('last_outbound_at')->nullable()->index();
            $table->unsignedInteger('unread_count')->default(0);

            $table->index(['workspace_id', 'inbox_status', 'priority']);
            $table->index(['workspace_id', 'assignee_user_id', 'inbox_status']);
            $table->index(['workspace_id', 'assignee_team_id', 'inbox_status']);
        });

        // Backfill workspace_id from users.current_workspace_id so that pre-existing
        // demo conversations don't disappear behind the new per-workspace scope.
        // Done in PHP rather than a SQL UPDATE..FROM because SQLite (used in CI)
        // doesn't support that join syntax.
        DB::table('conversations')
            ->whereNull('workspace_id')
            ->orderBy('id')
            ->chunkById(500, function ($rows) {
                foreach ($rows as $row) {
                    $wid = DB::table('users')
                        ->where('id', $row->user_id)
                        ->value('current_workspace_id');
                    if ($wid) {
                        DB::table('conversations')
                            ->where('id', $row->id)
                            ->update(['workspace_id' => $wid]);
                    }
                }
            });
    }

    public function down(): void
    {
        Schema::table('conversations', function (Blueprint $table) {
            $table->dropIndex(['workspace_id', 'inbox_status', 'priority']);
            $table->dropIndex(['workspace_id', 'assignee_user_id', 'inbox_status']);
            $table->dropIndex(['workspace_id', 'assignee_team_id', 'inbox_status']);
            $table->dropColumn([
                'workspace_id', 'inbox_status', 'priority',
                'assignee_user_id', 'assignee_team_id',
                'first_response_at', 'snoozed_until', 'resolved_at', 'resolved_by',
                'sla_policy_id', 'sla_first_response_due', 'sla_resolution_due', 'sla_breached',
                'channel', 'is_spam', 'routing_meta',
                'last_inbound_at', 'last_outbound_at', 'unread_count',
            ]);
        });
    }
};
