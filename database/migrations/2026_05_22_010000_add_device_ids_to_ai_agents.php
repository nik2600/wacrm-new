<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Multi-device AI agent scoping.
 *
 * When a workspace pairs more than one WhatsApp number, the operator
 * may want different AI agents per device (e.g. a "Sales Bot" persona
 * for the marketing number, a "Support Bot" for the helpdesk number).
 *
 * `device_ids` stores a JSON array of device ids the agent is allowed
 * to auto-respond on. NULL = any device (the existing behavior, so
 * single-device workspaces and pre-multi-device agents keep working
 * unchanged without a backfill).
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('ai_agents', function (Blueprint $table) {
            $table->json('device_ids')->nullable()->after('is_active');
        });
    }

    public function down(): void
    {
        Schema::table('ai_agents', function (Blueprint $table) {
            $table->dropColumn('device_ids');
        });
    }
};
