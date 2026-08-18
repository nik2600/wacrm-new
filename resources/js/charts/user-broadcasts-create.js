// /broadcasts/create page-level JS module.
//
// Owns ALL interactive UI for the New Broadcast screen:
//   - template-preview bubble (full WhatsApp-style render: media header,
//     body with real sample values, footer, and CTA / quick-reply chips —
//     matching the template BUILDER's preview exactly)
//   - schedule-card toggle
//   - device-share live percentages
//   - contact search / select-all / selected counter
//
// MULTI-ENGINE NOTE: each sender row's checkbox value, data-device-id and
// share-weight input name are now the composite "engine:id" key (e.g.
// "waba:7"), NOT a bare device id — a workspace can run several engines at
// once and devices.id / wa_provider_configs.id overlap. This module never
// parses the id (it works purely off .device-row / .device-cb / .device-share
// DOM nodes and the weight VALUES), so the key is treated as an opaque string
// and the live-percentage maths is unchanged. Do NOT reintroduce a
// parseInt(deviceId) here — it would break the WABA/Twilio composite keys.
//
// Previously this logic lived in an inline <script> in the blade. It was
// moved here so the preview can import the shared `template-vars.js`
// (fillSamples + loadAttributeValues) and resolve positional {{1}} tokens
// via each template's variable_map slot map — the same approach
// user-wa-campaigns-create.js uses for its template-mode preview.
//
// DISPLAY ONLY: nothing here touches the send path. The form submits
// natively via method="POST" + action="{{ route('user.broadcasts.store') }}".
import { fillSamples, loadAttributeValues } from '../template-vars.js';

