<?php

namespace App\Services\Inbox;

use App\Models\Conversation;
use App\Models\Device;
use App\Models\WaProviderConfig;
use App\Support\Audit;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Finds and merges DUPLICATE team-inbox threads — the same customer showing up
 * twice (or more) in the queue for the same business number.
 *
 * WHY THIS EXISTS
 * ---------------
 * The fork is fixed at the source now: every inbound/outbound path widened its
 * conversation lookup to match a number in BOTH shapes it can be stored in
 * (digits-only '919…' and the JID form '919…@s.whatsapp.net'), across BOTH the
 * raw_jid and alt_jid columns. Nothing new forks.
 *
 * But workspaces that ran the OLD code already have forked rows sitting in the
 * database, and no amount of correct new code retro-actively joins them — the
 * history is genuinely split across two ids. This service is the one-time
 * cleanup for that existing data.
 *
 * SAFETY MODEL
 * ------------
 * Nothing is thrown away. A merge only ever RE-POINTS child rows (messages,
 * notes, events, calls, deals…) at the surviving conversation and then removes
 * the now-empty shell. Message count before == message count after, always.
 *
 * Grouping is deliberately conservative — two threads merge only when all of
 * these match:
 *   - same workspace
 *   - same engine (provider) — a WABA thread and an Unofficial-API thread for
 *     the same number are SEPARATE channels by design, never merged
 *   - same BUSINESS number (resolved through the device, so a number that was
 *     deleted and re-paired — new device row, new id — still counts as one)
 *   - same CUSTOMER identity, where identity is every digit-form found in
 *     raw_jid / alt_jid. Two rows join when they share any one of them, which
 *     is what lets a '@lid'-keyed thread meet its '@s.whatsapp.net' twin: the
 *     row that knows both forms bridges them.
 */
class DuplicateConversationMerger
{
    /**
     * Tables carrying a plain conversation_id we can re-point in bulk.
     * Checked against the live schema before use so an older install
     * (or one where a feature was never migrated) can't fatal the merge.
     */
    private const CHILD_TABLES = [
        'inbox_messages',
        'messages',
        'conversation_notes',
        'conversation_events',
        'csat_responses',
        'platform_notes',
        'appointments',
        'chatbot_widget_visitors',
        'wa_calls',
        'ai_call_logs',
        'wa_form_submissions',
        'deals',
    ];

    /**
     * Tables with a UNIQUE(conversation_id, x) — a blind re-point would hit a
     * duplicate-key error when both threads already carry the same tag or the
     * same participant. The loser's colliding rows are dropped first; the
     * survivor already has that exact relationship.
     */
    private const UNIQUE_CHILD_TABLES = [
        'conversation_participants' => 'user_id',
        'conversation_tag'          => 'tag_id',
    ];

