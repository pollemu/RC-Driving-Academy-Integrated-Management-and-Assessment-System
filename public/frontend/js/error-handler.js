/* ============================================================
   error-handler.js — loaded on EVERY page, right after config.js
   and before shared.js / page scripts.

   Provides:
   - A global catch-all for uncaught JS errors and rejected promises
   - RCDA.safeFetch()   — fetch wrapper with timeout + JSON handling
   - RCDA.showToast()   — small non-blocking error/success banner
   - RCDA.tryRun()      — wraps a function call so one broken handler
                           can't crash the whole page
   ============================================================ */

window.RCDA = window.RCDA || {};

(function () {

  /* ---- Toast UI (created lazily, one instance reused) ---- */
  function ensureToastRoot(){
    let root = document.getElementById('rcdaToastRoot');
    if (root) return root;
    root = document.createElement('div');
    root.id = 'rcdaToastRoot';
    root.style.cssText = [
      'position:fixed', 'top:18px', 'right:18px', 'z-index:9999',
      'display:flex', 'flex-direction:column', 'gap:10px',
      'max-width:340px', 'pointer-events:none'
    ].join(';');
    document.body.appendChild(root);
    return root;
  }

  RCDA.showToast = function (message, type) {
    type = type || 'error'; // 'error' | 'success' | 'info'
    const colors = {
      error:   { bg:'#fef2f2', border:'#fecaca', text:'#b91c1c' },
      success: { bg:'#dcfce7', border:'#bbf7d0', text:'#15803d' },
      info:    { bg:'#eff6ff', border:'#bfdbfe', text:'#1d4ed8' }
    };
    const c = colors[type] || colors.error;

    const root = ensureToastRoot();
    const el = document.createElement('div');
    el.textContent = message;
    el.style.cssText = [
      `background:${c.bg}`, `border:1px solid ${c.border}`, `color:${c.text}`,
      'padding:12px 16px', 'border-radius:10px', 'font-size:13.5px',
      'font-family:Inter,sans-serif', 'box-shadow:0 8px 24px rgba(0,0,0,0.12)',
      'pointer-events:auto', 'opacity:0', 'transform:translateY(-8px)',
      'transition:opacity .2s ease, transform .2s ease'
    ].join(';');
    root.appendChild(el);

    requestAnimationFrame(() => {
      el.style.opacity = '1';
      el.style.transform = 'translateY(0)';
    });

    setTimeout(() => {
      el.style.opacity = '0';
      el.style.transform = 'translateY(-8px)';
      setTimeout(() => el.remove(), 200);
    }, 4200);
  };

  /* ---- Safe fetch: timeout + consistent error shape ----
     Usage:
       const { ok, data, error } = await RCDA.safeFetch('/enroll', { method:'POST', body: {...} });
       if (!ok) { RCDA.showToast(error); return; }
  */
  RCDA.safeFetch = async function (url, options) {
    options = options || {};
    const controller = new AbortController();
    const timeoutMs = (window.RCDA_CONFIG && RCDA_CONFIG.API_TIMEOUT_MS) || 10000;
    const timer = setTimeout(() => controller.abort(), timeoutMs);

    /* Attach auth token automatically (set by RCDA_AUTH after login). */
    const headers = { 'Content-Type': 'application/json', ...(options.headers || {}) };
    const token = (window.RCDA_AUTH && RCDA_AUTH.getToken && RCDA_AUTH.getToken()) || null;
    if (token) headers['Authorization'] = 'Bearer ' + token;

    const fetchOptions = {
      method: options.method || 'GET',
      headers: headers,
      signal: controller.signal
    };
    if (options.body !== undefined) {
      fetchOptions.body = typeof options.body === 'string' ? options.body : JSON.stringify(options.body);
    }

    try {
      const res = await fetch(url, fetchOptions);
      clearTimeout(timer);

      let data = null;
      const contentType = res.headers.get('content-type') || '';
      if (contentType.includes('application/json')) {
        data = await res.json().catch(() => null);
      }

      /* Unauthenticated / token expired → clear and send to login. */
      if (res.status === 401) {
        if (window.RCDA_AUTH) RCDA_AUTH.clear();
        const onAuthPage = window.location.pathname.endsWith('login.html') ||
                           window.location.pathname.endsWith('signup.html') ||
                           window.location.pathname.endsWith('index.html');
        if (!onAuthPage) {
          const inSubfolder = /\/(admin|instructor|student)\//.test(window.location.pathname);
          window.location.href = inSubfolder ? '../login.html' : 'login.html';
        }
        return { ok: false, status: 401, data, error: (data && (data.message || data.error)) || 'Session expired. Please log in again.' };
      }

      if (!res.ok) {
        const message = (data && (data.message || data.error)) || `Request failed (${res.status})`;
        return { ok: false, status: res.status, data, error: message };
      }
      return { ok: true, status: res.status, data, error: null };

    } catch (err) {
      clearTimeout(timer);
      if (err.name === 'AbortError') {
        return { ok: false, status: 0, data: null, error: 'The request timed out. Please check your connection and try again.' };
      }
      return { ok: false, status: 0, data: null, error: 'Network error. Please check your connection and try again.' };
    }
  };

  /* ---- tryRun: wrap any handler so a thrown error doesn't
     silently break the page (shows a toast instead) ---- */
  RCDA.tryRun = function (fn, context) {
    return function (...args) {
      try {
        return fn.apply(context || this, args);
      } catch (err) {
        console.error('[RCDA] Handler error:', err);
        RCDA.showToast('Something went wrong. Please try again.');
      }
    };
  };

  /* ---- Global catch-alls ---- */
  window.addEventListener('error', function (e) {
    console.error('[RCDA] Uncaught error:', e.error || e.message);
    // Keep this quiet in production if you don't want end users
    // seeing internal errors — comment out the line below to silence.
    RCDA.showToast('An unexpected error occurred.');
  });

  window.addEventListener('unhandledrejection', function (e) {
    console.error('[RCDA] Unhandled promise rejection:', e.reason);
    RCDA.showToast('An unexpected error occurred.');
  });

})();