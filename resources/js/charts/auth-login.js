import initRecaptcha from './auth-recaptcha.js';
import { themeColor } from '../theme-colors.js';

/*
 * /login page module.
 *
 * Wires the password show/hide eye-toggle and shows a one-shot
 * "you've been signed out" toast when the URL has ?logout=1.
 *
 * The toggle uses event delegation off `document` so it doesn't
 * matter whether the user clicks the button itself or the inner
 * SVG path — `closest('#pw-toggle')` catches both, and the guard
 * on the listener prevents double-binds when the module is hot-
 * reloaded.
 */
export default function init() {
    initRecaptcha();   // no-op unless reCAPTCHA v3 is enabled
    if (!window.__wadeskLoginWired) {
        window.__wadeskLoginWired = true;
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

    if (location.search.includes('logout=1')) {
        const t = document.createElement('div');
        t.textContent = "You've been signed out";
        t.style.cssText = `position:fixed;left:50%;top:24px;transform:translateX(-50%);background:${themeColor('ink-900')};color:${themeColor('paper-0')};padding:8px 14px;border-radius:999px;font-size:12px;font-weight:500;box-shadow:0 12px 28px -10px rgba(0,0,0,0.4);z-index:60`;
        document.body.appendChild(t);
        setTimeout(() => t.remove(), 2200);
    }
}
