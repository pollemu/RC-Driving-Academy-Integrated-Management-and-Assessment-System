/* ============================================================
   shared.js — loaded on EVERY page
   ============================================================ */

/* ---- MODALS ---- */
function openModal(id){ const m = document.getElementById(id); if(m) m.classList.add('open'); }
function closeModal(id){ const m = document.getElementById(id); if(m) m.classList.remove('open'); }

/* ---- PASSWORD VISIBILITY TOGGLE ---- */
function togglePw(inputId, btn){
  const input = document.getElementById(inputId);
  if (!input) return;
  const showing = input.type === 'text';
  input.type = showing ? 'password' : 'text';
  if (btn) btn.classList.toggle('showing', !showing);
}

/* Reusable spinner/loading placeholder (CSS in base.css).
   Labels are app-controlled literals, so no escaping needed. */
function rcdaLoading(label){
  return '<div class="rcda-loading"><div class="rcda-spinner"></div><div>' + (label || 'Loading…') + '</div></div>';
}

/* Centered full-screen loading overlay with a spinning circle.
   Opaque (cream) so the dashboard never shows behind it while data loads.
   Styles are also in base.css so splash markup in the HTML paints correctly
   on the very first frame; this copy is the fallback if base.css is stale. */
const PAGE_LOADING_CSS = `
  #rcdaPageLoading{position:fixed;inset:0;z-index:9998;display:flex;align-items:center;justify-content:center;background:#FAF8F6;opacity:1;visibility:visible;transition:opacity .18s ease,visibility .18s ease;}
  #rcdaPageLoading:not(.show){opacity:0;visibility:hidden;pointer-events:none;}
  #rcdaPageLoading .rcda-pl-card{display:flex;flex-direction:column;align-items:center;gap:16px;}
  #rcdaPageLoading .rcda-pl-spin{width:54px;height:54px;border:4px solid rgba(192,57,43,0.18);border-top-color:#C0392B;border-radius:50%;animation:rcda-pl-spin .8s linear infinite;}
  #rcdaPageLoading .rcda-pl-label{font-family:'Inter',system-ui,sans-serif;font-size:15px;font-weight:600;color:#C0392B;letter-spacing:.2px;}
  @keyframes rcda-pl-spin{to{transform:rotate(360deg);}}
`;
function ensurePageLoadingStyles(){
  if (document.getElementById('rcdaPageLoadingStyles')) return;
  const s = document.createElement('style');
  s.id = 'rcdaPageLoadingStyles';
  s.textContent = PAGE_LOADING_CSS;
  document.head.appendChild(s);
}
RCDA.showPageLoading = function(label){
  ensurePageLoadingStyles();
  let el = document.getElementById('rcdaPageLoading');
  if (!el) {
    el = document.createElement('div');
    document.body.appendChild(el);
  }
  el.id = 'rcdaPageLoading';
  el.innerHTML = '<div class="rcda-pl-card"><div class="rcda-pl-spin"></div><div class="rcda-pl-label">' +
    (label || 'Loading…') + '</div></div>';
  el.style.display = '';
  clearTimeout(el._hideTimer);
  clearTimeout(el._failsafeTimer);
  // Never let a hung request trap the user on a blank screen.
  el._failsafeTimer = setTimeout(() => { RCDA.hidePageLoading(); }, 15000);
  // Force a reflow so a splash already painted opaque stays put, then show.
  el.classList.add('show');
  return el;
};
RCDA.hidePageLoading = function(){
  const el = document.getElementById('rcdaPageLoading');
  if (!el) return;
  clearTimeout(el._failsafeTimer);
  el.style.transition = '';
  el.classList.remove('show');
  /* The element is deliberately NOT removed — a reload's outgoing frame must
     have a splash node available to re-show instantly (see the pagehide
     handler below); with nothing in the DOM the last frame shows the bare
     dashboard and the user sees a flash before the next spinner appears. */
};

