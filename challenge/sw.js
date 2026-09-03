self.addEventListener('install', (event) => {
    event.waitUntil((async () => {
        const cache = await caches.open('challenge-shell-86-7');
        await cache.addAll([
            '/challenge/manifest.php?v=86.7',
            '/challenge/assets/css/style.css?v=86.7',
            '/challenge/assets/js/app.js?v=86.7',
            '/challenge/assets/js/podcast-player.js?v=86.7',
            '/challenge/assets/js/site-shell.js?v=86.7',
            '/challenge/assets/js/water-scene.js?v=86.7',
            '/challenge/assets/kinto-favicon/kinto-favicon.ico?v=86.7',
            '/challenge/assets/kinto-favicon/kinto-favicon.svg?v=86.7',
            '/challenge/assets/kinto-favicon/kinto-favicon-96.png?v=86.7',
            '/challenge/assets/kinto-favicon/kinto-apple-touch-icon.png?v=86.7',
            '/challenge/assets/kinto-favicon/kinto-app-icon-192.png?v=86.7',
            '/challenge/assets/kinto-favicon/kinto-app-icon-512.png?v=86.7'
        ]);
        await self.skipWaiting();
    })());
});

self.addEventListener('activate', (event) => {
    event.waitUntil((async () => {
        const keep = new Set(['challenge-shell-86-7']);
        const keys = await caches.keys();
        await Promise.all(keys.map((key) => keep.has(key) ? null : caches.delete(key)));
        await self.clients.claim();
    })());
});

self.addEventListener('fetch', (event) => {
    const request = event.request;
    const url = new URL(request.url);

    if (request.method !== 'GET' || url.origin !== self.location.origin) {
        return;
    }

    if (url.pathname.startsWith('/challenge/api/')) {
        return;
    }

    if (request.mode === 'navigate') {
        event.respondWith(fetch(request).catch(async () => {
            const cachedDashboard = await caches.match('/challenge/app/dashboard.php');
            if (cachedDashboard) return cachedDashboard;

            return new Response(
                '<!doctype html><title>Kinto Offline</title><meta name="viewport" content="width=device-width,initial-scale=1"><body style="font-family:system-ui,sans-serif;padding:2rem;line-height:1.5"><h1>You are offline</h1><p>Reconnect to open Kinto and sync your latest activity.</p></body>',
                { headers: { 'Content-Type': 'text/html; charset=utf-8' } }
            );
        }));
        return;
    }

    event.respondWith((async () => {
        const cached = await caches.match(request);
        if (cached) return cached;

        const response = await fetch(request);
        if (response.ok && (
            url.pathname.startsWith('/challenge/assets/') ||
            url.pathname === '/challenge/manifest.php'
        )) {
            const cache = await caches.open('challenge-shell-86-7');
            cache.put(request, response.clone());
        }
        return response;
    })());
});

self.addEventListener('push', (event) => {
    let data = {};
    if (event.data) {
        try {
            data = event.data.json();
        } catch (error) {
            data = { body: event.data.text() };
        }
    }

    const title = data.title || 'Kinto';
    const options = {
        body: data.body || 'You have a new update.',
        icon: data.icon || '/challenge/assets/kinto-favicon/kinto-app-icon-192.png?v=86.0',
        badge: data.badge || '/challenge/assets/kinto-favicon/kinto-app-icon-192.png?v=86.0',
        data: {
            url: data.url || '/challenge/app/dashboard.php'
        }
    };

    event.waitUntil(self.registration.showNotification(title, options));
});

self.addEventListener('notificationclick', (event) => {
    event.notification.close();
    const targetUrl = new URL(event.notification.data?.url || '/challenge/app/dashboard.php', self.location.origin).href;

    event.waitUntil((async () => {
        const allClients = await clients.matchAll({ type: 'window', includeUncontrolled: true });
        for (const client of allClients) {
            if ('focus' in client) {
                await client.focus();
                if ('navigate' in client) {
                    await client.navigate(targetUrl);
                }
                return;
            }
        }
        await clients.openWindow(targetUrl);
    })());
});
