<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Allow MULTIPLE wa_provider_configs rows per workspace.
 *
 * The base table (2026_05_08_120000) added unique('workspace_id'). But
 * two features need several rows per workspace and were silently
 * blocked by it:
 *   - Multi-WABA (is_primary, 2026_05_23_220000) — its docblock claimed
 *     "no unique constraint blocks it", but the unique was never
 *     actually dropped.
 *   - Per-workspace Meta Ads credentials (provider=meta_ads) — a
 *     workspace that already sends via baileys/waba/twilio must also be
 *     able to store a separate meta_ads row for Click-to-WhatsApp keys.
 *
 * Dropping the unique fixes both. The (workspace_id, is_primary) index
 * remains for fast lookups, and uniqueness of the "primary sender" is
 * enforced in code (setAsPrimary + WorkspaceEngine), not by the schema.
 */
return new class extends Migration
{
    public function up(): void
    {
        $hasUnique = collect(Schema::getIndexes('wa_provider_configs'))
            ->contains(fn ($i) => ($i['unique'] ?? false)
                && count($i['columns'] ?? []) === 1
                && in_array('workspace_id', $i['columns'], true));

        if ($hasUnique) {
            Schema::table('wa_provider_configs', function (Blueprint $t) {
                $t->dropUnique('wa_provider_configs_workspace_id_unique');
            });
        }
    }

    public function down(): void
    {
        // Best-effort: re-adding the unique fails if multi-row data
        // already exists, which is expected once the new features run.
        try {
            Schema::table('wa_provider_configs', function (Blueprint $t) {
                $t->unique('workspace_id');
            });
        } catch (\Throwable $e) {
            // leave non-unique — multi-row data is in use
        }
    }
};
