// ============================================================
//  SERVICE WORKER — SHF Loan Proposal PWA
// ============================================================
var STATIC_CACHE = 'shf-static-v18';
var DYNAMIC_CACHE = 'shf-dynamic-v18';

var STATIC_ASSETS = [
    '/',
    '/index.php',
    '/config.php',
    '/css/common.css',
    '/css/index.css',
    '/css/config.css',
    '/js/config-defaults.js',
    '/js/config-translations.js',
    '/js/config-loader.js',
    '/js/offline-manager.js',
    '/js/pdf-renderer.js',
    '/js/password-ui.js',
    '/images/logo3.png',
    '/images/background.png',
    '/fonts/NotoSansGujarati-Regular.ttf',
    '/fonts/NotoSansGujarati-Bold.ttf',
    '/favicon/android-chrome-192x192.png',
    '/favicon/android-chrome-512x512.png',
    '/favicon/apple-touch-icon.png',
    '/manifest.json'
];

// Install: pre-cache static assets
self.addEventListener('install', function(event) {
    event.waitUntil(
        caches.open(STATIC_CACHE).then(function(cache) {
            return cache.addAll(STATIC_ASSETS);
        }).then(function() {
            return self.skipWaiting();
        })
    );
});

// Activate: clean old caches
self.addEventListener('activate', function(event) {
    event.waitUntil(
        caches.keys().then(function(keys) {
            return Promise.all(
                keys.filter(function(key) {
                    return key !== STATIC_CACHE && key !== DYNAMIC_CACHE;
                }).map(function(key) {
                    return caches.delete(key);
                })
            );
        }).then(function() {
            return self.clients.claim();
        })
    );
});

// Fetch routing
self.addEventListener('fetch', function(event) {
    var url = new URL(event.request.url);

    // Skip non-GET requests (POST to generate-pdf, save-config, etc.)
    if (event.request.method !== 'GET') {
        return;
    }

    // Static assets: Cache-First
    if (isStaticAsset(url.pathname)) {
        event.respondWith(
            caches.match(event.request).then(function(cached) {
                return cached || fetch(event.request).then(function(response) {
                    var clone = response.clone();
                    caches.open(STATIC_CACHE).then(function(cache) {
                        cache.put(event.request, clone);
                    });
                    return response;
                });
            })
        );
        return;
    }

    // HTML pages + API GETs: Network-First with cache fallback
    event.respondWith(
        fetch(event.request).then(function(response) {
            var clone = response.clone();
            caches.open(DYNAMIC_CACHE).then(function(cache) {
                cache.put(event.request, clone);
            });
            return response;
        }).catch(function() {
            return caches.match(event.request);
        })
    );
});

function isStaticAsset(pathname) {
    var exts = ['.css', '.js', '.ttf', '.woff', '.woff2', '.png', '.jpg', '.jpeg', '.gif', '.svg', '.ico'];
    for (var i = 0; i < exts.length; i++) {
        if (pathname.endsWith(exts[i])) return true;
    }
    if (pathname === '/manifest.json') return true;
    return false;
}
