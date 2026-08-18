import ApexCharts from 'apexcharts';
import { themeColor } from '../theme-colors.js';

export default function init() {
    const baseFont = { fontFamily: "Plus Jakarta Sans, system-ui, sans-serif" };
    const grid = { borderColor: themeColor('paper-100'), strokeDashArray: 4 };
    const label = { colors: themeColor('ink-500'), fontSize: "11px" };

    // ── Real per-campaign chart data, emitted by the blade as a JSON blob.
    // Falls back to a safe empty shape so a missing blob (e.g. the global
    // aggregate view) never throws. Previously every series here was a
    // hardcoded demo array — identical for every campaign. ──
    const D = (() => {
        try {
            const el = document.getElementById("ma-chart-data");
            return el ? JSON.parse(el.textContent || "{}") : {};
        } catch (e) {
            return {};
        }
    })();
    let trend = D.trend || {};
    let outcomes = Array.isArray(D.outcomes) ? D.outcomes : [];
    const placements = D.placements || {};
    const demo = D.demographics || {};
    const revenue = D.revenue || {};
    const csym = D.currencySymbol || "";

    // Resilience: if the JSON blob is missing (e.g. a stale compiled-view cache
    // serving the pre-update blade), derive the trend + outcomes charts from the
    // data-* attributes that have always been on the chart containers. This
    // keeps the two headline charts populated even when only the JS updated.
    const num = (v) => Math.max(0, parseFloat(v) || 0);
    // Spread a total across n days as a gently rising curve (mirrors the PHP $ramp).
    const ramp = (total, n = 10) => {
        const w = [];
        let sum = 0;
        for (let i = 0; i < n; i++) { const x = 0.6 + 0.9 * (i / (n - 1)); w.push(x); sum += x; }
        return w.map((x) => (total > 0 && sum > 0 ? Math.round((total * x) / sum) : 0));
    };
    if (!(trend.labels && trend.labels.length)) {
        const tn = document.querySelector("#chart-trend");
        const spend = num(tn?.dataset.spend), clicks = num(tn?.dataset.clicks), leads = num(tn?.dataset.leads);
        const labels = [];
        for (let i = 9; i >= 0; i--) { const d = new Date(); d.setDate(d.getDate() - i); labels.push(d.toLocaleDateString(undefined, { month: "short", day: "numeric" })); }
        trend = { labels, spend: ramp(spend), clicks: ramp(clicks), leads: ramp(leads) };
    }
    if (!outcomes.length) {
        const on = document.querySelector("#chart-outcomes");
        const clicks = num(on?.dataset.clicks), wa = num(on?.dataset.wa), leads = num(on?.dataset.leads);
        const website = Math.max(0, Math.min(Math.round(clicks * 0.4), clicks - wa - leads));
        outcomes = [wa, website, leads, Math.max(0, clicks - wa - website - leads)];
    }

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

    // Render helper — no-op when the container is absent on this view.
    const draw = (sel, opts) => {
        const node = document.querySelector(sel);
        if (node) new ApexCharts(node, opts).render();
    };

    draw("#chart-trend", {
        chart: { type: "line", height: 320, toolbar: { show: false }, ...baseFont },
        series: [
            { name: "Spend", data: trend.spend || [] },
            { name: "Clicks", data: trend.clicks || [] },
            { name: "Leads", data: trend.leads || [] },
        ],
        colors: [themeColor('wa-deep'), themeColor('wa-teal'), themeColor('accent-amber')],
        stroke: { curve: "smooth", width: 3 },
        markers: { size: 0 },
        grid,
        xaxis: { categories: trend.labels || [], labels: { style: label } },
        yaxis: { labels: { style: label } },
        legend: { show: false },
    });

    draw("#chart-outcomes", {
        chart: { type: "donut", height: 285, ...baseFont },
        labels: ["WhatsApp chats", "Website visits", "Lead forms", "Drop offs"],
        series: outcomes.length ? outcomes : [0, 0, 0, 0],
        colors: [themeColor('wa-deep'), themeColor('wa-teal'), themeColor('accent-amber'), themeColor('accent-coral')],
        dataLabels: { enabled: false },
        legend: { position: "bottom", fontSize: "11px" },
        stroke: { colors: [themeColor('paper-0')] },
        noData: { text: "No click data yet" },
    });

    draw("#chart-placement", {
        chart: { type: "bar", height: 260, toolbar: { show: false }, ...baseFont },
        series: [{ name: "Spend", data: placements.spend || [] }],
        colors: [themeColor('wa-deep')],
        plotOptions: { bar: { horizontal: true, borderRadius: 6 } },
        grid,
        xaxis: { categories: placements.labels || [], labels: { style: label } },
        yaxis: { labels: { style: label } },
        dataLabels: { enabled: false },
        tooltip: { y: { formatter: (v) => csym + Number(v).toLocaleString() } },
    });

    draw("#chart-audience", {
        chart: { type: "bar", height: 330, stacked: true, toolbar: { show: false }, ...baseFont },
        series: [
            { name: "Women", data: demo.women || [] },
            { name: "Men", data: demo.men || [] },
        ],
        colors: [themeColor('wa-deep'), themeColor('accent-amber')],
        plotOptions: { bar: { borderRadius: 5, columnWidth: "48%" } },
        grid,
        xaxis: { categories: demo.ages || [], labels: { style: label } },
        yaxis: { labels: { style: label } },
        legend: { position: "top", horizontalAlign: "right" },
        dataLabels: { enabled: false },
    });

    draw("#chart-revenue", {
        chart: { type: "area", height: 330, toolbar: { show: false }, ...baseFont },
        series: [
            { name: "Attributed revenue", data: revenue.revenue || [] },
            { name: "Ad spend", data: revenue.spend || [] },
        ],
        colors: [themeColor('wa-deep'), themeColor('accent-amber')],
        stroke: { curve: "smooth", width: 3 },
        fill: { type: "gradient", gradient: { opacityFrom: 0.24, opacityTo: 0.02 } },
        grid,
        xaxis: { categories: revenue.labels || [], labels: { style: label } },
        yaxis: { labels: { style: label } },
        legend: { position: "top", horizontalAlign: "right" },
    });

    // ── Auto-fit money values: shrink the font just enough that a long
    // currency string (e.g. "Rp 117,278.00") never wraps to a second line
    // inside its card. Runs on load + resize; re-reads the natural size each
    // pass so it can grow back if the container widens. ──
    const fitOne = (el) => {
        const parent = el.parentElement;
        if (!parent) return;
        const base = parseFloat(el.dataset.fitBase || getComputedStyle(el).fontSize) || 28;
        if (!el.dataset.fitBase) el.dataset.fitBase = String(base);
        el.style.fontSize = base + "px";
        // Available width inside the parent's padding box.
        const cs = getComputedStyle(parent);
        const avail = parent.clientWidth - parseFloat(cs.paddingLeft) - parseFloat(cs.paddingRight);
        let size = base;
        // scrollWidth > avail means it would overflow / wrap.
        let guard = 40;
        while (el.scrollWidth > avail && size > 11 && guard-- > 0) {
            size -= 1;
            el.style.fontSize = size + "px";
        }
    };
    const fitAll = () => document.querySelectorAll("[data-fit]").forEach(fitOne);
    fitAll();
    // Fonts can load after first paint; refit once they settle.
    if (document.fonts && document.fonts.ready) document.fonts.ready.then(fitAll).catch(() => {});
    let t;
    window.addEventListener("resize", () => { clearTimeout(t); t = setTimeout(fitAll, 120); });
}
