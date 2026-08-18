<?php

namespace App\Http\Controllers;

use App\Models\AiCallAssistant;
use App\Models\AiCallAssistantTool;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

/**
 * Voice-call AI assistant — workspace-scoped CRUD + the 5-step wizard.
 *
 * Real-time call handling lives in the Node bridge (Twilio Media
 * Streams → Gemini Live → ElevenLabs); Laravel is just the config
 * surface + logs viewer. See /node/services/voiceCallSession.js for
 * the runtime.
 */
class AiCallAssistantController extends Controller
{
    public function index(): View
    {
        $wsId = (int) (Auth::user()?->current_workspace_id ?? 0);
        $assistants = AiCallAssistant::query()
            ->where('workspace_id', $wsId)
            ->withCount(['tools', 'logs'])
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        $counts = [
            'all'    => AiCallAssistant::where('workspace_id', $wsId)->count(),
            'live'   => AiCallAssistant::where('workspace_id', $wsId)->where('status', 'live')->where('is_active', true)->count(),
            'draft'  => AiCallAssistant::where('workspace_id', $wsId)->where('status', 'draft')->count(),
            'paused' => AiCallAssistant::where('workspace_id', $wsId)->where('status', 'paused')->count(),
        ];

        return view('user.ai-assistants.index', compact('assistants', 'counts'));
    }

    public function create(): View
    {
        $wsId = (int) (Auth::user()?->current_workspace_id ?? 0);
        return view('user.ai-assistants.wizard', [
            'assistant' => null,
            'tools'     => collect(),
            'mode'      => 'create',
            'numbers'   => $this->wabaNumbers($wsId, null),
        ]);
    }

    public function edit(int $id): View
    {
        $wsId = (int) (Auth::user()?->current_workspace_id ?? 0);
        $assistant = AiCallAssistant::where('workspace_id', $wsId)->findOrFail($id);
        return view('user.ai-assistants.wizard', [
            'assistant' => $assistant,
            'tools'     => $assistant->tools()->get(),
            'mode'      => 'edit',
            'numbers'   => $this->wabaNumbers($wsId, $assistant->id),
        ]);
    }

    /**
     * The workspace's WABA calling numbers for the Routing-step picker. Each
     * entry carries `taken_by` = the name of another assistant that already owns
     * that number (so the UI disables it — one number → one assistant).
     */
    private function wabaNumbers(int $wsId, ?int $excludeAssistantId): array
    {
        if ($wsId <= 0) return [];
        $configs = \App\Models\WaProviderConfig::where('workspace_id', $wsId)
            ->where('provider', 'waba')->orderBy('id')->get();

        $ownerOf = [];
        AiCallAssistant::where('workspace_id', $wsId)
            ->when($excludeAssistantId, fn ($q) => $q->where('id', '!=', $excludeAssistantId))
            ->get(['id', 'name', 'device_ids'])
            ->each(function ($o) use (&$ownerOf) {
                foreach ((is_array($o->device_ids) ? $o->device_ids : []) as $cid) {
                    $ownerOf[(int) $cid] = $o->name;
                }
            });

        return $configs->map(function ($c) use ($ownerOf) {
            $creds = $c->creds();
            $num = (string) (((array) ($c->meta_json ?? []))['phone_number'] ?? '')
                ?: (string) ($creds['phone_number_id'] ?? '')
                ?: ('WABA #' . $c->id);
            return [
                'id'       => (int) $c->id,
                'label'    => $num,
                'taken_by' => $ownerOf[(int) $c->id] ?? null,
            ];
        })->values()->all();
    }

