/**
 * Universal password-reveal eye button.
 *
 * Auto-attaches to every <input type="password"> on the page (and any
 * input/textarea marked with [data-reveal]). The login form, register
 * form, admin payment-gateways credential fields, /admin/api-keys API
 * key inputs, /settings → security tab — all get the same affordance
 * without per-template wiring.
 *
 * Skip an input by adding [data-no-reveal] on it.
 *
 * Also picks up dynamically-added inputs via a MutationObserver so
 * payment-gateway cards that lazy-expand still get the toggle.
 */
import { themeColor } from '../theme-colors.js';

(function () {
    // Already-installed flag to keep the module idempotent across hot
    // reloads + multiple imports.
    if (window.__waPwReveal) return;
    window.__waPwReveal = true;

    const SVG_EYE_OPEN = '<svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path d="M1 8s2.5-5 7-5 7 5 7 5-2.5 5-7 5-7-5-7-5z"/><circle cx="8" cy="8" r="2"/></svg>';
    const SVG_EYE_OFF  = '<svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path d="M1 8s2.5-5 7-5c1.5 0 2.7.5 3.8 1.2M15 8s-2.5 5-7 5c-1.5 0-2.7-.5-3.8-1.2M3 13l10-10"/></svg>';

    function attach(input) {
        // Skip if not the right kind of field, already wrapped, or
        // explicitly opted out by the markup.
        if (!input || input.dataset.revealAttached === '1') return;
        if (input.dataset.noReveal === '1') return;
        const isPw   = input.tagName === 'INPUT' && input.type === 'password';
        const opted  = input.hasAttribute('data-reveal');
        if (!isPw && !opted) return;

        input.dataset.revealAttached = '1';

        // Wrap the input so we can position the button absolutely. If
        // an ancestor is already position:relative we skip the wrap and
        // mount the button into it instead — keeps existing CSS happy.
        let host;
        const parent = input.parentElement;
        const parentStyles = parent ? getComputedStyle(parent) : null;
        if (parent && parentStyles && parentStyles.position === 'relative' && parent.childElementCount === 1) {
            host = parent;
        } else {
            host = document.createElement('span');
            host.className = 'wa-pw-reveal-wrap';
            host.style.position    = 'relative';
            host.style.display     = 'block';
            host.style.width       = '100%';
            input.parentNode.insertBefore(host, input);
            host.appendChild(input);
        }

        // Make room on the right of the input for the button.
        const prevPadRight = input.style.paddingRight;
        input.style.paddingRight = '36px';

        const btn = document.createElement('button');
        btn.type = 'button';
        btn.setAttribute('aria-label', 'Show password');
        btn.setAttribute('tabindex', '-1');
        btn.className = 'wa-pw-reveal-btn';
        btn.style.cssText = [
            'position:absolute',
            'right:8px',
            'top:50%',
            'transform:translateY(-50%)',
            'width:24px',
            'height:24px',
            'border:0',
            'background:transparent',
            'color:' + themeColor('ink-500'),
            'cursor:pointer',
            'border-radius:6px',
            'display:inline-flex',
            'align-items:center',
            'justify-content:center',
            'padding:0',
        ].join(';');
        btn.innerHTML = SVG_EYE_OPEN;
        btn.addEventListener('mouseenter', () => { btn.style.color = themeColor('wa-deep'); });
        btn.addEventListener('mouseleave', () => { btn.style.color = themeColor('ink-500'); });
        btn.addEventListener('click', (e) => {
            e.preventDefault();
            const hidden = input.type === 'password';
            input.type = hidden ? 'text' : 'password';
            btn.innerHTML = hidden ? SVG_EYE_OFF : SVG_EYE_OPEN;
            btn.setAttribute('aria-label', hidden ? 'Hide password' : 'Show password');
        });
        host.appendChild(btn);
    }

    function scan(root) {
        (root || document).querySelectorAll('input[type="password"], [data-reveal]').forEach(attach);
    }

    // First sweep on DOM ready.
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => scan());
    } else {
        scan();
    }

    // Catch dynamically-added inputs (gateway cards that lazy-expand,
    // modals, AJAX-rendered forms). MutationObserver is cheap because
    // each new input is only touched once thanks to the dataset flag.
    const mo = new MutationObserver((muts) => {
        for (const m of muts) {
            m.addedNodes.forEach((n) => {
                if (!(n instanceof Element)) return;
                if (n.matches?.('input[type="password"], [data-reveal]')) attach(n);
                scan(n);
            });
        }
    });
    mo.observe(document.documentElement, { childList: true, subtree: true });

    // Expose a manual hook for callers that explicitly rescan.
    window.WaPwReveal = { scan };
})();
