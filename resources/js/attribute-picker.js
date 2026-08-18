/*
 * Slash-popover attribute picker.
 *
 * Any <textarea> or <input> with `data-attr-input` opts in.
 * When the user types `/`, a floating popover opens listing every
 * attribute (system + custom) returned by GET /attributes/api/list.
 * Type to filter, click or press Enter to insert. The inserted token
 * is the attribute KEY in double braces — {{name}}, {{order_id}} — so
 * the operator reads MEANING while authoring instead of a bare number.
 *
 * Storage stays positional: the server-side normalizer
 * (TemplatesController::normalizePlaceholders + buildVariableMap*) and
 * the campaign store path convert these named tokens back to {{1}} {{2}}
 * and (re)build the slot→key variable_map on save, so the persisted body
 * and the Meta/WABA submit payload remain byte-identical to before.
 *
 * For back-compat the picked key is still recorded into a nearby hidden
 * <input data-attr-map="..."> keyed by the SLOT NUMBER the token will
 * occupy once normalized (first-appearance order), so existing forms /
 * the variable-mapping panel keep working even if the named token is
 * later renumbered.
 */

let attrCache = null;

async function loadAttributes() {
    if (attrCache) return attrCache;
    try {
        const res = await fetch('/attributes/api/list', {
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        });
        if (!res.ok) throw new Error('HTTP ' + res.status);
        const data = await res.json();
        attrCache = [
            ...(data.system || []).map((a) => ({ ...a, type: 'system' })),
            ...(data.custom || []).map((a) => ({ ...a, type: 'custom' })),
        ];
        return attrCache;
    } catch (e) {
        attrCache = [];
        return attrCache;
    }
}

// Distinct tokens in the body in FIRST-APPEARANCE order. Matches both
// named ({{order_id}}) and numeric ({{1}}) tokens — the same order the
// server-side normalizer renumbers them into, so slot N here lines up
// with {{N}} after save.
function tokensInOrder(text) {
    const out = [];
    const seen = new Set();
    const re = /\{\{\s*([a-zA-Z0-9_][\w.-]*)\s*\}\}/g;
    let m;
    while ((m = re.exec(text || '')) !== null) {
        const tok = m[1];
        if (!seen.has(tok)) { seen.add(tok); out.push(tok); }
    }
    return out;
}

// Rebuild the hidden slot→key map ({"1":"name","2":"order_id"}) from the
// NAMED tokens currently in the body, in first-appearance order. This
// keeps the variable-mapping panel + back-compat controllers fed without
// the picker having to guess a slot number at insert time. Numeric tokens
// keep whatever key they already carried (existing positional templates).
function rebuildMapping(input) {
    const wrap = input.closest('[data-attr-form]') || input.parentElement;
    const map  = wrap?.querySelector('[data-attr-map]');
    if (!map) return;
    let prev = {};
    try { prev = JSON.parse(map.value || '{}'); } catch (_) { prev = {}; }
    const obj = {};
    tokensInOrder(input.value).forEach((tok, i) => {
        const slot = String(i + 1);
        // Named token → the token IS the key. Numeric token → keep the
        // previously-recorded key for that slot if any, else the number.
        obj[slot] = /^\d+$/.test(tok) ? (prev[tok] || prev[slot] || tok) : tok;
    });
    map.value = JSON.stringify(obj);
}

function buildPopover() {
    const el = document.createElement('div');
    el.className = 'absolute z-[60] hidden bg-paper-0 border border-paper-200 rounded-xl shadow-[0_18px_40px_-15px_rgba(11,31,28,0.45)] overflow-hidden w-[300px]';
    el.id = 'attr-popover';
    el.style.maxHeight = '280px';
    el.innerHTML = `
        <div class="px-3 py-2 border-b border-paper-200 bg-paper-50 text-[10px] font-mono uppercase tracking-[0.16em] text-ink-500 flex items-center gap-2">
            <svg viewBox="0 0 16 16" class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M3 8h10"/></svg>
            Insert attribute
            <span class="ml-auto text-ink-700" data-attr-search-display></span>
        </div>
        <div class="overflow-y-auto" style="max-height: 232px;" data-attr-list></div>
    `;
    document.body.appendChild(el);
    return el;
}

let pop;
let popState = null; // { input, slashStart, query }

function getPopover() {
    if (!pop) pop = buildPopover();
    return pop;
}

function close() {
    if (!pop) return;
    pop.classList.add('hidden');
    popState = null;
}

