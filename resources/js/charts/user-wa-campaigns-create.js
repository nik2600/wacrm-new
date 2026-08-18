import { initVariableMap } from '../template-var-map.js';
import { fillSamples, loadAttributeValues } from '../template-vars.js';

export default function init() {
    // Live preview substitutes a real SAMPLE VALUE for each {{token}} in
    // the custom body/header (real attribute demo value → friendly
    // built-in → {{key}} pill). Pure paint — never sent.
    let attrValues = {};
    loadAttributeValues().then((v) => { attrValues = v || {}; try { updatePreview(); } catch (_) {} });

    // Engine-aware custom composer. The sender picker posts a composite
    // `engine:id` value. Twilio custom sends are text + media only (buttons
    // need a Twilio Content template); WABA custom only reaches contacts
    // active in the last 24h. So when a Twilio/WABA sender is picked, hide the
    // Buttons section and show a per-engine note. Unofficial keeps the full set.
    (function wireEngineGate() {
        const sel = document.getElementById('device');
        if (!sel) return;
        const buttons = document.querySelector('[data-custom-buttons]');
        const note = document.querySelector('[data-engine-note]');
        const tplSel = document.getElementById('template-only');
        const tplOpts = tplSel ? Array.from(tplSel.querySelectorAll('option[data-channel]')) : [];
        const tplEmpty = document.querySelector('[data-tpl-channel-empty]');
        // A/B Variant B template picker — filtered by the same engine rule.
        const tplSelB = document.getElementById('template_id_b');
        const tplOptsB = tplSelB ? Array.from(tplSelB.querySelectorAll('option[data-channel]')) : [];
        const NOTES = {
            twilio: 'Twilio custom messages send text + media only. For buttons, create a Template campaign (Twilio Content SID).',
            waba: 'WABA custom messages only reach contacts who messaged you in the last 24 hours. For cold or bulk sends — and for buttons — use a Template campaign.',
        };
        const TPL_EMPTY = {
            twilio: 'No Twilio templates yet. Create one with a Content SID on the Templates page.',
            waba: 'No approved Meta (WABA) templates for this number yet. Create or get one approved on the Templates page.',
            baileys: 'No Unofficial API templates yet. Create one on the Templates page.',
        };
        const sync = () => {
            const engine = String(sel.value || '').split(':')[0];
            const limited = engine === 'twilio' || engine === 'waba';

            // Custom composer: Twilio/WABA can't carry buttons free-form.
            if (buttons) buttons.classList.toggle('hidden', limited);
            if (note) {
                if (limited && NOTES[engine]) { note.textContent = NOTES[engine]; note.classList.remove('hidden'); }
                else { note.classList.add('hidden'); }
            }

            // Template picker: show only templates that belong to the picked
            // sender's engine. A WABA template can't send on Twilio, etc.
            if (tplSel && tplOpts.length) {
                let usableVisible = 0;
                tplOpts.forEach((o) => {
                    const match = !engine || o.dataset.channel === engine;
                    o.hidden = !match;
                    if (match && !o.disabled) usableVisible++;
                });
                const cur = tplSel.selectedOptions[0];
                if (cur && cur.dataset.channel && engine && cur.dataset.channel !== engine) {
                    tplSel.value = '';
                }
                if (tplEmpty) {
                    if (engine && usableVisible === 0 && TPL_EMPTY[engine]) {
                        tplEmpty.textContent = TPL_EMPTY[engine];
                        tplEmpty.classList.remove('hidden');
                    } else {
                        tplEmpty.classList.add('hidden');
                    }
                }
            }

            // A/B Variant B template picker — same engine filter as Variant A.
            if (tplSelB && tplOptsB.length) {
                tplOptsB.forEach((o) => {
                    const match = !engine || o.dataset.channel === engine;
                    o.hidden = !match;
                });
                const curB = tplSelB.selectedOptions[0];
                if (curB && curB.dataset.channel && engine && curB.dataset.channel !== engine) {
                    tplSelB.value = '';
                }
            }

            // Repaint the live preview so it matches the picked engine (no stale
            // buttons / cleared template). Guarded — updatePreview may be a later
            // const at first run (TDZ); the change-event runs catch it.
            try { updatePreview(); } catch (_) {}
        };
        sel.addEventListener('change', sync);
        sync();
    })();
    const total = 5;
      let current = 1;
      let format = "Text";

      const panes = document.querySelectorAll(".step-pane");
      const nodes = document.querySelectorAll(".step-node");
      const prev = document.getElementById("prevBtn");
      const next = document.getElementById("nextBtn");
      const submit = document.getElementById("submitBtn");
      const curLabel = document.getElementById("cur-step");

      const add = (el, classes) => el.classList.add(...classes);
      const remove = (el, classes) => el.classList.remove(...classes);

      const setStep = (n) => {
        current = Math.max(1, Math.min(total, n));
        panes.forEach(pane => pane.classList.toggle("hidden", Number(pane.dataset.step) !== current));
        nodes.forEach(node => {
          const number = Number(node.dataset.n);
          const dot = node.querySelector(".dot");
          const lab = node.querySelector(".lab");
          const bar = node.querySelector(".bar");
          remove(dot, ["bg-paper-0", "bg-wa-deep", "border-paper-200", "border-wa-deep", "text-ink-500", "text-wa-deep", "text-paper-0", "ring-4", "ring-wa-deep/10"]);
          remove(lab, ["text-ink-500", "text-wa-deep", "font-medium", "font-semibold"]);
          if (number < current) {
            add(dot, ["bg-wa-deep", "border-wa-deep", "text-paper-0"]);
            add(lab, ["text-wa-deep", "font-semibold"]);
            if (bar) {
              bar.classList.remove("bg-paper-200");
              bar.classList.add("bg-wa-deep");
            }
          } else if (number === current) {
            add(dot, ["bg-paper-0", "border-wa-deep", "text-wa-deep", "ring-4", "ring-wa-deep/10"]);
            add(lab, ["text-wa-deep", "font-semibold"]);
            if (bar) {
              bar.classList.remove("bg-wa-deep");
              bar.classList.add("bg-paper-200");
            }
          } else {
            add(dot, ["bg-paper-0", "border-paper-200", "text-ink-500"]);
            add(lab, ["text-ink-500", "font-medium"]);
            if (bar) {
              bar.classList.remove("bg-wa-deep");
              bar.classList.add("bg-paper-200");
            }
          }
        });
        prev.disabled = current === 1;
        // Gate with inline style.display, NOT the .hidden class: Tailwind orders
        // .inline-flex AFTER .hidden, so a button with "hidden inline-flex" would
        // stay visible (the display utility wins). Inline style always wins, so
        // Next hides on the last step and Launch only shows on the last step.
        next.style.display = (current === total) ? "none" : "inline-flex";
        submit.style.display = (current !== total) ? "none" : "inline-flex";
        curLabel.textContent = current;
      };

      const selectedType = () => document.querySelector("input[name='campaign_type']:checked")?.value || "Custom message";
      const selectedSchedule = () => document.querySelector("input[name='schedule_type']:checked")?.value || "Send now";
      const numberFormat = (value) => new Intl.NumberFormat("en-IN").format(value);

      // Per-source recipient counts. Always read every field regardless
      // of which tab is active — the form posts ALL of them on submit
      // anyway, and the review pane should reflect the full set so the
      // user can see what they've queued. Switching tabs is purely a
      // visibility convenience, not a data filter.
      // Row count of the chosen CSV, parsed client-side on file select (below).
      // Without this the review total ignored the CSV → showed 0 recipients →
      // the "add at least one recipient" guard blocked Next even though a CSV
      // was uploaded (the server DID parse it). Counted here so the total + the
      // guard reflect the CSV.
      let csvCount = 0;
      const recipientBreakdown = () => {
        const mode = document.querySelector("input[name='recipient_mode']:checked")?.value || 'groups';
        const groups = Array.from(document.querySelectorAll("[data-recipient-count]:checked"));
        const groupCount   = groups.reduce((s, i) => s + Number(i.dataset.recipientCount || 0), 0);
        const contactCount = document.querySelectorAll("input[name='recipients[]']:checked").length;
        // Strip non-digits before measuring length — user might type
        // `+91…` or include spaces / dashes.
        const manualCount  = (document.getElementById("manual-numbers")?.value || "")
          .split(/[\n,;]+/)
          .map(v => v.replace(/\D+/g, ''))
          .filter(v => v.length >= 8).length;
        const csvFile = document.getElementById("csv-file-input")?.files?.[0] || null;
        return {
          mode,
          groups: groupCount,
          contacts: contactCount,
          manual: manualCount,
          csv: csvCount,
          csvFilename: csvFile?.name || null,
          total: groupCount + contactCount + manualCount + csvCount,
        };
      };
      const recipientTotal = () => recipientBreakdown().total;

      const scheduleText = () => {
        const mode = selectedSchedule();
        if (mode === "Send now") return "Now";
        return `${mode} ${document.getElementById("send-date").value} ${document.getElementById("send-time").value}`;
      };

      // Defensive setter — never throws if the element is missing, and
      // logs a tagged warning so devtools tells us which id was bad.
      const set = (id, value) => {
        const el = document.getElementById(id);
        if (!el) { console.warn("[WA-CAMPAIGN] missing element:", id); return; }
        el.textContent = value;
      };

      // WhatsApp markdown → HTML for the live preview bubble, mirroring
      // the template builder's parseWa(). Escapes first, then *bold*,
      // _italic_, ~strike~, ```code```, {{var}} pills, and newlines.
      const parseWa = (t) => {
        if (!t) return "";
        const esc = (s) => s.replace(/[&<>]/g, (c) => ({ "&":"&amp;","<":"&lt;",">":"&gt;" }[c]));
        let s = esc(t);
        s = s.replace(/\*([^\n*]+)\*/g, "<b>$1</b>");
        s = s.replace(/_([^\n_]+)_/g, "<i>$1</i>");
        s = s.replace(/~([^\n~]+)~/g, "<s>$1</s>");
        s = s.replace(/```([^\n`]+)```/g, "<code class='font-mono px-1 py-0.5 bg-paper-50 rounded text-[10px]'>$1</code>");
        s = s.replace(/\{\{([^}]+)\}\}/g, '<span class="inline-block bg-wa-bubble text-wa-deep rounded-full px-1.5 text-[10.5px] font-semibold font-mono">{{$1}}</span>');
        s = s.replace(/\n/g, "<br>");
        return s;
      };

      const updatePreview = () => {
        try {
          const titleRaw = document.getElementById("campaign-name")?.value?.trim() || "";
          const bodyRaw  = document.getElementById("message-body")?.value?.trim()  || "";
          const headerRaw = document.getElementById("cc-header")?.value?.trim()     || "";
          const footer   = document.getElementById("footer")?.value?.trim()        || "";
          // CTA buttons (text) + quick replies — both render as preview
          // chips, mirroring how they'll appear under the bubble.
          // Collect each button's label + kind so the preview can show the
          // right CTA glyph (link / copy / phone) or a plain quick-reply.
          // When the Buttons section is hidden for the selected engine
          // (Twilio/WABA custom sends can't carry buttons), the preview must
          // show none too — otherwise it would render stale buttons that
          // won't actually be sent.
          const btnHidden = document.querySelector('[data-custom-buttons]')?.classList.contains('hidden');
          const ccButtonObjs = btnHidden ? [] : Array.from(document.querySelectorAll("#cc-btn-list .cc-btn-row"))
            .map((r) => {
              const text = (r.querySelector(".cc-cta-text, .cc-reply-text")?.value || "").trim();
              const kind = r.querySelector(".cc-cta-type")?.value || "quick_reply";
              return { text, kind };
            })
            .filter((b) => b.text);
          const ccButtons = ccButtonObjs.map((b) => b.text);
          const button   = ccButtons[0] || "";
          const deviceSel  = document.getElementById("device");
          const deviceVal  = deviceSel?.value || "";
          const deviceLabel = deviceVal
            ? (deviceSel?.options?.[deviceSel.selectedIndex]?.textContent?.trim() || ("#" + deviceVal))
            : "";

          const type      = selectedType();
          const breakdown = recipientBreakdown();
          const total     = breakdown.total;
          const recipients = numberFormat(total);
          const schedule  = scheduleText();
          const ab = document.getElementById("ab-test")?.checked
            ? `${document.getElementById("split")?.value || 50}%` : "Off";

          // Empty-state toggle for the live preview phone-frame.
          // Show the empty-state when the user hasn't picked a template
          // AND hasn't typed a body AND hasn't picked a device.
          const tplPicked = !!document.querySelector('select[name="template_id"]')?.value;
          const hasContent = bodyRaw.length > 0 || tplPicked;
          const frame = document.getElementById("preview-frame");
          const empty = document.getElementById("preview-empty");
          if (frame && empty) {
            frame.classList.toggle("hidden", !hasContent);
            empty.classList.toggle("hidden", hasContent);
          }

          console.debug("[WA-CAMPAIGN] preview update", {
            mode: breakdown.mode,
            groups: breakdown.groups,
            contacts: breakdown.contacts,
            manual: breakdown.manual,
            csv: breakdown.csvFilename,
            total,
            body_len: bodyRaw.length,
            device: deviceLabel || "(none)",
          });

          set("preview-title",    titleRaw || "Untitled campaign");
          set("preview-device",   deviceLabel || "Pick a device");
          // Body — render WhatsApp markdown so the preview matches what the
          // recipient sees. Only paint the custom body when NOT in template
          // mode (template preview owns the bubble in that case).
          if (selectedType() !== 'template') {
            const pb = document.getElementById("preview-body");
            if (pb) pb.innerHTML = parseWa(fillSamples(bodyRaw, { valueByKey: attrValues })) || "Type your message body.";
            const ph = document.getElementById("preview-header");
            if (ph) { ph.innerHTML = parseWa(fillSamples(headerRaw, { valueByKey: attrValues })); ph.classList.toggle("hidden", !headerRaw); }
          }
          set("preview-format",   format);
          set("preview-recipients", recipients);
          set("preview-schedule", schedule === "Now" ? "Now" : "Later");
          set("preview-type",     type.replace(" message", ""));
          set("preview-ab",       ab);
          set("review-device",    deviceLabel || "Pick a device");
          set("review-type",      type);
          set("review-recipients", recipients);
          set("review-schedule",  schedule);
          set("msg-count",        bodyRaw.length);

          // Footer + button slots — only render when the user actually
          // filled them, otherwise hide so the preview reflects what the
          // recipient will actually see.
          const fNode = document.getElementById("preview-footer");
          if (fNode && selectedType() !== 'template') {
            fNode.textContent = footer;
            fNode.classList.toggle("hidden", !footer);
          }
          // Buttons — render every CTA / quick-reply chip the operator added
          // (template mode leaves this to renderTemplatePreview).
          const bNode = document.getElementById("preview-buttons");
          if (bNode && selectedType() !== 'template') {
            if (ccButtonObjs.length) {
              // CTA glyphs mirror what WhatsApp renders for each kind:
              // link (visit_website), copy (copy_code), phone (call_phone),
              // none for a quick reply.
              const ccGlyph = (kind) => {
                if (kind === 'visit_website') return '<svg viewBox="0 0 16 16" class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M6.5 9.5l3-3M5 8a2.5 2.5 0 0 1 0-3.5l1.5-1.5a2.5 2.5 0 0 1 3.5 3.5M11 8a2.5 2.5 0 0 1 0 3.5l-1.5 1.5a2.5 2.5 0 0 1-3.5-3.5"/></svg>';
                if (kind === 'copy_code') return '<svg viewBox="0 0 16 16" class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="1.6"><rect x="5.5" y="5.5" width="7" height="7" rx="1.2"/><path d="M3.5 10.5V4a.5.5 0 0 1 .5-.5h6.5"/></svg>';
                if (kind === 'call_phone') return '<svg viewBox="0 0 16 16" class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M4 3h2l1 3-1.5 1a7 7 0 0 0 3.5 3.5L11 12l3 1v2a1 1 0 0 1-1 1A11 11 0 0 1 2 4a1 1 0 0 1 1-1z"/></svg>';
                return '';
              };
              bNode.innerHTML = ccButtonObjs.map((b) => {
                const g = ccGlyph(b.kind);
                return `<button type="button" class="pp-btn bg-paper-0 rounded-[7px] px-3 py-2 text-center text-[12px] font-semibold text-wa-deep shadow-[0_1px_1px_rgba(0,0,0,0.06)] inline-flex items-center justify-center gap-1.5">${g}<span>${b.text.replace(/[<>]/g, '')}</span></button>`;
              }).join('');
              bNode.classList.remove("hidden");
            } else {
              bNode.classList.add("hidden");
            }
          }

          // ----- live review checklist + recipients summary -----
          // Billing is plan-first (0 = unlimited) so the cost panel was
          // removed; we only paint the recipients breakdown now.
          // Populate per-source breakdown rows.
          set("rev-src-groups",   numberFormat(breakdown.groups));
          set("rev-src-contacts", numberFormat(breakdown.contacts));
          const manualLabel = breakdown.csvFilename
            ? `${numberFormat(breakdown.manual)} + CSV: ${breakdown.csvFilename}`
            : numberFormat(breakdown.manual);
          set("rev-src-manual", manualLabel);
          set("rev-src-total",  numberFormat(breakdown.total));

          const setCheck = (id, ok, label) => {
            const el = document.getElementById(id);
            if (!el) return;
            const dot = el.querySelector('span:first-child');
            if (ok) {
              el.classList.remove('text-ink-500');
              el.classList.add('text-ink-900');
              if (dot) { dot.classList.remove('bg-paper-300'); dot.classList.add('bg-wa-deep'); dot.textContent = '✓'; }
            } else {
              el.classList.add('text-ink-500');
              el.classList.remove('text-ink-900');
              if (dot) { dot.classList.add('bg-paper-300'); dot.classList.remove('bg-wa-deep'); dot.textContent = '·'; }
            }
            if (label) {
              const span = el.childNodes[el.childNodes.length - 1];
              if (span && span.nodeType === Node.TEXT_NODE) span.textContent = ' ' + label;
              else el.appendChild(document.createTextNode(' ' + label));
            }
          };

          const deviceOk     = !!deviceVal;
          const contentOk    = bodyRaw.length > 0 || tplPicked;
          const recipientsOk = total > 0;

          setCheck('check-device',     deviceOk,     deviceOk     ? 'Device picked: ' + deviceLabel : 'Pick a connected device');
          setCheck('check-content',    contentOk,    contentOk    ? 'Message ready'                : 'Add a message body or template');
          setCheck('check-recipients', recipientsOk, recipientsOk ? `${recipients} recipient${total === 1 ? '' : 's'} ready` : 'Add at least one recipient');
        } catch (e) {
          // Catch-all so a transient missing element doesn't kill the
          // whole listener and freeze the preview. Logs once per error.
          console.error("[WA-CAMPAIGN] updatePreview threw:", e);
        }
      };

      // ── Per-step validation gate ─────────────────────────────────────
      // Block forward navigation (Next button OR a step-node jump) until
      // the current step's required fields are filled. Backward moves are
      // always allowed. validateStep returns {ok, msg, el} so the caller
      // can toast + highlight the offending field. Mirrors the auto-reply
      // create wizard gate.
      const toast = (m, k = 'error') => (window.toast ? window.toast(m, k) : alert(m));
      function flashInvalid(el) {
        if (!el) return;
        el.classList.add('ring-2', 'ring-accent-coral/60', 'border-accent-coral');
        try { el.focus({ preventScroll: false }); } catch (_) {}
        el.scrollIntoView({ behavior: 'smooth', block: 'center' });
        setTimeout(() => el.classList.remove('ring-2', 'ring-accent-coral/60', 'border-accent-coral'), 2600);
      }
      function validateStep(n) {
        if (n === 1) {
          const name = document.getElementById('campaign-name');
          if (!name || !name.value.trim()) return { ok: false, msg: 'Name your campaign.', el: name };
          const device = document.getElementById('device');
          if (!device || !device.value) return { ok: false, msg: 'Select a device.', el: device };
          if (!document.querySelector("input[name='campaign_type']:checked")) {
            return { ok: false, msg: 'Choose a campaign type.', el: document.querySelector('.type-tile') };
          }
        }
        if (n === 2) {
          const type = selectedType();   // radio value: "text" | "template" | "flow"
          if (type === 'template') {
            const tpl = document.getElementById('template-only');
            if (!tpl || !tpl.value) return { ok: false, msg: 'Choose a template.', el: tpl };
          } else if (type === 'flow') {
            const flow = document.getElementById('flow-only');
            if (flow && !flow.value) return { ok: false, msg: 'Select a flow.', el: flow };
          } else {
            const body = document.getElementById('message-body');
            const bodyVal = (body && ('value' in body ? body.value : body.textContent) || '').trim();
            if (!bodyVal) return { ok: false, msg: 'Write your message.', el: body };
          }
        }
        if (n === 3) {
          if (recipientTotal() === 0) {
            const mode = document.querySelector("input[name='recipient_mode']:checked")?.value || 'groups';
            const elMap = {
              groups: document.querySelector("[data-mode-pane='groups']"),
              csv: document.getElementById('csv-file-input'),
              manual: document.getElementById('manual-numbers'),
            };
            return { ok: false, msg: 'Pick recipients.', el: elMap[mode] || elMap.groups };
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

      document.querySelectorAll(".step-node").forEach(node => node.addEventListener("click", () => {
        const target = Number(node.dataset.n);
        // Free to jump backward; forward jumps must pass the current step.
        if (target <= current || gateForward(current)) setStep(target);
      }));
      prev.addEventListener("click", () => setStep(current - 1));
      next.addEventListener("click", () => { if (current < total && gateForward(current)) setStep(current + 1); });

      // Render the initial step on load so the Next/Launch buttons + step
      // indicators reflect step 1 immediately. Without this, the stepper only
      // renders after the first click, so "Launch campaign" (which should be
      // last-step-only) leaks onto step 1.
      setStep(current);

      document.querySelectorAll(".type-tile").forEach(tile => {
        tile.addEventListener("click", () => {
          document.querySelectorAll(".type-tile").forEach(item => {
            item.classList.remove("border-wa-deep", "bg-[#F0F8F6]");
            item.classList.add("border-paper-200");
            item.querySelector(".type-check")?.classList.add("opacity-0");
            item.querySelector(".type-check")?.classList.remove("opacity-100");
          });
          tile.classList.add("border-wa-deep", "bg-[#F0F8F6]");
          tile.classList.remove("border-paper-200");
          const radio = tile.querySelector("input");
          if (radio) {
            radio.checked = true;
            radio.dispatchEvent(new Event('change', { bubbles: true }));
          }
          tile.querySelector(".type-check")?.classList.remove("opacity-0");
          tile.querySelector(".type-check")?.classList.add("opacity-100");
          updatePreview();
          if (typeof syncCampaignSurfaces === 'function') syncCampaignSurfaces();
        });
      });

      document.querySelectorAll(".schedule-tile").forEach(tile => {
        tile.addEventListener("click", () => {
          document.querySelectorAll(".schedule-tile").forEach(item => {
            item.classList.remove("border-wa-deep", "bg-[#F0F8F6]");
            item.classList.add("border-paper-200");
          });
          tile.classList.add("border-wa-deep", "bg-[#F0F8F6]");
          tile.classList.remove("border-paper-200");
          const radio = tile.querySelector("input");
          if (radio) {
            radio.checked = true;
            radio.dispatchEvent(new Event('change', { bubbles: true }));
          }
          updatePreview();
          if (typeof syncScheduleFields === 'function') syncScheduleFields();
        });
      });

      // ── Header Type dropdown (None / Text) ──────────────────────────
      // Header is a single optional text line (custom_header) — no type
      // dropdown. It renders as the message title in the live preview.
      const ccHeaderInput = document.getElementById('cc-header');
      ccHeaderInput?.addEventListener('input', updatePreview);

      // ── Attachment Type dropdown (None / Image / Video / Document) ───
      // Cloned from the template builder's Attachment section. Picking a
      // type reveals ONLY that type's upload tile ([data-media-pane]) and
      // hides + clears the rest so a stale upload never rides along. The
      // chosen type also drives the `format` chip + the preview attachment
      // placeholder. The send-path input names (custom_image / custom_video
      // / custom_document) are untouched.
      const ccAttachType = document.getElementById('cc-attach-type');
      const mediaPanes = document.querySelectorAll('[data-media-pane]');
      const ppAttach = document.getElementById('preview-attach');
      function syncMediaPane() {
        const sel = ccAttachType?.value || 'none';
        format = sel === 'none' ? 'Text' : sel;
        mediaPanes.forEach((pane) => {
          const isActive = pane.getAttribute('data-media-pane') === sel;
          pane.classList.toggle('hidden', !isActive);
          if (!isActive) {
            const input = pane.querySelector('input[type="file"]');
            if (input) {
              input.value = '';
              const title = pane.querySelector('[data-media-title]');
              if (title && title.dataset.placeholder) title.textContent = title.dataset.placeholder;
            }
          }
        });
        // Paint the preview attachment placeholder for the chosen type
        // (an uploaded image overrides this with a real thumbnail below).
        if (ppAttach && selectedType() !== 'template') {
          if (sel === 'none') {
            ppAttach.classList.add('hidden');
            ppAttach.style.cssText = '';
            ppAttach.textContent = '';
          } else if (!ppAttach.style.backgroundImage) {
            ppAttach.classList.remove('hidden');
            ppAttach.textContent = sel + ' preview';
          }
        }
        updatePreview();
      }
      ccAttachType?.addEventListener('change', () => {
        // Clear any prior image thumbnail when the type changes.
        if (ppAttach) { ppAttach.style.cssText = ''; ppAttach.textContent = ''; }
        syncMediaPane();
      });

      // Reflect the chosen file's name in the tile + paint image thumbnails
      // into the preview bubble (matching the template builder).
      mediaPanes.forEach((pane) => {
        const input = pane.querySelector('input[type="file"]');
        const title = pane.querySelector('[data-media-title]');
        if (title) title.dataset.placeholder = title.textContent;
        input?.addEventListener('change', () => {
          const f = input.files?.[0];
          if (title) title.textContent = f ? f.name : (title.dataset.placeholder || title.textContent);
          if (ppAttach && f && f.type.startsWith('image/')) {
            const r = new FileReader();
            r.onload = (ev) => {
              ppAttach.classList.remove('hidden');
              ppAttach.textContent = '';
              ppAttach.style.cssText = `background-image:url('${ev.target.result}');background-size:cover;background-position:center;height:120px;`;
            };
            r.readAsDataURL(f);
          }
          updatePreview();
        });
      });
      syncMediaPane();

      // ── Buttons — CTA / Quick reply / Mix tabs (clone of template builder) ─
      // CTA rows submit as custom_buttons[i][text]/[url]; quick-reply rows
      // submit as custom_quick_replies[i]. Both are read by the verified
      // send path's $extras['buttons'] / $extras['quick_replies']. We keep
      // a stable running index so removing a middle row never collides names.
      const ccBtnList = document.getElementById('cc-btn-list');
      const ccBtnAdd  = document.getElementById('cc-btn-add');
      let ccBtnIdx = 1; // index 0 is the seed CTA row
      let ccQrIdx  = 0;
      let ccBtnMode = 'cta';

      function ccApplyBtnMode(mode) {
        ccBtnMode = mode;
        document.querySelectorAll('#cc-btn-list .cc-btn-row').forEach((r) => {
          const kind = r.dataset.kind;
          r.classList.toggle('hidden', !(mode === 'mix' || mode === kind));
        });
        const lbl = ccBtnAdd?.querySelector('[data-cc-add-label]');
        if (lbl) lbl.textContent = mode === 'reply' ? 'Add quick reply' : mode === 'mix' ? 'Add button' : 'Add CTA button';
      }
      document.querySelectorAll('#cc-btn-type .seg-btn').forEach((b) => b.addEventListener('click', () => {
        document.querySelectorAll('#cc-btn-type .seg-btn').forEach((x) => x.classList.toggle('active', x === b));
        ccApplyBtnMode(b.dataset.bt || 'cta');
      }));

      // Max 3 interactive buttons total (WhatsApp/Baileys cap).
      const CC_BTN_CAP = 3;
      function ccCount() { return document.querySelectorAll('#cc-btn-list .cc-btn-row').length; }
      function ccSyncAddBtn() {
        if (ccBtnAdd) ccBtnAdd.classList.toggle('hidden', ccCount() >= CC_BTN_CAP);
      }

      // A CTA row shows EITHER the [url] field (visit_website) or the
      // [value] field (copy_code / call_phone) based on its kind dropdown.
      // The inactive field is disabled so it never submits a stale value;
      // mergeButtonsFooter() reads url for visit_website, value otherwise.
      function ccSyncCtaKind(row) {
        const sel = row.querySelector('.cc-cta-type');
        if (!sel) return; // quick-reply rows have no kind
        const kind = sel.value;
        const url = row.querySelector('.cc-cta-url');
        const val = row.querySelector('.cc-cta-value');
        const showUrl = kind === 'visit_website';
        if (url) { url.classList.toggle('hidden', !showUrl); url.disabled = !showUrl; }
        if (val) {
          val.classList.toggle('hidden', showUrl); val.disabled = showUrl;
          val.placeholder = kind === 'call_phone' ? '+15551234567' : 'PROMO50';
        }
      }

      function ccWire(row) {
        row.querySelectorAll('input,select').forEach((i) => {
          i.addEventListener('input', updatePreview);
          i.addEventListener('change', updatePreview);
        });
        row.querySelector('.cc-cta-type')?.addEventListener('change', () => { ccSyncCtaKind(row); updatePreview(); });
        row.querySelector('[data-cc-remove]')?.addEventListener('click', () => { row.remove(); ccSyncAddBtn(); updatePreview(); });
        ccSyncCtaKind(row);
      }
      // Wire the seed row.
      document.querySelectorAll('#cc-btn-list .cc-btn-row').forEach(ccWire);

      function ccAddCta() {
        if (ccCount() >= CC_BTN_CAP) return;
        const i = ccBtnIdx++;
        const r = document.createElement('div');
        r.className = 'cc-btn-row grid grid-cols-[130px_1fr_1fr_28px] gap-1.5 items-center';
        r.dataset.kind = 'cta';
        r.innerHTML =
          `<select name="custom_buttons[${i}][type]" class="cc-cta-type w-full px-2 py-2 border border-paper-200 rounded-lg bg-white text-[12px] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10">` +
            `<option value="visit_website">Visit website</option>` +
            `<option value="copy_code">Copy code</option>` +
            `<option value="call_phone">Call phone</option>` +
          `</select>` +
          `<input type="text" name="custom_buttons[${i}][text]" maxlength="25" placeholder="Button text" class="cc-cta-text w-full px-3 py-2 border border-paper-200 rounded-lg bg-white text-[12.5px] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10">` +
          `<input type="text" name="custom_buttons[${i}][url]" placeholder="https://..." class="cc-cta-url w-full px-3 py-2 border border-paper-200 rounded-lg bg-white text-[12.5px] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10">` +
          `<input type="text" name="custom_buttons[${i}][value]" placeholder="PROMO50" class="cc-cta-value w-full px-3 py-2 border border-paper-200 rounded-lg bg-white text-[12.5px] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10 hidden">` +
          `<span class="w-7 h-7 rounded-[7px] inline-flex items-center justify-center text-ink-500 cursor-pointer transition hover:bg-[#FFEDE8] hover:text-accent-coral" data-cc-remove title="Remove"><svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 4l8 8M12 4l-8 8"/></svg></span>`;
        ccBtnList.appendChild(r); ccWire(r); ccSyncAddBtn(); updatePreview();
      }
      function ccAddReply() {
        if (ccCount() >= CC_BTN_CAP) return;
        const i = ccQrIdx++;
        const r = document.createElement('div');
        r.className = 'cc-btn-row grid grid-cols-[1fr_28px] gap-1.5 items-center';
        r.dataset.kind = 'reply';
        r.innerHTML =
          `<input type="text" name="custom_quick_replies[${i}]" maxlength="25" placeholder="Reply text (max 25)" class="cc-reply-text w-full px-3 py-2 border border-paper-200 rounded-lg bg-white text-[12.5px] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10">` +
          `<span class="w-7 h-7 rounded-[7px] inline-flex items-center justify-center text-ink-500 cursor-pointer transition hover:bg-[#FFEDE8] hover:text-accent-coral" data-cc-remove title="Remove"><svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 4l8 8M12 4l-8 8"/></svg></span>`;
        ccBtnList.appendChild(r); ccWire(r); ccSyncAddBtn(); updatePreview();
      }
      ccBtnAdd?.addEventListener('click', () => { if (ccBtnMode === 'reply') ccAddReply(); else ccAddCta(); });
      ccApplyBtnMode('cta');
      ccSyncAddBtn();

      // Variable-mapping panel for the CUSTOM body — same engine as the
      // template builder. Binds to compose-textarea's hidden
      // [name="custom_message_variable_map"] field; the slot→attribute
      // map it writes is consumed by the controller at send time.
      initVariableMap({
        body:   document.getElementById('message-body'),
        hidden: document.querySelector('[name="custom_message_variable_map"]'),
        rows:   document.getElementById('cc-var-map-rows'),
        empty:  document.getElementById('cc-var-map-empty'),
      });

      document.getElementById("split").addEventListener("input", event => {
        document.getElementById("split-val").textContent = `${event.target.value}%`;
        updatePreview();
      });

      // A/B split slider visibility — hide whole split block when toggle off.
      const abToggle = document.getElementById('ab-test');
      const abSplitWrap = document.getElementById('split')?.closest('div')?.parentElement;
      function syncAbSplit() {
        const on = !!abToggle?.checked;
        const splitContainer = document.getElementById('split')?.closest('.grid > div');
        if (splitContainer) splitContainer.classList.toggle('hidden', !on);
        // Variant B content blocks live IN the compose step (message-B textarea
        // + template-B picker), each tagged [data-ab-b]. Reveal when A/B is on.
        document.querySelectorAll('[data-ab-b]').forEach((el) => el.classList.toggle('hidden', !on));
      }
      abToggle?.addEventListener('change', syncAbSplit);
      syncAbSplit();

      // Step 2 surface — switch between custom-message / template / flow
      // based on the campaign_type radios picked in step 1. Each surface
      // is wrapped in [data-surface] inside step-2 so we can flip them.
      function syncCampaignSurfaces() {
        const t = selectedType();   // radio value: "text" | "template" | "flow"
        const map = { 'text': 'custom', 'template': 'template', 'flow': 'flow' };
        const target = map[t] || 'custom';
        document.querySelectorAll('[data-surface]').forEach((el) => {
          el.classList.toggle('hidden', el.dataset.surface !== target);
        });
      }

      // ----- Template preview -----
      // When a template is picked from #template-only, fetch its
      // body/header/footer/buttons/attachment via /templates/api/{id}
      // and populate the EXISTING right-side LIVE PREVIEW pane so the
      // operator sees a faithful preview of what each recipient will
      // get. There is no second in-form preview.
      const tplSelect = document.getElementById('template-only');
      const $$ = (id) => document.getElementById(id);

      const defaultPreview = {
          body:   $$('preview-body')?.textContent || '',
          footer: $$('preview-footer')?.textContent || '',
          button: $$('preview-button')?.textContent || '',
      };

      function clearTemplatePreview() {
          if ($$('preview-header')) {
              $$('preview-header').textContent = '';
              $$('preview-header').classList.add('hidden');
          }
          if ($$('preview-attach')) {
              $$('preview-attach').textContent = '';
              $$('preview-attach').style.backgroundImage = '';
              $$('preview-attach').classList.add('hidden');
          }
          if ($$('preview-body'))   $$('preview-body').textContent   = defaultPreview.body;
          if ($$('preview-footer')) $$('preview-footer').textContent = defaultPreview.footer;
          const btnsWrap = $$('preview-buttons');
          if (btnsWrap) {
              btnsWrap.innerHTML = `<button id="preview-button" type="button" class="w-full rounded-lg bg-paper-0 border border-wa-green/30 px-3 py-1.5 text-[11px] font-semibold text-wa-deep">${(defaultPreview.button || 'Shop offer').replace(/[<>]/g,'')}</button>`;
          }
      }

      async function renderTemplatePreview(id) {
        if (!id) { clearTemplatePreview(); return; }
        try {
          const res = await fetch(`/templates/api/${id}`, {
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
          });
          if (!res.ok) throw new Error('HTTP ' + res.status);
          const data = await res.json();
          // /templates/api/{id} returns { ok: true, template: {...} } —
          // older callers used { data: {...} } so we accept both.
          const tpl  = data.template || data.data || data || {};
          // Flatten the template's nested variable_map
          // ({header:[{num,key}], body:[{num,key}]}) to a {slot:key} map so
          // the preview can resolve positional {{N}} → a sample value.
          const slotMap = {};
          const vmap = tpl.variable_map || {};
          ['header', 'body'].forEach((sec) => {
            (Array.isArray(vmap[sec]) ? vmap[sec] : []).forEach((e) => {
              if (e && e.num != null && e.key) slotMap[String(e.num)] = String(e.key);
            });
          });
          const sampleOpts = { valueByKey: attrValues, slotMap };
          const body = fillSamples(tpl.template_body || tpl.body || '', sampleOpts);
          const head = fillSamples(tpl.header || '', sampleOpts);
          const foot = tpl.footer || '';
          const lang = tpl.language || 'en_US';
          const btns = Array.isArray(tpl.buttons) ? tpl.buttons : [];
          const att  = tpl.attachment_type;
          const attFile = tpl.attachment_file || null;
          const tplName = tpl.template_name || tpl.name || '';

          if ($$('preview-header')) {
              $$('preview-header').textContent = head;
              $$('preview-header').classList.toggle('hidden', !head);
          }
          if ($$('preview-body')) $$('preview-body').textContent = body;
          if ($$('preview-footer')) {
              $$('preview-footer').textContent = foot;
              $$('preview-footer').classList.toggle('hidden', !foot);
          }
          const attachEl = $$('preview-attach');
          if (attachEl) {
              if (att && att !== 'none') {
                  if (att === 'image' && attFile) {
                      attachEl.style.backgroundImage    = `url('/storage/${attFile}')`;
                      attachEl.style.backgroundSize     = 'cover';
                      attachEl.style.backgroundPosition = 'center';
                      attachEl.style.height             = '110px';
                      attachEl.textContent = '';
                  } else {
                      attachEl.style.backgroundImage = '';
                      attachEl.style.height          = '';
                      attachEl.textContent = att.charAt(0).toUpperCase() + att.slice(1) + ' preview';
                  }
                  attachEl.classList.remove('hidden');
              } else {
                  attachEl.classList.add('hidden');
                  attachEl.style.backgroundImage = '';
              }
          }
          const btnsWrap = $$('preview-buttons');
          if (btnsWrap) {
              btnsWrap.innerHTML = btns.length
                  ? btns.map((b) => `<button type="button" class="w-full rounded-lg bg-paper-0 border border-wa-green/30 px-3 py-1.5 text-[11px] font-semibold text-wa-deep">${(b.text || 'Button').replace(/[<>]/g,'')}</button>`).join('')
                  : '';
              // The element starts hidden (and updatePreview re-hides it in
              // custom mode). Filling innerHTML without clearing `hidden`
              // meant a template's CTAs were rendered into an invisible box —
              // the preview looked like a plain text message.
              btnsWrap.classList.toggle('hidden', btns.length === 0);
          }
          const fmtChip = $$('preview-format');
          if (fmtChip) fmtChip.textContent = lang;
          if (tplName && $$('preview-title') && selectedType() === 'template') {
              $$('preview-title').textContent = tplName;
          }
        } catch (e) {
          clearTemplatePreview();
          window.WaToaster?.error?.('Could not load template: ' + e.message);
        }
      }
      tplSelect?.addEventListener('change', (e) => renderTemplatePreview(e.target.value));
      if (tplSelect?.value) renderTemplatePreview(tplSelect.value);

      // When the user switches campaign type away from "template",
      // wipe any template-applied preview so the right-side bubble
      // reflects the active surface again.
      document.querySelectorAll('input[name="campaign_type"]').forEach((r) => {
          r.addEventListener('change', () => {
              if (selectedType() !== 'template') clearTemplatePreview();
          });
      });

      // ----- Flow preview + Test-flow button -----
      const flowSelect  = document.getElementById('flow-only');
      const flowPreview = document.getElementById('flow-preview');
      const flowTestBtn = document.getElementById('flow-test-btn');
      function renderFlowPreview(id, label) {
        if (!id) {
          flowPreview?.classList.add('hidden');
          flowTestBtn?.classList.add('hidden');
          return;
        }
        const $ = (id) => document.getElementById(id);
        $('flow-preview-name').textContent = label || 'Flow';
        $('flow-preview-meta').textContent = '#' + id;
        $('flow-preview-trigger').textContent = 'Trigger fires the flow when the campaign launches. Each recipient walks the flow independently / replies route them through branches.';
        flowPreview.classList.remove('hidden');
        if (flowTestBtn) {
          flowTestBtn.href = '/flows/builder/' + id + '?test=1';
          flowTestBtn.classList.remove('hidden');
        }
      }
      flowSelect?.addEventListener('change', (e) => {
        const opt = e.target.options[e.target.selectedIndex];
        renderFlowPreview(e.target.value, opt?.textContent?.trim());
      });
      if (flowSelect?.value) {
        const opt = flowSelect.options[flowSelect.selectedIndex];
        renderFlowPreview(flowSelect.value, opt?.textContent?.trim());
      }
      document.querySelectorAll('input[name="campaign_type"]').forEach((r) => {
        r.addEventListener('change', syncCampaignSurfaces);
      });
      document.querySelectorAll('.type-tile').forEach((tile) => {
        tile.addEventListener('click', () => setTimeout(syncCampaignSurfaces, 0));
      });
      syncCampaignSurfaces();

      // Schedule fields visibility — when "Send now" is picked, hide
      // date/time/timezone/batch fields. They only matter for
      // "Schedule later" and "Recurring".
      function syncScheduleFields() {
        const s = selectedSchedule();   // radio value: "now" | "scheduled" | "recurring"
        const isNow = s === 'now' || s === 'Send now';
        const isRecurring = s === 'recurring' || s === 'Recurring';
        document.querySelectorAll('[data-schedule-field]').forEach((el) => {
          el.classList.toggle('hidden', isNow);
        });
        // Repeat / Repeat-until cadence belongs to RECURRING only — never show
        // it for a one-off "Schedule later" (that was the bug).
        document.querySelectorAll('[data-recurring-field]').forEach((el) => {
          el.classList.toggle('hidden', !isRecurring);
        });
        document.querySelectorAll('[data-schedule-now-note]').forEach((el) => {
          el.classList.toggle('hidden', !isNow);
        });
      }
      document.querySelectorAll('.schedule-tile').forEach((tile) => {
        tile.addEventListener('click', () => setTimeout(syncScheduleFields, 0));
      });
      document.querySelectorAll('input[name="schedule_type"]').forEach((r) => {
        r.addEventListener('change', syncScheduleFields);
      });
      syncScheduleFields();

      // Keep the Smart-delivery "Times in <tz>" label in sync with the Schedule
      // step's timezone dropdown — the active-hours window is interpreted in
      // exactly that timezone (never UTC), so what the user sees is what runs.
      const tzSelect = document.getElementById('timezone');
      const syncSmartTz = () => {
        const tz = tzSelect?.value || '';
        if (!tz) return;
        document.querySelectorAll('[data-smart-tz]').forEach((el) => { el.textContent = tz; });
      };
      tzSelect?.addEventListener('change', syncSmartTz);
      syncSmartTz();

      document.getElementById("campaignForm").addEventListener("input", updatePreview);
      document.getElementById("campaignForm").addEventListener("change", updatePreview);
      // The send-time mapping panel writes into a hidden field, which fires
      // no input event the form can see — it signals us directly instead so
      // the phone preview reflects typed overrides immediately.
      document.addEventListener("tlm:changed", updatePreview);
      // Direct listeners on the recipient inputs — form-level bubbling
      // can miss `input` events on browser-restored textareas after a
      // hard refresh, and the file input never bubbles `input`.
      document.getElementById("manual-numbers")?.addEventListener("input", updatePreview);
      document.getElementById("csv-file-input")?.addEventListener("change", (e) => {
        const f = e.target.files?.[0];
        const echo = document.getElementById('csv-filename');
        if (echo) echo.textContent = f ? `selected: ${f.name}` : '';
        if (!f) { csvCount = 0; updatePreview(); return; }
        // Count the data rows so the review total + the "at least one recipient"
        // guard include the CSV. Drop a header row when the first line has no
        // digits (i.e. it's the "name,phone" label row, not a real number).
        const r = new FileReader();
        r.onload = () => {
          const lines = String(r.result || '').split(/\r?\n/).map(s => s.trim()).filter(Boolean);
          if (lines.length && !/\d/.test(lines[0])) lines.shift();
          csvCount = lines.length;
          updatePreview();
        };
        r.onerror = () => { csvCount = 0; updatePreview(); };
        r.readAsText(f);
      });

      // ----------------------------------------------------------------
      // Recipient mode tabs — show only the pane matching the picked radio.
      // Each radio carries data-mode-tab; each pane carries data-mode-pane.
      // We also visually highlight the active tile (border-wa-deep + bg).
      // ----------------------------------------------------------------
      function syncRecipientPanes() {
        const mode = document.querySelector("input[name='recipient_mode']:checked")?.value || 'groups';
        document.querySelectorAll('[data-mode-pane]').forEach((p) => {
          p.classList.toggle('hidden', p.getAttribute('data-mode-pane') !== mode);
        });
        document.querySelectorAll('.recipient-mode-tile').forEach((t) => {
          const isActive = t.getAttribute('data-mode-tab') === mode;
          t.classList.toggle('border-wa-deep', isActive);
          t.classList.toggle('bg-[#F0F8F6]', isActive);
          t.classList.toggle('border-paper-200', !isActive);
        });
        updatePreview();
      }
      document.querySelectorAll("input[name='recipient_mode']").forEach((r) => {
        r.addEventListener('change', syncRecipientPanes);
      });
      syncRecipientPanes();

      // One more recompute after the load tick so the initial paint
      // reflects values restored by browser autocomplete.
      setTimeout(updatePreview, 50);

      // ----------------------------------------------------------------
      // AJAX submit for the create form. Mirrors the helper used on
      // user-contacts-index.js — keep this file standalone (no shared
      // import) because the wa-campaigns flow is the only consumer.
      // ----------------------------------------------------------------
      async function ajaxSubmit(form) {
        const formData = new FormData(form);
        const method = (formData.get('_method') || form.method || 'POST').toUpperCase();
        formData.delete('_method');
        const csrf = document.querySelector('meta[name="csrf-token"]')?.content || formData.get('_token');
        const headers = { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' };
        if (csrf) headers['X-CSRF-TOKEN'] = csrf;
        let init = { method: method === 'GET' ? 'GET' : 'POST', headers };
        if (method !== 'GET' && method !== 'POST') formData.append('_method', method);
        if (method !== 'GET') init.body = formData;

        let res;
        try {
          res = await fetch(form.action, init);
        } catch (e) {
          window.toast?.('Network error. Please try again.', 'error');
          return null;
        }
        let json = null;
        try { json = await res.json(); } catch (_) {}
        if (!res.ok) {
          if (res.status === 422 && json?.errors) {
            const firstErr = Object.values(json.errors)[0]?.[0] || 'Validation failed.';
            window.toast?.(firstErr, 'error');
          } else {
            window.toast?.(json?.message || `Request failed (${res.status}).`, 'error');
          }
          return null;
        }
        return json;
      }

      const campaignForm = document.getElementById("campaignForm");
      campaignForm.addEventListener("submit", async (event) => {
        event.preventDefault();
        // The stepper hides #submitBtn until step 5, so submission only fires
        // when the operator has reached the Review pane and confirms.
        const json = await ajaxSubmit(campaignForm);
        if (json && json.ok) {
          window.toast?.(json.message || "Campaign launched.", "success");
          if (json.redirect) {
            window.location = json.redirect;
          }
        }
      });

      setStep(1);
      updatePreview();

      // ──────────────────────────────────────────────────────────────
      // Build with AI — modal open/close, model fetch, generate, fill
      // ──────────────────────────────────────────────────────────────
      const aiEl = (id) => document.getElementById(id);
      const aiModal      = aiEl('ai-modal');
      const openAiBtn    = aiEl('open-ai-modal');
      const closeAiBtn   = aiEl('ai-modal-close');
      const cancelAiBtn  = aiEl('ai-modal-cancel');
      const generateBtn  = aiEl('ai-generate');
      const aiError      = aiEl('ai-error');
      const aiEmpty      = aiEl('ai-empty');
      const aiFormBox    = aiEl('ai-form');
      const aiModelSel   = aiEl('ai-model');
      const aiBusiness   = aiEl('ai-business');
      const aiProduct    = aiEl('ai-product');
      const aiGoal       = aiEl('ai-goal');
      const aiTone       = aiEl('ai-tone');
      const aiAudience   = aiEl('ai-audience');
      const aiOffer      = aiEl('ai-offer');
      const aiCtaLabel   = aiEl('ai-cta-label');
      const aiCtaUrl     = aiEl('ai-cta-url');
      const aiPromptTa   = aiEl('ai-prompt');
      const genIcon      = aiEl('ai-generate-icon');
      const genSpin      = aiEl('ai-generate-spin');
      const genLabel     = aiEl('ai-generate-label');

      let modelsLoaded = false;
      let isGenerating = false;

      function openAiModal() {
          if (!aiModal) return;
          showAiError('');
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
      function showAiError(msg) {
          if (!aiError) return;
          if (!msg) { aiError.classList.add('hidden'); aiError.textContent = ''; }
          else      { aiError.classList.remove('hidden'); aiError.textContent = msg; }
      }

      async function loadModels() {
          try {
              const res = await fetch('/wa-campaigns/api/ai-models', {
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
              console.warn('[ai-wa-campaign] failed to load models', err);
              showAiError('Could not load AI models. Try again.');
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
              if (genLabel) genLabel.textContent = 'Generate campaign';
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
              showAiError('Business name is required.');
              aiBusiness?.focus();
              return;
          }
          const modelValue = aiModelSel?.value || '';
          if (!modelValue) { showAiError('Pick an AI model.'); return; }
          const [provider, model] = modelValue.split('|');
          showAiError('');
          setBusy(true);

          const payload = {
              provider,
              model,
              business_name: business,
              product:       aiProduct?.value || '',
              goal:          aiGoal?.value || '',
              audience:      aiAudience?.value || '',
              offer:         aiOffer?.value || '',
              cta_label:     aiCtaLabel?.value || '',
              cta_url:       aiCtaUrl?.value || '',
              tone:          aiTone?.value || '',
              custom_prompt: aiPromptTa?.value || '',
          };

          try {
              const res = await fetch('/wa-campaigns/api/ai-generate', {
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
                  showAiError(json?.message || `Generation failed (${res.status}).`);
                  setBusy(false);
                  return;
              }
              applyGeneratedCampaign(json.campaign || {});
              setBusy(false);
              closeAiModal();
              if (typeof window.toast === 'function') {
                  window.toast('Campaign draft filled from AI.', 'success');
              }
          } catch (err) {
              console.warn('[ai-wa-campaign] generate failed', err);
              showAiError('Network error talking to the server.');
              setBusy(false);
          }
      }

      function applyGeneratedCampaign(c) {
          if (!c || typeof c !== 'object') return;

          // AI-generated campaigns slot into the "text" / custom-message
          // surface. Force the campaign_type radio to `text` so the
          // surface switcher reveals the right panel before we paint.
          const textRadio = document.querySelector('input[name="campaign_type"][value="text"]');
          if (textRadio) {
              textRadio.checked = true;
              textRadio.dispatchEvent(new Event('change', { bubbles: true }));
          }

          // Identity
          const nameEl = $$('campaign-name');
          if (nameEl && c.campaign_name) nameEl.value = c.campaign_name;

          // Body — `#message-body` is rendered by <x-compose-textarea>.
          // It can be a textarea or a contenteditable; trigger an input
          // event either way so updatePreview() reads the new value.
          const bodyEl = $$('message-body');
          if (bodyEl && c.message) {
              if ('value' in bodyEl) bodyEl.value = c.message;
              else bodyEl.textContent = c.message;
              bodyEl.dispatchEvent(new Event('input', { bubbles: true }));
          }

          // Footer
          const footEl = $$('footer');
          if (footEl && c.footer) {
              footEl.value = c.footer;
              footEl.dispatchEvent(new Event('input', { bubbles: true }));
          }

          // Primary CTA — fill the seed CTA row's text + url inputs
          // (custom_buttons[0][text] / [url]) the verified send path reads.
          const btnEl = document.querySelector('input[name="custom_buttons[0][text]"]');
          if (btnEl && c.button_text) {
              btnEl.value = c.button_text;
              btnEl.dispatchEvent(new Event('input', { bubbles: true }));
              if (c.button_url) {
                  const urlInput = document.querySelector('input[name="custom_buttons[0][url]"]');
                  if (urlInput) {
                      urlInput.value = c.button_url;
                      urlInput.dispatchEvent(new Event('input', { bubbles: true }));
                  }
              }
          }

          // Quick replies — controller accepts custom_quick_replies as
          // an array. The blade doesn't render input rows for these,
          // so we inject hidden fields the form will submit.
          if (Array.isArray(c.quick_replies) && c.quick_replies.length) {
              const form = $$('campaignForm');
              // Wipe any previously-injected hidden quick-reply inputs
              // so re-running AI doesn't accumulate stale rows.
              form?.querySelectorAll('input[data-ai-quick-reply]').forEach((n) => n.remove());
              c.quick_replies.forEach((label, idx) => {
                  if (!label) return;
                  const inp = document.createElement('input');
                  inp.type = 'hidden';
                  inp.name = `custom_quick_replies[${idx}]`;
                  inp.value = label;
                  inp.dataset.aiQuickReply = '1';
                  form?.appendChild(inp);
              });
          }

          if (typeof syncCampaignSurfaces === 'function') syncCampaignSurfaces();
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
