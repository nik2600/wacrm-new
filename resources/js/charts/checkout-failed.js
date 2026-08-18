export default function init() {
    /* personalize from query */
      function qs(k){ const m = location.search.match(new RegExp('[?&]'+k+'=([^&]*)')); return m ? decodeURIComponent(m[1]) : ''; }
      const REASONS = {
        card_declined: { title:'Card declined by issuing bank', desc:"Your bank turned down the transaction. This is usually because of insufficient funds, an international-payments block, or a hold on your card." },
        insufficient_funds: { title:'Insufficient funds', desc:'There weren\'t enough funds on the card to complete this charge. Try a different card or top up the account.' },
        expired_card: { title:'Card expired', desc:'The expiry date on this card has passed. Use a different card or update its details.' },
        network_error: { title:'Network problem', desc:"We couldn't reach the payment gateway. This is usually temporary — please try again in a few seconds." },
        cancelled: { title:'Payment cancelled', desc:"Looks like you cancelled the payment in the bank's confirmation page. No charge was made." },
      };
      const r = REASONS[(qs('reason') || 'card_declined')] || REASONS.card_declined;
      document.getElementById('err-title').textContent = r.title;
      document.getElementById('err-desc').textContent  = r.desc;

      document.getElementById('attempt-at').textContent = new Date().toLocaleDateString('en-IN', { month:'short', day:'numeric', year:'numeric' }) + ' · ' + new Date().toLocaleTimeString([], { hour:'2-digit', minute:'2-digit' }) + ' IST';
      document.getElementById('ref-id').textContent     = 'PAY_' + Math.random().toString(36).slice(2, 10);
}