    /**
     * Group the workspace's conversations into duplicate sets.
     *
     * @return Collection<int, array{
     *   key: string, primary: Conversation, duplicates: Collection<int, Conversation>,
     *   phone: string, provider: string, thread_count: int, message_count: int
     * }>
     */
    public function scan(int $workspaceId): Collection
    {
        $rows = Conversation::query()
            ->where('workspace_id', $workspaceId)
            // Web-channel threads (chat widget) key on a visitor id, not a
            // phone — they have no duplicate semantics here.
            ->whereNotIn('channel', Conversation::ENGINE_AGNOSTIC_CHANNELS)
            ->whereIn('origin', ['inbox', 'chat', 'chatbot'])
            ->get([
                'id', 'workspace_id', 'user_id', 'device_id', 'provider', 'origin', 'channel',
                'raw_jid', 'alt_jid', 'title', 'preview', 'status', 'inbox_status', 'priority',
                'archived', 'pinned_at', 'muted_at', 'unread_count',
                'assignee_user_id', 'assignee_team_id', 'assignee_agent_id',
                'last_message_at', 'last_inbound_at', 'last_outbound_at', 'created_at',
            ]);

        if ($rows->count() < 2) {
            return collect();
        }

        $businessPhones = $this->businessPhoneMap($rows);
        $liveDeviceIds  = $this->liveDeviceIds($rows);

        // ORPHANS are handled apart from everything below. A thread whose
        // Unofficial-API device row no longer exists (number deleted, or the
        // same number re-paired on WABA/Twilio) can never be replied to on its
        // original channel — the inbox already hides it via deviceAlive(). It
        // is therefore safe, and the whole point, to let it join the live
        // thread for that customer even though the engine differs.
        [$orphans, $live] = $rows->partition(fn (Conversation $c) => $this->isOrphan($c, $liveDeviceIds));

        // Partition the live rows (workspace is already fixed): engine +
        // business number. Union-find never reaches across a partition, so a
        // customer messaging two of the workspace's own numbers keeps two
        // threads, and a live Meta thread never absorbs a live Unofficial one.
        $partitions = $live->groupBy(function (Conversation $c) use ($businessPhones) {
            $provider = (string) ($c->provider ?: 'baileys');
            $business = $businessPhones[$provider . ':' . (int) $c->device_id] ?? ('dev' . (int) $c->device_id);
            return $provider . '|' . $business;
        });

        $clusters = collect();

        foreach ($partitions as $partitionKey => $convos) {
            foreach ($this->unionByIdentity($convos) as $cluster) {
                $clusters->push(['partition' => (string) $partitionKey, 'rows' => $cluster->values()]);
            }
        }

        $clusters = $this->attachOrphans($clusters, $orphans);

        $groups = collect();

        foreach ($clusters as $cluster) {
            $rowsIn = $cluster['rows'];
            if ($rowsIn->count() < 2) {
                continue;
            }

            // The survivor must be a thread that can still be replied to, so a
            // live row always outranks an orphan. Among equals the oldest wins:
            // it holds the original id, keeping deal links and audit rows valid.
            $sorted = $rowsIn
                ->sortBy(fn (Conversation $c) => [$this->isOrphan($c, $liveDeviceIds) ? 1 : 0, (int) $c->id])
                ->values();

            $primary    = $sorted->first();
            $duplicates = $sorted->slice(1)->values();
            $ids        = $sorted->pluck('id')->all();

            $groups->push([
                'key'           => $cluster['partition'] . '|' . $primary->id,
                'primary'       => $primary,
                'duplicates'    => $duplicates,
                'provider'      => (string) ($primary->provider ?: 'baileys'),
                'phone'         => $this->displayPhone($sorted),
                'thread_count'  => $sorted->count(),
                'message_count' => $this->messageCount($ids),
            ]);
        }

        return $groups->sortByDesc('thread_count')->values();
    }

    /**
     * Merge every duplicate group in the workspace.
     *
     * @param  array<int, string>  $onlyKeys  Restrict to these group keys; empty = all.
     * @return array{groups: int, threads_removed: int, messages_moved: int, failed: int}
     */
    public function mergeAll(int $workspaceId, array $onlyKeys = []): array
    {
        $groups = $this->scan($workspaceId);

        if ($onlyKeys) {
            $groups = $groups->whereIn('key', $onlyKeys)->values();
        }

        $summary = ['groups' => 0, 'threads_removed' => 0, 'messages_moved' => 0, 'failed' => 0];

        foreach ($groups as $group) {
            try {
                $moved = $this->mergeGroup($group['primary'], $group['duplicates']);

                $summary['groups']++;
                $summary['threads_removed'] += $group['duplicates']->count();
                $summary['messages_moved']  += $moved;
            } catch (\Throwable $e) {
                // One bad group must not abort the rest — each merge is its own
                // transaction, so a failure leaves that group exactly as it was.
                $summary['failed']++;
                Log::error('[INBOX-MERGE] group failed', [
                    'workspace_id' => $workspaceId,
                    'primary'      => $group['primary']->id,
                    'duplicates'   => $group['duplicates']->pluck('id')->all(),
                    'error'        => $e->getMessage(),
                ]);
            }
        }

        if ($summary['groups'] > 0 || $summary['failed'] > 0) {
            Audit::log('inbox.conversations.merged', [
                'workspace_id' => $workspaceId,
                'meta'         => $summary,
                'result'       => $summary['failed'] ? 'partial' : 'success',
            ]);
        }

        return $summary;
    }

