/* ============================================================
   signup.js — loaded ONLY on signup.html
   Flow: account details -> payment method (online / FTF) -> success
   Now wired to the real Laravel API (register -> enroll -> pay).
   ============================================================ */

const COURSE_LABELS = {
  'TDC':      'Theoretical Driving Course (TDC)',
  'PDC':      'Practical Driving Course (PDC)',
  'TDC+PDC':  'TDC + PDC (Complete Package)'
};

let pendingStudent = null; // holds validated step-1 data while on the payment step

function formatPeso(n){
  return '₱' + Number(n || 0).toLocaleString('en-PH');
}

function generateRefCode(){
  const rand = Math.floor(10000 + Math.random() * 90000);
  const year = new Date().getFullYear();
  return `RCDA-${year}-${rand}`;
}

/* Pick the first active backend course matching a frontend category. */
function mapCourse(courses, category){
  if (!Array.isArray(courses)) return null;
  const type = (category === 'TDC') ? 'TDC' : 'PDC'; // TDC+PDC -> PDC package
  return courses.find(c => c.type === type && c.is_active) || courses.find(c => c.type === type);
}

/* ============================================================
   STEP 1 -> STEP 2  (register + enroll via API)
   ============================================================ */
async function goToPayment(){
  const first   = document.getElementById('regFirst')?.value.trim();
  const last    = document.getElementById('regLast')?.value.trim();
  const email   = document.getElementById('regEmail')?.value.trim();
  const phone   = document.getElementById('regPhone')?.value.trim();
  const course  = document.getElementById('regCourse')?.value;
  const pass    = document.getElementById('regPass')?.value;
  const confirm = document.getElementById('regPassConfirm')?.value;
  const consent = document.getElementById('regConsent')?.checked;
  const errEl   = document.getElementById('regError');

  setFieldError('regConsent', !consent);
  clearFieldErrorOnInput('regConsent');

  const allFilled = validateRequiredFields(['regFirst','regLast','regEmail','regPhone','regCourse','regPass','regPassConfirm']);
  if(!allFilled){ if(errEl){ errEl.style.display='block'; errEl.textContent='Please fill in all fields.'; } return; }
  if(!/^\S+@\S+\.\S+$/.test(email)){ setFieldError('regEmail', true); clearFieldErrorOnInput('regEmail'); if(errEl){ errEl.style.display='block'; errEl.textContent='Please enter a valid email address.'; } return; }
  if(pass !== confirm){ setFieldError('regPass', true); setFieldError('regPassConfirm', true); clearFieldErrorOnInput('regPass'); clearFieldErrorOnInput('regPassConfirm'); if(errEl){ errEl.style.display='block'; errEl.textContent='Passwords do not match.'; } return; }
  if(pass.length < 8){ setFieldError('regPass', true); clearFieldErrorOnInput('regPass'); if(errEl){ errEl.style.display='block'; errEl.textContent='Password must be at least 8 characters.'; } return; }
  if(!consent){ setFieldError('regConsent', true); clearFieldErrorOnInput('regConsent'); if(errEl){ errEl.style.display='block'; errEl.textContent='Please agree to the Terms & Conditions and Privacy Policy to continue.'; } return; }
  if(errEl) errEl.style.display='none';

  const btn = document.getElementById('regSubmitBtn');
  if (btn) btn.disabled = true;

  /* 1) Register the student account */
  const reg = await RCDA.safeFetch(`${RCDA_CONFIG.getApiBaseUrl()}/register`, {
    method: 'POST',
    body: { name: `${first} ${last}`, email, phone, password: pass, password_confirmation: confirm }
  });
  if (!reg.ok) {
    if (btn) btn.disabled = false;
    if(errEl){ errEl.style.display='block'; errEl.textContent = reg.error || 'Registration failed. Email may already be used.'; }
    return;
  }
  RCDA_AUTH.setToken(reg.data.token);
  RCDA_AUTH.setUser(reg.data.user);

  /* 2) Load courses and map the chosen category to a real course */
  const courseRes = await RCDA.safeFetch(`${RCDA_CONFIG.getApiBaseUrl()}/student/enroll`, { method: 'GET' });
  const chosen = mapCourse(courseRes.data ? courseRes.data.courses : [], course);
  if (!chosen) {
    if (btn) btn.disabled = false;
    if(errEl){ errEl.style.display='block'; errEl.textContent='No course available for this selection. Contact the academy.'; }
    return;
  }

  /* 3) Create a pending enrollment for that course */
  const enr = await RCDA.safeFetch(`${RCDA_CONFIG.getApiBaseUrl()}/student/enroll/${chosen.id}`, { method: 'POST' });
  if (!enr.ok) {
    if (btn) btn.disabled = false;
    if(errEl){ errEl.style.display='block'; errEl.textContent = enr.error || 'Could not start enrollment.'; }
    return;
  }

  if (btn) btn.disabled = false;

  pendingStudent = {
    first, last, email, phone, course,
    courseId: chosen.id, courseName: chosen.name,
    enrollmentId: enr.data.enrollment.id,
    amount: chosen.fee
  };

  document.getElementById('paySummaryCourse').textContent = chosen.name;
  document.getElementById('paySummaryAmount').textContent = formatPeso(chosen.fee);

  document.getElementById('signupBox').style.display  = 'none';
  document.getElementById('paymentBox').style.display  = 'block';
  document.getElementById('payOnlineTab').classList.remove('selected');
  document.getElementById('payFtfTab').classList.remove('selected');
  document.getElementById('onlinePanel').style.display = 'none';
  document.getElementById('ftfPanel').style.display    = 'none';
}

