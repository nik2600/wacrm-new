/**
 * tz-picker.js — shared timezone <select> upgrade.
 *
 * Mirrors the user-account profile picker exactly so admin and user-side
 * timezone fields look and behave identically.
 *
 *   wireTimezonePicker('#an-tz')                            // default Asia/Kolkata
 *   wireTimezonePicker('#timezone', { defaultTz: 'UTC' })
 *
 * Reads `data-value` attribute or existing `.value` for preselect.
 */
import TomSelect from 'tom-select';
import 'tom-select/dist/css/tom-select.css';

export function wireTimezonePicker(selectorOrEl, opts = {}) {
    const el = typeof selectorOrEl === 'string'
        ? document.querySelector(selectorOrEl)
        : selectorOrEl;
    if (!el) return null;

    const defaultTz = opts.defaultTz || 'Asia/Kolkata';

    let zones = [];
    try {
        if (typeof Intl !== 'undefined' && typeof Intl.supportedValuesOf === 'function') {
            zones = Intl.supportedValuesOf('timeZone') || [];
        }
    } catch (_) { /* ignore */ }
    if (!zones.length) {
        zones = ['UTC', 'Asia/Kolkata', 'Asia/Dubai', 'Asia/Singapore', 'Europe/London', 'Europe/Berlin',
                 'America/New_York', 'America/Los_Angeles', 'Australia/Sydney', 'Pacific/Auckland'];
    }

    const preselected = el.dataset.value || el.value || defaultTz;
    el.innerHTML = '';
    for (const z of zones) {
        const o = document.createElement('option');
        o.value = z; o.textContent = z;
        if (z === preselected) o.selected = true;
        el.appendChild(o);
    }
    if (!zones.includes(preselected)) {
        const o = document.createElement('option');
        o.value = preselected; o.textContent = preselected; o.selected = true;
        el.prepend(o);
    }
    const ts = new TomSelect(el, {
        maxOptions: 1000,
        searchField: ['text'],
        sortField: { field: 'text', direction: 'asc' },
        placeholder: opts.placeholder || 'Search timezone...',
    });
    // Force the pre-selected zone AFTER init. TomSelect can drop the native
    // <option selected> when it re-sorts the list (alphabetical) or when the
    // field is inside a hidden panel at init (e.g. the admin workspace edit
    // panel starts collapsed) — leaving the control on the first zone
    // (Africa/Abidjan) instead of the saved one. Re-applying it silently
    // guarantees the field shows the workspace's real timezone.
    if (preselected) ts.setValue(preselected, true);
    return ts;
}
