const CACHE_VERSION = '0.1.0-alpha-launch-core';
const STATIC_CACHE = `estudai-static-${CACHE_VERSION}`;

const STATIC_ASSETS = [
  './',
  './index.html',
  './app.html',
  './offline.html',
  './manifest.webmanifest',
  '../src/styles/design-system.css',
  '../src/services/ia.js',
  '../src/assets/brand/favicon.ico',
  '../src/assets/brand/estudai-mark-light.png',
  '../src/assets/brand/estudai-mark-dark.png',
  '../src/assets/brand/estudai-mark-light.svg',
  '../src/assets/brand/estudai-mark-dark.svg',
  '../src/assets/icons/estudai-icon.svg',
  '../src/config/api.js',
  '../src/services/http.js',
  '../src/services/pwa.js',
  '../src/pages/login.js',
  '../src/pages/app.js',
  '../src/styles/login.css',
  '../src/styles/app.css'
];

self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(STATIC_CACHE)
      .then((cache) => Promise.allSettled(STATIC_ASSETS.map((asset) => cache.add(asset))))
      .then(() => self.skipWaiting())
  );
});

self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys()
      .then((keys) => Promise.all(
        keys
          .filter((key) => key.startsWith('estudai-static-') && key !== STATIC_CACHE)
          .map((key) => caches.delete(key))
      ))
      .then(() => self.clients.claim())
  );
});

self.addEventListener('fetch', (event) => {
  const request = event.request;
  const url = new URL(request.url);

  if (request.method !== 'GET') return;
  if (url.pathname.includes('/server/api/')) return;

  if (request.mode === 'navigate') {
    event.respondWith(
      fetch(request)
        .then((response) => {
          if (!response || !response.ok) return response;
          const copy = response.clone();
          caches.open(STATIC_CACHE).then((cache) => cache.put(request, copy));
          return response;
        })
        .catch(() => caches.match(request).then((cached) => cached || caches.match('./offline.html')))
    );
    return;
  }

  event.respondWith(
    caches.match(request).then((cached) => {
      const fetchPromise = fetch(request)
        .then((response) => {
          if (response && response.ok) {
            const copy = response.clone();
            caches.open(STATIC_CACHE).then((cache) => cache.put(request, copy));
          }
          return response;
        })
        .catch(() => cached);

      return cached || fetchPromise;
    })
  );
});
