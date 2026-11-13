const CACHE_VERSION = '0.1.0-alpha-recovery-09';
const APP_ROOT = new URL('../', self.location.href);
const CACHE_PREFIX = `estudai-static-${encodeURIComponent(APP_ROOT.pathname)}-`;
const STATIC_CACHE = `${CACHE_PREFIX}${CACHE_VERSION}`;

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
      .catch(() => {}) // Cache failure must not make the network-only shell unusable.
      .then(() => self.skipWaiting())
  );
});

self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys()
      .then((keys) => Promise.all(
        keys
          .filter((key) => key.startsWith(CACHE_PREFIX) && key !== STATIC_CACHE)
          .map((key) => caches.delete(key))
      ))
      .catch(() => {})
      .then(() => self.clients.claim())
  );
});

function cachedResponse(request, ignoreSearch = false) {
  // Legacy caches are read only for this app's URLs, preserving warmed assets.
  // Do not delete the old generic caches: another project may share their name.
  return caches.match(request, { cacheName: STATIC_CACHE, ignoreSearch })
    .then((cached) => cached || caches.match(request, { ignoreSearch }))
    .then((cached) => cached instanceof Response ? cached : undefined)
    .catch(() => undefined);
}

function rememberResponse(request, response) {
  let copy;
  try { copy = response.clone(); }
  catch (_) { return Promise.resolve(); }
  return Promise.resolve()
    .then(() => caches.open(STATIC_CACHE))
    .then((cache) => cache.put(request, copy))
    .catch(() => {}); // Quota/storage errors cannot reject an otherwise valid fetch.
}

function unavailableResponse(navigation) {
  return new Response(navigation
    ? '<!doctype html><html lang="pt-BR"><meta charset="utf-8"><title>EstudAI offline</title><h1>Voce esta offline</h1><p>Os dados e as funcionalidades do backend precisam de conexao. Tente novamente quando a rede voltar.</p></html>'
    : 'Recurso do EstudAI indisponivel offline.', {
    status: 503,
    statusText: 'Service Unavailable',
    headers: { 'Content-Type': navigation ? 'text/html; charset=utf-8' : 'text/plain; charset=utf-8' }
  });
}

self.addEventListener('fetch', (event) => {
  const request = event.request;
  const url = new URL(request.url);

  if (request.method !== 'GET') return;
  if (url.pathname.includes('/server/api/')) return;
  if (url.origin !== APP_ROOT.origin || !url.pathname.startsWith(APP_ROOT.pathname)) return;

  const navigation = request.mode === 'navigate';
  const cacheKey = new URL(url);
  if (navigation) cacheKey.search = ''; // Static HTML is independent of tracking parameters.
  const network = Promise.resolve().then(() => fetch(request))
    .then((response) => {
      if (!(response instanceof Response)) throw new TypeError('Invalid network response');
      if (response.ok) event.waitUntil(rememberResponse(navigation ? cacheKey.href : request, response));
      return response;
    })
    .catch(() => undefined);
  event.waitUntil(network.then(() => {}));

  if (navigation) {
    event.respondWith(
      network.then(async (response) => response
        || await cachedResponse(request, true)
        || await cachedResponse(new URL('offline.html', self.location.href).href)
        || unavailableResponse(true))
    );
    return;
  }

  event.respondWith(
    cachedResponse(request)
      .then((cached) => cached || network)
      .then((response) => response || unavailableResponse(false))
  );
});
