document.getElementById('open-sidebar').addEventListener('click', () => {
    chrome.tabs.query({active: true, currentWindow: true}, (tabs) => {
        chrome.scripting.executeScript({
            target: {tabId: tabs[0].id},
            function: toggleSidebar
        });
    });
});

function toggleSidebar() {
    const sidebar = document.getElementById('wadesk-sidebar');
    if (sidebar) {
        sidebar.classList.toggle('open');
    } else {
        alert('Please refresh WhatsApp Web to load the extension.');
    }
}

/* ── Panel ON/OFF toggle ───────────────────────────────────────────
   Persists `wadesk_panel_enabled` and tells the active tab's content
   script to show/hide the floating launcher + side panel live. When OFF,
   the "Open panel" button is disabled (nothing to open). Default ON. */
(function () {
    const panelToggle = document.getElementById('panel-toggle');
    const openBtn = document.getElementById('open-sidebar');
    const statusEl = document.querySelector('.p-status');

    function applyState(enabled) {
        if (panelToggle) panelToggle.checked = enabled;
        if (openBtn) openBtn.disabled = !enabled;
        if (statusEl) statusEl.style.opacity = enabled ? '' : '0.5';
    }

    chrome.storage.local.get(['wadesk_panel_enabled'], (res) => {
        applyState(res.wadesk_panel_enabled !== false); // default ON
    });

    if (panelToggle) {
        panelToggle.addEventListener('change', () => {
            const enabled = panelToggle.checked;
            chrome.storage.local.set({ wadesk_panel_enabled: enabled });
            applyState(enabled);
            chrome.tabs.query({active: true, currentWindow: true}, (tabs) => {
                if (tabs[0] && tabs[0].id) {
                    chrome.tabs.sendMessage(tabs[0].id, { action: 'wadesk-set-panel', enabled }, () => {
                        void chrome.runtime.lastError; // ignore "no receiver" off WhatsApp Web
                    });
                }
            });
        });
    }
})();