    /**
     * Fold $duplicates into $primary. Returns how many message rows moved.
     */
    public function mergeGroup(Conversation $primary, Collection $duplicates): int
    {
        $loserIds = $duplicates->pluck('id')->map(fn ($i) => (int) $i)->all();

        if (!$loserIds) {
            return 0;
        }

        return DB::transaction(function () use ($primary, $duplicates, $loserIds) {
            $moved = 0;

            foreach (self::CHILD_TABLES as $table) {
                if (!Schema::hasTable($table) || !Schema::hasColumn($table, 'conversation_id')) {
                    continue;
                }

                $count = DB::table($table)
                    ->whereIn('conversation_id', $loserIds)
                    ->update(['conversation_id' => $primary->id]);

                if ($table === 'inbox_messages' || $table === 'messages') {
                    $moved += $count;
                }
            }

            foreach (self::UNIQUE_CHILD_TABLES as $table => $pairColumn) {
                if (!Schema::hasTable($table)) {
                    continue;
                }

                // Drop the loser rows whose (conversation_id, pair) already
                // exists on the survivor, then re-point what's left.
                $existing = DB::table($table)
                    ->where('conversation_id', $primary->id)
                    ->pluck($pairColumn)
                    ->all();

                if ($existing) {
                    DB::table($table)
                        ->whereIn('conversation_id', $loserIds)
                        ->whereIn($pairColumn, $existing)
                        ->delete();
                }

                DB::table($table)
                    ->whereIn('conversation_id', $loserIds)
                    ->update(['conversation_id' => $primary->id]);
            }

            $this->reconcile($primary, $duplicates);

            // Children are all re-pointed by now, so the shells are empty.
            // (inbox_messages cascades on delete — moving BEFORE deleting is
            // what keeps this non-destructive.)
            Conversation::whereIn('id', $loserIds)->delete();

            Log::info('[INBOX-MERGE] merged duplicate threads', [
                'workspace_id'  => (int) $primary->workspace_id,
                'primary'       => (int) $primary->id,
                'merged'        => $loserIds,
                'rows_moved'    => $moved,
            ]);

            return $moved;
        });
    }

    // ------------------------------------------------------------------
    // Internals
    // ------------------------------------------------------------------

