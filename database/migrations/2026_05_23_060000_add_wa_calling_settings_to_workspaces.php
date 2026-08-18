<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-workspace tunables for WhatsApp calling + voice-AI fallback.
 *
 *   auto_pickup_delay_sec    — how long an incoming call rings before
 *                              the AI voicemail fallback fires (default 15s).
 *   voicemail_delay_sec      — when there's also no AI agent assigned,
 *                              this is when we just send a "we missed you"
 *                              chat message instead (default 25s).
 *   default_voice_ai_agent_id — which AiAgent answers when no per-conversation
 *                              agent is pinned. Nullable; resolves to "first
 *                              active voice-enabled agent" when blank.
 *
 * All nullable / defaulted so existing workspaces inherit sensible
 * behaviour without admin intervention.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('workspaces', function (Blueprint $t) {
            $t->integer('auto_pickup_delay_sec')->default(15)->after('timezone');
            $t->integer('voicemail_delay_sec')->default(25)->after('auto_pickup_delay_sec');
            $t->unsignedBigInteger('default_voice_ai_agent_id')->nullable()->after('voicemail_delay_sec');
            $t->index('default_voice_ai_agent_id');
        });
    }

    public function down(): void
    {
        Schema::table('workspaces', function (Blueprint $t) {
            $t->dropIndex(['default_voice_ai_agent_id']);
            $t->dropColumn(['auto_pickup_delay_sec', 'voicemail_delay_sec', 'default_voice_ai_agent_id']);
        });
    }
};
