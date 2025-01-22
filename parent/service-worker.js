// parent/service-worker.js

// Minimal no-op service worker:
self.addEventListener('install', (event) => {
    console.log('Parent Service Worker installed (no offline caching).');
  });
  
  self.addEventListener('fetch', (event) => {
    // Forward all requests to the network.
  });
  