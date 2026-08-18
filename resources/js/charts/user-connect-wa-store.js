import intlTelInput from 'intl-tel-input/intlTelInputWithUtils';
import 'intl-tel-input/styles';
import QRCode from 'qrcode';
import { themeColor } from '../theme-colors.js';

/**
 * /connect?platform=wa-store
 *
 * The page now hosts the storefront onboarding wizard (shop name,
 * slug, custom domain, sending device). The legacy WABA / Baileys
 * picker handlers below are still imported but they no-op when the
 * relevant DOM elements aren't present — kept so if we ever bring
 * back the provider-picker view it still works.
 */
export default function init() {
    wireWizard();
    wireResetModal(document.querySelector('[data-wa-store-wizard]'));
    wireShareModal();
    wireWaba();
    wireBaileys();
}

// ─────────────────────────────────────────────────────────────────
// Share-shop modal — works on the shop list (per-card button) and
// inside the wizard's "this shop is live" panel.
//   • WhatsApp     → https://wa.me/?text=… (official deep link)
//   • Email        → mailto:?subject=…&body=…
//   • Telegram     → https://t.me/share/url
//   • Native share → navigator.share() (mobile)
//   • Copy link    → clipboard
//   • QR code      → rendered locally via the `qrcode` npm package
// ─────────────────────────────────────────────────────────────────
function wireShareModal() {
    const modal = document.getElementById('share-modal');
    if (!modal) return;

    const nameEl  = modal.querySelector('[data-share-name]');
    const urlIn   = modal.querySelector('[data-share-url-input]');
    const wa      = modal.querySelector('[data-share-wa]');
    const email   = modal.querySelector('[data-share-email]');
    const tg      = modal.querySelector('[data-share-tg]');
    const native  = modal.querySelector('[data-share-native]');
    const copyBtn = modal.querySelector('[data-share-copy]');
    const qrImg   = modal.querySelector('[data-share-qr]');
    const closeBtns = modal.querySelectorAll('[data-close-share]');
    const backdrop  = modal.querySelector('[data-share-backdrop]');

    function close() { modal.classList.add('hidden'); }
    function open(url, name) {
        const fullUrl = url || window.location.origin;
        const title   = name || 'My shop';
        const shareText = `Check out my shop: ${title}\n${fullUrl}`;

        nameEl.textContent = title;
        urlIn.value = fullUrl;
        wa.href     = `https://wa.me/?text=${encodeURIComponent(shareText)}`;
        email.href  = `mailto:?subject=${encodeURIComponent('Check out my shop · ' + title)}&body=${encodeURIComponent(shareText)}`;
        tg.href     = `https://t.me/share/url?url=${encodeURIComponent(fullUrl)}&text=${encodeURIComponent(title)}`;
        // Local QR (the shop's share-link). No external service.
        QRCode.toDataURL(fullUrl, { width: 240, margin: 1, errorCorrectionLevel: 'M' })
            .then((src) => { qrImg.src = src; })
            .catch(() => { /* leave previous src */ });

        // Native share is only useful on devices that support it.
        if (!navigator.share) {
            native.style.opacity = '.5';
            native.title = 'Not supported on this browser';
        }
        native.onclick = async () => {
            if (navigator.share) {
                try { await navigator.share({ title, text: shareText, url: fullUrl }); } catch (_) {}
            } else {
                // Fallback — copy and toast.
                navigator.clipboard.writeText(fullUrl);
                native.textContent = 'Copied';
                setTimeout(() => { native.innerHTML = native.dataset.originalHtml; }, 1500);
            }
        };
        native.dataset.originalHtml ??= native.innerHTML;

        copyBtn.onclick = () => {
            navigator.clipboard.writeText(fullUrl);
            const t = copyBtn.textContent;
            copyBtn.textContent = 'Copied ✓';
            setTimeout(() => copyBtn.textContent = t, 1500);
        };

        modal.classList.remove('hidden');
    }

    // All buttons that open this modal carry data-share-url + data-share-name.
    document.querySelectorAll('[data-share-shop]').forEach((btn) => {
        btn.addEventListener('click', () => open(btn.dataset.shareUrl, btn.dataset.shareName));
    });

    closeBtns.forEach((b) => b.addEventListener('click', close));
    backdrop?.addEventListener('click', close);
    document.addEventListener('keydown', (e) => { if (e.key === 'Escape') close(); });
}

