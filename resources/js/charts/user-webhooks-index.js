import ApexCharts from 'apexcharts';
import { themeColor } from '../theme-colors.js';

/*
 * /webhooks index — AJAX glue.
 *
 * Same pattern as the other dynamic pages in this app: status tabs +
 * search push state into the URL and re-fetch /webhooks?...&partial=1
 * for a JSON snippet that contains:
 *
 *   { ok, rows, stats, statusCounts, eventMix, recent, shown }
 *
 * Also wires per-row Test fire / Pause / Delete actions to the
 * controller endpoints and toasts on success/failure.
 */

const $ = (id) => document.getElementById(id);

function csrf() {
    return document.querySelector('meta[name=csrf-token]')?.content || '';
}
function debounce(fn, ms) { let t; return (...a) => { clearTimeout(t); t = setTimeout(() => fn(...a), ms); }; }

function readState() {
    const el = document.querySelector('[data-wh-state]');
    return {
        status: el?.dataset.whStatus || 'all',
        q:      el?.dataset.whSearch || '',
        page:   el?.dataset.whPage   || '1',
    };
}
function writeState(s) {
    const el = document.querySelector('[data-wh-state]');
    if (!el) return;
    el.dataset.whStatus = s.status;
    el.dataset.whSearch = s.q;
    el.dataset.whPage   = s.page || '1';
}

function paintActive(state) {
    document.querySelectorAll('[data-wh-filter="status"]').forEach((b) => {
        const active = b.dataset.whValue === state.status;
        b.classList.toggle('bg-wa-deep', active);
        b.classList.toggle('text-paper-0', active);
        b.classList.toggle('text-ink-600', !active);
        b.classList.toggle('hover:bg-paper-100', !active);
    });
    const search = $('wh-search');
    if (search && document.activeElement !== search) search.value = state.q || '';
}

function applyStats(stats) {
    if (!stats) return;
    Object.entries(stats).forEach(([k, v]) => {
        document.querySelectorAll(`[data-stat="${k}"]`).forEach((el) => {
            if (k === 'successRate')      el.textContent = Number(v).toFixed(1) + '%';
            else if (k === 'latencyP95')  el.textContent = v + 'ms';
            else                          el.textContent = Number(v).toLocaleString();
        });
    });
}

function applyStatusCounts(counts) {
    if (!counts) return;
    Object.entries(counts).forEach(([k, v]) => {
        const el = document.querySelector(`[data-wh-status-count="${k}"]`);
        if (el) el.textContent = Number(v).toLocaleString();
    });
}

function applyEventMix(mix) {
    const wrap = $('wh-event-mix');
    if (!wrap) return;
    if (!Array.isArray(mix) || mix.length === 0) {
        wrap.innerHTML = `
          <div class="bg-paper-0 border border-dashed border-paper-200 rounded-2xl p-8 md:p-10 shadow-card text-center">
            <div class="font-serif text-[22px] md:text-[24px] leading-tight text-ink-900">No data found</div>
            <p class="mt-2 text-[13px] text-ink-500 max-w-2xl mx-auto">No recent events found. Events fired in the last 24 hours will appear here.</p>
          </div>`;
        return;
    }
    wrap.innerHTML = mix.map((row) => `
        <div>
          <div class="flex items-center justify-between text-[12px] mb-1">
            <span class="font-mono text-ink-700">${row.name}</span>
            <span class="font-mono text-ink-900">${Number(row.count).toLocaleString()}</span>
          </div>
          <div class="h-2 bg-paper-100 rounded-full overflow-hidden">
            <div class="h-full ${row.color}" style="width:${row.pct}%"></div>
          </div>
        </div>
    `).join('');
}

async function fetchPartial(state, { silent = false } = {}) {
    const params = new URLSearchParams();
    if (state.status !== 'all') params.append('status', state.status);
    if (state.q)                params.append('q', state.q);
    if (Number(state.page || 1) > 1) params.append('page', state.page);
    const visible = '/webhooks' + (params.toString() ? '?' + params.toString() : '');
    history.pushState({}, '', visible);

    params.append('partial', '1');
    const rows = $('wh-rows');
    if (rows) rows.classList.add('opacity-60');
    try {
        const res = await fetch('/webhooks?' + params.toString(), {
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        });
        if (!res.ok) throw new Error('HTTP ' + res.status);
        const data = await res.json();
        if (rows) rows.innerHTML = data.rows;
        applyStats(data.stats);
        applyStatusCounts(data.statusCounts);
        applyEventMix(data.eventMix);
        const recent = $('wh-recent');
        if (recent && data.recent) recent.innerHTML = data.recent;
        const totalCount = Number(data.total ?? 0);
        $('wh-results-footer')?.classList.toggle('hidden', totalCount <= 0);
        const shown = document.querySelector('[data-wh-shown]');
        if (shown) shown.textContent = data.shown ?? '0';
        const total = document.querySelector('[data-wh-total]');
        if (total) total.textContent = totalCount.toLocaleString();
        const pager = $('wh-pagination');
        if (pager) pager.innerHTML = data.pagination || '';
        if (data.page) {
            state.page = String(data.page);
            writeState(state);
        }
        wireRowActions();
        wirePagination();
        if (!silent) window.WaToaster?.info?.('Refreshed', { duration: 1200 });
    } catch (e) {
        window.WaToaster?.error?.('Could not refresh: ' + e.message);
    } finally {
        if (rows) rows.classList.remove('opacity-60');
    }
}

