/**
 * /admin/settings/seo — live preview + character counters.
 *
 * Each input carries data-seo-input="<field>" and each preview node
 * carries data-seo-preview="<field>". On every input, the matching
 * preview node's text content is mirrored. Counters are wired through
 * data-seo-counter="<field>" with an optional data-target="N-M" range
 * that colours the counter amber when out of range.
 */
export default function init() {
    // Mirror input → preview text.
    const inputs = document.querySelectorAll('[data-seo-input]');
    inputs.forEach((el) => {
        const key  = el.dataset.seoInput;
        const sync = () => {
            const value = (el.value || '').trim();

            document.querySelectorAll(`[data-seo-preview="${key}"]`).forEach((p) => {
                p.textContent = value || p.dataset.seoEmpty || '';
            });

            // OG image: paint the preview card's background.
            if (key === 'og_image') {
                const slot = document.querySelector('[data-seo-preview-img="og_image"]');
                if (slot) {
                    if (value) {
                        slot.style.backgroundImage    = `url('${value}')`;
                        slot.style.backgroundSize     = 'cover';
                        slot.style.backgroundPosition = 'center';
                        slot.textContent = '';
                    } else {
                        slot.style.backgroundImage = '';
                        slot.textContent = 'No OG image set';
                    }
                }
            }

            // Counter (if any).
            const counter = document.querySelector(`[data-seo-counter="${key}"]`);
            if (counter) {
                const len = value.length;
                counter.textContent = String(len);
                const target = counter.dataset.target;
                if (target) {
                    const [lo, hi] = target.split('-').map(Number);
                    const inRange = len >= lo && len <= hi;
                    counter.classList.toggle('text-ink-500',     inRange);
                    counter.classList.toggle('text-accent-amber',!inRange && len > 0);
                    counter.classList.toggle('font-semibold',    !inRange && len > 0);
                }
            }
        };
        el.addEventListener('input', sync);
        sync();
    });
}
