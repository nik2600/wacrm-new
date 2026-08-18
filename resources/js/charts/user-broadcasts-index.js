/*
 * /broadcasts page AJAX glue: filters, search, row delete, and pagination.
 */

// Base path the page was actually served under (handles a /public install).
// pathname never includes the query string, so it stays the route across
// history.pushState calls.
const BASE = window.location.pathname.replace(/\/+$/, '');

const $ = (id) => document.getElementById(id);

function csrf() {
    return document.querySelector('meta[name=csrf-token]')?.content || '';
}

function debounce(fn, ms) {
    let t;
    return (...args) => { clearTimeout(t); t = setTimeout(() => fn(...args), ms); };
}

function readState() {
    const el = document.querySelector('[data-bc-state]');
    return {
        status: el?.dataset.bcStatus || 'all',
        range:  el?.dataset.bcRange  || 'all',
        q:      el?.dataset.bcSearch || '',
        page:   el?.dataset.bcPage   || '1',
    };
}

function writeState(state) {
    const el = document.querySelector('[data-bc-state]');
    if (!el) return;
    el.dataset.bcStatus = state.status;
    el.dataset.bcRange  = state.range;
    el.dataset.bcSearch = state.q;
    el.dataset.bcPage   = state.page || '1';
}

function paintActive(state) {
    document.querySelectorAll('[data-bc-filter]').forEach((el) => {
        const active = state[el.dataset.bcFilter] === el.dataset.bcValue;
        el.classList.toggle('bg-wa-deep', active);
        el.classList.toggle('text-paper-0', active);
        el.classList.toggle('text-ink-700', !active);
        el.classList.toggle('hover:bg-paper-50', !active);
        el.classList.toggle('border', !active && el.dataset.bcFilter === 'range');
        el.classList.toggle('border-paper-200', !active && el.dataset.bcFilter === 'range');
    });
    const search = $('bc-search');
    if (search && document.activeElement !== search) search.value = state.q || '';
}

function applyCounts(statusCounts, stats) {
    Object.entries(statusCounts || {}).forEach(([key, value]) => {
        const el = document.querySelector(`[data-bc-status-count="${key}"]`);
        if (el) el.textContent = Number(value).toLocaleString();
    });

    if (!stats) return;
    const set = (key, value) => {
        document.querySelectorAll(`[data-bc-totals="${key}"]`).forEach((el) => {
            el.textContent = typeof value === 'number' ? value.toLocaleString() : value;
        });
    };
    const sent = Number(stats.sent ?? 0);
    const delivered = Number(stats.delivered ?? 0);
    const read = Number(stats.read ?? 0);
    const failed = Number(stats.failed ?? 0);
    set('total', Number(stats.total ?? 0));
    set('sent', sent);
    set('delivered', delivered);
    set('read', read);
    set('failed', failed);
    set('processing', Number(stats.processing ?? 0));
    set('queued', Number(stats.queued ?? 0));
    set('delivery_pct', sent > 0 ? (delivered / sent * 100).toFixed(1) : '0.0');
    set('read_pct', delivered > 0 ? (read / delivered * 100).toFixed(1) : '0.0');
    set('failed_pct', sent > 0 ? (failed / sent * 100).toFixed(1) : '0.0');
}

async function fetchPartial(state, { silent = false } = {}) {
    const params = new URLSearchParams();
    if (state.status !== 'all') params.append('status', state.status);
    if (state.range !== 'all')  params.append('range', state.range);
    if (state.q)                params.append('q', state.q);
    if (Number(state.page || 1) > 1) params.append('page', state.page);

    history.pushState({}, '', BASE + (params.toString() ? '?' + params.toString() : ''));
    params.append('partial', '1');

    const list = $('bc-list');
    if (list) list.classList.add('opacity-60');
    try {
        const res = await fetch(BASE + '?' + params.toString(), {
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        });
        if (!res.ok) throw new Error('HTTP ' + res.status);
        const data = await res.json();
        const rows = $('bc-rows');
        if (rows) rows.innerHTML = data.rows;
        applyCounts(data.statusCounts, data.stats);
        const totalCount = Number(data.total ?? 0);
        $('bc-results-footer')?.classList.toggle('hidden', totalCount <= 0);
        const shown = document.querySelector('[data-bc-shown]');
        if (shown) shown.textContent = data.shown ?? '0';
        const total = document.querySelector('[data-bc-total]');
        if (total) total.textContent = totalCount.toLocaleString();
        const pager = $('bc-pagination');
        if (pager) pager.innerHTML = data.pagination || '';
        if (data.page) {
            state.page = String(data.page);
            writeState(state);
        }
        wireRows();
        wirePagination();
        if (!silent) window.WaToaster?.info?.('Refreshed', { duration: 1200 });
    } catch (e) {
        window.WaToaster?.error?.('Could not refresh: ' + e.message);
    } finally {
        if (list) list.classList.remove('opacity-60');
    }
}

