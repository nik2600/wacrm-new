// Voice Agent wizard — front-end.
//
// 5 steps, card-pickers + sliders + chip inputs, live preview sidebar.
// State is canonical here; final save POSTs the whole snapshot to
// /ai-assistants/api/save. Exported default function is what the page
// loader at app.js calls when data-page="user-ai-assistants-wizard".

export default function init() {
    const root = document.querySelector('[data-wizard-state]');
    if (!root) return;

    const initial = (window.WA_AI_ASSISTANT || {});
    const TOTAL_STEPS = 5;
    const LANGS_AVAILABLE = [
        ['en', 'English'], ['hi', 'Hindi'], ['es', 'Spanish'], ['fr', 'French'],
        ['de', 'German'],  ['ar', 'Arabic'], ['pt', 'Portuguese'], ['id', 'Indonesian'],
    ];

    const state = {
        id:                  initial.id || null,
        name:                initial.name || '',
        persona:             initial.persona || 'support',
        languages:           Array.isArray(initial.languages) && initial.languages.length ? initial.languages.slice() : ['en'],
        greeting_variations: Array.isArray(initial.greeting_variations) && initial.greeting_variations.length
            ? initial.greeting_variations.slice() : ['Hi! How can I help you today?'],
        status:              initial.status || 'draft',
        is_active:           initial.is_active !== false,
        ai_provider:         initial.ai_provider || 'gemini',
        ai_model:            initial.ai_model || 'gemini-2.5-flash-lite',
        ai_api_key:          '',
        ai_system_prompt:    initial.ai_system_prompt || '',
        knowledge_base_url:  initial.knowledge_base_url || '',
        natural_conciseness: initial.natural_conciseness !== false,
        personality:         Object.assign({ warmth: 60, formality: 50, pace: 50 }, initial.personality || {}),
        voice_provider:      initial.voice_provider || 'elevenlabs',
        voice_api_key:       '',
        voice_id:            initial.voice_id || '',
        stt_provider:        initial.stt_provider || 'elevenlabs',
        noise_suppression:   initial.noise_suppression !== false,
        record_agent:        initial.record_agent !== false,
        record_user:         initial.record_user !== false,
        auto_logging:        initial.auto_logging !== false,
        voicemail_behavior:  initial.voicemail_behavior || 'leave_message',
        human_handoff_team:  initial.human_handoff_team || '',
        exit_keywords:       Array.isArray(initial.exit_keywords) ? initial.exit_keywords.slice() : ['bye','goodbye'],
        last_greeting:       initial.last_greeting || 'Thanks for calling — have a great day!',
        tools:               Array.isArray(initial.tools) ? initial.tools.slice() : [],
        // Per-number routing (Routing step). Empty = catch-all (any number).
        device_ids:          Array.isArray(initial.device_ids) ? initial.device_ids.map(Number) : [],
    };

    // ── per-number routing checkboxes (data-wf-device) ───────────────
    function wireDevices() {
        document.querySelectorAll('[data-wf-device]').forEach(cb => {
            const id = parseInt(cb.value, 10);
            cb.checked = state.device_ids.includes(id);
            cb.addEventListener('change', () => {
                if (cb.checked) { if (!state.device_ids.includes(id)) state.device_ids.push(id); }
                else state.device_ids = state.device_ids.filter(x => x !== id);
            });
        });
    }
    wireDevices();

    // Provider → default model mapping when operator clicks a different brain.
    // Models kept in sync with AdminAiKeyController allow-list and the wizard
    // hint text — stale "claude-3-5-sonnet-latest" pinned operators to a 2024
    // model after Anthropic's 4.x cutover.
    const PROVIDER_DEFAULTS = {
        gemini:    'gemini-2.5-flash-lite',
        openai:    'gpt-4o-mini',
        anthropic: 'claude-haiku-4-5-20251001',
    };

    if (initial.has_ai_key)    document.querySelector('[data-ai-saved]')?.classList.remove('hidden');
    if (initial.has_voice_key) document.querySelector('[data-voice-saved]')?.classList.remove('hidden');

    let stepIndex = 1;

    // ── flat field bindings (text/textarea/select/checkbox) ───────
    function bindFlat() {
        document.querySelectorAll('[data-wf]').forEach(el => {
            const key = el.dataset.wf;
            const evt = (el.tagName === 'SELECT' || el.type === 'checkbox') ? 'change' : 'input';
            // Initial value
            if (key === 'status_toggle') el.checked = state.status === 'live';
            else if (el.type === 'checkbox') el.checked = !!state[key];
            else el.value = state[key] ?? '';
            el.addEventListener(evt, () => {
                if (key === 'status_toggle') { state.status = el.checked ? 'live' : 'draft'; renderStatusToggle(); return; }
                state[key] = el.type === 'checkbox' ? el.checked : el.value;
                renderPreview();
            });
        });
    }

    // ── stepper visual ────────────────────────────────────────────
    function gotoStep(n) {
        stepIndex = Math.max(1, Math.min(TOTAL_STEPS, n));
        document.querySelectorAll('.step-pane').forEach(p => p.classList.add('hidden'));
        document.querySelector(`[data-step="${stepIndex}"]`)?.classList.remove('hidden');
        document.querySelectorAll('#stepper .step-node').forEach(node => {
            const i = parseInt(node.dataset.n, 10);
            const dot = node.querySelector('.dot');
            const lab = node.querySelector('.lab');
            const bar = node.querySelector('.bar');
            const done = i < stepIndex, cur = i === stepIndex;
            dot.classList.toggle('bg-wa-deep', done || cur);
            dot.classList.toggle('text-paper-0', done || cur);
            dot.classList.toggle('border-wa-deep', done || cur);
            dot.classList.toggle('ring-4', cur);
            dot.classList.toggle('ring-wa-deep/10', cur);
            dot.classList.toggle('text-ink-500', !(done || cur));
            dot.classList.toggle('border-paper-200', !(done || cur));
            lab.classList.toggle('text-wa-deep', done || cur);
            lab.classList.toggle('font-semibold', cur);
            lab.classList.toggle('text-ink-500', !(done || cur));
            if (bar) {
                bar.classList.toggle('bg-wa-deep', done);
                bar.classList.toggle('bg-paper-200', !done);
            }
            if (done) dot.innerHTML = '<svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M3 8l3 3 7-7"/></svg>';
            else dot.textContent = String(i);
        });
        document.querySelector('[data-prev]').disabled = stepIndex === 1;
        document.querySelector('[data-next-label]').textContent = stepIndex === TOTAL_STEPS ? 'Save voice agent' : 'Next step';
        const err = document.querySelector('[data-wizard-error]');
        if (err) { err.classList.add('hidden'); err.textContent = ''; }
    }
    // ── Per-step validation gate ─────────────────────────────────────
    // Block forward navigation (Next button OR a step-node jump) until
    // the current step's required fields are filled. Backward moves are
    // always allowed. validateStep() returns {ok, msg, el} so the caller
    // can toast + highlight the offending field. Required fields mirror
    // the blade: step 1 carries the only red * (Agent name) plus the
    // mandatory greeting control; the model id is provider-essential.
    const aiToast = (m, k = 'error') => (window.toast ? window.toast(m, k) : null);
    function flashInvalid(el) {
        if (!el) return;
        el.classList.add('ring-2', 'ring-accent-coral/60', 'border-accent-coral');
        try { el.focus({ preventScroll: false }); } catch (_) {}
        el.scrollIntoView({ behavior: 'smooth', block: 'center' });
        setTimeout(() => el.classList.remove('ring-2', 'ring-accent-coral/60', 'border-accent-coral'), 2600);
    }
    function validateStep(n) {
        if (n === 1) {
            if (!state.name.trim()) {
                return { ok: false, msg: 'Enter an agent name.', el: document.querySelector('[data-wf="name"]') };
            }
            if (state.greeting_variations.every(g => !(g || '').trim())) {
                return { ok: false, msg: 'Add at least one greeting.', el: document.querySelector('[data-greetings] textarea') };
            }
        }
        if (n === 2) {
            if (!(state.ai_model || '').trim()) {
                return { ok: false, msg: 'Enter a model id.', el: document.querySelector('[data-wf="ai_model"]') };
            }
        }
        if (n === 3) {
            // Skills are optional, but any card the operator started must be
            // complete (name + endpoint) — the save filter silently drops
            // half-filled tools otherwise, which is confusing.
            const bad = state.tools.findIndex(t => {
                const hasName = (t.function_name || '').trim();
                const hasUrl  = (t.http_url || '').trim();
                return (hasName || hasUrl) && !(hasName && hasUrl);
            });
            if (bad !== -1) {
                const cards = document.querySelectorAll('[data-tools-list] > div');
                return { ok: false, msg: 'Give each skill a name and an endpoint.', el: cards[bad]?.querySelector('[data-tk="function_name"]') };
            }
        }
        return { ok: true };
    }
    // Guard a forward move; returns true if it's allowed to proceed.
    function gateForward(from) {
        const v = validateStep(from);
        if (!v.ok) { aiToast(v.msg, 'error'); flashInvalid(v.el); }
        return v.ok;
    }

    document.querySelectorAll('#stepper .step-node').forEach(node => {
        node.addEventListener('click', () => {
            const target = parseInt(node.dataset.n, 10);
            // Free to jump backward; forward jumps must pass the current step.
            if (target <= stepIndex || gateForward(stepIndex)) gotoStep(target);
        });
    });
    document.querySelector('[data-prev]')?.addEventListener('click', () => gotoStep(stepIndex - 1));
    document.querySelector('[data-next]')?.addEventListener('click', () => {
        if (!gateForward(stepIndex)) return;
        if (stepIndex < TOTAL_STEPS) gotoStep(stepIndex + 1);
        else save();
    });
    document.querySelector('[data-save-now]')?.addEventListener('click', () => save());

    function showErr(msg) {
        const err = document.querySelector('[data-wizard-error]');
        err.classList.remove('hidden');
        err.textContent = msg;
    }

    // ── status toggle (step 1) ────────────────────────────────────
    function renderStatusToggle() {
        const live = state.status === 'live';
        document.querySelector('[data-status-dot]')?.classList.toggle('bg-wa-green', live);
        document.querySelector('[data-status-dot]')?.classList.toggle('bg-paper-200', !live);
        const lbl = document.querySelector('[data-status-label]');
        if (lbl) lbl.textContent = live ? 'Live — answers calls' : 'Draft — won\'t pick up';
        document.querySelector('[data-status-track]')?.classList.toggle('bg-wa-deep', live);
        document.querySelector('[data-status-track]')?.classList.toggle('bg-paper-200', !live);
        document.querySelector('[data-status-knob]')?.classList.toggle('translate-x-4', live);
        const cb = document.querySelector('[data-wf="status_toggle"]');
        if (cb) cb.checked = live;
        const badge = document.querySelector('[data-status-badge]');
        if (badge) {
            badge.textContent = live ? 'Live · ready' : 'Draft / unsaved';
            badge.classList.toggle('bg-wa-mint', live);
            badge.classList.toggle('text-wa-deep', live);
            badge.classList.toggle('bg-paper-50', !live);
            badge.classList.toggle('text-ink-700', !live);
        }
    }

    // ── language chips ────────────────────────────────────────────
    function renderLangs() {
        const wrap = document.querySelector('[data-lang-chips]'); if (!wrap) return;
        wrap.innerHTML = '';
        LANGS_AVAILABLE.forEach(([code, label]) => {
            const on = state.languages.includes(code);
            const chip = document.createElement('button');
            chip.type = 'button';
            chip.className = 'px-2 py-1 rounded-full text-[11px] font-mono border ' +
                (on ? 'bg-wa-deep text-paper-0 border-wa-deep' : 'bg-paper-0 text-ink-700 border-paper-200 hover:bg-paper-50');
            chip.textContent = label;
            chip.addEventListener('click', () => {
                if (on) state.languages = state.languages.filter(c => c !== code);
                else state.languages = [...state.languages, code];
                if (state.languages.length === 0) state.languages = ['en'];
                renderLangs();
            });
            wrap.appendChild(chip);
        });
    }

    // ── persona cards (step 1) ────────────────────────────────────
    const PERSONA_PROMPTS = {
        support:   'You are a patient, empathetic support agent. Listen carefully, ask one question at a time, and use the available skills to look up account details before suggesting fixes.',
        sales:     'You are an upbeat sales agent. Greet warmly, ask discovery questions, and qualify the lead before pitching. Hand off to a human if the caller is ready to buy.',
        scheduler: 'You are a friendly scheduling assistant. Confirm what the caller needs, offer the next 2-3 calendar slots, and book the chosen slot using the available skills.',
        concierge: 'You are a warm concierge who remembers prior conversations. Reference the caller\'s history when relevant and route them efficiently.',
    };
    function renderPersona() {
        document.querySelectorAll('[data-persona]').forEach(btn => {
            const on = btn.dataset.persona === state.persona;
            btn.classList.toggle('border-wa-deep', on);
            btn.classList.toggle('bg-wa-bubble/40', on);
            btn.classList.toggle('ring-2', on);
            btn.classList.toggle('ring-wa-deep/30', on);
        });
    }
    document.querySelectorAll('[data-persona]').forEach(btn => {
        btn.addEventListener('click', () => {
            state.persona = btn.dataset.persona;
            // If the system prompt is empty or matches a previous preset,
            // overwrite it so persona switch actually changes behaviour.
            const prevValues = Object.values(PERSONA_PROMPTS);
            if (!state.ai_system_prompt.trim() || prevValues.includes(state.ai_system_prompt)) {
                state.ai_system_prompt = PERSONA_PROMPTS[state.persona] || '';
                const t = document.querySelector('[data-wf="ai_system_prompt"]');
                if (t) t.value = state.ai_system_prompt;
            }
            renderPersona();
            renderPreview();
        });
    });

    // ── greetings (step 1) ────────────────────────────────────────
    function renderGreetings() {
        const wrap = document.querySelector('[data-greetings]'); if (!wrap) return;
        wrap.innerHTML = '';
        state.greeting_variations.forEach((g, i) => {
            const row = document.createElement('div');
            row.className = 'flex items-start gap-2';
            row.innerHTML = `
                <span class="mt-2 w-5 h-5 rounded-full bg-paper-50 grid place-items-center font-mono text-[10px] text-ink-500">${i + 1}</span>
                <textarea rows="2" maxlength="500" class="flex-1 px-3 py-2 border border-paper-200 rounded-lg bg-white text-[12.5px] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10">${escapeHtml(g)}</textarea>
                <button type="button" class="mt-2 w-7 h-7 rounded-full hover:bg-accent-coral/15 text-accent-coral grid place-items-center"><svg viewBox="0 0 16 16" class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 4h10M6 4V2.5h4V4M5 4l1 9h4l1-9"/></svg></button>
            `;
            row.querySelector('textarea').addEventListener('input', e => { state.greeting_variations[i] = e.target.value; renderPreview(); });
            row.querySelector('button').addEventListener('click', () => {
                if (state.greeting_variations.length <= 1) { state.greeting_variations = ['']; renderGreetings(); return; }
                state.greeting_variations.splice(i, 1); renderGreetings(); renderPreview();
            });
            wrap.appendChild(row);
        });
    }
    document.querySelector('[data-add-greeting]')?.addEventListener('click', () => {
        if (state.greeting_variations.length >= 5) return;
        state.greeting_variations.push('');
        renderGreetings();
    });

    // ── provider cards (step 2) ───────────────────────────────────
    function renderProvider() {
        document.querySelectorAll('[data-provider]').forEach(btn => {
            const on = btn.dataset.provider === state.ai_provider;
            btn.classList.toggle('border-wa-deep', on);
            btn.classList.toggle('bg-wa-bubble/40', on);
            btn.classList.toggle('ring-2', on);
            btn.classList.toggle('ring-wa-deep/30', on);
        });
        renderPreview();
    }
    document.querySelectorAll('[data-provider]').forEach(btn => {
        btn.addEventListener('click', () => {
            const next = btn.dataset.provider;
            state.ai_provider = next;
            state.ai_model = PROVIDER_DEFAULTS[next] || state.ai_model;
            const m = document.querySelector('[data-wf="ai_model"]');
            if (m) m.value = state.ai_model;
            renderProvider();
        });
    });

    // ── voice cards (step 4) ──────────────────────────────────────
    function renderVoiceCards() {
        document.querySelectorAll('[data-voice-card]').forEach(btn => {
            const on = btn.dataset.voiceCard === state.voice_provider;
            btn.classList.toggle('border-wa-deep', on);
            btn.classList.toggle('bg-wa-bubble/40', on);
            btn.classList.toggle('ring-2', on);
            btn.classList.toggle('ring-wa-deep/30', on);
        });
        renderPreview();
    }
    document.querySelectorAll('[data-voice-card]').forEach(btn => {
        btn.addEventListener('click', () => { state.voice_provider = btn.dataset.voiceCard; renderVoiceCards(); });
    });

    // ── voicemail cards (step 5) ──────────────────────────────────
    function renderVoicemail() {
        document.querySelectorAll('[data-voicemail]').forEach(btn => {
            const on = btn.dataset.voicemail === state.voicemail_behavior;
            btn.classList.toggle('border-wa-deep', on);
            btn.classList.toggle('bg-wa-bubble/40', on);
            btn.classList.toggle('ring-2', on);
            btn.classList.toggle('ring-wa-deep/30', on);
        });
    }
    document.querySelectorAll('[data-voicemail]').forEach(btn => {
        btn.addEventListener('click', () => { state.voicemail_behavior = btn.dataset.voicemail; renderVoicemail(); });
    });

    // ── personality sliders (step 2) ──────────────────────────────
    document.querySelectorAll('[data-pers]').forEach(s => {
        const key = s.dataset.pers;
        s.value = state.personality[key] ?? 50;
        const lbl = document.querySelector(`[data-pers-val="${key}"]`);
        if (lbl) lbl.textContent = s.value;
        s.addEventListener('input', () => {
            state.personality[key] = parseInt(s.value, 10);
            if (lbl) lbl.textContent = s.value;
        });
    });

    // ── exit keyword chips (step 5) ───────────────────────────────
    function renderExit() {
        const wrap = document.querySelector('[data-exit-chips]'); if (!wrap) return;
        wrap.innerHTML = '';
        state.exit_keywords.forEach((kw, i) => {
            const chip = document.createElement('span');
            chip.className = 'inline-flex items-center gap-1 px-2 py-0.5 rounded-full border border-accent-coral/40 bg-accent-coral/10 text-accent-coral text-[11px] font-mono';
            chip.innerHTML = `${escapeHtml(kw)}<button type="button"><svg viewBox="0 0 16 16" class="w-2.5 h-2.5" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4l8 8M12 4l-8 8"/></svg></button>`;
            chip.querySelector('button').addEventListener('click', () => { state.exit_keywords.splice(i, 1); renderExit(); });
            wrap.appendChild(chip);
        });
    }
    document.querySelector('[data-exit-input]')?.addEventListener('keydown', e => {
        if (e.key !== 'Enter') return;
        e.preventDefault();
        const v = (e.target.value || '').trim();
        if (!v) return;
        if (!state.exit_keywords.includes(v)) state.exit_keywords.push(v);
        e.target.value = '';
        renderExit();
    });

    // ── tools list (step 3) ───────────────────────────────────────
    function renderTools() {
        const wrap = document.querySelector('[data-tools-list]'); if (!wrap) return;
        wrap.innerHTML = '';
        state.tools.forEach((t, i) => wrap.appendChild(renderToolCard(t, i)));
    }
    function renderToolCard(tool, idx) {
        const card = document.createElement('div');
        card.className = 'border border-paper-200 rounded-2xl bg-paper-0 overflow-hidden';
        // Single-column, tinted-header, stacked-section layout (intentionally
        // distinct from the two-column config/params split). All data-* hooks
        // are preserved so behaviour + the saved payload are unchanged.
        card.innerHTML = `
            <div class="px-4 py-2.5 bg-wa-bubble/40 border-b border-paper-200 flex items-center gap-3">
                <span class="w-6 h-6 rounded-lg bg-wa-deep text-paper-0 grid place-items-center text-[11px] font-mono shrink-0">${idx + 1}</span>
                <input data-tk="function_name" type="text" maxlength="80" placeholder="name_this_action" class="flex-1 min-w-0 bg-transparent border-0 border-b border-dashed border-paper-300 focus:border-wa-deep focus:outline-none text-[13px] font-mono px-0 py-1"/>
                <button type="button" data-delete-tool class="px-2.5 py-1 rounded-full text-accent-coral hover:bg-accent-coral/10 text-[11px] font-semibold shrink-0">Delete action</button>
            </div>
            <div class="p-4 space-y-4">
                <div>
                    <div class="text-[10px] font-mono uppercase tracking-[0.14em] text-ink-500 mb-1.5">Listens for</div>
                    <input data-tk-trigger type="text" placeholder="type a phrase, then Enter" class="w-full px-3 py-2 border border-paper-200 rounded-lg bg-white text-[12px]"/>
                    <div data-trigger-chips class="flex flex-wrap gap-1.5 mt-2"></div>
                </div>
                <div>
                    <div class="text-[10px] font-mono uppercase tracking-[0.14em] text-ink-500 mb-1.5">Calls this URL</div>
                    <div class="flex gap-2">
                        <select data-tk="http_method" class="px-2 py-2 border border-paper-200 rounded-lg bg-white text-[12px]"><option>GET</option><option>POST</option><option>PUT</option><option>PATCH</option><option>DELETE</option></select>
                        <input data-tk="http_url" type="url" placeholder="https://api.acme.com/orders/{{id}}" class="flex-1 px-3 py-2 border border-paper-200 rounded-lg bg-white text-[12px] font-mono"/>
                    </div>
                </div>
                <div class="rounded-xl border border-paper-200 bg-paper-50/50 p-3">
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-[11.5px] font-semibold">Details to collect from the caller</span>
                        <button type="button" data-add-param class="px-2.5 py-0.5 rounded-full border border-paper-200 bg-paper-0 text-[10px]">+ Add field</button>
                    </div>
                    <div data-params class="space-y-2"></div>
                </div>
                <div class="rounded-xl border border-paper-200 bg-paper-50/50 p-3">
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-[11.5px] font-semibold">Request headers <span class="text-ink-400 font-normal">· optional</span></span>
                        <button type="button" data-add-header class="px-2.5 py-0.5 rounded-full bg-wa-deep text-paper-0 text-[10px]">+ Add</button>
                    </div>
                    <div data-headers class="space-y-1.5"></div>
                </div>
            </div>
        `;
        card.querySelector('[data-tk="function_name"]').value = tool.function_name || '';
        card.querySelector('[data-tk="http_method"]').value = tool.http_method || 'GET';
        card.querySelector('[data-tk="http_url"]').value = tool.http_url || '';
        card.querySelectorAll('[data-tk]').forEach(el => {
            el.addEventListener('input', () => { state.tools[idx][el.dataset.tk] = el.value; });
        });
        renderHeaders(card, tool); renderParams(card, tool); renderTriggers(card, tool);
        card.querySelector('[data-add-header]').addEventListener('click', () => { tool.headers = (tool.headers || []); tool.headers.push({key:'',value:''}); renderHeaders(card, tool); });
        card.querySelector('[data-add-param]').addEventListener('click', () => { tool.parameters = (tool.parameters || []); tool.parameters.push({id:'',label:'',description:'',required:true}); renderParams(card, tool); });
        card.querySelector('[data-tk-trigger]').addEventListener('keydown', e => {
            if (e.key !== 'Enter') return; e.preventDefault();
            const v = (e.target.value || '').trim(); if (!v) return;
            tool.trigger_keywords = tool.trigger_keywords || [];
            if (!tool.trigger_keywords.includes(v)) tool.trigger_keywords.push(v);
            e.target.value = ''; renderTriggers(card, tool);
        });
        card.querySelector('[data-delete-tool]').addEventListener('click', () => { state.tools.splice(idx, 1); renderTools(); });
        return card;
    }
    function renderHeaders(card, tool) {
        const wrap = card.querySelector('[data-headers]'); wrap.innerHTML = '';
        (tool.headers || []).forEach((h, hi) => {
            const r = document.createElement('div'); r.className = 'flex gap-1.5';
            r.innerHTML = `<input class="flex-1 px-2 py-1 border border-paper-200 rounded bg-paper-0 text-[11px] font-mono" placeholder="Authorization"/><input class="flex-1 px-2 py-1 border border-paper-200 rounded bg-paper-0 text-[11px] font-mono" placeholder="Bearer …"/><button type="button" class="w-6 h-6 rounded hover:bg-accent-coral/15 text-accent-coral text-[12px]">×</button>`;
            const [k,v,del] = r.children; k.value = h.key||''; v.value = h.value||'';
            k.addEventListener('input', () => h.key = k.value); v.addEventListener('input', () => h.value = v.value);
            del.addEventListener('click', () => { tool.headers.splice(hi, 1); renderHeaders(card, tool); });
            wrap.appendChild(r);
        });
    }
    function renderParams(card, tool) {
        const wrap = card.querySelector('[data-params]'); wrap.innerHTML = '';
        (tool.parameters || []).forEach((p, pi) => {
            const r = document.createElement('div'); r.className = 'border border-paper-200 rounded-lg p-2 bg-paper-50';
            r.innerHTML = `
                <div class="grid grid-cols-2 gap-1.5 mb-1.5">
                    <input class="px-2 py-1 border border-paper-200 rounded bg-paper-0 text-[11px] font-mono" placeholder="field key (slug)"/>
                    <input class="px-2 py-1 border border-paper-200 rounded bg-paper-0 text-[11px]" placeholder="Spoken name"/>
                </div>
                <input class="w-full px-2 py-1 border border-paper-200 rounded bg-paper-0 text-[11px] mb-1.5" placeholder="Prompt if the caller hasn't said it"/>
                <div class="flex items-center justify-between text-[10.5px]">
                    <label class="inline-flex items-center gap-1"><input type="checkbox" class="w-3 h-3 accent-wa-deep"/>must collect</label>
                    <button type="button" class="text-accent-coral text-[10.5px]">remove</button>
                </div>
            `;
            const [grid, desc, foot] = r.children;
            const [ids, lbl] = grid.children;
            ids.value = p.id || ''; lbl.value = p.label || ''; desc.value = p.description || '';
            const req = foot.querySelector('input'); const del = foot.querySelector('button');
            req.checked = !!p.required;
            ids.addEventListener('input', () => p.id = ids.value);
            lbl.addEventListener('input', () => p.label = lbl.value);
            desc.addEventListener('input', () => p.description = desc.value);
            req.addEventListener('change', () => p.required = req.checked);
            del.addEventListener('click', () => { tool.parameters.splice(pi, 1); renderParams(card, tool); });
            wrap.appendChild(r);
        });
    }
    function renderTriggers(card, tool) {
        const wrap = card.querySelector('[data-trigger-chips]'); wrap.innerHTML = '';
        (tool.trigger_keywords || []).forEach((kw, ki) => {
            const chip = document.createElement('span');
            chip.className = 'inline-flex items-center gap-1 px-2 py-0.5 rounded-full border border-paper-200 bg-paper-0 text-[10.5px] font-mono';
            chip.innerHTML = `${escapeHtml(kw)}<button type="button">×</button>`;
            chip.querySelector('button').addEventListener('click', () => { tool.trigger_keywords.splice(ki, 1); renderTriggers(card, tool); });
            wrap.appendChild(chip);
        });
    }
    document.querySelector('[data-add-tool]')?.addEventListener('click', () => {
        state.tools.push({ function_name: '', trigger_keywords: [], http_method: 'GET', http_url: '', headers: [], parameters: [] });
        renderTools();
    });

    // ── live preview (sidebar) ────────────────────────────────────
    function renderPreview() {
        const wrap = document.querySelector('[data-preview-bubbles]'); if (!wrap) return;
        wrap.innerHTML = '';
        const ring = document.querySelector('[data-preview-ring]');
        if (ring) ring.classList.toggle('hidden', false);
        const name = document.querySelector('[data-preview-name]');
        if (name) name.textContent = state.name || 'Voice agent';

        const greet = (state.greeting_variations.find(g => (g || '').trim()) || '').trim();
        if (greet) {
            wrap.appendChild(bubble('agent', greet));
            wrap.appendChild(bubble('user', 'Hi, I\'m calling about my order'));
            wrap.appendChild(bubble('agent', 'Of course — what\'s your order number?'));
        }
        const provDot = document.querySelector('[data-preview-provider-dot]');
        const provLbl = document.querySelector('[data-preview-provider]');
        const colors = { gemini: '#4285F4', openai: '#10A37F', anthropic: '#D97757' };
        if (provDot) provDot.style.background = colors[state.ai_provider] || '#888';
        if (provLbl) provLbl.textContent = `${state.ai_provider} · ${state.ai_model}`;
        const voiceLbl = document.querySelector('[data-preview-voice]');
        if (voiceLbl) voiceLbl.textContent = `${state.voice_provider}${state.voice_id ? ' · ' + state.voice_id : ''}`;
    }
    function bubble(who, text) {
        const d = document.createElement('div');
        const isAgent = who === 'agent';
        d.className = 'flex ' + (isAgent ? 'justify-start' : 'justify-end');
        d.innerHTML = `<div class="max-w-[80%] px-2.5 py-1.5 rounded-lg text-[11.5px] leading-snug ${isAgent ? 'bg-paper-0 border border-paper-200 rounded-bl-[4px]' : 'bg-wa-mint rounded-br-[4px]'}"><div class="font-mono text-[9px] uppercase tracking-[0.14em] ${isAgent ? 'text-wa-deep' : 'text-ink-700'} mb-0.5">${isAgent ? 'agent' : 'caller'}</div>${escapeHtml(text)}</div>`;
        return d;
    }

    // ── save ──────────────────────────────────────────────────────
    async function save() {
        const csrf = document.querySelector('meta[name=csrf-token]')?.content || '';
        const body = {
            id: state.id,
            name: state.name,
            greeting_text: (state.greeting_variations[0] || '').trim(),
            status: state.status,
            is_active: state.is_active ? 1 : 0,
            ai_provider: state.ai_provider, ai_model: state.ai_model,
            ai_api_key: state.ai_api_key || '',
            ai_system_prompt: state.ai_system_prompt,
            knowledge_base_url: state.knowledge_base_url,
            natural_conciseness: state.natural_conciseness ? 1 : 0,
            voice_provider: state.voice_provider, voice_api_key: state.voice_api_key || '',
            voice_id: state.voice_id, stt_provider: state.stt_provider,
            record_agent: state.record_agent ? 1 : 0,
            record_user:  state.record_user ? 1 : 0,
            auto_logging: state.auto_logging ? 1 : 0,
            exit_keywords: state.exit_keywords,
            last_greeting: state.last_greeting,
            device_ids: state.device_ids,
            tools: state.tools.filter(t => (t.function_name || '').trim() && (t.http_url || '').trim()),
            meta: {
                persona: state.persona,
                languages: state.languages,
                greeting_variations: state.greeting_variations.filter(g => (g || '').trim()),
                personality: state.personality,
                noise_suppression: state.noise_suppression,
                voicemail_behavior: state.voicemail_behavior,
                human_handoff_team: state.human_handoff_team,
            },
        };
        const res = await fetch('/ai-assistants/api/save', {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf, 'X-Requested-With': 'XMLHttpRequest' },
            body: JSON.stringify(body),
        });
        const j = await res.json().catch(() => null);
        if (!res.ok || !j?.ok) {
            showErr(j?.message || (j?.errors ? Object.values(j.errors)[0][0] : `Save failed (${res.status})`));
            return;
        }
        window.location.href = j.redirect_to || '/ai-assistants';
    }

    function escapeHtml(s) {
        return String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
    }

    // ── boot ──────────────────────────────────────────────────────
    bindFlat();
    renderStatusToggle();
    renderLangs();
    renderPersona();
    renderGreetings();
    renderProvider();
    renderVoiceCards();
    renderVoicemail();
    renderExit();
    renderTools();
    renderPreview();
    gotoStep(1);
}
