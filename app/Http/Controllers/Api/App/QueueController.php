<?php

namespace App\Http\Controllers\Api\App;

use App\Http\Controllers\Controller;
use App\Models\Broadcast;
use App\Models\Contact;
use App\Models\Device;
use App\Models\ScheduledMessage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Mobile-app Message Queues (B4). The old project's
 * WhatsAppMessageApiController treated a "queue" as a batch of
 * `messages` rows sharing a generated `queue_id`, sent in bulk by
 * startMessageQueue(). Our codebase has no flat `messages`+`queue_id`
 * bulk engine — bulk sends are first-class `Broadcast` rows with
 * recipient-level fanout in `broadcast_contacts`, dispatched to the Node
 * bridge by BroadcastsController. So we map the old "queue" concept onto
 * our Broadcast model:
 *
 *   old queue_id                → Broadcast.id
 *   old per-queue recipients    → broadcast_contacts (Broadcast::contacts())
 *   old createMessageQueue      → createMessageQueue() here (persist Broadcast
 *                                  in `scheduled` = draft, attach contacts)
 *   old startMessageQueue (all) → startSelectedMessageQueue() (flip to
 *                                  processing + POST the Node bridge — REAL)
 *   old message-status          → messageStatus() from the cached aggregate
 *                                  columns + broadcast_contacts
 *
 * New endpoint: POST /schedule-message persists a ScheduledMessage row (our
 * future-send model) — the Node scheduler picks it up via /api/scheduled/active.
 *
 * Every query is workspace-scoped via Broadcast::forCurrentWorkspace() /
 * ScheduledMessage::forCurrentWorkspace(). Response keys stay compatible with
 * the old contract:
 *   createMessageQueue → {success, message, data:{queue_id, total_recipient}}
 *   getQueues          → {success, data:[...], total}
 *   messageStatus      → {success, stats:{sent, delivered, read, failed, ...}}
 *
 * REAL vs STUBBED: create / list / status / delete / start-sending /
 * schedule-message all run against real models, and start-sending performs
 * the real Node-bridge dispatch. Pin / archive have NO backing column on the
 * `broadcasts` table (would need a migration — a shared-code change we must
 * not make), so those endpoints respond honestly that the feature needs a
 * schema column. See the controller report.
 */
