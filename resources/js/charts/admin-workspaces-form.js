import { Country } from 'country-state-city';
import TomSelect from 'tom-select';
import 'tom-select/dist/css/tom-select.css';
import { wireTimezonePicker } from '../lib/tz-picker.js';

export default function init() {
    setupCountry();
    setupTimezone();
    setupOwnerMode();
    setupDnsModal();
}

function setupCountry() {
    const sel = document.getElementById('country');
    if (!sel) return;
    const initial = sel.dataset.value || '';

    if (initial) {
        const o = document.createElement('option');
        o.value = initial; o.textContent = initial; o.selected = true;
        sel.appendChild(o);
    }
    const options = Country.getAllCountries()
        .sort((a, b) => a.name.localeCompare(b.name))
        .map((c) => ({ value: c.isoCode, text: `${c.flag ? c.flag + '  ' : ''}${c.name}` }));

    new TomSelect(sel, {
        options,
        maxOptions: null,
        placeholder: 'Search country…',
        plugins: ['clear_button'],
        allowEmptyOption: true,
    });
}

function setupTimezone() {
    // Same Tom Select pattern as user-account profile.
    wireTimezonePicker('#timezone');
}

function setupOwnerMode() {
    const existing = document.querySelector('[data-mode-existing]');
    const invite   = document.querySelector('[data-mode-invite]');
    const paneE    = document.querySelector('[data-owner-existing]');
    const paneI    = document.querySelector('[data-owner-invite]');
    if (!existing || !invite || !paneE || !paneI) return;

    const apply = () => {
        const isInvite = invite.checked;
        paneE.classList.toggle('hidden', isInvite);
        paneI.classList.toggle('hidden', !isInvite);
    };
    existing.addEventListener('change', apply);
    invite.addEventListener('change', apply);
    apply();
}

function setupDnsModal() {
    const btn   = document.getElementById('dns-help-btn');
    const modal = document.getElementById('dns-help-modal');
    if (!btn || !modal) return;
    const close = () => modal.classList.add('hidden');
    btn.addEventListener('click', (e) => { e.preventDefault(); modal.classList.remove('hidden'); });
    modal.querySelectorAll('[data-dns-close]').forEach((b) => b.addEventListener('click', close));
    modal.addEventListener('click', (e) => { if (e.target === modal) close(); });
    document.addEventListener('keydown', (e) => { if (e.key === 'Escape') close(); });
}
