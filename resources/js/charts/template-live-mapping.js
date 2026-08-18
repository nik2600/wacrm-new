/**
 * Send-time template mapping panel.
 *
 * Renders one editable slot per template variable the moment a template is
 * picked, so an operator can change what gets sent WITHOUT editing the
 * template itself (which would change every other send using it).
 *
 * ONE implementation, four call sites (campaigns create/edit, broadcasts,
 * scheduled, chat composer) — deliberately not copy-pasted per surface.
 *
 * Contract with the server:
 *   - a <select data-tlm-template> whose <option>s carry data-send-meta
 *     (JSON from WaTemplate::sendMeta())
 *   - a container [data-tlm-panel] to render into
 *   - a hidden input [data-tlm-input] that receives the JSON posted as
 *     `template_overrides` and is read back by TemplateOverrideResolver
 *   - window.__tlmAttributes — the SendAttributes catalog
 *
 * Slot values are TOKEN STRINGS: a literal, an {{attribute}}, or a mix.
 * A blank slot is NOT an empty value — it means "fall back to the
 * template's own variable_map", which is what the resolver does.
 */

const esc = (s) =>
    String(s ?? '').replace(/[&<>"']/g, (c) => ({
        '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;',
    }[c]));

/** Translation helper — falls back to the literal when i18n isn't loaded. */
const t = (s) => (typeof window.t === 'function' ? window.t(s) : s);

/** WhatsApp's own per-type ceilings, shown up-front so a too-big file
 *  is caught before the upload rather than after it fails. */
const LIMITS = {
    image:    'JPG or PNG · up to 5 MB',
    video:    'MP4 · up to 16 MB',
    document: 'PDF, DOC or XLS · up to 100 MB',
    audio:    'MP3, AAC or OGG · up to 16 MB',
};
const MAX_BYTES = { image: 5e6, video: 16e6, document: 1e8, audio: 16e6 };

export default function initTemplateLiveMapping() {
    document.querySelectorAll('[data-tlm-root]').forEach(setupRoot);
}

function setupRoot(root) {
    if (root.dataset.tlmReady === '1') return;
    root.dataset.tlmReady = '1';

    const select = document.querySelector(root.dataset.tlmTemplate || '[data-tlm-template]');
    const panel  = root.querySelector('[data-tlm-panel]');
    const store  = root.querySelector('[data-tlm-input]');
    if (!select || !panel || !store) return;

    // Overrides already saved on this row (edit screens). Keyed so a
    // re-render of the same template restores what the operator typed.
    let state = safeParse(store.value) || {};

    const rerender = () => {
        state = mountPanel(panel, readMeta(select), state, (next) => {
            store.value = Object.keys(next).length ? JSON.stringify(next) : '';
        });
    };

    select.addEventListener('change', () => {
        // A different template has different slots — carrying the old
        // values across would silently map slot 1 of template A onto an
        // unrelated slot 1 of template B.
        state = {};
        store.value = '';
        rerender();
    });

    rerender();
}

/**
 * Render the mapping panel into `panel` for `meta`, wire it up, and report
 * every change through `onChange`.
 *
 * Form-based surfaces (campaigns / broadcasts / scheduled) reach this via
 * setupRoot and a hidden input. Surfaces with no form and no <select> —
 * the team-inbox and chat composers — call it directly with a meta object
 * they already hold. Both get the identical renderer, chips, media upload,
 * and preview; there is deliberately only one implementation of this panel
 * in the app.
 *
 * Returns the state actually in effect (the passed-in state, or {} when
 * there is no template to render).
 */
export function mountPanel(panel, meta, initial, onChange) {
    if (!panel) return {};
    let state = initial && typeof initial === 'object' ? initial : {};

    panel.innerHTML = meta ? renderPanel(meta, state, attributesList()) : '';
    panel.classList.toggle('hidden', !meta);

    if (meta) {
        wire(panel, meta, attributesList(), (next) => {
            state = next;
            onChange?.(next);
            paintPreview(panel, meta, next, attributesList());
        });
    }
    paintPreview(panel, meta, state, attributesList());
    return state;
}

/** The click-to-insert attribute catalog, published by the blade. */
function attributesList() {
    return Array.isArray(window.__tlmAttributes) ? window.__tlmAttributes : [];
}

