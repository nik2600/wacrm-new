/*
 * Variable-mapping panel for the template create / edit editors.
 *
 * Meta templates only allow POSITIONAL placeholders ({{1}}, {{2}}, …) in
 * STORAGE, but the editor now lets operators author NAMED tokens
 * ({{name}}, {{order_id}}) so the body reads as meaning. The server
 * normalizes named → positional on save.
 *
 * This panel records WHICH workspace attribute each token resolves to at
 * send time, writing a { "1": "promo_key", "2": "order_id" } object —
 * keyed by the token's FIRST-APPEARANCE slot number, the same order the
 * server renumbers into — onto the hidden `[name="variable_map_json"]`
 * field. The controller turns that into the stored `variable_map`, and
 * AttributeResolver fills the placeholder with the contact/workspace
 * attribute value on dispatch.
 *
 * For a NAMED token the attribute is already implied by the token name,
 * so its row is pre-selected to that attribute (and can be re-pointed).
 * For a bare NUMERIC token (legacy positional body) the operator picks
 * the attribute explicitly, exactly as before.
 *
 * The attribute list comes from the same endpoint the slash attribute
 * picker uses (GET /attributes/api/list → { system:[], custom:[] }).
 *
 * The picker (resources/js/attribute-picker.js) also writes the same
 * hidden field directly when an attribute is inserted via `/`; this
 * panel re-reads that field so a picker-inserted slot shows the right
 * attribute pre-selected.
 */

let attrCachePromise = null;

function loadAttributes() {
    if (attrCachePromise) return attrCachePromise;
    attrCachePromise = fetch('/attributes/api/list', {
        headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        credentials: 'same-origin',
    })
        .then((res) => (res.ok ? res.json() : { system: [], custom: [] }))
        .then((data) => [
            ...((data && data.system) || []),
            ...((data && data.custom) || []),
        ])
        .catch(() => []);
    return attrCachePromise;
}

function readMap(hidden) {
    try {
        const obj = JSON.parse(hidden.value || '{}');
        return obj && typeof obj === 'object' ? obj : {};
    } catch (_) {
        return {};
    }
}

function writeMap(hidden, obj) {
    hidden.value = JSON.stringify(obj);
}

// Ordered, de-duplicated list of every {{token}} in the body — named OR
// numeric — in first-appearance order. Each entry gets a 1-based `slot`
// matching how the server renumbers tokens on save, plus the original
// `token` and whether it was a bare number (legacy positional) or a name.
function slotsInBody(body) {
    const out = [];
    const seen = new Set();
    const re = /\{\{\s*([a-zA-Z0-9_][\w.-]*)\s*\}\}/g;
    let m;
    let i = 0;
    while ((m = re.exec(body || '')) !== null) {
        const token = m[1];
        if (seen.has(token)) continue;
        seen.add(token);
        i += 1;
        out.push({ slot: String(i), token, named: !/^\d+$/.test(token) });
    }
    return out;
}

/**
 * Wire the panel.
 * @param {object} opts
 * @param {HTMLTextAreaElement} opts.body   the body textarea (#tpl-body)
 * @param {HTMLInputElement}    opts.hidden  hidden variable_map_json input
 * @param {HTMLElement}         opts.rows    container for the slot rows
 * @param {HTMLElement}         opts.empty   "no variables" hint element
 */
export function initVariableMap({ body, hidden, rows, empty }) {
    if (!body || !hidden || !rows) return;
    let attributes = [];

    function optionsHtml(selectedKey) {
        const head = `<option value="">${'— not mapped —'}</option>`;
        const opts = attributes
            .map((a) => {
                const key = a.key || '';
                const label = (a.name || key) + (key ? ` (${key})` : '');
                const sel = key === selectedKey ? ' selected' : '';
                return `<option value="${key.replace(/"/g, '&quot;')}"${sel}>${label.replace(/</g, '&lt;')}</option>`;
            })
            .join('');
        return head + opts;
    }

    function render() {
        const prev = readMap(hidden);
        const slots = slotsInBody(body.value);
        const validSlots = new Set(slots.map((s) => s.slot));

        // Rebuild the slot→key map fresh from the tokens in the body so it
        // stays in sync with first-appearance order. A NAMED token implies
        // its key (the token name) unless the operator re-pointed it; a
        // NUMERIC token keeps whatever it was previously mapped to.
        const map = {};
        slots.forEach(({ slot, token, named }) => {
            if (named) map[slot] = prev[slot] || token;
            else if (prev[slot]) map[slot] = prev[slot];
        });
        // Defensive: never carry mappings for slots no longer present.
        Object.keys(prev).forEach((slot) => {
            if (!validSlots.has(slot)) { /* dropped */ }
        });
        writeMap(hidden, map);

        if (empty) empty.classList.toggle('hidden', slots.length > 0);

        rows.innerHTML = slots
            .map(
                ({ slot, token, named }) => `
            <div class="flex items-center gap-2" data-var-row="${slot}">
                <span class="inline-flex items-center justify-center min-w-[34px] px-1.5 py-1 rounded-md bg-wa-bubble text-wa-deep text-[11px] font-mono font-semibold shrink-0">{{${token.replace(/</g, '&lt;')}}}</span>
                <svg viewBox="0 0 16 16" class="w-3 h-3 text-ink-500 shrink-0" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M3 8h9M9 5l3 3-3 3"/></svg>
                <select data-var-slot="${slot}" class="ctrl flex-1 px-[9px] py-[5px] border border-paper-200 rounded-lg bg-white text-[12px] text-ink-900 leading-[1.4] font-sans focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10">
                    ${optionsHtml(map[slot] || '')}
                </select>
            </div>`,
            )
            .join('');

        rows.querySelectorAll('[data-var-slot]').forEach((sel) => {
            sel.addEventListener('change', () => {
                const m = readMap(hidden);
                const slot = sel.getAttribute('data-var-slot');
                const val = sel.value;
                if (val) m[slot] = val;
                else delete m[slot];
                writeMap(hidden, m);
            });
        });
    }

    // Re-render when the body changes (typing, format(), insertVar(),
    // or the slash picker — they all dispatch an 'input' event).
    body.addEventListener('input', render);

    loadAttributes().then((list) => {
        attributes = Array.isArray(list) ? list : [];
        render();
    });
}

export default initVariableMap;
