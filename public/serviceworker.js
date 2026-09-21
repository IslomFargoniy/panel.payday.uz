const staticCacheName = "pwa-v" + new Date().getTime();
const filesToCache = [
    '/favicon.ico',
    '/logo.svg',
    '/images/icons/icon-72x72.png',
    '/images/icons/icon-96x96.png',
    '/images/icons/icon-128x128.png',
    '/images/icons/icon-144x144.png',
    '/images/icons/icon-152x152.png',
    '/images/icons/icon-192x192.png',
    '/images/icons/icon-384x384.png',
    '/images/icons/icon-512x512.png',
    '/images/icons/big-payday.png',
    '/images/icons/big-payday-nobg.png',
];

// Cache on install safely without throwing Uncaught TypeError on missing files
self.addEventListener("install", event => {
    self.skipWaiting();
    event.waitUntil(
        caches.open(staticCacheName).then(cache => {
            return Promise.all(
                filesToCache.map(url => {
                    return cache.add(url).catch(err => {
                        console.warn("Failed to cache asset:", url, err);
                    });
                })
            );
        })
    );
});

// Clear old caches on activate
self.addEventListener('activate', event => {
    event.waitUntil(
        caches.keys().then(cacheNames => {
            return Promise.all(
                cacheNames
                    .filter(cacheName => cacheName.startsWith("pwa-") && cacheName !== staticCacheName)
                    .map(cacheName => caches.delete(cacheName))
            );
        }).then(() => self.clients.claim())
    );
});

// Serve with Network-First strategy (fallback to cache for static assets)
self.addEventListener("fetch", event => {
    if (event.request.method !== 'GET') {
        return;
    }

    const url = new URL(event.request.url);
    if (!url.protocol.startsWith('http')) {
        return;
    }

    event.respondWith(
        fetch(event.request)
            .then(response => {
                if (response && response.status === 200 && response.type === 'basic') {
                    if (url.pathname.startsWith('/build/') || url.pathname.startsWith('/images/')) {
                        const responseToCache = response.clone();
                        caches.open(staticCacheName).then(cache => {
                            cache.put(event.request, responseToCache);
                        });
                    }
                }
                return response;
            })
            .catch(() => {
                return caches.match(event.request);
            })
    );
});