    /**
     * POST /ai-assistants/api/save — wizard submit. Handles both new
     * and existing assistants; the wizard always POSTs the FULL state
     * so we don't have to track partial-step deltas server-side.
     */
    public function apiSave(Request $request): JsonResponse
    {
        $user = Auth::user();
        $wsId = (int) ($user?->current_workspace_id ?? 0);
        if (!$wsId) return response()->json(['ok' => false, 'error' => 'no_workspace'], 400);

        $data = $request->validate([
            'id'                   => 'nullable|integer',
            'name'                 => 'required|string|max:120',
            'greeting_text'        => 'nullable|string|max:1000',
            'status'               => 'required|in:live,draft,paused',
            'is_active'            => 'sometimes|boolean',
            // Step 2
            'ai_provider'          => 'required|in:gemini,openai,anthropic',
            'ai_model'             => 'required|string|max:80',
            'ai_api_key'           => 'nullable|string|max:500',  // BYOK override
            'ai_system_prompt'     => 'nullable|string|max:6000',
            'knowledge_base_url'   => 'nullable|url|max:500',
            'natural_conciseness'  => 'sometimes|boolean',
            // Step 4
            'voice_provider'       => 'required|in:elevenlabs,openai,deepgram',
            'voice_api_key'        => 'nullable|string|max:500',  // BYOK override
            'voice_id'             => 'nullable|string|max:80',
            'stt_provider'         => 'required|string|max:32',
            // Step 5
            'record_agent'         => 'sometimes|boolean',
            'record_user'          => 'sometimes|boolean',
            'auto_logging'         => 'sometimes|boolean',
            'exit_keywords'        => 'nullable|array',
            'exit_keywords.*'      => 'string|max:60',
            'last_greeting'        => 'nullable|string|max:500',
            // Per-device routing (Routing step). Numbers this assistant answers;
            // empty = workspace catch-all. Uniqueness enforced below.
            'device_ids'           => 'nullable|array',
            'device_ids.*'         => 'integer',
            // Meta — wizard's "better than competitor" extras stashed as
            // JSON so we don't migrate the table on every UX iteration.
            'meta'                 => 'nullable|array',
            'meta.persona'         => 'nullable|string|max:32',
            'meta.languages'       => 'nullable|array',
            'meta.languages.*'     => 'string|max:8',
            'meta.greeting_variations'   => 'nullable|array|max:5',
            'meta.greeting_variations.*' => 'string|max:500',
            'meta.personality'           => 'nullable|array',
            'meta.noise_suppression'     => 'nullable|boolean',
            'meta.voicemail_behavior'    => 'nullable|string|max:32',
            'meta.human_handoff_team'    => 'nullable|string|max:80',
            // Tools (step 3)
            'tools'                => 'nullable|array|max:25',
            'tools.*.function_name'  => 'required_with:tools|string|max:80',
            'tools.*.trigger_keywords' => 'nullable|array',
            'tools.*.http_method'    => 'required_with:tools|in:GET,POST,PUT,PATCH,DELETE',
            'tools.*.http_url'       => 'required_with:tools|url|max:600',
            'tools.*.headers'        => 'nullable|array',
            'tools.*.parameters'     => 'nullable|array',
        ]);

        // Resolve target row — id present + owned → update; else create.
        $assistant = null;
        if (!empty($data['id'])) {
            $assistant = AiCallAssistant::where('workspace_id', $wsId)->find($data['id']);
        }
        if (!$assistant) {
            $assistant = new AiCallAssistant();
            $assistant->workspace_id = $wsId;
            $assistant->user_id      = $user->id;
        }

        // Slug — derive from name if missing; ensure unique per workspace.
        $slug = $assistant->slug ?: Str::slug($data['name']);
        $base = $slug; $n = 1;
        while (AiCallAssistant::where('workspace_id', $wsId)
            ->where('slug', $slug)
            ->where('id', '!=', $assistant->id ?? 0)
            ->exists()) {
            $slug = $base . '-' . (++$n);
        }

        $assistant->fill([
            'name'                => $data['name'],
            'slug'                => $slug,
            'greeting_text'       => $data['greeting_text'] ?? null,
            'status'              => $data['status'],
            'is_active'           => (bool) ($data['is_active'] ?? true),
            'ai_provider'         => $data['ai_provider'],
            'ai_model'            => $data['ai_model'],
            'ai_system_prompt'    => $data['ai_system_prompt'] ?? null,
            'knowledge_base_url'  => $data['knowledge_base_url'] ?? null,
            'natural_conciseness' => (bool) ($data['natural_conciseness'] ?? true),
            'voice_provider'      => $data['voice_provider'],
            'voice_id'            => $data['voice_id'] ?? null,
            'stt_provider'        => $data['stt_provider'],
            'record_agent'        => (bool) ($data['record_agent'] ?? true),
            'record_user'         => (bool) ($data['record_user'] ?? true),
            'auto_logging'        => (bool) ($data['auto_logging'] ?? true),
            'exit_keywords_json'  => $data['exit_keywords'] ?? [],
            'last_greeting'       => $data['last_greeting'] ?? null,
            'meta_json'           => $data['meta'] ?? [],
        ]);

        // Only overwrite BYOK keys when the operator typed a new one.
        // Empty string in the form means "keep what's already there"
        // so existing keys aren't accidentally wiped on every save.
        if (array_key_exists('ai_api_key', $data) && $data['ai_api_key'] !== null && $data['ai_api_key'] !== '') {
            $assistant->ai_api_key_encrypted = $data['ai_api_key'];
        }
        if (array_key_exists('voice_api_key', $data) && $data['voice_api_key'] !== null && $data['voice_api_key'] !== '') {
            $assistant->voice_api_key_encrypted = $data['voice_api_key'];
        }

        // Per-device routing. Touch ONLY when the wizard actually posted the
        // field, so an older cached client that doesn't send it never wipes an
        // existing binding. Empty array from the UI = catch-all (all numbers).
        if ($request->has('device_ids')) {
            $ids = array_values(array_unique(array_map('intval', (array) ($data['device_ids'] ?? []))));
            // WABA calling numbers = this workspace's connected WABA provider
            // configs (the call carries wa_provider_config_id, so we bind by that
            // id). Keep only the workspace's own — a forged id can't bind another
            // tenant's number.
            $owned = \App\Models\WaProviderConfig::where('workspace_id', $wsId)
                ->where('provider', 'waba')
                ->pluck('id')->map(fn ($i) => (int) $i)->all();
            $ids = array_values(array_intersect($ids, $owned));
            // One number → one assistant. Reject if a selected number is already
            // owned by another assistant, naming it so the operator can unbind.
            if (!empty($ids)) {
                $conflict = AiCallAssistant::where('workspace_id', $wsId)
                    ->where('id', '!=', $assistant->id ?? 0)
                    ->get()
                    ->first(function ($other) use ($ids) {
                        $o = is_array($other->device_ids) ? $other->device_ids : [];
                        return count(array_intersect($ids, array_map('intval', $o))) > 0;
                    });
                if ($conflict) {
                    return response()->json([
                        'ok'      => false,
                        'error'   => 'device_conflict',
                        'message' => "One of the selected numbers is already assigned to \"{$conflict->name}\". Unassign it there first.",
                    ], 422);
                }
            }
            $assistant->device_ids = $ids;
        }

        $assistant->save();

        // Replace tools — simplest correct path. Wizard sends the
        // full tools[] array on every save; we wipe + reinsert so
        // the order matches the UI and stale rows don't survive.
        $assistant->tools()->delete();
        foreach (($data['tools'] ?? []) as $i => $t) {
            AiCallAssistantTool::create([
                'assistant_id'          => $assistant->id,
                'function_name'         => $t['function_name'],
                'trigger_keywords_json' => $t['trigger_keywords'] ?? [],
                'http_method'           => $t['http_method'],
                'http_url'              => $t['http_url'],
                'headers_json'          => $t['headers'] ?? [],
                'parameters_json'       => $t['parameters'] ?? [],
                'sort_order'            => $i,
            ]);
        }

        return response()->json([
            'ok' => true,
            'id' => $assistant->id,
            'slug' => $assistant->slug,
            'redirect_to' => route('user.ai-assistants.edit', $assistant->id),
        ]);
    }