/**
 * The picked template's shape.
 *
 * Prefers the stamped data-send-meta, but falls back to the window map so
 * this module never depends on whether the blade's inline stamper ran
 * first — module scripts and inline scripts race on DOMContentLoaded, and
 * a lost race would silently render no panel at all.
 */
function readMeta(select) {
    const opt = select.selectedOptions && select.selectedOptions[0];
    if (!opt || !opt.value) return null;
    return safeParse(opt.dataset.sendMeta) || (window.__tlmMeta || {})[opt.value] || null;
}

function safeParse(s) {
    if (!s) return null;
    try { return JSON.parse(s); } catch { return null; }
}

/* ------------------------------------------------------------------ */
/* Render                                                              */
/* ------------------------------------------------------------------ */

function renderPanel(meta, state, attributes) {
    const hasAnything =
        meta.header.slots > 0 ||
        meta.body.slots > 0 ||
        meta.footer.slots > 0 ||
        meta.media_editable ||
        meta.buttons.some((b) => b.editable);

    if (!meta.editable) {
        return notice(meta.locked_reason || t('This template can\'t be edited at send time.'));
    }
    if (!hasAnything) {
        return notice(t('This template has no variables, media, or button links to fill in — it sends exactly as written.'));
    }

    let html = '<div class="border border-paper-200 rounded-lg bg-paper-0 overflow-hidden">';
    html += `<div class="px-3.5 py-2.5 border-b border-paper-200 bg-paper-50 flex items-center gap-2">
        <span class="font-serif text-[15px] leading-none text-ink-900 flex-1">${esc(t('Fill in this send'))}</span>
        <span class="font-mono text-[10px] text-ink-500">${esc(t('applies to this send only'))}</span>
    </div>`;
    html += '<div class="p-3.5 space-y-4">';

    html += `<div class="text-[11.5px] text-ink-600 leading-relaxed">${esc(
        t('Values here override the template for this send only — the template itself is unchanged. Leave a field blank to keep what the template already does.')
    )}</div>`;

    // Twilio can still take body variables but not media/buttons.
    if (meta.locked_reason && meta.engine === 'twilio') {
        html += notice(meta.locked_reason);
    }

    // ---- HEADER ----
    if (meta.header.format === 'TEXT' && meta.header.slots > 0) {
        html += section(t('Header'), sectionIcon('doc'),
            slotRows('header', meta.header.slots, meta.header.defaults, state, meta.header.text, meta.header.tokens, attributes));
    } else if (meta.media_editable) {
        html += section(t('Header media'), sectionIcon('image'), mediaRow(meta, state));
    }

    // ---- BODY ----
    if (meta.body.slots > 0) {
        html += section(t('Message'), sectionIcon('doc'),
            slotRows('body', meta.body.slots, meta.body.defaults, state, meta.body.text, meta.body.tokens, attributes));
    }

    // ---- FOOTER ----
    if (meta.footer.slots > 0) {
        html += section(t('Footer'), sectionIcon('doc'),
            slotRows('footer', meta.footer.slots, [], state, meta.footer.text, meta.footer.tokens, attributes));
    }

    // ---- BUTTONS ----
    const editableBtns = meta.buttons.filter((b) => b.editable);
    if (meta.buttons_editable && editableBtns.length) {
        html += section(t('Button values'), sectionIcon('link'), editableBtns.map((b) => buttonRow(b, state)).join(''));
    }

    html += '</div>';

    // NO preview here on purpose. The page already owns a phone-frame LIVE
    // PREVIEW; rendering a second one inside this panel meant two previews
    // that could disagree — the same duplication that caused every bug in
    // this feature. This panel repaints the page's preview instead.

    html += '</div>';
    return html;
}

function notice(msg) {
    return `<div class="rounded-lg bg-accent-amber/15 border border-accent-amber/40 px-3 py-2 text-[12px] text-[#7B5A14]">${esc(msg)}</div>`;
}

function section(title, icon, inner) {
    return `<div>
        <div class="flex items-center gap-1.5 mb-2">
            ${icon}
            <span class="text-[11.5px] font-semibold text-ink-700">${esc(title)}</span>
        </div>
        <div class="space-y-2.5">${inner}</div>
    </div>`;
}

