import ApexCharts from 'apexcharts';
import { themeColor } from '../theme-colors.js';

export default function init() {
    const data = window.adminBillingHistory || { trend: { labels: [], charges: [], refunds: [] } };
    const baseFont = { fontFamily: 'Plus Jakarta Sans, system-ui, sans-serif' };
    const label    = { colors: themeColor('ink-500'), fontSize: '11px' };

    const el = document.getElementById('chart-billing');
    if (!el) return;

    new ApexCharts(el, {
        chart: { type: 'area', height: 260, toolbar: { show: false }, ...baseFont },
        series: [
            { name: 'Charges', data: data.trend.charges },
            { name: 'Refunds', data: data.trend.refunds },
        ],
        colors: [themeColor('wa-deep'), '#E76A6A'],
        stroke: { curve: 'smooth', width: 2 },
        fill: { type: 'gradient', gradient: { opacityFrom: 0.35, opacityTo: 0.05 } },
        dataLabels: { enabled: false },
        grid: { borderColor: themeColor('paper-100'), strokeDashArray: 4 },
        xaxis: {
            categories: data.trend.labels,
            tickAmount: Math.min(12, data.trend.labels.length),
            labels: { style: label, rotate: -30, hideOverlappingLabels: true, trim: true, maxHeight: 60 },
        },
        yaxis: { labels: { style: label, formatter: (v) => (window.WA_CURRENCY || '$') + Number(v).toLocaleString() } },
        legend: { show: false },
    }).render();
}
