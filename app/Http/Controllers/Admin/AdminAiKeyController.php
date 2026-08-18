<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminAiKey;
use Illuminate\Http\Request;

/**
 * Admin /api-keys — global AI provider keys. SnapNest-style UX:
 * every provider pre-seeded, admin types key + saves + toggles
 * Active. No install / destroy step.
 *
 *   GET   /admin/api-keys              → list providers
 *   PATCH /admin/api-keys/{id}         → save key + default_model + extra_config
 *   POST  /admin/api-keys/{id}/toggle  → activate / deactivate
 */
class AdminAiKeyController extends Controller
{
    /**
     * Default model dropdowns per provider.
     *
     * VERIFIED 2026-05-23 against each provider's public model catalog:
     *   OpenAI:    developers.openai.com/api/docs/models
     *   Anthropic: docs.anthropic.com/en/docs/about-claude/models
     *   Gemini:    ai.google.dev/gemini-api/docs/models
     *   Mistral:   docs.mistral.ai/models/overview
     *   ElevenLabs: elevenlabs.io/docs/overview/models
     *
     * Stale entries (deprecated / retired) removed. Latest flagship +
     * cost-tier variants kept. A small number of legacy aliases retained
     * where the provider still accepts them — they're below the dividing
     * "// legacy" comment so admin can spot which to migrate off.
     */
    public const MODELS = [
        'openai' => [
            // GPT-5.x line — current flagships
            'gpt-5.5', 'gpt-5.5-pro', 'gpt-5.4', 'gpt-5.4-pro',
            'gpt-5.4-mini', 'gpt-5.4-nano', 'gpt-5-mini', 'gpt-5-nano',
            // GPT-4.1 — still recommended for tooling / function-calling
            'gpt-4.1', 'gpt-4.1-mini',
            // legacy (still callable but receiving fewer updates)
            'gpt-4o', 'gpt-4o-mini',
        ],
        'anthropic' => [
            // Current — Opus 4.8 is the latest Opus-tier flagship; Fable 5 is
            // Anthropic's most capable model overall (premium pricing).
            'claude-opus-4-8', 'claude-fable-5', 'claude-sonnet-4-6', 'claude-haiku-4-5',
            // Previous-gen, still fully supported
            'claude-opus-4-7', 'claude-opus-4-6',
        ],
        'gemini' => [
            // Gemini 3.x — current
            'gemini-3.5-flash', 'gemini-3.1-pro-preview', 'gemini-3.1-flash-lite',
            // Gemini 2.5 — still maintained
            'gemini-2.5-pro', 'gemini-2.5-flash', 'gemini-2.5-flash-lite',
        ],
        'mistral' => [
            // Aliases — resolve to current generation automatically
            'mistral-large-latest', 'mistral-medium-latest', 'mistral-small-latest',
            'codestral-latest',
            // Specific dated builds
            'ministral-3-14b-25-12', 'ministral-3-8b-25-12',
            'devstral-2-25-12', 'magistral-medium-1-2-25-09',
        ],
        'elevenlabs' => [
            // Eleven v3 = current flagship (70+ languages)
            'eleven_v3',
            // Still active
            'eleven_multilingual_v2', 'eleven_turbo_v2_5', 'eleven_flash_v2_5',
        ],
        'deepgram' => [
            // Nova = Deepgram's realtime STT family; the AI voice-call bridge
            // streams to nova-2. (Model is informational here — the call bridge
            // reads only the key; it pins nova-2 in its own request.)
            'nova-3', 'nova-2',
        ],
    ];

