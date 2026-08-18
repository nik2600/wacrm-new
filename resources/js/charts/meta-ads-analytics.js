import ApexCharts from 'apexcharts';
import { themeColor } from '../theme-colors.js';

export default function init() {
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

      new ApexCharts(document.querySelector("#chart-trend"), {
        chart: { type: "line", height: 320, toolbar: { show: false }, ...baseFont },
        series: [
          { name: "Spend", data: [32, 38, 44, 49, 55, 60, 66, 68, 72, 78] },
          { name: "Clicks", data: [140, 166, 180, 205, 224, 238, 251, 264, 281, 301] },
          { name: "Leads", data: [10, 14, 16, 19, 18, 21, 23, 19, 22, 22] }
        ],
        colors: [themeColor('wa-deep'), themeColor('wa-teal'), themeColor('accent-amber')],
        stroke: { curve: "smooth", width: 3 },
        markers: { size: 0 },
        grid,
        xaxis: { categories: ["Apr 18", "Apr 19", "Apr 20", "Apr 21", "Apr 22", "Apr 23", "Apr 24", "Apr 25", "Apr 26", "Apr 27"], labels: { style: label } },
        yaxis: { labels: { style: label } },
        legend: { show: false }
      }).render();

      new ApexCharts(document.querySelector("#chart-outcomes"), {
        chart: { type: "donut", height: 285, ...baseFont },
        labels: ["WhatsApp chats", "Website visits", "Lead forms", "Drop offs"],
        series: [642, 914, 184, 470],
        colors: [themeColor('wa-deep'), themeColor('wa-teal'), themeColor('accent-amber'), themeColor('accent-coral')],
        dataLabels: { enabled: false },
        legend: { position: "bottom", fontSize: "11px" },
        stroke: { colors: [themeColor('paper-0')] }
      }).render();

      new ApexCharts(document.querySelector("#chart-placement"), {
        chart: { type: "bar", height: 260, toolbar: { show: false }, ...baseFont },
        series: [{ name: "Spend", data: [162, 104, 86, 60] }],
        colors: [themeColor('wa-deep')],
        plotOptions: { bar: { horizontal: true, borderRadius: 6 } },
        grid,
        xaxis: { categories: ["Facebook feed", "Instagram reels", "Instagram stories", "Marketplace"], labels: { style: label } },
        yaxis: { labels: { style: label } },
        dataLabels: { enabled: false }
      }).render();

      new ApexCharts(document.querySelector("#chart-audience"), {
        chart: { type: "bar", height: 330, stacked: true, toolbar: { show: false }, ...baseFont },
        series: [
          { name: "Women", data: [18, 48, 62, 31, 12] },
          { name: "Men", data: [9, 18, 24, 14, 6] }
        ],
        colors: [themeColor('wa-deep'), themeColor('accent-amber')],
        plotOptions: { bar: { borderRadius: 5, columnWidth: "48%" } },
        grid,
        xaxis: { categories: ["18-24", "25-34", "35-44", "45-54", "55+"], labels: { style: label } },
        yaxis: { labels: { style: label } },
        legend: { position: "top", horizontalAlign: "right" },
        dataLabels: { enabled: false }
      }).render();

      new ApexCharts(document.querySelector("#chart-revenue"), {
        chart: { type: "area", height: 330, toolbar: { show: false }, ...baseFont },
        series: [
          { name: "Attributed revenue", data: [180, 280, 320, 420, 510, 640, 780, 930, 1180, 1320] },
          { name: "Ad spend", data: [32, 70, 114, 163, 218, 278, 344, 412, 484, 562] }
        ],
        colors: [themeColor('wa-deep'), themeColor('accent-amber')],
        stroke: { curve: "smooth", width: 3 },
        fill: { type: "gradient", gradient: { opacityFrom: 0.24, opacityTo: 0.02 } },
        grid,
        xaxis: { categories: ["D0", "D1", "D2", "D3", "D4", "D5", "D6", "D7", "D8", "D9"], labels: { style: label } },
        yaxis: { labels: { style: label } },
        legend: { position: "top", horizontalAlign: "right" }
      }).render();
}
