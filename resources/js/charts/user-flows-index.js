/*
 * /flows index — AJAX glue.
 *
 * Same pattern as the rest of this app: status / category filter
 * buttons + search push state into the URL via history.pushState
 * and re-fetch /flows?...&partial=1 for a JSON snippet:
 *
 *   { ok, cards, featured, statusCounts, categoryCounts, shown }
 *
 * Per-row Pause/Resume / Delete hit the controller endpoints, and
 * the sidebar's Library + Status accordion both filter through the
 * same flow as the top tabs (since they all carry data-fl-filter).
 */

const $ = (id) => document.getElementById(id);

function csrf() {
    return document.querySelector('meta[name=csrf-token]')?.content || '';
}
function debounce(fn, ms) { let t; return (...a) => { clearTimeout(t); t = setTimeout(() => fn(...a), ms); }; }

function readState() {
    const el = document.querySelector('[data-fl-state]');
    return {
        status:   el?.dataset.flStatus   || 'all',
        category: el?.dataset.flCategory || 'all',
        q:        el?.dataset.flSearch   || '',
        page:     el?.dataset.flPage     || '1',
    };
}
function writeState(s) {
    const el = document.querySelector('[data-fl-state]');
    if (!el) return;
    el.dataset.flStatus   = s.status;
    el.dataset.flCategory = s.category;
    el.dataset.flSearch   = s.q;
    el.dataset.flPage     = s.page || '1';
}

function paintActive(state) {
    document.querySelectorAll('[data-fl-filter]').forEach((b) => {
        const kind = b.dataset.flFilter;
        const val  = b.dataset.flValue;
        const active = state[kind] === val;
        // Sidebar buttons (the Library + Status pills) and the top
        // tabs both share the same data-fl-filter scheme, so we
        // reuse one painter for both.
        if (b.matches('.tab-line')) {
            b.classList.toggle('text-wa-deep', active);
            b.classList.toggle('font-semibold', active);
            b.classList.toggle('border-wa-deep', active);
            b.classList.toggle('text-ink-600', !active);
            b.classList.toggle('hover:text-ink-900', !active);
            b.classList.toggle('border-transparent', !active);
        } else {
            b.classList.toggle('bg-wa-deep', active);
            b.classList.toggle('text-paper-0', active);
            b.classList.toggle('font-medium', active);
            b.classList.toggle('text-ink-700', !active);
            b.classList.toggle('hover:bg-paper-50', !active);
        }
    });
    const search = $('fl-search');
    if (search && document.activeElement !== search) search.value = state.q || '';
}

function applyCounts(statusCounts, categoryCounts) {
    Object.entries(statusCounts || {}).forEach(([k, v]) => {
        document.querySelectorAll(`[data-fl-status-count="${k}"]`).forEach((el) => {
            el.textContent = Number(v).toLocaleString();
        });
        document.querySelectorAll(`[data-fl-stat="${k}"]`).forEach((el) => {
            el.textContent = Number(v).toLocaleString();
        });
    });
    Object.entries(categoryCounts || {}).forEach(([k, v]) => {
        document.querySelectorAll(`[data-fl-cat-count="${k}"]`).forEach((el) => {
            el.textContent = Number(v).toLocaleString();
        });
    });
}

async function fetchPartial(state, { silent = false } = {}) {
    const params = new URLSearchParams();
    if (state.status   !== 'all') params.append('status',   state.status);
    if (state.category !== 'all') params.append('category', state.category);
    if (state.q)                  params.append('q',        state.q);
    if (Number(state.page || 1) > 1) params.append('page',  state.page);
    const visible = '/flows' + (params.toString() ? '?' + params.toString() : '');
    history.pushState({}, '', visible);

    params.append('partial', '1');
    const grid = $('fl-grid');
    if (grid) grid.classList.add('opacity-60');
    try {
        const res = await fetch('/flows?' + params.toString(), {
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        });
        if (!res.ok) throw new Error('HTTP ' + res.status);
        const data = await res.json();
        if (grid)     grid.innerHTML     = data.cards;
        const feat = $('fl-featured');
        if (feat)     feat.innerHTML     = data.featured || '';
        applyCounts(data.statusCounts, data.categoryCounts);
        const totalCount = Number(data.total ?? 0);
        $('fl-results-footer')?.classList.toggle('hidden', totalCount <= 0);
        const shown = document.querySelector('[data-fl-shown]');
        if (shown) shown.textContent = data.shown ?? '0';
        const total = document.querySelector('[data-fl-total]');
        if (total) total.textContent = totalCount.toLocaleString();
        const pager = $('fl-pagination');
        if (pager) pager.innerHTML = data.pagination || '';
        if (data.page) {
            state.page = String(data.page);
            writeState(state);
        }
        wireRowActions();
        wirePagination();
        if (!silent) window.WaToaster?.info?.('Refreshed', { duration: 1200 });
    } catch (e) {
        window.WaToaster?.error?.('Could not refresh: ' + e.message);
    } finally {
        if (grid) grid.classList.remove('opacity-60');
    }
}

