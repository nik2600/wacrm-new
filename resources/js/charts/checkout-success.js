import { themeColor } from '../theme-colors.js';

export default function init() {
    /* personalize */
      const APP = (window.WADESK_BRAND && window.WADESK_BRAND.appName) || 'WaDesk';
      const PLANS = {
        starter:{ name:'Starter', total:'₹ 0',         line:'Starter plan · Free forever',    amt:'Free',     subline:'No charge today' },
        growth: { name:'Growth',  total:'₹ 6,783',     line:'Growth plan · Yearly',           amt:'₹ 5,748',  subline:'12 months · billed annually · auto-renews Apr 27, 2027' },
        pro:    { name:'Pro',     total:'₹ 16,992',    line:'Pro plan · Yearly',              amt:'₹ 14,400', subline:'12 months · billed annually · auto-renews Apr 27, 2027' },
        'topup-1000': { name:'Wallet top-up', total:'₹ 1,180', line:'Wallet top-up', amt:'₹ 1,000', subline:'₹ 1,000 credit added to your ' + APP + ' wallet' },
      };
      function qs(k){ const m = location.search.match(new RegExp('[?&]'+k+'=([^&]*)')); return m ? decodeURIComponent(m[1]) : ''; }
      const p = PLANS[(qs('plan') || qs('item') || 'pro').toLowerCase()] || PLANS.pro;

      document.getElementById('plan-name').textContent  = p.name;
      document.getElementById('line-name').textContent  = p.line;
      document.getElementById('line-sub').textContent   = p.subline;
      document.getElementById('line-amount').textContent= p.amt;
      document.getElementById('total-paid').textContent = p.total;
      document.getElementById('paid-date').textContent  = new Date().toLocaleDateString('en-IN', { month:'short', day:'numeric', year:'numeric' }) + ' · ' + new Date().toLocaleTimeString([], { hour:'2-digit', minute:'2-digit' }) + ' IST';
      document.getElementById('inv-num').textContent    = (2400 + Math.floor(Math.random() * 99));

      /* confetti */
      const colors = [themeColor('wa-green'), themeColor('wa-deep'), themeColor('accent-amber'), '#5BA0F2', '#7B57C7', themeColor('accent-coral')];
      for (let i = 0; i < 60; i++) {
        const c = document.createElement('div');
        c.className = 'confetti';
        c.style.left = Math.random() * 100 + 'vw';
        c.style.background = colors[i % colors.length];
        c.style.animationDelay = (Math.random() * 4) + 's';
        c.style.animationDuration = (3 + Math.random() * 3) + 's';
        document.body.appendChild(c);
      }
      /* clear confetti after 8s for performance */
      setTimeout(() => document.querySelectorAll('.confetti').forEach(c => c.remove()), 8000);
}
