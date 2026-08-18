import ApexCharts from 'apexcharts';
import { themeColor } from '../theme-colors.js';

/**
 * /store overview — sales chart only.
 */
export default function init() {
    const el = document.getElementById('store-sales-chart');
    if (!el) return;
    const labels  = JSON.parse(el.dataset.labels  || '[]');
    const revenue = JSON.parse(el.dataset.revenue || '[]');
    const orders  = JSON.parse(el.dataset.orders  || '[]');
    const c = new ApexCharts(el, {
        chart: { type: 'bar', height: 260, toolbar: { show: false }, fontFamily: 'Plus Jakarta Sans' },
        series: [
            { name: 'Revenue (₹)', type: 'column', data: revenue },
            { name: 'Orders',      type: 'line',   data: orders  },
        ],
        stroke: { curve: 'smooth', width: [0, 2] },
        xaxis: {
            categories: labels,
            tickAmount: Math.min(labels.length, 10),
            labels: { rotate: -35, hideOverlappingLabels: true, style: { colors: themeColor('ink-500'), fontSize: '10px', fontFamily: 'JetBrains Mono' } },
            axisBorder: { show: false }, axisTicks: { show: false },
        },
        yaxis: [
            { title: { text: 'Revenue', style: { color: themeColor('ink-500'), fontSize: '10px' } }, labels: { style: { colors: themeColor('ink-500'), fontSize: '10px', fontFamily: 'JetBrains Mono' } } },
            { opposite: true, title: { text: 'Orders', style: { color: themeColor('ink-500'), fontSize: '10px' } }, labels: { style: { colors: themeColor('ink-500'), fontSize: '10px', fontFamily: 'JetBrains Mono' } } },
        ],
        colors: [themeColor('wa-deep'), themeColor('accent-coral')],
        plotOptions: { bar: { borderRadius: 6, columnWidth: '52%' } },
        dataLabels: { enabled: false },
        grid: { borderColor: themeColor('paper-100'), strokeDashArray: 3 },
        legend: { position: 'top', horizontalAlign: 'right', fontSize: '11px', fontFamily: 'JetBrains Mono', labels: { colors: '#3A5A55' } },
        tooltip: { y: { formatter: (v) => v.toLocaleString() } },
    });
    c.render();
}
