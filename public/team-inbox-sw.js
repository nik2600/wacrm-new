/*
 * Team-Inbox PWA service worker.
 *
 * Deliberately PUSH-ONLY — no fetch/asset caching. That avoids the #1 PWA
 * footgun (serving stale JS/CSS after an update); the inbox always loads fresh
 * from the network. Registered with scope '/team-inbox' from
 * resources/js/charts/user-team-inbox-index.js.
 *
 * Served from a STABLE url (public/, not Vite-hashed) because a service worker's
 * registration path must never change across builds.
 */

self.addEventListener('install', function () {
    // Activate this version immediately instead of waiting for old tabs to close.
    self.skipWaiting();
});

self.addEventListener('activate', function (event) {
    event.waitUntil(self.clients.claim());
});

// Web-Push → show a notification even when the app is fully closed.
self.addEventListener('push', function (event) {
    var data = {};
    try {
        data = event.data ? event.data.json() : {};
    } catch (e) {
        data = { body: (event.data && typeof event.data.text === 'function') ? event.data.text() : '' };
    }

    var title = data.title || 'New message';
    var options = {
        body: data.body || '',
        icon: data.icon || undefined,
        badge: data.badge || data.icon || undefined,
        tag: data.tag || 'team-inbox',
        renotify: true,
        data: { url: data.url || '/team-inbox' }
    };

    event.waitUntil(self.registration.showNotification(title, options));
});

// Tapping the notification focuses an open inbox tab (navigating it to the
// conversation) or opens a new one.
self.addEventListener('notificationclick', function (event) {
    event.notification.close();
    var url = (event.notification.data && event.notification.data.url) || '/team-inbox';

    event.waitUntil((async function () {
        var all = await self.clients.matchAll({ type: 'window', includeUncontrolled: true });
        for (var i = 0; i < all.length; i++) {
            var client = all[i];
            if (client.url.indexOf('/team-inbox') !== -1 && 'focus' in client) {
                try { await client.navigate(url); } catch (e) { /* cross-origin/nav guard */ }
                return client.focus();
            }
        }
        if (self.clients.openWindow) return self.clients.openWindow(url);
    })());
});
