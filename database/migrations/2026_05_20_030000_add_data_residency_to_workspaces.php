<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sprint 9.3 — per-workspace data residency. Drives the GDPR /
 * cross-border-transfer toggle on /legal/data-processing.
 *
 *   any   (default): translation can use any active driver
 *   eu_only         : only DeepL EU endpoint + LibreTranslate
 *                     allowed; MyMemory + Google routes blocked
 *   local           : translation MUST stay on the buyer's own
 *                     server — only LibreTranslate (self-hosted)
 *                     allowed. Customer message text never leaves
 *                     this WaDesk install.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workspaces', function (Blueprint $table) {
            $table->string('data_residency', 16)->default('any')->after('default_language');
        });
    }

    public function down(): void
    {
        Schema::table('workspaces', function (Blueprint $table) {
            $table->dropColumn('data_residency');
        });
    }
};
