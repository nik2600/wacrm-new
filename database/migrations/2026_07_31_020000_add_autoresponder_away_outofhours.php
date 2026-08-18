<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Auto Responder — two more trigger types (Out-of-hours + Away) + rule Priority.
 *
 *  • keyword_replies.priority — lower number = higher priority when several
 *    auto-responder rules could fire on the same inbound (competitor parity).
 *  • workspaces.inbox_away    — the manual "Away mode" switch. When ON, `away`
 *    trigger-type rules auto-reply (like WhatsApp Business away messages).
 *
 * The `out_of_hours` trigger type needs NO new column — it reuses the existing
 * working_hours JSON already on keyword_replies. All idempotent so it is safe to
 * re-run on any client schema.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('keyword_replies') && ! Schema::hasColumn('keyword_replies', 'priority')) {
            Schema::table('keyword_replies', function (Blueprint $t) {
                $t->integer('priority')->default(0)->index();
            });
        }

        if (Schema::hasTable('workspaces') && ! Schema::hasColumn('workspaces', 'inbox_away')) {
            Schema::table('workspaces', function (Blueprint $t) {
                $t->boolean('inbox_away')->default(false);
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('keyword_replies', 'priority')) {
            Schema::table('keyword_replies', fn (Blueprint $t) => $t->dropColumn('priority'));
        }
        if (Schema::hasColumn('workspaces', 'inbox_away')) {
            Schema::table('workspaces', fn (Blueprint $t) => $t->dropColumn('inbox_away'));
        }
    }
};
