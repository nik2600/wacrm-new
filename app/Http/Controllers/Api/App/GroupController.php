<?php

namespace App\Http\Controllers\Api\App;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\Device;
use App\Models\Message;
use App\Models\SystemSetting;
use App\Services\WorkspaceEngine;
use App\Services\WhatsAppDispatcher;
use App\Enums\WaProvider;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

/**
 * Mobile-app WhatsApp GROUPS API.
 *
 * Creating a real WhatsApp group is a Baileys-only operation — the Meta
 * Cloud API has no group-create endpoint (only the existing-group send
 * path), and Twilio has no group concept at all. Every method here is
 * gated on the workspace's active engine being Baileys; the others
 * return 409 with a clear "not supported on this engine" message so
 * the app can show the right empty state.
 *
 * The actual group action runs in node/controllers/groupController.js
 * via sock.groupCreate / sock.groupParticipantsUpdate / etc. We mirror
 * the result back into a local Conversation row (raw_jid = <jid>@g.us)
 * so the same /chats endpoints can read + send messages to it without
 * branching on "is this a group conversation".
 */
class GroupController extends Controller
{
    public function __construct(private readonly WhatsAppDispatcher $dispatcher)
    {
    }

    // -----------------------------------------------------------------
    // POST /groups — create a new WhatsApp group.
    //
    // Multi-engine note: a workspace can have Baileys + WABA + Twilio all
    // enabled at once. The Unofficial API (Baileys) is the ONLY engine
    // that supports group create (Meta's Groups API is gated to 100k+ MAU
    // accounts; Twilio has no native WA group). So we resolve the SPECIFIC
    // device the operator picked (`sender=engine:id` OR `device_id`) and
    // gate on THAT device's engine — never on the workspace default,
    // which would falsely 409 a Baileys-capable workspace whose default
    // happens to be Twilio/WABA.
    // -----------------------------------------------------------------
    public function create(Request $request): JsonResponse
    {
        $data = $request->validate([
            'subject'         => 'required|string|max:60',  // WhatsApp caps group subject at 25 chars but Baileys allows up to 100; 60 is a safe app cap
            'description'     => 'nullable|string|max:512',
            'participants'    => 'required|array|min:1|max:1024',
            'participants.*'  => 'required|string|max:32',
            'device_id'       => 'nullable|integer',
            'sender'          => 'nullable|string|max:64', // composite "engine:id" picker key
        ]);

        $user = $request->user();
        $wsId = (int) ($user->current_workspace_id ?? 0);

        // Resolve the actual Baileys device the operator wants to use.
        // resolveBaileysDevice walks: sender key → device_id → first active
        // Baileys device on the workspace. Returns null if none exist.
        $device = $this->resolveBaileysDevice($request, $wsId);
        if (! $device) {
            // Distinguish "wrong engine picked" from "no Baileys device at all".
            // If sender/device_id pointed at a non-Baileys sender, that's a 409;
            // otherwise it's a 422 (workspace simply has no Baileys device).
            $pickedEngine = $this->enginePickedByRequest($request, $wsId);
            if ($pickedEngine && $pickedEngine !== WorkspaceEngine::ENGINE_BAILEYS) {
                return response()->json([
                    'success' => false,
                    'message' => 'Group create is only supported on the Unofficial API engine. Pick a Baileys device.',
                    'engine'  => $pickedEngine,
                ], 409);
            }
            return response()->json([
                'success' => false,
                'message' => 'No connected Unofficial API device on this workspace.',
            ], 422);
        }
        $devicePhone = preg_replace('/\D+/', '', (string) ($device->country_code . $device->phone_number)) ?: null;
        if (! $devicePhone) {
            return response()->json(['success' => false, 'message' => 'Device has no phone number.'], 422);
        }

        // Normalise participants to phone digits (Baileys converts to JID).
        $participants = collect($data['participants'])
            ->map(fn ($p) => preg_replace('/\D+/', '', (string) $p))
            ->filter(fn ($p) => strlen($p) >= 8)
            ->unique()
            ->values()
            ->all();
        if (empty($participants)) {
            return response()->json(['success' => false, 'message' => 'No valid participant phones supplied.'], 422);
        }

        // Hit the Node bridge — groupCreate is sock.groupCreate(subject, jids).
        $bridge = $this->callNode("/api/groups/create/{$devicePhone}", [
            'subject'      => $data['subject'],
            'participants' => $participants,
        ]);
        if (! ($bridge['ok'] ?? false) || empty($bridge['jid'])) {
            return response()->json([
                'success' => false,
                'message' => 'Group create failed at the WhatsApp bridge.',
                'error'   => $bridge['error'] ?? 'unknown',
            ], 502);
        }

        $jid = (string) $bridge['jid'];

        // Optional description — best-effort second call so a failure
        // doesn't roll back the created group.
        if (! empty($data['description'])) {
            $this->callNode("/api/groups/description/{$devicePhone}", [
                'jid'         => $jid,
                'description' => $data['description'],
            ]);
        }

        // Mirror the group into our Conversation table so /chats works
        // identically for groups and 1:1 chats (same send + history
        // endpoints). The device IS a Baileys device (we gated above), so
        // the engine here is always baileys regardless of the workspace
        // default — that's the multi-engine fix the dispatcher relies on.
        $engine = WorkspaceEngine::ENGINE_BAILEYS;
        $convo = Conversation::create([
            'user_id'          => $user->id,
            'workspace_id'     => $wsId ?: null,
            'device_id'        => $device->id,
            'title'            => $data['subject'],
            'preview'          => null,
            'status'           => 'pending',
            'platform'         => WaProvider::tryFrom($engine)?->legacyCode() ?? 'W',
            'provider'         => $engine,
            'origin'           => 'chat',
            'recipients_count' => count($participants),
            'last_message_at'  => now(),
            'raw_jid'          => $jid,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Group created.',
            'data'    => [
                'conversation_id'    => $convo->id,
                'jid'                => $jid,
                'subject'            => $data['subject'],
                'description'        => $data['description'] ?? null,
                'participants_count' => $bridge['participants_count'] ?? count($participants),
                'device_id'          => $device->id,
                'meta'               => $bridge['meta'] ?? null,
            ],
        ], 201);
    }

