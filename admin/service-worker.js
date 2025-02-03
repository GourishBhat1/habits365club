self.addEventListener('install', (event) => {
  console.log('✅ Admin Service Worker installed.');
  self.skipWaiting(); // Forces the SW to activate immediately.
});

self.addEventListener('activate', (event) => {
  console.log('✅ Admin Service Worker activated.');
  return self.clients.claim();
});

self.addEventListener('fetch', (event) => {
  // Ensures all network requests go directly to the server.
  event.respondWith(fetch(event.request));
});
