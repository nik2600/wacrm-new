<?php

namespace App\Http\Controllers;

use App\Models\Contact;
use App\Models\Deal;
use App\Models\Pipeline;
use App\Models\PipelineStage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Sales Pipeline — Kanban board of deals (opportunities) a workspace chases.
 * Plan-gated via plan:access_sales_pipeline (route middleware) + the
 * config/plan_gates.php paywall overlay.
 */
class DealsController extends Controller
{
    /** GET /deals — the Kanban board. */
    public function index(Request $request)
    {
        $wsId = (int) ($request->user()->current_workspace_id ?? 0);

        // First visit seeds a default pipeline + the 6-stage ladder.
        $pipelines = Pipeline::forCurrentWorkspace()->orderBy('sort_order')->orderBy('id')->get();
        if ($pipelines->isEmpty()) {
            Pipeline::ensureDefaultForWorkspace($wsId);
            $pipelines = Pipeline::forCurrentWorkspace()->orderBy('sort_order')->orderBy('id')->get();
        }

        // Selected pipeline: ?pipeline= → default → first.
        $pipelineId = (int) $request->query('pipeline', 0);
        $pipeline   = $pipelines->firstWhere('id', $pipelineId)
            ?: $pipelines->firstWhere('is_default', true)
            ?: $pipelines->first();

        // Filters.
        $ownerId = (int) $request->query('owner', 0);
        $source  = (string) $request->query('source', '');
        $search  = trim((string) $request->query('q', ''));

        $stages = $pipeline->stages()
            ->with(['deals' => function ($q) use ($ownerId, $source, $search) {
                $q->with(['contact:id,name,first_name,last_name,country_code,mobile', 'owner:id,name'])
                  ->orderBy('sort_order')
                  ->orderByDesc('id');
                if ($ownerId) $q->where('owner_user_id', $ownerId);
                if ($source !== '') $q->where('source', $source);
                if ($search !== '') $q->where('title', 'like', '%' . str_replace(['%', '_'], ['\%', '\_'], $search) . '%');
            }])
            ->orderBy('sort_order')
            ->get();

        // Display currency follows the WORKSPACE setting (what the user picked),
        // falling back to the platform default — NOT the pipeline's stored code.
        // EVERY money figure on this page (cards, stage totals, KPIs) is converted
        // to it, so the board never mixes symbols (was: $ totals over ₹/SAR cards)
        // and mixed-currency deals sum correctly (not 100 USD + 100 INR = 200).
        $ws              = $request->user()->currentWorkspace;
        $members         = optional($ws)->members()->get(['users.id', 'users.name']) ?? collect();
        $displayCurrency = (string) (optional($ws)->currency
            ?: \App\Models\SystemSetting::get('default_currency', 'USD'));

        // Convert a deal's stored minor amount (in its OWN currency) → the display
        // currency, using the admin exchange rates (Admin → Currencies).
        $conv = function ($minor, $from) use ($displayCurrency): int {
            return (int) round(\App\Support\FormatSettings::convert(((int) $minor) / 100, $from ?: $displayCurrency, $displayCurrency) * 100);
        };

        // Per-stage + board rollups (weighted forecast = value × probability).
        // Each deal is converted first, and we stash the converted amount on the
        // model so the card renders in the SAME display currency as the totals.
        $columns = $stages->map(function (PipelineStage $s) use ($conv) {
            $deals = $s->deals;
            $deals->each(function ($d) use ($conv) {
                $d->display_minor = $conv($d->value_minor, $d->currency);
            });
            $valueMinor = (int) $deals->sum('display_minor');
            $weighted   = (int) round($valueMinor * $s->probability / 100);
            return [
                'stage'          => $s,
                'deals'          => $deals,
                'count'          => $deals->count(),
                'value_minor'    => $valueMinor,
                'weighted_minor' => $weighted,
            ];
        });

        $scope = fn () => Deal::forCurrentWorkspace()->where('pipeline_id', $pipeline->id);

        // KPIs convert each deal to the display currency before summing (can't be
        // done in SQL since each row may be a different currency).
        $openValueMinor = (int) $scope()->open()->get(['value_minor', 'currency'])
            ->sum(fn ($d) => $conv($d->value_minor, $d->currency));
        $forecastMinor  = (int) $columns->sum('weighted_minor');
        $openCount      = (int) $scope()->open()->count();
        $wonThisMonth   = (int) $scope()->where('status', 'won')->where('won_at', '>=', now()->startOfMonth())->count();
        $wonMonthValue  = (int) $scope()->where('status', 'won')->where('won_at', '>=', now()->startOfMonth())
            ->get(['value_minor', 'currency'])->sum(fn ($d) => $conv($d->value_minor, $d->currency));
        $wonAll         = (int) $scope()->where('status', 'won')->count();
        $lostAll        = (int) $scope()->where('status', 'lost')->count();
        $winRate        = ($wonAll + $lostAll) > 0 ? (int) round($wonAll / ($wonAll + $lostAll) * 100) : 0;

        return view('user.deals.index', [
            'pipelines'      => $pipelines,
            'pipeline'       => $pipeline,
            'columns'        => $columns,
            'members'        => $members,
            'sources'        => Deal::SOURCES,
            'currency'       => $displayCurrency,
            'kpis'           => [
                'open_count'     => $openCount,
                'open_value'     => $this->money($openValueMinor, $displayCurrency),
                'forecast'       => $this->money($forecastMinor, $displayCurrency),
                'won_this_month' => $wonThisMonth,
                'won_value'      => $this->money($wonMonthValue, $displayCurrency),
                'win_rate'       => $winRate,
            ],
            'filters'        => ['owner' => $ownerId, 'source' => $source, 'q' => $search],
            'wsSettings'     => [
                'auto' => (bool) optional($ws)->deals_auto_from_orders,
                'min'  => optional($ws)->deals_auto_min_minor !== null ? (int) $ws->deals_auto_min_minor / 100 : null,
            ],
        ]);
    }

