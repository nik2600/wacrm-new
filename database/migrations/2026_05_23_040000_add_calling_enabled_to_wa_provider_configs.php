<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * WABA calling toggle. One per WhatsApp Business API number — the
 * operator flips it on from Workspace Settings → WhatsApp Calling,
 * which simultaneously POSTs to Meta's
 * `/<phone_id>/settings { calling: { status: ENABLED } }` endpoint
 * and flips this column.
 *
 * Stored on wa_provider_configs (not workspaces) because a workspace
 * can own multiple WABA numbers — each number's calling state is
 * independent. Baileys/Twilio rows ignore the column.
 *
 *   calling_enabled       — boolean, local mirror of Meta's state
 *   calling_enabled_at    — when the toggle was last flipped on, for
 *                           the workspace settings UI to show "since…"
 *   calling_enabled_meta  — opaque JSON Meta returns when calling is
 *                           configured (eligibility, ICE config hints,
 *                           SIP URI if registered). Stored for audit.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('wa_provider_configs', function (Blueprint $t) {
            $t->boolean('calling_enabled')->default(false)->after('status');
            $t->timestamp('calling_enabled_at')->nullable()->after('calling_enabled');
            $t->json('calling_enabled_meta')->nullable()->after('calling_enabled_at');
        });
    }

    public function down(): void
    {
        Schema::table('wa_provider_configs', function (Blueprint $t) {
            $t->dropColumn(['calling_enabled', 'calling_enabled_at', 'calling_enabled_meta']);
        });
    }
};
