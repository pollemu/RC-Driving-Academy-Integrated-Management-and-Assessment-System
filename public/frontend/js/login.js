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
   FORGOT PASSWORD — pick Gmail or SMS, code, then new password
   Works for every role: accounts are found by email (any role)
   or by the phone number on file (students and instructors).
   ============================================================ */

function forgotChannel(){
  const sel = document.querySelector('input[name="forgotChannel"]:checked');
  return sel ? sel.value : 'email';
}

function openForgot(){
  const m = document.getElementById('forgotModal');
  if (!m) return;
  forgotStep(1);
  m.classList.add('open');
  document.body.style.overflow = 'hidden';
  setTimeout(() => { const el = document.getElementById('forgotContact'); if (el) el.focus(); }, 80);
}

function closeForgot(){
  const m = document.getElementById('forgotModal');
  if (!m) return;
  m.classList.remove('open');
  document.body.style.overflow = '';
  forgotError('');
}

function forgotStep(n){
  const s1 = document.getElementById('forgotStep1');
  const s2 = document.getElementById('forgotStep2');
  if (s1) s1.style.display = n === 1 ? 'block' : 'none';
  if (s2) s2.style.display = n === 2 ? 'block' : 'none';
  forgotError('');
}

function forgotError(msg){
  const el = document.getElementById('forgotError');
  if (!el) return;
  el.style.display = msg ? 'block' : 'none';
  el.textContent = msg;
}

/* Highlights the Gmail/SMS choice card and swaps the contact field. */
function forgotPick(){
  const ch = forgotChannel();
  const emailCard = document.getElementById('forgotCardEmail');
  const smsCard = document.getElementById('forgotCardSms');
  if (emailCard) emailCard.className = 'forgot-card ' + (ch === 'email' ? 'forgot-card-sel' : '');
  if (smsCard) smsCard.className = 'forgot-card ' + (ch === 'sms' ? 'forgot-card-sel' : '');
  const inp = document.getElementById('forgotContact');
  const lbl = document.getElementById('forgotContactLabel');
  if (inp) {
    inp.value = '';
    inp.placeholder = ch === 'email' ? 'you@gmail.com' : '09XX XXX XXXX';
    inp.type = ch === 'email' ? 'text' : 'tel';
    inp.focus();
  }
  if (lbl) lbl.innerHTML = (ch === 'email' ? 'Email address' : 'Phone number') + ' <span class="req">*</span>';
}

/* Step 1 — request the code through the chosen channel. */
async function forgotSend(){
  const ch = forgotChannel();
  const contact = ((document.getElementById('forgotContact') || {}).value || '').trim();
  if (!contact) { forgotError(ch === 'email' ? 'Enter your email address.' : 'Enter your phone number.'); return; }

  const btn = document.getElementById('forgotSendBtn');
  if (btn) btn.disabled = true;
  forgotError('');

  const body = { channel: ch };
  body[ch === 'email' ? 'email' : 'phone'] = contact;

  const { ok, data, error } = await RCDA.safeFetch(`${RCDA_CONFIG.getApiBaseUrl()}/password/forgot`, { method: 'POST', body });

  if (btn) btn.disabled = false;

  if (!ok) {
    forgotError((data && data.message) || error || 'Could not send the code. Try again.');
    return;
  }

  const note = document.getElementById('forgotSentNote');
  if (note) note.textContent = data.message || 'Code sent.';
  forgotStep(2);
  const codeEl = document.getElementById('forgotCode');
  if (codeEl) codeEl.focus();
}

/* Step 2 — verify the code and set the new password. */
async function forgotReset(){
  const ch = forgotChannel();
  const contact = ((document.getElementById('forgotContact') || {}).value || '').trim();
  const code = ((document.getElementById('forgotCode') || {}).value || '').trim();
  const pass = (document.getElementById('forgotPass') || {}).value || '';
  const pass2 = (document.getElementById('forgotPass2') || {}).value || '';

  if (!/^\d{6}$/.test(code)) { forgotError('Enter the 6-digit code we sent you.'); return; }
  if (pass.length < 8) { forgotError('New password must be at least 8 characters.'); return; }
  if (pass !== pass2) { forgotError('The two passwords do not match.'); return; }

  const btn = document.getElementById('forgotResetBtn');
  if (btn) btn.disabled = true;
  forgotError('');

  const body = { channel: ch, code, password: pass, password_confirmation: pass2 };
  body[ch === 'email' ? 'email' : 'phone'] = contact;

  const { ok, data, error } = await RCDA.safeFetch(`${RCDA_CONFIG.getApiBaseUrl()}/password/reset`, { method: 'POST', body });

  if (btn) btn.disabled = false;

  if (!ok) {
    forgotError((data && data.message) || error || 'Could not reset the password. Try again.');
    return;
  }

  closeForgot();
  showSuccess(data.message || 'Password updated. You can now sign in.');
}

/* Step-2 "change email/number" link — back to the picker. */
function forgotBack(){
  forgotStep(1);
  const inp = document.getElementById('forgotContact');
  if (inp) inp.focus();
}

function forgotEnter1(e){ if (e.key === 'Enter') forgotSend(); }
function forgotEnter2(e){ if (e.key === 'Enter') forgotReset(); }

/* ============================================================
   INIT
   ============================================================ */
document.addEventListener('DOMContentLoaded', () => {
  const passEl = document.getElementById('loginPass');
  if (passEl) passEl.addEventListener('keydown', e => { if(e.key === 'Enter') doLogin(); });
  /* Arriving from the dashboards' "Forgot password?" links. */
  if (new URLSearchParams(window.location.search).get('forgot')) openForgot();
});
