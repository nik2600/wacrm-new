<x-layouts.user :title="__('Unified Inbox')" nav-key="team-inbox" page="user-team-inbox-index" :hide-header="true">
    <!-- ========== TOP BAR (shared) ========== -->
    <!-- ========== INBOX FRAME ========== -->
    <div id="ti-frame" class="ti-frame no-contact border-t border-paper-200">
        <!-- ========== COLUMN 1: OMNI DARK RAIL ========== -->
        {{-- Thin dark icon rail (Omni). Every workspace/teams/AI/automation
             nav link is preserved as an icon button with a tooltip; the dynamic
             teams list + Create-team live in a flyout opened by the Teams icon.
             All wired IDs (#ti-nav-toggle, #team-nav-list, #nav-ai-agents, …)
             are kept so the existing JS binds exactly as before. --}}
        <aside class="ti-col ti-rail-omni">
            {{-- Logo / escape hatch back to the rest of WaDesk. Neutral inbox
                 tray (this is the multi-channel unified inbox — not WhatsApp). --}}
            <a href="{{ url('/dashboard') }}" class="ti-rl-logo" title="{{ __('Back to WaDesk') }}">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                    stroke-linejoin="round">
                    <path d="M22 12h-6l-2 3h-4l-2-3H2" />
                    <path
                        d="M5.5 5.5 2 12v6a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-6l-3.5-6.5A2 2 0 0 0 16.8 4H7.2a2 2 0 0 0-1.7 1.5z" />
                </svg>
            </a>
            {{-- Colour-coded channel filter tabs (All / WhatsApp / Instagram / …).
                 data-channels lists the CONNECTED channels so a tab shows even
                 before any chat arrives; JS (renderRailChannels) adds live counts
                 + click-filters the queue. --}}
            @php
                $__tiWsId = optional(auth()->user())->current_workspace_id;
                $__tiChannels = ['wa'];
                if ($__tiWsId && \App\Models\WorkspaceIgAccount::hasConnected($__tiWsId)) {
                    $__tiChannels[] = 'ig';
                }
            @endphp
            <div id="ti-rail-channels" class="ti-rl-channels" data-channels="{{ implode(',', $__tiChannels) }}"></div>
            <hr class="ti-rl-div">
            <div class="ti-rl-nav">
                <a href="{{ url('/team-chat') }}" class="ti-rl-btn" title="{{ __('Team chat') }}">
                    <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5">
                        <path d="M2 4c0-1 .8-2 2-2h8c1.2 0 2 1 2 2v6c0 1-.8 2-2 2H7l-3 3v-3H4c-1.2 0-2-1-2-2z" />
                    </svg>
                </a>
                {{-- "All teams" grid button removed 2026-07-29 (user request): on a
                     single-member workspace it's redundant — the default state already
                     shows every thread, and its count badge disagreed with the queue.
                     A hidden stub keeps the data-team-nav="all" JS reset target alive so
                     picking a team can still clear back to all without a null handler. --}}
                <button data-team-nav="all" class="hidden" aria-hidden="true"><span class="ct b"
                        data-nav-count="all">0</span></button>
                {{-- Teams flyout — the dynamic team list + Create-team, tucked
                     behind this icon so the thin rail stays uncluttered. --}}
                <div class="ti-rl-teamswrap">
                    <button type="button" id="ti-rl-teams-toggle" class="ti-rl-btn" title="{{ __('Teams') }}">
                        <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5">
                            <circle cx="5.5" cy="6" r="2" />
                            <circle cx="11" cy="6.5" r="1.6" />
                            <path d="M1.5 13c0-2 1.4-3.2 3.5-3.2M9 12.4c.2-1.6 1.3-2.5 2.7-2.5s2.3.9 2.5 2.3" />
                        </svg>
                    </button>
                    <div id="ti-rl-teams-pop" class="ti-rl-pop hidden">
                        <div class="flex items-center justify-between px-1 pb-1.5 mb-1 border-b border-paper-200">
                            <span
                                class="font-mono text-[10px] uppercase tracking-[0.14em] text-ink-500">{{ __('Teams') }}</span>
                            <button id="create-team-btn" title="{{ __('Create team') }}"
                                class="ti-nav-add w-5 h-5 rounded hover:bg-paper-200 grid place-items-center text-ink-500 hover:text-ink-900">
                                <svg viewBox="0 0 16 16" class="w-3 h-3" fill="none" stroke="currentColor"
                                    stroke-width="2">
                                    <path d="M8 3v10M3 8h10" />
                                </svg>
                            </button>
                        </div>
                        <div id="team-nav-list" class="space-y-0.5"></div>
                        <div id="team-nav-empty" class="hidden text-[11px] text-ink-500 px-1 py-2 leading-snug">
                            {{ __('No teams yet — click') }} <button class="text-wa-deep font-semibold"
                                data-open-create-team>+ {{ __('Create team') }}</button>
                        </div>
                    </div>
                </div>
                <a href="{{ url('/team-inbox/kanban') }}" class="ti-rl-btn" title="{{ __('Kanban view') }}">
                    <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5">
                        <rect x="2" y="2" width="3" height="12" rx="1" />
                        <rect x="6.5" y="2" width="3" height="9" rx="1" />
                        <rect x="11" y="2" width="3" height="6" rx="1" />
                    </svg>
                </a>
                @canWorkspace('member.invite')
                <a href="{{ url('/team-inbox/members') }}" class="ti-rl-btn" title="{{ __('Members') }}">
                    <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5">
                        <circle cx="6" cy="6" r="2.5" />
                        <path d="M2 14c0-3 1.7-5 4-5s4 2 4 5" />
                        <circle cx="11.5" cy="7" r="2" />
                        <path d="M9 14c0-2 1.5-3.5 3-3.5s3 1.5 3 3.5" />
                    </svg>
                </a>
                @endcanWorkspace
                @canWorkspace('integration.manage')
                <button id="nav-ai-agents" class="ti-rl-btn" title="{{ __('AI Agents') }}">
                    <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5">
                        <rect x="3" y="5" width="10" height="8" rx="2" />
                        <path d="M6 5V3.5A2 2 0 0 1 10 3.5V5" />
                        <circle cx="6" cy="9" r="1" fill="currentColor" stroke="none" />
                        <circle cx="10" cy="9" r="1" fill="currentColor" stroke="none" />
                        <path d="M5.5 11.5c.7.7 3.3.7 4 0" />
                    </svg>
                    <span class="ct b" id="nav-ai-count">0</span>
                </button>
                @endcanWorkspace
                @canWorkspace('routing.manage')
                <button id="nav-routing" class="ti-rl-btn" title="{{ __('Routing Rules') }}">
                    <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5">
                        <path d="M3 4h10M5 8h6M7 12h2" />
                    </svg>
                    <span class="ct b" id="nav-routing-count" style="display:none">0</span>
                </button>
                @endcanWorkspace
                @canWorkspace('savedreply.manage')
                <button id="nav-quick-replies" class="ti-rl-btn" title="{{ __('Quick Replies') }}">
                    <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5">
                        <path d="M2 4h12v8H4l-2 2z" />
                        <path d="M5 7.5h6M5 10.5h3.5" />
                    </svg>
                    <span class="ct b" id="nav-quick-replies-count" style="display:none">0</span>
                </button>
                @endcanWorkspace
                @canWorkspace('sla.manage')
                <a href="{{ url('/team-inbox/sla') }}" class="ti-rl-btn" title="{{ __('SLA policies') }}">
                    <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5">
                        <circle cx="8" cy="8" r="6" />
                        <path d="M8 4.5v3.5l2.2 1.3" />
                    </svg>
                </a>
                @endcanWorkspace
                @canWorkspace('analytics.view')
                <a href="{{ url('/team-inbox/analytics/team') }}" class="ti-rl-btn"
                    title="{{ __('Team Performance') }}">
                    <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5">
                        <path d="M2 12l3-4 3 2 3-6 3 3" />
                        <path d="M2 12h12" />
                    </svg>
                </a>
                <a href="{{ url('/team-inbox/analytics/ai-agents') }}" class="ti-rl-btn"
                    title="{{ __('AI Performance') }}">
                    <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5">
                        <rect x="3" y="5" width="10" height="8" rx="2" />
                        <path d="M6 5V3.5A2 2 0 0 1 10 3.5V5" />
                        <path d="M6 9h.01M10 9h.01" />
                    </svg>
                </a>
                @endcanWorkspace
            </div>
            <div class="ti-rl-sp"></div>
            @canWorkspace('member.invite')
            <button type="button" data-open-invite class="ti-rl-btn" title="{{ __('Invite teammate') }}">
                <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5">
                    <circle cx="6" cy="6" r="2.5" />
                    <path d="M2 14c0-3 1.7-5 4-5s4 2 4 5M12 5v4M14 7h-4" />
                </svg>
            </button>
            @endcanWorkspace
            {{-- Back to the devices / channels page. --}}
            <a href="{{ url('/devices') }}" class="ti-rl-btn" title="{{ __('Back to devices') }}">
                <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.6"
                    stroke-linecap="round" stroke-linejoin="round">
                    <path d="M10 3 5 8l5 5" />
                </svg>
            </a>
            {{-- Signed-in user. --}}
            <span class="ti-rl-avatar"
                title="{{ optional(auth()->user())->name }}">{{ strtoupper(mb_substr((string) (optional(auth()->user())->name ?: 'U'), 0, 2)) }}</span>
            {{-- Preserved for the existing nav-toggle JS binding (no visible collapse
                 control in the thin rail — it's already at its narrow width). --}}
            <button type="button" id="ti-nav-toggle" class="hidden" aria-controls="ti-frame"></button>
        </aside>
        <!-- ========== COLUMN 2: queue ========== -->
        <aside class="ti-col bg-paper-0 border-r border-paper-200">
            <div class="px-3 pt-3 pb-2 border-b border-paper-200 shrink-0 space-y-2">
                <div class="flex items-center justify-between gap-2">
                    <h1 class="font-serif text-[18px] leading-tight">{{ __('Unified') }} <span
                            class="italic text-wa-deep">{{ __('inbox') }}</span></h1>
                    <div class="flex items-center gap-1">
                        {{-- Team chat moved to the left rail, above All teams. --}}
                        <button id="compose-new-btn" type="button" title="{{ __('New message') }}"
                            class="w-7 h-7 rounded-full bg-wa-deep text-paper-0 hover:bg-wa-teal grid place-items-center shrink-0">
                            <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor"
                                stroke-width="2">
                                <path d="M8 3v10M3 8h10" />
                            </svg>
                        </button>
                        <button id="queue-refresh-btn" type="button" title="{{ __('Refresh queue') }}"
                            class="w-7 h-7 rounded-full hover:bg-paper-100 grid place-items-center text-ink-500 hover:text-wa-deep">
                            <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor"
                                stroke-width="1.7">
                                <path d="M14 3v4h-4M2 13v-4h4M3 7a5 5 0 0 1 9-2M13 9a5 5 0 0 1-9 2" />
                            </svg>
                        </button>
                        {{-- Duplicate-thread cleanup. Manager+ (inbox.bulk mirrors the
                             route's workspace.role:manager gate). Only useful on
                             workspaces carried over from an older build, so it opens a
                             scan-first modal rather than acting on click. --}}
                        @canWorkspace('inbox.bulk')
                        <button id="merge-dupes-btn" type="button"
                            title="{{ __('Find and merge duplicate chats') }}"
                            class="w-7 h-7 rounded-full hover:bg-paper-100 grid place-items-center text-ink-500 hover:text-wa-deep">
                            {{-- Two streams converging into one, arrow out to the
                                     right. The previous glyph drew its branches to
                                     (12,9) and (12,7) — parallel, never meeting — with
                                     the arrowhead pinned to neither, so a "merge" icon
                                     read as two unrelated lines. Both arcs now land
                                     exactly on (9,8), share one stem, and the arrow tip
                                     sits on the stem's end at (13,8). --}}
                            {{-- Git-merge glyph: two branch dots on the left, both
                                     arcs converging onto one node that continues right —
                                     unmistakably "merge into one", not a forward arrow. --}}
                            <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor"
                                stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                                <circle cx="3.5" cy="3.5" r="1.5" />
                                <circle cx="3.5" cy="12.5" r="1.5" />
                                <circle cx="12.5" cy="8" r="1.5" />
                                <path d="M5 3.7c1.2 2.4 2.6 4.3 6 4.3M5 12.3c1.2-2.4 2.6-4.3 6-4.3" />
                            </svg>
                        </button>
                        @endcanWorkspace
                        {{-- Coexistence only: pull the last 6 months of phone-app chats
                             from Meta into the inbox. Own form (no JS); shown only when
                             a Coexistence WABA number is connected. --}}
                        @if (!empty($coexHistoryConfigId))
                            {{-- Name is `user.team-inbox.sync-history`: the route is declared
                                 inside the Route::name('user.') group in routes/web.php, so the
                                 `user.` prefix is REQUIRED. Without it this threw
                                 "Route [team-inbox.sync-history] not defined" and 500'd the
                                 whole /team-inbox page for any Coexistence workspace. --}}
                            <form method="POST" action="{{ route('user.team-inbox.sync-history') }}" class="inline"
                                title="{{ __('Import 6-month chat history from WhatsApp (Coexistence)') }}">
                                @csrf
                                <button type="submit"
                                    class="w-7 h-7 rounded-full hover:bg-paper-100 grid place-items-center text-ink-500 hover:text-wa-deep">
                                    <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none"
                                        stroke="currentColor" stroke-width="1.6" stroke-linecap="round"
                                        stroke-linejoin="round">
                                        <path d="M8 4.5v3.5l2.2 1.3" />
                                        <circle cx="8" cy="8" r="6" />
                                    </svg>
                                </button>
                            </form>
                        @endif
                    </div>
                </div>
                {{-- History-sync result banner (from the sync-history POST). --}}
                @if (session('status'))
                    <div
                        class="mx-3 mt-2 rounded-lg border border-wa-green/40 bg-wa-mint px-3 py-2 text-[11px] text-wa-deep leading-relaxed">
                        {{ session('status') }}
                    </div>
                @endif
                @error('waba')
                    <div
                        class="mx-3 mt-2 rounded-lg border border-amber-300 bg-amber-50 px-3 py-2 text-[11px] text-amber-800 leading-relaxed">
                        {{ $message }}
                    </div>
                @enderror
                <div class="relative">
                    <svg viewBox="0 0 16 16"
                        class="w-3.5 h-3.5 absolute left-2.5 top-1/2 -translate-y-1/2 text-ink-500" fill="none"
                        stroke="currentColor" stroke-width="1.6">
                        <circle cx="7" cy="7" r="5" />
                        <path d="m11 11 3 3" />
                    </svg>
                    <input id="search" type="search" placeholder="{{ __('Search…') }}"
                        class="w-full pl-8 pr-3 py-1.5 border border-paper-200 rounded-md bg-paper-50 text-[12.5px] focus:outline-none focus:bg-paper-0 focus:border-wa-deep" />
                </div>
                {{-- Omni segmented quick-filter (All / Unread / …) with a sliding
                     thumb — matches the mockup's .seg. JS positions #seg-thumb
                     under the active button; solo workspaces see just All+Unread
                     (Mine/Unassigned/@me hidden by syncQueueTabsVisibility). --}}
                {{-- Mine/Unassigned/@me start HIDDEN so a solo workspace's first
                     paint already matches its final state (just All + Unread) —
                     no flash of extra tabs before syncQueueTabsVisibility() runs.
                     JS reveals them (display:'') only when the workspace has >1
                     member; the common solo case needs no post-load reflow. --}}
                <div class="ti-seg" id="queue-tabs">
                    <span class="ti-seg-thumb" id="seg-thumb"></span>
                    <button data-queue="all" class="on">{{ __('All') }} <span class="n"
                            data-count="all"></span></button>
                    <button data-queue="mine" style="display:none">{{ __('Mine') }} <span class="n"
                            data-count="mine"></span></button>
                    <button data-queue="unassigned" style="display:none">{{ __('Unassigned') }} <span class="n"
                            data-count="unassigned"></span></button>
                    <button data-queue="mentions" style="display:none">@me <span class="n"
                            data-count="mentions"></span></button>
                    <button data-queue="unread">{{ __('Unread') }} <span class="n"
                            data-count="unread"></span></button>
                </div>
                @if (($deviceFilterOptions ?? collect())->count() > 1)
                    {{-- Device filter — only render when the workspace has more
 than one paired device (single-device setups don't need
 this UI, it'd just be dead space). The JS reads the
 selection, persists to ?device_id= in the URL, and
 narrows /team-inbox/api/queue server-side. --}}
                    <div>
                        <select id="inbox-device-filter"
                            class="w-full px-2.5 py-1.5 text-[12px] rounded-md border border-paper-200 bg-paper-50 focus:outline-none focus:bg-paper-0 focus:border-wa-deep">
                            <option value="">{{ __('All channels') }}</option>
                            @foreach ($deviceFilterOptions as $d)
                                {{-- Value is "engine:id" (e.g. baileys:3 / waba:5) so the
                                     server filters device_id AND provider together — the
                                     polymorphic device_id can't collide across the two
                                     source tables. JS passes the value through verbatim. --}}
                                <option value="{{ $d['engine_key'] ?? 'baileys' }}:{{ $d['id'] }}">
                                    {{ $d['engine'] ?? __('Unofficial API') }} · {{ $d['label'] }}@if(!empty($d['phone'])) · {{ $d['phone'] }}@endif
                                </option>
                            @endforeach
                        </select>
                    </div>
                @endif
                {{-- Label filter — options populated from workspace tags
                     (state.tags) by JS; the whole control is hidden until the
                     workspace has at least one label. Narrows the queue to one
                     tag via ?tag_id= server-side. --}}
                <div id="inbox-label-filter-wrap" class="hidden">
                    <select id="inbox-label-filter"
                        class="w-full px-2.5 py-1.5 text-[12px] rounded-md border border-paper-200 bg-paper-50 focus:outline-none focus:bg-paper-0 focus:border-wa-deep">
                        <option value="">{{ __('All labels') }}</option>
                    </select>
                </div>
                <!-- Bulk-select toolbar + active team label -->
                <div class="flex items-center justify-between text-[10.5px] text-ink-500">
                    <button id="bulk-toggle" class="inline-flex items-center gap-1.5 hover:text-ink-900">
                        <svg viewBox="0 0 16 16" class="w-3 h-3" fill="none" stroke="currentColor"
                            stroke-width="1.7">
                            <rect x="3" y="3" width="10" height="10" rx="2" />
                        </svg>
                        <span id="bulk-toggle-label">{{ __('Select') }}</span>
                    </button>
                    <span id="active-team-label"
                        class="font-mono uppercase tracking-wider text-[10px]">{{ __('All teams') }}</span>
                </div>
            </div>
            {{-- WhatsApp-style "Archived" row — opens the archived view. Hidden
                 when there are no archived chats. In archived view it flips into
                 a back-header (JS toggles data-active + the label/icon). --}}
            <button id="archived-row" data-active="0" type="button"
                class="hidden w-full items-center gap-2.5 px-3 py-2.5 border-b border-paper-200 text-left hover:bg-paper-50 text-[13px] text-ink-700 shrink-0">
                <svg id="archived-row-icon" viewBox="0 0 16 16" class="w-4 h-4 shrink-0" fill="none"
                    stroke="currentColor" stroke-width="1.6">
                    <rect x="2" y="3" width="12" height="3" rx="1" />
                    <path d="M3 6v6a1 1 0 0 0 1 1h8a1 1 0 0 0 1-1V6M6.5 9h3" />
                </svg>
                <span id="archived-row-label" class="flex-1 font-medium">{{ __('Archived') }}</span>
                <span id="archived-row-count" class="font-mono text-[11px] text-ink-500"></span>
            </button>
            {{-- Omni channel filter — colour-coded All / WhatsApp / Instagram / …
                 Rendered + wired in JS (renderChannelTabs); stays hidden until the
                 workspace has more than one channel in the queue. --}}
            {{-- (Channel filter lives in the dark rail; the horizontal channel-tabs
                 row was removed as redundant.) --}}
            <div class="flex-1 overflow-y-auto" id="conv-list">
                {{-- Skeleton-shimmer placeholders so the queue doesn't flash empty
 on reload. JS replaces this innerHTML on the first loadQueue()
 render. --}}
                @for ($i = 0; $i < 8; $i++)
                    <div class="flex items-start gap-2.5 px-3 py-2.5 border-b border-paper-200">
                        <div class="skeleton w-9 h-9 rounded-full shrink-0"></div>
                        <div class="flex-1 space-y-1.5 min-w-0">
                            <div class="flex items-center gap-2">
                                <div class="skeleton h-3 rounded"
                                    style="width: {{ [60, 75, 50, 70, 65, 80, 55, 72][$i] }}%;"></div>
                                <div class="skeleton h-2.5 rounded w-8 ml-auto"></div>
                            </div>
                            <div class="skeleton h-2.5 rounded"
                                style="width: {{ [85, 70, 92, 60, 78, 55, 88, 65][$i] }}%;"></div>
                            <div class="flex items-center gap-1 mt-1">
                                <div class="skeleton h-3 rounded-full"
                                    style="width: {{ [50, 60, 45, 70][$i % 4] }}px;"></div>
                                <div class="skeleton h-3 rounded-full w-12"></div>
                            </div>
                        </div>
                    </div>
                @endfor
            </div>
            <!-- Bulk action bar (hidden until items selected) -->
            <div id="bulk-bar"
                class="hidden border-t border-paper-200 bg-wa-deep text-paper-0 px-3 py-2 flex flex-wrap items-center gap-2 text-[12px] shrink-0">
                <span class="font-mono"><span id="bulk-count">0</span> selected</span>
                <div class="flex-1"></div>
                <button id="bulk-assign"
                    class="px-2 py-1 rounded-full bg-paper-0/15 hover:bg-paper-0/25 inline-flex items-center gap-1">
                    <svg viewBox="0 0 16 16" class="w-3 h-3" fill="none" stroke="currentColor"
                        stroke-width="1.6">
                        <path d="M6 3a3 3 0 1 1 0 6 3 3 0 0 1 0-6zM2 14c0-3 2.2-5 4-5s4 2 4 5" />
                    </svg>
                    Assign
                </button>
                <button id="bulk-close"
                    class="px-2 py-1 rounded-full bg-paper-0/15 hover:bg-paper-0/25 inline-flex items-center gap-1">
                    <svg viewBox="0 0 16 16" class="w-3 h-3" fill="none" stroke="currentColor"
                        stroke-width="1.7">
                        <path d="M3 8l3 3 7-7" />
                    </svg>
                    Resolve
                </button>
                <button id="bulk-tag"
                    class="px-2 py-1 rounded-full bg-paper-0/15 hover:bg-paper-0/25 inline-flex items-center gap-1">
                    <svg viewBox="0 0 16 16" class="w-3 h-3" fill="none" stroke="currentColor"
                        stroke-width="1.6">
                        <path d="M3 3h6l5 5-6 6-5-5z" />
                        <circle cx="6" cy="6" r="0.8" />
                    </svg>
                    Tag
                </button>
                <button id="bulk-pin"
                    class="px-2 py-1 rounded-full bg-paper-0/15 hover:bg-paper-0/25 inline-flex items-center gap-1">
                    <svg viewBox="0 0 16 16" class="w-3 h-3" fill="none" stroke="currentColor"
                        stroke-width="1.6">
                        <path d="M9.5 2l4.5 4.5-2 .5-2.5 2.5-.5 3-1.5-1.5L3 14l3.5-4 -1.5-1.5 3-.5L10.5 5 9.5 2z" />
                    </svg>
                    Pin
                </button>
                <button id="bulk-mute"
                    class="px-2 py-1 rounded-full bg-paper-0/15 hover:bg-paper-0/25 inline-flex items-center gap-1">
                    <svg viewBox="0 0 16 16" class="w-3 h-3" fill="none" stroke="currentColor"
                        stroke-width="1.6">
                        <path d="M4 6v4h2l3 3V3L6 6H4zM11 6l3 4M14 6l-3 4" />
                    </svg>
                    Mute
                </button>
                <button id="bulk-archive"
                    class="px-2 py-1 rounded-full bg-paper-0/15 hover:bg-paper-0/25 inline-flex items-center gap-1">
                    <svg viewBox="0 0 16 16" class="w-3 h-3" fill="none" stroke="currentColor"
                        stroke-width="1.6">
                        <rect x="2" y="3" width="12" height="3" rx="1" />
                        <path d="M3 6v6a1 1 0 0 0 1 1h8a1 1 0 0 0 1-1V6M6.5 9h3" />
                    </svg>
                    Archive
                </button>
                <button id="bulk-delete"
                    class="px-2 py-1 rounded-full bg-red-500/25 hover:bg-red-500/40 inline-flex items-center gap-1">
                    <svg viewBox="0 0 16 16" class="w-3 h-3" fill="none" stroke="currentColor"
                        stroke-width="1.6">
                        <path d="M3 4h10M6 4V3a1 1 0 0 1 1-1h2a1 1 0 0 1 1 1v1M5 4v9a1 1 0 0 0 1 1h4a1 1 0 0 0 1-1V4" />
                    </svg>
                    Delete
                </button>
                <button id="bulk-cancel" class="px-2 py-1 rounded-full hover:bg-paper-0/15">×</button>
            </div>
            <div
                class="px-3 py-2 border-t border-paper-200 bg-paper-50/40 flex items-center gap-2 text-[10.5px] text-ink-600 shrink-0">
                {{-- Clickable avatar stack — navigates to /team-inbox/members so the
 operator can manage the team without leaving the inbox surface.
 Only owner/admin actually have access to that page; for others the
 server-side workspace.role middleware will bounce back. --}}
                <a href="{{ url('/team-inbox/members') }}"
                    class="flex items-center gap-2 flex-1 min-w-0 hover:opacity-80"
                    title="{{ __('View all members') }}">
                    <div id="presence-avatars" class="flex items-center -space-x-1.5"></div>
                    <span id="presence-summary" class="font-mono truncate text-wa-deep">—</span>
                </a>
                @canWorkspace('member.invite')
                <button type="button" data-open-invite title="{{ __('Invite teammate') }}"
                    class="w-6 h-6 rounded-full bg-wa-deep text-paper-0 hover:bg-wa-teal grid place-items-center shrink-0">
                    <svg viewBox="0 0 16 16" class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M8 3v10M3 8h10" />
                    </svg>
                </button>
                @endcanWorkspace
            </div>
        </aside>
        <!-- ========== MIDDLE: thread ========== -->
        <main class="ti-col bg-paper-0 relative">
            {{-- ===== COMPOSE: brand-new message to many recipients ===== --}}
            <div id="compose-panel" class="hidden absolute inset-0 z-30 bg-paper-0 flex flex-col">
                <div class="px-5 py-3 border-b border-paper-200 flex items-center justify-between gap-3 shrink-0">
                    <div class="font-serif text-[17px] leading-tight">{{ __('New') }} <span
                            class="italic text-wa-deep">{{ __('message') }}</span></div>
                    <button id="compose-close" type="button" title="{{ __('Close') }}"
                        class="w-8 h-8 rounded-full hover:bg-paper-100 grid place-items-center text-ink-500">
                        <svg viewBox="0 0 16 16" class="w-4 h-4" fill="none" stroke="currentColor"
                            stroke-width="1.8">
                            <path d="M4 4l8 8M12 4l-8 8" />
                        </svg>
                    </button>
                </div>
                <div class="flex-1 overflow-y-auto px-5 py-4 space-y-4">
                    {{-- Channel tabs. WhatsApp = message any number (contacts /
                         manual). Instagram = message people who already DM'd your
                         account (IG can't cold-message a number), so the recipient
                         picker below swaps to your existing IG conversations. The
                         IG tab is revealed by JS only when an IG account is
                         connected. --}}
                    <div id="compose-tabs" class="flex items-center gap-1">
                        <button type="button" data-ctab="wa" class="ti-tab active">{{ __('WhatsApp') }}</button>
                        <button type="button" data-ctab="ig" id="compose-tab-ig"
                            class="ti-tab hidden">{{ __('Instagram') }}</button>
                    </div>
                    <div>
                        <label
                            class="block font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500 mb-1.5">{{ __('Send from') }}</label>
                        <select id="compose-channel"
                            class="w-full px-3 py-2 text-[13px] rounded-lg border border-paper-200 bg-paper-50 focus:bg-paper-0 focus:border-wa-deep focus:outline-none">
                            <option value="">{{ __('Loading channels…') }}</option>
                        </select>
                    </div>
                    <div class="flex items-center justify-between">
                        <label
                            class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">{{ __('Recipients') }}</label>
                        <span id="compose-reccount" class="text-[11px] font-mono text-wa-deep">0
                            {{ __('selected') }}</span>
                    </div>
                    {{-- WhatsApp recipients: manual numbers + groups + saved contacts. --}}
                    <div id="compose-wa-recipients" class="space-y-4">
                        <div>
                            <label
                                class="block text-[11.5px] font-semibold text-ink-700 mb-1">{{ __('Manual numbers') }}</label>
                            <textarea id="compose-numbers" rows="2"
                                placeholder="{{ __('+91 98xxxxxxx, +1 415xxxxxxx — comma or new line') }}"
                                class="w-full px-3 py-2 text-[13px] rounded-lg border border-paper-200 bg-paper-50 focus:bg-paper-0 focus:border-wa-deep focus:outline-none"></textarea>
                        </div>
                        <div id="compose-groups-wrap" class="hidden">
                            <label
                                class="block text-[11.5px] font-semibold text-ink-700 mb-1">{{ __('Contact groups') }}</label>
                            <div id="compose-groups" class="flex flex-wrap gap-1.5"></div>
                        </div>
                        <div>
                            <div class="flex items-center justify-between mb-1">
                                <label class="text-[11.5px] font-semibold text-ink-700">{{ __('Contacts') }}</label>
                                <input id="compose-contact-search" type="search" placeholder="{{ __('Search…') }}"
                                    class="px-2 py-1 text-[12px] rounded-md border border-paper-200 bg-paper-50 focus:bg-paper-0 focus:border-wa-deep focus:outline-none w-36">
                            </div>
                            <div id="compose-contacts"
                                class="border border-paper-200 rounded-lg max-h-52 overflow-y-auto divide-y divide-paper-100 text-[12.5px]">
                            </div>
                        </div>
                    </div>
                    {{-- Instagram recipients: pick from existing IG conversations
                         (people who've DM'd the account). Populated by JS from
                         /compose/options → ig_conversations. --}}
                    <div id="compose-ig-recipients" class="hidden">
                        <div class="flex items-center justify-between mb-1">
                            <label
                                class="text-[11.5px] font-semibold text-ink-700">{{ __('Instagram conversations') }}</label>
                            <input id="compose-ig-search" type="search" placeholder="{{ __('Search…') }}"
                                class="px-2 py-1 text-[12px] rounded-md border border-paper-200 bg-paper-50 focus:bg-paper-0 focus:border-wa-deep focus:outline-none w-36">
                        </div>
                        <p class="text-[11px] text-ink-500 mb-1.5 leading-snug">
                            {{ __('Instagram only lets you message people who have already messaged your account. Pick one or more conversations.') }}
                        </p>
                        <div id="compose-ig-list"
                            class="border border-paper-200 rounded-lg max-h-64 overflow-y-auto divide-y divide-paper-100 text-[12.5px]">
                        </div>
                    </div>
                </div>
                <div class="border-t border-paper-200 px-5 py-3 shrink-0 space-y-2">
                    <textarea id="compose-body" rows="3" maxlength="4096" placeholder="{{ __('Type a message…') }}"
                        class="w-full px-3 py-2 text-[13px] rounded-lg border border-paper-200 bg-paper-50 focus:bg-paper-0 focus:border-wa-deep focus:outline-none resize-none"></textarea>
                    <div class="flex items-center justify-between gap-2">
                        <div id="compose-status" class="text-[11.5px] text-ink-500"></div>
                        <button id="compose-send" type="button"
                            class="px-4 py-2 rounded-full bg-wa-deep text-paper-0 text-[12.5px] font-semibold hover:bg-wa-teal inline-flex items-center gap-1.5 disabled:opacity-50">
                            <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor"
                                stroke-width="1.7">
                                <path d="M2 8l12-5-5 12-2-5-5-2z" />
                            </svg>
                            {{ __('Send') }}
                        </button>
                    </div>
                </div>
            </div>
            {{-- Active-conversation header. Empty by default — JS fills it
 once the user picks a row from the queue, otherwise the empty
 state in #thread renders. --}}
            <div id="th-header" class="px-5 py-3 border-b border-paper-200 flex items-center gap-3 shrink-0 hidden">
                <button id="mobile-back-btn"
                    class="mobile-back w-9 h-9 rounded-full hover:bg-paper-50 grid place-items-center shrink-0"
                    title="{{ __('Back to queue') }}">
                    <svg viewBox="0 0 16 16" class="w-4 h-4" fill="none" stroke="currentColor"
                        stroke-width="1.8">
                        <path d="M10 3l-4 5 4 5" />
                    </svg>
                </button>
                <span class="ti-avwrap shrink-0">
                    <span id="th-avatar"
                        class="w-10 h-10 rounded-full text-paper-0 font-semibold grid place-items-center bg-wa-deep"></span>
                    {{-- Channel badge — the platform this thread arrived on (WA/IG/…).
                         Class + glyph set per-conversation in renderActive(). --}}
                    <span id="th-chbadge" class="ti-chbadge ch-wa" style="width:16px;height:16px"></span>
                </span>
                <div class="min-w-0 flex-1">
                    {{-- The device pill moved UP to the badge row. It used to
                         sit under the number, where it claimed a whole second
                         line for one word. Beside OPEN / SLA it reads as one
                         status strip and the header loses a row. --}}
                    <div class="flex items-center gap-2 flex-wrap">
                        <div id="th-name" class="font-serif text-[17px] leading-tight"></div>
                        <span id="th-status" class="pill pill-open"></span>
                        <span id="th-device-state"
                            class="hidden inline-flex items-center gap-1 px-1.5 py-0.5 rounded-full text-[10px] font-mono"></span>
                        <span class="text-[10px] font-mono text-ink-500">{{ __('SLA ·') }} <span id="th-sla"
                                class="text-wa-deep font-semibold">—</span></span>
                        {{-- 24-hour customer-care window countdown. Only shown on
                             channels that enforce it (WABA/Twilio/Instagram),
                             filled + live-ticked by JS. Green >3h, amber <3h,
                             red when the window has closed. --}}
                        <span id="th-window"
                            class="hidden inline-flex items-center gap-1 px-1.5 py-0.5 rounded-full text-[10px] font-mono font-semibold"></span>
                    </div>
                    <div class="flex items-center gap-2 text-[11.5px] text-ink-500 flex-wrap">
                        <span id="th-phone" class="font-mono"></span>
                        <span id="th-presence" class="inline-flex items-center gap-1"></span>
                    </div>
                </div>
                <div class="flex items-center gap-1 relative">
                    <div class="relative">
                        <button id="assign-btn" title="{{ __('Assign') }}"
                            class="w-9 h-9 border border-paper-200 rounded-full bg-paper-0 hover:bg-paper-50 grid place-items-center">
                            <svg viewBox="0 0 16 16" class="w-4 h-4" fill="none" stroke="currentColor"
                                stroke-width="1.6">
                                <path d="M6 3a3 3 0 1 1 0 6 3 3 0 0 1 0-6zM2 14c0-3 2.2-5 4-5s4 2 4 5" />
                                <path d="M11 6h3M12.5 4.5v3" />
                            </svg>
                        </button>
                        <div id="assign-menu" class="menu hidden"></div>
                    </div>
                    <div class="relative">
                        <button id="ai-agent-btn"
                            class="px-3 py-1.5 border border-paper-200 rounded-full bg-paper-0 hover:bg-paper-50 text-[11.5px] font-medium inline-flex items-center gap-1.5">
                            <svg viewBox="0 0 16 16" class="w-3 h-3" fill="none" stroke="currentColor"
                                stroke-width="1.6">
                                <rect x="3" y="5" width="10" height="8" rx="2" />
                                <path d="M6 5V3.5A2 2 0 0 1 10 3.5V5" />
                                <circle cx="6" cy="9" r="1" fill="currentColor" stroke="none" />
                                <circle cx="10" cy="9" r="1" fill="currentColor" stroke="none" />
                            </svg>
                            <span id="ai-agent-label">{{ __('AI Agent') }}</span>
                        </button>
                        <div id="ai-agent-menu" class="menu hidden"></div>
                    </div>
                    {{-- WhatsApp voice call. Rendered only when the active
 conversation's WABA number has calling enabled (server sets
 wa_calling_enabled on the conversation payload). JS flips
 the .hidden class when the operator opens a conversation
 with the flag on; Baileys-only workspaces never see this. --}}
                    <button id="wa-call-btn" data-call-action="dial"
                        class="hidden px-3 py-1.5 rounded-full bg-wa-deep hover:bg-wa-teal text-paper-0 text-[11.5px] font-semibold inline-flex items-center gap-1.5"
                        title="{{ __('Start a WhatsApp call') }}">
                        <svg viewBox="0 0 16 16" class="w-3 h-3" fill="none" stroke="currentColor"
                            stroke-width="1.8">
                            <path
                                d="M3.5 3.5a1.5 1.5 0 0 1 1.5-1.5h1.6l1.2 3-1.4 1a8.5 8.5 0 0 0 4.6 4.6l1-1.4 3 1.2v1.6a1.5 1.5 0 0 1-1.5 1.5C7 14 2 9 2 5z" />
                        </svg>
                        Call
                    </button>
                    <button id="snooze-btn" title="{{ __('Snooze') }}"
                        class="w-9 h-9 border border-paper-200 rounded-full bg-paper-0 hover:bg-paper-50 grid place-items-center">
                        <svg viewBox="0 0 16 16" class="w-4 h-4" fill="none" stroke="currentColor"
                            stroke-width="1.6">
                            <circle cx="8" cy="8" r="6" />
                            <path d="M8 5v3l2 2" />
                        </svg>
                    </button>
                    <button id="create-deal-btn"
                        class="w-9 h-9 border border-paper-200 rounded-full bg-paper-0 hover:bg-paper-50 grid place-items-center"
                        title="{{ __('Create a sales deal from this conversation') }}">
                        <svg viewBox="0 0 16 16" class="w-4 h-4" fill="none" stroke="currentColor"
                            stroke-width="1.6" stroke-linejoin="round">
                            <path d="M2 11V7l6-4.5L14 7v4M2 11h12M6 11V8.5h4V11" />
                        </svg>
                    </button>
                    {{-- P6 — Run an Instaflow flow on this Instagram thread. Hidden
                         by default; JS reveals it only for IG conversations and
                         fills the popover from GET /ig-flows (bridge flows()). --}}
                    <div id="ig-flow-wrap" class="relative hidden">
                        <button id="ig-flow-btn" type="button"
                            class="w-9 h-9 border border-paper-200 rounded-full bg-paper-0 hover:bg-paper-50 grid place-items-center"
                            title="{{ __('Run an Instagram flow on this chat') }}">
                            <svg viewBox="0 0 16 16" class="w-4 h-4" fill="none" stroke="currentColor"
                                stroke-width="1.5" stroke-linejoin="round">
                                <circle cx="4" cy="4" r="1.8" />
                                <circle cx="12" cy="4" r="1.8" />
                                <circle cx="8" cy="12" r="1.8" />
                                <path d="M4 5.8v1.4a2 2 0 0 0 2 2h4a2 2 0 0 0 2-2V5.8M8 9.2v1" />
                            </svg>
                        </button>
                        <div id="ig-flow-pop"
                            class="menu hidden absolute right-0 z-30 mt-1 w-64 max-h-[300px] overflow-y-auto bg-paper-0 border border-paper-200 rounded-xl shadow-card p-1.5">
                            <div class="px-2 py-1.5 font-mono text-[10px] uppercase tracking-[0.14em] text-ink-500">
                                {{ __('Run Instagram flow') }}</div>
                            <div id="ig-flow-list" class="space-y-0.5"></div>
                        </div>
                    </div>
                    <button id="contact-open"
                        class="hidden w-9 h-9 rounded-full border border-paper-200 hover:bg-paper-50 grid place-items-center"
                        title="{{ __('Show contact panel') }}">
                        <svg viewBox="0 0 16 16" class="w-4 h-4" fill="none" stroke="currentColor"
                            stroke-width="1.6">
                            <rect x="2" y="3" width="12" height="10" rx="1" />
                            <path d="M10 3v10" />
                            <path d="M7 7l-1.5 1L7 9" />
                        </svg>
                    </button>
                </div>
            </div>
            {{-- Quick label shortcuts — one-tap chips that apply a preset tag +
 priority combo. Visible only when a conversation is open. --}}
            <div id="th-shortcuts"
                class="hidden px-5 py-1.5 border-b border-paper-200 bg-paper-50/40 flex items-center gap-1.5 flex-wrap shrink-0">
                <span
                    class="font-mono text-[9.5px] uppercase tracking-[0.14em] text-ink-500 mr-1">{{ __('Quick label') }}</span>
                {{-- Quick-label toggles. Neutral at rest (colored icon only); they
                     fill with the label's colour ONLY while that label is actually
                     applied to the open conversation (JS syncQuickLabels() adds .on).
                     Clicking toggles the label on/off — no more brief opacity flash. --}}
                <button type="button" data-quick-label="Urgent" data-quick-color="#a1431f"
                    data-quick-priority="urgent" class="ti-qlabel" style="--ql:#a1431f">
                    <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.4"
                        stroke-linecap="round" stroke-linejoin="round">
                        <path
                            d="M8 1.8c1.5 1.9 2.4 3.3 2.4 5a2.4 2.4 0 0 1-4.8 0c0-.5.1-1 .4-1.4C4.9 6.3 4.1 7.5 4.1 9a3.9 3.9 0 0 0 7.8 0c0-2.8-1.9-4.8-3.9-7.2Z" />
                    </svg>
                    Urgent</button>
                <button type="button" data-quick-label="Follow-up" data-quick-color="#7B5A14"
                    data-quick-priority="high" class="ti-qlabel" style="--ql:#7B5A14">
                    <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.4"
                        stroke-linecap="round" stroke-linejoin="round">
                        <path d="M4 2.5h8v11l-4-2.6-4 2.6z" />
                    </svg>
                    Follow-up</button>
                <button type="button" data-quick-label="VIP" data-quick-color="#5B3D8A" data-quick-priority="high"
                    class="ti-qlabel" style="--ql:#5B3D8A">
                    <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.4"
                        stroke-linecap="round" stroke-linejoin="round">
                        <path d="m8 1.8 1.85 3.75 4.15.6-3 2.93.71 4.12L8 11.28 4.29 13.2l.71-4.12-3-2.93 4.15-.6z" />
                    </svg>
                    VIP</button>
                <button type="button" data-quick-label="Lead" data-quick-color="#0C7A65"
                    data-quick-priority="normal" class="ti-qlabel" style="--ql:#0C7A65">
                    <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.4"
                        stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="8" cy="8" r="6" />
                        <circle cx="8" cy="8" r="2.6" />
                    </svg>
                    Lead</button>
                <button type="button" data-quick-label="Support" data-quick-color="#13478A"
                    data-quick-priority="normal" class="ti-qlabel" style="--ql:#13478A">
                    <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.4"
                        stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="8" cy="8" r="6" />
                        <circle cx="8" cy="8" r="2.5" />
                        <path d="M3.85 3.85 6.2 6.2M12.15 3.85 9.8 6.2M12.15 12.15 9.8 9.8M3.85 12.15 6.2 9.8" />
                    </svg>
                    Support</button>
                {{-- #48 — Inline label picker. Opens a menu with every tag in
 the workspace (state.tags) so operators can apply any
 existing label without leaving the chat header. The menu
 is populated by JS from the bootstrap payload. --}}
                <div class="relative ml-1">
                    <button id="label-picker-btn" type="button"
                        class="px-2 py-0.5 rounded-full text-[10.5px] font-mono border border-dashed border-paper-200 hover:bg-paper-100 text-ink-600 inline-flex items-center gap-1">
                        <svg viewBox="0 0 16 16" class="w-2.5 h-2.5" fill="none" stroke="currentColor"
                            stroke-width="1.8">
                            <path d="M8 3v10M3 8h10" />
                        </svg>
                        Label
                    </button>
                    <div id="label-picker-menu"
                        class="menu hidden absolute z-30 mt-1 left-0 min-w-[180px] max-h-[260px] overflow-y-auto bg-paper-0 border border-paper-200 rounded-lg shadow-card p-1.5">
                    </div>
                </div>
            </div>
            <div class="flex-1 overflow-y-auto chat-paper px-5 py-4 space-y-3" id="thread">
                {{-- Initial placeholder while bootstrap is fetching workspace state.
 Shows a clean skeleton-shimmer "thread" so the page never
 flashes the bigger getting-started panel before we know
 whether the workspace actually has conversations. JS removes
 this on first render. --}}
                <div id="thread-skeleton" class="h-full px-6 py-6">
                    <div class="max-w-[640px] mx-auto space-y-4">
                        <div class="flex items-start gap-3">
                            <div class="skeleton w-10 h-10 rounded-full shrink-0"></div>
                            <div class="flex-1 space-y-2">
                                <div class="skeleton h-3 rounded w-1/3"></div>
                                <div class="skeleton h-12 rounded-lg"></div>
                            </div>
                        </div>
                        <div class="flex items-start gap-3 flex-row-reverse">
                            <div class="flex-1 space-y-2 max-w-[70%] ml-auto">
                                <div class="skeleton h-3 rounded w-1/4 ml-auto"></div>
                                <div class="skeleton h-10 rounded-lg"></div>
                            </div>
                        </div>
                        <div class="flex items-start gap-3">
                            <div class="skeleton w-10 h-10 rounded-full shrink-0"></div>
                            <div class="flex-1 space-y-2">
                                <div class="skeleton h-3 rounded w-1/4"></div>
                                <div class="skeleton h-16 rounded-lg"></div>
                            </div>
                        </div>
                    </div>
                </div>
                {{-- Getting-started panel — only shown when bootstrap confirms the
 workspace has 0 conversations AND the user can manage. Hidden
 by default to avoid the flash on reload. --}}
                <div id="thread-getting-started" class="hidden h-full overflow-y-auto px-6 py-6">
                    <div class="w-full max-w-[1200px] mx-auto">
                        @php
                            $connected = (int) ($connectedDevices ?? 0);
                            $hasDevice = $connected > 0;
                        @endphp
                        <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-wa-deep mb-1">
                            {{ __('Welcome to your team inbox') }}</div>
                        <h2 class="font-serif text-[22px] leading-tight tracking-[-0.01em]">
                            {{ __('Customer WhatsApp messages will land here for') }} <span
                                class="italic text-wa-deep">{{ __('your whole team') }}</span> to handle.</h2>
                        <p class="text-[12.5px] text-ink-600 mt-1.5 max-w-[640px]">
                            @if ($hasDevice)
                                Your inbox is wired up — once customers message your
                                number{{ $connected > 1 ? 's' : '' }}, their threads will appear in the queue on the
                                left.
                            @else
                                Right now your inbox is empty because nothing's connected yet. Three quick steps will
                                get you to the first real conversation.
                            @endif
                        </p>
                        {{-- 3 setup steps laid out in a horizontal row on lg+, stacks
 on smaller screens so it doesn't squash. Compact padding
 so the whole panel fits in the viewport without scroll. --}}
                        <div class="mt-4 grid grid-cols-1 lg:grid-cols-3 gap-2.5">
                            <div class="bg-paper-0 border border-paper-200 rounded-xl p-3">
                                <div class="flex items-center gap-2 mb-1.5">
                                    @if ($hasDevice)
                                        <span
                                            class="w-6 h-6 rounded-full bg-wa-deep text-paper-0 grid place-items-center font-mono text-[11px] shrink-0">
                                            <svg viewBox="0 0 16 16" class="w-3 h-3" fill="none"
                                                stroke="currentColor" stroke-width="2.4">
                                                <path d="M3 8l3 3 7-8" />
                                            </svg>
                                        </span>
                                    @else
                                        <span
                                            class="w-6 h-6 rounded-full bg-wa-deep text-paper-0 grid place-items-center font-mono text-[11px] shrink-0">1</span>
                                    @endif
                                    <span
                                        class="font-semibold text-[13.5px]">{{ __('Connect a WhatsApp number') }}</span>
                                </div>
                                <div class="text-[11.5px] text-ink-500 leading-snug mb-2">
                                    @if ($hasDevice)
                                        {{ $connected }} device{{ $connected > 1 ? 's' : '' }} connected — incoming
                                        messages will route here.
                                    @else
                                        Without a connected device, nothing reaches the inbox.
                                    @endif
                                </div>
                                @if ($hasDevice)
                                    <a href="{{ url('/devices') }}"
                                        class="inline-block px-2.5 py-1 rounded-full border border-wa-deep/30 bg-wa-mint text-wa-deep text-[11.5px] font-semibold hover:bg-wa-bubble">{{ __('Manage') }}</a>
                                @else
                                    <a href="{{ url('/devices') }}"
                                        class="inline-block px-2.5 py-1 rounded-full bg-wa-deep text-paper-0 text-[11.5px] font-semibold hover:bg-wa-teal">{{ __('Connect') }}</a>
                                @endif
                            </div>
                            <div class="bg-paper-0 border border-paper-200 rounded-xl p-3">
                                <div class="flex items-center gap-2 mb-1.5">
                                    <span
                                        class="w-6 h-6 rounded-full bg-paper-100 text-ink-700 grid place-items-center font-mono text-[11px] shrink-0">2</span>
                                    <span class="font-semibold text-[13.5px]">{{ __('Invite your teammates') }}</span>
                                </div>
                                <div class="text-[11.5px] text-ink-500 leading-snug mb-2">
                                    {{ __("Built for teams — invite agents to share the workload. They see only what's assigned to them.") }}
                                </div>
                                <button type="button" data-open-invite
                                    class="inline-block px-2.5 py-1 rounded-full border border-paper-200 hover:bg-paper-50 text-[11.5px] font-semibold">{{ __('Invite') }}</button>
                            </div>
                            <div class="bg-paper-0 border border-paper-200 rounded-xl p-3">
                                <div class="flex items-center gap-2 mb-1.5">
                                    <span
                                        class="w-6 h-6 rounded-full bg-paper-100 text-ink-700 grid place-items-center font-mono text-[11px] shrink-0">3</span>
                                    <span class="font-semibold text-[13.5px]">{{ __('Create teams') }} <span
                                            class="text-ink-500 font-normal">(optional)</span></span>
                                </div>
                                <div class="text-[11.5px] text-ink-500 leading-snug mb-2">
                                    {{ __('Group agents (Sales, Support, Billing) so routing rules can auto-assign incoming messages.') }}
                                </div>
                                <button type="button" data-open-create-team
                                    class="inline-block px-2.5 py-1 rounded-full border border-paper-200 hover:bg-paper-50 text-[11.5px] font-semibold">{{ __('Create team') }}</button>
                            </div>
                        </div>
                        @if ($hasDevice)
                            {{-- "How to use" panel — 2 columns on lg+: device picker on
 the left, numbered steps on the right. Compact so the
 whole getting-started view fits without scroll. --}}
                            <div class="mt-3 rounded-xl border border-wa-green/30 bg-wa-bubble/40 p-3">
                                <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-wa-deep mb-1">
                                    {{ __('How to use this inbox') }}</div>
                                <h3 class="font-serif text-[16px] leading-tight mb-2.5">
                                    {{ __('Send a test WhatsApp to your number to see it appear here') }}</h3>
                                <div class="grid grid-cols-1 lg:grid-cols-2 gap-3 items-start">
                                    @if (!empty($connectedDeviceList) && count($connectedDeviceList))
                                        <div class="grid gap-1.5">
                                            @foreach ($connectedDeviceList as $d)
                                                <div
                                                    class="flex items-center gap-2.5 bg-paper-0 border border-paper-200 rounded-lg px-2.5 py-1.5">
                                                    <span
                                                        class="w-7 h-7 rounded-md bg-wa-mint text-wa-deep grid place-items-center shrink-0">
                                                        <svg viewBox="0 0 16 16" class="w-3.5 h-3.5"
                                                            fill="currentColor">
                                                            <path
                                                                d="M12 2H4a2 2 0 0 0-2 2v8a2 2 0 0 0 2 2h8a2 2 0 0 0 2-2V4a2 2 0 0 0-2-2zM4 1h8a3 3 0 0 1 3 3v8a3 3 0 0 1-3 3H4a3 3 0 0 1-3-3V4a3 3 0 0 1 3-3z" />
                                                        </svg>
                                                    </span>
                                                    <div class="flex-1 min-w-0">
                                                        <div class="text-[12px] font-semibold truncate leading-tight">
                                                            {{ $d['name'] }}</div>
                                                        <div
                                                            class="text-[11.5px] font-mono text-wa-deep leading-tight">
                                                            {{ $d['phone'] }}</div>
                                                    </div>
                                                    <a href="https://wa.me/{{ preg_replace('/\D+/', '', $d['phone']) }}?text={{ urlencode('Hi, this is a test message to my ' . brand_name() . ' inbox.') }}"
                                                        target="_blank"
                                                        class="px-2.5 py-1 rounded-full bg-wa-deep text-paper-0 text-[11px] font-semibold hover:bg-wa-teal shrink-0">{{ __('Open WhatsApp') }}</a>
                                                </div>
                                            @endforeach
                                        </div>
                                    @endif
                                    <ol class="text-[12px] text-ink-700 leading-snug space-y-1 list-decimal pl-4">
                                        <li>{{ __('WhatsApp the number from your second phone.') }}</li>
                                        <li>{{ __('The thread appears in the') }} <span
                                                class="font-mono">{{ __('Unassigned') }}</span> tab within seconds.
                                        </li>
                                        <li>{{ __('Click it to reply, or assign to a teammate from the right panel.') }}
                                        </li>
                                        <li>{{ __('Use') }} <span class="font-mono">@me</span> to find threads
                                            where you were tagged.</li>
                                    </ol>
                                </div>
                            </div>
                        @else
                            <div class="mt-3 text-[11px] text-ink-500 leading-snug">
                                <span class="font-semibold text-ink-700">{{ __('Tip:') }}</span> the queue on the
                                left has 4 tabs.
                                <span class="font-mono">{{ __('Mine') }}</span> shows what's assigned to you,
                                <span class="font-mono">{{ __('Unassigned') }}</span> is the team's bucket,
                                <span class="font-mono">{{ __('All') }}</span> is everything,
                                <span class="font-mono">@me</span> is conversations where someone tagged you in an
                                internal note.
                            </div>
                        @endif
                        {{-- "What your team unlocks" — compact 4-column feature row so
 the whole onboarding fits the viewport without scroll.
 Each tile links/opens the relevant settings surface. --}}
                        <div class="mt-2.5 pt-2.5 border-t border-paper-200">
                            <div class="font-mono text-[9.5px] uppercase tracking-[0.16em] text-ink-500 mb-1.5">
                                {{ __('What your team unlocks') }}</div>
                            <div class="grid grid-cols-2 md:grid-cols-4 gap-1.5">
                                <a href="{{ url('/team-inbox/members') }}"
                                    class="group bg-paper-0 border border-paper-200 rounded-lg px-2 py-1.5 hover:border-wa-deep/40 hover:shadow-card transition">
                                    <div class="flex items-center gap-1.5 mb-0.5">
                                        <span
                                            class="w-5 h-5 rounded bg-wa-mint text-wa-deep grid place-items-center shrink-0">
                                            <svg viewBox="0 0 16 16" class="w-2.5 h-2.5" fill="none"
                                                stroke="currentColor" stroke-width="1.8">
                                                <circle cx="6" cy="6" r="2.5" />
                                                <path d="M2 14c0-3 1.7-5 4-5s4 2 4 5" />
                                                <circle cx="11.5" cy="7" r="2" />
                                            </svg>
                                        </span>
                                        <span
                                            class="font-semibold text-[11px] leading-tight">{{ __('Assign & collaborate') }}</span>
                                    </div>
                                    <div class="text-[10px] text-ink-500 leading-tight">
                                        {{ __('Route threads, see typing, avoid double-replies.') }}</div>
                                </a>
                                <div class="bg-paper-0 border border-paper-200 rounded-lg px-2 py-1.5">
                                    <div class="flex items-center gap-1.5 mb-0.5">
                                        <span
                                            class="w-5 h-5 rounded bg-wa-mint text-wa-deep grid place-items-center shrink-0">
                                            <svg viewBox="0 0 16 16" class="w-2.5 h-2.5" fill="none"
                                                stroke="currentColor" stroke-width="1.8">
                                                <path d="M3 3h10v8H7l-3 3z" />
                                                <path d="M5 7h6M5 9h4" />
                                            </svg>
                                        </span>
                                        <span class="font-semibold text-[11px] leading-tight"><span
                                                class="font-mono">@</span>mentions in notes</span>
                                    </div>
                                    <div class="text-[10px] text-ink-500 leading-tight">{{ __('Type') }} <span
                                            class="font-mono">@name</span> in a note — teammate gets pinged.</div>
                                </div>
                                @canWorkspace('routing.manage')
                                <button type="button" data-open-modal="nav-routing"
                                    class="text-left group bg-paper-0 border border-paper-200 rounded-lg px-2 py-1.5 hover:border-wa-deep/40 hover:shadow-card transition">
                                    <div class="flex items-center gap-1.5 mb-0.5">
                                        <span
                                            class="w-5 h-5 rounded bg-wa-mint text-wa-deep grid place-items-center shrink-0">
                                            <svg viewBox="0 0 16 16" class="w-2.5 h-2.5" fill="none"
                                                stroke="currentColor" stroke-width="1.8">
                                                <path d="M2 4h6l3 4-3 4H2" />
                                                <path d="M11 8h3" />
                                            </svg>
                                        </span>
                                        <span
                                            class="font-semibold text-[11px] leading-tight">{{ __('Routing rules') }}<x-plan-crown
                                                feature="access_routing_rules" size="sm"
                                                :link="false" /></span>
                                    </div>
                                    <div class="text-[10px] text-ink-500 leading-tight">
                                        {{ __('Auto-assign by tag, keyword or hours.') }}</div>
                                </button>
                                @endcanWorkspace
                                <a href="{{ url('/team-inbox/sla') }}"
                                    class="group bg-paper-0 border border-paper-200 rounded-lg px-2 py-1.5 hover:border-wa-deep/40 hover:shadow-card transition">
                                    <div class="flex items-center gap-1.5 mb-0.5">
                                        <span
                                            class="w-5 h-5 rounded bg-wa-mint text-wa-deep grid place-items-center shrink-0">
                                            <svg viewBox="0 0 16 16" class="w-2.5 h-2.5" fill="none"
                                                stroke="currentColor" stroke-width="1.8">
                                                <circle cx="8" cy="8" r="6" />
                                                <path d="M8 4v4l2.5 2.5" />
                                            </svg>
                                        </span>
                                        <span
                                            class="font-semibold text-[11px] leading-tight">{{ __('SLA policies') }}<x-plan-crown
                                                feature="access_sla_policies" size="sm"
                                                :link="false" /></span>
                                    </div>
                                    <div class="text-[10px] text-ink-500 leading-tight">
                                        {{ __('First-response & resolution targets.') }}</div>
                                </a>
                                <button type="button" data-open-modal="nav-quick-replies"
                                    class="text-left group bg-paper-0 border border-paper-200 rounded-lg px-2 py-1.5 hover:border-wa-deep/40 hover:shadow-card transition">
                                    <div class="flex items-center gap-1.5 mb-0.5">
                                        <span
                                            class="w-5 h-5 rounded bg-wa-mint text-wa-deep grid place-items-center shrink-0">
                                            <svg viewBox="0 0 16 16" class="w-2.5 h-2.5" fill="none"
                                                stroke="currentColor" stroke-width="1.8">
                                                <path d="M8 2l1.5 4 4 .3-3 2.7 1 4-3.5-2.2L4.5 13l1-4-3-2.7 4-.3z" />
                                            </svg>
                                        </span>
                                        <span
                                            class="font-semibold text-[11px] leading-tight">{{ __('Quick replies') }}</span>
                                    </div>
                                    <div class="text-[10px] text-ink-500 leading-tight">
                                        {{ __('Snippets fired with') }} <span class="font-mono">/shortcut</span>.
                                    </div>
                                </button>
                                <a href="{{ url('/team-inbox/kanban') }}"
                                    class="group bg-paper-0 border border-paper-200 rounded-lg px-2 py-1.5 hover:border-wa-deep/40 hover:shadow-card transition">
                                    <div class="flex items-center gap-1.5 mb-0.5">
                                        <span
                                            class="w-5 h-5 rounded bg-wa-mint text-wa-deep grid place-items-center shrink-0">
                                            <svg viewBox="0 0 16 16" class="w-2.5 h-2.5" fill="none"
                                                stroke="currentColor" stroke-width="1.8">
                                                <rect x="2" y="2" width="3" height="12" rx="1" />
                                                <rect x="6.5" y="2" width="3" height="9" rx="1" />
                                                <rect x="11" y="2" width="3" height="6" rx="1" />
                                            </svg>
                                        </span>
                                        <span
                                            class="font-semibold text-[11px] leading-tight">{{ __('Kanban board') }}<x-plan-crown
                                                feature="access_kanban_view" size="sm"
                                                :link="false" /></span>
                                    </div>
                                    <div class="text-[10px] text-ink-500 leading-tight">
                                        {{ __('Drag threads between stages.') }}</div>
                                </a>
                                @canWorkspace('integration.manage')
                                <a href="{{ url('/ai-training') }}"
                                    class="group bg-paper-0 border border-paper-200 rounded-lg px-2 py-1.5 hover:border-wa-deep/40 hover:shadow-card transition">
                                    <div class="flex items-center gap-1.5 mb-0.5">
                                        <span
                                            class="w-5 h-5 rounded bg-wa-mint text-wa-deep grid place-items-center shrink-0">
                                            <svg viewBox="0 0 16 16" class="w-2.5 h-2.5" fill="none"
                                                stroke="currentColor" stroke-width="1.8">
                                                <rect x="3" y="5" width="10" height="8" rx="2" />
                                                <path d="M6 5V3.5A2 2 0 0 1 10 3.5V5" />
                                                <circle cx="6" cy="9" r="1" fill="currentColor"
                                                    stroke="none" />
                                                <circle cx="10" cy="9" r="1" fill="currentColor"
                                                    stroke="none" />
                                            </svg>
                                        </span>
                                        <span
                                            class="font-semibold text-[11px] leading-tight">{{ __('AI auto-reply') }}<x-plan-crown
                                                feature="access_ai_agents" size="sm" :link="false" /></span>
                                    </div>
                                    <div class="text-[10px] text-ink-500 leading-tight">
                                        {{ __('Train an AI on docs to answer FAQs.') }}</div>
                                </a>
                                @endcanWorkspace
                                <a href="{{ url('/call-logs') }}"
                                    class="group bg-paper-0 border border-paper-200 rounded-lg px-2 py-1.5 hover:border-wa-deep/40 hover:shadow-card transition">
                                    <div class="flex items-center gap-1.5 mb-0.5">
                                        <span
                                            class="w-5 h-5 rounded bg-wa-mint text-wa-deep grid place-items-center shrink-0">
                                            <svg viewBox="0 0 16 16" class="w-2.5 h-2.5" fill="none"
                                                stroke="currentColor" stroke-width="1.8">
                                                <path
                                                    d="M3 3.5C3 2.7 3.7 2 4.5 2h1.7c.6 0 1.1.4 1.3 1l.5 2c.1.4 0 .8-.3 1.1L6.6 7.4c.8 1.5 2 2.7 3.5 3.5l1.3-1.3c.3-.3.7-.4 1.1-.3l2 .5c.6.2 1 .7 1 1.3v1.7c0 .8-.7 1.5-1.5 1.5C7.7 14 2 8.3 2 4.5 2 3.7 2.7 3 3.5 3" />
                                            </svg>
                                        </span>
                                        <span
                                            class="font-semibold text-[11px] leading-tight">{{ __('AI voice calling') }}<x-plan-crown
                                                feature="access_waba_calling" size="sm"
                                                :link="false" /></span>
                                    </div>
                                    <div class="text-[10px] text-ink-500 leading-tight">
                                        {{ __('AI picks up WhatsApp calls + records.') }}</div>
                                </a>
                                @canWorkspace('integration.manage')
                                <a href="{{ url('/ai-assistants') }}"
                                    class="group bg-paper-0 border border-paper-200 rounded-lg px-2 py-1.5 hover:border-wa-deep/40 hover:shadow-card transition">
                                    <div class="flex items-center gap-1.5 mb-0.5">
                                        <span
                                            class="w-5 h-5 rounded bg-wa-mint text-wa-deep grid place-items-center shrink-0">
                                            <svg viewBox="0 0 16 16" class="w-2.5 h-2.5" fill="none"
                                                stroke="currentColor" stroke-width="1.8">
                                                <path
                                                    d="M8 2v2M4 4l1.4 1.4M2 8h2M4 12l1.4-1.4M12 4l-1.4 1.4M14 8h-2M12 12l-1.4-1.4M8 12v2" />
                                                <circle cx="8" cy="8" r="3" />
                                            </svg>
                                        </span>
                                        <span
                                            class="font-semibold text-[11px] leading-tight">{{ __('AI assistant wizard') }}<x-plan-crown
                                                feature="access_ai_voice_agent" size="sm"
                                                :link="false" /></span>
                                    </div>
                                    <div class="text-[10px] text-ink-500 leading-tight">
                                        {{ __('Configure voice + model + prompts.') }}</div>
                                </a>
                                @endcanWorkspace
                                <div class="bg-paper-0 border border-paper-200 rounded-lg px-2 py-1.5">
                                    <div class="flex items-center gap-1.5 mb-0.5">
                                        <span
                                            class="w-5 h-5 rounded bg-wa-mint text-wa-deep grid place-items-center shrink-0">
                                            <svg viewBox="0 0 16 16" class="w-2.5 h-2.5" fill="none"
                                                stroke="currentColor" stroke-width="1.8">
                                                <path d="M2 2h6l6 6-6 6-6-6z" />
                                                <circle cx="5" cy="5" r="1" fill="currentColor" />
                                            </svg>
                                        </span>
                                        <span
                                            class="font-semibold text-[11px] leading-tight">{{ __('Tags & labels') }}</span>
                                    </div>
                                    <div class="text-[10px] text-ink-500 leading-tight">
                                        {{ __('Organise threads, filter the queue.') }}</div>
                                </div>
                                <div class="bg-paper-0 border border-paper-200 rounded-lg px-2 py-1.5">
                                    <div class="flex items-center gap-1.5 mb-0.5">
                                        <span
                                            class="w-5 h-5 rounded bg-wa-mint text-wa-deep grid place-items-center shrink-0">
                                            <svg viewBox="0 0 16 16" class="w-2.5 h-2.5" fill="none"
                                                stroke="currentColor" stroke-width="1.8">
                                                <circle cx="8" cy="8" r="6" />
                                                <path d="M8 5v3l2 1.5" />
                                                <path d="M5.5 2.5L3.5 4M10.5 2.5L12.5 4" />
                                            </svg>
                                        </span>
                                        <span
                                            class="font-semibold text-[11px] leading-tight">{{ __('Snooze threads') }}</span>
                                    </div>
                                    <div class="text-[10px] text-ink-500 leading-tight">
                                        {{ __('Hide a conversation, resurface later.') }}</div>
                                </div>
                                <a href="{{ url('/team-chat') }}"
                                    class="group bg-paper-0 border border-paper-200 rounded-lg px-2 py-1.5 hover:border-wa-deep/40 hover:shadow-card transition">
                                    <div class="flex items-center gap-1.5 mb-0.5">
                                        <span
                                            class="w-5 h-5 rounded bg-wa-mint text-wa-deep grid place-items-center shrink-0">
                                            <svg viewBox="0 0 16 16" class="w-2.5 h-2.5" fill="none"
                                                stroke="currentColor" stroke-width="1.8">
                                                <path d="M2 3h12v8H6l-3 3z" />
                                                <path d="M5 6h6M5 8h4" />
                                            </svg>
                                        </span>
                                        <span
                                            class="font-semibold text-[11px] leading-tight">{{ __('Internal team chat') }}</span>
                                    </div>
                                    <div class="text-[10px] text-ink-500 leading-tight">
                                        {{ __('Slack-style channels for staff only.') }}</div>
                                </a>
                                <a href="{{ url('/team-inbox') }}"
                                    class="group bg-paper-0 border border-paper-200 rounded-lg px-2 py-1.5 hover:border-wa-deep/40 hover:shadow-card transition">
                                    <div class="flex items-center gap-1.5 mb-0.5">
                                        <span
                                            class="w-5 h-5 rounded bg-wa-mint text-wa-deep grid place-items-center shrink-0">
                                            <svg viewBox="0 0 16 16" class="w-2.5 h-2.5" fill="none"
                                                stroke="currentColor" stroke-width="1.8">
                                                <rect x="2" y="3" width="12" height="11" rx="1.5" />
                                                <path d="M2 6h12M5 2v3M11 2v3" />
                                            </svg>
                                        </span>
                                        <span
                                            class="font-semibold text-[11px] leading-tight">{{ __('Business hours') }}<x-plan-crown
                                                feature="access_business_hours" size="sm"
                                                :link="false" /></span>
                                    </div>
                                    <div class="text-[10px] text-ink-500 leading-tight">
                                        {{ __('Define when SLAs and replies count.') }}</div>
                                </a>
                                @canWorkspace('webhook.manage')
                                <a href="{{ url('/webhooks') }}"
                                    class="group bg-paper-0 border border-paper-200 rounded-lg px-2 py-1.5 hover:border-wa-deep/40 hover:shadow-card transition">
                                    <div class="flex items-center gap-1.5 mb-0.5">
                                        <span
                                            class="w-5 h-5 rounded bg-wa-mint text-wa-deep grid place-items-center shrink-0">
                                            <svg viewBox="0 0 16 16" class="w-2.5 h-2.5" fill="none"
                                                stroke="currentColor" stroke-width="1.8">
                                                <circle cx="4.5" cy="11" r="2" />
                                                <circle cx="11.5" cy="11" r="2" />
                                                <circle cx="8" cy="4" r="2" />
                                                <path d="M6.5 6L5.5 9M9.5 6L10.5 9M6.5 11h3" />
                                            </svg>
                                        </span>
                                        <span
                                            class="font-semibold text-[11px] leading-tight">{{ __('Webhooks') }}<x-plan-crown
                                                feature="access_outbound_webhooks" size="sm"
                                                :link="false" /></span>
                                    </div>
                                    <div class="text-[10px] text-ink-500 leading-tight">
                                        {{ __('Push events to your CRM or apps.') }}</div>
                                </a>
                                @endcanWorkspace
                                <a href="{{ url('/team-inbox/analytics/team') }}"
                                    class="group bg-paper-0 border border-paper-200 rounded-lg px-2 py-1.5 hover:border-wa-deep/40 hover:shadow-card transition">
                                    <div class="flex items-center gap-1.5 mb-0.5">
                                        <span
                                            class="w-5 h-5 rounded bg-wa-mint text-wa-deep grid place-items-center shrink-0">
                                            <svg viewBox="0 0 16 16" class="w-2.5 h-2.5" fill="none"
                                                stroke="currentColor" stroke-width="1.8">
                                                <path d="M2 14V2" />
                                                <path d="M2 14h12" />
                                                <rect x="4" y="9" width="2" height="4" />
                                                <rect x="7.5" y="6" width="2" height="7" />
                                                <rect x="11" y="3" width="2" height="10" />
                                            </svg>
                                        </span>
                                        <span
                                            class="font-semibold text-[11px] leading-tight">{{ __('Team analytics') }}<x-plan-crown
                                                feature="access_team_performance" size="sm"
                                                :link="false" /></span>
                                    </div>
                                    <div class="text-[10px] text-ink-500 leading-tight">
                                        {{ __('Response time, resolution, SLA stats.') }}</div>
                                </a>
                                <a href="{{ url('/templates') }}"
                                    class="group bg-paper-0 border border-paper-200 rounded-lg px-2 py-1.5 hover:border-wa-deep/40 hover:shadow-card transition">
                                    <div class="flex items-center gap-1.5 mb-0.5">
                                        <span
                                            class="w-5 h-5 rounded bg-wa-mint text-wa-deep grid place-items-center shrink-0">
                                            <svg viewBox="0 0 16 16" class="w-2.5 h-2.5" fill="none"
                                                stroke="currentColor" stroke-width="1.8">
                                                <rect x="2" y="2" width="12" height="12" rx="1.5" />
                                                <path d="M2 6h12M6 6v8" />
                                            </svg>
                                        </span>
                                        <span
                                            class="font-semibold text-[11px] leading-tight">{{ __('WhatsApp templates') }}</span>
                                    </div>
                                    <div class="text-[10px] text-ink-500 leading-tight">
                                        {{ __('Submit + reuse Meta-approved templates.') }}</div>
                                </a>
                                <a href="{{ url('/contacts') }}"
                                    class="group bg-paper-0 border border-paper-200 rounded-lg px-2 py-1.5 hover:border-wa-deep/40 hover:shadow-card transition">
                                    <div class="flex items-center gap-1.5 mb-0.5">
                                        <span
                                            class="w-5 h-5 rounded bg-wa-mint text-wa-deep grid place-items-center shrink-0">
                                            <svg viewBox="0 0 16 16" class="w-2.5 h-2.5" fill="none"
                                                stroke="currentColor" stroke-width="1.8">
                                                <rect x="2" y="3" width="12" height="10" rx="1.5" />
                                                <circle cx="6" cy="7" r="1.5" />
                                                <path d="M4 11c0-1.2 .8-2 2-2s2 .8 2 2" />
                                                <path d="M10 6h3M10 8.5h3M10 11h2" />
                                            </svg>
                                        </span>
                                        <span
                                            class="font-semibold text-[11px] leading-tight">{{ __('Contacts & CRM') }}</span>
                                    </div>
                                    <div class="text-[10px] text-ink-500 leading-tight">
                                        {{ __('Profiles, custom fields, CSV import.') }}</div>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
                {{-- Empty state #2 — nothing selected, or the current tab/filter
 has no match. Deliberately mirrors the getting-started panel
 above (mono eyebrow → serif headline → card rows) so the two
 empty states read as one design instead of two. --}}
                {{-- Content-sized: #thread already scrolls and adds px-5 py-4, so
                     this panel must not set its own height or padding. It fills
                     the pane by carrying enough content, the same way the
                     getting-started panel above does. --}}
                <div id="thread-empty" class="hidden h-full overflow-y-auto">
                    @php
                        $tiStats = $inboxStats ?? ['total' => 0, 'unassigned' => 0, 'unread' => 0];
                        $tiSenders = collect($deviceFilterOptions ?? []);
                        $tiLive = $tiSenders->filter(fn($s) => (bool) ($s['live'] ?? false))->count();
                    @endphp
                    @if ($tiSenders->isEmpty())
                        {{-- No number connected — nothing can arrive. A single, centered
                             call to action reads better than an empty dashboard. --}}
                        <div class="min-h-full flex flex-col items-center justify-center px-6 py-10 text-center">
                            <span class="w-16 h-16 rounded-2xl bg-wa-mint text-wa-deep grid place-items-center mb-5">
                                <svg viewBox="0 0 24 24" class="w-8 h-8" fill="none" stroke="currentColor"
                                    stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
                                    <rect x="6" y="2.5" width="12" height="19" rx="2.5" />
                                    <path d="M12 8.5v5M9.5 11h5" />
                                </svg>
                            </span>
                            <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-wa-deep mb-1">
                                {{ __('Your team inbox') }}</div>
                            <h2 class="font-serif text-[24px] leading-tight tracking-[-0.01em]">
                                {{ __('Connect a number and customer messages will') }} <span
                                    class="italic text-wa-deep">{{ __('land right here') }}</span>.
                            </h2>
                            <p class="text-[12.5px] text-ink-600 mt-2 max-w-[440px]">
                                {{ __('Nothing is connected yet, so no threads can arrive. Connect a WhatsApp number to get your first conversation.') }}
                            </p>
                            <button type="button" data-connect-device
                                class="mt-5 inline-flex items-center gap-2 px-4 py-2 rounded-full bg-wa-deep text-paper-0 text-[12.5px] font-semibold hover:bg-wa-teal cursor-pointer">
                                <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none"
                                    stroke="currentColor" stroke-width="1.8" stroke-linecap="round">
                                    <path d="M8 3.5v9M3.5 8h9" />
                                </svg>{{ __('Connect a device') }}
                            </button>
                        </div>
                    @else
                        {{-- Full-height dashboard: hero + KPI row pinned up top, then a
                             flex-1 two-column body (devices + shortcuts | how-it-works).
                             The trailing element in each column grows to absorb slack, so
                             the pane fills top-to-bottom with no dead space at the bottom. --}}
                        <div class="min-h-full flex flex-col px-6 py-6 max-w-[1320px] mx-auto w-full">
                            {{-- Hero --}}
                            <div class="shrink-0">
                                <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-wa-deep mb-1">
                                    {{ __('Your team inbox') }}</div>
                                <h2 class="font-serif text-[22px] leading-tight tracking-[-0.01em]">
                                    {{ __('Pick a conversation on the left and') }} <span
                                        class="italic text-wa-deep">{{ __('start replying') }}</span>.
                                </h2>
                                <p class="text-[12.5px] text-ink-600 mt-1.5 max-w-[640px]">
                                    {{ __('Nothing is open in this view — try another tab, or use one of the shortcuts below.') }}
                                </p>
                            </div>
                            {{-- Queue at a glance — same scope as the thread rail, so
                                 these can never disagree with the tab counts. --}}
                            <div class="mt-5 grid grid-cols-3 gap-3 shrink-0">
                                <div class="bg-paper-0 border border-paper-200 rounded-xl p-4">
                                    <div class="font-serif text-[26px] leading-none">
                                        {{ number_format($tiStats['total']) }}</div>
                                    <div class="font-mono text-[9.5px] uppercase tracking-[0.14em] text-ink-500 mt-2">
                                        {{ __('Open threads') }}</div>
                                </div>
                                <div class="bg-paper-0 border border-paper-200 rounded-xl p-4">
                                    <div class="font-serif text-[26px] leading-none text-wa-deep">
                                        {{ number_format($tiStats['unassigned']) }}</div>
                                    <div class="font-mono text-[9.5px] uppercase tracking-[0.14em] text-ink-500 mt-2">
                                        {{ __('Unassigned') }}</div>
                                </div>
                                <div class="bg-paper-0 border border-paper-200 rounded-xl p-4">
                                    <div class="font-serif text-[26px] leading-none">
                                        {{ number_format($tiStats['unread']) }}</div>
                                    <div class="font-mono text-[9.5px] uppercase tracking-[0.14em] text-ink-500 mt-2">
                                        {{ __('Unread') }}</div>
                                </div>
                            </div>
                            {{-- Body — fills the remaining vertical space. --}}
                            <div class="mt-4 grid grid-cols-1 lg:grid-cols-12 gap-4 flex-1 min-h-0">
                                {{-- LEFT — connected devices + jump-to shortcuts --}}
                                <div class="lg:col-span-5 flex flex-col gap-4 min-h-0">
                                    {{-- Connected numbers across ALL engines. Clicking one
                                         filters the queue to that number via ?device_id=. --}}
                                    <div class="rounded-xl border border-wa-green/30 bg-wa-bubble/40 p-3.5 shrink-0">
                                        <div class="flex items-center gap-2 mb-2.5">
                                            <span
                                                class="font-mono text-[10px] uppercase tracking-[0.16em] text-wa-deep">{{ __('Connected devices') }}</span>
                                            <span class="h-px flex-1 bg-wa-green/25"></span>
                                            <span
                                                class="font-mono text-[10px] text-ink-500">{{ $tiLive }}/{{ $tiSenders->count() }}
                                                {{ __('online') }}</span>
                                            <button type="button" data-connect-device
                                                class="px-2.5 py-1 rounded-full bg-wa-deep text-paper-0 text-[11px] font-semibold hover:bg-wa-teal shrink-0 cursor-pointer">{{ __('Add') }}</button>
                                        </div>
                                        <div class="grid grid-cols-1 gap-1.5">
                                            @foreach ($tiSenders as $s)
                                                @php $sLive = (bool) ($s['live'] ?? false); @endphp
                                                {{-- Composite "engine:id" (same value the queue dropdown uses)
                                                     so this quick-filter pins device_id AND provider — a Baileys
                                                     device and a WABA config with the same id can't collide. --}}
                                                <a href="{{ url('/team-inbox?device_id=' . ($s['engine_key'] ?? 'baileys') . ':' . $s['id']) }}"
                                                    class="group flex items-center gap-2.5 bg-paper-0 border border-paper-200 rounded-lg px-2.5 py-2 hover:border-wa-deep/40 hover:shadow-card transition">
                                                    <span
                                                        class="w-8 h-8 rounded-md bg-wa-mint text-wa-deep grid place-items-center shrink-0">
                                                        <svg viewBox="0 0 16 16" class="w-4 h-4" fill="none"
                                                            stroke="currentColor" stroke-width="1.6">
                                                            <rect x="4" y="2" width="8" height="12"
                                                                rx="1.5" />
                                                            <circle cx="8" cy="11" r="0.7"
                                                                fill="currentColor" />
                                                        </svg>
                                                    </span>
                                                    <div class="flex-1 min-w-0">
                                                        <div class="flex items-center gap-1.5 min-w-0">
                                                            <span
                                                                class="text-[12.5px] font-semibold truncate leading-tight">{{ $s['label'] }}</span>
                                                            <span
                                                                title="{{ $sLive ? __('Connected') : __('Offline') }}"
                                                                class="w-1.5 h-1.5 rounded-full shrink-0 {{ $sLive ? 'bg-wa-green' : 'bg-accent-coral' }}"></span>
                                                        </div>
                                                        <div
                                                            class="text-[11px] font-mono text-wa-deep leading-tight truncate">
                                                            {{ $s['phone'] }} · {{ $s['engine'] }}</div>
                                                    </div>
                                                    <span
                                                        class="px-2 py-0.5 rounded-full border border-paper-200 text-[10.5px] text-ink-500 shrink-0 opacity-0 group-hover:opacity-100 transition">{{ __('Filter') }}</span>
                                                </a>
                                            @endforeach
                                        </div>
                                    </div>
                                    {{-- Jump-to shortcuts — a vertical list that grows to fill
                                         the column so the left side reaches the bottom edge. --}}
                                    <div
                                        class="rounded-xl border border-paper-200 bg-paper-0 p-3.5 flex-1 flex flex-col min-h-0">
                                        <div
                                            class="font-mono text-[9.5px] uppercase tracking-[0.16em] text-ink-500 mb-2 shrink-0">
                                            {{ __('Jump to') }}</div>
                                        @php
                                            $tiTiles = [
                                                [
                                                    'url' => url('/broadcasts'),
                                                    'label' => __('Broadcast'),
                                                    'desc' => __('Message many contacts at once.'),
                                                    'path' =>
                                                        '<path d="M14 2 2 7.3l4.2 1.5L7.7 13 14 2z"/><path d="M14 2 6.2 8.8"/>',
                                                ],
                                                [
                                                    'url' => url('/chat'),
                                                    'label' => __('1-to-1 chat'),
                                                    'desc' => __('Start a thread with one number.'),
                                                    'path' => '<path d="M2 4h12v8H4l-2 2z"/>',
                                                ],
                                                [
                                                    'url' => url('/templates'),
                                                    'label' => __('Templates'),
                                                    'desc' => __('Reuse Meta-approved messages.'),
                                                    'path' => '<path d="M2.5 3.5h11v9h-11z"/><path d="M2.5 6.5h11"/>',
                                                ],
                                                [
                                                    'url' => url('/devices'),
                                                    'label' => __('Devices'),
                                                    'desc' => __('Connect or reconnect a number.'),
                                                    'path' =>
                                                        '<rect x="4" y="2" width="8" height="12" rx="1.5"/><circle cx="8" cy="11" r="0.7" fill="currentColor"/>',
                                                ],
                                            ];
                                        @endphp
                                        <div class="grid grid-rows-4 gap-1.5 flex-1 min-h-0">
                                            @foreach ($tiTiles as $t)
                                                <a href="{{ $t['url'] }}"
                                                    class="group flex items-center gap-3 rounded-lg border border-paper-200 px-3 hover:border-wa-deep/40 hover:bg-paper-50 hover:shadow-card transition">
                                                    <span
                                                        class="w-8 h-8 rounded-lg bg-wa-mint text-wa-deep grid place-items-center shrink-0">
                                                        <svg viewBox="0 0 16 16" class="w-4 h-4" fill="none"
                                                            stroke="currentColor" stroke-width="1.7"
                                                            stroke-linecap="round"
                                                            stroke-linejoin="round">{!! $t['path'] !!}</svg>
                                                    </span>
                                                    <div class="flex-1 min-w-0">
                                                        <div class="font-semibold text-[12.5px] leading-tight">
                                                            {{ $t['label'] }}</div>
                                                        <div class="text-[11px] text-ink-500 leading-tight truncate">
                                                            {{ $t['desc'] }}</div>
                                                    </div>
                                                    <svg viewBox="0 0 16 16"
                                                        class="w-3.5 h-3.5 text-ink-400 shrink-0 opacity-0 group-hover:opacity-100 transition"
                                                        fill="none" stroke="currentColor" stroke-width="1.8"
                                                        stroke-linecap="round" stroke-linejoin="round">
                                                        <path d="M6 3.5 10.5 8 6 12.5" />
                                                    </svg>
                                                </a>
                                            @endforeach
                                        </div>
                                    </div>
                                </div>
                                {{-- RIGHT — how this inbox works. Written detail rather than
                                     more tiles: explains how the queue, assignment and
                                     automation actually behave, which is what an operator
                                     staring at an empty pane needs to know. Cards stack and
                                     grow so the column reaches the bottom edge. --}}
                                <div class="lg:col-span-7 flex flex-col min-h-0">
                                    <div
                                        class="font-mono text-[9.5px] uppercase tracking-[0.16em] text-ink-500 mb-2 shrink-0">
                                        {{ __('How this inbox works') }}</div>
                                    <div class="grid grid-rows-3 gap-3 flex-1 min-h-0">
                                        <div class="bg-paper-0 border border-paper-200 rounded-xl p-4 flex flex-col">
                                            <div class="flex items-center gap-2 mb-1.5">
                                                <span
                                                    class="w-6 h-6 rounded-lg bg-wa-mint text-wa-deep grid place-items-center shrink-0">
                                                    <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none"
                                                        stroke="currentColor" stroke-width="1.6">
                                                        <rect x="2" y="3" width="5" height="4"
                                                            rx="1" />
                                                        <rect x="9" y="3" width="5" height="4"
                                                            rx="1" />
                                                        <rect x="2" y="9" width="5" height="4"
                                                            rx="1" />
                                                        <rect x="9" y="9" width="5" height="4"
                                                            rx="1" />
                                                    </svg>
                                                </span>
                                                <div class="font-semibold text-[13px]">{{ __('The four tabs') }}
                                                </div>
                                            </div>
                                            <p class="text-[11.5px] text-ink-600 leading-relaxed">
                                                <span class="font-mono text-[11px]">{{ __('All') }}</span>
                                                {{ __('is every open thread on the workspace.') }}
                                                <span class="font-mono text-[11px]">{{ __('Mine') }}</span>
                                                {{ __('narrows to threads assigned to you.') }}
                                                <span class="font-mono text-[11px]">{{ __('Unassigned') }}</span>
                                                {{ __('is the shared bucket — anything nobody has picked up yet, which is where triage starts.') }}
                                                <span class="font-mono text-[11px]">@me</span>
                                                {{ __('collects threads where a teammate tagged you in an internal note.') }}
                                            </p>
                                        </div>
                                        <div class="bg-paper-0 border border-paper-200 rounded-xl p-4 flex flex-col">
                                            <div class="flex items-center gap-2 mb-1.5">
                                                <span
                                                    class="w-6 h-6 rounded-lg bg-wa-mint text-wa-deep grid place-items-center shrink-0">
                                                    <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none"
                                                        stroke="currentColor" stroke-width="1.6"
                                                        stroke-linecap="round" stroke-linejoin="round">
                                                        <circle cx="6" cy="6" r="2.2" />
                                                        <path d="M2.5 13c0-2 1.6-3.2 3.5-3.2S9.5 11 9.5 13" />
                                                        <path d="M11 5.5l1.4 1.4L15 4.3" />
                                                    </svg>
                                                </span>
                                                <div class="font-semibold text-[13px]">
                                                    {{ __('Assignment and collisions') }}</div>
                                            </div>
                                            <p class="text-[11.5px] text-ink-600 leading-relaxed">
                                                {{ __('Assigning a thread makes one person responsible for it — it leaves the shared bucket and lands in their') }}
                                                <span class="font-mono text-[11px]">{{ __('Mine') }}</span>
                                                {{ __('tab. While a teammate is typing you will see it live, so two agents do not answer the same customer twice. Internal notes are never sent to the customer; only replies are.') }}
                                            </p>
                                        </div>
                                        <div class="bg-paper-0 border border-paper-200 rounded-xl p-4 flex flex-col">
                                            <div class="flex items-center gap-2 mb-1.5">
                                                <span
                                                    class="w-6 h-6 rounded-lg bg-wa-mint text-wa-deep grid place-items-center shrink-0">
                                                    <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none"
                                                        stroke="currentColor" stroke-width="1.6"
                                                        stroke-linecap="round" stroke-linejoin="round">
                                                        <path d="M8 2v2M8 12v2M2 8h2M12 8h2" />
                                                        <rect x="4.5" y="4.5" width="7" height="7"
                                                            rx="1.5" />
                                                        <circle cx="8" cy="8" r="1" />
                                                    </svg>
                                                </span>
                                                <div class="font-semibold text-[13px]">{{ __('Automation and AI') }}
                                                </div>
                                            </div>
                                            <p class="text-[11.5px] text-ink-600 leading-relaxed">
                                                {{ __('Routing rules can auto-assign by keyword, tag or business hours before a human sees the thread. An AI agent can answer on its own, and the moment you assign a human it stands down so the customer never gets two answers. Replies always go back out on the same number the customer messaged.') }}
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endif
                </div>
            </div>
            {{-- Resolution banner — visible when inbox_status === 'resolved'. --}}
            <div id="resolve-banner"
                class="hidden border-t border-paper-200 bg-accent-mint/20 text-[11.5px] text-ink-700 px-4 py-2 flex items-center gap-2 shrink-0">
            </div>
            {{-- Slim "open composer" tab. Shown only when the operator has
 closed the composer with the X — clicking it brings the full
 Reply / Note / Template composer back without losing the
 active thread. --}}
            <button id="composer-open" type="button"
                class="hidden border-t border-paper-200 bg-paper-0 hover:bg-paper-50 py-2 text-[11.5px] font-mono uppercase tracking-[0.16em] text-ink-500 hover:text-wa-deep">
                ＋ Reply, note or template
            </button>
            <div id="composer-wrap" class="border-t border-paper-200 bg-paper-0 shrink-0 hidden">
                {{-- 24-hour window CLOSED warning. Shown by JS only on windowed
                     channels (WABA/Twilio/IG) once the window has expired. The
                     Send button stays enabled (operator's call), but the reply
                     may bounce at Meta — so we say so clearly. --}}
                <div id="composer-window-warn"
                    class="hidden px-4 py-2 bg-accent-coral/12 border-b border-accent-coral/30 text-[11.5px] text-accent-coral flex items-center gap-2">
                    <svg viewBox="0 0 16 16" class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor"
                        stroke-width="1.6">
                        <circle cx="8" cy="8" r="6" />
                        <path d="M8 5v3.5M8 11h.01" />
                    </svg>
                    <span id="composer-window-warn-text"></span>
                </div>
                <div class="flex items-center gap-1 px-4 pt-3 pb-2 border-b border-paper-200" id="composer-tabs">
                    <button data-mode="reply" class="ti-tab active">{{ __('Reply') }}</button>
                    <button data-mode="note" class="ti-tab">{{ __('Internal note') }}</button>
                    <button data-mode="template" class="ti-tab">{{ __('Template') }}</button>
                    <button id="quick-reply-picker-btn" class="ti-tab"
                        title="{{ __('Quick replies — or type / in composer') }}">
                        {{ __('Shortcuts') }}
                    </button>
                    <button id="composer-close" type="button"
                        class="ml-auto w-7 h-7 rounded-full hover:bg-paper-100 grid place-items-center text-ink-500 hover:text-ink-700"
                        title="{{ __('Hide composer') }}">
                        <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor"
                            stroke-width="1.7">
                            <path d="M4 4l8 8M12 4l-8 8" />
                        </svg>
                    </button>
                </div>
                @if (!empty($aiSuggestEnabled))
                    {{-- P5 AI-suggest bar — asks the workspace AI agent to draft a
                         reply for THIS conversation, shown here for the operator to
                         Use (edit + send) or dismiss. Never auto-sends. Only present
                         when the plan includes AI agents and one is active. --}}
                    <div id="ti-aibar" class="ti-aibar hidden mx-4 mt-3" data-state="idle">
                        <span class="spark">
                            <svg viewBox="0 0 16 16" fill="currentColor">
                                <path
                                    d="M8 1.5l1.3 3.4a3 3 0 0 0 1.8 1.8L14.5 8l-3.4 1.3a3 3 0 0 0-1.8 1.8L8 14.5l-1.3-3.4a3 3 0 0 0-1.8-1.8L1.5 8l3.4-1.3a3 3 0 0 0 1.8-1.8z" />
                            </svg>
                        </span>
                        <div class="t">
                            <div class="l">{{ __('AI suggestion') }}</div>
                            <div class="s" id="ti-aibar-text">
                                {{ __('Draft a reply for this conversation.') }}</div>
                        </div>
                        <button type="button" id="ti-aibar-suggest" class="use">{{ __('Suggest') }}</button>
                        <button type="button" id="ti-aibar-use" class="use hidden">{{ __('Use') }}</button>
                        <button type="button" id="ti-aibar-x" class="x" title="{{ __('Dismiss') }}">
                            <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.8">
                                <path d="M4 4l8 8M12 4l-8 8" />
                            </svg>
                        </button>
                    </div>
                @endif
                <div class="px-4 pt-3 pb-3">
                    <div id="composer-hint"
                        class="hidden text-[10.5px] text-[#7B5A14] bg-accent-amber/15 border border-accent-amber/30 rounded-md px-2 py-1 mb-2">
                        {{ __("Note: only your team can see this. The contact won't be notified.") }}
                    </div>
                    {{-- Template picker — only visible when composer mode = template.
 Lists the same WaTemplate library /wa-campaigns/create reads
 from. On Baileys (no Meta approval) every status is usable
 so we show a pill so the operator can tell which are public,
 approved, pending, or rejected. Clicking a card fills the
 composer body and flips back to Reply for edit + send. --}}
                    <div id="template-panel" class="hidden mb-3">
                        @if (($chatTemplates ?? collect())->isEmpty())
                            <div
                                class="text-[12px] text-ink-500 bg-paper-50 border border-paper-200 rounded-md px-3 py-2">
                                No templates yet. Create one in <a href="{{ url('/templates/create') }}"
                                    class="text-wa-deep font-semibold hover:underline">{{ __('Templates') }}</a>.
                            </div>
                        @else
                            <div class="flex items-center justify-between mb-2">
                                <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">Templates
                                    ·
                                    {{ count($chatTemplates) }}</div>
                                <input id="template-search" type="text"
                                    placeholder="{{ __('Search templates…') }}"
                                    class="text-[11.5px] px-2 py-1 rounded border border-paper-200 bg-paper-0 focus:outline-none focus:border-wa-deep w-40">
                            </div>
                            <div id="template-cards"
                                class="grid grid-cols-2 lg:grid-cols-3 gap-2 max-h-[180px] overflow-y-auto pr-1">
                                @foreach ($chatTemplates as $tpl)
                                    @php
                                        $statusClass = match ($tpl['status']) {
                                            'approved', 'public' => 'bg-accent-mint/20 text-wa-deep',
                                            'pending' => 'bg-accent-amber/20 text-[#7B5A14]',
                                            'rejected' => 'bg-accent-coral/20 text-accent-coral',
                                            default => 'bg-paper-100 text-ink-500',
                                        };
                                    @endphp
                                    <button type="button"
                                        class="template-card text-left bg-paper-0 border border-paper-200 hover:border-wa-deep hover:bg-wa-deep/5 rounded-md px-2.5 py-2 transition"
                                        data-template-id="{{ $tpl['id'] }}"
                                        data-template-title="{{ $tpl['title'] }}"
                                        data-template-category="{{ $tpl['category'] }}"
                                        data-template-header="{{ $tpl['header'] }}"
                                        data-template-body="{{ $tpl['body'] }}"
                                        data-template-footer="{{ $tpl['footer'] }}"
                                        data-template-buttons='@json($tpl['buttons'])'
                                        data-template-channel="{{ $tpl['channel'] ?? '' }}"
                                        data-send-meta='@json($tpl['send_meta'])'>
                                        <div class="flex items-center justify-between gap-1.5 mb-1">
                                            <span
                                                class="text-[12px] font-serif text-ink-700 truncate">{{ $tpl['title'] ?: 'Untitled' }}</span>
                                            <span
                                                class="font-mono text-[8.5px] uppercase tracking-wider px-1.5 py-0.5 rounded {{ $statusClass }} shrink-0">{{ $tpl['status'] }}</span>
                                        </div>
                                        <div class="flex items-center gap-1.5 mb-1">
                                            <span
                                                class="font-mono text-[8.5px] uppercase tracking-wider text-ink-500">{{ $tpl['category'] }}</span>
                                            @if ($tpl['media'])
                                                <span
                                                    class="font-mono text-[8.5px] uppercase tracking-wider text-ink-500">·
                                                    {{ $tpl['media'] }}</span>
                                            @endif
                                            @if (count($tpl['buttons']) > 0)
                                                <span
                                                    class="font-mono text-[8.5px] uppercase tracking-wider text-ink-500">·
                                                    {{ count($tpl['buttons']) }} {{ __('btn') }}</span>
                                            @endif
                                        </div>
                                        <div class="text-[10.5px] text-ink-500 leading-snug line-clamp-2">
                                            {{ \Illuminate\Support\Str::limit($tpl['body'], 80) ?: 'Empty body' }}
                                        </div>
                                    </button>
                                @endforeach
                            </div>
                        @endif
                    </div>
                    {{-- Selected-template preview. Replaces the freeform composer
 when a template card is clicked — shows the WhatsApp-style
 bubble (header bold, body, footer italic, buttons listed)
 so the operator sees exactly what the contact will receive.
 Hidden by default; the JS toggles it. --}}
                    <div id="template-preview" class="hidden mb-3">
                        <div class="flex items-center justify-between mb-2">
                            <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">
                                {{ __('Selected template ·') }} <span id="tp-name" class="text-ink-700"></span>
                            </div>
                            <button type="button" id="tp-clear"
                                class="text-[10.5px] text-accent-coral font-semibold hover:underline">{{ __('Clear · pick another') }}</button>
                        </div>
                        <div id="tp-bubble"
                            class="bg-[#005c4b] text-white rounded-lg px-3.5 py-2.5 max-w-[420px] ml-auto shadow-sm">
                            <div id="tp-header" class="font-semibold text-[14px] leading-tight mb-1 hidden"></div>
                            <div id="tp-body" class="text-[13.5px] whitespace-pre-wrap leading-snug"></div>
                            <div id="tp-footer" class="text-[11.5px] text-white/70 mt-1.5 hidden"></div>
                            <div id="tp-buttons" class="mt-2 space-y-1 hidden"></div>
                        </div>
                        {{-- Send-time mapping — the same panel campaigns and
                             broadcasts use, mounted directly (no <select>,
                             no form) via mountPanel(). Lets the operator fill
                             variables, swap header media, and set button links
                             for THIS reply only. --}}
                        <div id="ti-tlm-panel" class="hidden mt-2"></div>
                    </div>
                    {{-- Saved-reply picker — hidden until the ⚡ Shortcuts tab or "/" trigger opens it. --}}
                    <div id="quick-reply-panel"
                        class="hidden mb-3 border border-paper-200 rounded-xl bg-paper-0 shadow-md overflow-hidden">
                        <div class="flex items-center gap-2 px-3 py-2 border-b border-paper-200 bg-paper-50">
                            <svg viewBox="0 0 16 16" class="w-3.5 h-3.5 text-ink-500 shrink-0" fill="none"
                                stroke="currentColor" stroke-width="1.6">
                                <circle cx="7" cy="7" r="5" />
                                <path d="m11 11 3 3" />
                            </svg>
                            <input id="qr-search-input" type="text"
                                placeholder="{{ __('Search shortcuts or type /name…') }}"
                                class="flex-1 text-[12px] bg-transparent focus:outline-none" autocomplete="off" />
                            <button id="qr-close-btn"
                                class="text-[11px] text-ink-500 hover:text-ink-900 font-mono">✕</button>
                        </div>
                        <div id="quick-reply-list" class="max-h-[180px] overflow-y-auto divide-y divide-paper-200">
                            @for ($i = 0; $i < 3; $i++)
                                <div class="px-3 py-2 space-y-1.5">
                                    <div class="flex items-center gap-2">
                                        <div class="skeleton h-3 rounded w-12"></div>
                                        <div class="skeleton h-3 rounded w-1/3"></div>
                                    </div>
                                    <div class="skeleton h-2.5 rounded w-3/4"></div>
                                </div>
                            @endfor
                        </div>
                    </div>
                    {{-- Same compose-textarea component campaigns + auto-reply use,
 so formatting toolbar (B/I/S) + emoji + char counter stay
 consistent. The hidden plain textarea below mirrors the
 value because the existing JS reads from #composer. --}}
                    <div id="composer-textarea-wrap">
                        <x-compose-textarea id="composer-rich" name="composer_rich" :rows="3"
                            :maxlength="4096" placeholder="{{ __('Type a reply…') }}" :show-counter="true" />
                    </div>
                    <textarea id="composer" class="hidden" tabindex="-1" aria-hidden="true"></textarea>
                    {{-- Voice-note recording panel — hidden by default. Replaces the
 composer toolbar once the user taps 🎤. Shows duration counter
 + waveform dots while recording, then preview/send/discard
 after stop. --}}
                    <div id="voice-recorder-bar"
                        class="hidden mt-2 flex items-center gap-2 px-3 py-2 rounded-full bg-accent-coral/10 border border-accent-coral/30">
                        <span class="relative flex h-2.5 w-2.5">
                            <span
                                class="animate-ping absolute inline-flex h-full w-full rounded-full bg-accent-coral opacity-75"></span>
                            <span class="relative inline-flex rounded-full h-2.5 w-2.5 bg-accent-coral"></span>
                        </span>
                        <span
                            class="font-mono text-[12px] text-accent-coral font-semibold">{{ __('Recording…') }}</span>
                        <span id="voice-timer" class="font-mono text-[12px] text-ink-700">0:00</span>
                        <div class="flex-1"></div>
                        <button id="voice-cancel-btn" type="button"
                            class="px-3 py-1 rounded-full bg-paper-100 hover:bg-paper-200 text-[11.5px] font-semibold text-ink-700">{{ __('Cancel') }}</button>
                        <button id="voice-stop-btn" type="button"
                            class="px-3 py-1 rounded-full bg-wa-deep hover:bg-wa-teal text-paper-0 text-[11.5px] font-semibold inline-flex items-center gap-1.5">
                            <svg viewBox="0 0 16 16" class="w-2.5 h-2.5" fill="currentColor">
                                <rect x="3" y="3" width="10" height="10" rx="1" />
                            </svg>
                            Stop
                        </button>
                    </div>
                    {{-- Voice-note preview — shown after stop, before send. --}}
                    <div id="voice-preview-bar"
                        class="hidden mt-2 flex items-center gap-2 px-3 py-2 rounded-full bg-wa-bubble border border-wa-deep/30">
                        <svg viewBox="0 0 16 16" class="w-3.5 h-3.5 text-wa-deep" fill="currentColor">
                            <path d="M8 1a3 3 0 0 0-3 3v4a3 3 0 0 0 6 0V4a3 3 0 0 0-3-3z" />
                            <path d="M3 8a5 5 0 0 0 10 0M8 13v2" />
                        </svg>
                        <audio id="voice-preview-audio" controls class="flex-1 h-7 max-w-[200px]"></audio>
                        <span id="voice-preview-duration" class="font-mono text-[11px] text-ink-500">0:00</span>
                        <button id="voice-discard-btn" type="button"
                            class="px-3 py-1 rounded-full bg-paper-100 hover:bg-paper-200 text-[11.5px] font-semibold text-ink-700">{{ __('Redo') }}</button>
                        <button id="voice-send-btn" type="button"
                            class="px-3 py-1 rounded-full bg-wa-deep hover:bg-wa-teal text-paper-0 text-[11.5px] font-semibold inline-flex items-center gap-1.5">
                            <svg viewBox="0 0 16 16" class="w-3 h-3" fill="currentColor">
                                <path d="M2 14l13-6L2 2v5l9 1-9 1z" />
                            </svg>
                            Send voice
                        </button>
                    </div>
                    {{-- Media preview strip — shown after the operator picks files via
 the paperclip. One thumb per file with × to remove. Send All
 dispatches each as a separate WhatsApp message. --}}
                    <div id="media-preview-bar"
                        class="hidden mt-2 p-2 rounded-lg bg-paper-50 border border-paper-200">
                        <div class="flex items-center gap-1.5 mb-1.5">
                            <span
                                class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">{{ __('Attachments') }}</span>
                            <span id="media-preview-count" class="font-mono text-[10px] text-ink-500">0</span>
                            <div class="flex-1"></div>
                            <button id="media-clear-btn" type="button"
                                class="text-[10.5px] text-accent-coral hover:underline">{{ __('Clear all') }}</button>
                            <button id="media-send-btn" type="button"
                                class="px-3 py-1 rounded-full bg-wa-deep text-paper-0 text-[11px] font-semibold hover:bg-wa-teal">{{ __('Send all') }}</button>
                        </div>
                        <div id="media-preview-grid" class="flex items-start gap-2 flex-wrap"></div>
                    </div>
                    <input id="media-file-input" type="file" accept="image/*,video/*,application/pdf" multiple
                        class="hidden">
                    <div id="composer-actions" class="flex items-center justify-end mt-2 gap-2">
                        <button id="media-attach-btn" type="button" title="{{ __('Attach images / files') }}"
                            class="w-8 h-8 rounded-full bg-paper-100 hover:bg-wa-deep hover:text-paper-0 text-ink-700 grid place-items-center transition">
                            <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor"
                                stroke-width="1.7">
                                <path d="M11 7l-5 5a2 2 0 0 1-3-3l6-6a3 3 0 0 1 4 4l-7 7" />
                            </svg>
                        </button>
                        <button id="voice-record-btn" type="button" title="{{ __('Record voice note') }}"
                            class="w-8 h-8 rounded-full bg-paper-100 hover:bg-wa-deep hover:text-paper-0 text-ink-700 grid place-items-center transition">
                            <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor"
                                stroke-width="1.7">
                                <rect x="6" y="2" width="4" height="8" rx="2" />
                                <path d="M3 8a5 5 0 0 0 10 0M8 13v2" />
                            </svg>
                        </button>
                        {{-- Catalog send (SPM / MPM / link) — opens the product picker modal --}}
                        <button id="catalog-btn" type="button" title="{{ __('Send catalog products') }}"
                            class="w-8 h-8 rounded-full bg-paper-100 hover:bg-wa-deep hover:text-paper-0 text-ink-700 grid place-items-center transition">
                            <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor"
                                stroke-width="1.7">
                                <rect x="2" y="3" width="12" height="10" rx="1.5" />
                                <path d="M2 6h12M5 3v3" />
                            </svg>
                        </button>
                        {{-- Google Meet — mints a Calendar event with a Meet link via
 the workspace's connected Google account, then drops the
 URL into the composer textarea. Customer needs no Google
 account; joining works from any browser. --}}
                        <button id="meet-btn" type="button" title="{{ __('Create Google Meet link') }}"
                            class="w-8 h-8 rounded-full bg-paper-100 hover:bg-wa-deep hover:text-paper-0 text-ink-700 grid place-items-center transition">
                            <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor"
                                stroke-width="1.7">
                                <rect x="2" y="5" width="8" height="6" rx="1" />
                                <path d="M10 7l4-2v6l-4-2z" />
                            </svg>
                        </button>
                        {{-- AI voice-assistant takeover — lists workspace assistants
 and pins one to this conversation. After assignment, the
 next customer reply triggers an automatic AI response. --}}
                        <button id="ai-takeover-btn" type="button"
                            title="{{ __('Let an AI assistant handle this chat') }}"
                            class="w-8 h-8 rounded-full bg-paper-100 hover:bg-wa-deep hover:text-paper-0 text-ink-700 grid place-items-center transition">
                            <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor"
                                stroke-width="1.7">
                                <rect x="3" y="4" width="10" height="9" rx="1.5" />
                                <path d="M3 6h10M6 9h2M9 9h1" />
                                <path d="M5 1v3M11 1v3" />
                            </svg>
                        </button>
                        <button id="send-btn"
                            class="px-4 py-1.5 rounded-full bg-wa-deep hover:bg-wa-teal text-paper-0 text-[12px] font-semibold inline-flex items-center gap-1.5 disabled:opacity-50 disabled:cursor-not-allowed">
                            <svg viewBox="0 0 16 16" class="w-3 h-3" fill="currentColor">
                                <path d="M2 14l13-6L2 2v5l9 1-9 1z" />
                            </svg>
                            Send
                        </button>
                    </div>
                    {{-- Catalog picker modal: 3 modes (SPM = 1 product · MPM = up to 30 · Link = none) --}}
                    <div id="catalog-modal" class="hidden fixed inset-0 z-50 grid place-items-center p-4">
                        <div class="absolute inset-0 bg-ink-900/40" data-catalog-backdrop></div>
                        <div
                            class="relative bg-paper-0 rounded-2xl w-full max-w-2xl shadow-2xl flex flex-col max-h-[85vh]">
                            <div
                                class="flex items-center justify-between px-5 py-3 border-b border-paper-200 shrink-0">
                                <div>
                                    <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">
                                        {{ __('Send catalog') }}</div>
                                    <h3 class="font-serif text-[20px] leading-tight">
                                        {{ __('Pick products to send') }}</h3>
                                </div>
                                <button data-catalog-close
                                    class="w-8 h-8 rounded-full hover:bg-paper-50 text-ink-500 text-[18px]"
                                    aria-label="{{ __('Close') }}">×</button>
                            </div>
                            <details class="border-b border-paper-200 bg-paper-50 shrink-0">
                                <summary
                                    class="px-5 py-2 text-[11.5px] font-mono uppercase tracking-[0.14em] text-ink-700 cursor-pointer hover:bg-paper-100 select-none flex items-center gap-2">
                                    <svg viewBox="0 0 16 16" class="w-3 h-3" fill="none"
                                        stroke="currentColor" stroke-width="1.8">
                                        <path d="M6 4l4 4-4 4" />
                                    </svg>
                                    How catalog send works
                                </summary>
                                <pre
                                    class="px-5 py-3 text-[11.5px] leading-relaxed text-ink-700 font-mono whitespace-pre overflow-x-auto bg-paper-0 border-t border-paper-200">
You pick a mode + select products
 │
 ├── Single product → one product card with image + price + Add to cart
 ├── Product list → up to 30 products in a scrollable list
 └── Catalog link → a clickable bubble opening your whole catalog
 │
 ▼
Click Send
 │
 ▼
Customer sees the catalog inside WhatsApp
 │
 ├── Taps a product → goes to product detail
 ├── Adds to cart → "Send cart" comes back to this chat
 └── You see the order in chat → reply normally to confirm
Tip: keep prices in sync via /catalog or Google Sheets so what they
see is what they pay.</pre>
                            </details>
                            <div
                                class="px-5 py-3 border-b border-paper-200 shrink-0 flex items-center gap-2 flex-wrap">
                                <div class="inline-flex rounded-full border border-paper-200 overflow-hidden"
                                    data-catalog-mode>
                                    <button type="button" data-mode="spm"
                                        class="px-3 py-1.5 text-[11.5px] font-semibold bg-wa-deep text-paper-0">{{ __('Single product') }}</button>
                                    <button type="button" data-mode="mpm"
                                        class="px-3 py-1.5 text-[11.5px] font-medium text-ink-700 hover:bg-paper-50">{{ __('Product list') }}</button>
                                    <button type="button" data-mode="link"
                                        class="px-3 py-1.5 text-[11.5px] font-medium text-ink-700 hover:bg-paper-50">{{ __('Catalog link') }}</button>
                                </div>
                                <div class="ml-auto relative">
                                    <svg viewBox="0 0 16 16"
                                        class="w-3.5 h-3.5 absolute left-3 top-1/2 -translate-y-1/2 text-ink-500"
                                        fill="none" stroke="currentColor" stroke-width="1.6">
                                        <circle cx="7" cy="7" r="5" />
                                        <path d="m11 11 3 3" />
                                    </svg>
                                    <input type="search" data-catalog-search
                                        placeholder="{{ __('Search products…') }}"
                                        class="pl-9 pr-3 py-1.5 border border-paper-200 rounded-full bg-paper-50 text-[12px] focus:outline-none focus:border-wa-deep w-56">
                                </div>
                            </div>
                            <div class="flex-1 min-h-0 overflow-y-auto px-5 py-3" data-catalog-list>
                                <div class="text-[12px] text-ink-500 py-6 text-center">{{ __('Loading products…') }}
                                </div>
                            </div>
                            <div class="px-5 py-3 border-t border-paper-200 shrink-0">
                                <input type="text" data-catalog-body
                                    placeholder="{{ __('Optional message (body text)') }}" maxlength="1024"
                                    class="w-full px-3 py-2 border border-paper-200 rounded-lg bg-paper-50 text-[12.5px] focus:outline-none focus:border-wa-deep mb-2">
                                <div class="flex items-center gap-2 flex-wrap">
                                    <span class="font-mono text-[11px] text-ink-500" data-catalog-summary>0
                                        selected</span>
                                    <span class="ml-auto"></span>
                                    <button type="button" data-catalog-close
                                        class="px-4 py-2 rounded-full border border-paper-200 hover:bg-paper-50 text-[12px]">{{ __('Cancel') }}</button>
                                    <button type="button" data-catalog-send
                                        class="px-5 py-2 rounded-full bg-wa-deep hover:bg-wa-teal text-paper-0 text-[12.5px] font-semibold disabled:opacity-50"
                                        disabled>{{ __('Send →') }}</button>
                                </div>
                                <div data-catalog-warn
                                    class="hidden mt-2 px-3 py-2 rounded-lg bg-accent-amber/15 text-[11.5px] text-[#A1431F]">
                                </div>
                            </div>
                        </div>
                    </div>
                    {{-- Google Meet picker — operator clicks the meet-btn, picks
 a title + duration + start time, hits Create. Backend
 mints a Calendar event via the workspace's Google account
 with conferenceData.createRequest, returns the Meet URL.
 JS drops the URL into the composer textarea (with the
 configured message template) so the operator can review
 and click the existing Send button. --}}
                    <div id="meet-modal" class="hidden fixed inset-0 z-50 grid place-items-center p-4">
                        <div class="absolute inset-0 bg-ink-900/40" data-meet-backdrop></div>
                        <div class="relative bg-paper-0 rounded-2xl w-full max-w-md shadow-2xl flex flex-col">
                            <div
                                class="flex items-center justify-between px-5 py-3 border-b border-paper-200 shrink-0">
                                <div>
                                    <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">
                                        {{ __('Google Meet') }}</div>
                                    <h3 class="font-serif text-[20px] leading-tight">{{ __('Create a meeting') }}
                                    </h3>
                                </div>
                                <button data-meet-close
                                    class="w-8 h-8 rounded-full hover:bg-paper-50 text-ink-500 text-[18px]"
                                    aria-label="{{ __('Close') }}">×</button>
                            </div>
                            <div class="px-5 py-4 space-y-3">
                                <label class="block">
                                    <span
                                        class="text-[11.5px] font-semibold text-ink-700 mb-1 block">{{ __('Meeting title') }}</span>
                                    <input id="meet-title" type="text" maxlength="200"
                                        placeholder="{{ __('Call with customer') }}"
                                        class="w-full px-3 py-2 border border-paper-200 rounded-lg bg-paper-0 text-[13px] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10" />
                                </label>
                                <div class="grid grid-cols-2 gap-3">
                                    <label class="block">
                                        <span
                                            class="text-[11.5px] font-semibold text-ink-700 mb-1 block">{{ __('Start') }}</span>
                                        <select id="meet-start"
                                            class="w-full px-3 py-2 border border-paper-200 rounded-lg bg-paper-0 text-[13px] focus:outline-none focus:border-wa-deep">
                                            <option value="5">{{ __('In 5 min') }}</option>
                                            <option value="15" selected>{{ __('In 15 min') }}</option>
                                            <option value="30">{{ __('In 30 min') }}</option>
                                            <option value="60">{{ __('In 1 hour') }}</option>
                                            <option value="1440">{{ __('Tomorrow 10:00 AM') }}</option>
                                        </select>
                                    </label>
                                    <label class="block">
                                        <span
                                            class="text-[11.5px] font-semibold text-ink-700 mb-1 block">{{ __('Duration') }}</span>
                                        <select id="meet-duration"
                                            class="w-full px-3 py-2 border border-paper-200 rounded-lg bg-paper-0 text-[13px] focus:outline-none focus:border-wa-deep">
                                            <option value="15">15 min</option>
                                            <option value="30" selected>30 min</option>
                                            <option value="45">45 min</option>
                                            <option value="60">60 min</option>
                                        </select>
                                    </label>
                                </div>
                                <label class="block">
                                    <span
                                        class="text-[11.5px] font-semibold text-ink-700 mb-1 block">{{ __('Message template') }}</span>
                                    <textarea id="meet-template" rows="3" maxlength="800"
                                        class="w-full px-3 py-2 border border-paper-200 rounded-lg bg-paper-0 text-[13px] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10">Your meeting link:
@{{ meet_link }}
Starts @{{ meet_start }}</textarea>
                                    <span class="text-[10.5px] text-ink-500 mt-1 block">@{{ meet_link }} and
                                        @{{ meet_start }} get substituted.</span>
                                </label>
                                <label class="inline-flex items-center gap-2 text-[12.5px] cursor-pointer">
                                    <input id="meet-invite" type="checkbox" class="w-4 h-4 accent-wa-deep" />
                                    <span
                                        class="text-ink-700">{{ __('Email a Google Calendar invite to the customer (only if their email is on file)') }}</span>
                                </label>
                                <div id="meet-error"
                                    class="hidden px-3 py-2 rounded-lg border border-accent-coral/30 bg-accent-coral/10 text-accent-coral text-[12px]">
                                </div>
                            </div>
                            <div
                                class="px-5 py-3 border-t border-paper-200 flex items-center justify-end gap-2 bg-paper-50/40">
                                <button data-meet-close
                                    class="px-3 py-1.5 rounded-full border border-paper-200 bg-paper-0 hover:bg-paper-50 text-[12px]">{{ __('Cancel') }}</button>
                                <button id="meet-create-btn"
                                    class="px-3.5 py-1.5 rounded-full bg-wa-deep hover:bg-wa-teal text-paper-0 text-[12px] font-semibold inline-flex items-center gap-1.5 disabled:opacity-50">
                                    <svg viewBox="0 0 16 16" class="w-3 h-3" fill="none"
                                        stroke="currentColor" stroke-width="2">
                                        <rect x="2" y="5" width="8" height="6" rx="1" />
                                        <path d="M10 7l4-2v6l-4-2z" />
                                    </svg>
                                    <span id="meet-create-label">{{ __('Create + insert into composer') }}</span>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            {{-- AI assistant takeover picker — lists the workspace's voice
 assistants and pins one to the active conversation. The
 existing inbound message pipeline then auto-replies with that
 assistant's system prompt + provider/model on every customer
 message until the operator detaches it. --}}
            <div id="ai-takeover-modal" class="hidden fixed inset-0 z-50 grid place-items-center p-4">
                <div class="absolute inset-0 bg-ink-900/40" data-aita-backdrop></div>
                <div class="relative bg-paper-0 rounded-2xl w-full max-w-lg shadow-2xl flex flex-col max-h-[80vh]">
                    <div class="flex items-center justify-between px-5 py-3 border-b border-paper-200 shrink-0">
                        <div>
                            <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">
                                {{ __('Hand over') }}</div>
                            <h3 class="font-serif text-[20px] leading-tight">{{ __('Let an AI assistant reply') }}
                            </h3>
                        </div>
                        <button data-aita-close
                            class="w-8 h-8 rounded-full hover:bg-paper-50 text-ink-500 text-[18px]">×</button>
                    </div>
                    <div class="px-5 py-3 border-b border-paper-200 bg-paper-50/40 text-[11.5px] text-ink-700">
                        The assistant will use its configured system prompt + AI model to reply on every subsequent
                        customer message. Click <span class="font-mono">{{ __('Detach') }}</span> to take control
                        back.
                    </div>
                    <div class="flex-1 min-h-0 overflow-y-auto px-3 py-2" data-aita-list>
                        <div class="text-[12px] text-ink-500 py-6 text-center">{{ __('Loading assistants…') }}</div>
                    </div>
                    <div class="px-5 py-3 border-t border-paper-200 flex items-center justify-end gap-2">
                        <button type="button" data-aita-close
                            class="px-3 py-1.5 rounded-full border border-paper-200 bg-paper-0 hover:bg-paper-50 text-[12px]">{{ __('Cancel') }}</button>
                        <button type="button" data-aita-detach
                            class="hidden px-3 py-1.5 rounded-full border border-accent-coral/40 text-accent-coral hover:bg-accent-coral/10 text-[12px] font-semibold">{{ __('Detach AI') }}</button>
                    </div>
                </div>
            </div>
        </main>
        <!-- ========== COLUMN 4: contact + collaboration ========== -->
        <aside id="ct-panel"
            class="ti-col ti-contact bg-paper-50/50 border-l border-paper-200 overflow-y-auto relative hidden">
            <button id="contact-close"
                class="absolute top-3 right-3 w-7 h-7 rounded-full bg-paper-0 border border-paper-200 hover:bg-paper-50 grid place-items-center z-10"
                title="{{ __('Hide contact panel') }}">
                <svg viewBox="0 0 16 16" class="w-3.5 h-3.5 text-ink-700" fill="none" stroke="currentColor"
                    stroke-width="1.7">
                    <path d="M4 4l8 8M12 4l-8 8" />
                </svg>
            </button>
            <div class="px-5 pt-5 pb-4 border-b border-paper-200 bg-paper-0">
                <div class="flex flex-col items-center text-center">
                    <span id="ct-avatar"
                        class="w-16 h-16 rounded-full bg-wa-deep text-paper-0 text-[20px] font-semibold grid place-items-center"></span>
                    <div id="ct-name" class="font-serif text-[18px] leading-tight mt-3"></div>
                    <div id="ct-phone" class="font-mono text-[11.5px] text-ink-500 mt-0.5"></div>
                </div>
            </div>
            {{-- Omni Quick actions — one obvious action strip on the profile.
                 Each button forwards its click to the existing control (New
                 deal / Note / Snooze / Tag) so there's no duplicate wiring. --}}
            <div class="px-4 py-3 border-b border-paper-200 bg-paper-0">
                <div class="font-mono text-[10px] uppercase tracking-[0.08em] text-ink-500 mb-2">
                    {{ __('Quick actions') }}</div>
                <div class="grid grid-cols-4 gap-1.5">
                    <button type="button" data-qa="create-deal-btn" class="ti-qa"
                        title="{{ __('Create a deal') }}">
                        <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5"
                            stroke-linejoin="round">
                            <path d="M2 11V7l6-4.5L14 7v4M2 11h12M6 11V8.5h4V11" />
                        </svg>
                        <span>{{ __('Deal') }}</span>
                    </button>
                    <button type="button" data-qa="add-note-btn" class="ti-qa"
                        title="{{ __('Add internal note') }}">
                        <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5"
                            stroke-linecap="round" stroke-linejoin="round">
                            <path d="M9 2H4a1 1 0 0 0-1 1v10a1 1 0 0 0 1 1h8a1 1 0 0 0 1-1V6z" />
                            <path d="M9 2v4h4M5.5 8.5h5M5.5 11h3" />
                        </svg>
                        <span>{{ __('Note') }}</span>
                    </button>
                    <button type="button" data-qa="snooze-btn" class="ti-qa" title="{{ __('Snooze') }}">
                        <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5"
                            stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="8" cy="8.5" r="5" />
                            <path d="M8 6v2.5l1.6 1.2M5.5 2 3 4M13 4l-2.5-2" />
                        </svg>
                        <span>{{ __('Snooze') }}</span>
                    </button>
                    <button type="button" data-qa="add-tag-btn" class="ti-qa" title="{{ __('Add tag') }}">
                        <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5"
                            stroke-linejoin="round">
                            <path d="M2.5 7.5V3.5A1 1 0 0 1 3.5 2.5h4l6 6-4 4z" />
                            <circle cx="5.5" cy="5.5" r=".9" fill="currentColor" stroke="none" />
                        </svg>
                        <span>{{ __('Tag') }}</span>
                    </button>
                </div>
            </div>
            {{-- Omni "Channel" card — the network this contact is reachable on.
                 Populated live per-conversation in renderActive(); currently one
                 chip (the open thread's channel), ready to list more once contacts
                 are linked across channels. --}}
            <div class="px-4 py-3 border-b border-paper-200 bg-paper-0">
                <div
                    class="font-mono text-[10px] uppercase tracking-[0.08em] text-ink-500 mb-2 flex items-center gap-1.5">
                    <svg viewBox="0 0 16 16" class="w-3 h-3" fill="none" stroke="currentColor"
                        stroke-width="1.5">
                        <path
                            d="M3 5.5A2.5 2.5 0 0 1 5.5 3h5A2.5 2.5 0 0 1 13 5.5v3A2.5 2.5 0 0 1 10.5 11H8l-3.5 2v-2A2.5 2.5 0 0 1 3 8.5v-3Z" />
                    </svg>
                    {{ __('Channel') }}
                </div>
                <div id="ct-channels" class="ti-chanrow"></div>
            </div>
            {{-- Omni Attributes — contact profile fields (email / phone / language
                 / address) + any custom attributes. Rendered per-conversation by
                 renderContactAttributes() from state.active.contact_profile; the
                 whole card hides when there's nothing to show. --}}
            <div id="ct-attributes-section" class="px-4 py-3 border-b border-paper-200 bg-paper-0 hidden">
                <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500 mb-2">
                    {{ __('Attributes') }}</div>
                <div id="ct-attributes" class="space-y-1.5"></div>
            </div>
            <div id="ct-stats"
                class="grid grid-cols-3 gap-2 px-4 py-3 border-b border-paper-200 bg-paper-0 hidden">
                <div class="text-center">
                    <div id="ct-orders" class="font-serif text-[16px] leading-none">—</div>
                    <div class="font-mono text-[9px] uppercase tracking-[0.14em] text-ink-500 mt-1">
                        {{ __('Orders') }}</div>
                </div>
                <div class="text-center border-l border-r border-paper-200">
                    <div id="ct-ltv" class="font-serif text-[16px] leading-none">—</div>
                    <div class="font-mono text-[9px] uppercase tracking-[0.14em] text-ink-500 mt-1">
                        {{ __('LTV') }}</div>
                </div>
                <div class="text-center">
                    <div id="ct-since" class="font-serif text-[16px] leading-none">—</div>
                    <div class="font-mono text-[9px] uppercase tracking-[0.14em] text-ink-500 mt-1">
                        {{ __('Customer') }}</div>
                </div>
            </div>
            <div class="px-4 py-3 border-b border-paper-200 bg-paper-0">
                <div class="flex items-center justify-between mb-2">
                    <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">
                        {{ __('Assignment') }}</div>
                    <button id="reassign-link"
                        class="text-[10.5px] text-wa-deep font-semibold hover:underline">{{ __('Reassign') }}</button>
                </div>
                <div id="ct-assignee" class="flex items-center gap-2.5"></div>
            </div>
            <div class="px-4 py-3 border-b border-paper-200 bg-paper-0">
                <div class="flex items-center justify-between mb-2">
                    <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">{{ __('AI Agent') }}
                    </div>
                    <button id="ct-agent-change"
                        class="text-[10.5px] text-wa-deep font-semibold hover:underline">{{ __('Change') }}</button>
                </div>
                <div id="ct-agent" class="text-[12px] text-ink-600">{{ __('None assigned') }}</div>
            </div>
            <div class="px-4 py-3 border-b border-paper-200 bg-paper-0">
                <div class="flex items-center justify-between mb-2">
                    <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">{{ __('Tags') }}
                    </div>
                    <button id="add-tag-btn" type="button"
                        class="text-[10.5px] text-wa-deep font-semibold hover:underline">+ Add</button>
                </div>
                <div id="ct-tags" class="flex items-center gap-1.5 flex-wrap"></div>
            </div>
            {{-- Sales Pipeline deals for this contact — only revealed when the
                 plan has the feature (JS toggles `hidden` after /deals fetch). --}}
            <div id="ct-deals-section" class="px-4 py-3 border-b border-paper-200 bg-paper-0 hidden">
                <div class="flex items-center justify-between mb-2">
                    <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">
                        {{ __('Deals ·') }} <span id="ct-deals-count">0</span></div>
                    <button id="ct-deal-new" type="button"
                        class="text-[10.5px] text-wa-deep font-semibold hover:underline">+
                        {{ __('New') }}</button>
                </div>
                <div id="ct-deals" class="space-y-1.5"></div>
            </div>
            <div class="px-4 py-3 border-b border-paper-200 bg-paper-0">
                <div class="flex items-center justify-between mb-2">
                    <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">
                        {{ __('Internal notes ·') }} <span id="notes-count">0</span></div>
                    <button id="add-note-btn" class="text-[10.5px] text-wa-deep font-semibold hover:underline">+ Add
                        note</button>
                </div>
                <div id="ct-notes" class="space-y-2"></div>
            </div>
            <div class="px-4 py-3 border-b border-paper-200 bg-paper-0">
                <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500 mb-2">
                    {{ __('Past conversations') }}</div>
                <ol class="relative border-l-2 border-paper-200 pl-3 space-y-2.5 ml-1" id="ct-history"></ol>
            </div>
            <div class="px-4 py-3 bg-paper-0">
                <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500 mb-2">
                    {{ __('Activity & Resolution History') }}</div>
                <ol id="ct-events" class="relative border-l-2 border-paper-200 pl-3 space-y-2 ml-1">
                    <li class="text-[11px] text-ink-500 ml-1">{{ __('No events yet.') }}</li>
                </ol>
            </div>
        </aside>
    </div>
    <div id="toast"></div>
    {{-- ======== Invite teammate modal ============================ --}}
    {{-- Duplicate-chat cleanup. Scan is read-only and always runs first, so the
         operator sees exactly which threads join before anything is touched. --}}
    <div id="merge-dupes-modal" class="hidden fixed inset-0 z-50 grid place-items-center p-4">
        <div class="absolute inset-0 bg-ink-900/40" data-close-dupes></div>
        <div class="relative bg-paper-0 rounded-2xl w-full max-w-lg p-5 shadow-2xl">
            <div class="flex items-start justify-between mb-1">
                <div>
                    <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">
                        {{ __('Inbox maintenance') }}</div>
                    <h3 class="font-serif text-[22px] leading-tight">{{ __('Merge duplicate chats') }}</h3>
                </div>
                <button data-close-dupes
                    class="w-8 h-8 rounded-full hover:bg-paper-50 grid place-items-center text-ink-700">×</button>
            </div>
            <p class="text-[12px] text-ink-500 mt-1">
                {{ __('Older builds could open a second thread for a customer who was already in your inbox. Newer builds no longer do, but threads that already split stay split. This joins them back together.') }}
            </p>
            <div
                class="mt-3 rounded-xl border border-paper-200 bg-paper-50 px-3 py-2.5 text-[11.5px] text-ink-700 leading-relaxed">
                <div class="font-mono uppercase tracking-[0.14em] text-[10px] text-ink-500 mb-1">
                    {{ __('What happens') }}</div>
                <ul class="space-y-1">
                    <li>· {{ __('Every message, note and tag moves to the oldest thread — nothing is deleted.') }}
                    </li>
                    <li>· {{ __('Only threads for the same customer on the same number are joined.') }}</li>
                    <li>·
                        {{ __('Different channels stay separate — a Meta chat never merges into an Unofficial API one.') }}
                    </li>
                </ul>
            </div>
            <div id="dupes-body" class="mt-4 min-h-[80px]">
                <div class="text-[12px] text-ink-500 py-6 text-center">{{ __('Scanning…') }}</div>
            </div>
            <div class="mt-4 flex items-center justify-end gap-2">
                <button data-close-dupes type="button"
                    class="px-3 py-1.5 rounded-full border border-paper-200 text-[12px] hover:bg-paper-50">
                    {{ __('Close') }}</button>
                <button id="dupes-merge-btn" type="button" disabled
                    class="px-4 py-1.5 rounded-full bg-wa-deep text-paper-0 text-[12px] hover:bg-wa-teal disabled:opacity-40 disabled:cursor-not-allowed">
                    {{ __('Merge all') }}</button>
            </div>
        </div>
    </div>
    <div id="invite-modal" class="hidden fixed inset-0 z-50 grid place-items-center p-4">
        <div class="absolute inset-0 bg-ink-900/40" data-close-invite></div>
        <div class="relative bg-paper-0 rounded-2xl w-full max-w-md p-5 shadow-2xl">
            <div class="flex items-start justify-between mb-1">
                <div>
                    <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">
                        {{ __('Workspace') }}</div>
                    <h3 class="font-serif text-[22px] leading-tight">{{ __('Invite a teammate') }}</h3>
                </div>
                <button data-close-invite
                    class="w-8 h-8 rounded-full hover:bg-paper-50 grid place-items-center text-ink-700">×</button>
            </div>
            <p class="text-[12px] text-ink-500 mt-1">
                {{ __("They'll appear in the inbox once they log in. If they don't have an account yet we'll create one for you and show a one-time password to share.") }}
            </p>
            <details class="mt-3 border border-paper-200 rounded-xl bg-paper-50 overflow-hidden">
                <summary
                    class="px-3 py-2 text-[11.5px] font-mono uppercase tracking-[0.14em] text-ink-700 cursor-pointer hover:bg-paper-100 select-none flex items-center gap-2">
                    <svg viewBox="0 0 16 16" class="w-3 h-3" fill="none" stroke="currentColor"
                        stroke-width="1.8">
                        <path d="M6 4l4 4-4 4" />
                    </svg>
                    How invites work
                </summary>
                <pre
                    class="px-4 py-3 text-[11.5px] leading-relaxed text-ink-700 font-mono whitespace-pre overflow-x-auto bg-paper-0 border-t border-paper-200">
You fill in name + email + role
 │
 ▼
Does this email already have an account?
 │
 ├── YES → they're added to your workspace with the chosen role
 │ (next time they log in they'll see your inbox)
 │
 └── NO → {{ brand_name() }} creates an account for them
 │
 ├── A one-time password is shown to you
 ├── Share it with them privately
 └── They log in → set a real password → ready
Roles in short:
 • Agent → only sees + replies to chats assigned to them
 • Manager → sees all chats, can assign + resolve
 • Admin → everything managers do + settings + billing</pre>
            </details>
            <form id="invite-form" class="mt-4 space-y-3">
                <div>
                    <label
                        class="text-[11.5px] font-semibold text-ink-700 mb-1.5 block">{{ __('Full name') }}</label>
                    <input type="text" name="name" required maxlength="191"
                        class="w-full px-3 py-2 border border-paper-200 rounded-lg bg-paper-0 text-[13px] focus:outline-none focus:border-wa-deep"
                        placeholder="{{ __('e.g. Riya Shah') }}" />
                </div>
                <div>
                    <label class="text-[11.5px] font-semibold text-ink-700 mb-1.5 block">{{ __('Email') }}</label>
                    <input type="email" name="email" required maxlength="191"
                        class="w-full px-3 py-2 border border-paper-200 rounded-lg bg-paper-0 text-[13px] focus:outline-none focus:border-wa-deep"
                        placeholder="riya@company.com" />
                </div>
                <div>
                    <label
                        class="text-[11.5px] font-semibold text-ink-700 mb-1.5 block">{{ __('Workspace role') }}</label>
                    @php
                        $grantable = \App\Support\WorkspacePermissions::grantableRolesFor(auth()->user());
                        $roleLabels = [
                            'agent' => 'Agent — handles assigned conversations only',
                            'manager' => 'Manager — sees all teams, can assign + resolve',
                            'admin' => 'Admin — full workspace access (no billing)',
                            'viewer' => 'Viewer — read-only',
                            'owner' => 'Owner — everything including billing',
                        ];
                    @endphp
                    <select name="role" required
                        class="w-full px-3 py-2 border border-paper-200 rounded-lg bg-paper-0 text-[13px] focus:outline-none focus:border-wa-deep">
                        @foreach ($grantable as $r)
                            <option value="{{ $r }}" @if ($r === 'agent') selected @endif>
                                {{ $roleLabels[$r] ?? $r }}</option>
                        @endforeach
                    </select>
                    <div class="text-[10.5px] text-ink-500 mt-1.5 leading-snug">
                        {{ __('You can change this later from the members list.') }}</div>
                </div>
                <div id="invite-result" class="hidden rounded-lg p-3 text-[12px]"></div>
                <div class="flex items-center gap-2 pt-1">
                    <button type="button" data-close-invite
                        class="ml-auto px-3 py-1.5 rounded-full border border-paper-200 hover:bg-paper-50 text-[12px]">{{ __('Cancel') }}</button>
                    <button type="submit" id="invite-submit"
                        class="px-3 py-1.5 rounded-full bg-wa-deep text-paper-0 text-[12px] font-semibold hover:bg-wa-teal">{{ __('Send invite') }}</button>
                </div>
            </form>
        </div>
    </div>
    {{-- ======== Edit team modal ================================== --}}
    <div id="edit-team-modal" class="hidden fixed inset-0 z-50 grid place-items-center p-4">
        <div class="absolute inset-0 bg-ink-900/40" data-close-edit-team></div>
        <div class="relative bg-paper-0 rounded-2xl w-full max-w-lg p-5 shadow-2xl">
            <div class="flex items-start justify-between mb-1">
                <div>
                    <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">
                        {{ __('Workspace') }}</div>
                    <h3 class="font-serif text-[22px] leading-tight">{{ __('Edit team') }}</h3>
                </div>
                <button data-close-edit-team
                    class="w-8 h-8 rounded-full hover:bg-paper-50 grid place-items-center text-ink-700">×</button>
            </div>
            <form id="edit-team-form" class="mt-4 space-y-3">
                <input type="hidden" id="edit-team-id" value="">
                <div>
                    <label
                        class="text-[11.5px] font-semibold text-ink-700 mb-1.5 block">{{ __('Team name') }}</label>
                    <input type="text" name="name" required maxlength="64"
                        class="w-full px-3 py-2 border border-paper-200 rounded-lg bg-paper-0 text-[13px] focus:outline-none focus:border-wa-deep" />
                </div>
                <div>
                    <label class="text-[11.5px] font-semibold text-ink-700 mb-1.5 block">{{ __('Color') }}</label>
                    <div class="flex items-center gap-2 flex-wrap" id="edit-team-color-picker">
                        <input type="hidden" name="color" value="#075E54" />
                        <button type="button" data-color="#075E54"
                            class="w-7 h-7 rounded-full ring-2 ring-offset-2 ring-wa-deep"
                            style="background:#075E54"></button>
                        <button type="button" data-color="#13478A" class="w-7 h-7 rounded-full"
                            style="background:#13478A"></button>
                        <button type="button" data-color="#5B3D8A" class="w-7 h-7 rounded-full"
                            style="background:#5B3D8A"></button>
                        <button type="button" data-color="#7B5A14" class="w-7 h-7 rounded-full"
                            style="background:#7B5A14"></button>
                        <button type="button" data-color="#A1431F" class="w-7 h-7 rounded-full"
                            style="background:#A1431F"></button>
                        <button type="button" data-color="#0C7A65" class="w-7 h-7 rounded-full"
                            style="background:#0C7A65"></button>
                    </div>
                </div>
                <div>
                    <label
                        class="text-[11.5px] font-semibold text-ink-700 mb-1.5 block">{{ __('Auto-assignment strategy') }}</label>
                    <select name="assignment_strategy"
                        class="w-full px-3 py-2 border border-paper-200 rounded-lg bg-paper-0 text-[13px] focus:outline-none focus:border-wa-deep">
                        <option value="manual">{{ __('Manual — a manager picks who gets it') }}</option>
                        <option value="round_robin">{{ __('Round robin — spread evenly') }}</option>
                        <option value="least_loaded">{{ __('Least loaded — fewest open first') }}</option>
                        <option value="sticky">{{ __('Sticky — last agent who replied to this contact') }}</option>
                    </select>
                </div>
                <div>
                    <label class="text-[11.5px] font-semibold text-ink-700 mb-1.5 block">{{ __('Members') }}</label>
                    <div id="edit-team-members-list"
                        class="space-y-1.5 max-h-[180px] overflow-y-auto border border-paper-200 rounded-lg p-2 bg-paper-50/40">
                        <x-skeleton kind="row" :rows="3" />
                    </div>
                </div>
                {{-- Multi-device whitelist. Populated by JS only when the
 workspace has 2+ paired devices. Empty selection = handles
 every device (same as today; single-device teams keep working
 without any data migration). --}}
                <div id="edit-team-devices-wrap" class="hidden">
                    <label
                        class="text-[11.5px] font-semibold text-ink-700 mb-1.5 block">{{ __('Limit to devices') }}
                        <span class="font-normal text-ink-400">(blank = any)</span></label>
                    <div id="edit-team-devices-list"
                        class="space-y-1.5 max-h-[140px] overflow-y-auto border border-paper-200 rounded-lg p-2 bg-paper-50/40">
                    </div>
                </div>
                <div class="flex items-center gap-2 pt-1">
                    <button type="button" id="edit-team-delete"
                        class="px-3 py-1.5 rounded-full border border-accent-coral/30 hover:bg-accent-coral/10 text-accent-coral text-[12px]">{{ __('Delete team') }}</button>
                    <button type="button" data-close-edit-team
                        class="ml-auto px-3 py-1.5 rounded-full border border-paper-200 hover:bg-paper-50 text-[12px]">{{ __('Cancel') }}</button>
                    <button type="submit" id="edit-team-submit"
                        class="px-3 py-1.5 rounded-full bg-wa-deep text-paper-0 text-[12px] font-semibold hover:bg-wa-teal">{{ __('Save changes') }}</button>
                </div>
            </form>
        </div>
    </div>
    {{-- ======== Create team modal ================================ --}}
    <div id="create-team-modal" class="hidden fixed inset-0 z-50 grid place-items-center p-4">
        <div class="absolute inset-0 bg-ink-900/40" data-close-team></div>
        <div class="relative bg-paper-0 rounded-2xl w-full max-w-lg p-5 shadow-2xl">
            <div class="flex items-start justify-between mb-1">
                <div>
                    <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">
                        {{ __('Workspace') }}</div>
                    <h3 class="font-serif text-[22px] leading-tight">{{ __('Create a team') }}</h3>
                </div>
                <button data-close-team
                    class="w-8 h-8 rounded-full hover:bg-paper-50 grid place-items-center text-ink-700">×</button>
            </div>
            <p class="text-[12px] text-ink-500 mt-1">
                {{ __('Group your agents by department. Conversations can be auto-assigned by team via routing rules.') }}
            </p>
            <details class="mt-3 border border-paper-200 rounded-xl bg-paper-50 overflow-hidden">
                <summary
                    class="px-3 py-2 text-[11.5px] font-mono uppercase tracking-[0.14em] text-ink-700 cursor-pointer hover:bg-paper-100 select-none flex items-center gap-2">
                    <svg viewBox="0 0 16 16" class="w-3 h-3" fill="none" stroke="currentColor"
                        stroke-width="1.8">
                        <path d="M6 4l4 4-4 4" />
                    </svg>
                    How teams work
                </summary>
                <pre
                    class="px-4 py-3 text-[11.5px] leading-relaxed text-ink-700 font-mono whitespace-pre overflow-x-auto bg-paper-0 border-t border-paper-200">
You name a team (e.g. "Sales") and pick a color
 │
 ▼
Add agents to the team (from the Members panel)
 │
 ▼
Use the team in three ways:
 │
 ├── Manual assign → operator clicks "Assign" → picks the team
 │ → {{ brand_name() }} routes the chat to the least-busy member
 │
 ├── Routing rule → e.g. "if message contains 'refund' → assign to Sales"
 │ → first message of new chat routes automatically
 │
 └── Team analytics → /analytics shows performance per team
 (reply time, resolution, CSAT)
Team color shows up as the chip color in the inbox queue so you spot
chats from each team at a glance.</pre>
            </details>
            <form id="create-team-form" class="mt-4 space-y-3">
                <div>
                    <label
                        class="text-[11.5px] font-semibold text-ink-700 mb-1.5 block">{{ __('Team name') }}</label>
                    <input type="text" name="name" required maxlength="64"
                        class="w-full px-3 py-2 border border-paper-200 rounded-lg bg-paper-0 text-[13px] focus:outline-none focus:border-wa-deep"
                        placeholder="{{ __('e.g. Sales / Support / Billing') }}" />
                </div>
                <div>
                    <label class="text-[11.5px] font-semibold text-ink-700 mb-1.5 block">{{ __('Color') }}</label>
                    <div class="flex items-center gap-2 flex-wrap" id="team-color-picker">
                        <input type="hidden" name="color" value="#075E54" />
                        <button type="button" data-color="#075E54"
                            class="w-7 h-7 rounded-full ring-2 ring-offset-2 ring-wa-deep"
                            style="background:#075E54"></button>
                        <button type="button" data-color="#13478A" class="w-7 h-7 rounded-full"
                            style="background:#13478A"></button>
                        <button type="button" data-color="#5B3D8A" class="w-7 h-7 rounded-full"
                            style="background:#5B3D8A"></button>
                        <button type="button" data-color="#7B5A14" class="w-7 h-7 rounded-full"
                            style="background:#7B5A14"></button>
                        <button type="button" data-color="#A1431F" class="w-7 h-7 rounded-full"
                            style="background:#A1431F"></button>
                        <button type="button" data-color="#0C7A65" class="w-7 h-7 rounded-full"
                            style="background:#0C7A65"></button>
                    </div>
                </div>
                <div>
                    <label
                        class="text-[11.5px] font-semibold text-ink-700 mb-1.5 block">{{ __('Auto-assignment strategy') }}</label>
                    <select name="assignment_strategy"
                        class="w-full px-3 py-2 border border-paper-200 rounded-lg bg-paper-0 text-[13px] focus:outline-none focus:border-wa-deep">
                        <option value="manual">{{ __('Manual — a manager picks who gets it') }}</option>
                        <option value="round_robin">{{ __('Round robin — spread evenly') }}</option>
                        <option value="least_loaded">{{ __('Least loaded — fewest open first') }}</option>
                        <option value="sticky">{{ __('Sticky — last agent who replied to this contact') }}</option>
                    </select>
                </div>
                <div>
                    <label class="text-[11.5px] font-semibold text-ink-700 mb-1.5 block">{{ __('Members') }}</label>
                    <div id="team-members-list"
                        class="space-y-1.5 max-h-[180px] overflow-y-auto border border-paper-200 rounded-lg p-2 bg-paper-50/40">
                        <x-skeleton kind="row" :rows="3" />
                    </div>
                </div>
                <div id="create-team-devices-wrap" class="hidden">
                    <label
                        class="text-[11.5px] font-semibold text-ink-700 mb-1.5 block">{{ __('Limit to devices') }}
                        <span class="font-normal text-ink-400">(blank = any)</span></label>
                    <div id="create-team-devices-list"
                        class="space-y-1.5 max-h-[140px] overflow-y-auto border border-paper-200 rounded-lg p-2 bg-paper-50/40">
                    </div>
                </div>
                <div class="flex items-center gap-2 pt-1">
                    <button type="button" data-close-team
                        class="ml-auto px-3 py-1.5 rounded-full border border-paper-200 hover:bg-paper-50 text-[12px]">{{ __('Cancel') }}</button>
                    <button type="submit" id="create-team-submit"
                        class="px-3 py-1.5 rounded-full bg-wa-deep text-paper-0 text-[12px] font-semibold hover:bg-wa-teal">{{ __('Create team') }}</button>
                </div>
            </form>
        </div>
    </div>
    {{-- ======== Incoming WhatsApp call toast + slim panel ======= --}}
    {{-- Populated by the team-inbox poll bridge — every few seconds the
 JS hits /wa-calling/pending and renders any ringing call here.
 Accept routes the SDP offer to the WebRTC peer; Decline sends
 a reject to Meta. Hidden until a call is ringing. --}}
    <div id="wa-incoming-toast"
        class="hidden fixed top-4 right-4 z-[80] w-80 rounded-2xl shadow-soft border border-paper-200 bg-paper-0 overflow-hidden">
        <div class="px-4 py-3 bg-wa-deep text-paper-0 flex items-center gap-2">
            <span class="w-8 h-8 rounded-full bg-paper-0/15 grid place-items-center shrink-0">
                <svg viewBox="0 0 16 16" class="w-4 h-4" fill="none" stroke="currentColor"
                    stroke-width="1.8">
                    <path
                        d="M3.5 3.5a1.5 1.5 0 0 1 1.5-1.5h1.6l1.2 3-1.4 1a8.5 8.5 0 0 0 4.6 4.6l1-1.4 3 1.2v1.6a1.5 1.5 0 0 1-1.5 1.5C7 14 2 9 2 5z" />
                </svg>
            </span>
            <div class="flex-1 min-w-0">
                <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-paper-0/70">
                    {{ __('Incoming WhatsApp call') }}</div>
                <div id="wa-incoming-name" class="font-serif text-[15px] leading-tight truncate">—</div>
                <div id="wa-incoming-phone" class="font-mono text-[10.5px] text-paper-0/70 truncate">—</div>
            </div>
        </div>
        <div class="px-4 py-3 flex items-center gap-2">
            <button id="wa-incoming-accept"
                class="flex-1 px-3 py-1.5 rounded-full bg-wa-deep hover:bg-wa-teal text-paper-0 text-[12px] font-semibold">{{ __('Accept') }}</button>
            <button id="wa-incoming-decline"
                class="flex-1 px-3 py-1.5 rounded-full border border-accent-coral/50 hover:bg-accent-coral/10 text-accent-coral text-[12px] font-semibold">{{ __('Decline') }}</button>
        </div>
    </div>
    <div id="wa-call-panel"
        class="hidden fixed bottom-4 right-4 z-[80] w-72 rounded-2xl shadow-soft border border-paper-200 bg-paper-0">
        <div class="px-4 py-3 bg-wa-deep text-paper-0 rounded-t-2xl">
            <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-paper-0/70">{{ __('On call') }}</div>
            <div id="wa-call-name" class="font-serif text-[15px] leading-tight truncate">—</div>
            <div class="flex items-center gap-2 mt-1 text-[11.5px]">
                <span id="wa-call-timer" class="font-mono">0:00</span>
                <span id="wa-call-status" class="text-paper-0/70">{{ __('connecting…') }}</span>
            </div>
        </div>
        <div class="px-4 py-4 flex items-end justify-center gap-8">
            <div class="flex flex-col items-center gap-1.5">
                <button id="wa-call-mute"
                    class="w-11 h-11 rounded-full bg-paper-100 border border-paper-200 hover:bg-paper-200 grid place-items-center text-ink-700 transition-colors active:scale-95"
                    title="{{ __('Mute') }}">
                    <svg viewBox="0 0 16 16" class="w-[18px] h-[18px]" fill="none" stroke="currentColor"
                        stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="6" y="2" width="4" height="7.5" rx="2" />
                        <path d="M3.5 7.5a4.5 4.5 0 0 0 9 0M8 12v2" />
                    </svg>
                </button>
                <span id="wa-call-mute-label"
                    class="font-mono text-[9px] uppercase tracking-[0.12em] text-ink-400">{{ __('Mute') }}</span>
            </div>
            <div class="flex flex-col items-center gap-1.5">
                <button id="wa-call-hangup"
                    class="w-14 h-14 rounded-full bg-accent-coral hover:bg-accent-coral/90 text-paper-0 grid place-items-center shadow-md transition-transform active:scale-95"
                    title="{{ __('End call') }}">
                    <svg viewBox="0 0 24 24" class="w-7 h-7 block" fill="currentColor" aria-hidden="true">
                        <path
                            d="M12 9c-1.6 0-3.15.25-4.6.72v3.1c0 .39-.23.74-.56.9-.98.49-1.87 1.12-2.66 1.85-.18.18-.43.28-.7.28-.28 0-.53-.11-.71-.29L.29 13.08A.98.98 0 0 1 0 12.38c0-.28.11-.53.29-.71C3.34 8.78 7.46 7 12 7s8.66 1.78 11.71 4.67c.18.18.29.43.29.71 0 .28-.11.53-.29.71l-2.48 2.48c-.18.18-.43.29-.71.29-.27 0-.52-.11-.7-.28-.79-.74-1.69-1.36-2.67-1.85a.99.99 0 0 1-.56-.9v-3.1C15.15 9.25 13.6 9 12 9z" />
                    </svg>
                </button>
                <span
                    class="font-mono text-[9px] uppercase tracking-[0.12em] text-accent-coral">{{ __('End') }}</span>
            </div>
            {{-- Record. Calls are NOT recorded automatically — nothing is
                 written until this is pressed, and only audio from that
                 moment on is captured. Once armed it stays armed for the
                 rest of the call (there is no partial-file stop). --}}
            <div class="flex flex-col items-center gap-1.5">
                <button id="wa-call-record"
                    class="w-11 h-11 rounded-full bg-paper-100 border border-paper-200 hover:bg-paper-200 grid place-items-center text-ink-700 transition-colors active:scale-95 disabled:opacity-60 disabled:cursor-not-allowed"
                    title="{{ __('Start recording') }}">
                    <svg viewBox="0 0 16 16" class="w-[18px] h-[18px]" fill="none" stroke="currentColor"
                        stroke-width="1.6">
                        <circle cx="8" cy="8" r="5.5" />
                        <circle cx="8" cy="8" r="2.5" fill="currentColor" stroke="none" />
                    </svg>
                </button>
                <span id="wa-call-record-label"
                    class="font-mono text-[9px] uppercase tracking-[0.12em] text-ink-400">{{ __('Record') }}</span>
            </div>
        </div>
    </div>
    {{-- ======== AI Agents Management Modal ==================== --}}
    <div id="ai-agents-modal" class="hidden fixed inset-0 z-50 grid place-items-center p-4">
        <div class="absolute inset-0 bg-ink-900/40" data-close-agents></div>
        <div class="relative bg-paper-0 rounded-2xl w-full max-w-2xl p-5 shadow-2xl max-h-[90vh] flex flex-col">
            <div class="flex items-start justify-between mb-1 shrink-0">
                <div>
                    <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">
                        {{ __('Workspace') }}</div>
                    <h3 class="font-serif text-[22px] leading-tight">{{ __('AI Agents') }}</h3>
                </div>
                <button data-close-agents
                    class="w-8 h-8 rounded-full hover:bg-paper-50 grid place-items-center text-ink-700">×</button>
            </div>
            <p class="text-[12px] text-ink-500 mt-1 shrink-0">
                {{ __('Create AI agents that auto-respond to incoming WhatsApp messages on behalf of your team.') }}
            </p>
            <div class="flex items-center justify-between mt-3 mb-2 shrink-0">
                <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">{{ __('Your agents') }}
                </div>
                <div class="flex items-center gap-2">
                    <a href="{{ url('/settings?tab=aikeys') }}" target="_blank"
                        class="text-[10.5px] text-ink-500 hover:text-wa-deep font-mono underline underline-offset-2">{{ __('API keys ↗') }}</a>
                    <button id="ai-agent-add-btn"
                        class="px-3 py-1.5 rounded-full bg-wa-deep text-paper-0 text-[11.5px] font-semibold hover:bg-wa-teal inline-flex items-center gap-1.5">
                        <svg viewBox="0 0 16 16" class="w-3 h-3" fill="none" stroke="currentColor"
                            stroke-width="2">
                            <path d="M8 3v10M3 8h10" />
                        </svg>
                        Add agent
                    </button>
                </div>
            </div>
            <div id="ai-agents-list" class="flex-1 overflow-y-auto space-y-2 pr-1">
                <x-skeleton kind="card" :rows="3" />
            </div>
        </div>
    </div>
    {{-- ======== AI Agent Create / Edit Panel ================= --}}
    <div id="ai-agent-form-modal" class="hidden fixed inset-0 z-[60] grid place-items-center p-4">
        <div class="absolute inset-0 bg-ink-900/50" data-close-agent-form></div>
        <div class="relative bg-paper-0 rounded-2xl w-full max-w-lg p-5 shadow-2xl overflow-y-auto max-h-[90vh]">
            <div class="flex items-start justify-between mb-3">
                <div>
                    <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">{{ __('AI Agent') }}
                    </div>
                    <h3 class="font-serif text-[20px] leading-tight" id="agent-form-title">
                        {{ __('Create agent') }}</h3>
                </div>
                <button data-close-agent-form
                    class="w-8 h-8 rounded-full hover:bg-paper-50 grid place-items-center text-ink-700">×</button>
            </div>
            <details class="mb-4 border border-paper-200 rounded-xl bg-paper-50 overflow-hidden">
                <summary
                    class="px-3 py-2 text-[11.5px] font-mono uppercase tracking-[0.14em] text-ink-700 cursor-pointer hover:bg-paper-100 select-none flex items-center gap-2">
                    <svg viewBox="0 0 16 16" class="w-3 h-3" fill="none" stroke="currentColor"
                        stroke-width="1.8">
                        <path d="M6 4l4 4-4 4" />
                    </svg>
                    How the AI agent will work
                </summary>
                <pre
                    class="px-4 py-3 text-[11.5px] leading-relaxed text-ink-700 font-mono whitespace-pre overflow-x-auto bg-paper-0 border-t border-paper-200">
You give the agent a name, picture, and a "personality" prompt
 │
 ▼
Operator opens a chat → clicks "AI Agent" → picks this agent
 │
 ▼
Every inbound message in that chat → this agent replies
 │
 ├── Reads the last 20 messages for context
 ├── Sends to your chosen model (ChatGPT / Gemini / etc.)
 ├── Sends the reply back as WhatsApp message
 └── Rates its own reply 1-10 (shown as ★ badge for managers)
 │
 ▼
Want to take over? Just type a message yourself → agent stops automatically
Tips:
 • Tone setting changes how casual or formal the bot sounds
 • Lower temperature = same answers each time, higher = more creative
 • Be specific in the prompt — "Reply briefly. Never quote prices."</pre>
            </details>
            <form id="ai-agent-form" class="space-y-4">
                <input type="hidden" id="agent-form-id" value="">
                <div class="grid grid-cols-2 gap-3">
                    <div class="col-span-2">
                        <label
                            class="text-[11.5px] font-semibold text-ink-700 mb-1.5 block">{{ __('Agent name') }}</label>
                        <input type="text" name="name" required maxlength="191"
                            class="w-full px-3 py-2 border border-paper-200 rounded-lg bg-paper-0 text-[13px] focus:outline-none focus:border-wa-deep"
                            placeholder="{{ __('e.g. Sales Bot, Support AI') }}" />
                    </div>
                    <div>
                        <label
                            class="text-[11.5px] font-semibold text-ink-700 mb-1.5 block">{{ __('Provider') }}</label>
                        <select name="provider" id="agent-provider"
                            class="w-full px-3 py-2 border border-paper-200 rounded-lg bg-paper-0 text-[13px] focus:outline-none focus:border-wa-deep">
                            <option value="openai">{{ __('OpenAI (GPT)') }}</option>
                            <option value="anthropic">{{ __('Anthropic (Claude)') }}</option>
                            <option value="gemini">{{ __('Google (Gemini)') }}</option>
                        </select>
                    </div>
                    <div>
                        <label
                            class="text-[11.5px] font-semibold text-ink-700 mb-1.5 block">{{ __('Model') }}</label>
                        <select name="model" id="agent-model"
                            class="w-full px-3 py-2 border border-paper-200 rounded-lg bg-paper-0 text-[13px] focus:outline-none focus:border-wa-deep">
                            @foreach (\App\Http\Controllers\Admin\AdminAiKeyController::MODELS as $providerKey => $modelList)
                                <optgroup label="{{ ucfirst($providerKey) }}">
                                    @foreach ($modelList as $m)
                                        <option value="{{ $m }}">{{ $m }}</option>
                                    @endforeach
                                </optgroup>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label
                            class="text-[11.5px] font-semibold text-ink-700 mb-1.5 block">{{ __('Tone') }}</label>
                        <select name="tone"
                            class="w-full px-3 py-2 border border-paper-200 rounded-lg bg-paper-0 text-[13px] focus:outline-none focus:border-wa-deep">
                            <option value="professional">{{ __('Professional') }}</option>
                            <option value="friendly">{{ __('Friendly') }}</option>
                            <option value="concise">{{ __('Concise') }}</option>
                            <option value="empathetic">{{ __('Empathetic') }}</option>
                        </select>
                    </div>
                    <div>
                        <label
                            class="text-[11.5px] font-semibold text-ink-700 mb-1.5 block">{{ __('Avatar color') }}</label>
                        <div class="flex items-center gap-2 flex-wrap" id="agent-color-picker">
                            <input type="hidden" name="avatar_color" value="#6366f1" />
                            <button type="button" data-color="#6366f1"
                                class="w-7 h-7 rounded-full ring-2 ring-offset-2 ring-[#6366f1]"
                                style="background:#6366f1"></button>
                            <button type="button" data-color="#075E54" class="w-7 h-7 rounded-full"
                                style="background:#075E54"></button>
                            <button type="button" data-color="#0ea5e9" class="w-7 h-7 rounded-full"
                                style="background:#0ea5e9"></button>
                            <button type="button" data-color="#f59e0b" class="w-7 h-7 rounded-full"
                                style="background:#f59e0b"></button>
                            <button type="button" data-color="#ef4444" class="w-7 h-7 rounded-full"
                                style="background:#ef4444"></button>
                            <button type="button" data-color="#8b5cf6" class="w-7 h-7 rounded-full"
                                style="background:#8b5cf6"></button>
                        </div>
                    </div>
                </div>
                <div>
                    <label
                        class="text-[11.5px] font-semibold text-ink-700 mb-1.5 block">{{ __('System prompt') }}</label>
                    <textarea name="system_prompt" rows="4" maxlength="4000"
                        class="w-full px-3 py-2 border border-paper-200 rounded-lg bg-paper-0 text-[13px] focus:outline-none focus:border-wa-deep resize-y"
                        placeholder="{{ __("You are a helpful WhatsApp assistant for Acme Corp. You help customers with orders, returns, and product questions. Always be polite and offer to escalate to a human agent when you're unsure.") }}"></textarea>
                    <div class="text-[10.5px] text-ink-500 mt-1">
                        {{ __("Describe the agent's role, knowledge, and behaviour. Leave blank for a generic assistant.") }}
                    </div>
                </div>
                <div class="grid grid-cols-3 gap-3">
                    <div>
                        <label
                            class="text-[11.5px] font-semibold text-ink-700 mb-1.5 block">{{ __('Max tokens') }}</label>
                        <input type="number" name="max_tokens" min="64" max="4096" value="512"
                            class="w-full px-3 py-2 border border-paper-200 rounded-lg bg-paper-0 text-[13px] focus:outline-none focus:border-wa-deep" />
                    </div>
                    <div>
                        <label
                            class="text-[11.5px] font-semibold text-ink-700 mb-1.5 block">{{ __('Temperature (0–10)') }}</label>
                        <input type="number" name="temperature" min="0" max="10" value="7"
                            class="w-full px-3 py-2 border border-paper-200 rounded-lg bg-paper-0 text-[13px] focus:outline-none focus:border-wa-deep" />
                        <div class="text-[10px] text-ink-500 mt-1">0=focused 10=creative</div>
                    </div>
                    <div>
                        <label
                            class="text-[11.5px] font-semibold text-ink-700 mb-1.5 block">{{ __('Auto-respond') }}</label>
                        <label class="flex items-center gap-2 mt-2 cursor-pointer">
                            <input type="checkbox" name="auto_respond" checked
                                class="w-4 h-4 rounded border-paper-200 accent-wa-deep" />
                            <span class="text-[12px] text-ink-700">{{ __('Enabled') }}</span>
                        </label>
                    </div>
                </div>
                {{-- Saved replies as guidance — when on, the LLM is fed the
 workspace's top 15 saved replies (by used_count) as a "canned
 responses you can use" block. Keeps the AI on-brand without
 rewriting the system prompt for every FAQ. --}}
                <div class="border border-paper-200 rounded-xl p-3 bg-wa-mint/10">
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="checkbox" name="use_saved_replies"
                            class="w-4 h-4 rounded border-paper-200 accent-wa-deep" />
                        <span
                            class="text-[12px] text-ink-700 font-semibold">{{ __('Use saved replies as canned answers') }}</span>
                    </label>
                    <p class="text-[10.5px] text-ink-500 mt-1.5 ml-6">
                        The AI sees your workspace's <a href="#" data-open-quick-replies
                            class="text-wa-deep font-semibold hover:underline">{{ __('Quick Replies') }}</a> and
                        uses them verbatim (or paraphrased) when a customer's question matches.
                        Top 15 by usage are sent on every reply.
                    </p>
                </div>
                {{-- Human handoff — stop the AI from looping forever in a support
 chat. When any trigger fires, the agent unassigns itself,
 tags the convo "Needs human", bumps priority to high, and
 pings every workspace member's notification bell. --}}
                <div class="border border-paper-200 rounded-xl p-3 bg-accent-amber/5">
                    <div class="flex items-center gap-2 mb-2">
                        <span
                            class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">{{ __('Human handoff') }}</span>
                        <label class="ml-auto flex items-center gap-2 cursor-pointer">
                            <input type="checkbox" name="handoff_enabled" checked
                                class="w-4 h-4 rounded border-paper-200 accent-wa-deep" />
                            <span class="text-[11.5px] text-ink-700 font-semibold">{{ __('Enabled') }}</span>
                        </label>
                    </div>
                    <p class="text-[10.5px] text-ink-500 mb-3">
                        {{ __('Stops the AI from running indefinitely. When triggered, the conversation flips to "Needs human" and every team member gets a notification.') }}
                    </p>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label
                                class="text-[11.5px] font-semibold text-ink-700 mb-1 block">{{ __('Max replies per chat') }}</label>
                            <input type="number" name="max_replies_per_conversation" min="0"
                                max="200" value="10"
                                class="w-full px-3 py-1.5 border border-paper-200 rounded-lg bg-paper-0 text-[12.5px] focus:outline-none focus:border-wa-deep" />
                            <div class="text-[10px] text-ink-500 mt-1">0 = no limit</div>
                        </div>
                        <div>
                            <label
                                class="text-[11.5px] font-semibold text-ink-700 mb-1 block">{{ __('Low-score threshold') }}</label>
                            <div class="flex gap-2">
                                <input type="number" name="handoff_low_score_threshold" min="0"
                                    max="10" value="0"
                                    class="w-16 px-2 py-1.5 border border-paper-200 rounded-lg bg-paper-0 text-[12.5px] focus:outline-none focus:border-wa-deep" />
                                <span class="text-[10.5px] text-ink-500 self-center">≤ score, for</span>
                                <input type="number" name="handoff_low_score_window" min="1"
                                    max="10" value="3"
                                    class="w-14 px-2 py-1.5 border border-paper-200 rounded-lg bg-paper-0 text-[12.5px] focus:outline-none focus:border-wa-deep" />
                                <span class="text-[10.5px] text-ink-500 self-center">{{ __('replies') }}</span>
                            </div>
                            <div class="text-[10px] text-ink-500 mt-1">0 = off. 1–10 = hand off if last N self-scores
                                ≤ this</div>
                        </div>
                        <div class="col-span-2">
                            <label
                                class="text-[11.5px] font-semibold text-ink-700 mb-1 block">{{ __('Customer keywords') }}
                                <span class="font-normal text-ink-400">(comma-separated,
                                    case-insensitive)</span></label>
                            <input type="text" name="handoff_keywords_csv"
                                placeholder="{{ __('human, real person, speak to agent, manager') }}"
                                class="w-full px-3 py-1.5 border border-paper-200 rounded-lg bg-paper-0 text-[12.5px] focus:outline-none focus:border-wa-deep" />
                            <div class="text-[10px] text-ink-500 mt-1">
                                {{ __("If the customer's message contains any of these phrases, hand off immediately. Leave blank for sensible defaults.") }}
                            </div>
                        </div>
                    </div>
                </div>
                {{-- Voice-AI channels.
 Voice-note replies work on both Baileys and WABA — when
 this agent is assigned to a conversation and the customer
 sends a voice message, we transcribe it, run the same
 prompt the text path uses, synthesise a reply, and send
 it back as a WhatsApp PTT voice note.
 Voice-call answering is WABA-only and unlocks once the
 workspace's WABA number has calling enabled. --}}
                <div class="border border-paper-200 rounded-xl p-3 bg-wa-deep/5">
                    <div class="flex items-center gap-2 mb-2">
                        <span
                            class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">{{ __('Voice AI') }}</span>
                    </div>
                    <p class="text-[10.5px] text-ink-500 mb-3">
                        {{ __('When the customer sends a voice note, the agent transcribes it, generates a reply with the same system prompt, and sends a voice note back. Works on both Unofficial API and WABA.') }}
                    </p>
                    <label class="flex items-center gap-2 cursor-pointer mb-2">
                        <input type="checkbox" name="voice_note_enabled"
                            class="w-4 h-4 rounded border-paper-200 accent-wa-deep" />
                        <span class="text-[12px] text-ink-700 font-semibold">{{ __('Reply to voice notes') }}</span>
                    </label>
                    <label class="flex items-center gap-2 cursor-pointer mb-3"
                        title="{{ __('Answers inbound WABA calls with the AI voice agent. Requires WABA calling enabled on a workspace number.') }}">
                        <input type="checkbox" name="voice_call_enabled"
                            class="w-4 h-4 rounded border-paper-200 accent-wa-deep" />
                        <span class="text-[12px] text-ink-700">{{ __('Answer voice calls') }} <span
                                class="text-[10px] text-ink-500 font-mono">{{ __('WABA') }}</span></span>
                    </label>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label
                                class="text-[11.5px] font-semibold text-ink-700 mb-1 block">{{ __('Voice provider') }}</label>
                            <select name="voice_provider"
                                class="w-full px-3 py-1.5 border border-paper-200 rounded-lg bg-paper-0 text-[12.5px] focus:outline-none focus:border-wa-deep">
                                <option value="openai">{{ __('OpenAI TTS') }}</option>
                                <option value="elevenlabs">{{ __('ElevenLabs') }}</option>
                            </select>
                        </div>
                        <div>
                            <label
                                class="text-[11.5px] font-semibold text-ink-700 mb-1 block">{{ __('Voice id / name') }}</label>
                            <input type="text" name="voice_id" maxlength="96"
                                placeholder="{{ __('alloy · nova · or ElevenLabs voice id') }}"
                                class="w-full px-3 py-1.5 border border-paper-200 rounded-lg bg-paper-0 text-[12.5px] focus:outline-none focus:border-wa-deep" />
                            <div class="text-[10px] text-ink-500 mt-1">
                                {{ __('OpenAI: alloy, echo, fable, onyx, nova, shimmer, ash, sage, coral. ElevenLabs: paste the voice id from your library.') }}
                            </div>
                        </div>
                        <div>
                            <label
                                class="text-[11.5px] font-semibold text-ink-700 mb-1 block">{{ __('Voice language') }}</label>
                            <select name="voice_language"
                                class="w-full px-3 py-1.5 border border-paper-200 rounded-lg bg-paper-0 text-[12.5px] focus:outline-none focus:border-wa-deep">
                                <option value="en">{{ __('English') }}</option>
                                <option value="hi">{{ __('Hindi') }}</option>
                                <option value="es">{{ __('Spanish') }}</option>
                                <option value="fr">{{ __('French') }}</option>
                                <option value="de">{{ __('German') }}</option>
                                <option value="pt">{{ __('Portuguese') }}</option>
                                <option value="it">{{ __('Italian') }}</option>
                                <option value="ar">{{ __('Arabic') }}</option>
                                <option value="ja">{{ __('Japanese') }}</option>
                                <option value="ko">{{ __('Korean') }}</option>
                                <option value="zh">{{ __('Chinese') }}</option>
                                <option value="tr">{{ __('Turkish') }}</option>
                                <option value="id">{{ __('Indonesian') }}</option>
                                <option value="nl">{{ __('Dutch') }}</option>
                                <option value="ru">{{ __('Russian') }}</option>
                            </select>
                        </div>
                        <div>
                            <label
                                class="text-[11.5px] font-semibold text-ink-700 mb-1 block">{{ __('Daily cap') }}</label>
                            <input type="number" name="max_voice_notes_per_day" min="0" max="10000"
                                value="200"
                                class="w-full px-3 py-1.5 border border-paper-200 rounded-lg bg-paper-0 text-[12.5px] focus:outline-none focus:border-wa-deep" />
                            <div class="text-[10px] text-ink-500 mt-1">
                                {{ __('Voice replies per day safety cap. 0 = block all.') }}</div>
                        </div>
                    </div>
                </div>
                {{-- Multi-device scoping — only rendered into when the workspace
 has 2+ paired devices. The JS in openAgentForm() populates
 this slot with one checkbox per paired device. With no
 boxes ticked the agent handles every device (preserves
 single-device behavior). --}}
                <div id="agent-device-scope-wrap"
                    class="hidden border border-paper-200 rounded-xl p-3 bg-paper-50/60">
                    <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500 mb-2">
                        {{ __('Limit to devices') }}</div>
                    <p class="text-[11.5px] text-ink-500 mb-2 leading-snug">
                        {{ __('Tick one or more paired numbers to restrict this agent to conversations on those devices only. Leave all unticked to handle every device.') }}
                    </p>
                    <div id="agent-device-scope-list" class="grid grid-cols-1 gap-1.5"></div>
                </div>
                {{-- Test panel --}}
                <div class="border border-paper-200 rounded-xl p-3 bg-paper-50/60">
                    <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500 mb-2">
                        {{ __('Test this agent') }}</div>
                    <div class="flex gap-2">
                        <input type="text" id="agent-test-input"
                            class="flex-1 px-3 py-2 border border-paper-200 rounded-lg bg-paper-0 text-[12.5px] focus:outline-none focus:border-wa-deep"
                            placeholder="{{ __('Type a test message…') }}" />
                        <button type="button" id="agent-test-btn"
                            class="px-3 py-2 rounded-full bg-paper-200 hover:bg-paper-300 text-[12px] font-semibold shrink-0">{{ __('Send') }}</button>
                    </div>
                    <div id="agent-test-result"
                        class="hidden mt-2 p-2.5 bg-wa-bubble rounded-lg text-[12.5px] text-ink-900 whitespace-pre-wrap">
                    </div>
                </div>
                <div id="agent-form-error"
                    class="hidden rounded-lg p-3 bg-accent-coral/10 text-accent-coral text-[12px]"></div>
                <div class="flex items-center gap-2 pt-1">
                    <button type="button" data-close-agent-form
                        class="px-3 py-1.5 rounded-full border border-paper-200 hover:bg-paper-50 text-[12px]">{{ __('Cancel') }}</button>
                    <button type="submit" id="agent-form-submit"
                        class="ml-auto px-4 py-1.5 rounded-full bg-wa-deep text-paper-0 text-[12px] font-semibold hover:bg-wa-teal">{{ __('Save agent') }}</button>
                </div>
            </form>
        </div>
    </div>
    {{-- ======== API Keys Modal ================================ --}}
    <div id="ai-keys-modal" class="hidden fixed inset-0 z-50 grid place-items-center p-4">
        <div class="absolute inset-0 bg-ink-900/40" data-close-keys></div>
        <div class="relative bg-paper-0 rounded-2xl w-full max-w-lg p-5 shadow-2xl">
            <div class="flex items-start justify-between mb-1">
                <div>
                    <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">
                        {{ __('Workspace') }}</div>
                    <h3 class="font-serif text-[22px] leading-tight">{{ __('AI Provider Keys') }}</h3>
                </div>
                <button data-close-keys
                    class="w-8 h-8 rounded-full hover:bg-paper-50 grid place-items-center text-ink-700">×</button>
            </div>
            <p class="text-[12px] text-ink-500 mt-1">
                {{ __('Add your own API keys. Keys are encrypted at rest and never exposed in the UI. If a workspace key exists it takes priority over any server-level key.') }}
            </p>
            <div id="ai-keys-list" class="mt-4 space-y-2"></div>
            <div class="mt-4 border-t border-paper-200 pt-4">
                <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500 mb-2">
                    {{ __('Add / replace key') }}</div>
                <form id="ai-key-form" class="space-y-3">
                    <div>
                        <select name="provider"
                            class="w-full px-3 py-2 border border-paper-200 rounded-lg bg-paper-0 text-[13px] focus:outline-none focus:border-wa-deep">
                            <option value="openai">{{ __('OpenAI') }}</option>
                            <option value="anthropic">{{ __('Anthropic') }}</option>
                            <option value="gemini">{{ __('Google Gemini') }}</option>
                            <option value="elevenlabs">{{ __('ElevenLabs (voice TTS)') }}</option>
                        </select>
                    </div>
                    <div>
                        <input type="password" name="api_key" required minlength="8"
                            class="w-full px-3 py-2 border border-paper-200 rounded-lg bg-paper-0 text-[13px] focus:outline-none focus:border-wa-deep"
                            placeholder="{{ __('sk-…') }}" autocomplete="new-password" />
                    </div>
                    <div class="flex items-center gap-2">
                        <button type="submit"
                            class="px-3 py-1.5 rounded-full bg-wa-deep text-paper-0 text-[12px] font-semibold hover:bg-wa-teal">{{ __('Save key') }}</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    {{-- ======== Routing Rules Modal ========================== --}}
    <div id="routing-modal" class="hidden fixed inset-0 z-50 grid place-items-center p-4">
        <div class="absolute inset-0 bg-ink-900/40" data-close-routing></div>
        <div class="relative bg-paper-0 rounded-2xl w-full max-w-2xl p-5 shadow-2xl max-h-[90vh] flex flex-col">
            <div class="flex items-start justify-between mb-1 shrink-0">
                <div>
                    <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">
                        {{ __('Automation') }}</div>
                    <h3 class="font-serif text-[22px] leading-tight">{{ __('Routing Rules') }}</h3>
                </div>
                <button data-close-routing
                    class="w-8 h-8 rounded-full hover:bg-paper-50 grid place-items-center text-ink-700">×</button>
            </div>
            <p class="text-[12px] text-ink-500 mt-1 shrink-0">
                {{ __('Auto-assign incoming conversations to teams, agents, or set priorities based on message content and contact details.') }}
            </p>
            <details class="mt-3 border border-paper-200 rounded-xl bg-paper-50 overflow-hidden shrink-0">
                <summary
                    class="px-3 py-2 text-[11.5px] font-mono uppercase tracking-[0.14em] text-ink-700 cursor-pointer hover:bg-paper-100 select-none flex items-center gap-2">
                    <svg viewBox="0 0 16 16" class="w-3 h-3" fill="none" stroke="currentColor"
                        stroke-width="1.8">
                        <path d="M6 4l4 4-4 4" />
                    </svg>
                    How routing works
                </summary>
                <pre
                    class="px-4 py-3 text-[11.5px] leading-relaxed text-ink-700 font-mono whitespace-pre overflow-x-auto bg-paper-0 border-t border-paper-200">
Customer sends a message
 │
 ▼
{{ brand_name() }} walks your rules top to bottom (in this list's order)
 │
 ├── Rule 1 conditions match?
 │ │
 │ ├── YES → run actions
 │ │ ├── "Stop on match" ticked? → done, skip rest
 │ │ └── Not ticked? → keep checking remaining rules
 │ │
 │ └── NO → next rule
 │
 ├── Rule 2 → same check
 ├── Rule 3 → same check
 │ ...
 │
 └── No rule matched? → fallback rules try last (one of them might match)
Tip: drag-reorder rules to control priority. Most specific first, catch-all last.</pre>
            </details>
            <div class="flex items-center justify-between mt-3 mb-2 shrink-0 gap-2">
                <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">
                    {{ __('Rules (evaluated in order)') }}</div>
                <div class="flex items-center gap-2">
                    <button id="business-hours-btn"
                        class="px-3 py-1.5 rounded-full border border-paper-200 hover:bg-paper-50 text-[11.5px] font-medium inline-flex items-center gap-1.5">
                        <svg viewBox="0 0 16 16" class="w-3 h-3" fill="none" stroke="currentColor"
                            stroke-width="1.7">
                            <circle cx="8" cy="8" r="6" />
                            <path d="M8 5v3l2 1" />
                        </svg>
                        Business hours
                    </button>
                    <button id="routing-add-btn"
                        class="px-3 py-1.5 rounded-full bg-wa-deep text-paper-0 text-[11.5px] font-semibold hover:bg-wa-teal inline-flex items-center gap-1.5">
                        <svg viewBox="0 0 16 16" class="w-3 h-3" fill="none" stroke="currentColor"
                            stroke-width="2">
                            <path d="M8 3v10M3 8h10" />
                        </svg>
                        Add rule
                    </button>
                </div>
            </div>
            <div id="routing-rules-list" class="flex-1 overflow-y-auto space-y-2 pr-1">
                <x-skeleton kind="card" :rows="3" />
            </div>
        </div>
    </div>
    {{-- ======== Routing Rule Form Modal ====================== --}}
    <div id="routing-form-modal" class="hidden fixed inset-0 z-[60] grid place-items-center p-4">
        <div class="absolute inset-0 bg-ink-900/50" data-close-routing-form></div>
        <div class="relative bg-paper-0 rounded-2xl w-full max-w-xl p-5 shadow-2xl overflow-y-auto max-h-[90vh]">
            <div class="flex items-start justify-between mb-3">
                <div>
                    <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">
                        {{ __('Automation') }}</div>
                    <h3 class="font-serif text-[20px] leading-tight" id="routing-form-title">
                        {{ __('Create rule') }}</h3>
                </div>
                <button data-close-routing-form
                    class="w-8 h-8 rounded-full hover:bg-paper-50 grid place-items-center text-ink-700">×</button>
            </div>
            {{-- How this rule will work — inline tree shown to the user --}}
            <details class="mb-4 border border-paper-200 rounded-xl bg-paper-50 overflow-hidden">
                <summary
                    class="px-3 py-2 text-[11.5px] font-mono uppercase tracking-[0.14em] text-ink-700 cursor-pointer hover:bg-paper-100 select-none flex items-center gap-2">
                    <svg viewBox="0 0 16 16" class="w-3 h-3" fill="none" stroke="currentColor"
                        stroke-width="1.8">
                        <path d="M6 4l4 4-4 4" />
                    </svg>
                    How this rule will work
                </summary>
                <pre
                    class="px-4 py-3 text-[11.5px] leading-relaxed text-ink-700 font-mono whitespace-pre overflow-x-auto bg-paper-0 border-t border-paper-200">
Customer sends a message
 │
 ▼
{{ brand_name() }} checks ALL conditions match
 │
 ├── If they match → run every action below
 │ ├── Add tag → chip appears on the chat
 │ ├── Auto-reply → template sent (respects 12h cooldown)
 │ ├── Trigger flow → flow starts for this contact
 │ ├── Assign team → routed to least-busy member (new chats only)
 │ ├── Assign user → goes to this person (new chats only)
 │ └── Set priority → priority badge updated (new chats only)
 │
 └── If no match → next rule is checked
Stop on match → skip all rules below this one once it fires
Fallback rule → only fires if no other rule matched first
Active off → rule stays saved but never fires</pre>
            </details>
            <form id="routing-rule-form" class="space-y-4">
                <input type="hidden" id="routing-form-id" value="">
                <div>
                    <label
                        class="text-[11.5px] font-semibold text-ink-700 mb-1.5 block">{{ __('Rule name') }}</label>
                    <input type="text" name="name" required maxlength="128"
                        class="w-full px-3 py-2 border border-paper-200 rounded-lg bg-paper-0 text-[13px] focus:outline-none focus:border-wa-deep"
                        placeholder="{{ __('e.g. Route sales keywords to Sales team') }}" />
                </div>
                <div>
                    <div class="flex items-center justify-between mb-2">
                        <label class="text-[11.5px] font-semibold text-ink-700">{{ __('Conditions') }} <span
                                class="text-[10px] font-normal text-ink-500">(ALL must match)</span></label>
                        <button type="button" id="add-condition-btn"
                            class="text-[10.5px] text-wa-deep font-semibold hover:underline">+ Add condition</button>
                    </div>
                    <div id="rule-conditions" class="space-y-2"></div>
                </div>
                <div>
                    <div class="flex items-center justify-between mb-2">
                        <label class="text-[11.5px] font-semibold text-ink-700">{{ __('Actions') }}</label>
                        <button type="button" id="add-action-btn"
                            class="text-[10.5px] text-wa-deep font-semibold hover:underline">+ Add action</button>
                    </div>
                    <div id="rule-actions" class="space-y-2"></div>
                </div>
                <div class="flex items-center gap-6 flex-wrap">
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="checkbox" name="stop_on_match"
                            class="w-4 h-4 rounded border-paper-200 accent-wa-deep" />
                        <span class="text-[12px] text-ink-700">{{ __('Stop on match') }}</span>
                        <span class="text-[10px] text-ink-500">(skip remaining rules)</span>
                    </label>
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="checkbox" name="is_fallback"
                            class="w-4 h-4 rounded border-paper-200 accent-wa-deep" />
                        <span class="text-[12px] text-ink-700">{{ __('Fallback rule') }}</span>
                        <span class="text-[10px] text-ink-500">(only fires when no other rule matched)</span>
                    </label>
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="checkbox" name="is_active" checked
                            class="w-4 h-4 rounded border-paper-200 accent-wa-deep" />
                        <span class="text-[12px] text-ink-700">{{ __('Active') }}</span>
                    </label>
                </div>
                <div class="flex items-center gap-2 pt-1">
                    <button type="button" data-close-routing-form
                        class="px-3 py-1.5 rounded-full border border-paper-200 hover:bg-paper-50 text-[12px]">{{ __('Cancel') }}</button>
                    <button type="submit" id="routing-form-submit"
                        class="ml-auto px-4 py-1.5 rounded-full bg-wa-deep text-paper-0 text-[12px] font-semibold hover:bg-wa-teal">{{ __('Save rule') }}</button>
                </div>
            </form>
        </div>
    </div>
    {{-- ======== Business Hours Modal ========================= --}}
    <div id="business-hours-modal" class="hidden fixed inset-0 z-[60] grid place-items-center p-4">
        <div class="absolute inset-0 bg-ink-900/50" data-close-business-hours></div>
        <div class="relative bg-paper-0 rounded-2xl w-full max-w-xl p-5 shadow-2xl overflow-y-auto max-h-[90vh]">
            <div class="flex items-start justify-between mb-2">
                <div>
                    <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">
                        {{ __('Workspace · Routing') }}</div>
                    <h3 class="font-serif text-[22px] leading-tight">{{ __('Business hours') }}</h3>
                    <p class="text-[12px] text-ink-500 mt-1">
                        {{ __('Set when your team is available. Outside these hours an auto-reply can fire. Leave blank to stay 24/7.') }}
                    </p>
                </div>
                <button data-close-business-hours
                    class="w-8 h-8 rounded-full hover:bg-paper-50 grid place-items-center text-ink-700">×</button>
            </div>
            <details class="mt-3 border border-paper-200 rounded-xl bg-paper-50 overflow-hidden">
                <summary
                    class="px-3 py-2 text-[11.5px] font-mono uppercase tracking-[0.14em] text-ink-700 cursor-pointer hover:bg-paper-100 select-none flex items-center gap-2">
                    <svg viewBox="0 0 16 16" class="w-3 h-3" fill="none" stroke="currentColor"
                        stroke-width="1.8">
                        <path d="M6 4l4 4-4 4" />
                    </svg>
                    How business hours work
                </summary>
                <pre
                    class="px-4 py-3 text-[11.5px] leading-relaxed text-ink-700 font-mono whitespace-pre overflow-x-auto bg-paper-0 border-t border-paper-200">
Customer sends a message
 │
 ▼
{{ brand_name() }} reads the clock in your chosen timezone
 │
 ├── Is today enabled AND is "now" between from and to?
 │ │
 │ ├── YES → inside hours → no auto-reply fires
 │ │
 │ └── NO → outside hours
 │ │
 │ ├── Outside action = "Do nothing"
 │ │ → message just sits in inbox normally
 │ │
 │ └── Outside action = "Auto-reply with a template"
 │ │
 │ ▼
 │ Anti-spam guard checks:
 │ ├── Cooldown → same contact got one in last N min? skip
 │ └── Flood → contact sent > N msgs in 60s? skip
 │
 └── Template fires → customer gets "we're closed" message</pre>
            </details>
            <form id="business-hours-form" class="mt-3 space-y-3">
                <label class="block">
                    <span
                        class="text-[11px] font-mono uppercase tracking-[0.14em] text-ink-500 block mb-1">{{ __('Timezone') }}</span>
                    <select id="bh-timezone"
                        class="w-full px-3 py-2 border border-paper-200 rounded-lg bg-paper-0 text-[12.5px] focus:outline-none focus:border-wa-deep">
                        {{-- IANA zones populated by JS so we can lazy-fetch the list --}}
                    </select>
                    <span
                        class="text-[10px] text-ink-500 mt-1 block">{{ __('All times above + outside-hours checks are evaluated in this timezone. Saves to your workspace.') }}</span>
                </label>
                <div id="bh-days" class="space-y-2">
                    {{-- rows injected by JS --}}
                </div>
                <div class="border-t border-paper-200 pt-3 mt-3 space-y-2">
                    <label
                        class="text-[11.5px] font-semibold text-ink-700 block">{{ __('When a message arrives outside hours:') }}</label>
                    <select id="bh-outside-action"
                        class="w-full px-3 py-2 border border-paper-200 rounded-lg bg-paper-0 text-[12.5px] focus:outline-none focus:border-wa-deep">
                        <option value="none">{{ __('Do nothing (rules still run)') }}</option>
                        <option value="template">{{ __('Auto-reply with a template') }}</option>
                    </select>
                    <select id="bh-outside-template"
                        class="w-full px-3 py-2 border border-paper-200 rounded-lg bg-paper-0 text-[12.5px] focus:outline-none focus:border-wa-deep hidden">
                        <option value="">— pick template —</option>
                    </select>
                </div>
                <div class="border-t border-paper-200 pt-3 mt-3 space-y-3">
                    <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">
                        {{ __('Anti-spam controls') }}</div>
                    <div class="grid grid-cols-2 gap-3">
                        <label class="block">
                            <span
                                class="text-[11px] text-ink-700 block mb-1">{{ __('Cooldown per contact') }}</span>
                            <div class="flex items-center gap-1">
                                <input id="bh-cooldown" type="number" min="1" max="10080"
                                    step="1" value="720"
                                    class="flex-1 px-2 py-1.5 border border-paper-200 rounded-lg bg-paper-0 text-[12.5px] focus:outline-none focus:border-wa-deep">
                                <span class="text-[10.5px] text-ink-500">{{ __('minutes') }}</span>
                            </div>
                            <span
                                class="text-[10px] text-ink-500 mt-0.5 block">{{ __("Same contact won't get auto-replies repeatedly. Default 720 (12h).") }}</span>
                        </label>
                        <label class="block">
                            <span class="text-[11px] text-ink-700 block mb-1">{{ __('Spam threshold') }}</span>
                            <div class="flex items-center gap-1">
                                <input id="bh-spam-threshold" type="number" min="2" max="500"
                                    step="1" value="20"
                                    class="flex-1 px-2 py-1.5 border border-paper-200 rounded-lg bg-paper-0 text-[12.5px] focus:outline-none focus:border-wa-deep">
                                <span class="text-[10.5px] text-ink-500">{{ __('msgs / 60s') }}</span>
                            </div>
                            <span
                                class="text-[10px] text-ink-500 mt-0.5 block">{{ __('More than this in a minute → contact auto-flagged spam, all replies stop.') }}</span>
                        </label>
                    </div>
                </div>
                <div class="flex items-center gap-2 pt-2">
                    <button type="button" data-close-business-hours
                        class="px-3 py-1.5 rounded-full border border-paper-200 hover:bg-paper-50 text-[12px]">{{ __('Cancel') }}</button>
                    <button type="submit"
                        class="ml-auto px-4 py-1.5 rounded-full bg-wa-deep text-paper-0 text-[12px] font-semibold hover:bg-wa-teal">{{ __('Save hours') }}</button>
                </div>
            </form>
        </div>
    </div>
    {{-- ======== Team Performance Stats Modal ================= --}}
    <div id="stats-modal" class="hidden fixed inset-0 z-50 grid place-items-center p-4">
        <div class="absolute inset-0 bg-ink-900/40" data-close-stats></div>
        <div class="relative bg-paper-0 rounded-2xl w-full max-w-3xl p-5 shadow-2xl max-h-[90vh] flex flex-col">
            <div class="flex items-start justify-between mb-1 shrink-0">
                <div>
                    <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">
                        {{ __('Analytics') }}</div>
                    <h3 class="font-serif text-[22px] leading-tight">{{ __('Team Performance') }}</h3>
                </div>
                <div class="flex items-center gap-3">
                    <span id="stats-updated" class="font-mono text-[10px] text-ink-400"></span>
                    <button data-close-stats
                        class="w-8 h-8 rounded-full hover:bg-paper-50 grid place-items-center text-ink-700">×</button>
                </div>
            </div>
            <p class="text-[12px] text-ink-500 mt-1 shrink-0">
                {{ __('Real-time queue snapshot + agent performance for today. Refreshes every 30 seconds.') }}</p>
            <div class="grid grid-cols-3 lg:grid-cols-6 gap-2 mt-4 shrink-0">
                <div class="bg-paper-50 border border-paper-200 rounded-xl p-3 text-center">
                    <div id="stat-open" class="font-serif text-[24px] leading-none text-ink-900">—</div>
                    <div class="font-mono text-[9.5px] uppercase tracking-[0.14em] text-ink-500 mt-1">
                        {{ __('Open') }}</div>
                </div>
                <div class="bg-paper-50 border border-paper-200 rounded-xl p-3 text-center">
                    <div id="stat-awaiting" class="font-serif text-[24px] leading-none text-ink-900">—</div>
                    <div class="font-mono text-[9.5px] uppercase tracking-[0.14em] text-ink-500 mt-1">
                        {{ __('Awaiting reply') }}</div>
                </div>
                <div class="bg-paper-50 border border-paper-200 rounded-xl p-3 text-center">
                    <div id="stat-unassigned" class="font-serif text-[24px] leading-none text-ink-900">—</div>
                    <div class="font-mono text-[9.5px] uppercase tracking-[0.14em] text-ink-500 mt-1">
                        {{ __('Unassigned') }}</div>
                </div>
                <div class="bg-accent-coral/10 border border-accent-coral/20 rounded-xl p-3 text-center">
                    <div id="stat-sla" class="font-serif text-[24px] leading-none text-accent-coral">—</div>
                    <div class="font-mono text-[9.5px] uppercase tracking-[0.14em] text-accent-coral/80 mt-1">
                        {{ __('SLA Breached') }}</div>
                </div>
                <div class="bg-wa-mint/20 border border-wa-deep/20 rounded-xl p-3 text-center">
                    <div id="stat-resolved" class="font-serif text-[24px] leading-none text-wa-deep">—</div>
                    <div class="font-mono text-[9.5px] uppercase tracking-[0.14em] text-wa-deep/70 mt-1">
                        {{ __('Resolved Today') }}</div>
                </div>
                <div class="bg-paper-50 border border-paper-200 rounded-xl p-3 text-center">
                    <div id="stat-frt" class="font-serif text-[24px] leading-none text-ink-900">—</div>
                    <div class="font-mono text-[9.5px] uppercase tracking-[0.14em] text-ink-500 mt-1">
                        {{ __('Avg First Reply') }}</div>
                </div>
            </div>
            <div class="mt-4 flex-1 overflow-y-auto">
                <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500 mb-2">
                    {{ __('Agent performance · today') }}</div>
                <table class="w-full text-left">
                    <thead>
                        <tr class="bg-paper-50">
                            <th class="px-3 py-2 font-mono text-[10px] uppercase tracking-wider text-ink-500">
                                {{ __('Agent') }}</th>
                            <th class="px-3 py-2 font-mono text-[10px] uppercase tracking-wider text-ink-500">
                                {{ __('Status') }}</th>
                            <th
                                class="px-3 py-2 font-mono text-[10px] uppercase tracking-wider text-ink-500 text-center">
                                {{ __('Load') }}</th>
                            <th
                                class="px-3 py-2 font-mono text-[10px] uppercase tracking-wider text-ink-500 text-center">
                                {{ __('Replies') }}</th>
                            <th
                                class="px-3 py-2 font-mono text-[10px] uppercase tracking-wider text-ink-500 text-center">
                                {{ __('Resolved') }}</th>
                            <th class="px-3 py-2 font-mono text-[10px] uppercase tracking-wider text-ink-500">
                                {{ __('Last seen') }}</th>
                        </tr>
                    </thead>
                    <tbody id="stats-agent-tbody">
                        <x-skeleton kind="tableRow" :rows="4" />
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    {{-- ======== Quick Replies Management Modal ============== --}}
    <div id="quick-replies-modal" class="hidden fixed inset-0 z-50 grid place-items-center p-4">
        <div class="absolute inset-0 bg-ink-900/40" data-close-quick-replies></div>
        <div class="relative bg-paper-0 rounded-2xl w-full max-w-2xl p-5 shadow-2xl max-h-[90vh] flex flex-col">
            <div class="flex items-start justify-between mb-1 shrink-0">
                <div>
                    <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">
                        {{ __('Automation') }}</div>
                    <h3 class="font-serif text-[22px] leading-tight">{{ __('Quick Replies') }}</h3>
                </div>
                <button data-close-quick-replies
                    class="w-8 h-8 rounded-full hover:bg-paper-50 grid place-items-center text-ink-700">×</button>
            </div>
            <p class="text-[12px] text-ink-500 mt-1 shrink-0">
                {{ __('Predefined messages triggered by shortcuts. Type') }} <span
                    class="font-mono">/shortcut</span> in the composer or click ⚡ Shortcuts to insert.</p>
            <div class="flex items-center justify-between mt-3 mb-2 shrink-0">
                <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">
                    {{ __('Saved replies') }}</div>
                <button id="qr-add-btn"
                    class="px-3 py-1.5 rounded-full bg-wa-deep text-paper-0 text-[11.5px] font-semibold hover:bg-wa-teal inline-flex items-center gap-1.5">
                    <svg viewBox="0 0 16 16" class="w-3 h-3" fill="none" stroke="currentColor"
                        stroke-width="2">
                        <path d="M8 3v10M3 8h10" />
                    </svg>
                    Add reply
                </button>
            </div>
            <div id="saved-replies-list" class="flex-1 overflow-y-auto space-y-2 pr-1">
                <x-skeleton kind="card" :rows="3" />
            </div>
        </div>
    </div>
    {{-- ======== Saved Reply Create / Edit Form ============== --}}
    <div id="saved-reply-form-modal" class="hidden fixed inset-0 z-[60] grid place-items-center p-4">
        <div class="absolute inset-0 bg-ink-900/50" data-close-sr-form></div>
        <div class="relative bg-paper-0 rounded-2xl w-full max-w-lg p-5 shadow-2xl">
            <div class="flex items-start justify-between mb-3">
                <div>
                    <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">
                        {{ __('Quick Reply') }}</div>
                    <h3 class="font-serif text-[20px] leading-tight" id="sr-form-title">
                        {{ __('New quick reply') }}</h3>
                </div>
                <button data-close-sr-form
                    class="w-8 h-8 rounded-full hover:bg-paper-50 grid place-items-center text-ink-700">×</button>
            </div>
            <details class="mb-4 border border-paper-200 rounded-xl bg-paper-50 overflow-hidden">
                <summary
                    class="px-3 py-2 text-[11.5px] font-mono uppercase tracking-[0.14em] text-ink-700 cursor-pointer hover:bg-paper-100 select-none flex items-center gap-2">
                    <svg viewBox="0 0 16 16" class="w-3 h-3" fill="none" stroke="currentColor"
                        stroke-width="1.8">
                        <path d="M6 4l4 4-4 4" />
                    </svg>
                    How quick replies work
                </summary>
                <pre
                    class="px-4 py-3 text-[11.5px] leading-relaxed text-ink-700 font-mono whitespace-pre overflow-x-auto bg-paper-0 border-t border-paper-200">
You save a shortcut like /greeting → "Hi @{{ name }}, thanks for messaging us…"
 │
 ▼
In any chat composer, type "/" to see all your shortcuts
 │
 ├── Type /greeting → preview pops up → press Tab/Enter to insert
 ├── @{{ name }} gets replaced with the contact's real name
 └── Edit before sending if you need to personalise further
 │
 ▼
Press Send → message goes out normally
Why use it:
 • Stop retyping the same answers
 • Keep wording consistent across the whole team
 • Category groups related replies (e.g. all /support-* together)</pre>
            </details>
            <form id="saved-reply-form" class="space-y-3">
                <input type="hidden" id="sr-form-id" value="">
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label
                            class="text-[11.5px] font-semibold text-ink-700 mb-1.5 block">{{ __('Shortcut') }}</label>
                        <div class="relative">
                            <span
                                class="absolute left-3 top-1/2 -translate-y-1/2 font-mono text-[13px] text-ink-500">/</span>
                            <input type="text" name="shortcut" required maxlength="64"
                                class="w-full pl-6 pr-3 py-2 border border-paper-200 rounded-lg bg-paper-0 text-[13px] focus:outline-none focus:border-wa-deep font-mono"
                                placeholder="{{ __('greeting') }}" />
                        </div>
                    </div>
                    <div>
                        <label class="text-[11.5px] font-semibold text-ink-700 mb-1.5 block">{{ __('Category') }}
                            <span class="font-normal text-ink-400">(optional)</span></label>
                        <input type="text" name="category" maxlength="64"
                            class="w-full px-3 py-2 border border-paper-200 rounded-lg bg-paper-0 text-[13px] focus:outline-none focus:border-wa-deep"
                            placeholder="{{ __('e.g. greetings, support') }}" />
                    </div>
                </div>
                <div>
                    <label class="text-[11.5px] font-semibold text-ink-700 mb-1.5 block">{{ __('Title') }}</label>
                    <input type="text" name="title" required maxlength="128"
                        class="w-full px-3 py-2 border border-paper-200 rounded-lg bg-paper-0 text-[13px] focus:outline-none focus:border-wa-deep"
                        placeholder="{{ __('e.g. Welcome greeting') }}" />
                </div>
                <div>
                    <label
                        class="text-[11.5px] font-semibold text-ink-700 mb-1.5 block">{{ __('Message body') }}</label>
                    <textarea name="body" required maxlength="4000" rows="5"
                        class="w-full px-3 py-2 border border-paper-200 rounded-lg bg-paper-0 text-[13px] focus:outline-none focus:border-wa-deep resize-y"
                        placeholder="{{ __('Hi! Thanks for reaching out to us. How can we help you today?') }}"></textarea>
                </div>
                <label class="flex items-center gap-2 cursor-pointer">
                    <input type="checkbox" name="personal"
                        class="w-4 h-4 rounded border-paper-200 accent-wa-deep" />
                    <span class="text-[12px] text-ink-700">{{ __('Personal — only visible to me') }}</span>
                </label>
                <div class="flex items-center gap-2 pt-1">
                    <button type="button" data-close-sr-form
                        class="px-3 py-1.5 rounded-full border border-paper-200 hover:bg-paper-50 text-[12px]">{{ __('Cancel') }}</button>
                    <button type="submit" id="sr-form-submit"
                        class="ml-auto px-4 py-1.5 rounded-full bg-wa-deep text-paper-0 text-[12px] font-semibold hover:bg-wa-teal">{{ __('Save') }}</button>
                </div>
            </form>
        </div>
    </div>
    {{-- Localise the JS-rendered inbox UI (status pills, buttons, toasts, …). --}}
    @include('partials.js-i18n')
    @php
        $tlmWsId = (int) (auth()->user()->current_workspace_id ?? 0);
        $tlmAttrs = $tlmWsId ? app(\App\Services\SendAttributes::class)->catalog($tlmWsId) : [];
    @endphp
    {{-- Click-to-insert attribute catalog for the send-time mapping panel.
         The inbox drives that panel directly rather than through the
         template-live-mapping blade component, so it publishes the same
         global the component would; the panel module reads only this.
         Must sit INSIDE the layout — a @push after the closing tag lands
         after the stack has already rendered and silently ships nothing. --}}
    <script>
        window.__tlmAttributes = @json($tlmAttrs);
    </script>
    <script>
        (function() {
            document.addEventListener('keydown', function(e) {
                var t = e.target;
                if (!t || t.id !== 'composer-rich') return; // only the reply editor
                if (e.key !== 'Enter') return;
                if (e.shiftKey || e.ctrlKey || e.metaKey || e.altKey || e.isComposing)
            return; // newline / native
                e.preventDefault(); // no newline
                e.stopPropagation();
                var btn = document.querySelector('#send-btn');
                if (btn) btn.click(); // send
            }, true); // capture: run before the editor adds a newline
        })();
    </script>
</x-layouts.user>
