import ApexCharts from 'apexcharts';
import { themeColor } from '../theme-colors.js';

export default function init() {
    // -----------------------------------------------------------------
    // AJAX helpers — wrap the campaign-action / delete forms so the
    // operator can pause / resume / send-now / delete without leaving
    // the analytics page.
    // -----------------------------------------------------------------
    async function ajaxSubmit(form, options = {}) {
      const formData = new FormData(form);
      const method = (formData.get('_method') || form.method || 'POST').toUpperCase();
      formData.delete('_method');
      const csrf = document.querySelector('meta[name="csrf-token"]')?.content || formData.get('_token');
      const headers = { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' };
      if (csrf) headers['X-CSRF-TOKEN'] = csrf;
      let init = { method: method === 'GET' ? 'GET' : 'POST', headers };
      if (method !== 'GET' && method !== 'POST') formData.append('_method', method);
      if (method !== 'GET') init.body = formData;
      let res;
      try { res = await fetch(form.action, init); }
      catch (e) { window.toast?.('Network error.', 'error'); return null; }
      let json = null;
      try { json = await res.json(); } catch (_) {}
      if (!res.ok) {
        window.toast?.(json?.message || `Request failed (${res.status}).`, 'error');
        return null;
      }
      if (json?.message && !options.silent) window.toast?.(json.message, 'success');
      return json;
    }

    document.querySelectorAll('form[data-ajax="campaign-action"]').forEach((form) => {
      form.addEventListener('submit', (e) => {
        e.preventDefault();
        const confirmMsg = form.dataset.confirm;
        const action = form.dataset.actionLabel || form.querySelector('button[type="submit"]')?.textContent?.trim() || 'Continue';
        const run = async () => {
          const json = await ajaxSubmit(form);
          if (json && json.ok && json.status) {
            const pill = document.getElementById('campaignStatusPill');
            if (pill) pill.textContent = json.status.charAt(0).toUpperCase() + json.status.slice(1);
          }
        };
        if (confirmMsg) {
          window.confirmDialog?.({
            title: action + '?',
            message: confirmMsg,
            confirmText: action,
            cancelText: 'Cancel',
            tone: 'info',
            onConfirm: run,
          });
          return;
        }
        run();
      });
    });

    document.querySelectorAll('form[data-ajax="delete-campaign"]').forEach((form) => {
      form.addEventListener('submit', (e) => {
        e.preventDefault();
        const confirmMsg = form.dataset.confirm || 'Delete this campaign?';
        window.confirmDialog?.({
          title: 'Delete campaign?',
          message: confirmMsg,
          confirmText: 'Delete',
          cancelText: 'Cancel',
          tone: 'danger',
          onConfirm: async () => {
            const json = await ajaxSubmit(form, { silent: true });
            if (json && json.ok) {
              // After a delete the detail page is gone — return to the list.
              window.location = window.appUrl("/wa-campaigns");
            }
          },
        });
      });
    });

    const baseFont = { fontFamily: "Plus Jakarta Sans, system-ui, sans-serif" };
      const grid = { borderColor: themeColor('paper-100'), strokeDashArray: 4 };
      const label = { colors: themeColor('ink-500'), fontSize: "11px" };

      const showTab = (name) => {
        document.querySelectorAll(".tab-panel").forEach(panel => panel.classList.toggle("hidden", panel.dataset.panel !== name));
        document.querySelectorAll(".tab-btn").forEach(btn => {
          const active = btn.dataset.tab === name;
          btn.classList.toggle("bg-wa-deep", active);
          btn.classList.toggle("text-paper-0", active);
          btn.classList.toggle("text-ink-600", !active);
          btn.classList.toggle("hover:bg-paper-50", !active);
        });
        window.dispatchEvent(new Event("resize"));
      };

      document.querySelectorAll(".tab-btn").forEach(btn => btn.addEventListener("click", () => showTab(btn.dataset.tab)));

      // The recipient tables paginate SERVER-SIDE (?mpage= / ?rpage=), which
      // reloads the whole analytics page. Re-open the tab the pager belongs to
      // so Prev/Next (and a message-log search) don't dump the operator back on
      // the Overview tab.
      (function restoreTabFromUrl() {
        const p = new URLSearchParams(window.location.search);
        if (p.has("rpage")) showTab("recipients");
        else if (p.has("mpage") || p.has("q")) showTab("messages");
      })();

      // Server-rendered payload from resources/views/user/wa-campaigns/detail.blade.php
      // (`window.WA_CAMPAIGN_DATA = @json($chartData)`). Each shape is
      // documented in WaCampaignsController::buildChartData(). Defaults below
      // keep the page from blowing up if the script tag is missing (e.g.
      // partial render during SSR refresh).
      const data = window.WA_CAMPAIGN_DATA || {};
      const deliveryData = data.delivery || { categories: [], sent: [], delivered: [], read: [] };
      const statusData = data.status || { labels: [], series: [] };
      const throughputData = data.throughput || { categories: [], series: [] };
      const engagementData = data.engagement || { categories: [], clicks: [], replies: [] };
      // Real read-heatmap data: WA_CAMPAIGN_HEATMAP is a 7×24 matrix from
      // PHP (rows: 0=Sun..6=Sat, cols: 0..23). Apex wants series as
      // [{name: dayLabel, data: [{x: hour, y: count}, ...]}, ...]. Falls
      // back to the legacy data.readHeatmap shape if PHP didn't supply
      // the matrix (older campaigns saved before the new pipeline).
      const dayLabels = ["Sun", "Mon", "Tue", "Wed", "Thu", "Fri", "Sat"];
      const matrix = Array.isArray(window.WA_CAMPAIGN_HEATMAP) ? window.WA_CAMPAIGN_HEATMAP : null;
      let heatmapData;
      if (matrix && matrix.length === 7) {
        heatmapData = matrix.map((row, dow) => ({
          name: dayLabels[dow] || `D${dow}`,
          data: (row || []).map((count, hr) => ({
            x: String(hr).padStart(2, "0"),
            y: Number(count) || 0,
          })),
        }));
      } else {
        heatmapData = Array.isArray(data.readHeatmap) && data.readHeatmap.length
          ? data.readHeatmap
          : [{ name: "—", data: [] }];
      }
      const intentsData = data.intents || { labels: [], series: [] };
      const segmentsData = data.segments || { labels: [], series: [] };
      const failuresData = data.failures || { labels: [], series: [] };

      // "% success" for the donut center — anything that *left the box*
      // (sent / delivered / read / responded) counts as success.
      // Only `Failed` is excluded. Previously this was `Delivered/Total`
      // which read 0% even after a successful send because delivery
      // receipts hadn't come back yet.
      const labelIdx = (label) => (statusData.labels || []).indexOf(label);
      const valAt = (label) => {
        const i = labelIdx(label);
        return i >= 0 ? Number(statusData.series[i] || 0) : 0;
      };
      const sentN      = valAt('Sent');
      const deliveredN = valAt('Delivered');
      const readN      = valAt('Read');
      const respondedN = valAt('Responded');
      const failedN    = valAt('Failed');
      const successN   = sentN + deliveredN + readN + respondedN;
      const totalN     = successN + failedN;
      const successPct = totalN > 0 ? Math.round((successN / totalN) * 100) : 0;

      new ApexCharts(document.querySelector("#chart-delivery"), {
        chart: { type: "area", height: 320, toolbar: { show: false }, ...baseFont },
        series: [
          { name: "Sent", data: deliveryData.sent },
          { name: "Delivered", data: deliveryData.delivered },
          { name: "Read", data: deliveryData.read }
        ],
        colors: [themeColor('wa-deep'), themeColor('wa-teal'), themeColor('accent-amber')],
        stroke: { curve: "smooth", width: 3 },
        fill: { type: "gradient", gradient: { opacityFrom: 0.24, opacityTo: 0.02 } },
        grid,
        xaxis: { categories: deliveryData.categories, labels: { style: label } },
        yaxis: { labels: { style: label } },
        legend: { show: false }
      }).render();

      new ApexCharts(document.querySelector("#chart-status"), {
        chart: { type: "donut", height: 300, ...baseFont },
        series: statusData.series,
        labels: statusData.labels,
        // Slice order is Sent / Delivered / Read / Failed / Responded — the
        // "Failed" coral is a semantic error colour and stays literal.
        colors: [themeColor('wa-deep'), themeColor('wa-green'), themeColor('accent-amber'), "#E87A5D", "#13478A"],
        dataLabels: { enabled: false },
        legend: { position: "bottom", labels: { colors: "#3A5A55" } },
        plotOptions: { pie: { donut: { size: "66%", labels: { show: true, total: { show: true, label: "Success", formatter: () => `${successPct}%` } } } } }
      }).render();

      new ApexCharts(document.querySelector("#chart-throughput"), {
        chart: { type: "bar", height: 320, toolbar: { show: false }, ...baseFont },
        series: [{ name: "Messages", data: throughputData.series }],
        colors: [themeColor('wa-deep')],
        plotOptions: { bar: { borderRadius: 7, columnWidth: "46%" } },
        grid,
        xaxis: { categories: throughputData.categories, labels: { style: label } },
        yaxis: { labels: { style: label } }
      }).render();

      new ApexCharts(document.querySelector("#chart-engagement"), {
        chart: { type: "line", height: 320, toolbar: { show: false }, ...baseFont },
        series: [
          { name: "Clicks", data: engagementData.clicks },
          { name: "Replies", data: engagementData.replies }
        ],
        colors: ["#13478A", themeColor('wa-deep')],
        stroke: { curve: "smooth", width: 3 },
        grid,
        xaxis: { categories: engagementData.categories, labels: { style: label } },
        yaxis: { labels: { style: label } },
        legend: { labels: { colors: "#3A5A55" } }
      }).render();

      new ApexCharts(document.querySelector("#chart-read-heatmap"), {
        chart: { type: "heatmap", height: 250, toolbar: { show: false }, ...baseFont },
        series: heatmapData,
        colors: [themeColor('wa-deep')],
        dataLabels: { enabled: false },
        xaxis: { labels: { style: label } },
        yaxis: { labels: { style: label } },
        grid: { padding: { top: 0, right: 0, bottom: 0, left: 0 } },
        plotOptions: { heatmap: { shadeIntensity: 0.55, radius: 6, colorScale: { ranges: [
          { from: 0, to: 50, color: themeColor('paper-200') },
          { from: 51, to: 75, color: themeColor('wa-teal') },
          { from: 76, to: 100, color: themeColor('wa-deep') }
        ] } } }
      }).render();

      new ApexCharts(document.querySelector("#chart-intents"), {
        chart: { type: "donut", height: 286, ...baseFont },
        series: intentsData.series,
        labels: intentsData.labels,
        colors: [themeColor('wa-deep'), themeColor('wa-teal'), themeColor('accent-amber'), "#13478A", themeColor('accent-coral')],
        dataLabels: { enabled: false },
        legend: { position: "bottom", labels: { colors: "#3A5A55" } },
        plotOptions: { pie: { donut: { size: "64%" } } }
      }).render();

      new ApexCharts(document.querySelector("#chart-segments"), {
        chart: { type: "bar", height: 320, toolbar: { show: false }, ...baseFont },
        series: [
          { name: "Recipients", data: segmentsData.series }
        ],
        colors: [themeColor('wa-deep'), themeColor('accent-amber')],
        plotOptions: { bar: { borderRadius: 7, columnWidth: "46%" } },
        grid,
        xaxis: { categories: segmentsData.labels, labels: { style: label } },
        yaxis: { labels: { style: label } },
        legend: { labels: { colors: "#3A5A55" } }
      }).render();

      new ApexCharts(document.querySelector("#chart-failures"), {
        chart: { type: "bar", height: 300, toolbar: { show: false }, ...baseFont },
        series: [{ name: "Failures", data: failuresData.series }],
        colors: ["#E87A5D"],
        plotOptions: { bar: { borderRadius: 7, horizontal: true } },
        grid,
        xaxis: { categories: failuresData.labels, labels: { style: label } },
        yaxis: { labels: { style: label } }
      }).render();

      // ──────────────────────────────────────────────────────────────
      // Live KPI refresh — campaign delivered / read receipts arrive
      // asynchronously via the Node bridge's
      // /api/campaigns/update-status-by-id callback. Poll every 15 s
      // and repaint the six KPI tiles + status pill so the operator
      // sees totals climb without forcing a manual refresh.
      //
      // Silent fetch (no toast). Skipped while the tab is hidden so
      // backgrounded windows don't burn CPU on the bridge.
      // ──────────────────────────────────────────────────────────────
      const kpiGrid = document.querySelector('[data-wac-detail-grid]');
      const campaignId = kpiGrid?.dataset?.campaignId;
      if (!campaignId) return;

      const fmt = new Intl.NumberFormat();
      const fmtPct = (n) => Number(n).toFixed(1);

      function paintCounts(stats) {
          if (!stats) return;
          const map = {
              recipients:    stats.recipients,
              delivered:     stats.delivered,
              read:          stats.read,
              replies:       stats.replies,
              clicks:        stats.clicks,
              failed:        stats.failed,
              delivered_pct: stats.delivered_pct,
              read_pct:      stats.read_pct,
              replies_pct:   stats.replies_pct,
              clicks_pct:    stats.clicks_pct,
              failed_pct:    stats.failed_pct,
          };
          Object.entries(map).forEach(([key, value]) => {
              const node = document.querySelector(`[data-wac-detail-totals="${key}"]`);
              if (!node || value == null) return;
              node.textContent = key.endsWith('_pct') ? fmtPct(value) : fmt.format(value);
          });
      }

      function paintStatus(status) {
          const pill = document.getElementById('campaignStatusPill');
          if (!pill || !status) return;
          pill.textContent = status.charAt(0).toUpperCase() + status.slice(1);
      }

      // Live "why did it fail" banner. The KPI tiles only ever said HOW MANY
      // failed — the operator had to reload to learn WHY. Render the grouped
      // reasons (most common first) straight from the poll so the real blocker
      // is on screen as it happens. Hidden entirely when nothing has failed.
      function paintFailures(summary) {
          const box = document.querySelector('[data-wac-failure-live]');
          if (!box) return;
          if (!Array.isArray(summary) || summary.length === 0) {
              box.classList.add('hidden');
              box.innerHTML = '';
              return;
          }
          const rows = summary.map((r) => {
              const li = document.createElement('li');
              li.className = 'flex items-start gap-2';
              const n = document.createElement('span');
              n.className = 'shrink-0 mono text-[10px] px-1.5 py-0.5 rounded-full bg-accent-coral/15 text-accent-coral';
              n.textContent = String(r.count);
              const t = document.createElement('span');
              t.className = 'text-[12px] text-ink-700 leading-snug';
              t.textContent = r.error;          // textContent → no HTML injection
              li.append(n, t);
              return li;
          });
          const head = document.createElement('div');
          head.className = 'mono text-[10px] uppercase tracking-[0.14em] text-accent-coral mb-2';
          head.textContent = 'Why sends are failing';
          const ul = document.createElement('ul');
          ul.className = 'space-y-1.5';
          rows.forEach((r) => ul.appendChild(r));
          box.innerHTML = '';
          box.append(head, ul);
          box.classList.remove('hidden');
      }

      async function fetchStats() {
          try {
              const res = await fetch(`/wa-campaigns/${campaignId}?partial=1`, {
                  headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                  credentials: 'same-origin',
              });
              if (!res.ok) return;
              const json = await res.json();
              if (!json?.ok) return;
              paintCounts(json.stats);
              paintStatus(json.status);
              paintFailures(json.failure_summary);
          } catch (_) {
              // Silent — network blips shouldn't disturb the page.
          }
      }

      const POLL_MS = 15_000;
      let pollHandle = setInterval(() => {
          if (document.hidden) return;
          fetchStats();
      }, POLL_MS);
      document.addEventListener('visibilitychange', () => {
          if (!document.hidden) fetchStats();
      });
      window.addEventListener('pagehide', () => {
          if (pollHandle) { clearInterval(pollHandle); pollHandle = null; }
      });

      // Message-log filter — hide recipient rows that don't match the
      // name / phone typed into the search box (client-side, instant).
      const searchBox = document.querySelector('[data-msglog-search]');
      const logBody   = document.querySelector('[data-msglog-table] tbody');
      if (searchBox && logBody) {
          searchBox.addEventListener('input', () => {
              const q = searchBox.value.trim().toLowerCase();
              logBody.querySelectorAll('tr').forEach((tr) => {
                  if (tr.querySelector('td[colspan]')) return; // empty-state row
                  tr.style.display = (!q || tr.textContent.toLowerCase().includes(q)) ? '' : 'none';
              });
          });
      }

      // ---- Resend menu -----------------------------------------------------
      // Resend used to be a single button that always re-sent to EVERY
      // recipient. It is now scoped (failed / everyone / specific), so it
      // needs a menu.
      const resendWrap   = document.querySelector('[data-wac-resend]');
      const resendToggle = document.querySelector('[data-wac-resend-toggle]');
      const resendMenu   = document.querySelector('[data-wac-resend-menu]');
      if (resendWrap && resendToggle && resendMenu) {
          const closeMenu = () => resendMenu.classList.add('hidden');

          resendToggle.addEventListener('click', (e) => {
              e.stopPropagation();
              resendMenu.classList.toggle('hidden');
          });
          // Click-away + Esc, so the menu can't be left stranded open.
          document.addEventListener('click', (e) => {
              if (!resendWrap.contains(e.target)) closeMenu();
          });
          document.addEventListener('keydown', (e) => {
              if (e.key === 'Escape') closeMenu();
          });

          // "Resend to specific…" — send the operator to the Recipients tab,
          // where the rows and their statuses already are, rather than
          // duplicating the list in a modal.
          const pickBtn = document.querySelector('[data-wac-resend-pick]');
          if (pickBtn) {
              pickBtn.addEventListener('click', () => {
                  closeMenu();
                  const tab = document.querySelector('[data-tab="recipients"]');
                  if (tab) tab.click();
                  const bar = document.querySelector('[data-wac-pick-bar]');
                  if (bar) {
                      bar.classList.remove('hidden');
                      bar.classList.add('flex');
                      bar.scrollIntoView({ behavior: 'smooth', block: 'center' });
                  }
              });
          }
      }

      // ---- Recipient picker ------------------------------------------------
      // Checkbox selection in the Recipients table -> contact_ids[] on the
      // scope=selected resend form.
      const pickForm  = document.querySelector('[data-wac-pick-form]');
      const pickBar   = document.querySelector('[data-wac-pick-bar]');
      const pickAll   = document.querySelector('[data-wac-pick-all]');
      const pickCount = document.querySelector('[data-wac-pick-count]');
      if (pickForm && pickBar) {
          const rows = () => Array.from(pickForm.querySelectorAll('[data-wac-pick-row]'));
          // Only rows the search filter is currently showing — selecting "all"
          // while a filter is active must not silently include hidden people.
          const visibleRows = () => rows().filter((cb) => {
              const tr = cb.closest('tr');
              return tr && tr.style.display !== 'none';
          });

          const sync = () => {
              const picked = rows().filter((cb) => cb.checked).length;
              if (pickCount) pickCount.textContent = String(picked);
              pickBar.classList.toggle('hidden', picked < 1);
              pickBar.classList.toggle('flex', picked > 0);
              if (pickAll) {
                  const vis = visibleRows();
                  pickAll.checked = vis.length > 0 && vis.every((cb) => cb.checked);
                  pickAll.indeterminate = picked > 0 && !pickAll.checked;
              }
          };

          pickForm.addEventListener('change', (e) => {
              if (e.target.matches('[data-wac-pick-row]')) sync();
          });

          if (pickAll) {
              pickAll.addEventListener('change', () => {
                  visibleRows().forEach((cb) => { cb.checked = pickAll.checked; });
                  sync();
              });
          }

          const clearBtn = document.querySelector('[data-wac-pick-clear]');
          if (clearBtn) {
              clearBtn.addEventListener('click', () => {
                  rows().forEach((cb) => { cb.checked = false; });
                  sync();
              });
          }

          // Guard the submit — the server also refuses an empty list, but
          // failing here is instant and doesn't cost a round trip. Uses
          // window.toast() like the rest of this page, not a native alert.
          pickForm.addEventListener('submit', (e) => {
              if (!rows().some((cb) => cb.checked)) {
                  e.preventDefault();
                  e.stopImmediatePropagation();
                  window.toast?.('Select at least one recipient first.', 'error');
              }
          });

          sync();
      }
}
