import { initVariableMap } from '../template-var-map.js';
import { fillSamples, loadAttributeValues } from '../template-vars.js';
import { themeColor } from '../theme-colors.js';

export default function init() {
    const $ = (id) => document.getElementById(id);

    // Template NAME hygiene. Meta only accepts lowercase letters, digits and
    // underscores in a template name — a space (or any other character) makes
    // the submit fail, and a duplicate name is rejected too. So sanitise the
    // field LIVE: as the operator types, lowercase it and replace every space /
    // invalid char with an underscore — done 1:1 so the caret never jumps —
    // then on blur collapse repeated underscores and trim the edges for a clean
    // "spring_promo_v3" shape. This is the same rule the AI-fill slug already
    // applies; now a hand-typed name gets it too.
    (function sanitizeTemplateName() {
        const el = $('tpl-name');
        if (!el) return;
        el.addEventListener('input', () => {
            const start = el.selectionStart;
            const cleaned = el.value.toLowerCase().replace(/[^a-z0-9_]/g, '_');
            if (cleaned !== el.value) {
                el.value = cleaned;
                // 1:1 replacement keeps the length identical → restore the caret
                // exactly where it was so typing mid-string isn't disrupted.
                try { el.setSelectionRange(start, start); } catch (_) {}
            }
        });
        el.addEventListener('blur', () => {
            el.value = el.value.replace(/_{2,}/g, '_').replace(/^_+|_+$/g, '').slice(0, 60);
        });
    })();

    // Send-channel → Twilio Content SID visibility. The SID field only
    // applies to the Twilio channel, so show it only when that radio is
    // picked and hide it for Unofficial API / WABA. Runs once on load too
    // (and is a no-op on single-engine pages where there's no SID block).
    (function wireChannelToggle() {
        const radios = document.querySelectorAll('input[name="channel"]');
        const sidBlock = document.querySelector('[data-twilio-sid-block]');
        const builder = document.querySelector('[data-builder-sections]');
        const preview = document.querySelector('.preview-col');
        const form = document.getElementById('templateForm');
        if (!sidBlock && !builder) return;
        const sync = () => {
            const checked = document.querySelector('input[name="channel"]:checked');
            const sel = checked ? checked.value : (radios[0] ? radios[0].value : '');
            const isTwilio = sel === 'twilio';
            // Twilio: show the Content SID, hide the Header/Body/Buttons/Carousel
            // builder + live preview (its content is built in Twilio). Other
            // channels: hide the SID, show the builder.
            if (sidBlock) sidBlock.classList.toggle('hidden', !isTwilio);
            if (builder) builder.classList.toggle('hidden', isTwilio);
            if (preview) preview.classList.toggle('hidden', isTwilio);
            // Collapse the 2-col grid to full width when the preview is hidden.
            if (form) form.style.gridTemplateColumns = isTwilio ? '1fr' : '';
        };
        radios.forEach((r) => r.addEventListener('change', sync));
        sync();
    })();

      // Meta template-body guard. Meta REJECTS a WABA template whose body STARTS
      // or ENDS with a {{N}} variable, or has two variables with no text between
      // them. Catch it on submit so the operator fixes it here instead of after
      // a submit → Meta round-trip that comes back "rejected". Only enforced for
      // the WABA channel (Meta approval); Unofficial / Twilio bodies are free.
      (function guardMetaBodyVariables() {
        const form = document.getElementById('templateForm');
        if (!form) return;
        form.addEventListener('submit', (e) => {
          const chan = document.querySelector('input[name="channel"]:checked');
          // No channel selector on single-engine installs → the on-screen hint
          // already says "Meta requires…", so assume WABA and validate.
          const isWaba = chan ? chan.value === 'waba' : true;
          if (!isWaba) return;
          // Visible body field: standard = tpl-body, carousel = car-body.
          let bodyEl = null;
          for (const id of ['tpl-body', 'car-body']) {
            const el = document.getElementById(id);
            if (el && el.offsetParent !== null) { bodyEl = el; break; }
          }
          const body = (bodyEl && bodyEl.value ? bodyEl.value : '').trim();
          if (body === '') return;  // the required-field check handles empty
          const startsVar = /^\{\{[^{}]+\}\}/.test(body);
          const endsVar   = /\{\{[^{}]+\}\}$/.test(body);
          const adjacent  = /\}\}\s*\{\{/.test(body);
          if (!startsVar && !endsVar && !adjacent) return;
          e.preventDefault();
          let msg;
          if (startsVar)      msg = 'Meta won\'t approve a template that STARTS with a variable like {{1}}. Add a word before it — e.g. "Hi {{1}}, …".';
          else if (endsVar)   msg = 'Meta won\'t approve a template that ENDS with a variable like {{1}}. Add a word after it — e.g. "… see you {{1}}.".';
          else                msg = 'Meta won\'t approve two variables next to each other ({{1}}{{2}}). Put text between them — e.g. "{{1}} and {{2}}".';
          if (window.toast) window.toast(msg, 'error'); else alert(msg);
          if (bodyEl) bodyEl.focus();
          return false;
        });
      })();

      // Attachment live-preview media — a CSS background can only paint an
      // image, so render the real <img>/<video>/doc per type. `picked*` is the
      // freshly chosen file (no saved file exists on the create page).
      let pickedSrc = "", pickedType = "";
      function setAttachPreview(at) {
        const pa = $("pp-attach");
        if (!pa) return;
        if (!at || at === "none" || at === "location") { pa.classList.add("hidden"); return; }
        pa.classList.remove("hidden");
        const src = (pickedSrc && pickedType === at) ? pickedSrc : "";
        pa.removeAttribute("style");
        if (!src) {
          pa.className = "pp-attachment bg-[#DFF1ED] rounded-[5px] h-20 mb-[5px] flex items-center justify-center text-wa-deep text-[10.5px] font-mono";
          pa.innerHTML = "";
          pa.textContent = at === "image" ? "Image preview" : at === "video" ? "Video preview" : "Document preview";
          return;
        }
        pa.textContent = "";
        pa.className = "pp-attachment rounded-[5px] mb-[5px] overflow-hidden bg-black";
        if (at === "image") {
          pa.innerHTML = `<img src="${src}" alt="" style="width:100%;height:120px;object-fit:cover;display:block">`;
        } else if (at === "video") {
          pa.innerHTML = `<video src="${src}" controls muted playsinline preload="metadata" style="width:100%;height:120px;object-fit:cover;display:block;background:#000"></video>`;
        } else {
          pa.className = "pp-attachment bg-[#DFF1ED] rounded-[5px] mb-[5px]";
          pa.innerHTML = `<a href="${src}" target="_blank" rel="noopener" style="display:flex;align-items:center;gap:8px;padding:10px;color:${themeColor('wa-deep')};font-size:10.5px;font-family:monospace;text-decoration:none">`
            + `<svg viewBox="0 0 16 16" width="22" height="22" fill="none" stroke="currentColor" stroke-width="1.4"><path d="M4 2h5l3 3v9H4z"/><path d="M9 2v3h3"/></svg>`
            + `<span>Document — tap to open</span></a>`;
        }
      }

      // Live preview substitutes a real SAMPLE VALUE for each {{token}}
      // (real attribute demo value → friendly built-in → {{key}} pill).
      // Pure paint — never sent. Repaints once the workspace values load.
      let attrValues = {};
      loadAttributeValues().then((v) => { attrValues = v || {}; renderPreview(); });

      // Read ?type= from the URL set by the modal in /templates
      const urlType = (new URLSearchParams(location.search).get("type") || "standard").toLowerCase();
      const isCarousel = urlType === "carousel";
      $("standard-sections").classList.toggle("hidden", isCarousel);
      $("standard-sections").querySelectorAll("input, select, textarea").forEach(el => el.disabled = isCarousel);

      $("carousel-sections").classList.toggle("hidden", !isCarousel);
      $("carousel-sections").querySelectorAll("input, select, textarea").forEach(el => el.disabled = !isCarousel);
      // Reflect format in the page chrome
      const fmtCrumb = document.getElementById("fmt-crumb");
      const fmtTitle = document.getElementById("fmt-title");
      const fmtPill  = document.getElementById("fmt-pill");
      if (fmtCrumb) fmtCrumb.textContent = isCarousel ? "Templates / New / Carousel" : "Templates / New / Standard";
      if (fmtTitle) fmtTitle.innerHTML = isCarousel ? 'Create <span class="italic text-wa-deep">carousel</span> template' : 'Create <span class="italic text-wa-deep">standard</span> template';
      if (fmtPill)  fmtPill.textContent = isCarousel ? "Carousel" : "Standard";

      // Button-type tabs — actually drive visibility of rows so users
      // see what they're building. Previously the tab only toggled
      // `.active` cosmetically and "Add button" always added a CTA
      // row, leaving Quick reply + Mix tabs feeling broken.
      //
      // Modes:
      //   cta   → show CTA rows only,    "Add button" appends a CTA row
      //   reply → show reply rows only,  "Add button" appends a reply row
      //   mix   → show both,             "Add button" opens a picker
      // Unified buttons editor. EVERY row is the same shape — a type
      // dropdown (Visit website / Call phone / Copy code / Quick reply) +
      // text + value. So you can freely MIX CTAs and quick replies in one
      // template; each row stays independently switchable via its dropdown.
      // The CTA / Quick reply / Mix tabs only choose the DEFAULT type for
      // the *next* row you add — they never hide existing rows. Hard cap of
      // 3 buttons — WhatsApp reliably renders only 3, so the editor never
      // lets you add a 4th in any mode.
      const BTN_MAX = 3;
      let btnDefaultType = "visit_website";
      function refreshAddBtn() {
        const addBtn = document.getElementById("btn-add");
        if (!addBtn) return;
        const count = document.querySelectorAll("#btn-list .btn-row").length;
        addBtn.classList.toggle("opacity-40", count >= BTN_MAX);
        addBtn.classList.toggle("pointer-events-none", count >= BTN_MAX);
      }
      function applyBtnMode(mode) {
        btnDefaultType = mode === "reply" ? "quick_reply" : "visit_website";
        const addBtn = document.getElementById("btn-add");
        const lbl = addBtn && addBtn.querySelector("[data-add-label]");
        if (lbl) lbl.textContent =
          mode === "reply" ? "Add quick reply"
          : mode === "mix" ? "Add button"
          : "Add CTA button";

        // Transform the EXISTING rows to match the chosen mode — the tabs
        // weren't doing this before, so picking "Quick reply" left the CTA
        // type dropdown + URL field visible. A quick reply has text only.
        document.querySelectorAll("#btn-list .btn-row").forEach((row) => {
          const sel = row.querySelector(".cta-action");
          const val = row.querySelector(".cta-value");
          if (!sel) return;
          if (mode === "reply") {
            sel.value = "quick_reply";          // submit as quick_reply
            sel.style.display = "none";         // hide the type dropdown
            if (val) val.style.display = "none";// hide the URL/value field
            row.style.gridTemplateColumns = "1fr 28px"; // text spans, no gaps
          } else if (mode === "cta") {
            if (sel.value === "quick_reply") sel.value = "visit_website";
            sel.style.display = "";
            if (val) { val.style.display = ""; val.placeholder = ctaPlaceholder(sel.value); }
            row.style.gridTemplateColumns = "";  // back to the class default
          } else { // mix — per-row choice; show dropdown, value follows its type
            sel.style.display = "";
            row.style.gridTemplateColumns = "";
            if (val) val.style.display = (sel.value === "quick_reply") ? "none" : "";
          }
        });
        if (typeof renderPreview === "function") renderPreview();
      }
      document.querySelectorAll("#btn-type .seg-btn").forEach((b) => b.addEventListener("click", () => {
        document.querySelectorAll("#btn-type .seg-btn").forEach((x) => x.classList.toggle("active", x === b));
        applyBtnMode(b.dataset.bt || "cta");
      }));

      const ta = $("tpl-body");
      function format(kind) {
        const map = { bold:"*", italic:"_", strike:"~", code:"```" };
        const m = map[kind];
        const s = ta.selectionStart, e = ta.selectionEnd, sel = ta.value.substring(s,e) || "text";
        ta.value = ta.value.substring(0,s) + m + sel + m + ta.value.substring(e);
        ta.focus(); ta.setSelectionRange(s + m.length, s + m.length + sel.length);
        renderPreview();
      }
      function insertVar(name) {
        const tok = "{{" + name + "}}", s = ta.selectionStart;
        ta.value = ta.value.substring(0,s) + tok + ta.value.substring(ta.selectionEnd);
        ta.focus(); ta.setSelectionRange(s + tok.length, s + tok.length);
        renderPreview();
      }
      function addCtaButton(defaultType = "visit_website") {
        if (document.querySelectorAll("#btn-list .btn-row").length >= BTN_MAX) return;
        const r = document.createElement("div");
        r.className = "btn-row grid grid-cols-[140px_1fr_1fr_28px] gap-1.5 items-center"; r.dataset.kind = "cta";
        r.innerHTML = `
          <select name="button_type[]" class="ctrl w-full px-[11px] py-[7px] border border-paper-200 rounded-lg bg-white text-[12.5px] text-ink-900 transition leading-[1.4] font-sans placeholder:text-[#8A9A95] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10 cta-action"><option value="visit_website">Visit website</option><option value="call_phone">Call phone</option><option value="copy_code">Copy code</option><option value="quick_reply">Quick reply</option></select>
          <input type="text" name="button_text[]"  class="ctrl w-full px-[11px] py-[7px] border border-paper-200 rounded-lg bg-white text-[12.5px] text-ink-900 transition leading-[1.4] font-sans placeholder:text-[#8A9A95] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10 cta-text" maxlength="25" placeholder="Button text">
          <input type="text" name="button_value[]" class="ctrl w-full px-[11px] py-[7px] border border-paper-200 rounded-lg bg-white text-[12.5px] text-ink-900 transition leading-[1.4] font-sans placeholder:text-[#8A9A95] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10 cta-value" placeholder="https://...">
          <span class="iconbtn w-7 h-7 rounded-[7px] inline-flex items-center justify-center text-ink-500 cursor-pointer transition hover:bg-[#FFEDE8] hover:text-accent-coral" onclick="removeBtn(this)" title="Remove"><svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 4l8 8M12 4l-8 8"/></svg></span>
        `;
        $("btn-list").appendChild(r);
        const selEl = r.querySelector(".cta-action");
        if (selEl) selEl.value = defaultType;
        r.querySelectorAll("input,select").forEach((i) => i.addEventListener("input", renderPreview));
        wireCtaKind(r);
        refreshAddBtn();
        renderPreview();
      }
      // Update the value field's placeholder to match the CTA kind so the
      // operator knows what to type (URL vs phone vs copy-code). The field
      // still submits under button_value[] for all kinds — processButtons()
      // reads {type,text,value} regardless.
      function ctaPlaceholder(kind) {
        if (kind === "call_phone") return "+15551234567";
        if (kind === "copy_code") return "PROMO50";
        return "https://...";
      }
      function wireCtaKind(row) {
        const sel = row.querySelector(".cta-action");
        const val = row.querySelector(".cta-value");
        if (!sel || !val) return;
        const sync = () => {
          if (sel.value === "quick_reply") {
            // Quick replies have no destination — hide the value field.
            val.style.display = "none";
          } else {
            val.style.display = "";
            val.placeholder = ctaPlaceholder(sel.value);
          }
        };
        sel.addEventListener("change", () => { sync(); renderPreview(); });
        sync();
      }
      function removeBtn(b) { b.closest(".btn-row").remove(); refreshAddBtn(); renderPreview(); }

      // Quick replies now use the SAME unified row as CTAs (the row's type
      // dropdown set to "Quick reply"), so this just adds a unified row
      // pre-set to quick_reply. Kept for the window.addQuickReply export.
      function addQuickReply() { addCtaButton("quick_reply"); }
      // Wrapper invoked by the "Add button" link — adds a unified row whose
      // dropdown defaults to the active tab's type but can be switched to
      // ANY of the 4 kinds, so CTAs + quick replies mix freely. Capped at 10.
      function addBtnRow() {
        if (document.querySelectorAll("#btn-list .btn-row").length >= BTN_MAX) return;
        addCtaButton(btnDefaultType);
      }

      let carCardCount = 0;
      function updateCardIndices() {
          document.querySelectorAll('#car-cards .hairline').forEach((card, idx) => {
              const idxInput = card.querySelector('.card-idx-input');
              if (idxInput) idxInput.value = idx;
              const imgInput = card.querySelector('input[type="file"]');
              if (imgInput) imgInput.name = `carousel_images[${idx}]`;
          });
      }

      function addCarouselCard() {
        if (carCardCount >= 10) return;
        if (carCardCount === 0) $("car-cards").innerHTML = "";
        carCardCount++;
        $("card-counter").textContent = carCardCount + "/10";
        const card = document.createElement("div");
        card.className = "hairline border border-paper-200 rounded-lg p-3 bg-paper-0";
        card.innerHTML = `
          <div class="flex items-center justify-between mb-2">
            <div class="text-[12px] font-semibold mono font-mono">Card</div>
            <span class="iconbtn w-7 h-7 rounded-[7px] inline-flex items-center justify-center text-ink-500 cursor-pointer transition hover:bg-[#FFEDE8] hover:text-accent-coral" onclick="this.closest('.hairline').remove();carCardCount--;document.getElementById('card-counter').textContent=carCardCount+'/10';updateCardIndices();" title="Remove"><svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 4l8 8M12 4l-8 8"/></svg></span>
          </div>
          <div class="grid grid-cols-1 md:grid-cols-2 gap-2 mb-2">
            <div>
              <label class="lbl text-[11.5px] font-semibold text-ink-700 flex items-center justify-between gap-2 mb-[5px]">Image</label>
              <div class="file-tile flex items-center gap-2.5 px-[11px] py-2.5 border border-dashed border-wa-deep rounded-lg bg-paper-0 cursor-pointer transition hover:bg-wa-bubble hover:border-solid [&.has-file]:border-solid [&.has-file]:bg-wa-bubble" data-file-tile data-accept="image/*">
                <span class="file-icon w-[34px] h-[34px] rounded-lg bg-[#DFF1ED] text-wa-deep inline-flex items-center justify-center shrink-0"><svg viewBox="0 0 16 16" class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.6"><rect x="2" y="3" width="12" height="10" rx="1.5"/><circle cx="6" cy="7" r="1.5"/><path d="M2 11l4-3 4 3 4-2"/></svg></span>
                <div class="file-meta flex-1 min-w-0"><div class="file-title text-[12px] font-semibold text-ink-900 whitespace-nowrap overflow-hidden text-ellipsis">Card image</div><div class="file-sub text-[10.5px] text-ink-500 font-mono">800x800 recommended</div></div>
                <span class="file-action text-[10.5px] font-semibold text-wa-deep px-[9px] py-1 rounded-full bg-white border border-wa-deep cursor-pointer shrink-0 [&.danger]:text-accent-coral [&.danger]:border-accent-coral">Browse</span>
              </div>
            </div>
            <div><label class="lbl text-[11.5px] font-semibold text-ink-700 flex items-center justify-between gap-2 mb-[5px]">Title</label><input type="text" name="carousel_titles[]" class="ctrl w-full px-[11px] py-[7px] border border-paper-200 rounded-lg bg-white text-[12.5px] text-ink-900 transition leading-[1.4] font-sans placeholder:text-[#8A9A95] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10" maxlength="40" placeholder="Spring jacket"></div>
          </div>
          <div class="mb-2"><label class="lbl text-[11.5px] font-semibold text-ink-700 flex items-center justify-between gap-2 mb-[5px]">Body <span class="sec-meta font-mono text-[10px] text-ink-500">max 160</span></label><textarea name="carousel_bodies[]" class="ctrl w-full px-[11px] py-[7px] border border-paper-200 rounded-lg bg-white text-[12.5px] text-ink-900 transition leading-[1.4] font-sans placeholder:text-[#8A9A95] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10" rows="2" maxlength="160" placeholder="Lightweight, breathable, reversible."></textarea></div>
          <div class="grid grid-cols-1 md:grid-cols-2 gap-2">
            <div><label class="lbl text-[11.5px] font-semibold text-ink-700 flex items-center justify-between gap-2 mb-[5px]">Button text</label>
                 <input type="hidden" name="carousel_button_card_indexes[]" class="card-idx-input">
                 <input type="hidden" name="carousel_button_types[]" value="url">
                 <input type="text" name="carousel_button_texts[]" class="ctrl w-full px-[11px] py-[7px] border border-paper-200 rounded-lg bg-white text-[12.5px] text-ink-900 transition leading-[1.4] font-sans placeholder:text-[#8A9A95] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10" maxlength="25" placeholder="View"></div>
            <div><label class="lbl text-[11.5px] font-semibold text-ink-700 flex items-center justify-between gap-2 mb-[5px]">Button URL</label><input type="text" name="carousel_button_values[]" class="ctrl w-full px-[11px] py-[7px] border border-paper-200 rounded-lg bg-white text-[12.5px] text-ink-900 transition leading-[1.4] font-sans placeholder:text-[#8A9A95] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10" placeholder="https://..."></div>
          </div>
        `;
        $("car-cards").appendChild(card);
        updateCardIndices();
      }

      $("pp-time").textContent = new Date().toTimeString().slice(0,5);

      function parseWa(t) {
        if (!t) return "";
        const esc = (s) => s.replace(/[&<>]/g, (c) => ({ "&":"&amp;","<":"&lt;",">":"&gt;" }[c]));
        let s = esc(t);
        s = s.replace(/\*([^\n*]+)\*/g, "<b>$1</b>");
        s = s.replace(/_([^\n_]+)_/g, "<i>$1</i>");
        s = s.replace(/~([^\n~]+)~/g, "<s>$1</s>");
        s = s.replace(/```([^\n`]+)```/g, "<code class='mono px-1 py-0.5 bg-paper-50 rounded text-[10px]'>$1</code>");
        s = s.replace(/\{\{([^}]+)\}\}/g, '<span class="var-pill inline-block bg-wa-bubble text-wa-deep rounded-full px-1.5 text-[10.5px] font-semibold font-mono">{{$1}}</span>');
        s = s.replace(/\n/g, "<br>");
        return s;
      }

      // Authentication category → Meta's FIXED verification-code layout.
      // Meta doesn't let you author an auth template's body/header/footer — the
      // message is a preset: "<code> is your verification code." + an optional
      // security line + a "Copy code" button. So when that category is picked we
      // paint that canonical bubble in the SAME phone frame instead of the
      // free-form header/body/footer preview. PURE DISPLAY — it just shows the
      // operator what a WhatsApp auth message looks like; the real wording/format
      // are injected by Meta at send time. (Meta 2026 spec + WA Manager preview.)
      function isAuthCategory() {
        return ($("tpl-category")?.value || "") === "authentication";
      }
      function renderAuthPreview() {
        $("pp-header")?.classList.add("hidden");
        const pa = $("pp-attach"); if (pa) pa.classList.add("hidden");
        $("pp-location")?.classList.add("hidden");
        $("pp-carousel")?.classList.add("hidden");

        // Body = fixed preset + the security disclaimer INLINE in the SAME
        // paragraph, exactly as Meta renders it (see the WA Manager screenshot:
        // "797011 is your verification code. For your security, do not share
        // this code."). Code is a sample; Meta injects the real one at send time.
        const code = "123456";
        $("pp-body").innerHTML =
          '<b>' + code + '</b> is your verification code. '
          + 'For your security, do not share this code.';

        // Expiry warning is the small grey FOOTER line under the body.
        const pf = $("pp-footer");
        if (pf) { pf.textContent = "This code expires in 10 minutes."; pf.classList.remove("hidden"); }

        // Single full-width "Copy code" button — Meta's default auth button (the
        // one-tap variant shows "Autofill" instead, but our generic builder has
        // no OTP-type picker, so we show the most common Copy code button).
        const pbl = $("pp-btn-list");
        pbl.classList.remove("hidden");
        pbl.innerHTML =
          '<div class="pp-btn bg-paper-0 rounded-[7px] px-3 py-2 text-center text-[12px] font-semibold text-wa-deep shadow-[0_1px_1px_rgba(0,0,0,0.06)] flex items-center justify-center gap-1.5">'
          + '<svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="1.6"><rect x="5.5" y="5.5" width="8" height="8" rx="1.2"/><path d="M3 10.5V4a1 1 0 0 1 1-1h6.5"/></svg>'
          + 'Copy code</div>';

        $("char-count").textContent = 0;
        $("char-count2").textContent = 0;
        $("lang-pill").textContent = $("tpl-language").value;
      }

      function renderPreview() {
        const bodyId = isCarousel ? "car-body" : "tpl-body";
        const headerId = isCarousel ? "car-header" : "tpl-header";
        const footerId = isCarousel ? "car-footer" : "tpl-footer";

        // Authentication uses Meta's fixed layout — paint it and stop here.
        if (!isCarousel && isAuthCategory()) { renderAuthPreview(); return; }

        const body = $(bodyId)?.value || "";
        // Character count reflects the RAW authored body, not the sample-filled paint.
        $("char-count").textContent = body.length;
        $("char-count2").textContent = body.length;
        $("pp-body").innerHTML = parseWa(fillSamples(body, { valueByKey: attrValues })) || "your message will appear here...";
        
        const header = $(headerId)?.value || "";
        const ph = $("pp-header"); ph.innerHTML = parseWa(fillSamples(header, { valueByKey: attrValues })); ph.classList.toggle("hidden", !header);
        
        const footer = $(footerId)?.value || "";
        const pf = $("pp-footer"); pf.innerHTML = parseWa(fillSamples(footer, { valueByKey: attrValues })); pf.classList.toggle("hidden", !footer);
        
        const at = $("attach-type").value;
        if (isCarousel) {
          const pa = $("pp-attach"); if (pa) pa.classList.add("hidden");
        } else {
          setAttachPreview(at);
        }
        
        const pbl = $("pp-btn-list");
        const pbc = $("pp-carousel");
        
        if (!isCarousel) {
            pbc.classList.add("hidden");
            const ctaRows = document.querySelectorAll("#btn-list .btn-row");
            pbl.innerHTML = "";
            if (ctaRows.length > 0) {
              pbl.classList.remove("hidden");
              ctaRows.forEach((r) => {
                const t = r.querySelector(".cta-text") || r.querySelector(".reply-text");
                const text = (t && t.value) || "Button";
              const div = document.createElement("div"); div.className = "pp-btn bg-paper-0 rounded-[7px] px-3 py-2 text-center text-[12px] font-semibold text-wa-deep shadow-[0_1px_1px_rgba(0,0,0,0.06)]"; div.textContent = text; pbl.appendChild(div);
              });
            } else pbl.classList.add("hidden");
        } else {
            pbl.classList.add("hidden");
            pbc.innerHTML = "";
            const cards = Array.from(document.querySelectorAll("#car-cards .hairline"));
            if (cards.length > 0) {
              pbc.classList.remove("hidden");
              cards.forEach(card => {
                 const inputs = card.querySelectorAll("input, textarea");
                 const title = inputs[0]?.value || "Card title";
                 const bodyText = inputs[1]?.value || "";
                 const btnText = inputs[2]?.value || "View";
                 const imgTile = card.querySelector(".file-thumb");
                 const imgSrc = imgTile ? imgTile.src : null;
                 
                 const cardDiv = document.createElement("div");
                 cardDiv.className = "snap-start shrink-0 w-[200px] bg-paper-0 rounded-[7px] flex flex-col overflow-hidden shadow-[0_1px_1px_rgba(0,0,0,0.06)] border border-paper-100";
                 
                 let imgHtml = "";
                 if (imgSrc) {
                     imgHtml = `<div class="h-[100px] bg-paper-100 bg-cover bg-center" style="background-image:url('${imgSrc}')"></div>`;
                 } else {
                     imgHtml = `<div class="h-[100px] bg-[#DFF1ED] flex items-center justify-center text-wa-deep text-[10.5px] font-mono">Image</div>`;
                 }
                 
                 let textHtml = `<div class="p-2 flex-1 flex flex-col gap-1">
                     <div class="font-semibold text-[12px] leading-tight text-ink-900 overflow-hidden text-ellipsis whitespace-nowrap">${parseWa(fillSamples(title, { valueByKey: attrValues }))}</div>
                     ${bodyText ? `<div class="text-[11px] leading-[1.3] text-ink-600">${parseWa(fillSamples(bodyText, { valueByKey: attrValues }))}</div>` : ''}
                 </div>`;
                 
                 let btnHtml = `<div class="border-t border-paper-100 p-2 text-center text-[11.5px] font-semibold text-wa-deep">${btnText}</div>`;
                 
                 cardDiv.innerHTML = imgHtml + textHtml + btnHtml;
                 pbc.appendChild(cardDiv);
              });
            } else {
              pbc.classList.add("hidden");
            }
        }
        $("lang-pill").textContent = $("tpl-language").value;
      }

      ["tpl-header","tpl-body","tpl-footer","attach-type","tpl-language","car-header","car-body","car-footer"].forEach((id) => {
          if ($(id)) $(id).addEventListener("input", renderPreview);
      });
      // Category drives the preview too — switching to/from Authentication swaps
      // between the fixed verification-code layout and the free-form preview.
      // It also shows/hides the OTP "Code validity" section, which only applies
      // to Authentication templates (meaningless for Marketing / Utility).
      function syncOtpSection() {
        const sec = $("otp-section");
        if (!sec) return;
        const isAuth = ($("tpl-category")?.value || "") === "authentication";
        sec.classList.toggle("hidden", !isAuth);
      }
      if ($("tpl-category")) {
        $("tpl-category").addEventListener("change", renderPreview);
        $("tpl-category").addEventListener("change", syncOtpSection);
      }
      syncOtpSection();
      document.querySelectorAll(".btn-row input, .btn-row select").forEach((i) => i.addEventListener("input", renderPreview));
      if ($("car-cards")) $("car-cards").addEventListener("input", renderPreview);

      // Attachment type "Location" → swap the file picker for lat/lng inputs,
      // and mirror a live map-pin bubble in the preview.
      function updateLocationPreview() {
        const ploc = $("pp-location");
        if (!ploc) return;
        const isLoc = ($("attach-type")?.value === "location");
        const lat = parseFloat((document.querySelector("[name=latitude]")?.value || "").trim());
        const lng = parseFloat((document.querySelector("[name=longitude]")?.value || "").trim());
        const valid = isFinite(lat) && isFinite(lng) && lat >= -90 && lat <= 90 && lng >= -180 && lng <= 180;
        if (isLoc && valid) {
          ploc.classList.remove("hidden");
          const nm = (document.querySelector("[name=location_name]")?.value || "").trim();
          if ($("pp-loc-name")) $("pp-loc-name").textContent = nm || "Location";
          if ($("pp-loc-coords")) $("pp-loc-coords").textContent = lat.toFixed(5) + ", " + lng.toFixed(5);
        } else {
          ploc.classList.add("hidden");
        }
      }
      function syncAttachMode() {
        const isLoc = ($("attach-type")?.value === "location");
        $("attach-file-wrap")?.classList.toggle("hidden", isLoc);
        $("attach-loc-wrap")?.classList.toggle("hidden", !isLoc);
        updateLocationPreview();
      }
      if ($("attach-type")) $("attach-type").addEventListener("change", syncAttachMode);
      ["latitude", "longitude", "location_name"].forEach((nm) => {
        const el = document.querySelector(`[name=${nm}]`);
        if (el) el.addEventListener("input", updateLocationPreview);
      });
      syncAttachMode();

      function activateFileTile(tile) {
        const input = document.createElement("input");
        input.type = "file"; input.accept = tile.dataset.accept || ""; input.className = "hidden";
        // Carousel cards live inside #car-cards and need indexed
        // names matching the controller's processCarouselData
        // (`carousel_images[0]` etc). The main-attachment tile
        // outside that container submits as `attachment_file`.
        if (!tile.closest('#car-cards')) {
            input.name = "attachment_file";
        } else {
            const cards = Array.from(document.querySelectorAll('#car-cards [data-file-tile]'));
            input.name = `carousel_images[${cards.indexOf(tile)}]`;
        }
        tile.appendChild(input);
        const titleEl = tile.querySelector(".file-title");
        const subEl = tile.querySelector(".file-sub");
        const actionEl = tile.querySelector(".file-action");
        const iconEl = tile.querySelector(".file-icon");
        const oT = titleEl.textContent, oS = subEl.textContent, oI = iconEl.innerHTML;
        tile.addEventListener("click", (e) => { if (e.target === actionEl && tile.classList.contains("has-file")) return; input.click(); });
        // The main-attachment tile lives outside #car-cards. Carousel
        // tiles live inside it. Only the main one drives the live
        // preview (#pp-attach); carousel cards each have their own
        // preview path.
        const isMainAttachment = !tile.closest('#car-cards');
        const ppAttach = $('pp-attach');
        const ppAttachOriginalHtml = ppAttach ? ppAttach.innerHTML : '';

        actionEl.addEventListener("click", (e) => {
          if (!tile.classList.contains("has-file")) return;
          e.stopPropagation();
          input.value = ""; tile.classList.remove("has-file");
          titleEl.textContent = oT; subEl.textContent = oS;
          actionEl.textContent = "Browse"; actionEl.classList.remove("danger");
          const old = tile.querySelector(".file-thumb, .file-icon");
          const ic = document.createElement("span"); ic.className = "file-icon w-[34px] h-[34px] rounded-lg bg-[#DFF1ED] text-wa-deep inline-flex items-center justify-center shrink-0"; ic.innerHTML = oI;
          old.replaceWith(ic);
          // Clear the picked media when the file is removed — renderPreview()
          // repaints the "… preview" placeholder.
          if (isMainAttachment && ppAttach) {
            try { if (pickedSrc) URL.revokeObjectURL(pickedSrc); } catch (_) {}
            pickedSrc = ""; pickedType = "";
            renderPreview();
          }
        });
        input.addEventListener("change", () => {
          const f = input.files && input.files[0]; if (!f) return;
          tile.classList.add("has-file");
          titleEl.textContent = f.name;
          subEl.textContent = (f.size / 1024).toFixed(1) + " KB / " + (f.type || "file");
          actionEl.textContent = "Remove"; actionEl.classList.add("danger");
          if (f.type.startsWith("image/")) {
            const r = new FileReader();
            r.onload = (ev) => {
              const img = document.createElement("img"); img.src = ev.target.result;
              img.className = "file-thumb w-[34px] h-[34px] rounded-lg object-cover shrink-0 border border-paper-200";
              const cur = tile.querySelector(".file-thumb, .file-icon"); if (cur) cur.replaceWith(img);
            };
            r.readAsDataURL(f);
          }

          // Main-attachment live preview — render the real media (image OR
          // video OR document) via an object URL.
          if (isMainAttachment && ppAttach) {
            try { if (pickedSrc) URL.revokeObjectURL(pickedSrc); } catch (_) {}
            pickedSrc = URL.createObjectURL(f);
            pickedType = $("attach-type")?.value || "";
          }
          renderPreview();
        });
      }
      document.querySelectorAll("[data-file-tile]").forEach(activateFileTile);
      new MutationObserver((muts) => muts.forEach((m) => m.addedNodes.forEach((n) => { if (n.nodeType === 1) n.querySelectorAll && n.querySelectorAll("[data-file-tile]").forEach(activateFileTile); }))).observe($("car-cards"), { childList:true, subtree:true });

      renderPreview();

      // Inline `onclick="addCtaButton()"` and `onclick="removeBtn(this)"`
      // attributes in the blade reference globals — expose them on
      // `window` so the buttons actually fire. Without this the
      // "Add button" link is dead.
      window.addCtaButton    = addCtaButton;
      window.addQuickReply   = addQuickReply;
      window.addBtnRow       = addBtnRow;
      window.addCarouselCard = addCarouselCard;
      window.removeBtn       = removeBtn;
      window.format          = format;
      window.insertVar       = insertVar;

      // Variable-mapping panel — maps each {{N}} slot in the body to a
      // workspace attribute and records it in the hidden
      // variable_map_json field for the controller to persist.
      initVariableMap({
          body:   $('tpl-body'),
          hidden: document.querySelector('[name="variable_map_json"]'),
          rows:   $('var-map-rows'),
          empty:  $('var-map-empty'),
      });

      // Run once on load so the initially-active tab actually
      // governs row visibility (the seed row in the blade is CTA,
      // so default-cta is a no-op — but mix/reply pre-selected via
      // old() restore will now hide/show correctly).
      const activeBt = document.querySelector("#btn-type .seg-btn.active");
      applyBtnMode(activeBt?.dataset.bt || "cta");

      // Wire any CTA rows that already exist on load (blade seed row or
      // old()-restored rows) so their value placeholder tracks the kind.
      document.querySelectorAll("#btn-list .btn-row[data-kind='cta']").forEach(wireCtaKind);
      refreshAddBtn();

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
      const aiIndustry   = $('ai-industry');
      const aiOccasion   = $('ai-occasion');
      const aiPurpose    = $('ai-purpose');
      const aiAction     = $('ai-action');
      const aiTone       = $('ai-tone');
      const aiPromptTa   = $('ai-prompt');
      const aiLangLabel  = $('ai-language-label');
      const genIcon      = $('ai-generate-icon');
      const genSpin      = $('ai-generate-spin');
      const genLabel     = $('ai-generate-label');

      let modelsLoaded = false;
      let isGenerating = false;

      function openAiModal() {
          if (!aiModal) return;
          showError('');
          if (aiLangLabel) aiLangLabel.textContent = $('tpl-language').value || 'en_US';
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
          if (!msg) {
              aiError.classList.add('hidden');
              aiError.textContent = '';
          } else {
              aiError.classList.remove('hidden');
              aiError.textContent = msg;
          }
      }

      async function loadModels() {
          try {
              const res = await fetch('/templates/api/ai-models', {
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
              console.warn('[ai-template] failed to load models', err);
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
              if (genLabel) genLabel.textContent = 'Generate Template';
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
          if (!modelValue) {
              showError('Pick an AI model.');
              return;
          }
          const [provider, model] = modelValue.split('|');
          showError('');
          setBusy(true);

          const payload = {
              provider,
              model,
              type:          isCarousel ? 'carousel' : 'standard',
              category:      ($('tpl-category')?.value || 'utility'),
              language:      ($('tpl-language')?.value || 'en_US'),
              business_name: business,
              industry:      aiIndustry?.value || '',
              occasion:      aiOccasion?.value || '',
              purpose:       aiPurpose?.value || '',
              action:        aiAction?.value || '',
              tone:          aiTone?.value || '',
              custom_prompt: aiPromptTa?.value || '',
          };

          try {
              const res = await fetch('/templates/api/ai-generate', {
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
                  const msg = json?.message || `Generation failed (${res.status}).`;
                  showError(msg);
                  setBusy(false);
                  return;
              }
              applyGeneratedTemplate(json.template || {});
              setBusy(false);
              closeAiModal();
              if (typeof window.toast === 'function') {
                  window.toast({ message: 'Template draft filled from AI.', kind: 'success' });
              }
          } catch (err) {
              console.warn('[ai-template] generate failed', err);
              showError('Network error talking to the server.');
              setBusy(false);
          }
      }

      function applyGeneratedTemplate(t) {
          if (!t || typeof t !== 'object') return;

          // Identity
          if (t.template_name) {
              const slug = String(t.template_name)
                  .toLowerCase()
                  .replace(/[^a-z0-9_]+/g, '_')
                  .replace(/^_+|_+$/g, '')
                  .slice(0, 60);
              const nameEl = $('tpl-name');
              if (nameEl) nameEl.value = slug;
          }

          if (!isCarousel) {
              const headerEl = $('tpl-header');
              if (headerEl) headerEl.value = String(t.header || '').slice(0, 60);

              const bodyEl = $('tpl-body');
              if (bodyEl) bodyEl.value = String(t.body || '').slice(0, 1024);

              const footerEl = $('tpl-footer');
              if (footerEl) footerEl.value = String(t.footer || '').slice(0, 60);

              // Buttons — clear existing, paint new rows.
              const btnList = $('btn-list');
              if (btnList && Array.isArray(t.buttons)) {
                  btnList.innerHTML = '';
                  t.buttons.slice(0, 3).forEach((b) => {
                      addCtaButton();
                      const rows = btnList.querySelectorAll('.btn-row[data-kind="cta"]');
                      const row = rows[rows.length - 1];
                      if (!row) return;
                      const sel = row.querySelector('.cta-action');
                      const txt = row.querySelector('.cta-text');
                      const val = row.querySelector('.cta-value');
                      const safeType = ['visit_website', 'call_phone', 'copy_code'].includes(b.type)
                          ? b.type : 'visit_website';
                      if (sel) { sel.value = safeType; sel.dispatchEvent(new Event('change')); }
                      if (txt) txt.value = String(b.text || '').slice(0, 25);
                      if (val) val.value = String(b.value || '').slice(0, 2000);
                  });
                  if (t.buttons.length === 0) {
                      // Re-seed one empty row so the user has a starting point.
                      addCtaButton();
                  }
              }
          } else {
              // Carousel mode — fill intro + cards.
              const ch = $('car-header'); if (ch) ch.value = String(t.header || '').slice(0, 60);
              const cf = $('car-footer'); if (cf) cf.value = String(t.footer || '').slice(0, 60);
              const cb = $('car-body');   if (cb) cb.value = String(t.body   || '').slice(0, 1024);

              // Reset card list, then add fresh cards.
              const cardList = $('car-cards');
              if (cardList) {
                  cardList.innerHTML = '<div class="hairline border border-paper-200 rounded-lg border-dashed py-6 text-center text-ink-500 text-[12px] bg-paper-50">No cards yet / add up to 10 swipeable cards.</div>';
                  carCardCount = 0;
                  $('card-counter').textContent = '0/10';
              }
              const cards = Array.isArray(t.cards) ? t.cards.slice(0, 10) : [];
              cards.forEach((c, idx) => {
                  addCarouselCard();
                  // The newly-appended card is the last .hairline child.
                  const cardEls = cardList?.querySelectorAll(':scope > .hairline');
                  const cardEl = cardEls ? cardEls[cardEls.length - 1] : null;
                  if (!cardEl) return;
                  const inputs = cardEl.querySelectorAll('input, textarea');
                  // Order matches addCarouselCard's template:
                  //   inputs[0] = title, inputs[1] = body (textarea),
                  //   inputs[2] = button text, inputs[3] = button URL
                  if (inputs[0]) inputs[0].value = String(c.title       || '').slice(0, 40);
                  if (inputs[1]) inputs[1].value = String(c.body        || '').slice(0, 160);
                  if (inputs[2]) inputs[2].value = String(c.button_text || '').slice(0, 25);
                  if (inputs[3]) inputs[3].value = String(c.button_url  || '').slice(0, 2000);
              });
          }

          renderPreview();
          // Let the variable-mapping panel (and anything else listening)
          // pick up the AI-filled body — value sets don't fire `input`.
          $('tpl-body')?.dispatchEvent(new Event('input', { bubbles: true }));
      }

      // Wire events
      openAiBtn?.addEventListener('click', openAiModal);
      closeAiBtn?.addEventListener('click', closeAiModal);
      cancelAiBtn?.addEventListener('click', closeAiModal);
      generateBtn?.addEventListener('click', generate);
      aiModal?.addEventListener('click', (e) => {
          // Click on the dimmed backdrop closes; click inside the
          // panel does not (events bubbling up from the panel hit
          // this handler with e.target === panel-or-child).
          if (e.target === aiModal) closeAiModal();
      });
      document.addEventListener('keydown', (e) => {
          if (e.key === 'Escape' && aiModal && !aiModal.classList.contains('hidden')) {
              closeAiModal();
          }
      });
}
