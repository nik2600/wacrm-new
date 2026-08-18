import ApexCharts from 'apexcharts';
import { themeColor, themeAlpha } from '../theme-colors.js';

export default function init() {
    const d = window.DASHBOARD_DATA || {
        labels: [], sent: [], delivered: [], failed: [], spark: [], readRate: 0,
    };

    // ===== KPI 1 sparkline =====
    const spark = document.querySelector("#kpi-spark");
    if (spark) {
        new ApexCharts(spark, {
            chart: { type: "bar", height: 32, sparkline: { enabled: true }, animations: { enabled: true, speed: 600 } },
            series: [{ name: "Sent/hr", data: d.spark.length ? d.spark : [0] }],
            plotOptions: { bar: {
                columnWidth: "60%", borderRadius: 2, distributed: true,
                colors: { ranges: [
                    { from: 0,  to: 9,    color: themeColor('wa-teal') },
                    { from: 10, to: 9999, color: themeColor('wa-green') },
                ] },
            } },
            dataLabels: { enabled: false },
            tooltip: { enabled: true, x: { show: false }, y: { formatter: (v) => v.toLocaleString() + " msg/h" } },
            states: { hover: { filter: { type: "darken", value: 0.92 } } },
        }).render();
    }

    // ===== KPI 2 read-rate radial donut =====
    const radial = document.querySelector("#kpi-readrate");
    if (radial) {
        new ApexCharts(radial, {
            chart: { type: "radialBar", height: 64, width: 64, sparkline: { enabled: true } },
            colors: [themeColor('wa-deep')],
            series: [Number(d.readRate || 0)],
            plotOptions: { radialBar: {
                hollow: { size: "58%" },
                track:  { background: themeAlpha('ink-900', 0.06), strokeWidth: "100%" },
                dataLabels: {
                    name: { show: false },
                    value: { show: true, fontSize: "10px", fontFamily: "JetBrains Mono, monospace", fontWeight: 600, color: themeColor('wa-deep'), offsetY: 4, formatter: (v) => Number(v).toFixed(1) },
                },
            } },
            stroke: { lineCap: "round" },
        }).render();
    }

    // ===== Message throughput — grouped bars with a working range filter =====
    const tp = document.querySelector("#chart-throughput");
    if (tp) {
        // Range datasets from the controller. Fall back to the legacy 7d
        // top-level fields so the chart still works if ranges are absent.
        const ranges = d.throughputRanges || {};
        const fallback7d = { labels: d.labels || [], sent: d.sent || [], delivered: d.delivered || [], failed: d.failed || [] };
        const getRange = (key) => ranges[key] || (key === "7d" ? fallback7d : { labels: [], sent: [], delivered: [], failed: [] });

        const subtitles = {
            "24h": "Outbound, delivered & failed events — hourly, last 24h.",
            "7d":  "Outbound, delivered & failed events — daily, last 7 days.",
            "30d": "Outbound, delivered & failed events — daily, last 30 days.",
            "qtd": "Outbound, delivered & failed events — daily, quarter to date.",
        };

        const initial = getRange("7d");
        const throughputChart = new ApexCharts(tp, {
            chart: { type: "bar", height: 220, fontFamily: "Inter, system-ui, sans-serif", toolbar: { show: false }, animations: { enabled: true, speed: 500 } },
            colors: [themeColor('wa-deep'), themeColor('wa-green'), themeColor('accent-coral')],
            series: [
                { name: "Sent",      data: initial.sent },
                { name: "Delivered", data: initial.delivered },
                { name: "Failed",    data: initial.failed },
            ],
            plotOptions: { bar: { columnWidth: "55%", borderRadius: 3 } },
            dataLabels: { enabled: false },
            grid: { borderColor: themeColor('paper-200'), strokeDashArray: 3 },
            xaxis: {
                categories: initial.labels,
                labels: { rotate: -45, rotateAlways: false, hideOverlappingLabels: true, trim: false, style: { fontFamily: "JetBrains Mono, monospace", fontSize: "10px", colors: themeColor('ink-500') } },
                tickPlacement: "on",
                axisBorder: { show: false }, axisTicks: { show: false },
            },
            yaxis: {
                labels: {
                    formatter: (v) => v >= 1000 ? (v/1000).toFixed(0) + "k" : v,
                    style: { fontFamily: "JetBrains Mono, monospace", fontSize: "10px", colors: themeColor('ink-500') },
                },
            },
            legend: { show: false },
            tooltip: { shared: true, intersect: false, y: { formatter: (v) => v.toLocaleString() + " msg" } },
        });
        throughputChart.render();
        // The dashboard cards play an entrance animation; ApexCharts can
        // measure a transformed (narrower) width at render time and cluster the
        // x-axis labels at the left. Force a re-measure once the layout settles.
        setTimeout(() => { try { window.dispatchEvent(new Event("resize")); } catch (e) {} }, 300);

        // Range buttons inside the chart card.
        const cardRangeButtons = document.querySelectorAll("[data-range]");
        const subtitleEl = document.querySelector("#throughput-subtitle");
        const setActive = (btn) => {
            cardRangeButtons.forEach((b) => {
                b.classList.remove("bg-ink-900", "text-paper-0");
                b.classList.add("text-ink-600");
            });
            if (btn) {
                btn.classList.remove("text-ink-600");
                btn.classList.add("bg-ink-900", "text-paper-0");
            }
        };

        // Shared range switcher — used by both the in-card toggle and the
        // header "Last 7 days ▾" dropdown so they stay in sync.
        const rangeLabels = { "24h": "Last 24 hours", "7d": "Last 7 days", "30d": "Last 30 days", "qtd": "Quarter to date" };
        const headerRangeLabel = document.querySelector("#dash-range-label");
        function applyRange(key) {
            const r = getRange(key);
            setActive(document.querySelector(`[data-range="${key}"]`));
            if (subtitleEl && subtitles[key]) subtitleEl.textContent = subtitles[key];
            if (headerRangeLabel && rangeLabels[key]) headerRangeLabel.textContent = rangeLabels[key];
            throughputChart.updateOptions({ xaxis: { categories: r.labels } }, false, false);
            throughputChart.updateSeries([
                { name: "Sent",      data: r.sent },
                { name: "Delivered", data: r.delivered },
                { name: "Failed",    data: r.failed },
            ], true);
        }

        cardRangeButtons.forEach((btn) => {
            btn.addEventListener("click", () => applyRange(btn.getAttribute("data-range")));
        });

        // Header date-range dropdown.
        const rangeBtn  = document.querySelector("#dash-range-btn");
        const rangeMenu = document.querySelector("#dash-range-menu");
        if (rangeBtn && rangeMenu) {
            rangeBtn.addEventListener("click", (e) => {
                e.stopPropagation();
                rangeMenu.classList.toggle("hidden");
            });
            rangeMenu.querySelectorAll(".dash-range-opt").forEach((opt) => {
                opt.addEventListener("click", () => {
                    applyRange(opt.getAttribute("data-range"));
                    rangeMenu.classList.add("hidden");
                });
            });
            document.addEventListener("click", (e) => {
                if (!rangeMenu.contains(e.target) && e.target !== rangeBtn) rangeMenu.classList.add("hidden");
            });
        }
    }

    // ===== Export — download the throughput series as a CSV =====
    const exportBtn = document.querySelector("#dash-export");
    if (exportBtn) {
        exportBtn.addEventListener("click", () => {
            const labels = d.labels || [];
            const sent = d.sent || [], delivered = d.delivered || [], failed = d.failed || [];
            const rows = [["date", "sent", "delivered", "failed"]];
            for (let i = 0; i < labels.length; i++) {
                rows.push([labels[i], sent[i] ?? 0, delivered[i] ?? 0, failed[i] ?? 0]);
            }
            const csv = rows
                .map((r) => r.map((c) => `"${String(c).replace(/"/g, '""')}"`).join(","))
                .join("\n");
            const blob = new Blob([csv], { type: "text/csv;charset=utf-8;" });
            const url = URL.createObjectURL(blob);
            const a = document.createElement("a");
            a.href = url;
            a.download = `dashboard-throughput-${new Date().toISOString().slice(0, 10)}.csv`;
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            URL.revokeObjectURL(url);
            if (typeof window.toast === "function") window.toast("Exported throughput CSV.", "success");
        });
    }

}

