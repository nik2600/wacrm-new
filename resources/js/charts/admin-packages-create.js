export default function init() {
    const total = 4;
    let current = 1;

    const panes  = document.querySelectorAll('.step-pane');
    const nodes  = document.querySelectorAll('.step-node');
    const prev   = document.getElementById('prevBtn');
    const next   = document.getElementById('nextBtn');
    const submit = document.getElementById('submitBtn');
    const curLab = document.getElementById('cur-step');
    const form   = document.getElementById('pkgForm');

    const add    = (el, cls) => el.classList.add(...cls);
    const remove = (el, cls) => el.classList.remove(...cls);

    const setStep = (n) => {
        current = Math.max(1, Math.min(total, n));
        panes.forEach((p) => p.classList.toggle('hidden', Number(p.dataset.step) !== current));
        nodes.forEach((node) => {
            const i   = Number(node.dataset.n);
            const dot = node.querySelector('.dot');
            const lab = node.querySelector('.lab');
            const bar = node.querySelector('.bar');
            remove(dot, ['bg-paper-0','bg-wa-deep','border-paper-200','border-wa-deep','text-ink-500','text-wa-deep','text-paper-0','ring-4','ring-wa-deep/10']);
            remove(lab, ['text-ink-500','text-wa-deep','font-medium','font-semibold']);
            if (i < current) {
                add(dot, ['bg-wa-deep','border-wa-deep','text-paper-0']);
                add(lab, ['text-wa-deep','font-semibold']);
                if (bar) { bar.classList.remove('bg-paper-200'); bar.classList.add('bg-wa-deep'); }
            } else if (i === current) {
                add(dot, ['bg-paper-0','border-wa-deep','text-wa-deep','ring-4','ring-wa-deep/10']);
                add(lab, ['text-wa-deep','font-semibold']);
                if (bar) { bar.classList.remove('bg-wa-deep'); bar.classList.add('bg-paper-200'); }
            } else {
                add(dot, ['bg-paper-0','border-paper-200','text-ink-500']);
                add(lab, ['text-ink-500','font-medium']);
                if (bar) { bar.classList.remove('bg-wa-deep'); bar.classList.add('bg-paper-200'); }
            }
        });
        prev.disabled = current === 1;
        next.classList.toggle('hidden', current === total);
        submit.classList.toggle('hidden', current !== total);
        curLab.textContent = current;
        if (current === total) renderReview();
        window.scrollTo({ top: 0, behavior: 'smooth' });
    };

    // Validate before advancing — keep it simple: only step 1 has hard-required fields.
    // Returns null on success, otherwise { msg, field }.
    const validateStep = (n) => {
        if (n !== 1) return null;
        const name   = form.querySelector('[name="pname"]');
        const amount = form.querySelector('[name="plan_amount"]');
        const dur    = form.querySelector('[name="plan_duration"]');
        const cur    = form.querySelector('[name="currency"]');
        if (!name.value.trim()) return { msg: 'Package name is required.', field: name };
        if (amount.value === '' || Number(amount.value) < 0) return { msg: 'Price must be a non-negative number.', field: amount };
        if (!dur.value || Number(dur.value) < 1) return { msg: 'Billing period must be at least 1.', field: dur };
        if (!cur.value) return { msg: 'Currency is required.', field: cur };
        return null;
    };

    const tryAdvance = async (target) => {
        const err = validateStep(current);
        if (err) {
            const fn = window.uiAlert || ((opts) => { window.alert(opts.message); return Promise.resolve(); });
            await fn({ title: 'Fix this first', message: err.msg });
            err.field?.focus();
            return false;
        }
        setStep(target);
        return true;
    };

    prev.addEventListener('click', () => setStep(current - 1));
    next.addEventListener('click', () => tryAdvance(current + 1));
    nodes.forEach((node) => node.addEventListener('click', () => {
        const target = Number(node.dataset.n);
        if (target <= current) setStep(target);
        else tryAdvance(target);
    }));

    // Build a friendly summary on step 4. Reads form data live so no stale state.
    function renderReview() {
        const fd = new FormData(form);
        const get = (k, fallback = '—') => {
            const v = fd.get(k);
            return v === null || v === '' ? fallback : v;
        };
        const yes = (k) => fd.get(k) === '1';
        // Count feature toggles + limit inputs by data-* marker so they are
        // tallied no matter which step they live on (incl. the Instagram step).
        const featOn = [];
        document.querySelectorAll('[data-feature-toggle]').forEach((cb) => {
            if (cb.checked) featOn.push(cb.name);
        });

        const limitsList = [];
        document.querySelectorAll('[data-limit-input]').forEach((inp) => {
            if (inp.value) limitsList.push(`${inp.previousElementSibling?.querySelector?.('span')?.textContent || inp.name}: ${Number(inp.value).toLocaleString()}`);
        });

        const rows = [
            ['Name',         get('pname')],
            ['Subtitle',     get('subtitle')],
            ['Price',        `${get('currency','USD')} ${get('plan_amount','0')}` + (fd.get('offer_price') ? ` (offer: ${fd.get('offer_price')})` : '')],
            ['Billing',      `${get('plan_duration','1')} ${get('plan_unit','months')}`],
            ['Flags',        [
                yes('free') && 'Free',
                yes('lifetime') && 'Lifetime',
                yes('status') && 'Active',
                yes('is_default') && 'Default',
                yes('is_highlighted') && 'Highlighted',
                yes('is_custom_quote') && 'Custom quote',
            ].filter(Boolean).join(' · ') || '—'],
            ['Limits set',   limitsList.length ? `${limitsList.length} cap(s) configured` : 'all unlimited'],
            ['Features on',  featOn.length ? `${featOn.length} enabled` : 'none enabled'],
            ['CTA',          `${get('cta_label','Get started')} → ${get('cta_url','/checkout')}`],
            ['Sort order',   get('sort_order', '0')],
        ];

        const out = document.getElementById('pkg-review');
        out.innerHTML = rows.map(([k, v]) => `
            <div class="flex items-start gap-3 py-1.5 border-b border-paper-200/60 last:border-0">
                <span class="text-ink-500 w-[130px] shrink-0">${k}</span>
                <span class="font-mono text-ink-900 break-words">${v}</span>
            </div>
        `).join('');
    }

    setStep(1);
}
