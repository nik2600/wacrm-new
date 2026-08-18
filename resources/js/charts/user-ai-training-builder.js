// Smart-agent builder — 5-step wizard (Identity → Persona → Brain →
// Safety → Knowledge). Same stepper UX as /wa-campaigns/create and
// /chatbot-widgets/create. Step 5 auto-saves the agent first because
// knowledge entries need a saved row to attach to.
//
// All popups use window.toast / window.confirmDialog from app.js.
export default function init() {
  const root = document.getElementById('ait-builder');
  if (!root) return;

  const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
  const defaults = JSON.parse(root.dataset.defaults || '{}');
  const state = { ...defaults };

  const toast = (m, kind = 'success') => (window.toast ? window.toast(m, kind) : null);
  const confirmDialog = (opts) => {
    if (window.confirmDialog) return window.confirmDialog(opts);
    if (window.confirm(opts.message || 'Are you sure?')) opts.onConfirm?.();
  };

  const TOTAL_STEPS = 5;
  let current  = 1;
  let furthest = state.id ? TOTAL_STEPS : 1;

  async function api(path, opts = {}) {
    const res = await fetch(path, {
      method: opts.method || 'GET',
      headers: {
        'Accept': 'application/json',
        'X-CSRF-TOKEN': csrf,
        ...(opts.body && !(opts.body instanceof FormData) ? { 'Content-Type': 'application/json' } : {}),
      },
      body: opts.body instanceof FormData ? opts.body : (opts.body ? JSON.stringify(opts.body) : undefined),
    });
    const json = await res.json().catch(() => ({}));
    return { ok: res.ok && json.ok !== false, json };
  }
  function escapeHtml(s) {
    return String(s ?? '').replace(/[&<>"']/g, (c) => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
  }

  // ---------------------------- form binding ----------------------------

  root.querySelectorAll('[data-field]').forEach((el) => {
    const key = el.dataset.field;
    const v = state[key];
    if (el.type === 'checkbox') el.checked = !!v;
    else if (el.type === 'radio') el.checked = String(el.value) === String(v ?? '');
    else el.value = v ?? '';
    el.addEventListener('input',  () => readInto(el));
    el.addEventListener('change', () => readInto(el));
  });
  function readInto(el) {
    const key = el.dataset.field;
    if (el.type === 'checkbox') state[key] = el.checked;
    else if (el.type === 'radio') { if (el.checked) state[key] = el.value; }
    else state[key] = el.value;
  }

  // --------------------------- step nav ---------------------------

  function paintStepper() {
    root.querySelectorAll('.step-node').forEach((node) => {
      const n = parseInt(node.dataset.n, 10);
      const dot = node.querySelector('.dot');
      const lab = node.querySelector('.lab');
      const bar = node.querySelector('.bar');
      if (!dot || !lab) return;
      dot.className = 'dot w-7 h-7 rounded-full grid place-items-center text-[11px] font-semibold font-mono shrink-0 transition border-[1.5px]';
      lab.className = 'lab text-[11.5px] whitespace-nowrap';
      if (n < current) {
        dot.classList.add('bg-wa-deep', 'border-wa-deep', 'text-paper-0');
        dot.innerHTML = '<svg viewBox="0 0 16 16" class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2.4"><path d="M3 8l3 3 7-8"/></svg>';
        lab.classList.add('font-semibold', 'text-ink-900');
      } else if (n === current) {
        dot.classList.add('bg-paper-0', 'border-wa-deep', 'text-wa-deep', 'ring-4', 'ring-wa-deep/10');
        dot.textContent = String(n);
        lab.classList.add('font-semibold', 'text-wa-deep');
      } else {
        dot.classList.add('bg-paper-0', 'border-paper-200', 'text-ink-500');
        dot.textContent = String(n);
        lab.classList.add('font-medium', 'text-ink-500');
      }
      if (bar) {
        bar.classList.remove('bg-paper-200', 'bg-wa-deep');
        bar.classList.add(n < current ? 'bg-wa-deep' : 'bg-paper-200');
      }
    });
  }

  function showStep(n) {
    if (n < 1 || n > TOTAL_STEPS) return;
    current = n;
    if (n > furthest) furthest = n;
    root.querySelectorAll('.step-pane').forEach((p) => p.classList.add('hidden'));
    const pane = root.querySelector(`.step-pane[data-step="${n}"]`);
    if (pane) pane.classList.remove('hidden');
    document.getElementById('ait-cur').textContent = String(n);
    document.getElementById('ait-prev').disabled = (n === 1);
    // Use inline style — Tailwind's `hidden` loses to `inline-flex` on
    // the button (same display category, source-order specificity).
    document.getElementById('ait-next').style.display   = (n === TOTAL_STEPS) ? 'none' : '';
    document.getElementById('ait-finish').style.display = (n === TOTAL_STEPS) ? '' : 'none';
    paintStepper();
  }

  root.querySelectorAll('.step-node').forEach((node) => {
    node.addEventListener('click', () => {
      const target = parseInt(node.dataset.n, 10);
      if (target === 5 && !state.id) {
        toast('Save the agent first — Knowledge needs a saved row.', 'info');
        return;
      }
      // Free to jump backward / to an already-cleared step; forward
      // jumps must pass the current step's required fields.
      if (target <= current || target <= furthest) showStep(target);
      else if (gateForward(current)) showStep(target);
    });
  });
  document.getElementById('ait-prev').addEventListener('click', () => { if (current > 1) showStep(current - 1); });
  document.getElementById('ait-next').addEventListener('click', async () => {
    if (!gateForward(current)) return;
    // Save before unlocking Knowledge (step 5).
    if (current === 4) {
      const ok = await saveAssistant({ silent: true });
      if (!ok) return;
      await loadSources();
    }
    showStep(current + 1);
  });

  // ── Per-step validation gate ─────────────────────────────────────
  // Block forward navigation (Next button OR a step-node jump) until
  // the current step's required fields are filled. Backward moves are
  // always allowed. validateStep returns {ok, msg, el} so the caller
  // can toast + highlight the offending field. Fields are bound by
  // [data-field] (not id), so we resolve elements that way.
  const field = (k) => root.querySelector(`[data-field="${k}"]`);
  function flashInvalid(el) {
    if (!el) return;
    el.classList.add('ring-2', 'ring-accent-coral/60', 'border-accent-coral');
    try { el.focus({ preventScroll: false }); } catch (_) {}
    el.scrollIntoView({ behavior: 'smooth', block: 'center' });
    setTimeout(() => el.classList.remove('ring-2', 'ring-accent-coral/60', 'border-accent-coral'), 2600);
  }

  function validateStep(n) {
    if (n === 1) {
      if (!String(state.name || '').trim()) {
        return { ok: false, msg: 'Name your assistant.', el: field('name') };
      }
    }
    if (n === 2) {
      if (!String(state.greeting || '').trim()) {
        return { ok: false, msg: 'Write an opening line.', el: field('greeting') };
      }
    }
    if (n === 3) {
      if (!String(state.ai_model || '').trim()) {
        return { ok: false, msg: 'Pick a model.', el: field('ai_model') };
      }
      const mt = parseInt(state.reply_max_tokens, 10);
      if (Number.isNaN(mt) || mt < 50 || mt > 4000) {
        return { ok: false, msg: 'Reply length must be 50–4000 tokens.', el: field('reply_max_tokens') };
      }
      const t = parseFloat(state.temperature);
      if (Number.isNaN(t) || t < 0 || t > 2) {
        return { ok: false, msg: 'Creativity must be between 0 and 2.', el: field('temperature') };
      }
    }
    // Step 4 (Safety) is all defaulted/optional; Step 5 (Knowledge)
    // sources are optional — neither gates.
    return { ok: true };
  }

  // Guard a forward move; returns true if it's allowed to proceed.
  function gateForward(from) {
    const v = validateStep(from);
    if (!v.ok) { toast(v.msg, 'error'); flashInvalid(v.el); }
    return v.ok;
  }

  // ------------------------------- save -------------------------------

  async function saveAssistant({ silent = false } = {}) {
    // Walk every step's validator before posting.
    for (let n = 1; n <= 4; n++) {
      const v = validateStep(n);
      if (!v.ok) { showStep(n); toast(v.msg, 'error'); flashInvalid(v.el); return false; }
    }
    const body = {
      id: state.id,
      name: state.name,
      status: state.status,
      greeting: state.greeting,
      system_prompt: state.system_prompt,
      tone: state.tone,
      language: state.language,
      ai_provider: state.ai_provider,
      ai_model: state.ai_model,
      reply_max_tokens: parseInt(state.reply_max_tokens, 10) || 400,
      temperature: parseFloat(state.temperature) || 0.7,
      fallback_message: state.fallback_message,
      handoff_enabled: !!state.handoff_enabled,
      handoff_keyword: state.handoff_keyword,
      handoff_message: state.handoff_message,
    };
    const { ok, json } = await api('/ai-training/api/assistant', { method: 'POST', body });
    if (!ok) { toast(json.error || 'Save failed — check the fields above.', 'error'); return false; }
    state.id = json.id;
    document.getElementById('ait-state-pill').textContent = 'Saved';
    if (!silent) toast('Agent saved.', 'success');
    return true;
  }

  document.getElementById('ait-save')?.addEventListener('click', () => saveAssistant());
  document.getElementById('ait-finish')?.addEventListener('click', async () => {
    const ok = await saveAssistant();
    if (ok) window.location.href = window.appUrl('/ai-training');
  });

  // ----------------------------- sources -----------------------------

  async function loadSources() {
    if (!state.id) return;
    const { ok, json } = await api(`/ai-training/api/sources?assistant_id=${state.id}`);
    if (!ok) return;
    const rows = document.getElementById('ait-source-rows');
    if (!rows) return;
    if (!json.sources?.length) {
      rows.innerHTML = `<div class="px-3 py-6 text-center text-[12px] text-ink-500">No knowledge yet. Pick a kind above to add the first entry.</div>`;
      return;
    }
    rows.innerHTML = json.sources.map((s) => `
      <div class="px-3 py-2 grid grid-cols-[80px_1fr_120px_70px] items-center gap-3 border-b border-paper-200 hover:bg-paper-50">
        <div class="text-[11px] font-mono uppercase tracking-[0.14em] text-ink-500">${s.kind}</div>
        <div class="min-w-0">
          <div class="text-[13px] text-ink-900 truncate">${escapeHtml(s.label)}</div>
          ${s.url ? `<div class="text-[11px] text-ink-500 font-mono truncate">${escapeHtml(s.url)}</div>` : ''}
        </div>
        <div>
          <span class="inline-flex items-center gap-1 text-[10.5px] font-mono uppercase tracking-[0.14em] px-1.5 py-0.5 rounded ${statusClasses(s.status)}">
            <span class="w-1.5 h-1.5 rounded-full ${statusDotClass(s.status)}"></span>${s.status}
          </span>
          ${s.error ? `<div class="text-[10px] text-accent-coral mt-0.5 truncate">${escapeHtml(s.error)}</div>` : ''}
        </div>
        <div class="text-right">
          <button type="button" data-delete-source="${s.id}" class="w-7 h-7 rounded-full hover:bg-accent-coral/15 text-accent-coral inline-flex items-center justify-center" title="Remove">
            <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M3 4h10M6 4V2.5h4V4M5 4l1 9h4l1-9"/></svg>
          </button>
        </div>
      </div>
    `).join('');
    rows.querySelectorAll('[data-delete-source]').forEach((b) => {
      b.addEventListener('click', () => {
        const id = b.dataset.deleteSource;
        confirmDialog({
          eyebrow: 'Remove knowledge',
          title: 'Remove this knowledge entry?',
          message: 'The agent will stop using it for new replies. Older conversations stay unchanged.',
          confirmText: 'Remove',
          cancelText: 'Keep',
          tone: 'danger',
          onConfirm: async () => {
            const { ok } = await api(`/ai-training/api/source/${id}`, { method: 'DELETE' });
            if (ok) { toast('Removed.', 'success'); loadSources(); }
            else { toast('Remove failed.', 'error'); }
          },
        });
      });
    });
  }

  const statusClasses = (s) =>
    s === 'ready'  ? 'bg-wa-mint text-wa-deep' :
    s === 'failed' ? 'bg-accent-coral/10 text-accent-coral' :
                     'bg-paper-100 text-ink-500';
  const statusDotClass = (s) =>
    s === 'ready'  ? 'bg-wa-green' :
    s === 'failed' ? 'bg-accent-coral' :
                     'bg-paper-200';

  const addPanel = document.getElementById('ait-source-add');

  function openAddPanel(kind) {
    if (!addPanel) return;
    let html = '';
    if (kind === 'url') {
      html = `
        <div class="font-mono text-[10px] uppercase tracking-[0.14em] text-ink-500">Fetch a public URL</div>
        <input data-src-label placeholder="Label (e.g. Pricing page)" class="w-full bg-paper-0 border border-paper-200 rounded-md px-2.5 py-1.5 text-[12.5px]">
        <input data-src-url placeholder="https://yoursite.com/pricing" class="w-full bg-paper-0 border border-paper-200 rounded-md px-2.5 py-1.5 text-[12.5px] font-mono">
        <div class="flex gap-2">
          <button type="button" data-src-cancel class="px-3 py-1.5 rounded-md border border-paper-200 text-[12px] font-semibold text-ink-700">Cancel</button>
          <button type="button" data-src-go class="px-3 py-1.5 rounded-md bg-wa-deep text-paper-0 text-[12px] font-semibold hover:bg-wa-teal">Fetch &amp; train</button>
        </div>`;
    } else if (kind === 'text') {
      html = `
        <div class="font-mono text-[10px] uppercase tracking-[0.14em] text-ink-500">Paste plain text</div>
        <input data-src-label placeholder="Label (e.g. Refund policy summary)" class="w-full bg-paper-0 border border-paper-200 rounded-md px-2.5 py-1.5 text-[12.5px]">
        <textarea data-src-content rows="6" placeholder="Paste any text the agent should know..." class="w-full bg-paper-0 border border-paper-200 rounded-md px-2.5 py-1.5 text-[12.5px]"></textarea>
        <div class="flex gap-2">
          <button type="button" data-src-cancel class="px-3 py-1.5 rounded-md border border-paper-200 text-[12px] font-semibold text-ink-700">Cancel</button>
          <button type="button" data-src-go class="px-3 py-1.5 rounded-md bg-wa-deep text-paper-0 text-[12px] font-semibold hover:bg-wa-teal">Save text</button>
        </div>`;
    } else if (kind === 'qa') {
      html = `
        <div class="font-mono text-[10px] uppercase tracking-[0.14em] text-ink-500">Single Q&amp;A</div>
        <input data-src-label placeholder="Label (e.g. Refund timing)" class="w-full bg-paper-0 border border-paper-200 rounded-md px-2.5 py-1.5 text-[12.5px]">
        <input data-src-question placeholder="Question · How long do refunds take?" class="w-full bg-paper-0 border border-paper-200 rounded-md px-2.5 py-1.5 text-[12.5px]">
        <textarea data-src-answer rows="3" placeholder="Answer · Refunds settle within 3-5 business days." class="w-full bg-paper-0 border border-paper-200 rounded-md px-2.5 py-1.5 text-[12.5px]"></textarea>
        <div class="flex gap-2">
          <button type="button" data-src-cancel class="px-3 py-1.5 rounded-md border border-paper-200 text-[12px] font-semibold text-ink-700">Cancel</button>
          <button type="button" data-src-go class="px-3 py-1.5 rounded-md bg-wa-deep text-paper-0 text-[12px] font-semibold hover:bg-wa-teal">Save pair</button>
        </div>`;
    } else if (kind === 'file') {
      html = `
        <div class="font-mono text-[10px] uppercase tracking-[0.14em] text-ink-500">Upload knowledge file</div>
        <input data-src-label placeholder="Label (e.g. Product handbook)" class="w-full bg-paper-0 border border-paper-200 rounded-md px-2.5 py-1.5 text-[12.5px]">
        <input data-src-file type="file" accept=".pdf,.docx,.txt,.md,.markdown,.csv,.html,.htm,.log" class="block w-full text-[12.5px]">
        <p class="text-[11px] text-ink-500">PDF, DOCX, TXT, Markdown, CSV or HTML — up to 10 MB. Text is extracted automatically.</p>
        <div class="flex gap-2">
          <button type="button" data-src-cancel class="px-3 py-1.5 rounded-md border border-paper-200 text-[12px] font-semibold text-ink-700">Cancel</button>
          <button type="button" data-src-go class="px-3 py-1.5 rounded-md bg-wa-deep text-paper-0 text-[12px] font-semibold hover:bg-wa-teal">Upload</button>
        </div>`;
    }
    addPanel.innerHTML = html;
    addPanel.classList.remove('hidden');
    addPanel.dataset.kind = kind;
    addPanel.querySelector('[data-src-cancel]')?.addEventListener('click', () => {
      addPanel.classList.add('hidden'); addPanel.innerHTML = '';
    });
    addPanel.querySelector('[data-src-go]')?.addEventListener('click', () => submitAdd(kind));
  }

  async function submitAdd(kind) {
    if (!state.id) { toast('Save the agent first.', 'info'); return; }
    const get = (sel) => addPanel.querySelector(sel)?.value ?? '';
    const label = get('[data-src-label]');
    if (!label.trim()) { toast('Add a label so you can find this entry later.', 'error'); return; }

    if (kind === 'file') {
      const fileEl = addPanel.querySelector('[data-src-file]');
      const file = fileEl?.files?.[0];
      if (!file) { toast('Pick a file (PDF, DOCX, TXT, Markdown, CSV or HTML).', 'error'); return; }
      const fd = new FormData();
      fd.append('file', file);
      fd.append('label', label);
      fd.append('assistant_id', state.id);
      const { ok, json } = await api('/ai-training/api/source/file', { method: 'POST', body: fd });
      if (!ok) { toast(json.error || 'Upload failed.', 'error'); return; }
    } else {
      const body = { assistant_id: state.id, kind, label };
      if (kind === 'url')  body.url = get('[data-src-url]');
      if (kind === 'text') body.content = get('[data-src-content]');
      if (kind === 'qa')   { body.question = get('[data-src-question]'); body.answer = get('[data-src-answer]'); }
      const { ok, json } = await api('/ai-training/api/source', { method: 'POST', body });
      if (!ok) { toast(json.error || 'Add failed.', 'error'); return; }
    }
    addPanel.classList.add('hidden');
    addPanel.innerHTML = '';
    toast('Knowledge added.', 'success');
    loadSources();
  }

  root.querySelectorAll('[data-add-source]').forEach((b) => {
    b.addEventListener('click', () => openAddPanel(b.dataset.addSource));
  });

  // ------------------------------- boot -------------------------------

  showStep(1);
  if (state.id) loadSources();
}
