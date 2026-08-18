// info-hint.js — click-to-open help popovers for the <x-info-hint> component.
//
// One delegated listener drives every ⓘ button on the page (present + future),
// so nothing needs per-page wiring: drop <x-info-hint text="…"/> anywhere and it
// just works. Click the icon to toggle its bubble; click elsewhere or press Esc
// to close. Kept deliberately tiny and dependency-free.

function closePanels(except) {
    document.querySelectorAll('[data-info-hint-panel]').forEach((p) => {
        if (p !== except) p.classList.add('hidden');
    });
}

document.addEventListener('click', (e) => {
    const toggle = e.target.closest('[data-info-hint-toggle]');
    if (toggle) {
        e.preventDefault();
        e.stopPropagation();
        const panel = toggle.parentElement?.querySelector('[data-info-hint-panel]');
        const willOpen = panel && panel.classList.contains('hidden');
        closePanels(panel);           // never show two bubbles at once
        if (panel) panel.classList.toggle('hidden', !willOpen);
        return;
    }
    // A click that isn't on an open bubble dismisses whatever is open.
    if (!e.target.closest('[data-info-hint-panel]')) closePanels(null);
});

document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') closePanels(null);
});
