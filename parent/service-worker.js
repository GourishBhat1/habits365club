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
    icon: '/assets/images/habits_logo.png'
  });
});