    /**
     * The credential field schema each provider's edit form renders.
     * Mirrors the PaymentGateway driver pattern so the UX is uniform.
     */
    private const FIELD_SCHEMA = [
        'openai' => [
            'api_key' => [
                'label' => 'API key',
                'type' => 'password',
                'hint' => 'Find at platform.openai.com/api-keys',
                'required' => true,
            ],
            'organization' => [
                'label' => 'Organization ID',
                'type' => 'text',
                'hint' => 'Optional. e.g. org-xxxxxxxxxxxx',
            ],
            'max_tokens' => [
                'label' => 'Max tokens per request',
                'type' => 'text',
                'placeholder' => 'e.g. 4096',
                'hint' => 'Optional ceiling — a single AI request can never burn more than this many output tokens. Leave blank for the model default.',
            ],
        ],
        'anthropic' => [
            'api_key' => [
                'label' => 'API key',
                'type' => 'password',
                'hint' => 'Find at console.anthropic.com/settings/keys',
                'required' => true,
            ],
            'max_tokens' => [
                'label' => 'Max tokens per request',
                'type' => 'text',
                'placeholder' => 'e.g. 4096',
                'hint' => 'Optional ceiling — a single AI request can never burn more than this many output tokens. Leave blank for the model default.',
            ],
        ],
        'gemini' => [
            'api_key' => [
                'label' => 'API key',
                'type' => 'password',
                'hint' => 'Generate at aistudio.google.com/app/apikey',
                'required' => true,
            ],
            'project_id' => [
                'label' => 'Project ID',
                'type' => 'text',
                'hint' => 'Optional. For Vertex AI billing.',
            ],
            'max_tokens' => [
                'label' => 'Max tokens per request',
                'type' => 'text',
                'placeholder' => 'e.g. 4096',
                'hint' => 'Optional ceiling — a single AI request can never burn more than this many output tokens. Leave blank for the model default.',
            ],
        ],
        'mistral' => [
            'api_key' => [
                'label' => 'API key',
                'type' => 'password',
                'hint' => 'Generate at console.mistral.ai/api-keys',
                'required' => true,
            ],
            'max_tokens' => [
                'label' => 'Max tokens per request',
                'type' => 'text',
                'placeholder' => 'e.g. 4096',
                'hint' => 'Optional ceiling — a single AI request can never burn more than this many output tokens. Leave blank for the model default.',
            ],
        ],
        'elevenlabs' => [
            'api_key' => [
                'label' => 'API key',
                'type' => 'password',
                'hint' => 'Find at elevenlabs.io/app/settings/api-keys',
                'required' => true,
            ],
            'voice_id' => [
                'label' => 'Default voice ID',
                'type' => 'text',
                'hint' => 'Optional. Voice used when caller hasn\'t picked one.',
            ],
        ],
        'deepgram' => [
            'api_key' => [
                'label' => 'API key',
                'type' => 'password',
                'hint' => 'Create at console.deepgram.com → API Keys. Powers AI voice-call speech-to-text (so the assistant can hear the caller).',
                'required' => true,
            ],
        ],
    ];

    public function index()
    {
        $providers = AdminAiKey::orderBy('sort_order')->get()->map(function (AdminAiKey $k) {
            $k->fields_schema        = self::FIELD_SCHEMA[$k->provider] ?? [];
            $k->extra_config_decoded = $k->extra_config_array;
            $k->model_choices        = self::MODELS[$k->provider] ?? [];
            return $k;
        });

        $stats = [
            'total'    => $providers->count(),
            'active'   => $providers->where('is_active', true)->count(),
            'ready'    => $providers->filter(fn ($p) => $p->is_active && !empty($p->api_key))->count(),
            'no_key'   => $providers->filter(fn ($p) => empty($p->api_key))->count(),
        ];

        return view('admin.api-keys.index', compact('providers', 'stats'));
    }

    public function update(Request $request, int $id)
    {
        $row    = AdminAiKey::findOrFail($id);
        $fields = self::FIELD_SCHEMA[$row->provider] ?? [];

        $data = $request->validate([
            'default_model' => ['nullable', 'string', 'max:80'],
            'api_key'       => ['nullable', 'string', 'max:1024'],
            'sort_order'    => ['nullable', 'integer', 'min:0'],
            'extra'         => ['nullable', 'array'],
            'extra.*'       => ['nullable', 'string', 'max:500'],
        ]);

        // API key — only overwrite if a non-empty value was submitted.
        // Lets the password placeholder ("leave blank to keep") work.
        if (!empty($data['api_key'])) {
            $row->api_key = $data['api_key'];
        }

        $row->default_model = $data['default_model'] ?? $row->default_model;
        $row->sort_order    = $data['sort_order'] ?? $row->sort_order;

        // Merge extra_config — only overwrite a key when the form
        // submitted a non-empty value (so blank fields don't wipe).
        $existing = $row->extra_config_array;
        $incoming = $data['extra'] ?? [];
        foreach ($fields as $key => $spec) {
            if ($key === 'api_key') continue;
            if (array_key_exists($key, $incoming) && $incoming[$key] !== '') {
                $existing[$key] = $incoming[$key];
            }
        }
        $row->extra_config = json_encode($existing, JSON_UNESCAPED_UNICODE);

        // Auto-activate the moment a usable key is on the row. Admins kept
        // hitting Save expecting the provider to turn ON, but it stayed DISABLED
        // (Activate was a separate click), so every AI picker showed "no
        // providers enabled". Saving with a key now enables it. (Toggle still
        // lets them deactivate.)
        if (!empty($row->api_key)) {
            $row->is_active = true;
        }
        $row->save();

        return back()->with('success', $row->name . ($row->is_active ? ' saved + activated.' : ' settings saved.'));
    }

    public function toggle(int $id)
    {
        $row = AdminAiKey::findOrFail($id);

        if (!$row->is_active && empty($row->api_key)) {
            return back()->with('error', 'Add an API key before activating.');
        }
        $row->update(['is_active' => !$row->is_active]);

        return back()->with('success', $row->is_active ? 'Activated.' : 'Deactivated.');
    }

    public static function fieldSchemaFor(string $provider): array
    {
        return self::FIELD_SCHEMA[$provider] ?? [];
    }
}