    // -----------------------------------------------------------------
    // GET /groups — every group the sender device is in.
    //
    // UNION of two sources:
    //   1. Live Baileys roster from `sock.groupFetchAllParticipating()`
    //      via the Node bridge — server-fresh, includes EVERY group the
    //      device is a participant of (including ones created from
    //      another linked phone). This is the authoritative source.
    //   2. Local Conversation mirror — every row this workspace owns
    //      with `raw_jid LIKE '%@g.us'` and `device_id=<picked>`. We
    //      write a row here on every successful `POST /groups`, so this
    //      table guarantees the **creator's** groups are visible
    //      immediately, even if the Node bridge is unreachable, returns
    //      an empty roster on a transient race, or hasn't propagated
    //      the just-created jid into its participating list yet (real
    //      production bug — the customer's freshly-made group vanished
    //      from the list for 5–10s).
    //
    // De-dupe by jid: bridge metadata wins when both sources have it
    // (it carries the live participants_count, ephemeral, etc.); the
    // mirror fills gaps with a minimal payload + `source: 'mirror'`
    // and `stale: true` flags so the app can show a soft "syncing…"
    // indicator on those rows.
    //
    // Bridge unreachable / 5xx → we still return 200 with the mirror
    // rows + `stale: true` at the top level. Empty result on a healthy
    // workspace is now a degraded read, NOT a hard 502.
    // -----------------------------------------------------------------
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $wsId = (int) ($user->current_workspace_id ?? 0);

        $device = $this->resolveBaileysDevice($request, $wsId);
        if (! $device) {
            return response()->json(['success' => false, 'message' => 'No connected device on this workspace.'], 422);
        }
        $devicePhone = preg_replace('/\D+/', '', (string) ($device->country_code . $device->phone_number)) ?: '';

        // 1. Live bridge roster (fail-open).
        $bridge      = $this->callNode("/api/groups/all/{$devicePhone}", null, 'GET');
        $bridgeOk    = (bool) ($bridge['ok'] ?? false);
        $bridgeRows  = is_array($bridge['groups'] ?? null) ? $bridge['groups'] : [];

        // 2. Local Conversation mirror — every @g.us row this workspace
        // owns that hasn't been left/archived. SQL WHERE is fine here:
        // raw_jid is a plain varchar(191) (not encrypted), confirmed
        // against Conversation::$casts + the migration. We MATCH BY
        // WORKSPACE ONLY (no device_id) so a re-paired device with a
        // new devices.id still finds its historical groups. Excluding
        // status='left' AND archived=true prevents a just-left group
        // from reappearing in the mirror leg after the Node bridge's
        // 30s _recentlyLeft TTL expires.
        // ALL @g.us mirrors (we look at archive flags separately below
        // so we can ALSO hide archived ones from the bridge leg).
        $allMirrors = Conversation::query()
            ->forCurrentWorkspace()
            ->where('raw_jid', 'like', '%@g.us')
            ->where('status', '!=', 'left')
            ->get();

        // Build two index sets, both keyed on the digits-only jid form
        // so casing / dashes / hidden Unicode never break the lookup:
        //   $archivedJids — every group the user archived locally;
        //                   these are HIDDEN from the active /groups feed
        //                   so the archived list (/chats/archived?kind=group)
        //                   is the ONLY surface they appear on.
        //   $mirrorRows   — active (non-archived) mirrors, used as the
        //                   fallback rows when the bridge can't see one.
        // Normalise to PURE DIGITS — every separator (dash, colon, @, etc.)
        // gets stripped. Some Baileys versions emit the same group with
        // and without the LID dash separator (`120363012345678901@g.us`
        // vs `120363012-345678901@g.us`); keeping the dash in the dedupe
        // key let them appear as two different rows. Pure digits is the
        // only safe normaliser.
        $norm = static fn ($j) => preg_replace('/\D+/', '', (string) $j);
        $archivedJids = [];
        $mirrorRows = collect();
        foreach ($allMirrors as $c) {
            $key = $norm($c->raw_jid);
            if ($key === '') continue;
            if ((bool) $c->archived) {
                $archivedJids[$key] = true;
            } else {
                $mirrorRows->push($c);
            }
        }

        // Merge. Bridge keyed by jid; mirror entries with a jid the
        // bridge already returned are dropped (bridge wins). Mirror-only
        // jids get a minimal Baileys-shaped row + source/stale flags.
        // Bridge rows whose jid matches an ARCHIVED mirror are skipped
        // outright — the bridge has no idea the user archived them
        // locally, so without this filter an archived group would
        // reappear in the main feed.
        $merged = [];
        $seen   = [];
        foreach ($bridgeRows as $row) {
            $jid = (string) ($row['jid'] ?? '');
            $key = $norm($jid);
            if ($key === '' || isset($seen[$key])) continue;
            if (isset($archivedJids[$key]))           continue;   // hidden — surfaced under /chats/archived
            $row['source'] = 'bridge';
            $row['stale']  = false;
            $merged[] = $row;
            $seen[$key] = true;
        }
        foreach ($mirrorRows as $c) {
            $jid = (string) $c->raw_jid;
            $key = $norm($jid);
            if ($key === '' || isset($seen[$key])) continue;
            $merged[] = [
                'jid'                => $jid,
                'subject'            => (string) ($c->title ?? ''),
                'owner'              => null,
                'creation'           => $c->created_at?->getTimestamp(),
                'description'        => null,
                'participants_count' => (int) ($c->recipients_count ?? 0),
                'announce_only'      => false,
                'restrict'           => false,
                'ephemeral_duration' => 0,
                // App can show a sync spinner on these rows; on a refresh
                // the bridge will likely have caught up and bridge wins.
                'source'             => 'mirror',
                'stale'              => true,
            ];
            $seen[$key] = true;
        }

