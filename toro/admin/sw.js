/**
 * TORO Admin — sw.js
 * Service Worker for PWA offline support (app-shell strategy).
 *
 * Caches the admin shell on install; serves from cache first for
 * navigation requests so the admin UI loads offline.  API/JSON requests
 * always go network-first so data is never stale.
 */

const CACHE_VERSION = 'toro-admin-v1';

// Admin shell assets to pre-cache
const SHELL_ASSETS = [
  '/toro/admin/index.php',
  'https://unpkg.com/feather-icons/dist/feather.min.js',
  'https://fonts.googleapis.com/css2?family=IBM+Plex+Sans+Arabic:wght@400;500;600;700&family=Inter:wght@400;500;600;700&display=swap',
];

// ── Install: pre-cache shell ──────────────────────────────────────────────────
self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(CACHE_VERSION).then((cache) => {
      return cache.addAll(SHELL_ASSETS).catch(() => {
        // Non-fatal: network may be unavailable during install
      });
    })
  );
  self.skipWaiting();
});

// ── Activate: clean up old caches ────────────────────────────────────────────
self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys().then((keys) =>
      Promise.all(
        keys
          .filter((k) => k !== CACHE_VERSION)
          .map((k) => caches.delete(k))
      )
    )
  );
  self.clients.claim();
});

// ── Fetch: strategy selection ────────────────────────────────────────────────
self.addEventListener('fetch', (event) => {
  const { request } = event;
  const url = new URL(request.url);

  // API calls → network-only (never cache JSON responses)
  if (url.pathname.startsWith('/toro/api/')) {
    return; // let browser handle natively
  }

  // Navigation requests → network-first, fallback to cache
  if (request.mode === 'navigate') {
    event.respondWith(
      fetch(request)
        .then((response) => {
          // Update cache with fresh copy
          const clone = response.clone();
          caches.open(CACHE_VERSION).then((cache) => cache.put(request, clone));
          return response;
        })
      .catch(() => {
        return caches.match(request)
          .then((cached) => cached || caches.match('/toro/admin/index.php'))
          .then((cached) => cached || new Response('Offline', {status: 503, headers: {'Content-Type': 'text/plain'}}));
      })
    );
    return;
  }

  // Static assets → cache-first
  event.respondWith(
    caches.match(request).then((cached) => {
      if (cached) return cached;
      return fetch(request).then((response) => {
        if (response && response.status === 200 && response.type === 'basic') {
          const clone = response.clone();
          caches.open(CACHE_VERSION).then((cache) => cache.put(request, clone));
        }
        return response;
      });
    })
  );
});
