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
  el.classList.remove('show');
  clearTimeout(el._hideTimer);
  el._hideTimer = setTimeout(() => {
    if (el.parentNode) el.parentNode.removeChild(el);
  }, 220);
};

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

/* Fills the sidebar / topbar with the logged-in user's name + initials. */
function applyUserInfo(){
  const u = RCDA_AUTH.getUser();
  if (!u) return;
  const name = (u.name || u.email || 'User');
  const initials = name.split(' ').filter(Boolean).map(w => w[0]).slice(0, 2).join('').toUpperCase();
  const nameEl = document.querySelector('.sidebar-user-info b');
  if (nameEl) nameEl.textContent = name;
  const roleEl = document.querySelector('.sidebar-user-info span');
  if (roleEl) roleEl.textContent = (u.role || '').charAt(0).toUpperCase() + (u.role || '').slice(1) + ' · RCDA';
  const av = document.querySelector('.sidebar-avatar');
  if (av) av.textContent = initials;
  const tb = document.getElementById('topbarAvatar');
  if (tb) tb.textContent = initials;
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
    text: "Student shows excellent steering control, smooth braking and accurate side-mirror checks. Braking distance was outstanding on every stop, and mirror observation was mastered throughout the session.",
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
    text: "Student has difficulty with steering control and unsafe braking on turns. Side-mirror checks were incorrect multiple times. Braking shows poor control and struggle with basic maneuvers.",
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