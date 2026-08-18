import ApexCharts from 'apexcharts';
import { themeColor } from '../theme-colors.js';

export default function init() {
    const baseFont = { fontFamily: "Plus Jakarta Sans, system-ui, sans-serif" };
      const grid = { borderColor: themeColor('paper-100'), strokeDashArray: 4 };
      const label = { colors: themeColor('ink-500'), fontSize: "11px" };
      const days = Array.from({length: 30}, (_, i) => `Day ${i+1}`);
      const charges = [4.2,5.1,4.8,5.6,6.1,5.9,6.4,7.0,6.8,7.4,7.8,8.1,7.6,8.3,8.7,8.9,9.2,9.5,9.1,9.8,10.2,10.5,10.1,10.8,11.2,11.4,11.0,11.6,11.9,12.2];
      const refunds = charges.map(v => Math.max(0, v * 0.06 + (Math.random()*0.3 - 0.1)));
      const failed = charges.map(v => Math.max(0, v * 0.04 + (Math.random()*0.2)));

      new ApexCharts(document.querySelector("#chart-flow"), {
        chart: { type: "area", height: 280, toolbar: { show: false }, ...baseFont },
        series: [
          { name: "Charges ($k)", data: charges },
          { name: "Refunds ($k)", data: refunds },
          { name: "Failed ($k)", data: failed }
        ],
        colors: [themeColor('wa-deep'), themeColor('accent-coral'), themeColor('accent-amber')],
        stroke: { curve: "smooth", width: 2.5 },
        fill: { type: "gradient", gradient: { shadeIntensity: 1, opacityFrom: 0.20, opacityTo: 0.04 } },
        dataLabels: { enabled: false },
        grid,
        xaxis: { categories: days, labels: { style: label } },
        yaxis: { labels: { style: label, formatter: v => (window.WA_CURRENCY || '$') + v.toFixed(0) + 'k' } },
        legend: { show: false }
      }).render();

      new ApexCharts(document.querySelector("#chart-gateway"), {
        chart: { type: "donut", height: 200, ...baseFont },
        labels: ["Stripe", "Razorpay", "Manual / wire", "PayPal"],
        series: [382, 108, 42, 10],
        colors: [themeColor('wa-deep'), "#13478A", themeColor('accent-amber'), "#D6CDB6"],
        dataLabels: { enabled: false },
        legend: { show: false },
        stroke: { colors: [themeColor('paper-0')], width: 2 }
      }).render();

      new ApexCharts(document.querySelector("#chart-currency"), {
        chart: { type: "donut", height: 200, ...baseFont },
        labels: ["USD", "INR", "EUR", "GBP"],
        series: [382, 108, 32, 20],
        colors: [themeColor('wa-deep'), "#13478A", themeColor('accent-amber'), "#D6CDB6"],
        dataLabels: { enabled: false },
        legend: { show: false },
        stroke: { colors: [themeColor('paper-0')], width: 2 }
      }).render();
}