/**
 * One row per placeholder, labelled with the placeholder's OWN name.
 *
 * The old version showed "Variable 1" plus a scrap of surrounding text,
 * which told the operator nothing about what the field is or what it
 * does if left alone. Now each row states three things plainly:
 *   1. the placeholder's real name          — "Phone Number"
 *   2. what it auto-fills with when blank   — "Auto: +919812345678"
 *   3. that it's optional                   — placeholder text says so
 */
function slotRows(sectionKey, count, defaults, state, sourceText, tokens, attributes) {
    const byKey = {};
    attributes.forEach((a) => { byKey[a.key] = a; });

    let out = '';
    for (let i = 0; i < count; i++) {
        const saved = (state[sectionKey] || [])[i];
        const val = saved !== undefined && saved !== null ? saved : '';
        const tok = (tokens && tokens[i]) || null;

        // What happens if this field is left blank: the matching contact
        // attribute, else the template's own variable_map entry.
        const match = tok ? byKey[tok.key] : null;
        const fallback = (defaults && defaults[i]) || '';
        let autoLine;
        if (match) {
            autoLine = `${t('Auto-fills from')} <span class="font-medium text-ink-700">${esc(match.label)}</span>`
                + (match.sample ? ` — <span class="font-mono">${esc(match.sample)}</span>` : '');
        } else if (fallback) {
            autoLine = `${t('Auto-fills from')} <span class="font-mono">${esc(fallback)}</span>`;
        } else {
            autoLine = `<span class="text-accent-coral">${esc(t('No matching attribute — this will send empty unless you fill it in'))}</span>`;
        }

        const title = tok && tok.name ? tok.name : `${t('Variable')} ${i + 1}`;

        out += `<div class="rounded-lg border border-paper-200 p-2.5 bg-paper-0">
            <div class="flex items-baseline gap-2 mb-1 min-w-0">
                <span class="text-[12px] font-semibold text-ink-800 truncate">${esc(title)}</span>
                ${tok ? `<code class="text-[10px] font-mono text-ink-400 truncate shrink-0 max-w-[45%]">{{${esc(tok.name)}}}</code>` : ''}
            </div>
            <div class="text-[10.5px] text-ink-500 mb-1.5 break-words">${autoLine}</div>
            <div class="relative">
                <input type="text" autocomplete="off"
                    data-tlm-slot="${esc(sectionKey)}" data-tlm-index="${i}"
                    value="${esc(val)}"
                    placeholder="${esc(t('Leave blank to auto-fill, or type a value'))}"
                    class="w-full px-2.5 py-1.5 pr-8 border border-paper-200 rounded-lg bg-white text-[12.5px] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10">
                ${insertButton()}
            </div>
            ${chipRow(attributes, tok)}
        </div>`;
    }
    return out;
}

/**
 * Always-visible click-to-insert chips for the most-used attributes.
 *
 * The competitor hides its whole merge list behind typing "@", so an
 * operator who doesn't know the convention never discovers it. Showing
 * the common ones inline is the single biggest usability win here; the
 * "+" button still opens the full searchable list.
 */
function chipRow(attributes, tok) {
    // Rank by usefulness, not by catalog order. Taking the first five
    // Contact entries offered five near-identical NAME variants (Full /
    // First / Last / Middle / Title) and buried Email and Phone entirely —
    // so the only reachable chips were ones that are usually blank.
    const PRIORITY = ['first_name', 'name', 'phone', 'email', 'last_name', 'company_name', 'city'];
    const byKey = {};
    attributes.forEach((a) => { byKey[a.key] = a; });

    const picked = [];
    // The slot's own attribute always leads — it's the one that belongs here.
    if (tok && byKey[tok.key]) picked.push(byKey[tok.key]);
    PRIORITY.forEach((k) => {
        if (byKey[k] && !picked.some((p) => p.key === k)) picked.push(byKey[k]);
    });
    // Prefer attributes that actually hold a value for the sample contact —
    // a chip that inserts a token resolving to "" just looks broken.
    const common = picked
        .sort((a, b) => (b.sample ? 1 : 0) - (a.sample ? 1 : 0))
        .slice(0, 5);
    if (!common.length) return '';
    return `<div class="flex flex-wrap gap-1 mt-1.5">
        ${common.map((a) => `<button type="button" data-tlm-chip="${esc(a.token)}"
            title="${esc(a.sample || a.key)}"
            class="px-1.5 py-0.5 rounded border border-paper-200 bg-paper-50 hover:bg-wa-bubble/50 hover:border-wa-deep/30 text-[10.5px] text-ink-600">
            ${esc(a.label)}</button>`).join('')}
    </div>`;
}

