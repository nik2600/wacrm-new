<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Support tickets — surfaces on /support (submission form) and on
 * /account?tab=support (per-user history list). Status machine:
 *
 *   open ─▶ awaiting_support ─▶ awaiting_user ─▶ resolved
 *
 * `open` is the initial state right after submit; `awaiting_support`
 * lights up when the operator pings back; `awaiting_user` flips when
 * WaDesk support has replied and we're waiting on the operator;
 * `resolved` is terminal. The /more card counts non-resolved as
 * "open" for its chip.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('support_tickets', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->foreignId('workspace_id')->nullable()->constrained()->nullOnDelete();

            // Public-facing ticket number — operator-friendly, shown
            // everywhere the row is referenced (UI lists, emails).
            // Generated as "TKT-<6 random alphanumerics>" and uniqued.
            $t->string('ticket_number', 16)->unique();

            // Reason buckets — same set the /support sidebar uses.
            $t->string('reason', 32)->default('other');

            $t->string('name', 191);
            $t->string('email', 191);
            $t->string('subject', 191);
            $t->text('message');

            // Status workflow.
            $t->string('status', 24)->default('open');
            $t->timestamp('last_reply_at')->nullable();
            $t->timestamp('resolved_at')->nullable();

            $t->timestamps();
            $t->softDeletes();

            $t->index(['user_id', 'status']);
            $t->index(['workspace_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('support_tickets');
    }
};
