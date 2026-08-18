import ApexCharts from 'apexcharts';
import { themeColor } from '../theme-colors.js';

export default function init() {
    const baseFont = { fontFamily: "Plus Jakarta Sans, system-ui, sans-serif" };
      const grid = { borderColor: themeColor('paper-100'), strokeDashArray: 4 };
      const label = { colors: themeColor('ink-500'), fontSize: "11px" };

      new ApexCharts(document.querySelector("#chart-trend"), {
        chart: { type: "area", height: 300, toolbar: { show: false }, ...baseFont },
        series: [
          { name: "Spend", data: [22, 28, 36, 41, 38, 44, 48, 52, 49, 56] },
          { name: "Clicks", data: [120, 168, 198, 220, 205, 240, 268, 290, 280, 312] },
          { name: "Leads", data: [10, 14, 17, 20, 18, 22, 24, 26, 25, 28] }
        ],
        colors: [themeColor('wa-deep'), themeColor('wa-teal'), themeColor('accent-amber')],
        stroke: { curve: "smooth", width: 2.5 },
        fill: { type: "gradient", gradient: { shadeIntensity: 1, opacityFrom: 0.20, opacityTo: 0.04 } },
        dataLabels: { enabled: false },
        grid,
        xaxis: { categories: ["Apr 18","Apr 19","Apr 20","Apr 21","Apr 22","Apr 23","Apr 24","Apr 25","Apr 26","Apr 27"], labels: { style: label } },
        yaxis: { labels: { style: label } },
        legend: { show: false }
      }).render();

      new ApexCharts(document.querySelector("#chart-outcomes"), {
        chart: { type: "donut", height: 240, ...baseFont },
        labels: ["WhatsApp starts", "Site visits", "Bounced", "Form fills"],
        series: [29, 38, 22, 11],
        colors: [themeColor('wa-deep'), themeColor('wa-teal'), themeColor('accent-amber'), themeColor('accent-coral')],
        dataLabels: { enabled: false },
        legend: { position: "bottom", fontSize: "11px" },
        stroke: { colors: [themeColor('paper-0')], width: 2 }
      }).render();

      new ApexCharts(document.querySelector("#chart-placement"), {
        chart: { type: "bar", height: 220, toolbar: { show: false }, ...baseFont },
        series: [{ name: "Spend", data: [196, 124, 68, 18, 6] }],
        colors: [themeColor('wa-deep')],
        plotOptions: { bar: { horizontal: true, borderRadius: 6 } },
        dataLabels: { enabled: false },
        grid,
        xaxis: { categories: ["Feed", "Stories", "Reels", "Audience Network", "Marketplace"], labels: { style: label } },
        yaxis: { labels: { style: label } }
      }).render();
}
