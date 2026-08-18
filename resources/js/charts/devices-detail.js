import ApexCharts from 'apexcharts';
import { themeColor } from '../theme-colors.js';

/*
 * Admin · Device analytics. All three charts read REAL per-device data the
 * controller stamped onto the chart divs as data-* attributes (scoped via
 * conversations.device_id — the same link the user-side device page uses).
 * No hardcoded series; an empty device renders honest zeros.
 */
export default function init() {
    const num = (v) => {
        const n = parseInt(v, 10);
        return Number.isFinite(n) ? n : 0;
    };
    const arr = (v) => {
        try {
            const p = JSON.parse(v || '[]');
            return Array.isArray(p) ? p : [];
        } catch {
            return [];
        }
    };

    // Volume — daily sent vs failed over the real 7-day window.
    const volEl = document.querySelector('#chart-volume');
    if (volEl) {
        const labels = arr(volEl.dataset.labels);
        const sent = arr(volEl.dataset.sent);
        const failed = arr(volEl.dataset.failed);
        new ApexCharts(volEl, {
            chart: { type: 'bar', height: 260, toolbar: { show: false }, fontFamily: 'Plus Jakarta Sans', stacked: true },
            series: [
                { name: 'Sent', data: sent },
                { name: 'Failed', data: failed },
            ],
            xaxis: { categories: labels, labels: { style: { colors: themeColor('ink-500'), fontSize: '10px', fontFamily: 'JetBrains Mono' } }, axisBorder: { show: false }, axisTicks: { show: false } },
            yaxis: { labels: { style: { colors: themeColor('ink-500'), fontSize: '10px', fontFamily: 'JetBrains Mono' } } },
            colors: [themeColor('wa-deep'), themeColor('accent-coral')],
            plotOptions: { bar: { borderRadius: 6, columnWidth: '48%' } },
            dataLabels: { enabled: false },
            grid: { borderColor: themeColor('paper-100'), strokeDashArray: 3 },
            legend: { position: 'top', horizontalAlign: 'right', fontSize: '11px', fontFamily: 'JetBrains Mono', labels: { colors: '#3A5A55' } },
            noData: { text: 'No sends in the last 7 days' },
            tooltip: { y: { formatter: (v) => v.toLocaleString() + ' msg' } },
        }).render();
    }

    // Status donut — read / delivered / pending / failed, real counts.
    const stEl = document.querySelector('#chart-status');
    if (stEl) {
        const read = num(stEl.dataset.read);
        const delivered = num(stEl.dataset.delivered);
        const pending = num(stEl.dataset.pending);
        const failed = num(stEl.dataset.failed);
        const total = read + delivered + pending + failed;
        new ApexCharts(stEl, {
            chart: { type: 'donut', height: 200, fontFamily: 'Plus Jakarta Sans' },
            series: total ? [read, delivered, pending, failed] : [1],
            labels: total ? ['Read', 'Delivered', 'Pending', 'Failed'] : ['No data'],
            colors: total
                ? [themeColor('wa-deep'), themeColor('wa-teal'), themeColor('accent-amber'), themeColor('accent-coral')]
                : [themeColor('paper-200')],
            legend: { show: false },
            dataLabels: { enabled: false },
            plotOptions: { pie: { donut: { size: '68%', labels: { show: true, total: { show: true, label: 'Total', fontFamily: 'JetBrains Mono', fontSize: '11px', color: themeColor('ink-500'), formatter: () => total.toLocaleString() }, value: { fontFamily: 'Fraunces', fontSize: '22px', color: themeColor('ink-900') } } } } },
            stroke: { width: 2, colors: [themeColor('paper-0')] },
        }).render();
    }

    // Heatmap — real 7-day x 24-hour send grid.
    const hmEl = document.querySelector('#chart-heatmap');
    if (hmEl) {
        const labels = arr(hmEl.dataset.labels);
        const grid = arr(hmEl.dataset.grid); // [7][24]
        const heatSeries = labels.map((label, i) => ({
            name: label,
            data: Array.from({ length: 24 }, (_, h) => ({
                x: String(h).padStart(2, '0'),
                y: Array.isArray(grid[i]) ? num(grid[i][h]) : 0,
            })),
        }));
        new ApexCharts(hmEl, {
            chart: { type: 'heatmap', height: 260, toolbar: { show: false }, fontFamily: 'Plus Jakarta Sans' },
            series: heatSeries,
            colors: [themeColor('wa-deep')],
            plotOptions: { heatmap: { radius: 3, colorScale: { ranges: [
                { from: 0, to: 0, color: themeColor('paper-50'), name: 'idle' },
                { from: 1, to: 8, color: themeColor('wa-mint'), name: 'low' },
                { from: 9, to: 20, color: '#7FCDB9', name: 'mid' },
                { from: 21, to: 100000, color: themeColor('wa-deep'), name: 'high' },
            ] } } },
            dataLabels: { enabled: false },
            xaxis: { labels: { style: { colors: themeColor('ink-500'), fontSize: '9px', fontFamily: 'JetBrains Mono' } }, axisBorder: { show: false }, axisTicks: { show: false } },
            yaxis: { labels: { style: { colors: themeColor('ink-500'), fontSize: '10px', fontFamily: 'JetBrains Mono' } } },
            grid: { padding: { top: 0, right: 0, bottom: 0, left: 0 } },
            legend: { show: false },
        }).render();
    }
}