    /** POST /deals/settings — save the per-workspace auto-deal-from-orders prefs. */
    public function saveSettings(Request $request): JsonResponse
    {
        $ws = $request->user()->currentWorkspace;
        if (!$ws) {
            return response()->json(['ok' => false, 'message' => 'No active workspace.'], 422);
        }
        $data = $request->validate([
            'auto_from_orders' => 'nullable|boolean',
            'min_value'        => 'nullable|numeric|min:0|max:99999999',
        ]);
        $ws->update([
            'deals_auto_from_orders' => (bool) ($data['auto_from_orders'] ?? false),
            'deals_auto_min_minor'   => (isset($data['min_value']) && $data['min_value'] !== null && $data['min_value'] !== '')
                ? (int) round((float) $data['min_value'] * 100)
                : null,
        ]);
        return response()->json(['ok' => true, 'message' => 'Settings saved.']);
    }

    /** POST /deals — quick-create a deal (lands in the first stage). */
    public function store(Request $request): JsonResponse
    {
        $wsId = (int) ($request->user()->current_workspace_id ?? 0);

        $data = $request->validate([
            'title'         => 'required|string|max:191',
            'pipeline_id'   => 'required|integer',
            'stage_id'      => 'nullable|integer',
            'value'         => 'nullable|numeric|min:0|max:99999999',
            'currency'      => 'nullable|string|max:10',
            'owner_user_id' => 'nullable|integer',
            'contact_id'    => 'nullable|integer',
            'expected_close_date' => 'nullable|date',
        ]);

        $pipeline = Pipeline::forCurrentWorkspace()->find((int) $data['pipeline_id']);
        if (!$pipeline) {
            return response()->json(['ok' => false, 'message' => 'Pipeline not found.'], 404);
        }

        // Target stage: requested (must belong to this pipeline) → first stage.
        $stage = null;
        if (!empty($data['stage_id'])) {
            $stage = $pipeline->stages()->find((int) $data['stage_id']);
        }
        $stage = $stage ?: $pipeline->stages()->orderBy('sort_order')->first();
        if (!$stage) {
            return response()->json(['ok' => false, 'message' => 'This pipeline has no stages.'], 422);
        }

        // Validate the linked contact / owner belong to the workspace.
        $contactId = null;
        if (!empty($data['contact_id'])) {
            $contactId = optional(Contact::forCurrentWorkspace()->find((int) $data['contact_id']))->id;
        }
        $ownerId = (int) ($data['owner_user_id'] ?? 0) ?: (int) $request->user()->id;

        $deal = Deal::create([
            'workspace_id'        => $wsId,
            'pipeline_id'         => $pipeline->id,
            'stage_id'            => $stage->id,
            'contact_id'          => $contactId,
            'title'               => $data['title'],
            'value_minor'         => (int) round((float) ($data['value'] ?? 0) * 100),
            'currency'            => $data['currency'] ?? $pipeline->currency,
            'owner_user_id'       => $ownerId,
            'expected_close_date' => $data['expected_close_date'] ?? null,
            'source'              => 'manual',
            'sort_order'          => 0,
        ]);

        return response()->json([
            'ok'      => true,
            'message' => 'Deal created.',
            'deal_id' => $deal->id,
        ]);
    }