class QueueController extends Controller
{
    /**
     * POST /create-queue — create a message queue (draft Broadcast).
     *
     * Old params: template_title, temp_caption, contact_numbers / group_id /
     * group_name, template_id, device_id. We accept `name` (or
     * template_title), `template_id`, `device_id`, plus recipients as
     * `contacts[]` (contact ids), `groups[]` (group ids), and
     * `contact_numbers` (raw phone strings — matched against the workspace's
     * contacts since broadcast_contacts is contact-id keyed).
     */
    public function createMessageQueue(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'name'            => 'required_without:template_title|nullable|string|max:255',
                'template_title'  => 'nullable|string|max:255',
                'template_id'     => 'nullable|integer',
                'device_id'       => 'nullable|integer',
                'message'         => 'nullable|string|max:4096',
                'temp_caption'    => 'nullable|string|max:4096',
                // Custom-message content (text + media + buttons + location).
                'template_type'   => 'nullable|string|max:40',
                'temp_image'      => 'nullable|string|max:500',   // existing path/url
                'template_media'  => 'nullable|file|max:51200',   // uploaded file (≤50MB)
                'button_text'     => 'nullable',                  // JSON string or array
                'latitude'        => 'nullable|string|max:32',
                'longitude'       => 'nullable|string|max:32',
                'contacts'        => 'nullable|array',
                'contacts.*'      => 'integer',
                'groups'          => 'nullable|array',
                'groups.*'        => 'integer',
                'contact_numbers' => 'nullable',
                'timezone'        => 'nullable|string|max:64',
            ]);

            $user = $request->user();
            $wsId = (int) ($user->current_workspace_id ?? 0);

            $name = $validated['name'] ?? $validated['template_title'] ?? null;
            if (!$name) {
                return response()->json([
                    'success' => false,
                    'message' => 'A queue name (or template_title) is required.',
                    'data'    => null,
                ], 422);
            }

            $contactIds = $this->resolveRecipientContactIds($request, $wsId, true);
            if (empty($contactIds)) {
                return response()->json([
                    'success' => false,
                    'message' => 'No recipients found. Provide contacts[], groups[], or contact_numbers.',
                    'data'    => null,
                ], 400);
            }

            // Resolve the sending device: explicit (workspace-validated) → else
            // the workspace's connected device, so device_id is never null.
            $deviceId = $this->resolveQueueDeviceId(
                isset($validated['device_id']) ? (int) $validated['device_id'] : null,
                (int) $wsId
            );

            // ── Resolve custom-message content so a non-template queue
            //    actually carries its text / media / buttons / location. ──
            $caption = $validated['message'] ?? $validated['temp_caption'] ?? null;

            // Media: an uploaded file wins; else an already-known path/url.
            $mediaPath = $validated['temp_image'] ?? null;
            if ($request->hasFile('template_media')) {
                $mediaPath = $request->file('template_media')->store('wa-queue', media_disk()); // → "wa-queue/xyz.jpg"
            }

            // Buttons: accept a JSON string or an array; store as a JSON string.
            $buttonsJson = null;
            $buttonsRaw  = $request->input('button_text');
            if ($buttonsRaw !== null && $buttonsRaw !== '') {
                $buttonsJson = is_array($buttonsRaw) ? json_encode($buttonsRaw) : (string) $buttonsRaw;
            }

            // template_type: explicit or inferred from what was provided.
            $templateType = $validated['template_type'] ?? null;
            if (!$templateType) {
                if (!empty($validated['latitude']) && !empty($validated['longitude'])) {
                    $templateType = 'Text-With-Location';
                } elseif ($mediaPath && $caption) {
                    $templateType = 'Text-With-Media';
                } elseif ($mediaPath) {
                    $templateType = 'Image-Only';
                } else {
                    $templateType = 'Plane-Text';
                }
            }

            $content = [
                'temp_caption'  => $caption,
                'template_type' => $templateType,
                'temp_image'    => $mediaPath,
                'button_text'   => $buttonsJson,
                'latitude'      => $validated['latitude'] ?? null,
                'longitude'     => $validated['longitude'] ?? null,
            ];

            $broadcast = DB::transaction(function () use ($validated, $name, $wsId, $user, $deviceId, $contactIds, $content) {
                $b = Broadcast::create([
                    'user_id'          => $user->id,
                    'workspace_id'     => $wsId,
                    // A resolved $deviceId is a Baileys device (devices table is
                    // Baileys-only) → stamp Baileys, not the workspace default, or
                    // Node routes the broadcast to the wrong engine. Same fix as
                    // the start-sending path below.
                    'provider'         => $deviceId
                        ? \App\Services\WorkspaceEngine::ENGINE_BAILEYS
                        : \App\Services\WorkspaceEngine::for($wsId ?: null),
                    'device_id'        => $deviceId,
                    'template_id'      => $validated['template_id'] ?? null,
                    'name'             => $name,
                    'timezone'         => $validated['timezone'] ?? 'UTC',
                    // `scheduled` here means "created, not yet sent" — the
                    // draft state. start-sending flips it to `processing`.
                    'status'           => 'scheduled',
                    'total_recipients' => count($contactIds),
                ] + $content);

                foreach ($contactIds as $cid) {
                    $b->contacts()->attach($cid, [
                        'status'     => 'pending',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }

                return $b;
            });

            return response()->json([
                'success' => true,
                'message' => 'Queue created successfully!',
                'data'    => [
                    'queue_id'        => $broadcast->id,
                    'total_recipient' => count($contactIds),
                    'name'            => $name,
                    'status'         => $broadcast->status,
                ],
            ], 200);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'data'    => ['errors' => $e->errors()],
            ], 422);
        } catch (\Throwable $e) {
            Log::error('WaDesk app createMessageQueue failed: ' . $e->getMessage(), [
                'user_id' => $request->user()?->id,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to create message queue.',
                'data'    => ['error' => $e->getMessage()],
            ], 500);
        }
    }

    /** GET /get-queues — list the workspace's queues (Broadcasts). */
    public function getQueues(Request $request): JsonResponse
    {
        try {
            // Hide archived queues from the main feed (they live under
            // /all-archive-queue). Tolerates an `include_archived=1` flag
            // for the rare case the app dev wants the combined list.
            $includeArchived = $request->boolean('include_archived', false);
            // Device scoping — same as the chat list: when the app's device/
            // account picker is active, show only THIS device's queues.
            $deviceId = $this->deviceIdFromRequest($request);
            $queues = Broadcast::query()
                ->forCurrentWorkspace()
                ->when($deviceId, fn ($q) => $q->where('device_id', $deviceId))
                ->when(! $includeArchived, fn ($q) => $q->where(function ($w) {
                    $w->where('archived', false)->orWhereNull('archived');
                }))
                ->orderByDesc('id')
                ->get()
                ->map(fn (Broadcast $b) => $this->queuePayload($b))
                ->values();

            return response()->json([
                'success' => true,
                'data'    => $queues,
                'total'   => $queues->count(),
            ], 200);
        } catch (\Throwable $e) {
            Log::error('WaDesk app getQueues failed: ' . $e->getMessage(), [
                'user_id' => $request->user()?->id,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch queues.',
                'data'    => ['error' => $e->getMessage()],
            ], 500);
        }
    }

    /** GET /get-queue/{queueId} — one queue's recipients + per-contact status. */
    public function getQueueMessages(Request $request, int $queueId): JsonResponse
    {
        try {
            $b = Broadcast::query()->forCurrentWorkspace()->find($queueId);
            if (!$b) {
                return response()->json(['success' => false, 'message' => 'Queue not found.'], 404);
            }

            // Hydrate the involved contacts so the encrypted name/mobile cast
            // decrypts; key by id for an O(1) merge with the pivot rows.
            $pivot = DB::table('broadcast_contacts')
                ->where('broadcast_id', $b->id)
                ->get(['contact_id', 'status', 'whatsapp_message_id', 'sent_at', 'delivered_at', 'read_at']);

            $contactMap = Contact::query()
                ->whereIn('id', $pivot->pluck('contact_id')->unique())
                ->get(['id', 'name', 'first_name', 'last_name', 'country_code', 'mobile'])
                ->keyBy('id');

            $messages = $pivot->map(function ($r) use ($contactMap) {
                $c     = $contactMap->get($r->contact_id);
                $phone = $c ? preg_replace('/\D+/', '', (string) ($c->country_code . $c->mobile)) : '';
                $name  = $c
                    ? (trim((string) ($c->name ?? '')) ?: trim(($c->first_name ?? '') . ' ' . ($c->last_name ?? '')) ?: $phone)
                    : ('Contact #' . $r->contact_id);

                return [
                    'contact_id'    => $r->contact_id,
                    'to_number'     => $phone,
                    'contact_name'  => $name,
                    'status'        => $r->status ?: 'pending',
                    'wa_message_id' => $r->whatsapp_message_id,
                    'sent_at'       => $r->sent_at,
                    'delivered_at'  => $r->delivered_at,
                    'read_at'       => $r->read_at,
                ];
            })->values();

            return response()->json([
                'success'        => true,
                'queue'          => $this->queuePayload($b),
                'messages'       => $messages,
                'total_messages' => $messages->count(),
            ], 200);
        } catch (\Throwable $e) {
            Log::error('WaDesk app getQueueMessages failed: ' . $e->getMessage(), [
                'user_id'  => $request->user()?->id,
                'queue_id' => $queueId,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch queue messages.',
                'data'    => ['error' => $e->getMessage()],
            ], 500);
        }
    }

    /**
     * GET /delete-queues — delete a queue (Broadcast). Accepts ?queue_id=
     * (single) or ?queue_ids=1,2,3. Any status is deletable: if the queue
     * is in-flight on Node (has node_schedule_id), we ask Node to cancel
     * it first so a future-scheduled send doesn't fire against a row that
     * no longer exists. Previously only scheduled/failed/completed rows
     * could be deleted, so draft / processing queues piled up forever.
     */
    public function deleteQueue(Request $request): JsonResponse
    {
        try {
            $ids = $this->queueIdsFromRequest($request);
            if (empty($ids)) {
                return response()->json(['success' => false, 'message' => 'No queue id provided.'], 422);
            }

            $deleted = 0;
            $cancelled = [];
            $missing = [];
            foreach ($ids as $id) {
                $b = Broadcast::query()->forCurrentWorkspace()->find($id);
                if (!$b) {
                    $missing[] = $id;
                    continue;
                }

                // Best-effort cancel of a Node-held schedule so it can't fire
                // after we've deleted the local row. Failure is swallowed so a
                // network blip doesn't trap the row as undeletable.
                if ($b->node_schedule_id) {
                    try {
                        $base = function_exists('wd_node_url') ? wd_node_url() : '';
                        if ($base !== '') {
                            Http::timeout(5)->delete(rtrim($base, '/') . '/api/broadcast/cancel/' . rawurlencode((string) $b->node_schedule_id));
                            $cancelled[] = $id;
                        }
                    } catch (\Throwable $e) {
                        Log::warning('[QUEUE-APP] Node cancel failed during delete', [
                            'queue_id' => $b->id,
                            'node_schedule_id' => $b->node_schedule_id,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }

                $b->contacts()->detach();
                $b->delete();
                $deleted++;
            }

            return response()->json([
                'success'       => $deleted > 0,
                'message'       => $deleted > 0
                    ? 'Queue(s) deleted successfully.'
                    : 'No queues matched the provided ids.',
                'deleted_count' => $deleted,
                'cancelled'     => $cancelled,
                'missing'       => $missing,
            ], $deleted > 0 ? 200 : 404);
        } catch (\Throwable $e) {
            Log::error('WaDesk app deleteQueue failed: ' . $e->getMessage(), [
                'user_id' => $request->user()?->id,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to delete queue.',
                'data'    => ['error' => $e->getMessage()],
            ], 500);
        }
    }

    /**
     * POST /start-sending — dispatch a queue to the Node bridge NOW.
     *
     * Old startSelectedMessageQueue sent every pending message in a queue.
     * We flip the Broadcast to `processing` and POST the recipient list to
     * Node's /api/broadcast/send-immediate/{phone} — the same endpoint
     * BroadcastsController@dispatchToBridge uses. REAL send.
     */
    public function startSelectedMessageQueue(Request $request): JsonResponse
    {
        try {
            // Accept BOTH `queue_id` (single) and `queue_ids` (CSV/array/JSON) —
            // the app sends queue_ids.
            $ids = $this->queueIdsFromRequest($request);
            if (empty($ids)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed.',
                    'data'    => ['errors' => ['queue_id' => ['The queue id field is required.']]],
                ], 422);
            }

            $broadcasts = Broadcast::query()->forCurrentWorkspace()->whereIn('id', $ids)->get();
            if ($broadcasts->isEmpty()) {
                return response()->json(['success' => false, 'message' => 'Queue not found.'], 404);
            }

            // IDEMPOTENCY GUARD — refuse re-dispatch when a fresh send is
            // already in flight (status='processing' updated within the last
            // 60 seconds). Stops the "double-tap the Schedule button on
            // Flutter → message fires twice" pattern at the source. The
            // 60-second window is wide enough to cover a slow Node response
            // but tight enough that genuine retries (after a full minute) go
            // through.
            $inFlight = $broadcasts->first(function ($b) {
                return $b->status === 'processing'
                    && $b->updated_at
                    && $b->updated_at->gt(now()->subMinute());
            });
            if ($inFlight) {
                return response()->json([
                    'success'  => false,
                    'code'     => 'queue_in_flight',
                    'message'  => 'This queue is already being dispatched. Wait a minute or hit Stop first.',
                    'queue_id' => $inFlight->id,
                ], 409);
            }

            // SCHEDULE BRANCH — if the app passed a future send time, schedule
            // instead of sending now. Web parity: stamp the row's
            // scheduled_at + timezone, then POST to Node's /api/broadcast/
            // schedule endpoint so Node holds the schedule. The earlier
            // implementation only wrote a local ScheduledMessage row +
            // relied on the ScheduledMessageSweeper, which silently did
            // nothing if the sweeper wasn't running on the server. Matches
            // BroadcastsController::dispatchToBridge's $immediate=false branch.
            [$whenUtc, $tz, $scheduleError] = $this->parseScheduleAt($request);
            // The caller submitted schedule input but it failed to parse OR
            // was within the next minute / in the past. Reject the request
            // instead of silently falling through to immediate fire — that
            // was the "instant + scheduled fires twice" bug: when send_at
            // failed to parse, we sent NOW, but the queue may already have
            // had a prior Node schedule that still fired at its time too.
            if ($scheduleError) {
                return response()->json([
                    'success' => false,
                    'message' => $scheduleError,
                ], 422);
            }
            if ($whenUtc) {
                $scheduled = [];
                $anyOk = false;
                foreach ($broadcasts as $b) {
                    // Persist the schedule on the row BEFORE dispatch so the
                    // bridge call reads scheduled_at + timezone from the model.
                    $b->update([
                        'status'       => 'scheduled',
                        'scheduled_at' => $whenUtc,
                        'timezone'     => $tz,
                    ]);
                    $dispatch = $this->dispatchBroadcastToBridge($b->refresh(), false);
                    $ok = (bool) ($dispatch['ok'] ?? false);
                    $anyOk = $anyOk || $ok;
                    $scheduled[] = [
                        'queue_id'         => $b->id,
                        'scheduled'        => $ok,
                        'node_schedule_id' => $dispatch['node_schedule_id'] ?? null,
                        'dispatch'         => $dispatch,
                    ];
                }
                return response()->json([
                    'success'        => $anyOk,
                    'message'        => $anyOk
                        ? 'Queue scheduled for ' . $whenUtc->copy()->setTimezone($tz)->toDateTimeString() . ' (' . $tz . ').'
                        : ('Queue could not be scheduled: ' . ($scheduled[0]['dispatch']['error'] ?? 'unknown error')),
                    'scheduled_time' => $whenUtc->toDateTimeString(),
                    'timezone'       => $tz,
                    'results'        => $scheduled,
                ], $anyOk ? 200 : 502);
            }

            $results = [];
            $anyOk   = false;
            foreach ($broadcasts as $b) {
                $b->update(['status' => 'processing']);
                $dispatch = $this->dispatchBroadcastToBridge($b, true);
                $anyOk = $anyOk || ($dispatch['ok'] ?? false);
                $results[] = [
                    'queue_id' => $b->id,
                    'status'   => $b->refresh()->status,
                    'ok'       => (bool) ($dispatch['ok'] ?? false),
                    'dispatch' => $dispatch,
                ];
            }

            $first = $results[0];

            return response()->json([
                'success'  => $anyOk,
                'message'  => $anyOk
                    ? 'Queue dispatched for sending.'
                    : ('Queue could not be dispatched: ' . ($first['dispatch']['error'] ?? 'unknown error')),
                'queue_id' => $first['queue_id'],
                'status'   => $first['status'],
                'dispatch' => $first['dispatch'],
                'results'  => $results,
            ], $anyOk ? 200 : 502);
        } catch (\Throwable $e) {
            Log::error('WaDesk app startSelectedMessageQueue failed: ' . $e->getMessage(), [
                'user_id' => $request->user()?->id,
            ]);

            return response()->json(['success' => false, 'message' => 'Failed to start sending.', 'data' => ['error' => $e->getMessage()]], 500);
        }
    }

    /**
     * POST /send-to-existing-queue — append more recipients to a queue.
     *
     * Old sendToExistingQueue added recipients to an existing queue_id. We
     * attach the resolved contact ids to the Broadcast (skipping ones already
     * attached) and bump total_recipients.
     */
    public function sendToExistingQueue(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate(['queue_id' => 'required|integer']);
            $user = $request->user();
            $wsId = (int) ($user->current_workspace_id ?? 0);

            $b = Broadcast::query()->forCurrentWorkspace()->find((int) $validated['queue_id']);
            if (!$b) {
                return response()->json(['success' => false, 'message' => 'Queue not found.'], 404);
            }

            // autoCreate=true → raw phone numbers that aren't saved contacts are
            // created on the fly, so "send to existing queue" works for any
            // number (text + media). This is what previously failed with
            // "No new recipients resolved" when the app sent unsaved numbers.
            $contactIds = $this->resolveRecipientContactIds($request, $wsId, true);

            $existing = $b->contacts()->pluck('contacts.id')->all();
            $toAdd    = array_values(array_diff($contactIds, $existing));

            // No NEW recipients to add. If the queue already has recipients,
            // that's fine — return success so the app can proceed to send
            // (don't 400). Only fail when the queue is genuinely empty AND
            // nothing was provided.
            if (empty($toAdd)) {
                $current = (int) $b->contacts()->count();
                if ($current > 0) {
                    return response()->json([
                        'success'         => true,
                        'message'         => empty($contactIds) ? 'Queue already has its recipients.' : 'All provided recipients are already in the queue.',
                        'queue_id'        => $b->id,
                        'added'           => 0,
                        'total_recipient' => $current,
                    ], 200);
                }
                return response()->json(['success' => false, 'message' => 'No recipients to add and the queue is empty.'], 400);
            }

            foreach ($toAdd as $cid) {
                $b->contacts()->attach($cid, [
                    'status'     => 'pending',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
            $b->update(['total_recipients' => $b->contacts()->count()]);

            return response()->json([
                'success'         => true,
                'message'         => 'Recipients added to queue.',
                'queue_id'        => $b->id,
                'added'           => count($toAdd),
                'total_recipient' => $b->total_recipients,
            ], 200);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['success' => false, 'message' => 'Validation failed.', 'data' => ['errors' => $e->errors()]], 422);
        } catch (\Throwable $e) {
            Log::error('WaDesk app sendToExistingQueue failed: ' . $e->getMessage(), ['user_id' => $request->user()?->id]);

            return response()->json(['success' => false, 'message' => 'Failed to add to queue.', 'data' => ['error' => $e->getMessage()]], 500);
        }
    }

    /** POST /update-queue-name — rename a queue (Broadcast.name, encrypted). */
    public function updateQueueName(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'queue_id' => 'required|integer',
                'name'     => 'required|string|max:255',
            ]);

            $b = Broadcast::query()->forCurrentWorkspace()->find((int) $validated['queue_id']);
            if (!$b) {
                return response()->json(['success' => false, 'message' => 'Queue not found.'], 404);
            }

            $b->update(['name' => $validated['name']]);

            return response()->json([
                'success'  => true,
                'message'  => 'Queue name updated.',
                'queue_id' => $b->id,
                'name'     => $validated['name'],
            ], 200);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['success' => false, 'message' => 'Validation failed.', 'data' => ['errors' => $e->errors()]], 422);
        } catch (\Throwable $e) {
            Log::error('WaDesk app updateQueueName failed: ' . $e->getMessage(), ['user_id' => $request->user()?->id]);

            return response()->json(['success' => false, 'message' => 'Failed to update queue name.', 'data' => ['error' => $e->getMessage()]], 500);
        }
    }

    /**
     * POST /archive-queue — archive / unarchive a queue.
     *
     * Body: { queue_id: int, archive?: bool }
     *   - `archive` omitted → toggles
     *   - `archive: true`   → force archived
     *   - `archive: false`  → force un-archived
     *
     * Persisted on broadcasts.archived (migration
     * 2026_06_27_120000_add_archived_to_broadcasts).
     */
    public function archiveQueue(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'queue_id' => 'required|integer',
            'archive'  => 'sometimes|boolean',
        ]);
        $b = Broadcast::query()->forCurrentWorkspace()->find((int) $validated['queue_id']);
        if (!$b) {
            return response()->json(['success' => false, 'message' => 'Queue not found.'], 404);
        }

        $next = array_key_exists('archive', $validated)
            ? (bool) $validated['archive']
            : ! ((bool) $b->archived);
        $b->update(['archived' => $next]);

        return response()->json([
            'success'        => true,
            'message'        => $next ? 'Queue archived.' : 'Queue unarchived.',
            'queue_id'       => $b->id,
            'archive_status' => $next ? 1 : 0,
        ], 200);
    }

    /**
     * GET /all-archive-queue — list archived queues for this workspace.
     *
     * Same row shape as the main queues feed, just filtered to archived=true.
     */
    public function all_archive_queue(Request $request): JsonResponse
    {
        $deviceId = $this->deviceIdFromRequest($request);
        $rows = Broadcast::query()
            ->forCurrentWorkspace()
            ->when($deviceId, fn ($q) => $q->where('device_id', $deviceId))
            ->where('archived', true)
            ->orderByDesc('id')
            ->limit(500)
            ->get();

        return response()->json([
            'success' => true,
            'data'    => $rows->map(fn (Broadcast $b) => [
                'id'             => $b->id,
                'name'           => (string) ($b->name ?? ''),
                'device_id'      => $b->device_id,
                'pinned'         => (bool) $b->pinned,
                'archived'       => (bool) $b->archived,
                'scheduled_at'   => $b->scheduled_at?->toIso8601String(),
                'completed_at'   => $b->completed_at?->toIso8601String(),
                'total_recipients'=> (int) ($b->total_recipients ?? 0),
                'success_count'  => (int) ($b->success_count ?? 0),
                'fail_count'     => (int) ($b->fail_count ?? 0),
                'created_at'     => $b->created_at?->toIso8601String(),
                'updated_at'     => $b->updated_at?->toIso8601String(),
            ])->values(),
            'total' => $rows->count(),
        ], 200);
    }

    /**
     * POST /queue/toggle-pin — pin/unpin a queue. Flips the broadcast's
     * `pinned` flag. Workspace-scoped.
     * Shape: { success, message, queue_id, pin_status }
     */
    public function togglePinQueue(Request $request): JsonResponse
    {
        $validated = $request->validate(['queue_id' => 'required|integer']);
        $b = Broadcast::query()->forCurrentWorkspace()->find((int) $validated['queue_id']);
        if (!$b) {
            return response()->json(['success' => false, 'message' => 'Queue not found.'], 404);
        }

        $b->pinned = ! (bool) $b->pinned;
        $b->save();

        return response()->json([
            'success'    => true,
            'message'    => $b->pinned ? 'Queue pinned.' : 'Queue unpinned.',
            'queue_id'   => $b->id,
            'pin_status' => (int) $b->pinned,
        ], 200);
    }

    /** GET /queues/pinned — list the workspace's pinned queues. */
    public function getPinnedQueues(Request $request): JsonResponse
    {
        try {
            $queues = Broadcast::query()
                ->forCurrentWorkspace()
                ->where('pinned', true)
                ->orderByDesc('id')
                ->get()
                ->map(fn (Broadcast $b) => $this->queuePayload($b))
                ->values();

            return response()->json([
                'success' => true,
                'data'    => $queues,
                'total'   => $queues->count(),
            ], 200);
        } catch (\Throwable $e) {
            Log::error('WaDesk app getPinnedQueues failed: ' . $e->getMessage(), [
                'user_id' => $request->user()?->id,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch pinned queues.',
                'data'    => ['error' => $e->getMessage()],
            ], 500);
        }
    }

    /**
     * GET /message-status/{queueId} — per-status counts for a queue.
     *
     * Mirrors the old messageStatus shape. Reads the Broadcast's cached
     * aggregate columns (kept in sync by the Node webhook), falling back to
     * a live count over broadcast_contacts. Cascades read→delivered→sent.
     */
    public function messageStatus(Request $request, int $queueId): JsonResponse
    {
        try {
            $b = Broadcast::query()->forCurrentWorkspace()->find($queueId);
            if (!$b) {
                return response()->json(['success' => false, 'message' => 'Queue not found.'], 404);
            }

            // status_counts accessor already cascades + falls back to a live
            // count for pre-aggregate broadcasts.
            $counts = $b->status_counts;

            return response()->json([
                'success'  => true,
                'queue_id' => $b->id,
                'status'   => $b->status,
                'stats'    => [
                    'total'      => (int) $b->total_recipients,
                    'sent'       => (int) ($counts['sent']       ?? 0),
                    'delivered'  => (int) ($counts['delivered']  ?? 0),
                    'read'       => (int) ($counts['read']       ?? 0),
                    'failed'     => (int) ($counts['failed']     ?? 0),
                    'clicked'    => (int) ($counts['clicked']    ?? 0),
                    'pending'    => (int) ($counts['pending']    ?? 0),
                    'processing' => (int) ($counts['processing'] ?? 0),
                ],
            ], 200);
        } catch (\Throwable $e) {
            Log::error('WaDesk app messageStatus failed: ' . $e->getMessage(), [
                'user_id'  => $request->user()?->id,
                'queue_id' => $queueId,
            ]);

            return response()->json(['success' => false, 'message' => 'Failed to fetch message status.', 'data' => ['error' => $e->getMessage()]], 500);
        }
    }

    /**
     * GET /get-contact-csv — export a queue's recipients as CSV.
     *
     * Old getContactCsv streamed a CSV of the queue's recipients. We build
     * the same from the Broadcast's contacts (decrypted name + phone).
     * Accepts ?queue_id=. When called WITHOUT a queue_id the app uses this
     * route as a "download import template" button — return a blank CSV
     * with header + two example rows so the operator can fill it in and
     * upload via /create-queue.
     */
    public function getContactCsv(Request $request)
    {
        try {
            $queueId = (int) $request->query('queue_id', 0);

            // TEMPLATE MODE — no queue_id → blank importable CSV. The app's
            // "Download template" button on the audience screen calls this
            // endpoint without a queue_id; previously it 404'd.
            if ($queueId <= 0) {
                $handle = fopen('php://temp', 'r+');
                fputcsv($handle, ['name', 'phone']);
                fputcsv($handle, ['Jane Doe', '919876543210']);
                fputcsv($handle, ['John Smith', '919876500001']);
                rewind($handle);
                $csv = stream_get_contents($handle);
                fclose($handle);

                return response($csv, 200, [
                    'Content-Type'        => 'text/csv',
                    'Content-Disposition' => 'attachment; filename="contacts_import_template.csv"',
                ]);
            }

            $b = Broadcast::query()->forCurrentWorkspace()->find($queueId);
            if (!$b) {
                return response()->json(['success' => false, 'message' => 'Queue not found.'], 404);
            }

            $pivot = DB::table('broadcast_contacts')->where('broadcast_id', $b->id)->get(['contact_id', 'status']);
            $contactMap = Contact::query()
                ->whereIn('id', $pivot->pluck('contact_id')->unique())
                ->get(['id', 'name', 'first_name', 'last_name', 'country_code', 'mobile'])
                ->keyBy('id');

            $rows = [];
            $rows[] = ['contact_id', 'name', 'phone', 'status'];
            foreach ($pivot as $p) {
                $c     = $contactMap->get($p->contact_id);
                $phone = $c ? preg_replace('/\D+/', '', (string) ($c->country_code . $c->mobile)) : '';
                $name  = $c
                    ? (trim((string) ($c->name ?? '')) ?: trim(($c->first_name ?? '') . ' ' . ($c->last_name ?? '')) ?: $phone)
                    : ('Contact #' . $p->contact_id);
                $rows[] = [$p->contact_id, $name, $phone, $p->status ?: 'pending'];
            }

            $handle = fopen('php://temp', 'r+');
            foreach ($rows as $row) {
                fputcsv($handle, $row);
            }
            rewind($handle);
            $csv = stream_get_contents($handle);
            fclose($handle);

            $filename = 'queue_' . $b->id . '_contacts.csv';

            return response($csv, 200, [
                'Content-Type'        => 'text/csv',
                'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            ]);
        } catch (\Throwable $e) {
            Log::error('WaDesk app getContactCsv failed: ' . $e->getMessage(), [
                'user_id' => $request->user()?->id,
            ]);

            return response()->json(['success' => false, 'message' => 'Failed to export contacts.', 'data' => ['error' => $e->getMessage()]], 500);
        }
    }

    /**
     * POST /schedule-message — schedule a send for later.
     *
     * NEW endpoint (no old contract). Persists a ScheduledMessage row — our
     * future-send model — which the Node scheduler reads via
     * /api/scheduled/active and fires at next_run_at. Recipients are stored
     * as raw phone numbers (encrypted target_numbers) so this works without
     * pre-saved contacts.
     *
     * Request shape:
     *   device_id   (int, optional)   — sending device
     *   name        (string)          — schedule label
     *   message     (string|nullable) — body text (text sends)
     *   template_id (int, optional)   — WaTemplate id (template sends)
     *   recipients  (array<string>)   — phone numbers
     *   send_at     (string)          — ISO/parseable datetime in `timezone`
     *   timezone    (string, optional)
     */
    public function scheduleMessage(Request $request): JsonResponse
    {
        try {
            // The app may post the body under temp_caption / message_text
            // (old key names). Fold them into `message` so a custom send
            // doesn't fail with "the message field is required".
            if (!$request->filled('message')) {
                $alt = $request->input('temp_caption', $request->input('message_text'));
                if ($alt !== null && $alt !== '') {
                    $request->merge(['message' => $alt]);
                }
            }

            $validated = $request->validate([
                'device_id'    => 'nullable|integer',
                'name'         => 'required|string|max:255',
                'message'      => 'required_without:template_id|nullable|string|max:4096',
                'template_id'  => 'nullable|integer',
                'recipients'   => 'required|array|min:1',
                'recipients.*' => 'required|string|max:32',
                'send_at'      => 'required|string',
                'timezone'     => 'nullable|string|max:64',
            ]);

            $user = $request->user();
            $wsId = (int) ($user->current_workspace_id ?? 0);

            $tz = $validated['timezone']
                ?? optional($user->currentWorkspace)->timezone
                ?? $user->timezone
                ?? config('app.timezone', 'UTC');

            try {
                $when = Carbon::parse($validated['send_at'], $tz);
            } catch (\Throwable $e) {
                return response()->json(['success' => false, 'message' => 'Could not parse send_at.'], 422);
            }
            if ($when->lt(now()->addMinute())) {
                return response()->json([
                    'success' => false,
                    'message' => 'send_at must be at least 1 minute in the future (in ' . $tz . ').',
                ], 422);
            }
            $whenUtc = $when->copy()->setTimezone('UTC');

            // Resolve sender device + its phone digits (workspace-scoped):
            // explicit id → else the workspace's connected device, so device_id
            // is never null.
            $fromNumber = null;
            $deviceId   = $this->resolveQueueDeviceId(
                isset($validated['device_id']) ? (int) $validated['device_id'] : null,
                (int) $wsId
            );
            if ($deviceId) {
                $device     = Device::find($deviceId);
                $fromNumber = $device
                    ? (preg_replace('/\D+/', '', (string) ($device->country_code . $device->phone_number)) ?: null)
                    : null;
            }

            $numbers = collect($validated['recipients'])
                ->map(fn ($n) => preg_replace('/\D+/', '', (string) $n))
                ->filter()
                ->unique()
                ->values()
                ->all();
            if (empty($numbers)) {
                return response()->json(['success' => false, 'message' => 'No valid recipient numbers.'], 422);
            }

            $templateType = !empty($validated['template_id']) ? 'template' : 'text';

            $sched = ScheduledMessage::create([
                'user_id'          => $user->id,
                'workspace_id'     => $wsId,
                // A resolved Baileys $deviceId must send via Baileys, not the
                // workspace DEFAULT engine — Node routes by `provider`, so without
                // this a Baileys-device schedule fires on Twilio/WABA. Same class
                // as the QuickMessageController fix.
                'provider'         => $deviceId
                    ? \App\Services\WorkspaceEngine::ENGINE_BAILEYS
                    : \App\Services\WorkspaceEngine::for($wsId ?: null),
                'device_id'        => $deviceId,
                'schedule_name'    => $validated['name'],
                'message_content'  => $validated['message'] ?? null,
                'template_id'      => $validated['template_id'] ?? null,
                'template_type'    => $templateType,
                'schedule_type'    => 'once',
                'send_date'        => $when->toDateString(),
                'send_time'        => $when->format('H:i'),
                'scheduled_time'   => $whenUtc,
                'timezone'         => $tz,
                'recipient_type'   => 'number',
                'target_numbers'   => $numbers,
                'total_recipients' => count($numbers),
                'from_number'      => $fromNumber,
                'status'           => 'scheduled',
                'next_run_at'      => $whenUtc,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Message scheduled successfully.',
                'data'    => [
                    'schedule_id'     => $sched->id,
                    'scheduled_time'  => $whenUtc->toDateTimeString(),
                    'timezone'        => $tz,
                    'total_recipient' => count($numbers),
                    'status'          => $sched->status,
                ],
            ], 200);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['success' => false, 'message' => 'Validation failed.', 'data' => ['errors' => $e->errors()]], 422);
        } catch (\Throwable $e) {
            Log::error('WaDesk app scheduleMessage failed: ' . $e->getMessage(), [
                'user_id' => $request->user()?->id,
            ]);

            return response()->json(['success' => false, 'message' => 'Failed to schedule message.', 'data' => ['error' => $e->getMessage()]], 500);
        }
    }

    // -----------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------

    /**
     * Resolve the sending device id: an explicit id (validated to the current
     * workspace) → else the workspace's first connected device. Returns null
     * only when the workspace has NO connected device. This keeps `device_id`
     * populated on create + in responses — it used to come back null whenever
     * the app didn't pass an explicit device.
     */
    private function resolveQueueDeviceId(?int $deviceId, ?int $wsId): ?int
    {
        if ($deviceId) {
            $d = Device::query()->forCurrentWorkspace()->find($deviceId);
            if ($d) {
                return $d->id;
            }
        }

        // The workspace's connected device. Use the SAME scope as /get-devices
        // (forCurrentWorkspace also includes devices paired before workspace
        // assignment, which carry workspace_id = NULL) so a genuinely connected
        // device is always found even when its workspace_id wasn't backfilled.
        return Device::query()
            ->forCurrentWorkspace()
            ->where('status', 'connected')
            ->orderByDesc('id')
            ->value('id');
    }

    /**
     * Parse an OPTIONAL schedule time from a start-sending request. Accepts
     * `send_at` / `scheduled_time` (full datetime) or `schedule_date` +
     * `schedule_time`. Returns [Carbon-UTC|null, tz]. Null = send now.
     */
    /**
     * Returns [whenUtc | null, tz, error | null].
     *
     *   - No schedule fields submitted          → [null, tz, null]      (caller fires immediate)
     *   - Schedule fields submitted, valid      → [Carbon, tz, null]    (caller schedules)
     *   - Schedule fields submitted, INVALID    → [null, tz, "<reason>"] (caller MUST 422 — don't silently send immediate)
     *
     * The third return slot is the fix for the "instant + scheduled
     * fires twice" bug: previously the second branch (invalid input)
     * fell through to immediate, so a queue that was already scheduled
     * on Node from a prior call would fire both that schedule AND a
     * new instant send. Now the caller sees the error and aborts.
     */
    private function parseScheduleAt(Request $request): array
    {
        $user = $request->user();
        $tz = (string) ($request->input('timezone')
            ?: optional($user?->currentWorkspace)->timezone
            ?: $user?->timezone
            ?: config('app.timezone', 'UTC'));

        $hadAny = $request->filled('send_at')
            || $request->filled('scheduled_time')
            || $request->filled('schedule_date')
            || $request->filled('schedule_time');

        $raw = $request->input('send_at', $request->input('scheduled_time'));
        if (!$raw) {
            $d = trim((string) $request->input('schedule_date', ''));
            $t = trim((string) $request->input('schedule_time', ''));
            if ($d !== '') $raw = $t !== '' ? ($d . ' ' . $t) : $d;
        }
        if (!$raw) {
            // No schedule input AT ALL → caller fires immediate.
            return [null, $tz, null];
        }

        try {
            $when = Carbon::parse((string) $raw, $tz);
        } catch (\Throwable $e) {
            return [null, $tz, 'Could not parse send_at — pass an ISO datetime or "YYYY-MM-DD HH:mm".'];
        }
        // Only a genuinely future time counts as a schedule. Anything
        // within the next minute, OR in the past, would race the cron
        // and cause weird re-fire — reject so the caller knows.
        if ($when->lte(now()->addMinute())) {
            return [null, $tz, 'send_at must be at least 1 minute in the future (in ' . $tz . ').'];
        }

        return [$when->copy()->setTimezone('UTC'), $tz, null];
    }

    /**
     * Convert a queue (Broadcast) into a one-off ScheduledMessage the Node
     * scheduler fires at `whenUtc`. Recipients come from the queue's attached
     * contacts (as raw numbers); content from its template / custom body.
     * NOTE: custom media/buttons aren't carried into ScheduledMessage — a
     * scheduled TEMPLATE queue keeps full rendering (via template_id); a
     * scheduled custom queue sends its text body.
     */
    private function scheduleBroadcastRow(Broadcast $b, Carbon $whenUtc, string $tz, $user): ?ScheduledMessage
    {
        $numbers = $b->contacts()->get(['contacts.country_code', 'contacts.mobile'])
            ->map(function ($c) {
                $cc    = preg_replace('/\D+/', '', (string) ($c->country_code ?? ''));
                $local = preg_replace('/\D+/', '', (string) ($c->mobile ?? ''));
                return $cc && $local && strpos($local, $cc) !== 0 ? $cc . $local : $local;
            })->filter()->unique()->values()->all();
        if (empty($numbers)) return null;

        $fromNumber = null;
        if ($b->device_id) {
            $device     = Device::find($b->device_id);
            $fromNumber = $device ? (preg_replace('/\D+/', '', (string) ($device->country_code . $device->phone_number)) ?: null) : null;
        }

        return ScheduledMessage::create([
            'user_id'          => $b->user_id ?: $user?->id,
            'workspace_id'     => $b->workspace_id,
            'provider'         => $b->provider ?: \App\Services\WorkspaceEngine::for($b->workspace_id ? (int) $b->workspace_id : null),
            'device_id'        => $b->device_id,
            'schedule_name'    => (string) ($b->name ?: ('Queue #' . $b->id)),
            'message_content'  => (string) ($b->temp_caption ?? ''),
            'template_id'      => $b->template_id,
            'template_type'    => $b->template_id ? 'template' : 'text',
            'schedule_type'    => 'once',
            'send_date'        => $whenUtc->copy()->setTimezone($tz)->toDateString(),
            'send_time'        => $whenUtc->copy()->setTimezone($tz)->format('H:i'),
            'scheduled_time'   => $whenUtc,
            'timezone'         => $tz,
            'recipient_type'   => 'number',
            'target_numbers'   => $numbers,
            'total_recipients' => count($numbers),
            'from_number'      => $fromNumber,
            'status'           => 'scheduled',
            'next_run_at'      => $whenUtc,
        ]);
    }

    /** Shape one Broadcast into the app's queue payload. */
    private function queuePayload(Broadcast $b): array
    {
        return [
            'queue_id'         => $b->id,
            'name'             => (string) ($b->name ?? ''),
            'status'           => $b->status,
            'pinned'           => (bool) $b->pinned,
            'pin_status'       => (int) (bool) $b->pinned,
            'device_id'        => $b->device_id ?: $this->resolveQueueDeviceId(null, $b->workspace_id ? (int) $b->workspace_id : null),
            'template_id'      => $b->template_id,
            // Custom-message content so the app can render the queue.
            'message'          => (string) ($b->temp_caption ?? ''),
            'temp_caption'     => (string) ($b->temp_caption ?? ''),
            'template_type'    => $b->template_type,
            'temp_image'       => $b->temp_image ? (\Illuminate\Support\Str::startsWith($b->temp_image, ['http://', 'https://']) ? $b->temp_image : media_url($b->temp_image)) : null,
            'button_text'      => $b->button_text ? (json_decode((string) $b->button_text, true) ?: $b->button_text) : null,
            'latitude'         => $b->latitude,
            'longitude'        => $b->longitude,
            // Linked template content so a template queue (incl. document /
            // image / video header types) renders in the app — previously only
            // the template_id was returned, so doc-type templates showed blank.
            'template'         => $this->queueTemplatePayload($b),
            'total_recipient'  => (int) $b->total_recipients,
            'success_count'    => (int) $b->success_count,
            'fail_count'       => (int) $b->fail_count,
            'scheduled_at'     => optional($b->scheduled_at)->toDateTimeString(),
            'completed_at'     => optional($b->completed_at)->toDateTimeString(),
            'created_at'       => optional($b->created_at)->toDateTimeString(),
        ];
    }

    /**
     * The linked WaTemplate's renderable content for a template queue. Returns
     * null for custom-message queues. Normalises the attachment so the app can
     * tell image / video / DOCUMENT apart and show the file (doc-type templates
     * were coming back blank because only template_id was exposed).
     */
    private function queueTemplatePayload(Broadcast $b): ?array
    {
        if (empty($b->template_id)) return null;
        $t = \App\Models\WaTemplate::find($b->template_id);
        if (!$t) return null;

        $file = $t->attachment_file ?? null;
        $url  = $file
            ? (\Illuminate\Support\Str::startsWith($file, ['http://', 'https://']) ? $file : media_url($file))
            : null;

        // Derive a concrete media kind so a 'document' header isn't mistaken
        // for an image. Prefer the stored attachment_type, else infer by ext.
        $type = $t->attachment_type ?: null;
        if (!$type && $file) {
            $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
            $type = in_array($ext, ['mp4', 'mov', 'webm', '3gp'], true) ? 'video'
                : (in_array($ext, ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'csv', 'txt'], true) ? 'document'
                : (in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'], true) ? 'image' : null));
        }

        return [
            'id'              => $t->id,
            'template_name'   => $t->template_name,
            'header'          => $t->header ?? null,
            'template_body'   => $t->template_body,
            'footer'          => $t->footer ?? null,
            'attachment_type' => $type,                 // image | video | document | null
            'attachment_file' => $url,
            'attachment_name' => $file ? basename($file) : null,
            'buttons'         => is_string($t->buttons ?? null) ? (json_decode($t->buttons, true) ?: null) : ($t->buttons ?? null),
            'carousel_data'   => is_string($t->carousel_data ?? null) ? (json_decode($t->carousel_data, true) ?: null) : ($t->carousel_data ?? null),
        ];
    }

    /** Parse queue id(s) from `queue_id` (single) or `queue_ids` (CSV/array/JSON). */
    private function queueIdsFromRequest(Request $request): array
    {
        if ($request->filled('queue_id')) {
            return [(int) $request->input('queue_id')];
        }
        $raw = $request->input('queue_ids');
        if (is_array($raw)) {
            return array_values(array_filter(array_map('intval', $raw)));
        }
        if (is_string($raw) && $raw !== '') {
            $decoded = json_decode($raw, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                return array_values(array_filter(array_map('intval', $decoded)));
            }
            return array_values(array_filter(array_map(fn ($v) => (int) trim($v), explode(',', $raw))));
        }
        return [];
    }

    /**
     * Resolve recipients into contact ids (the unit broadcast_contacts keys
     * on). Sources, all workspace-scoped:
     *   contacts[]        — contact ids (validated against the workspace)
     *   groups[]          — pull members via the encrypted contact_group JSON
     *   contact_numbers   — raw phones matched against saved contacts
     * Drops opted-out contacts, mirroring BroadcastsController@store.
     */
    private function resolveRecipientContactIds(Request $request, int $wsId, bool $autoCreate = false): array
    {
        $ids = collect();

        // Direct contact ids — intersect with the workspace.
        $rawContacts = $request->input('contacts', []);
        if (is_array($rawContacts) && !empty($rawContacts)) {
            $valid = Contact::query()->forCurrentWorkspace()
                ->whereIn('id', array_map('intval', $rawContacts))
                ->pluck('id');
            $ids = $ids->merge($valid);
        }

        // Group ids — the contact_group column is encrypted-array-cast, so
        // hydrate and filter in PHP (same as BroadcastsController@store).
        $rawGroups = collect($request->input('groups', []))->map(fn ($v) => (string) (int) $v)->filter()->values();
        if ($rawGroups->isNotEmpty()) {
            $members = Contact::query()->forCurrentWorkspace()->get(['id', 'contact_group'])
                ->filter(function (Contact $c) use ($rawGroups) {
                    $list = is_array($c->contact_group) ? $c->contact_group : [];
                    foreach ($list as $gid) {
                        if ($rawGroups->contains((string) $gid)) {
                            return true;
                        }
                    }
                    return false;
                })
                ->pluck('id');
            $ids = $ids->merge($members);
        }

        // Raw phone numbers — match against the workspace's saved contacts
        // (broadcast_contacts is contact-id keyed, so numbers without a saved
        // contact can't be attached here; those should use /schedule-message
        // or /send-quick-message which accept raw numbers).
        $rawNumbers = $request->input('contact_numbers');
        $numbers = [];
        if (is_array($rawNumbers)) {
            $numbers = $rawNumbers;
        } elseif (is_string($rawNumbers) && $rawNumbers !== '') {
            $decoded = json_decode($rawNumbers, true);
            $numbers = (json_last_error() === JSON_ERROR_NONE && is_array($decoded))
                ? $decoded
                : explode(',', $rawNumbers);
        }
        $numbers = collect($numbers)->map(fn ($n) => preg_replace('/\D+/', '', (string) $n))->filter()->unique();
        if ($numbers->isNotEmpty()) {
            $wsContacts = Contact::query()->forCurrentWorkspace()->get(['id', 'user_id', 'country_code', 'mobile']);
            // Track which input numbers actually matched a saved contact, so we
            // can auto-create the rest (broadcast_contacts is contact-id keyed —
            // a raw number with no saved contact can't be attached otherwise,
            // which is what produced "No new recipients resolved").
            $matchedNumbers = collect();
            $matched = $wsContacts->filter(function (Contact $c) use ($numbers, $matchedNumbers) {
                $digits = preg_replace('/\D+/', '', (string) ($c->country_code . $c->mobile));
                $bare   = preg_replace('/\D+/', '', (string) $c->mobile);
                if ($numbers->contains($digits)) { $matchedNumbers->push($digits); return true; }
                if ($numbers->contains($bare))   { $matchedNumbers->push($bare);   return true; }
                return false;
            })->pluck('id');
            $ids = $ids->merge($matched);

            // Auto-create lightweight contacts for numbers that aren't saved yet
            // (queues/bulk to ad-hoc numbers, like the old API). Events are
            // suppressed so a bulk send never fires contact_created flows.
            if ($autoCreate) {
                $unmatched = $numbers->reject(fn ($n) => $matchedNumbers->contains($n))->unique()->values();
                if ($unmatched->isNotEmpty()) {
                    $ownerId = (int) ($request->user()?->id ?? 0) ?: null;
                    Contact::withoutEvents(function () use ($unmatched, $wsId, $ownerId, &$ids) {
                        foreach ($unmatched as $num) {
                            $c = Contact::create([
                                'user_id'      => $ownerId,
                                'workspace_id' => $wsId,
                                'name'         => $num,
                                'mobile'       => $num,
                                'msg'          => 'Added from app message queue.',
                            ]);
                            $ids = $ids->push($c->id);
                        }
                    });
                }
            }
        }

        $ids = $ids->unique()->values();

        // Drop opted-out contacts (STOP / manual unsubscribe).
        if ($ids->isNotEmpty()) {
            $optedOut = Contact::query()->whereIn('id', $ids)->where('is_unsubscribed', true)->pluck('id');
            if ($optedOut->isNotEmpty()) {
                $ids = $ids->reject(fn ($id) => $optedOut->contains((int) $id))->values();
            }
        }

        return $ids->map(fn ($v) => (int) $v)->all();
    }

    /**
     * POST a broadcast's recipients to the Node bridge for an immediate send.
     * Self-contained mirror of BroadcastsController@dispatchToBridge's HTTP
     * contract (we can't call that private method from here, and editing it
     * is out of scope). Resolves the sender device → /api/broadcast/
     * send-immediate/{phone}. Returns a small result array; leaves the
     * Broadcast in `processing` on success or flips it to `failed` on a hard
     * error, matching the web path's behaviour.
     */
    private function dispatchBroadcastToBridge(Broadcast $b, bool $immediate): array
    {
        $base = function_exists('wd_node_url') ? wd_node_url() : '';
        if ($base === '') {
            // No bridge configured — the row stays in `processing` and the
            // Node scheduler / webhooks will reconcile if a bridge appears.
            Log::warning('[QUEUE-APP] dispatch skipped — Node bridge URL not set', ['queue_id' => $b->id]);
            return ['ok' => false, 'error' => 'Node bridge URL (SERVER_URL) is not configured.', 'local_only' => true];
        }

        // Resolve the sender device: broadcast.device_id → first connected
        // device in the workspace.
        $sender = null;
        if ($b->device_id) {
            $sender = Device::find($b->device_id);
        }
        if (!$sender) {
            $sender = Device::query()
                ->where('status', 'connected')
                ->where(function ($q) use ($b) {
                    if ($b->workspace_id) {
                        $q->where('workspace_id', $b->workspace_id);
                    }
                    // Devices paired before workspace assignment carry a NULL
                    // workspace_id but are owned by the broadcast's user.
                    $q->orWhere(function ($qq) use ($b) {
                        $qq->whereNull('workspace_id')->where('user_id', $b->user_id);
                    });
                })
                ->orderByDesc('id')
                ->first();
        }
        if (!$sender) {
            Log::warning('[QUEUE-APP] dispatch aborted — no sender device', ['queue_id' => $b->id]);
            $b->update(['status' => 'failed']);
            return ['ok' => false, 'error' => 'No connected device for this workspace. Pair one at /devices first.'];
        }

        $phoneDigits = preg_replace('/\D+/', '', (string) ($sender->country_code . $sender->phone_number));
        if ($phoneDigits === '') {
            $b->update(['status' => 'failed']);
            return ['ok' => false, 'error' => 'Sender device has no usable phone number.'];
        }

        // Build the recipient rows the Node broadcastService expects.
        $contactRows = $b->contacts()
            ->get(['contacts.id', 'contacts.country_code', 'contacts.mobile', 'contacts.name'])
            ->map(function ($c) {
                $cc    = preg_replace('/\D+/', '', (string) ($c->country_code ?? ''));
                $local = preg_replace('/\D+/', '', (string) ($c->mobile ?? ''));
                $phone = $cc && $local && strpos($local, $cc) !== 0 ? $cc . $local : $local;
                return ['id' => $c->id, 'phone' => $phone, 'name' => (string) ($c->name ?? '')];
            })
            ->filter(fn ($c) => $c['phone'] !== '')
            ->values()
            ->all();

        if (empty($contactRows)) {
            $b->update(['status' => 'failed']);
            return ['ok' => false, 'error' => 'No recipients with a usable phone number.'];
        }

        // CANCEL PREVIOUS — if this queue was already scheduled or fired
        // through Node (a repeated /start-sending call, retry, double-tap on
        // the Flutter "Schedule" button, etc.), cancel EVERY pending Node
        // job for this broadcast FIRST. Without this guard Node ends up with
        // TWO entries for the same broadcast: the prior cron + the new one
        // — fires both, looks like an "instant send AND scheduled send" to
        // the operator. Even worse symptom: with rapid re-schedules across
        // multiple clicks, Node can hold THREE+ pending jobs (early fire,
        // mid fire, scheduled fire) — that's the "send three times" report.
        //
        // We cancel BOTH by stored node_schedule_id (PHP-tracked) AND by
        // broadcast_id (Node-tracked) so stale schedules PHP forgot about
        // (e.g. a prior dispatch that failed mid-flight without writing
        // node_schedule_id back) also get killed.
        $cancelBaseUrl = rtrim($base, '/');
        if ($b->node_schedule_id) {
            try {
                Http::timeout(5)->withHeaders(['X-Node-Token' => node_token()])
                    ->delete($cancelBaseUrl . '/api/broadcast/cancel/' . rawurlencode((string) $b->node_schedule_id));
                Log::info('[QUEUE-APP] cancelled prior Node schedule (by schedule id)', [
                    'queue_id'         => $b->id,
                    'node_schedule_id' => $b->node_schedule_id,
                ]);
            } catch (\Throwable $e) {
                Log::warning('[QUEUE-APP] prior Node cancel by-id failed (continuing)', [
                    'queue_id' => $b->id, 'error' => $e->getMessage(),
                ]);
            }
        }
        // Always also cancel by broadcast id — kills ANY orphaned schedule
        // PHP doesn't know about (mid-flight failure, race, etc.).
        try {
            Http::timeout(5)->withHeaders(['X-Node-Token' => node_token()])
                ->delete($cancelBaseUrl . '/api/broadcast/cancel-by-broadcast/' . (int) $b->id);
            Log::info('[QUEUE-APP] cancelled prior Node schedule (by broadcast id)', ['queue_id' => $b->id]);
        } catch (\Throwable $e) {
            Log::warning('[QUEUE-APP] cancel-by-broadcast failed (continuing)', [
                'queue_id' => $b->id, 'error' => $e->getMessage(),
            ]);
        }
        // Null the stale id locally so the new dispatch writes its own
        // scheduleId fresh on response.
        if ($b->node_schedule_id) {
            $b->forceFill(['node_schedule_id' => null])->save();
        }

        // Route on $immediate — IMMEDIATE → /api/broadcast/send-immediate (Node
        // sends straight away); !IMMEDIATE → /api/broadcast/schedule (Node
        // holds the schedule until scheduleDateTime). Previously this method
        // hardcoded send-immediate even for scheduled queues, so a scheduled
        // request from the app went out instantly. This matches the web's
        // BroadcastsController::dispatchToBridge.
        $endpoint = rtrim($base, '/')
            . ($immediate ? '/api/broadcast/send-immediate/' : '/api/broadcast/schedule/')
            . rawurlencode($phoneDigits);
        $tpl = $b->template_id ? \App\Models\WaTemplate::find($b->template_id) : null;

        // Build the FULL templateData blob Node renders (text + image/caption +
        // buttons + carousel). For a saved template we use the SAME canonical
        // builder broadcasts/campaigns use, so every template type
        // (standard/media/carousel/auth) sends correctly. For a custom message
        // we shape the stored content into the same blob so Node's
        // sendTemplateMessage renders it identically. isTemplate is always true
        // so the rich renderer (not the plain-text branch) is used.
        if ($tpl) {
            $templateData = \App\Services\Whatsapp\TemplateDataBuilder::build($tpl, (int) $b->workspace_id);
        } else {
            $templateData = $this->buildCustomTemplateData($b);
        }
        $messageText = (string) ($templateData['template_body'] ?? '');

        $payload = [
            'broadcastId'    => $b->id,
            'targetContacts' => $contactRows,
            'isTemplate'     => true,
            'templateData'   => $templateData,
            'message'        => $messageText,
        ];
        if (!$immediate) {
            // ISO-8601 with offset so Node's moment.tz() converts UTC →
            // user-local correctly — a naive datetime gets treated AS IF
            // it's already user-local, shifting every send by the UTC
            // offset (e.g. 17:15 IST would fire at 11:45 IST). Same trick
            // the web BroadcastsController uses.
            $payload['scheduleDateTime'] = $b->scheduled_at?->toIso8601String();
            $payload['timezone']         = $b->timezone ?: 'UTC';
        }

        try {
            $res = Http::timeout(15)->post($endpoint, $payload);
            if ($res->successful()) {
                $b->update([
                    'node_schedule_id' => $res->json('scheduleId'),
                    'status'           => $immediate ? 'processing' : 'scheduled',
                ]);
                return [
                    'ok'               => true,
                    'node_schedule_id' => $res->json('scheduleId'),
                    'recipients'       => count($contactRows),
                    'scheduled'        => !$immediate,
                ];
            }
            $b->update(['status' => 'failed']);
            return ['ok' => false, 'error' => 'Node bridge returned HTTP ' . $res->status(), 'body' => mb_substr((string) $res->body(), 0, 240)];
        } catch (\Throwable $e) {
            Log::error('[QUEUE-APP] dispatch threw', ['queue_id' => $b->id, 'error' => $e->getMessage()]);
            $b->update(['status' => 'failed']);
            return ['ok' => false, 'error' => 'Node bridge unreachable: ' . $e->getMessage()];
        }
    }

    /**
     * Shape a custom (non-template) queue's stored content (text, caption,
     * media, buttons, location) into the same `templateData` blob Node's
     * sendTemplateMessage consumes — so a custom message renders with
     * buttons + image exactly like a template does.
     */
    private function buildCustomTemplateData(Broadcast $b): array
    {
        $buttons = [];
        if (!empty($b->button_text)) {
            $decoded = is_array($b->button_text) ? $b->button_text : json_decode((string) $b->button_text, true);
            if (is_array($decoded)) {
                $buttons = $decoded;
            }
        }

        $attType = null; $attFile = null; $attUrl = null; $attB64 = null; $attMime = null;
        if (!empty($b->temp_image)) {
            $rel    = ltrim((string) $b->temp_image, '/');
            $isHttp = \Illuminate\Support\Str::startsWith($rel, ['http://', 'https://']);
            $ext    = strtolower(pathinfo($rel, PATHINFO_EXTENSION));
            $attType = in_array($ext, ['mp4', 'mov', 'webm'], true) ? 'video'
                     : (in_array($ext, ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx'], true) ? 'document' : 'image');
            $attFile = basename($rel);
            $attUrl  = $isHttp ? $rel : media_url($rel);
            if (!$isHttp) {
                // Base64-inline from the active media disk (cloud or local) so
                // Node never has to download the media itself; URL stays as fallback.
                $d = media_storage();
                try {
                    if ($d->exists($rel)) {
                        $attB64  = base64_encode((string) $d->get($rel));
                        $attMime = $d->mimeType($rel) ?: null;
                    }
                } catch (\Throwable $e) { /* leave attB64/attMime null — URL fallback */ }
            }
        }

        return [
            'id'                 => 0,
            'template_name'      => '',
            'template_type'      => $attType ? 'media' : 'standard',
            'language'           => 'en_US',
            'header'             => null,
            'title_text'         => null,
            'template_body'      => (string) ($b->temp_caption ?? ''),
            'footer'             => null,
            'buttons'            => $buttons,
            'attachment_type'    => $attType,
            'attachment_file'    => $attFile,
            'attachment_url'     => $attUrl,
            'attachment_base64'  => $attB64,
            'attachment_mime'    => $attMime,
            'carousel_data'      => null,
            'variable_map'       => [],
            'latitude'           => $b->latitude,
            'longitude'          => $b->longitude,
            'twilio_content_sid' => null,
        ];
    }

    /**
     * Resolve the device the app wants the queue list scoped to — same contract
     * as ChatController: `sender=engine:id` (multi-engine picker key) or a bare
     * `device_id`. Returns Broadcast.device_id to match, or null for all.
     */
    private function deviceIdFromRequest(Request $request): ?int
    {
        $wsId   = (int) ($request->user()?->current_workspace_id ?? 0);
        $sender = trim((string) $request->query('sender', ''));
        if ($sender !== '' && $wsId > 0) {
            $picked = \App\Services\WorkspaceEngine::senderForKey($wsId, $sender);
            if ($picked && !empty($picked['id'])) return (int) $picked['id'];
        }
        // Bare device id from ?device_id OR the app-wide X-Device-Id header.
        $did = (int) ($request->query('device_id') ?: $request->header('X-Device-Id', 0));
        return $did > 0 ? $did : null;
    }
}
