<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-fire event log for the auto-reply analytics page.
 *
 *   keyword_replies.trigger_count gives the cumulative number — useful for
 *   the index "top performers" tile.
 *   keyword_reply_logs gives the per-event data that the analytics page
 *   needs for the recent-triggers feed, top-users list, hour heatmap,
 *   variant breakdown, and the daily-firing-pattern chart.
 *
 *   Inserted from AutoReplyController::lookup() on every successful match.
 *   Phone numbers are PII so the column is encrypted at rest.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('keyword_reply_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('keyword_reply_id')->index();
            $table->unsignedBigInteger('content_id')->nullable();   // which variant fired
            $table->text('contact_phone');                          // encrypted
            $table->text('matched_text')->nullable();               // encrypted, what the customer typed
            $table->string('matched_variant', 64)->nullable();      // which keyword token matched (for fuzzy)
            $table->timestamp('fired_at')->index();
            $table->timestamps();

            $table->index(['keyword_reply_id', 'fired_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('keyword_reply_logs');
    }
};
