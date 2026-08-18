<?php

namespace Database\Seeders;

use App\Models\AdminAiKey;
use Illuminate\Database\Seeder;

/**
 * Pre-populates the admin AI keys table with the 6 providers our
 * AiKeyResolver supports (incl. Deepgram STT for AI voice calls).
 * Admin lands on /admin/api-keys and sees all six immediately — no
 * install step, same UX as /admin/payment-gateways.
 *
 * Default models updated 2026-05 to match the verified flagships
 * in AdminAiKeyController::MODELS. Re-running this seeder against
 * an existing row is safe (firstOrCreate) — it never overwrites
 * an admin-set default.
 */
class AdminAiKeySeeder extends Seeder
{
    public function run(): void
    {
        $providers = [
            [
                'provider'      => 'openai',
                'name'          => 'OpenAI',
                'default_model' => 'gpt-5.4-mini',
                'sort_order'    => 1,
            ],
            [
                'provider'      => 'anthropic',
                'name'          => 'Anthropic Claude',
                'default_model' => 'claude-opus-4-8',
                'sort_order'    => 2,
            ],
            [
                'provider'      => 'gemini',
                'name'          => 'Google Gemini',
                'default_model' => 'gemini-3.5-flash',
                'sort_order'    => 3,
            ],
            [
                'provider'      => 'mistral',
                'name'          => 'Mistral',
                'default_model' => 'mistral-large-latest',
                'sort_order'    => 4,
            ],
            [
                'provider'      => 'elevenlabs',
                'name'          => 'ElevenLabs',
                'default_model' => 'eleven_v3',
                'sort_order'    => 5,
            ],
            [
                'provider'      => 'deepgram',
                'name'          => 'Deepgram',
                'default_model' => 'nova-2',
                'sort_order'    => 6,
            ],
        ];

        foreach ($providers as $p) {
            AdminAiKey::firstOrCreate(
                ['provider' => $p['provider']],
                [
                    'name'          => $p['name'],
                    'default_model' => $p['default_model'],
                    'is_active'     => false,
                    'sort_order'    => $p['sort_order'],
                    'extra_config'  => json_encode([]),
                ]
            );
        }
    }
}
