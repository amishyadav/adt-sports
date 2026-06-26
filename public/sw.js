// ADT Sports service worker.
// Strategy: network-first for HTML navigations (so online users always get
// fresh pages + valid CSRF tokens — we never serve a cached form), and
// cache-first for static assets (CSS bundle, fonts, images, icons).
const VERSION = 'adt-v2';
const STATIC_CACHE = 'adt-static-' + VERSION;
const PAGE_CACHE = 'adt-pages-' + VERSION;
const OFFLINE_URL = '/offline.html';

const PRECACHE = [OFFLINE_URL, '/icons/icon-192.png'];

self.addEventListener('install', (event) => {
  event.waitUntil(caches.open(STATIC_CACHE).then((c) => c.addAll(PRECACHE)));
  self.skipWaiting();
});

self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys().then((keys) =>
      Promise.all(keys.filter((k) => !k.endsWith(VERSION)).map((k) => caches.delete(k)))
    )
  );
  self.clients.claim();
});

function isStatic(url) {
  return (
    url.pathname.startsWith('/build/') ||
    url.pathname.startsWith('/uploads/') ||
    url.pathname.startsWith('/icons/') ||
    url.origin === 'https://fonts.gstatic.com' ||
    url.origin === 'https://fonts.googleapis.com' ||
    url.origin === 'https://cdnjs.cloudflare.com'
  );
}

self.addEventListener('fetch', (event) => {
  const req = event.request;
  if (req.method !== 'GET') return;

  const url = new URL(req.url);

  // Never touch authenticated or transactional pages — let the browser handle
  // them over the network (no caching, no offline copy).
  if (
    url.pathname.startsWith('/admin') ||
    url.pathname.startsWith('/subscribe/confirm') ||
    url.pathname.includes('/commenter/confirm')
  ) {
    return;
  }

  // HTML navigations: network-first, fall back to a cached copy, then offline page.
  if (req.mode === 'navigate') {
    event.respondWith(
      fetch(req)
        .then((res) => {
          // keep a copy of successful same-origin pages for offline reading
          if (res.ok && url.origin === self.location.origin) {
            const clone = res.clone();
            caches.open(PAGE_CACHE).then((c) => c.put(req, clone));
          }
          return res;
        })
        .catch(() => caches.match(req).then((hit) => hit || caches.match(OFFLINE_URL)))
    );
    return;
  }

  // Static assets: cache-first, revalidate in the background.
  if (isStatic(url)) {
    event.respondWith(
      caches.match(req).then((hit) => {
        const network = fetch(req)
          .then((res) => {
            if (res.ok) {
              const clone = res.clone();
              caches.open(STATIC_CACHE).then((c) => c.put(req, clone));
            }
            return res;
          })
          .catch(() => hit);
        return hit || network;
      })
    );
  }
});
