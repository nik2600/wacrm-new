/*
 * /notifications page — AJAX glue.
 *
 * Same pattern as the rest of this app: category tabs + search push
 * state into the URL via history.pushState and re-fetch
 * /notifications?...&partial=1 for a JSON snippet:
 *
 *   { ok, feed, pagination, stats, categoryCounts, shown, total, page }
 *
 * Per-row "Read" / dismiss buttons hit the controller endpoints,
 * and a global "Mark all read" button is wired to /notifications/read-all.
 */

const $ = (id) => document.getElementById(id);

function csrf() {
    return document.querySelector('meta[name=csrf-token]')?.content || '';
}
function debounce(fn, ms) { let t; return (...a) => { clearTimeout(t); t = setTimeout(() => fn(...a), ms); }; }

function readState() {
    const el = document.querySelector('[data-notif-state]');
    return {
        category: el?.dataset.notifCategory || 'all',
        q:        el?.dataset.notifSearch   || '',
        page:     el?.dataset.notifPage     || '1',
    };
}
function writeState(s) {
    const el = document.querySelector('[data-notif-state]');
    if (!el) return;
    el.dataset.notifCategory = s.category;
    el.dataset.notifSearch   = s.q;
    el.dataset.notifPage     = s.page || '1';
}

function paintActive(state) {
    document.querySelectorAll('[data-notif-filter="category"]').forEach((b) => {
        const active = b.dataset.notifValue === state.category;
        b.classList.toggle('bg-wa-deep', active);
        b.classList.toggle('text-paper-0', active);
        b.classList.toggle('text-ink-600', !active);
        b.classList.toggle('hover:bg-paper-100', !active);
    });
    const search = $('notif-search');
    if (search && document.activeElement !== search) search.value = state.q || '';
}

function applyStats(stats) {
    if (!stats) return;
    Object.entries(stats).forEach(([k, v]) => {
        document.querySelectorAll(`[data-stat="${k}"]`).forEach((el) => {
            el.textContent = Number(v).toLocaleString();
        });
    });
}

function applyCounts(counts) {
    if (!counts) return;
    Object.entries(counts).forEach(([k, v]) => {
        const el = document.querySelector(`[data-notif-cat-count="${k}"]`);
        if (el) el.textContent = Number(v).toLocaleString();
    });
}

async function fetchPartial(state, { silent = false } = {}) {
    const params = new URLSearchParams();
    if (state.category !== 'all') params.append('category', state.category);
    if (state.q)                  params.append('q', state.q);
    if (Number(state.page || 1) > 1) params.append('page', state.page);
    const visible = '/notifications' + (params.toString() ? '?' + params.toString() : '');
    history.pushState({}, '', visible);

    params.append('partial', '1');
    const feed = $('notif-feed');
    if (feed) feed.classList.add('opacity-60');
    try {
        const res = await fetch('/notifications?' + params.toString(), {
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        });
        if (!res.ok) throw new Error('HTTP ' + res.status);
        const data = await res.json();
        if (feed) feed.innerHTML = data.feed;
        const pager = $('notif-pagination');
        if (pager) pager.innerHTML = data.pagination || '';
        applyStats(data.stats);
        applyCounts(data.categoryCounts);
        const totalCount = Number(data.total ?? 0);
        $('notif-results-footer')?.classList.toggle('hidden', totalCount <= 0);
        const shown = document.querySelector('[data-notif-shown]');
        if (shown) shown.textContent = data.shown ?? '0';
        const total = document.querySelector('[data-notif-total]');
        if (total) total.textContent = totalCount.toLocaleString();
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
        if (feed) feed.classList.remove('opacity-60');
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
    document.querySelectorAll('[data-notif-read]').forEach((b) => {
        if (b.__wired) return; b.__wired = true;
        b.addEventListener('click', async (e) => {
            e.stopPropagation();
            const id = b.dataset.notifRead;
            const data = await callAction('POST', `/notifications/${id}/read`);
            if (data?.ok) fetchPartial(readState(), { silent: true });
        });
    });
    document.querySelectorAll('[data-notif-dismiss]').forEach((b) => {
        if (b.__wired) return; b.__wired = true;
        b.addEventListener('click', async (e) => {
            e.stopPropagation();
            const id = b.dataset.notifDismiss;
            const data = await callAction('DELETE', `/notifications/${id}`);
            if (data?.ok) fetchPartial(readState(), { silent: true });
        });
    });
}

function wirePagination() {
    document.querySelectorAll('a[data-notif-page]').forEach((link) => {
        if (link.__wired) return; link.__wired = true;
        link.addEventListener('click', (e) => {
            e.preventDefault();
            const state = readState();
            state.page = link.dataset.notifPage || '1';
            writeState(state);
            fetchPartial(state, { silent: true });
        });
    });
}

export default function init() {
    document.querySelectorAll('[data-notif-filter="category"]').forEach((b) => {
        b.addEventListener('click', (e) => {
            e.preventDefault();
            const state = readState();
            state.category = b.dataset.notifValue;
            state.page = '1';
            writeState(state);
            paintActive(state);
            fetchPartial(state, { silent: true });
        });
    });

    $('notif-search')?.addEventListener('input', (e) => {
        const state = readState();
        state.q = e.target.value.trim();
        state.page = '1';
        writeState(state);
        debouncedFetch(state);
    });

    $('mark-all')?.addEventListener('click', async () => {
        const data = await callAction('POST', '/notifications/read-all');
        if (data?.ok) {
            window.WaToaster?.success?.('All notifications marked read');
            fetchPartial(readState(), { silent: true });
        }
    });

    $('notif-clear-all')?.addEventListener('click', () => {
        const run = async () => {
            const data = await callAction('DELETE', '/notifications');
            if (data?.ok) {
                window.WaToaster?.success?.('Cleared all notifications');
                fetchPartial(readState(), { silent: true });
            }
        };
        if (typeof window.confirmDialog === 'function') {
            window.confirmDialog({
                title: 'Clear all notifications?',
                message: "This can't be undone.",
                confirmText: 'Clear',
                tone: 'danger',
                onConfirm: run,
            });
        } else if (window.confirm('Clear all notifications?')) {
            run();
        }
    });

    // DND toggle stays local — purely visual for now.
    const dndBtn   = $('dnd-btn');
    const dndLabel = $('dnd-label');
    let dndOn = false;
    dndBtn?.addEventListener('click', () => {
        dndOn = !dndOn;
        if (dndLabel) dndLabel.textContent = dndOn ? 'DND on' : 'Do not disturb';
        dndBtn.classList.toggle('bg-wa-deep', dndOn);
        dndBtn.classList.toggle('text-paper-0', dndOn);
    });

    wireRowActions();
    wirePagination();

    window.addEventListener('popstate', () => {
        const params = new URLSearchParams(window.location.search);
        const state = {
            category: params.get('category') || 'all',
            q:        params.get('q')        || '',
            page:     params.get('page')     || '1',
        };
        writeState(state);
        paintActive(state);
        fetchPartial(state, { silent: true });
    });
}
