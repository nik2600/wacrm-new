<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\BroadcastsController;
use App\Http\Requests\Api\V1\Broadcast\StoreBroadcastRequest;
use App\Http\Resources\Api\V1\BroadcastResource;
use App\Models\Broadcast;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Broadcasts — send one template to a list of contacts / a contact group,
 * then read status + per-recipient delivery.
 *
 * Reuses the tested web pipeline (App\Http\Controllers\BroadcastsController →
 * Broadcast + broadcast_contacts + Node bridge dispatch) for create/stop, and
 * re-wraps every result in the public { data } / { error } envelope. List /
 * read are served straight off the workspace-scoped Broadcast model since the
 * web controller's index/show return HTML, not data.
 */
class BroadcastController extends V1Controller
{
    /** POST /api/v1/broadcasts — send a template to a list / group. */
    public function store(StoreBroadcastRequest $request): JsonResponse
    {
        // Workspace-scope every id the caller supplied BEFORE handing off. The
        // web controller trusts its own form and doesn't re-scope these, so an
        // API key could otherwise reference another tenant's contacts, group or
        // template. Filter recipients/group to rows in THIS workspace, and
        // refuse a template that isn't owned here.
        $recipientIds = array_values(array_unique(array_map('intval', (array) $request->input('recipients', []))));
        if (!empty($recipientIds)) {
            $recipientIds = \App\Models\Contact::query()->forCurrentWorkspace()
                ->whereIn('id', $recipientIds)->pluck('id')->map(fn ($i) => (int) $i)->all();
        }

        $groupIds = [];
        if ($request->filled('group_id')) {
            $groupIds = \App\Models\ContactGroup::query()->forCurrentWorkspace()
                ->whereKey((int) $request->input('group_id'))->pluck('id')->map(fn ($i) => (int) $i)->all();
            if (empty($groupIds)) {
                return $this->fail('invalid_group', 'Contact group not found in this workspace.', 422);
            }
        }

        if (empty($recipientIds) && empty($groupIds)) {
            return $this->fail('no_recipients', 'No recipients in this workspace matched the supplied ids.', 422);
        }

        $ownsTemplate = \App\Models\WaTemplate::query()->forCurrentWorkspace()
            ->whereKey((int) $request->input('template_id'))->exists();
        if (!$ownsTemplate) {
            return $this->fail('invalid_template', 'Template not found in this workspace.', 422);
        }

        // Map the public-facing keys onto the web controller's input shape.
        // Presence of schedule_at switches the send to "later"; otherwise "now".
        $params = [
            'broadcast_name' => $request->input('name'),
            'template_id'    => (int) $request->input('template_id'),
            'contacts'       => $recipientIds,
            'groups'         => $groupIds,
            'device_id'      => $request->input('device_id'),
            'timezone'       => $request->input('timezone'),
        ];
        if ($request->filled('schedule_at')) {
            $when                  = \Illuminate\Support\Carbon::parse($request->input('schedule_at'));
            $params['schedule_type'] = 'later';
            $params['send_date']     = $when->format('Y-m-d');
            $params['send_time']     = $when->format('H:i');
        } else {
            $params['schedule_type'] = 'now';
        }

        $internal = Request::create('/broadcasts', 'POST', $params, [], [], [
            'HTTP_ACCEPT' => 'application/json',
        ]);
        $internal->setUserResolver(fn () => $request->user());

        $payload = app(BroadcastsController::class)->store($internal)->getData(true);

        if (($payload['ok'] ?? false) !== true) {
            return $this->fail('create_failed', $payload['message'] ?? $payload['error'] ?? 'Broadcast could not be created.', 422);
        }

        $broadcast = Broadcast::query()->forCurrentWorkspace()->find($payload['broadcast_id'] ?? 0);
        if (!$broadcast) {
            return $this->created([
                'broadcast_ids' => $payload['broadcast_ids'] ?? [],
                'devices_count' => $payload['devices_count'] ?? 1,
            ]);
        }

        return $this->created((new BroadcastResource($broadcast))->resolve(), [
            'broadcast_ids' => $payload['broadcast_ids'] ?? [$broadcast->id],
            'devices_count' => $payload['devices_count'] ?? 1,
        ]);
    }

