// Admin → Users → Bulk import. Small progressive-enhancement layer: show the
// chosen filename (the input is visually hidden), support drag-and-drop onto
// the zone, and a light client-side guard on extension/size before the POST.
// The form works WITHOUT this JS — it just gives feedback while picking a file.

export default function init() {
    const input = document.getElementById('import-file');
    const zone  = document.getElementById('import-dropzone');
    const title = document.getElementById('import-dz-title');
    const sub   = document.getElementById('import-dz-sub');
    if (!input || !zone) return;

    const DEFAULT_TITLE = title ? title.textContent : '';
    const DEFAULT_SUB   = sub ? sub.innerHTML : '';

    const human = (bytes) => {
        if (!bytes && bytes !== 0) return '';
        const kb = bytes / 1024;
        return kb < 1024 ? `${kb.toFixed(0)} KB` : `${(kb / 1024).toFixed(1)} MB`;
    };

    const show = (file) => {
        if (!file) {
            if (title) title.textContent = DEFAULT_TITLE;
            if (sub) sub.innerHTML = DEFAULT_SUB;
            return;
        }
        const okExt  = /\.csv$/i.test(file.name);
        const okSize = file.size <= 10 * 1024 * 1024;
        if (title) title.textContent = file.name;
        if (sub) {
            sub.textContent = !okExt
                ? 'That is not a .csv file — pick a CSV.'
                : !okSize
                    ? `Too large (${human(file.size)}) — max 10 MB.`
                    : `Ready · ${human(file.size)}`;
            sub.classList.toggle('text-accent-coral', !okExt || !okSize);
        }
    };

    input.addEventListener('change', () => show(input.files && input.files[0]));

    // Drag-and-drop → assign to the real input so it submits with the form.
    ['dragenter', 'dragover'].forEach((ev) => zone.addEventListener(ev, (e) => {
        e.preventDefault();
        zone.classList.add('border-wa-deep', 'bg-wa-bubble/30');
    }));
    ['dragleave', 'drop'].forEach((ev) => zone.addEventListener(ev, (e) => {
        e.preventDefault();
        zone.classList.remove('border-wa-deep', 'bg-wa-bubble/30');
    }));
    zone.addEventListener('drop', (e) => {
        const file = e.dataTransfer?.files?.[0];
        if (!file) return;
        try {
            const dt = new DataTransfer();
            dt.items.add(file);
            input.files = dt.files;
        } catch (_) { /* older browsers: user can still click to browse */ }
        show(file);
    });
}
