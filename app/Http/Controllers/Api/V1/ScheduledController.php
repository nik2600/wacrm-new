<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\App\QueueController;
use App\Http\Requests\Api\V1\Scheduled\StoreScheduledRequest;
use App\Http\Resources\Api\V1\ScheduledResource;
use App\Models\ScheduledMessage;
use App\Services\NodeSchedulerClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Scheduled messages — schedule a WhatsApp send for later, list/read schedules,
 * and cancel a future one.
 *
 * Reuses the tested scheduling pipeline (QueueController::scheduleMessage →
 * ScheduledMessage + Node scheduler via /api/scheduled/active), wrapping the
 * result in the public { data } envelope. Reads use the ScheduledMessage model
 * directly, workspace-scoped.
 */
class ScheduledController extends V1Controller
{
    /** POST /api/v1/scheduled — schedule a send (template/message + recipients + run_at). */
    public function store(StoreScheduledRequest $request): JsonResponse
    {
        // Ownership gate: reject a template_id that isn't in THIS workspace, so a
        // client can't schedule another tenant's template (the delegate persists
        // it unscoped — mirrors the check BroadcastController already does).
        $templateId = $request->input('template_id');
        if ($templateId && !\App\Models\WaTemplate::forCurrentWorkspace()->whereKey((int) $templateId)->exists()) {
            return $this->fail('template_not_found', 'Template not found in this workspace.', 404);
        }

        // Map the public-facing keys onto the keys QueueController::scheduleMessage
        // expects (run_at → send_at). The underlying controller resolves the
        // device, parses send_at in the timezone, and persists the row.
        $params = [
            'name'        => $request->input('name'),
            'message'     => $request->input('message'),
            'template_id' => $request->input('template_id'),
            'device_id'   => $request->input('device_id'),
            'recipients'  => (array) $request->input('recipients', []),
            'send_at'     => $request->input('run_at'),
            'timezone'    => $request->input('timezone'),
        ];

        $internal = Request::create('/api/app/schedule-message', 'POST', $params);
        $internal->setUserResolver(fn () => $request->user());

        $payload = app(QueueController::class)->scheduleMessage($internal)->getData(true);

        if (($payload['success'] ?? false) !== true) {
            return $this->fail(
                'schedule_failed',
                $payload['message'] ?? 'Message could not be scheduled.',
                422,
                $payload['data']['errors'] ?? []
            );
        }

        $id  = $payload['data']['schedule_id'] ?? null;
        $row = $id ? ScheduledMessage::find($id) : null;
        if (!$row) {
            return $this->fail('schedule_failed', 'Schedule was not persisted.', 422);
        }

        return $this->created((new ScheduledResource($row))->resolve());
    }

    /** GET /api/v1/scheduled — list the workspace's scheduled messages. */
    public function index(Request $request): JsonResponse
    {
        $limit = min(max((int) $request->input('limit', 50), 1), 200);

        $items = ScheduledMessage::query()
            ->forWorkspace($this->workspaceId())
            ->latest()
            ->limit($limit)
            ->get()
            ->map(fn ($s) => (new ScheduledResource($s))->resolve())
            ->values();

        return $this->ok($items, ['count' => $items->count()]);
    }

    /** GET /api/v1/scheduled/{id} — one scheduled message. */
    public function show(int $id): JsonResponse
    {
        $row = ScheduledMessage::query()
            ->forWorkspace($this->workspaceId())
            ->whereKey($id)
            ->first();

        if (!$row) {
            return $this->fail('not_found', 'Scheduled message not found.', 404);
        }

        return $this->ok((new ScheduledResource($row))->resolve());
    }

    /** DELETE /api/v1/scheduled/{id} — cancel a future scheduled message. */
    public function destroy(int $id): JsonResponse
    {
        $row = ScheduledMessage::query()
            ->forWorkspace($this->workspaceId())
            ->whereKey($id)
            ->first();

        if (!$row) {
            return $this->fail('not_found', 'Scheduled message not found.', 404);
        }

        if (in_array($row->status, ['completed', 'cancelled', 'failed'], true)) {
            return $this->fail('not_cancellable', 'This schedule is already finished.', 422);
        }

        // Drop the registered Node job before flipping local status — same as
        // ScheduledController::cancel — so the bot doesn't fire a cancelled send.
        if ($row->node_schedule_id) {
            app(NodeSchedulerClient::class)->cancel($row);
        }
        $row->forceFill(['status' => 'cancelled'])->save();

        return $this->ok((new ScheduledResource($row->fresh()))->resolve());
    }
}
