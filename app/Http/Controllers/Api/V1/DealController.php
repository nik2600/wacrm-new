<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Contact;
use App\Models\Deal;
use App\Models\Pipeline;
use App\Models\PipelineStage;
use App\Models\Workspace;
use App\Services\PlanLimitGuard;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Deals — the Sales Pipeline over the public REST API. Lets a CRM / n8n /
 * Zapier create + advance opportunities programmatically. Plan-gated by
 * access_sales_pipeline. Rows are workspace-scoped; the { data, meta }
 * envelope matches the rest of /api/v1.
 */
class DealController extends V1Controller
{
    /** GET /api/v1/deals — paginated, optional pipeline_id / stage_id / status. */
    public function index(Request $request): JsonResponse
    {
        if ($r = $this->ensurePlan()) return $r;

        $perPage = min(max((int) $request->input('per_page', 25), 1), 100);

        $q = Deal::query()->forCurrentWorkspace()->with('stage')->orderByDesc('id');
        if ($request->filled('pipeline_id')) $q->where('pipeline_id', (int) $request->input('pipeline_id'));
        if ($request->filled('stage_id'))    $q->where('stage_id', (int) $request->input('stage_id'));
        if ($request->filled('status'))      $q->where('status', (string) $request->input('status'));

        $page = $q->paginate($perPage);

        return $this->ok(
            collect($page->items())->map(fn (Deal $d) => $this->present($d))->all(),
            [
                'page'      => $page->currentPage(),
                'per_page'  => $page->perPage(),
                'total'     => $page->total(),
                'last_page' => $page->lastPage(),
            ],
        );
    }

    /** GET /api/v1/deals/{id}. */
    public function show(int $id): JsonResponse
    {
        if ($r = $this->ensurePlan()) return $r;
        $deal = Deal::query()->forCurrentWorkspace()->with('stage')->whereKey($id)->first();
        if (!$deal) return $this->fail('not_found', 'Deal not found.', 404);
        return $this->ok($this->present($deal));
    }

    /** POST /api/v1/deals — create an opportunity. */
    public function store(Request $request): JsonResponse
    {
        if ($r = $this->ensurePlan()) return $r;

        $data = $request->validate([
            'title'         => 'required|string|max:191',
            'pipeline_id'   => 'nullable|integer',
            'stage_id'      => 'nullable|integer',
            'value'         => 'nullable|numeric|min:0|max:99999999',
            'currency'      => 'nullable|string|max:10',
            'contact_id'    => 'nullable|integer',
            'owner_user_id' => 'nullable|integer',
            'expected_close_date' => 'nullable|date',
        ]);

        $wsId = $this->workspaceId();

        // Pipeline: given (must be ours) → default (seeded on demand).
        $pipeline = !empty($data['pipeline_id'])
            ? Pipeline::forCurrentWorkspace()->find((int) $data['pipeline_id'])
            : null;
        $pipeline = $pipeline ?: Pipeline::ensureDefaultForWorkspace($wsId);

        $stage = !empty($data['stage_id'])
            ? $pipeline->stages()->find((int) $data['stage_id'])
            : null;
        $stage = $stage ?: $pipeline->stages()->orderBy('sort_order')->first();
        if (!$stage) return $this->fail('unprocessable', 'Pipeline has no stages.', 422);

        $contactId = !empty($data['contact_id'])
            ? optional(Contact::forCurrentWorkspace()->find((int) $data['contact_id']))->id
            : null;

        $deal = Deal::create([
            'workspace_id'        => $wsId,
            'pipeline_id'         => $pipeline->id,
            'stage_id'            => $stage->id,
            'contact_id'          => $contactId,
            'title'               => $data['title'],
            'value_minor'         => (int) round((float) ($data['value'] ?? 0) * 100),
            'currency'            => $data['currency'] ?? $pipeline->currency,
            'owner_user_id'       => $data['owner_user_id'] ?? null,
            'expected_close_date' => $data['expected_close_date'] ?? null,
            'source'              => 'api',
        ]);

        return $this->ok($this->present($deal->load('stage')), [], 201);
    }

