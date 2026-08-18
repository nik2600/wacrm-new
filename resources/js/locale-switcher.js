// Language switcher dropdown — formerly inline @push('scripts') inside
// resources/views/components/locale-switcher.blade.php.
//
// Pulled out into a standalone bundle module because the inline
// `<script>` block was being pushed into the page's `scripts` stack on
// every render of the component. On the admin layout `@stack('scripts')`
// renders BEFORE the header-right source div, so each push was held in
// memory and never flushed — combined with the anonymous-component
// boot/restore cycle that runs on every request, this contributed to
// runaway memory growth during render of the header-right component
// (the OOM consistently fired right after the locale-switcher's
// anonymous-component cleanup at compiled line 84 of header-right).
//
// Now: one cached script in the bundled app.js, scanned for switcher
// instances after DOMContentLoaded.

function wireLocaleSwitcher(root) {
    if (root.dataset.localeBound === '1') return;
    root.dataset.localeBound = '1';

    const toggle = root.querySelector('[data-locale-toggle]');
    const menu   = root.querySelector('[data-locale-menu]');
    if (!toggle || !menu) return;

    toggle.addEventListener('click', (e) => {
        e.stopPropagation();
        const willOpen = menu.classList.contains('hidden');
        menu.classList.toggle('hidden');
        if (willOpen) {
            // Viewport-edge clamp. Default Tailwind `right-0` anchors the
            // menu's right edge to the toggle's right edge. On narrow
            // screens (or RTL layouts) the 224px-wide menu can overflow
            // the left viewport edge; flip to `left-0` if it would clip.
            const rect = menu.getBoundingClientRect();
            if (rect.left < 8) {
                menu.style.right = 'auto';
                menu.style.left  = '0';
            } else if (rect.right > window.innerWidth - 8) {
                menu.style.left  = 'auto';
                menu.style.right = '0';
            }
        }
    });

    document.addEventListener('click', (e) => {
        if (!root.contains(e.target)) menu.classList.add('hidden');
    });

    root.querySelectorAll('[data-locale-pick]').forEach((btn) => {
        btn.addEventListener('click', async () => {
            const code = btn.dataset.localePick;
            try {
                const r = await fetch('/locale', {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content || '',
                    },
                    body: JSON.stringify({ code }),
                });
                if (!r.ok) throw new Error('Switch failed');
                window.location.reload();
            } catch (err) {
                console.warn('[locale]', err);
            }
        });
    });
}

function boot() {
    document.querySelectorAll('[data-locale-switcher]').forEach(wireLocaleSwitcher);
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot, { once: true });
} else {
    boot();
}
