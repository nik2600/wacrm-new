import TomSelect from 'tom-select';
import 'tom-select/dist/css/tom-select.css';

/*
 * Register / step 2 — workspace creation.
 *
 * Hydrates the timezone <select> with every IANA timezone the
 * browser knows about (via Intl.supportedValuesOf), then upgrades
 * it to a Tom Select searchable combobox so the user can type
 * "kolkata", "london", "berlin", etc. instead of scrolling.
 */
export default function init() {
    const sel = document.getElementById('ws-timezone');
    if (!sel) return;

    let zones = [];
    try {
        if (typeof Intl !== 'undefined' && typeof Intl.supportedValuesOf === 'function') {
            zones = Intl.supportedValuesOf('timeZone') || [];
        }
    } catch (_) { /* ignore */ }

    if (!zones.length) {
        // Older browsers — fall back to a hand-curated short list.
        zones = [
            'UTC',
            'Asia/Kolkata', 'Asia/Dubai', 'Asia/Singapore', 'Asia/Tokyo', 'Asia/Shanghai',
            'Europe/London', 'Europe/Berlin', 'Europe/Paris', 'Europe/Madrid', 'Europe/Moscow',
            'America/New_York', 'America/Chicago', 'America/Denver', 'America/Los_Angeles',
            'America/Sao_Paulo', 'America/Toronto', 'America/Mexico_City',
            'Africa/Cairo', 'Africa/Johannesburg', 'Australia/Sydney', 'Pacific/Auckland',
        ];
    }

    // Pre-seed the existing option so it doesn't get wiped.
    const preselected = sel.value || sel.querySelector('option[selected]')?.value || 'Asia/Kolkata';
    sel.innerHTML = '';
    for (const tz of zones) {
        const opt = document.createElement('option');
        opt.value = tz;
        opt.textContent = tz;
        if (tz === preselected) opt.selected = true;
        sel.appendChild(opt);
    }
    if (!zones.includes(preselected)) {
        const opt = document.createElement('option');
        opt.value = preselected;
        opt.textContent = preselected;
        opt.selected = true;
        sel.prepend(opt);
    }

    new TomSelect(sel, {
        maxOptions: 1000,
        searchField: ['text'],
        sortField: { field: 'text', direction: 'asc' },
        placeholder: 'Search timezone...',
    });
}