async function renderList() {
    if (!popState) return;
    const list = pop.querySelector('[data-attr-list]');
    const display = pop.querySelector('[data-attr-search-display]');
    const all = await loadAttributes();
    const q = (popState.query || '').toLowerCase();
    const filtered = q
        ? all.filter((a) =>
              (a.key || '').toLowerCase().includes(q) ||
              (a.name || '').toLowerCase().includes(q) ||
              (a.description || '').toLowerCase().includes(q)
          )
        : all;
    display.textContent = q ? `/${q}` : '/';
    popState.filtered = filtered;
    popState.activeIdx = 0;
    if (!filtered.length) {
        list.innerHTML = '<div class="px-3 py-6 text-center text-[12px] text-ink-500">No attributes match. <a href="/attributes" class="text-wa-deep font-semibold hover:underline">Create one</a>.</div>';
        return;
    }
    list.innerHTML = filtered.map((a, i) => `
        <button type="button" data-attr-pick='${JSON.stringify({ key: a.key, name: a.name }).replace(/'/g, '&#39;')}' data-row-idx="${i}"
                class="attr-row w-full flex items-start gap-2 px-3 py-2 text-left hover:bg-paper-50 ${i === 0 ? 'bg-wa-mint' : ''}">
            <span class="w-6 h-6 rounded-md ${a.type === 'system' ? 'bg-wa-mint text-wa-deep' : 'bg-[#F3E9FF] text-[#5B3D8A]'} grid place-items-center text-[10px] font-semibold mt-0.5 shrink-0">${a.type === 'system' ? 'S' : 'C'}</span>
            <span class="flex-1 min-w-0">
                <span class="block text-[12.5px] font-medium text-ink-900 truncate">${a.name || a.key}</span>
                <span class="block text-[10.5px] text-ink-500 font-mono truncate">${a.key}${a.description ? ' / ' + a.description : ''}</span>
            </span>
        </button>
    `).join('');
    list.querySelectorAll('[data-attr-pick]').forEach((row) => {
        row.addEventListener('mousedown', (e) => {
            e.preventDefault();
            const pick = JSON.parse(row.dataset.attrPick.replace(/&#39;/g, "'"));
            insertPick(pick);
        });
        row.addEventListener('mouseenter', () => {
            popState.activeIdx = parseInt(row.dataset.rowIdx, 10);
            paintActiveRow();
        });
    });
    paintActiveRow();
}

function paintActiveRow() {
    if (!pop || !popState) return;
    pop.querySelectorAll('.attr-row').forEach((r) => {
        const i = parseInt(r.dataset.rowIdx, 10);
        r.classList.toggle('bg-wa-mint', i === popState.activeIdx);
    });
    const active = pop.querySelector(`.attr-row[data-row-idx="${popState.activeIdx}"]`);
    active?.scrollIntoView({ block: 'nearest' });
}

function insertPick(pick) {
    if (!popState) return;
    const { input, slashStart } = popState;
    // Insert the NAMED token ({{order_id}}) so the operator reads meaning.
    // The server normalizes named → positional {{1}} on save.
    const token = '{{' + pick.key + '}}';
    const before = input.value.substring(0, slashStart);
    const after  = input.value.substring(input.selectionEnd);
    input.value = before + token + after;
    const caret = (before + token).length;
    input.setSelectionRange(caret, caret);
    input.focus();
    rebuildMapping(input);
    input.dispatchEvent(new Event('input', { bubbles: true }));
    close();
}

/**
 * Measure the pixel position of `caretPos` inside `input` by mirroring
 * the field into a hidden div with the same font/box metrics, copying
 * text up to the caret, appending a span at the caret, and reading
 * that span's offset. Works for both <textarea> and <input>.
 */
function caretCoords(input, caretPos) {
    const isInput = input.tagName === 'INPUT';
    const mirror = document.createElement('div');
    const cs = getComputedStyle(input);
    const props = [
        'boxSizing','width','height','overflowX','overflowY','borderTopWidth','borderRightWidth','borderBottomWidth','borderLeftWidth',
        'paddingTop','paddingRight','paddingBottom','paddingLeft','fontStyle','fontVariant','fontWeight','fontStretch','fontSize',
        'fontSizeAdjust','lineHeight','fontFamily','textAlign','textTransform','textIndent','textDecoration','letterSpacing','wordSpacing',
        'tabSize','MozTabSize',
    ];
    mirror.style.position = 'absolute';
    mirror.style.visibility = 'hidden';
    mirror.style.whiteSpace = 'pre-wrap';
    mirror.style.wordWrap = 'break-word';
    if (isInput) mirror.style.whiteSpace = 'nowrap';
    for (const p of props) mirror.style[p] = cs[p];
    document.body.appendChild(mirror);

    const value = input.value.substring(0, caretPos);
    mirror.textContent = value;
    const span = document.createElement('span');
    span.textContent = '|';
    mirror.appendChild(span);

    const rect = input.getBoundingClientRect();
    const x = rect.left + span.offsetLeft - input.scrollLeft + window.scrollX;
    const y = rect.top  + span.offsetTop  - input.scrollTop  + window.scrollY;
    const lh = parseFloat(cs.lineHeight) || (parseFloat(cs.fontSize) * 1.4);

    document.body.removeChild(mirror);
    return { x, y, lineHeight: lh };
}

function positionPopover(input, caretPos) {
    const popW = 300, popH = 280;
    // Mobile / narrow viewport — anchor to the bottom of the screen
    // and stretch full-width minus a small inset, instead of trying to
    // float at the caret (the soft keyboard usually covers it).
    if (window.innerWidth < 640) {
        pop.style.left  = '8px';
        pop.style.right = '8px';
        pop.style.width = 'auto';
        pop.style.top   = (window.scrollY + window.innerHeight - popH - 12) + 'px';
        return;
    }
    pop.style.right = '';
    pop.style.width = popW + 'px';
    let { x, y, lineHeight } = caretCoords(input, caretPos);
    let top  = y + lineHeight + 4;
    let left = x;
    if (top + popH > window.scrollY + window.innerHeight - 8) {
        top = y - popH - 4;       // flip above when there's no room below
    }
    if (left + popW > window.scrollX + window.innerWidth - 8) {
        left = window.scrollX + window.innerWidth - popW - 8;
    }
    if (left < 8) left = 8;
    pop.style.top  = top + 'px';
    pop.style.left = left + 'px';
}

function onInput(e) {
    const input = e.target;
    if (!input.matches('[data-attr-input]')) return;
    const caret = input.selectionStart;
    const upToCaret = input.value.substring(0, caret);
    // Find the most recent `/` that isn't preceded by a non-space — we
    // only treat a slash as the trigger when it starts a token.
    const slashIdx = upToCaret.lastIndexOf('/');
    if (slashIdx === -1) return close();
    const before = slashIdx === 0 ? '' : upToCaret[slashIdx - 1];
    if (before && !/\s/.test(before)) return close();
    const query = upToCaret.substring(slashIdx + 1);
    if (/[\s\n]/.test(query)) return close();   // bail when the user types a space — they meant a real `/`
    if (query.length > 24) return close();      // bail on absurdly long queries
    popState = { input, slashStart: slashIdx, query };
    getPopover();
    pop.classList.remove('hidden');
    positionPopover(input, caret);
    renderList();
}

function onKeydown(e) {
    if (!popState) return;
    if (e.key === 'Escape') {
        e.preventDefault();
        close();
        return;
    }
    if (e.key === 'ArrowDown') {
        if (!popState.filtered?.length) return;
        e.preventDefault();
        popState.activeIdx = (popState.activeIdx + 1) % popState.filtered.length;
        paintActiveRow();
        return;
    }
    if (e.key === 'ArrowUp') {
        if (!popState.filtered?.length) return;
        e.preventDefault();
        popState.activeIdx = (popState.activeIdx - 1 + popState.filtered.length) % popState.filtered.length;
        paintActiveRow();
        return;
    }
    if (e.key === 'Enter' || e.key === 'Tab') {
        if (!popState.filtered?.length) return;
        e.preventDefault();
        const pick = popState.filtered[popState.activeIdx];
        if (pick) insertPick({ key: pick.key, name: pick.name });
    }
}

function init() {
    document.addEventListener('input', onInput, true);
    document.addEventListener('keydown', onKeydown, true);
    document.addEventListener('mousedown', (e) => {
        // Close when the click lands somewhere that isn't:
        //   - inside the popover (so the user can click a row)
        //   - the target input that opened the popover (typing more after `/`)
        if (!pop) return;
        if (pop.contains(e.target)) return;
        if (popState && e.target === popState.input) return;
        close();
    }, true);
    document.addEventListener('scroll', (e) => {
        if (!popState) return;
        // Scrolling INSIDE the attribute list (it has its own scrollbar) must
        // NOT close the popover — the user is browsing attributes. Only an
        // OUTER/page scroll detaches it from the input, so close only then.
        if (pop && pop.contains(e.target)) return;
        close();
    }, true);
    window.addEventListener('resize', () => { if (popState) close(); });
    document.addEventListener('blur', (e) => {
        if (e.target?.matches?.('[data-attr-input]')) {
            // Give a click on a popover row time to fire before we close.
            setTimeout(() => {
                if (!popState) return;
                if (document.activeElement && pop.contains(document.activeElement)) return;
                close();
            }, 120);
        }
    }, true);
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
} else {
    init();
}

export {};