function mediaRow(meta, state) {
    const cur = (state.header && state.header.media_url) || '';
    const fallback = meta.header.media_url || '';
    const shown = cur || fallback;
    const kind = meta.header.format.toLowerCase();
    return `<div class="space-y-2">
        ${shown && kind === 'image'
            ? `<img data-tlm-media-preview src="${esc(shown)}" alt="" class="max-h-32 rounded-lg border border-paper-200">`
            : `<div data-tlm-media-preview class="text-[11.5px] font-mono text-ink-500 break-all">${esc(shown || t('(template default)'))}</div>`}
        <div data-tlm-drop
            class="relative border-2 border-dashed border-paper-300 hover:border-wa-deep rounded-lg px-3 py-4 text-center cursor-pointer transition-colors">
            <div class="text-[12px] text-ink-700">
                <span class="font-semibold text-wa-deep">${esc(t('Choose a file'))}</span>
                ${esc(t('or drag it here'))}
            </div>
            <div class="text-[10.5px] text-ink-500 mt-0.5">${esc(LIMITS[kind] || '')}</div>
            <div data-tlm-upload-state class="text-[11px] text-ink-500 mt-1"></div>
            <div data-tlm-progress class="hidden mt-2 h-1 w-full bg-paper-200 rounded overflow-hidden">
                <div data-tlm-bar class="h-full bg-wa-deep transition-[width] duration-150" style="width:0%"></div>
            </div>
            <input type="file" data-tlm-file accept="${kind}/*" class="hidden">
        </div>
        <details class="mt-1">
            <summary class="text-[10.5px] text-ink-500 cursor-pointer">${esc(t('…or paste a link instead'))}</summary>
            <input type="url" data-tlm-media autocomplete="off"
                value="${esc(cur)}"
                placeholder="https://…"
                class="mt-1.5 w-full px-2.5 py-1.5 border border-paper-200 rounded-lg bg-white text-[12.5px] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10">
            <div class="text-[10.5px] text-ink-500 mt-1">${esc(
                t('Must be a public HTTPS link — WhatsApp fetches the file from its own servers.')
            )}</div>
        </details>
    </div>`;
}

function buttonRow(b, state) {
    const saved = ((state.buttons || []).find((x) => x.index === b.index) || {}).value;
    const val = saved !== undefined ? saved : '';
    const label = {
        url: t('Link'), copy_code: t('Code'), quick_reply: t('Reply payload'),
    }[b.sub_type] || t('Value');
    return `<div>
        <label class="block text-[11px] text-ink-600 mb-1">
            ${esc(b.label)} <span class="text-ink-400">· ${esc(label)}</span>
        </label>
        <div class="relative">
            <input type="text" autocomplete="off"
                data-tlm-button="${b.index}" data-tlm-subtype="${esc(b.sub_type)}"
                value="${esc(val)}"
                placeholder="${esc(b.value || t('Leave blank to keep the template value'))}"
                class="w-full px-2.5 py-1.5 pr-8 border border-paper-200 rounded-lg bg-white text-[12.5px] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10">
            ${insertButton()}
        </div>
    </div>`;
}

function insertButton() {
    return `<button type="button" data-tlm-insert
        title="${esc(t('Insert an attribute'))}"
        class="absolute right-1.5 top-1/2 -translate-y-1/2 p-1 rounded hover:bg-paper-50 text-ink-500">
        <svg width="14" height="14" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.6">
            <path d="M8 3v10M3 8h10" stroke-linecap="round"/>
        </svg>
    </button>`;
}

