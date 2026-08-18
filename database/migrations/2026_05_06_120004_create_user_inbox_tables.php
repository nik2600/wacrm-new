<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Per-(user, workspace) presence + capacity. AssignmentService reads this
        // to decide eligibility: away/busy users are skipped, and a user already
        // at capacity is skipped for new assignments.
        Schema::create('agent_statuses', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->index();
            $table->unsignedBigInteger('workspace_id')->index();
            $table->string('status', 12)->default('online');
            // online | away | busy | offline
            $table->string('status_message', 128)->nullable();
            $table->timestamp('last_seen_at')->nullable()->index();
            $table->unsignedInteger('current_load')->default(0);
            // count of open conversations currently assigned to this user
            $table->unsignedInteger('today_replies')->default(0);
            $table->unsignedInteger('today_resolutions')->default(0);
            $table->date('counters_date')->nullable();
            // when counters_date != today, reset today_* on next read
            $table->json('preferences')->nullable();
            // notif sounds, desktop push, etc.
            $table->timestamps();

            $table->unique(['user_id', 'workspace_id']);
        });

        // In-app notification feed. Email sends are dispatched as jobs and are
        // not stored here (the job itself is durable in the queue).
        Schema::create('inbox_notifications', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->index();
            $table->unsignedBigInteger('workspace_id')->index();
            $table->string('type', 32)->index();
            // assigned | mentioned | sla_warning | sla_breach | reply_received |
            // resolved | reopened | csat_received | system
            $table->string('title', 200);
            $table->text('body')->nullable();
            $table->json('data')->nullable();
            // { conversation_id, contact_name, by_user_id, ... }
            $table->string('link', 500)->nullable();
            $table->timestamp('read_at')->nullable()->index();
            $table->timestamps();

            $table->index(['user_id', 'read_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inbox_notifications');
        Schema::dropIfExists('agent_statuses');
    }
};
