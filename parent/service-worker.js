self.addEventListener('install', (event) => {
  console.log('✅ Parent Service Worker installed.');
  self.skipWaiting(); // Forces the SW to activate immediately
});

self.addEventListener('activate', (event) => {
  console.log('✅ Parent Service Worker activated.');
  return self.clients.claim();
});

self.addEventListener('fetch', (event) => {
  // Ensures all network requests go directly to the server
  event.respondWith(fetch(event.request));
});

self.addEventListener('push', function(event) {
  const data = event.data.json();
  self.registration.showNotification(data.title, {
    body: data.body,
    icon: '/assets/images/habits_logo.png',
    data: {
      url: 'dashboard.php'
    }
  });
});

self.addEventListener('notificationclick', function(event) {
  event.notification.close();
  const urlToOpen = event.notification.data && event.notification.data.url ? event.notification.data.url : 'dashboard.php';
  event.waitUntil(
    clients.matchAll({ type: 'window', includeUncontrolled: true }).then(function(clientList) {
      for (const client of clientList) {
        if (client.url.includes(urlToOpen) && 'focus' in client) {
          return client.focus();
        }
      }
      if (clients.openWindow) {
        return clients.openWindow(urlToOpen);
      }
    })
  );
});
