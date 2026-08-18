/**
 * Sub-folder base-path support for all client-side requests.
 *
 * When WaDesk is served from a sub-folder (e.g. https://example.com/public/)
 * the server-rendered links already carry the /public prefix (AppServiceProvider
 * forces the URL root from APP_URL). But hand-written AJAX in the dashboard uses
 * root-relative paths like fetch('/devices') — the browser resolves those against
 * the domain ROOT, dropping /public, so they 404.
 *
 * This module reads the base path the server injected as <meta name="app-base">
 * and transparently prefixes it onto any root-relative ("/…") fetch() and
 * XMLHttpRequest (which is what axios uses under the hood). It is a no-op when
 * the app is at the domain root (empty base), so local dev is unaffected.
 *
 * It is imported FIRST in app.js so the patch is in place before any other
 * module can fire a request.
 */
const base = (document.querySelector('meta[name="app-base"]')?.getAttribute('content') || '')
    .replace(/\/+$/, '');

// Expose for any code that wants to build a URL explicitly.
window.appBase = base;
window.appUrl = (path = '/') => {
    const p = String(path);
    if (/^([a-z]+:)?\/\//i.test(p)) return p;            // absolute (http://, //cdn)
    if (p.charAt(0) !== '/') return p;                    // relative — leave as-is
    return base + p;
};

// Only patch when there's actually a sub-folder to inject.
if (base) {
    const needsPrefix = (url) =>
        typeof url === 'string'
        && url.charAt(0) === '/'
        && url.charAt(1) !== '/'                          // not protocol-relative //host
        && !url.startsWith(base + '/')                    // not already prefixed
        && url !== base;

    // fetch()
    const origFetch = window.fetch.bind(window);
    window.fetch = function (input, init) {
        try {
            if (needsPrefix(input)) {
                input = base + input;
            } else if (input instanceof Request && needsPrefix(input.url)) {
                input = new Request(base + new URL(input.url, location.origin).pathname
                    + new URL(input.url, location.origin).search, input);
            }
        } catch (e) { /* fall through with original input */ }
        return origFetch(input, init);
    };

    // XMLHttpRequest (axios, legacy XHR)
    const origOpen = XMLHttpRequest.prototype.open;
    XMLHttpRequest.prototype.open = function (method, url, ...rest) {
        if (needsPrefix(url)) {
            url = base + url;
        }
        return origOpen.call(this, method, url, ...rest);
    };

    // history.pushState / replaceState — the History API is NOT covered by the
    // fetch/anchor patches above, so a hand-written pushState('/devices?…') would
    // drop the sub-folder and bounce the address bar off /public. Patch the URL
    // argument here so every page's filter/URL-sync stays on the sub-folder.
    ['pushState', 'replaceState'].forEach((fn) => {
        const origHist = history[fn].bind(history);
        history[fn] = function (state, title, url) {
            if (needsPrefix(url)) url = base + url;
            return origHist(state, title, url);
        };
    });

    // navigator.sendBeacon (presence-leave on tab close, abandoned-cart, …)
    if (navigator.sendBeacon) {
        const origBeacon = navigator.sendBeacon.bind(navigator);
        navigator.sendBeacon = function (url, data) {
            if (needsPrefix(url)) url = base + url;
            return origBeacon(url, data);
        };
    }

    // <form action="/…"> native submits — NOT covered by the fetch/XHR/anchor
    // patches above. A hardcoded action like /admin/support/5/status posts to
    // the domain root and drops the sub-folder → 404 under a /public install.
    // Rewrite the action attribute in the capture phase right before submit so
    // it carries the sub-folder. NO one-time flag here: some panels (e.g. the
    // support ticket slide-over) re-set a form's action to an un-prefixed path
    // every time they open, so we must re-check on every submit. needsPrefix()
    // already returns false for an action that's been prefixed, so this is
    // safely idempotent without a flag.
    document.addEventListener('submit', (e) => {
        const f = e.target;
        if (!f || f.tagName !== 'FORM') return;
        const a = f.getAttribute('action');
        if (needsPrefix(a)) f.setAttribute('action', base + a);
    }, true);

    // Anchors — the safety net. Any <a href="/…"> built in JS (innerHTML in
    // the flow builder, team-inbox, integrations, etc.) or hardcoded in a blade
    // is rewritten to carry the sub-folder the moment it's about to be used.
    // Rewriting the href ATTRIBUTE (not preventDefault) keeps Ctrl/Cmd-click,
    // middle-click and "open in new tab" working. Runs in the capture phase on
    // pointerdown (before navigation) + focusin (keyboard), so it covers links
    // added dynamically after page load without a MutationObserver.
    const fixAnchor = (a) => {
        if (!a || a.__wdBased) return;
        const h = a.getAttribute('href');
        if (needsPrefix(h)) {
            a.setAttribute('href', base + h);
            a.__wdBased = true; // never double-prefix the same node
        }
    };
    const onMaybeAnchor = (e) => {
        const a = e.target && e.target.closest ? e.target.closest('a[href]') : null;
        if (a) fixAnchor(a);
    };
    document.addEventListener('pointerdown', onMaybeAnchor, true);
    document.addEventListener('focusin', onMaybeAnchor, true);
    // Touch devices that don't emit pointerdown reliably:
    document.addEventListener('touchstart', onMaybeAnchor, true);
}
