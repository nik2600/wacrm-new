export default function init() {
    // Three-dot row menu — panel floats with position:fixed anchored to its button,
    // so it escapes the table wrapper's overflow-x-auto clipping (it used to render
    // trapped inside / under the table). Closes on outside-click, Esc, scroll, resize.
    const placeRowPanel = (btn, panel) => {
        const r = btn.getBoundingClientRect();
        const w = panel.offsetWidth || 210;
        let left = Math.round(r.right - w);
        if (left < 8) left = 8;
        let top = Math.round(r.bottom + 4);
        const h = panel.offsetHeight || 0;
        if (top + h > window.innerHeight - 8 && r.top - h - 4 > 8) top = Math.round(r.top - h - 4);
        panel.style.position = 'fixed';
        panel.style.top = top + 'px';
        panel.style.left = left + 'px';
        panel.style.right = 'auto';
        panel.style.zIndex = '9999';
    };
    const closeRowPanels = () => {
        document.querySelectorAll('[data-row-menu-panel]').forEach((p) => {
            p.classList.add('hidden');
            p.style.position = p.style.top = p.style.left = p.style.right = p.style.zIndex = '';
        });
    };
    document.querySelectorAll('[data-row-menu-toggle]').forEach((btn) => {
        btn.addEventListener('click', (e) => {
            e.stopPropagation();
            const panel = btn.closest('[data-row-menu]')?.querySelector('[data-row-menu-panel]');
            if (!panel) return;
            const willOpen = panel.classList.contains('hidden');
            closeRowPanels();
            if (willOpen) { panel.classList.remove('hidden'); placeRowPanel(btn, panel); }
        });
    });
    document.addEventListener('click', closeRowPanels);
    document.addEventListener('keydown', (e) => { if (e.key === 'Escape') closeRowPanels(); });
    window.addEventListener('scroll', closeRowPanels, true);
    window.addEventListener('resize', closeRowPanels);
}
