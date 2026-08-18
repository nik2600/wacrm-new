<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Twilio Content Templates require a per-template ContentSid (e.g. HX...)
 * that maps the local template to its approved counterpart in Twilio's
 * Content Builder. Without this column every Twilio "template" send fell
 * back to a plain `Body` text payload — losing Meta template approval and
 * putting the workspace's Twilio number at risk of suspension when used
 * for MARKETING/UTILITY/AUTHENTICATION traffic outside the 24h session
 * window.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('wa_templates', function (Blueprint $t) {
            // ContentSid is a short opaque token from Twilio (`HX` prefix,
            // 34 chars). Nullable because Baileys-only and WABA-only
            // workspaces won't have one. We don't encrypt: ContentSid isn't
            // sensitive (it's a public-facing reference per WA/Twilio docs).
            $t->string('twilio_content_sid', 64)->nullable()->after('meta_template_id');
            $t->index('twilio_content_sid');
        });
    }

    public function down(): void
    {
        Schema::table('wa_templates', function (Blueprint $t) {
            $t->dropIndex(['twilio_content_sid']);
            $t->dropColumn('twilio_content_sid');
        });
    }
};
