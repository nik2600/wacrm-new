// Chatbot Widget builder — same stepper UX as /wa-campaigns/create.
// 4 steps: Setup → Look → Voice → Launch. Step nodes are clickable,
// "Next" validates the current pane, "Launch" on step 4 saves and
// reveals the embed snippet. All popups use window.toast / window.confirmDialog.
//
// intl-tel-input is mounted on the WhatsApp number field (same setup
// /devices uses): full ITU country list, flag picker, dial code goes
// into the hidden target_whatsapp_cc field on every country change.
import intlTelInput from 'intl-tel-input/intlTelInputWithUtils';
import 'intl-tel-input/styles';
import { themeColor, themeAlpha } from '../theme-colors.js';

export default function init() {
  const root = document.getElementById('cbw-builder');
  if (!root) return;

  const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
  const defaults = JSON.parse(root.dataset.defaults || '{}');
  let token = root.dataset.token || '';
  const state = { ...defaults };

  const toast = (m, kind = 'success') => (window.toast ? window.toast(m, kind) : null);
  const TOTAL_STEPS = 4;
  let current = 1;
  let furthest = 1;

  // ---------------------------- form binding ----------------------------

  root.querySelectorAll('[data-field]').forEach((el) => {
    const key = el.dataset.field;
    const v = state[key];
    if (el.type === 'checkbox') el.checked = !!v;
    else if (el.type === 'radio') el.checked = String(el.value) === String(v ?? '');
    else el.value = v ?? '';
    el.addEventListener('input', () => readInto(el));
    el.addEventListener('change', () => readInto(el));
  });

  function readInto(el) {
    const key = el.dataset.field;
    if (el.type === 'checkbox') state[key] = el.checked;
    else if (el.type === 'radio') { if (el.checked) state[key] = el.value; }
    else state[key] = el.value;

    // Mirror paired color/text inputs that share the same field.
    root.querySelectorAll(`[data-field="${CSS.escape(key)}"]`).forEach((pair) => {
      if (pair === el || pair.type === 'checkbox' || pair.type === 'radio') return;
      if (pair.value !== state[key]) pair.value = state[key] ?? '';
    });

    if (key === 'mode') {
      // Repaint the radio tile selection (the check icon + border).
      root.querySelectorAll('[data-mode-tile]').forEach((tile) => {
        const active = tile.dataset.modeTile === state.mode;
        tile.classList.toggle('border-wa-deep', active);
        tile.classList.toggle('bg-[#F0F8F6]', active);
        tile.classList.toggle('border-paper-200', !active);
        const check = tile.querySelector('.type-check');
        if (check) check.classList.toggle('opacity-0', !active);
      });
    }
    applyVisibility();
    renderPreview();
  }

  // --------------------------- step nav -------------------------------

  function paintStepper() {
    root.querySelectorAll('.step-node').forEach((node) => {
      const n = parseInt(node.dataset.n, 10);
      const dot = node.querySelector('.dot');
      const lab = node.querySelector('.lab');
      const bar = node.querySelector('.bar');
      // States: completed (n < current), active (n === current), idle (n > current)
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
    document.getElementById('cbw-cur').textContent = String(n);
    document.getElementById('cbw-prev').disabled = (n === 1);
    // Inline style — Tailwind's `hidden` loses to `inline-flex` (same
    // display category, source-order specificity in the compiled CSS).
    document.getElementById('cbw-next').style.display   = (n === TOTAL_STEPS) ? 'none' : '';
    document.getElementById('cbw-launch').style.display = (n === TOTAL_STEPS) ? '' : 'none';
    paintStepper();
  }

  // Step pills: backward / already-cleared jumps are free; a forward
  // jump must pass the current step's required-field gate.
  root.querySelectorAll('.step-node').forEach((node) => {
    node.addEventListener('click', () => {
      const target = parseInt(node.dataset.n, 10);
      if (target <= current || target <= furthest) showStep(target);
      else if (gateForward(current)) showStep(target);
    });
  });

  document.getElementById('cbw-prev').addEventListener('click', () => {
    if (current > 1) showStep(current - 1);
  });
  document.getElementById('cbw-next').addEventListener('click', () => {
    if (current < TOTAL_STEPS && gateForward(current)) showStep(current + 1);
  });

  // ── Per-step validation gate ─────────────────────────────────────
  // Block forward navigation (Next button OR a step-node jump) until
  // the current step's required fields are filled. Backward moves are
  // always allowed. validateStep returns {ok, msg, el} so the caller
  // can toast + highlight the offending field. Fields are bound by
  // [data-field] (not id); for paired colour/text inputs we flash the
  // visible text box rather than the native colour swatch.
  const field = (k) =>
    root.querySelector(`[data-field="${k}"]:not([type="color"]):not([type="hidden"])`)
    || root.querySelector(`[data-field="${k}"]`);
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
        return { ok: false, msg: 'Name your widget.', el: field('name') };
      }
      if (['ai', 'both'].includes(state.mode) && !state.assistant_id) {
        return { ok: false, msg: 'Link a smart agent — or switch the engine to WhatsApp deep-link.', el: field('assistant_id') };
      }
      if (['whatsapp', 'both'].includes(state.mode)) {
        const digits = (String(state.target_whatsapp_cc || '') + String(state.target_whatsapp_number || '')).replace(/\D+/g, '');
        if (digits.length < 8) {
          return { ok: false, msg: 'Add a full WhatsApp number — country code and digits.', el: root.querySelector('[data-cbw-phone]') };
        }
      }
    }
    if (n === 2) {
      if (!/^#[0-9a-f]{3,8}$/i.test(String(state.button_color || ''))) {
        return { ok: false, msg: 'Launcher fill must be a hex colour (e.g. #075E54).', el: field('button_color') };
      }
      if (!String(state.header_title || '').trim()) {
        return { ok: false, msg: 'Give the chat window a title.', el: field('header_title') };
      }
    }
    if (n === 3) {
      if (!String(state.welcome_message || '').trim()) {
        return { ok: false, msg: 'Write an opening line.', el: field('welcome_message') };
      }
      if (state.body_bg_kind === 'image' && !String(state.body_bg_image_url || '').trim()) {
        return { ok: false, msg: 'Add a background image URL or switch to solid colour.', el: field('body_bg_image_url') };
      }
    }
    return { ok: true };
  }

  // Guard a forward move; returns true if it's allowed to proceed.
  function gateForward(from) {
    const v = validateStep(from);
    if (!v.ok) { toast(v.msg, 'error'); flashInvalid(v.el); }
    return v.ok;
  }

  // ------------------------- visibility toggles -------------------------

  function applyVisibility() {
    root.querySelectorAll('[data-show-when-mode]').forEach((el) => {
      const allowed = (el.dataset.showWhenMode || '').split(',').map((s) => s.trim());
      el.classList.toggle('hidden', !allowed.includes(state.mode));
    });
    root.querySelectorAll('[data-show-when-bgkind]').forEach((el) => {
      el.classList.toggle('hidden', state.body_bg_kind !== el.dataset.showWhenBgkind);
    });
  }

  // --------------------------- live preview ---------------------------

  function renderPreview() {
    const $ = (id) => document.getElementById(id);

    const header = $('cbw-prev-header');
    if (header) { header.style.background = state.header_bg || themeColor('wa-deep'); header.style.color = state.header_text_color || '#FFFFFF'; }
    const title = $('cbw-prev-title');
    if (title) title.textContent = state.header_title || 'Chat';

    const body = $('cbw-prev-body');
    if (body) {
      if (state.body_bg_kind === 'image' && state.body_bg_image_url) {
        body.style.backgroundImage = `url("${state.body_bg_image_url}")`;
        body.style.backgroundSize = 'cover';
        body.style.backgroundColor = '';
      } else {
        body.style.backgroundImage = `radial-gradient(circle at 1px 1px, ${themeAlpha('wa-deep', 0.09)} 1px, transparent 0)`;
        body.style.backgroundSize = '18px 18px';
        body.style.backgroundColor = state.body_bg_color || '#ECE5DD';
      }
    }
    const welcome = $('cbw-prev-welcome');
    if (welcome) {
      welcome.textContent = state.welcome_message || 'Hi!';
      welcome.style.background = state.message_bubble_color || '#FFFFFF';
      welcome.style.color = state.message_text_color || '#222222';
    }
    const btn = $('cbw-prev-btn');
    if (btn) {
      btn.textContent = state.button_label || 'Send';
      btn.style.background = state.action_button_bg || themeColor('wa-deep');
      btn.style.color = state.action_button_text_color || '#FFFFFF';
    }

    // Right-side meta cards
    const engineLabel = { ai: 'Smart agent', whatsapp: 'WhatsApp', both: 'AI + WhatsApp' }[state.mode] || 'AI';
    $('cbw-prev-engine')?.replaceChildren(document.createTextNode(engineLabel));
    $('cbw-prev-engine-label')?.replaceChildren(document.createTextNode(engineLabel));
    $('cbw-prev-placement')?.replaceChildren(document.createTextNode(state.position === 'bottom_left' ? 'Left' : 'Right'));
    $('cbw-prev-autoopen')?.replaceChildren(document.createTextNode(state.auto_open ? 'On' : 'Off'));
    const intakeBits = [state.collect_name && 'name', state.collect_email && 'email', state.collect_phone && 'phone'].filter(Boolean);
    $('cbw-prev-intake')?.replaceChildren(document.createTextNode(intakeBits.length ? intakeBits.join(' + ') : 'None'));
  }

  // ------------------------------- save -------------------------------

  async function save({ revealSnippet = false } = {}) {
    // Walk every step's validator before posting so the user sees the
    // first real problem rather than a 422 from the server.
    for (let n = 1; n <= TOTAL_STEPS; n++) {
      const v = validateStep(n);
      if (!v.ok) { showStep(n); toast(v.msg, 'error'); flashInvalid(v.el); return false; }
    }
    try {
      const res = await fetch('/chatbot-widgets/api/save', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
        body: JSON.stringify(state),
      });
      const json = await res.json();
      if (!res.ok || !json.ok) { toast(json.error || 'Save failed — check the highlighted step.', 'error'); return false; }
      state.id = json.id;
      token = json.embed_token;
      document.getElementById('cbw-state-pill').textContent = 'Saved';
      if (revealSnippet) {
        showStep(4);
        const block = document.getElementById('cbw-snippet-block');
        const snip = document.getElementById('cbw-snippet');
        const preview = document.getElementById('cbw-preview-link');
        if (block && snip) {
          block.classList.remove('hidden');
          snip.textContent = `<script defer src="${json.embed_url}"><\/script>`;
        }
        if (preview && json.preview_url) preview.href = json.preview_url;
        toast('Saved — embed ready to copy.', 'success');
      } else {
        toast('Draft saved.', 'success');
      }
      return true;
    } catch (e) {
      toast('Save failed — ' + (e?.message || 'network error'), 'error');
      return false;
    }
  }

  document.getElementById('cbw-save')?.addEventListener('click', () => save({ revealSnippet: false }));
  document.getElementById('cbw-launch')?.addEventListener('click', () => save({ revealSnippet: true }));
  document.getElementById('cbw-snippet-copy')?.addEventListener('click', async () => {
    const text = document.getElementById('cbw-snippet')?.textContent || '';
    if (!text) return;
    try { await navigator.clipboard.writeText(text); toast('Snippet copied.', 'success'); }
    catch { toast('Clipboard blocked — select the snippet manually.', 'info'); }
  });

  // ------------------------------- boot -------------------------------

  ['mode', 'position', 'body_bg_kind'].forEach((k) => {
    const sel = root.querySelector(`input[data-field="${k}"][value="${state[k]}"]`);
    if (sel) sel.checked = true;
  });
  applyVisibility();
  mountPhonePicker();
  renderPreview();
  if (state.id) furthest = TOTAL_STEPS;
  showStep(1);

  // -------------------------- intl-tel-input -------------------------
  // Mount the country picker on the WhatsApp number input. We keep
  // state.target_whatsapp_cc (the +CC string) and
  // state.target_whatsapp_number (national digits) in sync with the
  // widget so the rest of the wizard's validation and the save POST
  // work without changes.
  function mountPhonePicker() {
    const phoneInput = root.querySelector('[data-cbw-phone]');
    if (!phoneInput || phoneInput.__itiMounted) return;
    phoneInput.__itiMounted = true;

    const ccHidden = root.querySelector('input[data-field="target_whatsapp_cc"]');

    // Seed: if editing a saved widget with cc + number already set,
    // intl-tel-input understands a full E.164 string via setNumber.
    const seedCc      = String(state.target_whatsapp_cc || '').replace(/[^\d+]/g, '');
    const seedNumber  = String(state.target_whatsapp_number || '').replace(/\D+/g, '');
    const seedE164    = (seedCc && seedNumber) ? (seedCc.startsWith('+') ? seedCc : '+' + seedCc) + seedNumber : '';

    // Platform default country from <meta name="default-country-iso"> stamped
    // by the layout (admin picks this in /admin/settings/general).
    const _defIso = (document.querySelector('meta[name="default-country-iso"]')?.content || 'in').toLowerCase();
    const iti = intlTelInput(phoneInput, {
      initialCountry: _defIso,
      separateDialCode: true,
      nationalMode: false,
      autoPlaceholder: 'aggressive',
      // Portal the country list out of any overflow:hidden parents
      // (the stepper card has rounded-2xl + overflow-hidden).
      dropdownContainer: document.body,
    });

    if (seedE164) {
      try { iti.setNumber(seedE164); } catch { phoneInput.value = seedNumber; }
    }

    function syncCountry() {
      const data = iti.getSelectedCountryData();
      if (!data || !data.dialCode) return;
      const cc = '+' + data.dialCode;
      state.target_whatsapp_cc = cc;
      if (ccHidden) ccHidden.value = cc;
    }
    function syncNumber() {
      // Strip everything non-digit; the dial code lives on cc.
      state.target_whatsapp_number = (phoneInput.value || '').replace(/\D+/g, '');
    }

    phoneInput.addEventListener('countrychange', syncCountry);
    phoneInput.addEventListener('input', syncNumber);
    phoneInput.addEventListener('blur', () => {
      // Normalise to the national chunk after dial code on blur so a
      // pasted E.164 (e.g. +9198765…) doesn't end up double-counting
      // the country code.
      try {
        const e164 = iti.getNumber();
        if (e164) {
          const cc = state.target_whatsapp_cc || '';
          const stripped = e164.startsWith(cc) ? e164.slice(cc.length) : e164.replace(/^\+\d+/, '');
          state.target_whatsapp_number = stripped.replace(/\D+/g, '');
        }
      } catch { /* keep raw */ }
    });
    syncCountry();
    syncNumber();
  }
}
