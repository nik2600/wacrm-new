/**
 * Admin sidebar — wires up the expandable nav-group toggles and the
 * full-rail collapse toggle. The sidebar markup itself is rendered
 * server-side from resources/views/components/admin/sidebar.blade.php;
 * collapse styling lives in resources/css/wadesk.css.
 */
export default function initAdminSidebar() {
    document.querySelectorAll('[data-admin-toggle]').forEach((btn) => {
        btn.addEventListener('click', (event) => {
            event.preventDefault();
            const group = btn.closest('.admin-nav-group');
            if (!group) return;
            const panel = group.querySelector('.admin-nav-children');
            const chev = btn.querySelector('[data-admin-chevron]');
            if (panel) panel.classList.toggle('hidden');
            if (chev) chev.classList.toggle('rotate-180');
        });
    });

    initSidebarCollapse();
}

/**
 * Rail collapse. State is persisted in the `wa_admin_sidebar` cookie
 * (not localStorage) so the layout can render the collapsed class onto
 * <html> server-side — no inline bootstrap script, no flash on reload.
 */
function initSidebarCollapse() {
    const btn = document.getElementById('admin-sidebar-toggle');
    if (!btn || btn.dataset.wired === '1') return;
    btn.dataset.wired = '1';

    btn.addEventListener('click', () => {
        // On mobile (< md) the sidebar is an off-canvas drawer opened by the
        // header hamburger — collapsing it to an icon-rail makes no sense there.
        // So on mobile this button CLOSES the drawer (slide off + hide backdrop),
        // matching the backdrop-tap behaviour. The rail-collapse is desktop-only.
        if (window.matchMedia('(max-width: 767px)').matches) {
            const sidebar = document.getElementById('admin-sidebar');
            const backdrop = document.getElementById('admin-sidebar-backdrop');
            if (sidebar) sidebar.classList.add('-translate-x-full');
            if (backdrop) {
                backdrop.classList.add('opacity-0');
                setTimeout(() => backdrop.classList.add('hidden'), 300);
            }
            document.body.style.overflow = '';
            return;
        }

        const collapsed = document.documentElement.classList.toggle('admin-sidebar-collapsed');
        // 1 year, root path so every admin page sees it.
        document.cookie = `wa_admin_sidebar=${collapsed ? 'collapsed' : 'expanded'}; path=/; max-age=31536000; SameSite=Lax`;
        const label = collapsed ? 'Expand sidebar' : 'Collapse sidebar';
        btn.setAttribute('aria-label', label);
        btn.setAttribute('title', label);
    });
}
