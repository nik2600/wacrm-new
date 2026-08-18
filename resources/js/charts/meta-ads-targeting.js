/*
 * Advanced Meta Ads targeting — shared by /meta-ads/create + /edit.
 *
 * Wires the collapsible "Advanced targeting" section: geo (city/region/
 * zip + radius), detailed targeting (interest/behavior/life-event),
 * custom+lookalike audiences, exclusions, languages, placements, budget/
 * bidding, and a reach estimate. Every picker is a remote Tom Select that
 * hits /meta-ads/targeting-search; selections are serialized into hidden
 * JSON inputs on submit so the controller can pack them into `targeting`.
 *
 * Defensive throughout — a missing element never throws.
 */
import TomSelect from 'tom-select';
import 'tom-select/dist/css/tom-select.css';

export default function initAdvancedTargeting() {
    const root = document.querySelector('[data-adv-targeting]');
    if (!root || root.__advInit) return;
    root.__advInit = true;

    const searchUrl   = root.getAttribute('data-search-url') || '';
    const audiencesUrl = root.getAttribute('data-audiences-url') || '';
    const estimateUrl = root.getAttribute('data-estimate-url') || '';
    const form = root.closest('form');
    const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

    // ---- collapse toggle -------------------------------------------------
    const body  = root.querySelector('[data-adv-body]');
    const caret = root.querySelector('[data-adv-caret]');
    root.querySelector('[data-adv-toggle]')?.addEventListener('click', () => {
        const open = body.classList.toggle('hidden') === false;
        if (caret) caret.style.transform = open ? 'rotate(180deg)' : '';
    });

    // ---- Advantage+ switch ----------------------------------------------
    const advSwitch = root.querySelector('[data-adv-advantage]');
    const advVal    = root.querySelector('[data-adv-advantage-val]');
    advSwitch?.addEventListener('change', () => { if (advVal) advVal.value = advSwitch.checked ? '1' : '0'; });

    // ---- helpers ---------------------------------------------------------
    const parsePreset = (el) => {
        try { return JSON.parse(el?.getAttribute('data-preset') || '[]') || []; } catch (_) { return []; }
    };

    // Remote Tom Select whose options carry {value,text,name}.
    const remotePicker = (el, buildParams) => {
        if (!el || el.tomselect) return el?.tomselect || null;
        return new TomSelect(el, {
            plugins: ['remove_button'],
            valueField: 'value',
            labelField: 'text',
            searchField: ['text'],
            maxOptions: 50,
            create: false,
            load(query, cb) {
                if (!query.length) return cb();
                const url = searchUrl + (searchUrl.includes('?') ? '&' : '?') + buildParams(query);
                fetch(url, { headers: { Accept: 'application/json' } })
                    .then((r) => r.json())
                    .then((j) => cb((j.results || []).map((x) => ({
                        value: String(x.key ?? x.id ?? ''),
                        text: x.label || x.name || String(x.key ?? x.id ?? ''),
                        name: x.name || '',
                    }))))
                    .catch(() => cb());
            },
        });
    };

    // Serialize a picker's selection to [{id,name}] and write to a hidden input.
    const packPairs = (ts, hiddenName) => {
        if (!ts || !form) return;
        const input = form.querySelector(`input[name="${hiddenName}"]`);
        if (!input) return;
        const out = ts.items.map((v) => {
            const o = ts.options[v] || {};
            return { id: String(v), name: o.name || o.text || '' };
        });
        input.value = JSON.stringify(out);
    };

    const registry = []; // { ts, kind, hidden }

    // ---- GEO pickers (city / region / zip) ------------------------------
    root.querySelectorAll('[data-geo-picker]').forEach((el) => {
        const kind = el.getAttribute('data-geo-picker'); // city|region|zip
        const ts = remotePicker(el, (q) => `kind=geo&types=${kind}&q=${encodeURIComponent(q)}`);
        // restore preset
        parsePreset(el).forEach((row) => {
            const key = String(row.key ?? row.value ?? '');
            if (!key) return;
            ts?.addOption({ value: key, text: row.label || key, name: row.label || key });
            ts?.addItem(key, true);
        });
        registry.push({ ts, kind: `geo:${kind}`, el });
    });

    const radiusEl = root.querySelector('[data-geo-radius]');
    const unitEl   = root.querySelector('[data-geo-unit]');
    const packGeo = () => {
        if (!form) return;
        const grab = (kind) => registry.find((r) => r.kind === `geo:${kind}`)?.ts;
        const cityTs = grab('city'), regionTs = grab('region'), zipTs = grab('zip');
        const radius = parseInt(radiusEl?.value || '0', 10);
        const unit   = unitEl?.value || 'kilometer';
        const cities = (cityTs?.items || []).map((k) => radius > 0 ? { key: k, radius, distance_unit: unit } : { key: k });
        const regions = (regionTs?.items || []).map((k) => ({ key: k }));
        const zips    = (zipTs?.items || []).map((k) => ({ key: k }));
        form.querySelector('input[name="geo_cities"]') && (form.querySelector('input[name="geo_cities"]').value = JSON.stringify(cities));
        form.querySelector('input[name="geo_regions"]') && (form.querySelector('input[name="geo_regions"]').value = JSON.stringify(regions));
        form.querySelector('input[name="geo_zips"]') && (form.querySelector('input[name="geo_zips"]').value = JSON.stringify(zips));
    };

    // ---- DETAILED TARGETING + EXCLUSIONS (interest/behavior/life_event) --
    root.querySelectorAll('[data-tt-picker]').forEach((el) => {
        const kind = el.getAttribute('data-tt-picker');      // interest|behavior|life_event
        const hidden = el.getAttribute('data-json');
        const ts = remotePicker(el, (q) => `kind=${kind}&q=${encodeURIComponent(q)}`);
        parsePreset(el).forEach((row) => {
            const id = String(row.id ?? row.value ?? '');
            if (!id) return;
            ts?.addOption({ value: id, text: row.name || id, name: row.name || '' });
            ts?.addItem(id, true);
        });
        registry.push({ ts, kind: 'pairs', hidden });
    });

    // ---- CUSTOM / LOOKALIKE AUDIENCES (loaded once, both include/exclude) -
    const audEls = root.querySelectorAll('[data-aud-picker]');
    if (audEls.length && audiencesUrl) {
        fetch(audiencesUrl, { headers: { Accept: 'application/json' } })
            .then((r) => r.json())
            .then((j) => {
                const opts = (j.results || []).map((x) => ({ value: String(x.id), text: x.label || x.name, name: x.name || '' }));
                audEls.forEach((el) => {
                    if (el.tomselect) return;
                    const hidden = el.getAttribute('data-json');
                    const ts = new TomSelect(el, { plugins: ['remove_button'], valueField: 'value', labelField: 'text', searchField: ['text'], options: opts, maxOptions: 200, create: false });
                    parsePreset(el).forEach((row) => {
                        const id = String(row.id ?? row.value ?? '');
                        if (!id) return;
                        if (!ts.options[id]) ts.addOption({ value: id, text: row.name || id, name: row.name || '' });
                        ts.addItem(id, true);
                    });
                    registry.push({ ts, kind: 'pairs', hidden });
                });
            })
            .catch(() => {});
    }

    // ---- LOCALES (multi-select name=locales[] — native submit) ----------
    root.querySelectorAll('[data-locale-picker]').forEach((el) => {
        const ts = remotePicker(el, (q) => `kind=locale&q=${encodeURIComponent(q)}`);
        parsePreset(el).forEach((row) => {
            const id = String(typeof row === 'object' ? (row.id ?? row.value ?? '') : row);
            if (!id) return;
            ts?.addOption({ value: id, text: row.name || id, name: row.name || '' });
            ts?.addItem(id, true);
        });
    });

    // ---- bid strategy → toggle cap amount -------------------------------
    const bidSel = root.querySelector('[data-bid-strategy]');
    const bidWrap = root.querySelector('[data-bid-amount-wrap]');
    bidSel?.addEventListener('change', () => {
        if (bidWrap) bidWrap.classList.toggle('hidden', bidSel.value === 'LOWEST_COST_WITHOUT_CAP');
    });

    // ---- reach estimate --------------------------------------------------
    const estBtn = root.querySelector('[data-estimate-btn]');
    const estOut = root.querySelector('[data-estimate-out]');
    estBtn?.addEventListener('click', () => {
        if (!estimateUrl || !estOut) return;
        estOut.textContent = '…';
        fetch(estimateUrl, { headers: { Accept: 'application/json' } })
            .then((r) => r.json())
            .then((j) => {
                if (j.ok && (j.lower || j.upper)) {
                    const fmt = (n) => new Intl.NumberFormat().format(n);
                    estOut.textContent = `${fmt(j.lower)} – ${fmt(j.upper)}`;
                } else {
                    estOut.textContent = j.ready === false ? 'Not ready yet' : (j.error || '—');
                }
            })
            .catch(() => { estOut.textContent = '—'; });
    });

    // ---- serialize all pickers on submit --------------------------------
    form?.addEventListener('submit', () => {
        packGeo();
        registry.filter((r) => r.kind === 'pairs').forEach((r) => packPairs(r.ts, r.hidden));
    });
}
