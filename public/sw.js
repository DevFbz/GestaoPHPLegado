const VERSION = 'gestao-brindes-v14';
const STATIC_CACHE = VERSION + '-static';
const PAGE_CACHE = VERSION + '-pages';
const API_CACHE = VERSION + '-api';
const OFFLINE_URL = '/offline.html';

const STATIC_ASSETS = [
  '/manifest.webmanifest',
  '/offline.html',
  '/assets/css/app.css',
  '/assets/js/api.js',
  '/assets/js/state-sync.js',
  '/assets/js/app.js',
  '/assets/js/offline.js',
  '/assets/vendor/bootstrap/bootstrap.min.css',
  '/assets/vendor/bootstrap/bootstrap.bundle.min.js',
  '/assets/vendor/bootstrap-icons/bootstrap-icons.min.css',
  '/assets/vendor/bootstrap-icons/fonts/bootstrap-icons.woff2',
  '/assets/vendor/alpinejs/alpine.min.js'
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
      .then((keys) => Promise.all(keys.filter((key) => ![STATIC_CACHE, PAGE_CACHE, API_CACHE].includes(key)).map((key) => caches.delete(key))))
      .then(() => self.clients.claim())
  );
});

self.addEventListener('fetch', (event) => {
  const request = event.request;
  const url = new URL(request.url);
  if (request.method !== 'GET' || url.origin !== self.location.origin) return;

  if (request.mode === 'navigate') {
    event.respondWith(fetch(request, { cache: 'no-store' }).catch(() => caches.match(OFFLINE_URL)));
    return;
  }

  if (url.pathname.startsWith('/api/')) {
    // Dynamic authenticated data must never fall back to a stale snapshot.
    event.respondWith(fetch(request, { cache: 'no-store' }));
    return;
  }

  if (url.pathname.startsWith('/assets/') || url.pathname === '/manifest.webmanifest') {
    event.respondWith(
      caches.match(request).then((cached) => cached || fetch(request).then((response) => {
        if (response.ok) {
          const copy = response.clone();
          caches.open(STATIC_CACHE).then((cache) => cache.put(request, copy));
        }
        return response;
      }))
    );
  }
});

self.addEventListener('message', (event) => {
  if (event.data && event.data.type === 'clear-caches') {
    event.waitUntil(Promise.all([caches.delete(PAGE_CACHE), caches.delete(API_CACHE)]));
  }
});