    /** GET /api/v1/broadcasts — recent broadcasts for the workspace. */
    public function index(Request $request): JsonResponse
    {
        $limit = min(max((int) $request->input('limit', 50), 1), 200);

        $items = Broadcast::query()
            ->forCurrentWorkspace()
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->input('status')))
            ->orderByDesc('id')
            ->limit($limit)
            ->get()
            ->map(fn ($b) => (new BroadcastResource($b))->resolve())
            ->values();

        return $this->ok($items, ['count' => $items->count()]);
    }

    /** GET /api/v1/broadcasts/{id} — one broadcast with status counts. */
    public function show(int $id): JsonResponse
    {
        $broadcast = Broadcast::query()->forCurrentWorkspace()->find($id);
        if (!$broadcast) {
            return $this->fail('not_found', 'Broadcast not found.', 404);
        }

        return $this->ok((new BroadcastResource($broadcast))->resolve());
    }

    /** GET /api/v1/broadcasts/{id}/recipients — per-recipient delivery. */
    public function recipients(Request $request, int $id): JsonResponse
    {
        $broadcast = Broadcast::query()->forCurrentWorkspace()->find($id);
        if (!$broadcast) {
            return $this->fail('not_found', 'Broadcast not found.', 404);
        }

        // Rollup straight from the web controller so the totals stay in sync
        // with the dashboard's per-broadcast statistics modal.
        $stats = app(BroadcastsController::class)->statistics($id)->getData(true);

        $limit = min(max((int) $request->input('limit', 100), 1), 500);

        $rows = DB::table('broadcast_contacts')
            ->where('broadcast_id', $broadcast->id)
            ->orderByDesc('id')
            ->limit($limit)
            ->get(['contact_id', 'status', 'error_message', 'whatsapp_message_id', 'sent_at', 'delivered_at', 'read_at'])
            ->map(fn ($r) => [
                'contact_id'    => (int) $r->contact_id,
                'status'        => $r->status ?: 'pending',
                'error'         => $r->error_message,
                'wa_message_id' => $r->whatsapp_message_id,
                'sent_at'       => $r->sent_at,
                'delivered_at'  => $r->delivered_at,
                'read_at'       => $r->read_at,
            ])
            ->values();

        return $this->ok($rows, [
            'count' => $rows->count(),
            'stats' => $stats['stats'] ?? [],
        ]);
    }

    /** POST /api/v1/broadcasts/{id}/stop — cancel a scheduled / in-flight broadcast. */
    public function stop(int $id): JsonResponse
    {
        $broadcast = Broadcast::query()->forCurrentWorkspace()->find($id);
        if (!$broadcast) {
            return $this->fail('not_found', 'Broadcast not found.', 404);
        }

        if (!in_array($broadcast->status, ['scheduled', 'processing'], true)) {
            return $this->fail('not_stoppable', 'Only scheduled or in-flight broadcasts can be stopped.', 422);
        }

        // Best-effort cancel on the Node bridge before flipping local status —
        // same order + endpoint the web controller's destroy() uses.
        if ($broadcast->node_schedule_id) {
            $base = wd_node_url();
            if ($base !== '') {
                try {
                    \Illuminate\Support\Facades\Http::timeout(5)
                        ->delete(rtrim($base, '/') . '/api/broadcast/cancel/' . $broadcast->node_schedule_id);
                } catch (\Throwable $e) {
                    \Illuminate\Support\Facades\Log::warning('[V1] broadcast stop bridge cancel failed', [
                        'id'    => $broadcast->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }

        $broadcast->status       = 'cancelled';
        $broadcast->completed_at = $broadcast->completed_at ?? now();
        $broadcast->save();

        return $this->ok((new BroadcastResource($broadcast->fresh()))->resolve());
    }
}