function sectionIcon(kind) {
    const paths = {
        doc: '<path d="M4 2h5l3 3v9H4z"/><path d="M9 2v3h3"/>',
        image: '<rect x="2" y="3" width="12" height="10" rx="1"/><circle cx="6" cy="7" r="1"/><path d="M2 11l3.5-3 3 2.5L11 8l3 3"/>',
        link: '<path d="M7 9a3 3 0 004.2 0l2-2a3 3 0 00-4.2-4.2l-1 1"/><path d="M9 7a3 3 0 00-4.2 0l-2 2A3 3 0 007 13.2l1-1"/>',
    };
    return `<svg width="14" height="14" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.4" class="text-wa-deep">${paths[kind] || paths.doc}</svg>`;
}

/* ------------------------------------------------------------------ */
/* Behaviour                                                           */
/* ------------------------------------------------------------------ */

function wire(panel, meta, attributes, onChange) {
    // Detach the previous render's handlers first.
    //
    // mountPanel() replaces panel.innerHTML and calls wire() again on EVERY
    // template change, but `panel` itself survives that swap — so every listener
    // below stacked. After picking a template twice, one click ran the handler
    // twice, which for the drop zone meant calling .click() on the hidden file
    // input repeatedly. Browsers suppress a second file dialog opened from the
    // same gesture, so "Choose a file" silently stopped working while
    // drag-and-drop (a different, idempotent handler) kept working. Chips had
    // the same fault, inserting their token once per stacked listener.
    if (panel.__tlmHandlers) {
        panel.__tlmHandlers.forEach(([type, fn]) => panel.removeEventListener(type, fn));
    }
    panel.__tlmHandlers = [];
    const on = (type, fn) => {
        panel.addEventListener(type, fn);
        panel.__tlmHandlers.push([type, fn]);
    };

    const collect = () => {
        const out = {};

        ['header', 'body', 'footer'].forEach((sec) => {
            const inputs = panel.querySelectorAll(`[data-tlm-slot="${sec}"]`);
            if (!inputs.length) return;
            const vals = Array.from(inputs).map((i) => i.value);
            // All-blank means "no override" — don't persist a row of empty
            // strings, or the resolver would treat the section as touched.
            if (vals.join('') === '') return;
            if (sec === 'header') out.header = { mode: 'text', text: vals[0] || '' };
            else out[sec] = vals;
        });

        const media = panel.querySelector('[data-tlm-media]');
        if (media && media.value.trim() !== '') {
            out.header = { mode: 'media', media_url: media.value.trim() };
        }

        const btns = Array.from(panel.querySelectorAll('[data-tlm-button]'))
            .filter((i) => i.value.trim() !== '')
            .map((i) => ({
                index: Number(i.dataset.tlmButton),
                sub_type: i.dataset.tlmSubtype,
                value: i.value.trim(),
            }));
        if (btns.length) out.buttons = btns;

        onChange(out);
    };

    on('input', (e) => {
        if (e.target.matches('[data-tlm-slot],[data-tlm-button],[data-tlm-media]')) {
            if (e.target.matches('[data-tlm-media]')) refreshMediaPreview(panel, meta);
            collect();
        }
    });

    // Upload the picked file, then drop the returned public URL into the
    // same field the send already reads — WhatsApp fetches media by URL,
    // so hosting it is our job, not the operator's.
    // Drag-and-drop onto the zone. `dragover` must be prevented or the
    // browser navigates away to the dropped file instead of handing it over.
    on('dragover', (e) => {
        const zone = e.target.closest('[data-tlm-drop]');
        if (!zone) return;
        e.preventDefault();
        zone.classList.add('border-wa-deep', 'bg-wa-bubble/20');
    });
    on('dragleave', (e) => {
        const zone = e.target.closest('[data-tlm-drop]');
        if (zone) zone.classList.remove('border-wa-deep', 'bg-wa-bubble/20');
    });
    on('drop', (e) => {
        const zone = e.target.closest('[data-tlm-drop]');
        if (!zone) return;
        e.preventDefault();
        zone.classList.remove('border-wa-deep', 'bg-wa-bubble/20');
        const f = e.dataTransfer && e.dataTransfer.files && e.dataTransfer.files[0];
        if (f) uploadFile(f, panel, meta, collect);
    });

    on('change', (e) => {
        const input = e.target.closest('[data-tlm-file]');
        if (!input || !input.files || !input.files[0]) return;
        uploadFile(input.files[0], panel, meta, collect);
    });

    // Click interactions. This block previously lived INSIDE uploadFile(),
    // so it only attached AFTER a file had already been uploaded — meaning
    // the very click that should open the file picker was never wired, and
    // "Choose a file" did nothing. It belongs here in wire(), where it also
    // has `attributes` in scope for the insert picker.
    on('click', (e) => {
        // Inline chip — one click inserts the attribute, no menu to find.
        const chip = e.target.closest('[data-tlm-chip]');
        if (chip) {
            e.preventDefault();
            const row = chip.closest('div.rounded-lg');
            const input = row && row.querySelector('input[data-tlm-slot]');
            insertAtCursor(input, chip.dataset.tlmChip);
            collect();
            return;
        }

        // Click anywhere on the drop-zone opens the file picker. Uploading
        // from the operator's computer, rather than demanding they host the
        // image somewhere and paste a URL.
        const zone = e.target.closest('[data-tlm-drop]');
        if (zone && !e.target.closest('input')) {
            e.preventDefault();
            const f = zone.querySelector('[data-tlm-file]');
            if (f) f.click();
            return;
        }

        const btn = e.target.closest('[data-tlm-insert]');
        if (!btn) return;
        e.preventDefault();
        const input = btn.parentElement.querySelector('input');
        openPicker(btn, attributes, (token) => {
            insertAtCursor(input, token);
            collect();
        });
    });
}

