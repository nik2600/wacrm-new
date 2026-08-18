/*
 * /wa-campaigns page — AJAX glue.
 *
 * Same pattern as /meta-ads + /devices: filter buttons + range
 * pills + live search push state into the URL via history.pushState
 * and fetch /wa-campaigns?...&partial=1 for a JSON snippet:
 *
 *   {
 *     ok:           true,
 *     cards:        "<rendered-html>",
 *     stats:        { sent_total, delivered_total, … },
 *     statusCounts: { all, scheduled, running, completed, failed, … },
 *     messageTypes: { text, template, flow },
 *     shown:        <int>
 *   }
 *
 * Refresh button + per-row delete forms run through the same
 * fetcher and surface results via the global toaster.
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
    const el = document.querySelector('[data-wac-state]');
    return {
        status: el?.dataset.wacStatus || 'all',
        type:   el?.dataset.wacType   || 'all',
        range:  el?.dataset.wacRange  || 'all',
        q:      el?.dataset.wacSearch || '',
        page:   el?.dataset.wacPage   || '1',
    };
}

function writeState(s) {
    const el = document.querySelector('[data-wac-state]');
    if (!el) return;
    el.dataset.wacStatus = s.status;
    el.dataset.wacType   = s.type;
    el.dataset.wacRange  = s.range;
    el.dataset.wacSearch = s.q;
    el.dataset.wacPage   = s.page || '1';
}

function paintActive(state) {
    document.querySelectorAll('[data-wac-filter]').forEach((el) => {
        const k = el.dataset.wacFilter;
        const v = el.dataset.wacValue;
        const active = state[k] === v;
        if (k === 'status') {
            el.classList.toggle('bg-wa-deep',     active);
            el.classList.toggle('text-paper-0',   active);
            el.classList.toggle('font-semibold',  active);
            el.classList.toggle('text-ink-700',   !active);
            el.classList.toggle('hover:bg-paper-50', !active);
        } else if (k === 'type') {
            el.classList.toggle('bg-paper-50',  active);
            el.classList.toggle('text-ink-900', active);
            el.classList.toggle('font-medium',  active);
            el.classList.toggle('text-ink-700', !active);
            el.classList.toggle('hover:bg-paper-50', !active);
        } else if (k === 'range') {
            el.classList.toggle('bg-wa-deep',    active);
            el.classList.toggle('text-paper-0', active);
            el.classList.toggle('text-ink-600', !active);
            el.classList.toggle('hover:bg-paper-50', !active);
        }
    });
    const search = $('wac-search');
    if (search && document.activeElement !== search) search.value = state.q || '';
}

function applyCounts(statusCounts, typeCounts, stats) {
    Object.entries(statusCounts || {}).forEach(([k, v]) => {
        const el = document.querySelector(`[data-wac-status-count="${k}"]`);
        if (el) el.textContent = Number(v).toLocaleString();
    });
    if (typeCounts) {
        const allTotal = stats?.total ?? 0;
        const allEl = document.querySelector('[data-wac-type-count="all"]');
        if (allEl) allEl.textContent = Number(allTotal).toLocaleString();
        Object.entries(typeCounts).forEach(([k, v]) => {
            const el = document.querySelector(`[data-wac-type-count="${k}"]`);
            if (el) el.textContent = Number(v).toLocaleString();
        });
    }
    if (stats) {
        const set = (key, value) => {
            document.querySelectorAll(`[data-wac-totals="${key}"]`).forEach((el) => {
                el.textContent = typeof value === 'number' ? value.toLocaleString() : value;
            });
        };
        set('sent_total',      Number(stats.sent_total      ?? 0));
        set('delivered_total', Number(stats.delivered_total ?? 0));
        set('read_total',      Number(stats.read_total      ?? 0));
        set('failed_total',    Number(stats.failed_total    ?? 0));
        set('processing',      Number(stats.processing      ?? 0));
        set('queued',          Number(stats.queued          ?? 0));
        set('failed',          Number(stats.failed          ?? 0));
        set('total',           Number(stats.total           ?? 0));
        const sent = Number(stats.sent_total ?? 0);
        const delivered = Number(stats.delivered_total ?? 0);
        const read = Number(stats.read_total ?? 0);
        set('delivery_pct',    sent      > 0 ? (delivered / sent     * 100).toFixed(1) : '0');
        set('read_pct',        delivered > 0 ? (read      / delivered * 100).toFixed(1) : '0');
    }
}

async function fetchPartial(state, { silent = false } = {}) {
    const params = new URLSearchParams();
    if (state.status !== 'all') params.append('status', state.status);
    if (state.type   !== 'all') params.append('type',   state.type);
    if (state.range  !== 'all') params.append('range',  state.range);
    if (state.q)               params.append('q',      state.q);
    if (Number(state.page || 1) > 1) params.append('page', state.page);
    const visible = '/wa-campaigns' + (params.toString() ? '?' + params.toString() : '');
    history.pushState({}, '', visible);

    params.append('partial', '1');
    const list = $('campaignsList');
    if (list) list.classList.add('opacity-60');
    try {
        const res = await fetch('/wa-campaigns?' + params.toString(), {
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        });
        if (!res.ok) throw new Error('HTTP ' + res.status);
        const data = await res.json();
        if (list) list.innerHTML = data.cards;
        applyCounts(data.statusCounts, data.messageTypes, data.stats);
        const totalCount = Number(data.total ?? 0);
        $('wac-results-footer')?.classList.toggle('hidden', totalCount <= 0);
        const shown = document.querySelector('[data-wac-shown]');
        if (shown) shown.textContent = data.shown;
        const total = document.querySelector('[data-wac-total]');
        if (total) total.textContent = totalCount.toLocaleString();
        const pager = $('wac-pagination');
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
        if (list) list.classList.remove('opacity-60');
    }
}

const debouncedFetch = debounce((s) => fetchPartial(s, { silent: true }), 220);

function deleteCampaign(form) {
    const run = async () => {
        const csrf = getCsrf();
        try {
            const fd = new FormData(form);
            fd.delete('_method');
            fd.append('_method', 'DELETE');
            const res = await fetch(form.action, {
                method: 'POST',
                headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf, 'X-Requested-With': 'XMLHttpRequest' },
                body: fd,
            });
            if (!res.ok) throw new Error('HTTP ' + res.status);
            const row = form.closest('[data-campaign-row]');
            if (row) row.remove();
            // Pull fresh counts from the server so the sidebar
            // badges and KPI tiles update immediately after a delete.
            await fetchPartial(readState(), { silent: true });
        } catch (e) {
            window.WaToaster?.error?.('Delete failed: ' + e.message);
        }
    };
    const message = form.dataset.confirm || 'Delete this campaign? This can\'t be undone.';
    if (typeof window.confirmDialog === 'function') {
        window.confirmDialog({
            title: 'Delete campaign?',
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

async function postCampaignAction(form, { confirmText, successLabel, dangerous = false }) {
    const run = async () => {
        try {
            const fd = new FormData(form);
            const res = await fetch(form.action, {
                method: 'POST',
                headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': getCsrf(), 'X-Requested-With': 'XMLHttpRequest' },
                body: fd,
            });
            if (!res.ok) throw new Error('HTTP ' + res.status);
            window.WaToaster?.success?.(successLabel);
            // Re-render the whole list so status pills, KPI strips,
            // and action buttons all reflect the new state.
            await fetchPartial(readState(), { silent: true });
        } catch (e) {
            window.WaToaster?.error?.((successLabel || 'Action') + ' failed: ' + e.message);
        }
    };
    const ask = form.dataset.confirm || confirmText;
    if (!ask) return run();
    if (typeof window.confirmDialog === 'function') {
        window.confirmDialog({
            title:        successLabel,
            message:      ask,
            confirmText:  successLabel,
            cancelText:   'Keep as is',
            tone:         dangerous ? 'danger' : 'default',
            onConfirm:    run,
        });
    } else if (window.confirm(ask)) {
        run();
    }
}

function wireRowActions() {
    document.querySelectorAll('form[data-ajax="delete-campaign"]').forEach((form) => {
        if (form.__wired) return;
        form.__wired = true;
        form.addEventListener('submit', (e) => {
            e.preventDefault();
            deleteCampaign(form);
        });
    });
    document.querySelectorAll('form[data-ajax="cancel-campaign"]').forEach((form) => {
        if (form.__wired) return;
        form.__wired = true;
        form.addEventListener('submit', (e) => {
            e.preventDefault();
            postCampaignAction(form, { successLabel: 'Cancel', dangerous: true });
        });
    });
    document.querySelectorAll('form[data-ajax="resume-campaign"]').forEach((form) => {
        if (form.__wired) return;
        form.__wired = true;
        form.addEventListener('submit', (e) => {
            e.preventDefault();
            postCampaignAction(form, { successLabel: 'Resume' });
        });
    });
    document.querySelectorAll('form[data-ajax="resend-campaign"]').forEach((form) => {
        if (form.__wired) return;
        form.__wired = true;
        form.addEventListener('submit', (e) => {
            e.preventDefault();
            postCampaignAction(form, { successLabel: 'Resend' });
        });
    });
}

function wirePagination() {
    document.querySelectorAll('a[data-wac-page]').forEach((link) => {
        if (link.__wired) return;
        link.__wired = true;
        link.addEventListener('click', (e) => {
            e.preventDefault();
            const state = readState();
            state.page = link.dataset.wacPage || '1';
            writeState(state);
            fetchPartial(state, { silent: true });
        });
    });
}

export default function init() {
    document.querySelectorAll('[data-wac-filter]').forEach((el) => {
        el.addEventListener('click', (e) => {
            e.preventDefault();
            const state = readState();
            state[el.dataset.wacFilter] = el.dataset.wacValue;
            state.page = '1';
            writeState(state);
            paintActive(state);
            fetchPartial(state, { silent: true });
        });
    });

    $('wac-search')?.addEventListener('input', (e) => {
        const state = readState();
        state.q = e.target.value.trim();
        state.page = '1';
        writeState(state);
        debouncedFetch(state);
    });

    $('wac-refresh')?.addEventListener('click', () => fetchPartial(readState()));

    wireRowActions();
    wirePagination();

    // Live counts auto-refresh. Campaigns move through scheduled →
    // running → completed asynchronously on the Node bridge, and the
    // delivered / read receipts arrive via the
    // /api/campaigns/update-status-by-id callback long after the
    // initial dispatch. Poll every 15 s so the index repaints without
    // forcing the operator to hit "Refresh" manually. Silent fetch
    // (no toast) and skipped while the tab is hidden so the bridge
    // doesn't burn CPU on backgrounded windows.
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
    document.addEventListener('visibilitychange', () => {
        if (!document.hidden) fetchPartial(readState(), { silent: true });
    });
    window.addEventListener('pagehide', stopPoll);

    window.addEventListener('popstate', () => {
        const params = new URLSearchParams(window.location.search);
        const state = {
            status: params.get('status') || 'all',
            type:   params.get('type')   || 'all',
            range:  params.get('range')  || 'all',
            q:      params.get('q')      || '',
            page:   params.get('page')   || '1',
        };
        writeState(state);
        paintActive(state);
        fetchPartial(state, { silent: true });
    });
}
