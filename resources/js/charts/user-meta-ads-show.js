/*
 * /meta-ads/{id} — Meta campaign detail page.
 *
 * Polls /meta-ads/{id}/refresh every 60s while status is ACTIVE so
 * insights stay live. Stops on PAUSED/DRAFT/FAILED — no point burning
 * Meta GET quota on dormant campaigns.
 *
 * Wires the toggle (pause/activate) + retry (re-run 5-step sync) +
 * the auto-refresh tile updates. All inline — no page reload needed.
 */

const POLL_MS = 60_000;
let pollTimer = null;
let inflight  = false;

function csrf() {
    return document.querySelector('meta[name=csrf-token]')?.content || '';
}

function root() {
    return document.querySelector('[data-meta-show]');
}

function setTile(label, value) {
    const node = document.querySelector(`[data-tile="${label}"] .text-\\[16px\\]`);
    if (node) node.textContent = value;
}

function applyRefresh(j) {
    const el = root();
    if (!el || !j || !j.ok) return;
    if (j.meta_status) {
        el.dataset.metaStatus = j.meta_status;
        const label = document.querySelector('[data-status-label]');
        if (label) label.textContent = j.meta_status.charAt(0) + j.meta_status.slice(1).toLowerCase();
    }
    if (j.last_synced) {
        const slot = document.querySelector('[data-meta-last-synced]');
        if (slot) slot.textContent = 'Last synced ' + j.last_synced;
    }
    const i = j.insights || {};
    setTile('spend',         (i.spend ?? 0).toFixed(2));
    setTile('impressions',   Intl.NumberFormat().format(i.impressions ?? 0));
    setTile('clicks',        Intl.NumberFormat().format(i.clicks ?? 0));
    setTile('reach',         Intl.NumberFormat().format(i.reach ?? 0));
    setTile('conversations', Intl.NumberFormat().format(i.conversions ?? 0));
    setTile('ctr',           (i.ctr ?? 0).toFixed(2) + '%');
    setTile('cpc',           (i.cpc ?? 0).toFixed(2));
    setTile('frequency',     (i.frequency ?? 0).toFixed(2));
}

async function refresh() {
    const el = root();
    if (!el || inflight) return;
    const url = el.dataset.metaRefreshUrl;
    if (!url) return;
    inflight = true;
    try {
        const resp = await fetch(url, {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': csrf(), 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
        });
        if (!resp.ok) return;
        applyRefresh(await resp.json());
    } catch (_) { /* network blip — keep polling */ }
    finally { inflight = false; }
}

function startPolling() {
    if (pollTimer) return;
    pollTimer = setInterval(refresh, POLL_MS);
}
function stopPolling() {
    if (pollTimer) clearInterval(pollTimer);
    pollTimer = null;
}

function wireToggle() {
    document.querySelectorAll('[data-meta-toggle-btn]').forEach((btn) => {
        btn.addEventListener('click', async () => {
            btn.disabled = true;
            try {
                const url = root().dataset.metaToggleUrl;
                const r = await fetch(url, {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': csrf(), 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
                });
                if (r.ok) location.reload();
            } finally { btn.disabled = false; }
        });
    });
}

function wireRetry() {
    document.querySelectorAll('[data-meta-retry-btn]').forEach((btn) => {
        btn.addEventListener('click', async () => {
            btn.disabled = true;
            btn.textContent = 'Retrying…';
            try {
                const url = root().dataset.metaRetryUrl;
                const r = await fetch(url, {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': csrf(), 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
                });
                const j = r.ok ? await r.json() : null;
                if (j && j.ok) {
                    location.reload();
                } else if (j) {
                    const errSlot = document.querySelector('[data-meta-error]');
                    if (errSlot) errSlot.textContent = j.last_error || 'Retry failed.';
                    btn.disabled = false;
                    btn.textContent = 'Retry sync';
                }
            } catch (e) {
                btn.disabled = false;
                btn.textContent = 'Retry sync';
            }
        });
    });
}

export default function init() {
    const el = root();
    if (!el) return;
    wireToggle();
    wireRetry();

    if (el.dataset.metaStatus === 'ACTIVE' && document.visibilityState === 'visible') {
        startPolling();
    }
    document.addEventListener('visibilitychange', () => {
        if (document.visibilityState === 'visible' && root().dataset.metaStatus === 'ACTIVE') {
            startPolling(); refresh();
        } else {
            stopPolling();
        }
    });
}