    /** PATCH /deals/{deal}/stage — drag-drop a card to a new column. */
    public function updateStage(Request $request, int $deal): JsonResponse
    {
        $row = Deal::forCurrentWorkspace()->find($deal);
        if (!$row) {
            return response()->json(['ok' => false, 'message' => 'Deal not found.'], 404);
        }

        $data = $request->validate([
            'stage_id'   => 'required|integer',
            'sort_order' => 'nullable|integer',
        ]);

        // The destination stage MUST belong to the deal's pipeline + workspace.
        $stage = PipelineStage::forCurrentWorkspace()
            ->where('pipeline_id', $row->pipeline_id)
            ->find((int) $data['stage_id']);
        if (!$stage) {
            return response()->json(['ok' => false, 'message' => 'Invalid target stage.'], 422);
        }

        // The model's saving() hook syncs status / won_at / lost_at; the
        // updated() hook logs the stage_change activity.
        $row->update([
            'stage_id'   => $stage->id,
            'sort_order' => (int) ($data['sort_order'] ?? 0),
        ]);

        return response()->json([
            'ok'      => true,
            'status'  => $row->status,
            'stage'   => ['id' => $stage->id, 'name' => $stage->name, 'is_won' => $stage->is_won, 'is_lost' => $stage->is_lost],
        ]);
    }

    /** GET /deals/reports — pipeline analytics (value by stage, win-rate, forecast, leaderboard). */
    public function reports(Request $request)
    {
        $wsId = (int) ($request->user()->current_workspace_id ?? 0);
        Pipeline::ensureDefaultForWorkspace($wsId);

        // Pipeline value + weighted forecast by stage (open deals).
        $stages  = PipelineStage::forCurrentWorkspace()->orderBy('sort_order')->get();
        $byStage = $stages->map(function (PipelineStage $s) {
            $minor = (int) Deal::forCurrentWorkspace()->where('stage_id', $s->id)->open()->sum('value_minor');
            $count = (int) Deal::forCurrentWorkspace()->where('stage_id', $s->id)->open()->count();
            return [
                'name'     => $s->name,
                'color'    => $s->color,
                'value'    => $minor / 100,
                'count'    => $count,
                'weighted' => round($minor * $s->probability / 100) / 100,
            ];
        })->values();

        $won  = (int) Deal::forCurrentWorkspace()->where('status', 'won')->count();
        $lost = (int) Deal::forCurrentWorkspace()->where('status', 'lost')->count();

        // Won vs lost over the last 6 months.
        $months = [];
        for ($i = 5; $i >= 0; $i--) {
            $m = now()->startOfMonth()->subMonths($i);
            $months[] = [
                'label' => $m->format('M'),
                'won'   => (int) Deal::forCurrentWorkspace()->where('status', 'won')->whereBetween('won_at', [$m, $m->copy()->endOfMonth()])->count(),
                'lost'  => (int) Deal::forCurrentWorkspace()->where('status', 'lost')->whereBetween('lost_at', [$m, $m->copy()->endOfMonth()])->count(),
            ];
        }

        // Per-agent leaderboard (won deals by value).
        $leaders = Deal::forCurrentWorkspace()
            ->where('status', 'won')->whereNotNull('owner_user_id')
            ->selectRaw('owner_user_id, COUNT(*) as won_count, SUM(value_minor) as won_minor')
            ->groupBy('owner_user_id')->orderByDesc('won_minor')->limit(10)->get()
            ->map(function ($r) {
                $u = \App\Models\User::find($r->owner_user_id);
                return ['name' => $u?->name ?? 'Unknown', 'won' => (int) $r->won_count, 'value' => (int) $r->won_minor / 100];
            })->values();

        // Display currency = workspace setting → platform default (dynamic),
        // never the pipeline's stored code.
        $currency = (string) (optional(request()->user()->currentWorkspace)->currency
            ?: \App\Models\SystemSetting::get('default_currency', 'USD'));

        return view('user.deals.reports', [
            'byStage'  => $byStage,
            'winRate'  => ($won + $lost) > 0 ? (int) round($won / ($won + $lost) * 100) : 0,
            'won'      => $won,
            'lost'     => $lost,
            'openValue'=> $this->money((int) Deal::forCurrentWorkspace()->open()->sum('value_minor'), $currency),
            'forecast' => $this->money((int) round($byStage->sum('weighted') * 100), $currency),
            'wonValue' => $this->money((int) Deal::forCurrentWorkspace()->where('status', 'won')->sum('value_minor'), $currency),
            'months'   => $months,
            'leaders'  => $leaders,
            'currency' => $currency,
            'symbol'   => $symbol = \App\Models\Currency::symbolFor($currency),
            // Pre-encoded blob for the charts JS — passed as one var so the
            // blade can do @json($report) instead of a multi-line array literal
            // (which Blade's @json directive can't parse).
            'report'   => ['byStage' => $byStage, 'months' => $months, 'symbol' => $symbol],
        ]);
    }

