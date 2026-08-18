import { initVariableMap } from '../template-var-map.js';
import { fillSamples, loadAttributeValues } from '../template-vars.js';
import { themeColor } from '../theme-colors.js';

export default function init() {
    const $ = (id) => document.getElementById(id);

      // Meta template-body guard — same rule as the create page: Meta rejects a
      // WABA template body that STARTS or ENDS with a {{N}} variable, or has two
      // variables with no text between. Block it on submit so the operator fixes
      // it before the Meta round-trip. Channel comes from the form's data-channel
      // (the saved template's engine); non-WABA edits are unrestricted.
      (function guardMetaBodyVariables() {
        const form = document.getElementById('templateForm');
        if (!form) return;
        form.addEventListener('submit', (e) => {
          if ((form.dataset.channel || 'waba') !== 'waba') return;
          let bodyEl = null;
          for (const id of ['tpl-body', 'car-body']) {
            const el = document.getElementById(id);
            if (el && el.offsetParent !== null) { bodyEl = el; break; }
          }
          const body = (bodyEl && bodyEl.value ? bodyEl.value : '').trim();
          if (body === '') return;
          const startsVar = /^\{\{[^{}]+\}\}/.test(body);
          const endsVar   = /\{\{[^{}]+\}\}$/.test(body);
          const adjacent  = /\}\}\s*\{\{/.test(body);
          if (!startsVar && !endsVar && !adjacent) return;
          e.preventDefault();
          let msg;
          if (startsVar)    msg = 'Meta won\'t approve a template that STARTS with a variable like {{1}}. Add a word before it — e.g. "Hi {{1}}, …".';
          else if (endsVar) msg = 'Meta won\'t approve a template that ENDS with a variable like {{1}}. Add a word after it — e.g. "… see you {{1}}.".';
          else              msg = 'Meta won\'t approve two variables next to each other ({{1}}{{2}}). Put text between them — e.g. "{{1}} and {{2}}".';
          if (window.toast) window.toast(msg, 'error'); else alert(msg);
          if (bodyEl) bodyEl.focus();
          return false;
        });
      })();

      // ── Attachment live-preview media ──────────────────────────────
      // A CSS background-image can only paint an IMAGE — a video/PDF stays
      // blank. So render the real element per type. `existing*` is the saved
      // file (from the blade data-attrs); `picked*` is a freshly chosen file.
      const _pa0 = $("pp-attach");
      let existingUrl  = _pa0?.dataset.existingUrl  || "";
      let existingType = _pa0?.dataset.existingType || "";
      let pickedSrc = "", pickedType = "";

      function setAttachPreview(at) {
        const pa = $("pp-attach");
        if (!pa) return;
        if (!at || at === "none" || at === "location") { pa.classList.add("hidden"); return; }
        pa.classList.remove("hidden");

        // Resolve the media src for the CURRENT type (a just-picked file wins;
        // otherwise the saved file, but only if its type matches).
        let src = "";
        if (pickedSrc && pickedType === at) src = pickedSrc;
        else if (existingUrl && existingType === at) src = existingUrl;

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

      // Live preview substitutes a real SAMPLE VALUE for each {{token}}.
      // Pure paint — never sent. Repaints once workspace values load.
      let attrValues = {};
      loadAttributeValues().then((v) => { attrValues = v || {}; renderPreview(); });

      document.querySelectorAll("#type-seg .seg-btn").forEach((b) => b.addEventListener("click", () => {
        document.querySelectorAll("#type-seg .seg-btn").forEach((x) => x.classList.toggle("active", x === b));
        
        const isCarousel = b.dataset.type === "carousel";
        $("standard-sections").classList.toggle("hidden", isCarousel);
        $("standard-sections").querySelectorAll("input, select, textarea").forEach(el => el.disabled = isCarousel);
        
        $("carousel-sections").classList.toggle("hidden", !isCarousel);
        $("carousel-sections").querySelectorAll("input, select, textarea").forEach(el => el.disabled = !isCarousel);
        
        renderPreview();
      }));
      
      // Initialize disabled state on load based on template type
      const isInitialCarousel = document.getElementById("template-type-input")?.value === "carousel";
      if (document.getElementById("standard-sections")) {
        document.getElementById("standard-sections").querySelectorAll("input, select, textarea").forEach(el => el.disabled = isInitialCarousel);
      }
      if (document.getElementById("carousel-sections")) {
        document.getElementById("carousel-sections").querySelectorAll("input, select, textarea").forEach(el => el.disabled = !isInitialCarousel);
      }
      // Unified buttons editor (mirrors the create page). Every row is the
      // same shape with a 4-option dropdown (Visit website / Call phone /
      // Copy code / Quick reply), so CTAs + quick replies mix freely. The
      // tabs only set the DEFAULT type for the next added row. Hard cap of
      // 3 — WhatsApp reliably renders only 3 buttons, so no 4th in any mode.
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

        // Transform existing rows to match the chosen mode (the tabs weren't
        // doing this, so "Quick reply" left the CTA dropdown + URL visible).
        document.querySelectorAll("#btn-list .btn-row").forEach((row) => {
          const sel = row.querySelector(".cta-action");
          const val = row.querySelector(".cta-value");
          if (!sel) return;
          if (mode === "reply") {
            sel.value = "quick_reply";
            sel.style.display = "none";
            if (val) val.style.display = "none";
            row.style.gridTemplateColumns = "1fr 28px";
          } else if (mode === "cta") {
            if (sel.value === "quick_reply") sel.value = "visit_website";
            sel.style.display = "";
            if (val) { val.style.display = ""; val.placeholder = ctaPlaceholder(sel.value); }
            row.style.gridTemplateColumns = "";
          } else {
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
      function addBtnRow() {
        if (document.querySelectorAll("#btn-list .btn-row").length >= BTN_MAX) return;
        addCtaButton(btnDefaultType);
      }
      function addQuickReply() { addCtaButton("quick_reply"); }

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
      // CTA value placeholder follows the kind (URL / phone / copy-code).
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
            val.style.display = "none";   // quick replies have no destination
          } else {
            val.style.display = "";
            val.placeholder = ctaPlaceholder(sel.value);
          }
        };
        sel.addEventListener("change", () => { sync(); renderPreview(); });
        sync();
      }
      function addCtaButton(defaultType = "visit_website") {
        if (document.querySelectorAll("#btn-list .btn-row").length >= BTN_MAX) return;
        const r = document.createElement("div");
        r.className = "btn-row grid grid-cols-[140px_1fr_1fr_28px] gap-1.5 items-center"; r.dataset.kind = "cta";
        // NOTE: the name="button_type[]" / button_text[] / button_value[]
        // attributes are REQUIRED — without them a newly-added CTA row in the
        // edit form never submits, so processButtons() silently drops it. They
        // mirror the server-rendered seed rows in edit.blade.php.
        r.innerHTML = `
          <select name="button_type[]" class="ctrl w-full px-[11px] py-[7px] border border-paper-200 rounded-lg bg-white text-[12.5px] text-ink-900 transition leading-[1.4] font-sans placeholder:text-[#8A9A95] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10 cta-action"><option value="visit_website">Visit website</option><option value="call_phone">Call phone</option><option value="copy_code">Copy code</option><option value="quick_reply">Quick reply</option></select>
          <input type="text" name="button_text[]" class="ctrl w-full px-[11px] py-[7px] border border-paper-200 rounded-lg bg-white text-[12.5px] text-ink-900 transition leading-[1.4] font-sans placeholder:text-[#8A9A95] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10 cta-text" maxlength="25" placeholder="Button text">
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
      function removeBtn(b) { b.closest(".btn-row").remove(); refreshAddBtn(); renderPreview(); }
      // Wire existing server-rendered rows so their value field tracks the
      // kind dropdown (value hidden for quick replies).
      document.querySelectorAll("#btn-list .btn-row[data-kind='cta']").forEach(wireCtaKind);
      refreshAddBtn();

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

      function renderPreview() {
        const isCarousel = document.getElementById("template-type-input")?.value === "carousel" || (document.querySelector("#type-seg .seg-btn.active")?.dataset.type === "carousel");
        const bodyId = isCarousel ? "car-body" : "tpl-body";
        const headerId = isCarousel ? "car-header" : "tpl-header";
        const footerId = isCarousel ? "car-footer" : "tpl-footer";
        
        const body = $(bodyId)?.value || "";
        // Count reflects the RAW authored body, not the sample-filled paint.
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
            if(pbc) pbc.classList.add("hidden");
            const ctaRows = document.querySelectorAll("#btn-list .btn-row");
            pbl.innerHTML = "";
            if (ctaRows.length > 0) {
              pbl.classList.remove("hidden");
              ctaRows.forEach((r) => {
                const tEl = r.querySelector(".cta-text") || r.querySelector(".reply-text");
                const text = (tEl && tEl.value) || "Button";
              const div = document.createElement("div"); div.className = "pp-btn bg-paper-0 rounded-[7px] px-3 py-2 text-center text-[12px] font-semibold text-wa-deep shadow-[0_1px_1px_rgba(0,0,0,0.06)]"; div.textContent = text; pbl.appendChild(div);
              });
            } else pbl.classList.add("hidden");
        } else {
            pbl.classList.add("hidden");
            if (pbc) {
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
        }
        
        $("lang-pill").textContent = $("tpl-language").value;
      }

      ["tpl-header","tpl-body","tpl-footer","attach-type","tpl-language","car-header","car-body","car-footer"].forEach((id) => {
          if ($(id)) $(id).addEventListener("input", renderPreview);
      });
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
        tile.appendChild(input);
        const titleEl = tile.querySelector(".file-title");
        const subEl = tile.querySelector(".file-sub");
        const actionEl = tile.querySelector(".file-action");
        const iconEl = tile.querySelector(".file-icon");
        const oT = titleEl.textContent, oS = subEl.textContent, oI = iconEl.innerHTML;
        tile.addEventListener("click", (e) => { if (e.target === actionEl && tile.classList.contains("has-file")) return; input.click(); });
        actionEl.addEventListener("click", (e) => {
          if (!tile.classList.contains("has-file")) return;
          e.stopPropagation();
          input.value = ""; tile.classList.remove("has-file");
          titleEl.textContent = oT; subEl.textContent = oS;
          actionEl.textContent = "Browse"; actionEl.classList.remove("danger");
          const old = tile.querySelector(".file-thumb, .file-icon");
          const ic = document.createElement("span"); ic.className = "file-icon w-[34px] h-[34px] rounded-lg bg-[#DFF1ED] text-wa-deep inline-flex items-center justify-center shrink-0"; ic.innerHTML = oI;
          old.replaceWith(ic);
        });
        input.addEventListener("change", () => {
          const f = input.files && input.files[0]; if (!f) return;
          tile.classList.add("has-file");
          titleEl.textContent = f.name;
          subEl.textContent = (f.size / 1024).toFixed(1) + " KB / " + (f.type || "file");
          actionEl.textContent = "Remove"; actionEl.classList.add("danger");

          // Small thumbnail inside the file tile (images only).
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
          // video OR document) via an object URL. Skip carousel card tiles.
          if (!tile.closest('#car-cards') && document.getElementById('pp-attach')) {
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

      // Variable-mapping panel — pre-populated from the saved
      // variable_map (via the hidden field's value) and kept in sync as
      // the body's {{N}} placeholders change.
      initVariableMap({
          body:   $('tpl-body'),
          hidden: document.querySelector('[name="variable_map_json"]'),
          rows:   $('var-map-rows'),
          empty:  $('var-map-empty'),
      });

      // Inline onclick attributes in the blade reference these
      // helpers — expose on window so "Add button" / Remove
      // (×) actually fire.
      window.addCtaButton    = addCtaButton;
      window.addBtnRow       = addBtnRow;
      window.addQuickReply   = addQuickReply;
      window.removeBtn       = removeBtn;
      if (typeof addCarouselCard === 'function') window.addCarouselCard = addCarouselCard;
      window.format          = format;
      window.insertVar       = insertVar;
}
