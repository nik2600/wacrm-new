<x-layouts.user :title="__('Team Performance')" nav-key="team-inbox" page="user-team-inbox-analytics-team">

    <main class="max-w-none mx-auto px-4 sm:px-6 lg:px-7 py-7">
        <div class="flex items-end justify-between flex-wrap gap-3 mb-5">
            <div>
                <div class="flex items-center gap-3 mb-2 text-[11px] font-mono uppercase tracking-[0.18em] text-ink-500">
                    <span>{{ __('Analytics · Team performance') }}</span>
                    <span class="w-1 h-1 rounded-full bg-ink-500/50"></span>
                    <span id="ta-refresh-label" class="flex items-center gap-1.5"><span
                            class="w-1.5 h-1.5 rounded-full bg-wa-green animate-pulse"></span>Live</span>
                </div>
                <h1 class="font-serif tracking-[-0.01em] text-[26px] sm:text-[32px] xl:text-[44px] leading-[1.02]">
                    How fast did <span class="italic text-wa-deep">{{ __('your team') }}</span> respond?
                </h1>
                <p class="text-[13px] text-ink-600 mt-2 max-w-2xl" id="ta-summary"><span
                        class="skeleton inline-block h-3 rounded w-72 align-middle"></span></p>
            </div>
            <div class="flex items-center gap-2">
                <a href="{{ url('/team-inbox/analytics/ai-agents') }}"
                    class="px-3 py-1.5 rounded-full border border-paper-200 hover:bg-paper-50 text-[12px] font-semibold inline-flex items-center gap-1.5">
                    <svg viewBox="0 0 16 16" class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="1.6">
                        <rect x="3" y="5" width="10" height="8" rx="2" />
                        <path d="M6 5V3.5A2 2 0 0 1 10 3.5V5" />
                    </svg>
                    AI Performance
                </a>
                <a href="{{ url('/team-inbox') }}"
                    class="px-3 py-1.5 rounded-full border border-paper-200 hover:bg-paper-50 text-[12px] font-semibold">{{ __('Back to inbox') }}</a>
            </div>
        </div>

        <div class="flex items-center gap-2 mb-4 flex-wrap">
            <div class="flex items-center gap-1" id="ta-range-tabs">
                <button data-range="today" class="ti-tab active">{{ __('Today') }}</button>
                <button data-range="week" class="ti-tab">{{ __('This week') }}</button>
                <button data-range="month" class="ti-tab">{{ __('This month') }}</button>
            </div>
            {{-- Multi-device filter — wrapper is hidden until JS confirms
 the workspace has 2+ paired devices. Single-device installs
 never see this control. --}}
            <div id="ta-device-wrap"
                class="hidden items-center gap-2 ml-auto border border-paper-200 rounded-full px-3 py-1.5 bg-paper-50">
                <svg viewBox="0 0 16 16" class="w-3.5 h-3.5 text-ink-500 shrink-0" fill="none" stroke="currentColor"
                    stroke-width="1.6">
                    <rect x="4.5" y="2" width="7" height="12" rx="1.5" />
                    <path d="M7 12.5h2" />
                </svg>
                <select id="ta-device-filter" class="bg-transparent text-[12px] outline-none cursor-pointer">
                    <option value="">{{ __('All devices') }}</option>
                </select>
            </div>
        </div>

        <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-6">
            <div class="bg-paper-0 border border-paper-200 rounded-xl p-4">
                <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500 mb-1">
                    {{ __('Replies sent') }}</div>
                <div id="ta-replies" class="font-serif text-[32px] leading-none text-ink-900">—</div>
            </div>
            <div class="bg-paper-0 border border-paper-200 rounded-xl p-4">
                <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500 mb-1">{{ __('Resolved') }}
                </div>
                <div id="ta-resolved" class="font-serif text-[32px] leading-none text-wa-deep">—</div>
            </div>
            <div class="bg-paper-0 border border-paper-200 rounded-xl p-4">
                <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500 mb-1">{{ __('Inbounds') }}
                </div>
                <div id="ta-inbounds" class="font-serif text-[32px] leading-none text-ink-900">—</div>
            </div>
            <div class="bg-paper-0 border border-paper-200 rounded-xl p-4">
                <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500 mb-1">
                    {{ __('Avg first reply') }}</div>
                <div id="ta-afr" class="font-serif text-[32px] leading-none text-ink-900">—</div>
            </div>
        </div>

        <div class="bg-paper-0 border border-paper-200 rounded-xl overflow-hidden">
            <div class="px-4 py-3 border-b border-paper-200 flex items-center justify-between">
                <h2 class="font-serif text-[18px]">{{ __('Per-agent breakdown') }}</h2>
                <span class="font-mono text-[10px] text-ink-500" id="ta-updated">{{ __('Updated —') }}</span>
            </div>
            <div class="overflow-x-auto">
            <table class="w-full text-left">
                <thead>
                    <tr class="bg-paper-50">
                        <th class="px-4 py-2 font-mono text-[10px] uppercase tracking-wider text-ink-500">
                            {{ __('Agent') }}</th>
                        <th class="px-4 py-2 font-mono text-[10px] uppercase tracking-wider text-ink-500">
                            {{ __('Status') }}</th>
                        <th class="px-4 py-2 font-mono text-[10px] uppercase tracking-wider text-ink-500 text-center">
                            {{ __('Load') }}</th>
                        <th class="px-4 py-2 font-mono text-[10px] uppercase tracking-wider text-ink-500 text-center">
                            {{ __('Replies') }}</th>
                        <th class="px-4 py-2 font-mono text-[10px] uppercase tracking-wider text-ink-500 text-center">
                            {{ __('Resolved') }}</th>
                        <th class="px-4 py-2 font-mono text-[10px] uppercase tracking-wider text-ink-500 text-center">
                            {{ __('Avg reply') }}</th>
                        <th class="px-4 py-2 font-mono text-[10px] uppercase tracking-wider text-ink-500">
                            {{ __('Last seen') }}</th>
                    </tr>
                </thead>
                <tbody id="ta-tbody">
                    @for ($i = 0; $i < 5; $i++)
                        <tr class="border-t border-paper-200">
                            <td class="px-4 py-2.5">
                                <div class="skeleton h-3 rounded w-2/3"></div>
                            </td>
                            <td class="px-4 py-2.5">
                                <div class="skeleton h-3 rounded w-14"></div>
                            </td>
                            <td class="px-4 py-2.5 text-center">
                                <div class="skeleton h-3 rounded w-6 mx-auto"></div>
                            </td>
                            <td class="px-4 py-2.5 text-center">
                                <div class="skeleton h-3 rounded w-6 mx-auto"></div>
                            </td>
                            <td class="px-4 py-2.5 text-center">
                                <div class="skeleton h-3 rounded w-6 mx-auto"></div>
                            </td>
                            <td class="px-4 py-2.5 text-center">
                                <div class="skeleton h-3 rounded w-10 mx-auto"></div>
                            </td>
                            <td class="px-4 py-2.5">
                                <div class="skeleton h-3 rounded w-20"></div>
                            </td>
                        </tr>
                    @endfor
                </tbody>
            </table>
            </div>
        </div>
    </main>

    <div id="toast"
        class="hidden fixed top-4 left-1/2 -translate-x-1/2 z-[80] px-4 py-2 rounded-full text-[12.5px] font-mono shadow-soft">
    </div>

    <script>
        (function() {
            const csrf = document.querySelector('meta[name=csrf-token]')?.content || '';
            let range = 'today';
            // Multi-device filter — null = all (single-device default), or a
            // device id string. Hydrated from URL ?device_id= for deep-linking.
            let deviceFilter = null;
            try {
                const u = new URLSearchParams(window.location.search).get('device_id');
                if (u) deviceFilter = u;
            } catch (_) {}

            function fmtTime(iso) {
                if (!iso) return '—';
                const d = new Date(iso);
                return d.toLocaleString(undefined, {
                    month: 'short',
                    day: 'numeric',
                    hour: '2-digit',
                    minute: '2-digit'
                });
            }

            function fmtMin(m) {
                if (m == null) return '—';
                return m < 60 ? `${m}m` : `${Math.floor(m/60)}h ${m % 60}m`;
            }

            function esc(s) {
                return String(s ?? '').replace(/[&<>"']/g, c => ({
                    '&': '&amp;',
                    '<': '&lt;',
                    '>': '&gt;',
                    '"': '&quot;',
                    "'": '&#39;'
                } [c]));
            }

            async function load() {
                try {
                    const qs = new URLSearchParams({
                        range
                    });
                    if (deviceFilter) qs.set('device_id', deviceFilter);
                    const res = await fetch(`/team-inbox/api/analytics/team?${qs}`, {
                        headers: {
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                            'X-CSRF-TOKEN': csrf
                        },
                        credentials: 'same-origin',
                    });
                    const data = await res.json();
                    if (!res.ok) throw new Error(data?.message || `HTTP ${res.status}`);

                    const t = data.totals || {};
                    document.getElementById('ta-replies').textContent = (t.replies ?? 0).toLocaleString();
                    document.getElementById('ta-resolved').textContent = (t.resolved ?? 0).toLocaleString();
                    document.getElementById('ta-inbounds').textContent = (t.inbounds ?? 0).toLocaleString();
                    document.getElementById('ta-afr').textContent = fmtMin(t.avg_first_response);
                    document.getElementById('ta-summary').textContent =
                        `${(t.replies ?? 0).toLocaleString()} replies · ${(t.resolved ?? 0).toLocaleString()} resolved · avg first reply ${fmtMin(t.avg_first_response)}.`;
                    document.getElementById('ta-updated').textContent = 'Updated ' + new Date()
                .toLocaleTimeString();

                    const tbody = document.getElementById('ta-tbody');
                    const agents = data.agents || [];
                    if (agents.length === 0) {
                        tbody.innerHTML =
                            `<tr><td colspan="7" class="px-4 py-6 text-center text-[12px] text-ink-500">No agent activity in this window.</td></tr>`;
                    } else {
                        const sc = {
                            online: 'text-wa-deep',
                            away: 'text-accent-amber',
                            busy: 'text-accent-coral',
                            offline: 'text-ink-400'
                        };
                        tbody.innerHTML = agents.map(a => `
 <tr class="border-t border-paper-200">
 <td class="px-4 py-2.5 text-[12.5px] font-medium">${esc(a.name)}</td>
 <td class="px-4 py-2.5"><span class="font-mono text-[11px] ${sc[a.status]||'text-ink-500'}">${esc(a.status || 'unknown')}</span></td>
 <td class="px-4 py-2.5 text-center font-mono text-[12px]">${a.current_load ?? 0}</td>
 <td class="px-4 py-2.5 text-center font-mono text-[12px]">${a.replies ?? 0}</td>
 <td class="px-4 py-2.5 text-center font-mono text-[12px]">${a.resolved ?? 0}</td>
 <td class="px-4 py-2.5 text-center font-mono text-[12px]">${fmtMin(a.avg_response_min)}</td>
 <td class="px-4 py-2.5 font-mono text-[10.5px] text-ink-500">${a.last_seen_at ? fmtTime(a.last_seen_at) : '—'}</td>
 </tr>
 `).join('');
                    }
                } catch (e) {
                    console.error(e);
                }
            }

            document.querySelectorAll('#ta-range-tabs [data-range]').forEach(b => {
                b.addEventListener('click', () => {
                    document.querySelectorAll('#ta-range-tabs .ti-tab').forEach(x => x.classList.remove(
                        'active'));
                    b.classList.add('active');
                    range = b.dataset.range;
                    load();
                });
            });

            // Hydrate the device dropdown from the inbox bootstrap call so we
            // don't need a dedicated endpoint here. The wrapper stays hidden
            // when the workspace has 0–1 devices — single-device installs see
            // exactly what they always did.
            (async () => {
                try {
                    const r = await fetch('/team-inbox/api/bootstrap', {
                        headers: {
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                            'X-CSRF-TOKEN': csrf
                        },
                        credentials: 'same-origin',
                    });
                    if (!r.ok) return;
                    const boot = await r.json();
                    const devices = boot.devices || [];
                    if (devices.length <= 1) return;
                    const wrap = document.getElementById('ta-device-wrap');
                    const sel = document.getElementById('ta-device-filter');
                    wrap.classList.remove('hidden');
                    wrap.classList.add('inline-flex');
                    sel.innerHTML = '<option value="">All devices</option>' +
                        devices.map(d => `<option value="${d.id}">${esc(d.label)} · ${esc(d.phone)}</option>`)
                        .join('');
                    if (deviceFilter) sel.value = deviceFilter;
                    sel.addEventListener('change', () => {
                        deviceFilter = sel.value || null;
                        const url = new URL(window.location);
                        if (deviceFilter) url.searchParams.set('device_id', deviceFilter);
                        else url.searchParams.delete('device_id');
                        window.history.replaceState({}, '', url);
                        load();
                    });
                } catch (_) {
                    /* device-filter is optional UX, never block load() on it */ }
            })();

            load();
            setInterval(load, 30000); // auto-refresh every 30s
        })();
    </script>

</x-layouts.user>
