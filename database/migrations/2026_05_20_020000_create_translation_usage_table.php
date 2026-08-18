<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sprint 9 — translation usage ledger. One row per API call that
 * actually hit the wire (dictionary + cache hits do NOT log here).
 * Powers the /admin/translation-usage dashboard and the per-workspace
 * cost breakdown.
 *
 * cost_estimate is in micros (1e-6 of one USD). 240k chars × DeepL
 * pro rate ($20/M) = ~$4.80 = 4_800_000 micros. Storing as integer
 * keeps arithmetic exact and avoids float-rounding bugs in the
 * dashboard's SUM queries.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('translation_usage', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('workspace_id')->nullable()->index();
            $table->string('provider_slug', 32)->index();
            $table->string('source_lang', 8);
            $table->string('target_lang', 8);
            $table->unsignedInteger('chars_in')->default(0);
            $table->unsignedInteger('chars_out')->default(0);
            // Cost in micros (millionths of a dollar). 0 for free
            // drivers (mymemory / libretranslate / google_gtx).
            $table->unsignedBigInteger('cost_micros')->default(0);
            $table->boolean('was_fallback')->default(false);
            $table->timestamp('called_at')->useCurrent()->index();
            $table->index(['workspace_id', 'called_at']);
            $table->index(['provider_slug', 'called_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('translation_usage');
    }
};