/* Unload cover: on reload / navigation away, the outgoing page's last painted
   frame stays on screen until the new document paints. Re-show the splash at
   the earliest hook (beforeunload runs BEFORE the navigation request starts;
   pagehide is the guaranteed-when-unloading fallback) so the teardown frames
   are spinner-covered, not dashboard. */
function coverWithSplashOnUnload(){
  ensurePageLoadingStyles();
  const el = document.getElementById('rcdaPageLoading');
  if (el) {
    /* Instant, opaque — no fade, or the covering frame is translucent. */
    el.style.transition = 'none';
    el.style.opacity = '1';
    el.style.visibility = 'visible';
    el.classList.add('show');
  }
}
window.addEventListener('beforeunload', coverWithSplashOnUnload);
window.addEventListener('pagehide', coverWithSplashOnUnload);

/* Keyboard reloads: swallow the native reload event, cover the screen with
   the splash, let it composite for a couple of frames, and only then start
   the actual reload. The delay is imperceptible but guarantees the last
   painted frame of the outgoing page is the spinner — not the dashboard. */
window.addEventListener('keydown', function (e) {
  const isReloadKey = e.key === 'F5'
    || ((e.ctrlKey || e.metaKey) && (e.key === 'r' || e.key === 'R'));
  if (!isReloadKey) return;
  e.preventDefault();
  RCDA.showPageLoading();
  /* Force a synchronous style/flush so the covered state is real. */
  void document.documentElement.offsetHeight;
  setTimeout(function () { window.location.reload(); }, 140);
});

/* A back/forward cache restore shows the page as it was — with the splash we
   just re-showed on unload. Uncover it so the loaded page isn't stuck. */
window.addEventListener('pageshow', function (e) {
  if (!e.persisted) return;
  const el = document.getElementById('rcdaPageLoading');
  if (el) {
    el.style.transition = '';
    el.style.opacity = '';
    el.style.visibility = '';
    el.classList.remove('show');
  }
});

/* ---- AUTH GUARD (dashboards) ----
   Verifies the stored token against /api/me, redirecting to login
   when missing/invalid, and to the correct role dashboard when the
   role does not match. Returns true when access is allowed. */
async function requireAuth(role){
  const token = RCDA_AUTH.getToken();
  if (!token) { window.location.href = '../index.html'; return false; }

  const { ok, data } = await RCDA.safeFetch(`${RCDA_CONFIG.getApiBaseUrl()}/me`, { method: 'GET' });
  if (!ok || !data || !data.user) { RCDA_AUTH.clear(); window.location.href = '../index.html'; return false; }

  const u = data.user;
  if (role && u.role !== role) {
    const dest = u.role === 'admin' ? '../admin/dashboard.html'
              : u.role === 'instructor' ? '../instructor/dashboard.html'
              : '../student/dashboard.html';
    window.location.href = dest;
    return false;
  }
  RCDA_AUTH.setUser(u);
  applyUserInfo();
  return true;
}

/* Fills the sidebar / topbar with the logged-in user's name + initials,
   and shows the profile picture every place an avatar is rendered when the
   user has uploaded one (falls back to initials otherwise). */
function applyUserInfo(){
  const u = RCDA_AUTH.getUser();
  if (!u) return;
  const name = (u.name || u.email || 'User');
  const initials = name.split(' ').filter(Boolean).map(w => w[0]).slice(0, 2).join('').toUpperCase();
  const nameEl = document.querySelector('.sidebar-user-info b');
  if (nameEl) nameEl.textContent = name;
  const roleEl = document.querySelector('.sidebar-user-info span');
  if (roleEl) roleEl.textContent = (u.role || '').charAt(0).toUpperCase() + (u.role || '').slice(1) + ' · RCDA';
  const paintAvatar = (el) => {
    if (!el) return;
    if (!u.avatar_url) { el.textContent = initials; return; }
    el.textContent = '';
    const img = document.createElement('img');
    img.src = u.avatar_url;
    img.alt = '';
    img.style.cssText = 'width:100%;height:100%;object-fit:cover;border-radius:50%;position:absolute;inset:0;';
    img.addEventListener('error', () => { img.remove(); el.textContent = initials; });
    if (!getComputedStyle(el).position || getComputedStyle(el).position === 'static') {
      el.style.position = 'relative';
    }
    el.appendChild(img);
  };
  paintAvatar(document.querySelector('.sidebar-avatar'));
  paintAvatar(document.getElementById('topbarAvatar'));
}

