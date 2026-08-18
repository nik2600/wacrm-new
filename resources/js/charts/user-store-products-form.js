/**
 * Shared product form wiring for both /store/products/create and
 * /store/products/{id}/edit. Both blades render the same
 * `_form.blade.php` partial so the JS is identical — we just import
 * it twice (one entry-point per page) to keep the chart router
 * consistent.
 *
 * Responsibilities:
 *   - Image drop-zone: drag/drop + click-to-pick + filename label
 *   - Live storefront preview on the right rail (image, name, price)
 *   - Profit indicator: shows discount % when compare-at > price
 *   - Image URL field overrides the local preview when filled
 */
export default function init() {
    const form = document.querySelector('[data-product-form]');
    if (!form) return;

    wireDropzone(form);
    wireLivePreview(form);
    wireProfitIndicator(form);
    wireUrlOverride(form);
    wireGallery(form);
}

// Gallery editor — clones a hidden <template> on Add, removes rows
// on the trash button. Posts as gallery_urls[] (controller maps to
// gallery_json on the model).
function wireGallery(form) {
    const list = form.querySelector('[data-gallery-list]');
    const tpl  = form.querySelector('[data-gallery-template]');
    const addBtn = form.querySelector('[data-gallery-add]');
    if (!list || !tpl || !addBtn) return;

    const hideEmpty = () => {
        const empty = list.querySelector('[data-gallery-empty]');
        if (empty) empty.style.display = list.querySelector('[data-gallery-row]') ? 'none' : '';
    };

    function bindRow(row) {
        const input = row.querySelector('input[type="url"]');
        const thumb = row.querySelector('.w-12');
        const removeBtn = row.querySelector('[data-gallery-remove]');
        if (input && thumb) {
            input.addEventListener('input', () => {
                const v = input.value.trim();
                if (v) {
                    thumb.innerHTML = `<img src="${v}" class="w-full h-full object-cover" onerror="this.outerHTML='<span class=&quot;text-ink-400 text-[10px]&quot;>404</span>'">`;
                } else {
                    thumb.innerHTML = '<span class="text-ink-400 text-[10px]">img</span>';
                }
            });
        }
        if (removeBtn) {
            removeBtn.addEventListener('click', () => {
                row.remove();
                hideEmpty();
            });
        }
    }

    // Bind any rows already rendered server-side.
    list.querySelectorAll('[data-gallery-row]').forEach(bindRow);

    addBtn.addEventListener('click', () => {
        const clone = tpl.content.firstElementChild.cloneNode(true);
        list.appendChild(clone);
        bindRow(clone);
        clone.querySelector('input[type="url"]')?.focus();
        hideEmpty();
    });

    hideEmpty();
}

function wireDropzone(form) {
    const zone = form.querySelector('[data-product-dropzone]');
    const input = form.querySelector('[data-image-input]');
    const frame = form.querySelector('[data-image-frame]');
    const filename = form.querySelector('[data-image-filename]');
    if (!zone || !input || !frame) return;

    function setPreview(file) {
        if (!file) return;
        const reader = new FileReader();
        reader.onload = (e) => {
            frame.innerHTML = `<img data-image-preview src="${e.target.result}" class="w-full h-full object-cover" />`;
            // Also update the right-rail live preview.
            const livePreview = form.querySelector('[data-preview-image]');
            if (livePreview) {
                livePreview.innerHTML = `<img src="${e.target.result}" class="w-full h-full object-cover" />`;
            }
        };
        reader.readAsDataURL(file);
        if (filename) filename.textContent = file.name;
    }

    input.addEventListener('change', () => setPreview(input.files[0]));

    zone.addEventListener('dragover', (e) => {
        e.preventDefault();
        zone.classList.add('border-wa-deep', 'bg-wa-mint/20');
    });
    zone.addEventListener('dragleave', () => {
        zone.classList.remove('border-wa-deep', 'bg-wa-mint/20');
    });
    zone.addEventListener('drop', (e) => {
        e.preventDefault();
        zone.classList.remove('border-wa-deep', 'bg-wa-mint/20');
        const file = e.dataTransfer.files[0];
        if (file) {
            input.files = e.dataTransfer.files;
            setPreview(file);
        }
    });
}

