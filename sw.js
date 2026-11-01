// BEASTz BATTLE Service Worker v3 - Anti-Stale Caching Engine
const CACHE_NAME = 'beastz-battle-cache-v3';
const STATIC_ASSETS = [
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
  if (event.request.method !== 'GET') return;
  const url = new URL(event.request.url);

  // NEVER cache HTML navigations or PHP dynamic pages to guarantee instant login/logout/action state
  if (event.request.mode === 'navigate' || url.pathname.endsWith('.php') || url.pathname === '/' || url.pathname.includes('/api/')) {
    event.respondWith(fetch(event.request));
    return;
  }

  // Cache-first only for static icons and images
  if (url.pathname.match(/\.(png|jpg|jpeg|svg|gif|ico|woff2)$/)) {
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

  // Default network-first for everything else
  event.respondWith(
    fetch(event.request).catch(() => caches.match(event.request))
  );
});
