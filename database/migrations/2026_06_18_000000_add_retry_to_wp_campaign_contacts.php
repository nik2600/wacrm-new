<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Durable campaign delivery — bounded automatic retry with backoff.
 *
 * The per-recipient row in wp_campaign_contacts is already the persistent
 * progress ledger (sent/failed/pending) that survives a restart and is
 * resumed by CampaignScheduleSweeper. These two columns let a FAILED send
 * be retried a bounded number of times with exponential backoff instead of
 * staying terminally failed after one attempt:
 *   - send_attempts:   how many times we've tried this recipient
 *   - next_attempt_at: earliest UTC time the next retry may run (backoff)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('wp_campaign_contacts', function (Blueprint $table) {
            if (!Schema::hasColumn('wp_campaign_contacts', 'send_attempts')) {
                $table->unsignedTinyInteger('send_attempts')->default(0)->after('status');
            }
            if (!Schema::hasColumn('wp_campaign_contacts', 'next_attempt_at')) {
                $table->timestamp('next_attempt_at')->nullable()->after('send_attempts')->index();
            }
        });
    }

    public function down(): void
    {
        Schema::table('wp_campaign_contacts', function (Blueprint $table) {
            foreach (['send_attempts', 'next_attempt_at'] as $col) {
                if (Schema::hasColumn('wp_campaign_contacts', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