async function logout(){
  try {
    await RCDA.safeFetch(`${RCDA_CONFIG.getApiBaseUrl()}/logout`, { method: 'POST' });
  } catch (e) { /* ignore — token may already be invalid */ }
  RCDA_AUTH.clear();
  window.location.href = '../index.html';
}

document.addEventListener('DOMContentLoaded', () => {
  document.querySelectorAll('.modal-overlay').forEach(o => {
    o.addEventListener('click', e => { if(e.target === o) o.classList.remove('open'); });
  });
});

/* ---- MOBILE SIDEBAR ---- */
function toggleSidebar(){
  const sb = document.querySelector('.sidebar');
  const overlay = document.getElementById('sidebarOverlay');
  if (!sb) return;
  const isOpen = sb.classList.contains('open');
  if (isOpen) {
    sb.classList.remove('open');
    if (overlay) overlay.classList.remove('active');
  } else {
    sb.classList.add('open');
    if (overlay) overlay.classList.add('active');
  }
}

function closeAllSidebars(){
  document.querySelectorAll('.sidebar').forEach(s => s.classList.remove('open'));
  const overlay = document.getElementById('sidebarOverlay');
  if (overlay) overlay.classList.remove('active');
}

/* ---- ACTIVE DASHBOARD PANEL (survives reloads) ----
   Each dashboard stores the sidebar tab it is showing in the URL hash, e.g.
   student/dashboard.html#sessions. Reloading, or opening a shared link, then
   lands on the same tab instead of falling back to the default panel. */
function rememberPanel(panel) {
  if (!panel) return;
  try {
    if (location.hash !== '#' + panel) {
      history.replaceState(null, '', '#' + panel);
    }
  } catch (e) { /* history blocked — the tab still works, it just won't deep-link */ }
}

