<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Pre-seed the remaining admin_ai_keys rows (OpenAI, Anthropic, Gemini,
 * Mistral) so Admin → AI Keys lists ALL FIVE providers on a fresh deploy —
 * without having to run AdminAiKeySeeder by hand.
 *
 * ElevenLabs already has its own seed migration (2026_05_23_030000), which is
 * why a `php artisan migrate`-only deploy ends up showing just ElevenLabs.
 * These four previously lived only in database/seeders/AdminAiKeySeeder.php.
 *
 * Idempotent: each provider is inserted only when its row is missing, so
 * re-running migrate (or running after the seeder) never duplicates a row
 * or overwrites an admin-entered key.
 */
return new class extends Migration {
    public function up(): void
    {
        $providers = [
            ['provider' => 'openai',    'name' => 'OpenAI',           'default_model' => 'gpt-5.4-mini',         'sort_order' => 1],
            ['provider' => 'anthropic', 'name' => 'Anthropic Claude', 'default_model' => 'claude-opus-4-8',      'sort_order' => 2],
            ['provider' => 'gemini',    'name' => 'Google Gemini',    'default_model' => 'gemini-3.5-flash',     'sort_order' => 3],
            ['provider' => 'mistral',   'name' => 'Mistral',          'default_model' => 'mistral-large-latest', 'sort_order' => 4],
        ];

        foreach ($providers as $p) {
            // Never touch an existing row — preserve any admin-entered key.
            if (DB::table('admin_ai_keys')->where('provider', $p['provider'])->exists()) {
                continue;
            }
            DB::table('admin_ai_keys')->insert([
                'provider'      => $p['provider'],
                'name'          => $p['name'],
                'api_key'       => '', // blank until admin pastes the real key
                'default_model' => $p['default_model'],
                'extra_config'  => json_encode([]),
                'is_active'     => false,
                'sort_order'    => $p['sort_order'],
                'created_at'    => now(),
                'updated_at'    => now(),
            ]);
        }

        // Push ElevenLabs to the end so the list reads OpenAI → Anthropic →
        // Gemini → Mistral → ElevenLabs (it landed at sort_order=1 when its
        // own migration ran against an empty table). Cosmetic + safe.
        DB::table('admin_ai_keys')->where('provider', 'elevenlabs')->update(['sort_order' => 5]);
    }

    public function down(): void
    {
        // Only remove rows that are still blank — preserve admin-entered keys.
        DB::table('admin_ai_keys')
            ->whereIn('provider', ['openai', 'anthropic', 'gemini', 'mistral'])
            ->where('api_key', '')
            ->delete();
    }
};
