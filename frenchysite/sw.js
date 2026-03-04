/**
 * Service Worker — Cache les guides pour un accès hors-ligne.
 * Strategy: Network first, fallback to cache.
 */
var CACHE_NAME = 'vf-guides-v1';
var GUIDE_PATTERN = /(guide|wifi|piscine|sauna|sport|cinema|cuisine)\.php/;
var STATIC_ASSETS = [
    '/assets/css/guide.css',
    '/assets/img/favicon.svg'
];

// Install: pre-cache static assets
self.addEventListener('install', function (event) {
    event.waitUntil(
        caches.open(CACHE_NAME).then(function (cache) {
            return cache.addAll(STATIC_ASSETS);
        })
    );
    self.skipWaiting();
});

// Activate: clean old caches
self.addEventListener('activate', function (event) {
    event.waitUntil(
        caches.keys().then(function (names) {
            return Promise.all(
                names.filter(function (n) { return n !== CACHE_NAME; })
                     .map(function (n) { return caches.delete(n); })
            );
        })
    );
    self.clients.claim();
});

// Fetch: network-first for guide pages, cache-first for static assets
self.addEventListener('fetch', function (event) {
    var url = new URL(event.request.url);

    // Only handle same-origin GET requests
    if (event.request.method !== 'GET' || url.origin !== self.location.origin) return;

    var isGuide = GUIDE_PATTERN.test(url.pathname);
    var isStatic = url.pathname.startsWith('/assets/');

    if (!isGuide && !isStatic) return;

    if (isStatic) {
        // Cache-first for static assets
        event.respondWith(
            caches.match(event.request).then(function (cached) {
                return cached || fetch(event.request).then(function (response) {
                    var clone = response.clone();
                    caches.open(CACHE_NAME).then(function (cache) {
                        cache.put(event.request, clone);
                    });
                    return response;
                });
            })
        );
    } else {
        // Network-first for guide pages (content may change)
        event.respondWith(
            fetch(event.request).then(function (response) {
                var clone = response.clone();
                caches.open(CACHE_NAME).then(function (cache) {
                    cache.put(event.request, clone);
                });
                return response;
            }).catch(function () {
                return caches.match(event.request);
            })
        );
    }
});
