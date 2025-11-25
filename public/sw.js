// public/sw.js

const CACHE_NAME = 'random-chat-v4';
const ASSETS = [
    './',
    './index.php',
    './login.php',
    './register.php',
    './chat.php',
    './assets/css/style.css',
    './assets/js/chat.js',
    './manifest.json'
];

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(CACHE_NAME)
            .then((cache) => cache.addAll(ASSETS))
            .then(() => self.skipWaiting())
    );
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys().then((cacheNames) => {
            return Promise.all(
                cacheNames.map((cacheName) => {
                    if (cacheName !== CACHE_NAME) {
                        console.log('Deleting old cache:', cacheName);
                        return caches.delete(cacheName);
                    }
                })
            );
        }).then(() => self.clients.claim())
    );
});

self.addEventListener('fetch', (event) => {
    // Ignore non-GET requests (POST, etc.)
    if (event.request.method !== 'GET') {
        return;
    }

    // Ignore unsupported schemes (like chrome-extension)
    if (!event.request.url.startsWith('http')) {
        return;
    }

    // For API requests, network only
    if (event.request.url.includes('/api/')) {
        return;
    }

    // For HTML pages and JS files, use Network First, fall back to cache
    if (event.request.url.endsWith('.php') ||
        event.request.url.endsWith('/') ||
        event.request.url.endsWith('.js')) {
        event.respondWith(
            fetch(event.request)
                .then((response) => {
                    // Update cache with new version
                    const responseClone = response.clone();
                    caches.open(CACHE_NAME).then((cache) => {
                        cache.put(event.request, responseClone);
                    });
                    return response;
                })
                .catch(() => {
                    return caches.match(event.request);
                })
        );
        return;
    }

    // For other assets (CSS, Images), use Cache First
    event.respondWith(
        caches.match(event.request)
            .then((response) => {
                return response || fetch(event.request);
            })
    );
});
