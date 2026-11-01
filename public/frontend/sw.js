/* ============================================================
   sw.js — RC Driving Academy service worker (PWA offline mode)

   Scope: /frontend/ — registered from instructor/dashboard.html.
   Purpose (MOM non-functional requirement): instructors must be
   able to record session attendance and qualitative feedback on
   their phone even with no internet in the field.

   Strategy
   ─ precache the instructor app shell (html/css/js/images) so the
     dashboard opens from cache when the network is gone,
   ─ network-first runtime cache for GET /api responses — the
     panels show the last-known session/student lists offline,
   ─ POSTs go straight to the network and, when it fails, are
     queued by RCDA.offlineQueue (shared.js) and replayed when the
     device reconnects — the server endpoints are updateOrCreate
     idempotent so a replay is safe.
   ============================================================ */

const SHELL_CACHE = 'rcda-shell-v1';
const API_CACHE = 'rcda-api-v1';

const SHELL = [
  '/frontend/instructor/dashboard.html',
  '/frontend/manifest.webmanifest',
  '/frontend/images/icon.png',
  '/frontend/images/RCDA%20logo.jpg',
  '/frontend/css/base.css?v=66',
  '/frontend/css/instructor.css?v=67',
  '/frontend/js/config.js?v=66',
  '/frontend/js/error-handler.js?v=66',
  '/frontend/js/autosave.js?v=65',
  '/frontend/js/shared.js?v=78',
  '/frontend/js/instructor.js?v=69',
  '/frontend/js/profile-panel.js?v=70',
  '/frontend/js/profile.js?v=65',
];

self.addEventListener('install', event => {
  event.waitUntil(
    caches.open(SHELL_CACHE)
      .then(cache => cache.addAll(SHELL))
      .then(() => self.skipWaiting())
  );
});

self.addEventListener('activate', event => {
  event.waitUntil(
    caches.keys()
      .then(keys => Promise.all(
        keys.filter(k => k !== SHELL_CACHE && k !== API_CACHE).map(k => caches.delete(k))
      ))
      .then(() => self.clients.claim())
  );
});

self.addEventListener('fetch', event => {
  const req = event.request;
  if (req.method !== 'GET') return; // POSTs are handled by RCDA.offlineQueue in the page

  const url = new URL(req.url);
  if (url.origin !== location.origin) return;

  /* GET /api/* — network first, fall back to the last good response */
  if (url.pathname.startsWith('/api/')) {
    event.respondWith(
      fetch(req).then(res => {
        if (res && res.ok) {
          const copy = res.clone();
          caches.open(API_CACHE).then(cache => cache.put(req, copy)).catch(() => {});
        }
        return res;
      }).catch(() =>
        caches.match(req, { ignoreVary: true }).then(hit => hit || new Response(
          JSON.stringify({ message: 'Offline — showing your last known data.' }),
          { status: 503, headers: { 'Content-Type': 'application/json' } }
        ))
      )
    );
    return;
  }

  /* Page navigations — network first, cached document offline */
  if (req.mode === 'navigate' || (req.headers && req.headers.get('accept') || '').includes('text/html')) {
    event.respondWith(
      fetch(req).then(res => {
        const copy = res.clone();
        caches.open(SHELL_CACHE).then(cache => cache.put(req, copy)).catch(() => {});
        return res;
      }).catch(() =>
        caches.match(req, { ignoreVary: true }).then(hit =>
          hit || caches.match(SHELL[0])
        )
      )
    );
    return;
  }

  /* Static assets — cache first, refresh in the background */
  event.respondWith(
    caches.match(req, { ignoreVary: true }).then(hit => {
      if (hit) {
        fetch(req).then(res => {
          if (res && res.ok) {
            const copy = res.clone();
            caches.open(SHELL_CACHE).then(cache => cache.put(req, copy)).catch(() => {});
          }
        }).catch(() => {}); // offline: the cached asset is still served
        return hit;
      }
      return fetch(req).then(res => {
        if (res && res.ok) {
          const copy = res.clone();
          caches.open(SHELL_CACHE).then(cache => cache.put(req, copy)).catch(() => {});
        }
        return res;
      });
    })
  );
});
