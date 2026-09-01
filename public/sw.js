const CACHE_NAME = 'creditsoft-shell-v20260505-11';
const OFFLINE_URL = '/offline.html';
const PRECACHE_URLS = [
    OFFLINE_URL,
    '/favicon.svg?v=2',
    '/apple-touch-icon.png?v=2',
    '/pwa-192.png',
    '/pwa-512.png',
    '/manifest.webmanifest?v=3',
];

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(CACHE_NAME).then((cache) => cache.addAll(PRECACHE_URLS)),
    );
    self.skipWaiting();
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys().then((keys) =>
            Promise.all(
                keys
                    .filter((key) => key !== CACHE_NAME)
                    .map((key) => caches.delete(key)),
            ),
        ),
    );
    self.clients.claim();
});

self.addEventListener('fetch', (event) => {
    if (event.request.method !== 'GET') {
        return;
    }

    const requestUrl = new URL(event.request.url);
    const isPrecachedAsset = PRECACHE_URLS.some((url) => {
        const cachedUrl = new URL(url, self.location.origin);

        return cachedUrl.pathname === requestUrl.pathname;
    });

    if (event.request.mode === 'navigate') {
        event.respondWith(
            fetch(event.request, { cache: 'no-store' }).catch(async () => {
                const cache = await caches.open(CACHE_NAME);
                const offline = await cache.match(OFFLINE_URL);

                return offline || Response.error();
            }),
        );

        return;
    }

    if (requestUrl.origin === self.location.origin && isPrecachedAsset) {
        event.respondWith(
            caches.match(event.request).then((cached) => {
                if (cached) {
                    return cached;
                }

                return fetch(event.request).then((response) => {
                    if (!response.ok || response.type !== 'basic') {
                        return response;
                    }

                    const responseClone = response.clone();

                    caches.open(CACHE_NAME).then((cache) => {
                        cache.put(event.request, responseClone);
                    });

                    return response;
                });
            }),
        );
    }
});
