<x-layouts.user :title="__('AI Agent Performance')" nav-key="team-inbox" page="user-team-inbox-analytics-ai">

    <main class="max-w-none mx-auto px-4 sm:px-6 lg:px-7 py-7">
        <div class="flex items-end justify-between flex-wrap gap-3 mb-5">
            <div>
                <div class="flex items-center gap-3 mb-2 text-[11px] font-mono uppercase tracking-[0.18em] text-ink-500">
                    <span>{{ __('Analytics · AI agents') }}</span>
                    <span class="w-1 h-1 rounded-full bg-ink-500/50"></span>
                    <span class="flex items-center gap-1.5"><span
                            class="w-1.5 h-1.5 rounded-full bg-wa-green animate-pulse"></span>Live</span>
                </div>
                <h1 class="font-serif tracking-[-0.01em] text-[26px] sm:text-[32px] xl:text-[44px] leading-[1.02]">
                    How well are <span class="italic text-wa-deep">{{ __('your AI agents') }}</span> doing?
                </h1>
                <p class="text-[13px] text-ink-600 mt-2 max-w-2xl" id="aa-summary">
                    Each AI scores its own response 1–10 after every reply. <span
                        class="italic">{{ __('Visible only to your team — never to the customer.') }}</span>
                </p>
            </div>
            <div class="flex items-center gap-2">
                <a href="{{ url('/team-inbox/analytics/team') }}"
                    class="px-3 py-1.5 rounded-full border border-paper-200 hover:bg-paper-50 text-[12px] font-semibold inline-flex items-center gap-1.5">
                    <svg viewBox="0 0 16 16" class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="1.6">
                        <path d="M2 12l3-4 3 2 3-6 3 3" />
                        <path d="M2 12h12" />
                    </svg>
                    Team Performance
                </a>
                <a href="{{ url('/team-inbox') }}"
                    class="px-3 py-1.5 rounded-full border border-paper-200 hover:bg-paper-50 text-[12px] font-semibold">{{ __('Back to inbox') }}</a>
            </div>
        </div>

        <div class="flex items-center gap-2 mb-4 flex-wrap">
            <div class="flex items-center gap-1" id="aa-range-tabs">
                <button data-range="today" class="ti-tab active">{{ __('Today') }}</button>
                <button data-range="week" class="ti-tab">{{ __('This week') }}</button>
                <button data-range="month" class="ti-tab">{{ __('This month') }}</button>
            </div>
            <div id="aa-device-wrap"
                class="hidden items-center gap-2 ml-auto border border-paper-200 rounded-full px-3 py-1.5 bg-paper-50">
                <svg viewBox="0 0 16 16" class="w-3.5 h-3.5 text-ink-500 shrink-0" fill="none" stroke="currentColor"
                    stroke-width="1.6">
                    <rect x="4.5" y="2" width="7" height="12" rx="1.5" />
                    <path d="M7 12.5h2" />
                </svg>
                <select id="aa-device-filter" class="bg-transparent text-[12px] outline-none cursor-pointer">
                    <option value="">{{ __('All devices') }}</option>
                </select>
            </div>
        </div>

        <div id="aa-cards" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3 mb-6">
            @for ($i = 0; $i < 3; $i++)
                <div class="bg-paper-0 border border-paper-200 rounded-xl p-4 space-y-3">
                    <div class="flex items-start gap-3">
                        <div class="skeleton w-10 h-10 rounded-full shrink-0"></div>
                        <div class="flex-1 space-y-1.5">
                            <div class="skeleton h-3.5 rounded w-1/2"></div>
                            <div class="skeleton h-2.5 rounded w-3/4"></div>
                        </div>
                    </div>
                    <div class="grid grid-cols-3 gap-2">
                        <div class="skeleton h-8 rounded"></div>
                        <div class="skeleton h-8 rounded"></div>
                        <div class="skeleton h-8 rounded"></div>
                    </div>
                    <div class="skeleton h-2 rounded"></div>
                </div>
            @endfor
        </div>

        <div class="bg-paper-0 border border-paper-200 rounded-xl overflow-hidden">
            <div class="px-4 py-3 border-b border-paper-200 flex items-center justify-between">
                <h2 class="font-serif text-[18px]">{{ __('Recent self-rated replies') }}</h2>
                <span class="font-mono text-[10px] text-ink-500" id="aa-updated">{{ __('Updated —') }}</span>
            </div>
            <div id="aa-rated" class="divide-y divide-paper-200">
                <x-skeleton kind="row" :rows="4" />
            </div>
        </div>
    </main>

    <script>
        (function() {
            const csrf = document.querySelector('meta[name=csrf-token]')?.content || '';
            let range = 'today';
            let deviceFilter = null;
            try {
                const u = new URLSearchParams(window.location.search).get('device_id');
                if (u) deviceFilter = u;
            } catch (_) {}

            function esc(s) {
                return String(s ?? '').replace(/[&<>"']/g, c => ({
                    '&': '&amp;',
                    '<': '&lt;',
                    '>': '&gt;',
                    '"': '&quot;',
                    "'": '&#39;'
                } [c]));
            }

            function fmtTime(iso) {
                if (!iso) return '—';
                return new Date(iso).toLocaleString(undefined, {
                    month: 'short',
                    day: 'numeric',
                    hour: '2-digit',
                    minute: '2-digit'
                });
            }

            function scoreColor(s) {
                if (s == null) return 'text-ink-500';
                if (s >= 8) return 'text-wa-deep';
                if (s >= 5) return 'text-accent-amber';
                return 'text-accent-coral';
            }

            function agentCard(a) {
                const initials = (a.name || '?').split(/\s+/).map(s => s[0] || '').slice(0, 2).join('').toUpperCase();
                const sr = a.success_rate;
                const qa = a.avg_quality;
                const ratedBar = qa != null ? `
 <div class="w-full bg-paper-100 rounded-full h-1.5 mt-1 overflow-hidden">
 <div class="h-full ${qa >= 8 ? 'bg-wa-deep' : qa >= 5 ? 'bg-accent-amber' : 'bg-accent-coral'}" style="width:${(qa/10*100).toFixed(0)}%"></div>
 </div>` : '';
                return `<div class="bg-paper-0 border border-paper-200 rounded-xl p-4">
 <div class="flex items-start gap-3 mb-3">
 <span class="w-10 h-10 rounded-full text-paper-0 text-[12px] font-semibold grid place-items-center" style="background:${a.avatar_color}">${esc(initials)}</span>
 <div class="flex-1 min-w-0">
 <div class="flex items-center gap-2 flex-wrap">
 <div class="font-serif text-[16px] truncate">${esc(a.name)}</div>
 <span class="px-1.5 py-0.5 rounded text-[9.5px] font-mono ${a.is_active ? 'bg-wa-mint/40 text-wa-deep' : 'bg-paper-100 text-ink-500'}">${a.is_active ? 'active' : 'off'}</span>
 </div>
 <div class="text-[10.5px] text-ink-500 font-mono">${esc(a.provider)} · ${esc(a.model)} · ${esc(a.tone || '')}</div>
 </div>
 </div>
 <div class="grid grid-cols-3 gap-2 mb-2">
 <div class="text-center">
 <div class="font-serif text-[20px] leading-none text-ink-900">${a.messages_sent ?? 0}</div>
 <div class="font-mono text-[9px] uppercase tracking-[0.14em] text-ink-500 mt-1">Sent</div>
 </div>
 <div class="text-center">
 <div class="font-serif text-[20px] leading-none ${sr == null ? 'text-ink-400' : sr >= 90 ? 'text-wa-deep' : 'text-accent-amber'}">${sr == null ? '—' : sr + '%'}</div>
 <div class="font-mono text-[9px] uppercase tracking-[0.14em] text-ink-500 mt-1">Success</div>
 </div>
 <div class="text-center">
 <div class="font-serif text-[20px] leading-none text-ink-900">${a.active_now ?? 0}</div>
 <div class="font-mono text-[9px] uppercase tracking-[0.14em] text-ink-500 mt-1">Active</div>
 </div>
 </div>
 <div class="border-t border-paper-200 pt-2 mt-2">
 <div class="flex items-center justify-between">
 <span class="font-mono text-[10px] uppercase tracking-[0.14em] text-ink-500">Self-rating</span>
 <span class="font-serif text-[16px] ${scoreColor(qa)}">${qa != null ? qa + '/10' : '—'}</span>
 </div>
 ${ratedBar}
 <div class="font-mono text-[9.5px] text-ink-500 mt-1">${a.rated_count ?? 0} rated · ${a.conversations ?? 0} convos · ${a.lifetime_sent ?? 0} lifetime</div>
 </div>
 </div>`;
            }

            function ratedRow(r) {
                return `<div class="px-4 py-3 flex items-start gap-3">
 <span class="w-7 h-7 rounded-full text-paper-0 text-[10px] font-semibold grid place-items-center shrink-0" style="background:${r.agent_color}">${esc((r.agent_name||'AI').slice(0,2).toUpperCase())}</span>
 <div class="flex-1 min-w-0">
 <div class="flex items-center gap-2">
 <span class="font-semibold text-[12.5px] truncate">${esc(r.agent_name)}</span>
 <span class="font-mono text-[10px] text-ink-500">conv #${r.conversation_id}</span>
 <span class="ml-auto font-mono text-[10px] text-ink-500">${fmtTime(r.created_at)}</span>
 </div>
 ${r.note ? `<div class="text-[11.5px] text-ink-700 mt-1 italic">"${esc(r.note)}"</div>` : ''}
 </div>
 <span class="font-serif text-[18px] ${scoreColor(r.score)} shrink-0">${r.score}/10</span>
 </div>`;
            }

            async function load() {
                try {
                    const qs = new URLSearchParams({
                        range
                    });
                    if (deviceFilter) qs.set('device_id', deviceFilter);
                    const res = await fetch(`/team-inbox/api/analytics/ai-agents?${qs}`, {
                        headers: {
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                            'X-CSRF-TOKEN': csrf
                        },
                        credentials: 'same-origin',
                    });
                    const data = await res.json();
                    if (!res.ok) throw new Error(data?.message || `HTTP ${res.status}`);

                    const cards = document.getElementById('aa-cards');
                    const agents = data.agents || [];
                    cards.innerHTML = agents.length === 0 ?
                        `<div class="text-[12px] text-ink-500 col-span-full text-center py-8">No AI agents yet — set them up in the team-inbox sidebar.</div>` :
                        agents.map(agentCard).join('');

                    const rated = document.getElementById('aa-rated');
                    const rrows = data.recent_rated || [];
                    rated.innerHTML = rrows.length === 0 ?
                        `<div class="px-4 py-6 text-center text-[12px] text-ink-500">No rated replies yet. Once your AI replies to a customer, it scores its own response here.</div>` :
                        rrows.map(ratedRow).join('');

                    document.getElementById('aa-updated').textContent = 'Updated ' + new Date()
                .toLocaleTimeString();
                } catch (e) {
                    console.error(e);
                }
            }

            document.querySelectorAll('#aa-range-tabs [data-range]').forEach(b => {
                b.addEventListener('click', () => {
                    document.querySelectorAll('#aa-range-tabs .ti-tab').forEach(x => x.classList.remove(
                        'active'));
                    b.classList.add('active');
                    range = b.dataset.range;
                    load();
                });
            });

            // Device filter hydrated from bootstrap, same pattern as the team
            // analytics page. Hidden when workspace has 0–1 devices.
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
                    const wrap = document.getElementById('aa-device-wrap');
                    const sel = document.getElementById('aa-device-filter');
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
                } catch (_) {}
            })();

            load();
            setInterval(load, 30000);
        })();
    </script>

</x-layouts.user>
