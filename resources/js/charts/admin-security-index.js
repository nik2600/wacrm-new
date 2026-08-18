/**
 * /admin/security — tab switching.
 *
 * The page ships every panel except "summary" with the `hidden` class, and
 * every policy control (49 inputs) lives inside those hidden panels. Without
 * this initialiser the tab buttons are inert, so no toggle is ever reachable
 * and saving appears to do nothing.
 *
 * Note: panels are hidden by CLASS, not inline style. Clearing `style.display`
 * (what the analytics page does) would leave `.hidden` in force — we toggle the
 * class instead.
 */
export default function initAdminSecurityIndex() {
    const scope = document.querySelector('[data-wa-tab-scope]') || document;
    const buttons = scope.querySelectorAll('[data-wa-tabs] [data-wa-tab]');
    const panels = scope.querySelectorAll('[data-wa-tab-panel]');
    if (!buttons.length || !panels.length) return;

    function showTab(key) {
        buttons.forEach((btn) => {
            const on = btn.dataset.waTab === key;
            btn.classList.toggle('bg-wa-deep', on);
            btn.classList.toggle('text-paper-0', on);
            btn.classList.toggle('text-ink-600', !on);
            btn.classList.toggle('hover:bg-paper-50', !on);
        });
        panels.forEach((panel) => {
            const keys = (panel.getAttribute('data-wa-tab-panel') || '').split(/\s+/);
            panel.classList.toggle('hidden', !keys.includes(key));
        });
    }

    // Remember the open tab across a save. Saving POSTs and then 302-redirects,
    // and a URL fragment is never sent to the server — so the hash alone cannot
    // survive the round trip. sessionStorage does, and is per-tab so two admin
    // windows don't fight over it.
    const STORE_KEY = 'wa:admin-security:tab';
    const keys = Array.from(buttons).map((b) => b.dataset.waTab);

    const remember = (key) => {
        try { sessionStorage.setItem(STORE_KEY, key); } catch (_) { /* private mode */ }
        // replaceState, not location.hash — assigning the hash jumps the page.
        try { history.replaceState(null, '', '#' + key); } catch (_) { /* file:// */ }
    };

    buttons.forEach((btn) => {
        btn.addEventListener('click', () => {
            showTab(btn.dataset.waTab);
            remember(btn.dataset.waTab);
        });
    });

    // Restore priority: URL hash (shareable link) → last tab used → the tab the
    // server already marked active → first tab.
    const fromHash = (location.hash || '').replace(/^#/, '');
    let stored = null;
    try { stored = sessionStorage.getItem(STORE_KEY); } catch (_) { /* private mode */ }
    const active = Array.from(buttons).find((b) => b.classList.contains('bg-wa-deep'));

    const initial = [fromHash, stored, active && active.dataset.waTab, keys[0]]
        .find((k) => k && keys.includes(k));

    showTab(initial);
}
