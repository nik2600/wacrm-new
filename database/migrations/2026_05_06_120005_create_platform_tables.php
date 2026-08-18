<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // A platform admin "logging in as" a customer workspace. Closing the
        // session sets ended_at; ImpersonationBanner middleware reads the open
        // row to inject the banner and rewrite session.current_workspace_id.
        Schema::create('impersonation_sessions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('admin_user_id')->index();
            $table->unsignedBigInteger('target_workspace_id')->index();
            $table->unsignedBigInteger('original_workspace_id')->nullable();
            $table->string('reason', 500);
            $table->string('ip', 64)->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->timestamp('started_at')->useCurrent();
            $table->timestamp('ended_at')->nullable();
            $table->timestamps();

            $table->index(['admin_user_id', 'ended_at']);
        });

        // Notes only platform staff can see — never leak to the customer's UI.
        Schema::create('platform_notes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('conversation_id')->nullable()->index();
            $table->unsignedBigInteger('workspace_id')->nullable()->index();
            $table->unsignedBigInteger('admin_user_id');
            $table->text('body');
            // encrypted at rest
            $table->string('severity', 12)->default('info');
            // info | warn | critical
            $table->timestamps();
        });

        // Spam/abuse/billing flags applied to a workspace. Drives suspension UI.
        Schema::create('workspace_flags', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('workspace_id')->index();
            $table->string('flag', 32)->index();
            // spam | abuse | fraud | billing_overdue | tos_violation | frozen
            $table->string('reason', 500)->nullable();
            $table->unsignedBigInteger('flagged_by_user_id')->nullable();
            $table->timestamp('cleared_at')->nullable();
            $table->unsignedBigInteger('cleared_by_user_id')->nullable();
            $table->timestamps();

            $table->index(['workspace_id', 'cleared_at']);
        });

        // Cross-cutting audit log. Workspace AND platform actions both land here
        // so we have a single timeline for compliance/support investigations.
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->string('layer', 12)->index();
            // platform | workspace
            $table->unsignedBigInteger('workspace_id')->nullable()->index();
            $table->unsignedBigInteger('actor_user_id')->nullable()->index();
            $table->string('action', 64)->index();
            // login | impersonation_start | impersonation_stop |
            // workspace_suspend | workspace_unsuspend | flag_added | flag_cleared |
            // role_changed | member_invited | member_removed |
            // conversation_assigned | conversation_resolved | ...
            $table->string('subject_type', 64)->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->json('payload')->nullable();
            $table->string('ip', 64)->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->timestamp('created_at')->useCurrent()->index();

            $table->index(['subject_type', 'subject_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('workspace_flags');
        Schema::dropIfExists('platform_notes');
        Schema::dropIfExists('impersonation_sessions');
    }
};
