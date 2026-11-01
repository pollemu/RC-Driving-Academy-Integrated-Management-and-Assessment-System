/* ============================================================
   config.js — loaded FIRST on every page.
   Defines RCDA_CONFIG (API base, features) and RCDA_AUTH
   (token storage + redirect helpers) used by error-handler.js.
   ============================================================ */

window.RCDA_CONFIG = {
  /* Same-origin: the frontend is served from public/frontend under
     the Laravel host, so the API is just /api on the same origin. */
  getApiBaseUrl() {
    return window.location.origin + '/api';
  },
  /* The host `php artisan serve` runs on. Used only to rescue pages that
     were opened straight from Windows Explorer (file://) — there the API
     is unreachable and every request would fail with a network error. */
  APP_HOST: 'http://127.0.0.1:8123',
  API_TIMEOUT_MS: 15000,
  FEATURES: { autosaveForms: true },
  TOKEN_KEY: 'rcda_token',
  USER_KEY: 'rcda_user'
};

/* Opened by double-clicking an .html file? The API can never be reached
   from a file:// page — bounce to the served copy of the SAME page so the
   app "just works". Must match RCDA_CONFIG.APP_HOST. */
if (window.location.protocol === 'file:' && /RC Driving Academy IMAS|RC%20Driving%20Academy%20IMAS/.test(window.location.pathname)) {
  (function () {
    const segs = window.location.pathname.split('/');
    const route = segs[segs.length - 2] + '/' + segs[segs.length - 1]; // e.g. frontend/login.html
    window.location.replace(RCDA_CONFIG.APP_HOST + '/' + route + window.location.search + window.location.hash);
  })();
}

/* ---- Auth token + user helpers (localStorage backed) ---- */
window.RCDA_AUTH = {
  getToken() {
    try { return localStorage.getItem(RCDA_CONFIG.TOKEN_KEY); } catch (e) { return null; }
  },
  setToken(t) {
    try { localStorage.setItem(RCDA_CONFIG.TOKEN_KEY, t); } catch (e) {}
  },
  getUser() {
    try { return JSON.parse(localStorage.getItem(RCDA_CONFIG.USER_KEY) || 'null'); } catch (e) { return null; }
  },
  setUser(u) {
    try { localStorage.setItem(RCDA_CONFIG.USER_KEY, JSON.stringify(u)); } catch (e) {}
  },
  clear() {
    try {
      localStorage.removeItem(RCDA_CONFIG.TOKEN_KEY);
      localStorage.removeItem(RCDA_CONFIG.USER_KEY);
    } catch (e) {}
  },
  isAuthed() { return !!this.getToken(); },
  /* Redirect to login if not authenticated. Returns false (and
     navigates away) when there is no token. */
  requireAuth(redirect) {
    if (!this.isAuthed()) {
      window.location.href = (redirect || 'login.html');
      return false;
    }
    return true;
  }
};