export default function init() {
    const $ = (s) => document.querySelector(s);
    const $$ = (s) => document.querySelectorAll(s);

    // ── Template preview ───────────────────────────────────────────────
    // Reads the selected <option>'s data-* attributes (seeded from the
    // WaTemplate row in the blade) and projects the FULL template into the
    // preview bubble: media header, sample-filled body, footer, buttons —
    // exactly what the template builder shows.
    const tplSel   = $('#templateSelect');
    const pvMedia   = $('#previewMedia');
    const pvHeader  = $('#previewHeader');
    const pvBody    = $('#previewBody');
    const pvFooter  = $('#previewFooter');
    const pvButtons = $('#previewButtons');
    const pvCat     = $('#previewCategory');
    const pvTime    = $('#previewTime');

    // Real workspace attribute demo values (keyed by attribute key) feed
    // the sample substitution. Resilient: failure resolves to {} and the
    // preview falls back to the built-in friendly stand-ins. Repaints once
    // the values land.
    let attrValues = {};
    loadAttributeValues().then((v) => { attrValues = v || {}; try { refreshPreview(); } catch (_) {} });

    // CTA glyphs mirror what WhatsApp renders for each button kind:
    // link (visit_website), phone (call_phone), copy (copy_code),
    // reply (quick_reply). Same SVGs the campaign builder uses.
    const btnGlyph = (type) => {
        if (type === 'visit_website') return '<svg viewBox="0 0 16 16" class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M6.5 9.5l3-3M5 8a2.5 2.5 0 0 1 0-3.5l1.5-1.5a2.5 2.5 0 0 1 3.5 3.5M11 8a2.5 2.5 0 0 1 0 3.5l-1.5 1.5a2.5 2.5 0 0 1-3.5-3.5"/></svg>';
        if (type === 'copy_code')     return '<svg viewBox="0 0 16 16" class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="1.6"><rect x="5.5" y="5.5" width="7" height="7" rx="1.2"/><path d="M3.5 10.5V4a.5.5 0 0 1 .5-.5h6.5"/></svg>';
        if (type === 'call_phone')    return '<svg viewBox="0 0 16 16" class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M4 3h2l1 3-1.5 1a7 7 0 0 0 3.5 3.5L11 12l3 1v2a1 1 0 0 1-1 1A11 11 0 0 1 2 4a1 1 0 0 1 1-1z"/></svg>';
        // quick_reply — a small return arrow
        return '<svg viewBox="0 0 16 16" class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M7 4L3 8l4 4M3 8h6a4 4 0 0 1 4 4v0"/></svg>';
    };

    const esc = (s) => String(s == null ? '' : s).replace(/[<>]/g, (c) => ({ '<': '&lt;', '>': '&gt;' }[c]));

    // Safe JSON parse for a data-* attribute (object/array → value, else fallback).
    const parseData = (raw, fallback) => {
        if (!raw) return fallback;
        try { const v = JSON.parse(raw); return v == null ? fallback : v; }
        catch (_) { return fallback; }
    };

    // Flatten the template's nested variable_map
    //   { header:[{num,key}], body:[{num,key}] }
    // into a flat { slot: key } map so fillSamples() can resolve positional
    // {{N}} → sample value. (Named {{name}} tokens resolve without this.)
    const buildSlotMap = (vmap) => {
        const slotMap = {};
        if (!vmap || typeof vmap !== 'object') return slotMap;
        ['header', 'body'].forEach((sec) => {
            const list = Array.isArray(vmap[sec]) ? vmap[sec] : [];
            list.forEach((e) => {
                if (e && e.num != null && e.key) slotMap[String(e.num)] = String(e.key);
            });
        });
        return slotMap;
    };

    function clearPreview() {
        if (pvMedia)  { pvMedia.classList.add('hidden'); pvMedia.innerHTML = ''; }
        if (pvHeader) { pvHeader.classList.add('hidden'); pvHeader.textContent = ''; }
        if (pvBody)   pvBody.textContent = 'Pick a template to preview…';
        if (pvFooter) { pvFooter.classList.add('hidden'); pvFooter.textContent = ''; }
        if (pvButtons){ pvButtons.classList.add('hidden'); pvButtons.innerHTML = ''; }
        if (pvCat)    pvCat.textContent = '—';
    }

    function refreshPreview() {
        const opt = tplSel?.selectedOptions?.[0];
        if (!opt || !opt.value) { clearPreview(); return; }

        const slotMap   = buildSlotMap(parseData(opt.dataset.variableMap, {}));
        const sampleOpts = { valueByKey: attrValues, slotMap };

        const h = opt.dataset.header || '';
        const b = opt.dataset.body   || '';
        const f = opt.dataset.footer || '';
        const c = opt.dataset.category || 'utility';
        const buttons = parseData(opt.dataset.buttons, []);
        const att     = (opt.dataset.attachmentType || '').toLowerCase();
        const attUrl  = opt.dataset.attachmentUrl || '';

        // Media header — image renders an <img>; video/doc render a chip.
        if (pvMedia) {
            if (att && att !== 'none') {
                if (att === 'image' && attUrl) {
                    pvMedia.innerHTML = `<img src="${esc(attUrl)}" alt="" class="block w-full max-h-[150px] object-cover">`;
                } else {
                    const label = att === 'video' ? 'Video' : att === 'document' ? 'Document' : (att.charAt(0).toUpperCase() + att.slice(1));
                    const glyph = att === 'video'
                        ? '<svg viewBox="0 0 16 16" class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.6"><rect x="2" y="4" width="9" height="8" rx="1.5"/><path d="M11 7l3-2v6l-3-2z"/></svg>'
                        : '<svg viewBox="0 0 16 16" class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M4 2h5l3 3v9H4z"/><path d="M9 2v3h3"/></svg>';
                    pvMedia.innerHTML = `<div class="bg-[#DFF1ED] text-wa-deep h-20 flex items-center justify-center gap-1.5 text-[10.5px] font-mono">${glyph}<span>${esc(label)} preview</span></div>`;
                }
                pvMedia.classList.remove('hidden');
            } else {
                pvMedia.classList.add('hidden'); pvMedia.innerHTML = '';
            }
        }

        // Header + body with sample values filled (positional {{1}} via the
        // slot map, named {{name}} directly).
        if (pvHeader) {
            const hv = fillSamples(h, sampleOpts);
            if (hv) { pvHeader.textContent = hv; pvHeader.classList.remove('hidden'); }
            else    { pvHeader.classList.add('hidden'); pvHeader.textContent = ''; }
        }
        if (pvBody) pvBody.textContent = b ? fillSamples(b, sampleOpts) : '(empty body)';

        if (pvFooter) {
            if (f) { pvFooter.textContent = f; pvFooter.classList.remove('hidden'); }
            else   { pvFooter.classList.add('hidden'); pvFooter.textContent = ''; }
        }

        // Button chips — one per template button, each with its kind glyph.
        if (pvButtons) {
            const list = Array.isArray(buttons) ? buttons.filter((x) => x && x.text) : [];
            if (list.length) {
                pvButtons.innerHTML = list.map((btn) => {
                    const g = btnGlyph(btn.type);
                    return `<div class="bg-paper-0 rounded-[7px] px-3 py-2 text-center text-[12px] font-semibold text-wa-deep shadow-[0_1px_1px_rgba(0,0,0,0.06)] inline-flex items-center justify-center gap-1.5">${g}<span>${esc(btn.text)}</span></div>`;
                }).join('');
                pvButtons.classList.remove('hidden');
            } else {
                pvButtons.classList.add('hidden'); pvButtons.innerHTML = '';
            }
        }

        if (pvCat) pvCat.textContent = c.charAt(0).toUpperCase() + c.slice(1);
    }
    tplSel?.addEventListener('change', refreshPreview);
    refreshPreview();

    // Tick-time on the preview bubble — purely cosmetic, mirrors
    // WhatsApp's right-side timestamp.
    if (pvTime) pvTime.textContent = new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });

    // ── Schedule toggle ────────────────────────────────────────────────
    const cards = $$('#scheduleCards .schedule-card');
    const schedFields = $('#scheduleFields');
    const schedLabel  = $('#scheduleLabel');
    function applyScheduleMode(mode) {
        cards.forEach(c => {
            const on = c.dataset.card === mode;
            c.classList.toggle('border-wa-deep', on);
            c.classList.toggle('bg-wa-bubble/50', on);
            c.classList.toggle('border-paper-200', !on);
            c.classList.toggle('bg-white', !on);
        });
        if (mode === 'later') {
            schedFields.classList.remove('hidden'); schedFields.classList.add('grid');
            schedLabel.textContent = 'Later';
        } else {
            schedFields.classList.add('hidden'); schedFields.classList.remove('grid');
            schedLabel.textContent = 'Now';
        }
    }
    cards.forEach(c => c.addEventListener('click', () => {
        const radio = c.querySelector('input[type="radio"]');
        if (radio) { radio.checked = true; applyScheduleMode(c.dataset.card); }
    }));
    applyScheduleMode('now');

    // ── Device picker — live share percentages ─────────────────────────
    const deviceList = $('#broadcastDeviceList');
    if (deviceList) {
        const recalcSharePcts = () => {
            const checkedRows = Array.from(deviceList.querySelectorAll('.device-cb:checked'))
                .map(cb => cb.closest('.device-row'));
            const totalWeight = checkedRows.reduce((sum, row) => {
                const v = parseFloat(row.querySelector('.device-share')?.value || '0');
                return sum + (isFinite(v) && v > 0 ? v : 0);
            }, 0);
            deviceList.querySelectorAll('.device-row').forEach(row => {
                const cb    = row.querySelector('.device-cb');
                const wrap  = row.querySelector('.device-share-wrap');
                const input = row.querySelector('.device-share');
                const pct   = row.querySelector('.device-share-pct');
                if (!cb.checked) {
                    wrap.classList.add('opacity-50', 'pointer-events-none');
                    pct.textContent = '—';
                    return;
                }
                wrap.classList.remove('opacity-50', 'pointer-events-none');
                const w = parseFloat(input.value || '0');
                if (totalWeight <= 0 || !(w > 0)) {
                    pct.textContent = '0%';
                } else {
                    pct.textContent = Math.round((w / totalWeight) * 100) + '%';
                }
            });
        };
        deviceList.addEventListener('change', (e) => {
            if (e.target.matches('.device-cb') || e.target.matches('.device-share')) recalcSharePcts();
        });
        deviceList.addEventListener('input', (e) => {
            if (e.target.matches('.device-share')) recalcSharePcts();
        });
        recalcSharePcts();
    }

    // ── Contacts — selected counter + select-all + search filter ───────
    const tbody     = $('#contactsTbody');
    const search    = $('#contactSearch');
    const selectAll = $('#selectAllContacts');
    const selSum    = $('#selectedSummary');
    const selCount  = $('#selectedCount');

    function updateSelectedCount() {
        const n = $$('input[name="contacts[]"]:checked').length;
        if (selCount) selCount.textContent = String(n);
        if (selSum)   selSum.textContent   = n + ' selected';
    }
    tbody?.addEventListener('change', (e) => {
        if (e.target.matches('input[name="contacts[]"]')) updateSelectedCount();
    });
    selectAll?.addEventListener('change', (e) => {
        $$('input[name="contacts[]"]').forEach(cb => {
            if (cb.closest('tr')?.classList.contains('hidden')) return;
            cb.checked = e.target.checked;
        });
        updateSelectedCount();
    });
    search?.addEventListener('input', () => {
        const q = (search.value || '').trim().toLowerCase();
        tbody.querySelectorAll('tr[data-search]').forEach(row => {
            row.classList.toggle('hidden', q !== '' && !(row.dataset.search || '').includes(q));
        });
    });
    updateSelectedCount();
}
