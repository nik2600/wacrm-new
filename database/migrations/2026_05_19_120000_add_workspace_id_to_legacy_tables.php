<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Backfill multi-workspace scoping across 11 legacy tables.
 *
 * Adds a nullable `workspace_id` column + FK to each table and seeds
 * existing rows from the most reliable owner→workspace path available:
 *
 *   contacts, contact_groups, broadcasts, wa_templates, flows,
 *   webhooks, devices, notifications  →  user.current_workspace_id
 *   messages                          →  conversation.workspace_id
 *   auto_reply_lookups                →  device → user.current_workspace_id
 *
 * `wpcampaigns` has neither user_id nor any workspace FK — it gets
 * the column but no backfill; legacy rows stay NULL and the next-
 * write path will stamp them.
 *
 * Keeps the column NULLABLE on purpose. A follow-up migration can
 * make it NOT NULL once we've manually inspected any stragglers. We
 * never want the multi-workspace scoping fix itself to start hard-
 * failing inserts.
 */
return new class extends Migration {
    public function up(): void
    {
        $tables = [
            'contacts', 'contact_groups', 'broadcasts', 'wa_templates',
            'flows', 'webhooks', 'devices', 'notifications',
            'messages', 'auto_reply_lookups', 'wpcampaigns',
        ];

        // Step 1 — add the column to each table that doesn't already
        // have it. Nullable + FK with nullOnDelete so deleting a
        // workspace doesn't cascade-orphan rows the operator might
        // still want to read.
        foreach ($tables as $t) {
            if (!Schema::hasTable($t)) continue;
            if (Schema::hasColumn($t, 'workspace_id')) continue;
            Schema::table($t, function (Blueprint $tbl) use ($t) {
                $tbl->foreignId('workspace_id')->nullable()
                    ->constrained('workspaces')->nullOnDelete();
                $tbl->index('workspace_id');
            });
        }

        // Step 2 — backfill from user.current_workspace_id for the
        // owner-based tables. Single SQL UPDATE per table for speed.
        foreach (['contacts', 'contact_groups', 'broadcasts', 'wa_templates', 'flows', 'webhooks', 'devices', 'notifications'] as $t) {
            if (!Schema::hasTable($t) || !Schema::hasColumn($t, 'workspace_id') || !Schema::hasColumn($t, 'user_id')) continue;
            DB::statement("
                UPDATE {$t} t
                INNER JOIN users u ON u.id = t.user_id
                SET t.workspace_id = u.current_workspace_id
                WHERE t.workspace_id IS NULL
                  AND u.current_workspace_id IS NOT NULL
            ");
        }

        // Step 3 — messages backfill via conversations (more reliable
        // than user.current_workspace_id since messages already chain
        // through a conversation that was workspace-stamped on inbound).
        if (Schema::hasTable('messages') && Schema::hasColumn('messages', 'workspace_id')) {
            DB::statement("
                UPDATE messages m
                INNER JOIN conversations c ON c.id = m.conversation_id
                SET m.workspace_id = c.workspace_id
                WHERE m.workspace_id IS NULL
                  AND c.workspace_id IS NOT NULL
            ");
        }

        // Step 4 — auto_reply_lookups backfill via device → user.
        if (Schema::hasTable('auto_reply_lookups') && Schema::hasColumn('auto_reply_lookups', 'workspace_id')) {
            DB::statement("
                UPDATE auto_reply_lookups arl
                INNER JOIN devices d ON d.id = arl.device_id
                INNER JOIN users u   ON u.id = d.user_id
                SET arl.workspace_id = u.current_workspace_id
                WHERE arl.workspace_id IS NULL
                  AND u.current_workspace_id IS NOT NULL
            ");
        }
    }

    public function down(): void
    {
        $tables = [
            'contacts', 'contact_groups', 'broadcasts', 'wa_templates',
            'flows', 'webhooks', 'devices', 'notifications',
            'messages', 'auto_reply_lookups', 'wpcampaigns',
        ];
        foreach ($tables as $t) {
            if (!Schema::hasTable($t)) continue;
            if (!Schema::hasColumn($t, 'workspace_id')) continue;
            Schema::table($t, function (Blueprint $tbl) {
                $tbl->dropForeign(['workspace_id']);
                $tbl->dropIndex(['workspace_id']);
                $tbl->dropColumn('workspace_id');
            });
        }
    }
};
