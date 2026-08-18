<?php

namespace App\Http\Controllers;

use Illuminate\Contracts\View\View;

/**
 * Renders the read-only prototype views for the operator-facing app.
 * Each method just returns a Blade view — wire DB-backed controllers in later.
 */
class UserPagesController extends Controller
{
    public function dashboard(): View
    {
        $user = \Illuminate\Support\Facades\Auth::user();
        $credits = (int) ($user?->wallet_credits ?? 0);
        $perMessage = max(1, (int) \App\Models\SystemSetting::get('credits_per_message', 1));

        $wsId = $user?->current_workspace_id;
        $userIdsForWs = $wsId
            ? \App\Models\User::where('current_workspace_id', $wsId)->pluck('id')->all()
            : [$user?->id];

        // Workspace-scope filter — matches workspace_id directly so
        // teammates who currently have a different active workspace
        // still count toward THIS workspace's rows, AND a user's rows
        // in OTHER workspaces don't bleed in. Falls back to the legacy
        // user_id list for pre-migration rows with NULL workspace_id.
        $wsScope = function ($q) use ($wsId, $userIdsForWs) {
            if ($wsId) {
                $q->where(function ($qq) use ($wsId, $userIdsForWs) {
                    $qq->where('workspace_id', $wsId)
                       ->orWhere(function ($qqq) use ($userIdsForWs) {
                           $qqq->whereNull('workspace_id')->whereIn('user_id', $userIdsForWs);
                       });
                });
            } else {
                $q->whereIn('user_id', $userIdsForWs);
            }
        };

        // inbox_messages has NO workspace_id column of its own (it
        // lives on the parent Conversation). Filter through the
        // relationship for any InboxMessage query.
        $wsScopeInbox = function ($q) use ($wsId, $userIdsForWs) {
            if ($wsId) {
                $q->whereHas('conversation', function ($cq) use ($wsId, $userIdsForWs) {
                    $cq->where(function ($qq) use ($wsId, $userIdsForWs) {
                        $qq->where('workspace_id', $wsId)
                           ->orWhere(function ($qqq) use ($userIdsForWs) {
                               $qqq->whereNull('workspace_id')->whereIn('user_id', $userIdsForWs);
                           });
                    });
                });
            } else {
                $q->whereIn('user_id', $userIdsForWs);
            }
        };

        // Window: last 7d to match the "Last 7 days" pill in the header.
        $now    = now();
        $to     = $now->copy()->endOfDay();
        $from   = $now->copy()->subDays(7)->startOfDay();
        $prevTo = $from->copy()->subSecond();
        $prevFrom = $prevTo->copy()->subDays(7)->startOfDay();

        $msgQ = \App\Models\Message::query()
            ->where($wsScope)
            ->forCurrentEngine()
            ->whereBetween('created_at', [$from, $to]);
        $imQ  = \App\Models\InboxMessage::query()
            ->where($wsScopeInbox)
            ->forCurrentEngine()
            ->whereBetween('created_at', [$from, $to]);

        $sent24h      = (clone $msgQ)->where('direction', 'out')->count()
                      + (clone $imQ)->where('direction', 'out')->count();
        $delivered24h = (clone $msgQ)->where('direction', 'out')->whereIn('status', ['delivered','read','sent'])->count()
                      + (clone $imQ)->where('direction', 'out')->whereIn('status', ['delivered','read','sent'])->count();
        $read24h      = (clone $msgQ)->where('direction', 'out')->where('status', 'read')->count()
                      + (clone $imQ)->where('direction', 'out')->where('status', 'read')->count();
        $failed24h    = (clone $msgQ)->where('direction', 'out')->where('status', 'failed')->count()
                      + (clone $imQ)->where('direction', 'out')->where('status', 'failed')->count();
        $replies24h   = (clone $msgQ)->where('direction', 'in')->count()
                      + (clone $imQ)->where('direction', 'in')->count();

        $deliverabilityPct = $sent24h > 0 ? round($delivered24h / $sent24h * 100, 1) : 0;
        $readRatePct       = $sent24h > 0 ? round($read24h / $sent24h * 100, 1) : 0;
        $replyRatePct      = $sent24h > 0 ? round($replies24h / $sent24h * 100, 1) : 0;
        $optOutPct         = 0.0;

        // Previous 7 days for trend pills. forCurrentEngine() (widened in
        // Phase 6 to the ENABLED ENGINE SET) is applied to BOTH windows so
        // the trend delta compares like-for-like (enabled set vs enabled
        // set), matching the current-window $msgQ/$imQ above.
        $prevMsgQ = \App\Models\Message::query()
            ->where($wsScope)
            ->forCurrentEngine()
            ->whereBetween('created_at', [$prevFrom, $prevTo]);
        $prevImQ  = \App\Models\InboxMessage::query()
            ->where($wsScopeInbox)
            ->forCurrentEngine()
            ->whereBetween('created_at', [$prevFrom, $prevTo]);
        $prevSent     = (clone $prevMsgQ)->where('direction', 'out')->count()
                      + (clone $prevImQ)->where('direction', 'out')->count();
        $prevDelivered = (clone $prevMsgQ)->where('direction', 'out')->whereIn('status', ['delivered','read','sent'])->count()
                      + (clone $prevImQ)->where('direction', 'out')->whereIn('status', ['delivered','read','sent'])->count();
        $prevRead     = (clone $prevMsgQ)->where('direction', 'out')->where('status', 'read')->count()
                      + (clone $prevImQ)->where('direction', 'out')->where('status', 'read')->count();
        $prevReadRate = $prevSent > 0 ? round($prevRead / $prevSent * 100, 1) : 0;

        $pct = function (float $now, float $prev): float {
            if ($prev <= 0) return $now > 0 ? 100.0 : 0.0;
            return round(($now - $prev) / $prev * 100, 1);
        };
        $deltaSent     = $pct($sent24h, $prevSent);
        $deltaReadRate = round($readRatePct - $prevReadRate, 1);

        // Daily series (8 days = 7-day window + today) for the throughput chart.
        $days = 8;
        $dailyMsgQ = $msgQ;
        $dailyImQ  = $imQ;
        $dailyLabels = [];
        $dailySent = []; $dailyDelivered = []; $dailyFailed = [];
        for ($i = 0; $i < $days; $i++) {
            $dayStart = $now->copy()->subDays($days - 1 - $i)->startOfDay();
            $dayEnd   = $dayStart->copy()->endOfDay();
            $dailyLabels[]    = $dayStart->format('D');
            $dailySent[]      = \App\Models\Message::where($wsScope)->forCurrentEngine()->where('direction','out')->whereBetween('created_at', [$dayStart, $dayEnd])->count()
                              + \App\Models\InboxMessage::where($wsScopeInbox)->forCurrentEngine()->where('direction','out')->whereBetween('created_at', [$dayStart, $dayEnd])->count();
            $dailyDelivered[] = \App\Models\Message::where($wsScope)->forCurrentEngine()->where('direction','out')->whereIn('status', ['delivered','read','sent'])->whereBetween('created_at', [$dayStart, $dayEnd])->count()
                              + \App\Models\InboxMessage::where($wsScopeInbox)->forCurrentEngine()->where('direction','out')->whereIn('status', ['delivered','read','sent'])->whereBetween('created_at', [$dayStart, $dayEnd])->count();
            $dailyFailed[]    = \App\Models\Message::where($wsScope)->forCurrentEngine()->where('direction','out')->where('status','failed')->whereBetween('created_at', [$dayStart, $dayEnd])->count()
                              + \App\Models\InboxMessage::where($wsScopeInbox)->forCurrentEngine()->where('direction','out')->where('status','failed')->whereBetween('created_at', [$dayStart, $dayEnd])->count();
        }

        // ─── Throughput ranges (24h / 7d / 30d / qtd) for the range filter ───
        // Build each range's buckets with a single pair of lean queries
        // (Message + InboxMessage) that pull only created_at + status for
        // outbound rows in the window, then bucket in PHP. Same workspace
        // scope + status logic as the 7d series above.
        $bucketRange = function (\Carbon\Carbon $rangeFrom, \Carbon\Carbon $rangeTo, string $unit, int $bucketCount, string $labelFmt)
            use ($wsScope, $wsScopeInbox) {
            // Pre-build empty buckets + their boundaries.
            $labels = [];
            $sent = $delivered = $failed = [];
            for ($i = 0; $i < $bucketCount; $i++) {
                $start = $unit === 'hour'
                    ? $rangeFrom->copy()->addHours($i)
                    : $rangeFrom->copy()->addDays($i);
                $labels[] = $start->format($labelFmt);
                $sent[$i] = 0; $delivered[$i] = 0; $failed[$i] = 0;
            }
            $deliveredStatuses = ['delivered', 'read', 'sent'];

            $fromTs   = $rangeFrom->getTimestamp();
            $fromDay  = $rangeFrom->copy()->startOfDay()->getTimestamp();
            $place = function (\Carbon\Carbon $start) use ($unit, $fromTs, $fromDay, $bucketCount) {
                $idx = $unit === 'hour'
                    ? (int) floor(($start->getTimestamp() - $fromTs) / 3600)
                    : (int) floor(($start->copy()->startOfDay()->getTimestamp() - $fromDay) / 86400);
                return ($idx >= 0 && $idx < $bucketCount) ? $idx : null;
            };

            $apply = function ($rows) use (&$sent, &$delivered, &$failed, $deliveredStatuses, $place) {
                foreach ($rows as $row) {
                    $ts = $row->created_at instanceof \Carbon\Carbon
                        ? $row->created_at
                        : \Carbon\Carbon::parse($row->created_at);
                    $idx = $place($ts);
                    if ($idx === null) continue;
                    $sent[$idx]++;
                    if ($row->status === 'failed') {
                        $failed[$idx]++;
                    } elseif (in_array($row->status, $deliveredStatuses, true)) {
                        $delivered[$idx]++;
                    }
                }
            };

            $apply(\App\Models\Message::query()->where($wsScope)->forCurrentEngine()
                ->where('direction', 'out')
                ->whereBetween('created_at', [$rangeFrom, $rangeTo])
                ->get(['created_at', 'status']));
            $apply(\App\Models\InboxMessage::query()->where($wsScopeInbox)->forCurrentEngine()
                ->where('direction', 'out')
                ->whereBetween('created_at', [$rangeFrom, $rangeTo])
                ->get(['created_at', 'status']));

            return [
                'labels'    => $labels,
                'sent'      => array_values($sent),
                'delivered' => array_values($delivered),
                'failed'    => array_values($failed),
            ];
        };

        $r24From = $now->copy()->subHours(23)->startOfHour();
        $r24To   = $now->copy()->endOfHour();
        $r7From  = $now->copy()->subDays(7)->startOfDay();
        $r30From = $now->copy()->subDays(29)->startOfDay();
        $qtdFrom = $now->copy()->firstOfQuarter()->startOfDay();
        $qtdDays = (int) $qtdFrom->copy()->startOfDay()->diffInDays($now->copy()->startOfDay()) + 1;

        $throughputRanges = [
            '24h' => $bucketRange($r24From, $r24To, 'hour', 24, 'H:00'),
            '7d'  => $bucketRange($r7From, $r24To->copy()->endOfDay(), 'day', 8, 'D'),
            '30d' => $bucketRange($r30From, $r24To->copy()->endOfDay(), 'day', 30, 'M d'),
            'qtd' => $bucketRange($qtdFrom, $r24To->copy()->endOfDay(), 'day', max($qtdDays, 1), 'M d'),
        ];

        // Per-hour sent series for KPI sparkline (last 20h, matching old data length).
        $sparkData = [];
        for ($h = 19; $h >= 0; $h--) {
            $slotStart = $now->copy()->subHours($h + 1);
            $slotEnd   = $slotStart->copy()->addHour();
            $sparkData[] = \App\Models\Message::where($wsScope)->forCurrentEngine()->where('direction','out')->whereBetween('created_at', [$slotStart, $slotEnd])->count()
                         + \App\Models\InboxMessage::where($wsScopeInbox)->forCurrentEngine()->where('direction','out')->whereBetween('created_at', [$slotStart, $slotEnd])->count();
        }

        // Peak/avg over last-24h window for the throughput card.
        $hourlySent = [];
        $last24hFrom = $now->copy()->subDay();
        for ($h = 0; $h < 24; $h++) {
            $slotStart = $last24hFrom->copy()->addHours($h);
            $slotEnd   = $slotStart->copy()->addHour();
            $hourlySent[] = [
                'label' => $slotStart->format('D H:00'),
                'sent'  => \App\Models\Message::where($wsScope)->where('direction','out')->whereBetween('created_at', [$slotStart, $slotEnd])->count()
                         + \App\Models\InboxMessage::where($wsScopeInbox)->where('direction','out')->whereBetween('created_at', [$slotStart, $slotEnd])->count(),
            ];
        }
        $peakHour = collect($hourlySent)->sortByDesc('sent')->first() ?: ['label' => '—', 'sent' => 0];
        $avgPerHour = (int) round(array_sum(array_column($hourlySent, 'sent')) / 24);

        // ─── Contacts roll-up ───
        $contactsQ = $user
            ? \App\Models\Contact::query()->where($wsScope)
            : null;
        $contactsTotal     = $contactsQ ? (clone $contactsQ)->count() : 0;
        $contactsOpted     = $contactsQ ? (clone $contactsQ)->where('is_unsubscribed', 0)->count() : 0;
        $contactsBlocked   = $contactsQ ? (clone $contactsQ)->where('is_unsubscribed', 1)->count() : 0;
        $contactsSubscribed= $contactsOpted; // Same column — unsubscribed=0 means subscribed
        $prevContacts      = $contactsQ ? (clone $contactsQ)->where('created_at', '<', $from)->count() : 0;
        $newContacts       = max(0, $contactsTotal - $prevContacts);
        $deltaContacts     = $pct($contactsTotal, $prevContacts);

        // ─── Broadcasts roll-up ───
        // Engine-filter so a WABA workspace doesn't count Baileys
        // broadcasts in its KPI tiles.
        $bcQ = \App\Models\Broadcast::query()->where($wsScope)->forCurrentEngine();
        $broadcastsRunning   = (clone $bcQ)->whereIn('status', ['sending','running','processing','active'])->count();
        $broadcastsScheduled = (clone $bcQ)->whereIn('status', ['scheduled','pending','queued'])->count();
        $broadcastsPaused    = (clone $bcQ)->whereIn('status', ['paused','failed','cancelled'])->count();
        $broadcastsTotal     = (clone $bcQ)->count();

        // ─── Active campaigns list (top 4 most-recent) ───
        $activeCampaigns = [];
        $bcRows = (clone $bcQ)->orderByDesc('updated_at')->limit(4)->get();
        foreach ($bcRows as $b) {
            $total   = (int) ($b->total_recipients ?: 0);
            $done    = (int) ($b->success_count ?: 0);
            $pctDone = $total > 0 ? round($done / $total * 100) : 0;
            $tpl     = $b->template_id ? \App\Models\WaTemplate::find($b->template_id) : null;
            $status  = strtolower((string) ($b->status ?: 'unknown'));
            $statusPill = match (true) {
                in_array($status, ['sending','running','processing','active'], true) => ['Sending', 'bg-wa-green/15 text-wa-deep'],
                in_array($status, ['scheduled','pending','queued'], true)            => ['Scheduled', 'bg-accent-amber/20 text-[#8B5A14]'],
                in_array($status, ['paused','cancelled'], true)                      => ['Paused', 'bg-accent-coral/15 text-accent-coral'],
                in_array($status, ['failed'], true)                                  => ['Failed', 'bg-accent-coral/15 text-accent-coral'],
                in_array($status, ['completed','done','sent'], true)                 => ['Completed', 'bg-paper-100 text-ink-700'],
                default                                                              => [ucfirst($status), 'bg-paper-100 text-ink-700'],
            };
            $activeCampaigns[] = [
                'name'       => $b->name,
                'template'   => $tpl?->template_name,
                'category'   => $tpl?->category ?: '—',
                'total'      => $total,
                'done'       => $done,
                'pct'        => $pctDone,
                'status'     => $statusPill[0],
                'status_css' => $statusPill[1],
            ];
        }

        // ─── Devices — engine-aware (ENABLED SET) ───
        // Dashboard Devices section reflects EVERY engine the workspace is
        // running, not just its single default. For each enabled engine we
        // count senders: Baileys → `devices` rows, WABA / Twilio → that
        // provider's wa_provider_configs rows. The TOTAL (slot cap) and the
        // ONLINE count sum across the enabled set; the per-row list comes
        // from WorkspaceEngine::senders() (connected senders, default-first,
        // already merged across engines). Single-engine workspaces are
        // byte-identical (the sum over [default] == the old single branch).
        $deviceEngines = \App\Services\WorkspaceEngine::enginesFor($wsId);
        $devicesTotalAll = 0;   // total senders (connected + disconnected)
        $devicesActive   = 0;   // connected + active
        foreach ($deviceEngines as $eng) {
            if ($eng === \App\Services\WorkspaceEngine::ENGINE_BAILEYS) {
                // TENANT ISOLATION — devices must NOT use $wsScope.
                // $wsScope's orphan leg matches whereNull('workspace_id') for
                // EVERY user whose current_workspace_id equals this workspace.
                // That is right for shared workspace data (messages, contacts,
                // broadcasts) but wrong for a device: a NULL-workspace device is
                // a PERSONAL pairing. A platform admin left parked on a client's
                // workspace (which is the normal state until they click Stop
                // impersonating) therefore leaked their own Unofficial number
                // into the client's dashboard. Device::forCurrentWorkspace()
                // keys the orphan fallback to the VIEWER and is the one audited
                // rule for this — use it rather than a fourth local copy.
                $bDevices = \App\Models\Device::forCurrentWorkspace()->get(['status', 'active']);
                $devicesTotalAll += $bDevices->count();
                $devicesActive   += $bDevices->where('status', 'connected')->where('active', true)->count();
            } else {
                $eCfgs = \App\Models\WaProviderConfig::query()
                    ->where('workspace_id', $wsId)
                    ->where('provider', $eng)
                    ->get(['status']);
                $devicesTotalAll += $eCfgs->count();
                $devicesActive   += $eCfgs->where('status', \App\Models\WaProviderConfig::STATUS_CONNECTED)->count();
            }
        }
        $deviceSlotCap = max($devicesTotalAll, 6);
        // Per-row list: up to 4 senders across the enabled set INCLUDING
        // disconnected ones, so the card's connecting/offline (amber/coral)
        // states still render — default-engine-first then active-first. Mirrors
        // the analytics device card. Single-engine workspaces are byte-identical
        // to the old Device take(4) (real status/is_online, not hard-coded).
        $devicesList = [];
        foreach ($deviceEngines as $eng) {
            if (count($devicesList) >= 4) break;
            $remaining = 4 - count($devicesList);
            if ($eng === \App\Services\WorkspaceEngine::ENGINE_BAILEYS) {
                // Tenant isolation — see the note on the device counter above.
                $rows = \App\Models\Device::forCurrentWorkspace()
                    ->orderByDesc('active')
                    ->orderByDesc('last_seen_at')
                    ->orderByDesc('id')
                    ->limit($remaining)
                    ->get();
                foreach ($rows as $d) {
                    $total  = max(1, (int) ($d->sent_24h ?: 1));
                    $failed = (int) ($d->failed_24h ?: 0);
                    $deliv  = max(0.0, round((1 - $failed / $total) * 100, 1));
                    $devicesList[] = [
                        'phone'    => $d->phone_number ? ('+' . ltrim($d->country_code ?? '', '+') . ' ' . $d->phone_number) : '—',
                        'label'    => $d->device_name ?: ($d->phone_number ?: ('Device #' . $d->id)),
                        'region'   => $d->region,
                        'sent_24h' => (int) ($d->sent_24h ?: 0),
                        'deliv_pct'=> $deliv,
                        'status'   => (string) $d->status,
                        'is_online'=> $d->status === 'connected' && (bool) $d->active,
                        'engine'   => $eng,
                    ];
                }
            } else {
                $desc = \App\Services\WorkspaceEngine::descriptor($eng);
                $cfgs = \App\Models\WaProviderConfig::query()
                    ->where('workspace_id', $wsId)
                    ->where('provider', $eng)
                    ->orderByDesc('connected_at')
                    ->limit($remaining)
                    ->get();
                foreach ($cfgs as $cfg) {
                    $devicesList[] = [
                        'phone'    => $cfg->phone_number ? ('+' . preg_replace('/\D+/', '', (string) $cfg->phone_number)) : '—',
                        'label'    => $cfg->display_label ?: ($desc['label'] . ' · ' . ($cfg->phone_number ?: ('#' . $cfg->id))),
                        'region'   => null,
                        'sent_24h' => 0,
                        'deliv_pct'=> 100.0,
                        'status'   => (string) $cfg->status,
                        'is_online'=> $cfg->status === \App\Models\WaProviderConfig::STATUS_CONNECTED,
                        'engine'   => $eng,
                    ];
                }
            }
        }

        // ─── Templates ───
        // providerLive(): only templates whose provider config is still connected
        // (or provider-agnostic). Without it the dashboard kept counting templates
        // whose WABA device was removed, so it showed a count while the Templates
        // page (which uses providerLive) was empty.
        $allTemplates = \App\Models\WaTemplate::query()->where($wsScope)->providerLive()->get();
        $templatesCount = $allTemplates->count();
        $topTemplates = [];
        $tplRows = \App\Models\WaTemplate::query()
            ->where($wsScope)
            ->providerLive()
            ->orderByDesc('updated_at')
            ->limit(5)
            ->get(['id', 'template_name', 'category', 'language', 'template_type', 'status']);
        foreach ($tplRows as $t) {
            $sends = (clone $msgQ)->where('direction', 'out')->where('template_id', $t->id)->count();
            $deliv = (clone $msgQ)->where('direction', 'out')->where('template_id', $t->id)->whereIn('status', ['delivered','read','sent'])->count();
            $read  = (clone $msgQ)->where('direction', 'out')->where('template_id', $t->id)->where('status', 'read')->count();
            $ctr   = $sends > 0 ? round($read / $sends * 100, 1) : null;
            $perf  = $sends > 0 ? min(100, (int) round($deliv / $sends * 100)) : 0;
            $topTemplates[] = [
                'id'        => $t->id,
                'name'      => $t->template_name,
                'category'  => ucfirst((string) ($t->category ?: '—')),
                'type'      => ucfirst((string) ($t->template_type ?: '—')),
                'language'  => $t->language,
                'sends'     => $sends,
                'delivered' => $deliv,
                'read'      => $read,
                'ctr'       => $ctr,
                'perf'      => $perf,
                'status'    => strtolower((string) ($t->status ?: 'pending')),
            ];
        }
        usort($topTemplates, fn ($a, $b) => $b['sends'] - $a['sends']);

        // ─── Live inbox preview (top 3 conversations by last_message_at) ───
        $liveConvos = [];
        $oldestUnreadAgo = null;
        $unreadCount = 0;
        if ($wsId) {
            // Use the Team Inbox's EXACT scope + metric so the dashboard's
            // "N unread" agrees with the inbox rail. Two bugs made it disagree
            // (e.g. dashboard "180 unread" while the inbox showed ~480):
            //   1. it SUMMED unread_count (total unread MESSAGES) while the inbox
            //      COUNTS unread chats — different meaning, different number;
            //   2. it omitted deviceAlive() + archived=false, so it counted
            //      threads the inbox hides (dead device / archived).
            // Mirroring TeamInboxController's $scoped() closure fixes both.
            $inboxScope = fn () => \App\Models\Conversation::query()
                ->where('workspace_id', $wsId)
                ->forCurrentEngine()
                ->deviceAlive()
                ->where('archived', false);

            $convos = $inboxScope()
                ->orderByDesc('last_message_at')
                ->limit(3)
                ->get(['id', 'title', 'raw_jid', 'last_message_at', 'unread_count']);
            $unreadCount = (int) $inboxScope()
                ->where('unread_count', '>', 0)
                ->count();
            $oldestUnread = $inboxScope()
                ->where('unread_count', '>', 0)
                ->orderBy('last_message_at')
                ->first();
            $oldestUnreadAgo = $oldestUnread?->last_message_at?->diffForHumans(null, true);
            foreach ($convos as $c) {
                $lastMsg = \App\Models\InboxMessage::where('conversation_id', $c->id)
                    ->orderByDesc('created_at')
                    ->first(['body', 'created_at', 'direction']);
                $liveConvos[] = [
                    'title'    => $c->title ?: ('+' . $c->raw_jid),
                    'initials' => strtoupper(substr(preg_replace('/[^A-Za-z]/', '', (string) ($c->title ?: 'NA')), 0, 2)) ?: 'NA',
                    'preview'  => $lastMsg ? \Illuminate\Support\Str::limit((string) $lastMsg->body, 60) : '—',
                    'ago'      => $c->last_message_at?->diffForHumans(null, true) ?: '—',
                    'unread'   => (int) ($c->unread_count ?? 0),
                ];
            }
        }

        // ─── Geo (by contact.country_code, then by device.region as fallback) ───
        $geoRows = [];
        if ($contactsQ) {
            $rows = (clone $contactsQ)
                ->whereNotNull('country_code')
                ->where('country_code', '!=', '')
                ->select('country_code', \DB::raw('COUNT(*) as n'))
                ->groupBy('country_code')
                ->orderByDesc('n')
                ->limit(6)
                ->get();
            foreach ($rows as $r) {
                // Resolve to ISO so the chip shows "IN" not "91". Falls
                // through to the raw value for unknown codes so unmapped
                // markets still render (rare emerging-market dial codes).
                $iso = $this->dialToIso((string) $r->country_code);
                $geoRows[] = [
                    'code'  => $iso,
                    'name'  => $this->countryName((string) $r->country_code),
                    'flag'  => $this->countryFlag((string) $r->country_code),
                    'count' => (int) $r->n,
                ];
            }
        }
        $geoTotal = array_sum(array_column($geoRows, 'count'));
        $geoMax   = $geoTotal > 0 ? max(array_column($geoRows, 'count')) : 0;
        $geoOther = max(0, $contactsTotal - $geoTotal);
        $geoMarkets = $contactsQ
            ? (clone $contactsQ)->whereNotNull('country_code')->where('country_code', '!=', '')->distinct('country_code')->count('country_code')
            : 0;

        // ─── Funnel (7d) ───
        // Engine-filter so the numerator matches the sent/delivered
        // denominators in the funnel — without this the JOIN returned
        // inbound from ALL engines while $sent24h is engine-scoped,
        // producing > 100% reply rates (e.g. 77 replies / 72 sends).
        // Multi-engine: the denominators ($sent24h/$delivered24h/$replies24h)
        // are scoped via InboxMessage::forCurrentEngine() = the ENABLED SET, so
        // the numerator must use the SAME set (whereIn enginesFor), not the
        // single default — otherwise multi-engine workspaces undercount
        // "engaged" and the rate breaks again. enginesFor() is never empty.
        $funnelEngines = $wsId
            ? \App\Services\WorkspaceEngine::enginesFor($wsId)
            : ['baileys'];
        $uniqueRepliers = $wsId
            ? \App\Models\InboxMessage::query()
                ->join('conversations', 'conversations.id', '=', 'inbox_messages.conversation_id')
                ->where('conversations.workspace_id', $wsId)
                ->whereIn('conversations.provider', $funnelEngines)
                ->whereIn('inbox_messages.provider', $funnelEngines)
                ->whereBetween('inbox_messages.created_at', [$from, $to])
                ->where('inbox_messages.direction', 'in')
                ->distinct('inbox_messages.conversation_id')
                ->count('inbox_messages.conversation_id')
            : 0;
        $pctOf = fn (int $n, int $base) => $base > 0 ? round($n / $base * 100, 1) : 0.0;
        $funnelSteps = [
            ['stage' => '01 · sent',      'label' => 'Outbound messages',     'count' => (int) $sent24h,       'pct' => 100.0,                                'drop' => 0],
            ['stage' => '02 · delivered', 'label' => 'Successfully delivered', 'count' => (int) $delivered24h, 'pct' => $pctOf($delivered24h, $sent24h),     'drop' => max(0, $sent24h - $delivered24h)],
            ['stage' => '03 · replied',   'label' => 'Inbound replies',        'count' => (int) $replies24h,   'pct' => $pctOf($replies24h, $sent24h),       'drop' => max(0, $delivered24h - $replies24h)],
            ['stage' => '04 · engaged',   'label' => 'Unique repliers',        'count' => (int) $uniqueRepliers,'pct' => $pctOf($uniqueRepliers, $sent24h),  'drop' => max(0, $replies24h - $uniqueRepliers)],
        ];
        $funnelEndPct = $funnelSteps[count($funnelSteps) - 1]['pct'];

        // ─── Activity log (from audit_logs, latest 6) ───
        // Filter out auth/session/infrastructure events — they're
        // logged for security purposes but are noise on the operator
        // dashboard. Customer-facing actions (sends, broadcasts,
        // template edits, etc.) are the relevant signal here.
        $events = [];
        if ($wsId) {
            $rows = \App\Models\AuditLog::query()
                ->where('workspace_id', $wsId)
                ->where('action', 'not like', 'auth.%')
                ->where('action', 'not like', 'session.%')
                ->where('action', 'not like', 'user.%')
                ->orderByDesc('created_at')
                ->limit(10)
                ->get();
            foreach ($rows as $r) {
                $actor = $r->actor_user_id ? \App\Models\User::find($r->actor_user_id) : null;
                $events[] = [
                    'action' => (string) $r->action,
                    'subject'=> (string) $r->subject_type,
                    'actor'  => $actor?->name ?: 'System',
                    'ago'    => $r->created_at?->diffForHumans(null, true) ?: '—',
                ];
            }
        }

        // ─── Integrations health ───
        $providerConfig = $wsId ? \App\Models\WaProviderConfig::query()->where('workspace_id', $wsId)->first() : null;
        $wooEvents24h = \App\Models\WebhookDelivery::query()
            ->whereBetween('fired_at', [$from, $to])
            ->count();
        $sheetsConnected = (bool) ($user?->sheets_api_key ?? null);
        $integrations = [
            ['code' => 'woo',    'name' => 'WooCommerce',   'badge' => 'Wo', 'bg' => '#7F54B3', 'connected' => $wooEvents24h > 0,            'detail' => $wooEvents24h.' events / 7d'],
            ['code' => 'shop',   'name' => 'Shopify',       'badge' => 'Sh', 'bg' => '#96BF48', 'connected' => false,                         'detail' => 'not connected'],
            ['code' => 'sheets', 'name' => 'Google Sheets', 'badge' => 'Sh', 'bg' => '#0F9D58', 'connected' => $sheetsConnected,             'detail' => $sheetsConnected ? 'API key issued' : 'not connected'],
            ['code' => 'meta',   'name' => 'Meta Catalog',  'badge' => 'FB', 'bg' => '#1877F2', 'connected' => $providerConfig && $providerConfig->isConnected(), 'detail' => $providerConfig?->isConnected() ? 'WABA online' : 'not connected'],
        ];
        $integrationsConnected = count(array_filter($integrations, fn ($i) => $i['connected']));
        $integrationsTotal     = 12; // Conceptual total surface area; the UI is "5/12" style.

        // ─── Flow Copilot card — real, workspace-scoped automation stats ───
        $flowQ = \App\Models\Flow::query()->where($wsScope);
        $copilotFlows  = (clone $flowQ)->count();
        $copilotActive = (clone $flowQ)->where('is_active', true)->count();
        $flowIds       = (clone $flowQ)->pluck('id');
        $copilotSubscribers = $flowIds->isEmpty()
            ? 0
            : \App\Models\FlowSubscriber::whereIn('flow_id', $flowIds)->count();

        // ─── Recent flows (dashboard list — fills the slot the geo card had) ───
        $recentFlows = [];
        try {
            $flowRows = \App\Models\Flow::query()->where($wsScope)
                ->orderByDesc('updated_at')->limit(6)->get();
            $rfIds = $flowRows->pluck('id');
            $rfSubs = $rfIds->isEmpty()
                ? collect()
                : \App\Models\FlowSubscriber::whereIn('flow_id', $rfIds)
                    ->selectRaw('flow_id, COUNT(*) AS c')->groupBy('flow_id')->pluck('c', 'flow_id');
            foreach ($flowRows as $f) {
                $steps = 0;
                try {
                    $g = $f->decoded_flow_data;
                    $steps = is_array($g) ? count($g['flowNodes'] ?? []) : 0;
                } catch (\Throwable $e) {}
                $recentFlows[] = [
                    'name'    => (string) ($f->flow_name ?: 'Untitled flow'),
                    'active'  => (bool) $f->is_active,
                    'subs'    => (int) ($rfSubs[$f->id] ?? 0),
                    'steps'   => $steps,
                    'trigger' => (string) ($f->trigger_kind ?: 'keyword'),
                ];
            }
        } catch (\Throwable $e) {
            $recentFlows = [];
        }

        // Greeting/date must read the operator's workspace timezone, not the
        // server's (config app.timezone) — otherwise the date card is off.
        $dashTz   = optional($user?->currentWorkspace)->timezone ?: config('app.timezone', 'UTC');
        $localNow = $now->copy()->setTimezone($dashTz);

        return view('user.dashboard.index', [
            'recentFlows'      => $recentFlows,
            'walletCredits'    => $credits,
            'creditsPerMessage'=> $perMessage,
            'estMessages'      => (int) floor($credits / $perMessage),
            // Identity
            'userName'         => $user?->name ?: 'there',
            'greeting'         => match (true) {
                $localNow->hour < 12 => 'Good morning',
                $localNow->hour < 17 => 'Good afternoon',
                default              => 'Good evening',
            },
            'today'            => $localNow->format('l, F j'),
            // Header KPIs
            'sent24h'          => (int) $sent24h,
            'delivered24h'     => (int) $delivered24h,
            'failed24h'        => (int) $failed24h,
            'replies24h'       => (int) $replies24h,
            'deliverabilityPct'=> $deliverabilityPct,
            'readRatePct'      => $readRatePct,
            'replyRatePct'     => $replyRatePct,
            'optOutPct'        => $optOutPct,
            'deltaSent'        => $deltaSent,
            'deltaReadRate'    => $deltaReadRate,
            // Contacts
            'contactsTotal'    => $contactsTotal,
            'contactsSubscribed'=> $contactsSubscribed,
            'contactsOpted'    => $contactsOpted,
            'contactsBlocked'  => $contactsBlocked,
            'deltaContacts'    => $deltaContacts,
            'newContacts'      => $newContacts,
            // Broadcasts/campaigns
            'broadcastsRunning'   => $broadcastsRunning,
            'broadcastsScheduled' => $broadcastsScheduled,
            'broadcastsPaused'    => $broadcastsPaused,
            'broadcastsTotal'     => $broadcastsTotal,
            'activeCampaigns'  => $activeCampaigns,
            // Devices
            'devicesList'      => $devicesList,
            'devicesActive'    => $devicesActive,
            'devicesTotal'     => $devicesTotalAll,
            'deviceSlotCap'    => $deviceSlotCap,
            // Templates
            'topTemplates'     => $topTemplates,
            'templatesCount'   => $templatesCount,
            // Throughput meta
            'avgPerHour'       => $avgPerHour,
            'peakHour'         => $peakHour,
            'dailyLabels'      => $dailyLabels,
            'dailySent'        => $dailySent,
            'dailyDelivered'   => $dailyDelivered,
            'dailyFailed'      => $dailyFailed,
            'throughputRanges' => $throughputRanges,
            'sparkData'        => $sparkData,
            // Live inbox
            'liveConvos'       => $liveConvos,
            'unreadCount'      => $unreadCount,
            'oldestUnreadAgo'  => $oldestUnreadAgo,
            // Geo
            'geoRows'          => $geoRows,
            'geoMax'           => $geoMax,
            'geoOther'         => $geoOther,
            'geoMarkets'       => $geoMarkets,
            // Funnel
            'funnelSteps'      => $funnelSteps,
            'funnelEndPct'     => $funnelEndPct,
            // Events
            'events'           => $events,
            // Integrations
            'integrations'         => $integrations,
            'integrationsConnected'=> $integrationsConnected,
            'integrationsTotal'    => $integrationsTotal,
            // Flow Copilot card
            'copilotFlows'         => $copilotFlows,
            'copilotActive'        => $copilotActive,
            'copilotSubscribers'   => $copilotSubscribers,
            // Sales Pipeline KPIs — null when the plan lacks the feature, so
            // the dashboard card stays hidden for non-CRM workspaces.
            'dealStats'            => $this->dealStats($user),
            // Instagram roll-up — null unless the workspace has an Instagram
            // account connected, so the card only appears then (else the
            // dashboard is the WhatsApp view exactly as before).
            'igStats'              => $this->igStats($wsId),
        ]);
    }