function backToDetails(){
  document.getElementById('paymentBox').style.display = 'none';
  document.getElementById('signupBox').style.display  = 'block';
}

/* ============================================================
   PAYMENT METHOD SELECTION
   ============================================================ */
function selectPayMethod(method){
  document.getElementById('payOnlineTab').classList.toggle('selected', method === 'online');
  document.getElementById('payFtfTab').classList.toggle('selected', method === 'ftf');
  document.getElementById('onlinePanel').style.display = method === 'online' ? 'block' : 'none';
  document.getElementById('ftfPanel').style.display    = method === 'ftf'    ? 'block' : 'none';
}

/* Calls the payment API. Returns the parsed result or shows an error. */
async function submitPayment(method, reference){
  const errEl = document.getElementById('payError');
  if (!pendingStudent || !pendingStudent.enrollmentId) {
    RCDA.showToast('Your session details were lost — please start over.');
    backToDetails();
    return null;
  }
  const payload = { method, reference_no: reference || '' };
  const res = await RCDA.safeFetch(
    `${RCDA_CONFIG.getApiBaseUrl()}/student/payments/${pendingStudent.enrollmentId}`,
    { method: 'POST', body: payload }
  );
  if (!res.ok) {
    if (errEl){ errEl.style.display='block'; errEl.textContent = res.error || 'Payment could not be submitted.'; }
    return null;
  }
  return res.data;
}

/* ---- Online payment (GCash / BDO via PayMongo) ---- */
async function payOnline(){
  const btnLabel = document.getElementById('payOnlineLabel');
  if (btnLabel) btnLabel.textContent = 'Processing...';

  const number = document.getElementById('payNumber')?.value.trim();
  const expiry = document.getElementById('payExpiry')?.value.trim();
  const cvv    = document.getElementById('payCvv')?.value.trim();
  const allFilled = validateRequiredFields(['payNumber','payExpiry','payCvv']);
  if(!allFilled){ if (btnLabel) btnLabel.textContent = 'Pay Now'; return; }

  const data = await submitPayment('gcash', number);
  if (btnLabel) btnLabel.textContent = 'Pay Now';
  if (!data) return;

  if (data.checkout_url) {
    window.location.href = data.checkout_url; // redirect to PayMongo checkout
    return;
  }
  showSuccess({
    badge: 'verified', badgeText: 'Paid', title: 'Payment submitted!',
    message: `Thanks, ${pendingStudent.first}! Your payment of ${formatPeso(pendingStudent.amount)} for ${pendingStudent.courseName} was received and is pending admin verification.`
  });
  RCDA.autosave.clear('signupForm');
}

/* ---- Face-to-face payment: recorded on-site by the administrator ---- */
async function confirmFtf(){
  const data = await submitPayment('gcash', 'CASH-ONSITE');
  if (!data) return;
  showSuccess({
    badge: 'pending', badgeText: 'Pending — Pay at Center', title: 'Enrollment reserved!',
    message: `Thanks, ${pendingStudent.first}! Your seat in ${pendingStudent.courseName} is reserved. Please pay ${formatPeso(pendingStudent.amount)} in cash at the RC Driving Academy center — the administrator will record your payment and confirm your enrollment.`
  });
  RCDA.autosave.clear('signupForm');
}

/* ============================================================
   SUCCESS STATE
   ============================================================ */
function showSuccess({ badge, badgeText, title, message }){
  document.getElementById('paymentBox').style.display  = 'none';
  document.getElementById('signupBox').style.display    = 'none';

  const badgeEl = document.getElementById('successBadge');
  badgeEl.className = 'badge ' + badge;
  badgeEl.textContent = badgeText;

  document.querySelector('#signupSuccess .title').textContent = title;
  document.getElementById('signupSuccessMsg').textContent = message;
  document.getElementById('refCode').textContent = generateRefCode();

  document.getElementById('signupSuccess').style.display = 'block';
}

/* ============================================================
   AUTOSAVE
   ============================================================ */
document.addEventListener('DOMContentLoaded', () => {
  if (!window.RCDA || !RCDA.autosave) return;
  RCDA.autosave.attach(
    'signupForm',
    ['regFirst', 'regLast', 'regEmail', 'regPhone', 'regCourse'],
    { onRestore(){ RCDA.showToast('Welcome back — we restored your unfinished sign-up.', 'info'); } }
  );
});