/**
 * Upload one file and drop the returned public URL into the field the send
 * reads. XHR rather than fetch purely because fetch cannot report upload
 * progress — a 16 MB video with no feedback looks like a hung page.
 */
function uploadFile(file, panel, meta, collect) {
    {
        const state = panel.querySelector('[data-tlm-upload-state]');
        const urlField = panel.querySelector('[data-tlm-media]');
        const kind = (meta.header.format || 'IMAGE').toLowerCase();
        const say = (msg, bad) => {
            if (!state) return;
            state.textContent = msg;
            state.className = 'text-[11px] ' + (bad ? 'text-accent-coral' : 'text-ink-500');
        };

        const type = ['image', 'video', 'audio', 'document'].includes(kind) ? kind : 'document';

        // Catch an oversized file here — the server would reject it anyway,
        // but only after the operator waited through the whole upload.
        const cap = MAX_BYTES[type];
        if (cap && file.size > cap) {
            say(`${file.name} — ${t('too large')}. ${LIMITS[type] || ''}`, true);
            return;
        }

        const bar  = panel.querySelector('[data-tlm-bar]');
        const wrap = panel.querySelector('[data-tlm-progress]');
        if (wrap) wrap.classList.remove('hidden');
        if (bar) bar.style.width = '0%';
        say(t('Uploading…') + ' ' + file.name);

        const fd = new FormData();
        fd.append('file', file);
        fd.append('type', type);

        const csrf = document.querySelector('meta[name="csrf-token"]');
        const xhr  = new XMLHttpRequest();
        xhr.open('POST', '/api/upload-media', true);
        xhr.withCredentials = true;
        if (csrf) xhr.setRequestHeader('X-CSRF-TOKEN', csrf.content);

        xhr.upload.onprogress = (ev) => {
            if (!ev.lengthComputable || !bar) return;
            bar.style.width = Math.round((ev.loaded / ev.total) * 100) + '%';
        };
        xhr.onload = () => {
            if (wrap) wrap.classList.add('hidden');
            let j = null;
            try { j = JSON.parse(xhr.responseText); } catch { /* non-JSON error page */ }
            const url = j && j.success && j.data && j.data.url;
            if (!url) {
                // Surface the server's reason rather than a silent no-op.
                const err = (j && j.errors) ? Object.values(j.errors).flat()[0]
                    : `${t('Upload failed')} (HTTP ${xhr.status})`;
                say(err, true);
                return;
            }
            if (urlField) urlField.value = url;
            say(file.name);
            refreshMediaPreview(panel, meta);
            collect();
        };
        xhr.onerror = () => {
            if (wrap) wrap.classList.add('hidden');
            say(t('Upload failed — check your connection'), true);
        };
        xhr.send(fd);
    }
}

