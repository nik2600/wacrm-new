<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Pre-seed an admin_ai_keys row for ElevenLabs so the Admin → AI Keys
 * page lists it alongside OpenAI / Anthropic / Gemini / Mistral. The
 * row starts inactive with a blank api_key — admin fills it in to
 * unlock ElevenLabs TTS for voice replies workspace-wide.
 *
 * Idempotent: skips when a row for elevenlabs already exists.
 */
return new class extends Migration {
    public function up(): void
    {
        $exists = DB::table('admin_ai_keys')->where('provider', 'elevenlabs')->exists();
        if ($exists) return;

        $maxSort = (int) DB::table('admin_ai_keys')->max('sort_order');
        DB::table('admin_ai_keys')->insert([
            'provider'      => 'elevenlabs',
            'name'          => 'ElevenLabs',
            'api_key'       => '', // empty until admin pastes the real key
            'default_model' => 'eleven_turbo_v2_5',
            'extra_config'  => json_encode([]),
            'is_active'     => false,
            'sort_order'    => $maxSort + 1,
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);
    }

    public function down(): void
    {
        // Only delete if it's still blank — preserve admin-entered key.
        DB::table('admin_ai_keys')
            ->where('provider', 'elevenlabs')
            ->where('api_key', '')
            ->delete();
    }
};