    /** GET /deals/{deal} — full detail (JSON) for the slide-over panel. */
    public function show(Request $request, int $deal): JsonResponse
    {
        $row = Deal::forCurrentWorkspace()
            ->with(['contact', 'owner', 'stage', 'pipeline', 'activities.user'])
            ->find($deal);
        if (!$row) {
            return response()->json(['ok' => false, 'message' => 'Deal not found.'], 404);
        }

        $stages  = $row->pipeline ? $row->pipeline->stages()->orderBy('sort_order')->get(['id', 'name', 'is_won', 'is_lost']) : collect();
        $members = optional($request->user()->currentWorkspace)->members()->get(['users.id', 'users.name']) ?? collect();

        return response()->json([
            'ok'         => true,
            'deal'       => $this->serializeDeal($row),
            'stages'     => $stages,
            'members'    => $members,
            'activities' => $row->activities->map(fn ($a) => $this->serializeActivity($a, $stages))->values(),
        ]);
    }

    /** PATCH /deals/{deal} — edit fields (title, value, owner, stage, contact, close date). */
    public function update(Request $request, int $deal): JsonResponse
    {
        $row = Deal::forCurrentWorkspace()->find($deal);
        if (!$row) {
            return response()->json(['ok' => false, 'message' => 'Deal not found.'], 404);
        }

        $data = $request->validate([
            'title'               => 'sometimes|required|string|max:191',
            'value'               => 'sometimes|nullable|numeric|min:0|max:99999999',
            'currency'            => 'sometimes|nullable|string|max:10',
            'owner_user_id'       => 'sometimes|nullable|integer',
            'stage_id'            => 'sometimes|nullable|integer',
            'contact_id'          => 'sometimes|nullable|integer',
            'expected_close_date' => 'sometimes|nullable|date',
        ]);

        $patch = [];
        if (array_key_exists('title', $data))    $patch['title'] = $data['title'];
        if (array_key_exists('value', $data))    $patch['value_minor'] = (int) round((float) ($data['value'] ?? 0) * 100);
        if (array_key_exists('currency', $data) && $data['currency']) $patch['currency'] = $data['currency'];
        if (array_key_exists('expected_close_date', $data)) $patch['expected_close_date'] = $data['expected_close_date'];

        if (array_key_exists('owner_user_id', $data)) {
            $patch['owner_user_id'] = $data['owner_user_id'] ? (int) $data['owner_user_id'] : null;
        }
        if (array_key_exists('contact_id', $data)) {
            $patch['contact_id'] = $data['contact_id']
                ? optional(Contact::forCurrentWorkspace()->find((int) $data['contact_id']))->id
                : null;
        }
        // Stage change validated against the deal's own pipeline.
        if (!empty($data['stage_id'])) {
            $stage = PipelineStage::forCurrentWorkspace()->where('pipeline_id', $row->pipeline_id)->find((int) $data['stage_id']);
            if ($stage) $patch['stage_id'] = $stage->id;
        }

        $row->update($patch);

        return response()->json(['ok' => true, 'deal' => $this->serializeDeal($row->fresh(['contact', 'owner', 'stage']))]);
    }

