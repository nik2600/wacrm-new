<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * WABA-template Meta-sync columns.
 *
 * The existing `wa_templates` row models the local draft and the
 * legacy single-tenant approval flow. This migration layers the
 * Meta Cloud API state machine on top WITHOUT touching the
 * existing columns or the `status` enum the UI already uses:
 *
 *   `provider_config_id`     → which WABA submitted this template
 *                              (multi-WABA workspaces need this FK)
 *   `meta_template_id`       → Meta's returned id; needed for
 *                              GET /{id} polls, edit, delete, and
 *                              every `type:template` send
 *   `meta_status`            → raw Meta enum (APPROVED, REJECTED,
 *                              PENDING, IN_APPEAL, DISABLED, PAUSED,
 *                              LIMIT_EXCEEDED, FLAGGED, …)
 *   `parameter_format`       → POSITIONAL ({{1}}) or NAMED
 *                              ({{first_name}}). Default POSITIONAL.
 *   `quality_score`          → UNKNOWN | GREEN | YELLOW | RED, from
 *                              `message_template_quality_update`
 *                              webhook. Drives send-time guardrails.
 *   `rejection_reason_code`  → Meta's enum (ABUSIVE_CONTENT,
 *                              INVALID_FORMAT, PROMOTIONAL,
 *                              TAG_CONTENT_MISMATCH, SCAM, NONE).
 *                              Kept SEPARATE from the existing
 *                              `rejection_reason` text column so the
 *                              admin UI can both render Meta's enum
 *                              AND a human-written note.
 *   `submitted_at`           → when we POSTed to Meta
 *   `last_synced_at`         → debounce key for the 30-min sweep job;
 *                              prevents thundering herd on big tenants
 *   `paused_until`           → if PAUSED, when Meta said it can run
 *                              again. NULL = not paused.
 *
 * Indexes:
 *   - `meta_template_id` unique → webhook lookup hot path
 *   - `(provider_config_id, meta_status)` → sweep job WHERE clause
 *
 * Backwards-compat: every new column is nullable with a sensible
 * default. The existing local-only approval flow (`status` ∈
 * pending/approved/rejected/public) keeps working untouched — Meta
 * sync is opt-in per row, gated by `provider_config_id IS NOT NULL`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('wa_templates', function (Blueprint $table) {
            $table->unsignedBigInteger('provider_config_id')->nullable()->after('workspace_id');
            $table->string('meta_template_id', 64)->nullable()->after('provider_config_id');
            $table->string('meta_status', 32)->nullable()->after('meta_template_id');
            $table->string('parameter_format', 16)->default('POSITIONAL')->after('language');
            $table->string('quality_score', 16)->nullable()->after('meta_status');
            $table->string('rejection_reason_code', 64)->nullable()->after('rejection_reason');
            $table->timestamp('submitted_at')->nullable()->after('approved_at');
            $table->timestamp('last_synced_at')->nullable()->after('submitted_at');
            $table->timestamp('paused_until')->nullable()->after('last_synced_at');

            $table->index('provider_config_id');
            $table->unique('meta_template_id');
            $table->index(['provider_config_id', 'meta_status'], 'wa_templates_cfg_status_idx');
            $table->index('meta_status');
        });

        // Backfill: legacy locally-approved rows get meta_status=APPROVED
        // so existing seeded templates remain sendable through the new
        // dispatcher path (which only sends if meta_status=APPROVED).
        // Locally-rejected rows mirror to REJECTED; pending stays PENDING.
        \DB::table('wa_templates')->where('status', 'approved')->update(['meta_status' => 'APPROVED']);
        \DB::table('wa_templates')->where('status', 'public')->update(['meta_status' => 'APPROVED']);
        \DB::table('wa_templates')->where('status', 'rejected')->update(['meta_status' => 'REJECTED']);
        \DB::table('wa_templates')->where('status', 'pending')->update(['meta_status' => 'PENDING']);
    }

    public function down(): void
    {
        Schema::table('wa_templates', function (Blueprint $table) {
            $table->dropIndex('wa_templates_cfg_status_idx');
            $table->dropIndex(['provider_config_id']);
            $table->dropIndex(['meta_status']);
            $table->dropUnique(['meta_template_id']);

            $table->dropColumn([
                'provider_config_id',
                'meta_template_id',
                'meta_status',
                'parameter_format',
                'quality_score',
                'rejection_reason_code',
                'submitted_at',
                'last_synced_at',
                'paused_until',
            ]);
        });
    }
};
