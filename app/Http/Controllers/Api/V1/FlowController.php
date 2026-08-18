<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\Flow\EnrollFlowRequest;
use App\Http\Resources\Api\V1\FlowResource;
use App\Models\Contact;
use App\Models\Flow;
use App\Models\FlowSubscriber;
use App\Services\Flow\FlowEnrollmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Flows — read the workspace's chatbot flows, enroll a contact into one, and
 * list a flow's subscribers.
 *
 * Reuses the existing flow stack: the same Flow::forCurrentWorkspace() scope
 * the web FlowsController queries, and FlowEnrollmentService::enroll() for the
 * (idempotent, Node-backed) enrollment pipeline. Results are wrapped in the
 * public { data } envelope.
 */
class FlowController extends V1Controller
{
    /** GET /api/v1/flows — list the workspace's flows. */
    public function index(Request $request): JsonResponse
    {
        $items = Flow::query()
            ->forCurrentWorkspace()
            ->withCount(['subscribers as subscribers_count'])
            ->orderByDesc('updated_at')
            ->get()
            ->map(fn (Flow $f) => (new FlowResource($f))->resolve())
            ->values();

        return $this->ok($items, ['count' => $items->count()]);
    }

    /** GET /api/v1/flows/{id} — one flow. */
    public function show(int $id): JsonResponse
    {
        $flow = Flow::query()
            ->forCurrentWorkspace()
            ->withCount(['subscribers as subscribers_count'])
            ->find($id);

        if (!$flow) {
            return $this->fail('not_found', 'Flow not found.', 404);
        }

        return $this->ok((new FlowResource($flow))->resolve());
    }

    /**
     * POST /api/v1/flows/{id}/enroll — enroll a contact (by id or phone) into
     * the flow. Delegates to FlowEnrollmentService::enroll(), which is
     * idempotent at the UNIQUE(flow_id, contact_id) constraint and POSTs to
     * the Node flow runtime.
     */
    public function enroll(EnrollFlowRequest $request, int $id): JsonResponse
    {
        $flow = Flow::query()->forCurrentWorkspace()->find($id);
        if (!$flow) {
            return $this->fail('not_found', 'Flow not found.', 404);
        }
        if (!$flow->is_active) {
            return $this->fail('flow_inactive', 'Flow is not active.', 422);
        }

        $contact = $this->resolveContact($request, (int) $flow->workspace_id);
        if (!$contact) {
            return $this->fail('contact_not_found', 'No contact matches that id or phone in this workspace.', 404);
        }

        try {
            $sub = app(FlowEnrollmentService::class)->enroll($contact, $flow);
        } catch (\Throwable $e) {
            return $this->fail('enroll_failed', $e->getMessage(), 422);
        }

        return $this->created([
            'flow_id'       => $flow->id,
            'contact_id'    => $contact->id,
            'subscriber_id' => $sub->id,
            'status'        => $sub->status,
            'enrolled_at'   => optional($sub->enrolled_at)->toIso8601String(),
        ]);
    }

    /**
     * GET /api/v1/flows/{id}/subscribers — list the flow's subscribers and
     * their state. Mirrors FlowsController::apiSubscribers.
     */
    public function subscribers(Request $request, int $id): JsonResponse
    {
        $flow = Flow::query()->forCurrentWorkspace()->find($id);
        if (!$flow) {
            return $this->fail('not_found', 'Flow not found.', 404);
        }

        $limit = min(max((int) $request->input('limit', 100), 1), 200);

        $subs = FlowSubscriber::query()
            ->where('flow_id', $flow->id)
            ->with(['contact:id,first_name,last_name,country_code,mobile'])
            ->orderByDesc('id')
            ->limit($limit)
            ->get();

        $items = $subs->map(fn (FlowSubscriber $s) => [
            'id'             => $s->id,
            'contact_id'     => $s->contact_id,
            'contact_name'   => trim(($s->contact->first_name ?? '') . ' ' . ($s->contact->last_name ?? '')) ?: ('#' . $s->contact_id),
            'contact_phone'  => preg_replace('/\D+/', '', (string) (($s->contact->country_code ?? '') . ($s->contact->mobile ?? ''))) ?: '',
            'status'         => $s->status,
            'enrolled_at'    => optional($s->enrolled_at)->toIso8601String(),
            'failed_at'      => optional($s->failed_at)->toIso8601String(),
            'failure_reason' => $s->failure_reason,
        ])->values();

        $counts = [
            'active'    => $subs->where('status', 'active')->count(),
            'paused'    => $subs->where('status', 'paused')->count(),
            'completed' => $subs->where('status', 'completed')->count(),
            'failed'    => $subs->where('status', 'failed')->count(),
        ];

        return $this->ok($items, ['count' => $items->count(), 'counts' => $counts]);
    }

    /**
     * Resolve the request's contact by `contact_id` or `phone`, scoped to the
     * flow's workspace. `mobile` is encrypted-at-rest so the phone lookup
     * hydrates the workspace's contacts and compares digits in PHP (same
     * approach as FlowEnrollmentService::onConversationTagged).
     */
    private function resolveContact(EnrollFlowRequest $request, int $workspaceId): ?Contact
    {
        if ($request->filled('contact_id')) {
            return Contact::query()
                ->where('id', $request->integer('contact_id'))
                ->where('workspace_id', $workspaceId)
                ->first();
        }

        $target = preg_replace('/\D+/', '', (string) $request->input('phone'));
        if ($target === '') {
            return null;
        }

        return Contact::query()
            ->where('workspace_id', $workspaceId)
            ->get()
            ->first(function (Contact $c) use ($target) {
                $stored = preg_replace('/\D+/', '', (string) (($c->country_code ?? '') . ($c->mobile ?? '')));
                return $stored !== '' && $stored === $target;
            });
    }
}