    /** DELETE /deals/{deal}. */
    public function destroy(Request $request, int $deal): JsonResponse
    {
        $row = Deal::forCurrentWorkspace()->find($deal);
        if (!$row) {
            return response()->json(['ok' => false, 'message' => 'Deal not found.'], 404);
        }
        $row->activities()->delete();
        $row->delete();
        return response()->json(['ok' => true]);
    }

    /** POST /deals/{deal}/activity — add a note / task / logged call or message. */
    public function addActivity(Request $request, int $deal): JsonResponse
    {
        $row = Deal::forCurrentWorkspace()->find($deal);
        if (!$row) {
            return response()->json(['ok' => false, 'message' => 'Deal not found.'], 404);
        }

        $data = $request->validate([
            'type'   => 'required|in:note,call,message,task',
            'body'   => 'required|string|max:5000',
            'due_at' => 'nullable|date',
        ]);

        $activity = $row->activities()->create([
            'workspace_id' => $row->workspace_id,
            'user_id'      => $request->user()->id,
            'type'         => $data['type'],
            'body'         => $data['body'],
            'due_at'       => $data['type'] === 'task' ? ($data['due_at'] ?? null) : null,
        ]);

        $stages = $row->pipeline ? $row->pipeline->stages()->get(['id', 'name', 'is_won', 'is_lost']) : collect();
        return response()->json(['ok' => true, 'activity' => $this->serializeActivity($activity->load('user'), $stages)]);
    }

    /** POST /deals/{deal}/task/{activity}/done — tick a task complete (or re-open). */
    public function completeTask(Request $request, int $deal, int $activity): JsonResponse
    {
        $row = Deal::forCurrentWorkspace()->find($deal);
        if (!$row) {
            return response()->json(['ok' => false, 'message' => 'Deal not found.'], 404);
        }
        $task = $row->activities()->where('type', 'task')->find($activity);
        if (!$task) {
            return response()->json(['ok' => false, 'message' => 'Task not found.'], 404);
        }
        $done = !$task->done_at;
        $task->update(['done_at' => $done ? now() : null]);
        return response()->json(['ok' => true, 'done' => $done]);
    }

    /** POST /deals/{deal}/won — move to the pipeline's Won stage. */
    public function markWon(Request $request, int $deal): JsonResponse
    {
        return $this->markOutcome($request, $deal, 'is_won');
    }

    /** POST /deals/{deal}/lost — move to the Lost stage (+ optional reason). */
    public function markLost(Request $request, int $deal): JsonResponse
    {
        return $this->markOutcome($request, $deal, 'is_lost');
    }

    private function markOutcome(Request $request, int $deal, string $flag): JsonResponse
    {
        $row = Deal::forCurrentWorkspace()->find($deal);
        if (!$row) {
            return response()->json(['ok' => false, 'message' => 'Deal not found.'], 404);
        }
        $stage = $row->pipeline?->stages()->where($flag, true)->orderBy('sort_order')->first();
        if (!$stage) {
            return response()->json(['ok' => false, 'message' => 'This pipeline has no ' . ($flag === 'is_won' ? 'Won' : 'Lost') . ' stage.'], 422);
        }
        if ($flag === 'is_lost') {
            $reason = trim((string) $request->input('reason', ''));
            if ($reason !== '') $row->lost_reason = mb_substr($reason, 0, 191);
        }
        $row->stage_id = $stage->id; // observer flips status + stamps timestamp
        $row->save();

        return response()->json(['ok' => true, 'status' => $row->status, 'stage' => ['id' => $stage->id, 'name' => $stage->name]]);
    }

