/*
 * Frontend live-editor BRIDGE — runs INSIDE the public-site iframe.
 *
 * It is injected by components/layouts/frontend.blade.php ONLY when an
 * authed platform admin views the page with ?fc_edit=1 (see fc_editing()).
 * Public visitors never load this file.
 *
 * Responsibilities:
 *   - turn every [data-fc] element into an inline editor (click → edit →
 *     blur saves a DRAFT via POST to the admin endpoint),
 *   - tell the parent editor shell which field is selected (postMessage)
 *     so the inspector can show its key + a reset button,
 *   - block real navigation so editing a link's label can't leave the page.
 *
 * Config comes from this script tag's data-* attributes (set in Blade):
 *   data-save  = POST url for drafts
 *   data-csrf  = CSRF token
 */
(function () {
  'use strict';

  var self = document.currentScript;
  var SAVE_URL = self && self.getAttribute('data-save');
  var CSRF = self && self.getAttribute('data-csrf');
  if (!SAVE_URL) return;

  var PARENT_ORIGIN = window.location.origin;
  var editingEl = null;     // element currently in contentEditable
  var originalHTML = '';    // snapshot for Escape-to-cancel

  /* ───────────────────────── styles ───────────────────────── */

  var css = document.createElement('style');
  css.textContent = [
    '[data-fc]{outline-offset:2px;cursor:text;transition:outline-color .12s,background-color .12s;}',
    '[data-fc]:hover{outline:1.5px dashed rgba(7,94,84,.55);background:rgba(37,211,102,.06);}',
    '[data-fc].fc-active{outline:2px solid #075E54 !important;background:rgba(37,211,102,.10) !important;}',
    '[data-fc].fc-saved{animation:fcSaved .9s ease;}',
    '@keyframes fcSaved{0%{background:rgba(37,211,102,.35);}100%{background:transparent;}}',
    '[data-fc-section]{position:relative;}',
    '[data-fc-section].fc-sec-hi{outline:2px dashed rgba(7,94,84,.7);outline-offset:-2px;}',
    '.fc-eyebrow{position:fixed;z-index:2147483000;pointer-events:none;background:#075E54;color:#fff;'
      + 'font:600 10px/1 ui-sans-serif,system-ui;padding:3px 6px;border-radius:5px;transform:translateY(-100%);'
      + 'white-space:nowrap;box-shadow:0 2px 6px rgba(0,0,0,.2);}',
  ].join('');
  document.head.appendChild(css);
  document.documentElement.setAttribute('data-fc-editing', '1');

  // Floating key label that follows the hovered field.
  var eyebrow = document.createElement('div');
  eyebrow.className = 'fc-eyebrow';
  eyebrow.style.display = 'none';
  document.body.appendChild(eyebrow);

  // Floating rich-text toolbar (bold / italic / clear) shown when a
  // richtext field is being edited and text is selected.
  var rtbar = document.createElement('div');
  rtbar.style.cssText = 'position:fixed;z-index:2147483600;display:none;gap:2px;padding:3px;border-radius:9px;'
    + 'background:#0B1F1C;box-shadow:0 6px 20px rgba(0,0,0,.28);transform:translate(-50%,-100%);';
  rtbar.innerHTML = ''
    + '<button data-rt="bold"   style="all:unset;cursor:pointer;color:#fff;font:700 13px/1 Georgia,serif;padding:5px 9px;border-radius:6px;">B</button>'
    + '<button data-rt="italic" style="all:unset;cursor:pointer;color:#fff;font:italic 600 13px/1 Georgia,serif;padding:5px 9px;border-radius:6px;">I</button>'
    + '<button data-rt="removeFormat" title="Clear formatting" style="all:unset;cursor:pointer;color:#9AA8A4;font:600 11px/1 ui-sans-serif;padding:6px 8px;border-radius:6px;">clear</button>';
  document.body.appendChild(rtbar);
  rtbar.addEventListener('mousedown', function (e) {
    // Keep the selection — act before focus leaves the editable.
    var btn = e.target.closest('[data-rt]');
    if (!btn) return;
    e.preventDefault();
    document.execCommand(btn.getAttribute('data-rt'), false, null);
  });

  function positionRtbar() {
    if (!editingEl || typeOf(editingEl) !== 'richtext') { rtbar.style.display = 'none'; return; }
    var sel = window.getSelection();
    if (!sel || sel.isCollapsed || sel.rangeCount === 0) { rtbar.style.display = 'none'; return; }
    var r = sel.getRangeAt(0).getBoundingClientRect();
    if (!r || (r.width === 0 && r.height === 0)) { rtbar.style.display = 'none'; return; }
    rtbar.style.display = 'flex';
    rtbar.style.left = (r.left + r.width / 2) + 'px';
    rtbar.style.top = (r.top - 8) + 'px';
  }
  document.addEventListener('selectionchange', positionRtbar);

  /* ──────────────────────── helpers ────────────────────────── */

  function fieldOf(node) {
    return node && node.closest ? node.closest('[data-fc]') : null;
  }

  function typeOf(el) {
    return el.getAttribute('data-fc-type') || 'text';
  }

  function valueOf(el, type) {
    return type === 'richtext' ? el.innerHTML.trim() : el.innerText.trim();
  }

  function post(url, body) {
    return fetch(url, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' },
      credentials: 'same-origin',
      body: JSON.stringify(body),
    });
  }

  function toParent(msg) {
    try { window.parent.postMessage(msg, PARENT_ORIGIN); } catch (e) {}
  }

  /* ───────────────────── inline editing ────────────────────── */

  function beginEdit(el) {
    if (editingEl === el) return;
    if (editingEl) commitEdit();           // close any other open editor first

    editingEl = el;
    originalHTML = el.innerHTML;
    var type = typeOf(el);

    el.classList.add('fc-active');
    el.setAttribute('contenteditable', type === 'richtext' ? 'true' : 'plaintext-only');
    el.focus();

    // place caret at click point / select all as a starting affordance
    try {
      var range = document.createRange();
      range.selectNodeContents(el);
      var sel = window.getSelection();
      sel.removeAllRanges();
      sel.addRange(range);
    } catch (e) {}

    // If this field is (or sits inside) a link that's editable-by-design
    // (carries data-fc-url), hand the parent the link key + current href so
    // the inspector can offer a "Link target" input.
    var link = el.matches('a[href]') ? el : el.closest('a[href]');
    var urlKey = link ? (el.getAttribute('data-fc-url') || link.getAttribute('data-fc-url')) : null;

    toParent({
      type: 'fc:select',
      key: el.getAttribute('data-fc'),
      fieldType: type,
      text: el.innerText.slice(0, 80),
      urlKey: urlKey || null,
      href: urlKey && link ? link.getAttribute('href') : null,
    });
  }

  function commitEdit() {
    if (!editingEl) return;
    var el = editingEl;
    var type = typeOf(el);
    var value = valueOf(el, type);

    el.removeAttribute('contenteditable');
    el.classList.remove('fc-active');
    editingEl = null;
    rtbar.style.display = 'none';

    // Empty → leave the element as-is but don't save a blank (so the
    // shipped default keeps showing). Admin can use the inspector reset.
    if (value === '') { el.innerHTML = originalHTML; return; }

    toParent({ type: 'fc:saving' });
    post(SAVE_URL, { key: el.getAttribute('data-fc'), value: value, type: type })
      .then(function (r) { return r.ok ? r.json() : Promise.reject(r); })
      .then(function () {
        el.classList.add('fc-saved');
        setTimeout(function () { el.classList.remove('fc-saved'); }, 900);
        toParent({ type: 'fc:saved', key: el.getAttribute('data-fc') });
      })
      .catch(function () {
        el.innerHTML = originalHTML;        // revert on failure
        toParent({ type: 'fc:error', key: el.getAttribute('data-fc') });
      });
  }

  function cancelEdit() {
    if (!editingEl) return;
    editingEl.innerHTML = originalHTML;
    editingEl.removeAttribute('contenteditable');
    editingEl.classList.remove('fc-active');
    editingEl = null;
    rtbar.style.display = 'none';
  }

  /* ───────────────────────── events ────────────────────────── */

  // Capture-phase click: intercept BEFORE the site's own handlers/links.
  document.addEventListener('click', function (e) {
    // Clicks on the floating format toolbar must not close the editor.
    if (e.target.closest && e.target.closest('[data-rt]')) { e.preventDefault(); return; }
    var field = fieldOf(e.target);
    if (field) {
      e.preventDefault();
      e.stopPropagation();
      beginEdit(field);
      return;
    }
    // Not an editable field — block navigation so the admin stays put,
    // but let the field currently being edited commit first.
    var link = e.target.closest && e.target.closest('a[href]');
    if (link) { e.preventDefault(); }
    if (editingEl) commitEdit();
  }, true);

  // Block form submits inside the preview.
  document.addEventListener('submit', function (e) { e.preventDefault(); }, true);

  document.addEventListener('keydown', function (e) {
    if (!editingEl) return;
    if (e.key === 'Escape') { e.preventDefault(); cancelEdit(); editingEl && editingEl.blur(); }
    // Enter commits for single-line text fields (Shift+Enter = newline,
    // and richtext keeps Enter for paragraphs).
    if (e.key === 'Enter' && !e.shiftKey && typeOf(editingEl) !== 'richtext') {
      e.preventDefault();
      commitEdit();
    }
  }, true);

  document.addEventListener('blur', function (e) {
    if (editingEl && e.target === editingEl) commitEdit();
  }, true);

  // Hover eyebrow showing the field key.
  document.addEventListener('mouseover', function (e) {
    var field = fieldOf(e.target);
    if (!field || editingEl) { eyebrow.style.display = 'none'; return; }
    var r = field.getBoundingClientRect();
    eyebrow.textContent = field.getAttribute('data-fc');
    eyebrow.style.display = 'block';
    eyebrow.style.left = Math.max(4, r.left) + 'px';
    eyebrow.style.top = (r.top - 4) + 'px';
  });
  document.addEventListener('mouseout', function (e) {
    if (!fieldOf(e.target)) eyebrow.style.display = 'none';
  });

  /* ─────────────── messages from the parent shell ──────────── */

  window.addEventListener('message', function (e) {
    if (e.origin !== PARENT_ORIGIN || !e.data || typeof e.data !== 'object') return;
    var d = e.data;
    if (d.type === 'fc:highlightSection') {
      document.querySelectorAll('.fc-sec-hi').forEach(function (n) { n.classList.remove('fc-sec-hi'); });
      var sec = document.querySelector('[data-fc-section="' + (d.slug || '') + '"]');
      if (sec) {
        sec.classList.add('fc-sec-hi');
        sec.scrollIntoView({ behavior: 'smooth', block: 'center' });
        setTimeout(function () { sec.classList.remove('fc-sec-hi'); }, 1600);
      }
    }
  });

  // Announce readiness so the parent can enable controls.
  toParent({ type: 'fc:ready' });
})();
