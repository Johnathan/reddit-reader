/*
 * Reddit Reader service worker.
 *
 * Deliberately conservative: this is a single-user *authenticated* app, so we
 * never cache page HTML (it's private and personalised). The SW exists to make
 * the app installable and to show a friendly offline screen — not to cache
 * content. Only the static app-shell assets (icons, manifest, offline page)
 * are precached; everything else goes straight to the network.
 */
const VERSION = 'v2';
const SHELL_CACHE = 'reader-shell-' + VERSION;

const SHELL_ASSETS = [
  '/offline.html',
  '/manifest.webmanifest',
  '/icons/icon-192.png',
  '/icons/icon-512.png',
  '/icons/apple-touch-icon.png',
];

self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(SHELL_CACHE).then((cache) => cache.addAll(SHELL_ASSETS)).then(() => self.skipWaiting())
  );
});

self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys()
      .then((keys) => Promise.all(keys.filter((k) => k !== SHELL_CACHE).map((k) => caches.delete(k))))
      .then(() => self.clients.claim())
  );
});

self.addEventListener('fetch', (event) => {
  const { request } = event;

  if (request.method !== 'GET') {
    return; // Never touch POST/DELETE (subscribe, feeds, logout, …).
  }

  const url = new URL(request.url);
  const sameOrigin = url.origin === self.location.origin;

  // Page navigations: network-first, fall back to the offline screen. We do
  // NOT cache the response — authenticated pages must not be stored.
  if (request.mode === 'navigate') {
    event.respondWith(fetch(request).catch(() => caches.match('/offline.html')));
    return;
  }

  // Static shell assets: cache-first (they're versioned by content rarely).
  if (sameOrigin && (url.pathname.startsWith('/icons/') || url.pathname === '/manifest.webmanifest')) {
    event.respondWith(
      caches.match(request).then((hit) => hit || fetch(request).then((res) => {
        const copy = res.clone();
        caches.open(SHELL_CACHE).then((cache) => cache.put(request, copy));
        return res;
      }))
    );
    return;
  }

  // Everything else (Reddit images, the suggest endpoint, etc.): just network.
});
