import ApexCharts from 'apexcharts';
import { themeColor } from '../theme-colors.js';

/**
 * Sales Pipeline reports — renders the two charts from the JSON the controller
 * dropped in #dl-report-data (value-by-stage bar + won/lost trend).
 */
export default function userDealsReports() {
    const node = document.getElementById('dl-report-data');
    if (!node) return;

    let data;
    try { data = JSON.parse(node.textContent || '{}'); } catch (_) { return; }

    const accent = themeColor('wa-deep');
    const sym = data.symbol || '';

    // Pipeline value by stage.
    const stageEl = document.getElementById('dl-chart-stage');
    if (stageEl && Array.isArray(data.byStage)) {
        new ApexCharts(stageEl, {
            chart: { type: 'bar', height: 280, fontFamily: 'inherit', toolbar: { show: false } },
            series: [{ name: 'Open value', data: data.byStage.map((s) => Math.round(s.value)) }],
            xaxis: { categories: data.byStage.map((s) => s.name) },
            colors: data.byStage.map((s) => s.color || accent),
            plotOptions: { bar: { distributed: true, borderRadius: 6, columnWidth: '55%' } },
            legend: { show: false },
            dataLabels: { enabled: false },
            yaxis: { labels: { formatter: (v) => sym + Math.round(v) } },
            tooltip: { y: { formatter: (v) => sym + Math.round(v) } },
            grid: { borderColor: themeColor('paper-100') },
        }).render();
    }

    // Won vs lost over time.
    const trendEl = document.getElementById('dl-chart-trend');
    if (trendEl && Array.isArray(data.months)) {
        new ApexCharts(trendEl, {
            chart: { type: 'bar', height: 280, fontFamily: 'inherit', toolbar: { show: false }, stacked: false },
            series: [
                { name: 'Won', data: data.months.map((m) => m.won) },
                { name: 'Lost', data: data.months.map((m) => m.lost) },
            ],
            xaxis: { categories: data.months.map((m) => m.label) },
            colors: ['#16A34A', '#DC2626'],
            plotOptions: { bar: { borderRadius: 5, columnWidth: '55%' } },
            dataLabels: { enabled: false },
            legend: { position: 'top', horizontalAlign: 'right' },
            grid: { borderColor: themeColor('paper-100') },
        }).render();
    }
}
