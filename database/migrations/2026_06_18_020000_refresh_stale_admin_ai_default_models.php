<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Refresh stale admin_ai_keys.default_model values onto current models.
 *
 * The provider rows are seeded once and never overwritten, so installs created
 * before the model lists were last refreshed keep their original (now old, in
 * some cases RETIRED) default_model — e.g. anthropic on `claude-3-5-sonnet-latest`
 * (retired, 404s) and gemini on `gemini-1.5-pro`. The narrow opus-4-7→4-8 bump
 * didn't catch those.
 *
 * Policy (conservative — never clobber a deliberate, working admin choice):
 *   - A row with NO key in use yet (is_active = 0) is refreshed to the current
 *     default — harmless, and gives admins a current starting point.
 *   - An ACTIVE row is refreshed ONLY if its default_model is a known RETIRED
 *     string (it would 404 regardless). Active rows on any other value are left
 *     untouched.
 * default_model is not a secret (only api_key is encrypted), so a plain update
 * is correct here.
 */
return new class extends Migration {
    public function up(): void
    {
        // Current per-provider defaults (kept in sync with AdminAiKeySeeder).
        $current = [
            'openai'     => 'gpt-5.4-mini',
            'anthropic'  => 'claude-opus-4-8',
            'gemini'     => 'gemini-3.5-flash',
            'mistral'    => 'mistral-large-latest',
            'elevenlabs' => 'eleven_v3',
        ];

        // Known RETIRED / removed model strings per provider — an active row on
        // one of these would error, so it's safe (and necessary) to move it.
        $retired = [
            'openai'     => ['gpt-3.5-turbo', 'gpt-4', 'gpt-4-turbo', 'gpt-4-32k', 'gpt-4-0613'],
            'anthropic'  => [
                'claude-3-5-sonnet-latest', 'claude-3-5-sonnet',
                'claude-3-5-sonnet-20240620', 'claude-3-5-sonnet-20241022',
                'claude-3-5-haiku-20241022', 'claude-3-opus', 'claude-3-opus-20240229',
                'claude-3-sonnet-20240229', 'claude-2.1', 'claude-2.0',
            ],
            'gemini'     => ['gemini-1.5-pro', 'gemini-1.5-flash', 'gemini-1.0-pro', 'gemini-pro', 'gemini-2.0-flash-exp'],
            'mistral'    => [],
            'elevenlabs' => ['eleven_monolingual_v1', 'eleven_multilingual_v1'],
        ];

        foreach ($current as $provider => $default) {
            $row = DB::table('admin_ai_keys')->where('provider', $provider)->first();
            if (!$row) {
                continue;
            }
            $noKeyInUse = empty($row->is_active);
            $isRetired  = in_array($row->default_model, $retired[$provider] ?? [], true);
            if (($noKeyInUse || $isRetired) && $row->default_model !== $default) {
                DB::table('admin_ai_keys')
                    ->where('provider', $provider)
                    ->update(['default_model' => $default]);
            }
        }
    }

    public function down(): void
    {
        // No-op: prior values were stale/retired; there is nothing useful to
        // restore them to.
    }
};
