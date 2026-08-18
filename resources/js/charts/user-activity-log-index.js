import ApexCharts from 'apexcharts';
import { themeColor } from '../theme-colors.js';

// Base path the page was actually served under (handles a /public install).
// pathname never includes the query string, so it stays the route across
// history.replaceState calls.
const BASE = window.location.pathname.replace(/\/+$/, '');

/**
 * /activity-log page wiring. Mirrors /message-history's structure:
 *   - 3-chart layout: stacked volume bar (auth/writes/other), category
 *     donut (auth vs writes vs reads), category-mix horizontal bar.
 *   - bucket tabs (daily/hourly/weekly) live next to the volume chart.
 *   - filter inputs (range, scope tabs, category select, search) → AJAX
 *     reload via /activity-log?partial=1.
 *   - row click → swap detail panel via /activity-log/{id}.
 *   - copy id, copy payload, export CSV all real.
 */
export default function init() {
    let chartVolume = null;
    let chartDirection = null;
    let chartCategories = null;

    function readJSON(el, attr, fallback) {
        try { return JSON.parse(el.getAttribute(attr)) ?? fallback; }
        catch { return fallback; }
    }

    function buildVolume() {
        const el = document.getElementById('chart-volume');
        if (!el) return;
        const labels = readJSON(el, 'data-labels', []);
        const auth   = readJSON(el, 'data-auth',   []);
        const write  = readJSON(el, 'data-write',  []);
        const other  = readJSON(el, 'data-other',  []);
        chartVolume = new ApexCharts(el, {
            chart: { type:'bar', height:260, toolbar:{show:false}, fontFamily:'Plus Jakarta Sans', stacked:true },
            series: [
                { name:'Auth',   data: auth },
                { name:'Writes', data: write },
                { name:'Other',  data: other },
            ],
            xaxis: { categories: labels, labels:{style:{colors:themeColor('ink-500'),fontSize:'10px',fontFamily:'JetBrains Mono'}}, axisBorder:{show:false}, axisTicks:{show:false} },
            yaxis: { labels:{style:{colors:themeColor('ink-500'),fontSize:'10px',fontFamily:'JetBrains Mono'}} },
            colors: [themeColor('wa-deep'), themeColor('wa-teal'), themeColor('accent-coral')],
            plotOptions: { bar: { borderRadius:6, columnWidth:'48%' } },
            dataLabels: { enabled:false },
            grid: { borderColor:themeColor('paper-100'), strokeDashArray:3 },
            legend: { position:'top', horizontalAlign:'right', fontSize:'11px', fontFamily:'JetBrains Mono', labels:{colors:'#3A5A55'} },
            tooltip:{ y:{formatter:v=>v.toLocaleString()+' events'} },
        });
        chartVolume.render();
    }

    function buildDirection() {
        const el = document.getElementById('chart-direction');
        if (!el) return;
        const auth   = parseInt(el.dataset.auth   || '0', 10);
        const writes = parseInt(el.dataset.writes || '0', 10);
        const reads  = parseInt(el.dataset.reads  || '0', 10);
        const total  = parseInt(el.dataset.total  || '0', 10);
        chartDirection = new ApexCharts(el, {
            chart: { type:'donut', height:200, fontFamily:'Plus Jakarta Sans' },
            series: [auth, writes, reads],
            labels: ['Auth', 'Writes', 'Reads / other'],
            colors: [themeColor('wa-deep'), themeColor('wa-teal'), themeColor('accent-coral')],
            legend: { show:false },
            dataLabels: { enabled:false },
            plotOptions: { pie: { donut: { size:'68%', labels:{ show:true, total:{show:true, label:'Total', fontFamily:'JetBrains Mono', fontSize:'11px', color:themeColor('ink-500'), formatter:()=>total.toLocaleString()}, value:{fontFamily:'Fraunces', fontSize:'22px', color:themeColor('ink-900')} } } } },
            stroke: { width:2, colors:[themeColor('paper-0')] },
        });
        chartDirection.render();
    }

    function buildCategories() {
        const el = document.getElementById('chart-categories');
        if (!el) return;
        const labels = readJSON(el, 'data-labels', []);
        const data   = readJSON(el, 'data-data',   []);
        chartCategories = new ApexCharts(el, {
            chart: { type:'bar', height:220, toolbar:{show:false}, fontFamily:'Plus Jakarta Sans' },
            series: [{ name:'Events', data }],
            xaxis: { categories: labels, labels:{style:{colors:themeColor('ink-500'),fontSize:'10px',fontFamily:'JetBrains Mono'}}, axisBorder:{show:false}, axisTicks:{show:false} },
            yaxis: { labels:{style:{colors:themeColor('ink-500'),fontSize:'10px',fontFamily:'JetBrains Mono'}} },
            colors: [themeColor('wa-deep')],
            plotOptions: { bar: { borderRadius:5, horizontal:true, barHeight:'62%' } },
            dataLabels: { enabled:false },
            grid: { borderColor:themeColor('paper-100'), strokeDashArray:3 },
            tooltip:{ y:{formatter:v=>v.toLocaleString()+' events'} },
        });
        chartCategories.render();
    }

    buildVolume();
    buildDirection();
    buildCategories();

    const filters = {
        range:  document.getElementById('al-range')?.value || '7d',
        scope:  document.querySelector('#al-scope-tabs .al-scope-tab.bg-wa-deep')?.dataset.scope || 'me',
        cat:    document.getElementById('al-category')?.value || 'all',
        bucket: document.querySelector('#al-bucket-tabs .bg-wa-deep')?.dataset.bucket || 'daily',
        q:      document.getElementById('al-search')?.value || '',
        page:   1,
    };

    function buildQuery() {
        const p = new URLSearchParams();
        if (filters.range !== '7d')      p.set('range', filters.range);
        if (filters.scope !== 'me')      p.set('scope', filters.scope);
        if (filters.cat   !== 'all')     p.set('category', filters.cat);
        if (filters.bucket !== 'daily')  p.set('bucket', filters.bucket);
        if (filters.q)                    p.set('q', filters.q);
        if (filters.page > 1)             p.set('page', filters.page);
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
            if (e.name !== 'AbortError') console.error('[activity-log]', e);
        }
    }

    function applyPayload(d) {
        if (d.stats) {
            const total = Math.max(1, d.stats.total);
            setText('[data-al="total"]',  d.stats.total.toLocaleString());
            setText('[data-al="logins"]', d.stats.logins.toLocaleString());
            setText('[data-al="writes"]', d.stats.writes.toLocaleString());
            setText('[data-al="reads"]',  d.stats.reads.toLocaleString());
            setText('[data-al="ips"]',    d.stats.uniqueIps.toLocaleString());
            setText('[data-al="loginsPct"]', Math.round(d.stats.logins / total * 100) + '%');
            setText('[data-al="writesPct"]', Math.round(d.stats.writes / total * 100) + '%');
            setText('[data-al="readsPct"]',  Math.round(d.stats.reads  / total * 100) + '%');
        }
        const tbody = document.getElementById('al-rows');
        if (tbody && d.rowsHtml) tbody.innerHTML = d.rowsHtml;
        wireRows();

        const totalRows = Number(d.total ?? 0);
        document.getElementById('al-results-footer')?.classList.toggle('hidden', totalRows <= 0);
        setText('[data-al="shownRange"]', `${d.shownFrom}–${d.shownTo}`);
        setText('[data-al="totalRows"]',  totalRows.toLocaleString());
        renderPagination(d.page, d.pageCount, totalRows);

        if (chartVolume && d.volume) {
            chartVolume.updateOptions({ xaxis: { categories: d.volume.labels } }, false, false);
            chartVolume.updateSeries([
                { name:'Auth',   data: d.volume.auth },
                { name:'Writes', data: d.volume.write },
                { name:'Other',  data: d.volume.other },
            ]);
        }
        if (chartDirection && d.categoryDonut) {
            chartDirection.updateOptions({
                plotOptions: { pie: { donut: { labels:{ total:{ formatter:()=>d.categoryDonut.total.toLocaleString() } } } } }
            }, false, false);
            chartDirection.updateSeries([d.categoryDonut.auth, d.categoryDonut.writes, d.categoryDonut.reads]);
        }
        if (chartCategories && d.categoryMix) {
            chartCategories.updateOptions({ xaxis: { categories: d.categoryMix.labels } }, false, false);
            chartCategories.updateSeries([{ name:'Events', data: d.categoryMix.data }]);
        }

        if (d.detail) renderDetail(d.detail);
        else renderDetailEmpty();
    }

    function setText(sel, val) { document.querySelectorAll(sel).forEach(el => el.textContent = val); }

    function renderPagination(page, pageCount, totalRows = 0) {
        const wrap = document.getElementById('al-pagination');
        if (!wrap) return;
        if (totalRows <= 0) {
            wrap.innerHTML = '';
            return;
        }
        const html = [];
        const dPrev = page <= 1 ? 'disabled' : '';
        const dNext = page >= pageCount ? 'disabled' : '';
        html.push(`<button class="px-2.5 py-1 rounded-md border border-paper-200 hover:bg-paper-50 text-[11px]" data-al-page="prev" ${dPrev}>Prev</button>`);
        const start = Math.max(1, page - 2);
        const end   = Math.min(pageCount, page + 2);
        for (let i = start; i <= end; i++) {
            const cls = i === page ? 'bg-wa-deep text-paper-0 font-semibold' : 'hover:bg-paper-50';
            html.push(`<button class="px-2.5 py-1 rounded-md ${cls} text-[11px]" data-al-page="${i}">${i}</button>`);
        }
        html.push(`<button class="px-2.5 py-1 rounded-md border border-paper-200 hover:bg-paper-50 text-[11px]" data-al-page="next" ${dNext}>Next</button>`);
        wrap.innerHTML = html.join('');
        wirePagination();
    }

    function wireRows() {
        document.querySelectorAll('.al-row').forEach(row => {
            row.addEventListener('click', () => loadDetail(row.dataset.id));
        });
        document.querySelectorAll('.al-open').forEach(b => {
            b.addEventListener('click', e => { e.stopPropagation(); loadDetail(b.dataset.id); });
        });
    }

    async function loadDetail(id) {
        if (!id) return;
        try {
            const res = await fetch(`${BASE}/${id}`, { headers: { Accept: 'application/json' } });
            if (!res.ok) return;
            const json = await res.json();
            renderDetail(json.detail);
        } catch (e) { /* swallow */ }
    }

    function renderDetail(d) {
        if (!d) { renderDetailEmpty(); return; }
        const ev = document.getElementById('al-event-id');
        if (ev) ev.textContent = '#' + d.id;
        const icon = document.getElementById('d-icon');
        if (icon) {
            icon.innerHTML = d.iconHtml;
            icon.className = `w-10 h-10 rounded-lg ${d.iconBg} ${d.iconFg} grid place-items-center shrink-0`;
        }
        setText('#d-action', d.actionLabel);
        setText('#d-action-key', d.action);
        setText('#d-category', d.categoryLabel);
        setText('#d-layer', d.layer);
        setText('#d-actor', d.actorName);
        setText('#d-when', d.createdAt);
        setText('#d-subject', d.subject);
        setText('#d-ip', d.ip);
        setText('#d-ua', d.fullUserAgent);
        const pl = document.getElementById('d-payload');
        if (pl) pl.textContent = d.payloadJson || '{}';
        const c1 = document.getElementById('d-copy-id');
        if (c1) { c1.disabled = false; c1.dataset.id = d.id; }
        const c2 = document.getElementById('d-copy-payload');
        if (c2) { c2.disabled = false; c2.dataset.payload = d.payloadJson || '{}'; }
    }

    function renderDetailEmpty() {
        const body = document.getElementById('al-detail-body');
        if (body) {
            body.innerHTML = `
              <div class="bg-paper-0 border border-dashed border-paper-200 rounded-2xl p-8 md:p-10 shadow-card text-center">
                <div class="font-serif text-[22px] md:text-[24px] leading-tight text-ink-900">No data found</div>
                <p class="mt-2 text-[13px] text-ink-500 max-w-2xl mx-auto">No events found. Sign in, switch workspaces, or work in the inbox and events will appear here.</p>
              </div>`;
        }
        const c1 = document.getElementById('d-copy-id');      if (c1) c1.disabled = true;
        const c2 = document.getElementById('d-copy-payload'); if (c2) c2.disabled = true;
    }

    // ── filter inputs ─────────────────────────────────────────────
    document.getElementById('al-range')?.addEventListener('change', e => {
        filters.range = e.target.value; filters.page = 1;
        const exp = document.getElementById('al-export');
        if (exp) exp.href = `/activity-log/export?range=${encodeURIComponent(filters.range)}&scope=${encodeURIComponent(filters.scope)}`;
        reload();
    });

    document.querySelectorAll('#al-scope-tabs .al-scope-tab').forEach(b => {
        b.addEventListener('click', () => {
            document.querySelectorAll('#al-scope-tabs .al-scope-tab').forEach(x => {
                x.classList.remove('bg-wa-deep', 'text-paper-0');
                x.classList.add('text-ink-600', 'hover:bg-paper-100');
            });
            b.classList.add('bg-wa-deep', 'text-paper-0');
            b.classList.remove('text-ink-600', 'hover:bg-paper-100');
            filters.scope = b.dataset.scope; filters.page = 1;
            const exp = document.getElementById('al-export');
            if (exp) exp.href = `/activity-log/export?range=${encodeURIComponent(filters.range)}&scope=${encodeURIComponent(filters.scope)}`;
            reload();
        });
    });

    document.getElementById('al-category')?.addEventListener('change', e => {
        filters.cat = e.target.value; filters.page = 1; reload();
    });

    document.querySelectorAll('#al-bucket-tabs button').forEach(b => {
        b.addEventListener('click', () => {
            document.querySelectorAll('#al-bucket-tabs button').forEach(x => {
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
    document.getElementById('al-search')?.addEventListener('input', e => {
        clearTimeout(searchT);
        searchT = setTimeout(() => {
            filters.q = e.target.value.trim();
            filters.page = 1; reload();
        }, 220);
    });

    document.getElementById('al-clear-filters')?.addEventListener('click', () => {
        filters.q = ''; filters.cat = 'all'; filters.page = 1;
        const s = document.getElementById('al-search'); if (s) s.value = '';
        const c = document.getElementById('al-category'); if (c) c.value = 'all';
        reload();
    });

    document.getElementById('al-refresh')?.addEventListener('click', () => reload());

    function wirePagination() {
        document.querySelectorAll('[data-al-page]').forEach(b => {
            if (b.disabled) return;
            b.addEventListener('click', () => {
                const v = b.dataset.alPage;
                if (v === 'prev') filters.page = Math.max(1, filters.page - 1);
                else if (v === 'next') filters.page = filters.page + 1;
                else filters.page = parseInt(v, 10) || 1;
                reload();
            });
        });
    }
    wirePagination();

    document.getElementById('al-pick-all')?.addEventListener('change', e => {
        document.querySelectorAll('.al-pick').forEach(cb => { cb.checked = e.target.checked; });
    });

    document.getElementById('d-copy-id')?.addEventListener('click', async () => {
        const id = document.getElementById('d-copy-id').dataset.id;
        if (!id) return;
        try { await navigator.clipboard.writeText('audit-' + id); toast('ID copied.'); }
        catch (e) { toast('Could not copy.'); }
    });
    document.getElementById('d-copy-payload')?.addEventListener('click', async () => {
        const payload = document.getElementById('d-copy-payload').dataset.payload || '{}';
        try { await navigator.clipboard.writeText(payload); toast('Payload copied.'); }
        catch (e) { toast('Could not copy.'); }
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

    wireRows();
}
