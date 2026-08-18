import ApexCharts from 'apexcharts';
import { themeColor } from '../theme-colors.js';

/*
 * /auto-reply/keyword?id=… analytics page.
 * Reads server-side aggregates from data attributes on the chart divs:
 *   #chart-triggers data-series='[{date, count}, …]'  (last 30 days)
 *   #chart-variants — donut, built from the inline list rendered by blade
 *   #chart-heatmap   data-hours='[count, count, …]'   (24 hourly buckets)
 *
 * Falls back to empty/zero data when the attribute is missing or empty —
 * shows a flat baseline rather than crashing or rendering demo numbers.
 */
export default function init() {
    /* ── Triggers over time ─────────────────────────────────────────── */
    const trigEl = document.getElementById('chart-triggers');
    if (trigEl) {
        let series = [];
        try {
            const raw = JSON.parse(trigEl.dataset.series || '[]');
            series = Array.isArray(raw) ? raw : [];
        } catch (_) { series = []; }

        const labels = series.map(s => {
            // s.date is "YYYY-MM-DD" from Carbon. Cheap date label "Apr 8".
            try {
                const [, m, d] = (s.date || '').split('-');
                return new Date(2000, +m - 1, +d).toLocaleDateString(undefined, { month: 'short', day: 'numeric' });
            } catch (_) { return s.date || ''; }
        });
        const data = series.map(s => +s.count || 0);

        new ApexCharts(trigEl, {
            chart:  { type: 'area', height: 260, toolbar: { show: false }, fontFamily: 'Plus Jakarta Sans' },
            series: [{ name: 'Fires', data: data.length ? data : Array(30).fill(0) }],
            xaxis:  {
                categories: labels.length ? labels : Array.from({ length: 30 }, (_, i) => `D${i+1}`),
                labels: { style: { colors: themeColor('ink-500'), fontSize: '10px', fontFamily: 'JetBrains Mono' } },
                axisBorder: { show: false }, axisTicks: { show: false },
            },
            yaxis:   { labels: { style: { colors: themeColor('ink-500'), fontSize: '10px', fontFamily: 'JetBrains Mono' } } },
            colors:  [themeColor('wa-deep')],
            stroke:  { curve: 'smooth', width: 2 },
            fill:    { type: 'gradient', gradient: { shadeIntensity: 1, opacityFrom: 0.35, opacityTo: 0.05 } },
            grid:    { borderColor: themeColor('paper-100'), strokeDashArray: 3 },
            dataLabels: { enabled: false },
            noData:  { text: 'No fires in the last 30 days.', style: { color: themeColor('ink-500'), fontSize: '12px' } },
            tooltip: { y: { formatter: v => v + ' fires' } },
        }).render();
    }

    /* ── Variant donut ──────────────────────────────────────────────── */
    // The variant breakdown is rendered as an inline list in the blade
    // (one row per variant). We turn that list into the donut series so
    // there's no need to pass JSON twice.
    const varEl  = document.getElementById('chart-variants');
    if (varEl) {
        const variantRows = Array.from(document.querySelectorAll('#chart-variants ~ .mt-3 .flex.items-center.justify-between'));
        const labels = variantRows.map(r => r.querySelector('span.flex')?.textContent.trim()).filter(Boolean);
        const counts = variantRows.map(r => {
            const t = r.querySelector('.font-mono')?.textContent || '';
            return parseInt(t.replace(/[^\d]/g, ''), 10) || 0;
        });
        const total  = counts.reduce((a, b) => a + b, 0);

        new ApexCharts(varEl, {
            chart:  { type: 'donut', height: 200, fontFamily: 'Plus Jakarta Sans' },
            series: counts.length ? counts : [1],
            labels: counts.length ? labels : ['No data'],
            colors: counts.length
                ? [themeColor('wa-deep'), themeColor('wa-teal'), themeColor('accent-amber'), '#13478A', themeColor('accent-plum')].slice(0, counts.length)
                : [themeColor('paper-100')],
            legend:     { show: false },
            dataLabels: { enabled: false },
            plotOptions: {
                pie: {
                    donut: {
                        size: '68%',
                        labels: {
                            show: true,
                            total: {
                                show: true, label: 'Total',
                                fontFamily: 'JetBrains Mono', fontSize: '11px', color: themeColor('ink-500'),
                                formatter: () => total.toString(),
                            },
                            value: { fontFamily: 'Fraunces', fontSize: '24px', color: themeColor('ink-900') },
                        },
                    },
                },
            },
            stroke: { width: 2, colors: [themeColor('paper-0')] },
        }).render();
    }

    /* ── Hour-of-day heatmap ────────────────────────────────────────── */
    // We only have a single 24-bucket array (server side), not a 7×24 grid
    // — so render as a single-row strip ("Last 30 days") instead of the
    // weekday × hour grid the demo had. Same visual idiom, fewer fakes.
    const heatEl = document.getElementById('chart-heatmap');
    if (heatEl) {
        let hours = [];
        try {
            const raw = JSON.parse(heatEl.dataset.hours || '[]');
            hours = Array.isArray(raw) ? raw.map(n => +n || 0) : [];
        } catch (_) { hours = []; }
        if (hours.length !== 24) hours = Array(24).fill(0);

        const max = Math.max(...hours, 1);
        const series = [{
            name: 'Fires',
            data: hours.map((y, h) => ({ x: String(h).padStart(2, '0'), y })),
        }];

        // Bucket boundaries scale to the actual peak so a workspace with
        // 4 fires/hour doesn't show a flat strip.
        const t1 = Math.ceil(max * 0.25), t2 = Math.ceil(max * 0.5), t3 = Math.ceil(max * 0.75);

        new ApexCharts(heatEl, {
            chart:   { type: 'heatmap', height: 260, toolbar: { show: false }, fontFamily: 'Plus Jakarta Sans' },
            series,
            colors:  [themeColor('wa-deep')],
            plotOptions: {
                heatmap: {
                    radius: 3, useFillColorAsStroke: false,
                    colorScale: {
                        ranges: [
                            { from: 0,        to: 0,            color: themeColor('paper-50'), name: 'idle' },
                            { from: 1,        to: Math.max(1, t1), color: themeColor('wa-mint'), name: 'low'  },
                            { from: t1 + 1,   to: t2,           color: '#7FCDB9', name: 'mid'  },
                            { from: t2 + 1,   to: t3,           color: '#0F8556', name: 'high' },
                            { from: t3 + 1,   to: max + 1,      color: themeColor('wa-deep'), name: 'peak' },
                        ],
                    },
                },
            },
            dataLabels: { enabled: false },
            xaxis: { labels: { style: { colors: themeColor('ink-500'), fontSize: '9px',  fontFamily: 'JetBrains Mono' } }, axisBorder: { show: false }, axisTicks: { show: false } },
            yaxis: { labels: { style: { colors: themeColor('ink-500'), fontSize: '10px', fontFamily: 'JetBrains Mono' } } },
            grid:  { padding: { top: 0, right: 0, bottom: 0, left: 0 } },
            legend: { show: false },
            tooltip: { y: { formatter: v => v + (v === 1 ? ' fire' : ' fires') } },
        }).render();
    }
}
