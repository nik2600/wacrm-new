/* WaDesk admin Updater — drives verify → backup → upload → apply → migrate →
 * finalize, plus rollback. Vanilla JS; config + routes come from
 * window.WD_UPDATER (set inline in the blade). No build step needed. */
(function () {
  var CFG = window.WD_UPDATER || {};
  var root = document.getElementById('wd-updater');
  if (!root || !CFG.urls) return;

  var state = { verified: !!CFG.verified, done: 0, busy: false };

  function badge(n)  { return root.querySelector('[data-badge="' + n + '"]'); }
  function msgEl(n)  { return root.querySelector('[data-msg="' + n + '"]'); }
  function btn(n)    { return root.querySelector('[data-step-btn="' + n + '"]'); }
  function label(n)  { return root.querySelector('[data-btn-label="' + n + '"]'); }

  function banner(text, ok) {
    var b = document.getElementById('wd-up-banner');
    if (!b) return;
    b.textContent = text;
    b.className = 'rounded-xl px-4 py-3 text-[13px] font-medium ' +
      (ok ? 'bg-green-50 text-green-700 border border-green-200'
          : 'bg-red-50 text-red-700 border border-red-200');
  }

  function setMsg(n, text, ok) {
    var m = msgEl(n); if (!m) return;
    if (!text) { m.className = 'mt-2 text-[12px] hidden'; m.textContent = ''; return; }
    m.textContent = text;
    m.className = 'mt-2 text-[12px] ' + (ok ? 'text-green-600' : 'text-red-600');
  }

  function markDone(n) {
    var bd = badge(n); if (!bd) return;
    bd.classList.remove('bg-paper-100', 'bg-paper-200', 'text-ink-500', 'text-ink-700');
    bd.classList.add('bg-wa-deep', 'text-paper-0');
    bd.innerHTML = '<svg viewBox="0 0 16 16" class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2"><path d="m3 8 3.5 3.5L13 5"/></svg>';
  }

  function refreshGate() {
    for (var n = 1; n <= 5; n++) {
      var b = btn(n); if (!b) continue;
      var ready = state.verified && state.done >= (n - 1) && !state.busy;
      b.classList.toggle('opacity-40', !(state.verified && state.done >= (n - 1)));
      b.classList.toggle('pointer-events-none', !ready);
    }
  }

  function post(url, body, isForm) {
    var opts = { method: 'POST', headers: { 'X-CSRF-TOKEN': CFG.csrf, 'Accept': 'application/json' } };
    if (isForm) { opts.body = body; }
    else { opts.headers['Content-Type'] = 'application/json'; opts.body = JSON.stringify(body || {}); }
    return fetch(url, opts).then(function (r) {
      // Read as text first: an oversized upload rejected by nginx (HTTP 413)
      // or by PHP post_max_size returns an HTML error page, NOT JSON — so
      // keep the status + raw body so the UI can show the REAL reason instead
      // of a bare "Upload failed."
      return r.text().then(function (t) {
        var j = {};
        try { j = t ? JSON.parse(t) : {}; } catch (e) { j = {}; }
        return { ok: r.ok, status: r.status, body: j, raw: t };
      }).catch(function () { return { ok: r.ok, status: r.status, body: {}, raw: '' }; });
    });
  }

  // Turn an upload failure into a specific, actionable message. Covers the
  // common server-limit cases the generic handler used to swallow.
  function uploadErrorMessage(res) {
    var body = res.body || {};
    var s = res.status || 0;
    if (body.message) {
      if (/file field is required|failed to upload|larger than|upload_max|post_max|exceeds/i.test(body.message)) {
        return body.message + ' — the ZIP exceeds the server upload limit. Raise PHP upload_max_filesize + post_max_size (and nginx client_max_body_size) to 64M, then restart php-fpm + reload nginx.';
      }
      return body.message;
    }
    if (s === 413) return 'File too large (HTTP 413). The web server rejected the ZIP before it reached the app. Raise nginx client_max_body_size (and PHP upload_max_filesize / post_max_size) to at least 64M, then reload nginx + restart php-fpm.';
    if (s === 419) return 'Session expired (HTTP 419). Refresh this page and try again.';
    if (s === 500) return 'Server error (HTTP 500) while saving the upload — check storage/logs/laravel.log.';
    if (s === 0)   return 'Upload was cut off — the server closed the connection, almost always the upload size limit. Raise nginx client_max_body_size + PHP upload_max_filesize / post_max_size, then reload.';
    var txt = res.raw ? String(res.raw).replace(/<[^>]+>/g, ' ').replace(/\s+/g, ' ').trim().slice(0, 180) : '';
    return 'Upload failed (HTTP ' + s + ').' + (txt ? ' Server said: ' + txt : '');
  }

  // ---- Step 0: verify purchase ----
  var verifyBtn = document.getElementById('wd-up-verify');
  if (verifyBtn) {
    verifyBtn.addEventListener('click', function () {
      var input = document.getElementById('wd-up-code');
      var code = input ? input.value : '';
      if (!code || code.indexOf('•') === 0) { setMsg(0, 'Enter your purchase code.', false); return; }
      verifyBtn.disabled = true; setMsg(0, 'Verifying with Envato…', true);
      post(CFG.urls.verify, { purchase_code: code }).then(function (res) {
        verifyBtn.disabled = false;
        var b = res.body || {};
        if (res.ok && b.success) {
          state.verified = true; markDone(0);
          setMsg(0, b.message || 'Verified.', true);
          var sec = document.getElementById('wd-up-steps'); if (sec) sec.removeAttribute('data-locked');
          refreshGate();
        } else { setMsg(0, b.message || 'Verification failed.', false); }
      }).catch(function () { verifyBtn.disabled = false; setMsg(0, 'Network error — try again.', false); });
    });
  }

  // ---- POST-only steps (1 backup, 3 apply, 4 migrate, 5 finalize) ----
  function runStep(n) {
    if (state.busy) return;
    var b = btn(n); if (b && b.classList.contains('pointer-events-none')) return;
    state.busy = true; refreshGate();
    var lbl = label(n); var prev = lbl ? lbl.textContent : '';
    if (lbl) lbl.textContent = '…';
    setMsg(n, '', true);
    var map = { 1: CFG.urls.backup, 3: CFG.urls.apply, 4: CFG.urls.migrate, 5: CFG.urls.finalize };
    post(map[n], {}).then(function (res) {
      state.busy = false;
      var body = res.body || {};
      if (res.ok && body.success) {
        markDone(n); state.done = Math.max(state.done, n);
        setMsg(n, body.message || 'Done.', true);
        if (lbl) lbl.textContent = 'Done';
        if (n === 5) { if (body.health) renderHealth(body.health); banner(body.message || 'Update complete.', true); }
      } else {
        setMsg(n, body.message || 'Step failed.', false);
        if (lbl) lbl.textContent = prev;
      }
      refreshGate();
    }).catch(function () {
      state.busy = false; if (lbl) lbl.textContent = prev;
      setMsg(n, 'Network error — try again.', false); refreshGate();
    });
  }
  root.querySelectorAll('[data-step-run]').forEach(function (el) {
    el.addEventListener('click', function () { runStep(parseInt(el.getAttribute('data-step-run'), 10)); });
  });

  // ---- Step 2: upload ZIP ----
  var fileInput = document.getElementById('wd-up-file');
  if (fileInput) {
    fileInput.addEventListener('change', function () {
      var b2 = btn(2); if (b2 && b2.classList.contains('pointer-events-none')) { fileInput.value = ''; return; }
      var f = fileInput.files[0]; if (!f) return;
      state.busy = true; refreshGate();
      var lbl = label(2); if (lbl) lbl.textContent = 'Uploading…';
      setMsg(2, '', true);
      var fd = new FormData(); fd.append('file', f);
      post(CFG.urls.upload, fd, true).then(function (res) {
        state.busy = false; var body = res.body || {};
        if (res.ok && body.success) {
          markDone(2); state.done = Math.max(state.done, 2);
          setMsg(2, body.message || 'Uploaded.', true); if (lbl) lbl.textContent = 'Uploaded';
        } else { setMsg(2, uploadErrorMessage(res), false); if (lbl) lbl.textContent = 'Choose ZIP'; }
        fileInput.value = ''; refreshGate();
      }).catch(function () {
        state.busy = false; if (lbl) lbl.textContent = 'Choose ZIP';
        setMsg(2, 'Upload was cut off — the server closed the connection, almost always the upload size limit (nginx client_max_body_size / PHP post_max_size). Raise those and retry.', false); fileInput.value = ''; refreshGate();
      });
    });
  }

  function renderHealth(health) {
    var box = root.querySelector('[data-health]'); if (!box) return;
    box.innerHTML = '';
    Object.keys(health).forEach(function (k) {
      var ok = !!health[k];
      var d = document.createElement('div');
      d.className = 'flex items-center gap-1.5 ' + (ok ? 'text-green-600' : 'text-red-600');
      d.textContent = (ok ? 'OK · ' : 'FAIL · ') + k.replace(/_/g, ' ');
      box.appendChild(d);
    });
    box.classList.remove('hidden');
  }

  // ---- Rollback (two-click confirm, no native dialog) ----
  root.querySelectorAll('[data-rollback]').forEach(function (el) {
    var armed = false, timer = null;
    el.addEventListener('click', function () {
      if (!armed) {
        armed = true; el.textContent = 'Confirm rollback?';
        el.classList.add('bg-red-600', 'text-white');
        timer = setTimeout(function () {
          armed = false; el.textContent = 'Rollback'; el.classList.remove('bg-red-600', 'text-white');
        }, 4000);
        return;
      }
      clearTimeout(timer); el.disabled = true; el.textContent = '…';
      post(CFG.urls.rollback, { backup_dir: el.getAttribute('data-rollback') }).then(function (res) {
        var body = res.body || {};
        banner(body.message || (body.success ? 'Rolled back.' : 'Rollback failed.'), !!body.success);
        if (res.ok && body.success) { setTimeout(function () { location.reload(); }, 1300); }
        else { el.disabled = false; el.textContent = 'Rollback'; el.classList.remove('bg-red-600', 'text-white'); }
      }).catch(function () {
        el.disabled = false; el.textContent = 'Rollback'; el.classList.remove('bg-red-600', 'text-white');
        banner('Network error — try again.', false);
      });
    });
  });

  if (state.verified) markDone(0);
  refreshGate();
})();
