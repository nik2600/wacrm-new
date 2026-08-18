<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Meta Business Agent coexistence (MBA-0/MBA-1). Per-workspace flags that let a
 * merchant declare "Meta's Business Agent answers my WhatsApp" so OUR automated
 * responders (AI agent + keyword auto-reply) stand down — no double replies.
 *
 *   ai_responder_mode:
 *     wadesk_only            — our AI/keyword auto-reply (default, unchanged)
 *     meta_agent_only        — Meta's agent answers; we only observe/log
 *     meta_agent_then_handoff— Meta fronts tier-1; escalations land in Team Inbox
 */
return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('workspaces')) {
            return;
        }
        Schema::table('workspaces', function (Blueprint $t) {
            if (!Schema::hasColumn('workspaces', 'meta_agent_enabled')) {
                $t->boolean('meta_agent_enabled')->default(false)->after('default_engine');
            }
            if (!Schema::hasColumn('workspaces', 'ai_responder_mode')) {
                $t->string('ai_responder_mode', 32)->default('wadesk_only')->after('meta_agent_enabled');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('workspaces')) {
            return;
        }
        Schema::table('workspaces', function (Blueprint $t) {
            foreach (['meta_agent_enabled', 'ai_responder_mode'] as $col) {
                if (Schema::hasColumn('workspaces', $col)) {
                    $t->dropColumn($col);
                }
            }
        });
    }
};
