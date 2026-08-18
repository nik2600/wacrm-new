/*
 * /meta-ads page — AJAX glue.
 *
 * Filter rail, sort dropdown, "Best performers" pill, and the
 * search input all push their state into the URL query and
 * fetch /meta-ads?...&partial=1 for a JSON snippet:
 *
 *   {
 *     cards:    "<rendered-cards-html>",
 *     counts:   { all, ACTIVE, PAUSED, ... },
 *     objCounts:{ MESSAGES, LINK_CLICKS, ... },
 *     totals:   { total, active, spend, clicks },
 *     shown:    <int>
 *   }
 *
 * The JS swaps innerHTML on #meta-campaign-list, updates the badge
 * counts on the left rail, and updates the stat row at the top —
 * no full page reload, history.pushState keeps the URL in sync.
 *
 * The Sync now button hits POST /meta-ads/sync via fetch and
 * surfaces the result through the global toaster.
 */

const $ = (id) => document.getElementById(id);

function getCsrf() {
    return document.querySelector('meta[name=csrf-token]')?.content || '';
}

function debounce(fn, ms) {
    let t;
    return (...args) => { clearTimeout(t); t = setTimeout(() => fn(...args), ms); };
}

function buildQuery(params) {
    const out = new URLSearchParams();
    Object.entries(params).forEach(([k, v]) => {
        if (k === 'page' && Number(v || 1) <= 1) return;
        if (v !== null && v !== '' && v !== undefined && v !== 'all') out.append(k, v);
    });
    return out.toString();
}

function readState() {
    const main = document.querySelector('[data-meta-state]');
    if (!main) return { status: 'all', objective: 'all', range: 'all', q: '', sort: 'date-desc', page: '1' };
    return {
        status:    main.dataset.metaStatus    || 'all',
        objective: main.dataset.metaObjective || 'all',
        range:     main.dataset.metaRange     || 'all',
        q:         main.dataset.metaSearch    || '',
        sort:      main.dataset.metaSort      || 'date-desc',
        page:      main.dataset.metaPage      || '1',
    };
}

function writeState(state) {
    const main = document.querySelector('[data-meta-state]');
    if (!main) return;
    main.dataset.metaStatus    = state.status;
    main.dataset.metaObjective = state.objective;
    main.dataset.metaRange     = state.range;
    main.dataset.metaSearch    = state.q;
    main.dataset.metaSort      = state.sort;
    main.dataset.metaPage      = state.page || '1';
}

function paintActive(state) {
    document.querySelectorAll('[data-meta-filter]').forEach((el) => {
        const k = el.dataset.metaFilter;
        const v = el.dataset.metaValue;
        const active = state[k] === v;
        el.classList.toggle('bg-wa-deep',  active);
        el.classList.toggle('text-paper-0', active);
        el.classList.toggle('font-medium',  active);
        el.classList.toggle('text-ink-700', !active);
        el.classList.toggle('hover:bg-paper-50', !active);
    });
    document.querySelectorAll('[data-meta-pill]').forEach((el) => {
        const k = el.dataset.metaPill;
        const v = el.dataset.metaValue;
        const active = state[k] === v || (k === 'sort' && state.sort === v);
        el.classList.toggle('bg-wa-deep',    active);
        el.classList.toggle('text-paper-0', active);
        el.classList.toggle('text-ink-600', !active);
        el.classList.toggle('hover:bg-paper-50', !active);
    });
    const sortSel = $('meta-sort');
    if (sortSel) sortSel.value = state.sort;
    const searchInp = $('meta-search');
    if (searchInp && document.activeElement !== searchInp) searchInp.value = state.q || '';
}

function applyCounts(counts, objCounts, totals) {
    Object.entries(counts || {}).forEach(([k, v]) => {
        const el = document.querySelector(`[data-status-count="${k}"]`);
        if (el) el.textContent = v;
    });
    document.querySelector('[data-status-count="all"]')?.replaceChildren?.(document.createTextNode(counts?.all ?? 0));
    Object.entries(objCounts || {}).forEach(([k, v]) => {
        const el = document.querySelector(`[data-obj-count="${k}"]`);
        if (el) el.textContent = v;
    });
    if (totals) {
        const set = (sel, val) => { const el = document.querySelector(sel); if (el) el.textContent = val; };
        set('[data-totals="total"]',  Number(totals.total ?? 0).toLocaleString());
        set('[data-totals="active"]', Number(totals.active ?? 0).toLocaleString());
        set('[data-totals="spend"]',  (window.WA_CURRENCY || '$') + Number(totals.spend  ?? 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }));
        set('[data-totals="clicks"]', Number(totals.clicks ?? 0).toLocaleString());
    }
}