function panelFromHash() {
  return (location.hash || '').replace(/^#/, '');
}

/* Finds the sidebar button that opens `panel` so the highlight is restored too. */
function navItemForPanel(panel) {
  const items = document.querySelectorAll('.sidebar-nav .nav-item');
  for (let i = 0; i < items.length; i++) {
    const handler = items[i].getAttribute('onclick') || '';
    if (handler.indexOf("'" + panel + "'") !== -1) return items[i];
  }
  return null;
}

/* ---- SCROLL NAV (landing page only) ---- */
window.addEventListener('scroll', () => {
  const nav = document.getElementById('mainNav');
  if (nav) nav.classList.toggle('scrolled', window.scrollY > 40);
});

/* ---- NAME + PHONE INPUT GUARDS (shared by signup, profile and admin forms) ----
   Names accept letters (any language), spaces, period, apostrophe and hyphen.
   PH mobile numbers are stored as 11 digits in 09XXXXXXXXX form. Keeping the
   client in sync with the server rules avoids a round-trip just to say "no
   digits in a name". */
function sanitizeNameInput(el){
  if (!el) return;
  const clean = el.value.replace(/[^\p{L}\s.'-]/gu, '').replace(/\s{2,}/g, ' ');
  if (clean !== el.value) el.value = clean;
}

function sanitizePhoneInput(el){
  if (!el) return;
  const digits = el.value.replace(/\D+/g, '').slice(0, 11);
  if (digits !== el.value) el.value = digits;
}

function isValidPersonName(value){
  const v = String(value || '').trim();
  return v.length >= 2 && v.length <= 60 && /^\p{L}[\p{L}\s.'-]*$/u.test(v);
}

function isValidPhMobile(value){
  return /^09\d{9}$/.test(String(value || '').trim());
}

/* ---- FIELD VALIDATION ----
   Marks/unmarks a field (by id) as invalid — adds a red outline via
   the .field-error class on its wrapper, and clears it as soon as
   the user starts fixing it. Works for .input-wrap and .m-inp-wrap
   layouts, and for plain checkboxes. */
function setFieldError(id, hasError){
  const el = document.getElementById(id);
  if (!el) return;

  const wrap = el.closest('.input-wrap') || el.closest('.m-inp-wrap');
  if (wrap) {
    wrap.classList.toggle('field-error', hasError);
  } else if (el.type === 'checkbox') {
    el.classList.toggle('field-error-checkbox', hasError);
  } else {
    el.classList.toggle('field-error', hasError);
  }
}

/* Clears a field's error the moment the user edits/checks it —
   call once per field, typically right after you first validate it. */
function clearFieldErrorOnInput(id){
  const el = document.getElementById(id);
  if (!el || el.dataset.rcdaErrorBound) return;
  el.dataset.rcdaErrorBound = 'true';
  const evt = (el.tagName === 'SELECT' || el.type === 'checkbox') ? 'change' : 'input';
  el.addEventListener(evt, () => setFieldError(id, false));
}

/* Validates a list of required field ids: marks empties red,
   clears previously-valid ones, and returns true if all are filled. */
function validateRequiredFields(ids){
  let allValid = true;
  ids.forEach(id => {
    const el = document.getElementById(id);
    const isEmpty = !el || !el.value || !el.value.trim();
    setFieldError(id, isEmpty);
    clearFieldErrorOnInput(id);
    if (isEmpty) allValid = false;
  });
  return allValid;
}

/* ---- STAR RATING ---- */
function rateStar(star){
  const row = star.closest('.star-row');
  if (!row) return;
  const stars = [...row.querySelectorAll('.star')];
  const idx = stars.indexOf(star);
  stars.forEach((s, i) => s.classList.toggle('lit', i <= idx));
}

/* ---- NLP DATA ---- */
const nlpData = {
  fast: {
    text: "Magaling siya mag-drive: sobrang smooth ang clutch at braking, one-take lagi ang parking, walang stall kahit traffic, at laging tama ang speed limit buong session.",
    tokens: [{t:'excellent',c:'pos'},{t:'outstanding',c:'pos'},{t:'mastered',c:'pos'},{t:'accurate',c:'pos'},{t:'smooth',c:'pos'}],
    badge: '⚡ Fast Learner', cls: 'fast', score: 91,
    msg: 'Excellent performance. Continue at current pace.'
  },
  avg: {
    text: "Student shows good progress with steering and side-mirror checks, but braking still needs more practice. Mirror observation is improving. Braking is adequate but can be smoother.",
    tokens: [{t:'good',c:'pos'},{t:'improving',c:'pos'},{t:'adequate',c:''},{t:'needs practice',c:'neg'},{t:'can be smoother',c:'neg'}],
    badge: '📊 Average Learner', cls: 'avg', score: 65,
    msg: 'Steady progress. Focus on braking consistency and mirror checks.'
  },
  needs: {
    text: "Grabe ang kamalian sa pag-shift ng gear, madalas mamatay ang makina, delikado ang pagpreno at pumapasok sa kabilang linya ang pagliko sa kanan.",
    tokens: [{t:'difficulty',c:'neg'},{t:'unsafe',c:'neg'},{t:'incorrect',c:'neg'},{t:'poor',c:'neg'},{t:'struggle',c:'neg'}],
    badge: '⚠ Needs Improvement', cls: 'needs', score: 38,
    msg: 'Instructor intervention recommended. Additional sessions advised.'
  }
};

function classifyNLP(text){
  const txt = (text || '').toLowerCase();
  if (txt.includes('excellent') || txt.includes('mastered') || txt.includes('outstanding')) return 'fast';
  if (txt.includes('poor') || txt.includes('unsafe') || txt.includes('difficulty') || txt.includes('struggle')) return 'needs';
  return 'avg';
}

/* ============================================================
   OFFLINE QUEUE — saved on device, flushed automatically
   Field instructors lose signal mid-session (rural venues,
    basements, dead zones). Submissions made while offline go
   into a localStorage queue and are replayed as soon as the
   connection returns. The instructor sees a badge.
   ============================================================ */
RCDA.offlineQueue = (function () {
  const KEY = 'rcda_offline_queue';
  const badgeId = 'rcdaOfflineBadge';

  function read(){
    try { return JSON.parse(localStorage.getItem(KEY)) || []; } catch (e) { return []; }
  }
  function write(items){
    try { localStorage.setItem(KEY, JSON.stringify(items)); } catch (e) { /* storage full — drop */ }
  }

  const listeners = [];
  function emit(){
    updateBadge();
    listeners.forEach(fn => { try { fn(count(), read()); } catch (e) {} });
  }

  /** Queue a submission. `item` is a descriptor for the badge tooltip. */
  function push(url, options, item){
    const items = read();
    items.push(Object.assign({
      id: Date.now() + Math.random().toString(36).slice(2, 7),
      url: url,
      options: options,
      queued_at: new Date().toISOString(),
      label: (item && item.label) || 'Submission'
    }, item || {}));
    write(items);
    emit();
  }

  function count(){ return read().length; }

  /* Replay every queued submission. A real server response (any status
     code) means the request arrived: success = done, a 4xx means the
     payload itself was wrong so keep-or-drop is decided by removeAfter.
     A network failure (safeFetch status 0) keeps the item for later. */
  let flushing = false;
  async function flush(){
    if (flushing) return 0;
    flushing = true;
    let synced = 0;
    let items = read();
    try {
      while (items.length && navigator.onLine) {
        const next = items[0];
        const res = await RCDA.safeFetch(next.url, next.options);
        if (res.status === 0) break;                   // still no connection — stop
        items = items.filter(i => i.id !== next.id);   // reached the server
        if (res.ok) synced++;
        else if (next.onFail) { try { next.onFail(res); } catch (e) {} }
        write(items);
        emit();
      }
    } catch (e) { /* flush continues next time */ }
    flushing = false;
    if (synced) window.dispatchEvent(new CustomEvent('rcda:offlinesync', { detail: { synced } }));
    updateBadge();
    return synced;
  }

  /* ---- Badge in the dashboard topbar ---- */
  function setBadgeText(text, color){
    const b = document.getElementById(badgeId);
    if (!b) return;
    if (!text) { b.style.display = 'none'; return; }
    b.style.display = 'inline-flex';
    b.innerHTML = '<span style="width:8px;height:8px;border-radius:50%;background:' + color + ';margin-right:7px;"></span>' + text;
  }

  function updateBadge(){
    const n = count();
    if (!navigator.onLine) {
      setBadgeText('Offline — saving on device', '#d97706');
    } else if (n > 0) {
      setBadgeText(n + (n === 1 ? ' entry' : ' entries') + ' waiting to sync', '#7d5bb5');
    } else {
      setBadgeText(null);
    }
  }

  function initBadge(){
    if (document.getElementById(badgeId)) return;
    const bar = document.querySelector('.dash-topbar .topbar-left') || document.body;
    const b = document.createElement('div');
    b.id = badgeId;
    b.style.cssText = 'display:none;margin-left:12px;align-items:center;font-size:12.5px;font-weight:700;padding:7px 13px;border-radius:99px;background:rgba(255,255,255,0.9);box-shadow:0 2px 10px rgba(0,0,0,0.12);color:var(--charcoal);';
    bar.appendChild(b);
    updateBadge();

    window.addEventListener('online', () => { updateBadge(); flush(); });
    window.addEventListener('offline', updateBadge);
    document.addEventListener('visibilitychange', () => { if (!document.hidden && navigator.onLine) flush(); });
  }

  return { push, flush, count, read, onUpdated: fn => listeners.push(fn), initBadge, updateBadge };
})();