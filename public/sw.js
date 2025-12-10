// public/sw.js

const CACHE_NAME = 'random-chat-v7';
const STATIC_ASSETS = [
    './assets/css/style.css',
    './assets/js/chat.js',
    './assets/js/pwa.js',
    './assets/icons/icon-192x192.png',
    './assets/icons/icon-512x512.png',
    './manifest.json',
    './favicon.png',
    './favicon.ico'
];

// Install: cache static assets only
self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(CACHE_NAME)
            .then((cache) => {
                console.log('Caching static assets');
                return cache.addAll(STATIC_ASSETS);
            })
            .then(() => self.skipWaiting())
            .catch((err) => {
                console.error('Failed to cache:', err);
            })
    );
});

// Activate: clean old caches
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
        }).then(() => {
            console.log('Service Worker activated');
            return self.clients.claim();
        })
    );
});

// Fetch: Network first for PHP, Cache first for static assets
self.addEventListener('fetch', (event) => {
    const url = new URL(event.request.url);
    
    // Ignore non-GET requests
    if (event.request.method !== 'GET') {
        return;
    }

    // Ignore unsupported schemes
    if (!url.protocol.startsWith('http')) {
        return;
    }

    // API requests: Network only, no caching
    if (url.pathname.includes('/api/')) {
        event.respondWith(fetch(event.request));
        return;
    }

    // PHP files and navigation: Network first (NEVER serve PHP from cache as primary)
    if (url.pathname.endsWith('.php') || 
        url.pathname.endsWith('/') || 
        event.request.mode === 'navigate') {
        event.respondWith(
            fetch(event.request)
                .then((response) => {
                    return response;
                })
                .catch(() => {
                    // Only on network failure, show offline page or cached version
                    return caches.match(event.request).then((cached) => {
                        if (cached) {
                            return cached;
                        }
                        // Return a basic offline response
                        return new Response(
                            '<!DOCTYPE html><html><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Hors ligne</title><style>body{font-family:sans-serif;display:flex;justify-content:center;align-items:center;height:100vh;margin:0;background:#dfe6e9;text-align:center;}.offline{padding:40px;background:white;border-radius:12px;box-shadow:0 4px 15px rgba(0,0,0,0.1);}.offline h1{color:#6c5ce7;}.offline button{margin-top:20px;padding:12px 24px;background:#6c5ce7;color:white;border:none;border-radius:6px;cursor:pointer;}</style></head><body><div class="offline"><h1>📵 Hors ligne</h1><p>Vérifiez votre connexion internet.</p><button onclick="location.reload()">Réessayer</button></div></body></html>',
                            {
                                status: 503,
                                statusText: 'Service Unavailable',
                                headers: new Headers({
                                    'Content-Type': 'text/html; charset=utf-8'
                                })
                            }
                        );
                    });
                })
        );
        return;
    }

    // Static assets (CSS, JS, images): Cache first, then network
    event.respondWith(
        caches.match(event.request)
            .then((cachedResponse) => {
                if (cachedResponse) {
                    // Return cached version, but also update cache in background
                    fetch(event.request).then((networkResponse) => {
                        if (networkResponse && networkResponse.status === 200) {
                            caches.open(CACHE_NAME).then((cache) => {
                                cache.put(event.request, networkResponse);
                            });
                        }
                    }).catch(() => {});
                    return cachedResponse;
                }
                
                // Not in cache, fetch from network and cache it
                return fetch(event.request).then((response) => {
                    if (!response || response.status !== 200) {
                        return response;
                    }
                    const responseToCache = response.clone();
                    caches.open(CACHE_NAME).then((cache) => {
                        cache.put(event.request, responseToCache);
                    });
                    return response;
                });
            })
    );
});

// Handle messages from clients
self.addEventListener('message', (event) => {
    if (event.data && event.data.type === 'SKIP_WAITING') {
        self.skipWaiting();
    }
});
