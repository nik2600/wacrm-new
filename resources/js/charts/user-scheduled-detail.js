/*
 * /scheduled/{id} — detail page wiring.
 * - Donut + engagement curve render from data-* attributes on <main>
 *   (no /api round-trip; the blade already computed the buckets).
 * - Tabs filter the per-recipient table in-place by data-status.
 *   Tab semantics match the KPI strip: "sent" includes delivered/read,
 *   "delivered" includes read.
 * - Action buttons (pause/resume/run-now/cancel/destroy) hit the JSON
 *   endpoints; the controllers already call NodeSchedulerClient to
 *   pause/cancel the cron on Node.
 * - Confirmations use the in-page #sched-confirm modal, not window.confirm.
 */
import ApexCharts from 'apexcharts';
import { schedConfirm } from './sched-confirm-modal.js';
import { themeColor } from '../theme-colors.js';

export default function init() {
    const main = document.querySelector('main[data-sched-id]');
    if (!main) return;

    /* ─────────── Donut ─────────── */
    const donutSeries = (() => { try { return JSON.parse(main.dataset.donut || '[]'); } catch { return []; } })();
    const donutTotal  = donutSeries.reduce((a, b) => a + (Number(b) || 0), 0);
    const donutEl     = document.querySelector('#chart-status');
    if (donutEl && donutTotal > 0) {
        new ApexCharts(donutEl, {
            chart: { type: 'donut', height: 200, fontFamily: 'Plus Jakarta Sans' },
            series: donutSeries,
            labels: ['Delivered', 'Sent · not delivered', 'Failed', 'Pending'],
            // Slice 3 is "Failed" — coral stays literal as a semantic error colour.
            colors: [themeColor('wa-deep'), themeColor('wa-teal'), '#E87A5D', themeColor('paper-100')],
            legend: { show: false },
            dataLabels: { enabled: false },
            plotOptions: {
                pie: {
                    donut: {
                        size: '68%',
                        labels: {
                            show: true,
                            total: { show: true, label: 'Total', fontFamily: 'JetBrains Mono', fontSize: '11px', color: themeColor('ink-500'), formatter: () => donutTotal.toLocaleString() },
                            value: { fontFamily: 'Fraunces', fontSize: '24px', color: themeColor('ink-900') },
                        },
                    },
                },
            },
            stroke: { width: 2, colors: [themeColor('paper-0')] },
        }).render();
    }

    /* ─────────── Engagement curve ─────────── */
    const engageEl = document.querySelector('#chart-engage');
    const hasEngageData = main.dataset.engagementHasData === '1';
    if (engageEl && hasEngageData) {
        const categories = (() => { try { return JSON.parse(main.dataset.engagementCategories || '[]'); } catch { return []; } })();
        const reads      = (() => { try { return JSON.parse(main.dataset.engagementReads || '[]'); } catch { return []; } })();
        const sentSeries = (() => { try { return JSON.parse(main.dataset.engagementSent || '[]'); } catch { return []; } })();
        new ApexCharts(engageEl, {
            chart: { type: 'area', height: 260, toolbar: { show: false }, fontFamily: 'Plus Jakarta Sans' },
            series: [
                { name: 'Cumulative reads', data: reads },
                { name: 'Cumulative sent',  data: sentSeries },
            ],
            xaxis: {
                categories,
                labels: { style: { colors: themeColor('ink-500'), fontSize: '10px', fontFamily: 'JetBrains Mono' } },
                axisBorder: { show: false },
                axisTicks:  { show: false },
            },
            yaxis: { labels: { style: { colors: themeColor('ink-500'), fontSize: '10px', fontFamily: 'JetBrains Mono' } } },
            colors: [themeColor('wa-deep'), themeColor('accent-amber')],
            stroke: { curve: 'smooth', width: 2 },
            fill: { type: 'gradient', gradient: { shadeIntensity: 1, opacityFrom: 0.3, opacityTo: 0.05 } },
            grid: { borderColor: themeColor('paper-100'), strokeDashArray: 3 },
            dataLabels: { enabled: false },
            legend: { position: 'top', horizontalAlign: 'right', fontSize: '11px', fontFamily: 'JetBrains Mono', labels: { colors: '#3A5A55' } },
        }).render();
    }

    /* ─────────── Recipient tabs ─────────── */
    // Each tab corresponds to a SET of statuses. "Sent" is the union
    // of sent + delivered + read because in the funnel sense a message
    // that's been read was also sent. Same rule index page uses.
    const tabStatusMap = {
        all:       null,
        sent:      ['sent', 'delivered', 'read'],
        delivered: ['delivered', 'read'],
        read:      ['read'],
        failed:    ['failed'],
        pending:   ['pending'],
    };
    const rows = Array.from(document.querySelectorAll('#rec-tbody .rec-row'));
    const emptyRow = document.getElementById('rec-empty-row');
    const tabBtns = Array.from(document.querySelectorAll('#rec-tabs [data-rec-tab]'));

    function applyTab(tabKey) {
        const allowed = tabStatusMap[tabKey];
        let shown = 0;
        rows.forEach(tr => {
            const visible = allowed === null || allowed.includes(tr.dataset.status);
            tr.style.display = visible ? '' : 'none';
            if (visible) shown++;
        });
        if (emptyRow) emptyRow.classList.toggle('hidden', shown > 0);

        tabBtns.forEach(b => {
            const isActive = b.dataset.recTab === tabKey;
            b.classList.toggle('bg-wa-deep', isActive);
            b.classList.toggle('text-paper-0', isActive);
            b.classList.toggle('text-ink-600', !isActive);
            b.classList.toggle('hover:bg-paper-100', !isActive);
        });
    }
    tabBtns.forEach(b => b.addEventListener('click', () => applyTab(b.dataset.recTab)));

    /* ─────────── Action buttons (pause / resume / run-now / cancel / destroy) ─────────── */
    const csrf = () => document.querySelector('meta[name=csrf-token]')?.content || '';
    // Spread `...opts` FIRST so the canonical fields (credentials, the
    // merged headers with X-CSRF-TOKEN) win over anything the caller
    // passed. Earlier order let `opts.headers` clobber X-CSRF-TOKEN →
    // every JSON-body request hit 419.
    const api  = (path, opts = {}) => fetch(path, {
        ...opts,
        credentials: 'same-origin',
        headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': csrf(), ...(opts.headers || {}) },
    }).then(async r => {
        const data = await r.json().catch(() => ({}));
        if (!r.ok) throw new Error(data?.message || data?.errors?.[Object.keys(data?.errors || {})[0]]?.[0] || `HTTP ${r.status}`);
        return data;
    });
    const toast = (msg, kind = 'info') => window.toast ? window.toast(msg, kind === 'error' ? 'error' : 'success') : console.log(`[${kind}]`, msg);

    document.querySelectorAll('[data-sched-action]').forEach(btn => {
        btn.addEventListener('click', async () => {
            const action = btn.dataset.schedAction;
            const id     = btn.dataset.schedId;

            const meta = {
                'run-now': { title: 'Fire this schedule now?', body: 'The bot will send within the next dispatcher tick (~60s). This counts against your message wallet immediately.', ok: 'Send now', danger: false },
                cancel:    { title: 'Cancel this schedule?',    body: 'The Node cron will be removed and this schedule will not fire again. The history stays.',                ok: 'Yes, cancel',  danger: true  },
                destroy:   { title: 'Delete this schedule permanently?', body: 'The Node cron is removed AND the local record is dropped. This cannot be undone.',              ok: 'Delete forever', danger: true },
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
                if (action === 'destroy') {
                    setTimeout(() => window.location.href = window.appUrl('/scheduled'), 400);
                } else {
                    setTimeout(() => window.location.reload(), 400);
                }
            } catch (e) {
                btn.disabled = false;
                btn.classList.remove('opacity-60');
                toast(e.message, 'error');
            }
        });
    });
}
