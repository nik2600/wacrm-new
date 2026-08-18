/**
 * /admin/payment-gateways page initialiser.
 *
 * Two responsibilities:
 *   1. Tab + search filter — each card carries data-cat="all india …"
 *      and data-search="razorpay razorpay"; the tab bar + search input
 *      filter conjunctively.
 *   2. Collapsible cards — every gateway card is collapsed by default
 *      (only the header row is visible). Clicking the header expands /
 *      collapses the form panel. State is NOT persisted — page refresh
 *      starts everyone collapsed, matching SnapNest's pattern.
 */
export default function init() {
    // ── filter ──────────────────────────────────────────────────────
    let activeCat = 'all';
    let activeSearch = '';
    const tabs   = document.querySelectorAll('[data-gateway-cat]');
    const cards  = document.querySelectorAll('[data-gateway-card]');
    const search = document.getElementById('gateway-search');
    const empty  = document.getElementById('gw-empty-state');

    function applyFilter() {
        let shown = 0;
        cards.forEach((c) => {
            const cats = (c.getAttribute('data-cat') || '').split(/\s+/);
            const matchCat    = activeCat === 'all' || cats.includes(activeCat);
            const matchSearch = !activeSearch || (c.getAttribute('data-search') || '').includes(activeSearch);
            const visible = matchCat && matchSearch;
            c.style.display = visible ? '' : 'none';
            if (visible) shown++;
        });
        if (empty) empty.classList.toggle('hidden', shown > 0);
    }

    tabs.forEach((t) => {
        t.addEventListener('click', () => {
            tabs.forEach((x) => x.setAttribute('data-active', 'false'));
            t.setAttribute('data-active', 'true');
            activeCat = t.getAttribute('data-gateway-cat');
            applyFilter();
        });
    });
    if (search) {
        search.addEventListener('input', (e) => {
            activeSearch = (e.target.value || '').toLowerCase();
            applyFilter();
        });
    }

    // ── collapsible cards ───────────────────────────────────────────
    cards.forEach((card) => {
        const head = card.querySelector('[data-gateway-head]');
        const body = card.querySelector('[data-gateway-body]');
        const chev = card.querySelector('[data-gateway-chev]');
        if (!head || !body) return;
        head.addEventListener('click', (e) => {
            // Don't collapse when the user clicks the Activate/Disable
            // form button — let the form submit normally.
            if (e.target.closest('form, button, a, input')) return;
            const open = !body.classList.contains('hidden');
            body.classList.toggle('hidden', open);
            if (chev) chev.style.transform = open ? '' : 'rotate(180deg)';
            card.setAttribute('data-open', open ? 'false' : 'true');
        });
    });

    // ── currency-pill picker ────────────────────────────────────────
    // Replaces the old <select multiple> with clickable badges. Each
    // pill has a hidden checkbox inside its <label>, so toggling the
    // pill flips the checkbox and the form submits the supported_
    // currencies[] array with zero JS-supplied data.
    document.querySelectorAll('[data-currency-pills]').forEach((grid) => {
        const pills = grid.querySelectorAll('[data-currency-pill]');
        const count = grid.parentElement?.querySelector('[data-currency-count]');
        const updateCount = () => {
            if (!count) return;
            count.textContent = String(
                grid.querySelectorAll('input[type="checkbox"]:checked').length
            );
        };
        pills.forEach((pill) => {
            const cb = pill.querySelector('input[type="checkbox"]');
            if (!cb) return;
            pill.addEventListener('click', (e) => {
                // The native <label> already toggles the checkbox on
                // click — we just sync the visual data-selected attr.
                // Use setTimeout so we read the checkbox AFTER the
                // browser flips it.
                setTimeout(() => {
                    pill.setAttribute('data-selected', cb.checked ? 'true' : 'false');
                    updateCount();
                }, 0);
            });
        });
        updateCount();
    });
}
