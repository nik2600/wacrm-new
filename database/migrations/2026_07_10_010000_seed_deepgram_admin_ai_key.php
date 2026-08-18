<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Pre-seed an admin_ai_keys row for Deepgram so the Admin → AI Keys page
 * lists it alongside OpenAI / Anthropic / Gemini / Mistral / ElevenLabs.
 * The row starts inactive with a blank api_key — admin fills it in to
 * unlock AI voice-call speech-to-text (STT). Without it AiKeyResolver
 * ('deepgram') returns '' and the WABA call bridge falls back to OpenAI
 * realtime STT (or declines the call). The Node bridge + resolver + docs
 * already referenced Deepgram; only the key-entry row was missing.
 *
 * Idempotent: skips when a row for deepgram already exists.
 */
return new class extends Migration {
    public function up(): void
    {
        $exists = DB::table('admin_ai_keys')->where('provider', 'deepgram')->exists();
        if ($exists) return;

        $maxSort = (int) DB::table('admin_ai_keys')->max('sort_order');
        DB::table('admin_ai_keys')->insert([
            'provider'      => 'deepgram',
            'name'          => 'Deepgram',
            'api_key'       => '', // empty until admin pastes the real key
            'default_model' => 'nova-2',
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
            ->where('provider', 'deepgram')
            ->where('api_key', '')
            ->delete();
    }
};
