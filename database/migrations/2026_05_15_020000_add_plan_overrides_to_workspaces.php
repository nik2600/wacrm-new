<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Per-workspace limit overrides (#31-36). The `packages` table
     * already has 16+ limit columns; this JSON lets admins bump one
     * specific limit on one specific workspace without forking the
     * whole plan.
     *
     * Shape:
     *   { contacts_limit: 50000, broadcast_limit: 200, ... }
     *
     * Missing keys → inherit from the workspace's plan.
     * Workspace::effectiveLimit('contacts_limit') applies the merge.
     */
    public function up(): void
    {
        Schema::table('workspaces', function (Blueprint $table) {
            $table->json('plan_overrides')->nullable()->after('plan');
        });
    }

    public function down(): void
    {
        Schema::table('workspaces', function (Blueprint $table) {
            $table->dropColumn('plan_overrides');
        });
    }
};
