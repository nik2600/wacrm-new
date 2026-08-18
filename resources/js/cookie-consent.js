/**
 * Cookie consent manager.
 *
 * Two surfaces (rendered by partials/cookie-consent.blade.php):
 *   • #wa-cookie-bar    — compact sticky banner (default first surface)
 *   • #wa-cookie-consent — granular 3-toggle modal (opens on "Customize")
 *
 * Behaviour on first visit (no `wadesk_cookie_consent` cookie):
 *   • banner-style = "modal"    → modal opens
 *   • banner-style = "bottom-bar"/"top-bar" → bar appears
 * Behaviour on subsequent visits: both hidden until user clicks any
 * [data-cookie-prefs-open] link (footer, account settings).
 *
 * Action buttons (on either surface):
 *   • data-cookie-action="reject"  → necessary-only, 1 year
 *   • data-cookie-action="save"    → modal-toggle state, 1 year (modal only)
 *   • data-cookie-action="accept"  → necessary + analytics + marketing
 *
 * Persisted as JSON in `wadesk_cookie_consent` (SameSite=Lax, 1 year).
 * Exposes window.wadeskCookieConsent.{get, has, open}.
 */
(function () {
    'use strict';

    const COOKIE_NAME = 'wadesk_cookie_consent';
    const MAX_AGE     = 60 * 60 * 24 * 365;

    function readCookie() {
        const raw = document.cookie
            .split('; ')
            .find((row) => row.startsWith(COOKIE_NAME + '='));
        if (!raw) return null;
        try {
            return JSON.parse(decodeURIComponent(raw.split('=')[1]));
        } catch (_) { return null; }
    }

    function writeCookie(prefs) {
        const value = encodeURIComponent(JSON.stringify({ ...prefs, ts: Math.floor(Date.now() / 1000) }));
        const secure = location.protocol === 'https:' ? '; Secure' : '';
        document.cookie = `${COOKIE_NAME}=${value}; path=/; max-age=${MAX_AGE}; SameSite=Lax${secure}`;
    }

    function show(el) {
        if (!el) return;
        el.classList.remove('hidden');
        el.classList.add(el.dataset.cookieModal !== undefined ? 'flex' : 'block');
    }
    function hide(el) {
        if (!el) return;
        el.classList.add('hidden');
        el.classList.remove('flex', 'block');
    }

    function persist(prefs, reload = true) {
        writeCookie(prefs);
        hide(document.getElementById('wa-cookie-bar'));
        hide(document.getElementById('wa-cookie-consent'));
        // Reload so the server-side partial re-emits the matching
        // tracker scripts (or removes them). Skipped when the caller
        // explicitly disables it — e.g. closing the picker without
        // changing anything.
        if (reload) setTimeout(() => location.reload(), 180);
    }

    document.addEventListener('DOMContentLoaded', function () {
        const bar   = document.getElementById('wa-cookie-bar');
        const modal = document.getElementById('wa-cookie-consent');
        const style = document.documentElement.dataset.cookieBannerStyle || 'bottom-bar';
        if (!bar && !modal) return;

        // Reopen buttons in the footer / account settings always work.
        document.querySelectorAll('[data-cookie-prefs-open]').forEach((btn) => {
            btn.addEventListener('click', (e) => {
                e.preventDefault();
                hide(bar);
                if (!modal) return;
                const saved = readCookie();
                if (saved) {
                    const aIn = modal.querySelector('[data-cookie-toggle="analytics"]');
                    const mIn = modal.querySelector('[data-cookie-toggle="marketing"]');
                    if (aIn) aIn.checked = !!saved.analytics;
                    if (mIn) mIn.checked = !!saved.marketing;
                }
                show(modal);
            });
        });

        // First-visit decision.
        const saved = readCookie();
        if (!saved) {
            const dntRespect = document.documentElement.dataset.dntRespect === '1';
            const dntOn      = (navigator.doNotTrack === '1' || window.doNotTrack === '1');
            if (dntRespect && dntOn) {
                // DNT respected → implicit reject (no UI shown).
                persist({ necessary: true, analytics: false, marketing: false }, false);
            } else if (style === 'modal' && modal) {
                show(modal);
            } else if (bar) {
                show(bar);
            } else if (modal) {
                show(modal);
            }
        }

        // Wire every action button on BOTH surfaces.
        document.querySelectorAll('[data-cookie-action]').forEach((btn) => {
            btn.addEventListener('click', () => {
                const action = btn.dataset.cookieAction;
                if (action === 'accept') {
                    persist({ necessary: true, analytics: true, marketing: true });
                } else if (action === 'reject') {
                    persist({ necessary: true, analytics: false, marketing: false });
                } else if (action === 'save') {
                    const a = modal?.querySelector('[data-cookie-toggle="analytics"]')?.checked;
                    const m = modal?.querySelector('[data-cookie-toggle="marketing"]')?.checked;
                    persist({ necessary: true, analytics: !!a, marketing: !!m });
                }
            });
        });
    });

    window.wadeskCookieConsent = {
        get: readCookie,
        has(category) {
            const c = readCookie();
            return !!(c && c[category]);
        },
        open() {
            const bar   = document.getElementById('wa-cookie-bar');
            const modal = document.getElementById('wa-cookie-consent');
            const style = document.documentElement.dataset.cookieBannerStyle || 'bottom-bar';
            if (style === 'modal' && modal) {
                modal.classList.remove('hidden');
                modal.classList.add('flex');
            } else if (bar) {
                bar.classList.remove('hidden');
                bar.classList.add('block');
            }
        },
    };
}());
