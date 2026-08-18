export default function init() {
    const monthlyBtn = document.getElementById('bill-monthly');
    const yearlyBtn = document.getElementById('bill-yearly');

    // The switcher only exists when the admin enabled the yearly toggle. Without
    // this guard every page render threw on the first addEventListener below,
    // which killed the rest of this module.
    if (!monthlyBtn || !yearlyBtn) return;

    function setBilling(period) {
        document.querySelectorAll('.price').forEach((p) => {
            p.textContent = p.dataset[period] || p.textContent;
        });

        // Repoint the buy links at the period actually being shown. Previously
        // only the displayed PRICE changed while the href kept whatever period
        // it was rendered with, so choosing "Monthly" still sent the buyer to
        // yearly checkout — the card and the amount charged disagreed.
        document.querySelectorAll('[data-checkout-base]').forEach((a) => {
            const base = a.dataset.checkoutBase;
            if (!base) return;
            a.setAttribute('href', period === 'yearly' ? base + '?period=yearly' : base);
        });

        monthlyBtn.classList.toggle('bg-wa-deep', period === 'monthly');
        monthlyBtn.classList.toggle('text-paper-0', period === 'monthly');
        monthlyBtn.classList.toggle('text-ink-600', period !== 'monthly');
        yearlyBtn.classList.toggle('bg-wa-deep', period === 'yearly');
        yearlyBtn.classList.toggle('text-paper-0', period === 'yearly');
        yearlyBtn.classList.toggle('text-ink-600', period !== 'yearly');
    }

    monthlyBtn.addEventListener('click', () => setBilling('monthly'));
    yearlyBtn.addEventListener('click', () => setBilling('yearly'));
}