function refreshMediaPreview(panel, meta) {
    const input = panel.querySelector('[data-tlm-media]');
    const prev = panel.querySelector('[data-tlm-media-preview]');
    if (!input || !prev) return;
    const url = input.value.trim() || meta.header.media_url || '';
    if (prev.tagName === 'IMG') prev.src = url;
    else prev.textContent = url || '(template default)';
}

/** Click-to-insert attribute list — the thing the competitor's `@`-only picker lacks. */
function openPicker(anchor, attributes, pick) {
    document.querySelectorAll('[data-tlm-pop]').forEach((n) => n.remove());
    if (!attributes.length) return;

    const pop = document.createElement('div');
    pop.setAttribute('data-tlm-pop', '');
    pop.className =
        'absolute z-50 mt-1 right-0 w-72 max-h-72 overflow-y-auto bg-white border border-paper-200 rounded-lg shadow-lg py-1';

    const groups = {};
    attributes.forEach((a) => { (groups[a.group] = groups[a.group] || []).push(a); });

    let html = `<div class="px-2.5 py-1.5 sticky top-0 bg-white border-b border-paper-100">
        <input data-tlm-search type="text" placeholder="Search attributes"
            class="w-full px-2 py-1 border border-paper-200 rounded text-[12px] focus:outline-none focus:border-wa-deep">
    </div>`;
    Object.keys(groups).forEach((g) => {
        html += `<div class="px-2.5 pt-2 pb-1 text-[10px] font-mono uppercase tracking-wide text-ink-400">${esc(g)}</div>`;
        groups[g].forEach((a) => {
            html += `<button type="button" data-token="${esc(a.token)}"
                data-hay="${esc((a.label + ' ' + a.key).toLowerCase())}"
                class="w-full text-left px-2.5 py-1.5 hover:bg-paper-50 flex items-baseline gap-2">
                <span class="text-[12px] text-ink-800 flex-1 truncate">${esc(a.label)}</span>
                <span class="text-[10px] font-mono text-ink-400 truncate max-w-[7rem]">${esc(a.sample || a.key)}</span>
            </button>`;
        });
    });
    pop.innerHTML = html;

    anchor.parentElement.style.position = 'relative';
    anchor.parentElement.appendChild(pop);

    const search = pop.querySelector('[data-tlm-search]');
    search && search.focus();
    search && search.addEventListener('input', () => {
        const q = search.value.toLowerCase();
        pop.querySelectorAll('[data-token]').forEach((b) => {
            b.classList.toggle('hidden', q !== '' && !b.dataset.hay.includes(q));
        });
    });

    pop.addEventListener('click', (e) => {
        const b = e.target.closest('[data-token]');
        if (!b) return;
        e.preventDefault();
        pick(b.dataset.token);
        pop.remove();
    });

    setTimeout(() => {
        const close = (e) => {
            if (!pop.contains(e.target)) { pop.remove(); document.removeEventListener('click', close); }
        };
        document.addEventListener('click', close);
    }, 0);
}

function insertAtCursor(input, text) {
    if (!input) return;
    const s = input.selectionStart ?? input.value.length;
    const e = input.selectionEnd ?? input.value.length;
    input.value = input.value.slice(0, s) + text + input.value.slice(e);
    input.focus();
    input.setSelectionRange(s + text.length, s + text.length);
}

/* ------------------------------------------------------------------ */
/* Live preview                                                        */
/* ------------------------------------------------------------------ */

/**
 * Substitute against the catalog's sample values so the operator sees a
 * realistic result. This is a PREVIEW ONLY — the server re-resolves per
 * recipient at send time via TemplateOverrideResolver, so an unknown
 * token renders the same way here as it will there: empty.
 */