    /**
     * Copy across every field where a duplicate knows more than the survivor,
     * then recompute the aggregates from the (now merged) message rows.
     */
    private function reconcile(Conversation $primary, Collection $duplicates): void
    {
        $all = collect([$primary])->merge($duplicates);

        // The live row — most recent activity — owns the routing fields. Its
        // device_id/provider are the pair a reply will actually go out on, which
        // matters when the fork came from re-pairing the number.
        $newest = $all->sortByDesc(fn ($c) => $c->last_message_at?->getTimestamp() ?? 0)->first();

        $attrs = [];

        if ($newest && (int) $newest->id !== (int) $primary->id) {
            $attrs['device_id'] = $newest->device_id;
            $attrs['provider']  = $newest->provider;
        }

        // Both JID shapes must end up ON the survivor, or the next inbound
        // matches nothing and forks a fresh thread all over again.
        [$rawJid, $altJid] = $this->reconcileJids($all);
        if ($rawJid !== null) $attrs['raw_jid'] = $rawJid;
        $attrs['alt_jid'] = $altJid;

        // Prefer a title that carries a name over a bare number.
        $title = $all->map(fn ($c) => (string) (Conversation::safeRead($c, 'title') ?? ''))
            ->filter(fn ($t) => trim($t) !== '')
            ->sortByDesc(fn ($t) => (preg_match('/\p{L}/u', $t) ? 1000 : 0) + mb_strlen($t))
            ->first();
        if ($title) $attrs['title'] = $title;

        $attrs['last_message_at'] = $this->maxDate($all, 'last_message_at');
        $attrs['last_inbound_at'] = $this->maxDate($all, 'last_inbound_at');
        $attrs['last_outbound_at'] = $this->maxDate($all, 'last_outbound_at');
        $attrs['unread_count'] = (int) $all->sum(fn ($c) => (int) $c->unread_count);

        // Sticky states win — never silently un-pin, un-mute, or bury an open
        // thread because the row it merged into happened to be resolved.
        $attrs['pinned_at'] = $all->pluck('pinned_at')->filter()->sort()->first();
        $attrs['muted_at']  = $all->pluck('muted_at')->filter()->sort()->first();
        $attrs['archived']  = $all->every(fn ($c) => (bool) $c->archived);

        if ($all->contains(fn ($c) => in_array($c->inbox_status, ['open', 'pending'], true))) {
            $attrs['inbox_status'] = $all->contains(fn ($c) => $c->inbox_status === 'open') ? 'open' : 'pending';
        }

        // Keep an existing assignment rather than dropping the thread back into
        // the unassigned queue.
        foreach (['assignee_user_id', 'assignee_team_id', 'assignee_agent_id'] as $col) {
            if (!$primary->{$col}) {
                $found = $all->pluck($col)->filter()->first();
                if ($found) $attrs[$col] = $found;
            }
        }

        $priorities = array_flip(Conversation::PRIORITIES);
        $topPriority = $all->pluck('priority')->filter()
            ->sortByDesc(fn ($p) => $priorities[$p] ?? 0)->first();
        if ($topPriority) $attrs['priority'] = $topPriority;

        // Preview follows the newest surviving bubble, so the queue row reads
        // as the real last message instead of whichever shell won before.
        if (Schema::hasTable('inbox_messages')) {
            $latest = DB::table('inbox_messages')
                ->where('conversation_id', $primary->id)
                ->whereNull('deleted_at')
                ->orderByDesc('id')
                ->value('body');

            if ($latest !== null) {
                try {
                    $attrs['preview'] = (string) decrypt($latest);
                } catch (\Throwable $e) {
                    // Pre-encryption row — store as-is.
                    $attrs['preview'] = (string) $latest;
                }
            }
        }

        $primary->forceFill($attrs)->save();
    }

    /**
     * Pick the JID pair to keep. The phone form is preferred for raw_jid
     * (outbound routing uses it); the other distinct form is parked on alt_jid
     * so a lookup on either shape lands on this row.
     *
     * @return array{0: ?string, 1: ?string}
     */
    private function reconcileJids(Collection $all): array
    {
        $jids = collect();
        foreach ($all as $c) {
            foreach ([$c->raw_jid, $c->alt_jid] as $j) {
                $j = trim((string) $j);
                if ($j !== '') $jids->push($j);
            }
        }

        $jids = $jids->unique()->values();
        if ($jids->isEmpty()) {
            return [null, null];
        }

        $phone = $jids->first(fn ($j) => str_contains($j, '@s.whatsapp.net'))
              ?? $jids->first(fn ($j) => !str_contains($j, '@'))
              ?? $jids->first();

        $alt = $jids->first(fn ($j) => $j !== $phone && $this->digits($j) !== $this->digits($phone))
            ?? $jids->first(fn ($j) => $j !== $phone);

        return [$phone, $alt];
    }

