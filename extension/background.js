// Background Service Worker - WaDesk
chrome.runtime.onInstalled.addListener(() => {
    console.log('[WaDesk] Extension Installed');
});

// Proxy fetch requests from content script to avoid mixed content (HTTPS -> HTTP) blocking
chrome.runtime.onMessage.addListener((message, sender, sendResponse) => {
    if (message.type === 'WADESK_FETCH') {
        var opts = {
            method: message.method || 'GET',
            headers: message.headers || {}
        };

        // Handle body - for FormData we receive serialized fields
        if (message.formData) {
            var form = new FormData();
            for (var key in message.formData) {
                if (message.formData[key] && message.formData[key].__isFile) {
                    // Convert base64 back to Blob for file uploads
                    var fileInfo = message.formData[key];
                    var byteString = atob(fileInfo.data);
                    var ab = new ArrayBuffer(byteString.length);
                    var ia = new Uint8Array(ab);
                    for (var i = 0; i < byteString.length; i++) { ia[i] = byteString.charCodeAt(i); }
                    var blob = new Blob([ab], { type: fileInfo.type });
                    form.append(key, blob, fileInfo.name);
                } else {
                    form.append(key, message.formData[key]);
                }
            }
            opts.body = form;
            // Remove Content-Type so browser sets multipart boundary automatically
            delete opts.headers['Content-Type'];
        } else if (message.body) {
            opts.body = message.body;
        }

        fetch(message.url, opts)
            .then(async function(response) {
                var contentType = response.headers.get('content-type') || '';
                if (contentType.includes('application/json') || contentType.includes('text/')) {
                    var text = await response.text();
                    sendResponse({ ok: response.ok, status: response.status, body: text, contentType: contentType });
                } else {
                    // Binary response (for CSV download etc) - send as base64
                    var blob = await response.blob();
                    var reader = new FileReader();
                    reader.onloadend = function() {
                        sendResponse({ ok: response.ok, status: response.status, body: reader.result, contentType: contentType, isBinary: true });
                    };
                    reader.readAsDataURL(blob);
                }
            })
            .catch(function(err) {
                sendResponse({ ok: false, status: 0, body: JSON.stringify({ message: err.message }), error: true });
            });

        return true; // Keep message channel open for async sendResponse
    }
});
