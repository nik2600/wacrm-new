/*
 * /templates page — AJAX glue.
 *
 * Same pattern as /meta-ads, /devices, /wa-campaigns,
 * /broadcasts: category tabs + status sidebar + sort dropdown +
 * live search push state into the URL via history.pushState
 * and fetch /templates?...&partial=1 for a JSON snippet:
 *
 *   { ok, cards, categoryCounts, statusCounts, totalCount, shown }
 *
 * Per-card delete uses fetch + global toaster.
 */

const $ = (id) => document.getElementById(id);

function getCsrf() {
    return document.querySelector('meta[name=csrf-token]')?.content || '';
}

function debounce(fn, ms) {
    let t;
    return (...args) => { clearTimeout(t); t = setTimeout(() => fn(...args), ms); };
}

function readState() {
    const el = document.querySelector('[data-tpl-state]');
    return {
        category: el?.dataset.tplCategory || 'all',
        status:   el?.dataset.tplStatus   || 'all',
        channel:  el?.dataset.tplChannel  || 'all',
        q:        el?.dataset.tplSearch   || '',
        sort:     el?.dataset.tplSort     || 'newest',
        page:     el?.dataset.tplPage     || '1',
    };
}

function writeState(s) {
    const el = document.querySelector('[data-tpl-state]');
    if (!el) return;
    el.dataset.tplCategory = s.category;
    el.dataset.tplStatus   = s.status;
    el.dataset.tplChannel  = s.channel;
    el.dataset.tplSearch   = s.q;
    el.dataset.tplSort     = s.sort;
    el.dataset.tplPage     = s.page || '1';
}

function paintActive(state) {
    document.querySelectorAll('[data-tpl-filter]').forEach((el) => {
        const k = el.dataset.tplFilter;
        const v = el.dataset.tplValue;
        const active = state[k] === v;
        if (k === 'category') {
            el.classList.toggle('text-wa-deep',     active);
            el.classList.toggle('font-semibold',    active);
            el.classList.toggle('border-wa-deep',   active);
            el.classList.toggle('text-ink-600',     !active);
            el.classList.toggle('border-transparent', !active);
        } else if (k === 'status') {
            el.classList.toggle('bg-wa-deep',     active);
            el.classList.toggle('text-paper-0',   active);
            el.classList.toggle('font-semibold',  active);
            el.classList.toggle('text-ink-700',   !active);
            el.classList.toggle('hover:bg-paper-50', !active);
        } else if (k === 'channel') {
            // Channel pills carry their own colour when active (WhatsApp = green,
            // Instagram = pink). Repaint the whole class list since the partial
            // reload doesn't re-render the tab strip.
            const base = 'tpl-ch inline-flex items-center gap-1.5 px-3.5 py-1.5 rounded-full border text-[12.5px] font-medium transition';
            const activeCls = {
                all:       'border-ink-900 text-ink-900 bg-paper-0',
                whatsapp:  'border-wa-deep text-wa-deep bg-wa-mint',
                instagram: 'border-[#E1306C] text-[#C13584] bg-[#FDF2F8]',
            }[v] || 'border-ink-900 text-ink-900 bg-paper-0';
            const inactive = 'border-paper-200 text-ink-600 bg-paper-0 hover:border-ink-300';
            el.className = base + ' ' + (active ? activeCls : inactive);
        }
    });
    const sort = $('tpl-sort');
    if (sort) sort.value = state.sort;
    const search = $('tpl-search');
    if (search && document.activeElement !== search) search.value = state.q || '';
}

function applyCounts(catCounts, statusCounts, channelCounts) {
    Object.entries(catCounts || {}).forEach(([k, v]) => {
        const el = document.querySelector(`[data-tpl-cat-count="${k}"]`);
        if (el) el.textContent = Number(v).toLocaleString();
    });
    Object.entries(statusCounts || {}).forEach(([k, v]) => {
        const el = document.querySelector(`[data-tpl-status-count="${k}"]`);
        if (el) el.textContent = Number(v).toLocaleString();
    });
    Object.entries(channelCounts || {}).forEach(([k, v]) => {
        const el = document.querySelector(`[data-tpl-ch-count="${k}"]`);
        if (el) el.textContent = Number(v).toLocaleString();
    });
}

