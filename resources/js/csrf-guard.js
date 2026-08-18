/**
 * CSRF staleness guard — kills the "Your session expired for security" loop.
 *
 * Mobile browsers and CDNs sometimes serve a CACHED login (or any) page whose
 * hidden <input name="_token"> points at a session that has since been swept
 * from the DB. The next POST then fails Laravel's CSRF check → 419 → the
 * graceful "session expired" bounce lands the user back on the same cached
 * page → it happens again and again.
 *
 * Fix: fetch a LIVE token (one that matches the CURRENT session cookie) from
 * /csrf-token and write it into every <input name="_token"> plus the
 * <meta name="csrf-token"> tag, so a cached form still submits a valid token.
 * We do this on first paint and on back/forward (bfcache) restore.
 *
 * Deliberately does NOT intercept form submits — that keeps the reCAPTCHA v3
 * submit handler (auth-recaptcha.js) and any AJAX form handlers untouched.
 */

function applyToken(tok) {
    if (!tok) return;
    document.querySelectorAll('input[name="_token"]').forEach((i) => { i.value = tok; });
    const meta = document.querySelector('meta[name="csrf-token"]');
    if (meta) meta.setAttribute('content', tok);
}

async function refreshToken() {
    // Only the pages that can actually 419 — i.e. that carry a CSRF form.
    if (!document.querySelector('input[name="_token"]')) return;
    try {
        const url = (typeof window.appUrl === 'function') ? window.appUrl('/csrf-token') : '/csrf-token';
        const res = await fetch(url, {
            headers: { Accept: 'application/json' },
            credentials: 'same-origin',
            cache: 'no-store',
        });
        if (!res.ok) return;
        const data = await res.json();
        applyToken(data && data.token);
    } catch (e) {
        /* offline / blocked — the server-side graceful 419 handler is the net */
    }
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', refreshToken);
} else {
    refreshToken();
}

// bfcache restores the DOM (and its stale _token) verbatim without re-running
// module top-level code, so refresh again when the page comes back from cache.
window.addEventListener('pageshow', (e) => { if (e.persisted) refreshToken(); });
