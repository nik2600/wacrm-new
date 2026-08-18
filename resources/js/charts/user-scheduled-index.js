/*
 * /scheduled — list page wiring.
 * - Renders all rows server-side (encrypted name/body forces this).
 * - Filters rows in-memory by status tab + search box.
 * - Action POSTs (pause / resume / cancel / run-now / destroy) hit the
 *   JSON endpoints, then trigger a SILENT partial re-fetch — no hard
 *   page reload. Same shape /broadcasts uses. The Node cron is
 *   pause/cancel'd by the controller as part of the action handler.
 * - Confirmations use the shared schedConfirm modal (no window.confirm).
 */
import { schedConfirm } from './sched-confirm-modal.js';

export default function init() {
    const csrf = () => document.querySelector('meta[name=csrf-token]')?.content || '';
    // Header merge order matters: spread `...opts` FIRST so caller-supplied
    // method/body/etc land, then write the canonical fields LAST so
    // `credentials` + the merged `headers` (with X-CSRF-TOKEN) override
    // anything the caller passed. Previously the spread happened last,
    // wiping out X-CSRF-TOKEN whenever a caller passed its own
    // `headers` (e.g. retry's `Content-Type: application/json`) →
    // every JSON-body request hit a 419.
    const api  = (path, opts = {}) => fetch(path, {
        ...opts,
        credentials: 'same-origin',
        headers: {
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': csrf(),
            ...(opts.headers || {}),
        },
    }).then(async r => {
        const data = await r.json().catch(() => ({}));
        if (!r.ok) throw new Error(data?.message || data?.errors?.[Object.keys(data?.errors || {})[0]]?.[0] || `HTTP ${r.status}`);
        return data;
    });

    const $  = (s, r = document) => r.querySelector(s);
    const $$ = (s, r = document) => Array.from(r.querySelectorAll(s));
    const toast = (msg, kind = 'info') => {
        if (window.toast) return window.toast(msg, kind === 'error' ? 'error' : (kind === 'success' ? 'success' : 'info'));
        if (window.WaToaster) return window.WaToaster[kind === 'error' ? 'error' : 'info']?.(msg);
        console.log(`[${kind}]`, msg);
    };

    let activeTab  = 'all';
    let searchTerm = '';

    function applyFilter() {
        const term = searchTerm.trim().toLowerCase();
        $$('#sched-tbody .sched-row').forEach(tr => {
            const status = tr.dataset.schedStatus;
            const name   = tr.dataset.schedName || '';
            const tabOk  = activeTab === 'all' || status === activeTab;
            const termOk = !term || name.includes(term);
            tr.style.display = (tabOk && termOk) ? '' : 'none';
        });
    }

    /**
     * Silent partial refresh — re-renders #sched-tbody from the controller's
     * `?partial=1` JSON path. Called after every action so the row's status
     * pill / action buttons / counters reflect the new state without a
     * full page reload. Same pattern as /broadcasts.
     */
    async function refreshSilent() {
        const tbody = $('#sched-tbody');
        if (!tbody) return;
        tbody.classList.add('opacity-60');
        try {
            const data = await api('/scheduled?partial=1', { method: 'GET' });
            if (typeof data.rows === 'string') {
                tbody.innerHTML = data.rows;
            }
            // Update tab badges from the fresh totals. The map mirrors
            // the [data-sched-tab] values in the blade.
            const totals = data.totals || {};
            const tabCount = {
                all:       totals.total       ?? 0,
                scheduled: totals.active      ?? 0,
                paused:    totals.paused      ?? 0,
                completed: totals.completed   ?? 0,
                failed:    totals.failed      ?? 0,
                cancelled: totals.cancelled   ?? 0,
            };
            $$('#sched-tabs [data-sched-tab]').forEach(btn => {
                const key = btn.dataset.schedTab;
                const badge = btn.querySelector('[data-sched-tab-count]');
                if (badge && tabCount[key] !== undefined) {
                    badge.textContent = Number(tabCount[key]).toLocaleString();
                }
            });
            // Re-bind action handlers + re-apply current filter so the
            // user's tab + search stay intact after the swap.
            wireActions();
            applyFilter();
        } catch (e) {
            toast('Refresh failed: ' + e.message, 'error');
        } finally {
            tbody.classList.remove('opacity-60');
        }
    }

    $$('#sched-tabs [data-sched-tab]').forEach(btn => {
        btn.addEventListener('click', () => {
            $$('#sched-tabs [data-sched-tab]').forEach(b => {
                b.classList.remove('bg-wa-deep', 'text-paper-0');
                b.classList.add('text-ink-600', 'hover:bg-paper-100');
            });
            btn.classList.add('bg-wa-deep', 'text-paper-0');
            btn.classList.remove('text-ink-600', 'hover:bg-paper-100');
            activeTab = btn.dataset.schedTab;
            applyFilter();
        });
    });

    let searchT;
    $('#sched-search')?.addEventListener('input', e => {
        clearTimeout(searchT);
        searchTerm = e.target.value;
        searchT = setTimeout(applyFilter, 100);
    });

    /**
     * Action button wiring. Re-runs after every silent refresh because
     * the row HTML is replaced. The `__wired` flag prevents double-binding
     * the same button across refreshes (matches the broadcasts pattern).
     */
    /**
     * Live poll. Schedules transition through scheduled → running →
     * completed/failed on the Node bridge with no user interaction —
     * a cron fires hours after the operator left the page open. The
     * poll re-fetches the partial every 15s so the row collapses to
     * just the eye the moment the Node bot reports completion,
     * without the operator having to hit refresh.
     *
     * Skipped while the tab is hidden (saves battery + Node CPU);
     * fires once immediately when the tab comes back so a stale page
     * snaps to current.
     */
    const POLL_MS = 15_000;
    let pollHandle = null;
    function startPoll() {
        if (pollHandle) return;
        pollHandle = setInterval(() => {
            if (document.hidden) return;
            refreshSilent();
        }, POLL_MS);
    }
    function stopPoll() {
        if (!pollHandle) return;
        clearInterval(pollHandle);
        pollHandle = null;
    }
    startPoll();
    document.addEventListener('visibilitychange', () => {
        if (!document.hidden) refreshSilent();
    });
    window.addEventListener('pagehide', stopPoll);

    /**
     * Retry-with-new-time modal. Failed rows show a retry icon — clicking
     * it pops this modal, the operator picks date/time/tz, and we POST
     * to /scheduled/{id}/retry which resets the row + re-registers the
     * Node cron with the new ISO time.
     */
    function openRetryModal(scheduleId) {
        const modal = $('#sched-retry');
        if (!modal) return Promise.resolve(false);
        const dateEl  = $('#retry-date');
        const timeEl  = $('#retry-time');
        const tzEl    = $('#retry-tz');
        const okBtn   = $('#retry-confirm');
        const cancel  = $('#retry-cancel');
        const errEl   = $('#retry-error');
        if (errEl) errEl.classList.add('hidden');

        return new Promise(resolve => {
            const close = (value) => {
                modal.classList.add('hidden');
                modal.classList.remove('flex');
                okBtn?.removeEventListener('click', onOk);
                cancel?.removeEventListener('click', onCancel);
                modal.removeEventListener('click', onBackdrop);
                document.removeEventListener('keydown', onKey);
                resolve(value);
            };
            const onOk = async () => {
                const payload = {
                    send_date: dateEl?.value || '',
                    send_time: timeEl?.value || '',
                    timezone:  tzEl?.value   || '',
                };
                if (!payload.send_date || !payload.send_time || !payload.timezone) {
                    if (errEl) {
                        errEl.textContent = 'Date, time and timezone are all required.';
                        errEl.classList.remove('hidden');
                    }
                    return;
                }
                okBtn.disabled = true;
                okBtn.textContent = 'Retrying…';
                try {
                    await api(`/scheduled/${scheduleId}/retry`, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify(payload),
                    });
                    close(true);
                } catch (e) {
                    okBtn.disabled = false;
                    okBtn.textContent = 'Retry now';
                    if (errEl) {
                        errEl.textContent = e.message || 'Retry failed.';
                        errEl.classList.remove('hidden');
                    }
                }
            };
            const onCancel  = () => close(false);
            const onBackdrop= (e) => { if (e.target === modal) close(false); };
            const onKey     = (e) => { if (e.key === 'Escape') close(false); };

            okBtn?.addEventListener('click', onOk);
            cancel?.addEventListener('click', onCancel);
            modal.addEventListener('click', onBackdrop);
            document.addEventListener('keydown', onKey);

            modal.classList.remove('hidden');
            modal.classList.add('flex');
            dateEl?.focus();
        });
    }

    function wireActions() {
        $$('[data-sched-action]').forEach(btn => {
            if (btn.__wired) return;
            btn.__wired = true;
            btn.addEventListener('click', async () => {
                const action = btn.dataset.schedAction;
                const id     = btn.dataset.schedId;

                // Retry has its own modal flow (date/time/tz inputs),
                // separate from the yes/no schedConfirm modal.
                if (action === 'retry') {
                    const ok = await openRetryModal(id);
                    if (ok) {
                        toast('Retry scheduled.', 'success');
                        await refreshSilent();
                    }
                    return;
                }

                const meta = {
                    'run-now': { title: 'Fire this schedule now?', body: 'The bot will send within the next dispatcher tick (~60s). This counts against your message wallet immediately.', ok: 'Send now', danger: false },
                    cancel:    { title: 'Cancel this schedule?',    body: 'The Node cron will be removed and this schedule will not fire again. The history stays.',                ok: 'Yes, cancel',    danger: true  },
                    destroy:   { title: 'Delete this schedule permanently?', body: 'The Node cron is removed AND the local record is dropped. This cannot be undone.',              ok: 'Delete forever', danger: true  },
                }[action];

                if (meta?.title) {
                    const ok = await schedConfirm({ title: meta.title, body: meta.body, ok: meta.ok, danger: meta.danger });
                    if (!ok) return;
                }

                const verbAndPath = {
                    pause:     ['POST',   `/scheduled/${id}/pause`],
                    resume:    ['POST',   `/scheduled/${id}/resume`],
                    cancel:    ['POST',   `/scheduled/${id}/cancel`],
                    'run-now': ['POST',   `/scheduled/${id}/run-now`],
                    destroy:   ['DELETE', `/scheduled/${id}`],
                }[action];
                if (!verbAndPath) return;

                try {
                    btn.disabled = true;
                    btn.classList.add('opacity-60');
                    await api(verbAndPath[1], { method: verbAndPath[0] });
                    toast({
                        pause: 'Paused.', resume: 'Resumed.',
                        cancel: 'Cancelled — Node cron removed.',
                        'run-now': 'Will fire shortly.',
                        destroy: 'Deleted — Node cron removed.',
                    }[action], 'success');
                    // Silent partial refresh — no full page reload.
                    await refreshSilent();
                } catch (e) {
                    btn.disabled = false;
                    btn.classList.remove('opacity-60');
                    toast(e.message, 'error');
                }
            });
        });
    }

    wireActions();
}
