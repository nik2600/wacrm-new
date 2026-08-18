<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * `workspaces.brand_color` shipped as NOT NULL DEFAULT '#075E54'.
 *
 * The app wants it NULLABLE: a null brand colour means "no deliberate choice",
 * so workspace_brand_color() follows the active theme instead of pinning every
 * workspace avatar to the shipped green forever. Registration passed an explicit
 * null for exactly that reason and crashed the whole signup with:
 *
 *   SQLSTATE[23000]: Integrity constraint violation: 1048
 *   Column 'brand_color' cannot be null
 *
 * Raw ALTER rather than ->change(): doctrine/dbal isn't a dependency here, and
 * this keeps the existing default intact for rows/inserts that don't set it.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('workspaces') || ! Schema::hasColumn('workspaces', 'brand_color')) {
            return;
        }

        try {
            DB::statement("ALTER TABLE `workspaces` MODIFY `brand_color` VARCHAR(16) NULL DEFAULT '#075E54'");
        } catch (\Throwable $e) {
            // Non-MySQL driver or insufficient grants — the controller-side guard
            // (AuthController: only set brand_color when non-empty) already keeps
            // registration working, so never fail the update over this.
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('workspaces') || ! Schema::hasColumn('workspaces', 'brand_color')) {
            return;
        }

        try {
            DB::statement("UPDATE `workspaces` SET `brand_color` = '#075E54' WHERE `brand_color` IS NULL");
            DB::statement("ALTER TABLE `workspaces` MODIFY `brand_color` VARCHAR(16) NOT NULL DEFAULT '#075E54'");
        } catch (\Throwable $e) {
            // best-effort
        }
    }
};
