<?php
/**
 * Service worker with versioned shell cache (generated from APP_VERSION).
 */

header('Content-Type: application/javascript; charset=utf-8');
header('Service-Worker-Allowed: /challenge/');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

require_once __DIR__ . '/includes/functions.php';

$shellCache = shellCacheName();
$css = assetUrl('/challenge/assets/css/style.css');
$appJs = assetUrl('/challenge/assets/js/app.js');
$podcastPlayerJs = assetUrl('/challenge/assets/js/podcast-player.js');
$siteShellJs = assetUrl('/challenge/assets/js/site-shell.js');
$waterSceneJs = assetUrl('/challenge/assets/js/water-scene.js');
$jarSceneJs = assetUrl('/challenge/assets/js/jar-scene.js');
$jarPageJs = assetUrl('/challenge/assets/js/jar-page.js');
$kintoAvatarJs = assetUrl('/challenge/assets/js/kinto-avatar.js');
$manifest = assetUrl('/challenge/manifest.php');
$faviconIco = faviconUrl('kinto-favicon.ico');
$faviconSvg = faviconUrl('kinto-favicon.svg');
$favicon96 = faviconUrl('kinto-favicon-96.png');
$appleTouch = faviconUrl('kinto-apple-touch-icon.png');
$icon192 = faviconUrl('kinto-app-icon-192.png');
$icon512 = faviconUrl('kinto-app-icon-512.png');
$pushIcon = $icon192;

echo <<<JS
const SHELL_CACHE = '{$shellCache}';

self.addEventListener('install', (event) => {
    event.waitUntil((async () => {
        const cache = await caches.open(SHELL_CACHE);
        await cache.addAll([
            '{$manifest}',
            '{$css}',
            '{$appJs}',
            '{$podcastPlayerJs}',
            '{$siteShellJs}',
            '{$waterSceneJs}',
            '{$jarSceneJs}',
            '{$jarPageJs}',
            '{$kintoAvatarJs}',
            '{$faviconIco}',
            '{$faviconSvg}',
            '{$favicon96}',
            '{$appleTouch}',
            '{$icon192}',
            '{$icon512}'
        ]);
        await self.skipWaiting();
    })());
});

self.addEventListener('activate', (event) => {
    event.waitUntil((async () => {
        const keep = new Set([SHELL_CACHE]);
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
            url.pathname === '/challenge/manifest.php' ||
            url.pathname === '/challenge/manifest.json'
        )) {
            const cache = await caches.open(SHELL_CACHE);
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
        icon: data.icon || '{$pushIcon}',
        badge: data.badge || '{$pushIcon}',
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
        const windows = await clients.matchAll({ type: 'window', includeUncontrolled: true });
        for (const client of windows) {
            if (client.url === targetUrl && 'focus' in client) {
                return client.focus();
            }
        }
        if (clients.openWindow) {
            return clients.openWindow(targetUrl);
        }
    })());
});
JS;
