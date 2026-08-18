import ApexCharts from 'apexcharts';
import { themeColor } from '../theme-colors.js';

export default function init() {
    const baseFont = { fontFamily: 'Plus Jakarta Sans, system-ui, sans-serif' };
    const grid = { borderColor: themeColor('paper-100'), strokeDashArray: 4 };
    const label = { colors: themeColor('ink-500'), fontSize: '11px' };

    // Server-rendered data (OverviewController). Fall back to a friendly empty state.
    const data = window.adminOverview || {};
    const revenue  = data.revenue  || { labels: [], series: [], target: [] };
    const region   = data.region   || { labels: ['Europe','Americas','Africa','Middle East','Pacific','Asia'], series: [0,0,0,0,0,0] };
    const platform = data.platform || { labels: ['Stripe','Razorpay','PayPal'], series: [0,0,0] };
    const plans    = data.plans    || { percent: 0, totalDisplay: '0' };

    const revenueEl = document.querySelector('#admin-revenue-chart');
    if (revenueEl) {
        new ApexCharts(revenueEl, {
            chart: { type: 'line', height: 275, toolbar: { show: false }, ...baseFont },
            series: [
                { name: 'Revenue', data: revenue.series },
                { name: 'Target',  data: revenue.target },
            ],
            colors: [themeColor('wa-deep'), themeColor('accent-amber')],
            stroke: { curve: 'smooth', width: 3 },
            markers: { size: 0 },
            grid,
            xaxis: { categories: revenue.labels, labels: { style: label } },
            yaxis: { labels: { style: label, formatter: (v) => (window.WA_CURRENCY || '$') + Number(v).toLocaleString() } },
            legend: { show: false },
        }).render();
    }

    const regionEl = document.querySelector('#admin-region-chart');
    if (regionEl) {
        new ApexCharts(regionEl, {
            chart: { type: 'radar', height: 245, toolbar: { show: false }, ...baseFont },
            series: [{ name: 'Sales', data: region.series }],
            labels: region.labels,
            colors: [themeColor('wa-deep')],
            fill: { opacity: 0.16 },
            markers: { size: 3 },
            yaxis: { show: false },
        }).render();
    }

    const platformEl = document.querySelector('#admin-platform-chart');
    if (platformEl) {
        new ApexCharts(platformEl, {
            chart: { type: 'donut', height: 245, ...baseFont },
            labels: platform.labels,
            series: platform.series.map(Number),
            colors: [themeColor('wa-deep'), '#D9E5F2', themeColor('accent-amber')],
            dataLabels: { enabled: false },
            legend: { position: 'bottom', fontSize: '11px' },
            stroke: { colors: [themeColor('paper-0')] },
        }).render();
    }

    const usersEl = document.querySelector('#admin-users-chart');
    if (usersEl) {
        new ApexCharts(usersEl, {
            chart: { type: 'radialBar', height: 220, ...baseFont },
            series: [plans.percent || 0],
            colors: [themeColor('wa-deep')],
            plotOptions: {
                radialBar: {
                    hollow: { size: '62%' },
                    dataLabels: {
                        name:  { show: true, text: 'Total users', color: themeColor('ink-500'), fontSize: '11px' },
                        value: { show: true, formatter: () => plans.totalDisplay || '0', color: themeColor('ink-900'), fontSize: '26px', fontWeight: 700 },
                    },
                },
            },
            labels: ['Total users'],
        }).render();
    }
}
