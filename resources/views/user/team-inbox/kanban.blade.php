<x-layouts.user :title="__('Kanban — Unified Inbox')" nav-key="team-inbox" page="user-team-inbox-kanban">

    {{--
 Kanban board — new "in flight" design.

 Same data + same drag-drop semantics as before; the difference is purely
 visual: bigger editorial header, stats card, prettier cards with chips
 + first-name pills + 🤖 AI badge, drop-target shadow, etc.

 We still hit the same APIs:
 - /team-inbox/api/bootstrap → members, teams, ai_agents
 - /team-inbox/api/queue?status=all&tab=all&per_page=200
 - /team-inbox/api/conversations/{id}/{resolve|reopen|snooze|priority|assign|unassign|assign-agent}
--}}

    <style>
        :root {
            --kb-accent: var(--color-wa-deep);
        }

        .kb-tabular {
            font-variant-numeric: tabular-nums;
        }

        /* Column / card.
 Columns grow to fit their cards — the PAGE scrolls (Trello-like),
 not the column body. This was the "page scroll not working" fix:
 the previous max-height: calc(100vh - 280px) + per-column overflow
 was trapping all scroll inside each column, so when a column wrapped
 to a second row or a column got tall, you couldn't reach it from
 the page. Now scroll behaves like a normal long page. */
        .kb-col {
            display: flex;
            flex-direction: column;
            background: #FFFFFF;
            border: 1px solid #E5DFD0;
            border-radius: 16px;
            min-height: 220px;
            box-shadow: 0 1px 0 rgba(11, 31, 28, 0.04);
            transition: box-shadow .15s ease, border-color .15s ease, background .15s ease;
        }

        .kb-col.dragover {
            box-shadow: inset 0 0 0 2px var(--kb-accent), 0 1px 0 rgba(11, 31, 28, 0.04);
            background: #FAF7F0;
        }

        .kb-col-body {
            flex: 1 1 auto;
            padding: 8px 10px 10px;
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .kb-card {
            background: #FFFFFF;
            border: 1px solid #E5DFD0;
            border-radius: 12px;
            padding: 10px 11px 9px;
            cursor: grab;
            transition: border-color .12s ease, transform .12s ease, box-shadow .12s ease;
            user-select: none;
        }

        .kb-card:hover {
            border-color: var(--kb-accent);
            box-shadow: 0 1px 0 rgba(11, 31, 28, 0.04), 0 2px 8px -2px rgba(7, 94, 84, 0.16);
        }

        .kb-card.dragging {
            opacity: 0.5;
            cursor: grabbing;
            transform: rotate(-1deg) scale(0.99);
            box-shadow: 0 14px 32px -10px rgba(7, 94, 84, 0.32);
        }

        .kb-card:active {
            cursor: grabbing;
        }

        .kb-card .preview {
            display: -webkit-box;
            -webkit-line-clamp: 1;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .kb-avatar {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            font-weight: 600;
        }

        /* Pills */
        .kb-chip {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 2px 7px;
            border-radius: 999px;
            font-size: 10.5px;
            font-weight: 500;
            line-height: 1.4;
            white-space: nowrap;
        }

        .kb-chip-mint {
            background: rgba(123, 181, 154, 0.18);
            color: #2C5C46;
        }

        .kb-chip-urgent {
            background: rgba(232, 122, 93, 0.18);
            color: #B14026;
        }

        .kb-chip-high {
            background: rgba(217, 164, 65, 0.22);
            color: #8B5A14;
        }

        .kb-chip-low {
            background: rgba(11, 31, 28, 0.06);
            color: #4A5A57;
        }

        .kb-chip-sla {
            background: rgba(232, 122, 93, 0.18);
            color: #B14026;
            font-family: 'JetBrains Mono', monospace;
            font-size: 9.5px;
            padding: 1px 6px;
        }

        .kb-chip-time {
            color: #6B807C;
            font-family: 'JetBrains Mono', monospace;
            font-size: 10.5px;
        }

        .kb-chip-unread {
            background: var(--color-wa-green);
            color: #fff;
            font-family: 'JetBrains Mono', monospace;
            font-size: 9.5px;
            font-weight: 600;
            padding: 1px 6px;
            min-width: 18px;
            text-align: center;
        }

        /* Group-by pill row */
        .kb-gb-pill {
            padding: 6px 14px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 500;
            color: #4A5A57;
            cursor: pointer;
            transition: all .12s ease;
            border: none;
            background: transparent;
        }

        .kb-gb-pill:hover {
            background: #FAF7F0;
        }

        .kb-gb-pill.active {
            background: var(--kb-accent);
            color: #fff;
        }

        /* Board grid */
        .kb-board {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(290px, 1fr));
            gap: 12px;
        }

        /* Column header */
        .kb-col-bar {
            height: 3px;
            border-radius: 99px;
        }

        /* Refresh spin */
        @keyframes kb-spin {
            from {
                transform: rotate(0)
            }

            to {
                transform: rotate(360deg)
            }
        }

        .kb-spin {
            animation: kb-spin .8s linear;
        }

        /* Toast */
        .kb-toast {
            position: fixed;
            top: 18px;
            left: 50%;
            transform: translateX(-50%) translateY(-30px);
            opacity: 0;
            pointer-events: none;
            transition: transform .25s ease, opacity .25s ease;
            z-index: 60;
        }

        .kb-toast.show {
            transform: translateX(-50%) translateY(0);
            opacity: 1;
        }

        /* Skeleton */
        .kb-skel {
            background: linear-gradient(90deg, #F0EBDC 0%, #FAF7F0 50%, #F0EBDC 100%);
            background-size: 200% 100%;
            animation: kb-shimmer 1.4s infinite;
            border-radius: 8px;
        }

        @keyframes kb-shimmer {
            0% {
                background-position: 200% 0;
            }

            100% {
                background-position: -200% 0;
            }
        }
    </style>

    {{-- ========== PAGE HEADER ========== --}}
    <section class="max-w-none mx-auto px-4 sm:px-6 lg:px-7 pt-6 pb-3">
        <div class="flex items-end justify-between gap-6 flex-wrap">
            <div class="min-w-0">
                <div class="flex items-center gap-3 mb-2 text-[11px] font-mono uppercase tracking-[0.18em] text-ink-500">
                    <span>{{ __('Unified Inbox') }}</span>
                    <span class="w-1 h-1 rounded-full bg-ink-500/50"></span>
                    <span>{{ __('Kanban') }}</span>
                    <span class="w-1 h-1 rounded-full bg-ink-500/50"></span>
                    <span class="flex items-center gap-1.5"><span
                            class="w-1.5 h-1.5 rounded-full bg-wa-green animate-pulse"></span>Live · auto-refresh
                        10s</span>
                </div>
                <h1 class="font-serif text-[28px] sm:text-[36px] xl:text-[52px] leading-[1.05]">
                    Kanban board. <span class="italic text-wa-deep" id="kb-hl-count">— conversations</span> in flight.
                </h1>
                <p id="kb-hint" class="text-[13px] text-ink-600 mt-2 max-w-2xl">
                    {{ __('Drag a card between columns to change its status (Active / Snoozed / Resolved). Click to open the conversation.') }}
                </p>
            </div>
            <div class="flex items-center gap-2 shrink-0">
                <a href="{{ url('/team-inbox') }}"
                    class="px-4 py-2 border border-paper-200 rounded-full bg-paper-0 text-[12px] font-medium hover:bg-paper-50 flex items-center gap-2">
                    <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor"
                        stroke-width="1.5">
                        <path d="M3 4h10M3 8h10M3 12h10" />
                        <circle cx="1.5" cy="4" r=".6" fill="currentColor" />
                        <circle cx="1.5" cy="8" r=".6" fill="currentColor" />
                        <circle cx="1.5" cy="12" r=".6" fill="currentColor" />
                    </svg>
                    List view
                </a>
                <button id="kb-refresh-btn"
                    class="px-3 py-2 border border-paper-200 rounded-full bg-paper-0 text-[12px] font-medium hover:bg-paper-50 flex items-center gap-2"
                    title="{{ __('Refresh') }}">
                    <svg id="kb-refresh-icon" viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none"
                        stroke="currentColor" stroke-width="1.5">
                        <path d="M14 8a6 6 0 1 1-1.76-4.24" />
                        <path d="M14 2v3.5h-3.5" />
                    </svg>
                    <span class="font-mono text-[11px] text-ink-500" id="kb-refresh-time">{{ __('just now') }}</span>
                </button>
            </div>
        </div>
    </section>

    {{-- ========== TOOLBAR ROW ========== --}}
    <section class="max-w-none mx-auto px-4 sm:px-6 lg:px-7 pb-4">
        <div
            class="bg-paper-0 border border-paper-200 rounded-2xl shadow-card px-4 py-3 flex items-center gap-4 flex-wrap">
            <span
                class="font-mono text-[10px] uppercase tracking-widest text-ink-500 shrink-0">{{ __('Group by') }}</span>
            <div class="flex items-center gap-1 border border-paper-200 rounded-full p-1" id="kb-group">
                <button data-gb="status" class="kb-gb-pill active">{{ __('Status') }}</button>
                <button data-gb="agent" class="kb-gb-pill">{{ __('Agent') }}</button>
                <button data-gb="team" class="kb-gb-pill">{{ __('Team') }}</button>
                <button data-gb="priority" class="kb-gb-pill">{{ __('Priority') }}</button>
                <button data-gb="ai" class="kb-gb-pill">{{ __('AI Agent') }}</button>
            </div>

            {{-- Device filter — hidden when the workspace has 0–1 devices.
 JS toggles visibility and populates options after bootstrap
 via renderDeviceFilter(). --}}
            <div id="kb-device-wrap"
                class="hidden flex items-center gap-2 border border-paper-200 rounded-full px-3 py-1.5 bg-paper-50">
                <svg viewBox="0 0 16 16" class="w-3.5 h-3.5 text-ink-500 shrink-0" fill="none" stroke="currentColor"
                    stroke-width="1.6">
                    <rect x="4.5" y="2" width="7" height="12" rx="1.5" />
                    <path d="M7 12.5h2" />
                </svg>
                <select id="kb-device-filter" class="bg-transparent text-[12px] outline-none cursor-pointer">
                    <option value="">{{ __('All devices') }}</option>
                </select>
            </div>

            <div class="flex-1 min-w-0"></div>

            <div class="flex items-center gap-2 border border-paper-200 rounded-full px-3 py-1.5 bg-paper-50 w-full max-w-[280px]">
                <svg viewBox="0 0 16 16" class="w-3.5 h-3.5 text-ink-500 shrink-0" fill="none" stroke="currentColor"
                    stroke-width="1.5">
                    <circle cx="7" cy="7" r="5" />
                    <path d="m11 11 3 3" />
                </svg>
                <input id="kb-search" type="text" placeholder="{{ __('Search name, preview, agent…') }}"
                    class="bg-transparent text-[12px] flex-1 outline-none placeholder:text-ink-500 min-w-0" />
                <kbd
                    class="font-mono text-[10px] text-ink-500 px-1.5 py-0.5 rounded border border-paper-200 bg-paper-0 shrink-0">⌘K</kbd>
            </div>

            <div class="flex items-center gap-4 pl-4 border-l border-paper-200">
                <div class="text-right">
                    <div class="font-mono text-[10px] uppercase tracking-widest text-ink-500">{{ __('Total') }}</div>
                    <div class="font-serif text-[20px] leading-none kb-tabular" id="kb-stat-total">—</div>
                </div>
                <div class="text-right">
                    <div class="font-mono text-[10px] uppercase tracking-widest text-ink-500">{{ __('SLA breach') }}
                    </div>
                    <div class="font-serif text-[20px] leading-none kb-tabular text-accent-coral" id="kb-stat-sla">—
                    </div>
                </div>
                <div class="text-right">
                    <div class="font-mono text-[10px] uppercase tracking-widest text-ink-500">{{ __('Unassigned') }}
                    </div>
                    <div class="font-serif text-[20px] leading-none kb-tabular" id="kb-stat-unassigned">—</div>
                </div>
            </div>
        </div>
    </section>

    {{-- ========== BOARD ========== --}}
    <section class="max-w-none mx-auto px-4 sm:px-6 lg:px-7 pb-10">
        <div id="kb-board" class="kb-board">
            {{-- Loading skeleton — 3 columns, 3 cards each. --}}
            @for ($i = 0; $i < 3; $i++)
                <div class="kb-col">
                    <div class="px-3 pt-3 pb-2 border-b border-paper-200 flex items-center gap-2">
                        <div class="kb-skel w-6 h-1 rounded-full"></div>
                        <div class="kb-skel h-3 w-20"></div>
                        <div class="kb-skel h-3 w-5 ml-auto"></div>
                    </div>
                    <div class="kb-col-body">
                        @for ($j = 0; $j < 3; $j++)
                            <div class="bg-paper-0 border border-paper-200 rounded-xl p-2.5">
                                <div class="flex items-center gap-2 mb-2">
                                    <div class="kb-skel w-8 h-8 rounded-full"></div>
                                    <div class="flex-1 space-y-1">
                                        <div class="kb-skel h-3 w-2/3"></div>
                                        <div class="kb-skel h-2 w-1/2"></div>
                                    </div>
                                </div>
                                <div class="kb-skel h-2.5 w-4/5"></div>
                            </div>
                        @endfor
                    </div>
                </div>
            @endfor
        </div>
        <div id="kb-empty" class="hidden text-center py-20 text-ink-500 text-[13px]">
            {{ __('Nothing to show yet.') }}</div>
    </section>

    {{-- ========== TOAST ========== --}}
    <div id="kb-toast" class="kb-toast"></div>

    <script>
        (function() {
            const csrf = document.querySelector('meta[name=csrf-token]')?.content || '';

            // ── State ──────────────────────────────────────────────────────────
            const state = {
                groupBy: 'status',
                search: '',
                conversations: [],
                members: [],
                teams: [],
                aiAgents: [],
                // Multi-device feature: devices list is hydrated from bootstrap.
                // deviceFilter null = all devices (single-device default), or a
                // device id string (URL param survives reloads).
                devices: [],
                deviceFilter: null,
                lastLoadedAt: null,
            };
            try {
                const u = new URLSearchParams(window.location.search).get('device_id');
                if (u) state.deviceFilter = u;
            } catch (_) {}

            const HINTS = {
                status: 'Drag a card between columns to change its status (Active / Snoozed / Resolved). Click to open the conversation.',
                agent: 'Drag a card onto an operator to reassign. Drag to "Unassigned" to clear the assignment.',
                team: 'Drag a card onto a team — assignment-strategy decides which operator within that team gets it.',
                priority: 'Drag a card between priority columns to bump it Urgent / High / Normal / Low.',
                ai: 'Drag a card onto an AI Agent to make it auto-respond. Drag to "No AI" to remove the AI handler.',
            };

            // ── Helpers ────────────────────────────────────────────────────────
            const $ = (s) => document.querySelector(s);
            const $$ = (s) => document.querySelectorAll(s);
            const esc = (v) => String(v ?? '').replace(/[&<>"']/g, c => ({
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#39;'
            } [c]));

            // Deterministic avatar gradient from a name — same name = same colors.
            const PALETTE = [
                ['#128C7E', '#075E54'],
                ['#E87A5D', '#D9A441'],
                ['#7BB59A', '#128C7E'],
                ['#8D6BA0', '#5E3F77'],
                ['#3B82A4', '#075E54'],
                ['#D9A441', '#7BB59A'],
                ['#E87A5D', '#8D6BA0'],
                ['#075E54', '#128C7E'],
                ['#3B82A4', '#8D6BA0'],
            ];

            function avatarFor(name) {
                const s = String(name || '?');
                let hash = 0;
                for (let i = 0; i < s.length; i++) hash = (hash * 31 + s.charCodeAt(i)) | 0;
                const p = PALETTE[Math.abs(hash) % PALETTE.length];
                return `linear-gradient(135deg, ${p[0]}, ${p[1]})`;
            }

            function initialsFor(name) {
                const s = String(name || '?').trim();
                if (!s) return '?';
                const parts = s.split(/\s+/).slice(0, 2);
                return parts.map(p => (p[0] || '').toUpperCase()).join('') || '?';
            }

            function firstNameFor(name) {
                return String(name || '').split(/\s+/)[0] || '';
            }

            function fmtTime(iso) {
                if (!iso) return '';
                const d = new Date(iso);
                if (isNaN(d.getTime())) return '';
                const now = new Date();
                if (d.toDateString() === now.toDateString()) {
                    return d.getHours().toString().padStart(2, '0') + ':' + d.getMinutes().toString().padStart(2, '0');
                }
                return d.toLocaleDateString(undefined, {
                    month: 'short',
                    day: 'numeric'
                });
            }

            function fmtAgo(date) {
                if (!date) return 'just now';
                const sec = Math.floor((Date.now() - date.getTime()) / 1000);
                if (sec < 5) return 'just now';
                if (sec < 60) return sec + 's ago';
                if (sec < 3600) return Math.floor(sec / 60) + 'm ago';
                return Math.floor(sec / 3600) + 'h ago';
            }

            async function api(path, opts = {}) {
                const res = await fetch(path, {
                    credentials: 'same-origin',
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': csrf,
                        ...(opts.body ? {
                            'Content-Type': 'application/json'
                        } : {}),
                    },
                    ...opts,
                    body: opts.body ? JSON.stringify(opts.body) : undefined,
                });
                const data = await res.json().catch(() => ({}));
                if (!res.ok) throw new Error(data?.message || data?.error || ('HTTP ' + res.status));
                return data;
            }

            // ── Columns ────────────────────────────────────────────────────────
            function getColumns() {
                switch (state.groupBy) {
                    case 'status':
                        return [{
                                key: 'open',
                                label: 'Active',
                                color: 'var(--color-wa-deep)'
                            },
                            {
                                key: 'snoozed',
                                label: 'Snoozed',
                                color: '#D9A441'
                            },
                            {
                                key: 'resolved',
                                label: 'Resolved',
                                color: 'var(--color-wa-green)'
                            },
                        ];
                    case 'priority':
                        return [{
                                key: 'urgent',
                                label: 'Urgent',
                                color: '#E87A5D'
                            },
                            {
                                key: 'high',
                                label: 'High',
                                color: '#D9A441'
                            },
                            {
                                key: 'normal',
                                label: 'Normal',
                                color: '#3B82A4'
                            },
                            {
                                key: 'low',
                                label: 'Low',
                                color: '#6B807C'
                            },
                        ];
                    case 'agent': {
                        const cols = [{
                            key: 'unassigned',
                            label: 'Unassigned',
                            color: '#6B807C'
                        }];
                        (state.members || []).forEach(m =>
                            cols.push({
                                key: 'u:' + m.id,
                                label: m.name,
                                color: 'var(--color-wa-deep)',
                                user_id: m.id
                            }));
                        return cols;
                    }
                    case 'team': {
                        const cols = [{
                            key: 'unassigned',
                            label: 'No team',
                            color: '#6B807C'
                        }];
                        (state.teams || []).forEach(t =>
                            cols.push({
                                key: 't:' + t.id,
                                label: t.name,
                                color: t.color || 'var(--color-wa-deep)',
                                team_id: t.id
                            }));
                        return cols;
                    }
                    case 'ai': {
                        const cols = [{
                            key: 'none',
                            label: 'No AI',
                            color: '#6B807C'
                        }];
                        (state.aiAgents || []).forEach(a =>
                            cols.push({
                                key: 'a:' + a.id,
                                label: a.name,
                                color: a.avatar_color || '#8D6BA0',
                                agent_id: a.id
                            }));
                        return cols;
                    }
                }
                return [];
            }

            function bucketKey(c) {
                switch (state.groupBy) {
                    case 'status': {
                        const s = c.inbox_status || 'open';
                        return (s === 'pending' || s === 'open') ? 'open' : s;
                    }
                    case 'priority':
                        return c.priority || 'normal';
                    case 'agent':
                        return c.assignee_user_id ? 'u:' + c.assignee_user_id : 'unassigned';
                    case 'team':
                        return c.assignee_team_id ? 't:' + c.assignee_team_id : 'unassigned';
                    case 'ai':
                        return c.assignee_agent_id ? 'a:' + c.assignee_agent_id : 'none';
                }
                return 'open';
            }

            // ── Card render ────────────────────────────────────────────────────
            function renderCard(c) {
                const name = c.title || '—';
                const initials = initialsFor(name);
                const avatar = avatarFor(name);
                // Masked display copy only — never render the raw customer number.
                const phone = c.contact_phone_display || '';

                let prioChip = '';
                if (c.priority === 'urgent') prioChip = '<span class="kb-chip kb-chip-urgent">● Urgent</span>';
                else if (c.priority === 'high') prioChip = '<span class="kb-chip kb-chip-high">▲ High</span>';
                else if (c.priority === 'low') prioChip = '<span class="kb-chip kb-chip-low">Low</span>';

                const assigneeChip = c.assignee_name ?
                    `<span class="kb-chip kb-chip-mint">${esc(firstNameFor(c.assignee_name))}</span>` :
                    '';
                const teamColor = esc(c.team_color || 'var(--color-wa-deep)');
                const teamChip = c.team_name ?
                    `<span class="kb-chip" style="background:color-mix(in srgb, ${teamColor} 10%, transparent);color:${teamColor}">${esc(c.team_name)}</span>` :
                    '';
                const aiChip = c.agent_name ?
                    `<span class="kb-chip" style="background:${esc(c.agent_color || '#8D6BA0')}22;color:${esc(c.agent_color || '#8D6BA0')}">🤖 ${esc(c.agent_name)}</span>` :
                    '';
                const slaChip = c.sla_breached ? '<span class="kb-chip kb-chip-sla">SLA</span>' : '';
                // Per-card device chip — only renders when the workspace is in
                // multi-device mode AND the conversation has a stamped device.
                // Tooltip carries the full phone number for at-a-glance context.
                const deviceChip = (c.device_label && (state.devices || []).length > 1) ?
                    `<span class="kb-chip inline-flex items-center gap-1" style="background:#0B4F3F12;color:#0B4F3F" title="${esc(c.device_phone || '')}"><svg viewBox="0 0 16 16" class="w-2.5 h-2.5" fill="none" stroke="currentColor" stroke-width="1.7"><rect x="4.5" y="2" width="7" height="12" rx="1.5"/><path d="M7 12.5h2"/></svg>${esc(c.device_label)}</span>` :
                    '';
                const unreadChip = (c.unread_count > 0) ? `<span class="kb-chip-unread">${c.unread_count}</span>` : '';

                return `
 <div class="kb-card" draggable="true" data-id="${c.id}">
 <div class="flex items-center gap-2.5 mb-1.5">
 <span class="kb-avatar w-8 h-8 rounded-full text-[10.5px]" style="background:${avatar}">${esc(initials)}</span>
 <div class="flex-1 min-w-0">
 <div class="text-[13px] font-semibold text-ink-900 truncate leading-tight">${esc(name)}</div>
 ${phone ? `<div class="font-mono text-[10px] text-ink-500 truncate leading-tight">${esc(phone)}</div>` : ''}
 </div>
 ${unreadChip}
 </div>
 <div class="preview text-[12px] text-ink-600 leading-snug mb-2">${esc(c.preview || '')}</div>
 <div class="flex items-center gap-1 flex-wrap">
 ${assigneeChip}${teamChip}${aiChip}${deviceChip}${prioChip}${slaChip}
 <span class="flex-1"></span>
 <span class="kb-chip-time">${esc(fmtTime(c.last_message_at))}</span>
 </div>
 </div>
 `;
            }

            function renderColumn(col, cards) {
                const cardsHtml = cards.length ?
                    cards.map(renderCard).join('') :
                    '<div class="text-[12px] text-ink-500 italic px-2 py-6 text-center">Drop here</div>';
                return `
 <div class="kb-col" data-col="${esc(col.key)}" data-user-id="${esc(col.user_id || '')}" data-team-id="${esc(col.team_id || '')}" data-agent-id="${esc(col.agent_id || '')}">
 <div class="px-3 pt-3 pb-2 border-b border-paper-200 flex items-center gap-2">
 <span class="kb-col-bar w-6" style="background:${esc(col.color)}"></span>
 <span class="text-[13px] font-semibold text-ink-900 truncate flex-1">${esc(col.label)}</span>
 <span class="font-mono text-[10.5px] text-ink-500 kb-tabular px-1.5 py-0.5 rounded bg-paper-50">${cards.length}</span>
 </div>
 <div class="kb-col-body">${cardsHtml}</div>
 </div>
 `;
            }

            function render() {
                // Hint line + active pill
                $('#kb-hint').textContent = HINTS[state.groupBy];
                $$('#kb-group .kb-gb-pill').forEach(btn => btn.classList.toggle('active', btn.dataset.gb === state
                    .groupBy));

                // Filter
                const q = state.search.trim().toLowerCase();
                const filtered = (state.conversations || []).filter(c => {
                    if (!q) return true;
                    return (c.title || '').toLowerCase().includes(q) ||
                        (c.preview || '').toLowerCase().includes(q) ||
                        (c.assignee_name || '').toLowerCase().includes(q);
                });

                // Bucket
                const cols = getColumns();
                const buckets = new Map(cols.map(c => [c.key, []]));
                filtered.forEach(c => {
                    const k = bucketKey(c);
                    if (buckets.has(k)) buckets.get(k).push(c);
                });

                // Render board
                const board = $('#kb-board');
                const empty = $('#kb-empty');
                if (!cols.length) {
                    board.classList.add('hidden');
                    empty.classList.remove('hidden');
                } else {
                    board.classList.remove('hidden');
                    empty.classList.add('hidden');
                    board.innerHTML = cols.map(col => renderColumn(col, buckets.get(col.key) || [])).join('');
                }

                // Wire cards + columns
                $$('.kb-card').forEach(c => {
                    c.addEventListener('dragstart', onDragStart);
                    c.addEventListener('dragend', onDragEnd);
                    c.addEventListener('click', onCardClick);
                });
                $$('.kb-col').forEach(c => {
                    c.addEventListener('dragover', onDragOver);
                    c.addEventListener('dragleave', onDragLeave);
                    c.addEventListener('drop', onDrop);
                });

                // Stats
                $('#kb-stat-total').textContent = filtered.length;
                $('#kb-stat-sla').textContent = filtered.filter(c => c.sla_breached).length;
                $('#kb-stat-unassigned').textContent = filtered.filter(c => !c.assignee_user_id).length;
                $('#kb-hl-count').textContent = filtered.length + ' conversation' + (filtered.length === 1 ? '' : 's');
            }

            // ── Drag & drop ────────────────────────────────────────────────────
            let dragId = null;

            function onDragStart(e) {
                dragId = e.currentTarget.dataset.id;
                e.currentTarget.classList.add('dragging');
                e.dataTransfer.effectAllowed = 'move';
                e.dataTransfer.setData('text/plain', dragId);
            }

            function onDragEnd(e) {
                e.currentTarget.classList.remove('dragging');
                $$('.kb-col.dragover').forEach(c => c.classList.remove('dragover'));
                dragId = null;
            }

            function onDragOver(e) {
                e.preventDefault();
                e.dataTransfer.dropEffect = 'move';
                e.currentTarget.classList.add('dragover');
            }

            function onDragLeave(e) {
                if (!e.currentTarget.contains(e.relatedTarget)) e.currentTarget.classList.remove('dragover');
            }
            async function onDrop(e) {
                e.preventDefault();
                e.currentTarget.classList.remove('dragover');
                const id = e.dataTransfer.getData('text/plain') || dragId;
                const dst = e.currentTarget.dataset.col;
                if (!id || !dst) return;
                const conv = (state.conversations || []).find(c => String(c.id) === String(id));
                if (!conv) return;
                if (bucketKey(conv) === dst) return; // same column
                const meta = {
                    user_id: e.currentTarget.dataset.userId || null,
                    team_id: e.currentTarget.dataset.teamId || null,
                    agent_id: e.currentTarget.dataset.agentId || null,
                };
                await moveCard(parseInt(id, 10), dst, meta);
            }

            function onCardClick(e) {
                if (e.target.closest('.dragging')) return;
                const id = e.currentTarget.dataset.id;
                window.location.href = (window.appUrl ? window.appUrl('/team-inbox') : '/team-inbox') + '#c=' + id;
            }

            async function moveCard(id, dstKey, meta) {
                try {
                    if (state.groupBy === 'status') {
                        if (dstKey === 'resolved') {
                            await api('/team-inbox/api/conversations/' + id + '/resolve', {
                                method: 'POST'
                            });
                        } else if (dstKey === 'open') {
                            await api('/team-inbox/api/conversations/' + id + '/reopen', {
                                method: 'POST'
                            });
                        } else if (dstKey === 'snoozed') {
                            const until = new Date(Date.now() + 3600_000).toISOString();
                            await api('/team-inbox/api/conversations/' + id + '/snooze', {
                                method: 'POST',
                                body: {
                                    until
                                }
                            });
                        }
                    } else if (state.groupBy === 'priority') {
                        await api('/team-inbox/api/conversations/' + id + '/priority', {
                            method: 'POST',
                            body: {
                                priority: dstKey
                            }
                        });
                    } else if (state.groupBy === 'agent') {
                        if (dstKey === 'unassigned') {
                            await api('/team-inbox/api/conversations/' + id + '/unassign', {
                                method: 'POST'
                            });
                        } else {
                            await api('/team-inbox/api/conversations/' + id + '/assign', {
                                method: 'POST',
                                body: {
                                    user_id: parseInt(meta.user_id, 10),
                                    strategy: 'manual'
                                }
                            });
                        }
                    } else if (state.groupBy === 'team') {
                        if (dstKey === 'unassigned') {
                            await api('/team-inbox/api/conversations/' + id + '/unassign', {
                                method: 'POST'
                            });
                        } else {
                            await api('/team-inbox/api/conversations/' + id + '/assign', {
                                method: 'POST',
                                body: {
                                    team_id: parseInt(meta.team_id, 10),
                                    strategy: 'least_loaded'
                                }
                            });
                        }
                    } else if (state.groupBy === 'ai') {
                        await api('/team-inbox/api/conversations/' + id + '/assign-agent', {
                            method: 'POST',
                            body: {
                                agent_id: meta.agent_id ? parseInt(meta.agent_id, 10) : null
                            }
                        });
                    }
                    toast('Moved.');
                    await load();
                } catch (e) {
                    toast('Move failed: ' + e.message, 'error');
                }
            }

            // ── Toast ──────────────────────────────────────────────────────────
            let toastTimer;

            function toast(msg, kind) {
                const t = $('#kb-toast');
                t.innerHTML = `<span class="inline-flex items-center gap-2 px-4 py-2 rounded-full ${kind === 'error' ? 'bg-accent-coral text-paper-0' : 'bg-ink-900 text-paper-0'} shadow-soft text-[12px] font-medium">
 <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 8l3 3 7-7"/></svg>${esc(msg)}
 </span>`;
                t.classList.add('show');
                clearTimeout(toastTimer);
                toastTimer = setTimeout(() => t.classList.remove('show'), 2500);
            }

            // ── Data load ──────────────────────────────────────────────────────
            async function load() {
                try {
                    if (!state.members.length && !state.teams.length && !state.aiAgents.length) {
                        const boot = await api('/team-inbox/api/bootstrap');
                        state.members = boot.members || [];
                        state.teams = boot.teams || [];
                        state.aiAgents = boot.ai_agents || [];
                        state.devices = boot.devices || [];
                        renderDeviceFilter();
                    }
                    const params = new URLSearchParams({
                        status: 'all',
                        tab: 'all',
                        per_page: '200'
                    });
                    if (state.deviceFilter) params.set('device_id', state.deviceFilter);
                    const data = await api('/team-inbox/api/queue?' + params.toString());
                    state.conversations = data.items || [];
                    state.lastLoadedAt = new Date();
                    render();
                    updateRefreshAgo();
                } catch (e) {
                    toast('Load failed: ' + e.message, 'error');
                }
            }

            function updateRefreshAgo() {
                const el = $('#kb-refresh-time');
                if (el && state.lastLoadedAt) el.textContent = fmtAgo(state.lastLoadedAt);
            }

            // Populate the device filter <select> and reveal its wrapper when
            // the workspace has 2+ paired devices. Single-device installs see
            // exactly what they saw before — the wrapper stays hidden.
            function renderDeviceFilter() {
                const wrap = $('#kb-device-wrap');
                const sel = $('#kb-device-filter');
                if (!wrap || !sel) return;
                if ((state.devices || []).length <= 1) {
                    wrap.classList.add('hidden');
                    return;
                }
                wrap.classList.remove('hidden');
                wrap.classList.add('flex');
                // Preserve "All devices" + repopulate per-device options.
                sel.innerHTML = '<option value="">All devices</option>' +
                    state.devices.map(d => `<option value="${d.id}">${esc(d.label)} · ${esc(d.phone)}</option>`).join(
                        '');
                if (state.deviceFilter) sel.value = state.deviceFilter;
            }

            // ── Wire controls ──────────────────────────────────────────────────
            $$('#kb-group .kb-gb-pill').forEach(btn => {
                btn.addEventListener('click', () => {
                    state.groupBy = btn.dataset.gb;
                    render();
                });
            });
            $('#kb-search')?.addEventListener('input', (e) => {
                state.search = e.target.value || '';
                render();
            });
            $('#kb-device-filter')?.addEventListener('change', (e) => {
                state.deviceFilter = e.target.value || null;
                // Mirror to URL so deep-links + page refresh keep the filter.
                const url = new URL(window.location);
                if (state.deviceFilter) url.searchParams.set('device_id', state.deviceFilter);
                else url.searchParams.delete('device_id');
                window.history.replaceState({}, '', url);
                load();
            });
            $('#kb-refresh-btn')?.addEventListener('click', () => {
                const ic = $('#kb-refresh-icon');
                ic?.classList.add('kb-spin');
                setTimeout(() => ic?.classList.remove('kb-spin'), 800);
                load();
            });

            // Keyboard Ctrl/⌘+K → focus search
            document.addEventListener('keydown', (e) => {
                if ((e.metaKey || e.ctrlKey) && e.key.toLowerCase() === 'k') {
                    e.preventDefault();
                    $('#kb-search')?.focus();
                }
            });

            // ── Init ───────────────────────────────────────────────────────────
            load();
            setInterval(load, 10000);
            setInterval(updateRefreshAgo, 5000);
        })();
    </script>

</x-layouts.user>
