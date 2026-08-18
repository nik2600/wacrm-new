// Laravel Echo + Pusher bootstrap. Reads the PUBLIC key + cluster from the
// meta tags the user layout prints (never the secret). If real-time isn't
// enabled by the admin, the meta is blank → we skip init entirely and every
// page keeps its polling behaviour. Sets window.Echo for feature code
// (the Team Inbox) to subscribe when present.
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

const meta = (n) => document.querySelector(`meta[name="${n}"]`)?.content || '';

const key = meta('pusher-key');
const cluster = meta('pusher-cluster');

if (key) {
    window.Pusher = Pusher;
    try {
        window.Echo = new Echo({
            broadcaster: 'pusher',
            key,
            cluster: cluster || 'mt1',
            forceTLS: true,
            // Private channels are authorized by Laravel's /broadcasting/auth
            // (web+auth). Send the CSRF token so the POST passes.
            auth: {
                headers: {
                    'X-CSRF-TOKEN': meta('csrf-token'),
                    'X-Requested-With': 'XMLHttpRequest',
                },
            },
        });
        window.__realtime = { enabled: true, wsId: parseInt(meta('ws-id') || '0', 10) || 0 };
        console.debug('[realtime] Echo ready (pusher)');
    } catch (e) {
        console.warn('[realtime] Echo init failed:', e?.message);
        window.__realtime = { enabled: false };
    }
} else {
    window.__realtime = { enabled: false };
}
