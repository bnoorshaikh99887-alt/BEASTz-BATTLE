// BEASTz BATTLE Service Worker
const CACHE_NAME = 'beastz-battle-cache-v1';
const STATIC_ASSETS = [
  '/',
  '/manifest.json',
  '/icon-192.png',
  '/icon-512.png',
  '/icon-512-maskable.png',
  '/apple-touch-icon.png'
];

self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(CACHE_NAME).then((cache) => cache.addAll(STATIC_ASSETS)).catch(() => {})
  );
  self.skipWaiting();
});

self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys().then((keys) => {
      return Promise.all(
        keys.filter((key) => key !== CACHE_NAME).map((key) => caches.delete(key))
      );
    })
  );
  self.clients.claim();
});

self.addEventListener('fetch', (event) => {
  // Only handle standard GET requests
  if (event.request.method !== 'GET') return;
  const url = new URL(event.request.url);

  // Cache-first for images, json, icons
  if (url.pathname.match(/\.(png|jpg|jpeg|svg|gif|json|ico)$/)) {
    event.respondWith(
      caches.match(event.request).then((cached) => {
        if (cached) return cached;
        return fetch(event.request).then((networkRes) => {
          if (networkRes && networkRes.status === 200) {
            const clone = networkRes.clone();
            caches.open(CACHE_NAME).then((c) => c.put(event.request, clone));
          }
          return networkRes;
        });
      })
    );
    return;
  }

  // Network-first for dynamic PHP pages & APIs to always keep live match data & wallet fresh
  event.respondWith(
    fetch(event.request).catch(() => caches.match(event.request))
  );
});
