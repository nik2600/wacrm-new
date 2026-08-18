<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * Cross-page AI helpers wired into the global <x-compose-textarea>
 * component. Provides a "review this message" endpoint that:
 *   1. classifies portions of the operator's draft as good vs bad
 *      (clarity, tone, hooks, CTA strength, length)
 *   2. returns a rewritten "best version" the operator can paste back
 *
 * Auth-only (no workspace.role gate) — every operator can use it.
 * Provider keys come from /admin/api-keys via AiKeyResolver so a user
 * can never call a model the server isn't configured for.
 */
class AiHelpersController extends Controller
{
    /**
     * GET /ai/models — same model list as templates/flows/meta-ads use.
     * Surfacing it here lets the compose-textarea pick a default model
     * without depending on which feature the textarea is rendered in.
     */
    public function models(): JsonResponse
    {
        $rows = \DB::table('admin_ai_keys')
            ->where('is_active', true)
            ->whereNotIn('provider', ['elevenlabs'])
            ->orderBy('sort_order')
            ->get(['provider', 'name', 'default_model', 'extra_config']);

        $providerLabel = [
            'openai'    => 'OpenAI',
            'anthropic' => 'Anthropic',
            'gemini'    => 'Google',
            'mistral'   => 'Mistral',
        ];

        $models = [];
        foreach ($rows as $r) {
            $label = $providerLabel[$r->provider] ?? ucfirst($r->provider);
            $default = (string) ($r->default_model ?? '');
            if ($default === '') continue;
            $extra = json_decode((string) ($r->extra_config ?? '[]'), true) ?: [];
            $extraModels = is_array($extra['models'] ?? null) ? $extra['models'] : [];
            $list = array_values(array_unique(array_merge([$default], $extraModels)));
            foreach ($list as $m) {
                $models[] = [
                    'value'    => $m,
                    'label'    => $label . ' · ' . $m,
                    'provider' => $r->provider,
                ];
            }
        }
        return response()->json(['ok' => true, 'models' => $models]);
    }

    /**
     * POST /ai/review-text — analyse a draft message and return a
     * structured review the front-end paints under the textarea.
     *
     * Response shape:
     *   {
     *     ok: true,
     *     analysis: {
     *       good:  ["clear opening", "single CTA"],
     *       bad:   ["too long", "vague benefit"],
     *       score: 0-100
     *     },
     *     best_version: "<rewritten message>"
     *   }
     *
     * The model picker honours the first active admin provider when
     * the caller doesn't supply provider/model — keeps the global
     * textarea simple (no model dropdown).
     */
    public function reviewText(Request $request): JsonResponse
    {
        $data = $request->validate([
            'text'     => 'required|string|max:4096',
            'context'  => 'nullable|string|max:120',
            'provider' => 'nullable|string|in:openai,anthropic,gemini',
            'model'    => 'nullable|string|max:120',
        ]);

        // Resolve provider/model. If the caller didn't pick one, pull
        // the first active admin provider so the global textarea
        // doesn't need a model picker.
        $provider = $data['provider'] ?? null;
        $model    = $data['model'] ?? null;
        if (!$provider || !$model) {
            $row = \DB::table('admin_ai_keys')
                ->where('is_active', true)
                ->whereNotIn('provider', ['elevenlabs'])
                ->orderBy('sort_order')
                ->first(['provider', 'default_model']);
            if ($row) {
                $provider = $provider ?: $row->provider;
                $model    = $model    ?: $row->default_model;
            }
        }
        if (!$provider || !$model) {
            return response()->json([
                'ok'      => false,
                'error'   => 'no_provider',
                'message' => 'Admin has not enabled any AI provider yet.',
            ], 422);
        }
        if (!in_array($provider, ['openai', 'anthropic', 'gemini'], true)) {
            return response()->json([
                'ok'      => false,
                'error'   => 'unsupported_provider',
                'message' => "Provider {$provider} is not supported for text review.",
            ], 422);
        }

        $user = Auth::user();
        $workspace = $user?->current_workspace_id
            ? \App\Models\Workspace::find($user->current_workspace_id)
            : null;

        $resolved = \App\Services\AiKeyResolver::resolve($workspace, $provider);
        if (!$resolved['key']) {
            return response()->json([
                'ok'      => false,
                'error'   => 'no_key',
                'message' => 'Admin has not enabled this provider in /admin/api-keys.',
            ], 422);
        }

        $systemPrompt = <<<'SYS'
You review WhatsApp marketing-message drafts and return structured
feedback. Output STRICT JSON only — no prose, no markdown, no code
fences. Schema:

{
  "analysis": {
    "good":  ["<short bullet about something the draft does well>", ...],
    "bad":   ["<short bullet about a weakness or risk>", ...],
    "score": <integer 0-100, your overall quality estimate>
  },
  "best_version": "<a rewritten version of the same message, <= 1024 chars, plain text, optional *bold* _italic_, keep {{var}} placeholders intact>"
}

Rules:
1. Keep each bullet under 80 characters.
2. Surface at most 4 good points and 4 bad points.
3. The rewritten "best_version" should fix the bad points while
   keeping the message's intent.
4. No emojis. No clickbait. Respect WhatsApp Business policy.
5. Score 0-100 reflects clarity + hook + CTA strength + length.
6. Output ONLY the JSON object. No explanation. No code fences.
SYS;

        $userPrompt = "Context: " . ($data['context'] ?? 'WhatsApp campaign / template draft') . "\n\nDraft message:\n" . $data['text'];

        $ai = app(\App\Services\AiAgentService::class);
        $raw = $ai->callProvider(
            provider:     $provider,
            model:        $model,
            workspaceId:  (int) ($workspace?->id ?? 0),
            systemPrompt: $systemPrompt,
            userPrompt:   $userPrompt,
            maxTokens:    900,
            temperature:  0.4,
        );

        if (!$raw) {
            return response()->json([
                'ok'      => false,
                'error'   => 'provider_failed',
                'message' => 'AI provider returned no content.',
            ], 502);
        }

        $clean = trim($raw);
        if (preg_match('/^```(?:json)?\s*(.*?)\s*```$/s', $clean, $m)) {
            $clean = trim($m[1]);
        }
        $out = json_decode($clean, true);
        if (!is_array($out)) {
            Log::warning('[AI-Review] bad JSON from model: ' . substr($raw, 0, 400));
            return response()->json([
                'ok'      => false,
                'error'   => 'bad_json',
                'message' => 'Model output was not valid JSON.',
            ], 422);
        }

        $analysis = is_array($out['analysis'] ?? null) ? $out['analysis'] : [];
        $good = array_values(array_filter(array_map(
            fn ($s) => mb_substr((string) $s, 0, 120),
            is_array($analysis['good'] ?? null) ? $analysis['good'] : []
        )));
        $bad  = array_values(array_filter(array_map(
            fn ($s) => mb_substr((string) $s, 0, 120),
            is_array($analysis['bad'] ?? null) ? $analysis['bad'] : []
        )));
        $score = is_numeric($analysis['score'] ?? null)
            ? max(0, min(100, (int) $analysis['score']))
            : null;

        return response()->json([
            'ok'           => true,
            'analysis'     => [
                'good'  => array_slice($good, 0, 4),
                'bad'   => array_slice($bad,  0, 4),
                'score' => $score,
            ],
            'best_version' => mb_substr((string) ($out['best_version'] ?? ''), 0, 4096),
            'model'        => $model,
        ]);
    }
}