async function fetchPartial(state, { silent = false } = {}) {
    const params = new URLSearchParams();
    if (state.category !== 'all')              params.append('category', state.category);
    if (state.status   !== 'all')              params.append('status',   state.status);
    if (state.channel  !== 'all')              params.append('channel',  state.channel);
    if (state.q)                               params.append('q',        state.q);
    if (state.sort && state.sort !== 'newest') params.append('sort',     state.sort);
    if (Number(state.page || 1) > 1)           params.append('page',     state.page);
    const visible = '/templates' + (params.toString() ? '?' + params.toString() : '');
    history.pushState({}, '', visible);

    params.append('partial', '1');
    const grid = $('tpl-grid');
    if (grid) grid.classList.add('opacity-60');
    try {
        const res = await fetch('/templates?' + params.toString(), {
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        });
        if (!res.ok) throw new Error('HTTP ' + res.status);
        const data = await res.json();
        if (grid) grid.innerHTML = data.cards;
        applyCounts(data.categoryCounts, data.statusCounts, data.channelCounts);
        const totalCount = Number(data.total ?? 0);
        $('tpl-results-footer')?.classList.toggle('hidden', totalCount <= 0);
        const shown = document.querySelector('[data-tpl-shown]');
        if (shown) shown.textContent = data.shown;
        const total = document.querySelector('[data-tpl-total]');
        if (total) total.textContent = totalCount.toLocaleString();
        const pager = $('tpl-pagination');
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

function deleteTemplate(id, name) {
    const run = async () => {
        try {
            const res = await fetch(`/templates/${id}`, {
                method: 'DELETE',
                headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': getCsrf() },
            });
            if (!res.ok) throw new Error('HTTP ' + res.status);
            await fetchPartial(readState(), { silent: true });
            window.WaToaster?.success?.('Template deleted');
        } catch (e) {
            window.WaToaster?.error?.('Delete failed: ' + e.message);
        }
    };
    const message = `Delete "${name}"? This can't be undone.`;
    if (typeof window.confirmDialog === 'function') {
        window.confirmDialog({
            title: 'Delete template?',
            message,
            confirmText: 'Delete',
            cancelText:  'Cancel',
            tone:        'danger',
            onConfirm:   run,
        });
    } else if (window.confirm(message)) {
        run();
    }
}

function wireRowActions() {
    document.querySelectorAll('[data-template-delete]').forEach((b) => {
        if (b.__wired) return;
        b.__wired = true;
        b.addEventListener('click', () => deleteTemplate(b.dataset.templateDelete, b.dataset.name || ''));
    });
}

function wirePagination() {
    document.querySelectorAll('a[data-tpl-page]').forEach((link) => {
        if (link.__wired) return;
        link.__wired = true;
        link.addEventListener('click', (e) => {
            e.preventDefault();
            const state = readState();
            state.page = link.dataset.tplPage || '1';
            writeState(state);
            fetchPartial(state, { silent: true });
        });
    });
}

/**
 * Fire-and-forget WABA template status sweep.
 *
 * Runs on page load AND every time the tab returns to foreground.
 * The server-side endpoint is debounced (1 sweep / 10 min / workspace)
 * so opening this page on five tabs at once still results in a single
 * Meta GET batch. If the sweep updated anything, we silently refresh
 * the card grid so the user sees the new APPROVED / REJECTED pill.
 *
 * No console scheduler needed — this matches the team-inbox queue()
 * inline-escalation pattern.
 */
function wireStatusSweep() {
    const url = '/templates/sync-stale';

    const runSweep = async () => {
        try {
            const resp = await fetch(url, {
                method:  'POST',
                headers: {
                    'X-CSRF-TOKEN':     getCsrf(),
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept':           'application/json',
                },
            });
            if (!resp.ok) return;
            const j = await resp.json();
            if (j.ok && j.changed > 0) {
                // Silently refresh the visible cards to reflect new statuses.
                fetchPartial(readState(), { silent: true });
            }
        } catch (_) { /* network blip — ignore */ }
    };

    // Fire on initial load — non-blocking.
    runSweep();

    // Re-sweep when the tab returns to foreground after being hidden.
    // Cheap thanks to the server-side debounce; helps users who leave
    // the page open and return after a coffee break.
    document.addEventListener('visibilitychange', () => {
        if (document.visibilityState === 'visible') runSweep();
    });
}

export default function init() {
    document.querySelectorAll('[data-tpl-filter]').forEach((el) => {
        el.addEventListener('click', (e) => {
            e.preventDefault();
            const state = readState();
            state[el.dataset.tplFilter] = el.dataset.tplValue;
            state.page = '1';
            writeState(state);
            paintActive(state);
            fetchPartial(state, { silent: true });
            // close the More dropdown if a category was picked from inside it
            $('more-menu')?.classList.add('hidden');
        });
    });

    $('tpl-sort')?.addEventListener('change', (e) => {
        const state = readState();
        state.sort = e.target.value;
        state.page = '1';
        writeState(state);
        fetchPartial(state, { silent: true });
    });

    $('tpl-search')?.addEventListener('input', (e) => {
        const state = readState();
        state.q = e.target.value.trim();
        state.page = '1';
        writeState(state);
        debouncedFetch(state);
    });

    // "More" dropdown for the additional category tabs.
    const moreToggle = $('more-toggle');
    const moreMenu   = $('more-menu');
    moreToggle?.addEventListener('click', (e) => {
        e.stopPropagation();
        moreMenu?.classList.toggle('hidden');
    });
    document.addEventListener('click', (e) => {
        if (!e.target.closest('#more-menu') && !e.target.closest('#more-toggle')) {
            moreMenu?.classList.add('hidden');
        }
    });

    // "Template Messages" sidebar accordion — collapsed by default
    // (per the redesign request). Click toggles aria-expanded
    // and animates the sub-list via max-h/opacity so the page
    // doesn't show all sub-items the moment it loads.
    const tplToggle = $('tpl-msg-toggle');
    const tplSub    = $('tpl-msg-sub');
    const tplChev   = $('tpl-msg-chev');
    tplToggle?.addEventListener('click', () => {
        const open = tplToggle.getAttribute('aria-expanded') === 'true';
        const next = !open;
        tplToggle.setAttribute('aria-expanded', String(next));
        tplToggle.classList.toggle('bg-wa-deep',   next);
        tplToggle.classList.toggle('text-paper-0', next);
        tplToggle.classList.toggle('text-ink-700', !next);
        tplToggle.classList.toggle('hover:bg-paper-50', !next);
        if (tplSub) {
            tplSub.classList.toggle('max-h-0',     !next);
            tplSub.classList.toggle('opacity-0',   !next);
            tplSub.classList.toggle('max-h-[200px]', next);
            tplSub.classList.toggle('opacity-100',   next);
        }
        if (tplChev) tplChev.style.transform = next ? 'rotate(180deg)' : '';
    });

    wireRowActions();
    wirePagination();
    wireStatusSweep();

    window.addEventListener('popstate', () => {
        const params = new URLSearchParams(window.location.search);
        const state = {
            category: params.get('category') || 'all',
            status:   params.get('status')   || 'all',
            q:        params.get('q')        || '',
            sort:     params.get('sort')     || 'newest',
            page:     params.get('page')     || '1',
        };
        writeState(state);
        paintActive(state);
        fetchPartial(state, { silent: true });
    });
}
