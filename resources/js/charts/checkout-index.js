export default function init() {
    /* plan / item ----------------------------------- */
      const PLANS = {
        starter:  { name:'Starter plan', sub:'Free forever',         price: 0 },
        growth:   { name:'Growth plan',  sub:'Yearly billing · save 20%', price: 5748 },
        pro:      { name:'Pro plan',     sub:'Yearly billing · save 20%', price: 14400 },
        'topup-1000': { name:'Wallet top-up', sub:'₹1,000 credit', price: 1000 },
      };
      function qs(k){ const m = location.search.match(new RegExp('[?&]'+k+'=([^&]*)')); return m ? decodeURIComponent(m[1]) : ''; }
      const plan = PLANS[(qs('plan') || qs('item') || 'pro').toLowerCase()] || PLANS.pro;

      let subtotal = plan.price;
      let coupon = null;
      let couponPct = 0;

      function fmt(n) { return '₹ ' + Math.round(n).toLocaleString('en-IN'); }
      function recompute() {
        document.getElementById('item-name').textContent = plan.name;
        document.getElementById('item-sub').textContent  = plan.sub;
        document.getElementById('item-price').textContent = fmt(subtotal);
        document.getElementById('sub-amt').textContent    = fmt(subtotal);
        const discount = Math.round(subtotal * couponPct / 100);
        const taxBase  = subtotal - discount;
        const tax      = Math.round(taxBase * 0.18);
        const total    = taxBase + tax;
        const dRow = document.getElementById('discount-row');
        if (discount > 0) { dRow.style.display = 'flex'; document.getElementById('discount-amt').textContent = '−' + fmt(discount); document.getElementById('coupon-applied').textContent = coupon; }
        else dRow.style.display = 'none';
        document.getElementById('tax-amt').textContent   = fmt(tax);
        document.getElementById('total-amt').textContent = fmt(total);
        document.getElementById('cta-amt').textContent   = fmt(total);
      }
      recompute();

      /* coupon ---------------------------------------- */
      function applyCoupon() {
        const code = (document.getElementById('coupon').value || '').trim().toUpperCase();
        if (!code) return;
        /* mock: VETRICK20 = 20%, FIRST10 = 10% */
        if (code === 'VETRICK20') { coupon = code; couponPct = 20; }
        else if (code === 'FIRST10') { coupon = code; couponPct = 10; }
        else { alert('Invalid coupon code.'); return; }
        recompute();
      }

      /* payment method tabs --------------------------- */
      document.querySelectorAll('[data-pm]').forEach(b => b.addEventListener('click', () => {
        document.querySelectorAll('[data-pm]').forEach(x => {
          x.classList.remove('border-wa-deep','bg-wa-mint/30','text-wa-deep','font-semibold');
          x.classList.add('border-paper-200','bg-paper-0','hover:bg-paper-50','font-medium');
        });
        b.classList.add('border-wa-deep','bg-wa-mint/30','text-wa-deep','font-semibold');
        b.classList.remove('border-paper-200','bg-paper-0','hover:bg-paper-50','font-medium');
        document.querySelectorAll('[data-pm-panel]').forEach(p => p.classList.toggle('hidden', p.dataset.pmPanel !== b.dataset.pm));
      }));

      /* pay (mock) ------------------------------------ */
      function pay() {
        /* 1 in 8 chance of failure for demo */
        const fail = Math.random() < 0.125;
        location = fail ? '/checkout/failed?reason=card_declined' : '/checkout/success?plan=' + (qs('plan') || qs('item') || 'pro');
      }
}
