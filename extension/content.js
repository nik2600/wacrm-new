// Content Script for WaDesk Extension - Production Ready
// ===================== CONFIG =====================
// Set your server URL here (from your Laravel .env APP_URL)
const WADESK_SERVER_URL = 'https://agendiz.cloud';
// ===================== END CONFIG =====================
console.log('[WaDesk] Content Script Loaded v1.0.0');

const sidebarHTML = `
    <div class="ws-header">
        <div style="display:flex; align-items:center; gap:12px;">
            <div class="ws-logo-box" id="ws-app-logo"><img id="ws-app-logo-img" alt="WaDesk"></div>
            <span id="ws-app-name" style="font-weight:700; font-size:16px; color:var(--ws-text);">WaDesk</span>
        </div>
        <div style="display:flex; align-items:center; gap:12px;">
             <span class="ws-credit-pill" id="ws-header-credits" title="Message credits" style="display:none;">
                <svg viewBox="0 0 16 16" width="12" height="12" fill="none" stroke="currentColor" stroke-width="1.7"><circle cx="8" cy="8" r="6"/><path d="M8 5v6M6 7l2-2 2 2"/></svg>
                <b id="ws-header-credit-num">0</b>
             </span>
             <div class="ws-user-avatar" id="ws-user-initials" title="Profile" style="cursor:pointer;">?</div>
             <span id="ws-close-btn" style="cursor:pointer; font-size:20px; color:var(--ws-ink-500);">&times;</span>
        </div>
    </div>
    <div class="ws-nav-tabs" id="ws-nav-tabs" style="display:none;">
        <div class="ws-nav-item active" data-tab="send">
            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
            Send
        </div>
        <div class="ws-nav-item" data-tab="recent">
            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><polyline points="12 7 12 12 15 14"/></svg>
            Recent
        </div>
        <div class="ws-nav-item" data-tab="templates">
            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><line x1="3" y1="9" x2="21" y2="9"/><line x1="9" y1="21" x2="9" y2="9"/></svg>
            Templates
        </div>
    </div>

    <!-- Auth View -->
    <div class="ws-auth-container" id="ws-auth-view" style="display:none;">
        <div class="ws-auth-card">
            <div class="ws-auth-eyebrow">Sign in</div>
            <h3 class="ws-auth-title">Welcome <span class="ws-accent">back</span>.</h3>
            <p class="ws-auth-sub">Enter your details to get back into your workspace.</p>
            <div class="form-group">
                <label class="form-label">Email</label>
                <input type="email" id="ws-email" class="form-control" placeholder="you@company.com">
            </div>
            <div class="form-group">
                <label class="form-label">Password</label>
                <input type="password" id="ws-password" class="form-control" placeholder="••••••••">
            </div>
            <button id="ws-login-btn" class="btn btn-primary" style="margin-top:4px;">Sign in</button>
            <p style="text-align:center; font-size:11px; color:var(--ws-ink-500); margin-top:14px;">Secure login via WaDesk</p>
        </div>
    </div>

    <!-- Profile View -->
    <div class="ws-auth-container" id="ws-profile-view" style="display:none;">
        <div class="ws-profile-view">
           <div class="ws-profile-avatar-lg" id="ws-profile-avatar-lg">ME</div>
           <h3 class="ws-profile-name" id="ws-profile-name">User Name</h3>
           <p class="ws-profile-meta" id="ws-profile-email">user@example.com</p>
           <div class="form-group" style="text-align:left; margin-bottom:20px;">
               <label class="form-label">Select Active Device</label>
               <select id="ws-profile-device-select" class="form-control">
                   <option value="">Loading devices...</option>
               </select>
           </div>
           <button id="ws-start-btn" class="btn btn-primary" style="width:100%;">Start Messaging</button>
           <button id="ws-logout-btn" class="btn" style="width:100%; margin-top:10px; background:white; border:1px solid var(--ws-coral); color:var(--ws-coral);">Logout</button>
        </div>
    </div>

    <!-- App View -->
    <div class="ws-body" id="ws-app-view" style="display:none;">

        <!-- Send Message Tab -->
        <div id="tab-send" class="ws-tab-content">

            <!-- Recipients -->
            <div class="ws-sec">
                <div class="ws-sec-label">
                    <span>Recipients</span>
                    <div class="ws-sec-actions">
                        <span id="ws-file-name" class="ws-sec-file"></span>
                        <div class="ws-icon-btn" id="ws-download-demo" title="Download demo CSV">
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#6B807C" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                        </div>
                        <div class="ws-icon-btn ws-excel-btn" title="Upload CSV/TXT">
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="#075E54"><path d="M14 2H6c-1.1 0-1.99.9-1.99 2L4 20c0 1.1.89 2 1.99 2H18c1.1 0 2-.9 2-2V8l-6-6zm2 16H8v-2h8v2zm0-4H8v-2h8v2zm-3-5V3.5L18.5 9H13z"/></svg>
                            <input type="file" id="ws-file-upload" accept=".csv,.xlsx,.xls,.txt" style="display:none;">
                        </div>
                    </div>
                </div>
                <div class="ws-input-group">
                    <select class="ws-country-select" id="ws-country-code"></select>
                    <div class="ws-tag-container" id="ws-numbers-container"></div>
                    <input type="text" id="ws-number-input" class="ws-number-input" placeholder="Add number…">
                </div>
                <div class="ws-meta-row">Numbers <b id="ws-contact-count">0</b><span class="ws-dot">·</span><span id="ws-filter-btn" class="ws-link">Filter</span></div>
            </div>

            <!-- Sending options -->
            <div class="ws-sec">
                <div class="ws-settings-row-inline">
                    <span>Time gap between messages</span>
                    <label class="ws-toggle-switch"><input type="checkbox" id="ws-random-delay-toggle"><span class="ws-slider"></span></label>
                </div>
                <div class="ws-settings-detail" id="ws-random-inputs" style="display:none;">
                    Random gap: <input type="number" id="ws-delay-min" class="ws-small-input" value="0" min="0"> to
                    <input type="number" id="ws-delay-max" class="ws-small-input" value="5" min="1"> sec
                </div>
                <div class="ws-settings-row-inline">
                    <span>Split into batches</span>
                    <label class="ws-toggle-switch"><input type="checkbox" id="ws-batching-toggle"><span class="ws-slider"></span></label>
                </div>
                <div class="ws-settings-detail" id="ws-batching-inputs" style="display:none;">
                    Batches of <input type="number" id="ws-batch-size" class="ws-small-input" value="10" min="1">,
                    wait <input type="number" id="ws-batch-wait" class="ws-small-input" value="1" min="1"> min each
                </div>
            </div>

            <!-- Message -->
            <div class="ws-sec">
                <div class="ws-sec-label"><span>Message</span></div>
                <div id="ws-customizations" class="ws-custom-row"></div>
                <div class="ws-textarea-wrap">
                    <textarea id="ws-message-input" class="ws-textarea" placeholder="Type your message here…"></textarea>
                </div>
                <div class="ws-footer-actions ws-footer-attach-only">
                    <div class="ws-attach-btn" id="ws-attach-trigger">
                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21.44 11.05l-9.19 9.19a6 6 0 0 1-8.49-8.49l9.19-9.19a4 4 0 0 1 5.66 5.66l-9.2 9.19a2 2 0 0 1-2.83-2.83l8.49-8.48"/></svg>
                        <span id="ws-attach-label">Attach a file (max 8MB)</span>
                        <input type="file" id="ws-attachment-upload" accept=".jpg,.jpeg,.png,.gif,.mp4,.pdf,.doc,.docx" style="display:none;">
                    </div>
                </div>
                <button id="ws-send-btn" class="btn btn-primary ws-send-btn">Send</button>
            </div>


            <!-- Progress -->
            <div id="ws-progress-area" style="margin-top:12px; display:none; background:var(--ws-paper-100); padding:12px; border-radius:8px;">
                 <div style="font-size:12px; margin-bottom:5px; display:flex; justify-content:space-between;">
                    <span>Sending... <b id="ws-progress-text">0/0</b></span>
                    <span id="ws-status-badge" style="color:var(--ws-amber); font-weight:600;">Running</span>
                 </div>
                 <div style="width:100%; background:var(--ws-paper-200); height:6px; border-radius:3px;">
                    <div id="ws-progress-bar" style="width:0%; background:var(--ws-primary); height:100%; border-radius:3px; transition:width 0.3s;"></div>
                 </div>
                 <div style="margin-top:4px; font-size:11px; color:var(--ws-ink-500);">
                    <span style="color:var(--ws-deep);" id="ws-sent-count">0 sent</span> &middot;
                    <span style="color:var(--ws-coral);" id="ws-failed-count">0 failed</span>
                 </div>
                 <div style="margin-top:8px; display:flex; gap:10px;">
                    <button class="btn" id="ws-pause-resume" style="flex:1; background:white; border:1px solid var(--ws-paper-200); font-size:12px; color:var(--ws-ink-900);">Pause</button>
                    <button class="btn" id="ws-stop" style="flex:1; background:white; border:1px solid var(--ws-coral); color:var(--ws-coral); font-size:12px;">Stop</button>
                 </div>
            </div>
        </div>

        <!-- Recent Tab -->
        <div id="tab-recent" class="ws-tab-content" style="display:none;">
            <div class="ws-recent-toolbar">
                <h4 style="margin:0;">Recent sends</h4>
                <button class="ws-recent-csv" id="ws-download-report" title="Export CSV">
                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                    Export CSV
                </button>
            </div>
            <div id="ws-reports-list" class="ws-recent-cards"><p class="ws-recent-empty">No sends yet.</p></div>
            <div id="ws-reports-pagination" class="ws-recent-pager"></div>
        </div>

        <!-- Templates Tab -->
        <div id="tab-templates" class="ws-tab-content" style="display:none;">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:10px;">
                <h4 style="margin:0;">Message Templates</h4>
                <span id="ws-templates-count" style="font-size:12px; color:var(--ws-ink-500);"></span>
            </div>
            <div class="ws-search-box">
                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#92A29E" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                <input type="text" id="ws-template-search" placeholder="Search templates..." class="ws-search-input">
            </div>
            <div id="ws-templates-list"><p style="color:var(--ws-ink-500); text-align:center;">Loading...</p></div>
        </div>
    </div>

    <!-- CSV Mapper Modal -->
    <div class="ws-modal-overlay" id="ws-mapper-modal" style="display:none;">
        <div class="ws-modal-content">
            <div class="ws-modal-header">
                <span>Import Contacts</span>
                <span style="cursor:pointer;" id="ws-close-mapper">&times;</span>
            </div>
            <div class="ws-modal-body">
                <p style="font-size:12px; color:var(--ws-ink-500); margin-bottom:10px;" id="ws-mapper-filename">File: contacts.csv</p>
                <label class="form-label">Sheet</label>
                <select id="ws-mapper-sheet" class="form-control" style="margin-bottom:10px;"><option>Sheet1</option></select>
                <label class="form-label">Column (Phone Number)</label>
                <select id="ws-mapper-column" class="form-control" style="margin-bottom:10px;"></select>
                <div style="display:flex; gap:10px;">
                    <div style="flex:1;"><label class="form-label">From Row</label><input type="number" id="ws-mapper-from" class="form-control" value="2"></div>
                    <div style="flex:1;"><label class="form-label">To Row</label><input type="number" id="ws-mapper-to" class="form-control" value="1000"></div>
                </div>
            </div>
            <div class="ws-modal-footer">
                <button class="btn" id="ws-mapper-cancel" style="border:1px solid var(--ws-paper-200); background:white; width:auto; color:var(--ws-ink-900);">Cancel</button>
                <button class="btn btn-primary" id="ws-mapper-import" style="width:auto;">Import</button>
            </div>
        </div>
    </div>

    <!-- Attribute Picker Modal -->
    <div class="ws-modal-overlay" id="ws-attr-modal" style="display:none;">
        <div class="ws-modal-content">
            <div class="ws-modal-header"><span>Insert Attribute</span><span style="cursor:pointer;" id="ws-close-attr-modal">&times;</span></div>
            <div class="ws-modal-body" id="ws-attr-modal-body"></div>
        </div>
    </div>

    <!-- Template Variable Mapping Modal.
         Same job as the web <x-template-live-mapping> panel on
         Campaigns / Broadcasts / Scheduled: map each {{n}} in the chosen
         template to a contact attribute (or a fixed value) for THIS send.
         Rendered from the template's send_meta, which /api/get-templates
         now returns — the identical structure the web panel consumes. -->
    <div class="ws-modal-overlay" id="ws-tplmap-modal" style="display:none;">
        <div class="ws-modal-content" style="max-width:380px;">
            <div class="ws-modal-header"><span>Map template variables</span><span style="cursor:pointer;" id="ws-close-tplmap">&times;</span></div>
            <div class="ws-modal-body">
                <div id="ws-tplmap-name" style="font-size:12px; color:var(--ws-ink-500); margin-bottom:8px;"></div>
                <div id="ws-tplmap-rows"></div>
                <div id="ws-tplmap-preview" style="margin-top:10px; padding:8px; background:var(--ws-paper-100); border-radius:6px; font-size:12px; line-height:1.5; color:var(--ws-ink-700); white-space:pre-wrap; word-break:break-word;"></div>
                <button class="btn btn-primary" id="ws-tplmap-apply" style="width:100%; margin-top:10px; font-size:13px;">Use this template</button>
            </div>
        </div>
    </div>

    <!-- Filter Modal -->
    <div class="ws-modal-overlay" id="ws-filter-modal" style="display:none;">
        <div class="ws-modal-content">
            <div class="ws-modal-header"><span>Filter Contacts</span><span style="cursor:pointer;" id="ws-close-filter-modal">&times;</span></div>
            <div class="ws-modal-body">
                <button class="btn btn-primary" id="ws-remove-dupes" style="width:100%; margin-bottom:8px; font-size:13px;">Remove Duplicates</button>
                <button class="btn btn-primary" id="ws-remove-invalid" style="width:100%; margin-bottom:8px; font-size:13px;">Remove Invalid (&lt;10 digits)</button>
                <button class="btn" id="ws-clear-all" style="width:100%; border:1px solid var(--ws-coral); color:var(--ws-coral); background:white; font-size:13px;">Clear All</button>
            </div>
        </div>
    </div>
`;

