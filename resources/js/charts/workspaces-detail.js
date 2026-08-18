import ApexCharts from 'apexcharts';
import { themeColor } from '../theme-colors.js';

export default function init() {
    const baseFont = { fontFamily: "Plus Jakarta Sans, system-ui, sans-serif" };
      const grid = { borderColor: themeColor('paper-100'), strokeDashArray: 4 };
      const label = { colors: themeColor('ink-500'), fontSize: "11px" };
      const dates = Array.from({length: 30}, (_, i) => `Apr ${i+1}`);
      const sent = [42,48,52,49,55,62,68,72,70,78,82,85,80,88,92,94,90,96,98,102,98,105,108,112,110,118,122,124,120,126];
      const delv = sent.map(v => Math.round(v * 0.984));

      new ApexCharts(document.querySelector("#chart-volume"), {
        chart: { type: "area", height: 280, toolbar: { show: false }, ...baseFont },
        series: [
          { name: "Sent (k)", data: sent },
          { name: "Delivered (k)", data: delv }
        ],
        colors: [themeColor('wa-deep'), themeColor('wa-teal')],
        stroke: { curve: "smooth", width: 2.5 },
        fill: { type: "gradient", gradient: { shadeIntensity: 1, opacityFrom: 0.20, opacityTo: 0.04 } },
        dataLabels: { enabled: false },
        grid,
        xaxis: { categories: dates, labels: { style: label } },
        yaxis: { labels: { style: label, formatter: (v) => v + 'k' } },
        legend: { show: false }
      }).render();
}
