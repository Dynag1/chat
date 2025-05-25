// Mise en cache des fichiers statiques
self.addEventListener('install', event => {
  self.skipWaiting();
  event.waitUntil(
    caches.open('static-v1').then(cache => {
      return cache.addAll([
        '/manifest.webmanifest',
        '/icon-192.png',
        '/icon-512.png',
        // Ajoute ici seulement les fichiers statiques
      ]);
    })
  );
});

self.addEventListener('fetch', event => {
  // Ne met en cache que les fichiers statiques
  if (
    event.request.url.endsWith('.png') ||
    event.request.url.endsWith('.webmanifest') ||
    event.request.url.endsWith('.css') ||
    event.request.url.endsWith('.js')
  ) {
    event.respondWith(
      caches.match(event.request, {ignoreSearch: true}).then(response => {
        return response || fetch(event.request);
      })
    );
  }
});

self.addEventListener('push', function(event) {
  let data = {};
  if (event.data) {
    try {
      data = event.data.json();
    } catch (e) {
      data = { title: 'Notification', body: event.data.text() };
    }
  }
  const title = data.title || 'Chat Pirate';
  const options = {
    body: data.body || 'Nouveau message ou événement sur Chat Pirate',
    icon: '/icon-192.png',
    badge: '/icon-192.png',
    data: data.url ? { url: data.url } : {},
    vibrate: [100, 50, 100]
  };
  event.waitUntil(
    self.registration.showNotification(title, options)
  );
});

// Notifications locales (envoyées depuis le front via postMessage)
self.addEventListener('message', (event) => {
  const notification = event.data;
  if (notification && notification.title) {
    self.registration.showNotification(
      notification.title,
      notification.options || {}
    );
  }
});

// Ouvrir la bonne page si l'utilisateur clique sur la notification
self.addEventListener('notificationclick', function(event) {
  event.notification.close();
  const url = event.notification.data && event.notification.data.url
    ? event.notification.data.url
    : '/';
  event.waitUntil(
    clients.matchAll({ type: 'window', includeUncontrolled: true }).then(windowClients => {
      // Si une fenêtre est déjà ouverte, focus dessus
      for (let client of windowClients) {
        if (client.url === url && 'focus' in client) {
          return client.focus();
        }
      }
      // Sinon, ouvre une nouvelle fenêtre
      if (clients.openWindow) {
        return clients.openWindow(url);
      }
    })
  );
});
