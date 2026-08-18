// WhatsApp Link builder — 3-step wizard (Destination → Starter → Tracking).
// Mints a /l/{slug} short link + QR. Same stepper shape as the chatbot
// widget and AI training builders.
import intlTelInput from 'intl-tel-input/intlTelInputWithUtils';
import 'intl-tel-input/styles';

export default function init() {
  const root = document.getElementById('wcl-builder');
  if (!root) return;

  const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
  const defaults = JSON.parse(root.dataset.defaults || '{}');
  const state = { ...defaults };

  const toast = (m, kind = 'success') => (window.toast ? window.toast(m, kind) : null);
  const TOTAL_STEPS = 3;
  let current  = 1;
  let furthest = state.id ? TOTAL_STEPS : 1;

  // ---------------------------- form binding ----------------------------

  root.querySelectorAll('[data-field]').forEach((el) => {
    const key = el.dataset.field;
    const v = state[key];
    if (el.type === 'checkbox') el.checked = !!v;
    else el.value = v ?? '';
    el.addEventListener('input',  () => readInto(el));
    el.addEventListener('change', () => readInto(el));
  });
  function readInto(el) {
    const key = el.dataset.field;
    if (el.type === 'checkbox') state[key] = el.checked;
    else state[key] = el.value;
    renderPreview();
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
    document.getElementById('wcl-cur').textContent = String(n);
    document.getElementById('wcl-prev').disabled = (n === 1);
    document.getElementById('wcl-next').style.display = (n === TOTAL_STEPS) ? 'none' : '';
    document.getElementById('wcl-mint').style.display = (n === TOTAL_STEPS) ? '' : 'none';
    paintStepper();
  }

  root.querySelectorAll('.step-node').forEach((node) => {
    node.addEventListener('click', () => {
      const target = parseInt(node.dataset.n, 10);
      if (target <= furthest) showStep(target);
      else toast('Use Next to move forward — each step has a few required fields.', 'info');
    });
  });
  document.getElementById('wcl-prev').addEventListener('click', () => { if (current > 1) showStep(current - 1); });
  document.getElementById('wcl-next').addEventListener('click', () => {
    const err = validateStep(current);
    if (err) { toast(err, 'error'); return; }
    showStep(current + 1);
  });

  function validateStep(n) {
    if (n === 1) {
      if (!String(state.name || '').trim()) return 'Add an internal label first.';
      const digits = String(state.phone_number || '').replace(/\D+/g, '');
      if (digits.length < 6) return 'Phone number looks too short — include enough digits.';
    }
    if (n === 3) {
      const slug = String(state.slug || '').trim();
      if (slug && !/^[a-z0-9-]+$/.test(slug)) return 'Slug can only contain lowercase letters, numbers, and dashes.';
    }
    return null;
  }

  // --------------------------- live preview ---------------------------

  function liveWaUrl() {
    const cc = String(state.country_code || '').replace(/\D+/g, '');
    const num = String(state.phone_number || '').replace(/\D+/g, '');
    if (!cc || !num) return null;
    const msg = String(state.welcome_message || '').trim();
    let url = `https://wa.me/${cc}${num}`;
    if (msg) url += '?text=' + encodeURIComponent(msg);
    return url;
  }

  function renderPreview() {
    const $ = (id) => document.getElementById(id);
    const slugShown = String(state.slug || '').trim() || '—';
    $('wcl-prev-short').textContent = `${location.origin}/l/${slugShown}`;
    const wa = liveWaUrl();
    $('wcl-prev-wa').textContent = wa || 'https://wa.me/—';
    const bubble = $('wcl-prev-bubble');
    if (bubble) {
      const msg = String(state.welcome_message || '').trim();
      bubble.textContent = msg || '(empty — visitor lands in WhatsApp with an empty chat)';
      bubble.classList.toggle('italic', !msg);
      bubble.classList.toggle('text-ink-500', !msg);
    }
    $('wcl-prev-status').textContent = state.status === 'paused' ? 'Paused' : 'Active';
    $('wcl-prev-expiry').textContent = state.expires_at ? state.expires_at : 'none';
  }

  // ------------------------------- mint -------------------------------

  async function mint() {
    for (let n = 1; n <= TOTAL_STEPS; n++) {
      const err = validateStep(n);
      if (err) { showStep(n); toast(err, 'error'); return; }
    }
    try {
      const res = await fetch('/wa-links/api/save', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
        body: JSON.stringify(state),
      });
      const json = await res.json();
      if (!res.ok || !json.ok) { toast(json.error || 'Mint failed — check the fields.', 'error'); return; }
      state.id   = json.id;
      state.slug = json.slug;
      document.getElementById('wcl-state-pill').textContent = 'Saved';
      revealMintPanel(json.short_url, json.wa_url);
      toast('Short link minted.', 'success');
    } catch (e) {
      toast('Mint failed — ' + (e?.message || 'network error'), 'error');
    }
  }

  document.getElementById('wcl-save').addEventListener('click', mint);
  document.getElementById('wcl-mint').addEventListener('click', mint);

  function revealMintPanel(shortUrl, waUrl) {
    const block = document.getElementById('wcl-mint-block');
    block.classList.remove('hidden');
    document.getElementById('wcl-short').textContent = shortUrl;
    document.getElementById('wcl-wa').textContent    = waUrl;
    document.getElementById('wcl-open-short').href   = shortUrl;
    // Free QR generator — qrserver.com is a long-running public API.
    // 256px is enough for business-card print quality.
    const qrSrc = `https://api.qrserver.com/v1/create-qr-code/?size=256x256&data=${encodeURIComponent(shortUrl)}`;
    const qrImg = document.getElementById('wcl-qr');
    const qrDl  = document.getElementById('wcl-qr-dl');
    qrImg.src = qrSrc;
    qrDl.href = qrSrc;
  }

  document.getElementById('wcl-copy-short').addEventListener('click', async () => {
    const text = document.getElementById('wcl-short')?.textContent || '';
    if (!text) return;
    try { await navigator.clipboard.writeText(text); toast('Short link copied.', 'success'); }
    catch { toast('Clipboard blocked — select the link manually.', 'info'); }
  });

  // -------------------------- intl-tel-input -------------------------

  function mountPhonePicker() {
    const phoneInput = root.querySelector('[data-wcl-phone]');
    if (!phoneInput || phoneInput.__itiMounted) return;
    phoneInput.__itiMounted = true;

    const ccHidden = root.querySelector('input[data-field="country_code"]');
    const numHidden = root.querySelector('input[data-field="phone_number"]');

    const seedCc      = String(state.country_code || '').replace(/[^\d+]/g, '');
    const seedNumber  = String(state.phone_number || '').replace(/\D+/g, '');
    const seedE164    = (seedCc && seedNumber) ? (seedCc.startsWith('+') ? seedCc : '+' + seedCc) + seedNumber : '';

    // Default country from layout meta (admin's /admin/settings/general pick).
    const _defIso = (document.querySelector('meta[name="default-country-iso"]')?.content || 'in').toLowerCase();
    const iti = intlTelInput(phoneInput, {
      initialCountry: _defIso,
      separateDialCode: true,
      nationalMode: false,
      autoPlaceholder: 'aggressive',
      dropdownContainer: document.body,
    });

    if (seedE164) {
      try { iti.setNumber(seedE164); } catch { phoneInput.value = seedNumber; }
    } else if (seedNumber) {
      phoneInput.value = seedNumber;
    }

    function syncCountry() {
      const data = iti.getSelectedCountryData();
      if (!data || !data.dialCode) return;
      const cc = '+' + data.dialCode;
      state.country_code = cc;
      if (ccHidden) ccHidden.value = cc;
      renderPreview();
    }
    function syncNumber() {
      state.phone_number = (phoneInput.value || '').replace(/\D+/g, '');
      if (numHidden) numHidden.value = state.phone_number;
      renderPreview();
    }
    phoneInput.addEventListener('countrychange', syncCountry);
    phoneInput.addEventListener('input', syncNumber);
    phoneInput.addEventListener('blur', () => {
      try {
        const e164 = iti.getNumber();
        if (e164) {
          const cc = state.country_code || '';
          const stripped = e164.startsWith(cc) ? e164.slice(cc.length) : e164.replace(/^\+\d+/, '');
          state.phone_number = stripped.replace(/\D+/g, '');
          if (numHidden) numHidden.value = state.phone_number;
          renderPreview();
        }
      } catch { /* keep raw */ }
    });
    syncCountry();
    syncNumber();
  }

  // -------------------------------- boot --------------------------------

  mountPhonePicker();
  renderPreview();
  showStep(1);

  // If editing a saved row, reveal the mint panel so the operator
  // can copy + share without having to hit Mint again.
  if (state.id) {
    const existingSlug  = root.dataset.existingSlug || '';
    const existingShort = root.dataset.existingShort || '';
    if (existingSlug && existingShort) {
      const wa = liveWaUrl();
      if (wa) revealMintPanel(existingShort, wa);
    }
  }
}
