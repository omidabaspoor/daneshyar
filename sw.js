const CACHE_NAME = 'daneshyar-v3-exam';
const OFFLINE_URL = '/offline.html';

const PRECACHE_URLS = [
    '/',
    '/offline.html',
    '/manifest.json',
    '/assets/css/style.css?v=11',
    '/assets/css/chat.css?v=25',
    '/assets/js/chat.js?v=26',
    '/assets/img/logo.png',
    '/assets/img/icon-192.png',
    '/assets/img/icon-512.png',
    '/assets/img/apple-touch-icon.png',
    '/assets/vendor/fonts/Vazirmatn-Regular.woff2',
    '/assets/vendor/fonts/Vazirmatn-Medium.woff2',
    '/assets/vendor/fonts/Vazirmatn-Bold.woff2',
    '/assets/vendor/fonts/vazirmatn.css'
];

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(CACHE_NAME)
            .then((cache) => cache.addAll(PRECACHE_URLS))
            .then(() => self.skipWaiting())
    );
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys().then((cacheNames) => Promise.all(
            cacheNames.filter((cacheName) => cacheName !== CACHE_NAME).map((cacheName) => caches.delete(cacheName))
        )).then(() => self.clients.claim())
    );
});

self.addEventListener('fetch', (event) => {
    if (event.request.method !== 'GET') return;

    const url = new URL(event.request.url);

    if (url.pathname.startsWith('/api/')) return;
    if (url.pathname.startsWith('/uploads/')) return;

    const isStatic = /\.(css|js|woff2|png|jpg|jpeg|gif|svg|ico)$/.test(url.pathname);

    event.respondWith(
        caches.match(event.request, { ignoreSearch: isStatic }).then((cachedResponse) => {
            if (cachedResponse) {
                if (isStatic) {
                    fetch(event.request).then((response) => {
                        if (response && response.status === 200) {
                            caches.open(CACHE_NAME).then((cache) => cache.put(event.request, response.clone()));
                        }
                    }).catch(() => {});
                }
                return cachedResponse;
            }

            return fetch(event.request)
                .then((response) => {
                    if (!response || response.status !== 200) return response;
                    if (isStatic || event.request.headers.get('accept')?.includes('text/html')) {
                        const responseClone = response.clone();
                        caches.open(CACHE_NAME).then((cache) => cache.put(event.request, responseClone));
                    }
                    return response;
                })
                .catch(() => {
                    if (event.request.headers.get('accept')?.includes('text/html')) {
                        return caches.match(OFFLINE_URL);
                    }
                });
        })
    );
});
