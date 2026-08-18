/*
 * Installer wizard — smooth, no-reload step transitions.
 *
 * Intercepts internal /install navigation (links + plain POST forms), fetches
 * the next step, and swaps ONLY the content panel (#install-content) + the
 * left stepper (#install-stepper) into the live DOM — so the card never
 * blinks. Shows a thin top loading bar, flips the just-completed step circle
 * to a checkmark, and re-inits Alpine on the swapped subtrees.
 *
 * Safety: every navigation is wrapped in try/catch and falls back to a normal
 * full page load on any error, so the installer can never get stranded by
 * this enhancement. With JS off, every link/form works natively as before.
 *
 * Alpine-managed forms (the Database step, which uses @submit.prevent) are not
 * hijacked here — that step routes its own submit through window.installGo().
 */

const CONTENT_SEL = '#install-content';
const STEPPER_SEL = '#install-stepper';

let navigating = false;

// Progress lives INSIDE the active step's number circle: a spinning ring is
// drawn around it while the next step is fetched. The stepper is replaced
// wholesale on swap, so the ring vanishes on its own and the just-completed
// circle flips to its checkmark.
function ringOn() {
    const circle = document.querySelector(STEPPER_SEL + ' [data-step-active]');
    if (circle) circle.classList.add('step-loading');
}
function ringOff() {
    document.querySelectorAll(STEPPER_SEL + ' .step-loading').forEach((el) => el.classList.remove('step-loading'));
}

function isInstallUrl(href) {
    try {
        const u = new URL(href, location.href);
        if (u.origin !== location.origin) return false;
        return /(^|\/)install(\/|$|\?)/.test(u.pathname);
    } catch (_) {
        return false;
    }
}

function swap(html, finalUrl) {
    const doc = new DOMParser().parseFromString(html, 'text/html');
    const newContent = doc.querySelector(CONTENT_SEL);
    const curContent = document.querySelector(CONTENT_SEL);
    // If the swap targets aren't both present, bail to a real navigation.
    if (!newContent || !curContent) { location.assign(finalUrl); return; }

    const newStepper = doc.querySelector(STEPPER_SEL);
    const curStepper = document.querySelector(STEPPER_SEL);
    const prevDone = curStepper ? curStepper.querySelectorAll('[data-step-done]').length : 0;

    curContent.replaceWith(newContent);
    if (newStepper && curStepper) curStepper.replaceWith(newStepper);

    if (doc.title) document.title = doc.title;

    // Re-run the target step's INLINE scripts. The per-step Alpine component
    // definitions (dbForm/appBasics/…) live in the layout's @stack('scripts'),
    // which sits OUTSIDE the swapped region — so they must be re-executed here
    // (before Alpine inits the new tree) or x-data="dbForm()" would be
    // undefined. Module/src scripts (Vite, Alpine CDN) are skipped so we never
    // re-boot the app. Re-defining a component function is idempotent.
    try {
        doc.querySelectorAll('script:not([src])').forEach((old) => {
            if ((old.type || '').includes('module')) return;
            const code = old.textContent || '';
            if (!code.trim()) return;
            const s = document.createElement('script');
            s.textContent = code;
            document.body.appendChild(s);
            s.remove();
        });
    } catch (_) { /* non-fatal */ }

    // Re-init Alpine on the freshly inserted subtrees (next step's widgets).
    try {
        if (window.Alpine && typeof window.Alpine.initTree === 'function') {
            window.Alpine.initTree(newContent);
            if (newStepper) window.Alpine.initTree(newStepper);
        }
    } catch (_) { /* Alpine may not be ready — non-fatal */ }

    // Flip the newest completed step circle to its checkmark.
    try {
        const dones = (newStepper || document).querySelectorAll('[data-step-done]');
        if (dones.length && dones.length > prevDone) {
            dones[dones.length - 1].classList.add('step-flip');
        }
    } catch (_) { /* cosmetic only */ }

    // Reset the panel scroll + play the enter animation.
    const scroller = newContent.closest('.install-scroll');
    if (scroller && scroller.scrollTo) scroller.scrollTo(0, 0);
    newContent.classList.add('step-enter');

    try { history.pushState({}, '', finalUrl); } catch (_) { /* ignore */ }
}

async function go(url, opts = {}) {
    if (navigating) return;
    navigating = true;
    ringOn();
    const startedAt = Date.now();
    try {
        const res = await fetch(url, {
            method: opts.method || 'GET',
            body: opts.body || undefined,
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'text/html' },
            credentials: 'same-origin',
            redirect: 'follow',
        });
        // 422 = validation errors re-rendered on the same step (still swap).
        if (!res.ok && res.status !== 422) throw new Error('HTTP ' + res.status);
        const finalUrl = res.url || url;
        // The server bounced us off the installer (e.g. already installed) —
        // hand off to a real navigation rather than swapping a foreign page.
        if (!isInstallUrl(finalUrl)) { location.assign(finalUrl); return; }
        // The final "Install" step auto-runs the install via Alpine x-init.
        // Load it with a real navigation so that init fires EXACTLY once — a
        // swap + Alpine re-init could risk double-triggering execution.
        let finalPath = finalUrl;
        try { finalPath = new URL(finalUrl, location.href).pathname; } catch (_) {}
        if (/\/install\/run(\/|$|\?)/.test(finalPath)) { location.assign(finalUrl); return; }
        const html = await res.text();
        // Hold until the ring has FILLED (≈450ms) plus a short beat at full,
        // so the sequence reads "fill → complete → flip + pop to tick".
        const wait = 560 - (Date.now() - startedAt);
        if (wait > 0) await new Promise((r) => setTimeout(r, wait));
        swap(html, finalUrl);
    } catch (_) {
        ringOff();
        location.assign(url); // hard fallback — never strand the installer
    } finally {
        navigating = false;
    }
}

function onClick(e) {
    if (e.defaultPrevented || e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) return;
    const a = e.target.closest && e.target.closest('a[href]');
    if (!a || a.target === '_blank' || a.hasAttribute('download') || a.dataset.noPjax != null) return;
    if (!isInstallUrl(a.getAttribute('href'))) return;
    e.preventDefault();
    go(a.href, { method: 'GET' });
}

function onSubmit(e) {
    const form = e.target;
    if (!(form instanceof HTMLFormElement)) return;
    if (form.dataset.noPjax != null) return;
    // Skip Alpine-managed forms — they handle their own submit/navigation.
    if (form.hasAttribute('x-data') || form.hasAttribute('@submit.prevent') || form.hasAttribute('x-on:submit.prevent')) return;
    if ((form.getAttribute('method') || 'get').toLowerCase() !== 'post') return;
    const action = form.getAttribute('action') || location.href;
    if (!isInstallUrl(action)) return;
    if (typeof form.reportValidity === 'function' && !form.reportValidity()) { e.preventDefault(); return; }
    e.preventDefault();
    go(action, { method: 'POST', body: new FormData(form) });
}

export default function init() {
    // Expose for Alpine-driven steps (Database) to route their submit here.
    window.installGo = go;
    document.addEventListener('click', onClick);
    document.addEventListener('submit', onSubmit, true);
    // Back/forward: simplest correct behaviour is a full reload to the URL.
    window.addEventListener('popstate', () => { location.reload(); });
}
