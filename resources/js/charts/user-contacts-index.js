import intlTelInput from 'intl-tel-input/intlTelInputWithUtils';
import 'intl-tel-input/styles';
import { themeColor } from '../theme-colors.js';

export default function init() {
    const panels = {
        contacts: document.getElementById("contactsPanel"),
        groups: document.getElementById("groupsPanel")
      };
      const searchInput = document.getElementById("contactSearch");
      const groupSelect = document.getElementById("groupSelect");
      const rowsContainer = document.getElementById("contactsBody");
      let rows = Array.from(document.querySelectorAll("[data-contact-row]"));
      const emptyState = document.getElementById("emptyState");
      const selectAll = document.getElementById("selectAll");
      const deleteSelected = document.getElementById("deleteSelected");
      const bulkBar = document.getElementById("bulkBar");
      const selectAllMatchingBar = document.getElementById("selectAllMatchingBar");
      const contactFilter = window.WADESK_CONTACT_FILTER || { q: "", group: "", tag: "", total: 0, shown: 0 };
      // True once the operator extends the page selection to EVERY contact
      // matching the current filter (not just the rows rendered on this page).
      let selectAllMatching = false;

      // ----------------------------------------------------------------
      // intl-tel-input on the create + edit phone fields — full searchable
      // country list with flags (same picker as /register and /account),
      // replacing the old 8-entry country_code <select>. The hidden
      // country_code input still carries "+<dial>" to the server so the
      // controller contract is unchanged.
      // ----------------------------------------------------------------
      let createIti = null, editIti = null, createCc = null;
      (function setupPhonePickers() {
        // Platform-wide default country from layout meta. Admin picks once
        // in /admin/settings/general and every contact-form phone picker
        // here switches to that country with no further code change.
        const _defIso = (document.querySelector('meta[name="default-country-iso"]')?.content || 'in').toLowerCase();
        const _preferred = Array.from(new Set([_defIso, "in", "us", "gb", "ae", "sg"]));
        const ITI_OPTS = {
          preferredCountries: _preferred,
          separateDialCode: true,
          nationalMode: false,
          dropdownContainer: document.body,
        };
        const isoFromCc = (ccVal) => {
          const d = String(ccVal || "").replace(/[^\d]/g, "");
          const map = { "1":"us", "44":"gb", "971":"ae", "65":"sg", "91":"in", "62":"id", "60":"my", "66":"th", "63":"ph", "92":"pk", "880":"bd" };
          return map[d] || _defIso;
        };
        const createPhone = document.getElementById("create-phone");
        createCc = document.getElementById("create-cc");
        if (createPhone) {
          createIti = intlTelInput(createPhone, { ...ITI_OPTS, initialCountry: isoFromCc(createCc?.value) });
          const sync = () => { if (createCc) createCc.value = "+" + createIti.getSelectedCountryData().dialCode; };
          sync();
          createPhone.addEventListener("countrychange", sync);
        }
        const editPhone = document.getElementById("edit-phone");
        const editCc = document.getElementById("edit-cc");
        if (editPhone) {
          editIti = intlTelInput(editPhone, { ...ITI_OPTS, initialCountry: _defIso });
          const sync = () => { if (editCc) editCc.value = "+" + editIti.getSelectedCountryData().dialCode; };
          editPhone.addEventListener("countrychange", sync);
        }
      })();

      // ----------------------------------------------------------------
      // Generic AJAX form submitter. Pass `{ silent: true }` to skip the
      // success toast (used on destructive actions where the modal +
      // row removal is the visual feedback). Errors always toast.
      // ----------------------------------------------------------------
      async function ajaxSubmit(form, options = {}) {
          const formData = new FormData(form);
          const method = (formData.get('_method') || form.method || 'POST').toUpperCase();
          formData.delete('_method');
          const url = form.action;
          const csrf = document.querySelector('meta[name="csrf-token"]')?.content || formData.get('_token');

          const headers = {
              'Accept': 'application/json',
              'X-Requested-With': 'XMLHttpRequest',
          };
          if (csrf) headers['X-CSRF-TOKEN'] = csrf;

          let init = { method: method === 'GET' ? 'GET' : 'POST', headers };
          if (method !== 'GET' && method !== 'POST') {
              formData.append('_method', method);
          }
          if (method !== 'GET') {
              init.body = formData;
          }

          // Loading state — disable the submit button + show a spinner so a
          // long request (e.g. a big CSV import) shows visible progress instead
          // of a dead, unresponsive screen.
          const submitBtn = form.querySelector('[type="submit"], button:not([type="button"]):not([data-modal-open])');
          const origHtml  = submitBtn ? submitBtn.innerHTML : null;
          if (submitBtn) {
              submitBtn.disabled = true;
              submitBtn.innerHTML =
                  '<span class="inline-flex items-center gap-2">' +
                  '<svg class="animate-spin w-3.5 h-3.5" viewBox="0 0 16 16" fill="none">' +
                  '<circle cx="8" cy="8" r="6" stroke="currentColor" stroke-width="2" stroke-opacity="0.25"/>' +
                  '<path d="M14 8a6 6 0 0 0-6-6" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>' +
                  'Working…</span>';
          }
          const restoreBtn = () => { if (submitBtn) { submitBtn.disabled = false; submitBtn.innerHTML = origHtml; } };

          let res;
          try {
              res = await fetch(url, init);
          } catch (e) {
              restoreBtn();
              window.toast?.('Network error. Please try again.', 'error');
              return null;
          }

          let json = null;
          try { json = await res.json(); } catch (_) {}

          if (!res.ok) {
              restoreBtn();
              if (res.status === 422 && json?.errors) {
                  const firstErr = Object.values(json.errors)[0]?.[0] || 'Validation failed.';
                  window.toast?.(firstErr, 'error');
              } else {
                  window.toast?.(json?.message || `Request failed (${res.status}).`, 'error');
              }
              return null;
          }

          restoreBtn();
          if (json?.message && !options.silent) window.toast?.(json.message, 'success');
          return json;
      }

      // ----------------------------------------------------------------
      // Tabs / filtering / search
      // ----------------------------------------------------------------
      function setTab(tab) {
        document.querySelectorAll("[data-panel]").forEach((el) => el.classList.toggle("hidden", el.dataset.panel !== tab));
        document.querySelectorAll("[data-tab-target]").forEach((btn) => {
          const active = btn.dataset.tabTarget === tab;
          if (btn.classList.contains("top-tab")) btn.classList.toggle("active", active);
          if (btn.classList.contains("side-tab")) {
            btn.classList.toggle("bg-wa-deep", active);
            btn.classList.toggle("text-paper-0", active);
            btn.classList.toggle("font-medium", active);
            btn.classList.toggle("text-ink-700", !active);
          }
        });
      }

      function setGroupFilter(group) {
        const normalized = (group === undefined || group === null || group === "") ? "all" : String(group);
        if (groupSelect) {
          const hasOpt = Array.from(groupSelect.options).some((o) => o.value === normalized);
          if (hasOpt) groupSelect.value = normalized;
        }
        document.querySelectorAll(".group-filter").forEach((btn) => {
          const btnVal = btn.dataset.groupFilter || btn.dataset.filterGroup || "";
          const active = btnVal === normalized || (normalized === "all" && btnVal === "all");
          btn.classList.toggle("bg-paper-50", active);
          btn.classList.toggle("text-ink-900", active);
          btn.classList.toggle("font-medium", active);
        });
        filterRows(normalized);
      }

      // Source-filter state shared with filterRows(). Drives the sidebar
      // "Imported" + "No group" chips. Distinct from group-filter because
      // it filters by an orthogonal axis (origin/source vs. group
      // membership), and "No group" means "no group_ids at all".
      let sourceFilter = "all";

      function filterRows(groupOverride) {
        rows = Array.from(document.querySelectorAll("[data-contact-row]"));
        const q = (searchInput?.value || "").trim().toLowerCase();
        const group = (groupOverride !== undefined ? groupOverride : (groupSelect ? groupSelect.value : "all")) || "all";
        let visible = 0;
        rows.forEach((row) => {
          const matchText = !q || (row.dataset.search || "").includes(q);
          const ids = (row.dataset.groupIds || "").split(",").map((s) => s.trim()).filter(Boolean);
          const matchGroup = group === "all" || ids.includes(String(group));
          let matchSource = true;
          if (sourceFilter === "import")    matchSource = (row.dataset.source || "") === "import";
          else if (sourceFilter === "no_group") matchSource = ids.length === 0;
          const show = matchText && matchGroup && matchSource;
          row.classList.toggle("hidden", !show);
          if (show) visible += 1;
        });
        if (emptyState) emptyState.classList.toggle("hidden", visible !== 0 || rows.length === 0);
        // Only override the server-rendered total when a client-side source
        // filter (Imported / No group) is narrowing the page; otherwise leave
        // the real workspace/filtered total the server already rendered.
        if (sourceFilter !== "all") {
          document.querySelectorAll("[data-visible-count]").forEach((el) => { el.textContent = visible; });
        }
      }

      // Sidebar source-filter chips (Imported / No group). Click toggles
      // off when the same chip is re-clicked, so the user can clear back
      // to "all" without a separate reset button.
      document.querySelectorAll("[data-source-filter]").forEach((btn) => {
        btn.addEventListener("click", () => {
          const val = btn.dataset.sourceFilter || "all";
          sourceFilter = (sourceFilter === val) ? "all" : val;
          document.querySelectorAll(".source-filter").forEach((b) => {
            const active = b.dataset.sourceFilter === sourceFilter;
            b.classList.toggle("bg-paper-50", active);
            b.classList.toggle("text-ink-900", active);
            b.classList.toggle("font-medium", active);
          });
          filterRows();
        });
      });

      // Build the URL for a group view. "All groups" → the unfiltered list.
      function groupUrl(val) {
        const base = window.WADESK_CONTACTS_URL || window.location.pathname;
        return (val && val !== "all") ? `${base}?group=${encodeURIComponent(val)}` : base;
      }
      document.querySelectorAll("[data-tab-target]").forEach((btn) => {
        btn.addEventListener("click", () => {
          // "All contacts" while a server filter (?q / ?group) is active clears
          // it by navigating to the unfiltered list. Otherwise just switch panel.
          if (btn.dataset.tabTarget === "contacts" && !btn.dataset.groupJump &&
              /[?&](q|group)=/.test(window.location.search)) {
            window.location.href = window.WADESK_CONTACTS_URL || window.location.pathname;
            return;
          }
          setTab(btn.dataset.tabTarget);
          if (btn.dataset.groupJump) window.location.href = groupUrl(btn.dataset.groupJump);
        });
      });
      // Sidebar group list + "All groups" open a fresh server-filtered PAGE
      // (?group=<id>) so the view shows EVERY contact in that group, not just
      // whatever happened to be on the current page.
      document.querySelectorAll("[data-group-filter], [data-filter-group]").forEach((btn) => btn.addEventListener("click", () => {
        const val = btn.dataset.groupFilter || btn.dataset.filterGroup || "all";
        window.location.href = groupUrl(val);
      }));
      // Search box: debounce, then submit the GET form so the SERVER searches
      // across ALL contacts (encrypted columns can't be matched in SQL, so the
      // controller filters the whole decrypted set — not just this page).
      let searchTimer = null;
      if (searchInput) searchInput.addEventListener("input", () => {
        clearTimeout(searchTimer);
        searchTimer = setTimeout(() => {
          const form = document.getElementById("contactFilterForm");
          if (form) form.submit();
        }, 450);
      });
      // The group dropdown submits the GET form via its inline onchange.

      function updateSelection() {
        const rowChecks = Array.from(document.querySelectorAll(".row-check"));
        const checked = rowChecks.filter((cb) => cb.checked);
        const any = checked.length > 0;
        if (bulkBar) {
          bulkBar.classList.toggle("hidden", !any);
          bulkBar.classList.toggle("flex", any);
        }
        // Count reflects the EFFECTIVE selection — the whole filtered book when
        // "all matching" is active, otherwise just the ticked rows on this page.
        document.querySelectorAll("[data-bulk-count]").forEach((el) => {
          el.textContent = selectAllMatching ? contactFilter.total : checked.length;
        });
        // Select-all-matching bar: only offer it while every row on the page is
        // ticked AND more matching rows exist off-page. Unticking any row
        // cancels the extended selection.
        if (selectAllMatchingBar) {
          const allOnPage = rowChecks.length > 0 && checked.length === rowChecks.length;
          if (!allOnPage) selectAllMatching = false;
          const offer = allOnPage && contactFilter.total > contactFilter.shown;
          selectAllMatchingBar.classList.toggle("hidden", !offer);
          selectAllMatchingBar.classList.toggle("flex", offer);
          const idle = selectAllMatchingBar.querySelector("[data-sam-idle]");
          const active = selectAllMatchingBar.querySelector("[data-sam-active]");
          if (idle) idle.classList.toggle("hidden", selectAllMatching);
          if (active) active.classList.toggle("hidden", !selectAllMatching);
        }
        if (!any) hideBulkGroupPicker();
      }
      document.getElementById("selectAllMatchingBtn")?.addEventListener("click", () => {
        selectAllMatching = true;
        updateSelection();
      });
      document.getElementById("selectAllMatchingClear")?.addEventListener("click", () => {
        selectAllMatching = false;
        if (selectAll) selectAll.checked = false;
        document.querySelectorAll(".row-check").forEach((cb) => { cb.checked = false; });
        updateSelection();
      });
      function bindRowCheck(cb) {
        cb.addEventListener("change", updateSelection);
      }
      document.querySelectorAll(".row-check").forEach(bindRowCheck);
      if (selectAll) {
        selectAll.addEventListener("change", () => {
          document.querySelectorAll("[data-contact-row]").forEach((row) => {
            if (!row.classList.contains("hidden")) {
              const cb = row.querySelector(".row-check");
              if (cb) cb.checked = selectAll.checked;
            }
          });
          updateSelection();
        });
      }
      if (deleteSelected) {
        deleteSelected.addEventListener("click", () => {
          const ids = Array.from(document.querySelectorAll(".row-check:checked")).map((cb) => cb.value);
          const slot = document.getElementById("bulkDeleteIds");
          if (slot) {
            slot.innerHTML = ids.map((id) => `<input type="hidden" name="ids[]" value="${id}">`).join("");
          }
          openModal("deleteModal");
        });
      }

      // ----------------------------------------------------------------
      // Bulk actions on the current selection: Export CSV, Add-to-group,
      // Remove-from-group. (Delete reuses the deleteModal above.) Plus the
      // workspace-wide "Remove duplicates" button in the header.
      // ----------------------------------------------------------------
      const bulkGroupPicker = document.getElementById("bulkGroupPicker");
      let bulkGroupAction = "add";
      function hideBulkGroupPicker() { if (bulkGroupPicker) bulkGroupPicker.classList.add("hidden"); }
      function selectedIds() {
        return Array.from(document.querySelectorAll(".row-check:checked")).map((cb) => cb.value);
      }
      async function postJson(url, body) {
        const csrf = document.querySelector('meta[name="csrf-token"]')?.content || "";
        try {
          const res = await fetch(url, {
            method: "POST",
            headers: { "Accept": "application/json", "X-Requested-With": "XMLHttpRequest", "X-CSRF-TOKEN": csrf },
            body,
          });
          return await res.json();
        } catch (e) {
          window.toast?.("Network error. Please try again.", "error");
          return null;
        }
      }
      function reloadWithToast(message) {
        try { sessionStorage.setItem("wadeskPendingToast", JSON.stringify({ message, type: "success" })); } catch (_) {}
        window.location.reload();
      }

      // Export selected → fill the hidden POST form + submit so the browser
      // downloads the CSV (no AJAX needed for a file response).
      document.getElementById("bulkExportBtn")?.addEventListener("click", () => {
        const ids = selectedIds();
        if (!ids.length) return;
        const form = document.getElementById("bulkExportForm");
        const slot = document.getElementById("bulkExportIds");
        if (form && slot) {
          slot.innerHTML = ids.map((id) => `<input type="hidden" name="ids[]" value="${id}">`).join("");
          form.submit();
        }
      });

      function showBulkGroupPicker(action) {
        if (!bulkGroupPicker) return;
        const sel = document.getElementById("bulkGroupSelect");
        if (!sel || sel.options.length === 0) { window.toast?.("Create a group first.", "error"); return; }
        bulkGroupAction = action;
        const title = bulkGroupPicker.querySelector("[data-bulk-group-title]");
        if (title) title.textContent = action === "add" ? "Add to group" : "Remove from group";
        bulkGroupPicker.classList.remove("hidden");
      }
      document.getElementById("bulkAddGroupBtn")?.addEventListener("click", (e) => { e.stopPropagation(); showBulkGroupPicker("add"); });
      document.getElementById("bulkRemoveGroupBtn")?.addEventListener("click", (e) => { e.stopPropagation(); showBulkGroupPicker("remove"); });
      document.getElementById("bulkGroupCancel")?.addEventListener("click", hideBulkGroupPicker);
      document.addEventListener("click", (e) => {
        if (!bulkGroupPicker || bulkGroupPicker.classList.contains("hidden")) return;
        if (bulkGroupPicker.contains(e.target)) return;
        if (e.target.closest("#bulkAddGroupBtn") || e.target.closest("#bulkRemoveGroupBtn")) return;
        hideBulkGroupPicker();
      });

      document.getElementById("bulkGroupApply")?.addEventListener("click", async () => {
        const sel = document.getElementById("bulkGroupSelect");
        const groupId = sel ? sel.value : "";
        if (!groupId) { hideBulkGroupPicker(); return; }
        const body = new FormData();
        body.append("group_id", groupId);
        body.append("action", bulkGroupAction);
        if (selectAllMatching) {
          // Whole filtered book — the server resolves the set from the filter,
          // so a 3,500-contact assignment is one request, not one per page.
          body.append("all", "1");
          body.append("q", contactFilter.q || "");
          body.append("group", contactFilter.group || "");
          body.append("tag", contactFilter.tag || "");
        } else {
          const ids = selectedIds();
          if (!ids.length) { hideBulkGroupPicker(); return; }
          ids.forEach((id) => body.append("ids[]", id));
        }
        const json = await postJson(window.WADESK_CONTACT_BULK_GROUP_URL, body);
        if (json?.ok) reloadWithToast(json.message);
        else if (json) window.toast?.(json.message || "Could not update groups.", "error");
      });

      // ---- Bulk tag / untag -------------------------------------------------
      // Same shape as the group picker above, with one extra affordance: the
      // "add" mode also accepts a brand-new tag name, so an operator can tag a
      // selection with a tag that doesn't exist yet in a single step.
      const bulkTagPicker = document.getElementById("bulkTagPicker");
      let bulkTagAction = "add";
      function hideBulkTagPicker() { if (bulkTagPicker) bulkTagPicker.classList.add("hidden"); }

      function showBulkTagPicker(action) {
        if (!bulkTagPicker) return;
        const sel = document.getElementById("bulkTagSelect");
        // Untag can only ever target a tag that already exists.
        if (action === "remove" && (!sel || !sel.value)) {
          window.toast?.("No tags to remove yet.", "error");
          return;
        }
        bulkTagAction = action;
        const title = bulkTagPicker.querySelector("[data-bulk-tag-title]");
        if (title) title.textContent = action === "add" ? "Add tag" : "Remove tag";
        // Hide the create-new field when untagging — you can't remove a tag
        // you're inventing on the spot.
        bulkTagPicker.querySelector("[data-bulk-tag-new]")?.classList.toggle("hidden", action !== "add");
        hideBulkGroupPicker();
        bulkTagPicker.classList.remove("hidden");
      }
      document.getElementById("bulkTagBtn")?.addEventListener("click", (e) => { e.stopPropagation(); showBulkTagPicker("add"); });
      document.getElementById("bulkUntagBtn")?.addEventListener("click", (e) => { e.stopPropagation(); showBulkTagPicker("remove"); });
      document.getElementById("bulkTagCancel")?.addEventListener("click", hideBulkTagPicker);
      document.addEventListener("click", (e) => {
        if (!bulkTagPicker || bulkTagPicker.classList.contains("hidden")) return;
        if (bulkTagPicker.contains(e.target)) return;
        if (e.target.closest("#bulkTagBtn") || e.target.closest("#bulkUntagBtn")) return;
        hideBulkTagPicker();
      });

      document.getElementById("bulkTagApply")?.addEventListener("click", async () => {
        const ids = selectedIds();
        const sel = document.getElementById("bulkTagSelect");
        const newName = (document.getElementById("bulkTagNew")?.value || "").trim();
        if (!ids.length) { hideBulkTagPicker(); return; }
        // A typed name wins over the dropdown — if the operator bothered to
        // type, that's the tag they mean.
        if (!newName && !(sel && sel.value)) {
          window.toast?.("Pick a tag or type a new name.", "error");
          return;
        }
        const body = new FormData();
        ids.forEach((id) => body.append("ids[]", id));
        if (newName && bulkTagAction === "add") body.append("name", newName);
        else body.append("tag_id", sel.value);
        body.append("action", bulkTagAction);
        const json = await postJson(window.WADESK_CONTACT_BULK_TAG_URL, body);
        if (json?.ok) reloadWithToast(json.message);
        else if (json) window.toast?.(json.message || "Could not update tags.", "error");
      });

      // Remove duplicates (whole workspace) — collapse same-number rows.
      document.getElementById("dedupeBtn")?.addEventListener("click", () => {
        const run = async () => {
          const json = await postJson(window.WADESK_CONTACT_DEDUPE_URL, new FormData());
          if (json?.ok) reloadWithToast(json.message);
          else if (json) window.toast?.(json.message || "Could not remove duplicates.", "error");
        };
        if (window.confirmDialog) {
          window.confirmDialog({
            title: "Remove duplicates?",
            message: "This deletes contacts that repeat the same phone number, keeping the earliest of each. This cannot be undone.",
            confirmText: "Remove duplicates",
            cancelText: "Cancel",
            tone: "danger",
            onConfirm: run,
          });
        } else {
          run();
        }
      });

      // ----------------------------------------------------------------
      // Edit contact: populate form
      // ----------------------------------------------------------------
      const editForm = document.getElementById("editContactForm");
      const editTpl = editForm ? editForm.dataset.updateTemplate : null;
      function bindEditButton(btn) {
        btn.addEventListener("click", () => {
          if (!editForm || !editTpl) return;
          editForm.action = editTpl.replace(":id", btn.dataset.editContact);
          editForm.dataset.contactId = btn.dataset.editContact;
          const setField = (name, val) => {
            const f = editForm.querySelector(`[data-edit-field="${name}"]`);
            if (f) f.value = val ?? "";
          };
          setField("title", btn.dataset.title);
          setField("first_name", btn.dataset.firstName);
          setField("middle_name", btn.dataset.middleName);
          setField("last_name", btn.dataset.lastName);
          setField("country_code", btn.dataset.countryCode);
          const mobile = (btn.dataset.mobile || "").trim();
          const cc = (btn.dataset.countryCode || "").trim();
          const ccDigits = cc.replace(/\D/g, "");                 // "+20" -> "20"
          // National part = the stored number minus any country code it
          // redundantly repeats (some rows store the full number).
          let nat = mobile.replace(/^\+/, "");
          if (ccDigits && nat.startsWith(ccDigits)) nat = nat.slice(ccDigits.length).trim();
          setField("mobile", nat);
          // Feed intl-tel-input a GUARANTEED "+"-prefixed E.164 string so it
          // detects the country FROM THE NUMBER (libphonenumber via withUtils),
          // then read the dial code back into the hidden field. Without the
          // leading "+", a row whose country_code column is blank was parsed as
          // a NATIONAL number for the workspace-default country — so an
          // Egyptian +20 number showed (and could be saved) as Saudi +966.
          if (editIti && (nat || ccDigits)) {
            const e164 = mobile.startsWith("+")
              ? mobile
              : (ccDigits ? "+" + ccDigits + nat : "+" + mobile);
            try { editIti.setNumber(e164); } catch (_) {}
            const ccF = editForm.querySelector('[data-edit-field="country_code"]');
            if (ccF) ccF.value = "+" + editIti.getSelectedCountryData().dialCode;
          }
          setField("email", btn.dataset.email);
          setField("msg", btn.dataset.msg);
          setField("address", btn.dataset.address);
          // Status / source live in custom_attributes server-side; the row carries
          // them as data-status / data-source. Setting .value on the <select>
          // selects the matching option ("" → the "— none —" entry).
          setField("status", btn.dataset.status);
          setField("source", btn.dataset.source);
          let groupIds = [];
          try { groupIds = JSON.parse(btn.dataset.groups || "[]"); } catch (e) { groupIds = []; }
          const groupIdSet = new Set(groupIds.map(String));
          editForm.querySelectorAll("[data-edit-group-input]").forEach((cb) => {
            cb.checked = groupIdSet.has(String(cb.dataset.editGroupInput));
          });
          loadContactTags(editForm.dataset.contactId);
        });
      }
      document.querySelectorAll("[data-edit-contact]").forEach(bindEditButton);

      // ----------------------------------------------------------------
      // Contact tags — attach/detach; attaching fires the flow `tag_added`
      // trigger server-side (ContactsController::attachTag).
      // ----------------------------------------------------------------
      const tagsWrap  = editForm ? editForm.querySelector("[data-edit-tags]") : null;
      const tagInput  = editForm ? editForm.querySelector("[data-tag-input]") : null;
      const tagAddBtn = editForm ? editForm.querySelector("[data-tag-add]") : null;
      const tagList   = document.getElementById("contactTagList");
      const csrfToken = () =>
        ((document.querySelector('meta[name="csrf-token"]') || {}).content) ||
        (editForm && editForm.querySelector('input[name="_token"]') ? editForm.querySelector('input[name="_token"]').value : "");
      const tt = (s) => (window.t ? window.t(s) : s);

      function renderContactTags(tags, all) {
        if (!tagsWrap) return;
        tagsWrap.innerHTML = "";
        if (!tags || !tags.length) {
          const empty = document.createElement("span");
          empty.className = "text-[12px] text-ink-500 px-2 py-1";
          empty.setAttribute("data-tags-empty", "");
          empty.textContent = tt("No tags yet.");
          tagsWrap.appendChild(empty);
        } else {
          tags.forEach((tg) => {
            const chip = document.createElement("span");
            chip.className = "inline-flex items-center gap-[5px] px-2 py-[3px] rounded-full border border-paper-200 bg-paper-0 text-[10.5px] font-semibold whitespace-nowrap";
            const dot = document.createElement("span");
            dot.className = "w-[7px] h-[7px] rounded-full";
            dot.style.background = tg.color || themeColor('wa-deep');
            chip.appendChild(dot);
            chip.appendChild(document.createTextNode(tg.name || ("#" + tg.id)));
            const x = document.createElement("button");
            x.type = "button";
            x.className = "ml-0.5 text-ink-400 hover:text-red-600 leading-none text-[13px]";
            x.textContent = "×";
            x.setAttribute("aria-label", "Remove");
            x.addEventListener("click", () => detachTag(tg.id));
            chip.appendChild(x);
            tagsWrap.appendChild(chip);
          });
        }
        if (tagList && all) {
          tagList.innerHTML = "";
          all.forEach((tg) => { const o = document.createElement("option"); o.value = tg.name; tagList.appendChild(o); });
          tagList.__all = all;
        }

        // Multi-select chip picker. A <datalist> only suggests while you type
        // and takes one tag per Add — an operator tagging a contact with three
        // labels had to type-and-click three times, and never saw which tags
        // exist. Every workspace tag is now a toggle: click to attach, click
        // again to remove. Same endpoints, no new API.
        const picker = document.querySelector("[data-tag-picker]");
        if (picker && all) {
          const attached = new Set((tags || []).map((t) => Number(t.id)));
          picker.innerHTML = "";
          if (!all.length) {
            picker.innerHTML = '<span class="text-[11px] text-ink-500">'
              + tt("No tags in this workspace yet — type a name below to create the first one.")
              + "</span>";
          }
          all.forEach((tg) => {
            const on = attached.has(Number(tg.id));
            const b = document.createElement("button");
            b.type = "button";
            b.className = "inline-flex items-center gap-1.5 pl-2 pr-2.5 py-1 rounded-full border text-[11.5px] font-medium transition "
              + (on ? "border-wa-deep bg-[#F0F8F6] text-wa-deep" : "border-paper-200 bg-paper-0 hover:bg-paper-50 text-ink-700");
            const dot = document.createElement("span");
            dot.className = "w-[7px] h-[7px] rounded-full shrink-0";
            dot.style.background = tg.color || themeColor('wa-deep');
            b.appendChild(dot);
            b.appendChild(document.createTextNode(tg.name || ("#" + tg.id)));
            if (on) {
              const c = document.createElement("span");
              c.className = "text-wa-deep/60 leading-none";
              c.textContent = "✓";
              b.appendChild(c);
            }
            b.addEventListener("click", () => (on ? detachTag(tg.id) : attachTagById(tg.id)));
            picker.appendChild(b);
          });
        }
      }

      /** Attach a KNOWN tag by id — the chip path, no name lookup needed. */
      function attachTagById(tagId) {
        const id = editForm ? editForm.dataset.contactId : "";
        if (!id || !tagId) return;
        fetch("/contacts/" + id + "/tags", {
          method: "POST",
          headers: { "Content-Type": "application/json", Accept: "application/json", "X-CSRF-TOKEN": csrfToken(), "X-Requested-With": "XMLHttpRequest" },
          credentials: "same-origin",
          body: JSON.stringify({ tag_id: tagId }),
        })
          .then((r) => (r.ok ? r.json() : null))
          .then((j) => { if (j && j.ok) loadContactTags(id); })
          .catch(() => {});
      }

      function loadContactTags(id) {
        if (!id || !tagsWrap) return;
        if (tagInput) tagInput.value = "";
        fetch("/contacts/" + id + "/tags", { headers: { Accept: "application/json", "X-Requested-With": "XMLHttpRequest" }, credentials: "same-origin" })
          .then((r) => (r.ok ? r.json() : null))
          .then((j) => { if (j && j.ok) renderContactTags(j.tags, j.all); })
          .catch(() => {});
      }

      function attachTag() {
        const id = editForm ? editForm.dataset.contactId : "";
        const name = tagInput ? tagInput.value.trim() : "";
        if (!id || !name) return;
        // Reuse an existing tag id when the typed name matches, so we don't
        // create a duplicate tag row for the same label.
        let tagId = null;
        const all = (tagList && tagList.__all) || [];
        const hit = all.find((t) => (t.name || "").toLowerCase() === name.toLowerCase());
        if (hit) tagId = hit.id;
        fetch("/contacts/" + id + "/tags", {
          method: "POST",
          headers: { "Content-Type": "application/json", Accept: "application/json", "X-CSRF-TOKEN": csrfToken(), "X-Requested-With": "XMLHttpRequest" },
          credentials: "same-origin",
          body: JSON.stringify(tagId ? { tag_id: tagId } : { name: name }),
        })
          .then((r) => (r.ok ? r.json() : null))
          .then((j) => { if (j && j.ok) { if (tagInput) tagInput.value = ""; loadContactTags(id); } })
          .catch(() => {});
      }

      function detachTag(tagId) {
        const id = editForm ? editForm.dataset.contactId : "";
        if (!id || !tagId) return;
        fetch("/contacts/" + id + "/tags/" + tagId, {
          method: "DELETE",
          headers: { Accept: "application/json", "X-CSRF-TOKEN": csrfToken(), "X-Requested-With": "XMLHttpRequest" },
          credentials: "same-origin",
        })
          .then((r) => (r.ok ? r.json() : null))
          .then((j) => { if (j && j.ok) loadContactTags(id); })
          .catch(() => {});
      }

      if (tagAddBtn) tagAddBtn.addEventListener("click", attachTag);
      if (tagInput) tagInput.addEventListener("keydown", (e) => { if (e.key === "Enter") { e.preventDefault(); attachTag(); } });

      // ----------------------------------------------------------------
      // Group modal: switch between create / edit
      // ----------------------------------------------------------------
      const groupForm = document.getElementById("groupForm");
      const groupCreateAction = groupForm ? groupForm.dataset.createAction : null;
      const groupUpdateTpl = groupForm ? groupForm.dataset.updateTemplate : null;
      const groupMethodInput = document.getElementById("groupFormMethod");
      function resetGroupForm() {
        if (!groupForm) return;
        groupForm.action = groupCreateAction;
        groupForm.dataset.editingId = "";
        if (groupMethodInput) groupMethodInput.value = "";
        const nameF = groupForm.querySelector('[data-group-field="name"]');
        const noteF = groupForm.querySelector('[data-group-field="note"]');
        if (nameF) nameF.value = "";
        if (noteF) noteF.value = "";
        const radios = groupForm.querySelectorAll('input[name="color"]');
        radios.forEach((r, i) => { r.checked = (i === 0); });
      }
      function bindEditGroupButton(btn) {
        btn.addEventListener("click", () => {
          if (!groupForm || !groupUpdateTpl) return;
          groupForm.action = groupUpdateTpl.replace(":id", btn.dataset.editGroup);
          groupForm.dataset.editingId = btn.dataset.editGroup;
          if (groupMethodInput) groupMethodInput.value = "PUT";
          const nameF = groupForm.querySelector('[data-group-field="name"]');
          const noteF = groupForm.querySelector('[data-group-field="note"]');
          if (nameF) nameF.value = btn.dataset.name || "";
          if (noteF) noteF.value = btn.dataset.note || "";
          const targetColor = (btn.dataset.color || "").toLowerCase();
          let matched = false;
          groupForm.querySelectorAll('input[name="color"]').forEach((r) => {
            if (r.value.toLowerCase() === targetColor) { r.checked = true; matched = true; }
            else { r.checked = false; }
          });
          if (!matched) {
            const first = groupForm.querySelector('input[name="color"]');
            if (first) first.checked = true;
          }
        });
      }
      document.querySelectorAll("[data-edit-group]").forEach(bindEditGroupButton);
      document.querySelectorAll('[data-modal-open="groupModal"][data-new-group]').forEach((btn) => {
        btn.addEventListener("click", resetGroupForm);
      });
      document.querySelectorAll('[data-modal-open="groupModal"]:not([data-edit-group]):not([data-new-group])').forEach((btn) => {
        btn.addEventListener("click", resetGroupForm);
      });

      // ----------------------------------------------------------------
      // Modals
      // ----------------------------------------------------------------
      function openModal(id) {
        const m = document.getElementById(id);
        if (!m) return;
        m.classList.add("open");
        document.body.classList.add("overflow-hidden");
      }
      function closeModal(modal) {
        if (typeof modal === "string") modal = document.getElementById(modal);
        if (!modal) return;
        modal.classList.remove("open");
        if (!document.querySelector(".modal.open")) document.body.classList.remove("overflow-hidden");
      }
      document.querySelectorAll("[data-modal-open]").forEach((btn) => btn.addEventListener("click", () => openModal(btn.dataset.modalOpen)));
      document.querySelectorAll(".modal").forEach((modal) => {
        modal.addEventListener("click", (e) => { if (e.target === modal) closeModal(modal); });
        modal.querySelectorAll("[data-modal-close]").forEach((btn) => btn.addEventListener("click", () => closeModal(modal)));
      });
      document.addEventListener("keydown", (e) => {
        if (e.key === "Escape") document.querySelectorAll(".modal.open").forEach(closeModal);
      });

      // ----------------------------------------------------------------
      // Helpers for rendering / counts
      // ----------------------------------------------------------------
      function escapeHtml(str) {
        return String(str ?? "")
          .replace(/&/g, "&amp;")
          .replace(/</g, "&lt;")
          .replace(/>/g, "&gt;")
          .replace(/"/g, "&quot;")
          .replace(/'/g, "&#039;");
      }
      const AVATAR_PALETTE = ["#FFE9E4","#E8F5E9","#FFF6E0","#F3E9FF","#EEF4FF","#FCE0D5"];
      function avatarBg(id) { return AVATAR_PALETTE[(parseInt(id, 10) || 0) % AVATAR_PALETTE.length]; }
      function initialsFor(name) {
        const trimmed = (name || "?").trim();
        return trimmed.substring(0, 2).toUpperCase();
      }
      function buildGroupChipsHtml(groups) {
        if (!Array.isArray(groups)) return "";
        return groups.map((g) => `
          <span class="group-chip inline-flex items-center gap-[5px] px-2 py-[3px] rounded-full border border-paper-200 bg-paper-0 text-[10.5px] font-semibold whitespace-nowrap">
            <span class="group-dot w-[7px] h-[7px] rounded-full" style="background:${escapeHtml(g.color || themeColor('wa-deep'))}"></span>${escapeHtml(g.name)}
          </span>`).join("");
      }
      function renderContactRow(c) {
        const groupSlugs = (c.group_ids || []).map((i) => "g" + i).join(" ");
        const groupIdsCsv = (c.group_ids || []).map(String).join(",");
        const searchKey = `${(c.name || "")} ${(c.email || "")} ${(c.mobile || "")}`.toLowerCase().trim();
        const tr = document.createElement("tr");
        tr.setAttribute("data-contact-row", "");
        tr.dataset.contactId = c.id;
        tr.dataset.groups = groupSlugs;
        tr.dataset.groupIds = groupIdsCsv;
        tr.dataset.search = searchKey;
        tr.innerHTML = `
          <td class="py-3 pl-4 pr-2"><input class="row-check" type="checkbox" value="${c.id}"></td>
          <td class="py-3 px-3"><div class="flex items-center gap-3"><span class="avatar w-[38px] h-[38px] rounded-full border border-ink-900/15 grid place-items-center font-extrabold text-[12px] text-ink-900 shrink-0" style="background:${avatarBg(c.id)}">${escapeHtml(initialsFor(c.name))}</span><div><div class="font-semibold">${escapeHtml(c.name || "")}</div><div class="text-[11px] text-ink-500">${escapeHtml(c.language || "")}</div></div></div></td>
          <td class="py-3 px-3"><div class="flex flex-wrap gap-1">${buildGroupChipsHtml(c.groups)}</div></td>
          <td class="py-3 px-3 text-ink-700">${escapeHtml(c.email || "")}</td>
          <td class="py-3 px-3 mono font-mono text-[12px]">${escapeHtml(c.mobile_masked || c.mobile || "")}</td>
          <td class="py-3 px-3 text-ink-500 max-w-[220px] truncate">${escapeHtml(c.msg || "")}</td>
          <td class="py-3 pr-4 pl-3 text-right">
            <button type="button" data-modal-open="editModal" data-edit-contact="${c.id}" data-name="${escapeHtml(c.name || "")}" data-first-name="${escapeHtml(c.first_name || "")}" data-middle-name="${escapeHtml(c.middle_name || "")}" data-last-name="${escapeHtml(c.last_name || "")}" data-title="${escapeHtml(c.title || "")}" data-mobile="${escapeHtml(c.mobile || "")}" data-country-code="${escapeHtml(c.country_code || "")}" data-email="${escapeHtml(c.email || "")}" data-language="${escapeHtml(c.language || "")}" data-address="${escapeHtml(c.address || "")}" data-msg="${escapeHtml(c.msg || "")}" data-status="${escapeHtml(c.status || "")}" data-source="${escapeHtml(c.source || "")}" data-groups='${escapeHtml(JSON.stringify(c.group_ids || []))}' class="icon-btn w-[30px] h-[30px] rounded-full inline-flex items-center justify-center border border-paper-200 bg-white text-ink-600 transition hover:border-wa-deep hover:text-wa-deep hover:bg-paper-50" title="Edit"><svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M3 11.5V13h1.5L12 5.5 10.5 4 3 11.5z"/></svg></button>
            <form method="POST" action="${escapeHtml(window.WADESK_CONTACT_DESTROY_TEMPLATE || "").replace(":id", c.id)}" data-ajax="delete-contact" data-confirm="Delete this contact?" class="inline">
              <input type="hidden" name="_token" value="${escapeHtml(document.querySelector('meta[name=\"csrf-token\"]')?.content || "")}">
              <input type="hidden" name="_method" value="DELETE">
              <button type="submit" class="icon-btn w-[30px] h-[30px] rounded-full inline-flex items-center justify-center border border-paper-200 bg-white text-ink-600 transition hover:border-wa-deep hover:text-wa-deep hover:bg-paper-50 ml-1 text-accent-coral" title="Delete"><svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M3 4h10M6 4V2h4v2M5 6v7h6V6"/></svg></button>
            </form>
          </td>`;
        return tr;
      }
      function updateContactRow(tr, c) {
        const groupSlugs = (c.group_ids || []).map((i) => "g" + i).join(" ");
        const groupIdsCsv = (c.group_ids || []).map(String).join(",");
        tr.dataset.groups = groupSlugs;
        tr.dataset.groupIds = groupIdsCsv;
        tr.dataset.search = `${(c.name || "")} ${(c.email || "")} ${(c.mobile || "")}`.toLowerCase().trim();
        // Name + initials
        const nameDiv = tr.querySelector("td:nth-child(2) .font-semibold");
        if (nameDiv) nameDiv.textContent = c.name || "";
        const langDiv = tr.querySelector("td:nth-child(2) .text-ink-500");
        if (langDiv) langDiv.textContent = c.language || "";
        const avatarSpan = tr.querySelector("td:nth-child(2) .avatar");
        if (avatarSpan) avatarSpan.textContent = initialsFor(c.name);
        // Groups
        const groupsCell = tr.querySelector("td:nth-child(3) .flex");
        if (groupsCell) groupsCell.innerHTML = buildGroupChipsHtml(c.groups);
        // Email / mobile / memo
        const emailTd = tr.querySelector("td:nth-child(4)");
        if (emailTd) emailTd.textContent = c.email || "";
        const mobileTd = tr.querySelector("td:nth-child(5)");
        if (mobileTd) mobileTd.textContent = c.mobile_masked || c.mobile || "";
        const memoTd = tr.querySelector("td:nth-child(6)");
        if (memoTd) memoTd.textContent = c.msg || "";
        // Refresh edit button data attrs
        const editBtn = tr.querySelector("[data-edit-contact]");
        if (editBtn) {
          editBtn.dataset.editContact = c.id;
          editBtn.dataset.name = c.name || "";
          editBtn.dataset.firstName = c.first_name || "";
          editBtn.dataset.middleName = c.middle_name || "";
          editBtn.dataset.lastName = c.last_name || "";
          editBtn.dataset.title = c.title || "";
          editBtn.dataset.mobile = c.mobile || "";
          editBtn.dataset.countryCode = c.country_code || "";
          editBtn.dataset.email = c.email || "";
          editBtn.dataset.language = c.language || "";
          editBtn.dataset.address = c.address || "";
          editBtn.dataset.msg = c.msg || "";
          editBtn.dataset.status = c.status || "";
          editBtn.dataset.source = c.source || "";
          editBtn.dataset.groups = JSON.stringify(c.group_ids || []);
        }
      }
      function updateAllContactsCount(delta) {
        document.querySelectorAll("[data-visible-count]").forEach((el) => {
          // Visible count is recomputed in filterRows
        });
        filterRows();
      }
      function refreshGroupCheckboxesInModals() {
        // Pull current groups list straight from sidebar tiles in DOM if present.
        // For simplicity we re-fetch the page-level group list from the existing
        // group filter sidebar & rebuild the checkbox markup in both add & edit
        // modals.
        const sidebarGroups = Array.from(document.querySelectorAll('[data-filter-group]')).map((btn) => {
          const id = btn.dataset.filterGroup;
          const dot = btn.querySelector('.group-dot');
          const labelSpan = btn.querySelector('span span');
          const name = btn.querySelector('span')?.textContent?.trim() || "";
          const color = dot ? dot.style.background : themeColor('wa-deep');
          return { id, name, color };
        });
        const buildCheckboxes = (markEditScope) => sidebarGroups.map((g) => `
          <label class="group-chip inline-flex items-center gap-[5px] px-2 py-[3px] rounded-full border border-paper-200 bg-paper-0 text-[10.5px] font-semibold whitespace-nowrap cursor-pointer hover:bg-paper-50 has-[:checked]:bg-wa-bubble has-[:checked]:border-wa-deep">
            <input type="checkbox" name="contact_group[]" value="${escapeHtml(g.id)}"${markEditScope ? ` data-edit-group-input="${escapeHtml(g.id)}"` : ""} class="w-3 h-3 accent-wa-deep">
            <span class="group-dot w-[7px] h-[7px] rounded-full" style="background:${escapeHtml(g.color)}"></span>${escapeHtml(g.name)}
          </label>`).join("");
        const addContainer = document.querySelector('#addContactForm label .lbl + div, #addContactForm .hairline.flex.flex-wrap');
        // Find by label text "Contact groups" — easier:
        const addLabel = Array.from(document.querySelectorAll('#addContactForm label.lbl')).find(l => l.textContent.includes('Contact groups'));
        if (addLabel) {
          const wrapper = addLabel.parentElement.querySelector('.hairline');
          if (wrapper) wrapper.innerHTML = buildCheckboxes(false) || '<span class="text-[12px] text-ink-500 px-2 py-1">No groups yet — create one first.</span>';
        }
        const editGroupsWrap = document.querySelector('#editContactForm [data-edit-groups]');
        if (editGroupsWrap) {
          editGroupsWrap.innerHTML = buildCheckboxes(true) || '<span class="text-[12px] text-ink-500 px-2 py-1">No groups yet.</span>';
        }
      }

      // ----------------------------------------------------------------
      // Wire up generic AJAX submission. Destructive forms (data-ajax
      // starts with "delete-" or has data-destructive) get the themed
      // confirmDialog + silent submit (no success toast). Non-destructive
      // forms keep the existing toast-on-success behaviour.
      // ----------------------------------------------------------------
      function isDestructive(form) {
        const ajaxTag = form.dataset.ajax || "";
        return form.hasAttribute("data-destructive") || ajaxTag.startsWith("delete-") || ajaxTag.startsWith("bulk-delete");
      }
      function attachAjaxSubmit(form) {
        form.addEventListener("submit", async (e) => {
          e.preventDefault();
          const confirmMsg = form.dataset.confirm;
          const destructive = isDestructive(form);
          const run = async () => {
            const json = await ajaxSubmit(form, { silent: destructive });
            if (json && json.ok) {
              form.dispatchEvent(new CustomEvent("ajax:success", { detail: json }));
            }
          };
          if (confirmMsg) {
            window.confirmDialog?.({
              title: destructive ? "Delete?" : "Are you sure?",
              message: confirmMsg,
              confirmText: destructive ? "Delete" : "Confirm",
              cancelText: "Cancel",
              tone: destructive ? "danger" : "info",
              onConfirm: run,
            });
            return;
          }
          await run();
        });
      }
      document.querySelectorAll("form[data-ajax]").forEach(attachAjaxSubmit);

      // ----------------------------------------------------------------
      // Per-form post-success handlers
      // ----------------------------------------------------------------

      // 1) Add contact
      const addContactForm = document.getElementById("addContactForm");
      if (addContactForm) {
        addContactForm.addEventListener("ajax:success", (e) => {
          const c = e.detail?.contact;
          if (!c) return;
          const tbody = document.getElementById("contactsBody");
          // Remove the empty placeholder if present
          if (tbody) {
            const placeholder = tbody.querySelector("tr td[colspan]");
            if (placeholder) placeholder.parentElement.remove();
            const newRow = renderContactRow(c);
            tbody.prepend(newRow);
            // Wire its check/edit/delete handlers
            const cb = newRow.querySelector(".row-check");
            if (cb) bindRowCheck(cb);
            const editBtn = newRow.querySelector("[data-edit-contact]");
            if (editBtn) bindEditButton(editBtn);
            // The new-row modal-open & data-modal-open binding:
            newRow.querySelectorAll("[data-modal-open]").forEach((btn) => btn.addEventListener("click", () => openModal(btn.dataset.modalOpen)));
            const innerForm = newRow.querySelector("form[data-ajax]");
            if (innerForm) attachAjaxSubmit(innerForm);
          }
          closeModal("contactModal");
          addContactForm.reset();
          // reset() clears the phone input but not the flag — put it back to default.
          if (createIti) { try { createIti.setCountry("in"); } catch (_) {} if (createCc) createCc.value = "+91"; }
          filterRows();
        });
      }

      // 2) Edit contact
      if (editForm) {
        editForm.addEventListener("ajax:success", (e) => {
          const c = e.detail?.contact;
          if (!c) return;
          const tr = document.querySelector(`[data-contact-row][data-contact-id="${c.id}"]`);
          if (tr) updateContactRow(tr, c);
          closeModal("editModal");
          filterRows();
        });
      }

      // 3) Delete contact (per-row)
      function attachDeleteContactSuccess(form) {
        form.addEventListener("ajax:success", () => {
          const tr = form.closest("tr");
          if (tr) tr.remove();
          filterRows();
        });
      }
      document.querySelectorAll('form[data-ajax="delete-contact"]').forEach(attachDeleteContactSuccess);

      // 4) Bulk delete
      const bulkDeleteForm = document.getElementById("bulkDeleteForm");
      if (bulkDeleteForm) {
        bulkDeleteForm.addEventListener("ajax:success", (e) => {
          const ids = e.detail?.ids || [];
          ids.forEach((id) => {
            const tr = document.querySelector(`[data-contact-row][data-contact-id="${id}"]`);
            if (tr) tr.remove();
          });
          if (selectAll) selectAll.checked = false;
          updateSelection();
          closeModal("deleteModal");
          filterRows();
        });
      }

      // 5) Group save (create or update)
      if (groupForm) {
        groupForm.addEventListener("ajax:success", (e) => {
          const g = e.detail?.group;
          if (!g) return;
          // Update sidebar group filter list
          const sidebar = document.querySelector('.card .mono.font-mono.text-[10px].uppercase.tracking-[0.16em]');
          // Find sidebar Groups card by label "Groups"
          const groupsCardLabel = Array.from(document.querySelectorAll('.card div')).find(d => d.textContent.trim() === 'Groups');
          // Find existing sidebar button
          const existingSideBtn = document.querySelector(`[data-filter-group="${g.id}"]`);
          if (existingSideBtn) {
            const dot = existingSideBtn.querySelector('.group-dot');
            if (dot) dot.style.background = g.color || themeColor('wa-deep');
            const nameSpan = existingSideBtn.querySelector('span');
            if (nameSpan) {
              // The first <span> is the wrapper containing the dot + name text node.
              const textSpan = existingSideBtn.querySelector('span span:nth-of-type(1)');
              // Easier: replace the visible label - the span structure is dot + text, edit second text node.
              const wrap = existingSideBtn.querySelector(':scope > span:first-child');
              if (wrap) {
                const childNodes = Array.from(wrap.childNodes);
                const textNode = childNodes.find(n => n.nodeType === Node.TEXT_NODE && n.textContent.trim() !== "");
                if (textNode) textNode.textContent = g.name;
              }
            }
            const countSpan = existingSideBtn.querySelector('.mono');
            if (countSpan) countSpan.textContent = (g.members_count ?? 0).toLocaleString();
          } else {
            // Append new group filter button to the second sidebar card (the Groups card)
            const sidebarCards = document.querySelectorAll('aside .card');
            const groupsCard = sidebarCards[1];
            if (groupsCard) {
              const btn = document.createElement('button');
              btn.dataset.groupFilter = g.id;
              btn.dataset.filterGroup = g.id;
              btn.className = "group-filter w-full flex items-center justify-between px-3 py-2 rounded-xl text-[13px] text-ink-700 hover:bg-paper-50";
              btn.innerHTML = `<span class="flex items-center gap-2"><span class="group-dot w-[7px] h-[7px] rounded-full" style="background:${escapeHtml(g.color || themeColor('wa-deep'))}"></span>${escapeHtml(g.name)}</span><span class="mono font-mono text-[11px] text-ink-500">${(g.members_count ?? 0).toLocaleString()}</span>`;
              groupsCard.appendChild(btn);
              btn.addEventListener("click", () => setGroupFilter(String(g.id)));
            }
          }
          // #7b — keep the server-side filter dropdown in sync. Without this a
          // newly-created (or renamed) group never appeared in #groupSelect
          // until a full page reload. Upsert the <option> so it shows now.
          const groupSelectEl = document.getElementById('groupSelect');
          if (groupSelectEl) {
            let opt = groupSelectEl.querySelector(`option[value="${g.id}"]`);
            if (!opt) {
              opt = document.createElement('option');
              opt.value = String(g.id);
              groupSelectEl.appendChild(opt);
            }
            opt.textContent = g.name;
          }
          // Keep the bulk "Add/Remove to group" picker list in sync too.
          const bulkGroupSelEl = document.getElementById('bulkGroupSelect');
          if (bulkGroupSelEl) {
            let bopt = bulkGroupSelEl.querySelector(`option[value="${g.id}"]`);
            if (!bopt) {
              bopt = document.createElement('option');
              bopt.value = String(g.id);
              bulkGroupSelEl.appendChild(bopt);
            }
            bopt.textContent = g.name;
          }
          // Update / append group card on the right (groupsPanel)
          const groupsPanel = document.getElementById("groupsPanel");
          if (groupsPanel) {
            const existingCard = groupsPanel.querySelector(`[data-edit-group="${g.id}"]`)?.closest('.card');
            if (existingCard) {
              const heading = existingCard.querySelector('h3');
              if (heading) heading.textContent = g.name;
              const noteP = existingCard.querySelector('p');
              if (noteP) noteP.textContent = g.note || "";
              const colorBlock = existingCard.querySelector('.w-11.h-11');
              if (colorBlock) colorBlock.style.background = g.color || themeColor('wa-deep');
              const editBtn = existingCard.querySelector('[data-edit-group]');
              if (editBtn) {
                editBtn.dataset.name = g.name || "";
                editBtn.dataset.note = g.note || "";
                editBtn.dataset.color = g.color || "";
              }
            } else {
              // Append a new group card before the dashed "Create new group" tile.
              const dashed = groupsPanel.querySelector('[data-new-group]');
              const card = document.createElement('div');
              card.className = "card bg-white border border-paper-200 rounded-[14px] shadow-card p-5";
              card.innerHTML = `
                <div class="flex items-start gap-3">
                  <span class="w-11 h-11 rounded-xl grid place-items-center text-paper-0" style="background:${escapeHtml(g.color || themeColor('wa-deep'))}"><svg viewBox="0 0 16 16" class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="6" cy="6" r="3"/><path d="M2 14c0-3 2.5-5 4-5s4 2 4 5"/></svg></span>
                  <div class="flex-1 min-w-0">
                    <h3 class="serif font-serif font-normal tracking-[-0.01em] text-[22px] leading-tight truncate">${escapeHtml(g.name)}</h3>
                    <p class="text-[12px] text-ink-500 leading-relaxed">${escapeHtml(g.note || "")}</p>
                  </div>
                  <div class="text-right"><div class="serif font-serif font-normal tracking-[-0.01em] text-[30px] leading-none">${g.members_count ?? 0}</div><div class="mono font-mono text-[10px] text-ink-500 uppercase">contacts</div></div>
                </div>
                <div class="mt-4 flex gap-2">
                  <a href="${(window.WADESK_CONTACTS_URL || '/contacts')}?group=${g.id}" class="filter-tab inline-flex items-center gap-1.5 px-3.5 py-2 rounded-full text-[13px] text-ink-600 cursor-pointer transition hover:bg-paper-50 bg-paper-50 no-underline">View contacts</a>
                  <button type="button" data-modal-open="groupModal" data-edit-group="${g.id}" data-name="${escapeHtml(g.name || "")}" data-note="${escapeHtml(g.note || "")}" data-color="${escapeHtml(g.color || "")}" class="icon-btn w-[30px] h-[30px] rounded-full inline-flex items-center justify-center border border-paper-200 bg-white text-ink-600 transition hover:border-wa-deep hover:text-wa-deep hover:bg-paper-50"><svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M3 11.5V13h1.5L12 5.5 10.5 4 3 11.5z"/></svg></button>
                  <form method="POST" action="${escapeHtml((window.WADESK_GROUP_DESTROY_TEMPLATE || "").replace(":id", g.id))}" data-ajax="delete-group" data-group-id="${g.id}" data-confirm="Delete this group?" class="inline">
                    <input type="hidden" name="_token" value="${escapeHtml(document.querySelector('meta[name=\"csrf-token\"]')?.content || "")}">
                    <input type="hidden" name="_method" value="DELETE">
                    <button type="submit" class="icon-btn w-[30px] h-[30px] rounded-full inline-flex items-center justify-center border border-paper-200 bg-white text-accent-coral transition hover:border-accent-coral hover:bg-accent-coral/10" title="Delete group"><svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M3 4h10M6 4V2h4v2M5 6v7h6V6"/></svg></button>
                  </form>
                </div>`;
              if (dashed) groupsPanel.insertBefore(card, dashed);
              else groupsPanel.appendChild(card);
              // Wire new buttons
              card.querySelectorAll("[data-modal-open]").forEach((btn) => btn.addEventListener("click", () => openModal(btn.dataset.modalOpen)));
              card.querySelectorAll("[data-edit-group]").forEach(bindEditGroupButton);
              card.querySelectorAll("[data-tab-target]").forEach((btn) => {
                btn.addEventListener("click", () => {
                  setTab(btn.dataset.tabTarget);
                  if (btn.dataset.groupJump) setGroupFilter(btn.dataset.groupJump);
                });
              });
              const innerForm = card.querySelector("form[data-ajax]");
              if (innerForm) {
                attachAjaxSubmit(innerForm);
                attachDeleteGroupSuccess(innerForm);
              }
            }
          }
          // Refresh group multi-select pills inside add/edit contact modals
          refreshGroupCheckboxesInModals();
          closeModal("groupModal");
          resetGroupForm();
        });
      }

      // 6) Delete group
      function attachDeleteGroupSuccess(form) {
        form.addEventListener("ajax:success", (e) => {
          const id = e.detail?.id ?? form.dataset.groupId;
          // Remove sidebar button
          const sideBtn = document.querySelector(`[data-filter-group="${id}"]`);
          if (sideBtn) sideBtn.remove();
          // Remove the group card
          const card = form.closest('.card');
          if (card) card.remove();
          // Refresh modal pills
          refreshGroupCheckboxesInModals();
          // If the active filter was this group, clear it.
          setGroupFilter("all");
        });
      }
      document.querySelectorAll('form[data-ajax="delete-group"]').forEach(attachDeleteGroupSuccess);

      // 7) Bulk upload (CSV import) — toast then reload
      const bulkUploadForm = document.getElementById("bulkUploadForm");
      if (bulkUploadForm) {
        bulkUploadForm.addEventListener("ajax:success", (e) => {
          // Persist toast across reload via sessionStorage so it shows after refresh.
          try {
            sessionStorage.setItem("wadeskPendingToast", JSON.stringify({
              message: e.detail?.message || "Import complete.",
              type: "success",
            }));
          } catch (_) {}
          closeModal("bulkModal");
          window.location.reload();
        });
      }
      // Show any pending toast queued by previous reload (CSV upload).
      try {
        const pending = sessionStorage.getItem("wadeskPendingToast");
        if (pending) {
          const obj = JSON.parse(pending);
          sessionStorage.removeItem("wadeskPendingToast");
          if (obj?.message) window.toast?.(obj.message, obj.type || "success");
        }
      } catch (_) {}
}