function paintPreview(panel, meta, state, attributes) {
    // The page owns the preview. Nudge it to repaint so typed overrides show
    // up in the phone frame — one preview, one source of truth.
    document.dispatchEvent(new CustomEvent('tlm:changed'));

    const el = panel.querySelector('[data-tlm-preview]');
    if (!el || !meta) return;

    // Same normalisation the server uses, so "{{Phone Number}}" previews
    // exactly as it will send. Mirrors TemplateOverrideResolver::normalizeKey.
    const ALIASES = {
        phone_number: 'phone', phone_no: 'phone', number: 'phone',
        contact_number: 'phone', whatsapp_number: 'phone',
        mobile_number: 'mobile', mobile_no: 'mobile',
        full_name: 'name', customer_name: 'name', contact_name: 'name',
        first: 'first_name', last: 'last_name', surname: 'last_name',
        mail: 'email', email_address: 'email', e_mail: 'email',
    };
    const normKey = (raw) => {
        let k = String(raw || '').toLowerCase().trim()
            .replace(/[\s\-.]+/g, '_')
            .replace(/[^a-z0-9_]/g, '')
            .replace(/^_+|_+$/g, '');
        return ALIASES[k] || k;
    };

    const samples = {};
    attributes.forEach((a) => { samples[a.key] = a.sample || ''; });

    // TOKEN_RE equivalent — accepts spaces and capitals.
    const TOKEN = /\{\{\s*([^{}]+?)\s*\}\}/g;
    const render = (s) =>
        String(s ?? '').replace(TOKEN, (_, k) => samples[normKey(k)] ?? samples[String(k).trim()] ?? '');

    const fill = (text, vals, defaults) => {
        let i = 0;
        return String(text ?? '').replace(TOKEN, (whole, name) => {
            const typed = vals && vals[i] !== undefined && vals[i] !== '' ? vals[i] : null;
            i++;
            if (typed !== null) return render(typed);
            // Blank field → what the send will actually auto-fill with.
            const auto = samples[normKey(name)];
            if (auto !== undefined && auto !== '') return auto;
            const dflt = defaults && defaults[i - 1];
            return dflt ? render(dflt) : '';
        });
    };

    // Same cleanup the SEND path applies after an empty substitution.
    // Without it the preview shows "Hi ," while the real message says
    // "Hi there," — preview that lies is worse than no preview.
    const tidy = (s) => String(s ?? '')
        .replace(/[ \t]{2,}/g, ' ')
        .replace(/\s+([,.!?;:])/g, '$1')
        .replace(/([,;:])\s*([,.!?;:])/g, '$2')
        .trim();

    const lines = [];
    if (meta.header.format === 'TEXT' && meta.header.text) {
        const hv = state.header && state.header.mode === 'text' ? [state.header.text] : [];
        lines.push(tidy(fill(meta.header.text, hv, meta.header.defaults)));
    }
    if (meta.body.text) lines.push(tidy(fill(meta.body.text, state.body, meta.body.defaults)));
    if (meta.footer.text) lines.push(tidy(fill(meta.footer.text, state.footer, [])));

    el.textContent = lines.filter(Boolean).join('\n\n') || '—';

    // Buttons — the preview dropped them entirely, so a template with a CTA
    // looked like a plain text message. Uses the SAME markup + classes as the
    // template editor's live preview (`.pp-btn`) so the two panes agree.
    const holder = el.parentElement;
    let btnWrap = holder.querySelector('[data-tlm-preview-btns]');
    const btns = Array.isArray(meta.buttons) ? meta.buttons : [];
    if (!btns.length) { if (btnWrap) btnWrap.remove(); return; }

    if (!btnWrap) {
        btnWrap = document.createElement('div');
        btnWrap.setAttribute('data-tlm-preview-btns', '');
        btnWrap.className = 'mt-2 space-y-1';
        holder.appendChild(btnWrap);
    }
    btnWrap.innerHTML = '';
    btns.forEach((b) => {
        // Show the value the operator actually typed for this send, else the
        // template's own — the label is what WhatsApp renders either way.
        const ovr = (state.buttons || []).find((x) => x.index === b.index);
        const div = document.createElement('div');
        div.className = 'pp-btn bg-paper-0 rounded-[7px] px-3 py-2 text-center text-[12px] font-semibold text-wa-deep shadow-[0_1px_1px_rgba(0,0,0,0.06)]';
        div.textContent = b.label || 'Button';
        div.title = render((ovr && ovr.value) || b.value || '');
        btnWrap.appendChild(div);
    });
}
