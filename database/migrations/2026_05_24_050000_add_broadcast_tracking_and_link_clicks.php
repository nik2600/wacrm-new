<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Broadcast tracking + URL click tracking.
 *
 * `broadcasts` already has `success_count` and `fail_count`. This
 * migration adds the granular per-state columns so the index +
 * detail pages can show full lifecycle metrics (sent → delivered
 * → read → clicked).
 *
 * `wa_link_clicks` is the new short-URL table. When LinkTracker
 * wraps a URL inside a template button or text body, it inserts
 * a row keyed by a random token. The /r/{token} route looks up
 * the row, increments `clicks`, records the click context, and
 * 302-redirects to `original_url`.
 *
 * Click context is recorded so the broadcasts detail page can
 * answer "which recipient clicked which link". We index on
 * (broadcast_id) and (message_id) for the per-broadcast detail
 * query, plus a unique index on `token` for the lookup hot path.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('broadcasts', function (Blueprint $table) {
            $table->unsignedInteger('delivered_count')->default(0)->after('success_count');
            $table->unsignedInteger('read_count')->default(0)->after('delivered_count');
            $table->unsignedInteger('clicked_count')->default(0)->after('read_count');
        });

        Schema::create('wa_link_clicks', function (Blueprint $table) {
            $table->id();
            $table->string('token', 24)->unique();
            $table->text('original_url');

            // Send-time context — nullable so the same shortener can
            // be used outside broadcast/campaign sends in the future.
            $table->unsignedBigInteger('workspace_id')->nullable()->index();
            $table->unsignedBigInteger('broadcast_id')->nullable()->index();
            $table->unsignedBigInteger('campaign_id')->nullable()->index();
            $table->unsignedBigInteger('message_id')->nullable()->index();
            $table->unsignedBigInteger('contact_id')->nullable()->index();
            $table->unsignedBigInteger('template_id')->nullable();
            $table->string('phone', 32)->nullable()->index();

            // Click metrics — `clicks` is bumped on every visit (Meta
            // doesn't deduplicate, neither do we). `unique_clicks` is
            // a best-effort distinct-IP tally for the UI.
            $table->unsignedInteger('clicks')->default(0);
            $table->unsignedInteger('unique_clicks')->default(0);
            $table->timestamp('first_click_at')->nullable();
            $table->timestamp('last_click_at')->nullable();
            $table->string('last_ip', 45)->nullable();
            $table->string('last_user_agent', 191)->nullable();

            // Expiry — short URLs live 90 days by default. Past
            // expiry the redirect returns 410 Gone (no quiet 404).
            $table->timestamp('expires_at')->nullable()->index();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::table('broadcasts', function (Blueprint $table) {
            $table->dropColumn(['delivered_count', 'read_count', 'clicked_count']);
        });
        Schema::dropIfExists('wa_link_clicks');
    }
};