    /**
     * A thread is ORPHANED when its Unofficial-API device row is gone. It can
     * never be replied to again — deviceAlive() already hides it from the queue
     * — so its history is effectively lost until it is folded into a live
     * thread. WABA/Twilio rows key device_id to a different table and are never
     * treated as orphans here; nor are device-less legacy rows.
     */
    private function isOrphan(Conversation $c, array $liveDeviceIds): bool
    {
        $provider = (string) ($c->provider ?: 'baileys');
        if (!in_array($provider, ['baileys', ''], true)) {
            return false;
        }
        $deviceId = (int) $c->device_id;
        return $deviceId > 0 && !isset($liveDeviceIds[$deviceId]);
    }

    /** @return array<int, true> device ids that still exist, as a lookup set */
    private function liveDeviceIds(Collection $rows): array
    {
        $ids = $rows->pluck('device_id')->filter()->unique()->values()->all();
        if (!$ids) {
            return [];
        }
        return Device::whereIn('id', $ids)->pluck('id')
            ->mapWithKeys(fn ($id) => [(int) $id => true])->all();
    }

    /**
     * Fold orphaned threads into the live cluster for the same customer.
     *
     * This is the ONE place engines are allowed to mix, and only in the
     * direction that cannot lose anything: a dead Unofficial thread joining the
     * live thread that replaced it. That is exactly the "connected Unofficial,
     * chatted, then moved the number to WABA and removed the device" migration.
     *
     * Ambiguity is refused. If a customer's identity matches two different live
     * clusters (say the workspace runs the same customer on two live numbers)
     * we cannot know which one inherits the history, so the orphan is left
     * alone rather than guessed into the wrong thread.
     *
     * @param  Collection<int, array{partition: string, rows: Collection}>  $clusters
     * @return Collection<int, array{partition: string, rows: Collection}>
     */
    private function attachOrphans(Collection $clusters, Collection $orphans): Collection
    {
        if ($orphans->isEmpty()) {
            return $clusters;
        }

        // Plain array, not a Collection: the loop writes back into
        // $list[$i]['rows'], and a nested write through Collection's ArrayAccess
        // silently does nothing ("indirect modification has no effect").
        $list = $clusters->values()->all();
        $unattached = collect();

        foreach ($orphans as $orphan) {
            $identities = $this->identitiesOf($orphan);
            if (!$identities) {
                continue;
            }

            $matchedIdx = [];
            foreach ($list as $i => $cluster) {
                foreach ($cluster['rows'] as $row) {
                    if (array_intersect($identities, $this->identitiesOf($row))) {
                        $matchedIdx[] = $i;
                        break;
                    }
                }
            }

            if (count($matchedIdx) === 1) {
                $i = $matchedIdx[0];
                $list[$i]['rows'] = $list[$i]['rows']->push($orphan)->values();
            } elseif (!$matchedIdx) {
                $unattached->push($orphan);
            }
            // >1 match: genuinely ambiguous, leave it out.
        }

        $clusters = collect($list);

        // Orphans with no live counterpart can still be duplicates of EACH
        // OTHER — but only within the SAME dead device. Two dead threads on two
        // different (deleted) business numbers are separate conversations that
        // happen to share a customer, exactly as they would be if both numbers
        // were still connected. Grouping by device_id first keeps that true.
        foreach ($unattached->groupBy(fn (Conversation $c) => (int) $c->device_id) as $deviceId => $sameDevice) {
            foreach ($this->unionByIdentity($sameDevice) as $cluster) {
                if ($cluster->count() > 1) {
                    $clusters->push(['partition' => 'orphan:' . $deviceId, 'rows' => $cluster->values()]);
                }
            }
        }

        return $clusters;
    }