// ─────────────────────────────────────────────────────────────────
// Storefront wizard — slug autosync + live URL preview that uses
// the browser's current origin (so the preview is correct whether
// the user accesses via localhost, an IP, or a real domain).
// ─────────────────────────────────────────────────────────────────
function wireWizard() {
    const root = document.querySelector('[data-wa-store-wizard]');
    if (!root) return;

    const shopInput     = root.querySelector('[data-shop-name]');
    const slugReadout   = root.querySelector('[data-slug-readonly]');
    const preview       = root.querySelector('[data-preview-url]');
    const previewHost   = root.querySelector('[data-preview-host]');
    const subdomainHost = root.dataset.subdomainHost || '';
    const subdomainUsable = root.dataset.subdomainUsable === '1';
    // Server-provided root — includes any sub-path the app is mounted under
    // (e.g. a cPanel deploy served from https://b2sender.com/public). Falls
    // back to the bare origin only when the attribute is absent.
    const baseUrl = (root.dataset.baseUrl || window.location.origin).replace(/\/+$/, '');

    // The slug is server-generated from the shop name; once a
    // storefront row exists the slug is frozen so shared links
    // keep working. The readout div captures both states — its
    // initial text content is the source of truth.
    const slugLocked = (slugReadout?.textContent || '').trim().length > 0
        && root.dataset.slugLocked === '1';

    if (!slugReadout || !preview) return;

    function slugify(v) {
        return (v || '')
            .toLowerCase()
            .normalize('NFKD')
            .replace(/[^a-z0-9\s-]/g, '')
            .trim()
            .replace(/\s+/g, '-')
            .replace(/-+/g, '-')
            .slice(0, 64);
    }

    function currentSlug() {
        if (slugLocked) {
            return slugReadout.textContent.trim();
        }
        return slugify(shopInput?.value || '') || 'your-shop';
    }

    function renderPreview() {
        const s = currentSlug();
        if (!slugLocked) slugReadout.textContent = s;
        preview.textContent = subdomainUsable
            ? 'https://' + s + '.' + subdomainHost
            : baseUrl + '/s/' + s;
    }

    // Show the dynamic origin in the slug field's prefix box too.
    if (previewHost && !subdomainUsable) {
        previewHost.textContent = baseUrl + '/s/';
    }

    if (shopInput && !slugLocked) {
        shopInput.addEventListener('input', renderPreview);
    }

    renderPreview();
}

// Reset / delete-shop modal — works both inside the edit-mode form
// (delete current shop) and on the shop list (delete a specific row).
function wireResetModal(root) {
    const resetModal = document.getElementById('reset-modal');
    if (!resetModal) return;

    const open = (shopId, shopName) => {
        const idInput = resetModal.querySelector('[data-reset-shop-id]');
        const nameEl  = resetModal.querySelector('[data-reset-shop-name]');
        if (idInput) idInput.value = shopId || '';
        if (nameEl) nameEl.textContent = shopName || 'this shop';
        resetModal.classList.remove('hidden');
    };
    const close = () => resetModal.classList.add('hidden');

    // Edit-mode "Delete this shop" button (inside the form)
    if (root) {
        root.querySelectorAll('[data-open-reset]').forEach((b) =>
            b.addEventListener('click', () => open(
                resetModal.querySelector('[data-reset-shop-id]')?.value,
                document.querySelector('[data-wa-store-wizard] [name="shop_name"]')?.value,
            )),
        );
    }
    // List-mode per-row trash icon
    document.querySelectorAll('[data-delete-shop]').forEach((b) =>
        b.addEventListener('click', () => open(b.dataset.deleteShop, b.dataset.shopName)),
    );

    resetModal.querySelectorAll('[data-close-reset]').forEach((b) =>
        b.addEventListener('click', close),
    );
    resetModal.querySelector('[data-reset-backdrop]')?.addEventListener('click', close);
}