const debouncedFetch = debounce((state) => fetchPartial(state, { silent: true }), 220);

function deleteBroadcast(id, name) {
    const run = async () => {
        try {
            const res = await fetch(`${BASE}/${id}`, {
                method: 'DELETE',
                headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf() },
            });
            const data = await res.json().catch(() => ({}));
            if (!res.ok) throw new Error(data.message || 'HTTP ' + res.status);
            await fetchPartial(readState(), { silent: true });
        } catch (e) {
            window.WaToaster?.error?.('Delete failed: ' + e.message);
        }
    };
    if (typeof window.confirmDialog === 'function') {
        window.confirmDialog({
            title: 'Delete broadcast?',
            message: `Delete "${name || 'this broadcast'}"? This can't be undone.`,
            confirmText: 'Delete',
            tone: 'danger',
            onConfirm: run,
        });
    } else if (window.confirm(`Delete "${name || 'this broadcast'}"?`)) {
        run();
    }
}

function wireRows() {
    document.querySelectorAll('[data-broadcast-delete]').forEach((btn) => {
        if (btn.__wired) return;
        btn.__wired = true;
        btn.addEventListener('click', () => deleteBroadcast(btn.dataset.broadcastDelete, btn.dataset.name || ''));
    });
}

function wirePagination() {
    document.querySelectorAll('a[data-bc-page]').forEach((link) => {
        if (link.__wired) return;
        link.__wired = true;
        link.addEventListener('click', (e) => {
            e.preventDefault();
            const state = readState();
            state.page = link.dataset.bcPage || '1';
            writeState(state);
            fetchPartial(state, { silent: true });
        });
    });
}

export default function init() {
    document.querySelectorAll('[data-bc-filter]').forEach((el) => {
        el.addEventListener('click', (e) => {
            e.preventDefault();
            const state = readState();
            state[el.dataset.bcFilter] = el.dataset.bcValue;
            state.page = '1';
            writeState(state);
            paintActive(state);
            fetchPartial(state, { silent: true });
        });
    });

    $('bc-search')?.addEventListener('input', (e) => {
        const state = readState();
        state.q = e.target.value.trim();
        state.page = '1';
        writeState(state);
        debouncedFetch(state);
    });

    $('bc-refresh')?.addEventListener('click', () => fetchPartial(readState()));

    // Live counts auto-refresh. Broadcasts move through pending →
    // processing → completed asynchronously on the Node bridge, so
    // we poll every 15s to surface status / success / fail counts
    // without forcing the operator to manually refresh. Silent fetch
    // — no toast — and skipped while the tab is hidden to save
    // both the laptop battery and the Node bridge's CPU.
    const POLL_MS = 15_000;
    let pollHandle = null;
    function startPoll() {
        if (pollHandle) return;
        pollHandle = setInterval(() => {
            if (document.hidden) return;
            fetchPartial(readState(), { silent: true });
        }, POLL_MS);
    }
    function stopPoll() {
        if (!pollHandle) return;
        clearInterval(pollHandle);
        pollHandle = null;
    }
    startPoll();
    // Re-fetch immediately when the tab becomes visible after a long
    // hide — counters might be very stale otherwise.
    document.addEventListener('visibilitychange', () => {
        if (!document.hidden) fetchPartial(readState(), { silent: true });
    });
    // Clean up if the page is being unloaded so we don't leave the
    // interval running in a bf-cache restoration scenario.
    window.addEventListener('pagehide', stopPoll);

    wireRows();
    wirePagination();

    window.addEventListener('popstate', () => {
        const params = new URLSearchParams(window.location.search);
        const state = {
            status: params.get('status') || 'all',
            range:  params.get('range')  || 'all',
            q:      params.get('q')      || '',
            page:   params.get('page')   || '1',
        };
        writeState(state);
        paintActive(state);
        fetchPartial(state, { silent: true });
    });
}