function wireLivePreview(form) {
    const nameInput = form.querySelector('input[name="name"]');
    const priceInput = form.querySelector('input[name="price_major"]');
    const compareInput = form.querySelector('input[name="compare_price_major"]');
    const currencySelect = form.querySelector('select[name="currency_code"]');

    const previewName = form.querySelector('[data-preview-name]');
    const previewPrice = form.querySelector('[data-preview-price]');
    const previewCompare = form.querySelector('[data-preview-compare]');

    function currencySymbol() {
        const c = currencySelect?.value || 'INR';
        return { INR: '₹', USD: '$', EUR: '€', GBP: '£' }[c] || c;
    }
    function formatPrice(v) {
        const n = parseFloat(v);
        if (!Number.isFinite(n)) return null;
        const display = n === Math.trunc(n) ? n.toFixed(0) : n.toFixed(2);
        return `${currencySymbol()} ${display}`;
    }
    function render() {
        if (previewName) previewName.textContent = nameInput?.value?.trim() || 'Product name';
        if (previewPrice) previewPrice.textContent = formatPrice(priceInput?.value) || `${currencySymbol()} 0`;

        if (previewCompare) {
            const cmp = formatPrice(compareInput?.value);
            const price = parseFloat(priceInput?.value || '0');
            const compare = parseFloat(compareInput?.value || '0');
            if (cmp && compare > price) {
                previewCompare.textContent = cmp;
                previewCompare.classList.remove('hidden');
            } else {
                previewCompare.classList.add('hidden');
            }
        }
    }
    [nameInput, priceInput, compareInput, currencySelect].forEach((el) => {
        if (el) el.addEventListener('input', render);
        if (el && el.tagName === 'SELECT') el.addEventListener('change', render);
    });
    render();
}

function wireProfitIndicator(form) {
    const priceInput = form.querySelector('input[name="price_major"]');
    const compareInput = form.querySelector('input[name="compare_price_major"]');
    const output = form.querySelector('[data-profit-output]');
    if (!priceInput || !compareInput || !output) return;

    function recalc() {
        const price = parseFloat(priceInput.value);
        const compare = parseFloat(compareInput.value);
        if (!Number.isFinite(price) || !Number.isFinite(compare) || compare <= 0) {
            output.textContent = '—';
            output.classList.remove('text-wa-deep', 'text-accent-coral');
            output.classList.add('text-ink-700');
            return;
        }
        if (compare > price) {
            const pct = Math.round(((compare - price) / compare) * 100);
            output.textContent = `-${pct}%  off`;
            output.classList.remove('text-ink-700', 'text-accent-coral');
            output.classList.add('text-wa-deep');
        } else if (compare < price) {
            output.textContent = 'Compare < price';
            output.classList.remove('text-ink-700', 'text-wa-deep');
            output.classList.add('text-accent-coral');
        } else {
            output.textContent = '0% — same';
            output.classList.remove('text-wa-deep', 'text-accent-coral');
            output.classList.add('text-ink-700');
        }
    }
    priceInput.addEventListener('input', recalc);
    compareInput.addEventListener('input', recalc);
    recalc();
}

function wireUrlOverride(form) {
    const urlInput = form.querySelector('input[name="image_url"]');
    const livePreview = form.querySelector('[data-preview-image]');
    if (!urlInput || !livePreview) return;

    urlInput.addEventListener('change', () => {
        const v = urlInput.value.trim();
        if (!v) return;
        livePreview.innerHTML = `<img src="${v}" class="w-full h-full object-cover" onerror="this.parentElement.innerHTML='<span class=&quot;text-ink-400 text-[10.5px]&quot;>URL failed to load</span>'" />`;
    });
}
