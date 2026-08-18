import intlTelInput from 'intl-tel-input/intlTelInputWithUtils';
import 'intl-tel-input/styles';
import TomSelect from 'tom-select';
import 'tom-select/dist/css/tom-select.css';

export default function init() {
    const TAB_TITLES = {
        profile:   ['Profile <span class="italic text-wa-deep">settings</span>', 'Update your photo, name, and contact details.'],
        plan:      ['Plan &amp; <span class="italic text-wa-deep">usage</span>',  'Your current plan, what it includes, and how much of this month you have used.'],
        orders:    ['Order <span class="italic text-wa-deep">history</span>',    'Every payment, plan change, and add-on you\'ve bought.'],
        wallet:    ['Wallet',                                                    'Top up, configure auto top-up, and review wallet activity.'],
        addons:    ['Add<span class="italic text-wa-deep">-ons</span>',          'Extra feature packs you can buy on top of your current plan.'],
        affiliate: ['Affiliate <span class="italic text-wa-deep">program</span>','20% recurring commission for every paid plan you refer.'],
        support:   ['Support history',                                           'Your past tickets and ongoing conversations with the team.'],
        branding:  ['Branding',                                                  'Logo, favicon, colors. Used in invoices and the customer portal.'],
        translation: ['Real-time <span class="italic text-wa-deep">translation</span>', 'Auto-translate inbox conversations between your team language and your customers\' languages.'],
        password:  ['Change password',                                           'Pick a strong password and rotate it regularly.'],
        delete:    ['Delete account',                                            'Permanently remove your workspace, contacts, and history.'],
    };
    const TAB_LABELS = { profile:'Profile', plan:'Plan & usage', orders:'Order history', wallet:'Wallet', addons:'Add-ons', affiliate:'Affiliate', support:'Support history', branding:'Branding', translation:'Translation', password:'Change password', delete:'Delete account' };

    function getTab() { const m = location.search.match(/tab=([a-z]+)/); return (m ? m[1] : 'profile').toLowerCase(); }

    function activate(tab) {
        if (!TAB_TITLES[tab]) tab = 'profile';
        document.querySelectorAll('[data-pane]').forEach(p => p.classList.toggle('hidden', p.dataset.pane !== tab));
        document.querySelectorAll('[data-tab]').forEach(t => t.classList.toggle('active', t.dataset.tab === tab));
        const bc = document.getElementById('bc-tab'); if (bc) bc.textContent = TAB_LABELS[tab];
        const [title, desc] = TAB_TITLES[tab];
        const t = document.getElementById('page-title'); if (t) t.innerHTML = title;
        const d = document.getElementById('page-desc');  if (d) d.textContent = desc;
    }

    document.querySelectorAll('[data-tab]').forEach(a => a.addEventListener('click', e => {
        e.preventDefault();
        const t = a.dataset.tab;
        history.pushState(null, '', '?tab=' + t);
        activate(t);
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }));
    window.addEventListener('popstate', () => activate(getTab()));

    function toast(msg) {
        if (window.WaToaster?.info) return window.WaToaster.info(msg);
        const el = document.getElementById('toast'); if (!el) return;
        el.textContent = msg;
        el.style.opacity = '1'; el.style.transform = 'translate(-50%, -4px)';
        clearTimeout(toast._t); toast._t = setTimeout(() => { el.style.opacity = '0'; el.style.transform = 'translateX(-50%)'; }, 1700);
    }

    // Photo upload — real POST + persistent storage. Replaces the
    // previous blob-URL-only fake that vanished on reload.
    window.onAvatar = async function (e) {
        const f = e.target.files && e.target.files[0]; if (!f) return;
        const av = document.getElementById('avatar-preview');
        // The avatar is an initials base with an overlay <img> on top — set/update
        // that overlay (don't touch the initials, so they remain the fallback).
        const setImg = (src) => {
            let img = document.getElementById('avatar-img');
            if (!img) {
                img = document.createElement('img');
                img.id = 'avatar-img';
                img.alt = '';
                img.className = 'absolute inset-0 w-full h-full object-cover';
                av.appendChild(img);
            }
            img.src = src;
        };
        // Optimistic preview while the upload runs.
        const blobUrl = URL.createObjectURL(f);
        const hadImg = !!document.getElementById('avatar-img');
        setImg(blobUrl);
        try {
            const fd = new FormData(); fd.append('photo', f);
            const csrf = document.querySelector('meta[name=csrf-token]')?.content || '';
            const r = await fetch('/account/photo', {
                method: 'POST', body: fd, credentials: 'same-origin',
                headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
            });
            if (!r.ok) {
                // Surface the REAL reason instead of always blaming image size.
                let msg;
                if (r.status === 413) {
                    msg = 'Image rejected by the server (request too large). Ask your admin to raise the upload limit (Nginx client_max_body_size + PHP upload_max_filesize), or use a smaller image.';
                } else if (r.status === 419) {
                    msg = 'Your session expired — reload the page and try again.';
                } else if (r.status === 403) {
                    msg = 'Not allowed to change the photo on this account.';
                } else {
                    try {
                        const j = await r.json();
                        msg = j?.message || (j?.errors && j.errors.photo && j.errors.photo[0]) || ('Upload failed (HTTP ' + r.status + ').');
                    } catch (e) { msg = 'Upload failed (HTTP ' + r.status + ').'; }
                }
                throw new Error(msg);
            }
            const data = await r.json();
            if (data?.url) setImg(data.url);
            toast('Photo updated');
        } catch (err) {
            // Revert preview if upload failed — drop the optimistic overlay so
            // the initials (or the previously-saved photo) show again.
            if (!hadImg) document.getElementById('avatar-img')?.remove();
            toast(err && err.message ? err.message : 'Upload failed — please try again.');
        } finally {
            URL.revokeObjectURL(blobUrl);
            e.target.value = ''; // allow re-picking the same file later
        }
    };
    window.copyText = function (text) { navigator.clipboard?.writeText(text); toast('Copied to clipboard'); };

    // Real account-deletion: POSTs the confirmation phrase, scrubs PII,
    // logs out, redirects to /login?account_deleted=1.
    window.confirmDelete = async function () {
        const v = (document.getElementById('del-confirm').value || '').trim();
        if (v !== 'DELETE my account') return toast('Type "DELETE my account" exactly to confirm.');
        const csrf = document.querySelector('meta[name=csrf-token]')?.content || '';
        try {
            const r = await fetch('/account', {
                method: 'DELETE', credentials: 'same-origin',
                headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json', 'Content-Type': 'application/json' },
                body: JSON.stringify({ confirmation: v }),
            });
            const data = await r.json().catch(() => ({}));
            if (!r.ok) {
                if (data?.error === 'owner_of_active_workspaces') {
                    toast(data.message || 'Transfer your workspaces first.');
                } else {
                    toast(data?.message || 'Deletion failed.');
                }
                return;
            }
            toast('Account deleted. Redirecting…');
            setTimeout(() => location.href = data.redirect || '/login?account_deleted=1', 700);
        } catch (e) {
            toast('Deletion failed: ' + e.message);
        }
    };

    activate(getTab());

    // ---- intl-tel-input on phone ----
    // Default country from <meta name="default-country-*"> set by the admin
    // in /admin/settings/general (one place flips every phone picker).
    const defIso  = (document.querySelector('meta[name="default-country-iso"]')?.content || 'in').toLowerCase();
    const defCode = (document.querySelector('meta[name="default-country-code"]')?.content || '+91');
    const phone = document.getElementById('acc-phone');
    const cc    = document.getElementById('acc-country-code');
    if (phone) {
        const preferred = Array.from(new Set([defIso, 'in', 'us', 'gb', 'ae', 'sg']));
        const iti = intlTelInput(phone, {
            initialCountry:    defIso,
            preferredCountries: preferred,
            separateDialCode:  true,
            nationalMode:      false,
            dropdownContainer: document.body,
        });
        // Restore the SAVED country from its dial code by letting the library
        // resolve it (e.g. +94 → Sri Lanka). A hand-built dial→ISO map used to
        // miss most countries and silently fall back to India — and the sync()
        // below then OVERWROTE the saved +94 with +91 on load, so the picker
        // appeared to "revert to India" on every save.
        const savedDial = (cc?.value || '').replace(/[^\d]/g, '');
        if (savedDial) {
            const nat = (phone.value || '').replace(/\D/g, '');
            try { iti.setNumber('+' + savedDial + nat); } catch (e) { /* keep default */ }
        }
        // Mirror the chosen country's dial code into the hidden field the form
        // submits. Runs AFTER the restore above so it captures the saved
        // country, not the default — and on every manual country change.
        const sync = () => { if (cc) cc.value = '+' + iti.getSelectedCountryData().dialCode; };
        phone.addEventListener('countrychange', sync);
        sync();
    }

    // ---- tom-select on timezone (full IANA list) ----
    const tz = document.getElementById('acc-tz');
    if (tz) {
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
        const preselected = tz.value || 'Asia/Kolkata';
        tz.innerHTML = '';
        for (const z of zones) {
            const opt = document.createElement('option');
            opt.value = z; opt.textContent = z;
            if (z === preselected) opt.selected = true;
            tz.appendChild(opt);
        }
        if (!zones.includes(preselected)) {
            const opt = document.createElement('option');
            opt.value = preselected; opt.textContent = preselected; opt.selected = true;
            tz.prepend(opt);
        }
        new TomSelect(tz, {
            maxOptions: 1000,
            searchField: ['text'],
            sortField: { field: 'text', direction: 'asc' },
            placeholder: 'Search timezone...',
        });
    }

    // ---- password eye toggles on the password tab ----
    document.querySelectorAll('[data-pw-eye]').forEach((btn) => {
        if (btn.__wired) return;
        btn.__wired = true;
        btn.addEventListener('click', (e) => {
            e.preventDefault();
            const inp = document.getElementById(btn.dataset.pwEye);
            if (!inp) return;
            inp.type = inp.type === 'password' ? 'text' : 'password';
            btn.setAttribute('title', inp.type === 'password' ? 'Show password' : 'Hide password');
        });
    });
}
