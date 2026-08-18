import ApexCharts from 'apexcharts';
import { themeColor } from '../theme-colors.js';

// Base path the page was actually served under (handles a /public install).
// pathname never includes the query string, so it stays the route across
// history.replaceState calls.
const BASE = window.location.pathname.replace(/\/+$/, '');

/**
 * /message-history page wiring. Every interactive element on the page
 * routes through here:
 *   - range select, dir tabs, type/device selects, search box, bucket
 *     tabs, pagination → AJAX reload via /message-history?partial=1
 *   - row click → swap detail panel from row's data-* attrs (cheap),
 *     followed by a fetch to /message-history/{id} for the full body
 *     + timeline + metadata.
 *   - export, archive, copy id, resend → real HTTP endpoints.
 */
export default function init() {
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';

    // chart instances kept around so filter changes update in place
    let chartVolume = null;
    let chartDirection = null;
    let chartTypes = null;

    function readJSON(el, attr, fallback) {
        try { return JSON.parse(el.getAttribute(attr)) ?? fallback; }
        catch { return fallback; }
    }

    function buildVolume() {
        const el = document.getElementById('chart-volume');
        if (!el) return;
        const labels = readJSON(el, 'data-labels', []);
        const sent   = readJSON(el, 'data-sent',   []);
        const rcv    = readJSON(el, 'data-received', []);
        const fail   = readJSON(el, 'data-failed', []);
        chartVolume = new ApexCharts(el, {
            chart: { type:'bar', height:260, toolbar:{show:false}, fontFamily:'Plus Jakarta Sans', stacked:true },
            series: [
                { name:'Sent',     data: sent },
                { name:'Received', data: rcv  },
                { name:'Failed',   data: fail },
            ],
            xaxis: { categories: labels, labels:{style:{colors:themeColor('ink-500'),fontSize:'10px',fontFamily:'JetBrains Mono'}}, axisBorder:{show:false}, axisTicks:{show:false} },
            yaxis: { labels:{style:{colors:themeColor('ink-500'),fontSize:'10px',fontFamily:'JetBrains Mono'}} },
            colors: [themeColor('wa-deep'), themeColor('wa-teal'), themeColor('accent-coral')],
            plotOptions: { bar: { borderRadius:6, columnWidth:'48%' } },
            dataLabels: { enabled:false },
            grid: { borderColor:themeColor('paper-100'), strokeDashArray:3 },
            legend: { position:'top', horizontalAlign:'right', fontSize:'11px', fontFamily:'JetBrains Mono', labels:{colors:'#3A5A55'} },
            tooltip:{ y:{formatter:v => v.toLocaleString()+' msg'} },
        });
        chartVolume.render();
    }

    function buildDirection() {
        const el = document.getElementById('chart-direction');
        if (!el) return;
        const sent  = parseInt(el.dataset.sent || '0', 10);
        const rcv   = parseInt(el.dataset.received || '0', 10);
        const fail  = parseInt(el.dataset.failed || '0', 10);
        const total = parseInt(el.dataset.total || '0', 10);
        chartDirection = new ApexCharts(el, {
            chart: { type:'donut', height:200, fontFamily:'Plus Jakarta Sans' },
            series: [sent, rcv, fail],
            labels: ['Sent','Received','Failed'],
            colors: [themeColor('wa-deep'), themeColor('wa-teal'), themeColor('accent-coral')],
            legend: { show:false },
            dataLabels: { enabled:false },
            plotOptions: { pie: { donut: { size:'68%', labels:{ show:true, total:{show:true, label:'Total', fontFamily:'JetBrains Mono', fontSize:'11px', color:themeColor('ink-500'), formatter:()=>total.toLocaleString()}, value:{fontFamily:'Fraunces', fontSize:'22px', color:themeColor('ink-900')} } } } },
            stroke: { width:2, colors:[themeColor('paper-0')] }
        });
        chartDirection.render();
    }

    function buildTypes() {
        const el = document.getElementById('chart-types');
        if (!el) return;
        const labels = readJSON(el, 'data-labels', []);
        const data   = readJSON(el, 'data-data',   []);
        chartTypes = new ApexCharts(el, {
            chart: { type:'bar', height:220, toolbar:{show:false}, fontFamily:'Plus Jakarta Sans' },
            series: [{ name:'Messages', data }],
            xaxis: { categories: labels, labels:{style:{colors:themeColor('ink-500'),fontSize:'10px',fontFamily:'JetBrains Mono'}}, axisBorder:{show:false}, axisTicks:{show:false} },
            yaxis: { labels:{style:{colors:themeColor('ink-500'),fontSize:'10px',fontFamily:'JetBrains Mono'}} },
            colors: [themeColor('wa-deep')],
            plotOptions: { bar: { borderRadius:5, horizontal:true, barHeight:'62%' } },
            dataLabels: { enabled:false },
            grid: { borderColor:themeColor('paper-100'), strokeDashArray:3 },
            tooltip:{ y:{formatter:v => v.toLocaleString()+' msg'} }
        });
        chartTypes.render();
    }

    buildVolume();
    buildDirection();
    buildTypes();

    // ── filter state ──────────────────────────────────────────────
    const filters = {
        range:  document.getElementById('mh-range')?.value || '7d',
        dir:    document.querySelector('#dir-tabs .dir-tab.bg-wa-deep')?.dataset.dir || 'all',
        type:   document.getElementById('mh-type')?.value || 'all',
        device_id: document.getElementById('mh-device')?.value || '',
        q:      document.getElementById('mh-search')?.value || '',
        bucket: document.querySelector('#mh-bucket-tabs .bg-wa-deep')?.dataset.bucket || 'daily',
        page:   1,
    };

    function buildQuery() {
        const params = new URLSearchParams();
        if (filters.range && filters.range !== '7d') params.set('range', filters.range);
        if (filters.dir && filters.dir !== 'all')    params.set('dir', filters.dir);
        if (filters.type && filters.type !== 'all')  params.set('type', filters.type);
        if (filters.device_id)                        params.set('device_id', filters.device_id);
        if (filters.q)                                params.set('q', filters.q);
        if (filters.bucket && filters.bucket !== 'daily') params.set('bucket', filters.bucket);
        if (filters.page > 1)                         params.set('page', filters.page);
        return params;
    }

    let reloadAbort = null;
    async function reload() {
        try {
            reloadAbort?.abort();
            reloadAbort = new AbortController();
            const params = buildQuery();
            params.set('partial', '1');
            const res = await fetch(BASE + '?' + params.toString(), {
                headers: { Accept: 'application/json' },
                signal: reloadAbort.signal,
            });
            if (!res.ok) throw new Error('reload failed');
            const json = await res.json();
            applyPayload(json.data);

            // sync URL without reloading
            const visible = buildQuery().toString();
            history.replaceState(null, '', BASE + (visible ? '?' + visible : ''));
        } catch (e) {
            if (e.name !== 'AbortError') console.error('[message-history]', e);
        }
    }

    function applyPayload(d) {
        if (d.stats) {
            setText('[data-mh="total"]',     d.stats.total.toLocaleString());
            setText('[data-mh="sent"]',      d.stats.sent.toLocaleString());
            setText('[data-mh="received"]',  d.stats.received.toLocaleString());
            setText('[data-mh="failed"]',    d.stats.failed.toLocaleString());
            setText('[data-mh="sentPct"]',     d.stats.sentPct + '%');
            setText('[data-mh="receivedPct"]', d.stats.receivedPct + '%');
            setText('[data-mh="failPct"]',     d.stats.failPct + '%');
            setText('[data-mh="avgReply"]',    d.stats.avgReplyHuman || '—');
        }
        if (d.dirCounts) {
            for (const k of ['all','out','in','fail']) {
                const el = document.querySelector(`[data-mh-dir-count="${k}"]`);
                if (el) el.textContent = (d.dirCounts[k] ?? 0).toLocaleString();
            }
        }
        const tbody = document.getElementById('msg-rows');
        if (tbody && d.rowsHtml) tbody.innerHTML = d.rowsHtml;
        wireRows();

        const totalRows = Number(d.total ?? 0);
        document.getElementById('mh-results-footer')?.classList.toggle('hidden', totalRows <= 0);
        setText('[data-mh="shownRange"]', `${d.shownFrom}–${d.shownTo}`);
        setText('[data-mh="totalRows"]', totalRows.toLocaleString());
        renderPagination(d.page, d.pageCount, totalRows);

        if (chartVolume && d.volume) {
            chartVolume.updateOptions({ xaxis: { categories: d.volume.labels } }, false, false);
            chartVolume.updateSeries([
                { name:'Sent',     data: d.volume.sent },
                { name:'Received', data: d.volume.received },
                { name:'Failed',   data: d.volume.failed },
            ]);
        }
        if (chartDirection && d.direction) {
            chartDirection.updateOptions({
                plotOptions: { pie: { donut: { labels:{ total:{ formatter:()=>d.direction.total.toLocaleString() } } } } }
            }, false, false);
            chartDirection.updateSeries([d.direction.sent, d.direction.received, d.direction.failed]);
        }
        if (chartTypes && d.typeMix) {
            chartTypes.updateOptions({ xaxis: { categories: d.typeMix.labels } }, false, false);
            chartTypes.updateSeries([{ name:'Messages', data: d.typeMix.data }]);
        }

        if (d.detail) renderDetail(d.detail);
        else renderDetailEmpty();
    }

    function setText(sel, value) {
        document.querySelectorAll(sel).forEach(el => el.textContent = value);
    }

    function renderPagination(page, pageCount, totalRows = 0) {
        const wrap = document.getElementById('mh-pagination');
        if (!wrap) return;
        if (totalRows <= 0) {
            wrap.innerHTML = '';
            return;
        }
        const buttons = [];
        const disabledPrev = page <= 1 ? 'disabled' : '';
        const disabledNext = page >= pageCount ? 'disabled' : '';
        buttons.push(`<button class="px-2.5 py-1 rounded-md border border-paper-200 hover:bg-paper-50 text-[11px]" data-mh-page="prev" ${disabledPrev}>Prev</button>`);
        const start = Math.max(1, page - 2);
        const end   = Math.min(pageCount, page + 2);
        for (let i = start; i <= end; i++) {
            const cls = i === page ? 'bg-wa-deep text-paper-0 font-semibold' : 'hover:bg-paper-50';
            buttons.push(`<button class="px-2.5 py-1 rounded-md ${cls} text-[11px]" data-mh-page="${i}">${i}</button>`);
        }
        buttons.push(`<button class="px-2.5 py-1 rounded-md border border-paper-200 hover:bg-paper-50 text-[11px]" data-mh-page="next" ${disabledNext}>Next</button>`);
        wrap.innerHTML = buttons.join('');
        wirePagination();
    }

    function wireRows() {
        document.querySelectorAll('.msg-row').forEach(row => {
            row.addEventListener('click', () => selectRow(row));
        });
    }

    async function selectRow(row) {
        const ds = row.dataset;
        document.querySelectorAll('.msg-row').forEach(r => r.classList.remove('bg-wa-deep/5'));
        row.classList.add('bg-wa-deep/5');

        const av = document.getElementById('d-avatar');
        if (av) {
            av.textContent = ds.initials || '··';
            av.className = `w-10 h-10 rounded-full bg-gradient-to-br ${ds.avatar || 'from-wa-teal to-wa-deep'} text-paper-0 grid place-items-center text-[12px] font-semibold`;
        }
        setText('#d-name', ds.name || '—');
        setText('#d-phone', ds.phone || '—');
        setText('#d-direction', ds.direction || '—');
        setText('#d-status', ds.status || '—');
        setText('#d-type', ds.type || '—');
        setText('#d-time', ds.time || '—');
        setText('#d-body', ds.body || '—');

        try {
            const res = await fetch(`${BASE}/${ds.msg}`, { headers: { Accept: 'application/json' } });
            if (!res.ok) return;
            const json = await res.json();
            renderDetail(json.detail);
        } catch (e) { /* swallowed — optimistic data is still on screen */ }
    }

    function renderDetail(detail) {
        if (!detail) { renderDetailEmpty(); return; }
        const av = document.getElementById('d-avatar');
        if (av) {
            av.textContent = detail.initials || '··';
            av.className = `w-10 h-10 rounded-full bg-gradient-to-br ${detail.avatar} text-paper-0 grid place-items-center text-[12px] font-semibold`;
        }
        setText('#d-name', detail.name);
        setText('#d-phone', detail.phone || '—');
        setText('#d-direction', detail.directionLabel);
        setText('#d-status', detail.statusLabel);
        setText('#d-type', detail.typeLabel);
        setText('#d-time', detail.time);
        setText('#d-body', detail.body || '—');

        const tl = document.getElementById('d-timeline');
        if (tl && Array.isArray(detail.timeline)) {
            tl.innerHTML = detail.timeline.map(s => {
                const dot = s.state === 'done' ? 'bg-wa-green' : (s.state === 'fail' ? 'bg-accent-coral' : 'bg-paper-200');
                return `<li class="flex items-center justify-between"><span class="flex items-center gap-1.5"><span class="w-1.5 h-1.5 rounded-full ${dot}"></span>${escape(s.label)}</span><span class="font-mono text-ink-700">${escape(s.time)}</span></li>`;
            }).join('');
        }

        const md = document.getElementById('d-metadata');
        if (md && Array.isArray(detail.metadata)) {
            md.innerHTML = detail.metadata.map(r => {
                return `<div class="flex items-center justify-between"><dt class="text-ink-500">${escape(r.label)}</dt><dd class="font-mono text-ink-900 truncate ml-2">${escape(r.value)}</dd></div>`;
            }).join('');
        }

        const rb = document.getElementById('d-resend');
        if (rb) { rb.disabled = !detail.canResend; rb.dataset.id = detail.id; }
        const cb = document.getElementById('d-copy-id');
        if (cb) { cb.disabled = false; cb.dataset.id = detail.id; }
    }

    function renderDetailEmpty() {
        const body = document.getElementById('mh-detail-body');
        if (body) {
            body.innerHTML = `<div class="bg-paper-0 border border-dashed border-paper-200 rounded-2xl p-5 text-center"><div class="font-serif text-[18px] leading-tight">Select a message</div><p class="mt-1 text-[12px] text-ink-500">Click any row to inspect its delivery timeline and metadata.</p></div>`;
        }
        const rb = document.getElementById('d-resend');  if (rb) rb.disabled = true;
        const cb = document.getElementById('d-copy-id'); if (cb) cb.disabled = true;
    }

    function escape(s) {
        return String(s ?? '').replace(/[&<>"']/g, c => ({ '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;' })[c]);
    }

    // ── filter inputs ─────────────────────────────────────────────
    document.getElementById('mh-range')?.addEventListener('change', e => {
        filters.range = e.target.value;
        filters.page = 1;
        const exp = document.getElementById('mh-export');
        if (exp) exp.href = `/message-history/export?range=${encodeURIComponent(filters.range)}`;
        reload();
    });

    document.getElementById('mh-type')?.addEventListener('change', e => {
        filters.type = e.target.value;
        filters.page = 1; reload();
    });
    document.getElementById('mh-device')?.addEventListener('change', e => {
        filters.device_id = e.target.value;
        filters.page = 1; reload();
    });

    let searchT;
    document.getElementById('mh-search')?.addEventListener('input', e => {
        clearTimeout(searchT);
        searchT = setTimeout(() => {
            filters.q = e.target.value.trim();
            filters.page = 1;
            reload();
        }, 220);
    });

    // ── direction tabs ────────────────────────────────────────────
    document.querySelectorAll('#dir-tabs .dir-tab').forEach(b => {
        b.addEventListener('click', () => {
            document.querySelectorAll('#dir-tabs .dir-tab').forEach(x => {
                x.classList.remove('bg-wa-deep','text-paper-0');
                x.classList.add('text-ink-600','hover:bg-paper-100');
            });
            b.classList.add('bg-wa-deep','text-paper-0');
            b.classList.remove('text-ink-600','hover:bg-paper-100');
            filters.dir = b.dataset.dir;
            filters.page = 1;
            reload();
        });
    });

    // ── chart bucket tabs (daily / hourly / weekly) ───────────────
    document.querySelectorAll('#mh-bucket-tabs button').forEach(b => {
        b.addEventListener('click', () => {
            document.querySelectorAll('#mh-bucket-tabs button').forEach(x => {
                x.classList.remove('bg-wa-deep', 'text-paper-0');
                x.classList.add('hover:bg-paper-100');
            });
            b.classList.add('bg-wa-deep', 'text-paper-0');
            b.classList.remove('hover:bg-paper-100');
            filters.bucket = b.dataset.bucket;
            reload();
        });
    });

    // ── pagination ────────────────────────────────────────────────
    function wirePagination() {
        document.querySelectorAll('[data-mh-page]').forEach(b => {
            if (b.disabled) return;
            b.addEventListener('click', () => {
                const v = b.dataset.mhPage;
                if (v === 'prev') filters.page = Math.max(1, filters.page - 1);
                else if (v === 'next') filters.page = filters.page + 1;
                else filters.page = parseInt(v, 10) || 1;
                reload();
            });
        });
    }
    wirePagination();

    // ── reset filters ─────────────────────────────────────────────
    document.getElementById('mh-clear-filters')?.addEventListener('click', () => {
        filters.q = ''; filters.dir = 'all'; filters.type = 'all'; filters.device_id = ''; filters.page = 1;
        const s = document.getElementById('mh-search'); if (s) s.value = '';
        const t = document.getElementById('mh-type');   if (t) t.value = 'all';
        const dv = document.getElementById('mh-device');if (dv) dv.value = '';
        document.querySelectorAll('#dir-tabs .dir-tab').forEach(x => {
            const isAll = x.dataset.dir === 'all';
            x.classList.toggle('bg-wa-deep', isAll);
            x.classList.toggle('text-paper-0', isAll);
            x.classList.toggle('text-ink-600', !isAll);
            x.classList.toggle('hover:bg-paper-100', !isAll);
        });
        reload();
    });

    // ── select-all checkbox ───────────────────────────────────────
    document.getElementById('mh-pick-all')?.addEventListener('change', e => {
        document.querySelectorAll('.msg-pick').forEach(cb => { cb.checked = e.target.checked; });
    });

    // ── archive selected ──────────────────────────────────────────
    document.getElementById('mh-archive')?.addEventListener('click', async () => {
        const ids = Array.from(document.querySelectorAll('.msg-pick:checked')).map(cb => cb.value);
        if (ids.length === 0) {
            toast('Select messages first to archive.');
            return;
        }
        try {
            const res = await fetch(BASE + '/archive', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, Accept: 'application/json' },
                body: JSON.stringify({ ids }),
            });
            const json = await res.json();
            toast(json.ok ? `Archived ${json.archived ?? ids.length} message(s).` : (json.message || 'Archive failed.'));
            if (json.ok) reload();
        } catch (e) {
            toast('Archive failed.');
        }
    });

    // ── copy id ───────────────────────────────────────────────────
    document.getElementById('d-copy-id')?.addEventListener('click', async () => {
        const id = document.getElementById('d-copy-id').dataset.id;
        if (!id) return;
        try {
            await navigator.clipboard.writeText('msg-' + id);
            toast('ID copied to clipboard.');
        } catch (e) {
            toast('Could not copy.');
        }
    });

    // ── resend ────────────────────────────────────────────────────
    document.getElementById('d-resend')?.addEventListener('click', async () => {
        const id = document.getElementById('d-resend').dataset.id;
        if (!id) return;
        try {
            const res = await fetch(`${BASE}/${id}/resend`, {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': csrf, Accept: 'application/json' },
            });
            const json = await res.json();
            toast(json.message || (json.ok ? 'Re-queued.' : 'Resend failed.'));
            if (json.ok) reload();
        } catch (e) {
            toast('Resend failed.');
        }
    });

    // ── tiny inline toast (no design dependency) ──────────────────
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
