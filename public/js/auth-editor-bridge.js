/*
 * Auth-page live editor BRIDGE — runs INSIDE the auth page (login / register /
 * forgot) when an authed platform admin opens it with ?fc_edit=1. Injected by
 * components/layouts/guest.blade.php; never served to real visitors.
 *
 * Capabilities (saved LIVE to auth.* SystemSettings — no draft/publish step):
 *   - [data-fc="{page}.{field}"]  → click to edit text inline (Enter saves,
 *     Esc cancels, empty reverts to the shipped default).
 *   - [data-ae-media="{page}"]    → the big side panel; hover shows a toolbar
 *     to upload an image / video / GIF or remove it (reverts to the showcase).
 *
 * Config from this script tag's data-* attributes (set in Blade):
 *   data-save-text   = POST url for text   {key,value}
 *   data-save-media  = POST url for media  (multipart: page, media)
 *   data-clear-media = POST url to clear   {page}
 *   data-csrf        = CSRF token
 */
(function () {
  'use strict';

  var self = document.currentScript;
  var SAVE_TEXT = self && self.getAttribute('data-save-text');
  var SAVE_MEDIA = self && self.getAttribute('data-save-media');
  var CLEAR_MEDIA = self && self.getAttribute('data-clear-media');
  var CSRF = self && self.getAttribute('data-csrf');
  if (!SAVE_TEXT) return;

  var editingEl = null;
  var originalHTML = '';

  /* ───────────────────────── styles ───────────────────────── */
  var css = document.createElement('style');
  css.textContent = [
    '[data-fc]{outline-offset:2px;cursor:text;transition:outline-color .12s,background-color .12s;border-radius:3px;}',
    '[data-fc]:hover{outline:1.5px dashed rgba(37,211,102,.7);background:rgba(37,211,102,.10);}',
    '[data-fc].ae-active{outline:2px solid #25D366 !important;background:rgba(37,211,102,.14) !important;}',
    '[data-fc].ae-saved{animation:aeSaved .9s ease;}',
    '@keyframes aeSaved{0%{background:rgba(37,211,102,.45);}100%{background:transparent;}}',
    '[data-ae-media]{position:relative;}',
    '.ae-mediabar{position:absolute;z-index:2147483000;top:14px;left:14px;display:flex;gap:6px;'
      + 'opacity:0;transition:opacity .15s;pointer-events:none;}',
    '[data-ae-media]:hover .ae-mediabar{opacity:1;pointer-events:auto;}',
    '.ae-mediabar button{all:unset;cursor:pointer;background:#075E54;color:#fff;font:600 11px/1 ui-sans-serif,system-ui;'
      + 'padding:7px 11px;border-radius:8px;box-shadow:0 2px 8px rgba(0,0,0,.35);}',
    '.ae-mediabar button.ae-danger{background:#B42318;}',
    '.ae-hint{position:fixed;z-index:2147483600;bottom:16px;left:50%;transform:translateX(-50%);background:#0B1F1C;'
      + 'color:#fff;font:600 12px/1.3 ui-sans-serif,system-ui;padding:9px 16px;border-radius:999px;box-shadow:0 6px 24px rgba(0,0,0,.4);}',
  ].join('');
  document.head.appendChild(css);
  document.documentElement.setAttribute('data-ae-editing', '1');

  // Persistent helper hint.
  var hint = document.createElement('div');
  hint.className = 'ae-hint';
  hint.textContent = 'Editing mode — click any text to change it, hover the side panel to set an image / video.';
  document.body.appendChild(hint);
  setTimeout(function () { hint.style.transition = 'opacity .4s'; hint.style.opacity = '0'; }, 5000);

  function postJson(url, body) {
    return fetch(url, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' },
      credentials: 'same-origin',
      body: JSON.stringify(body),
    });
  }
  function postForm(url, formData) {
    return fetch(url, {
      method: 'POST',
      headers: { 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' },
      credentials: 'same-origin',
      body: formData,
    });
  }

  /* ───────────────────── text inline editing ───────────────── */
  function fieldOf(node) { return node && node.closest ? node.closest('[data-fc]') : null; }

  function beginEdit(el) {
    if (editingEl === el) return;
    if (editingEl) commitEdit();
    editingEl = el;
    originalHTML = el.innerHTML;
    el.classList.add('ae-active');
    el.setAttribute('contenteditable', 'plaintext-only');
    el.focus();
    try {
      var range = document.createRange();
      range.selectNodeContents(el);
      var sel = window.getSelection();
      sel.removeAllRanges();
      sel.addRange(range);
    } catch (e) {}
  }

  function commitEdit() {
    if (!editingEl) return;
    var el = editingEl;
    var value = el.innerText.trim();
    el.removeAttribute('contenteditable');
    el.classList.remove('ae-active');
    editingEl = null;
    if (value === '') { el.innerHTML = originalHTML; return; }   // keep shipped default
    postJson(SAVE_TEXT, { key: el.getAttribute('data-fc'), value: value })
      .then(function (r) { return r.ok ? r.json() : Promise.reject(r); })
      .then(function () {
        el.classList.add('ae-saved');
        setTimeout(function () { el.classList.remove('ae-saved'); }, 900);
      })
      .catch(function () { el.innerHTML = originalHTML; });
  }

  function cancelEdit() {
    if (!editingEl) return;
    editingEl.innerHTML = originalHTML;
    editingEl.removeAttribute('contenteditable');
    editingEl.classList.remove('ae-active');
    editingEl = null;
  }

  /* ───────────────────── media (image/video) ───────────────── */
  var fileInput = document.createElement('input');
  fileInput.type = 'file';
  fileInput.accept = 'image/*,video/mp4,video/webm,image/gif';
  fileInput.style.display = 'none';
  document.body.appendChild(fileInput);
  var pendingPage = null;

  fileInput.addEventListener('change', function () {
    if (!fileInput.files || !fileInput.files[0] || !pendingPage) return;
    var fd = new FormData();
    fd.append('page', pendingPage);
    fd.append('media', fileInput.files[0]);
    hint.style.opacity = '1';
    hint.textContent = 'Uploading…';
    postForm(SAVE_MEDIA, fd)
      .then(function (r) { return r.ok ? r.json() : Promise.reject(r); })
      .then(function () { location.reload(); })       // re-render with the new media
      .catch(function () { hint.textContent = 'Upload failed — check the file type / size (max 20 MB).'; });
    fileInput.value = '';
  });

  // Build a hover toolbar on each media panel.
  document.querySelectorAll('[data-ae-media]').forEach(function (panel) {
    var page = panel.getAttribute('data-ae-media');
    var bar = document.createElement('div');
    bar.className = 'ae-mediabar';
    var up = document.createElement('button');
    up.textContent = 'Set image / video / GIF';
    up.addEventListener('click', function (e) { e.preventDefault(); e.stopPropagation(); pendingPage = page; fileInput.click(); });
    bar.appendChild(up);
    var rm = document.createElement('button');
    rm.className = 'ae-danger';
    rm.textContent = 'Remove';
    rm.addEventListener('click', function (e) {
      e.preventDefault(); e.stopPropagation();
      postJson(CLEAR_MEDIA, { page: page })
        .then(function (r) { return r.ok ? r.json() : Promise.reject(r); })
        .then(function () { location.reload(); })
        .catch(function () {});
    });
    bar.appendChild(rm);
    panel.appendChild(bar);
  });

  /* ───────────────────────── events ────────────────────────── */
  document.addEventListener('click', function (e) {
    // Media toolbar clicks handled by their own listeners.
    if (e.target.closest && e.target.closest('.ae-mediabar')) return;
    var field = fieldOf(e.target);
    if (field) { e.preventDefault(); e.stopPropagation(); beginEdit(field); return; }
    var link = e.target.closest && e.target.closest('a[href]');
    if (link) e.preventDefault();               // stay on the page
    if (editingEl) commitEdit();
  }, true);

  document.addEventListener('submit', function (e) { e.preventDefault(); }, true);  // don't submit login

  document.addEventListener('keydown', function (e) {
    if (!editingEl) return;
    if (e.key === 'Escape') { e.preventDefault(); cancelEdit(); }
    if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); commitEdit(); }
  }, true);

  document.addEventListener('blur', function (e) {
    if (editingEl && e.target === editingEl) commitEdit();
  }, true);
})();
