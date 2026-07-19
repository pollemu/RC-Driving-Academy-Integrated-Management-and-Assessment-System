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

  /* Styles are injected here (not relying on base.css) so the alert always
     renders correctly even if the stylesheet fails to load/cache. */
  const ALERT_CSS = `
  #rcdaToastRoot{position:fixed;inset:0;z-index:9999;display:flex;align-items:center;justify-content:center;padding:20px;}
  .rcda-toast-overlay{position:fixed;inset:0;display:flex;align-items:center;justify-content:center;background:rgba(16,18,22,0.5);opacity:0;transition:opacity .24s ease;backdrop-filter:blur(2px);cursor:pointer;}
  .rcda-toast-overlay.show{opacity:1;}
  .rcda-toast{position:relative;width:min(420px,94vw);box-sizing:border-box;background:#fff;border-radius:20px;box-shadow:0 40px 80px rgba(16,24,40,.32);overflow:hidden;font-family:Inter,system-ui,sans-serif;line-height:1.45;transform:scale(.94) translateY(8px);opacity:0;transition:opacity .26s ease,transform .26s cubic-bezier(.2,.9,.3,1.2);}
  .rcda-toast.show{opacity:1;transform:scale(1) translateY(0);}
  .rcda-toast .toast-banner{height:6px;width:100%;}
  .rcda-toast .toast-inner{padding:30px 28px 22px;display:flex;flex-direction:column;align-items:center;text-align:center;}
  .rcda-toast .ic{flex:0 0 auto;width:64px;height:64px;border-radius:18px;display:flex;align-items:center;justify-content:center;color:#fff;font-weight:800;font-size:32px;margin-bottom:16px;box-shadow:0 10px 24px rgba(16,24,40,.18);}
  .rcda-toast .body{flex:1 1 auto;min-width:0;}
  .rcda-toast .title{font-family:'Poppins',sans-serif;font-weight:700;font-size:20px;margin-bottom:6px;color:#1F1F1F;}
  .rcda-toast .msg{flex:1 1 auto;word-break:break-word;font-size:14px;opacity:.82;color:#2A2A2A;}
  .rcda-toast .bar{position:absolute;left:0;bottom:0;height:4px;width:100%;transform-origin:left;animation:rcda-toast-bar 2.69s linear forwards;}
  @keyframes rcda-toast-bar{from{transform:scaleX(1);}to{transform:scaleX(0);}}
  .rcda-toast--success .toast-banner{background:linear-gradient(90deg,#12B76A,#0E9A5A);}
  .rcda-toast--success .ic{background:linear-gradient(135deg,#12B76A,#0E9A5A);}
  .rcda-toast--success .bar{background:#12B76A;}
  .rcda-toast--error .toast-banner{background:linear-gradient(90deg,#F04438,#D92D20);}
  .rcda-toast--error .ic{background:linear-gradient(135deg,#F04438,#D92D20);}
  .rcda-toast--error .bar{background:#F04438;}
  .rcda-toast--info .toast-banner{background:linear-gradient(90deg,#2E90FA,#1C78DD);}
  .rcda-toast--info .ic{background:linear-gradient(135deg,#2E90FA,#1C78DD);}
  .rcda-toast--info .bar{background:#2E90FA;}
  `;
  function ensureAlertStyles(){
    if (document.getElementById('rcdaToastStyles')) return;
    const s = document.createElement('style');
    s.id = 'rcdaToastStyles';
    s.textContent = ALERT_CSS;
    document.head.appendChild(s);
  }

  /* ---- Centered alert popup (SweetAlert-style) ----
     Signature kept as showToast(message, type) so every call site still works.
     Renders a dimmed full-screen overlay with a centered card that auto-dismisses
     after 2.69s (and on click). type: 'success' | 'error' | 'info'. */
  const ALERT_MS = 2690;

  function ensureToastRoot(){
    let root = document.getElementById('rcdaToastRoot');
    if (root) return root;
    root = document.createElement('div');
    root.id = 'rcdaToastRoot';
    root.style.cssText = [
      'position:fixed', 'inset:0', 'z-index:9999',
      'display:flex', 'align-items:center', 'justify-content:center',
      'pointer-events:none', 'padding:20px'
    ].join(';');
    document.body.appendChild(root);
    return root;
  }

  RCDA.showToast = function (message, type) {
    type = type || 'error'; // 'error' | 'success' | 'info'
    ensureAlertStyles();
    const icons = { error: '!', success: '✓', info: 'i' };
    const titles = { error: 'Something went wrong', success: 'All set', info: 'Heads up' };
    const root = ensureToastRoot();

    // Replace any visible alert so only one shows at a time.
    const prev = root.querySelector('.rcda-toast');
    if (prev) prev.remove();

    const overlay = document.createElement('div');
    overlay.className = 'rcda-toast-overlay';

    const el = document.createElement('div');
    el.className = 'rcda-toast rcda-toast--' + type;
    el.setAttribute('role', 'status');

    const banner = document.createElement('div');
    banner.className = 'toast-banner';

    const inner = document.createElement('div');
    inner.className = 'toast-inner';

    const ic = document.createElement('span');
    ic.className = 'ic';
    ic.textContent = icons[type] || icons.error;

    const body = document.createElement('div');
    body.className = 'body';

    const title = document.createElement('div');
    title.className = 'title';
    title.textContent = titles[type] || titles.error;

    const msg = document.createElement('div');
    msg.className = 'msg';
    msg.textContent = (message === null || message === undefined) ? '' : String(message);

    body.appendChild(title);
    body.appendChild(msg);
    inner.appendChild(ic);
    inner.appendChild(body);

    const bar = document.createElement('span');
    bar.className = 'bar';

    el.appendChild(banner);
    el.appendChild(inner);
    el.appendChild(bar);
    overlay.appendChild(el);
    root.appendChild(overlay);
    root.style.pointerEvents = 'auto';

    requestAnimationFrame(() => { overlay.classList.add('show'); el.classList.add('show'); });

    const hide = () => {
      if (!el.isConnected) return;
      overlay.classList.remove('show');
      el.classList.remove('show');
      setTimeout(() => { overlay.remove(); if (!root.children.length) root.style.pointerEvents = 'none'; }, 260);
    };
    overlay.addEventListener('click', hide);
    setTimeout(hide, ALERT_MS);

    return el;
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