async function fetchPartial(state) {
    const query = buildQuery(state);
    const url = query ? `/meta-ads?${query}` : '/meta-ads';
    history.pushState({}, '', url);

    const list = $('meta-campaign-list');
    if (list) list.classList.add('opacity-60');

    try {
        const res = await fetch(url + (query ? '&' : '?') + 'partial=1', {
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        });
        if (!res.ok) throw new Error('HTTP ' + res.status);
        const data = await res.json();
        if (list) list.innerHTML = data.cards;
        applyCounts(data.counts, data.objCounts, data.totals);
        const totalCount = Number(data.total ?? 0);
        $('meta-results-footer')?.classList.toggle('hidden', totalCount <= 0);
        const showFooter = document.querySelector('[data-meta-shown]');
        if (showFooter) showFooter.textContent = data.shown;
        const totalFooter = document.querySelector('[data-meta-total]');
        if (totalFooter) totalFooter.textContent = totalCount.toLocaleString();
        const pager = $('meta-pagination');
        if (pager) pager.innerHTML = data.pagination || '';
        if (data.page) {
            state.page = String(data.page);
            writeState(state);
        }
        wireRowActions();
        wirePagination();
    } catch (e) {
        window.WaToaster?.error?.('Could not refresh: ' + e.message);
    } finally {
        if (list) list.classList.remove('opacity-60');
    }
}

const debouncedFetch = debounce((state) => fetchPartial(state), 220);

/* ---- row-level actions (toggle / delete) ---- */
async function toggleCampaign(id) {
    try {
        const res = await fetch(`/meta-ads/${id}/toggle`, {
            method: 'POST',
            headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': getCsrf() },
        });
        const data = await res.json();
        if (!res.ok) throw new Error(data.message || res.statusText);
        window.WaToaster?.success?.(`Campaign ${data.data.status.toLowerCase()}`);
        const state = readState();
        await fetchPartial(state);
    } catch (e) {
        window.WaToaster?.error?.('Toggle failed: ' + e.message);
    }
}

function deleteCampaign(id, name) {
    const run = async () => {
        try {
            const res = await fetch(`/meta-ads/${id}`, {
                method: 'DELETE',
                headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': getCsrf() },
            });
            if (!res.ok) throw new Error('HTTP ' + res.status);
            // Destructive: skip the success toast — row removal is the feedback.
            await fetchPartial(readState());
        } catch (e) {
            window.WaToaster?.error?.('Delete failed: ' + e.message);
        }
    };
    if (typeof window.confirmDialog === 'function') {
        window.confirmDialog({
            title: 'Delete campaign?',
            message: `Delete "${name}"? This can't be undone.`,
            confirmText: 'Delete',
            cancelText: 'Cancel',
            tone: 'danger',
            onConfirm: run,
        });
    } else {
        if (window.confirm(`Delete "${name}"? This can't be undone.`)) run();
    }
}

function wireRowActions() {
    document.querySelectorAll('[data-meta-toggle]').forEach((b) => {
        if (b.__wired) return;
        b.__wired = true;
        b.addEventListener('click', () => toggleCampaign(b.dataset.metaToggle));
    });
    document.querySelectorAll('[data-meta-delete]').forEach((b) => {
        if (b.__wired) return;
        b.__wired = true;
        b.addEventListener('click', () => deleteCampaign(b.dataset.metaDelete, b.dataset.name || ''));
    });
}

function wirePagination() {
    document.querySelectorAll('a[data-meta-page]').forEach((link) => {
        if (link.__wired) return;
        link.__wired = true;
        link.addEventListener('click', (e) => {
            e.preventDefault();
            const state = readState();
            state.page = link.dataset.metaPage || '1';
            writeState(state);
            fetchPartial(state);
        });
    });
}

