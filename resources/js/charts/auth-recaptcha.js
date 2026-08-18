/*
 * reCAPTCHA wiring for the guest auth forms (login + register).
 *
 * v2 (checkbox) needs no JS — Google's api.js auto-renders the
 * .g-recaptcha box and injects g-recaptcha-response on submit. That
 * <script> tag is emitted directly by auth/_recaptcha.blade.php.
 *
 * v3 (score) is invisible: we lazy-load Google's api.js?render=<siteKey>,
 * then intercept the form submit to fetch a fresh token, drop it into the
 * hidden #recaptcha-token field, and resubmit. The site key + action come
 * from data- attributes on the [data-recaptcha-v3] marker, so nothing is
 * hardcoded — they are admin-configured and rendered server-side.
 *
 * Called from auth-login.js and auth-register.js. Safe to call when
 * reCAPTCHA is disabled or set to v2 (it no-ops).
 */
export default function initRecaptcha() {
    const host = document.querySelector('[data-recaptcha-v3]');
    if (!host) return;                       // disabled, or v2 — nothing to do here
    if (host.__wadeskReWired) return;
    host.__wadeskReWired = true;

    const siteKey = host.getAttribute('data-sitekey');
    const action  = host.getAttribute('data-action') || 'login';
    const token   = document.getElementById('recaptcha-token');
    const form    = token ? token.closest('form') : null;
    if (!siteKey || !form || !token) return;

    // Lazy-load the v3 library exactly once.
    if (!document.getElementById('recaptcha-v3-lib')) {
        const s = document.createElement('script');
        s.id = 'recaptcha-v3-lib';
        s.src = 'https://www.google.com/recaptcha/api.js?render=' + encodeURIComponent(siteKey);
        s.async = true;
        document.head.appendChild(s);
    }

    let passed = false;
    form.addEventListener('submit', (e) => {
        if (passed) return;                  // second pass — token already attached
        e.preventDefault();

        const run = (tries) => {
            if (window.grecaptcha && typeof window.grecaptcha.execute === 'function') {
                window.grecaptcha.ready(() => {
                    window.grecaptcha.execute(siteKey, { action }).then((t) => {
                        token.value = t;
                        passed = true;
                        form.submit();
                    }).catch(() => {
                        // Token fetch failed — submit anyway; the server-side
                        // RecaptchaService still guards and will reject if needed.
                        passed = true;
                        form.submit();
                    });
                });
            } else if (tries > 0) {
                setTimeout(() => run(tries - 1), 300);   // library still loading
            } else {
                passed = true;
                form.submit();                            // give up waiting; server guards
            }
        };
        run(20);                              // up to ~6s for a slow network
    });
}