// ===== Quick access tiles — edit modal. Now wired GLOBALLY by
// initQuickAccessModal() in app.js (so the edge-drawer pencil works on every
// page); this dashboard copy is intentionally no longer called. =====
// eslint-disable-next-line no-unused-vars
function quickAccessInit() {
    const modal = document.getElementById("qa-modal");
    if (!modal) return;

    const open = () => { modal.classList.remove("hidden"); modal.classList.add("flex"); updateCount(); };
    const close = () => { modal.classList.add("hidden"); modal.classList.remove("flex"); };
    document.querySelectorAll("[data-qa-open]").forEach((b) => b.addEventListener("click", open));
    modal.querySelectorAll("[data-qa-close]").forEach((b) => b.addEventListener("click", close));
    document.addEventListener("keydown", (e) => { if (e.key === "Escape") close(); });

    const cats = () => Array.from(modal.querySelectorAll(".qa-cat"));
    const customRows = () => Array.from(modal.querySelectorAll(".qa-custom-row"));
    const countEl = document.getElementById("qa-count");

    function selectedCount() {
        const c = cats().filter((x) => x.checked).length;
        const cu = customRows().filter((r) =>
            r.querySelector(".qa-cu-label").value.trim() && r.querySelector(".qa-cu-url").value.trim()).length;
        return c + cu;
    }
    function updateCount() {
        if (countEl) countEl.textContent = `${selectedCount()} / 10 pinned`;
    }

    cats().forEach((c) => c.addEventListener("change", () => {
        if (c.checked && selectedCount() > 10) {
            c.checked = false;
            window.toast?.("You can pin up to 10 shortcuts.", "error");
        }
        updateCount();
    }));

    document.getElementById("qa-add-custom")?.addEventListener("click", () => {
        if (selectedCount() >= 10) { window.toast?.("You can pin up to 10 shortcuts.", "error"); return; }
        const list = document.getElementById("qa-custom-list");
        const row = document.createElement("div");
        row.className = "qa-custom-row flex items-center gap-2";
        row.innerHTML =
            '<input type="text" class="qa-cu-label flex-1 rounded-xl border border-paper-200 px-3 py-2 text-[12.5px]" placeholder="Label">' +
            '<input type="text" class="qa-cu-url flex-1 rounded-xl border border-paper-200 px-3 py-2 text-[12.5px] font-mono" placeholder="https://… or /path">' +
            '<button type="button" class="qa-cu-del text-ink-400 hover:text-accent-coral"><svg viewBox="0 0 16 16" class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M3 4.5h10M6 4.5V3h4v1.5M5 4.5l.5 8h5l.5-8"/></svg></button>';
        list.appendChild(row);
        updateCount();
    });

    modal.addEventListener("click", (e) => {
        const del = e.target.closest(".qa-cu-del");
        if (del) { del.closest(".qa-custom-row")?.remove(); updateCount(); }
    });
    modal.addEventListener("input", (e) => { if (e.target.closest(".qa-custom-row")) updateCount(); });

    document.getElementById("qa-save")?.addEventListener("click", async (e) => {
        const btn = e.currentTarget;
        const items = [];
        cats().filter((c) => c.checked).forEach((c) => items.push({ key: c.value }));
        customRows().forEach((r) => {
            const label = r.querySelector(".qa-cu-label").value.trim();
            const url = r.querySelector(".qa-cu-url").value.trim();
            if (label && url) items.push({ label, url });
        });
        if (items.length > 10) { window.toast?.("Up to 10 shortcuts only.", "error"); return; }

        const csrf = document.querySelector('meta[name="csrf-token"]')?.content || "";
        btn.disabled = true; btn.style.opacity = "0.6";
        try {
            const res = await fetch(btn.dataset.url, {
                method: "POST",
                headers: {
                    Accept: "application/json",
                    "X-Requested-With": "XMLHttpRequest",
                    "X-CSRF-TOKEN": csrf,
                    "Content-Type": "application/json",
                },
                body: JSON.stringify({ items }),
            });
            if (res.ok) { window.toast?.("Quick access updated.", "success"); location.reload(); }
            else { window.toast?.("Could not save. Please try again.", "error"); btn.disabled = false; btn.style.opacity = ""; }
        } catch (_) {
            window.toast?.("Network error. Please try again.", "error");
            btn.disabled = false; btn.style.opacity = "";
        }
    });
}
