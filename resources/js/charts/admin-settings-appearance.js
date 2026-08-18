/**
 * /admin/settings/appearance — live preview.
 *
 * Mirrors the form state onto #appearance-preview without touching the page
 * around it. The trick is that the preview box carries its own `--color-*`
 * custom properties: Tailwind token utilities compile to
 * `background-color: var(--color-wa-deep)`, and custom properties inherit, so
 * every `bg-wa-deep` INSIDE the preview resolves against the preview's value
 * while the surrounding admin UI keeps using :root. No class rewriting, no
 * duplicate stylesheet, and the mock stays a plain piece of markup.
 *
 * Size and opacity are applied to the preview as zoom/opacity — the same two
 * properties theme_css() writes globally on save — so what the admin sees here
 * is what the dashboard becomes.
 */
export default function initAppearancePreview() {
    const preview = document.getElementById('appearance-preview');
    if (!preview) return;

    const colorInputs = Array.from(document.querySelectorAll('input[type="color"][name^="colors["]'));
    const sliders     = Array.from(document.querySelectorAll('input[type="range"][data-metric]'));

    /** colors[wa-deep] -> wa-deep */
    const tokenOf = (input) => {
        const m = /^colors\[(.+)\]$/.exec(input.getAttribute('name') || '');
        return m ? m[1] : null;
    };

    function applyColors() {
        colorInputs.forEach((input) => {
            const token = tokenOf(input);
            if (token) preview.style.setProperty('--color-' + token, input.value);
        });
    }

    function applyMetrics() {
        sliders.forEach((slider) => {
            const key = slider.dataset.metric;
            const pct = parseInt(slider.value, 10);
            if (Number.isNaN(pct)) return;

            const out = document.querySelector(`[data-metric-out="${key}"]`);
            if (out) out.textContent = pct;

            // The preview is ~1/4 the width of a real dashboard, so applying the
            // raw zoom would overflow the aside. Damp it to half the deviation
            // from 100% — enough to read the difference, not enough to break the
            // panel. The saved value is still the exact number on the slider.
            if (key === 'font-scale') {
                preview.style.zoom = (100 + (pct - 100) / 2) / 100;
            } else if (key === 'ui-opacity') {
                preview.style.opacity = pct / 100;
            }
        });
    }

    function syncLabel(input) {
        // The swatch caption reads "token · #hex" — keep the hex honest as it changes.
        const token = tokenOf(input);
        if (!token) return;
        const caption = input.parentElement?.querySelector('.font-mono');
        if (caption) caption.textContent = `${token} · ${input.value}`;
    }

    colorInputs.forEach((input) => {
        input.addEventListener('input', () => {
            applyColors();
            syncLabel(input);
        });
    });

    sliders.forEach((slider) => {
        slider.addEventListener('input', applyMetrics);
    });

    // -/+ steppers next to each slider.
    document.querySelectorAll('[data-metric-step]').forEach((btn) => {
        btn.addEventListener('click', () => {
            const slider = document.querySelector(`input[type="range"][data-metric="${btn.dataset.metricStep}"]`);
            if (!slider) return;

            const step = parseInt(btn.dataset.step, 10) || 0;
            const min  = parseInt(slider.min, 10);
            const max  = parseInt(slider.max, 10);
            slider.value = Math.max(min, Math.min(max, parseInt(slider.value, 10) + step));

            applyMetrics();
        });
    });

    applyColors();
    applyMetrics();
}
