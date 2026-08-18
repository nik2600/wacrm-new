<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Follow-up to the legacy backfill migration — adds `workspace_id`
 * to `meta_campaigns` (Meta Ads campaigns). Same nullable + backfill
 * pattern; never NOT NULL so the scoping fix can't reject inserts.
 */
return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('meta_campaigns')) return;
        if (Schema::hasColumn('meta_campaigns', 'workspace_id')) return;

        Schema::table('meta_campaigns', function (Blueprint $t) {
            $t->foreignId('workspace_id')->nullable()
                ->constrained('workspaces')->nullOnDelete();
            $t->index('workspace_id');
        });

        if (Schema::hasColumn('meta_campaigns', 'user_id')) {
            DB::statement("
                UPDATE meta_campaigns m
                INNER JOIN users u ON u.id = m.user_id
                SET m.workspace_id = u.current_workspace_id
                WHERE m.workspace_id IS NULL
                  AND u.current_workspace_id IS NOT NULL
            ");
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('meta_campaigns')) return;
        if (!Schema::hasColumn('meta_campaigns', 'workspace_id')) return;
        Schema::table('meta_campaigns', function (Blueprint $t) {
            $t->dropForeign(['workspace_id']);
            $t->dropIndex(['workspace_id']);
            $t->dropColumn('workspace_id');
        });
    }
};
