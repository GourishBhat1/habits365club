// teacher/service-worker.js

// Minimal no-op service worker:
self.addEventListener('install', (event) => {
    console.log('Teacher Service Worker installed (no offline caching).');
  });
  
  self.addEventListener('fetch', (event) => {
    // All requests pass through to the network
  });
  