    /** GET /deals/contacts/search?q= — link-a-contact picker. */
    public function contactsSearch(Request $request): JsonResponse
    {
        $q = trim((string) $request->query('q', ''));
        $rows = Contact::forCurrentWorkspace()
            ->when($q !== '', function ($w) use ($q) {
                $esc = str_replace(['%', '_'], ['\%', '\_'], $q);
                $w->where(function ($x) use ($esc) {
                    $x->where('name', 'like', "%{$esc}%")
                      ->orWhere('mobile', 'like', "%{$esc}%");
                });
            })
            ->orderByDesc('id')
            ->limit(15)
            ->get(['id', 'name', 'first_name', 'last_name', 'country_code', 'mobile']);

        return response()->json([
            'ok'   => true,
            'data' => $rows->map(fn ($c) => [
                'id'    => $c->id,
                'name'  => $c->name ?: trim(($c->first_name ?? '') . ' ' . ($c->last_name ?? '')) ?: mask_phone((string) ($c->country_code . $c->mobile)),
                'phone' => mask_phone((string) ($c->country_code . $c->mobile)),
            ])->values(),
        ]);
    }

    /** GET /deals/stages — default pipeline's stages (for the flow-builder deal_stage_changed picker). */
    public function stagesJson(Request $request): JsonResponse
    {
        $wsId = (int) ($request->user()->current_workspace_id ?? 0);
        $pipeline = Pipeline::ensureDefaultForWorkspace($wsId);
        return response()->json([
            'ok'   => true,
            'data' => $pipeline->stages()->orderBy('sort_order')->get(['id', 'name'])->all(),
        ]);
    }

    /* -------------------- serializers -------------------- */

    private function serializeDeal(Deal $d): array
    {
        $c = $d->contact;
        $hasContact = $c && $c->id;
        $rawPhone   = $hasContact ? preg_replace('/\D+/', '', (string) ($c->country_code . $c->mobile)) : null;

        return [
            'id'            => $d->id,
            'title'         => $d->title,
            'value'         => $d->value_minor / 100,
            'value_display' => $d->value_display,
            'currency'      => $d->currency,
            'status'        => $d->status,
            'conversation_id' => $d->conversation_id, // deep-link "Open in inbox"
            'stage_id'      => $d->stage_id,
            'stage_name'    => optional($d->stage)->name,
            'owner_user_id' => $d->owner_user_id,
            'owner_name'    => $d->owner && $d->owner->id ? $d->owner->name : null,
            'source'        => $d->source,
            'lost_reason'   => $d->lost_reason,
            'expected_close_date' => optional($d->expected_close_date)->toDateString(),
            'created_at'    => optional($d->created_at)->toDayDateTimeString(),
            'contact'       => $hasContact ? [
                'id'        => $c->id,
                'name'      => $c->name ?: trim(($c->first_name ?? '') . ' ' . ($c->last_name ?? '')) ?: mask_phone((string) ($c->country_code . $c->mobile)),
                'phone'     => mask_phone((string) ($c->country_code . $c->mobile)),
                'wa_phone'  => $rawPhone, // for the wa.me deep link only
            ] : null,
        ];
    }

    private function serializeActivity($a, $stages): array
    {
        $label = null;
        if ($a->type === 'stage_change' && is_array($a->meta)) {
            $from = $stages->firstWhere('id', (int) ($a->meta['from_stage_id'] ?? 0));
            $to   = $stages->firstWhere('id', (int) ($a->meta['to_stage_id'] ?? 0));
            $label = trim((optional($from)->name ?? '?') . ' → ' . (optional($to)->name ?? '?'));
        }

        return [
            'id'         => $a->id,
            'type'       => $a->type,
            'body'       => $a->body,
            'label'      => $label,
            'user_name'  => $a->user && $a->user->id ? $a->user->name : 'System',
            'due_at'     => optional($a->due_at)->toDayDateTimeString(),
            'done'       => (bool) $a->done_at,
            'created_at' => optional($a->created_at)->diffForHumans(),
        ];
    }

    /** Minor-units → display string (mirrors Deal::valueDisplay). */
    private function money(int $minor, string $currency): string
    {
        // Symbol resolves dynamically from the admin currencies table — one
        // source of truth, no hardcoded per-currency map.
        return \App\Models\Currency::symbolFor($currency) . number_format($minor / 100, 2);
    }
}