// ─────────────────────────────────────────────────────────────────
// Legacy WABA handler — no-ops if the page doesn't have the WABA
// signup button. Kept so the import doesn't break if/when the
// provider picker is reintroduced.
// ─────────────────────────────────────────────────────────────────
function wireWaba() {
    const btn = document.getElementById('waba-signup-btn');
    if (!btn) return;
    const appId = btn.dataset.appId;
    const configId = btn.dataset.configId;
    // Graph/SDK version comes from the admin "Graph API version" setting
    // (data-graph-version) so the FB dialog matches the server-side REST calls
    // instead of being pinned to an old hardcoded version.
    const graphVersion = btn.dataset.graphVersion || 'v23.0';
    if (!appId || !configId) return;

    if (!window.FB) {
        const s = document.createElement('script');
        s.async = true; s.defer = true; s.crossOrigin = 'anonymous';
        s.src = 'https://connect.facebook.net/en_US/sdk.js';
        s.onload = () => initFB(appId, graphVersion);
        document.head.appendChild(s);
    } else initFB(appId, graphVersion);

    btn.addEventListener('click', async () => {
        if (!window.FB) return alert('Meta SDK still loading.');
        showStatus('Opening Meta dialog…');

        const listener = (event) => {
            if (!['https://www.facebook.com', 'https://web.facebook.com'].includes(event.origin)) return;
            try {
                const data = JSON.parse(event.data);
                if (data.type === 'WA_EMBEDDED_SIGNUP' && data.event === 'FINISH' && data.data) {
                    window.__waba_finish = data.data;
                }
            } catch (_) {}
        };
        window.addEventListener('message', listener);

        window.FB.login(async (res) => {
            window.removeEventListener('message', listener);
            if (!res.authResponse?.code) return showStatus('Cancelled.');
            const f = window.__waba_finish || {};
            const payload = {
                code: res.authResponse.code,
                phone_number_id: f.phone_number_id || prompt('Phone number ID:'),
                waba_id:         f.waba_id         || prompt('WABA ID:'),
                business_id:     f.business_id     || null,
            };
            showStatus('Provisioning on Meta…');
            try {
                const r = await fetch('/connect/wa-store/waba', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf(), 'Accept': 'application/json' },
                    body: JSON.stringify(payload),
                });
                const j = await r.json();
                if (j.ok && j.redirect) {
                    showStatus('Connected ✓ Redirecting…', 'ok');
                    setTimeout(() => location.href = j.redirect, 800);
                } else {
                    showStatus('Failed: ' + (j.message || JSON.stringify(j.errors || j)), 'err');
                }
            } catch (e) { showStatus('Network error: ' + e.message, 'err'); }
        }, {
            config_id: configId, response_type: 'code', override_default_response_type: true,
            extras: { feature: 'whatsapp_embedded_signup', sessionInfoVersion: 3 },
        });
    });
}

function initFB(appId, version = 'v23.0') {
    if (!window.FB) return;
    window.FB.init({ appId, version, xfbml: false, cookie: false });
}

function showStatus(msg, kind = 'pending') {
    const el = document.getElementById('waba-status');
    if (!el) return;
    el.classList.remove('hidden');
    const cls = kind === 'ok'  ? 'border-wa-green/40 bg-wa-mint text-wa-deep'
              : kind === 'err' ? 'border-accent-coral/40 bg-accent-coral/10 text-[#A1431F]'
              : 'border-paper-200 bg-paper-50 text-ink-700';
    el.className = `mt-4 rounded-lg border px-3 py-2 text-[12.5px] font-mono ${cls}`;
    el.textContent = msg;
}

