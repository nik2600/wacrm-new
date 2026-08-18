<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Real-time conversation translation. Adds the per-message translation
 * columns the team inbox + widget + AI render, the per-conversation pinned
 * customer language, and the per-workspace inbox auto-translate toggle.
 *
 *   inbox_messages.detected_language  — ISO code detected on the message
 *   inbox_messages.translated_body    — the AGENT-language view (encrypted)
 *   inbox_messages.is_translated      — UI badge flag
 *   conversations.customer_language   — pinned once from the first inbound
 *   workspaces.inbox_translate        — master on/off (default ON)
 *
 * `body` always stays the canonical WhatsApp text (inbound = what the
 * customer sent; outbound = what was actually delivered). `translated_body`
 * is the convenience translation into the viewing agent's language.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inbox_messages', function (Blueprint $t) {
            if (!Schema::hasColumn('inbox_messages', 'detected_language')) {
                $t->string('detected_language', 12)->nullable()->after('body');
            }
            if (!Schema::hasColumn('inbox_messages', 'translated_body')) {
                $t->text('translated_body')->nullable()->after('detected_language');
            }
            if (!Schema::hasColumn('inbox_messages', 'is_translated')) {
                $t->boolean('is_translated')->default(false)->after('translated_body');
            }
        });

        Schema::table('conversations', function (Blueprint $t) {
            if (!Schema::hasColumn('conversations', 'customer_language')) {
                $t->string('customer_language', 12)->nullable()->after('provider');
            }
        });

        Schema::table('workspaces', function (Blueprint $t) {
            if (!Schema::hasColumn('workspaces', 'inbox_translate')) {
                // ON by default — the plan gate (access_translation) is the
                // real switch; this lets a workspace opt out without losing the plan feature.
                $t->boolean('inbox_translate')->default(true)->after('default_language');
            }
        });
    }

    public function down(): void
    {
        Schema::table('inbox_messages', function (Blueprint $t) {
            $t->dropColumn(['detected_language', 'translated_body', 'is_translated']);
        });
        Schema::table('conversations', function (Blueprint $t) {
            $t->dropColumn('customer_language');
        });
        Schema::table('workspaces', function (Blueprint $t) {
            $t->dropColumn('inbox_translate');
        });
    }
};
