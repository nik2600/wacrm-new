/*
 * Admin · Platform devices — row-action menu + status tabs.
 *
 * The three-dot menu opens via an INLINE onclick="toggleDevMenu(...)" in the
 * blade, so the handler MUST be a global (window.*) — a module-scoped function
 * is invisible to inline handlers and the menu silently never opens. The
 * previous build defined none, and additionally grabbed a non-existent
 * #add-device-btn without a null check, so init threw before wiring anything.
 */
export default function init() {
    // Status tabs are cosmetic highlight only; the real filter is the server
    // query string on the tab links.
    document.querySelectorAll('#status-tabs .status-tab').forEach((b) => {
        b.addEventListener('click', () => {
            document.querySelectorAll('#status-tabs .status-tab').forEach((x) => {
                x.classList.remove('bg-wa-deep', 'text-paper-0');
                x.classList.add('text-ink-600', 'hover:bg-paper-100');
            });
            b.classList.add('bg-wa-deep', 'text-paper-0');
            b.classList.remove('text-ink-600', 'hover:bg-paper-100');
        });
    });

    // Close any open row menu when clicking outside it.
    document.addEventListener('click', (e) => {
        if (e.target.closest('[onclick^="toggleDevMenu"]')) return;
        if (e.target.closest('.dev-action-menu')) return; // let the clicked action run
        window.closeAllDevMenus?.();
    });

    // Esc closes every menu.
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') window.closeAllDevMenus?.();
    });

    // A fixed-position menu would float detached from its row once the page or
    // any scroll container moves, so close it on scroll. Capture-phase catches
    // scrolls on inner containers (e.g. the table's overflow wrapper) too.
    window.addEventListener('scroll', () => window.closeAllDevMenus?.(), true);
}

/*
 * Toggle the action menu for one row. Global because the blade calls it inline.
 * Registered at module load (not inside init) so it exists even if the page
 * initializer runs late — the inline handler can fire the instant the row
 * paints.
 */
function closeAllDevMenus() {
    document.querySelectorAll('.dev-action-menu').forEach((m) => {
        m.classList.add('hidden');
        // Undo the fixed-position styles so a re-open recomputes cleanly.
        m.style.position = '';
        m.style.top = '';
        m.style.left = '';
    });
}
window.closeAllDevMenus = closeAllDevMenus;

window.toggleDevMenu = function (event, btn) {
    event.preventDefault();
    event.stopPropagation();
    const menu = btn.parentElement.querySelector('.dev-action-menu');
    if (!menu) return;
    const wasHidden = menu.classList.contains('hidden');
    closeAllDevMenus();
    if (!wasHidden) return;

    // The table sits inside an overflow-x-auto wrapper, which CLIPS an
    // absolutely-positioned dropdown at the table edge (the menu appeared cut
    // off below the last visible row). Switching to fixed positioning anchored
    // to the trigger button lifts it out of every overflow ancestor. Right edge
    // aligns to the button; flips above when there isn't room below.
    menu.classList.remove('hidden');
    const r = btn.getBoundingClientRect();
    const mw = menu.offsetWidth || 200;
    const mh = menu.offsetHeight || 0;
    const gap = 4;
    let top = r.bottom + gap;
    if (top + mh > window.innerHeight - 8) {
        top = Math.max(8, r.top - mh - gap); // flip above
    }
    menu.style.position = 'fixed';
    menu.style.top = `${Math.round(top)}px`;
    menu.style.left = `${Math.round(Math.max(8, r.right - mw))}px`;
};