// ===================== INIT =====================
function initSidebar() {
    if (document.getElementById('wadesk-sidebar')) return;

    // Load the WaDesk brand fonts (Fraunces + Plus Jakarta Sans +
    // JetBrains Mono) so the panel matches the main app. If WhatsApp
    // Web's CSP blocks the request, the CSS falls back to system
    // serif/sans automatically — purely cosmetic, never functional.
    if (!document.getElementById('wadesk-fonts')) {
        var fontLink = document.createElement('link');
        fontLink.id = 'wadesk-fonts';
        fontLink.rel = 'stylesheet';
        fontLink.href = 'https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,400;9..144,500&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;500&display=swap';
        document.head.appendChild(fontLink);
    }

    const sidebar = document.createElement('div');
    sidebar.id = 'wadesk-sidebar';
    sidebar.innerHTML = sidebarHTML;
    document.body.appendChild(sidebar);

    // Resolve the bundled logo via the extension URL (MV3 content scripts
    // can't use relative paths). Used for both the floating launcher and
    // the panel header badge so neither renders a blank box.
    var WS_LOGO_URL = chrome.runtime.getURL('assets/images/logo.png');

    var headerLogoImg = document.getElementById('ws-app-logo-img');
    if (headerLogoImg) headerLogoImg.src = WS_LOGO_URL;

    const fab = document.createElement('div');
    fab.className = 'wadesk-fab';
    fab.innerHTML = '<img src="' + WS_LOGO_URL + '" alt="WaDesk">';
    document.body.appendChild(fab);

    fab.addEventListener('click', function() {
        sidebar.classList.add('open');
        document.body.classList.add('ws-sidebar-open');
    });
    document.getElementById('ws-close-btn').addEventListener('click', function() {
        sidebar.classList.remove('open');
        document.body.classList.remove('ws-sidebar-open');
    });
    document.addEventListener('click', function(e) {
        if (sidebar.classList.contains('open') && !sidebar.contains(e.target) && !fab.contains(e.target)) {
            sidebar.classList.remove('open');
            document.body.classList.remove('ws-sidebar-open');
        }
    });

    // ── Panel ON/OFF (controlled from the extension popup) ───────────
    // When OFF, the floating launcher is hidden and the side panel closed —
    // the extension stays fully out of the way on WhatsApp Web. Default ON.
    function wsSetPanelVisible(enabled) {
        fab.style.display = enabled ? '' : 'none';
        if (!enabled) {
            sidebar.classList.remove('open');
            document.body.classList.remove('ws-sidebar-open');
        }
    }
    try {
        chrome.storage.local.get(['wadesk_panel_enabled'], function(res) {
            wsSetPanelVisible(res.wadesk_panel_enabled !== false); // default ON
        });
        chrome.runtime.onMessage.addListener(function(msg) {
            if (msg && msg.action === 'wadesk-set-panel') wsSetPanelVisible(!!msg.enabled);
        });
    } catch (e) { /* storage/runtime unavailable — leave panel visible */ }

    // ===================== TOAST (replaces native alert) =====================
    // Native wsToast() in a content script renders as an ugly
    // "web.whatsapp.com says" browser dialog. This shows an in-panel
    // toast styled to the WaDesk theme instead. Function declaration so
    // it's hoisted and usable by every handler below.
    var _wsLastToast = { msg: '', type: '', at: 0 };
    function wsToast(message, type) {
        type = type || 'info';
        // De-dupe: swallow an identical toast fired within 1.2s (prevents
        // the doubled "No pending contacts" the user saw).
        var _now = Date.now();
        if (String(message) === _wsLastToast.msg && type === _wsLastToast.type && (_now - _wsLastToast.at) < 1200) {
            return;
        }
        _wsLastToast = { msg: String(message), type: type, at: _now };
        var wrap = document.getElementById('ws-toast-wrap');
        if (!wrap) {
            wrap = document.createElement('div');
            wrap.id = 'ws-toast-wrap';
            wrap.className = 'ws-toast-wrap';
            sidebar.appendChild(wrap);
        }
        var icons = {
            error:   '<svg viewBox="0 0 16 16" width="14" height="14" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="8" cy="8" r="6"/><path d="M8 5v3.5M8 11v.4"/></svg>',
            success: '<svg viewBox="0 0 16 16" width="14" height="14" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="8" cy="8" r="6"/><path d="M5.5 8.5l2 2 3-4"/></svg>',
            info:    '<svg viewBox="0 0 16 16" width="14" height="14" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="8" cy="8" r="6"/><path d="M8 7.5v3.5M8 5v.4"/></svg>'
        };
        var t = document.createElement('div');
        t.className = 'ws-toast ws-toast-' + type;
        t.innerHTML = (icons[type] || icons.info) + '<span></span>';
        t.querySelector('span').textContent = String(message);
        wrap.appendChild(t);
        setTimeout(function () { t.style.opacity = '0'; setTimeout(function () { t.remove(); }, 250); }, 3200);
    }

    // ===================== COUNTRY DIAL CODES =====================
    // Full ITU dial-code list so the picker covers every country, not
    // just a handful. Populated into #ws-country-code on boot; default
    // selection is +91 (India), matching the previous behaviour.
    var WS_DIAL_CODES = [
        ['+93','Afghanistan'],['+355','Albania'],['+213','Algeria'],['+376','Andorra'],['+244','Angola'],
        ['+1','Antigua/US/Canada'],['+54','Argentina'],['+374','Armenia'],['+61','Australia'],['+43','Austria'],
        ['+994','Azerbaijan'],['+973','Bahrain'],['+880','Bangladesh'],['+375','Belarus'],['+32','Belgium'],
        ['+501','Belize'],['+229','Benin'],['+975','Bhutan'],['+591','Bolivia'],['+387','Bosnia'],
        ['+267','Botswana'],['+55','Brazil'],['+673','Brunei'],['+359','Bulgaria'],['+226','Burkina Faso'],
        ['+257','Burundi'],['+855','Cambodia'],['+237','Cameroon'],['+238','Cape Verde'],['+236','Central African Rep.'],
        ['+235','Chad'],['+56','Chile'],['+86','China'],['+57','Colombia'],['+269','Comoros'],
        ['+242','Congo'],['+243','Congo (DRC)'],['+506','Costa Rica'],['+225','Côte d’Ivoire'],['+385','Croatia'],
        ['+53','Cuba'],['+357','Cyprus'],['+420','Czechia'],['+45','Denmark'],['+253','Djibouti'],
        ['+1809','Dominican Rep.'],['+593','Ecuador'],['+20','Egypt'],['+503','El Salvador'],['+240','Eq. Guinea'],
        ['+291','Eritrea'],['+372','Estonia'],['+251','Ethiopia'],['+679','Fiji'],['+358','Finland'],
        ['+33','France'],['+241','Gabon'],['+220','Gambia'],['+995','Georgia'],['+49','Germany'],
        ['+233','Ghana'],['+30','Greece'],['+502','Guatemala'],['+224','Guinea'],['+592','Guyana'],
        ['+509','Haiti'],['+504','Honduras'],['+852','Hong Kong'],['+36','Hungary'],['+354','Iceland'],
        ['+91','India'],['+62','Indonesia'],['+98','Iran'],['+964','Iraq'],['+353','Ireland'],
        ['+972','Israel'],['+39','Italy'],['+1876','Jamaica'],['+81','Japan'],['+962','Jordan'],
        ['+7','Kazakhstan/Russia'],['+254','Kenya'],['+965','Kuwait'],['+996','Kyrgyzstan'],['+856','Laos'],
        ['+371','Latvia'],['+961','Lebanon'],['+266','Lesotho'],['+231','Liberia'],['+218','Libya'],
        ['+370','Lithuania'],['+352','Luxembourg'],['+853','Macau'],['+261','Madagascar'],['+265','Malawi'],
        ['+60','Malaysia'],['+960','Maldives'],['+223','Mali'],['+356','Malta'],['+222','Mauritania'],
        ['+230','Mauritius'],['+52','Mexico'],['+373','Moldova'],['+377','Monaco'],['+976','Mongolia'],
        ['+382','Montenegro'],['+212','Morocco'],['+258','Mozambique'],['+95','Myanmar'],['+264','Namibia'],
        ['+977','Nepal'],['+31','Netherlands'],['+64','New Zealand'],['+505','Nicaragua'],['+227','Niger'],
        ['+234','Nigeria'],['+850','North Korea'],['+389','North Macedonia'],['+47','Norway'],['+968','Oman'],
        ['+92','Pakistan'],['+970','Palestine'],['+507','Panama'],['+675','Papua New Guinea'],['+595','Paraguay'],
        ['+51','Peru'],['+63','Philippines'],['+48','Poland'],['+351','Portugal'],['+974','Qatar'],
        ['+40','Romania'],['+250','Rwanda'],['+966','Saudi Arabia'],['+221','Senegal'],['+381','Serbia'],
        ['+248','Seychelles'],['+232','Sierra Leone'],['+65','Singapore'],['+421','Slovakia'],['+386','Slovenia'],
        ['+252','Somalia'],['+27','South Africa'],['+82','South Korea'],['+211','South Sudan'],['+34','Spain'],
        ['+94','Sri Lanka'],['+249','Sudan'],['+597','Suriname'],['+46','Sweden'],['+41','Switzerland'],
        ['+963','Syria'],['+886','Taiwan'],['+992','Tajikistan'],['+255','Tanzania'],['+66','Thailand'],
        ['+228','Togo'],['+676','Tonga'],['+1868','Trinidad'],['+216','Tunisia'],['+90','Turkey'],
        ['+993','Turkmenistan'],['+256','Uganda'],['+380','Ukraine'],['+971','UAE'],['+44','United Kingdom'],
        ['+598','Uruguay'],['+998','Uzbekistan'],['+58','Venezuela'],['+84','Vietnam'],['+967','Yemen'],
        ['+260','Zambia'],['+263','Zimbabwe']
    ];

    function populateCountryCodes() {
        var sel = document.getElementById('ws-country-code');
        if (!sel) return;
        var html = '';
        WS_DIAL_CODES.forEach(function (c) {
            // Collapsed select is narrow, so show the dial code only; the
            // country name rides in the title for hover identification.
            html += '<option value="' + c[0] + '" title="' + c[1] + '"' + (c[0] === '+91' ? ' selected' : '') + '>' + c[0] + '</option>';
        });
        html += '<option value="">None</option>';
        sel.innerHTML = html;
    }
    populateCountryCodes();

    // ===================== STATE =====================
    var BASE_URL = '';
    var AUTH_TOKEN = '';
    var contactQueue = [];
    var isSending = false;
    var isPaused = false;
    var selectedFile = null;
    var parsedCSVData = null;
    var userAttributes = [];
    var isTemplateLocked = false;
    var lockedTemplateName = '';
    var allTemplatesCache = [];
    // Template mapping for the CURRENT send. `overrides` is posted verbatim as
    // `template_overrides` and read back server-side by
    // TemplateOverrideResolver — the same field the web send screens post.
    var selectedTemplateId = null;
    var templateOverrides = null;
    // Attribute catalog for the mapping dropdowns. Comes from the same
    // SendAttributes catalog every web send screen uses, so the two lists
    // can never drift. Falls back to the older /api/attributes shape.
    var sendAttributes = [];

    function getHeaders() { return { 'Authorization': 'Bearer ' + AUTH_TOKEN, 'Accept': 'application/json' }; }
    function apiUrl(path) { return BASE_URL.replace(/\/$/, '') + path; }

    // Route all API calls through background service worker to avoid HTTPS->HTTP mixed content blocking
    function bgFetch(url, options, timeoutMs) {
        timeoutMs = timeoutMs || 30000;
        return new Promise(function(resolve, reject) {
            var timer = setTimeout(function() { reject(new Error('Request timed out')); }, timeoutMs);
            var msg = { type: 'WADESK_FETCH', url: url, method: (options && options.method) || 'GET', headers: {} };

            // Copy headers (skip Content-Type for FormData)
            if (options && options.headers) {
                for (var key in options.headers) { msg.headers[key] = options.headers[key]; }
            }

            // Handle body
            if (options && options.body && typeof options.body === 'string') {
                msg.body = options.body;
            }

            chrome.runtime.sendMessage(msg, function(response) {
                clearTimeout(timer);
                if (chrome.runtime.lastError) { return reject(new Error(chrome.runtime.lastError.message)); }
                if (!response) { return reject(new Error('No response from background')); }
                resolve(response);
            });
        });
    }

    // Send FormData through background (serializes file to base64)
    function bgFetchForm(url, headers, formFields, file, timeoutMs) {
        timeoutMs = timeoutMs || 60000;
        return new Promise(function(resolve, reject) {
            var timer = setTimeout(function() { reject(new Error('Request timed out')); }, timeoutMs);

            function doSend(serializedForm) {
                var msg = { type: 'WADESK_FETCH', url: url, method: 'POST', headers: headers || {}, formData: serializedForm };
                chrome.runtime.sendMessage(msg, function(response) {
                    clearTimeout(timer);
                    if (chrome.runtime.lastError) { return reject(new Error(chrome.runtime.lastError.message)); }
                    if (!response) { return reject(new Error('No response from background')); }
                    resolve(response);
                });
            }

            // Build serializable form data
            var serialized = {};
            for (var key in formFields) { serialized[key] = formFields[key]; }

            if (file) {
                var reader = new FileReader();
                reader.onload = function() {
                    var base64 = reader.result.split(',')[1];
                    serialized['image_file'] = { __isFile: true, data: base64, type: file.type, name: file.name };
                    doSend(serialized);
                };
                reader.onerror = function() { clearTimeout(timer); reject(new Error('Failed to read file')); };
                reader.readAsDataURL(file);
            } else {
                doSend(serialized);
            }
        });
    }

    // ===================== TAB SWITCHING =====================
    var tabs = sidebar.querySelectorAll('.ws-nav-item');
    var tabContents = sidebar.querySelectorAll('.ws-tab-content');
    tabs.forEach(function(tab) {
        tab.addEventListener('click', function() {
            tabs.forEach(function(t) { t.classList.remove('active'); });
            tabContents.forEach(function(c) { c.style.display = 'none'; });
            tab.classList.add('active');
            var target = document.getElementById('tab-' + tab.dataset.tab);
            if (target) {
                target.style.display = 'block';
                if (tab.dataset.tab === 'recent') fetchReports(1);
                if (tab.dataset.tab === 'templates') fetchTemplates();
            }
        });
    });

    // ===================== AUTH =====================
    var authView = document.getElementById('ws-auth-view');
    var profileView = document.getElementById('ws-profile-view');
    var appView = document.getElementById('ws-app-view');

    // Pull the dynamic platform name from system settings so the header
    // shows whatever the admin set (never a hardcoded "WaDesk").
    function applyBranding() {
        bgFetch(apiUrl('/api/app-config'), { headers: { 'Accept': 'application/json' } }, 8000)
            .then(function (res) {
                if (!res.ok) return;
                var cfg = JSON.parse(res.body);
                var name = (cfg && cfg.app_name) ? String(cfg.app_name).trim() : '';
                if (!name) return;
                document.getElementById('ws-app-name').innerText = name;
                // Keep the logo image badge — don't replace it with text
                // initials (that's what caused the blank/letter box).
            })
            .catch(function () { /* keep default header */ });
    }

    function checkSession() {
        chrome.storage.local.get(['wadesk_token', 'wadesk_base_url', 'wadesk_user'], function(res) {
            BASE_URL = res.wadesk_base_url || WADESK_SERVER_URL.replace(/\/$/, '');
            applyBranding();
            if (res.wadesk_token) {
                AUTH_TOKEN = res.wadesk_token;
                var user = res.wadesk_user || {};
                var initials = user.name ? user.name.substring(0, 2).toUpperCase() : 'ME';
                document.getElementById('ws-user-initials').innerText = initials;
                document.getElementById('ws-profile-avatar-lg').innerText = initials;
                document.getElementById('ws-profile-name').innerText = user.name || 'User';
                document.getElementById('ws-profile-email').innerText = user.email || '';
                authView.style.display = 'none';
                profileView.style.display = 'block';
                appView.style.display = 'none';
                document.getElementById('ws-nav-tabs').style.display = 'none'; (function(){ var _hp = document.getElementById('ws-header-credits'); if (_hp) _hp.style.display = 'none'; })();
                fetchDevices();
                loadAttributes();
            } else {
                authView.style.display = 'block';
                profileView.style.display = 'none';
                appView.style.display = 'none';
                document.getElementById('ws-nav-tabs').style.display = 'none'; (function(){ var _hp = document.getElementById('ws-header-credits'); if (_hp) _hp.style.display = 'none'; })();
            }
        });
    }

    // Login
    document.getElementById('ws-login-btn').addEventListener('click', async function() {
        var email = document.getElementById('ws-email').value.trim();
        var password = document.getElementById('ws-password').value;
        if (!email) return wsToast('Please enter email', 'error');
        if (!password) return wsToast('Please enter password', 'error');

        var btn = document.getElementById('ws-login-btn');
        btn.innerText = 'Connecting...'; btn.disabled = true;
        try {
            // Fetch base URL from app-config endpoint
            var serverUrl = WADESK_SERVER_URL.replace(/\/$/, '');
            var finalUrl = serverUrl;
            try {
                var configRes = await bgFetch(serverUrl + '/api/app-config', { headers: { 'Accept': 'application/json' } }, 10000);
                if (configRes.ok) { var config = JSON.parse(configRes.body); if (config.app_url) finalUrl = config.app_url.replace(/\/$/, ''); }
            } catch (e) { /* use configured SERVER_URL */ }

            btn.innerText = 'Logging in...';
            var res = await bgFetch(finalUrl + '/api/login', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                body: JSON.stringify({ email: email, password: password })
            }, 15000);
            var data = JSON.parse(res.body);
            if (res.ok && data.status === 'success') {
                chrome.storage.local.set({ wadesk_token: data.access_token, wadesk_user: data.user, wadesk_base_url: finalUrl }, function() { checkSession(); });
            } else {
                wsToast(data.message || 'Login failed.', 'error');
            }
        } catch (e) {
            wsToast('Connection failed. Please try again.', 'error');
        } finally { btn.innerText = 'Sign In'; btn.disabled = false; }
    });

    // Logout
    document.getElementById('ws-logout-btn').addEventListener('click', function() {
        chrome.storage.local.remove(['wadesk_token', 'wadesk_user', 'wadesk_selected_device'], function() { AUTH_TOKEN = ''; checkSession(); });
    });

    // Avatar click -> back to profile
    document.getElementById('ws-user-initials').addEventListener('click', function() {
        if (appView.style.display === 'block') { appView.style.display = 'none'; profileView.style.display = 'block'; document.getElementById('ws-nav-tabs').style.display = 'none'; (function(){ var _hp = document.getElementById('ws-header-credits'); if (_hp) _hp.style.display = 'none'; })(); fetchDevices(); }
    });

    // ===================== DEVICES =====================
    async function fetchDevices() {
        try {
            var res = await bgFetch(apiUrl('/api/get-devices'), { headers: getHeaders() }, 15000);
            if (!res.ok) throw new Error('HTTP ' + res.status);
            var data = JSON.parse(res.body);
            var devices = data.devices || (Array.isArray(data) ? data : []);
            var select = document.getElementById('ws-profile-device-select');
            select.innerHTML = '';
            if (devices.length > 0) {
                devices.forEach(function(d) {
                    var opt = document.createElement('option');
                    opt.value = d.phone_number;
                    opt.innerText = (d.device_name || 'Device') + ' (' + d.phone_number + ')';
                    select.appendChild(opt);
                });
                chrome.storage.local.get('wadesk_selected_device', function(s) { if (s.wadesk_selected_device) select.value = s.wadesk_selected_device; });
            } else { select.innerHTML = '<option value="">No devices found</option>'; }
        } catch (e) { document.getElementById('ws-profile-device-select').innerHTML = '<option value="">Error loading</option>'; }
    }

    document.getElementById('ws-start-btn').addEventListener('click', function() {
        var device = document.getElementById('ws-profile-device-select').value;
        if (!device) return wsToast('Please select a device.', 'error');
        chrome.storage.local.set({ wadesk_selected_device: device });
        profileView.style.display = 'none'; appView.style.display = 'block';
        document.getElementById('ws-nav-tabs').style.display = 'flex';
        fetchPlanCredits();
    });

    // ===================== ATTRIBUTES / CUSTOMIZATIONS =====================
    async function loadAttributes() {
        // Only the workspace's real attributes — same source the web
        // /attributes page uses. No synthetic built-ins.
        userAttributes = [];
        try {
            var res = await bgFetch(apiUrl('/api/attributes'), { headers: getHeaders() }, 15000);
            if (res.ok) {
                var data = JSON.parse(res.body);
                if (data && typeof data === 'object') {
                    userAttributes = (data.custom_attributes || data.data || []).map(function(a) {
                        return { key: a.attribute_key || a.key, name: a.attribute_name || a.name };
                    });
                }
            }
        } catch (e) { userAttributes = []; }
        renderCustomizations();
    }

    function renderCustomizations() {
        var container = document.getElementById('ws-customizations');
        container.innerHTML = '';

        // When a template is selected the body is locked, so instead of
        // attribute chips we surface which template is active (with a way
        // to clear it) — sits above the textarea, never over the text.
        if (isTemplateLocked) {
            var tLbl = document.createElement('span');
            tLbl.style.cssText = 'font-size:12px; color:var(--ws-ink-500);';
            tLbl.innerText = 'Template:';
            container.appendChild(tLbl);

            var chip = document.createElement('span');
            chip.className = 'ws-template-chip';
            chip.appendChild(document.createTextNode(lockedTemplateName || 'Template'));
            var rm = document.createElement('span');
            rm.className = 'ws-template-chip-x';
            rm.title = 'Remove template';
            rm.innerHTML = '&times;';
            rm.addEventListener('click', unlockTemplate);
            chip.appendChild(rm);
            container.appendChild(chip);
            return;
        }

        if (!userAttributes.length) return; // no workspace attributes → nothing to show

        var lbl = document.createElement('span');
        lbl.style.cssText = 'font-size:12px; color:var(--ws-ink-500);';
        lbl.innerText = 'Customizations:';
        container.appendChild(lbl);

        userAttributes.slice(0, 2).forEach(function(attr) {
            var btn = document.createElement('button');
            btn.className = 'ws-custom-tag'; btn.innerText = attr.name;
            btn.addEventListener('click', function() { insertAttr(attr.key); });
            container.appendChild(btn);
        });
        if (userAttributes.length > 2) {
            var moreBtn = document.createElement('button');
            moreBtn.className = 'ws-custom-tag'; moreBtn.innerText = '+ ' + (userAttributes.length - 2);
            moreBtn.addEventListener('click', showAttrModal);
            container.appendChild(moreBtn);
        }
    }

    function insertAttr(key) {
        var ta = document.getElementById('ws-message-input');
        if (isTemplateLocked) return; // can't edit template
        var pos = ta.selectionStart; var txt = ta.value; var ph = '{{' + key + '}}';
        ta.value = txt.substring(0, pos) + ph + txt.substring(pos);
        ta.focus(); ta.selectionStart = ta.selectionEnd = pos + ph.length;
    }

    function lockTemplate(name) {
        isTemplateLocked = true;
        lockedTemplateName = name;
        var ta = document.getElementById('ws-message-input');
        ta.readOnly = true;
        ta.classList.add('ws-textarea-locked');
        renderCustomizations();
    }

    function unlockTemplate() {
        isTemplateLocked = false;
        lockedTemplateName = '';
        // Drop the mapping with the template — a stale template_id would make
        // the server resolve slots for a template that is no longer selected.
        selectedTemplateId = null;
        templateOverrides = null;
        var ta = document.getElementById('ws-message-input');
        ta.readOnly = false;
        ta.value = '';
        ta.classList.remove('ws-textarea-locked');
        renderCustomizations();
    }

    function showAttrModal() {
        var modal = document.getElementById('ws-attr-modal');
        var body = document.getElementById('ws-attr-modal-body');
        body.innerHTML = '';
        userAttributes.forEach(function(attr) {
            var btn = document.createElement('button');
            btn.className = 'btn';
            btn.style.cssText = 'width:100%; margin-bottom:8px; background:white; border:1px solid var(--ws-paper-200); text-align:left; font-size:13px; color:var(--ws-ink-900);';
            btn.innerText = '{{' + attr.key + '}} - ' + attr.name;
            btn.addEventListener('click', function() { insertAttr(attr.key); modal.style.display = 'none'; });
            body.appendChild(btn);
        });
        modal.style.display = 'flex';
    }
    document.getElementById('ws-close-attr-modal').addEventListener('click', function() { document.getElementById('ws-attr-modal').style.display = 'none'; });

    // ===================== SLASH "/" ATTRIBUTE PICKER =====================
    // Mirrors the web chat composer: typing "/" at a token start opens an
    // inline dropdown of attributes; picking one inserts {{key}}. Arrow
    // keys navigate, Enter selects, Escape closes.
    (function initSlashPicker() {
        var ta = document.getElementById('ws-message-input');
        var wrap = ta ? ta.closest('.ws-textarea-wrap') : null;
        if (!ta || !wrap) return;
        var pop = null, slashStart = -1, items = [], activeIdx = 0;

        function closePop() { if (pop) { pop.remove(); pop = null; } slashStart = -1; }
        function ensurePop() { if (!pop) { pop = document.createElement('div'); pop.className = 'ws-attr-pop'; wrap.appendChild(pop); } return pop; }
        function paint() { if (pop) pop.querySelectorAll('.ws-attr-pop-item').forEach(function (r) { r.classList.toggle('active', parseInt(r.dataset.i, 10) === activeIdx); }); }

        function render(query) {
            var q = query.toLowerCase();
            items = (userAttributes || []).filter(function (a) {
                return a.key.toLowerCase().indexOf(q) !== -1 || (a.name || '').toLowerCase().indexOf(q) !== -1;
            });
            var p = ensurePop();
            if (items.length === 0) { p.innerHTML = '<div class="ws-attr-pop-empty">No matching attributes</div>'; return; }
            activeIdx = 0;
            p.innerHTML = items.map(function (a, i) {
                return '<div class="ws-attr-pop-item' + (i === 0 ? ' active' : '') + '" data-i="' + i + '"><span class="n">' + a.name + '</span><span class="k">{{' + a.key + '}}</span></div>';
            }).join('');
            p.querySelectorAll('.ws-attr-pop-item').forEach(function (row) {
                row.addEventListener('mousedown', function (e) { e.preventDefault(); pick(parseInt(row.dataset.i, 10)); });
            });
        }

        function pick(i) {
            var a = items[i]; if (!a || isTemplateLocked) { closePop(); return; }
            var caret = ta.selectionStart;
            var before = ta.value.substring(0, slashStart);
            var after = ta.value.substring(caret);
            var token = '{{' + a.key + '}}';
            ta.value = before + token + after;
            var pos = (before + token).length;
            ta.focus(); ta.selectionStart = ta.selectionEnd = pos;
            closePop();
        }

        ta.addEventListener('input', function () {
            if (isTemplateLocked) return closePop();
            var caret = ta.selectionStart;
            var upto = ta.value.substring(0, caret);
            var sidx = upto.lastIndexOf('/');
            if (sidx === -1) return closePop();
            var prev = sidx === 0 ? '' : upto[sidx - 1];
            if (prev && !/\s/.test(prev)) return closePop();   // only trigger at a token start
            var query = upto.substring(sidx + 1);
            if (/[\s\n]/.test(query) || query.length > 24) return closePop();
            slashStart = sidx;
            render(query);
        });

        ta.addEventListener('keydown', function (e) {
            if (!pop) return;
            if (e.key === 'Escape') { e.preventDefault(); closePop(); }
            else if (e.key === 'ArrowDown') { e.preventDefault(); activeIdx = Math.min(activeIdx + 1, items.length - 1); paint(); }
            else if (e.key === 'ArrowUp') { e.preventDefault(); activeIdx = Math.max(activeIdx - 1, 0); paint(); }
            else if (e.key === 'Enter' && items.length) { e.preventDefault(); pick(activeIdx); }
        });
        ta.addEventListener('blur', function () { setTimeout(closePop, 150); });
    })();

    // ===================== CONTACTS =====================
    function addTag(phone) {
        phone = phone.replace(/[^0-9+]/g, '');
        var cc = document.getElementById('ws-country-code').value;
        if (cc && !phone.startsWith('+') && !phone.startsWith('0') && phone.length <= 10) {
            phone = cc.replace('+', '') + phone;
        }
        phone = phone.replace(/\D/g, '');
        if (phone.length < 10) return;
        if (contactQueue.find(function(c) { return c.phone === phone; })) return;
        contactQueue.push({ phone: phone, status: 'pending' });
        renderTags(); updateCount();
    }

    function renderTags() {
        var container = document.getElementById('ws-numbers-container');
        container.innerHTML = '';
        contactQueue.slice(0, 60).forEach(function(c) {
            var tag = document.createElement('div');
            tag.className = 'ws-tag' + (c.status === 'sent' ? ' ws-tag-sent' : c.status === 'failed' ? ' ws-tag-failed' : '');
            tag.innerHTML = c.phone + '<span class="ws-tag-close" data-phone="' + c.phone + '">&times;</span>';
            container.appendChild(tag);
        });
        if (contactQueue.length > 60) {
            var more = document.createElement('div'); more.className = 'ws-tag'; more.style.background = 'var(--ws-mint)';
            more.innerText = '+' + (contactQueue.length - 60) + ' more'; container.appendChild(more);
        }
        container.querySelectorAll('.ws-tag-close').forEach(function(btn) {
            btn.addEventListener('click', function(e) {
                contactQueue = contactQueue.filter(function(c) { return c.phone !== e.target.dataset.phone; });
                renderTags(); updateCount();
            });
        });
    }

    function updateCount() { document.getElementById('ws-contact-count').innerText = contactQueue.length; }

    var numberInput = document.getElementById('ws-number-input');
    numberInput.addEventListener('keydown', function(e) {
        if (e.key === 'Enter' || e.key === ',') {
            e.preventDefault();
            var val = numberInput.value.trim();
            if (val) { val.split(/[,\n\s]+/).forEach(function(n) { if (n.trim()) addTag(n.trim()); }); }
            numberInput.value = '';
        }
    });
    numberInput.addEventListener('paste', function() {
        setTimeout(function() {
            var val = numberInput.value.trim();
            if (val && (val.includes(',') || val.includes('\n') || val.includes(' ') || val.length > 15)) {
                val.split(/[,\n\s]+/).forEach(function(n) { if (n.trim()) addTag(n.trim()); });
                numberInput.value = '';
            }
        }, 150);
    });

    // ===================== DEMO CSV DOWNLOAD =====================
    document.getElementById('ws-download-demo').addEventListener('click', function() {
        var csv = 'phone_number\n919876543210\n918765432109\n14155552671\n447911123456\n971501234567';
        var blob = new Blob([csv], { type: 'text/csv' });
        var url = URL.createObjectURL(blob);
        var a = document.createElement('a'); a.href = url; a.download = 'demo_contacts.csv'; a.click();
        URL.revokeObjectURL(url);
    });

    // ===================== CSV IMPORT =====================
    document.querySelector('.ws-excel-btn').addEventListener('click', function() { document.getElementById('ws-file-upload').click(); });
    document.getElementById('ws-file-upload').addEventListener('change', function(e) {
        var file = e.target.files[0]; if (!file) return;
        document.getElementById('ws-file-name').innerText = file.name;
        var reader = new FileReader();
        reader.onload = function(ev) {
            var text = ev.target.result;
            var lines = text.split(/\r\n|\n/).filter(function(l) { return l.trim() !== ''; });
            if (lines.length === 0) return wsToast('File is empty.', 'error');
            var headers = lines[0].split(/[,;\t]/);
            parsedCSVData = { headers: headers, rows: lines.slice(1), filename: file.name };
            document.getElementById('ws-mapper-filename').innerText = 'File: ' + file.name;
            var colSelect = document.getElementById('ws-mapper-column');
            colSelect.innerHTML = '';
            headers.forEach(function(h, i) {
                var opt = document.createElement('option'); opt.value = i;
                opt.innerText = (h.trim() || 'Column ' + (i + 1)) + ' (' + (i + 1) + ')';
                colSelect.appendChild(opt);
            });
            document.getElementById('ws-mapper-to').value = lines.length;
            document.getElementById('ws-mapper-modal').style.display = 'flex';
        };
        reader.readAsText(file); e.target.value = '';
    });

    document.getElementById('ws-mapper-cancel').addEventListener('click', function() { document.getElementById('ws-mapper-modal').style.display = 'none'; });
    document.getElementById('ws-close-mapper').addEventListener('click', function() { document.getElementById('ws-mapper-modal').style.display = 'none'; });
    document.getElementById('ws-mapper-import').addEventListener('click', function() {
        if (!parsedCSVData) return;
        var colIndex = parseInt(document.getElementById('ws-mapper-column').value);
        var fromRow = parseInt(document.getElementById('ws-mapper-from').value) - 2;
        var toRow = parseInt(document.getElementById('ws-mapper-to').value) - 1;
        var rows = parsedCSVData.rows.slice(Math.max(0, fromRow), toRow);
        var added = 0;
        rows.forEach(function(row) {
            var cols = row.split(/[,;\t]/);
            if (cols[colIndex]) {
                var phone = cols[colIndex].trim().replace(/[^0-9+]/g, '');
                if (phone && phone.replace(/\D/g, '').length >= 10) { addTag(phone); added++; }
            }
        });
        wsToast('Imported ' + added + ' contacts.', 'success');
        document.getElementById('ws-mapper-modal').style.display = 'none';
    });

    // ===================== FILTER =====================
    document.getElementById('ws-filter-btn').addEventListener('click', function() { document.getElementById('ws-filter-modal').style.display = 'flex'; });
    document.getElementById('ws-close-filter-modal').addEventListener('click', function() { document.getElementById('ws-filter-modal').style.display = 'none'; });
    document.getElementById('ws-remove-dupes').addEventListener('click', function() {
        var before = contactQueue.length; var seen = {};
        contactQueue = contactQueue.filter(function(c) { if (seen[c.phone]) return false; seen[c.phone] = true; return true; });
        renderTags(); updateCount(); wsToast('Removed ' + (before - contactQueue.length) + ' duplicates.', 'success');
    });
    document.getElementById('ws-remove-invalid').addEventListener('click', function() {
        var before = contactQueue.length;
        contactQueue = contactQueue.filter(function(c) { return c.phone.length >= 10; });
        renderTags(); updateCount(); wsToast('Removed ' + (before - contactQueue.length) + ' invalid numbers.', 'success');
    });
    document.getElementById('ws-clear-all').addEventListener('click', function() {
        contactQueue = []; renderTags(); updateCount(); document.getElementById('ws-filter-modal').style.display = 'none';
    });

    // ===================== SETTINGS TOGGLES =====================
    document.getElementById('ws-random-delay-toggle').addEventListener('change', function(e) {
        document.getElementById('ws-random-inputs').style.display = e.target.checked ? 'flex' : 'none';
    });
    document.getElementById('ws-batching-toggle').addEventListener('change', function(e) {
        document.getElementById('ws-batching-inputs').style.display = e.target.checked ? 'flex' : 'none';
    });

    // ===================== ATTACHMENT =====================
    document.getElementById('ws-attach-trigger').addEventListener('click', function() { document.getElementById('ws-attachment-upload').click(); });
    document.getElementById('ws-attachment-upload').addEventListener('change', function(e) {
        selectedFile = e.target.files[0];
        if (selectedFile) {
            if (selectedFile.size > 8 * 1024 * 1024) { wsToast('File exceeds 8MB limit.', 'error'); selectedFile = null; e.target.value = ''; return; }
            document.getElementById('ws-attach-label').innerText = selectedFile.name.length > 25 ? selectedFile.name.substring(0, 25) + '...' : selectedFile.name;
            document.getElementById('ws-attach-trigger').style.color = 'var(--ws-deep)';
        }
    });

    // ===================== PROGRESS UI =====================
    function updateProgressUI() {
        var area = document.getElementById('ws-progress-area');
        var total = contactQueue.length;
        if (total === 0) { area.style.display = 'none'; return; }
        area.style.display = 'block';
        var sent = contactQueue.filter(function(c) { return c.status === 'sent'; }).length;
        var failed = contactQueue.filter(function(c) { return c.status === 'failed'; }).length;
        var current = sent + failed;
        document.getElementById('ws-progress-text').innerText = current + '/' + total;
        document.getElementById('ws-progress-bar').style.width = (current / total * 100) + '%';
        document.getElementById('ws-sent-count').innerText = sent + ' sent';
        document.getElementById('ws-failed-count').innerText = failed + ' failed';
        var badge = document.getElementById('ws-status-badge');
        if (isPaused) { badge.innerText = 'Paused'; badge.style.color = 'var(--ws-amber)'; }
        else if (isSending) { badge.innerText = 'Running'; badge.style.color = 'var(--ws-deep)'; }
        else if (current === total && total > 0) { badge.innerText = 'Completed'; badge.style.color = 'var(--ws-deep)'; }
        else { badge.innerText = 'Stopped'; badge.style.color = 'var(--ws-coral)'; }
        renderTags();
    }

    // ===================== SENDING =====================
    var sendBtnEl = document.getElementById('ws-send-btn');
    sendBtnEl.addEventListener('click', async function() {
        // Double-send guard: ignore clicks while a send is already in
        // flight (this, plus disabling the button, kills the duplicate
        // toast the user saw).
        if (isSending) return;

        var msg = document.getElementById('ws-message-input').value;
        var unsubEl = document.getElementById('ws-unsubscribe-toggle');
        var unsubscribe = unsubEl ? unsubEl.checked : false;

        var fromNumber = await new Promise(function(r) { chrome.storage.local.get('wadesk_selected_device', function(d) { r(d.wadesk_selected_device || ''); }); });
        if (!fromNumber) return wsToast('No device selected. Click your avatar to select one.', 'error');
        if (!msg && !selectedFile) return wsToast('Enter a message or attach a file.', 'error');

        // Add typed number if any
        var inputVal = numberInput.value.trim();
        if (inputVal) { inputVal.split(/[,\n\s]+/).forEach(function(n) { if (n.trim()) addTag(n.trim()); }); numberInput.value = ''; }

        // Re-queue: if nothing is pending but the list still has contacts
        // (all previously sent/failed), reset them to pending so the user
        // can cleanly "send again to this list". Only a genuinely empty
        // queue (no numbers at all) shows "No pending contacts".
        var pending = contactQueue.filter(function(c) { return c.status === 'pending'; });
        if (pending.length === 0) {
            if (contactQueue.length === 0) return wsToast('No pending contacts.', 'error');
            contactQueue.forEach(function(c) { c.status = 'pending'; });
            renderTags(); updateCount();
        }

        var finalMsg = msg;
        if (unsubscribe && msg) finalMsg += '\n\n_Reply STOP to unsubscribe_';

        // Pacing controls (ban-protection). "Time gap between messages"
        // toggle reveals min/max sec; "Split into batches" reveals
        // batch-size + wait-minutes. Read + sanitise so bad input can't
        // disable the throttle.
        var useRandomDelay = document.getElementById('ws-random-delay-toggle').checked;
        var minDelay = Math.max(0, (parseInt(document.getElementById('ws-delay-min').value, 10) || 0)) * 1000;
        var maxDelay = Math.max(0, (parseInt(document.getElementById('ws-delay-max').value, 10) || 0)) * 1000;
        if (maxDelay < minDelay) { var _t = minDelay; minDelay = maxDelay; maxDelay = _t; } // swap if reversed
        var useBatching = document.getElementById('ws-batching-toggle').checked;
        var batchSize = Math.max(1, parseInt(document.getElementById('ws-batch-size').value, 10) || 1);
        var batchWait = Math.max(1, parseInt(document.getElementById('ws-batch-wait').value, 10) || 1) * 60 * 1000;

        isSending = true; isPaused = false;
        sendBtnEl.disabled = true;
        sendBtnEl.classList.add('ws-send-btn-busy');
        document.getElementById('ws-pause-resume').innerText = 'Pause';
        updateProgressUI();

        var sentCount = 0;
        var queueToProcess = contactQueue.filter(function(c) { return c.status === 'pending'; });

        for (var i = 0; i < queueToProcess.length; i++) {
            var contact = queueToProcess[i];
            if (!isSending) break;
            while (isPaused) { await new Promise(function(r) { setTimeout(r, 500); }); if (!isSending) break; }
            if (!isSending) break;

            // Batch pause
            if (useBatching && sentCount > 0 && sentCount % batchSize === 0) {
                document.getElementById('ws-status-badge').innerText = 'Batch Wait...';
                document.getElementById('ws-status-badge').style.color = 'var(--ws-amber)';
                await new Promise(function(r) { setTimeout(r, batchWait); });
                if (!isSending) break;
            }

            try {
                var tType = 'Plane-Text';
                if (selectedFile) { tType = finalMsg ? 'Text-With-Media' : 'Image-Only'; }
                var fields = { to_number: contact.phone, from_number: fromNumber, message_text: finalMsg, template_type: tType };
                // Hand the server the template + mapping so it resolves the
                // variables for THIS recipient. Without these the body would
                // go out with its literal "{{1}}" braces.
                if (selectedTemplateId) fields.template_id = selectedTemplateId;
                if (templateOverrides) fields.template_overrides = templateOverrides;
                var response = await bgFetchForm(
                    apiUrl('/api/send-quick-message'),
                    { 'Authorization': 'Bearer ' + AUTH_TOKEN, 'Accept': 'application/json' },
                    fields, selectedFile, 60000
                );
                if (response.ok) { contact.status = 'sent'; sentCount++; }
                else { contact.status = 'failed'; }
            } catch (err) { contact.status = 'failed'; }

            updateProgressUI();

            if (useRandomDelay) {
                // Random gap between min and max seconds (inclusive).
                var delay = Math.floor(Math.random() * (maxDelay - minDelay + 1)) + minDelay;
                if (delay < 300) delay = 300; // never hammer back-to-back
                await new Promise(function(r) { setTimeout(r, delay); });
            } else {
                // Toggle off → small fixed gap so sends still pace.
                await new Promise(function(r) { setTimeout(r, 1000); });
            }
        }

        // Natural completion = we walked the whole queue without Stop
        // interrupting (every item we set out to process is no longer
        // pending). Stop leaves trailing 'pending' items → partial run.
        var completedNaturally = queueToProcess.every(function(c) { return c.status !== 'pending'; });

        isSending = false;
        sendBtnEl.disabled = false;
        sendBtnEl.classList.remove('ws-send-btn-busy');
        updateProgressUI();
        fetchReports(1);

        if (completedNaturally) {
            var doneSent = contactQueue.filter(function(c) { return c.status === 'sent'; }).length;
            var doneFailed = contactQueue.filter(function(c) { return c.status === 'failed'; }).length;
            wsToast('Sent ' + doneSent + ' / failed ' + doneFailed + ' — form cleared.', 'success');
            // Brief pause so the user still sees "Completed", then wipe
            // the form so a new send can start with no leftovers.
            setTimeout(resetComposeForm, 1400);
        }
    });

    // Clears the compose form after a fully-completed send so the panel is
    // immediately reusable (Bug 1). NOT called on Stop/partial runs.
    function resetComposeForm() {
        // Message + template
        var ta = document.getElementById('ws-message-input');
        if (isTemplateLocked) { unlockTemplate(); } // unlocks body + clears chip
        if (ta) { ta.value = ''; ta.readOnly = false; ta.classList.remove('ws-textarea-locked'); }

        // Recipients / queue
        contactQueue = [];
        renderTags(); updateCount();

        // Attachment
        selectedFile = null;
        var attEl = document.getElementById('ws-attachment-upload');
        if (attEl) attEl.value = '';
        var attLabel = document.getElementById('ws-attach-label');
        if (attLabel) attLabel.innerText = 'Attach a file (max 8MB)';
        var attTrigger = document.getElementById('ws-attach-trigger');
        if (attTrigger) attTrigger.style.color = '';
        var fileNameEl = document.getElementById('ws-file-name');
        if (fileNameEl) fileNameEl.innerText = '';
        parsedCSVData = null;

        // Progress UI → reset to 0/0 with no leftover status
        var ptext = document.getElementById('ws-progress-text');
        if (ptext) ptext.innerText = '0/0';
        var pbar = document.getElementById('ws-progress-bar');
        if (pbar) pbar.style.width = '0%';
        var sc = document.getElementById('ws-sent-count'); if (sc) sc.innerText = '0 sent';
        var fc = document.getElementById('ws-failed-count'); if (fc) fc.innerText = '0 failed';
        var badge = document.getElementById('ws-status-badge'); if (badge) { badge.innerText = ''; }
        var area = document.getElementById('ws-progress-area'); if (area) area.style.display = 'none';

        renderCustomizations();
    }

    document.getElementById('ws-pause-resume').addEventListener('click', function() {
        isPaused = !isPaused;
        document.getElementById('ws-pause-resume').innerText = isPaused ? 'Resume' : 'Pause';
        updateProgressUI();
    });
    document.getElementById('ws-stop').addEventListener('click', function() {
        isSending = false; isPaused = false;
        // Re-enable Send immediately; the form is intentionally NOT
        // cleared on Stop (partial run — user may resume/inspect).
        sendBtnEl.disabled = false;
        sendBtnEl.classList.remove('ws-send-btn-busy');
        updateProgressUI();
    });

    // ===================== REPORTS =====================
    async function fetchReports(page) {
        page = page || 1;
        var container = document.getElementById('ws-reports-list');
        var pagination = document.getElementById('ws-reports-pagination');
        container.innerHTML = '<p class="ws-recent-empty">Loading…</p>';
        pagination.innerHTML = '';
        function esc(s) { return String(s == null ? '' : s).replace(/[&<>"]/g, function(c) { return { '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;' }[c]; }); }
        try {
            var res = await bgFetch(apiUrl('/api/message-history?page=' + page), { headers: getHeaders() }, 20000);
            if (!res.ok) throw new Error('HTTP ' + res.status);
            var data = JSON.parse(res.body);

            var messages = []; var lastPage = 1;
            if (data.data && data.data.data) { messages = data.data.data; lastPage = data.data.last_page || 1; }
            else if (Array.isArray(data.data)) { messages = data.data; }
            else if (Array.isArray(data)) { messages = data; }

            if (messages.length === 0) {
                container.innerHTML = '<p class="ws-recent-empty">No sends yet.</p>';
                return;
            }

            var html = '';
            messages.forEach(function(msg) {
                var st = msg.status;
                var stCls = st == 1 ? 'ws-rs-sent' : (st == 2 ? 'ws-rs-failed' : 'ws-rs-pending');
                var stText = st == 1 ? 'Sent' : (st == 2 ? 'Failed' : 'Pending');
                var msgText = msg.temp_caption || msg.message || msg.template_title || 'Media';
                var time = msg.created_at ? new Date(msg.created_at).toLocaleString() : (msg.time || '');
                html += '<div class="ws-recent-card">' +
                    '<div class="ws-recent-card-head">' +
                        '<span class="ws-recent-num">' + esc(msg.to_number) + '</span>' +
                        '<span class="ws-recent-pill ' + stCls + '">' + stText + '</span>' +
                    '</div>' +
                    '<div class="ws-recent-body">' + esc(msgText) + '</div>' +
                    '<div class="ws-recent-time">' +
                        '<svg xmlns="http://www.w3.org/2000/svg" width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><polyline points="12 7 12 12 15 14"/></svg>' +
                        esc(time) +
                    '</div>' +
                '</div>';
            });
            container.innerHTML = html;

            if (lastPage > 1) {
                for (var p = 1; p <= Math.min(lastPage, 5); p++) {
                    (function(pageNum) {
                        var btn = document.createElement('button'); btn.className = 'btn';
                        btn.style.cssText = 'width:auto; padding:4px 10px; font-size:12px; ' + (pageNum === page ? 'background:var(--ws-primary); color:white;' : 'background:white; border:1px solid var(--ws-paper-200); color:var(--ws-ink-900);');
                        btn.innerText = pageNum;
                        btn.addEventListener('click', function() { fetchReports(pageNum); });
                        pagination.appendChild(btn);
                    })(p);
                }
            }
        } catch (e) { container.innerHTML = '<p class="ws-recent-empty" style="color:var(--ws-coral);">Couldn\'t load recent sends.</p>'; }
    }

    document.getElementById('ws-download-report').addEventListener('click', async function() {
        try {
            var res = await bgFetch(apiUrl('/api/get-contact-csv'), { headers: getHeaders() }, 30000);
            if (res.ok) {
                var blob;
                if (res.isBinary) {
                    // base64 data URL from background
                    var fetchRes = await fetch(res.body);
                    blob = await fetchRes.blob();
                } else {
                    blob = new Blob([res.body], { type: 'text/csv' });
                }
                var url = URL.createObjectURL(blob);
                var a = document.createElement('a'); a.href = url; a.download = 'report.csv'; a.click(); URL.revokeObjectURL(url);
            } else { wsToast('Download not available.', 'error'); }
        } catch (e) { wsToast('Failed to download.', 'error'); }
    });

    // ===================== TEMPLATES =====================
    function switchToSendTab() {
        tabs.forEach(function(t) { t.classList.remove('active'); });
        tabContents.forEach(function(c) { c.style.display = 'none'; });
        var sendTab = sidebar.querySelector('.ws-nav-item[data-tab="send"]');
        if (sendTab) sendTab.classList.add('active');
        var sendContent = document.getElementById('tab-send');
        if (sendContent) sendContent.style.display = 'block';
    }

    function renderTemplateCards(templates) {
        var container = document.getElementById('ws-templates-list');
        var countEl = document.getElementById('ws-templates-count');
        if (templates.length === 0) {
            container.innerHTML = '<p style="color:var(--ws-ink-500); text-align:center; padding:20px;">No templates found.</p>';
            countEl.innerText = '';
            return;
        }
        countEl.innerText = templates.length + ' template' + (templates.length > 1 ? 's' : '');
        container.innerHTML = '';
        templates.forEach(function(t) {
            var name = t.template_name || t.name || 'Untitled';
            var body = t.template_body || t.body || '';
            var preview = body.length > 80 ? body.substring(0, 80) + '...' : body;
            var type = t.template_type || 'text';
            var typeIcon = type === 'text' ? '' : '<span style="display:inline-flex; align-items:center; gap:3px; font-size:10px; color:var(--ws-ink-500); background:var(--ws-paper-100); padding:1px 6px; border-radius:10px; margin-left:6px;">' + type + '</span>';

            var card = document.createElement('div');
            card.className = 'ws-template-card';
            card.innerHTML =
                '<div style="display:flex; justify-content:space-between; align-items:flex-start; gap:10px;">' +
                    '<div style="flex:1; min-width:0;">' +
                        '<div style="display:flex; align-items:center; margin-bottom:4px;">' +
                            '<span style="font-weight:600; font-size:13px; color:var(--ws-ink-900);">' + name + '</span>' + typeIcon +
                        '</div>' +
                        '<div style="font-size:12px; color:var(--ws-ink-500); line-height:1.4; overflow:hidden; text-overflow:ellipsis; display:-webkit-box; -webkit-line-clamp:2; -webkit-box-orient:vertical;">' + (preview || '<i style="color:var(--ws-ink-400);">No text content</i>') + '</div>' +
                    '</div>' +
                    '<button class="ws-use-template-btn">Use</button>' +
                '</div>';

            card.querySelector('.ws-use-template-btn').addEventListener('click', function() {
                // A template with {{n}} placeholders needs its variables mapped
                // before it can go out — otherwise the braces reach the
                // customer. Same gate the web send screens apply.
                var meta = t.send_meta || null;
                var slots = meta && meta.body ? (meta.body.slots || 0) : countSlots(body);

                if (slots > 0) {
                    openTemplateMapping(t, body, name, slots);
                    return;
                }

                selectedTemplateId = t.id || null;
                templateOverrides = null;
                document.getElementById('ws-message-input').value = body;
                lockTemplate(name);
                switchToSendTab();
            });

            container.appendChild(card);
        });
    }

    /** Count `{{1}}`-style placeholders — fallback when send_meta is absent. */
    function countSlots(text) {
        var m = String(text || '').match(/\{\{\s*[\w_]+\s*\}\}/g);
        return m ? m.length : 0;
    }

    /**
     * Template variable mapping — the extension's equivalent of the web
     * <x-template-live-mapping> panel.
     *
     * Each {{n}} gets a dropdown of the workspace's attributes plus a "Fixed
     * text" escape hatch. The chosen mapping posts as `template_overrides`
     * and is resolved PER RECIPIENT server-side, so {{1}} becomes that
     * contact's own name rather than one value for everybody.
     */
    function openTemplateMapping(tpl, body, name, slots) {
        var modal = document.getElementById('ws-tplmap-modal');
        var rows = document.getElementById('ws-tplmap-rows');
        var preview = document.getElementById('ws-tplmap-preview');

        document.getElementById('ws-tplmap-name').innerText = name;
        rows.innerHTML = '';

        var meta = tpl.send_meta || null;
        // Template's own defaults (its variable_map), so a slot already mapped
        // on the web opens pre-selected here instead of blank.
        var defaults = (meta && meta.body && meta.body.tokens) ? meta.body.tokens : [];

        var picks = [];

        function repaintPreview() {
            var i = 0;
            preview.innerText = String(body).replace(/\{\{\s*[\w_]+\s*\}\}/g, function () {
                var p = picks[i]; i++;
                if (!p) return '…';
                return p.mode === 'fixed' ? (p.value || '…') : ('{{' + p.value + '}}');
            });
        }

        for (var s = 0; s < slots; s++) {
            (function (idx) {
                var row = document.createElement('div');
                row.style.cssText = 'margin-bottom:8px;';

                var lbl = document.createElement('div');
                lbl.style.cssText = 'font-size:11px; color:var(--ws-ink-500); margin-bottom:3px;';
                lbl.innerText = 'Variable {{' + (idx + 1) + '}}';
                row.appendChild(lbl);

                var sel = document.createElement('select');
                sel.className = 'ws-search-input';
                sel.style.cssText = 'width:100%; font-size:12px;';

                sendAttributes.forEach(function (a) {
                    var o = document.createElement('option');
                    o.value = a.key;
                    o.text = a.label + (a.group ? '  (' + a.group + ')' : '');
                    sel.appendChild(o);
                });
                var fixedOpt = document.createElement('option');
                fixedOpt.value = '__fixed__';
                fixedOpt.text = 'Fixed text…';
                sel.appendChild(fixedOpt);

                var fixed = document.createElement('input');
                fixed.type = 'text';
                fixed.className = 'ws-search-input';
                fixed.placeholder = 'Same text for every recipient';
                fixed.style.cssText = 'width:100%; font-size:12px; margin-top:4px; display:none;';

                // Pre-select the template's own mapping when it names a real
                // attribute. An imported template defaults its key to the slot
                // NUMBER ("1"), which maps to nothing — leave those unset so
                // the operator has to choose.
                var def = defaults[idx];
                var defKey = def && typeof def === 'object' ? (def.key || def.token || '') : (def || '');
                defKey = String(defKey || '').replace(/[{}]/g, '').trim();
                if (defKey && !/^\d+$/.test(defKey) && sendAttributes.some(function (a) { return a.key === defKey; })) {
                    sel.value = defKey;
                }

                picks[idx] = { mode: 'attr', value: sel.value || (sendAttributes[0] && sendAttributes[0].key) || 'name' };

                sel.addEventListener('change', function () {
                    if (sel.value === '__fixed__') {
                        fixed.style.display = 'block';
                        picks[idx] = { mode: 'fixed', value: fixed.value };
                    } else {
                        fixed.style.display = 'none';
                        picks[idx] = { mode: 'attr', value: sel.value };
                    }
                    repaintPreview();
                });
                fixed.addEventListener('input', function () {
                    picks[idx] = { mode: 'fixed', value: fixed.value };
                    repaintPreview();
                });

                row.appendChild(sel);
                row.appendChild(fixed);
                rows.appendChild(row);
            })(s);
        }

        repaintPreview();

        document.getElementById('ws-tplmap-apply').onclick = function () {
            // Shape matches TemplateOverrideResolver::sanitize() — {body: {slot: token}},
            // 1-indexed, exactly what the web panel posts.
            var bodyMap = {};
            picks.forEach(function (p, i) {
                if (!p) return;
                bodyMap[String(i + 1)] = p.mode === 'fixed' ? (p.value || '') : ('{{' + p.value + '}}');
            });

            selectedTemplateId = tpl.id || null;
            templateOverrides = JSON.stringify({ body: bodyMap });

            document.getElementById('ws-message-input').value = body;
            lockTemplate(name);
            modal.style.display = 'none';
            switchToSendTab();
        };

        modal.style.display = 'flex';
    }

    document.getElementById('ws-close-tplmap').addEventListener('click', function () {
        document.getElementById('ws-tplmap-modal').style.display = 'none';
    });

    async function fetchTemplates() {
        var container = document.getElementById('ws-templates-list');
        container.innerHTML = '<div style="text-align:center; padding:20px; color:var(--ws-ink-500);">Loading...</div>';
        document.getElementById('ws-templates-count').innerText = '';
        document.getElementById('ws-template-search').value = '';
        try {
            var res = await bgFetch(apiUrl('/api/get-templates'), { headers: getHeaders() }, 15000);
            if (!res.ok) throw new Error('HTTP ' + res.status);
            var data = JSON.parse(res.body);
            allTemplatesCache = data.templates || data.data || (Array.isArray(data) ? data : []);
            // Catalog for the mapping dropdowns. Served alongside the
            // templates so the picker always matches what the server can
            // actually resolve. Falls back to the workspace attributes the
            // `/` picker already loaded on older builds.
            if (Array.isArray(data.attributes) && data.attributes.length) {
                sendAttributes = data.attributes.map(function (a) {
                    return { key: a.key, label: a.label || a.key, group: a.group || '' };
                });
            } else if (!sendAttributes.length) {
                sendAttributes = userAttributes.map(function (a) {
                    return { key: a.key, label: a.name || a.key, group: 'Custom field' };
                });
            }
            renderTemplateCards(allTemplatesCache);
        } catch (e) { container.innerHTML = '<p style="color:var(--ws-coral); text-align:center;">Failed to load templates.</p>'; }
    }

    document.getElementById('ws-template-search').addEventListener('input', function() {
        var q = this.value.trim().toLowerCase();
        if (!q) { renderTemplateCards(allTemplatesCache); return; }
        var filtered = allTemplatesCache.filter(function(t) {
            var name = (t.template_name || t.name || '').toLowerCase();
            var body = (t.template_body || t.body || '').toLowerCase();
            return name.indexOf(q) !== -1 || body.indexOf(q) !== -1;
        });
        renderTemplateCards(filtered);
    });

    // ===================== PLAN CREDITS (header pill) =====================
    async function fetchPlanCredits() {
        try {
            var res = await bgFetch(apiUrl('/api/credits'), { headers: getHeaders() }, 15000);
            if (!res.ok) return;
            var data = JSON.parse(res.body);
            if (!data.success || !data.data) return;

            var d = data.data;
            var limit = d.monthly_messages_limit || 0;
            var used = d.delivered_count || 0;
            var remaining = Math.max(0, limit - used);

            var pill = document.getElementById('ws-header-credits');
            var num  = document.getElementById('ws-header-credit-num');
            if (!pill || !num) return;

            num.innerText = d.unlimited_access ? '\u221E' : remaining.toLocaleString();
            pill.title = d.unlimited_access
                ? 'Unlimited messages'
                : (remaining.toLocaleString() + ' of ' + limit.toLocaleString() + ' messages left' + (used ? ' (' + used.toLocaleString() + ' used)' : ''));
            pill.style.display = 'inline-flex';
        } catch (e) { /* silently fail \u2014 pill stays hidden */ }
    }

    // ===================== BOOT =====================
    checkSession();
}

if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', initSidebar); }
else { initSidebar(); }
