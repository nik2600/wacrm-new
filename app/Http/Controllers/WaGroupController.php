<?php

namespace App\Http\Controllers;

use App\Models\Device;
use App\Models\WaGroup;
use App\Models\WaGroupMember;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Receives the WhatsApp group directory the Node bridge mirrors from
 * sock.groupFetchAllParticipating(). Auth: X-Node-Token (Node → Laravel).
 *
 * Per group it does a REPLACE of the member list (delete + reinsert in one
 * transaction) so adds/removes on the WhatsApp side stay in step. The workspace
 * is resolved from the bot's device phone (digits) — the same way inbound
 * Baileys messages resolve their tenant.
 */
class WaGroupController extends Controller
{
    private function authed(Request $request): bool
    {
        $token = (string) env('NODE_WEBHOOK_TOKEN', '');
        return $token !== '' && hash_equals($token, (string) $request->header('X-Node-Token', ''));
    }

    // ── Web UI (session-auth): view synced groups + manage ordering codes ──

    /** GET /store/groups — list the WhatsApp groups the bot is a member of. */
    public function index(Request $request): View
    {
        $wsId = (int) (Auth::user()->current_workspace_id ?? 0);
        $q    = trim((string) $request->string('q'));

        $groups = WaGroup::where('workspace_id', $wsId)
            ->when($q !== '', fn ($qq) => $qq->where('subject', 'like', '%' . $q . '%'))
            ->orderByDesc('synced_at')->orderBy('subject')
            ->paginate(30)->withQueryString();

        // Bot number for the wa.me ordering link (first connected device).
        $bot = Device::where('workspace_id', $wsId)->get(['country_code', 'phone_number'])
            ->map(fn ($d) => preg_replace('/\D+/', '', (string) ($d->country_code . $d->phone_number)))
            ->filter()->first();

        $total = WaGroup::where('workspace_id', $wsId)->count();
        $coded = WaGroup::where('workspace_id', $wsId)->whereNotNull('group_code')->where('group_code', '!=', '')->count();

        return view('user.store.groups.index', compact('groups', 'q', 'bot', 'total', 'coded'));
    }

    /** PUT /store/groups/{id}/code — set/clear a group's ordering code. */
    public function updateCode(Request $request, int $id): RedirectResponse
    {
        $wsId = (int) (Auth::user()->current_workspace_id ?? 0);
        $data = $request->validate([
            'group_code' => 'nullable|string|max:48|regex:/^[A-Za-z0-9._-]*$/',
        ]);
        $group = WaGroup::where('workspace_id', $wsId)->findOrFail($id);
        $code  = trim((string) ($data['group_code'] ?? ''));

        if ($code !== '') {
            $dupe = WaGroup::where('workspace_id', $wsId)->where('group_code', $code)
                ->where('id', '!=', $group->id)->exists();
            if ($dupe) {
                return back()->withErrors(['group_code' => 'That code is already used by another group.']);
            }
        }
        $group->group_code = $code !== '' ? $code : null;
        $group->save();

        return back()->with('status', $code !== '' ? 'Ordering code saved.' : 'Ordering code cleared.');
    }

    /** Resolve the workspace that owns a Baileys phone (digits-only match). */
    private function workspaceForPhone(string $digits): ?int
    {
        if ($digits === '') return null;
        $device = Device::query()
            ->get(['id', 'workspace_id', 'country_code', 'phone_number'])
            ->first(fn ($d) => preg_replace('/\D+/', '', (string) ($d->country_code . $d->phone_number)) === $digits);
        return $device?->workspace_id ? (int) $device->workspace_id : null;
    }

    public function sync(Request $request): JsonResponse
    {
        if (!$this->authed($request)) {
            return response()->json(['ok' => false, 'error' => 'unauthorized'], 401);
        }
        $data = $request->validate([
            'device_phone'                 => 'required|string|max:24',
            'workspace_id'                 => 'nullable|integer',
            'groups'                       => 'required|array',
            'groups.*.jid'                 => 'required|string|max:64',
            'groups.*.subject'             => 'nullable|string|max:191',
            'groups.*.size'                => 'nullable|integer',
            'groups.*.participants'        => 'nullable|array',
            'groups.*.participants.*.phone'=> 'nullable|string|max:24',
            'groups.*.participants.*.admin'=> 'nullable|boolean',
        ]);

        $deviceDigits = preg_replace('/\D+/', '', (string) $data['device_phone']);
        $wsId = (int) ($data['workspace_id'] ?? 0) ?: ($this->workspaceForPhone($deviceDigits) ?? 0);
        if ($wsId <= 0) {
            Log::warning('[GROUP-SYNC] no workspace for device ' . $deviceDigits);
            return response()->json(['ok' => false, 'error' => 'no workspace for device'], 422);
        }

        $now = now();
        $groupsUpserted = 0; $membersWritten = 0;

        foreach ($data['groups'] as $g) {
            $jid     = (string) $g['jid'];
            $subject = (string) ($g['subject'] ?? '');
            $parts   = is_array($g['participants'] ?? null) ? $g['participants'] : [];
            $size    = (int) ($g['size'] ?? count($parts));

            WaGroup::updateOrCreate(
                ['workspace_id' => $wsId, 'group_jid' => $jid],
                ['device_phone' => $deviceDigits, 'subject' => mb_substr($subject, 0, 191), 'size' => $size, 'synced_at' => $now]
            );
            $groupsUpserted++;

            // Replace this group's members in one transaction so removals stick.
            DB::transaction(function () use ($wsId, $jid, $parts, $now, &$membersWritten) {
                WaGroupMember::where('group_jid', $jid)->where('workspace_id', $wsId)->delete();
                $rows = [];
                foreach ($parts as $p) {
                    $phone = preg_replace('/\D+/', '', (string) ($p['phone'] ?? ''));
                    if ($phone === '') continue;
                    $rows[] = [
                        'workspace_id' => $wsId,
                        'group_jid'    => $jid,
                        'phone'        => mb_substr($phone, 0, 24),
                        'is_admin'     => (bool) ($p['admin'] ?? false),
                        'synced_at'    => $now,
                        'created_at'   => $now,
                        'updated_at'   => $now,
                    ];
                }
                // de-dup within the batch on phone (a participant can't appear twice)
                $rows = collect($rows)->unique('phone')->values()->all();
                // upsert, not insert: the delete above is workspace-scoped, so a
                // plain insert threw 1062 the moment another workspace already
                // held the same (group_jid, phone) — killing the whole sync. Keyed
                // on the real unique triple so a re-sync just refreshes the row.
                foreach (array_chunk($rows, 500) as $chunk) {
                    WaGroupMember::upsert(
                        $chunk,
                        ['workspace_id', 'group_jid', 'phone'],
                        ['is_admin', 'synced_at', 'updated_at']
                    );
                    $membersWritten += count($chunk);
                }
            });
        }

        return response()->json(['ok' => true, 'workspace_id' => $wsId, 'groups' => $groupsUpserted, 'members' => $membersWritten]);
    }
}