    /**
     * Compact Sales Pipeline KPI block for the dashboard. Returns null unless
     * the workspace plan has access_sales_pipeline, so the pipeline surfaces
     * on the main dashboard (not just buried under /deals) when it's in play.
     */
    private function dealStats($user): ?array
    {
        try {
            $ws = $user?->currentWorkspace;
            if (!$ws || !\App\Services\PlanLimitGuard::hasFeature($ws, 'access_sales_pipeline')) {
                return null;
            }
            // Display in the WORKSPACE'S selected currency (same source /deals
            // uses), converting each deal from its own stored currency via the
            // admin exchange rates. Previously this read the default pipeline
            // row's currency and fell back to hardcoded INR — so a GBP workspace
            // saw ₹ on the dashboard while /deals correctly showed £.
            $displayCurrency = (string) (optional($ws)->currency
                ?: \App\Models\SystemSetting::get('default_currency', 'USD'));
            $conv = fn ($minor, $from) => (int) round(
                \App\Support\FormatSettings::convert(((int) $minor) / 100, $from ?: $displayCurrency, $displayCurrency) * 100
            );

            $openMinor = (int) \App\Models\Deal::forCurrentWorkspace()->open()
                ->get(['value_minor', 'currency'])->sum(fn ($d) => $conv($d->value_minor, $d->currency));
            $openCount = (int) \App\Models\Deal::forCurrentWorkspace()->open()->count();
            $wonMonth  = (int) \App\Models\Deal::forCurrentWorkspace()->where('status', 'won')
                ->where('won_at', '>=', now()->startOfMonth())
                ->get(['value_minor', 'currency'])->sum(fn ($d) => $conv($d->value_minor, $d->currency));
            $wonAll    = (int) \App\Models\Deal::forCurrentWorkspace()->where('status', 'won')->count();
            $lostAll   = (int) \App\Models\Deal::forCurrentWorkspace()->where('status', 'lost')->count();

            return [
                'open_count'  => $openCount,
                'open_value'  => \App\Support\FormatSettings::formatIn($openMinor / 100, $displayCurrency),
                'won_month'   => \App\Support\FormatSettings::formatIn($wonMonth / 100, $displayCurrency),
                'win_rate'    => ($wonAll + $lostAll) > 0 ? (int) round($wonAll / ($wonAll + $lostAll) * 100) : 0,
            ];
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Compact Instagram roll-up for the dashboard / analytics pages. Returns
     * null unless the workspace has at least one Instagram account linked from
     * the connected Instaflow install (WorkspaceIgAccount mirror) — so the
     * Instagram card stays hidden entirely for WhatsApp-only workspaces and
     * these pages fall back to the WhatsApp view, exactly as before.
     *
     * Instagram DMs are mirrored into the SAME conversations / inbox_messages
     * tables WhatsApp uses, tagged channel = 'instagram', so the volume + reply
     * metrics reuse that data — no Instaflow round-trip on page load.
     */
    private function igStats(?int $wsId): ?array
    {
        if (!$wsId) return null;
        try {
            // Gate on the single source of truth: hides the card the moment the
            // Instaflow connection is cut at /admin/extensions OR the last account
            // is unlinked — staying gone until Instagram is connected again.
            if (!\App\Models\WorkspaceIgAccount::hasConnected($wsId)) return null;

            $accounts = \App\Models\WorkspaceIgAccount::forWorkspace($wsId)
                ->connected()
                ->orderByDesc('followers')
                ->get();
            if ($accounts->isEmpty()) return null;

            $now  = now();
            $from = $now->copy()->subDays(7)->startOfDay();

            $convoQ = \App\Models\Conversation::query()
                ->where('workspace_id', $wsId)
                ->where('channel', 'instagram');

            $open       = (clone $convoQ)->count();
            // "Needs reply" — the thread's last message came IN (the customer
            // is waiting), or it still has unread messages. This is the number
            // the card surfaces as the actionable count.
            $needsReply = (clone $convoQ)->where(function ($q) {
                $q->where('unread_count', '>', 0)
                  ->orWhereColumn('last_inbound_at', '>', 'last_outbound_at');
            })->count();
            $unread     = (int) (clone $convoQ)->sum('unread_count');

            // 7-day DM volume, scoped THROUGH the instagram conversations above
            // (inbox_messages has no workspace_id of its own).
            $msg7 = \App\Models\InboxMessage::query()
                ->whereHas('conversation', function ($q) use ($wsId) {
                    $q->where('workspace_id', $wsId)->where('channel', 'instagram');
                })
                ->whereBetween('created_at', [$from, $now]);
            $dmsIn7d  = (clone $msg7)->where('direction', 'in')->count();
            $dmsOut7d = (clone $msg7)->where('direction', 'out')->count();

            return [
                'accounts_count' => $accounts->count(),
                'followers'      => (int) $accounts->sum('followers'),
                'open'           => $open,
                'needs_reply'    => $needsReply,
                'unread'         => $unread,
                'dms_in_7d'      => $dmsIn7d,
                'dms_out_7d'     => $dmsOut7d,
                'reply_rate'     => $dmsIn7d > 0 ? (int) round(min(100, $dmsOut7d / $dmsIn7d * 100)) : 0,
                'accounts'       => $accounts->take(4)->map(fn ($a) => [
                    'handle'    => '@' . $a->handle(),
                    'name'      => $a->name ?: ('@' . $a->handle()),
                    'avatar'    => $a->avatar,
                    'followers' => (int) $a->followers,
                    'status'    => (string) ($a->status ?: 'connected'),
                ])->values()->all(),
            ];
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Crude ISO-2 country-code → readable name map. Covers the codes a
     * WhatsApp operator is likely to see. Falls back to the code itself
     * when unknown (so the UI still renders).
     */
    private function countryName(string $code): string
    {
        // Contacts.country_code is stored as the DIAL prefix ('91',
        // '1', '44') because the form's intlTelInput widget gives us
        // the calling code, not the ISO. Translate to ISO first so the
        // lookup below works. Already-ISO inputs pass straight through.
        $iso = $this->dialToIso($code);
        $map = [
            'IN' => 'India', 'BR' => 'Brazil', 'ES' => 'Spain', 'DE' => 'Germany',
            'FR' => 'France', 'MX' => 'Mexico', 'US' => 'United States', 'GB' => 'United Kingdom',
            'NG' => 'Nigeria', 'ID' => 'Indonesia', 'PK' => 'Pakistan', 'BD' => 'Bangladesh',
            'AR' => 'Argentina', 'CO' => 'Colombia', 'PH' => 'Philippines', 'EG' => 'Egypt',
            'AE' => 'UAE', 'SA' => 'Saudi Arabia', 'ZA' => 'South Africa', 'TR' => 'Turkey',
            'IT' => 'Italy', 'NL' => 'Netherlands', 'PL' => 'Poland', 'CA' => 'Canada',
            'AU' => 'Australia', 'NZ' => 'New Zealand', 'SG' => 'Singapore', 'MY' => 'Malaysia',
            'TH' => 'Thailand', 'VN' => 'Vietnam', 'LK' => 'Sri Lanka', 'NP' => 'Nepal',
            'KE' => 'Kenya', 'JP' => 'Japan', 'KR' => 'South Korea', 'CN' => 'China',
        ];
        return $map[$iso] ?? $iso;
    }

    private function countryFlag(string $code): string
    {
        $iso = $this->dialToIso($code);
        if (strlen($iso) !== 2 || !ctype_alpha($iso)) return '🌐';
        return mb_chr(0x1F1E6 + (ord($iso[0]) - 65)) . mb_chr(0x1F1E6 + (ord($iso[1]) - 65));
    }

    /**
     * Translate a dial prefix (`91`, `+44`) to its primary ISO-3166-1
     * alpha-2 code (`IN`, `GB`). If the input is already a 2-letter
     * ISO code it's returned uppercased. North-American dial code `1`
     * maps to US (Canada/Caribbean numbers share the prefix and we
     * can't distinguish without the area code).
     */
    private function dialToIso(string $code): string
    {
        $raw = strtoupper(trim($code));
        if (strlen($raw) === 2 && ctype_alpha($raw)) return $raw;

        $digits = preg_replace('/\D+/', '', $raw);
        if ($digits === '') return $raw;

        static $dial = [
            '1'   => 'US', '7'   => 'RU', '20'  => 'EG', '27'  => 'ZA', '30'  => 'GR',
            '31'  => 'NL', '32'  => 'BE', '33'  => 'FR', '34'  => 'ES', '36'  => 'HU',
            '39'  => 'IT', '40'  => 'RO', '41'  => 'CH', '43'  => 'AT', '44'  => 'GB',
            '45'  => 'DK', '46'  => 'SE', '47'  => 'NO', '48'  => 'PL', '49'  => 'DE',
            '51'  => 'PE', '52'  => 'MX', '53'  => 'CU', '54'  => 'AR', '55'  => 'BR',
            '56'  => 'CL', '57'  => 'CO', '58'  => 'VE', '60'  => 'MY', '61'  => 'AU',
            '62'  => 'ID', '63'  => 'PH', '64'  => 'NZ', '65'  => 'SG', '66'  => 'TH',
            '81'  => 'JP', '82'  => 'KR', '84'  => 'VN', '86'  => 'CN', '90'  => 'TR',
            '91'  => 'IN', '92'  => 'PK', '93'  => 'AF', '94'  => 'LK', '95'  => 'MM',
            '98'  => 'IR', '212' => 'MA', '213' => 'DZ', '216' => 'TN', '218' => 'LY',
            '220' => 'GM', '221' => 'SN', '233' => 'GH', '234' => 'NG', '249' => 'SD',
            '254' => 'KE', '255' => 'TZ', '256' => 'UG', '260' => 'ZM', '263' => 'ZW',
            '351' => 'PT', '353' => 'IE', '354' => 'IS', '358' => 'FI', '359' => 'BG',
            '370' => 'LT', '371' => 'LV', '372' => 'EE', '375' => 'BY', '380' => 'UA',
            '420' => 'CZ', '421' => 'SK', '852' => 'HK', '855' => 'KH', '856' => 'LA',
            '880' => 'BD', '886' => 'TW', '960' => 'MV', '961' => 'LB', '962' => 'JO',
            '963' => 'SY', '964' => 'IQ', '965' => 'KW', '966' => 'SA', '967' => 'YE',
            '968' => 'OM', '971' => 'AE', '972' => 'IL', '973' => 'BH', '974' => 'QA',
            '977' => 'NP',
        ];
        // Longest-prefix match (3 digits beats 2 beats 1).
        foreach ([3, 2, 1] as $len) {
            $prefix = substr($digits, 0, $len);
            if (isset($dial[$prefix])) return $dial[$prefix];
        }
        return $raw;
    }
    // chat() removed — handled by App\Http\Controllers\ChatController@index now.
    // contacts() removed — handled by App\Http\Controllers\ContactController@index now.

    // broadcasts() / broadcastCreate() removed — handled by App\Http\Controllers\BroadcastsController now.

    public function campaigns(): View         { return view('user.campaigns.index'); }
    public function campaignCreate(): View    { return view('user.campaigns.create'); }
    public function campaignEdit(string $id): View { return view('user.campaigns.edit'); }

    // wa-campaigns methods removed — handled by WaCampaignsController now.

    public function flows(): View             { return view('user.flows.index'); }
    public function flowBuilder(): View       { return view('user.flows.builder'); }

    // template methods removed — handled by App\Http\Controllers\TemplatesController now.

    public function devices(): View           { return view('user.devices.index'); }
    public function deviceDetail(string $id): View { return view('user.devices.detail'); }
    public function connect(\Illuminate\Http\Request $request)
    {
        $platform = $request->string('platform')->toString();

        if ($platform === 'wa-store') {
            $user = \Illuminate\Support\Facades\Auth::user();
            $wsId = $user?->current_workspace_id;

            // Available sending devices for the dropdown. Filtered to
            // "connected" so the wizard can only bind to numbers that
            // actually work right now.
            $connectedDevices = $user
                ? \App\Models\Device::query()
                    ->forCurrentWorkspace()
                    ->where('status', 'connected')
                    ->orderByDesc('active')
                    ->orderByDesc('last_seen_at')
                    ->get()
                : collect();

            $providerConfig = $wsId ? \App\Models\WaProviderConfig::query()->primaryForWorkspace($wsId)->first() : null;
            $hasWaba        = $providerConfig && $providerConfig->isConnected();

            // All shops the workspace owns. Multi-shop is supported —
            // the unique(workspace_id) constraint was dropped so a
            // workspace can run multiple storefronts (different brands,
            // sub-brands, language variants, etc.).
            $shops = $wsId
                ? \App\Models\WaStorefront::where('workspace_id', $wsId)->orderByDesc('id')->get()
                : collect();

            // Mode selection:
            //   • ?action=add        → always show wizard, blank slate
            //   • ?shop=ID           → edit that specific shop in the wizard
            //   • no params, 0 shops → wizard (first-time setup)
            //   • no params, 1+ shops→ shop list (manage existing + add new)
            $action = $request->string('action')->toString();
            $shopId = (int) $request->integer('shop');
            $storefront = null;
            if ($shopId) {
                $storefront = $shops->firstWhere('id', $shopId);
            }
            $mode = match (true) {
                $storefront !== null     => 'edit',
                $action === 'add'        => 'add',
                $shops->isEmpty()        => 'add',
                default                  => 'list',
            };

            $workspace = $wsId ? \App\Models\Workspace::find($wsId) : null;

            // Subdomain host: only useful when explicitly set to a real
            // public DNS host. On local/IP dev we render the path-based
            // preview URL instead.
            $subdomainHost = config('storefront.subdomain_host');
            $appHost       = parse_url(config('app.url'), PHP_URL_HOST) ?: 'localhost';
            $subdomainUsable = $subdomainHost
                && $subdomainHost !== 'localhost'
                && $subdomainHost !== $appHost
                && !filter_var(explode(':', $subdomainHost)[0], FILTER_VALIDATE_IP);

            return view('user.connect.wa-store', [
                'mode'             => $mode,
                'shops'            => $shops,
                'storefront'       => $storefront,
                'workspace'        => $workspace,
                'connectedDevices' => $connectedDevices,
                'providerConfig'   => $providerConfig,
                'hasWaba'          => $hasWaba,
                'subdomainHost'    => $subdomainHost,
                'subdomainUsable'  => $subdomainUsable,
            ]);
        }

        return view('user.connect.index');
    }

    public function autoReply(): View         { return view('user.auto-reply.index'); }
    public function autoReplyCreate(): View   { return view('user.auto-reply.create'); }
    public function autoReplyKeyword(): View  { return view('user.auto-reply.keyword'); }

    public function scheduled(): View         { return view('user.scheduled.index'); }
    public function scheduledCreate(): View   { return view('user.scheduled.create'); }
    public function scheduledDetail(string $id): View { return view('user.scheduled.detail'); }

    public function webhooks(): View          { return view('user.webhooks.index'); }
    public function webhookCreate(): View     { return view('user.webhooks.create'); }
    public function webhookDetail(string $id): View { return view('user.webhooks.detail'); }

    public function integrations(): View      { return view('user.integrations.index'); }
    // shopifyDashboard() / woocommerceDashboard() removed — Shopify and
    // WooCommerce are now handled by ShopifyController / WoocommerceController.

    /**
     * GET /analytics/export — CSV of the workspace's headline analytics for
     * the selected range. Self-contained (doesn't depend on the full
     * analytics() build) + workspace-scoped + Schema-guarded so it stays
     * correct as the dashboard evolves and never errors on a partial install.
     */
    public function analyticsExport(\Illuminate\Http\Request $request): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $range = $request->string('range')->toString() ?: '30d';
        $now   = now();
        $to    = $now->copy()->endOfDay();
        $from  = match ($range) {
            '7d'    => $now->copy()->subDays(7)->startOfDay(),
            '90d'   => $now->copy()->subDays(90)->startOfDay(),
            default => $now->copy()->subDays(30)->startOfDay(),
        };
        if ($range === 'custom') {
            try {
                $from = $request->filled('from') ? \Carbon\Carbon::parse($request->string('from'))->startOfDay() : $from;
                $to   = $request->filled('to')   ? \Carbon\Carbon::parse($request->string('to'))->endOfDay()   : $to;
            } catch (\Throwable $e) { /* keep defaults */ }
            if ($from->gt($to)) { [$from, $to] = [$to->copy()->startOfDay(), $from->copy()->endOfDay()]; }
        }

        $user    = \Illuminate\Support\Facades\Auth::user();
        $wsId    = (int) ($user?->current_workspace_id ?? 0);
        $userIds = $wsId
            ? \App\Models\User::where('current_workspace_id', $wsId)->pluck('id')->all()
            : [$user?->id];

        $count = function (string $table) use ($wsId, $userIds, $from, $to): int {
            if (! \Illuminate\Support\Facades\Schema::hasTable($table)) return 0;
            $q = \DB::table($table);
            if ($wsId && \Illuminate\Support\Facades\Schema::hasColumn($table, 'workspace_id')) {
                $q->where(function ($qq) use ($wsId, $userIds) {
                    $qq->where('workspace_id', $wsId)
                       ->orWhere(fn ($x) => $x->whereNull('workspace_id')->whereIn('user_id', $userIds));
                });
            } elseif (\Illuminate\Support\Facades\Schema::hasColumn($table, 'user_id')) {
                $q->whereIn('user_id', $userIds);
            }
            return (int) $q->whereBetween('created_at', [$from, $to])->count();
        };

        $rows = [
            ['Metric', 'Value'],
            ['Range', $from->toDateString() . ' to ' . $to->toDateString()],
            ['Outbound messages', $count('messages')],
            ['Campaigns', $count('wpcampaigns')],
            ['Broadcasts', $count('broadcasts')],
            ['Contacts added', $count('contacts')],
        ];

        $appName  = \App\Models\SystemSetting::get('app_name', config('app.name', 'WaDesk'));
        $filename = \Illuminate\Support\Str::slug($appName . ' analytics ' . $from->toDateString() . ' ' . $to->toDateString()) . '.csv';

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            foreach ($rows as $r) {
                // Same CSV formula-injection guard the audit-log export uses.
                $r = array_map(fn ($v) => preg_match('/^[=+\-@]/', (string) $v) ? "'" . $v : (string) $v, $r);
                fputcsv($out, $r);
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    public function analytics(\Illuminate\Http\Request $request): View
    {
        // ─── Date filter: ?range=7d|30d|90d|custom + ?from=/to= ───
        // Defaults to 30d. Custom range honours from/to when valid;
        // otherwise we fall back to 30d so an empty filter never
        // wipes the dashboard.
        $range = $request->string('range')->toString() ?: '30d';
        $now   = now();
        $to    = $now->copy()->endOfDay();
        $from  = match ($range) {
            '7d'     => $now->copy()->subDays(7)->startOfDay(),
            '90d'    => $now->copy()->subDays(90)->startOfDay(),
            'custom' => null,
            default  => $now->copy()->subDays(30)->startOfDay(),
        };
        if ($range === 'custom') {
            $fromStr = $request->string('from')->toString();
            $toStr   = $request->string('to')->toString();
            try {
                $from = $fromStr ? \Carbon\Carbon::parse($fromStr)->startOfDay() : $now->copy()->subDays(30)->startOfDay();
                $to   = $toStr   ? \Carbon\Carbon::parse($toStr)->endOfDay()     : $now->copy()->endOfDay();
            } catch (\Throwable $e) {
                $from = $now->copy()->subDays(30)->startOfDay();
            }
            // Guard against inverted ranges.
            if ($from->gt($to)) { [$from, $to] = [$to->copy()->startOfDay(), $from->copy()->endOfDay()]; }
        }
        $days = max(1, (int) $from->diffInDays($to) + 1);

        // Workspace scope — everything below is restricted to the
        // signed-in user's current workspace so each tenant sees only
        // its own numbers.
        $user = \Illuminate\Support\Facades\Auth::user();
        $wsId = $user?->current_workspace_id;
        $userIdsForWs = $wsId
            ? \App\Models\User::where('current_workspace_id', $wsId)->pluck('id')->all()
            : [$user?->id];

        // Workspace-scope filter — workspace_id is the source of truth,
        // user_id whereIn is a legacy fallback for NULL-workspace rows.
        $wsScope = function ($q) use ($wsId, $userIdsForWs) {
            if ($wsId) {
                $q->where(function ($qq) use ($wsId, $userIdsForWs) {
                    $qq->where('workspace_id', $wsId)
                       ->orWhere(function ($qqq) use ($userIdsForWs) {
                           $qqq->whereNull('workspace_id')->whereIn('user_id', $userIdsForWs);
                       });
                });
            } else {
                $q->whereIn('user_id', $userIdsForWs);
            }
        };

        // inbox_messages has NO workspace_id column — filter through
        // the parent Conversation instead.
        $wsScopeInbox = function ($q) use ($wsId, $userIdsForWs) {
            if ($wsId) {
                $q->whereHas('conversation', function ($cq) use ($wsId, $userIdsForWs) {
                    $cq->where(function ($qq) use ($wsId, $userIdsForWs) {
                        $qq->where('workspace_id', $wsId)
                           ->orWhere(function ($qqq) use ($userIdsForWs) {
                               $qqq->whereNull('workspace_id')->whereIn('user_id', $userIdsForWs);
                           });
                    });
                });
            } else {
                $q->whereIn('user_id', $userIdsForWs);
            }
        };

        // Both message tables count toward analytics: legacy `messages`
        // (campaigns/broadcasts/scheduled) AND `inbox_messages` (team-
        // inbox / chat). Combining them here so the operator sees ONE
        // dashboard instead of two.
        $msgQ = \App\Models\Message::query()
            ->where($wsScope)
            ->forCurrentEngine()
            ->whereBetween('created_at', [$from, $to]);
        $imQ  = \App\Models\InboxMessage::query()
            ->where($wsScopeInbox)
            ->forCurrentEngine()
            ->whereBetween('created_at', [$from, $to]);

        $totalMessages = (clone $msgQ)->where('direction', 'out')->count()
                       + (clone $imQ)->where('direction', 'out')->count();
        $delivered     = (clone $msgQ)->where('direction', 'out')->whereIn('status', ['delivered','read','sent'])->count()
                       + (clone $imQ)->where('direction', 'out')->whereIn('status', ['delivered','read','sent'])->count();
        $failed        = (clone $msgQ)->where('direction', 'out')->where('status', 'failed')->count()
                       + (clone $imQ)->where('direction', 'out')->where('status', 'failed')->count();
        $queued        = max(0, $totalMessages - $delivered - $failed);
        $repliesIn     = (clone $msgQ)->where('direction', 'in')->count()
                       + (clone $imQ)->where('direction', 'in')->count();

        $uniqueRecipients = $wsId
            ? \App\Models\Conversation::where('workspace_id', $wsId)
                ->whereBetween('created_at', [$from, $to])
                ->count()
            : 0;

        $deliverabilityPct = $totalMessages ? round($delivered / $totalMessages * 100, 1) : 0;
        $replyRatePct      = $totalMessages ? round($repliesIn / $totalMessages * 100, 1) : 0;

        // ─── Previous-period stats for delta trend pills ───
        // Same-length window immediately before $from. Used to compute
        // pct change so the "↑ 14%" / "↓ 2%" pills are real, not faked.
        $prevTo   = $from->copy()->subSecond();
        $prevFrom = $prevTo->copy()->subDays($days - 1)->startOfDay();
        $prevMsgQ = \App\Models\Message::query()
            ->where($wsScope)
            ->whereBetween('created_at', [$prevFrom, $prevTo]);
        $prevImQ  = \App\Models\InboxMessage::query()
            ->where($wsScopeInbox)
            ->whereBetween('created_at', [$prevFrom, $prevTo]);
        $prevTotal     = (clone $prevMsgQ)->where('direction', 'out')->count()
                       + (clone $prevImQ)->where('direction', 'out')->count();
        $prevDelivered = (clone $prevMsgQ)->where('direction', 'out')->whereIn('status', ['delivered','read','sent'])->count()
                       + (clone $prevImQ)->where('direction', 'out')->whereIn('status', ['delivered','read','sent'])->count();
        $prevFailed    = (clone $prevMsgQ)->where('direction', 'out')->where('status', 'failed')->count()
                       + (clone $prevImQ)->where('direction', 'out')->where('status', 'failed')->count();
        $prevQueued    = max(0, $prevTotal - $prevDelivered - $prevFailed);
        $prevReplies   = (clone $prevMsgQ)->where('direction', 'in')->count()
                       + (clone $prevImQ)->where('direction', 'in')->count();
        $prevRecipients = $wsId
            ? \App\Models\Conversation::where('workspace_id', $wsId)
                ->whereBetween('created_at', [$prevFrom, $prevTo])
                ->count()
            : 0;
        $prevReplyRate = $prevTotal ? round($prevReplies / $prevTotal * 100, 1) : 0;

        $pctChange = function (float $now, float $prev): float {
            if ($prev <= 0) return $now > 0 ? 100.0 : 0.0;
            return round(($now - $prev) / $prev * 100, 1);
        };
        $deltaDelivered  = $pctChange($delivered, $prevDelivered);
        $deltaRecipients = $pctChange($uniqueRecipients, $prevRecipients);
        $deltaQueued     = $pctChange($queued, $prevQueued);
        $deltaFailed     = $pctChange($failed, $prevFailed);
        $deltaReplyRate  = round($replyRatePct - $prevReplyRate, 1);

        // ─── Real funnel from workspace data ───
        // Sent → Delivered → Replies → Unique repliers. Each step shows
        // the absolute count, % of step 1, and absolute drop to the next.
        $uniqueRepliers = $wsId
            ? \App\Models\InboxMessage::query()
                ->join('conversations', 'conversations.id', '=', 'inbox_messages.conversation_id')
                ->where('conversations.workspace_id', $wsId)
                ->whereBetween('inbox_messages.created_at', [$from, $to])
                ->where('inbox_messages.direction', 'in')
                ->distinct('inbox_messages.conversation_id')
                ->count('inbox_messages.conversation_id')
            : 0;
        $pctOf = fn (int $n, int $base) => $base > 0 ? round($n / $base * 100, 1) : 0.0;
        $funnelSteps = [
            ['stage' => '01 · sent',      'label' => 'Outbound messages',    'count' => (int) $totalMessages,    'pct' => 100.0,                                       'drop' => 0],
            ['stage' => '02 · delivered', 'label' => 'Successfully delivered','count' => (int) $delivered,        'pct' => $pctOf($delivered, $totalMessages),          'drop' => max(0, $totalMessages - $delivered)],
            ['stage' => '03 · replied',   'label' => 'Inbound replies',      'count' => (int) $repliesIn,        'pct' => $pctOf($repliesIn, $totalMessages),          'drop' => max(0, $delivered - $repliesIn)],
            ['stage' => '04 · engaged',   'label' => 'Unique repliers',      'count' => (int) $uniqueRepliers,   'pct' => $pctOf($uniqueRepliers, $totalMessages),     'drop' => max(0, $repliesIn - $uniqueRepliers)],
        ];
        $funnelEndPct = $funnelSteps[count($funnelSteps) - 1]['pct'];

        // End-to-end delta (final step % now vs previous window)
        $prevUniqueRepliers = $wsId
            ? \App\Models\InboxMessage::query()
                ->join('conversations', 'conversations.id', '=', 'inbox_messages.conversation_id')
                ->where('conversations.workspace_id', $wsId)
                ->whereBetween('inbox_messages.created_at', [$prevFrom, $prevTo])
                ->where('inbox_messages.direction', 'in')
                ->distinct('inbox_messages.conversation_id')
                ->count('inbox_messages.conversation_id')
            : 0;
        $prevFunnelEndPct = $prevTotal > 0 ? round($prevUniqueRepliers / $prevTotal * 100, 1) : 0;
        $funnelDeltaPp    = round($funnelEndPct - $prevFunnelEndPct, 1);

        // ─── Daily series for the volume + totals + spark charts ───
        // One row per day in the window with sent/delivered/failed/queued.
        $dailySent = $this->dailyCount($msgQ, $imQ, $from, $days, 'out', ['delivered','read','sent','queued','pending']);
        $dailyDel  = $this->dailyCount($msgQ, $imQ, $from, $days, 'out', ['delivered','read','sent']);
        $dailyFail = $this->dailyCount($msgQ, $imQ, $from, $days, 'out', ['failed']);
        $dailyQ    = array_map(
            fn ($s, $d, $f) => max(0, (int) $s - (int) $d - (int) $f),
            $dailySent, $dailyDel, $dailyFail,
        );
        $dailyLabels = [];
        for ($i = 0; $i < $days; $i++) {
            $dailyLabels[] = $from->copy()->addDays($i)->format('M d');
        }

        // ─── Devices (engine-aware · ENABLED SET) ───
        // Analytics device list reflects EVERY engine the workspace runs.
        // For each enabled engine we surface its senders as uniform device
        // objects: Baileys → `devices` rows, WABA / Twilio → that provider's
        // wa_provider_configs rows. Capped at 8 total (default engine first,
        // via enginesFor's ordering). Single-engine workspaces are
        // byte-identical (one engine → the old single branch).
        $analyticsEngines = \App\Services\WorkspaceEngine::enginesFor($wsId);
        $devices = collect();
        foreach ($analyticsEngines as $eng) {
            if ($devices->count() >= 8) break;
            $remaining = 8 - $devices->count();
            if ($eng === \App\Services\WorkspaceEngine::ENGINE_BAILEYS) {
                // Tenant isolation — see the note on the dashboard device counter.
                $engDevices = \App\Models\Device::forCurrentWorkspace()
                    ->orderByDesc('active')
                    ->orderByDesc('id')
                    ->limit($remaining)
                    ->get();
            } else {
                $engDevices = \App\Models\WaProviderConfig::query()
                    ->where('workspace_id', $wsId)
                    ->where('provider', $eng)
                    ->orderByDesc('connected_at')
                    ->limit($remaining)
                    ->get()
                    ->map(fn ($cfg) => (object) [
                        'id'           => $cfg->id,
                        'device_name'  => $cfg->display_label ?: strtoupper($eng) . ' #' . $cfg->id,
                        'phone_number' => $cfg->phone_number,
                        'country_code' => '',
                        'active'       => $cfg->status === \App\Models\WaProviderConfig::STATUS_CONNECTED,
                        'status'       => (string) $cfg->status,
                        'sent_24h'     => 0,
                        'failed_24h'   => 0,
                        'region'       => null,
                    ]);
            }
            $devices = $devices->concat($engDevices);
        }
        $devices = $devices->values();
        $deviceLabels = $devices->map(function ($d) {
            $tail = $d->phone_number
                ? ' · +' . ltrim($d->country_code ?: '', '+') . ' ' . substr($d->phone_number, 0, 4) . 'xx'
                : '';
            return ($d->device_name ?: 'Device #' . $d->id) . $tail;
        })->values()->all();
        // Per-device outbound count for the selected window.
        $devicePhoneList = $devices->pluck('phone_number')->filter()->all();
        $deviceData = [];
        foreach ($devices as $d) {
            $count = $d->phone_number
                ? (\App\Models\Message::where('from_number', $d->phone_number)
                        ->whereBetween('created_at', [$from, $to])
                        ->count()
                    + \App\Models\InboxMessage::where('from_number', $d->phone_number)
                        ->whereBetween('created_at', [$from, $to])
                        ->count())
                : 0;
            $deviceData[] = (int) $count;
        }
        $devicesOnlineCount = $devices->where('status', 'connected')->where('active', true)->count();
        $devicesTotalCount  = $devices->count();

        // ─── Message types — both tables use `media_type` ───
        // Templates aren't on a per-row column; we approximate them by
        // looking at `template_id IS NOT NULL` for the legacy messages.
        $typeBuckets = ['Text' => 0, 'Template' => 0, 'Media' => 0, 'Interactive' => 0, 'Location' => 0];
        $rowsM  = (clone $msgQ)->where('direction', 'out')->select(['media_type', 'template_id'])->get();
        $rowsIM = (clone $imQ)->where('direction', 'out')->select(['media_type', 'template_id'])->get();
        foreach ($rowsM->concat($rowsIM) as $r) {
            $mt = strtolower((string) ($r->media_type ?? ''));
            $key = match (true) {
                !empty($r->template_id)                                    => 'Template',
                in_array($mt, ['image','video','audio','document'], true)  => 'Media',
                $mt === 'location'                                          => 'Location',
                $mt === 'contact'                                           => 'Interactive',
                default                                                     => 'Text',
            };
            $typeBuckets[$key]++;
        }
        $typeLabels = array_keys(array_filter($typeBuckets, fn ($v) => $v > 0));
        $typeValues = array_values(array_filter($typeBuckets, fn ($v) => $v > 0));
        if (empty($typeLabels)) { $typeLabels = ['Text']; $typeValues = [0]; }

        // ─── Top templates (real query against wa_templates · join sends) ───
        $topTemplates = [];
        if (class_exists(\App\Models\WaTemplate::class)) {
            $rows = \App\Models\WaTemplate::query()
                ->forCurrentWorkspace()
                ->orderByDesc('updated_at')
                ->limit(8)
                ->get(['id', 'template_name', 'category', 'language']);
            foreach ($rows as $t) {
                $sends = (clone $msgQ)
                    ->where('direction', 'out')
                    ->where('template_id', $t->id)
                    ->count();
                $topTemplates[] = [
                    'name'     => $t->template_name,
                    'category' => $t->category,
                    'language' => $t->language,
                    'sends'    => $sends,
                ];
            }
            usort($topTemplates, fn ($a, $b) => $b['sends'] - $a['sends']);
        }

        // ─── Top contacts (by message count in window) ───
        $topContacts = [];
        if ($wsId) {
            $rows = \App\Models\Conversation::query()
                ->where('workspace_id', $wsId)
                ->orderByDesc('last_message_at')
                ->limit(20)
                ->get(['id', 'title', 'raw_jid', 'last_message_at']);
            foreach ($rows as $c) {
                $msgCount = \App\Models\InboxMessage::where('conversation_id', $c->id)
                    ->whereBetween('created_at', [$from, $to])
                    ->count();
                if ($msgCount === 0) continue;
                $topContacts[] = [
                    'title'    => $c->title ?: ('+' . $c->raw_jid),
                    'phone'    => $c->raw_jid,
                    'msgs'     => $msgCount,
                    'last_at'  => $c->last_message_at?->diffForHumans(),
                ];
                if (count($topContacts) >= 5) break;
            }
            usort($topContacts, fn ($a, $b) => $b['msgs'] - $a['msgs']);
        }

        // ─── Geo distribution (by device.region as a proxy) ───
        $geoBuckets = [];
        foreach ($devices as $d) {
            $key = strtoupper((string) ($d->region ?: '—'));
            if (!isset($geoBuckets[$key])) {
                $geoBuckets[$key] = ['code' => $key, 'count' => 0];
            }
            $geoBuckets[$key]['count'] += (int) ($d->sent_24h ?? 0);
        }
        usort($geoBuckets, fn ($a, $b) => $b['count'] - $a['count']);
        $geoBuckets = array_slice($geoBuckets, 0, 7);

        // ─── Live event stream (recent activity log) ───
        $events = [];
        $recent = (clone $msgQ)->orderByDesc('created_at')->limit(6)->get(['id', 'direction', 'status', 'to_number', 'created_at']);
        foreach ($recent as $r) {
            $events[] = [
                'title' => ($r->direction === 'out' ? 'Sent → ' : 'Received from ') . ($r->to_number ?: '—'),
                'meta'  => 'msg · ' . ($r->status ?: '—'),
                'at'    => $r->created_at->diffForHumans(),
            ];
        }

        // ─── Hourly × weekday heatmap (read rate per slot) ───
        $heatmap = $this->buildHeatmap($wsId, $from, $to);

        // Meta ads roll-up (workspace-scoped where possible)
        $metaCampaignsQ = \App\Models\MetaCampaign::query();
        if (\Illuminate\Support\Facades\Schema::hasColumn('meta_campaigns', 'workspace_id') && $wsId) {
            $metaCampaignsQ->where('workspace_id', $wsId);
        }
        $metaCampaigns = (clone $metaCampaignsQ)->get(['insights', 'status']);
        $metaTotalSpend   = $metaCampaigns->sum(fn ($c) => (float) ($c->insights['spend']   ?? 0));
        $metaTotalRevenue = $metaCampaigns->sum(fn ($c) => (float) ($c->insights['revenue'] ?? 0));
        $metaActive       = $metaCampaigns->where('status', 'ACTIVE')->count();

        // ─── Team performance — per-agent rollup (#6) ───
        // Agents = workspace members who have AT LEAST ONE conversation
        // assigned in the window. For each: count of assignments,
        // average first-response latency (created_at → first_response_at),
        // average resolution latency (created_at → resolved_at), and a
        // CSAT proxy from csat_responses joined on csat_responses.conversation_id.
        $teamPerformance = [];
        if ($wsId) {
            $convRows = \App\Models\Conversation::query()
                ->where('workspace_id', $wsId)
                ->whereBetween('created_at', [$from, $to])
                ->whereNotNull('assignee_user_id')
                ->get(['id', 'assignee_user_id', 'created_at', 'first_response_at', 'resolved_at']);

            $byUser = $convRows->groupBy('assignee_user_id');
            // csat_responses uses `rating` (1–5) not `score`.
            $csatRows = \App\Models\CsatResponse::query()
                ->whereIn('conversation_id', $convRows->pluck('id'))
                ->whereNotNull('rating')
                ->get(['conversation_id', 'rating']);
            $csatByConv = $csatRows->keyBy('conversation_id');

            $memberMap = \App\Models\User::query()
                ->whereIn('id', $byUser->keys())
                ->get(['id', 'name', 'email'])
                ->keyBy('id');

            foreach ($byUser as $uid => $convs) {
                $member = $memberMap->get($uid);
                if (!$member) continue;

                $firstRespMins = $convs
                    ->filter(fn ($c) => $c->first_response_at !== null)
                    ->map(fn ($c) => \Carbon\Carbon::parse($c->created_at)->diffInMinutes($c->first_response_at, false))
                    ->filter(fn ($m) => $m !== null && $m >= 0);
                $resolveMins = $convs
                    ->filter(fn ($c) => $c->resolved_at !== null)
                    ->map(fn ($c) => \Carbon\Carbon::parse($c->created_at)->diffInMinutes($c->resolved_at, false))
                    ->filter(fn ($m) => $m !== null && $m >= 0);
                $csatScores = $convs
                    ->map(fn ($c) => $csatByConv->get($c->id)?->rating)
                    ->filter(fn ($s) => $s !== null);

                $teamPerformance[] = [
                    'user_id'           => $uid,
                    'name'              => $member->name,
                    'email'             => $member->email,
                    'convos'            => $convs->count(),
                    'resolved'          => $convs->whereNotNull('resolved_at')->count(),
                    'avg_first_resp_m'  => $firstRespMins->isNotEmpty() ? round($firstRespMins->avg(), 1) : null,
                    'avg_resolve_m'     => $resolveMins->isNotEmpty()   ? round($resolveMins->avg(), 1)   : null,
                    'csat'              => $csatScores->isNotEmpty()    ? round($csatScores->avg(), 2)   : null,
                    'csat_count'        => $csatScores->count(),
                ];
            }
            // Sort by most-handled descending so top performers are at the top.
            usort($teamPerformance, fn ($a, $b) => $b['convos'] <=> $a['convos']);
        }

        return view('user.analytics.index', [
            'range'              => $range,
            'fromDate'           => $from->format('Y-m-d'),
            'toDate'             => $to->format('Y-m-d'),
            'totalMessages'      => $totalMessages,
            'delivered'          => $delivered,
            'failed'             => $failed,
            'queued'             => $queued,
            'repliesIn'          => $repliesIn,
            'uniqueRecipients'   => $uniqueRecipients,
            'deliverabilityPct'  => $deliverabilityPct,
            'replyRatePct'       => $replyRatePct,
            'deltaDelivered'     => $deltaDelivered,
            'deltaRecipients'    => $deltaRecipients,
            'deltaQueued'        => $deltaQueued,
            'deltaFailed'        => $deltaFailed,
            'deltaReplyRate'     => $deltaReplyRate,
            'funnelSteps'        => $funnelSteps,
            'funnelEndPct'       => $funnelEndPct,
            'funnelDeltaPp'      => $funnelDeltaPp,
            'metaTotalSpend'     => $metaTotalSpend,
            'metaTotalRevenue'   => $metaTotalRevenue,
            'metaActive'         => $metaActive,
            'deviceLabels'       => $deviceLabels,
            'deviceData'         => $deviceData,
            'devicesOnlineCount' => $devicesOnlineCount,
            'devicesTotalCount'  => $devicesTotalCount,
            // Charts
            'dailyLabels'        => $dailyLabels,
            'dailySent'          => $dailySent,
            'dailyDelivered'     => $dailyDel,
            'dailyFailed'        => $dailyFail,
            'dailyQueued'        => $dailyQ,
            'typeLabels'         => $typeLabels,
            'typeValues'         => $typeValues,
            'topTemplates'       => $topTemplates,
            'topContacts'        => $topContacts,
            'geoBuckets'         => $geoBuckets,
            'eventStream'        => $events,
            'heatmapSeries'      => $heatmap,
            // The zone the heatmap is actually bucketed in — the badge used to
            // read a hardcoded "UTC+1" regardless of the real setting.
            'heatmapTzLabel'     => $this->utcOffsetLabel(wa_tz()),
            'teamPerformance'    => $teamPerformance,
            // Instagram roll-up — null unless an Instagram account is connected,
            // so the analytics page shows the Instagram band only then and stays
            // the WhatsApp view otherwise.
            'igStats'            => $this->igStats($wsId),
        ]);
    }

    /**
     * Per-day count over both message tables, returning an array of
     * length $days starting at $from.
     */
    private function dailyCount($msgQ, $imQ, $from, int $days, string $direction, array $statuses): array
    {
        $msgDaily = (clone $msgQ)
            ->where('direction', $direction)
            ->whereIn('status', $statuses)
            ->selectRaw('DATE(created_at) as d, COUNT(*) as n')
            ->groupBy('d')
            ->pluck('n', 'd')
            ->toArray();
        $imDaily = (clone $imQ)
            ->where('direction', $direction)
            ->whereIn('status', $statuses)
            ->selectRaw('DATE(created_at) as d, COUNT(*) as n')
            ->groupBy('d')
            ->pluck('n', 'd')
            ->toArray();

        $out = [];
        for ($i = 0; $i < $days; $i++) {
            $key = $from->copy()->addDays($i)->format('Y-m-d');
            $out[] = (int) (($msgDaily[$key] ?? 0) + ($imDaily[$key] ?? 0));
        }
        return $out;
    }

    /**
     * "UTC+7" / "UTC-3:30" / "UTC" for a zone name — what the heatmap badge
     * shows. Half-hour zones (India, Iran) print their minutes, so an IST
     * workspace reads UTC+5:30 rather than a rounded, wrong UTC+5.
     */
    private function utcOffsetLabel(string $tz): string
    {
        try {
            $mins = (int) round(now($tz)->getOffset() / 60);
        } catch (\Throwable $e) {
            return 'UTC';
        }
        if ($mins === 0) return 'UTC';
        $sign = $mins < 0 ? '-' : '+';
        $mins = abs($mins);
        $h    = intdiv($mins, 60);
        $m    = $mins % 60;
        return 'UTC' . $sign . $h . ($m ? ':' . str_pad((string) $m, 2, '0', STR_PAD_LEFT) : '');
    }

    /**
     * Hour × weekday read-rate heatmap. Sun..Sat × 0..23. Returns the
     * ApexCharts heatmap series shape directly:
     *   [{ name: 'Sun', data: [{x:'00',y:N},...] }, ...]
     */
    private function buildHeatmap(?int $wsId, $from, $to): array
    {
        $days = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
        $series = [];

        // Bucket by the VIEWER's local hour, not the stored one. created_at is
        // written in the app timezone (UTC by default), so grouping on a raw
        // HOUR() built a UTC chart — a Jakarta workspace saw its 09:00 peak
        // sitting at 02:00. Shift by the app→display offset inside SQL so the
        // day boundary moves with it (an hour-only shift would leave rows on
        // the wrong weekday). Cast to int: this is interpolated into raw SQL.
        $offset = (int) (now(wa_tz())->getOffset() - now(config('app.timezone') ?: 'UTC')->getOffset());
        $local  = "DATE_ADD(inbox_messages.created_at, INTERVAL {$offset} SECOND)";

        $rows = $wsId
            ? \App\Models\InboxMessage::query()
                ->join('conversations', 'conversations.id', '=', 'inbox_messages.conversation_id')
                ->where('conversations.workspace_id', $wsId)
                ->whereBetween('inbox_messages.created_at', [$from, $to])
                ->selectRaw("DAYOFWEEK({$local})-1 as dow, HOUR({$local}) as hr, COUNT(*) as n")
                ->groupBy('dow', 'hr')
                ->get()
            : collect();
        $grid = [];
        foreach ($rows as $r) {
            $grid[(int) $r->dow][(int) $r->hr] = (int) $r->n;
        }
        foreach ($days as $i => $name) {
            $data = [];
            for ($h = 0; $h < 24; $h++) {
                $data[] = ['x' => str_pad((string) $h, 2, '0', STR_PAD_LEFT), 'y' => (int) ($grid[$i][$h] ?? 0)];
            }
            $series[] = ['name' => $name, 'data' => $data];
        }
        return $series;
    }
    public function metaAdsAnalytics(): View  { return view('user.meta-ads.analytics'); }
    public function messageHistory(): View    { return view('user.message-history.index'); }

    public function support(): View           { return view('user.support.index'); }
    public function teamInbox(): View
    {
        // Count of connected devices visible to this team-inbox view.
        // We try three lookup paths so the count is right whether the
        // device is owned by the signed-in user, by someone else in
        // the same workspace, or by someone whose `current_workspace_id`
        // doesn't yet point at the shared workspace (legacy data).
        $user = \Illuminate\Support\Facades\Auth::user();
        $wsId = $user?->current_workspace_id;

        // Path 1: workspace siblings — anyone whose current_workspace_id
        // matches the viewer's.
        $userIds = $wsId
            ? \App\Models\User::query()->where('current_workspace_id', $wsId)->pluck('id')
            : collect([$user?->id]);

        // Engine-aware connected count — summed across the ENABLED ENGINE
        // SET. For each enabled engine: Baileys counts connected+active
        // `devices` (keeping the 3-path max() fallback for status flapping);
        // WABA / Twilio count connected wa_provider_configs for that
        // provider. Single-engine workspaces are byte-identical (the sum
        // over [default] == the old single branch). A pure-Twilio workspace
        // now correctly counts Twilio configs instead of falling through to
        // Baileys.
        $myConnected = $anyForMe = 0;   // kept for the diagnostic log below
        $connectedDevices = 0;
        foreach (\App\Services\WorkspaceEngine::enginesFor($wsId) as $eng) {
            if ($eng === \App\Services\WorkspaceEngine::ENGINE_BAILEYS) {
                // Was ->whereIn('user_id', $userIds) with NO workspace_id
                // condition at all, so it counted those users' devices in EVERY
                // workspace, not just this one. Scope it the same way the
                // dashboard now does.
                $wsConnected = \App\Models\Device::forCurrentWorkspace()
                    ->where('status', 'connected')
                    ->where('active', true)
                    ->count();
                $myConnected = \App\Models\Device::query()
                    ->where('user_id', $user?->id)
                    ->where('status', 'connected')
                    ->where('active', true)
                    ->count();
                $anyForMe = \App\Models\Device::query()
                    ->where('user_id', $user?->id)
                    ->where('active', true)
                    ->count();
                $connectedDevices += max($wsConnected, $myConnected, $anyForMe);
            } else {
                $connectedDevices += \App\Models\WaProviderConfig::query()
                    ->where('workspace_id', $wsId)
                    ->where('provider', $eng)
                    ->where('status', \App\Models\WaProviderConfig::STATUS_CONNECTED)
                    ->count();
            }
        }

        \Illuminate\Support\Facades\Log::info('[TEAM-INBOX] device count', [
            'auth_user_id'     => $user?->id,
            'auth_user_email'  => $user?->email,
            'workspace_id'     => $wsId,
            'workspace_user_ids' => $userIds->all(),
            'count_workspace'  => $connectedDevices,
            'count_mine'       => $myConnected,
            'count_any_active' => $anyForMe,
            'final'            => $connectedDevices,
        ]);

        return view('user.team-inbox.index', compact('connectedDevices'));
    }
    /**
     * /guidebook — list of published guidebook articles grouped by
     * category, plus a search box. Data lives in guidebook_articles
     * (admin manages at /admin/guidebook).
     */
    public function guidebook(\Illuminate\Http\Request $request): View
    {
        $q       = trim((string) $request->query('q', ''));
        $catSlug = trim((string) $request->query('category', ''));

        $base = \App\Models\GuidebookArticle::published();
        if ($q !== '') {
            $base->where(function ($w) use ($q) {
                $w->where('title',   'like', "%{$q}%")
                  ->orWhere('excerpt','like', "%{$q}%")
                  ->orWhere('body',   'like', "%{$q}%");
            });
        }
        if ($catSlug !== '') $base->where('category', $catSlug);
        $articles = $base->orderBy('sort_order')->orderBy('title')->get();

        // All-time per-category counts (ignore filters) so the sidebar
        // stays stable as the user narrows the right pane.
        $categories = \App\Models\GuidebookArticle::published()
            ->selectRaw('category, COUNT(*) as cnt')
            ->groupBy('category')
            ->orderBy('category')
            ->get();
        $totalCount = \App\Models\GuidebookArticle::published()->count();

        return view('user.guidebook.index', compact('articles', 'categories', 'totalCount', 'q', 'catSlug'));
    }

    /** /guidebook/{slug} — single article page. */
    public function guidebookShow(string $slug): View|\Illuminate\Http\RedirectResponse
    {
        $article = \App\Models\GuidebookArticle::published()->where('slug', $slug)->first();
        if (! $article) {
            return redirect()->route('user.guidebook')->with('error', 'Article not found.');
        }
        try { $article->increment('views_count'); } catch (\Throwable $e) {}
        $related = \App\Models\GuidebookArticle::published()
            ->where('category', $article->category)
            ->where('id', '!=', $article->id)
            ->orderBy('sort_order')->limit(5)->get();
        $categories = \App\Models\GuidebookArticle::published()
            ->selectRaw('category, COUNT(*) as cnt')
            ->groupBy('category')
            ->orderBy('category')
            ->get();
        $totalCount = \App\Models\GuidebookArticle::published()->count();
        return view('user.guidebook.show', compact('article', 'related', 'categories', 'totalCount'));
    }

    /** POST /guidebook/{slug}/vote — increment helpful or not_helpful. */
    public function guidebookVote(\Illuminate\Http\Request $request, string $slug): \Illuminate\Http\RedirectResponse
    {
        $data = $request->validate(['vote' => 'required|in:helpful,not_helpful']);
        $article = \App\Models\GuidebookArticle::published()->where('slug', $slug)->firstOrFail();
        $article->increment($data['vote'] === 'helpful' ? 'helpful_count' : 'not_helpful_count');
        return back()->with('success', $data['vote'] === 'helpful' ? 'Thanks for the feedback!' : 'Noted — we will improve this article.');
    }

    public function notifications(): View     { return view('user.notifications.index'); }
    public function settings(\Illuminate\Http\Request $request): View
    {
        $user = $request->user();
        $ws   = $user?->currentWorkspace;

        // Per-workspace BYOK keys (one row per provider). The model
        // decrypts the api_key via cast; we don't re-emit it in the
        // form — we only show whether each provider has a key set.
        $byokKeys = [];
        if ($ws) {
            foreach (\App\Models\AiProviderKey::where('workspace_id', $ws->id)->get() as $row) {
                $byokKeys[$row->provider] = $row;
            }
        }

        // Two-factor enrollment secret — generated lazily per session
        // until the user confirms, then promoted to users.two_factor_secret
        // inside SettingsTabsController::enableTwoFactor().
        $twoFactorSecret = null;
        $otpAuthUrl      = null;
        if ($user && !$user->two_factor_enabled) {
            try {
                $twoFactorSecret = $request->hasSession() ? $request->session()->get('two_factor_settings_secret') : null;
                if (!$twoFactorSecret) {
                    $twoFactorSecret = \App\Support\TwoFactorService::generateSecret();
                    if ($request->hasSession()) {
                        $request->session()->put('two_factor_settings_secret', $twoFactorSecret);
                    }
                }
                $otpAuthUrl = \App\Support\TwoFactorService::buildOtpAuthUrl($user, $twoFactorSecret, config('app.name', 'WaDesk'));
            } catch (\Throwable $e) {
                $twoFactorSecret = \App\Support\TwoFactorService::generateSecret();
                $otpAuthUrl      = \App\Support\TwoFactorService::buildOtpAuthUrl($user, $twoFactorSecret, config('app.name', 'WaDesk'));
            }
        }

        // Active sessions — Laravel's database driver writes one row
        // per session ID, which means a single browser shows up many
        // times (the framework regenerates the ID on login + on CSRF
        // token refresh). Consolidate by (ip, user_agent) so the user
        // sees ONE row per device, then paginate 10/page.
        $sessions = new \Illuminate\Pagination\LengthAwarePaginator([], 0, 10);
        try {
            if (\Illuminate\Support\Facades\Schema::hasTable('sessions') && $user) {
                $lifetime = (int) config('session.lifetime', 120);
                $cutoff   = now()->subMinutes($lifetime)->timestamp;
                $currentId = $request->hasSession() ? $request->session()->getId() : null;

                $rows = \Illuminate\Support\Facades\DB::table('sessions')
                    ->where('user_id', $user->id)
                    ->where('last_activity', '>=', $cutoff)
                    ->orderByDesc('last_activity')
                    ->get();

                // Group by (ip, user_agent) — keep the latest session
                // ID per device, but track the count + a "this device"
                // flag (true if any row in the group matches the
                // current session ID).
                $groups = [];
                foreach ($rows as $r) {
                    $key = ($r->ip_address ?? '') . '|' . md5((string) ($r->user_agent ?? ''));
                    if (!isset($groups[$key]) || $groups[$key]->last_activity < $r->last_activity) {
                        $existingCount = isset($groups[$key]) ? $groups[$key]->session_count : 0;
                        $existingCurrent = $groups[$key]->is_current ?? false;
                        $r->session_count = $existingCount + 1;
                        $r->is_current    = $existingCurrent || ($currentId && $r->id === $currentId);
                        $groups[$key] = $r;
                    } else {
                        $groups[$key]->session_count++;
                        if ($currentId && $r->id === $currentId) $groups[$key]->is_current = true;
                    }
                }
                // Sort: current device first, then by recency.
                $sorted = collect(array_values($groups))->sort(function ($a, $b) {
                    if ($a->is_current !== $b->is_current) return $a->is_current ? -1 : 1;
                    return $b->last_activity <=> $a->last_activity;
                })->values();

                $page    = max(1, (int) $request->query('session_page', 1));
                $perPage = 10;
                $offset  = ($page - 1) * $perPage;
                $items   = $sorted->slice($offset, $perPage)->values()->all();
                $sessions = new \Illuminate\Pagination\LengthAwarePaginator(
                    $items, $sorted->count(), $perPage, $page,
                    [
                        'path'     => $request->url(),
                        'pageName' => 'session_page',
                        'query'    => array_merge($request->query(), ['tab' => 'security']),
                    ]
                );
            }
        } catch (\Throwable $e) {
            \Log::warning('[UserPages] sessions load failed: ' . $e->getMessage(), [
                'user_id' => $userId,
                'exception' => get_class($e),
            ]);
        }

        // Workspace members for the team tab. Uses the `workspace_user`
        // pivot (NOT a hypothetical `workspace_members` table — the
        // existing WaDesk model is many-to-many through this pivot).
        $workspaceMembers = [];
        if ($ws && \Illuminate\Support\Facades\Schema::hasTable('workspace_user')) {
            try {
                $workspaceMembers = \Illuminate\Support\Facades\DB::table('workspace_user')
                    ->join('users', 'workspace_user.user_id', '=', 'users.id')
                    ->where('workspace_user.workspace_id', $ws->id)
                    ->select(
                        'users.id', 'users.name', 'users.email',
                        'workspace_user.role',
                        \Illuminate\Support\Facades\DB::raw('CASE WHEN workspace_user.joined_at IS NULL THEN "invited" ELSE "active" END as status')
                    )
                    ->get()
                    ->all();
            } catch (\Throwable $e) {
                \Log::warning('[UserPages] workspace members load failed: ' . $e->getMessage(), [
                    'workspace_id' => $ws->id,
                    'exception' => get_class($e),
                ]);
            }
        }

        return view('user.settings.index', compact(
            'byokKeys', 'twoFactorSecret', 'otpAuthUrl',
            'sessions', 'workspaceMembers'
        ));
    }

    /**
     * Persist workspace-level settings (timezone, currency, name).
     * Only the workspace owner can edit these — non-owners get a
     * silent ignore (the form fields render disabled for them anyway).
     */
    public function settingsUpdate(\Illuminate\Http\Request $request)
    {
        $data = $request->validate([
            'workspace_name' => ['nullable', 'string', 'max:191'],
            'timezone'       => ['nullable', 'string', 'max:64', 'timezone'],
            'currency'       => ['nullable', 'string', 'max:10', 'exists:currencies,code'],
        ]);

        $user = $request->user();
        $ws   = $user?->currentWorkspace;
        if (!$ws) return back()->with('settings_status', 'No workspace.');

        if ((int) $ws->owner_user_id !== (int) $user->id) {
            // Silent no-op for non-owners; UI also disables the inputs.
            return redirect()->route('user.settings')->with('settings_status', 'Only the workspace owner can edit these.');
        }

        $updates = [];
        if (!empty($data['workspace_name'])) $updates['name']     = $data['workspace_name'];
        if (!empty($data['timezone']))       $updates['timezone'] = $data['timezone'];
        if (!empty($data['currency']))       $updates['currency'] = strtoupper($data['currency']);
        if (!empty($updates)) $ws->update($updates);

        \App\Support\FormatSettings::flushCache();
        return redirect()->route('user.settings')->with('settings_status', 'Settings saved.');
    }
    public function more(): View
    {
        // Real recent activity for the sidebar — pulled from audit_logs.
        // Keeps the workspace context: shows what the signed-in user did
        // OR what happened in their current workspace, whichever is
        // wider. The full feed lives at /activity-log.
        $user = \Illuminate\Support\Facades\Auth::user();
        $userId = $user?->id;
        $workspaceId = $user?->current_workspace_id;

        // Workspace-scoped only. The earlier OR-logic (`actor = $user OR
        // workspace = $current_workspace`) leaked audit events from OTHER
        // workspaces where this user was the actor. We want only what
        // happened in the workspace currently selected from the switcher.
        $recentActivity = \App\Models\AuditLog::query()
            ->when($user?->current_workspace_id, fn ($q) => $q->where('workspace_id', $user->current_workspace_id))
            ->with('actor')
            ->orderByDesc('created_at')
            ->limit(5)
            ->get()
            ->map(function ($r) {
                $cat = explode('.', (string) $r->action, 2)[0] ?? 'other';
                return [
                    'id'         => $r->id,
                    'action'     => $r->action,
                    'category'   => $cat,
                    'label'      => $this->actionLabelFor((string) $r->action),
                    'when'       => optional($r->created_at)->diffForHumans(),
                    'badge'      => $this->categoryBadgeFor($cat),
                    'badgeBg'    => $this->categoryBadgeBg($cat),
                    'badgeFg'    => $this->categoryBadgeFg($cat),
                    'actor'      => $r->actor?->name ?: 'System',
                ];
            });

        // ── /more dashboard counters ─────────────────────────────────
        // Each card on the page shows a one-line "footer" stat. We
        // resolve them all here so the view stays a dumb template.
        $weekAgo = now()->subDays(7);

        // Top stats strip
        // Workspace-scoped counts. `keyword_replies` + `scheduled_messages`
        // already carry `workspace_id`; the rest (flows, webhooks, devices,
        // contacts, broadcasts, messages, etc.) still need a schema
        // migration before they can be filtered properly — see the
        // Multi-Workspace Scoping Audit doc. Until then they read `user_id`
        // and will show stale numbers after a workspace switch.
        // Every workspace-scoped table now has `workspace_id` (added
        // by the 2026_05_19_120000 backfill migration), so the /more
        // stats switch cleanly when the operator changes workspace.
        $autoReplyCount    = $workspaceId
            ? \App\Models\KeywordReply::where('workspace_id', $workspaceId)->count() : 0;
        $autoReplyNew      = $workspaceId
            ? \App\Models\KeywordReply::where('workspace_id', $workspaceId)->where('created_at', '>=', $weekAgo)->count() : 0;
        $flowCount         = $workspaceId
            ? \App\Models\Flow::where('workspace_id', $workspaceId)->count() : 0;
        $webhookActive     = $workspaceId
            ? \App\Models\Webhook::where('workspace_id', $workspaceId)->where('status', 1)->count() : 0;
        // "Active rules" must count LIVE automations only — a draft/disabled flow
        // or a disabled keyword reply is not an active rule (client saw "2" while
        // only one was live). Keyword replies gate on `status`, flows on
        // published+active (Flow's own live definition); webhooks already filtered.
        $activeRulesTotal  = ($workspaceId ? \App\Models\KeywordReply::where('workspace_id', $workspaceId)->where('status', true)->count() : 0)
            + ($workspaceId ? \App\Models\Flow::where('workspace_id', $workspaceId)->where('is_published', true)->where('is_active', true)->count() : 0)
            + $webhookActive;
        $activeRulesNew    = $autoReplyNew
            + ($workspaceId ? \App\Models\Flow::where('workspace_id', $workspaceId)->where('created_at', '>=', $weekAgo)->count() : 0)
            + ($workspaceId ? \App\Models\Webhook::where('workspace_id', $workspaceId)->where('created_at', '>=', $weekAgo)->count() : 0);

        $scheduledQueued = $workspaceId
            ? \App\Models\ScheduledMessage::where('workspace_id', $workspaceId)
                ->whereIn('status', ['scheduled', 'running'])->count()
            : 0;
        $nextScheduled   = $workspaceId
            ? \App\Models\ScheduledMessage::where('workspace_id', $workspaceId)
                ->whereIn('status', ['scheduled', 'running'])
                ->whereNotNull('next_run_at')
                ->orderBy('next_run_at')
                ->value('next_run_at')
            : null;
        $nextScheduledHuman = $nextScheduled ? $nextScheduled->diffForHumans() : 'no upcoming runs';

        // Engine-aware device totals for the dashboard footer cards.
        // Baileys counts paired phones; WABA / Twilio count their
        // wa_provider_configs rows of the active engine.
        $deviceEngine = $workspaceId ? \App\Services\WorkspaceEngine::for($workspaceId) : null;
        if ($workspaceId && $deviceEngine === \App\Services\WorkspaceEngine::ENGINE_BAILEYS) {
            $deviceTotal  = \App\Models\Device::where('workspace_id', $workspaceId)->count();
            $deviceActive = \App\Models\Device::where('workspace_id', $workspaceId)->where('active', 1)->count();
        } elseif ($workspaceId) {
            $waba = \App\Models\WaProviderConfig::where('workspace_id', $workspaceId)
                ->where('provider', $deviceEngine)->get(['status']);
            $deviceTotal  = $waba->count();
            $deviceActive = $waba->where('status', \App\Models\WaProviderConfig::STATUS_CONNECTED)->count();
        } else {
            $deviceTotal = 0; $deviceActive = 0;
        }
        $deviceHealth = $deviceTotal === 0 ? 'no devices' : ($deviceActive === $deviceTotal ? 'healthy' : ($deviceActive === 0 ? 'offline' : 'partial'));

        // Card footers — workspace-scoped via the new workspace_id
        // columns added in the 2026_05_19 backfill migration.
        $contactsTotal = $workspaceId ? \App\Models\Contact::where('workspace_id', $workspaceId)->count() : 0;
        $groupsTotal   = $workspaceId ? \App\Models\ContactGroup::where('workspace_id', $workspaceId)->count() : 0;
        $tagsTotal     = $workspaceId ? \App\Models\Tag::where('workspace_id', $workspaceId)->count() : 0;

        $broadcastQueued = $workspaceId
            ? \App\Models\Broadcast::where('workspace_id', $workspaceId)
                ->whereIn('status', ['pending', 'processing', 'scheduled'])->count()
            : 0;
        // Delivered % — over outgoing messages associated with broadcasts in the
        // last 30 days. Falls back to the simpler all-time delivered/sent ratio
        // if we can't find broadcast-attributable rows.
        $bcSinceMonth = now()->subDays(30);
        $outgoing = \App\Models\Message::query()
            ->when($workspaceId, fn ($q) => $q->where('workspace_id', $workspaceId))
            ->where('direction', 'out')
            ->where('created_at', '>=', $bcSinceMonth);
        $outTotal     = (clone $outgoing)->count();
        $outDelivered = (clone $outgoing)->whereIn('status', ['delivered', 'read'])->count();
        $broadcastDeliveredPct = $outTotal > 0 ? (int) round($outDelivered / $outTotal * 100) : null;

        // Auto-reply match rate — workspace-scoped via the lookups'
        // own workspace_id (backfilled by the legacy migration).
        $arLookups = \App\Models\AutoReplyLookup::query()
            ->when($workspaceId, fn ($q) => $q->where('workspace_id', $workspaceId))
            ->where('created_at', '>=', $bcSinceMonth);
        $arTotalLookups = (clone $arLookups)->count();
        $arMatched      = (clone $arLookups)->whereNotNull('matched_keyword_reply_id')->count();
        $autoReplyMatchPct = $arTotalLookups > 0 ? (int) round($arMatched / $arTotalLookups * 100) : null;

        $messageHistoryCount = $workspaceId
            ? \App\Models\Message::where('workspace_id', $workspaceId)->count()
            : 0;
        $messageHistoryHuman = $this->humanShortNumber($messageHistoryCount);

        // Notifications unread count — use the SAME canonical scope as the
        // notifications page + bell (Notification::forCurrentWorkspace). The old
        // raw `orWhereNull('workspace_id')` swept in EVERY legacy/system
        // notification row (NULL workspace_id is no longer a shared fallback per
        // the model docstring), so this card showed the same enormous number
        // (e.g. 77k) in every workspace instead of the operator's real unread.
        $notifActive = \App\Models\Notification::query()
            ->forCurrentWorkspace()
            ->where('status', true)
            ->count();

        // Webhook uptime — share of last-30d deliveries that returned 2xx.
        $whSince = now()->subDays(30);
        $whQuery = \App\Models\WebhookDelivery::query()
            ->whereHas('webhook', fn ($q) => $q->where('workspace_id', $workspaceId))
            ->where('fired_at', '>=', $whSince);
        $whTotal = (clone $whQuery)->count();
        $whOk    = (clone $whQuery)->whereBetween('status_code', [200, 299])->count();
        $webhookUptimePct = $whTotal > 0 ? round($whOk / $whTotal * 100, 1) : null;
        $webhookEndpoints = $workspaceId
            ? \App\Models\Webhook::where('workspace_id', $workspaceId)->count()
            : 0;

        // Plan card — workspace plan + a count of messages this calendar
        // month. There's no monthly cap stored anywhere yet, so we render
        // the count without a denominator (the view checks for null).
        $workspace = $workspaceId ? \App\Models\Workspace::find($workspaceId) : null;
        // Show the package's display name (pname), not the raw slug/id.
        // Scope-aware so account-mode shows the owner's effective plan.
        $planLabel = $workspace?->billingPackage()?->pname ?: ($workspace?->plan ?: 'Free');
        $monthStart = now()->startOfMonth();
        $messagesThisMonth = $workspaceId
            ? \App\Models\Message::where('workspace_id', $workspaceId)
                ->where('direction', 'out')
                ->where('created_at', '>=', $monthStart)
                ->count()
            : 0;
        $seatCount = $workspaceId
            ? \DB::table('workspace_user')->where('workspace_id', $workspaceId)->count()
            : 1;

        // ── Team Inbox hero counts ───────────────────────────────────
        // The /more hero used to hardcode `12 open / 8 unassigned /
        // 6 agents online`. Now driven by real queries scoped to the
        // current workspace. "Agents online" = workspace members whose
        // `last_active_at` is within the last 5 minutes — matches the
        // green-pill heuristic the rest of the app uses.
        $inboxOpen = $workspaceId
            ? \App\Models\Conversation::where('workspace_id', $workspaceId)
                ->where('inbox_status', 'open')->count()
            : 0;
        $inboxUnassigned = $workspaceId
            ? \App\Models\Conversation::where('workspace_id', $workspaceId)
                ->where('inbox_status', 'open')
                ->whereNull('assignee_user_id')->count()
            : 0;
        $agentsOnline = $workspaceId
            ? \DB::table('workspace_user')
                ->join('users', 'users.id', '=', 'workspace_user.user_id')
                ->where('workspace_user.workspace_id', $workspaceId)
                ->where('users.inbox_last_seen_at', '>=', now()->subMinutes(5))
                ->count()
            : 0;

        // ── Integrations card ────────────────────────────────────────
        // Three native integration models — Hubspot / Shopify / Woo —
        // each scoped by workspace_id (matches the existing
        // /integrations page). Count rows that report `status='active'`
        // since `isActive()` on each model is just that check plus an
        // access-token presence test we already trust the DB to honour.
        $integrationsConnected = 0;
        if ($workspaceId) {
            foreach ([
                \App\Models\HubspotIntegration::class,
                \App\Models\ShopifyIntegration::class,
                \App\Models\WoocommerceIntegration::class,
            ] as $mc) {
                $integrationsConnected += $mc::where('workspace_id', $workspaceId)
                    ->where('status', 'active')->count();
            }
        }
        // Available = the catalog size on the /integrations page. Keep
        // in sync if we ship new integrations.
        $integrationsAvailable = 3;

        // ── Affiliate stats ──────────────────────────────────────────
        // referrals = rows the current user generated as the referrer.
        // credits_awarded = sum of credits granted by those rows.
        $affiliateReferrals = $userId
            ? \App\Models\Referral::where('referrer_user_id', $userId)->count()
            : 0;
        $affiliateCredits = $userId
            ? (int) \App\Models\Referral::where('referrer_user_id', $userId)
                ->sum('credits_awarded')
            : 0;

        // ── Support tickets ──────────────────────────────────────────
        // Total = every ticket the operator has ever opened. Open =
        // anything not yet resolved (`open` / `awaiting_user` /
        // `awaiting_support`). The /more card chip renders both.
        $supportTotal = $userId
            ? \App\Models\SupportTicket::where('user_id', $userId)->count()
            : 0;
        $supportOpen  = $userId
            ? \App\Models\SupportTicket::where('user_id', $userId)
                ->where('status', '!=', 'resolved')->count()
            : 0;

        // ── Card subtitles that were previously hardcoded tagline text
        //     — converted to live counts so every /more tile shows real
        //     workspace data, not generic marketing copy.
        // ─────────────────────────────────────────────────────────────

        // Analytics card: messages sent in the last 7 days. Pulls from
        // the same inbox_messages source the /analytics page reads.
        $analytics7d = $workspaceId
            ? \DB::table('inbox_messages as im')
                ->join('conversations as c', 'c.id', '=', 'im.conversation_id')
                ->where('c.workspace_id', $workspaceId)
                ->where('im.direction', 'out')
                ->where('im.created_at', '>=', now()->subDays(7))
                ->count()
            : 0;

        // Attributes card: count of saved contact attributes for this
        // workspace (template variables the operator uses in broadcasts).
        // Table is `attributes`, not `contact_attributes`.
        $attributesTotal = $workspaceId
            ? \DB::table('attributes')->where('workspace_id', $workspaceId)->count()
            : 0;

        // Activity log card: events in the last 24h. Source is the
        // platform-wide `audit_logs` table (the same one /admin/audit-log
        // reads). Workspace-scoped via the workspace_id column on each
        // audit row.
        $activity24h = $workspaceId && \Schema::hasTable('audit_logs')
            ? \DB::table('audit_logs')
                ->where('workspace_id', $workspaceId)
                ->where('created_at', '>=', now()->subDay())
                ->count()
            : 0;

        // Guidebook card: how many articles are published. Driven by
        // the platform's guidebook content store.
        $guidebookArticles = (int) \DB::table('guidebook_articles')
            ->where('is_published', 1)
            ->count();

        // Pricing card: instead of a static "save 20%" tagline, show
        // either the current package name + period (active plan) or
        // a clear "free plan" label so operators see what they're on.
        $currentPackage = null;
        if ($workspaceId) {
            $ws = \App\Models\Workspace::find($workspaceId);
            // Scope-aware resolve (slug or numeric id; owner-wide in account mode).
            $currentPackage = $ws?->billingPackage();
        }
        $pricingSubtitle = $currentPackage
            ? trim(($currentPackage->pname ?: 'Plan') . ' · ' . ($currentPackage->plan_unit ?: 'monthly'))
            : 'Free plan · upgrade to unlock';

        $stats = [
            // top strip
            'activeRules'       => $activeRulesTotal,
            'activeRulesNew'    => $activeRulesNew,
            'scheduledQueued'   => $scheduledQueued,
            'nextScheduled'     => $nextScheduledHuman,
            'deviceActive'      => $deviceActive,
            'deviceTotal'       => $deviceTotal,
            'deviceHealth'      => $deviceHealth,

            // card footers
            'contactsTotal'     => $contactsTotal,
            'groupsTotal'       => $groupsTotal,
            'tagsTotal'         => $tagsTotal,
            'broadcastQueued'   => $broadcastQueued,
            'broadcastPct'      => $broadcastDeliveredPct,
            'autoReplyCount'    => $autoReplyCount,
            'autoReplyMatchPct' => $autoReplyMatchPct,
            'messageHistoryCount' => $messageHistoryCount,
            'messageHistoryHuman' => $messageHistoryHuman,
            'notifActive'       => $notifActive,
            'webhookEndpoints'  => $webhookEndpoints,
            'webhookUptimePct'  => $webhookUptimePct,

            // plan card
            'planLabel'         => $planLabel,
            'planSeats'         => $seatCount,
            'messagesThisMonth' => $messagesThisMonth,

            // wallet
            'walletCredits'     => (int) ($user?->wallet_credits ?? 0),

            // team inbox hero
            'inboxOpen'         => $inboxOpen,
            'inboxUnassigned'   => $inboxUnassigned,
            'agentsOnline'      => $agentsOnline,

            // integrations card
            'integrationsConnected' => $integrationsConnected,
            'integrationsAvailable' => $integrationsAvailable,

            // affiliate hero
            'affiliateReferrals' => $affiliateReferrals,
            'affiliateCredits'   => $affiliateCredits,

            // support card
            'supportTotal'       => $supportTotal,
            'supportOpen'        => $supportOpen,

            // Previously-hardcoded card subtitles — now live counts
            'analytics7d'        => $analytics7d,
            'attributesTotal'    => $attributesTotal,
            'activity24h'        => $activity24h,
            'guidebookArticles'  => $guidebookArticles,
            'pricingSubtitle'    => $pricingSubtitle,

            // Sales Pipeline card — live open-deal count.
            'dealsOpen'          => (int) \App\Models\Deal::forCurrentWorkspace()->open()->count(),
        ];

        return view('user.more.index', compact('recentActivity', 'stats'));
    }

    /**
     * Compact "12.4k / 1.2M" rendering for big counts. Kept here next
     * to its only caller because no other page needs this exact format.
     */
    private function humanShortNumber(int $n): string
    {
        if ($n < 1000)   return (string) $n;
        if ($n < 10000)  return number_format($n / 1000, 1) . 'k';
        if ($n < 1000000) return (int) round($n / 1000) . 'k';
        if ($n < 10000000) return number_format($n / 1000000, 1) . 'M';
        return (int) round($n / 1000000) . 'M';
    }

    private function actionLabelFor(string $action): string
    {
        $custom = [
            'auth.login' => 'Signed in',
            'auth.logout' => 'Signed out',
            'auth.failed' => 'Sign-in failed',
            'workspace.entered' => 'Switched workspace',
            'conversation.assigned' => 'Assigned a conversation',
            'conversation.resolved' => 'Resolved a conversation',
            'conversation.replied'  => 'Replied in inbox',
            'note.added' => 'Added internal note',
            'team.created' => 'Created a team',
        ];
        if (isset($custom[$action])) return $custom[$action];
        $parts = explode('.', $action, 2);
        return count($parts) === 2
            ? ucfirst(str_replace('_', ' ', $parts[0])) . ' ' . str_replace('_', ' ', $parts[1])
            : ucfirst(str_replace('_', ' ', $action));
    }

    private function categoryBadgeFor(string $cat): string
    {
        return match ($cat) {
            'auth' => 'AU',
            'conversation' => 'IN',
            'note' => 'NT',
            'team' => 'TM',
            'broadcast' => 'BC',
            'webhook' => 'WH',
            'workspace' => 'WS',
            default => '••',
        };
    }

    private function categoryBadgeBg(string $cat): string
    {
        return match ($cat) {
            'auth' => 'bg-wa-mint',
            'conversation' => 'bg-[#D9E5F2]',
            'note' => 'bg-[#F3E9FF]',
            'team' => 'bg-accent-amber/20',
            'broadcast' => 'bg-[#E8F5E9]',
            'webhook' => 'bg-paper-100',
            'workspace' => 'bg-wa-mint',
            default => 'bg-paper-100',
        };
    }

    private function categoryBadgeFg(string $cat): string
    {
        return match ($cat) {
            'auth' => 'text-wa-deep',
            'conversation' => 'text-[#13478A]',
            'note' => 'text-[#5B3D8A]',
            'team' => 'text-[#7B5A14]',
            'broadcast' => 'text-wa-deep',
            'webhook' => 'text-ink-700',
            'workspace' => 'text-wa-deep',
            default => 'text-ink-700',
        };
    }
    public function account(): View           { return view('user.account.index'); }
}