    /** PUT /api/v1/deals/{id} — update fields (incl. stage to advance it). */
    public function update(Request $request, int $id): JsonResponse
    {
        if ($r = $this->ensurePlan()) return $r;
        $deal = Deal::query()->forCurrentWorkspace()->whereKey($id)->first();
        if (!$deal) return $this->fail('not_found', 'Deal not found.', 404);

        $data = $request->validate([
            'title'         => 'sometimes|required|string|max:191',
            'value'         => 'sometimes|nullable|numeric|min:0|max:99999999',
            'currency'      => 'sometimes|nullable|string|max:10',
            'stage_id'      => 'sometimes|nullable|integer',
            'contact_id'    => 'sometimes|nullable|integer',
            'owner_user_id' => 'sometimes|nullable|integer',
            'expected_close_date' => 'sometimes|nullable|date',
            'lost_reason'   => 'sometimes|nullable|string|max:191',
        ]);

        $patch = [];
        if (array_key_exists('title', $data))       $patch['title'] = $data['title'];
        if (array_key_exists('value', $data))       $patch['value_minor'] = (int) round((float) ($data['value'] ?? 0) * 100);
        if (array_key_exists('currency', $data) && $data['currency']) $patch['currency'] = $data['currency'];
        if (array_key_exists('owner_user_id', $data)) $patch['owner_user_id'] = $data['owner_user_id'] ?: null;
        if (array_key_exists('expected_close_date', $data)) $patch['expected_close_date'] = $data['expected_close_date'];
        if (array_key_exists('lost_reason', $data))  $patch['lost_reason'] = $data['lost_reason'];
        if (array_key_exists('contact_id', $data)) {
            $patch['contact_id'] = $data['contact_id'] ? optional(Contact::forCurrentWorkspace()->find((int) $data['contact_id']))->id : null;
        }
        if (!empty($data['stage_id'])) {
            $stage = PipelineStage::forCurrentWorkspace()->where('pipeline_id', $deal->pipeline_id)->find((int) $data['stage_id']);
            if (!$stage) return $this->fail('unprocessable', 'Stage not in this deal\'s pipeline.', 422);
            $patch['stage_id'] = $stage->id; // observer syncs status + fires deal_stage_changed flows
        }

        $deal->update($patch);
        return $this->ok($this->present($deal->fresh('stage')));
    }

    /** DELETE /api/v1/deals/{id}. */
    public function destroy(int $id): JsonResponse
    {
        if ($r = $this->ensurePlan()) return $r;
        $deal = Deal::query()->forCurrentWorkspace()->whereKey($id)->first();
        if (!$deal) return $this->fail('not_found', 'Deal not found.', 404);
        $deal->activities()->delete();
        $deal->delete();
        return $this->ok(['deleted' => true]);
    }

    /* -------------------- helpers -------------------- */

    private function ensurePlan(): ?JsonResponse
    {
        $ws = Workspace::find($this->workspaceId());
        if (!$ws || !PlanLimitGuard::hasFeature($ws, 'access_sales_pipeline')) {
            return $this->fail('plan_feature_disabled', "Your plan doesn't include the Sales Pipeline. Upgrade to unlock the deals API.", 403);
        }
        return null;
    }

    private function present(Deal $d): array
    {
        return [
            'id'                  => $d->id,
            'title'               => $d->title,
            'pipeline_id'         => $d->pipeline_id,
            'stage_id'            => $d->stage_id,
            'stage'               => optional($d->stage)->name,
            'status'              => $d->status,
            'value'               => (int) $d->value_minor / 100,
            'value_minor'         => (int) $d->value_minor,
            'currency'            => $d->currency,
            'contact_id'          => $d->contact_id,
            'owner_user_id'       => $d->owner_user_id,
            'source'              => $d->source,
            'expected_close_date' => optional($d->expected_close_date)->toDateString(),
            'lost_reason'         => $d->lost_reason,
            'won_at'              => optional($d->won_at)->toIso8601String(),
            'lost_at'             => optional($d->lost_at)->toIso8601String(),
            'created_at'          => optional($d->created_at)->toIso8601String(),
            'updated_at'          => optional($d->updated_at)->toIso8601String(),
        ];
    }
}
