/*
 * Frontend live-editor SHELL — runs on the admin page /admin/frontend.
 *
 * Drives the iframe preview of the public site and the side panels
 * (Sections / Theme / Inspector). The inline text editing itself lives in
 * the iframe (frontend-editor-bridge.js); this file handles everything the
 * panels do — colours, show/hide, publish, reset — and reacts to the
 * bridge's postMessage events.
 *
 * Server data comes from the #fc-editor-data JSON script tag.
 */
(function () {
  'use strict';

  var dataEl = document.getElementById('fc-editor-data');
  if (!dataEl) return;
  var CFG = JSON.parse(dataEl.textContent);
  var ORIGIN = window.location.origin;

  var iframe = document.getElementById('fc-frame');
  var current = CFG.activePage;

  /* ──────────────────────── utilities ──────────────────────── */

  function post(url, body) {
    var fd = new URLSearchParams();
    Object.keys(body).forEach(function (k) {
      fd.append(k, typeof body[k] === 'object' ? JSON.stringify(body[k]) : body[k]);
    });
    setStatus('saving');
    return fetch(url, {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-CSRF-TOKEN': CFG.csrf, 'Accept': 'application/json' },
      credentials: 'same-origin',
      body: fd.toString(),
    }).then(function (r) {
      return r.json().then(function (j) {
        setStatus(r.ok ? 'saved' : 'error');
        return { ok: r.ok, body: j };
      });
    }).catch(function (e) { setStatus('error'); throw e; });
  }

  // Autosave status pill in the toolbar.
  var statusEl = document.getElementById('fc-status');
  var statusText = document.getElementById('fc-status-text');
  var statusDot = document.getElementById('fc-status-dot');
  function setStatus(state) {
    if (!statusEl) return;
    statusEl.setAttribute('data-state', state);
    var map = {
      saving: { text: 'Saving…', color: 'text-accent-amber', icon: '<path d="M8 3v3M8 13v-1.5M3 8H6M13 8h-1.5" />' },
      saved:  { text: 'All changes saved', color: 'text-wa-green', icon: '<path d="M3.5 8.5 7 12l5.5-7" />' },
      error:  { text: 'Save failed', color: 'text-accent-coral', icon: '<path d="M8 4v5M8 11.5v.5" />' },
    };
    var m = map[state] || map.saved;
    if (statusText) statusText.textContent = m.text;
    if (statusDot) { statusDot.setAttribute('class', 'w-3.5 h-3.5 ' + m.color); statusDot.innerHTML = m.icon; }
  }

  var toastEl;
  function toast(msg, kind) {
    if (!toastEl) {
      toastEl = document.createElement('div');
      toastEl.style.cssText = 'position:fixed;bottom:20px;left:50%;transform:translateX(-50%);z-index:9999;'
        + 'padding:10px 18px;border-radius:9999px;font:600 12.5px/1 ui-sans-serif,system-ui;'
        + 'box-shadow:0 6px 24px rgba(0,0,0,.18);opacity:0;transition:opacity .2s,transform .2s;pointer-events:none;';
      document.body.appendChild(toastEl);
    }
    toastEl.textContent = msg;
    toastEl.style.background = kind === 'error' ? '#E87A5D' : '#075E54';
    toastEl.style.color = '#fff';
    toastEl.style.opacity = '1';
    toastEl.style.transform = 'translateX(-50%) translateY(0)';
    clearTimeout(toast._t);
    toast._t = setTimeout(function () {
      toastEl.style.opacity = '0';
      toastEl.style.transform = 'translateX(-50%) translateY(8px)';
    }, 2200);
  }

  function reloadFrame() {
    // Cache-bust so the draft re-renders; keep on the current page.
    var url = CFG.pages[current].url;
    iframe.src = url + (url.indexOf('?') >= 0 ? '&' : '?') + '_t=' + Date.now();
  }

  function bumpPending(delta) {
    var badge = document.getElementById('fc-pending');
    if (!badge) return;
    var n = parseInt(badge.getAttribute('data-count') || '0', 10) + (delta || 0);
    if (n < 0) n = 0;
    badge.setAttribute('data-count', String(n));
    badge.textContent = n + (n === 1 ? ' draft' : ' drafts');
    badge.style.display = n > 0 ? '' : 'none';
  }

  /* ───────────────────── full-screen editor ────────────────── */

  var editorMain = document.getElementById('fc-editor-main');
  var fsBtn = document.getElementById('fc-fullscreen');
  if (fsBtn && editorMain) {
    fsBtn.addEventListener('click', function () {
      if (document.fullscreenElement) {
        document.exitFullscreen();
      } else if (editorMain.requestFullscreen) {
        editorMain.requestFullscreen();
      }
    });
    document.addEventListener('fullscreenchange', function () {
      var on = document.fullscreenElement === editorMain;
      // In fullscreen the admin header is gone, so use the full viewport.
      editorMain.classList.toggle('h-screen', on);
      editorMain.classList.toggle('h-[calc(100vh-4rem)]', !on);
      var openI = fsBtn.querySelector('[data-fc-fs-open]');
      var closeI = fsBtn.querySelector('[data-fc-fs-close]');
      if (openI) openI.classList.toggle('hidden', on);
      if (closeI) closeI.classList.toggle('hidden', !on);
    });
  }

  /* ─────────────────── device preview modes ────────────────── */

  var frameWrap = document.getElementById('fc-frame-wrap');
  var DEVICE_W = { desktop: '100%', tablet: '834px', mobile: '414px' };
  document.querySelectorAll('[data-fc-device]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var d = btn.getAttribute('data-fc-device');
      if (frameWrap) frameWrap.style.maxWidth = DEVICE_W[d] || '100%';
      document.querySelectorAll('[data-fc-device]').forEach(function (b) {
        var on = b === btn;
        b.classList.toggle('bg-paper-0', on);
        b.classList.toggle('text-wa-deep', on);
        b.classList.toggle('shadow-sm', on);
        b.classList.toggle('text-ink-500', !on);
      });
    });
  });

  /* ─────────────────────── theme presets ────────────────────── */

  document.querySelectorAll('[data-fc-preset]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var preset = btn.getAttribute('data-fc-preset');
      post(CFG.endpoints.preset, { preset: preset }).then(function (res) {
        if (!res.ok) { toast(res.body.error || 'Could not apply preset', 'error'); return; }
        var tokens = res.body.tokens || {};
        Object.keys(tokens).forEach(function (k) {
          var input = document.querySelector('[data-fc-color="' + k + '"]');
          if (input) {
            input.value = tokens[k];
            var t = input.parentElement.querySelector('[data-fc-color-text]');
            if (t) t.textContent = tokens[k];
          }
        });
        bumpPending(1);
        reloadFrame();
        toast('Theme applied');
      });
    });
  });

  /* ──────────────────────── page tabs ──────────────────────── */

  document.querySelectorAll('[data-fc-page]').forEach(function (tab) {
    tab.addEventListener('click', function () {
      current = tab.getAttribute('data-fc-page');
      document.querySelectorAll('[data-fc-page]').forEach(function (t) {
        var on = t === tab;
        t.classList.toggle('bg-wa-deep', on);
        t.classList.toggle('text-paper-0', on);
        t.classList.toggle('text-ink-600', !on);
      });
      var pageReset = document.getElementById('fc-reset-page');
      if (pageReset) pageReset.setAttribute('data-fc-reset-scope', current);
      renderSections();
      reloadFrame();
    });
  });

  /* ──────────────────────── sections ───────────────────────── */

  var secWrap = document.getElementById('fc-sections');
  var dragSlug = null;

  // The order to show rows in: heroes (fixed) first, then removable
  // sections in the saved order, then any removable not yet in it.
  function orderedSlugs(page) {
    var all = CFG.pages[page].sections;
    var saved = CFG.order[page] || [];
    var heroes = [], removable = [];
    all.forEach(function (s) {
      var m = CFG.sectionMeta[s] || {};
      (m.removable === false ? heroes : removable).push(s);
    });
    var out = [];
    saved.forEach(function (s) { if (removable.indexOf(s) >= 0 && out.indexOf(s) < 0) out.push(s); });
    removable.forEach(function (s) { if (out.indexOf(s) < 0) out.push(s); });
    return heroes.concat(out);
  }

  // Persist the current removable order from the DOM.
  function saveOrder() {
    var order = Array.prototype.map.call(secWrap.querySelectorAll('[data-slug][draggable="true"]'),
      function (n) { return n.getAttribute('data-slug'); });
    CFG.order[current] = order;
    post(CFG.endpoints.reorder, { page: current, order: order }).then(function (res) {
      if (!res.ok) { toast(res.body.error || 'Could not reorder', 'error'); return; }
      bumpPending(1);
      reloadFrame();
      toast('Order saved');
    });
  }

  function renderSections() {
    if (!secWrap) return;
    var hidden = CFG.hidden[current] || [];
    secWrap.innerHTML = '';
    orderedSlugs(current).forEach(function (slug) {
      var meta = CFG.sectionMeta[slug] || { label: slug, removable: true };
      var isHidden = hidden.indexOf(slug) >= 0;
      var fixed = meta.removable === false;
      var row = document.createElement('div');
      row.setAttribute('data-slug', slug);
      row.className = 'flex items-center gap-2 px-2.5 py-2 rounded-xl border border-paper-200 bg-paper-0 '
        + (fixed ? '' : 'cursor-grab');

      if (!fixed) {
        row.setAttribute('draggable', 'true');
        var grip = document.createElement('span');
        grip.className = 'shrink-0 text-ink-400';
        grip.innerHTML = '<svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="currentColor"><circle cx="6" cy="4" r="1"/><circle cx="10" cy="4" r="1"/><circle cx="6" cy="8" r="1"/><circle cx="10" cy="8" r="1"/><circle cx="6" cy="12" r="1"/><circle cx="10" cy="12" r="1"/></svg>';
        row.appendChild(grip);
        row.addEventListener('dragstart', function (e) { dragSlug = slug; row.classList.add('opacity-40'); e.dataTransfer.effectAllowed = 'move'; });
        row.addEventListener('dragend', function () { row.classList.remove('opacity-40'); });
        row.addEventListener('dragover', function (e) { e.preventDefault(); });
        row.addEventListener('drop', function (e) {
          e.preventDefault();
          if (!dragSlug || dragSlug === slug) return;
          var dragEl = secWrap.querySelector('[data-slug="' + dragSlug + '"]');
          if (dragEl && dragEl !== row) { secWrap.insertBefore(dragEl, row); saveOrder(); }
        });
      }

      var left = document.createElement('button');
      left.type = 'button';
      left.className = 'flex-1 text-left text-[12.5px] font-medium text-ink-800 truncate hover:text-wa-deep';
      left.textContent = meta.label;
      left.title = 'Scroll to section';
      left.addEventListener('click', function () {
        iframe.contentWindow.postMessage({ type: 'fc:highlightSection', slug: slug }, ORIGIN);
      });
      row.appendChild(left);

      if (fixed) {
        var lock = document.createElement('span');
        lock.className = 'shrink-0 text-[10px] font-mono uppercase tracking-wide text-ink-400';
        lock.textContent = 'fixed';
        row.appendChild(lock);
      } else {
        var btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'shrink-0 inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-semibold transition '
          + (isHidden ? 'bg-paper-100 text-ink-500' : 'bg-wa-green/15 text-wa-deep');
        btn.textContent = isHidden ? 'Hidden' : 'Shown';
        btn.addEventListener('click', function () {
          var nextHidden = !isHidden;
          post(CFG.endpoints.section, { page: current, slug: slug, hidden: nextHidden ? 1 : 0 }).then(function (res) {
            if (!res.ok) { toast(res.body.error || 'Could not update', 'error'); return; }
            CFG.hidden[current] = res.body.hidden || [];
            renderSections();
            reloadFrame();
            bumpPending(1);
            toast(meta.label + (nextHidden ? ' hidden' : ' shown'));
          });
        });
        row.appendChild(btn);
      }
      secWrap.appendChild(row);
    });
  }

  /* ───────────────────────── theme ─────────────────────────── */

  var themeTimers = {};
  document.querySelectorAll('[data-fc-color]').forEach(function (input) {
    var key = input.getAttribute('data-fc-color');
    input.addEventListener('input', function () {
      var hex = input.value;
      var sw = input.parentElement.querySelector('[data-fc-color-text]');
      if (sw) sw.textContent = hex;
      clearTimeout(themeTimers[key]);
      themeTimers[key] = setTimeout(function () {
        post(CFG.endpoints.draft, { key: key, value: hex, type: 'color' }).then(function (res) {
          if (!res.ok) { toast('Invalid colour', 'error'); return; }
          bumpPending(1);
          reloadFrame();
        });
      }, 350);
    });
  });

  /* ──────────────────────── inspector ──────────────────────── */

  var inspEmpty = document.getElementById('fc-insp-empty');
  var inspCard = document.getElementById('fc-insp-card');
  var inspKey = document.getElementById('fc-insp-key');
  var inspType = document.getElementById('fc-insp-type');
  var inspReset = document.getElementById('fc-insp-reset');
  var inspLink = document.getElementById('fc-insp-link');
  var inspUrl = document.getElementById('fc-insp-url');
  var inspUrlSave = document.getElementById('fc-insp-url-save');
  var selectedKey = null;
  var selectedUrlKey = null;

  function showInspector(d) {
    selectedKey = d.key;
    selectedUrlKey = d.urlKey || null;
    if (inspEmpty) inspEmpty.style.display = 'none';
    if (inspCard) inspCard.style.display = '';
    if (inspKey) inspKey.textContent = d.key;
    if (inspType) inspType.textContent = d.fieldType || 'text';
    if (inspLink) {
      inspLink.style.display = selectedUrlKey ? '' : 'none';
      if (selectedUrlKey && inspUrl) inspUrl.value = d.href || '';
    }
  }

  if (inspUrlSave) {
    inspUrlSave.addEventListener('click', function () {
      if (!selectedUrlKey) return;
      var url = (inspUrl.value || '').trim();
      if (!url) { toast('Enter a URL', 'error'); return; }
      post(CFG.endpoints.draft, { key: selectedUrlKey, value: url, type: 'text' }).then(function (res) {
        if (!res.ok) { toast('Could not save link', 'error'); return; }
        toast('Link updated');
        bumpPending(1);
        reloadFrame();
      });
    });
  }

  if (inspReset) {
    inspReset.addEventListener('click', function () {
      if (!selectedKey) return;
      post(CFG.endpoints.reset, { key: selectedKey }).then(function (res) {
        if (!res.ok) { toast('Could not reset', 'error'); return; }
        toast('Reset to default');
        reloadFrame();
      });
    });
  }

  /* ─────────────── publish / reset-all controls ────────────── */

  var publishBtn = document.getElementById('fc-publish');
  if (publishBtn) {
    publishBtn.addEventListener('click', function () {
      publishBtn.disabled = true;
      post(CFG.endpoints.publish, { scope: 'all' }).then(function (res) {
        publishBtn.disabled = false;
        if (!res.ok) { toast(res.body.error || 'Publish failed', 'error'); return; }
        toast('Published — ' + (res.body.published || 0) + ' change(s) live');
        bumpPending(-9999);
        reloadFrame();
      });
    });
  }

  var discardBtn = document.querySelector('[data-fc-discard]');
  if (discardBtn) {
    discardBtn.addEventListener('click', function () {
      if (!window.confirm('Discard all unpublished drafts? Anything already published stays live; unsaved edits are thrown away.')) return;
      discardBtn.disabled = true;
      post(CFG.endpoints.discard, { scope: 'all' }).then(function (res) {
        discardBtn.disabled = false;
        if (!res.ok) { toast(res.body.error || 'Discard failed', 'error'); return; }
        toast('Drafts discarded — ' + (res.body.reverted || 0) + ' reverted');
        bumpPending(-9999);
        // Drafts (incl. section show/hide) revert to the published set, which
        // this page's in-memory state doesn't know — full reload resyncs it.
        setTimeout(function () { window.location.reload(); }, 700);
      });
    });
  }

  // Frontend on/off — flips the public homepage between live and redirect-to-login.
  var feToggle = document.querySelector('[data-fc-frontend-toggle]');
  if (feToggle) {
    var feInput = feToggle.querySelector('input[type=checkbox]');
    var feUrl = feToggle.getAttribute('data-url');
    if (feInput && feUrl) {
      feInput.addEventListener('change', function () {
        var on = feInput.checked;
        post(feUrl, { enabled: on ? 1 : 0 }).then(function (res) {
          if (!res.ok) {
            feInput.checked = !on; // revert the switch on failure
            toast((res.body && res.body.error) || 'Update failed', 'error');
            return;
          }
          toast(on ? 'Homepage enabled — public site is live' : 'Homepage disabled — visitors go to login');
        }).catch(function () { feInput.checked = !on; });
      });
    }
  }

  document.querySelectorAll('[data-fc-reset-scope]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var scope = btn.getAttribute('data-fc-reset-scope');
      if (!window.confirm('Reset ' + (scope === 'all' ? 'the WHOLE site' : scope) + ' to shipped defaults? This clears edits and drafts.')) return;
      post(CFG.endpoints.reset, { scope: scope }).then(function (res) {
        if (!res.ok) { toast(res.body.error || 'Reset failed', 'error'); return; }
        toast('Reset done');
        if (scope === 'all' || scope === current) {
          // hidden + order sets for affected pages are gone now
          if (scope === 'all') {
            Object.keys(CFG.hidden).forEach(function (p) { CFG.hidden[p] = []; });
            Object.keys(CFG.order).forEach(function (p) { CFG.order[p] = []; });
          } else {
            CFG.hidden[scope] = [];
            CFG.order[scope] = [];
          }
          renderSections();
        }
        reloadFrame();
      });
    });
  });

  /* ─────────────── messages from the iframe bridge ─────────── */

  window.addEventListener('message', function (e) {
    if (e.origin !== ORIGIN || !e.data || typeof e.data !== 'object') return;
    var d = e.data;
    if (d.type === 'fc:select') showInspector(d);
    else if (d.type === 'fc:saving') setStatus('saving');
    else if (d.type === 'fc:saved') { setStatus('saved'); toast('Saved'); bumpPending(1); }
    else if (d.type === 'fc:error') { setStatus('error'); toast('Save failed', 'error'); }
  });

  /* ───────────────────────── init ──────────────────────────── */

  renderSections();
})();
