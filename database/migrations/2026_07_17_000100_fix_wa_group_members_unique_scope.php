<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Scope wa_group_members' unique index to the workspace.
 *
 * It was UNIQUE(group_jid, phone) — tenant-blind. But the member sync
 * deletes/re-inserts per workspace (`where group_jid ... where workspace_id`),
 * so when TWO workspaces sync the SAME WhatsApp group, workspace B's delete
 * never touches workspace A's rows and its insert then collides:
 *
 *   1062 Duplicate entry '1203634...@g.us-919389487259'
 *        for key 'wa_group_members_group_jid_phone_unique'
 *
 * which aborts the whole sync transaction — so B's group members never save.
 * The real key is (workspace_id, group_jid, phone): each workspace keeps its
 * own copy, while a phone still can't appear twice in one group per workspace.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('wa_group_members')) return;

        // Drop any pre-existing duplicates the old index couldn't have held but
        // a failed/partial sync may have left, so the new index can be built.
        $dupes = DB::table('wa_group_members')
            ->select('workspace_id', 'group_jid', 'phone', DB::raw('MIN(id) as keep_id'), DB::raw('COUNT(*) as c'))
            ->groupBy('workspace_id', 'group_jid', 'phone')
            ->having('c', '>', 1)
            ->get();
        foreach ($dupes as $d) {
            DB::table('wa_group_members')
                ->where('workspace_id', $d->workspace_id)
                ->where('group_jid', $d->group_jid)
                ->where('phone', $d->phone)
                ->where('id', '!=', $d->keep_id)
                ->delete();
        }

        Schema::table('wa_group_members', function (Blueprint $table) {
            try { $table->dropUnique('wa_group_members_group_jid_phone_unique'); } catch (\Throwable $e) {}
        });
        Schema::table('wa_group_members', function (Blueprint $table) {
            try { $table->unique(['workspace_id', 'group_jid', 'phone'], 'wa_group_members_ws_group_phone_unique'); } catch (\Throwable $e) {}
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('wa_group_members')) return;
        Schema::table('wa_group_members', function (Blueprint $table) {
            try { $table->dropUnique('wa_group_members_ws_group_phone_unique'); } catch (\Throwable $e) {}
            try { $table->unique(['group_jid', 'phone'], 'wa_group_members_group_jid_phone_unique'); } catch (\Throwable $e) {}
        });
    }
};
