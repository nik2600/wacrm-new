import ApexCharts from 'apexcharts';
import { themeColor } from '../theme-colors.js';

export default function init() {
    const baseFont = { fontFamily: "Plus Jakarta Sans, system-ui, sans-serif" };
      const grid = { borderColor: themeColor('paper-100'), strokeDashArray: 4 };
      const label = { colors: themeColor('ink-500'), fontSize: "11px" };

      new ApexCharts(document.querySelector("#chart-trend"), {
        chart: { type: "area", height: 300, toolbar: { show: false }, ...baseFont },
        series: [
          { name: "Sent", data: [820, 1840, 2160, 1840, 1480, 920, 480, 200, 100, 0] },
          { name: "Delivered", data: [798, 1812, 2108, 1788, 1422, 882, 460, 192, 96, 0] },
          { name: "Read", data: [240, 920, 1480, 1320, 1080, 680, 360, 140, 60, 0] }
        ],
        colors: [themeColor('wa-deep'), themeColor('wa-teal'), themeColor('accent-amber')],
        stroke: { curve: "smooth", width: 2.5 },
        fill: { type: "gradient", gradient: { shadeIntensity: 1, opacityFrom: 0.20, opacityTo: 0.04 } },
        dataLabels: { enabled: false },
        grid,
        xaxis: { categories: ["09:00","09:05","09:10","09:15","09:20","09:25","09:30","09:35","09:40","09:48"], labels: { style: label } },
        yaxis: { labels: { style: label } },
        legend: { show: false }
      }).render();

      new ApexCharts(document.querySelector("#chart-status"), {
        chart: { type: "donut", height: 240, ...baseFont },
        labels: ["Read", "Delivered (unread)", "Failed", "Pending"],
        series: [69, 26, 0.5, 4.5],
        colors: [themeColor('wa-deep'), themeColor('wa-teal'), themeColor('accent-coral'), themeColor('accent-amber')],
        dataLabels: { enabled: false },
        legend: { position: "bottom", fontSize: "11px" },
        stroke: { colors: [themeColor('paper-0')], width: 2 }
      }).render();

      new ApexCharts(document.querySelector("#chart-replies"), {
        chart: { type: "bar", height: 220, toolbar: { show: false }, ...baseFont },
        series: [{ name: "Replies", data: [186, 142, 58, 24, 2] }],
        colors: [themeColor('wa-deep')],
        plotOptions: { bar: { horizontal: true, borderRadius: 6 } },
        dataLabels: { enabled: false },
        grid,
        xaxis: { categories: ["Quick reply: YES", "Quick reply: NO", "Free text question", "Click coupon", "STOP"], labels: { style: label } },
        yaxis: { labels: { style: label } }
      }).render();
}