        // Sort newest-first by creation timestamp so the list is stable
        // across renders. Without this, a freshly-mirrored group jumps
        // position once the bridge picks it up.
        usort($merged, fn ($a, $b) => (int) ($b['creation'] ?? 0) <=> (int) ($a['creation'] ?? 0));

        return response()->json([
            'success' => true,
            'data'    => $merged,
            'total'   => count($merged),
            // Top-level `stale: true` when the bridge call itself failed
            // and we're only returning mirror rows. Helps the app surface
            // "couldn't reach WhatsApp, showing cached list" once instead
            // of per-row.
            'stale'   => ! $bridgeOk,
            // Carry the bridge error string so the app can log/show it
            // for debug. Only present when stale.
            'bridge_error' => $bridgeOk ? null : ($bridge['error'] ?? 'bridge unreachable'),
        ]);
    }

    // -----------------------------------------------------------------
    // GET /groups/{jid} — full metadata (participants + settings).
    // -----------------------------------------------------------------
    public function show(Request $request, string $jid): JsonResponse
    {
        $jid = $this->normaliseGroupJid($jid);
        if (! $jid) return response()->json(['success' => false, 'message' => 'Invalid group jid.'], 422);

        // Bridge-authoritative access check — auto-creates the local mirror
        // when the device sees the group, so subsequent ops take the fast
        // path. Replaces the old strict mirror-only check that 404'd any
        // group created outside our app.
        $access = $this->resolveGroupAccess($jid, $request);
        if (! $access['ok']) return $access['error'];
        $device = $access['device'];
        $devicePhone = preg_replace('/\D+/', '', (string) ($device->country_code . $device->phone_number)) ?: '';

        // resolveGroupAccess MAY have already fetched fresh meta (when it
        // had to ask the bridge to confirm). Reuse it when available to
        // avoid a second round-trip; otherwise pull it now.
        if (! empty($access['meta'])) {
            $bridge = ['ok' => true, 'meta' => $access['meta']];
        } else {
            $bridge = $this->callNode("/api/groups/meta/{$devicePhone}?jid=" . urlencode($jid), null, 'GET');
        }
        if (! ($bridge['ok'] ?? false)) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch group metadata.',
                'error'   => $bridge['error'] ?? 'unknown',
            ], 502);
        }
        $meta = $bridge['meta'] ?? null;
        if (! is_array($meta)) {
            return response()->json(['success' => true, 'data' => null]);
        }

        // Derive viewer permissions so the app can disable the composer
        // when the group is announce-only and the device is NOT an admin.
        // WhatsApp announcement groups silently drop non-admin sends —
        // that's why the app previously saw "message went out" but the
        // recipient never received it.
        $viewer = $this->resolveViewerRoleInGroup($devicePhone, $meta);
        $announceOnly = (bool) ($meta['announce'] ?? $meta['announce_only'] ?? false);
        $canSend = $announceOnly ? $viewer['is_admin'] : true;

        $meta['viewer_phone']     = $devicePhone;
        $meta['viewer_jid']       = $viewer['jid'];
        $meta['viewer_is_admin']  = $viewer['is_admin'];     // bool
        $meta['viewer_role']      = $viewer['role'];         // 'superadmin'|'admin'|'member'|null
        $meta['announce_only']    = $announceOnly;
        $meta['can_send']         = $canSend;
        $meta['cannot_send_reason'] = $canSend
            ? null
            : 'This group is set to admin-only messaging. The connected device is not an admin.';

        return response()->json(['success' => true, 'data' => $meta]);
    }

    /**
     * Walk the bridge's participants list to figure out whether the
     * connected device is an admin / super-admin / regular member of
     * the group. The participant ids in groupMetadata are JIDs, but
     * they can arrive as `<digits>@s.whatsapp.net` OR `<digits>@lid`
     * (post-LID-migration accounts) — match BOTH by digit-prefix.
     *
     * Returns:
     *   ['jid'=>?string, 'role'=>?string, 'is_admin'=>bool]
     *   role is one of 'superadmin'|'admin'|'member' when matched, else null.
     */
    private function resolveViewerRoleInGroup(string $devicePhone, array $meta): array
    {
        $digits = preg_replace('/\D+/', '', $devicePhone);
        if ($digits === '') {
            return ['jid' => null, 'role' => null, 'is_admin' => false];
        }
        $participants = is_array($meta['participants'] ?? null) ? $meta['participants'] : [];
        foreach ($participants as $p) {
            $pid = (string) ($p['id'] ?? '');
            if ($pid === '') continue;
            $head = preg_replace('/\D+/', '', explode('@', $pid)[0]);
            if ($head !== $digits) continue;
            $admin = $p['admin'] ?? null;
            $role = $admin === 'superadmin'
                ? 'superadmin'
                : ($admin === 'admin' ? 'admin' : 'member');
            return [
                'jid'      => $pid,
                'role'     => $role,
                'is_admin' => $role === 'admin' || $role === 'superadmin',
            ];
        }
        // Device phone didn't match any participant — likely a LID-only
        // group the device joined under a different identity. Be conservative:
        // not an admin.
        return ['jid' => null, 'role' => null, 'is_admin' => false];
    }

    // -----------------------------------------------------------------
    // POST /groups/{jid}/participants — action: add|remove|promote|demote
    // -----------------------------------------------------------------
    public function participants(Request $request, string $jid): JsonResponse
    {
        $data = $request->validate([
            'action'         => 'required|in:add,remove,promote,demote',
            'participants'   => 'required|array|min:1|max:200',
            'participants.*' => 'required|string|max:32',
        ]);

        $jid = $this->normaliseGroupJid($jid);
        if (! $jid) return response()->json(['success' => false, 'message' => 'Invalid group jid.'], 422);

        // Bridge-authoritative access — auto-creates the local mirror when
        // the device sees the group (replaces the old strict mirror-only
        // check that 404'd groups created outside our app).
        $access = $this->resolveGroupAccess($jid, $request);
        if (! $access['ok']) return $access['error'];
        $device = $access['device'];
        $devicePhone = preg_replace('/\D+/', '', (string) ($device->country_code . $device->phone_number)) ?: '';

        $participants = collect($data['participants'])
            ->map(fn ($p) => preg_replace('/\D+/', '', (string) $p))
            ->filter(fn ($p) => strlen($p) >= 8)
            ->unique()->values()->all();

        $bridge = $this->callNode("/api/groups/participants/{$devicePhone}", [
            'jid'          => $jid,
            'action'       => $data['action'],
            'participants' => $participants,
        ]);
        if (! ($bridge['ok'] ?? false)) {
            // Translate the most common Node-side failure patterns to a
            // message the operator can act on. Baileys's groupParticipantsUpdate
            // throws when the device isn't an admin, when the number isn't on
            // WhatsApp, or when Node times out reaching Meta. The raw "unknown"
            // gave the dev no signal at all — usually the cause is admin.
            $raw  = (string) ($bridge['error'] ?? '');
            $low  = strtolower($raw);
            $hint = match (true) {
                str_contains($low, '403')
                || str_contains($low, 'not allowed')
                || str_contains($low, 'forbidden')
                || str_contains($low, 'admin')  => 'The connected device is not an admin of this group — promote it via WhatsApp first, then retry.',
                str_contains($low, '404')
                || str_contains($low, 'not-on-whatsapp')
                || str_contains($low, 'not on whatsapp') => 'One or more numbers are not on WhatsApp.',
                str_contains($low, '408')
                || str_contains($low, 'timeout') => 'WhatsApp timed out — try again in a few seconds.',
                str_contains($low, '409')
                || str_contains($low, 'already') => $data['action'] === 'add'
                    ? 'One or more numbers are already in the group.'
                    : 'One or more numbers are already removed.',
                str_contains($low, 'no participants') => 'No valid participant phone numbers — they must be at least 8 digits.',
                default => 'Participant update failed. Most often this means the connected device is not a group admin. Promote it in WhatsApp first.',
            };
            return response()->json([
                'success'  => false,
                'message'  => $hint,
                'error'    => $raw !== '' ? $raw : 'unknown',
                'action'   => $data['action'],
                'common_causes' => [
                    'Device is not a group admin (most common — WhatsApp blocks non-admins from add/remove/promote/demote)',
                    'Trying to add a number that\'s not on WhatsApp',
                    'Trying to add someone already in the group / remove someone not in it',
                    'Group has the "only admins can add members" setting on',
                ],
            ], 502);
        }

        // The Node bridge now re-fetches groupMetadata after the mutation
        // and returns it as `meta` — pass it through so the app can render
        // the authoritative post-mutation roster without a second show()
        // round-trip (which would race Baileys' cache anyway).
        return response()->json([
            'success' => true,
            'message' => 'Participants updated.',
            'data'    => [
                'action' => $data['action'],
                'result' => $bridge['result'] ?? [],
                'meta'   => $bridge['meta']   ?? null,
            ],
        ]);
    }

    // -----------------------------------------------------------------
    // POST /groups/{jid}/subject — rename the group.
    // -----------------------------------------------------------------
    public function updateSubject(Request $request, string $jid): JsonResponse
    {
        $data = $request->validate(['subject' => 'required|string|max:60']);

        $jid = $this->normaliseGroupJid($jid);
        if (! $jid) return response()->json(['success' => false, 'message' => 'Invalid group jid.'], 422);

        // Bridge-authoritative access — auto-creates the local mirror when
        // the device sees the group (replaces the old strict mirror-only
        // check that 404'd groups created outside our app).
        $access = $this->resolveGroupAccess($jid, $request);
        if (! $access['ok']) return $access['error'];
        $device = $access['device'];
        $devicePhone = preg_replace('/\D+/', '', (string) ($device->country_code . $device->phone_number)) ?: '';

        $bridge = $this->callNode("/api/groups/subject/{$devicePhone}", [
            'jid'     => $jid,
            'subject' => $data['subject'],
        ]);
        if (! ($bridge['ok'] ?? false)) {
            return response()->json(['success' => false, 'message' => 'Subject update failed.', 'error' => $bridge['error'] ?? 'unknown'], 502);
        }

        // Mirror onto our Conversation row if we have one for this jid.
        Conversation::query()->forCurrentWorkspace()->where('raw_jid', $jid)
            ->update(['title' => $data['subject']]);

        return response()->json(['success' => true, 'message' => 'Group subject updated.', 'data' => ['jid' => $jid, 'subject' => $data['subject']]]);
    }

    // -----------------------------------------------------------------
    // POST /groups/{jid}/description — set the group description.
    // -----------------------------------------------------------------
    public function updateDescription(Request $request, string $jid): JsonResponse
    {
        $data = $request->validate(['description' => 'present|string|max:512']);

        $jid = $this->normaliseGroupJid($jid);
        if (! $jid) return response()->json(['success' => false, 'message' => 'Invalid group jid.'], 422);

        // Bridge-authoritative access — auto-creates the local mirror when
        // the device sees the group (replaces the old strict mirror-only
        // check that 404'd groups created outside our app).
        $access = $this->resolveGroupAccess($jid, $request);
        if (! $access['ok']) return $access['error'];
        $device = $access['device'];
        $devicePhone = preg_replace('/\D+/', '', (string) ($device->country_code . $device->phone_number)) ?: '';

        $bridge = $this->callNode("/api/groups/description/{$devicePhone}", [
            'jid'         => $jid,
            'description' => $data['description'],
        ]);
        if (! ($bridge['ok'] ?? false)) {
            return response()->json(['success' => false, 'message' => 'Description update failed.', 'error' => $bridge['error'] ?? 'unknown'], 502);
        }

        return response()->json(['success' => true, 'message' => 'Group description updated.', 'data' => ['jid' => $jid, 'description' => $data['description']]]);
    }

    // -----------------------------------------------------------------
    // POST /groups/{jid}/settings — announcement | not_announcement |
    // locked | unlocked. Admin-only restriction toggles.
    // -----------------------------------------------------------------
    public function updateSetting(Request $request, string $jid): JsonResponse
    {
        $data = $request->validate(['setting' => 'required|in:announcement,not_announcement,locked,unlocked']);

        $jid = $this->normaliseGroupJid($jid);
        if (! $jid) return response()->json(['success' => false, 'message' => 'Invalid group jid.'], 422);

        // Bridge-authoritative access — auto-creates the local mirror when
        // the device sees the group (replaces the old strict mirror-only
        // check that 404'd groups created outside our app).
        $access = $this->resolveGroupAccess($jid, $request);
        if (! $access['ok']) return $access['error'];
        $device = $access['device'];
        $devicePhone = preg_replace('/\D+/', '', (string) ($device->country_code . $device->phone_number)) ?: '';

        $bridge = $this->callNode("/api/groups/settings/{$devicePhone}", [
            'jid'     => $jid,
            'setting' => $data['setting'],
        ]);
        if (! ($bridge['ok'] ?? false)) {
            return response()->json(['success' => false, 'message' => 'Setting update failed.', 'error' => $bridge['error'] ?? 'unknown'], 502);
        }

        return response()->json(['success' => true, 'message' => 'Group setting updated.', 'data' => ['jid' => $jid, 'setting' => $data['setting']]]);
    }

    // -----------------------------------------------------------------
    // POST /groups/{jid}/leave — leave the group.
    //
    // Side-effects on success:
    //   - The mirrored Conversation row is stamped status='left' AND
    //     archived=true. /chats list filters out archived rows by
    //     default and /groups index() now keeps showing it via the
    //     mirror UNLESS we suppress it here. We KEEP the row (rather
    //     than delete) so the team-inbox history isn't wiped — the
    //     operator can still view past messages from the group.
    //   - We don't delete the messages — same reason.
    //
    // The bridge call itself can race the Baileys leave-notify, so a
    // /groups GET in the next ~1s may still include the left jid until
    // Baileys' participating roster updates. The Conversation mirror
    // change is the durable signal the app can rely on.
    // -----------------------------------------------------------------
    public function leave(Request $request, string $jid): JsonResponse
    {
        $jid = $this->normaliseGroupJid($jid);
        if (! $jid) return response()->json(['success' => false, 'message' => 'Invalid group jid.'], 422);

        // Jid-ownership gate — only let this workspace leave a group it
        // has a mirror for. Without this, any authenticated user could
        // POST an arbitrary jid and have us run sock.groupLeave against
        // groups outside their tenancy (Node bridge enforces no jid
        // ownership of its own).
        // Bridge-authoritative access — auto-creates the local mirror when
        // the device sees the group (replaces the old strict mirror-only
        // check that 404'd groups created outside our app).
        $access = $this->resolveGroupAccess($jid, $request);
        if (! $access['ok']) return $access['error'];
        $device = $access['device'];
        $devicePhone = preg_replace('/\D+/', '', (string) ($device->country_code . $device->phone_number)) ?: '';

        $bridge = $this->callNode("/api/groups/leave/{$devicePhone}", ['jid' => $jid]);
        if (! ($bridge['ok'] ?? false)) {
            return response()->json(['success' => false, 'message' => 'Leave group failed.', 'error' => $bridge['error'] ?? 'unknown'], 502);
        }

        // Mark the mirror row(s) as left. SQL WHERE: raw_jid is plaintext,
        // confirmed against Conversation::$casts + the migration. Match
        // by workspace+raw_jid only (not device_id) so a re-paired
        // device still updates its historical row. Bulk update — much
        // faster than the previous load-all + filter-in-PHP pattern.
        Conversation::query()
            ->forCurrentWorkspace()
            ->where('raw_jid', $jid)
            ->update([
                'status'   => 'left',
                'archived' => true,
            ]);

        return response()->json(['success' => true, 'message' => 'Left the group.']);
    }

    // -----------------------------------------------------------------
    // GET /groups/{jid}/invite-code — returns join code + chat.whatsapp link.
    // -----------------------------------------------------------------
    public function inviteCode(Request $request, string $jid): JsonResponse
    {
        $jid = $this->normaliseGroupJid($jid);
        if (! $jid) return response()->json(['success' => false, 'message' => 'Invalid group jid.'], 422);

        // Bridge-authoritative access — auto-creates the local mirror when
        // the device sees the group (replaces the old strict mirror-only
        // check that 404'd groups created outside our app).
        $access = $this->resolveGroupAccess($jid, $request);
        if (! $access['ok']) return $access['error'];
        $device = $access['device'];
        $devicePhone = preg_replace('/\D+/', '', (string) ($device->country_code . $device->phone_number)) ?: '';

        $bridge = $this->callNode("/api/groups/invite-code/{$devicePhone}?jid=" . urlencode($jid), null, 'GET');
        if (! ($bridge['ok'] ?? false)) {
            return response()->json(['success' => false, 'message' => 'Invite-code fetch failed.', 'error' => $bridge['error'] ?? 'unknown'], 502);
        }

        return response()->json([
            'success' => true,
            'data'    => [
                'jid'  => $jid,
                'code' => $bridge['code'] ?? null,
                'url'  => $bridge['url']  ?? null,
            ],
        ]);
    }

    // -----------------------------------------------------------------
    // POST /groups/{jid}/revoke-invite — rotates the invite code so
    // the previous link stops working.
    // -----------------------------------------------------------------
    public function revokeInvite(Request $request, string $jid): JsonResponse
    {
        $jid = $this->normaliseGroupJid($jid);
        if (! $jid) return response()->json(['success' => false, 'message' => 'Invalid group jid.'], 422);

        // Bridge-authoritative access — auto-creates the local mirror when
        // the device sees the group (replaces the old strict mirror-only
        // check that 404'd groups created outside our app).
        $access = $this->resolveGroupAccess($jid, $request);
        if (! $access['ok']) return $access['error'];
        $device = $access['device'];
        $devicePhone = preg_replace('/\D+/', '', (string) ($device->country_code . $device->phone_number)) ?: '';

        $bridge = $this->callNode("/api/groups/revoke-invite/{$devicePhone}", ['jid' => $jid]);
        if (! ($bridge['ok'] ?? false)) {
            return response()->json(['success' => false, 'message' => 'Revoke invite failed.', 'error' => $bridge['error'] ?? 'unknown'], 502);
        }

        return response()->json([
            'success' => true,
            'message' => 'Invite code rotated.',
            'data'    => [
                'jid'  => $jid,
                'code' => $bridge['code'] ?? null,
                'url'  => $bridge['url']  ?? null,
            ],
        ]);
    }

    // =================================================================
    // Helpers
    // =================================================================

    /**
     * Resolve a BAILEYS device on this workspace, in priority order:
     *   1. `sender` composite key — only honoured when engine=baileys.
     *   2. `device_id` — bare Baileys device row id.
     *   3. First active Baileys device on the workspace (devices table is
     *      Baileys-only; WABA/Twilio senders live in wa_provider_configs).
     * Returns null when no Baileys device exists; the caller surfaces a
     * clear 422 / 409 depending on what the operator picked. This is the
     * MULTI-ENGINE-aware replacement for the old `resolveDevice` which
     * silently returned any active device and broke groups when the
     * workspace default engine wasn't Baileys.
     */
    private function resolveBaileysDevice(Request $request, int $wsId): ?Device
    {
        // 1. Composite "engine:id" picker key.
        // CRITICAL: when the caller passed an EXPLICIT sender/device_id and
        // it can't be resolved, return null instead of falling through to
        // the workspace default. Without this, operator targets device 47
        // but we silently send via device 12 — the mirror row stores
        // device 12, the customer never finds their group in /groups, and
        // they think the create failed. The "no fallback when explicit"
        // contract is what every other multi-engine controller in this
        // codebase relies on.
        if ($request->filled('sender')) {
            $picked = WorkspaceEngine::senderForKey($wsId, (string) $request->input('sender'));
            // Unknown sender key OR known but non-Baileys → caller's pick
            // is rejected. Don't substitute a default Baileys device.
            if (! $picked) return null;
            if (($picked['engine'] ?? null) !== WorkspaceEngine::ENGINE_BAILEYS) return null;
            // Baileys sender → must resolve to a real device row.
            $d = Device::query()->forCurrentWorkspace()->find((int) $picked['id']);
            return $d ?: null;
        }

        // 2. Bare device_id (always a Baileys devices.id by table convention).
        // Same no-fallback rule when explicit.
        if ($request->filled('device_id')) {
            $d = Device::query()->forCurrentWorkspace()->find((int) $request->input('device_id'));
            return $d ?: null;
        }

        // 3. Implicit fallback ONLY when no sender/device_id was supplied —
        // first active Baileys device on the workspace.
        return Device::query()->forCurrentWorkspace()->where('active', 1)->orderByDesc('id')->first();
    }

    /**
     * What engine did the request POINT AT (sender key) so we can return
     * the right 409 message — "you picked Twilio, that's not supported"
     * vs. "this workspace has no Baileys at all". Returns null when the
     * request didn't pick anything explicit.
     */
    private function enginePickedByRequest(Request $request, int $wsId): ?string
    {
        if ($request->filled('sender')) {
            $picked = WorkspaceEngine::senderForKey($wsId, (string) $request->input('sender'));
            if ($picked) return (string) ($picked['engine'] ?? '');
        }
        return null;
    }

    /**
     * Normalise a group identifier into a strict `<digits>-<digits>@g.us`
     * JID. WhatsApp group jids are always digits and dashes followed by
     * `@g.us`; anything else (an XSS payload, a SQL fragment, a path
     * traversal string) is rejected. Caller surfaces a 422.
     */
    private function normaliseGroupJid(string $jid): ?string
    {
        $jid = trim($jid);
        if ($jid === '') return null;
        // Strip any non-digit/dash chars from the pre-suffix portion so
        // input like "120363-XX@g.us" (post-decode URL) still resolves.
        if (str_ends_with($jid, '@g.us')) {
            $head = substr($jid, 0, -strlen('@g.us'));
            $clean = preg_replace('/[^0-9-]/', '', $head);
        } else {
            $clean = preg_replace('/[^0-9-]/', '', $jid);
        }
        if ($clean === '' || ! preg_match('/^\d+(?:-\d+)?$/', $clean)) return null;
        return $clean . '@g.us';
    }

    /**
     * Look up the workspace's local mirror row for a group jid. Used as
     * the ownership gate on every mutating endpoint: a workspace can
     * only `/leave|participants|subject|description|settings|invite-code|
     * revoke-invite` against groups it has a Conversation mirror for.
     * Returns null when no mirror row exists in the current workspace
     * (caller turns this into a 404).
     *
     * The match is workspace + raw_jid only — NOT device_id — because
     * the device row may have been re-paired (new devices.id) since the
     * mirror was written. The workspace is the durable identity.
     */
    private function mirrorForJid(string $jid): ?Conversation
    {
        return Conversation::query()
            ->forCurrentWorkspace()
            ->where('raw_jid', $jid)
            ->first();
    }

    /**
     * Resolve "does this workspace see this group?" using the LIVE bridge as
     * the authority, falling back to a local Conversation mirror when present.
     *
     * Why: the strict mirrorForJid() check above rejects any group that
     * doesn't have a local Conversation row, even when the connected Baileys
     * device IS a participant (and therefore can read/manage it). That
     * 404'd every read+mutate call to groups created outside the app
     * (other linked device, team-mate's account, group from before
     * open-chat existed). The bridge's groupMetadata is the source of
     * truth for membership — if it returns 200+meta, the device sees the
     * group; if it 404s, the device doesn't. The local mirror is just a
     * chat-list cache, not an access boundary.
     *
     * Side-effect — when the bridge confirms but no local mirror exists, we
     * auto-create one so the chat list immediately reflects the group AND
     * subsequent calls take the fast (no-bridge) path.
     *
     * Returns an array:
     *   ['ok' => true,  'mirror' => Conversation, 'meta' => array, 'error' => null, 'device' => Device]
     *   ['ok' => false, 'mirror' => null, 'meta' => null, 'error' => JsonResponse, 'device' => null|Device]
     *
     * Caller pattern:
     *   $r = $this->resolveGroupAccess($jid, $request);
     *   if (! $r['ok']) return $r['error'];
     *   $mirror = $r['mirror'];
     */
    private function resolveGroupAccess(string $jid, Request $request): array
    {
        $user = $request->user();
        $wsId = (int) ($user?->current_workspace_id ?? 0);

        // Fast path — local mirror already exists. Caller doesn't need the
        // bridge for a simple ownership check.
        $existing = $this->mirrorForJid($jid);

        // Resolve a Baileys device for the bridge lookup AND (if needed) the
        // mirror creation. Without a device we can't ask the bridge OR send.
        $device = $this->resolveBaileysDevice($request, $wsId);
        if (! $device) {
            return [
                'ok' => false, 'mirror' => null, 'meta' => null, 'device' => null,
                'error' => response()->json([
                    'success' => false,
                    'message' => 'No connected Unofficial API device on this workspace.',
                ], 422),
            ];
        }
        $devicePhone = preg_replace('/\D+/', '', (string) ($device->country_code . $device->phone_number)) ?: '';

        // If the mirror was already there, return early — no bridge round-trip
        // needed for ownership. (Read endpoints that DO want fresh meta call
        // the bridge themselves after this returns; mutating endpoints use
        // the existing mirror without re-querying.)
        if ($existing) {
            return ['ok' => true, 'mirror' => $existing, 'meta' => null, 'device' => $device, 'error' => null];
        }

        // No mirror — ask the bridge. If the device participates, the bridge
        // returns groupMetadata; if not, it 404s or errors. We treat anything
        // other than ok+meta as "not visible to this workspace".
        $bridge = $this->callNode("/api/groups/meta/{$devicePhone}?jid=" . urlencode($jid), null, 'GET');
        $meta   = $bridge['meta'] ?? null;
        if (! ($bridge['ok'] ?? false) || ! is_array($meta)) {
            return [
                'ok' => false, 'mirror' => null, 'meta' => null, 'device' => $device,
                'error' => response()->json([
                    'success' => false,
                    'message' => 'Group not visible from this device (not a participant or device offline).',
                    'error'   => $bridge['error'] ?? 'unknown',
                ], 404),
            ];
        }

        // Bridge says yes → auto-create the local mirror so /chats/{id}/*
        // works AND the next call here takes the fast path.
        $engine = WorkspaceEngine::ENGINE_BAILEYS;
        $mirror = Conversation::create([
            'user_id'          => $user->id,
            'workspace_id'     => $wsId ?: null,
            'device_id'        => $device->id,
            'title'            => (string) ($meta['subject'] ?? 'Group'),
            'preview'          => null,
            'status'           => 'pending',
            'platform'         => WaProvider::tryFrom($engine)?->legacyCode() ?? 'W',
            'provider'         => $engine,
            'origin'           => 'chat',
            'recipients_count' => (int) ($meta['participants_count']
                ?? (is_array($meta['participants'] ?? null) ? count($meta['participants']) : 0)),
            'last_message_at'  => now(),
            'raw_jid'          => $jid,
        ]);
        return ['ok' => true, 'mirror' => $mirror, 'meta' => $meta, 'device' => $device, 'error' => null];
    }

    // -----------------------------------------------------------------
    // POST /groups/{jid}/open-chat — open-or-create a Conversation row
    // for an existing WhatsApp group. The chat list, /chats/{id}/messages,
    // and /chats/{id}/template all operate on Conversation ids, but
    // groups that already existed in WhatsApp (created in the app, on
    // another linked device, or via a team-mate's account) often have
    // no local Conversation row yet — which is why the app could see
    // the group in /groups, but tapping "send" returned a 404 / no-op.
    //
    // This endpoint normalises that: pass any group jid the device is
    // a participant of, and we'll either return the existing Conversation
    // or create a fresh one wired to the picked Baileys device. The app
    // then sends via /chats/{conversation_id}/messages or .../template
    // exactly as it does for 1-to-1 chats. Group-aware send paths in
    // ChatController already detect `raw_jid LIKE '%@g.us'` and stamp
    // meta.target_jid so the dispatcher routes to the group.
    // -----------------------------------------------------------------
    public function openChat(Request $request, string $jid): JsonResponse
    {
        $jid = $this->normaliseGroupJid($jid);
        if (! $jid) return response()->json(['success' => false, 'message' => 'Invalid group jid.'], 422);

        $user = $request->user();
        $wsId = (int) ($user->current_workspace_id ?? 0);

        // Pick the Baileys device (sender / device_id / first active).
        $device = $this->resolveBaileysDevice($request, $wsId);
        if (! $device) {
            return response()->json(['success' => false, 'message' => 'No connected Unofficial API device on this workspace.'], 422);
        }
        $devicePhone = preg_replace('/\D+/', '', (string) ($device->country_code . $device->phone_number)) ?: '';

        // Cheap freshness check — confirm the device actually participates
        // in this group before opening a chat. Avoids creating phantom
        // Conversation rows for spoofed jids.
        $bridge = $this->callNode("/api/groups/meta/{$devicePhone}?jid=" . urlencode($jid), null, 'GET');
        $meta   = $bridge['meta'] ?? null;
        if (! ($bridge['ok'] ?? false) || ! is_array($meta)) {
            return response()->json([
                'success' => false,
                'message' => 'Group not visible from this device (not a participant or device offline).',
                'error'   => $bridge['error'] ?? 'unknown',
            ], 404);
        }

        // Existing mirror row → return as-is. The Conversation already
        // has raw_jid + device_id, the app can hit /chats/{id}/messages.
        $existing = $this->mirrorForJid($jid);
        if ($existing) {
            $updates = [];
            // Resync the title from live meta in case the group was renamed
            // outside our app since we last touched the row.
            if (! empty($meta['subject']) && (string) $existing->title !== (string) $meta['subject']) {
                $updates['title'] = (string) $meta['subject'];
            }
            // Re-point the mirror at the device we JUST verified is a live
            // participant (the freshness check above used $device). The group
            // may have been first synced on a different device that's since
            // gone offline — mirrorForJid matches by workspace+raw_jid only,
            // so it returned that stale row. Sending must go through THIS
            // connected device or the group send silently fails ("existing
            // synced group send not working").
            if ((int) $existing->device_id !== (int) $device->id) {
                $updates['device_id'] = $device->id;
                $updates['provider'] = WorkspaceEngine::ENGINE_BAILEYS;
                $updates['platform'] = WaProvider::tryFrom(WorkspaceEngine::ENGINE_BAILEYS)?->legacyCode() ?? 'W';
            }
            if ($updates) $existing->update($updates);
            return response()->json([
                'success' => true,
                'message' => 'Group chat already open.',
                'data'    => [
                    'conversation_id' => $existing->id,
                    'jid'             => $jid,
                    'subject'         => (string) ($meta['subject'] ?? $existing->title ?? ''),
                    'device_id'       => $existing->device_id,
                    'created'         => false,
                ],
            ]);
        }

        // Mirror the group locally so all downstream chat endpoints work.
        $engine = WorkspaceEngine::ENGINE_BAILEYS;
        $convo = Conversation::create([
            'user_id'          => $user->id,
            'workspace_id'     => $wsId ?: null,
            'device_id'        => $device->id,
            'title'            => (string) ($meta['subject'] ?? 'Group'),
            'preview'          => null,
            'status'           => 'pending',
            'platform'         => WaProvider::tryFrom($engine)?->legacyCode() ?? 'W',
            'provider'         => $engine,
            'origin'           => 'chat',
            'recipients_count' => (int) ($meta['participants_count'] ?? (is_array($meta['participants'] ?? null) ? count($meta['participants']) : 0)),
            'last_message_at'  => now(),
            'raw_jid'          => $jid,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Group chat opened.',
            'data'    => [
                'conversation_id' => $convo->id,
                'jid'             => $jid,
                'subject'         => (string) ($meta['subject'] ?? ''),
                'device_id'       => $device->id,
                'created'         => true,
            ],
        ], 201);
    }

    /**
     * POST/GET to the Node bridge. Returns the JSON body as an assoc
     * array (or ['ok' => false, 'error' => '...'] on transport failure).
     *
     * The current authenticated user's workspace_id is auto-injected:
     *   - POST → into the request body as `workspace_id`
     *   - GET  → into the query string as `?workspace_id=<id>` (with a
     *            `&` separator when other query params already exist)
     * The Node bridge uses workspace_id to scope its in-memory
     * _recentlyLeft cache so two tenants on the same phone digits
     * can't cross-pollute each other's group list.
     */
    private function callNode(string $path, ?array $body, string $method = 'POST'): array
    {
        $nodeUrl = rtrim((string) (SystemSetting::get('baileys_server_url', '') ?: env('SERVER_URL', '')), '/');
        if ($nodeUrl === '') {
            return ['ok' => false, 'error' => 'Node bridge URL is not configured.'];
        }
        $wsId = (int) (\Illuminate\Support\Facades\Auth::user()?->current_workspace_id ?? 0);

        try {
            $req = Http::withHeaders(['X-Node-Token' => node_token()])
                ->timeout(20)
                ->acceptJson();
            if (strtoupper($method) === 'GET') {
                $sep = str_contains($path, '?') ? '&' : '?';
                $url = $nodeUrl . $path . ($wsId ? "{$sep}workspace_id={$wsId}" : '');
                $resp = $req->get($url);
            } else {
                $payload = $body ?? [];
                if ($wsId && ! isset($payload['workspace_id'])) $payload['workspace_id'] = $wsId;
                $resp = $req->post($nodeUrl . $path, $payload);
            }
            $json = $resp->json();
            if (! is_array($json)) {
                return ['ok' => false, 'error' => 'Bridge returned non-JSON.', 'status' => $resp->status()];
            }
            if (! $resp->successful() && ! isset($json['ok'])) {
                $json['ok'] = false;
            }
            return $json;
        } catch (\Throwable $e) {
            Log::warning('[App\Group] node bridge call failed', ['path' => $path, 'err' => $e->getMessage()]);
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }
}