    public function toggle(int $id): RedirectResponse
    {
        $wsId = (int) (Auth::user()?->current_workspace_id ?? 0);
        $a = AiCallAssistant::where('workspace_id', $wsId)->findOrFail($id);
        $a->status = $a->status === 'live' ? 'paused' : 'live';
        $a->save();
        return back()->with('success', 'Status updated to ' . $a->status . '.');
    }

    public function destroy(int $id): RedirectResponse
    {
        $wsId = (int) (Auth::user()?->current_workspace_id ?? 0);
        $a = AiCallAssistant::where('workspace_id', $wsId)->findOrFail($id);
        $a->delete();
        return redirect('/ai-assistants')->with('success', 'Assistant removed.');
    }

    public function duplicate(int $id): RedirectResponse
    {
        $wsId = (int) (Auth::user()?->current_workspace_id ?? 0);
        $src = AiCallAssistant::where('workspace_id', $wsId)->with('tools')->findOrFail($id);
        $copy = $src->replicate(['slug']);
        $copy->name = $src->name . ' (copy)';
        $copy->slug = Str::slug($copy->name . '-' . substr((string) Str::uuid(), 0, 4));
        $copy->status = 'draft';
        $copy->save();
        foreach ($src->tools as $t) {
            $clone = $t->replicate(['assistant_id']);
            $clone->assistant_id = $copy->id;
            $clone->save();
        }
        return redirect()->route('user.ai-assistants.edit', $copy->id)->with('success', 'Assistant duplicated.');
    }
}
