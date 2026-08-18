// Broadcast analytics page — tab switcher + ApexCharts.
//
// Data shape lives in `window.WA_BROADCAST_DATA` (set by the blade).
// Three series:
//   delivery: { categories: [hourLabels], sent: [], delivered: [], read: [] }
//   status:   { labels: ['Sent','Delivered','Read','Queued','Failed'], series: [...] }
//   failures: { labels: [errorReason], series: [count] }
//
// Charts render lazily on first reveal of their host tab so an
// operator who never opens "Failures" doesn't pay the ApexCharts
// init cost. Resize event fires after every tab switch so the
// SVGs recompute their width to the now-visible container.
import ApexCharts from 'apexcharts';
import { themeColor } from '../theme-colors.js';

export default function init() {
    const data = window.WA_BROADCAST_DATA || {};
    const delivery = data.delivery || { categories: [], sent: [], delivered: [], read: [] };
    const status   = data.status   || { labels: [], series: [] };
    const failures = data.failures || { labels: [], series: [] };

    const baseFont = { fontFamily: 'Plus Jakarta Sans, system-ui, sans-serif' };
    const grid     = { borderColor: themeColor('paper-100'), strokeDashArray: 4 };
    const labelSty = { colors: themeColor('ink-500'), fontSize: '11px' };

    // Tab switcher — clones the wa-campaigns pattern so the visual
    // active state matches the rest of the app.
    const showTab = (name) => {
        document.querySelectorAll('.tab-panel').forEach(p => p.classList.toggle('hidden', p.dataset.panel !== name));
        document.querySelectorAll('.tab-btn').forEach(b => {
            const active = b.dataset.tab === name;
            b.classList.toggle('bg-wa-deep', active);
            b.classList.toggle('text-paper-0', active);
            b.classList.toggle('text-ink-600', !active);
            b.classList.toggle('hover:bg-paper-50', !active);
        });
        // ApexCharts uses SVG <foreignObject> which doesn't know
        // about display:hidden — firing resize forces a re-layout
        // for the now-visible chart container.
        window.dispatchEvent(new Event('resize'));
    };
    document.querySelectorAll('.tab-btn').forEach(b => b.addEventListener('click', () => showTab(b.dataset.tab)));

    // Delivery curve — area chart with three series stacked visually
    // (smooth lines, soft gradient fills).
    const deliveryEl = document.querySelector('#chart-delivery');
    if (deliveryEl) {
        new ApexCharts(deliveryEl, {
            chart:  { type: 'area', height: 300, toolbar: { show: false }, ...baseFont },
            series: [
                { name: 'Sent',      data: delivery.sent      || [] },
                { name: 'Delivered', data: delivery.delivered || [] },
                { name: 'Read',      data: delivery.read      || [] },
            ],
            colors: [themeColor('wa-deep'), themeColor('wa-teal'), themeColor('accent-amber')],
            stroke: { curve: 'smooth', width: 3 },
            fill:   { type: 'gradient', gradient: { opacityFrom: 0.24, opacityTo: 0.02 } },
            grid,
            xaxis:  { categories: delivery.categories || [], labels: { style: labelSty } },
            yaxis:  { labels: { style: labelSty } },
            legend: { show: false },
            tooltip:{ shared: true, intersect: false },
        }).render();
    }

    // Status donut — center label shows success% (everything not
    // failed and not pending counts as success).
    const statusEl = document.querySelector('#chart-status');
    if (statusEl) {
        const total   = (status.series || []).reduce((a, b) => a + (Number(b) || 0), 0);
        const failed  = Number(status.series?.[status.labels?.indexOf('Failed')] || 0);
        const queued  = Number(status.series?.[status.labels?.indexOf('Queued')] || 0);
        const success = total - failed - queued;
        const successPct = total > 0 ? Math.round((success / total) * 100) : 0;
        new ApexCharts(statusEl, {
            chart:  { type: 'donut', height: 260, ...baseFont },
            series: status.series || [],
            labels: status.labels || [],
            colors: [themeColor('wa-deep'), themeColor('wa-teal'), themeColor('accent-amber'), '#9CA8A4', themeColor('accent-coral')],
            dataLabels: { enabled: false },
            legend: { position: 'bottom', labels: { colors: '#3A5A55' } },
            plotOptions: { pie: { donut: { size: '66%', labels: { show: true, total: { show: true, label: 'Success', formatter: () => `${successPct}%` } } } } },
        }).render();
    }

    // Failure histogram — only renders when there's at least one
    // failure. Horizontal bar so long reason strings don't overflow.
    const failuresEl = document.querySelector('#chart-failures');
    if (failuresEl && (failures.labels || []).length > 0) {
        new ApexCharts(failuresEl, {
            chart:  { type: 'bar', height: 280, toolbar: { show: false }, ...baseFont },
            series: [{ name: 'Recipients', data: failures.series || [] }],
            colors: [themeColor('accent-coral')],
            plotOptions: { bar: { borderRadius: 5, horizontal: true, barHeight: '50%' } },
            grid,
            xaxis: { categories: failures.labels || [], labels: { style: labelSty } },
            yaxis: { labels: { style: labelSty } },
            dataLabels: { enabled: true, style: { fontSize: '11px', colors: ['#fff'] } },
        }).render();
    }

    // Recipients table — client-side search + status filter.
    const tbody = document.getElementById('rcptTbody');
    if (tbody) {
        const search = document.getElementById('rcptSearch');
        const statusSel = document.getElementById('rcptStatus');
        const apply = () => {
            const q = (search?.value || '').trim().toLowerCase();
            const s = statusSel?.value || '';
            tbody.querySelectorAll('tr[data-rcpt]').forEach(row => {
                const matchSearch = !q || (row.dataset.search || '').includes(q);
                const matchStatus = !s || row.dataset.status === s;
                row.classList.toggle('hidden', !(matchSearch && matchStatus));
            });
        };
        search?.addEventListener('input', apply);
        statusSel?.addEventListener('change', apply);
    }

    // Live KPI tile poll. Twilio + Baileys + WABA all post status updates
    // via different paths (StatusCallback / Node update-message-status /
    // Meta webhook) — instead of subscribing per-provider, poll the
    // /live-stats endpoint every 12s while the broadcast is in flight.
    // Pauses on hidden tab, resumes on visibilitychange.
    const liveEl = document.querySelector('[data-broadcast-live]');
    if (liveEl) {
        const url = liveEl.dataset.liveUrl;
        const fmt = (n) => Number(n || 0).toLocaleString();
        let timer = null;
        const tick = async () => {
            try {
                const r = await fetch(url, { credentials: 'same-origin', headers: { 'Accept': 'application/json' } });
                if (!r.ok) return;
                const j = await r.json();
                const setText = (key, value) => {
                    const el = liveEl.querySelector(`[data-live="${key}"]`);
                    if (el) el.textContent = value;
                };
                setText('sent',      fmt(j.sent));
                setText('delivered', fmt(j.delivered));
                setText('read',      fmt(j.read));
                setText('queued',    fmt(j.queued));
                setText('failed',    fmt(j.failed));
                if (j.pct) {
                    setText('sent-pct',      `${j.pct.sent}% of audience`);
                    setText('delivered-pct', `${j.pct.delivered}% delivery rate`);
                    setText('read-pct',      `${j.pct.read}% of delivered`);
                    setText('failed-pct',    `${j.pct.failed}% failure rate`);
                }
                const pill = document.querySelector('[data-broadcast-status-pill]');
                if (pill && j.status) {
                    pill.textContent = j.status.replace(/_/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase());
                }
                if (!j.in_flight && timer) { clearInterval(timer); timer = null; }
            } catch (_) { /* network blips are silent */ }
        };
        const start = () => { if (!timer) { tick(); timer = setInterval(tick, 12000); } };
        const stop  = () => { if (timer)  { clearInterval(timer); timer = null; } };
        start();
        document.addEventListener('visibilitychange', () => { if (document.hidden) stop(); else start(); });
    }

    // Retry-failed button — confirm + POST.
    document.querySelectorAll('[data-broadcast-retry]').forEach((btn) => {
        btn.addEventListener('click', async () => {
            if (!window.confirm('Re-send to all previously-failed recipients?')) return;
            btn.disabled = true;
            const original = btn.innerHTML;
            btn.textContent = 'Retrying…';
            try {
                const r = await fetch(btn.dataset.retryUrl, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content || '',
                    },
                });
                const j = await r.json().catch(() => ({}));
                if (r.ok) {
                    if (window.toast) window.toast(j.message || 'Retry submitted.', 'success');
                    setTimeout(() => window.location.reload(), 800);
                } else {
                    if (window.toast) window.toast(j.message || 'Retry failed.', 'error');
                    btn.disabled = false;
                    btn.innerHTML = original;
                }
            } catch (_) {
                btn.disabled = false;
                btn.innerHTML = original;
                if (window.toast) window.toast('Network error retrying.', 'error');
            }
        });
    });
}
