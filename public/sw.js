// ponytail: minimal SW — cache-first for hashed build assets, network-first for pages with offline fallback.
// Add push notifications / background sync here when needed.
const CACHE = 'sparingin-v3';
const OFFLINE = '/offline.html';

self.addEventListener('install', (e) => {
    e.waitUntil(caches.open(CACHE).then((c) => c.addAll([OFFLINE, '/images/logo-wordmark.png'])));
    self.skipWaiting();
});

self.addEventListener('activate', (e) => {
    e.waitUntil(caches.keys().then((keys) => Promise.all(keys.filter((k) => k !== CACHE).map((k) => caches.delete(k)))));
    self.clients.claim();
});

self.addEventListener('fetch', (e) => {
    const { request } = e;
    if (request.method !== 'GET') return;
    const url = new URL(request.url);
    if (url.origin !== location.origin) return;

    if (url.pathname.startsWith('/build/')) {
        e.respondWith(caches.match(request).then((hit) => hit || fetch(request).then((res) => {
            const copy = res.clone();
            caches.open(CACHE).then((c) => c.put(request, copy));
            return res;
        })));
        return;
    }

    if (request.mode === 'navigate') {
        e.respondWith(fetch(request).catch(() => caches.match(OFFLINE)));
    }
});
