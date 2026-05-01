const CACHE_NAME = 'assettrack-v1';
const ASSETS_TO_CACHE = [
    '/assettrack/manifest.json',
    '/assettrack/assets/css/style.css',
    '/assettrack/assets/js/app.js',
    '/assettrack/index.php',
    '/assettrack/assets/img/icon-192.png',
    '/assettrack/assets/img/icon-512.png'
];

self.addEventListener('install', event => {
    event.waitUntil(
        caches.open(CACHE_NAME)
            .then(cache => cache.addAll(ASSETS_TO_CACHE))
            .then(() => self.skipWaiting())
    );
});

self.addEventListener('activate', event => {
    event.waitUntil(
        caches.keys().then(keys => Promise.all(
            keys.filter(key => key !== CACHE_NAME)
                .map(key => caches.delete(key))
        )).then(() => self.clients.claim())
    );
});

self.addEventListener('fetch', event => {
    event.respondWith(
        fetch(event.request)
            .then(response => {
                if (response && response.status === 200 && response.type === 'basic') {
                    const responseClone = response.clone();
                    caches.open(CACHE_NAME).then(cache => cache.put(event.request, responseClone));
                }
                return response;
            })
            .catch(() => caches.match(event.request).then(cachedResponse => {
                if (cachedResponse) {
                    return cachedResponse;
                }
                if (event.request.mode === 'navigate') {
                    return new Response('<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Offline</title></head><body><h1>Offline</h1><p>U bent momenteel offline. Probeer het later opnieuw.</p></body></html>', {
                        headers: { 'Content-Type': 'text/html' }
                    });
                }
                return new Response('Offline', { status: 503, statusText: 'Offline' });
            }))
    );
});
