<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Adds the 3 new reply-action target columns so a keyword can trigger:
     *   - target_contact_id   → share that Contact's vCard back to the customer (#19)
     *   - target_catalog_id   → send a Meta-catalog product list (#20)
     *   - request_location    → send a "share your location" prompt (#23)
     *
     * The `reply_type` column is already a free string, no enum migration
     * needed — we just teach the autoreply controller to recognize the
     * 3 new string values: 'share_contact', 'send_catalog', 'request_location'.
     */
    public function up(): void
    {
        Schema::table('keyword_replies', function (Blueprint $table) {
            // Original column was varchar(12) — too short for the new
            // values 'share_contact' (13) and 'request_location' (16).
            // Widening to 32 leaves headroom for future types.
            $table->string('reply_type', 32)->change();
            $table->unsignedBigInteger('target_contact_id')->nullable()->after('flow_id');
            $table->unsignedBigInteger('target_catalog_id')->nullable()->after('target_contact_id');
        });
    }

    public function down(): void
    {
        Schema::table('keyword_replies', function (Blueprint $table) {
            $table->dropColumn(['target_contact_id', 'target_catalog_id']);
            $table->string('reply_type', 12)->change();
        });
    }
};
