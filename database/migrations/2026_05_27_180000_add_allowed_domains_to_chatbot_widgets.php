<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * CORS whitelist for chatbot widget public endpoints.
     *
     * Each widget can be embedded on the operator's own sites only.
     * `allowed_domains` is a JSON array of bare hostnames (no scheme,
     * no path) — e.g. ["acme.com","staging.acme.com","localhost:3000"].
     *
     * Empty array = "block every origin" (the operator hasn't added
     * any domain yet, so the embed is effectively paused for the
     * public). NULL = legacy row, behave as empty.
     *
     * The CORS middleware reads this list, echoes the request Origin
     * back as `Access-Control-Allow-Origin` when matched, and returns
     * 403 otherwise. Without this column every visitor's browser
     * blocked the widget on first XHR with a CORS preflight error.
     */
    public function up(): void
    {
        Schema::table('chatbot_widgets', function (Blueprint $t) {
            $t->json('allowed_domains')->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('chatbot_widgets', function (Blueprint $t) {
            $t->dropColumn('allowed_domains');
        });
    }
};
