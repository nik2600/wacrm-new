import ApexCharts from 'apexcharts';
import { themeColor } from '../theme-colors.js';

export default function init() {
    const data = window.adminOrderAnalytics || {};
    const motion  = data.motion  || { labels: [], new: [], cancel: [] };
    const typeMix = data.typeMix || { labels: ['Renewal','Add-on','Cancel'], series: [0,0,0] };

    const baseFont = { fontFamily: 'Plus Jakarta Sans, system-ui, sans-serif' };
    const grid     = { borderColor: themeColor('paper-100'), strokeDashArray: 4 };
    const label    = { colors: themeColor('ink-500'), fontSize: '11px' };

    const motionEl = document.querySelector('#chart-motion');
    if (motionEl) {
        new ApexCharts(motionEl, {
            chart: { type: 'bar', height: 280, toolbar: { show: false }, ...baseFont, stacked: true },
            series: [
                { name: 'New',    data: motion.new },
                // Show cancels as negative bars (downward direction) so they read like churn.
                { name: 'Cancel', data: (motion.cancel || []).map((v) => -Math.abs(v)) },
            ],
            colors: [themeColor('wa-deep'), themeColor('accent-coral')],
            plotOptions: { bar: { columnWidth: '60%', borderRadius: 4 } },
            dataLabels: { enabled: false },
            grid,
            xaxis: {
                categories: motion.labels,
                tickAmount: Math.min(14, motion.labels.length),
                labels: { style: label, rotate: -30, hideOverlappingLabels: true, trim: true, maxHeight: 60 },
            },
            yaxis: { labels: { style: label } },
            legend: { show: false },
        }).render();
    }

    const typeEl = document.querySelector('#chart-type');
    if (typeEl) {
        const series = (typeMix.series || []).map(Number);
        new ApexCharts(typeEl, {
            chart: { type: 'donut', height: 200, ...baseFont },
            labels: typeMix.labels.length ? typeMix.labels : ['No data'],
            series: series.length ? series : [1],
            colors: [themeColor('wa-deep'), '#13478A', themeColor('accent-coral')],
            dataLabels: { enabled: false },
            legend: { show: false },
            stroke: { colors: [themeColor('paper-0')], width: 2 },
        }).render();
    }
}