const debouncedFetch = debounce((s) => fetchPartial(s, { silent: true }), 220);

async function rowAction(method, url, okMsg) {
    try {
        const res = await fetch(url, {
            method,
            headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf() },
        });
        if (!res.ok) throw new Error('HTTP ' + res.status);
        const data = await res.json();
        if (okMsg) window.WaToaster?.success?.(okMsg);
        return data;
    } catch (e) {
        window.WaToaster?.error?.('Action failed: ' + e.message);
    }
}

function wireRowActions() {
    document.querySelectorAll('[data-hook-test]').forEach((b) => {
        if (b.__wired) return; b.__wired = true;
        b.addEventListener('click', async () => {
            const id = b.dataset.hookTest;
            const data = await rowAction('POST', `/webhooks/${id}/test-fire`, null);
            if (data?.ok) {
                window.WaToaster?.success?.(`Test fired / ${data.statusCode || '/'} / ${data.latencyMs}ms`);
                await fetchPartial(readState(), { silent: true });
            }
        });
    });
    document.querySelectorAll('[data-hook-toggle]').forEach((b) => {
        if (b.__wired) return; b.__wired = true;
        b.addEventListener('click', async () => {
            const id = b.dataset.hookToggle;
            const data = await rowAction('POST', `/webhooks/${id}/toggle`, null);
            if (data?.ok) {
                await fetchPartial(readState(), { silent: true });
                window.WaToaster?.success?.(data.status ? 'Webhook resumed' : 'Webhook paused');
            }
        });
    });
    document.querySelectorAll('[data-hook-delete]').forEach((b) => {
        if (b.__wired) return; b.__wired = true;
        b.addEventListener('click', () => {
            const id   = b.dataset.hookDelete;
            const name = b.dataset.name || 'this webhook';
            const run  = async () => {
                const data = await rowAction('DELETE', `/webhooks/${id}`, 'Webhook deleted');
                if (data?.ok) await fetchPartial(readState(), { silent: true });
            };
            if (typeof window.confirmDialog === 'function') {
                window.confirmDialog({
                    title: 'Delete webhook?',
                    message: `Delete "${name}"? This can't be undone.`,
                    confirmText: 'Delete',
                    tone: 'danger',
                    onConfirm: run,
                });
            } else if (window.confirm(`Delete "${name}"?`)) {
                run();
            }
        });
    });
}

function wirePagination() {
    document.querySelectorAll('a[data-wh-page]').forEach((link) => {
        if (link.__wired) return;
        link.__wired = true;
        link.addEventListener('click', (e) => {
            e.preventDefault();
            const state = readState();
            state.page = link.dataset.whPage || '1';
            writeState(state);
            fetchPartial(state, { silent: true });
        });
    });
}

export default function init() {
    document.querySelectorAll('[data-wh-filter="status"]').forEach((b) => {
        b.addEventListener('click', (e) => {
            e.preventDefault();
            const state = readState();
            state.status = b.dataset.whValue;
            state.page = '1';
            writeState(state);
            paintActive(state);
            fetchPartial(state, { silent: true });
        });
    });
    $('wh-search')?.addEventListener('input', (e) => {
        const state = readState();
        state.q = e.target.value.trim();
        state.page = '1';
        writeState(state);
        debouncedFetch(state);
    });

    wireRowActions();
    wirePagination();

    window.addEventListener('popstate', () => {
        const params = new URLSearchParams(window.location.search);
        const state = {
            status: params.get('status') || 'all',
            q:      params.get('q')      || '',
            page:   params.get('page')   || '1',
        };
        writeState(state);
        paintActive(state);
        fetchPartial(state, { silent: true });
    });

    // Activity chart (placeholder series; safe to leave static for v1).
    const chartEl = document.querySelector('#chart-activity');
    if (chartEl) {
        new ApexCharts(chartEl, {
            chart:  { type: 'area', height: 260, toolbar: { show: false }, fontFamily: 'Plus Jakarta Sans' },
            series: [
                { name: 'Success', data: [218,242,288,312,378,412,388,452,498,524,562,608,592,648,712,694,742,768,792,812,826,798,742,684] },
                { name: 'Failed',  data: [2,1,3,2,4,3,2,5,3,2,4,8,12,6,4,2,3,5,4,2,3,4,2,1] },
            ],
            xaxis: { categories: Array.from({ length: 24 }, (_, i) => String(i).padStart(2, '0') + 'h'), labels: { style: { colors: themeColor('ink-500'), fontSize: '10px', fontFamily: 'JetBrains Mono' } }, axisBorder: { show: false }, axisTicks: { show: false } },
            yaxis: { labels: { style: { colors: themeColor('ink-500'), fontSize: '10px', fontFamily: 'JetBrains Mono' } } },
            // Series 2 is the failure series — coral stays literal as a semantic error colour.
            colors: [themeColor('wa-deep'), '#E87A5D'],
            stroke: { curve: 'smooth', width: 2 },
            fill:   { type: 'gradient', gradient: { shadeIntensity: 1, opacityFrom: 0.3, opacityTo: 0.05 } },
            grid:   { borderColor: themeColor('paper-100'), strokeDashArray: 3 },
            dataLabels: { enabled: false },
            legend: { position: 'top', horizontalAlign: 'right', fontSize: '11px', fontFamily: 'JetBrains Mono', labels: { colors: '#3A5A55' } },
        }).render();
    }
}