/* ---- sync button ---- */
async function runSync() {
    const btn = $('meta-sync-btn');
    if (btn) { btn.disabled = true; btn.classList.add('opacity-60'); }
    const sticky = window.WaToaster?.info?.('Syncing campaigns…', { duration: 0 });
    try {
        const res = await fetch('/meta-ads/sync', {
            method: 'POST',
            headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': getCsrf() },
        });
        if (!res.ok) throw new Error('HTTP ' + res.status);
        const data = await res.json();
        sticky?.dismiss?.();
        window.WaToaster?.success?.(
            `Synced ${data.data.total} campaigns from ${data.source === 'meta-graph' ? 'Meta Graph' : 'placeholder data'}`,
            { title: 'Sync complete' }
        );
        const lastSync = $('meta-last-sync');
        if (lastSync) lastSync.textContent = 'just now';
        await fetchPartial(readState());
    } catch (e) {
        sticky?.dismiss?.();
        window.WaToaster?.error?.('Sync failed: ' + e.message);
    } finally {
        if (btn) { btn.disabled = false; btn.classList.remove('opacity-60'); }
    }
}

/**
 * Image preview + dimension lint on the create/edit form's image
 * input. Meta requires 1080×1080 minimum for CTWA — catching this
 * client-side saves a server round-trip AND a likely Meta error
 * 1487616 ("Ad image is too small").
 */
function wireImagePreview() {
    document.querySelectorAll('[data-creative-image]').forEach((input) => {
        input.addEventListener('change', () => {
            const file = input.files?.[0];
            const row  = document.querySelector('[data-image-preview-row]');
            const warn = document.querySelector('[data-image-warn]');
            const dims = document.querySelector('[data-image-dims]');
            if (!file || !row) return;

            const url = URL.createObjectURL(file);
            const img = new Image();
            img.onload = () => {
                row.classList.remove('hidden');
                document.querySelector('[data-image-preview]').src = url;
                if (dims) dims.textContent = `${img.naturalWidth}×${img.naturalHeight} px`;
                const tooSmall = img.naturalWidth < 1080 || img.naturalHeight < 1080;
                if (warn) {
                    if (tooSmall) {
                        warn.textContent = `Meta requires 1080×1080+ — this image is ${img.naturalWidth}×${img.naturalHeight}.`;
                        warn.classList.remove('hidden');
                    } else {
                        warn.classList.add('hidden');
                    }
                }
            };
            img.src = url;
        });
    });
}

/**
 * Silent auto-refresh — the page header promises auto-refresh and
 * pre-2026-05-24 it lied. Now: every 60 s while the tab is visible
 * AND at least one campaign is in a transient state (ACTIVE / DRAFT
 * recently created), re-fetch the partial to update counts + cards
 * inline. Stops on backgrounded tab; resumes on foreground.
 */
const AUTO_REFRESH_MS = 60_000;
let __pollTimer = null;
function startAutoRefresh() {
    if (__pollTimer) return;
    __pollTimer = setInterval(() => {
        if (document.visibilityState === 'visible') {
            fetchPartial(readState(), { silent: true });
        }
    }, AUTO_REFRESH_MS);
}
function stopAutoRefresh() {
    if (__pollTimer) clearInterval(__pollTimer);
    __pollTimer = null;
}

/* ---- keys / connection modal ---- */
function wireKeysModal() {
    const modal = $('meta-keys-modal');
    if (!modal) return;

    const open  = () => { modal.classList.remove('hidden'); document.body.classList.add('overflow-hidden'); };
    const close = () => { modal.classList.add('hidden');    document.body.classList.remove('overflow-hidden'); };

    document.querySelectorAll('[data-open-keys]').forEach((b) => b.addEventListener('click', open));
    modal.querySelectorAll('[data-close-keys]').forEach((b) => b.addEventListener('click', close));

    // Click on the dimmed backdrop (but not the panel) closes it.
    modal.addEventListener('click', (e) => { if (e.target === modal) close(); });
    // Esc closes when open.
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && !modal.classList.contains('hidden')) close();
    });

    // Auto-open: the Create-gate redirect (?connect=1) or a failed
    // saveKeys() validation (server stamps data-autoopen) lands here.
    if (modal.hasAttribute('data-autoopen')) open();
}

