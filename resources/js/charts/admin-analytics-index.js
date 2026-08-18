/**
 * /admin/analytics page initialiser.
 *
 * Two concerns:
 *   1. Tab switching — buttons with data-wa-tab="<key>" inside a
 *      [data-wa-tabs] bar show/hide every [data-wa-tab-panel="<keys>"]
 *      whose space-separated list contains <key>. Charts inside a
 *      hidden panel render to a 0-height box, so we re-render after
 *      every tab switch.
 *   2. Charts — each chart div carries its series + labels as JSON
 *      data-* attributes so Blade writes them directly (no globals).
 *      Missing/empty data → the chart simply doesn't draw.
 */
import { themeColor } from '../theme-colors.js';

export default async function init() {
    const A = await import('apexcharts').then((m) => m.default).catch(() => null);

    const baseFont = { fontFamily: 'Plus Jakarta Sans, system-ui, sans-serif' };
    const grid     = { borderColor: themeColor('paper-100'), strokeDashArray: 4 };
    const label    = { colors: themeColor('ink-500'), fontSize: '11px' };
    const json     = (el, k) => {
        try { return JSON.parse(el.getAttribute('data-' + k) || '[]'); }
        catch (_) { return []; }
    };

    // Track chart instances so we can re-render them after a tab switch
    // (Apex skips drawing when the container width is 0).
    const charts = {};

    function buildCharts() {
        if (!A) return;

        // ── Growth chart ───────────────────────────────────────────
        const growthEl = document.querySelector('#chart-platform-growth');
        if (growthEl && !charts.growth) {
            const series = json(growthEl, 'series');
            const cats   = json(growthEl, 'categories');
            charts.growth = new A(growthEl, {
                chart:   { type: 'area', height: 315, toolbar: { show: false }, ...baseFont },
                series:  [{ name: 'Signups', data: series }],
                colors:  [themeColor('wa-deep')],
                stroke:  { curve: 'smooth', width: 3 },
                fill:    { type: 'gradient', gradient: { shade: 'light', opacityFrom: 0.3, opacityTo: 0.05 } },
                grid,
                xaxis:   { categories: cats, labels: { style: label } },
                yaxis:   { labels: { style: label, formatter: (v) => v.toFixed(0) } },
                legend:  { show: false },
            });
            charts.growth.render();
        }

        // ── Plan distribution donut ────────────────────────────────
        const mixEl = document.querySelector('#chart-module-mix');
        if (mixEl && !charts.mix) {
            const labels = json(mixEl, 'labels');
            const series = json(mixEl, 'series');
            if (series.length) {
                charts.mix = new A(mixEl, {
                    chart:    { type: 'donut', height: 225, ...baseFont },
                    labels, series,
                    colors:   [themeColor('wa-deep'), themeColor('wa-teal'), '#13478A', themeColor('accent-amber'), themeColor('accent-coral'), '#9DC9B6', '#3FA37A', '#0A7A66'],
                    dataLabels: { enabled: false },
                    legend:   { show: true, position: 'bottom', fontSize: '11px', labels: { colors: themeColor('ink-500') } },
                    stroke:   { colors: [themeColor('paper-0')], width: 2 },
                });
                charts.mix.render();
            } else {
                // Friendly empty state instead of a blank box. The user
                // sees this when there are zero workspaces, or every
                // workspace.plan column is the same null value.
                mixEl.innerHTML = '<div class="h-full w-full flex flex-col items-center justify-center text-center px-4">'
                    + '<div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500 mb-1">No data yet</div>'
                    + '<div class="text-[12px] text-ink-600 max-w-[200px]">Plan distribution shows once at least one workspace has been assigned a plan.</div>'
                    + '</div>';
            }
        }

        // ── Top devices horizontal bar ─────────────────────────────
        // For Apex horizontal bars, the category labels go on xaxis,
        // not yaxis. Apex swaps the axes internally when horizontal:true.
        const delEl = document.querySelector('#chart-delivery');
        if (delEl && !charts.del) {
            const labels = json(delEl, 'labels');
            const series = json(delEl, 'series');
            if (series.length) {
                charts.del = new A(delEl, {
                    chart:    { type: 'bar', height: 225, toolbar: { show: false }, ...baseFont },
                    series:   [{ name: 'Sends (24h)', data: series }],
                    colors:   [themeColor('wa-deep')],
                    plotOptions: { bar: { borderRadius: 4, horizontal: true, barHeight: '52%', distributed: false } },
                    dataLabels: { enabled: false },
                    grid,
                    xaxis:    { categories: labels, labels: { style: label } },
                    yaxis:    { labels: { style: label } },
                    legend:   { show: false },
                });
                charts.del.render();
            } else {
                delEl.innerHTML = '<div class="h-full w-full flex flex-col items-center justify-center text-center px-4">'
                    + '<div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500 mb-1">No sends yet</div>'
                    + '<div class="text-[12px] text-ink-600 max-w-[200px]">Top devices populate as soon as any number sends a message.</div>'
                    + '</div>';
            }
        }

        // ── Daily signup bars ──────────────────────────────────────
        const retEl = document.querySelector('#chart-retention');
        if (retEl && !charts.ret) {
            const series = json(retEl, 'series');
            const cats   = json(retEl, 'categories');
            if (series.length) {
                charts.ret = new A(retEl, {
                    chart:    { type: 'bar', height: 285, toolbar: { show: false }, ...baseFont },
                    series:   [{ name: 'New users', data: series }],
                    colors:   [themeColor('wa-deep')],
                    plotOptions: { bar: { columnWidth: '60%', borderRadius: 4 } },
                    dataLabels: { enabled: false },
                    grid,
                    xaxis:    { categories: cats, labels: { style: label } },
                    yaxis:    { labels: { style: label, formatter: (v) => v.toFixed(0) } },
                    legend:   { show: false },
                });
                charts.ret.render();
            }
        }
    }

    // ── Tab switching ──────────────────────────────────────────────
    // Show every [data-wa-tab-panel] whose space-separated keys
    // contains the active one; hide the rest. We toggle the dark-pill
    // style on the buttons to match the prototype.
    function showTab(key) {
        document.querySelectorAll('[data-wa-tabs] [data-wa-tab]').forEach((btn) => {
            const on = btn.dataset.waTab === key;
            btn.classList.toggle('bg-wa-deep', on);
            btn.classList.toggle('text-paper-0', on);
            btn.classList.toggle('text-ink-600', !on);
            btn.classList.toggle('hover:bg-paper-50', !on);
        });
        document.querySelectorAll('[data-wa-tab-panel]').forEach((panel) => {
            const keys = (panel.getAttribute('data-wa-tab-panel') || '').split(/\s+/);
            panel.style.display = keys.includes(key) ? '' : 'none';
        });
        // Apex won't render into a hidden box. After making panels
        // visible, ping every initialised chart to redraw at the new
        // container width.
        Object.values(charts).forEach((c) => {
            try { c.windowResizeHandler && c.windowResizeHandler(); } catch (_) {}
        });
    }

    document.querySelectorAll('[data-wa-tabs] [data-wa-tab]').forEach((btn) => {
        btn.addEventListener('click', () => showTab(btn.dataset.waTab));
    });

    // Build charts on first paint, then apply the default ("overview")
    // panel filter. Order matters: charts must be instantiated while
    // their containers are visible so Apex captures the right width.
    buildCharts();
    showTab('overview');
}
