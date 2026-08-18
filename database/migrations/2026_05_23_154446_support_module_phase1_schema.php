<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Support module Phase 1 schema.
 *
 * Adds:
 *   - support_messages       : thread under each ticket (admin replies + customer replies)
 *   - support_agents         : pivot for which users are support agents (per workspace OR platform-wide when workspace_id=NULL)
 *   - support_assignments    : history of who handled which ticket
 *   - sla_breaches           : audit of every time a ticket missed first_response or resolution
 *   - playbooks              : reusable macro sequences (set status, send template, assign agent, add tag)
 * Extends support_tickets with: priority, assigned_agent_id, sla_policy_id, first_response_at,
 *   meta (json), tags (json).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('support_messages', function (Blueprint $t) {
            $t->id();
            $t->foreignId('ticket_id')->constrained('support_tickets')->cascadeOnDelete();
            $t->foreignId('author_user_id')->nullable()->constrained('users')->nullOnDelete();
            $t->string('author_role', 16)->default('admin'); // admin | customer | system
            $t->text('body');
            $t->json('attachments')->nullable();
            $t->boolean('is_internal_note')->default(false);  // private to admins
            $t->timestamp('created_at')->useCurrent();
            $t->index(['ticket_id', 'created_at']);
        });

        Schema::create('support_agents', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $t->foreignId('workspace_id')->nullable()->constrained('workspaces')->cascadeOnDelete();
            $t->boolean('is_active')->default(true);
            $t->string('specialty', 60)->nullable();    // e.g. "billing", "integrations"
            $t->unsignedInteger('current_load')->default(0); // open tickets assigned
            $t->timestamps();
            $t->unique(['user_id', 'workspace_id']);
        });

        Schema::create('support_assignments', function (Blueprint $t) {
            $t->id();
            $t->foreignId('ticket_id')->constrained('support_tickets')->cascadeOnDelete();
            $t->foreignId('agent_user_id')->constrained('users')->cascadeOnDelete();
            $t->timestamp('assigned_at')->useCurrent();
            $t->timestamp('released_at')->nullable();
            $t->string('outcome', 24)->nullable(); // resolved | abandoned | reassigned
            $t->index(['ticket_id', 'released_at']);
        });

        Schema::create('sla_breaches', function (Blueprint $t) {
            $t->id();
            $t->foreignId('ticket_id')->constrained('support_tickets')->cascadeOnDelete();
            $t->foreignId('sla_policy_id')->nullable()->constrained('sla_policies')->nullOnDelete();
            $t->string('breach_type', 24); // first_response | resolution
            $t->timestamp('breached_at')->useCurrent();
            $t->string('severity', 16)->default('warn'); // warn | breach | hard_breach
            $t->unsignedInteger('over_by_minutes')->nullable();
            $t->index(['ticket_id', 'breach_type']);
        });

        Schema::create('playbooks', function (Blueprint $t) {
            $t->id();
            $t->foreignId('workspace_id')->nullable()->constrained('workspaces')->cascadeOnDelete();
            $t->string('name', 120);
            $t->string('slug', 140)->unique();
            $t->string('trigger_type', 24)->default('manual'); // manual | status_change | tag_added
            $t->string('trigger_value', 120)->nullable();       // e.g. "refund" for tag trigger
            $t->json('steps')->nullable();   // ordered list of { action, params }
            $t->boolean('is_active')->default(true);
            $t->unsignedInteger('use_count')->default(0);
            $t->timestamps();
        });

        Schema::table('support_tickets', function (Blueprint $t) {
            $t->string('priority', 12)->default('normal')->after('status'); // low | normal | high | urgent
            $t->foreignId('assigned_agent_id')->nullable()->after('priority')->constrained('users')->nullOnDelete();
            $t->foreignId('sla_policy_id')->nullable()->after('assigned_agent_id')->constrained('sla_policies')->nullOnDelete();
            $t->timestamp('first_response_at')->nullable()->after('last_reply_at');
            $t->json('tags')->nullable()->after('first_response_at');
            $t->json('meta')->nullable()->after('tags');
            $t->index(['status', 'priority']);
            $t->index('assigned_agent_id');
        });
    }

    public function down(): void
    {
        Schema::table('support_tickets', function (Blueprint $t) {
            $t->dropForeign(['assigned_agent_id']);
            $t->dropForeign(['sla_policy_id']);
            $t->dropColumn(['priority', 'assigned_agent_id', 'sla_policy_id', 'first_response_at', 'tags', 'meta']);
        });
        Schema::dropIfExists('playbooks');
        Schema::dropIfExists('sla_breaches');
        Schema::dropIfExists('support_assignments');
        Schema::dropIfExists('support_agents');
        Schema::dropIfExists('support_messages');
    }
};
