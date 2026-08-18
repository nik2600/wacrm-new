/*
 * /meta-ads/create — live ad preview.
 *
 * Wires every text/select input + the file picker to the right-side
 * Ad-preview pane. Defensive: every getElementById call uses optional
 * chaining so a missing element doesn't crash the whole init.
 */
import TomSelect from 'tom-select';
import 'tom-select/dist/css/tom-select.css';
import initAdvancedTargeting from './meta-ads-targeting.js';

export default function init() {
    initAdvancedTargeting();
    const $ = (id) => document.getElementById(id);
    const v = (id) => $(id)?.value ?? '';

    // Tom Select chips — curated dropdowns instead of free-text comma
    // input. Countries ships ISO codes; interests ships catalog names
    // that the server resolves to Meta IDs at sync time.
    const countriesEl = $('countries');
    const interestsEl = $('interests');
    let countriesTs = null;
    let interestsTs = null;
    if (countriesEl && !countriesEl.tomselect) {
        countriesTs = new TomSelect(countriesEl, {
            plugins: ['remove_button', 'clear_button'],
            placeholder: 'Pick one or more countries…',
            maxOptions: null,
            hidePlaceholder: false,
            searchField: ['text', 'value'],
        });
    }
    if (interestsEl && !interestsEl.tomselect) {
        interestsTs = new TomSelect(interestsEl, {
            plugins: ['remove_button', 'clear_button'],
            placeholder: 'Pick interests (empty = broad targeting)…',
            maxOptions: null,
            hidePlaceholder: false,
            searchField: ['text'],
        });
    }

    function normalizeUrlLabel(value) {
        if (!value) return 'yourwebsite.com';
        return value.replace(/^https?:\/\//, '').replace(/\/$/, '');
    }

    function updatePreview() {
        const objective = v('objective');
        const adType    = v('ad-type') || 'ctwa';
        // CTWA detail fields (phone / prefilled message / CTA) apply ONLY
        // to the Click-to-WhatsApp ad type; the legacy ctwa-enabled toggle
        // still gates them within that type. For Instagram link ads and
        // Click-to-Instagram-DM they're hidden — the website URL + image
        // (and the connected IG account as identity) drive those.
        const ctwa      = adType === 'ctwa' && ($('ctwa-enabled')?.checked || false);
        $('ctwa-fields')?.classList.toggle('hidden', !ctwa);
        $('destination-wrap')?.classList.toggle('opacity-50', ctwa);

        if ($('ad-headline')) $('ad-headline').textContent = v('headline') || 'Your headline appears here';
        if ($('ad-body'))     $('ad-body').textContent     = v('body')     || 'Write ad copy to preview it here.';
        if ($('ad-url'))      $('ad-url').textContent      = ctwa ? 'wa.me/' + (v('ctwa-phone') || 'your-number') : normalizeUrlLabel(v('destination'));
        if ($('ad-cta'))      $('ad-cta').textContent      = ctwa ? 'Send Message' : (objective === 'LINK_CLICKS' ? 'Learn More' : 'Apply Now');

        const objSelect = $('objective');
        if ($('preview-objective-pill') && objSelect?.selectedOptions?.length) {
            const lbl = objSelect.selectedOptions[0].textContent || '';
            $('preview-objective-pill').textContent = lbl.split(/[—-]/)[0].trim() || 'Messages';
        }
        if ($('phone-message')) $('phone-message').textContent = v('ctwa-message') || 'Hi, I am interested.';

        if ($('headline-count')) $('headline-count').textContent = (v('headline') || '').length;
        if ($('body-count'))     $('body-count').textContent     = (v('body') || '').length;
        if ($('interest-count')) {
            // Tom Select stores its picked values on the original
            // <select multiple>; fall back to comma-split for the legacy
            // textarea shape in case the picker failed to mount.
            let picked = 0;
            if (interestsTs) picked = (interestsTs.getValue() || []).length;
            else if ($('interests')) {
                const raw = (v('interests') || '');
                picked = raw.split(',').map((x) => x.trim()).filter(Boolean).length;
            }
            $('interest-count').textContent = picked;
        }
    }

    // Wire every form-side input (id-based) to the preview. Skip ones
    // that don't exist instead of throwing.
    const fields = ['campaign-name', 'objective', 'budget', 'ad-type', 'ctwa-enabled', 'ctwa-message', 'ctwa-phone',
                    'headline', 'body', 'destination', 'interests'];
    fields.forEach((id) => {
        const el = $(id);
        if (!el) return;
        el.addEventListener('input',  updatePreview);
        el.addEventListener('change', updatePreview);
    });

    if ($('phone-time')) $('phone-time').textContent = new Date().toTimeString().slice(0, 5);

    // Bind the inline ad-image input directly to the preview's
    // background image. The file tile in the blade is just a styled
    // <label> wrapping a real <input type="file" name="creative_image_file">.
    function wireImageInput(input) {
        if (!input || input.__wired) return;
        input.__wired = true;
        input.addEventListener('change', () => {
            const file = input.files && input.files[0];
            const tile = input.closest('label.file-tile');
            const labelEl = tile?.querySelector('[data-file-label]') || tile?.querySelector('.file-title');
            const subEl   = tile?.querySelector('.file-sub');
            const actionEl= tile?.querySelector('.file-action');
            const mediaEl = $('ad-media');
            const imgEl   = $('ad-media-img');
            const labelMedia = $('ad-media-label');

            if (!file) {
                if (labelEl) labelEl.textContent = 'Choose image';
                if (imgEl)   { imgEl.removeAttribute('src'); imgEl.classList.add('hidden'); }
                if (labelMedia) labelMedia.classList.remove('hidden');
                if (mediaEl) mediaEl.style.backgroundImage = '';
                return;
            }
            if (labelEl) labelEl.textContent = file.name;
            if (subEl)   subEl.textContent   = (file.size / 1024).toFixed(1) + ' KB · ' + (file.type || 'image');
            if (actionEl) { actionEl.textContent = 'Replace'; actionEl.classList.add('text-accent-coral'); }
            if (file.type.startsWith('image/')) {
                const reader = new FileReader();
                reader.onload = (ev) => {
                    if (imgEl) {
                        imgEl.src = ev.target.result;
                        imgEl.classList.remove('hidden');
                    }
                    if (labelMedia) labelMedia.classList.add('hidden');
                    if (mediaEl) {
                        mediaEl.style.backgroundImage = `url('${ev.target.result}')`;
                        mediaEl.style.backgroundSize  = 'cover';
                        mediaEl.style.backgroundPosition = 'center';
                    }
                };
                reader.readAsDataURL(file);
            }
        });
    }
    document.querySelectorAll('input[type="file"][name="creative_image_file"]').forEach(wireImageInput);

    updatePreview();

    // ──────────────────────────────────────────────────────────────
    // Build with AI — modal open/close, model fetch, generate
    // ──────────────────────────────────────────────────────────────
    const aiModal      = $('ai-modal');
    const openAiBtn    = $('open-ai-modal');
    const closeAiBtn   = $('ai-modal-close');
    const cancelAiBtn  = $('ai-modal-cancel');
    const generateBtn  = $('ai-generate');
    const aiError      = $('ai-error');
    const aiEmpty      = $('ai-empty');
    const aiFormBox    = $('ai-form');
    const aiModelSel   = $('ai-model');
    const aiBusiness   = $('ai-business');
    const aiProduct    = $('ai-product');
    const aiObjective  = $('ai-objective');
    const aiCta        = $('ai-cta');
    const aiTone       = $('ai-tone');
    const aiCountries  = $('ai-countries');
    const aiAudience   = $('ai-audience');
    const aiDestination= $('ai-destination');
    const aiWhatsApp   = $('ai-whatsapp');
    const aiPromptTa   = $('ai-prompt');
    const genIcon      = $('ai-generate-icon');
    const genSpin      = $('ai-generate-spin');
    const genLabel     = $('ai-generate-label');

    let modelsLoaded = false;
    let isGenerating = false;

    function openAiModal() {
        if (!aiModal) return;
        showError('');
        aiModal.classList.remove('hidden');
        document.body.style.overflow = 'hidden';
        if (!modelsLoaded) loadModels();
        setTimeout(() => aiBusiness?.focus(), 50);
    }
    function closeAiModal() {
        if (!aiModal) return;
        aiModal.classList.add('hidden');
        document.body.style.overflow = '';
    }
    function showError(msg) {
        if (!aiError) return;
        if (!msg) { aiError.classList.add('hidden'); aiError.textContent = ''; }
        else      { aiError.classList.remove('hidden'); aiError.textContent = msg; }
    }

    async function loadModels() {
        try {
            const res = await fetch('/meta-ads/api/ai-models', {
                headers: { 'Accept': 'application/json' },
                credentials: 'same-origin',
            });
            const json = await res.json();
            const models = Array.isArray(json?.models) ? json.models : [];
            if (models.length === 0) {
                aiFormBox?.classList.add('hidden');
                aiEmpty?.classList.remove('hidden');
                generateBtn?.setAttribute('disabled', 'disabled');
                return;
            }
            aiEmpty?.classList.add('hidden');
            aiFormBox?.classList.remove('hidden');
            generateBtn?.removeAttribute('disabled');
            aiModelSel.innerHTML = '';
            models.forEach((m) => {
                const opt = document.createElement('option');
                opt.value = `${m.provider}|${m.value}`;
                opt.textContent = m.label;
                aiModelSel.appendChild(opt);
            });
            modelsLoaded = true;
        } catch (err) {
            console.warn('[ai-meta-ads] failed to load models', err);
            showError('Could not load AI models. Try again.');
        }
    }

    function setBusy(busy) {
        isGenerating = busy;
        if (busy) {
            generateBtn?.setAttribute('disabled', 'disabled');
            genIcon?.classList.add('hidden');
            genSpin?.classList.remove('hidden');
            if (genLabel) genLabel.textContent = 'Generating…';
        } else {
            generateBtn?.removeAttribute('disabled');
            genIcon?.classList.remove('hidden');
            genSpin?.classList.add('hidden');
            if (genLabel) genLabel.textContent = 'Generate ad copy';
        }
    }

    function getCsrf() {
        const el = document.querySelector('meta[name="csrf-token"]')
            || document.querySelector('input[name="_token"]');
        return el ? (el.getAttribute('content') || el.value) : '';
    }

    async function generate() {
        if (isGenerating) return;
        const business = (aiBusiness?.value || '').trim();
        if (!business) {
            showError('Business name is required.');
            aiBusiness?.focus();
            return;
        }
        const modelValue = aiModelSel?.value || '';
        if (!modelValue) { showError('Pick an AI model.'); return; }
        const [provider, model] = modelValue.split('|');
        showError('');
        setBusy(true);

        const payload = {
            provider,
            model,
            business_name:    business,
            product:          aiProduct?.value || '',
            objective:        aiObjective?.value || '',
            audience:         aiAudience?.value || '',
            countries:        aiCountries?.value || '',
            destination_url:  aiDestination?.value || '',
            cta:              aiCta?.value || '',
            tone:             aiTone?.value || '',
            whatsapp_message: !!aiWhatsApp?.checked,
            custom_prompt:    aiPromptTa?.value || '',
        };

        try {
            const res = await fetch('/meta-ads/api/ai-generate', {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': getCsrf(),
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin',
                body: JSON.stringify(payload),
            });
            const json = await res.json();
            if (!res.ok || !json?.ok) {
                showError(json?.message || `Generation failed (${res.status}).`);
                setBusy(false);
                return;
            }
            applyGeneratedAd(json.ad || {}, payload);
            setBusy(false);
            closeAiModal();
            if (typeof window.toast === 'function') {
                window.toast({ message: 'Ad draft filled from AI.', kind: 'success' });
            }
        } catch (err) {
            console.warn('[ai-meta-ads] generate failed', err);
            showError('Network error talking to the server.');
            setBusy(false);
        }
    }

    function applyGeneratedAd(ad, brief) {
        if (!ad || typeof ad !== 'object') return;

        // Names — campaign + adset.
        const nameEl   = $('campaign-name'); if (nameEl && ad.campaign_name) nameEl.value = ad.campaign_name;
        const adsetEl  = $('adset-name');    if (adsetEl && ad.adset_name)    adsetEl.value = ad.adset_name;

        // Creative copy.
        const headEl   = $('headline'); if (headEl && ad.headline) headEl.value = ad.headline;
        const bodyEl   = $('body');     if (bodyEl && ad.body)     bodyEl.value = ad.body;
        // Interests: AI returns either an array or a comma-separated
        // string of catalog NAMES. Tom Select silently ignores any
        // names it doesn't know about — that's safer than wiping the
        // dropdown when the model invents a label.
        if (ad.interests && interestsTs) {
            const list = Array.isArray(ad.interests)
                ? ad.interests
                : String(ad.interests).split(',').map((x) => x.trim()).filter(Boolean);
            interestsTs.setValue(list, /*silent*/ false);
        }

        // Targeting hints.
        if (ad.suggested_age_min) {
            const ageMin = $('age-min'); if (ageMin) ageMin.value = ad.suggested_age_min;
        }
        if (ad.suggested_age_max) {
            const ageMax = $('age-max'); if (ageMax) ageMax.value = ad.suggested_age_max;
        }
        if (ad.suggested_countries && countriesTs) {
            // AI returns ISO codes — multi or single, comma OR space
            // delimited. Normalise then push into Tom Select.
            const codes = (Array.isArray(ad.suggested_countries)
                ? ad.suggested_countries
                : String(ad.suggested_countries).split(/[,\s]+/))
                .map((x) => String(x).trim().toUpperCase())
                .filter(Boolean);
            countriesTs.setValue(codes, /*silent*/ false);
        }

        // Destination + objective from the brief (not the AI output —
        // these are operator choices, not generated copy).
        if (brief?.destination_url) {
            const destEl = $('destination'); if (destEl) destEl.value = brief.destination_url;
        }
        const objectiveMap = {
            'Messages (start WhatsApp chats)': 'MESSAGES',
            'Link clicks':       'LINK_CLICKS',
            'Conversions':       'CONVERSIONS',
            'Lead generation':   'LEAD_GENERATION',
            'Reach':             'REACH',
            'Brand awareness':   'BRAND_AWARENESS',
            'Video views':       'VIDEO_VIEWS',
        };
        const objSel = $('objective');
        if (objSel && brief?.objective && objectiveMap[brief.objective]) {
            objSel.value = objectiveMap[brief.objective];
        }

        // CTWA — enable the section and paste the message if the user
        // checked the WhatsApp box in the brief.
        if (brief?.whatsapp_message) {
            const ctwa = $('ctwa-enabled');
            if (ctwa) { ctwa.checked = true; }
            const msg  = $('ctwa-message');
            if (msg && ad.ctwa_message) msg.value = ad.ctwa_message;
            const cta  = $('ctwa-cta');
            if (cta) cta.value = 'WHATSAPP_MESSAGE';
        }

        updatePreview();
    }

    openAiBtn?.addEventListener('click', openAiModal);
    closeAiBtn?.addEventListener('click', closeAiModal);
    cancelAiBtn?.addEventListener('click', closeAiModal);
    generateBtn?.addEventListener('click', generate);
    aiModal?.addEventListener('click', (e) => { if (e.target === aiModal) closeAiModal(); });
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && aiModal && !aiModal.classList.contains('hidden')) {
            closeAiModal();
        }
    });
}