const debouncedFetch = debounce((s) => fetchPartial(s, { silent: true }), 220);

async function callAction(method, url) {
    try {
        const res = await fetch(url, {
            method,
            headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf() },
        });
        if (!res.ok) throw new Error('HTTP ' + res.status);
        return await res.json();
    } catch (e) {
        window.WaToaster?.error?.('Action failed: ' + e.message);
    }
}

function wireRowActions() {
    document.querySelectorAll('[data-flow-toggle]').forEach((b) => {
        if (b.__wired) return; b.__wired = true;
        b.addEventListener('click', async () => {
            const id = b.dataset.flowToggle;
            const data = await callAction('POST', `/flows/${id}/toggle`);
            if (data?.ok) {
                window.WaToaster?.success?.(data.is_active ? 'Flow resumed' : 'Flow paused');
                fetchPartial(readState(), { silent: true });
            }
        });
    });
    document.querySelectorAll('[data-flow-delete]').forEach((b) => {
        if (b.__wired) return; b.__wired = true;
        b.addEventListener('click', () => {
            const id   = b.dataset.flowDelete;
            const name = b.dataset.name || 'this flow';
            const run  = async () => {
                const data = await callAction('DELETE', `/flows/${id}`);
                if (data?.ok) {
                    window.WaToaster?.success?.('Flow deleted');
                    fetchPartial(readState(), { silent: true });
                }
            };
            if (typeof window.confirmDialog === 'function') {
                window.confirmDialog({
                    title: 'Delete flow?',
                    message: `Delete "${name}"? This can't be undone.`,
                    confirmText: 'Delete',
                    tone: 'danger',
                    onConfirm: run,
                });
            } else if (window.confirm(`Delete "${name}"?`)) {
                run();
            }
        });
    });
    // Manual-enroll trigger flows show an Enroll button instead of the
    // builder link. Prompts the operator for a contact group name (the
    // controller resolves group_name → contacts in that group's set) and
    // POSTs to /flows/{id}/enroll.
    document.querySelectorAll('[data-flow-enroll]').forEach((b) => {
        if (b.__wired) return; b.__wired = true;
        b.addEventListener('click', async () => {
            const id = b.dataset.flowEnroll;
            const name = b.dataset.name || 'this flow';
            const groupName = window.prompt(
                `Enroll a contact group into "${name}".\n\nEnter the group name as it appears in /contact-groups (e.g. "VIP customers"):`,
                ''
            );
            if (!groupName) return;
            try {
                const res = await fetch(`/flows/${id}/enroll`, {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrf(),
                    },
                    body: JSON.stringify({ group_name: groupName }),
                });
                const j = await res.json();
                if (j?.ok) {
                    window.WaToaster?.success?.(`Enrolled ${j.enrolled} contact(s)${j.failed ? ', ' + j.failed + ' failed' : ''}`);
                    fetchPartial(readState(), { silent: true });
                } else {
                    window.WaToaster?.error?.(j?.error || 'Enrollment failed');
                }
            } catch (e) {
                window.WaToaster?.error?.('Enrollment failed: ' + e.message);
            }
        });
    });
}

function wirePagination() {
    document.querySelectorAll('a[data-fl-page]').forEach((link) => {
        if (link.__wired) return;
        link.__wired = true;
        link.addEventListener('click', (e) => {
            e.preventDefault();
            const state = readState();
            state.page = link.dataset.flPage || '1';
            writeState(state);
            fetchPartial(state, { silent: true });
        });
    });
}

/**
 * Channel picker for the single "Create flow" button.
 *
 * Replaces the old three-button row (blank / call / instagram). Each option
 * is a plain <a> to the builder with the right ?type=, so the picker needs no
 * state of its own — it only shows and hides the sheet.
 */
