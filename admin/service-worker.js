// admin/service-worker.js

// A minimal no-op service worker:
self.addEventListener('install', (event) => {
    console.log('Admin Service Worker installed (no offline caching).');
  });
  
  self.addEventListener('fetch', (event) => {
    // Do nothing; all requests go directly to the network.
  });
  