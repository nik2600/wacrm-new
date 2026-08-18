import ApexCharts from 'apexcharts';
import { themeColor } from '../theme-colors.js';

export default function init() {
    // Read the per-device numbers the blade serialised onto the
    // chart hosts. Falls back to zeroed-out arrays when the
    // device hasn't sent anything yet, so the chart still renders.
    const volEl = document.querySelector('#chart-volume');
    const stEl  = document.querySelector('#chart-status');
    if (!volEl || !stEl) return;
    let sentData = [], failedData = [];
    try { sentData   = JSON.parse(volEl.dataset.sent   || '[]'); } catch {}
    try { failedData = JSON.parse(volEl.dataset.failed || '[]'); } catch {}
    const delivered = parseInt(stEl.dataset.delivered || '0', 10);
    const failedAll = parseInt(stEl.dataset.failed    || '0', 10);

    // Volume chart — daily over 7 days, sent + failed stacked
      new ApexCharts(volEl, {
        chart: { type:'bar', height:260, toolbar:{show:false}, fontFamily:'Plus Jakarta Sans', stacked:true },
        series: [
          { name:'Sent',   data: sentData.length   ? sentData   : [0,0,0,0,0,0,0] },
          { name:'Failed', data: failedData.length ? failedData : [0,0,0,0,0,0,0] }
        ],
        xaxis: { categories:['Mon','Tue','Wed','Thu','Fri','Sat','Sun'], labels:{style:{colors:themeColor('ink-500'),fontSize:'10px',fontFamily:'JetBrains Mono'}}, axisBorder:{show:false}, axisTicks:{show:false} },
        yaxis: { labels:{style:{colors:themeColor('ink-500'),fontSize:'10px',fontFamily:'JetBrains Mono'}} },
        colors: [themeColor('wa-deep'), themeColor('accent-coral')],
        plotOptions: { bar: { borderRadius:6, columnWidth:'48%' } },
        dataLabels: { enabled:false },
        grid: { borderColor:themeColor('paper-100'), strokeDashArray:3 },
        legend: { position:'top', horizontalAlign:'right', fontSize:'11px', fontFamily:'JetBrains Mono', labels:{colors:'#3A5A55'} },
        tooltip:{ y:{formatter:v=>v.toLocaleString()+' msg'} }
      }).render();

      // Status donut — uses the device's own delivered/failed
      // totals that the blade put on data-* attributes.
      const total = delivered + failedAll;
      new ApexCharts(stEl, {
        chart: { type:'donut', height:200, fontFamily:'Plus Jakarta Sans' },
        series: total ? [delivered, failedAll] : [1],
        labels: total ? ['Delivered','Failed'] : ['No data'],
        colors: total ? [themeColor('wa-deep'), themeColor('accent-coral')] : [themeColor('paper-200')],
        legend: { show:false },
        dataLabels: { enabled:false },
        plotOptions: { pie: { donut: { size:'68%', labels:{ show:true, total:{show:true, label:'Total', fontFamily:'JetBrains Mono', fontSize:'11px', color:themeColor('ink-500'), formatter:()=> total.toLocaleString()}, value:{fontFamily:'Fraunces', fontSize:'22px', color:themeColor('ink-900')} } } } },
        stroke: { width:2, colors:[themeColor('paper-0')] }
      }).render();

      // Heatmap (24h × 7d) — only renders if the host element
      // exists on the page (heatmap section may be omitted on
      // devices with no traffic).
      const heatHost = document.querySelector('#chart-heatmap');
      if (!heatHost) return;
      const days = ['Mon','Tue','Wed','Thu','Fri','Sat','Sun'];
      const dayBaseline = sentData.length ? sentData : days.map(() => 0);
      const heatSeries = days.map((d, di) => ({
        name: d,
        data: Array.from({length:24},(_,h)=>{
          const peak = h>=9 && h<=20 ? 1 : 0.25;
          const wknd = (d==='Sat'||d==='Sun') ? 0.55 : 1;
          const base = (dayBaseline[di] || 0) / 24;
          return { x: String(h).padStart(2,'0'), y: Math.round(base * peak * wknd) };
        })
      }));
      new ApexCharts(heatHost, {
        chart: { type:'heatmap', height:260, toolbar:{show:false}, fontFamily:'Plus Jakarta Sans' },
        series: heatSeries,
        colors: [themeColor('wa-deep')],
        plotOptions: { heatmap: { radius:3, colorScale:{ ranges:[
          { from:0, to:8, color:themeColor('paper-50'), name:'idle' },
          { from:9, to:20, color:themeColor('wa-mint'), name:'low' },
          { from:21, to:35, color:'#7FCDB9', name:'mid' },
          { from:36, to:60, color:themeColor('wa-deep'), name:'high' }
        ] } } },
        dataLabels: { enabled:false },
        xaxis: { labels:{style:{colors:themeColor('ink-500'),fontSize:'9px',fontFamily:'JetBrains Mono'}}, axisBorder:{show:false}, axisTicks:{show:false} },
        yaxis: { labels:{style:{colors:themeColor('ink-500'),fontSize:'10px',fontFamily:'JetBrains Mono'}} },
        grid: { padding:{ top:0, right:0, bottom:0, left:0 } },
        legend: { show:false }
      }).render();
}
