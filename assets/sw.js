// BASE afleiden uit de eigen locatie van dit script (in plaats van hardcoded
// vanaf de domeinroot) -- zo werkt de precache ook correct als AssetTrack in
// een submap draait (bijv. https://domein.nl/assettrack/), niet alleen op de
// domeinroot. Dit script staat altijd op "<BASE>/assets/sw.js".
const BASE = self.location.pathname.replace(/\/assets\/sw\.js$/, '');

const CACHE_NAME = 'assettrack-v2';
const ASSETS_TO_CACHE = [
    BASE + '/manifest.json',
    BASE + '/assets/css/style.css',
    BASE + '/assets/js/app.js',
    BASE + '/index.php',
    BASE + '/assets/img/icon-192.png',
    BASE + '/assets/img/icon-512.png',
    BASE + '/assets/img/icon-192-maskable.png',
    BASE + '/assets/img/icon-512-maskable.png',
    BASE + '/assets/img/favicon-16x16.png',
    BASE + '/assets/img/favicon-32x32.png',
    BASE + '/assets/img/apple-touch-icon.png',
    BASE + '/favicon.ico'
];

self.addEventListener('install', event => {
    event.waitUntil(
        caches.open(CACHE_NAME)
            .then(cache => cache.addAll(ASSETS_TO_CACHE).catch(() => {}))
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
                if (cachedResponse) return cachedResponse;
                if (event.request.mode === 'navigate') {
                    return new Response(
                        '<!DOCTYPE html><html><head><meta charset="UTF-8">' +
                        '<meta name="viewport" content="width=device-width,initial-scale=1">' +
                        '<title>Offline — AssetTrack</title>' +
                        '<style>body{font-family:sans-serif;text-align:center;padding:60px 20px;background:#f0f4f8;}' +
                        'h1{color:#1a2332;}p{color:#64748b;}</style></head>' +
                        '<body><h1>📦 AssetTrack</h1>' +
                        '<p>Geen internetverbinding.<br>Probeer het opnieuw als je online bent.</p>' +
                        '</body></html>',
                        { headers: { 'Content-Type': 'text/html' } }
                    );
                }
                return new Response('Offline', { status: 503 });
            }))
    );
});
