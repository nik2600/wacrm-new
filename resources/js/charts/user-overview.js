import ApexCharts from 'apexcharts';
import { themeColor, themeAlpha } from '../theme-colors.js';

export default function init() {
    const sparkEl = document.querySelector('#kpi-spark');
    if (sparkEl) {
        new ApexCharts(sparkEl, {
            chart: { type: 'bar', height: 32, sparkline: { enabled: true }, animations: { enabled: true, speed: 600 } },
            series: [{ name: 'Sent/hr', data: [18, 22, 16, 26, 20, 24, 28, 22, 18, 26, 20, 30, 24, 18, 22, 26, 20, 28, 30, 32] }],
            plotOptions: {
                bar: {
                    columnWidth: '60%',
                    borderRadius: 2,
                    distributed: true,
                    colors: {
                        ranges: [
                            { from: 0, to: 27, color: themeColor('wa-teal') },
                            { from: 28, to: 99, color: themeColor('wa-green') },
                        ],
                    },
                },
            },
            dataLabels: { enabled: false },
            tooltip: { enabled: true, x: { show: false }, y: { formatter: (v) => v + 'k msg/h' } },
            states: { hover: { filter: { type: 'darken', value: 0.92 } } },
        }).render();
    }

    const readRateEl = document.querySelector('#kpi-readrate');
    if (readRateEl) {
        new ApexCharts(readRateEl, {
            chart: { type: 'radialBar', height: 64, width: 64, sparkline: { enabled: true } },
            colors: [themeColor('wa-deep')],
            series: [82.6],
            plotOptions: {
                radialBar: {
                    hollow: { size: '58%' },
                    track: { background: themeAlpha('ink-900', 0.06), strokeWidth: '100%' },
                    dataLabels: {
                        name: { show: false },
                        value: {
                            show: true,
                            fontSize: '10px',
                            fontFamily: 'JetBrains Mono, monospace',
                            fontWeight: 600,
                            color: themeColor('wa-deep'),
                            offsetY: 4,
                            formatter: (v) => v.toFixed(1),
                        },
                    },
                },
            },
            stroke: { lineCap: 'round' },
        }).render();
    }

    const throughputEl = document.querySelector('#chart-throughput');
    if (throughputEl) {
        new ApexCharts(throughputEl, {
            chart: {
                type: 'bar',
                height: 220,
                fontFamily: 'Plus Jakarta Sans, system-ui, sans-serif',
                toolbar: { show: false },
                animations: { enabled: true, speed: 500 },
            },
            colors: [themeColor('wa-deep'), themeColor('wa-green'), themeColor('accent-coral')],
            series: [
                { name: 'Sent',      data: [4500, 9800, 13500, 18421, 11500, 14500, 7800, 6500] },
                { name: 'Delivered', data: [4350, 9500, 13100, 17902, 11150, 14100, 7550, 6280] },
                { name: 'Failed',    data: [150, 300, 400, 519, 350, 400, 250, 220] },
            ],
            plotOptions: { bar: { columnWidth: '55%', borderRadius: 3 } },
            dataLabels: { enabled: false },
            grid: { borderColor: themeColor('paper-200'), strokeDashArray: 3 },
            xaxis: {
                categories: ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun', 'Mon'],
                labels: { style: { fontFamily: 'JetBrains Mono, monospace', fontSize: '10px', colors: themeColor('ink-500') } },
                axisBorder: { show: false },
                axisTicks: { show: false },
            },
            yaxis: {
                labels: {
                    formatter: (v) => (v >= 1000 ? (v / 1000).toFixed(0) + 'k' : v),
                    style: { fontFamily: 'JetBrains Mono, monospace', fontSize: '10px', colors: themeColor('ink-500') },
                },
            },
            legend: { show: false },
            tooltip: { shared: true, intersect: false, y: { formatter: (v) => v.toLocaleString() + ' msg' } },
        }).render();
    }
}