    /**
     * Union-find over shared JID identities — two conversations land in the
     * same cluster when any digit-form they carry matches.
     *
     * @return Collection<int, Collection<int, Conversation>>
     */
    private function unionByIdentity(Collection $convos): Collection
    {
        $parent = [];
        $find = function ($x) use (&$parent, &$find) {
            while (($parent[$x] ?? $x) !== $x) {
                $parent[$x] = $parent[$parent[$x]] ?? $parent[$x];
                $x = $parent[$x];
            }
            return $x;
        };
        $union = function ($a, $b) use (&$parent, $find) {
            $ra = $find($a); $rb = $find($b);
            if ($ra !== $rb) $parent[$rb] = $ra;
        };

        $byIdentity = [];

        foreach ($convos as $c) {
            $id = 'c' . $c->id;
            $parent[$id] ??= $id;

            foreach ($this->identitiesOf($c) as $identity) {
                if (isset($byIdentity[$identity])) {
                    $union($byIdentity[$identity], $id);
                } else {
                    $byIdentity[$identity] = $id;
                }
            }
        }

        return $convos
            ->groupBy(fn ($c) => $find('c' . $c->id))
            ->values();
    }

    /**
     * Every digit-form this conversation is known by. An empty result means the
     * row has no usable identity and is left alone rather than guessed at.
     *
     * @return array<int, string>
     */
    private function identitiesOf(Conversation $c): array
    {
        $out = [];
        foreach ([$c->raw_jid, $c->alt_jid] as $jid) {
            $d = $this->digits((string) $jid);
            // Below 8 digits is noise, not a phone or a LID.
            if ($d !== '' && strlen($d) >= 8) {
                $out[] = $d;
            }
        }
        return array_values(array_unique($out));
    }

    /**
     * device_id is polymorphic — `devices` for Unofficial API, and
     * `wa_provider_configs` for WABA/Twilio, two tables with overlapping
     * auto-increment ids. Key the map by provider so an id collision between
     * them can never resolve to the wrong business number.
     *
     * @return array<string, string>
     */
    private function businessPhoneMap(Collection $rows): array
    {
        $map = [];

        $deviceIds = $rows->where('provider', 'baileys')->pluck('device_id')
            ->merge($rows->whereNull('provider')->pluck('device_id'))
            ->filter()->unique()->values()->all();

        $cfgIds = $rows->whereIn('provider', ['waba', 'twilio'])->pluck('device_id')
            ->filter()->unique()->values()->all();

        if ($deviceIds) {
            foreach (Device::whereIn('id', $deviceIds)->get(['id', 'country_code', 'phone_number']) as $d) {
                $phone = $this->digits((string) ($d->country_code . $d->phone_number));
                if ($phone === '') continue;
                $map['baileys:' . $d->id] = $phone;
                $map[':' . $d->id]        = $phone;   // legacy NULL provider
            }
        }

        if ($cfgIds) {
            foreach (WaProviderConfig::whereIn('id', $cfgIds)->get(['id', 'provider', 'phone_number']) as $cfg) {
                $phone = $this->digits((string) $cfg->phone_number);
                if ($phone === '') continue;
                $map[((string) $cfg->provider) . ':' . $cfg->id] = $phone;
            }
        }

        return $map;
    }

    private function messageCount(array $conversationIds): int
    {
        if (!Schema::hasTable('inbox_messages')) {
            return 0;
        }

        return (int) DB::table('inbox_messages')
            ->whereIn('conversation_id', $conversationIds)
            ->whereNull('deleted_at')
            ->count();
    }

    /** A readable number for the confirm dialog, masked the way the inbox masks it. */
    private function displayPhone(Collection $cluster): string
    {
        foreach ($cluster as $c) {
            foreach ([$c->raw_jid, $c->alt_jid] as $jid) {
                $d = $this->digits((string) $jid);
                if ($d !== '' && strlen($d) <= 15) {
                    return function_exists('mask_phone') ? (string) mask_phone($d) : $d;
                }
            }
        }
        return '';
    }

    private function maxDate(Collection $all, string $column)
    {
        return $all->pluck($column)->filter()->sortDesc()->first();
    }

    private function digits(string $v): string
    {
        return (string) preg_replace('/\D+/', '', $v);
    }
}
