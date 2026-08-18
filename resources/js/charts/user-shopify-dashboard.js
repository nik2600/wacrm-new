import ApexCharts from 'apexcharts';
import { themeColor } from '../theme-colors.js';

export default function init() {
    /* ──────────── tab switcher ──────────── */
      function getTab() {
        const m = location.search.match(/tab=([a-z]+)/);
        return (m ? m[1] : 'overview').toLowerCase();
      }
      const TAB_LABELS = { overview:'Overview', orders:'Orders', products:'Products', customers:'Customers', catalog:'WhatsApp Catalog', analytics:'Analytics' };
      const TAB_TITLES = {
        overview:  ['Bloomly <span class="italic text-wa-deep">store</span>', 'Performance overview of your active automations and store activity.'],
        orders:    ['Orders', 'View and manage every order coming in from Shopify.'],
        products:  ['Products', 'Browse the catalog synced from Bloomly.'],
        customers: ['Customers', 'Customers synced from your Shopify store.'],
        catalog:   ['WhatsApp <span class="italic text-wa-deep">catalog</span>', 'Sync product cards into WhatsApp and share them in a single tap.'],
        analytics: ['Sales <span class="italic text-wa-deep">analytics</span>', 'Forecast, year-over-year trends and per-product performance.'],
      };

      function activateTab(tab) {
        document.querySelectorAll('[data-pane]').forEach(p => p.classList.toggle('hidden', p.dataset.pane !== tab));
        document.querySelectorAll('[data-tab]').forEach(t => t.classList.toggle('active', t.dataset.tab === tab));
        document.getElementById('bc-tab').textContent = TAB_LABELS[tab] || 'Overview';
        const [title, desc] = TAB_TITLES[tab] || TAB_TITLES.overview;
        document.getElementById('page-title').innerHTML = title;
        document.getElementById('page-desc').textContent = desc;
        if (tab === 'analytics') setTimeout(renderAnalyticsCharts, 50);
        if (tab === 'overview')  setTimeout(renderOverviewChart, 50);
      }

      /* ──────────── seed data ──────────── */
      const BRANDS = [
        { name:'Nike',        color:'#3FBE6E' },
        { name:'Adidas',      color:'#E87A5D' },
        { name:'Puma',        color:'#7B57C7' },
        { name:'New Balance', color:'#F2B5A0' },
        { name:'Converse',    color:'#1F4540' },
        { name:'Reebok',      color:'#92A29E' },
        { name:'Sketchers',   color:'#3D7CD3' },
      ];

      const NEW_ARRIVALS = [
        { name:'Cloud Shift Lightweight Runner Pro Edition', price:8225, rating:5.0, color:'#D8F571', accent:'#1F4540' },
        { name:'Wave Strike Dynamic Boost Sneaker',          price:9970, rating:4.7, color:'#F4B7DC', accent:'#7B57C7' },
        { name:'Titan Edge High Impact Stability Trainers',  price:5410, rating:3.5, color:'#1F4540', accent:'#FBFAF6' },
        { name:'Velocity Boost Xtreme High Shock Absorbers', price:9140, rating:4.9, color:'#F2F2F2', accent:'#E87A5D' },
      ];
      const POPULAR = [
        { name:'Air Stride Pro 23',           price:7890, rating:4.6, color:'#FCBFAB', accent:'#A1431F' },
        { name:'Glide Tempo Marathon',        price:9450, rating:4.8, color:'#3FBE6E', accent:'#FBFAF6' },
        { name:'Stratos Cushion Walk',        price:6320, rating:4.4, color:'#5BA0F2', accent:'#FBFAF6' },
        { name:'Sprint Aero Hybrid',          price:8800, rating:4.9, color:'#1F4540', accent:'#D8F571' },
      ];

      const TOP_PRODUCTS = [
        { name:'Black Sports Jersey',  units:78247 },
        { name:'Stainless Steel Tumbler', units:21008 },
        { name:'50-Piece Drawing Set', units:49083 },
        { name:'11-Piece Crochet Hook Set', units:24781 },
        { name:'Pink Travel Backpack', units:22446 },
      ];

      const ORDERS = [
        { id:'#10287', customer:'Anya Mehra', email:'anya@bloomly.in', total:'₹ 4,820', pay:'paid', ful:'fulfilled', date:'Today 14:42' },
        { id:'#10286', customer:'Rahul Iyer', email:'rahul@example.com', total:'₹ 12,140', pay:'paid', ful:'unfulfilled', date:'Today 13:08' },
        { id:'#10285', customer:'Guest', email:'—', total:'₹ 1,950', pay:'pending', ful:'unfulfilled', date:'Today 11:24' },
        { id:'#10284', customer:'Priya Reddy', email:'priya@bloomly.in', total:'₹ 8,820', pay:'paid', ful:'fulfilled', date:'Yesterday' },
        { id:'#10283', customer:'Kyung J.', email:'kyung@example.com', total:'₹ 3,210', pay:'refunded', ful:'fulfilled', date:'2 days ago' },
        { id:'#10282', customer:'Vetrick R.', email:'vetrick@bloomly.in', total:'₹ 6,440', pay:'paid', ful:'partial', date:'2 days ago' },
      ];

      const CUSTOMERS = [
        { name:'Anya Mehra', email:'anya@bloomly.in', phone:'+91 98765 43210', orders:14, spent:'₹ 1.8L', since:'Jan 2024' },
        { name:'Rahul Iyer', email:'rahul@example.com', phone:'+91 90220 18821', orders:9, spent:'₹ 92,400', since:'Mar 2024' },
        { name:'Priya Reddy', email:'priya@bloomly.in', phone:'+91 99888 11221', orders:21, spent:'₹ 2.4L', since:'Nov 2023' },
        { name:'Kyung Jin', email:'kyung@example.com', phone:'+82 10 1234 5678', orders:3, spent:'₹ 18,200', since:'Aug 2025' },
        { name:'Vetrick R.', email:'vetrick@bloomly.in', phone:'+91 98098 11345', orders:6, spent:'₹ 48,600', since:'Apr 2024' },
      ];

      const CATALOG = [
        { name:'Nike Air Max 270', rid:'SKU-AM270', price:'₹ 11,640', avail:'in stock',  sync:'synced' },
        { name:'Nike Dunk Low',   rid:'SKU-DLL01', price:'₹ 9,140',  avail:'in stock',  sync:'synced' },
        { name:'Nike Air Force 1', rid:'SKU-AF1-W',price:'₹ 8,050',  avail:'low',       sync:'syncing' },
        { name:'Adidas Ultraboost 22', rid:'SKU-UB22', price:'₹ 14,200', avail:'in stock', sync:'synced' },
        { name:'Puma RS-X Bold',  rid:'SKU-RSX01',  price:'₹ 7,300',  avail:'out',       sync:'failed' },
      ];

      const FORECAST = [
        { sku:'BLCK-SPRT-JRSY', name:'Black Sports Jersey',     model:'AI model',                 d1:303, d7:1854, p7:3369, w:'+81.7%', d30:9736, p30:14246, m:'+46.3%', d90:21813, p90:26209, badge:'A' },
        { sku:'STLS-STLL-TBLR', name:'Stainless Steel Tumbler',model:'AI model',                 d1:42,  d7:295,  p7:652.6,w:'+121.2%',d30:2043, p30:3188,  m:'+56.1%', d90:7543,  p90:7490,  badge:'B' },
        { sku:'50PC-DRWN-SETT', name:'50-Piece Drawing Set',    model:'AI model · with adjustments', d1:113, d7:843,  p7:1539, w:'+82.6%', d30:11173,p30:7098,  m:'−36.5%', d90:18818, p90:13939, badge:'A' },
        { sku:'11PC-CRCH-HSET', name:'11-Piece Crochet Hook Set', model:'Moving average',         d1:157, d7:880,  p7:1047, w:'+19.0%', d30:4982, p30:3969,  m:'−20.3%', d90:11001, p90:8459,  badge:'B' },
        { sku:'GREY-CHNL-MATS', name:'Grey Chenille Bath Mat Set', model:'Moving average',        d1:92,  d7:741,  p7:784.1,w:'+5.8%',  d30:4553, p30:3738,  m:'−17.9%', d90:8241,  p90:7087,  badge:'B' },
        { sku:'PINK-TRVL-BKPK', name:'Pink Travel Backpack',    model:'AI model',                 d1:89,  d7:661,  p7:829.2,w:'+25.4%', d30:4125, p30:3187,  m:'−22.7%', d90:7382,  p90:7084,  badge:'B' },
        { sku:'BLCK-YOGA-MATT', name:'Black yoga mat padded 1/2-inch with…', model:'AI model',    d1:40,  d7:270,  p7:772.3,w:'+186.0%',d30:1387, p30:3434,  m:'+147.6%',d90:3339,  p90:7934,  badge:'B' },
      ];

      const ANALYTICS = [
        { sku:'BLCK-SPRT-JRSY', name:'Black Sports Jersey',          sales:3488512, perDay:9558, contrib:'+14.2%', vy:'−18.7%', units:78247, perDayU:214.4, avg:44.58, badge:'A' },
        { sku:'STLS-STLL-TBLR', name:'Stainless Steel Tumbler',     sales:1561294, perDay:4278, contrib:'+6.3%',  vy:'−45.3%', units:21008, perDayU:57.6,  avg:74.32, badge:'B' },
        { sku:'50PC-DRWN-SETT', name:'50-Piece Drawing Set',         sales:2261434, perDay:6196, contrib:'+9.2%',  vy:'+137.3%',units:49083, perDayU:134.5, avg:46.07, badge:'A' },
        { sku:'11PC-CRCH-HSET', name:'11-Piece Crochet Hook Set',   sales:1538218, perDay:4214, contrib:'+6.2%',  vy:'+161.3%',units:24781, perDayU:67.9,  avg:62.07, badge:'B' },
        { sku:'GREY-CHNL-MATS', name:'Grey Chenille Bath Mat Set',  sales:1416454, perDay:3881, contrib:'+5.8%',  vy:'+12.3%', units:24929, perDayU:68.3,  avg:56.82, badge:'B' },
        { sku:'PINK-TRVL-BKPK', name:'Pink Travel Backpack',         sales:1223456, perDay:3352, contrib:'+5.0%',  vy:'+169.7%',units:22446, perDayU:61.5,  avg:54.51, badge:'B' },
        { sku:'BLCK-YOGA-MATT', name:'Black yoga mat padded 1/2-inch with nylon strap.', sales:1232974, perDay:3378, contrib:'+5.0%', vy:'−34.2%', units:21605, perDayU:59.2, avg:57.07, badge:'B' },
        { sku:'BLCK-WOMN-SORT', name:"Black Women's Running Shorts with Adjustable Elastic Band", sales:971808, perDay:2662, contrib:'+3.9%', vy:'+70.0%', units:19507, perDayU:53.4, avg:49.82, badge:'C' },
        { sku:'FRVS-PWDR-MIXX', name:'Fruit, Veggies and Spices Powder Mix', sales:905229, perDay:2480, contrib:'+3.7%', vy:'+59.7%', units:13294, perDayU:36.4, avg:68.09, badge:'C' },
      ];

      /* ──────────── renderers ──────────── */
      function rupee(n) { return '₹ ' + n.toLocaleString('en-IN'); }
      function fmt(n)   { return n.toLocaleString('en-US'); }
      function delta(s) { return `<span class="font-mono ${s.startsWith('−') || s.startsWith('-') ? 'delta-down' : 'delta-up'}">${s}</span>`; }

      // sneaker silhouette
      function shoeSvg(color, accent) {
        return `<svg viewBox="0 0 200 110" class="w-full h-full">
          <ellipse cx="100" cy="92" rx="78" ry="5" fill="rgba(0,0,0,0.06)"/>
          <path d="M30 78 Q40 58 70 53 L130 48 Q160 48 175 73 L175 88 Q175 92 170 92 L40 92 Q30 92 30 86 Z" fill="${color}"/>
          <path d="M70 53 Q90 28 130 33 Q150 36 145 53" fill="${accent}" opacity="0.75"/>
          <circle cx="100" cy="55" r="11" fill="${accent}"/>
          <path d="M88 60 Q100 52 112 60" fill="none" stroke="${accent}" stroke-width="2"/>
        </svg>`;
      }

      // Brands
      document.getElementById('brand-chips').innerHTML = BRANDS.map(b => `
        <a href="#" class="bg-paper-0 border border-paper-200 rounded-2xl p-3 hover:border-wa-deep hover:shadow-card transition flex flex-col items-center gap-2">
          <span class="text-[12px] font-semibold text-ink-900">${b.name}</span>
          <span class="w-full h-12 prod-img rounded-lg grid place-items-center">
            ${shoeSvg(b.color, '#FBFAF6')}
          </span>
        </a>
      `).join('');

      function productCard(p) {
        const stars = Math.round(p.rating);
        return `<div class="bg-paper-0 border border-paper-200 rounded-2xl p-3 shadow-card hover:border-wa-deep hover:shadow-soft transition">
          <div class="prod-img rounded-xl h-32 grid place-items-center">${shoeSvg(p.color, p.accent)}</div>
          <div class="text-[12.5px] font-medium leading-snug mt-3 line-clamp-2 min-h-[36px]">${p.name}</div>
          <div class="mt-3 flex items-center justify-between">
            <span class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded-full bg-accent-amber/20 text-[10px] font-mono text-ink-800">
              <svg viewBox="0 0 16 16" class="w-2.5 h-2.5" fill="#E5A04E"><path d="M8 1l2.2 4.4 4.8.7-3.5 3.4.8 4.8L8 12l-4.3 2.3.8-4.8L1 6.1l4.8-.7z"/></svg>
              ${p.rating.toFixed(1)}
            </span>
            <span class="font-semibold text-[13px]">${rupee(p.price)}</span>
            <button class="px-2 py-1 rounded-full border border-paper-200 hover:bg-paper-50 text-[10.5px] font-semibold inline-flex items-center gap-1">
              <svg viewBox="0 0 16 16" class="w-2.5 h-2.5" fill="none" stroke="currentColor" stroke-width="2"><path d="M2 4h2l1.5 8h7l1-5H6"/></svg>
              Add
            </button>
          </div>
        </div>`;
      }
      document.getElementById('new-arrivals').innerHTML    = NEW_ARRIVALS.map(productCard).join('');
      document.getElementById('popular-products').innerHTML = POPULAR.map(productCard).join('');

      // Top products list
      document.getElementById('top-products').innerHTML = TOP_PRODUCTS.map((p, i) => `
        <li class="flex items-center gap-3">
          <span class="w-7 h-7 rounded-md grid place-items-center font-mono text-[11px] font-semibold bg-paper-50 border border-paper-200 text-ink-700">${i+1}</span>
          <div class="flex-1 min-w-0">
            <div class="text-[12.5px] font-medium truncate">${p.name}</div>
            <div class="font-mono text-[10px] text-ink-500">${fmt(p.units)} units</div>
          </div>
          <div class="w-20 h-1.5 rounded-full bg-paper-100 overflow-hidden">
            <div class="h-full bg-wa-deep" style="width:${Math.min(100, p.units/800)}%"></div>
          </div>
        </li>`).join('');

      // Automations
      document.getElementById('automations-rows').innerHTML = [
        ['Abandoned checkout', 'green', '3,214', '486', '128', '26.3%', '₹ 6.2L'],
        ['Order confirmation', 'green', '512',   '512', '512', '100%',  '₹ 24.6L'],
        ['Shipping update',    'green', '418',   '402', '388', '96.5%', '—'],
        ['Review request',     'green', '320',   '180', '64',  '35.5%', '—'],
        ['Win-back · 30 days', 'amber', '120',   '32',  '7',   '21.8%', '₹ 38,200'],
        ['Welcome new buyer',  'green', '42',    '42',  '12',  '28.5%', '₹ 1.6L'],
      ].map(r => `<tr class="hover:bg-paper-50/60">
        <td class="px-4 py-2.5 font-medium">${r[0]}</td>
        <td class="px-2 py-2.5"><span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10.5px] font-mono ${r[1]==='green'?'bg-wa-mint text-wa-deep':'bg-accent-amber/20 text-[#7B5A14]'}"><span class="w-1.5 h-1.5 rounded-full ${r[1]==='green'?'bg-wa-green':'bg-accent-amber'}"></span>${r[1]==='green'?'Active':'Draft'}</span></td>
        <td class="px-2 py-2.5 font-mono">${r[2]}</td>
        <td class="px-2 py-2.5 font-mono">${r[3]}</td>
        <td class="px-2 py-2.5 font-mono">${r[4]}</td>
        <td class="px-2 py-2.5 font-mono">${r[5]}</td>
        <td class="px-2 py-2.5 font-mono">${r[6]}</td>
        <td class="px-4 py-2.5 text-right text-ink-500"><svg viewBox="0 0 16 16" class="w-4 h-4 inline" fill="currentColor"><circle cx="3" cy="8" r="1.4"/><circle cx="8" cy="8" r="1.4"/><circle cx="13" cy="8" r="1.4"/></svg></td>
      </tr>`).join('');

      // Orders
      const PAY = { paid:['bg-wa-mint','text-wa-deep','bg-wa-green'], pending:['bg-accent-amber/20','text-[#7B5A14]','bg-accent-amber'], refunded:['bg-[#E0EBF7]','text-[#13478A]','bg-[#3D7CD3]'] };
      document.getElementById('orders-rows').innerHTML = ORDERS.map(o => {
        const c = PAY[o.pay] || PAY.pending;
        return `<tr class="hover:bg-paper-50/60">
          <td class="px-4 py-2.5 font-mono text-wa-deep font-semibold">${o.id}</td>
          <td class="px-2 py-2.5"><div class="font-medium">${o.customer}</div><div class="text-[10.5px] text-ink-500">${o.email}</div></td>
          <td class="px-2 py-2.5 font-semibold">${o.total}</td>
          <td class="px-2 py-2.5"><span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10.5px] font-mono ${c[0]} ${c[1]}"><span class="w-1.5 h-1.5 rounded-full ${c[2]}"></span>${o.pay}</span></td>
          <td class="px-2 py-2.5"><span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10.5px] font-mono ${o.ful==='fulfilled'?'bg-wa-mint text-wa-deep':o.ful==='partial'?'bg-accent-amber/20 text-[#7B5A14]':'bg-paper-50 text-ink-700'}">${o.ful}</span></td>
          <td class="px-2 py-2.5 font-mono text-[11px] text-ink-700">${o.date}</td>
        </tr>`;
      }).join('');

      // Customers
      document.getElementById('customers-rows').innerHTML = CUSTOMERS.map(c => `
        <tr class="hover:bg-paper-50/60">
          <td class="px-4 py-2.5 font-medium">${c.name}</td>
          <td class="px-2 py-2.5 text-ink-700">${c.email}</td>
          <td class="px-2 py-2.5 font-mono text-[11px] text-ink-700">${c.phone}</td>
          <td class="px-2 py-2.5 font-mono">${c.orders}</td>
          <td class="px-2 py-2.5 font-semibold">${c.spent}</td>
          <td class="px-2 py-2.5 font-mono text-[11px] text-ink-500">${c.since}</td>
        </tr>`).join('');

      // Catalog
      const CAT_AVAIL = { 'in stock':['bg-wa-mint','text-wa-deep'], low:['bg-accent-amber/20','text-[#7B5A14]'], out:['bg-accent-coral/15','text-accent-coral'] };
      const CAT_SYNC  = { synced:['bg-wa-mint','text-wa-deep'], syncing:['bg-[#E0EBF7]','text-[#13478A]'], failed:['bg-accent-coral/15','text-accent-coral'] };
      document.getElementById('catalog-rows').innerHTML = CATALOG.map(p => `
        <tr class="hover:bg-paper-50/60">
          <td class="px-4 py-2.5"><input type="checkbox"></td>
          <td class="px-2 py-2.5"><div class="w-9 h-9 rounded-lg prod-img grid place-items-center">${shoeSvg('#3FBE6E','#FBFAF6')}</div></td>
          <td class="px-2 py-2.5 font-medium">${p.name}</td>
          <td class="px-2 py-2.5"><code class="font-mono text-[10.5px] bg-paper-50 px-1.5 py-0.5 rounded">${p.rid}</code></td>
          <td class="px-2 py-2.5 font-semibold">${p.price}</td>
          <td class="px-2 py-2.5"><span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10.5px] font-mono ${CAT_AVAIL[p.avail][0]} ${CAT_AVAIL[p.avail][1]}">${p.avail}</span></td>
          <td class="px-2 py-2.5"><span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10.5px] font-mono ${CAT_SYNC[p.sync][0]} ${CAT_SYNC[p.sync][1]}">${p.sync}</span></td>
        </tr>`).join('');

      // Forecast & Analytics rows
      function badge(letter) {
        const map = { A:'#3FBE6E', B:'#5BA0F2', C:'#E5A04E' };
        return `<span class="w-5 h-5 rounded-full grid place-items-center text-[9.5px] font-mono font-bold text-paper-0" style="background:${map[letter]||'#92A29E'}">${letter}</span>`;
      }
      function fc(n){ return typeof n === 'number' ? fmt(n) : n; }

      document.getElementById('forecast-rows').innerHTML = FORECAST.map(r => `
        <tr class="hover:bg-paper-50/60">
          <td class="px-3 py-2.5">
            <div class="flex items-center gap-2">${badge(r.badge)}
              <div class="min-w-0">
                <div class="font-mono text-[11px] text-ink-900">${r.sku}</div>
                <div class="text-[11px] text-ink-500 truncate max-w-[220px]">${r.name}</div>
              </div>
            </div>
          </td>
          <td class="px-3 py-2.5 text-ink-700">Amazon US</td>
          <td class="px-3 py-2.5 text-ink-700">${r.model}</td>
          <td class="text-right px-2 py-2.5 font-mono">${fc(r.d1)}</td>
          <td class="text-right px-2 py-2.5 font-mono"><div>${fc(r.d7)}</div><div class="text-[10px] text-ink-500">${(r.d7/7).toFixed(1)}/day</div></td>
          <td class="text-right px-2 py-2.5 font-mono"><div>${fc(r.p7)}</div><div class="text-[10px] text-ink-500">${(r.p7/7).toFixed(1)}/day</div></td>
          <td class="text-right px-2 py-2.5">${delta(r.w)}</td>
          <td class="text-right px-2 py-2.5 font-mono"><div>${fc(r.d30)}</div><div class="text-[10px] text-ink-500">${(r.d30/30).toFixed(1)}/day</div></td>
          <td class="text-right px-2 py-2.5 font-mono"><div>${fc(r.p30)}</div><div class="text-[10px] text-ink-500">${(r.p30/30).toFixed(1)}/day</div></td>
          <td class="text-right px-2 py-2.5">${delta(r.m)}</td>
          <td class="text-right px-2 py-2.5 font-mono"><div>${fc(r.d90)}</div><div class="text-[10px] text-ink-500">${(r.d90/90).toFixed(1)}/day</div></td>
          <td class="text-right px-2 py-2.5 font-mono"><div>${fc(r.p90)}</div><div class="text-[10px] text-ink-500">${(r.p90/90).toFixed(1)}/day</div></td>
        </tr>`).join('');

      document.getElementById('analytics-rows').innerHTML = ANALYTICS.map(r => `
        <tr class="hover:bg-paper-50/60">
          <td class="px-3 py-2.5">
            <div class="flex items-center gap-2">${badge(r.badge)}
              <div class="min-w-0">
                <div class="font-mono text-[11px] text-ink-900">${r.sku}</div>
                <div class="text-[11px] text-ink-500 truncate max-w-[260px]">${r.name}</div>
              </div>
            </div>
          </td>
          <td class="px-3 py-2.5 text-ink-700">Amazon US</td>
          <td class="text-right px-3 py-2.5 font-mono">${fc(r.sales)}</td>
          <td class="text-right px-3 py-2.5 font-mono">${fc(r.perDay)}</td>
          <td class="text-right px-3 py-2.5">${delta(r.contrib)}</td>
          <td class="text-right px-3 py-2.5">${delta(r.vy)}</td>
          <td class="text-right px-3 py-2.5 font-mono">${fc(r.units)}</td>
          <td class="text-right px-3 py-2.5 font-mono">${r.perDayU}</td>
          <td class="text-right px-3 py-2.5 font-mono">${r.avg}</td>
        </tr>`).join('');

      /* ──────────── charts (Apex) ──────────── */
      let revChart, fcChart, yoyChart;

      function renderOverviewChart() {
        if (revChart || !document.getElementById('revenue-chart')) return;
        const series = [];
        for (let i = 0; i < 30; i++) series.push(50000 + Math.round(Math.sin(i/3)*8000) + Math.round(Math.random()*15000));
        const dates = Array.from({ length:30 }, (_, i) => {
          const d = new Date(); d.setDate(d.getDate()-(29-i));
          return d.toLocaleDateString('en-US', { month:'short', day:'numeric' });
        });
        revChart = new ApexCharts(document.getElementById('revenue-chart'), {
          chart: { type:'area', height:260, toolbar:{ show:false }, fontFamily:'Plus Jakarta Sans' },
          series: [{ name:'Revenue', data:series }],
          xaxis: { categories: dates, labels:{ style:{ colors:themeColor('ink-500'), fontSize:'10px' } }, axisBorder:{ show:false }, axisTicks:{ show:false } },
          yaxis: { labels:{ style:{ colors:themeColor('ink-500'), fontSize:'10px' }, formatter:v => '₹'+(v/1000).toFixed(0)+'k' } },
          stroke: { curve:'smooth', width:2.5, colors:[themeColor('wa-deep')] },
          fill: { type:'gradient', gradient:{ shadeIntensity:1, opacityFrom:0.35, opacityTo:0.05, stops:[0,100], colorStops:[{ offset:0, color:themeColor('wa-green') }, { offset:100, color:themeColor('wa-deep') }] } },
          grid: { borderColor:themeColor('paper-100'), strokeDashArray:3 },
          tooltip: { y:{ formatter: v => '₹ '+v.toLocaleString('en-IN') } },
          dataLabels: { enabled:false },
        });
        revChart.render();
      }

      function renderAnalyticsCharts() {
        if (!fcChart && document.getElementById('forecast-chart')) {
          const past = [], future = [];
          const start = new Date(); start.setMonth(start.getMonth()-24);
          for (let i = 0; i < 365 * 2; i++) {
            const d = new Date(start); d.setDate(start.getDate()+i);
            let v = 1500 + Math.round(Math.sin(i/30)*400) + Math.round(Math.random()*600);
            if (i % 60 < 3) v += 3500;
            past.push([d.getTime(), v]);
          }
          const lastDate = past[past.length-1][0];
          for (let i = 1; i <= 365; i++) {
            const d = new Date(lastDate); d.setDate(d.getDate()+i);
            let v = 1500 + Math.round(Math.sin((730+i)/30)*400) + Math.round(Math.random()*500);
            if (i % 60 < 3) v += 3000;
            future.push([d.getTime(), v]);
          }
          fcChart = new ApexCharts(document.getElementById('forecast-chart'), {
            chart: { type:'line', height:280, toolbar:{ show:false }, fontFamily:'Plus Jakarta Sans' },
            series: [
              { name:'Adjusted sales', type:'area', data: past },
              { name:'Adjusted forecast', type:'line', data: future },
            ],
            xaxis: { type:'datetime', labels:{ style:{ colors:themeColor('ink-500'), fontSize:'10px' } }, axisBorder:{ show:false } },
            yaxis: { labels:{ style:{ colors:themeColor('ink-500'), fontSize:'10px' }, formatter: v => (v/1000).toFixed(1)+'K' } },
            stroke: { width:[1.5, 2], curve:'smooth', dashArray:[0, 5], colors:[themeColor('accent-amber'),themeColor('accent-amber')] },
            fill: { type:['gradient','solid'], gradient:{ opacityFrom:0.18, opacityTo:0, stops:[0,100] }, colors:[themeColor('accent-amber'),themeColor('accent-amber')] },
            grid: { borderColor:themeColor('paper-100'), strokeDashArray:3 },
            legend: { show:false },
            dataLabels: { enabled:false },
            annotations: {
              xaxis: [{ x: lastDate, borderColor:'#92A29E', strokeDashArray:4, label:{ text:'Today', style:{ color:'#3A5A55', background:themeColor('paper-0'), fontSize:'10px' } } }],
            },
          });
          fcChart.render();
        }

        if (!yoyChart && document.getElementById('yoy-chart')) {
          const months = ['Dec 2023','Jan 2024','Feb 2024','Mar 2024','Apr 2024','May 2024','Jun 2024','Jul 2024','Aug 2024','Sep 2024','Oct 2024','Nov 2024','Dec 2024'];
          const last  = [2.8, 2.2, 1.9, 2.0, 1.9, 1.9, 2.0, 2.0, 2.1, 2.0, 2.5, 3.2, 1.8];
          const prev  = [2.6, 2.5, 1.7, 1.7, 1.6, 1.7, 1.7, 1.7, 1.8, 1.9, 2.4, 2.6, 0.7];
          yoyChart = new ApexCharts(document.getElementById('yoy-chart'), {
            chart: { type:'bar', height:300, toolbar:{ show:false }, fontFamily:'Plus Jakarta Sans' },
            series: [
              { name:'Same 365 days, 1 year before', data: prev },
              { name:'Last 365 days', data: last },
            ],
            xaxis: { categories: months, labels:{ style:{ colors:themeColor('ink-500'), fontSize:'10px' } }, axisBorder:{ show:false } },
            yaxis: { labels:{ style:{ colors:themeColor('ink-500'), fontSize:'10px' }, formatter: v => (window.WA_CURRENCY || '$')+v.toFixed(1)+'M' } },
            plotOptions: { bar:{ columnWidth:'58%', borderRadius:3 } },
            colors: ['#D6CDB6', '#3D7CD3'],
            grid: { borderColor:themeColor('paper-100'), strokeDashArray:3 },
            legend: { show:false },
            dataLabels: { enabled:false },
            tooltip: { y:{ formatter: v => (window.WA_CURRENCY || '$')+(v).toFixed(2)+'M' } },
          });
          yoyChart.render();
        }
      }

      /* ──────────── boot ──────────── */
      activateTab(getTab());
      document.querySelectorAll('[data-tab]').forEach(a => a.addEventListener('click', e => {
        e.preventDefault();
        const t = a.dataset.tab;
        history.pushState(null, '', '?tab=' + t);
        activateTab(t);
        window.scrollTo({ top:0, behavior:'smooth' });
      }));
      window.addEventListener('popstate', () => activateTab(getTab()));
}
