/* ============================================================
   signup.js — loaded ONLY on signup.html
   Creates a student account, then drops the user into
   their dashboard where they enroll in courses (cart flow).
   ============================================================ */

function formatPeso(n){
  return '₱' + Number(n || 0).toLocaleString('en-PH');
}

function registerAccount(){
  const first   = document.getElementById('regFirst')?.value.trim();
  const last    = document.getElementById('regLast')?.value.trim();
  const email   = document.getElementById('regEmail')?.value.trim();
  const phone   = document.getElementById('regPhone')?.value.trim();
  const pass    = document.getElementById('regPass')?.value;
  const confirm = document.getElementById('regPassConfirm')?.value;
  const consent = document.getElementById('regConsent')?.checked;
  const errEl   = document.getElementById('regError');
  const btn     = document.getElementById('regSubmitBtn');

  setFieldError('regConsent', !consent);
  clearFieldErrorOnInput('regConsent');

  const allFilled = validateRequiredFields(['regFirst','regLast','regEmail','regPhone','regPass','regPassConfirm']);
  if(!allFilled){ if(errEl){ errEl.style.display='block'; errEl.textContent='Please fill in all fields.'; } return; }
  if(!/^\S+@\S+\.\S+$/.test(email)){ setFieldError('regEmail', true); clearFieldErrorOnInput('regEmail'); if(errEl){ errEl.style.display='block'; errEl.textContent='Please enter a valid email address.'; } return; }
  if(pass !== confirm){ setFieldError('regPass', true); setFieldError('regPassConfirm', true); clearFieldErrorOnInput('regPass'); clearFieldErrorOnInput('regPassConfirm'); if(errEl){ errEl.style.display='block'; errEl.textContent='Passwords do not match.'; } return; }
  if(pass.length < 8){ setFieldError('regPass', true); clearFieldErrorOnInput('regPass'); if(errEl){ errEl.style.display='block'; errEl.textContent='Password must be at least 8 characters.'; } return; }
  if(!consent){ setFieldError('regConsent', true); clearFieldErrorOnInput('regConsent'); if(errEl){ errEl.style.display='block'; errEl.textContent='Please agree to the Terms & Conditions and Privacy Policy to continue.'; } return; }
  if(errEl) errEl.style.display='none';

  if (btn) { btn.disabled = true; btn.classList.add('loading'); }

  (async () => {
    const reg = await RCDA.safeFetch(`${RCDA_CONFIG.getApiBaseUrl()}/register`, {
      method: 'POST',
      body: { name: `${first} ${last}`, email, phone, password: pass, password_confirmation: confirm }
    });
    if (!reg.ok) {
      if (btn) { btn.disabled = false; btn.classList.remove('loading'); }
      if(errEl){ errEl.style.display='block'; errEl.textContent = reg.error || 'Registration failed. Email may already be used.'; }
      return;
    }
    RCDA_AUTH.setToken(reg.data.token);
    RCDA_AUTH.setUser(reg.data.user);
    RCDA.showToast('Account created! Complete your enrollment from the dashboard.', 'success');
    RCDA.autosave.clear('signupForm');
    window.location.href = 'student/dashboard.html';
  })();
}

/* ============================================================
   AUTOSAVE (restore unfinished sign-up if the tab is closed)
   ============================================================ */
document.addEventListener('DOMContentLoaded', () => {
  if (!window.RCDA || !RCDA.autosave) return;
  RCDA.autosave.attach(
    'signupForm',
    ['regFirst', 'regLast', 'regEmail', 'regPhone'],
    { onRestore(){ RCDA.showToast('Welcome back — we restored your unfinished sign-up.', 'info'); } }
  );
});
