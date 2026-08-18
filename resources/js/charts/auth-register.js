import intlTelInput from 'intl-tel-input/intlTelInputWithUtils';
import 'intl-tel-input/styles';
import initRecaptcha from './auth-recaptcha.js';

/*
 * /register page module (account step).
 *
 * Two responsibilities:
 *   1. Password show/hide eye-toggle, wired through the document
 *      (event delegation, same as auth-login.js — survives any
 *      future blade rewrites that re-render the password block).
 *   2. intl-tel-input on the WhatsApp number field. Same flag-picker
 *      we use on /devices/index and /account?tab=profile so the
 *      register page matches the rest of the app — country dropdown
 *      with search + auto-IP geolocation, dial code written into the
 *      hidden country_code input on every change.
 */
export default function init() {
    initRecaptcha();   // no-op unless reCAPTCHA v3 is enabled

    if (!window.__wadeskRegisterWired) {
        window.__wadeskRegisterWired = true;
        document.addEventListener('click', (e) => {
            const btn = e.target.closest('#pw-toggle');
            if (!btn) return;
            e.preventDefault();
            const pw = document.getElementById('pw');
            if (!pw) return;
            pw.type = pw.type === 'password' ? 'text' : 'password';
            btn.setAttribute('title', pw.type === 'password' ? 'Show password' : 'Hide password');
        });
    }

    // Platform-wide default country comes from <meta name="default-country-*">
    // stamped by every layout (admin/user/guest/instagram). Lets the admin
    // flip the default in /admin/settings/general without touching this JS.
    const defIso  = (document.querySelector('meta[name="default-country-iso"]')?.content || 'in').toLowerCase();
    const defCode = (document.querySelector('meta[name="default-country-code"]')?.content || '+91');
    const phone = document.getElementById('reg-phone');
    const cc    = document.getElementById('reg-country-code');
    if (phone) {
        const initial = (cc?.value || defCode).replace(/[^\d]/g, '');
        // Detect ISO from the saved dial code; fall back to platform default ISO.
        const dialMap = { '1':'us', '44':'gb', '971':'ae', '65':'sg', '91':'in', '62':'id', '60':'my', '66':'th', '63':'ph', '92':'pk', '880':'bd' };
        const iso = dialMap[initial] || defIso;
        // Put the platform-default country at the top of the dropdown so it
        // shows first when the picker opens. De-dupe with a Set.
        const preferred = Array.from(new Set([defIso, 'in', 'us', 'gb', 'ae', 'sg']));
        const iti = intlTelInput(phone, {
            initialCountry:    iso,
            preferredCountries: preferred,
            separateDialCode:  true,
            nationalMode:      false,
            dropdownContainer: document.body,
        });
        const sync = () => { if (cc) cc.value = '+' + iti.getSelectedCountryData().dialCode; };
        sync();
        phone.addEventListener('countrychange', sync);
    }
}
