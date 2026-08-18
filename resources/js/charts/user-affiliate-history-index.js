import ApexCharts from 'apexcharts';
import { themeColor } from '../theme-colors.js';

// Base path the page was actually served under (handles a /public install).
// pathname never includes the query string, so it stays the route across
// history.replaceState calls.
const BASE = window.location.pathname.replace(/\/+$/, '');

/**
 * /affiliate-history page wiring. Mirrors message-history / activity-log:
 *   - filter inputs (range, bucket tabs, search) → AJAX reload via
 *     /affiliate-history?partial=1
 *   - copy-link buttons (header + sidebar) — both write the share link
 *     into the clipboard and flash a tiny inline toast.
 */
export default function init() {
    let chart = null;

    function readJSON(el, attr, fallback) {
        try { return JSON.parse(el.getAttribute(attr)) ?? fallback; }
        catch { return fallback; }
    }

    function buildChart() {
        const el = document.getElementById('chart-volume');
        if (!el) return;
        const labels  = readJSON(el, 'data-labels',  []);
        const signups = readJSON(el, 'data-signups', []);
        const credits = readJSON(el, 'data-credits', []);
        chart = new ApexCharts(el, {
            chart: { type: 'bar', height: 260, toolbar: { show: false }, fontFamily: 'Plus Jakarta Sans' },
            series: [
                { name: 'Signups', type: 'column', data: signups },
                { name: 'Credits', type: 'line',   data: credits },
            ],
            stroke: { curve: 'smooth', width: [0, 2] },
            xaxis: {
                categories: labels,
                // Cap ticks at ~10 visible labels regardless of bucket
                // size; without this, a 90-day daily series crams 90
                // dates into the axis and they overlap into a fuzzy
                // line.
                tickAmount: Math.min(labels.length, 10),
                labels: {
                    rotate: -35,
                    rotateAlways: false,
                    hideOverlappingLabels: true,
                    style: { colors: themeColor('ink-500'), fontSize: '10px', fontFamily: 'JetBrains Mono' },
                },
                axisBorder: { show: false },
                axisTicks: { show: false },
            },
            yaxis: [
                { title: { text: 'Signups', style: { color: themeColor('ink-500'), fontSize: '10px', fontFamily: 'JetBrains Mono' } }, labels: { style: { colors: themeColor('ink-500'), fontSize: '10px', fontFamily: 'JetBrains Mono' } } },
                { opposite: true, title: { text: 'Credits', style: { color: themeColor('ink-500'), fontSize: '10px', fontFamily: 'JetBrains Mono' } }, labels: { style: { colors: themeColor('ink-500'), fontSize: '10px', fontFamily: 'JetBrains Mono' } } },
            ],
            colors: [themeColor('wa-deep'), themeColor('accent-coral')],
            plotOptions: { bar: { borderRadius: 6, columnWidth: '52%' } },
            dataLabels: { enabled: false },
            grid: { borderColor: themeColor('paper-100'), strokeDashArray: 3 },
            legend: { position: 'top', horizontalAlign: 'right', fontSize: '11px', fontFamily: 'JetBrains Mono', labels: { colors: '#3A5A55' } },
            tooltip: { y: { formatter: (v) => v.toLocaleString() } },
        });
        chart.render();
    }
    buildChart();

    const filters = {
        range:  document.getElementById('ah-range')?.value || '90d',
        bucket: document.querySelector('#ah-bucket-tabs .bg-wa-deep')?.dataset.bucket || 'daily',
        q:      document.getElementById('ah-search')?.value || '',
        page:   1,
    };

    function buildQuery() {
        const p = new URLSearchParams();
        if (filters.range !== '90d')    p.set('range', filters.range);
        if (filters.bucket !== 'daily') p.set('bucket', filters.bucket);
        if (filters.q)                  p.set('q', filters.q);
        if (filters.page > 1)           p.set('page', filters.page);
        return p;
    }

    let abortCtrl = null;
    async function reload() {
        try {
            abortCtrl?.abort();
            abortCtrl = new AbortController();
            const params = buildQuery();
            params.set('partial', '1');
            const res = await fetch(BASE + '?' + params.toString(), {
                headers: { Accept: 'application/json' },
                signal: abortCtrl.signal,
            });
            if (!res.ok) throw new Error('reload failed');
            const json = await res.json();
            applyPayload(json.data);
            const visible = buildQuery().toString();
            history.replaceState(null, '', BASE + (visible ? '?' + visible : ''));
        } catch (e) {
            if (e.name !== 'AbortError') console.error('[affiliate-history]', e);
        }
    }

    function applyPayload(d) {
        if (d.stats) {
            setText('[data-ah="signupsLifetime"]', d.stats.signupsLifetime.toLocaleString());
            setText('[data-ah="creditsLifetime"]', d.stats.creditsLifetime.toLocaleString());
            setText('[data-ah="signups30d"]',     d.stats.signups30d.toLocaleString());
            setText('[data-ah="credits30d"]',     '+' + d.stats.credits30d.toLocaleString());
            setText('[data-ah="avgPerSignup"]',   d.stats.avgPerSignup.toLocaleString());
        }
        const tbody = document.getElementById('ah-rows');
        if (tbody && d.rowsHtml) tbody.innerHTML = d.rowsHtml;

        const totalRows = Number(d.total ?? 0);
        document.getElementById('ah-results-footer')?.classList.toggle('hidden', totalRows <= 0);
        setText('[data-ah="shownRange"]', `${d.shownFrom}–${d.shownTo}`);
        setText('[data-ah="totalRows"]',  totalRows.toLocaleString());
        renderPagination(d.page, d.pageCount, totalRows);

        if (chart && d.volume) {
            chart.updateOptions({
                xaxis: {
                    categories: d.volume.labels,
                    tickAmount: Math.min(d.volume.labels.length, 10),
                },
            }, false, false);
            chart.updateSeries([
                { name: 'Signups', type: 'column', data: d.volume.signups },
                { name: 'Credits', type: 'line',   data: d.volume.credits },
            ]);
        }
    }

    function setText(sel, val) { document.querySelectorAll(sel).forEach(el => el.textContent = val); }

    function renderPagination(page, pageCount, totalRows = 0) {
        const wrap = document.getElementById('ah-pagination');
        if (!wrap) return;
        if (totalRows <= 0) {
            wrap.innerHTML = '';
            return;
        }
        const html = [];
        const dPrev = page <= 1 ? 'disabled' : '';
        const dNext = page >= pageCount ? 'disabled' : '';
        html.push(`<button class="px-2.5 py-1 rounded-md border border-paper-200 hover:bg-paper-50 text-[11px]" data-ah-page="prev" ${dPrev}>Prev</button>`);
        const start = Math.max(1, page - 2);
        const end   = Math.min(pageCount, page + 2);
        for (let i = start; i <= end; i++) {
            const cls = i === page ? 'bg-wa-deep text-paper-0 font-semibold' : 'hover:bg-paper-50';
            html.push(`<button class="px-2.5 py-1 rounded-md ${cls} text-[11px]" data-ah-page="${i}">${i}</button>`);
        }
        html.push(`<button class="px-2.5 py-1 rounded-md border border-paper-200 hover:bg-paper-50 text-[11px]" data-ah-page="next" ${dNext}>Next</button>`);
        wrap.innerHTML = html.join('');
        wirePagination();
    }

    function wirePagination() {
        document.querySelectorAll('[data-ah-page]').forEach(b => {
            if (b.disabled) return;
            b.addEventListener('click', () => {
                const v = b.dataset.ahPage;
                if (v === 'prev') filters.page = Math.max(1, filters.page - 1);
                else if (v === 'next') filters.page = filters.page + 1;
                else filters.page = parseInt(v, 10) || 1;
                reload();
            });
        });
    }
    wirePagination();

    document.getElementById('ah-range')?.addEventListener('change', e => {
        filters.range = e.target.value; filters.page = 1;
        const exp = document.getElementById('ah-export');
        if (exp) exp.href = `/affiliate-history/export?range=${encodeURIComponent(filters.range)}`;
        reload();
    });

    document.querySelectorAll('#ah-bucket-tabs button').forEach(b => {
        b.addEventListener('click', () => {
            document.querySelectorAll('#ah-bucket-tabs button').forEach(x => {
                x.classList.remove('bg-wa-deep', 'text-paper-0');
                x.classList.add('hover:bg-paper-100');
            });
            b.classList.add('bg-wa-deep', 'text-paper-0');
            b.classList.remove('hover:bg-paper-100');
            filters.bucket = b.dataset.bucket;
            reload();
        });
    });

    let searchT;
    document.getElementById('ah-search')?.addEventListener('input', e => {
        clearTimeout(searchT);
        searchT = setTimeout(() => {
            filters.q = e.target.value.trim();
            filters.page = 1; reload();
        }, 220);
    });

    document.getElementById('ah-clear-filters')?.addEventListener('click', () => {
        filters.q = ''; filters.page = 1;
        const s = document.getElementById('ah-search'); if (s) s.value = '';
        reload();
    });

    // Copy-link buttons (header + sidebar share both share the same handler)
    document.querySelectorAll('#ah-copy-link, .ah-copy-link').forEach(b => {
        b.addEventListener('click', async () => {
            const url = b.dataset.url;
            if (!url) return;
            try { await navigator.clipboard.writeText(url); toast('Share link copied!'); }
            catch (e) { toast('Could not copy.'); }
        });
    });

    let toastEl = null;
    function toast(text) {
        if (!toastEl) {
            toastEl = document.createElement('div');
            toastEl.className = 'fixed bottom-6 right-6 z-50 bg-ink-900 text-paper-0 text-[12px] font-mono px-3 py-2 rounded-lg shadow-card transition-opacity opacity-0';
            document.body.appendChild(toastEl);
        }
        toastEl.textContent = text;
        toastEl.style.opacity = '1';
        clearTimeout(toastEl._t);
        toastEl._t = setTimeout(() => { toastEl.style.opacity = '0'; }, 2200);
    }
}
