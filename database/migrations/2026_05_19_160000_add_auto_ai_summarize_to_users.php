<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-user toggle that switches the "Auto AI summarize" behaviour on
 * the global <x-compose-textarea> component. When true, every keystroke
 * (debounced) gets sent to the AI review endpoint and the operator sees
 * red/green annotations + a best-version suggestion below the input.
 * When false, the same flow runs on-demand via the toolbar's sparkle
 * button instead of automatically.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'auto_ai_summarize_enabled')) {
                $table->boolean('auto_ai_summarize_enabled')
                    ->default(false)
                    ->after('theme_preference');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'auto_ai_summarize_enabled')) {
                $table->dropColumn('auto_ai_summarize_enabled');
            }
        });
    }
};
