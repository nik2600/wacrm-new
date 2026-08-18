import ApexCharts from 'apexcharts';
import { themeColor, themeAlpha } from '../theme-colors.js';

export default function init() {
    // ===== Synthetic data =====
      function makeSeries(n, base, variance, failRate) {
        const labels = [], sent = [], queued = [], failed = [];
        const today = new Date();
        for (let i = n - 1; i >= 0; i--) {
          const d = new Date(today); d.setDate(today.getDate() - i);
          labels.push(d.toLocaleDateString("en-US", { month:"short", day:"2-digit" }));
          const day = Math.max(0, Math.round(base + Math.sin(i*0.45)*variance*0.6 + (Math.random()-0.5)*variance));
          const failCount = Math.round(day * failRate);
          const queuedCount = Math.round(day * (0.06 + Math.random()*0.04));
          sent.push(Math.max(0, day - failCount - queuedCount));
          failed.push(failCount);
          queued.push(queuedCount);
        }
        return { labels, sent, failed, queued };
      }
      const RANGES = { "7d": makeSeries(7, 8200, 1200, 0.025), "30d": makeSeries(30, 7800, 1900, 0.03), "90d": makeSeries(90, 8400, 2400, 0.035) };
      RANGES.custom = RANGES["30d"];

      const baseFont = { fontFamily: "Plus Jakarta Sans, system-ui, sans-serif" };
      const muted = themeAlpha('ink-900', 0.08);
      const charts = {};

      function renderHeroSpark(d) {
        const opts = {
          chart: { type:"area", height: 60, sparkline:{enabled:true} },
          colors: [themeColor('wa-green')],
          stroke: { curve:"smooth", width: 2.5 },
          fill: { type:"gradient", gradient:{ shadeIntensity:1, opacityFrom:0.5, opacityTo:0, stops:[0,100] } },
          series: [{ name:"Sent", data: d.sent }],
          tooltip: { y:{ formatter:(v)=> v.toLocaleString()+" msg" }, x:{show:false} },
        };
        if (charts.hero) charts.hero.updateOptions(opts, true, true);
        else { charts.hero = new ApexCharts(document.querySelector("#hero-spark"), opts); charts.hero.render(); }
      }

      function renderVolume(d) {
        const delivered = d.sent.map(s => Math.round(s * 0.97));
        const opts = {
          chart: { type:"area", height: 320, ...baseFont, toolbar:{show:false}, animations:{enabled:true} },
          colors: [themeColor('wa-deep'), themeColor('wa-teal'), themeColor('accent-coral')],
          stroke: { curve:"smooth", width:[3, 2, 2], dashArray:[0, 6, 0] },
          fill: { type:"gradient", gradient:{ shadeIntensity:1, opacityFrom:0.32, opacityTo:0.02, stops:[0,90,100] } },
          series: [
            { name:"Sent", data: d.sent },
            { name:"Delivered", data: delivered },
            { name:"Failed", data: d.failed },
          ],
          dataLabels: { enabled:false },
          grid: { borderColor: muted, strokeDashArray:4 },
          xaxis: { categories: d.labels, labels:{style:{fontSize:"10px"}}, tickAmount: 8, axisBorder:{show:false}, axisTicks:{show:false} },
          yaxis: { labels: { formatter:(v)=> v >= 1000 ? (v/1000).toFixed(0)+"k" : v, style:{fontSize:"10px"} } },
          tooltip: { y:{formatter:(v)=> v.toLocaleString()+" msg"} },
          legend: { show:false },
          markers: { size:0, hover:{size:5} },
        };
        if (charts.volume) charts.volume.updateOptions(opts, true, true);
        else { charts.volume = new ApexCharts(document.querySelector("#chart-volume"), opts); charts.volume.render(); }
      }

      function renderTotals(d) {
        const opts = {
          chart: { type:"bar", height: 280, stacked:true, ...baseFont, toolbar:{show:false} },
          colors: [themeColor('wa-deep'), themeColor('accent-amber'), themeColor('accent-coral')],
          series: [
            { name:"Sent", data: d.sent },
            { name:"Queued", data: d.queued },
            { name:"Failed", data: d.failed },
          ],
          plotOptions: { bar: { columnWidth:"55%", borderRadius:3 } },
          dataLabels: { enabled:false },
          grid: { borderColor: muted, strokeDashArray:4 },
          xaxis: { categories: d.labels, labels:{style:{fontSize:"10px"}}, tickAmount: 6, axisBorder:{show:false}, axisTicks:{show:false} },
          yaxis: { labels: { formatter:(v)=> v >= 1000 ? (v/1000).toFixed(0)+"k" : v, style:{fontSize:"10px"} } },
          tooltip: { y:{formatter:(v)=> v.toLocaleString()+" msg"} },
          legend: { position:"bottom", fontSize:"11px" },
        };
        if (charts.totals) charts.totals.updateOptions(opts, true, true);
        else { charts.totals = new ApexCharts(document.querySelector("#chart-totals"), opts); charts.totals.render(); }
      }

      function renderRates(d) {
        const total = d.sent.reduce((a,b)=>a+b,0) + d.queued.reduce((a,b)=>a+b,0) + d.failed.reduce((a,b)=>a+b,0);
        const sentT = d.sent.reduce((a,b)=>a+b,0);
        const queuedT = d.queued.reduce((a,b)=>a+b,0);
        const failedT = d.failed.reduce((a,b)=>a+b,0);
        const pct = (v) => total>0 ? Number((v/total*100).toFixed(1)) : 0;
        const opts = {
          chart: { type:"donut", height:280, ...baseFont },
          colors: [themeColor('wa-deep'), themeColor('accent-amber'), themeColor('accent-coral')],
          labels: ["Delivered","Queued","Failed"],
          series: [pct(sentT), pct(queuedT), pct(failedT)],
          stroke: { width:3, colors:[themeColor('paper-0')] },
          legend: { position:"bottom", fontSize:"11px" },
          dataLabels: { formatter:(v)=> v.toFixed(1)+"%" },
          plotOptions: { pie:{ donut:{ size:"68%", labels:{ show:true, value:{ fontSize:"22px", fontFamily:"Fraunces, serif", fontWeight:500 }, total:{ show:true, label:"events", formatter:()=> total.toLocaleString() } } } } },
          tooltip: { y:{formatter:(v)=> v+"%"} },
        };
        if (charts.rates) charts.rates.updateOptions(opts, true, true);
        else { charts.rates = new ApexCharts(document.querySelector("#chart-rates"), opts); charts.rates.render(); }
      }

      function renderTypes() {
        const opts = {
          chart: { type:"donut", height:280, ...baseFont },
          colors: ["#7B61FF", themeColor('wa-deep'), themeColor('accent-amber'), themeColor('accent-coral'), "#13478A"],
          labels: ["Text", "Template", "Media", "Interactive", "Location"],
          series: [48, 32, 11, 6, 3],
          stroke: { width:3, colors:[themeColor('paper-0')] },
          legend: { position:"bottom", fontSize:"11px" },
          dataLabels: { formatter:(v)=> v.toFixed(1)+"%" },
          plotOptions: { pie:{ donut:{ size:"68%" } } },
        };
        new ApexCharts(document.querySelector("#chart-types"), opts).render();
      }

      function renderDevices() {
        const el = document.querySelector("#chart-devices");
        if (!el) return;
        let labels = [];
        let values = [];
        try { labels = JSON.parse(el.dataset.labels || "[]"); } catch (e) {}
        try { values = JSON.parse(el.dataset.values || "[]"); } catch (e) {}
        if (!labels.length) {
          el.innerHTML = '<div class="text-[12px] text-ink-500 py-6 text-center">No connected devices yet.</div>';
          return;
        }
        const opts = {
          chart: { type:"bar", height:280, ...baseFont, toolbar:{show:false} },
          colors: [themeColor('wa-deep')],
          series: [{ name:"Messages", data: values }],
          plotOptions: { bar: { horizontal:true, borderRadius:6, barHeight:"58%", distributed:false } },
          dataLabels: { enabled:true, style:{ colors:[themeColor('paper-0')], fontWeight:600, fontSize:"11px" }, formatter:(v)=> v.toLocaleString(), offsetX: -2 },
          xaxis: { categories: labels, labels:{ formatter:(v)=> v.toLocaleString(), style:{fontSize:"10px"} } },
          yaxis: { labels: { style:{ fontSize:"11px" } } },
          grid: { borderColor: muted, strokeDashArray:4 },
          tooltip: { y:{formatter:(v)=> v.toLocaleString()+" msg"} },
        };
        new ApexCharts(el, opts).render();
      }

      function renderHeatmap() {
        const days = ["Sun","Mon","Tue","Wed","Thu","Fri","Sat"];
        const series = days.map((d) => ({
          name: d,
          data: Array.from({length:24}, (_, h) => {
            // engagement bias: weekday morning + afternoon peaks
            const isWeekday = d !== "Sun" && d !== "Sat";
            const morning = Math.exp(-Math.pow(h-10,2)/8) * (isWeekday ? 88 : 35);
            const afternoon = Math.exp(-Math.pow(h-15,2)/6) * (isWeekday ? 100 : 50);
            const evening = Math.exp(-Math.pow(h-20,2)/12) * 45;
            const total = Math.max(2, Math.round(morning + afternoon + evening + (Math.random()*8-4)));
            return { x: String(h).padStart(2,"0"), y: total };
          })
        }));
        const opts = {
          chart: { type:"heatmap", height: 320, ...baseFont, toolbar:{show:false} },
          series,
          dataLabels: { enabled:false },
          colors: [themeColor('wa-deep')],
          plotOptions: { heatmap: { radius: 4, useFillColorAsStroke: false, colorScale: { ranges: [
            { from: 0,  to: 25, color: themeColor('paper-50'), name: "low" },
            { from: 26, to: 50, color: "#CFE3DA", name: "med-low" },
            { from: 51, to: 75, color: "#7FB89D", name: "med" },
            { from: 76, to: 92, color: "#2A8865", name: "high" },
            { from: 93, to: 999, color: themeColor('wa-deep'), name: "peak" },
          ] } } },
          xaxis: { type:"category", labels: { style:{fontSize:"10px", fontFamily:"JetBrains Mono"} } },
          yaxis: { labels: { style:{fontSize:"11px"} } },
          tooltip: { y:{ formatter:(v)=> v + "% read rate" } },
          grid: { padding: { left:8, right:8 } },
        };
        new ApexCharts(document.querySelector("#chart-heatmap"), opts).render();
      }

      function applyRange(key) {
        const d = RANGES[key] || RANGES["30d"];
        renderHeroSpark(d);
        renderVolume(d);
        renderTotals(d);
        renderRates(d);
      }

      applyRange("30d");
      renderTypes();
      renderDevices();
      renderHeatmap();

      document.querySelectorAll("#range-bar [data-range]").forEach((b) => b.addEventListener("click", () => {
        document.querySelectorAll("#range-bar [data-range]").forEach((x) => x.classList.toggle("active", x === b));
        applyRange(b.dataset.range);
      }));
}
