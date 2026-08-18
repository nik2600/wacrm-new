import ApexCharts from 'apexcharts';
import { themeColor, themeAlpha } from '../theme-colors.js';

/**
 * /analytics — all chart rendering reads from window.ANALYTICS_DATA
 * which the Blade view injects from the controller. No more synthetic
 * data: every series comes from real workspace queries scoped to the
 * currently selected date range.
 *
 * Filter buttons + Apply are server-side (form submit with ?range=...
 * & from=/to=). This module only renders — re-render happens on full
 * page reload, which is fine for an analytics page where users hit
 * filters once and stare at the result.
 */
export default function init() {
    const d = window.ANALYTICS_DATA || {
        labels: [], sent: [], delivered: [], failed: [], queued: [],
        types: { labels: [], values: [] },
        devices: { labels: [], values: [] },
        heatmap: [],
    };

    const baseFont = { fontFamily: 'Plus Jakarta Sans, system-ui, sans-serif' };
    const muted = themeAlpha('ink-900', 0.08);

    function renderHeroSpark() {
        const el = document.querySelector('#hero-spark');
        if (!el) return;
        new ApexCharts(el, {
            chart: { type: 'area', height: 60, sparkline: { enabled: true } },
            colors: [themeColor('wa-green')],
            stroke: { curve: 'smooth', width: 2.5 },
            fill: { type: 'gradient', gradient: { shadeIntensity: 1, opacityFrom: 0.5, opacityTo: 0, stops: [0, 100] } },
            series: [{ name: 'Sent', data: d.sent }],
            tooltip: { y: { formatter: (v) => v.toLocaleString() + ' msg' }, x: { show: false } },
        }).render();
    }

    function renderVolume() {
        const el = document.querySelector('#chart-volume');
        if (!el) return;
        new ApexCharts(el, {
            chart: { type: 'area', height: 320, ...baseFont, toolbar: { show: false }, animations: { enabled: true } },
            colors: [themeColor('wa-deep'), themeColor('wa-teal'), themeColor('accent-coral')],
            stroke: { curve: 'smooth', width: [3, 2, 2], dashArray: [0, 6, 0] },
            fill: { type: 'gradient', gradient: { shadeIntensity: 1, opacityFrom: 0.32, opacityTo: 0.02, stops: [0, 90, 100] } },
            series: [
                { name: 'Sent',      data: d.sent },
                { name: 'Delivered', data: d.delivered },
                { name: 'Failed',    data: d.failed },
            ],
            dataLabels: { enabled: false },
            grid: { borderColor: muted, strokeDashArray: 4 },
            xaxis: { categories: d.labels, labels: { style: { fontSize: '10px' } }, tickAmount: 8, axisBorder: { show: false }, axisTicks: { show: false } },
            yaxis: { labels: { formatter: (v) => v >= 1000 ? (v / 1000).toFixed(0) + 'k' : v, style: { fontSize: '10px' } } },
            tooltip: { y: { formatter: (v) => v.toLocaleString() + ' msg' } },
            legend: { show: false },
            markers: { size: 0, hover: { size: 5 } },
        }).render();
    }

    function renderTotals() {
        const el = document.querySelector('#chart-totals');
        if (!el) return;
        new ApexCharts(el, {
            chart: { type: 'bar', height: 280, stacked: true, ...baseFont, toolbar: { show: false } },
            colors: [themeColor('wa-deep'), themeColor('accent-amber'), themeColor('accent-coral')],
            series: [
                { name: 'Sent',   data: d.sent },
                { name: 'Queued', data: d.queued },
                { name: 'Failed', data: d.failed },
            ],
            plotOptions: { bar: { columnWidth: '55%', borderRadius: 3 } },
            dataLabels: { enabled: false },
            grid: { borderColor: muted, strokeDashArray: 4 },
            xaxis: { categories: d.labels, labels: { style: { fontSize: '10px' } }, tickAmount: 6, axisBorder: { show: false }, axisTicks: { show: false } },
            yaxis: { labels: { formatter: (v) => v >= 1000 ? (v / 1000).toFixed(0) + 'k' : v, style: { fontSize: '10px' } } },
            tooltip: { y: { formatter: (v) => v.toLocaleString() + ' msg' } },
            legend: { position: 'bottom', fontSize: '11px' },
        }).render();
    }

    function renderRates() {
        const el = document.querySelector('#chart-rates');
        if (!el) return;
        const sentT   = (d.sent     || []).reduce((a, b) => a + b, 0);
        const delT    = (d.delivered|| []).reduce((a, b) => a + b, 0);
        const queueT  = (d.queued   || []).reduce((a, b) => a + b, 0);
        const failT   = (d.failed   || []).reduce((a, b) => a + b, 0);
        const total   = sentT + queueT + failT;
        const pct     = (v) => total > 0 ? Number((v / total * 100).toFixed(1)) : 0;
        new ApexCharts(el, {
            chart: { type: 'donut', height: 280, ...baseFont },
            colors: [themeColor('wa-deep'), themeColor('accent-amber'), themeColor('accent-coral')],
            labels: ['Delivered', 'Queued', 'Failed'],
            series: [pct(delT), pct(queueT), pct(failT)],
            stroke: { width: 3, colors: [themeColor('paper-0')] },
            legend: { position: 'bottom', fontSize: '11px' },
            dataLabels: { formatter: (v) => v.toFixed(1) + '%' },
            plotOptions: { pie: { donut: { size: '68%', labels: { show: true, value: { fontSize: '22px', fontFamily: 'Fraunces, serif', fontWeight: 500 }, total: { show: true, label: 'events', formatter: () => total.toLocaleString() } } } } },
            tooltip: { y: { formatter: (v) => v + '%' } },
        }).render();
    }

    function renderTypes() {
        const el = document.querySelector('#chart-types');
        if (!el) return;
        const labels = d.types?.labels || ['Text'];
        const values = d.types?.values || [0];
        new ApexCharts(el, {
            chart: { type: 'donut', height: 280, ...baseFont },
            colors: ['#7B61FF', themeColor('wa-deep'), themeColor('accent-amber'), themeColor('accent-coral'), '#13478A'],
            labels,
            series: values,
            stroke: { width: 3, colors: [themeColor('paper-0')] },
            legend: { position: 'bottom', fontSize: '11px' },
            dataLabels: { formatter: (v) => v.toFixed(1) + '%' },
            plotOptions: { pie: { donut: { size: '68%' } } },
        }).render();
    }

    function renderDevices() {
        const el = document.querySelector('#chart-devices');
        if (!el) return;
        const labels = d.devices?.labels || [];
        const values = d.devices?.values || [];
        if (!labels.length) {
            el.innerHTML = '<div class="text-[12px] text-ink-500 py-6 text-center">No connected devices yet.</div>';
            return;
        }
        new ApexCharts(el, {
            chart: { type: 'bar', height: 280, ...baseFont, toolbar: { show: false } },
            colors: [themeColor('wa-deep')],
            series: [{ name: 'Messages', data: values }],
            plotOptions: { bar: { horizontal: true, borderRadius: 6, barHeight: '58%' } },
            dataLabels: { enabled: true, style: { colors: [themeColor('paper-0')], fontWeight: 600, fontSize: '11px' }, formatter: (v) => v.toLocaleString(), offsetX: -2 },
            xaxis: { categories: labels, labels: { formatter: (v) => v.toLocaleString(), style: { fontSize: '10px' } } },
            yaxis: { labels: { style: { fontSize: '11px' } } },
            grid: { borderColor: muted, strokeDashArray: 4 },
            tooltip: { y: { formatter: (v) => v.toLocaleString() + ' msg' } },
        }).render();
    }

    function renderHeatmap() {
        const el = document.querySelector('#chart-heatmap');
        if (!el) return;
        const series = d.heatmap && d.heatmap.length ? d.heatmap : [];
        if (!series.length) {
            el.innerHTML = '<div class="text-[12px] text-ink-500 py-6 text-center">No message volume in this window.</div>';
            return;
        }
        new ApexCharts(el, {
            chart: { type: 'heatmap', height: 320, ...baseFont, toolbar: { show: false } },
            series,
            dataLabels: { enabled: false },
            colors: [themeColor('wa-deep')],
            plotOptions: { heatmap: { radius: 4, useFillColorAsStroke: false, colorScale: { ranges: [
                { from: 0,   to: 0,    color: themeColor('paper-50'), name: 'none' },
                { from: 1,   to: 5,    color: '#CFE3DA', name: 'low' },
                { from: 6,   to: 20,   color: '#7FB89D', name: 'med' },
                { from: 21,  to: 50,   color: '#2A8865', name: 'high' },
                { from: 51,  to: 9999, color: themeColor('wa-deep'), name: 'peak' },
            ] } } },
            xaxis: { type: 'category', labels: { style: { fontSize: '10px', fontFamily: 'JetBrains Mono' } } },
            yaxis: { labels: { style: { fontSize: '11px' } } },
            tooltip: { y: { formatter: (v) => v + ' messages' } },
            grid: { padding: { left: 8, right: 8 } },
        }).render();
    }

    renderHeroSpark();
    renderVolume();
    renderTotals();
    renderRates();
    renderTypes();
    renderDevices();
    renderHeatmap();
}