// ─────────────────────────────────────────────────────────────────
// Legacy Baileys handler — also no-ops if the relevant form is
// not on the page.
// ─────────────────────────────────────────────────────────────────
function wireBaileys() {
    const form = document.getElementById('baileys-form');
    const btn = document.getElementById('baileys-generate');
    const phone = document.getElementById('baileys-phone');
    const cc = document.getElementById('baileys-cc');
    if (!form || !btn || !phone) return;

    // Platform default country from layout meta (admin sets in /admin/settings/general).
    const _defIso = (document.querySelector('meta[name="default-country-iso"]')?.content || 'in').toLowerCase();
    const _preferred = Array.from(new Set([_defIso, 'in', 'us', 'gb', 'ae', 'sg']));
    const iti = intlTelInput(phone, {
        initialCountry:    _defIso,
        preferredCountries: _preferred,
        separateDialCode:  true,
        nationalMode:      false,
        dropdownContainer: document.body,
    });
    const sync = () => { if (cc) cc.value = '+' + iti.getSelectedCountryData().dialCode; };
    sync();
    phone.addEventListener('countrychange', sync);

    let qrPollTimer = null;

    btn.addEventListener('click', async () => {
        if (!phone.value.replace(/\D+/g, '')) {
            return alert('Enter your WhatsApp phone number first.');
        }
        btn.disabled = true;
        btn.textContent = 'Connecting to Node…';

        try {
            const fd = new FormData(form);
            const r = await fetch('/connect/wa-store/baileys', {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': csrf(), 'Accept': 'application/json' },
                body: fd,
            });
            const j = await r.json();
            if (!j.ok) {
                setQrSlot(`<div style="color:${themeColor('accent-coral')};font-family:monospace">${j.message || 'Save failed'}</div>`);
                btn.textContent = 'Try again';
                btn.disabled = false;
                return;
            }

            btn.textContent = 'Polling for QR…';
            setQrSlot(`<div style="font-family:monospace;color:${themeColor('ink-500')}">Waiting for Node to generate QR…</div>`);
            startQrPoll(j.qr_poll_url);
        } catch (e) {
            setQrSlot(`<div style="color:${themeColor('accent-coral')}">${e.message}</div>`);
            btn.textContent = 'Try again';
            btn.disabled = false;
        }
    });

    function startQrPoll(url) {
        clearInterval(qrPollTimer);
        let attempts = 0;
        qrPollTimer = setInterval(async () => {
            attempts++;
            try {
                const r = await fetch(url, { headers: { Accept: 'application/json' } });
                const j = await r.json();
                if (j.paired) {
                    clearInterval(qrPollTimer);
                    showStatusBanner('✓ Paired! Phone connected as ' + (j.phone || ''), 'ok');
                    setQrSlot(`<div style="text-align:center"><div style="font-size:48px">✓</div><div style="margin-top:12px;color:${themeColor('wa-deep')};font-weight:600">Connected</div></div>`);
                    setTimeout(() => location.href = window.appUrl('/store'), 1200);
                } else if (j.qr_data) {
                    renderQr(j.qr_data);
                    showStatusBanner('Scan the QR with your WhatsApp app.', 'pending');
                } else if (attempts > 60) {
                    clearInterval(qrPollTimer);
                    showStatusBanner('Timed out — Node didn\'t generate a QR. Check that the Node bridge is running.', 'err');
                }
            } catch (e) { /* keep polling */ }
        }, 2000);
    }

    async function renderQr(data) {
        // Local QR render — no external service.
        let src = data;
        if (!String(data).startsWith('data:image')) {
            try {
                src = await QRCode.toDataURL(String(data), { width: 320, margin: 2, errorCorrectionLevel: 'M' });
            } catch (e) { return; }
        }
        setQrSlot(`<img src="${src}" alt="QR" style="width:100%;height:100%;object-fit:contain"/>`);
    }

    function setQrSlot(html) {
        const slot = document.getElementById('baileys-qr');
        if (slot) slot.innerHTML = html;
    }

    function showStatusBanner(msg, kind = 'pending') {
        const el = document.getElementById('baileys-status');
        if (!el) return;
        el.classList.remove('hidden');
        const cls = kind === 'ok'  ? 'border-wa-green/40 bg-wa-mint text-wa-deep'
                  : kind === 'err' ? 'border-accent-coral/40 bg-accent-coral/10 text-[#A1431F]'
                  : 'border-paper-200 bg-paper-50 text-ink-700';
        el.className = `bg-paper-50 border border-paper-200 rounded-2xl p-3 text-[12px] font-mono ${cls.replace('border-paper-200', '').trim()}`;
        el.textContent = msg;
    }
}

function csrf() { return document.querySelector('meta[name="csrf-token"]')?.content || ''; }
