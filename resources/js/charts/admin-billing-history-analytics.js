import ApexCharts from 'apexcharts';
import { themeColor } from '../theme-colors.js';

export default function init() {
    const data = window.adminBillingAnalytics || {};
    const trend      = data.trend      || { labels: [], charges: [], refunds: [], failed: [] };
    const gatewayMix = data.gatewayMix || { labels: [], series: [] };
    const statusMix  = data.statusMix  || { labels: [], series: [] };

    const baseFont = { fontFamily: 'Plus Jakarta Sans, system-ui, sans-serif' };
    const label    = { colors: themeColor('ink-500'), fontSize: '11px' };

    const tEl = document.getElementById('chart-billing-trend');
    if (tEl) {
        new ApexCharts(tEl, {
            chart: { type: 'area', height: 280, toolbar: { show: false }, ...baseFont },
            series: [
                { name: 'Charges', data: trend.charges },
                { name: 'Refunds', data: trend.refunds },
                { name: 'Failed',  data: trend.failed },
            ],
            colors: [themeColor('wa-deep'), '#E76A6A', themeColor('accent-amber')],
            stroke: { curve: 'smooth', width: 2 },
            fill: { type: 'gradient', gradient: { opacityFrom: 0.35, opacityTo: 0.05 } },
            dataLabels: { enabled: false },
            grid: { borderColor: themeColor('paper-100'), strokeDashArray: 4 },
            xaxis: {
                categories: trend.labels,
                tickAmount: Math.min(12, trend.labels.length),
                labels: { style: label, rotate: -30, hideOverlappingLabels: true, trim: true, maxHeight: 60 },
            },
            yaxis: { labels: { style: label, formatter: (v) => (window.WA_CURRENCY || '$') + Number(v).toLocaleString() } },
            legend: { show: false },
        }).render();
    }

    const gEl = document.getElementById('chart-gateway-mix');
    if (gEl) {
        const series = (gatewayMix.series || []).map(Number);
        new ApexCharts(gEl, {
            chart: { type: 'donut', height: 240, ...baseFont },
            labels: gatewayMix.labels.length ? gatewayMix.labels : ['No data'],
            series: series.length ? series : [1],
            colors: [themeColor('wa-deep'), '#D9E5F2', themeColor('accent-amber'), '#F3E9FF', '#FFF4E0'],
            dataLabels: { enabled: false },
            legend: { position: 'bottom', fontSize: '11px' },
            stroke: { colors: [themeColor('paper-0')] },
        }).render();
    }

    const sEl = document.getElementById('chart-status-mix');
    if (sEl) {
        const series = (statusMix.series || []).map(Number);
        new ApexCharts(sEl, {
            chart: { type: 'donut', height: 240, ...baseFont },
            labels: statusMix.labels.length ? statusMix.labels : ['No data'],
            series: series.length ? series : [1],
            colors: [themeColor('wa-deep'), themeColor('accent-amber'), '#E76A6A', themeColor('accent-plum'), '#A0AEC0'],
            dataLabels: { enabled: false },
            legend: { position: 'bottom', fontSize: '11px' },
            stroke: { colors: [themeColor('paper-0')] },
        }).render();
    }
}
