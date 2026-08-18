/*
 * Shared display helpers for template / campaign variable tokens.
 *
 * STORAGE + the Meta/WABA submit payload stay POSITIONAL ({{1}} {{2}}) —
 * this module ONLY changes what the operator SEES while authoring:
 *
 *   1. The `/` attribute picker + the {{1}}{{2}} insert chips insert the
 *      attribute KEY — {{name}}, {{order_id}} — so the body reads as
 *      meaning, not a number. The server-side normalizer converts those
 *      named tokens back to positional {{1}} on save (see
 *      TemplatesController::normalizePlaceholders + buildVariableMap*).
 *
 *   2. The live-preview bubble renders the actual SAMPLE VALUE for each
 *      token (real attribute value → friendly built-in stand-in →
 *      {{key}} literal), so "Hi {{name}}" previews as "Hi John".
 *
 * Nothing here ever sends: the sample substitution is pure front-end
 * paint for the preview pane only.
 */

// Built-in friendly stand-ins, keyed by the LOWERCASED attribute key.
// Used when the workspace attribute has no demo value of its own. Keep
// this small and human — it only feeds the preview bubble.
const BUILTIN_SAMPLES = {
    name: 'John',
    first_name: 'John',
    firstname: 'John',
    last_name: 'Doe',
    lastname: 'Doe',
    full_name: 'John Doe',
    fullname: 'John Doe',
    customer_name: 'John',
    company: 'Acme Co',
    company_name: 'Acme Co',
    business: 'Acme Co',
    email: 'john@example.com',
    phone: '+1 555 0142',
    mobile: '+1 555 0142',
    city: 'Austin',
    country: 'USA',
    order_id: 'ORD-12',
    order_number: 'ORD-12',
    order: 'ORD-12',
    invoice: 'INV-77',
    invoice_id: 'INV-77',
    amount: '$49.00',
    total: '$49.00',
    price: '$49.00',
    discount: '15%',
    coupon: 'SAVE15',
    promo: 'SAVE15',
    promo_code: 'SAVE15',
    code: 'SAVE15',
    otp: '482913',
    date: 'May 25',
    time: '2:30 PM',
    appointment: 'May 25, 2:30 PM',
    tracking: 'TRK-93421',
    tracking_id: 'TRK-93421',
    tracking_number: 'TRK-93421',
    product: 'Hoodie',
    product_name: 'Hoodie',
    quantity: '2',
    qty: '2',
    url: 'example.com/x',
    link: 'example.com/x',
};

/**
 * Best sample value for a single attribute key.
 * Priority: caller-supplied real value (from the workspace attribute) →
 * built-in friendly stand-in → null (caller decides the {{key}} fallback).
 *
 * @param {string} key
 * @param {Object<string,string>} [valueByKey]  real attribute values keyed by attribute key
 * @returns {string|null}
 */
export function sampleFor(key, valueByKey = {}) {
    if (!key) return null;
    const real = valueByKey[key];
    if (real !== undefined && real !== null && String(real).trim() !== '') {
        return String(real);
    }
    const builtin = BUILTIN_SAMPLES[String(key).toLowerCase()];
    if (builtin !== undefined) return builtin;
    return null;
}

/**
 * Replace every {{token}} in `text` with its preview sample value.
 *
 * Handles BOTH token styles so the preview is correct whether the body
 * is still named ({{name}}) in the editor or already positional ({{1}}):
 *
 *   - named   {{order_id}}  → sampleFor('order_id') or {{order_id}}
 *   - numeric {{1}}         → slotMap[1] gives the key, then sampleFor(key);
 *                             if no key is known, falls back to {{1}}
 *
 * @param {string} text
 * @param {Object} [opts]
 * @param {Object<string,string>} [opts.valueByKey]  real attribute values by key
 * @param {Object<string|number,string>} [opts.slotMap]  {1:'name'} positional→key map
 * @returns {string} text with samples substituted (unknown tokens left literal)
 */
export function fillSamples(text, opts = {}) {
    if (!text) return text || '';
    const valueByKey = opts.valueByKey || {};
    const slotMap = opts.slotMap || {};
    return String(text).replace(/\{\{\s*([a-zA-Z0-9_][\w.-]*)\s*\}\}/g, (full, token) => {
        // Numeric slot → resolve its mapped key first.
        if (/^\d+$/.test(token)) {
            const key = slotMap[token] ?? slotMap[Number(token)] ?? null;
            if (key) {
                const s = sampleFor(key, valueByKey);
                if (s !== null) return s;
            }
            return full; // unmapped numeric slot — keep literal
        }
        // Named token.
        const s = sampleFor(token, valueByKey);
        return s !== null ? s : full;
    });
}

let valueCachePromise = null;

/**
 * Fetch the workspace's attribute demo/real values once, keyed by
 * attribute key, for the preview substitution. Resilient: any failure
 * resolves to an empty map so the preview falls back to built-ins.
 *
 * @returns {Promise<Object<string,string>>}
 */
export function loadAttributeValues() {
    if (valueCachePromise) return valueCachePromise;
    valueCachePromise = fetch('/attributes/api/list', {
        headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        credentials: 'same-origin',
    })
        .then((res) => (res.ok ? res.json() : { system: [], custom: [] }))
        .then((data) => {
            const out = {};
            [...((data && data.system) || []), ...((data && data.custom) || [])].forEach((a) => {
                if (!a || !a.key) return;
                const v = a.value;
                if (v !== undefined && v !== null && String(v).trim() !== '') {
                    out[a.key] = String(v);
                }
            });
            return out;
        })
        .catch(() => ({}));
    return valueCachePromise;
}

export default { sampleFor, fillSamples, loadAttributeValues };