function wireChannelPicker() {
    const modal = document.querySelector('[data-flow-channel-modal]');
    const open = document.querySelector('[data-flow-create]');
    if (!modal || !open) return;

    // The sheet is `hidden items-center justify-center` in markup — toggling
    // `hidden` alone would leave it display:none, so swap to flex on show.
    const show = () => { modal.classList.remove('hidden'); modal.classList.add('flex'); };
    const hide = () => { modal.classList.add('hidden'); modal.classList.remove('flex'); };

    open.addEventListener('click', show);
    modal.querySelector('[data-flow-channel-cancel]')?.addEventListener('click', hide);

    // Backdrop click closes; clicks inside the card must not bubble out to it.
    modal.addEventListener('click', (e) => { if (e.target === modal) hide(); });

    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && !modal.classList.contains('hidden')) hide();
    });
}

// WhatsApp ⇆ Instagram flow-list tabs. Present only when an IG account is
// connected. Toggles which panel (#fl-tab-wa / #fl-tab-ig) is visible and
// swaps the active pill styling — green for WhatsApp, pink gradient for
// Instagram. The panels live outside #fl-grid, so an AJAX card refresh (which
// only replaces #fl-grid) leaves the tab state intact.
function wireFlowChannelTabs() {
    const tabs = document.querySelectorAll('#fl-channel-tabs [data-fltab]');
    if (!tabs.length) return;
    // Big Instagram banner (icon + description + Fetch/Open builder buttons)
    // only belongs on the dedicated Instagram tab. On the "All" view it's a
    // heavy mid-page divider, so there we swap in the slim #fl-ig-minilabel.
    const paintIgHeader = (tab) => {
        const full = $('fl-ig-header');
        const mini = $('fl-ig-minilabel');
        if (full) { full.classList.toggle('hidden', tab !== 'ig'); full.classList.toggle('flex', tab === 'ig'); }
        if (mini) { mini.classList.toggle('hidden', tab !== 'all'); mini.classList.toggle('flex', tab === 'all'); }
    };
    tabs.forEach((btn) => {
        btn.addEventListener('click', () => {
            const tab = btn.dataset.fltab;
            // 'all' shows both panels; 'wa'/'ig' show just that channel.
            $('fl-tab-wa')?.classList.toggle('hidden', !(tab === 'wa' || tab === 'all'));
            $('fl-tab-ig')?.classList.toggle('hidden', !(tab === 'ig' || tab === 'all'));
            paintIgHeader(tab);
            tabs.forEach((b) => {
                const on = b.dataset.fltab === tab;
                const ig = b.dataset.fltab === 'ig';
                b.classList.remove('bg-wa-deep', 'text-paper-0', 'border-wa-deep', 'bg-paper-0', 'text-ink-600', 'border-paper-200', 'text-white', 'border-transparent');
                b.style.background = '';
                if (on && ig) { b.classList.add('text-white', 'border-transparent'); b.style.background = 'linear-gradient(135deg,#833AB4,#E1306C,#F77737)'; }
                else if (on)  { b.classList.add('bg-wa-deep', 'text-paper-0', 'border-wa-deep'); }
                else          { b.classList.add('bg-paper-0', 'text-ink-600', 'border-paper-200'); }
            });
        });
    });
}

export default function init() {
    document.querySelectorAll('[data-fl-filter]').forEach((b) => {
        b.addEventListener('click', (e) => {
            e.preventDefault();
            const state = readState();
            state[b.dataset.flFilter] = b.dataset.flValue;
            state.page = '1';
            writeState(state);
            paintActive(state);
            fetchPartial(state, { silent: true });
        });
    });

    $('fl-search')?.addEventListener('input', (e) => {
        const state = readState();
        state.q = e.target.value.trim();
        state.page = '1';
        writeState(state);
        debouncedFetch(state);
    });

    wireRowActions();
    wirePagination();
    wireChannelPicker();
    wireFlowChannelTabs();

    window.addEventListener('popstate', () => {
        const params = new URLSearchParams(window.location.search);
        const state = {
            status:   params.get('status')   || 'all',
            category: params.get('category') || 'all',
            q:        params.get('q')        || '',
            page:     params.get('page')     || '1',
        };
        writeState(state);
        paintActive(state);
        fetchPartial(state, { silent: true });
    });
}
