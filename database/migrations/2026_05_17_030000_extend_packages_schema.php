<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Round out the packages table so EVERY user-facing feature is
 * bindable to a plan. Existing columns already cover the obvious
 * "how many X can you create" cases (devices, contacts, templates,
 * broadcasts, campaigns, flows, autoreplies, etc.). This migration
 * adds:
 *
 *  - 7 numeric LIMIT columns that were missing
 *  - 5 per-integration ACCESS toggles
 *  - 11 granular ACCESS toggles for features that previously had
 *    no plan gate at all
 *
 * Empty / NULL on a limit column = unlimited (matches
 * Workspace::effectiveLimit() fallback behaviour).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('packages', function (Blueprint $table) {
            // ── New numeric LIMITs ──
            $table->unsignedInteger('workspaces_per_owner_limit')->nullable()->after('user_seat_limit');
            $table->unsignedInteger('routing_rules_limit')->nullable()->after('workspaces_per_owner_limit');
            $table->unsignedInteger('drip_campaigns_limit')->nullable()->after('routing_rules_limit');
            $table->unsignedInteger('appointments_limit')->nullable()->after('drip_campaigns_limit');
            $table->unsignedInteger('ai_agents_limit')->nullable()->after('appointments_limit');
            $table->unsignedInteger('saved_replies_limit')->nullable()->after('ai_agents_limit');
            $table->unsignedInteger('webhooks_limit')->nullable()->after('saved_replies_limit');

            // ── Per-integration access toggles ──
            $table->boolean('integration_shopify')->default(true)->after('webhooks_limit');
            $table->boolean('integration_woocommerce')->default(true)->after('integration_shopify');
            $table->boolean('integration_hubspot')->default(true)->after('integration_woocommerce');
            $table->boolean('integration_google_calendar')->default(true)->after('integration_hubspot');
            $table->boolean('integration_google_sheets')->default(true)->after('integration_google_calendar');

            // ── Granular feature toggles ──
            $table->boolean('access_kanban_view')->default(true)->after('integration_google_sheets');
            $table->boolean('access_appointment_booking')->default(true)->after('access_kanban_view');
            $table->boolean('access_edit_messages')->default(true)->after('access_appointment_booking');
            $table->boolean('access_internal_notes')->default(true)->after('access_edit_messages');
            $table->boolean('access_message_reactions')->default(true)->after('access_internal_notes');
            $table->boolean('access_routing_rules')->default(true)->after('access_message_reactions');
            $table->boolean('access_business_hours')->default(true)->after('access_routing_rules');
            $table->boolean('access_team_performance')->default(true)->after('access_business_hours');
            $table->boolean('access_outbound_webhooks')->default(true)->after('access_team_performance');
            $table->boolean('access_keyword_replies')->default(true)->after('access_outbound_webhooks');
            $table->boolean('access_ai_agents')->default(true)->after('access_keyword_replies');
        });
    }

    public function down(): void
    {
        Schema::table('packages', function (Blueprint $table) {
            $table->dropColumn([
                'workspaces_per_owner_limit',
                'routing_rules_limit',
                'drip_campaigns_limit',
                'appointments_limit',
                'ai_agents_limit',
                'saved_replies_limit',
                'webhooks_limit',
                'integration_shopify',
                'integration_woocommerce',
                'integration_hubspot',
                'integration_google_calendar',
                'integration_google_sheets',
                'access_kanban_view',
                'access_appointment_booking',
                'access_edit_messages',
                'access_internal_notes',
                'access_message_reactions',
                'access_routing_rules',
                'access_business_hours',
                'access_team_performance',
                'access_outbound_webhooks',
                'access_keyword_replies',
                'access_ai_agents',
            ]);
        });
    }
};
