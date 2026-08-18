<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Follow-up to support_module_phase1_schema. Two HIGH-severity fixes
 * surfaced by the review:
 *
 * 1. `sla_policies.workspace_id` was NOT NULL — admin-side platform-wide
 *    SLA policies (the only ones the admin's /admin/support/sla form
 *    creates) need it nullable, otherwise INSERT crashes.
 *
 * 2. `support_tickets.status` had legacy values `awaiting_support` /
 *    `awaiting_user` from the pre-Phase-1 schema. The new kanban + UI
 *    use `open | in_progress | pending | resolved | closed`. Remap the
 *    legacy values so existing tickets stay visible.
 */
return new class extends Migration
{
    public function up(): void
    {
        // 1. Make sla_policies.workspace_id nullable. Uses raw SQL because
        //    doctrine/dbal may not be installed and ->nullable()->change()
        //    requires it.
        DB::statement('ALTER TABLE sla_policies MODIFY workspace_id BIGINT UNSIGNED NULL');

        // 2. Remap legacy status values.
        DB::table('support_tickets')->where('status', 'awaiting_support')->update(['status' => 'pending']);
        DB::table('support_tickets')->where('status', 'awaiting_user')->update(['status' => 'in_progress']);
    }

    public function down(): void
    {
        // We don't reverse the status remap — the legacy strings are gone.
        // Reverse the column nullability only.
        DB::statement('ALTER TABLE sla_policies MODIFY workspace_id BIGINT UNSIGNED NOT NULL');
    }
};