export default function init() {
    wireImagePreview();
    wireKeysModal();
    startAutoRefresh();
    document.addEventListener('visibilitychange', () => {
        if (document.visibilityState === 'visible') {
            // Catch up immediately on tab-foreground.
            fetchPartial(readState(), { silent: true });
            startAutoRefresh();
        } else {
            stopAutoRefresh();
        }
    });

    // Filter rail (status / objective / range) — every link with
    // data-meta-filter is converted to a button-like behaviour.
    document.querySelectorAll('[data-meta-filter]').forEach((el) => {
        el.addEventListener('click', (e) => {
            e.preventDefault();
            const state = readState();
            state[el.dataset.metaFilter] = el.dataset.metaValue;
            state.page = '1';
            writeState(state);
            paintActive(state);
            fetchPartial(state);
        });
    });

    // Top filter pills (range presets / Best performers).
    document.querySelectorAll('[data-meta-pill]').forEach((el) => {
        el.addEventListener('click', (e) => {
            e.preventDefault();
            const state = readState();
            state[el.dataset.metaPill] = el.dataset.metaValue;
            state.page = '1';
            writeState(state);
            paintActive(state);
            fetchPartial(state);
        });
    });

    // Sort dropdown.
    const sort = $('meta-sort');
    sort?.addEventListener('change', () => {
        const state = readState();
        state.sort = sort.value;
        state.page = '1';
        writeState(state);
        paintActive(state);
        fetchPartial(state);
    });

    // Live search — fires on input, debounced.
    const search = $('meta-search');
    search?.addEventListener('input', () => {
        const state = readState();
        state.q = search.value.trim();
        state.page = '1';
        writeState(state);
        debouncedFetch(state);
    });

    // Sync button.
    $('meta-sync-btn')?.addEventListener('click', runSync);

    // First-pass wire row actions in the SSR'd cards.
    wireRowActions();
    wirePagination();

    // Browser back/forward → re-fetch from the URL.
    window.addEventListener('popstate', () => {
        const params = new URLSearchParams(window.location.search);
        const state = {
            status:    params.get('status')    || 'all',
            objective: params.get('objective') || 'all',
            range:     params.get('range')     || 'all',
            q:         params.get('q')         || '',
            sort:      params.get('sort')      || 'date-desc',
            page:      params.get('page')      || '1',
        };
        writeState(state);
        paintActive(state);
        fetchPartial(state);
    });
}

// Auto-detect Meta Ads assets (ad account + page) from the token already
// granted via WhatsApp embedded signup / coexistence — so the operator does
// not have to paste raw IDs after connecting. Delegated so it works whether the
// connect fields live in the modal or the standalone /meta-ads/connect page.
document.addEventListener('click', async (e) => {
    const btn = e.target.closest('[data-meta-discover]');
    if (!btn) return;
    e.preventDefault();

    const label = btn.querySelector('[data-meta-discover-label]');
    const msg   = document.querySelector('[data-meta-discover-msg]');
    const orig  = label ? label.textContent : '';
    const setMsg = (txt, ok) => {
        if (!msg) return;
        msg.textContent = txt;
        msg.className = 'mb-3 text-[11px] rounded-lg px-3 py-2 ' +
            (ok ? 'bg-wa-bubble text-wa-deep' : 'bg-accent-coral/10 text-[#A1431F]');
    };

    if (label) label.textContent = '…';
    btn.disabled = true;
    try {
        const res = await fetch('/meta-ads/discover', {
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        });
        const data = await res.json();

        if (data.autofilled || data.configured) {
            setMsg('Ad account and page detected from your connection. Reloading…', true);
            setTimeout(() => window.location.reload(), 900);
            return;
        }

        const accts = Array.isArray(data.ad_accounts) ? data.ad_accounts : [];
        const pages = Array.isArray(data.pages) ? data.pages : [];
        const adInput = document.querySelector('input[name="ad_account_id"]');
        const pgInput = document.querySelector('input[name="page_id"]');
        if (adInput && accts.length) adInput.value = String(accts[0].id).replace(/^act_/, '');
        if (pgInput && pages.length) pgInput.value = String(pages[0].id);

        if (accts.length || pages.length) {
            setMsg('Detected ' + accts.length + ' ad account(s) and ' + pages.length +
                ' page(s) — filled the first. Adjust if needed, then Save.', true);
        } else if (data.error === 'no_token') {
            setMsg('Connect WhatsApp through Meta first, then try again — or enter your keys below.', false);
        } else {
            setMsg('Your WhatsApp connection has no Ads permission yet. Enter your Ad Account + Page ID below, ' +
                'or reconnect granting ads access.', false);
        }
    } catch (err) {
        setMsg('Could not auto-detect. Please enter your keys manually below.', false);
    } finally {
        btn.disabled = false;
        if (label) label.textContent = orig;
    }
});
