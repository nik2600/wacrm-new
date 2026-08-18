<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-call audit of every inbound the bot asked us to match against.
 *
 * Every hit on /api/keyword-replies writes one row here — whether it
 * matched a rule or not. That gives us:
 *
 *   - "Incoming" funnel step       — count of all rows for the device
 *   - "Matched any rule" step      — rows where matched_keyword_reply_id IS NOT NULL
 *   - "Matched this rule" step     — rows where matched_keyword_reply_id = X
 *   - "Reply latency"              — avg(latency_ms) from rows for this rule
 *
 * keyword_reply_logs (created in the previous migration) only stores
 * successful fires, so it can't power the conversion funnel by itself.
 *
 * Phone + query_text are PII so the columns are encrypted at rest.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('auto_reply_lookups', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('device_id')->nullable()->index();
            $table->unsignedBigInteger('matched_keyword_reply_id')->nullable()->index();
            $table->text('contact_phone');     // encrypted
            $table->text('query_text');        // encrypted
            $table->unsignedInteger('latency_ms')->nullable();
            $table->timestamp('created_at')->index()->useCurrent();

            $table->index(['device_id', 'created_at']);
            $table->index(['matched_keyword_reply_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('auto_reply_lookups');
    }
};
