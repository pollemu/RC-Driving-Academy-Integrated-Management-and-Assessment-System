/* ============================================================
   login.js — loaded ONLY on login.html
   Single login form: role is determined by the account itself,
   so we just send email + password and follow the API response.
   ============================================================ */

const ROLE_REDIRECT = {
  admin:      'admin/dashboard.html',
  instructor: 'instructor/dashboard.html',
  student:    'student/dashboard.html'
};

/* ---- Login (real API call) ---- */
async function doLogin(){
  const emailEl = document.getElementById('loginEmail');
  const passEl  = document.getElementById('loginPass');
  const errEl   = document.getElementById('loginError');
  const email   = emailEl ? emailEl.value.trim() : '';
  const pass    = passEl  ? passEl.value : '';

  if (!email || !pass) {
    if (errEl) { errEl.style.display = 'block'; errEl.textContent = 'Please enter your email and password.'; }
    setFieldError('loginEmail', !email);
    setFieldError('loginPass', !pass);
    return;
  }

  setFieldError('loginEmail', false);
  setFieldError('loginPass', false);
  if (errEl) errEl.style.display = 'none';

  const btn = document.querySelector('.login-btn');
  if (btn) btn.disabled = true;

  const { ok, data, error } = await RCDA.safeFetch(
    `${RCDA_CONFIG.getApiBaseUrl()}/login`,
    { method: 'POST', body: { email, password: pass } }
  );

  if (btn) btn.disabled = false;

  if (!ok) {
    if (errEl) { errEl.style.display = 'block'; errEl.textContent = error || 'Login failed.'; }
    setFieldError('loginEmail', true);
    setFieldError('loginPass', true);
    return;
  }

  RCDA_AUTH.setToken(data.token);
  RCDA_AUTH.setUser(data.user);

  const role = (data.user && data.user.role) || 'student';
  window.location.href = ROLE_REDIRECT[role] || 'index.html';
}

/* ============================================================
   SHARED SUCCESS MODAL
   ============================================================ */
function showSuccess(msg){
  const msgEl = document.getElementById('successMsg');
  if (typeof msg === 'string') { if (msgEl) msgEl.textContent = msg; }
  const m = document.getElementById('successModal');
  if(m){ m.classList.add('open'); document.body.style.overflow = 'hidden'; }
}

function closeSuccess(){
  const m = document.getElementById('successModal');
  if(m){ m.classList.remove('open'); document.body.style.overflow = ''; }
}

document.addEventListener('click', e => {
  if(e.target === document.getElementById('successModal')) closeSuccess();
});

/* ============================================================
   INIT
   ============================================================ */
document.addEventListener('DOMContentLoaded', () => {
  const passEl = document.getElementById('loginPass');
  if (passEl) passEl.addEventListener('keydown', e => { if(e.key === 'Enter') doLogin(); });
});
