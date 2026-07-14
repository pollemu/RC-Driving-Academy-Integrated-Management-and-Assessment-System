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
  API_TIMEOUT_MS: 15000,
  FEATURES: { autosaveForms: true },
  TOKEN_KEY: 'rcda_token',
  USER_KEY: 'rcda_user'
};

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
