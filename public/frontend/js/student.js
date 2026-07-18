/* ============================================================
   student.js — loaded ONLY on student/dashboard.html
   ============================================================ */

/* ---- SCROLL TOPBAR (clear-on-scroll, same as admin/instructor) ---- */
window.addEventListener('scroll', () => {
  const topbar = document.querySelector('.dash-topbar');
  if (topbar) topbar.classList.toggle('scrolled', window.scrollY > 40);
});

const STUD_TITLES = {
  profile: 'My Profile', handbook: 'Handbook',
  sessions: 'My Sessions', myassess: 'My Results',
  enroll: 'Enroll', payment: 'Payment'
};

function showPanel(panel, el) {
  document.querySelectorAll('.panel').forEach(p => p.classList.remove('active'));
  const target = document.getElementById('stud-' + panel);
  if (target) target.classList.add('active');

  const titleEl = document.getElementById('studTitle');
  if (titleEl && STUD_TITLES[panel]) titleEl.textContent = STUD_TITLES[panel];

  if (el) {
    const parentNav = el.closest ? el.closest('.sidebar-nav') : null;
    if (parentNav) parentNav.querySelectorAll('.nav-item').forEach(n => n.classList.remove('active'));
    if (el.classList) el.classList.add('active');
  }
  closeAllSidebars();
}

/* ---- HANDBOOK (book-like chapters) ---- */
const HB_CHAPTERS = ['tdc', 'pdc4', 'pdc2', 'safety'];

function openHbChapter(key) {
  const book = document.querySelector('.handbook-book');
  if (!book) return;

  book.querySelectorAll('.hb-tab').forEach(t => t.classList.toggle('active', t.dataset.chapter === key));
  book.querySelectorAll('.hb-page-content').forEach(p => p.classList.toggle('active', p.dataset.chapter === key));

  const prevBtn = document.getElementById('hbPrevBtn');
  const nextBtn = document.getElementById('hbNextBtn');
  const idx = HB_CHAPTERS.indexOf(key);
  if (prevBtn) prevBtn.disabled = idx <= 0;
  if (nextBtn) {
    const isLast = idx >= HB_CHAPTERS.length - 1;
    nextBtn.textContent = isLast ? 'Back to Start →' : 'Next →';
  }

  const page = book.querySelector('.hb-page');
  if (page) page.scrollTop = 0;
}

function hbTurn(direction) {
  const activeTab = document.querySelector('.handbook-book .hb-tab.active');
  const current = activeTab ? activeTab.dataset.chapter : HB_CHAPTERS[0];
  let idx = HB_CHAPTERS.indexOf(current) + direction;
  if (idx >= HB_CHAPTERS.length) idx = 0;
  if (idx < 0) idx = 0;
  openHbChapter(HB_CHAPTERS[idx]);
}

/* ============================================================
   API WIRING
   ============================================================ */
const STUD_API = () => RCDA_CONFIG.getApiBaseUrl();

function money(n) {
  return '₱' + Number(n || 0).toLocaleString('en-PH');
}

function formatDate(s) {
  if (!s) return '—';
  try { return new Date(s).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' }); }
  catch (e) { return s; }
}

function statusBadge(status) {
  const map = {
    pending: ['confirmed', 'Pending'],
    active: ['enrolled', 'Active'],
    completed: ['done', 'Completed'],
    verified: ['verified', 'Verified'],
    rejected: ['', 'Rejected'],
    open: ['confirmed', 'Open'],
    done: ['done', 'Done'],
    full: ['', 'Full'],
    cancelled: ['', 'Cancelled'],
  };
  const [cls, label] = map[status] || ['', (status || '').charAt(0).toUpperCase() + (status || '').slice(1)];
  return `<span class="badge ${cls}">${label}</span>`;
}

function classBadge(cls) {
  const map = {
    fast: ['fast', '⚡ Fast Learner'],
    avg: ['avg', '📊 Average Learner'],
    needs: ['needs', '⚠ Needs Improvement'],
  };
  const [c, l] = map[cls] || ['', '—'];
  return `<span class="badge ${c}">${l}</span>`;
}

/* ---- HTML ESCAPING (prevents breaking layout / XSS on user data) ---- */
function escapeHtml(str) {
  if (str === null || str === undefined) return '';
  return String(str)
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#39;');
}

/* ---- PROFILE ---- */
async function loadStudentProfile() {
  const API = STUD_API();
  const [me, dash, pay, prog] = await Promise.all([
    RCDA.safeFetch(API + '/me'),
    RCDA.safeFetch(API + '/student/dashboard'),
    RCDA.safeFetch(API + '/student/payments'),
    RCDA.safeFetch(API + '/student/progress'),
  ]);

  const meData = me.ok ? me.data.user : (RCDA_AUTH.getUser() || {});
  const enrollments = (dash.ok && dash.data.enrollments) || [];
  const bookings = (dash.ok && dash.data.bookings) || [];
  const payments = (pay.ok && pay.data.payments) || [];
  const assessments = (prog.ok && prog.data.assessments) || [];

  const name = meData.name || 'Student';
  const initials = name.split(' ').filter(Boolean).map(w => w[0]).slice(0, 2).join('').toUpperCase();
  const courseNames = enrollments.map(e => e.course && e.course.name).filter(Boolean);
  const courseLabel = courseNames.length ? courseNames.join(' + ') : '—';
  const instructor = bookings.length ? bookings[0].instructor_name : '—';
  const enrolledOn = enrollments.length && enrollments[0].enrolled_at ? formatDate(enrollments[0].enrolled_at) : '—';

  const totalFees = enrollments.reduce((s, e) => s + (Number(e.course && e.course.fee) || 0), 0);
  const totalPaid = payments.filter(p => p.status === 'verified').reduce((s, p) => s + (Number(p.amount) || 0), 0);
  let payLabel, payCls;
  if (totalPaid <= 0) { payLabel = 'Unpaid'; payCls = ''; }
  else if (totalPaid < totalFees) { payLabel = 'Partial'; payCls = 'confirmed'; }
  else { payLabel = 'Fully Paid'; payCls = 'enrolled'; }

  const latest = assessments[0];
  const classHtml = latest ? classBadge(latest.classification) : '<span class="badge">—</span>';

  const progressHtml = enrollments.length
    ? enrollments.map(e => `
        <div style="display:flex;justify-content:space-between;padding:10px 0;border-bottom:1px solid var(--gray-light);font-size:13.5px;">
          <span>${escapeHtml(e.course ? e.course.name : 'Course')}</span>
          ${statusBadge(e.status)}
        </div>`).join('')
    : '<div style="font-size:13.5px;color:var(--gray);">You are not enrolled in any course yet.</div>';

  const body = document.getElementById('studProfileBody');
  if (body) body.innerHTML = `
    <div class="card">
      <div style="display:flex;align-items:center;gap:16px;margin-bottom:20px;">
        <div style="width:60px;height:60px;border-radius:50%;background:linear-gradient(135deg,var(--orange),#ff9a52);display:flex;align-items:center;justify-content:center;color:white;font-weight:800;font-size:20px;font-family:'Poppins';box-shadow:0 6px 16px rgba(242,106,33,0.3);">${initials}</div>
        <div><h3 style="font-size:18px;">${escapeHtml(name)}</h3><span style="font-size:13px;color:var(--gray);">${escapeHtml(meData.email || '')}</span></div>
      </div>
      <div style="display:flex;flex-direction:column;gap:10px;">
        <div style="display:flex;justify-content:space-between;padding:10px 0;border-bottom:1px solid var(--gray-light);font-size:13.5px;"><span style="color:var(--gray);">Course</span><b>${escapeHtml(courseLabel)}</b></div>
        <div style="display:flex;justify-content:space-between;padding:10px 0;border-bottom:1px solid var(--gray-light);font-size:13.5px;"><span style="color:var(--gray);">Instructor</span><b>${escapeHtml(instructor)}</b></div>
        <div style="display:flex;justify-content:space-between;padding:10px 0;border-bottom:1px solid var(--gray-light);font-size:13.5px;"><span style="color:var(--gray);">Enrolled On</span><b>${escapeHtml(enrolledOn)}</b></div>
        <div style="display:flex;justify-content:space-between;padding:10px 0;border-bottom:1px solid var(--gray-light);font-size:13.5px;"><span style="color:var(--gray);">Payment</span><span class="badge ${payCls}">${payLabel}</span></div>
        <div style="display:flex;justify-content:space-between;padding:10px 0;font-size:13.5px;"><span style="color:var(--gray);">Classification</span>${classHtml}</div>
      </div>
    </div>
    <div class="card">
      <div class="card-head"><h3>Enrollments</h3></div>
      ${progressHtml}
    </div>`;
}

/* ---- SESSIONS ---- */
async function loadStudentSessions() {
  const API = STUD_API();
  const [dash, sched] = await Promise.all([
    RCDA.safeFetch(API + '/student/dashboard'),
    RCDA.safeFetch(API + '/student/schedules'),
  ]);
  const bookings = (dash.ok && dash.data.bookings) || [];
  const schedules = (sched.ok && sched.data.schedules) || [];
  const bookedIds = (sched.ok && sched.data.bookedIds) || [];

  const isUpcoming = (b) => b.date && b.time_start
    ? new Date(b.date + 'T' + b.time_start).getTime() >= Date.now()
    : false;
  const hasUpcoming = bookings.some(isUpcoming);

  const bookedRows = bookings.length ? bookings.map(b => `
    <tr>
      <td>${escapeHtml(b.course ? b.course.name : '—')}</td>
      <td>—</td>
      <td>${escapeHtml(b.date || '—')}</td>
      <td>${escapeHtml((b.time_start || '') + (b.time_end ? ' – ' + b.time_end : ''))}</td>
      <td>${escapeHtml(b.instructor_name || '—')}</td>
      <td>—</td>
      <td>${b.status === 'pending'
        ? `<span class="badge pending">Pending acceptance</span>`
        : (b.cancelable
          ? `<button class="btn btn-sm" style="border:1px solid #dc2626;color:#dc2626;background:#fff;" onclick="cancelBooking(${b.schedule_id})">Cancel</button>`
          : `<span class="badge confirmed">Booked</span>`)}</td>
    </tr>`).join('')
    : '<tr><td colspan="7" style="text-align:center;color:var(--gray);padding:18px;">No sessions booked yet.</td></tr>';

  const availRows = schedules.length ? schedules.map(s => {
    const full = s.booked >= s.max_students;
    const isBooked = bookedIds.includes(s.id);
    let btn;
    if (isBooked) {
      btn = '<span class="badge enrolled">Booked</span>';
    } else if (full) {
      btn = '<span class="badge">Full</span>';
    } else if (hasUpcoming) {
      btn = '<span class="badge">1 session booked</span>';
    } else {
      btn = `<button class="btn btn-sm btn-primary" onclick="bookSession(${s.id})">Book</button>`;
    }
    return `<tr>
      <td>${escapeHtml(s.course ? s.course.name : '—')}</td>
      <td>${escapeHtml(s.date || '—')}</td>
      <td>${escapeHtml((s.time_start || '') + ' – ' + (s.time_end || ''))}</td>
      <td>${escapeHtml(s.venue || '—')}</td>
      <td>${escapeHtml(s.instructor ? s.instructor.name : '—')}</td>
      <td>${s.booked}/${s.max_students}</td>
      <td>${btn}</td>
    </tr>`;
  }).join('')
    : '<tr><td colspan="7" style="text-align:center;color:var(--gray);padding:18px;">No open sessions available for your active courses.</td></tr>';

  const body = document.getElementById('studSessionsBody');
  if (body) body.innerHTML = `
    <div class="card">
      <div class="card-head"><h3>My Booked Sessions</h3></div>
      <div style="overflow-x:auto;">
        <table><thead><tr><th>Course</th><th>Session</th><th>Date</th><th>Time</th><th>Instructor</th><th>Venue</th><th>Status</th></tr></thead>
        <tbody>${bookedRows}</tbody></table>
      </div>
    </div>
    <div class="card" style="margin-top:18px;">
      <div class="card-head"><h3>Available Sessions</h3><span style="font-size:13px;color:var(--gray);">Book a slot in your active course</span></div>
      ${hasUpcoming ? '<div class="enroll-note" style="margin:0 0 12px;">You’re already booked for an upcoming session. Cancel it from “My Booked Sessions” first if you want to pick a different one.</div>' : ''}
      <div style="overflow-x:auto;">
        <table><thead><tr><th>Course</th><th>Date</th><th>Time</th><th>Venue</th><th>Instructor</th><th>Slots</th><th></th></tr></thead>
        <tbody>${availRows}</tbody></table>
      </div>
    </div>`;
}

async function bookSession(id) {
  const { ok, error } = await RCDA.safeFetch(STUD_API() + `/student/schedules/${id}/book`, { method: 'POST' });
  if (!ok) { RCDA.showToast(error || 'We couldn’t book that session. Please try again.'); return; }
  RCDA.showToast('Booking submitted — the admin must accept the instructor before your slot is confirmed.', 'success');
  loadStudentSessions();
}

async function cancelBooking(id) {
  const { ok, error } = await RCDA.safeFetch(STUD_API() + `/student/schedules/${id}/book`, { method: 'DELETE' });
  if (!ok) { RCDA.showToast(error || 'We couldn’t cancel that booking. Please try again.'); return; }
  RCDA.showToast('Booking cancelled. You can now book another session.', 'success');
  loadStudentSessions();
}

/* ---- RESULTS ---- */
async function loadStudentResults() {
  const { ok, data } = await RCDA.safeFetch(STUD_API() + '/student/progress');
  const body = document.getElementById('studResultsBody');
  if (!ok) { if (body) body.innerHTML = '<div class="card">We couldn’t load this right now. Please refresh.</div>'; return; }

  const assessments = data.assessments || [];
  const certs = data.certifications || [];
  const latest = assessments[0];

  const renderCats = (cats) => (cats || []).map(c => {
    const n = Number(c.score) || 0;
    const stars = '★★★★★'.split('').map((s, i) => `<span style="color:${i < n ? '#f59e0b' : '#d1d5db'};font-size:15px;">★</span>`).join('');
    return `<div style="display:flex;align-items:center;justify-content:space-between;padding:7px 0;border-bottom:1px solid var(--gray-light);">
      <span style="font-size:13px;font-weight:600;color:var(--charcoal);">${escapeHtml(c.label)}</span>
      <span style="display:flex;align-items:center;gap:8px;">${stars}${c.note ? `<span style="font-size:11.5px;color:var(--gray);">${escapeHtml(c.note)}</span>` : ''}</span>
    </div>`;
  }).join('');

  const renderSuggestions = (sugs) => (sugs && sugs.length) ? sugs.map(s => `
    <div style="display:flex;gap:10px;padding:10px 0;border-bottom:1px solid var(--gray-light);align-items:flex-start;">
      <span style="font-size:16px;line-height:1.2;">📘</span>
       <div><b style="font-size:13px;color:var(--charcoal);">${escapeHtml(s.label || 'Training')}</b><br>
      <span style="font-size:12.5px;color:var(--gray);">${escapeHtml(s.module)}</span></div>
    </div>`).join('') : '<div style="font-size:12px;color:var(--gray);">No extra training needed — great work!</div>';

  const summary = latest ? `
    <div style="padding:20px 0;">
      <div style="text-align:center;">${classBadge(latest.classification)}</div>
      <div class="score-ring-wrap" style="margin:14px auto 0;">
        <svg viewBox="0 0 160 160">
          <circle class="score-ring-bg" cx="80" cy="80" r="68"/>
          <circle class="score-ring-fill" cx="80" cy="80" r="68" stroke-dasharray="427.26" stroke-dashoffset="${Math.round(427.26 * (1 - (Number(latest.confidence_score) || 0)))}"/>
        </svg>
        <div class="score-ring-label"><span class="num">${Math.round((Number(latest.confidence_score) || 0) * 100)}%</span><span class="cap">Confidence</span></div>
      </div>
      <p style="font-size:14px;margin-top:16px;text-align:center;">${escapeHtml(latest.recommendation || 'Your instructor will advise next steps.')}</p>
      <div style="margin-top:14px;">${renderCats(latest.categories)}</div>
      <div class="card-head" style="margin-top:18px;"><h3>Suggested Training</h3></div>
      <div>${renderSuggestions(latest.suggestions)}</div>
    </div>`
    : '<div style="text-align:center;padding:20px 0;color:var(--gray);">No assessments yet.</div>';

  const list = assessments.length ? assessments.map(a => `
    <div class="session-row">
      <div class="session-dot done"></div>
      <div><b style="font-size:13.5px;">${escapeHtml(a.course_name || 'Session')}</b><br>
      <span style="font-size:12px;color:var(--gray);">${classBadge(a.classification)} · ${Math.round((Number(a.confidence_score) || 0) * 100)}% confidence · ${(a.categories || []).filter(c => Number(c.score) > 0).length}/${(a.categories || []).length} skills rated</span></div>
    </div>`).join('')
    : '<div style="font-size:12px;color:var(--gray);">No assessment records.</div>';

  const certHtml = certs.length ? certs.map(c => `
    <div style="display:flex;justify-content:space-between;padding:10px 0;border-bottom:1px solid var(--gray-light);font-size:13.5px;">
      <span>${escapeHtml(c.course ? c.course.name : 'Course')}</span>
      ${statusBadge(c.status)}
    </div>`).join('')
    : '<div style="font-size:12px;color:var(--gray);">No certificates issued yet.</div>';

  if (body) body.innerHTML = `
    <div class="two-col" style="align-items:start;">
      <div class="card">
        <div class="card-head"><h3>My Assessment Results</h3></div>
        ${summary}
      </div>
      <div class="card">
        <div class="card-head"><h3>Progress Over Sessions</h3></div>
        <div style="display:flex;flex-direction:column;gap:12px;">${list}</div>
        <div class="card-head" style="margin-top:18px;"><h3>Certifications</h3></div>
        ${certHtml}
      </div>
    </div>`;
}

/* ---- PAYMENTS ---- */
async function loadStudentPayments() {
  const API = STUD_API();
  const [pay, dash] = await Promise.all([
    RCDA.safeFetch(API + '/student/payments'),
    RCDA.safeFetch(API + '/student/dashboard'),
  ]);
  const payments = (pay.ok && pay.data.payments) || [];
  const enrollments = (dash.ok && dash.data.enrollments) || [];

  const totalPaid = payments.filter(p => p.status === 'verified').reduce((s, p) => s + Number(p.amount || 0), 0);
  const totalFees = enrollments.reduce((s, e) => s + Number(e.course ? e.course.fee : 0), 0);
  const balance = Math.max(0, totalFees - totalPaid);

  const histRows = payments.length ? payments.map(p => `
    <tr>
      <td>${escapeHtml(formatDate(p.created_at))}</td>
      <td><b>${money(p.amount)}</b></td>
      <td>${escapeHtml((p.method || '').toUpperCase())}</td>
      <td>${escapeHtml(p.reference_no || '—')}</td>
      ${statusBadge(p.status)}
    </tr>`).join('')
    : '<tr><td colspan="5" style="text-align:center;color:var(--gray);padding:18px;">No payments yet.</td></tr>';

  const dues = enrollments.filter(e => e.status === 'pending');
  const hasRejected = payments.some(p => p.status === 'rejected');
  const duesRows = dues.length ? dues.map(e => `
    <tr>
      <td>${escapeHtml(e.course ? e.course.name : 'Course')}</td>
      <td><b>${money(e.course ? e.course.fee : 0)}</b></td>
      <td>${statusBadge(e.status)}</td>
      <td><button class="btn btn-sm btn-primary" onclick="payEnrollment(${e.course.id})">Pay Now</button></td>
    </tr>`).join('')
    : '<tr><td colspan="4" style="text-align:center;color:var(--gray);padding:18px;">No pending dues. 🎉</td></tr>';

  const body = document.getElementById('studPaymentBody');
  if (body) body.innerHTML = `
    <div class="payment-summary">
      <div class="pay-stat"><img src="../images/increased-revenue.png" alt="Total Paid" class="pay-stat-icon"><div class="pay-stat-text"><div class="label">Total Paid</div><div class="amount" style="color:#16a34a;">${money(totalPaid)}</div><div class="hint">Verified payments</div></div></div>
      <div class="pay-stat"><img src="../images/pending-payment.png" alt="Balance" class="pay-stat-icon"><div class="pay-stat-text"><div class="label">Balance</div><div class="amount">${money(balance)}</div><div class="hint">${balance > 0 ? 'Outstanding' : 'Fully paid'}</div></div></div>
      <div class="pay-stat"><img src="../images/payment-day.png" alt="Next Payment" class="pay-stat-icon"><div class="pay-stat-text"><div class="label">Pending Dues</div><div class="amount" style="color:var(--gray);">${dues.length}</div><div class="hint">${dues.length ? 'Needs payment' : 'None'}</div></div></div>
    </div>
    ${hasRejected ? `<div class="notice notice-warn" style="margin-top:14px;">One of your payments was rejected. Please pay again below to activate your enrollment.</div>` : ''}
    <div class="card" style="margin-top:18px;">
      <div class="card-head"><h3>Outstanding Dues</h3></div>
      <div style="overflow-x:auto;">
        <table><thead><tr><th>Course</th><th>Amount</th><th>Status</th><th></th></tr></thead>
        <tbody>${duesRows}</tbody></table>
      </div>
    </div>
    <div class="card" style="margin-top:18px;">
      <div class="card-head"><h3>Payment History</h3></div>
      <div style="overflow-x:auto;">
        <table><thead><tr><th>Date</th><th>Amount</th><th>Method</th><th>Reference</th><th>Status</th></tr></thead>
        <tbody>${histRows}</tbody></table>
      </div>
    </div>`;
}

function payEnrollment(courseId) {
  openPayModal([courseId]);
}

/* ---- ENROLL (cart) ---- */
let ENROLL_STATE = null;                 // last /student/enroll payload
let ENROLL_SELECTED = { tdc: null, pdc: {} }; // tdc: selected courseId | pdc: courseId -> true

function enrollRender(){
  const data = ENROLL_STATE;
  const body = document.getElementById('studEnrollBody');
  if (!body || !data) return;

  const courses = data.courses || [];
  const tdc = courses.filter(c => c.type === 'TDC');
  const pdc = courses.filter(c => c.type === 'PDC');
  const tdcCompleted = !!data.tdc_completed;

  const activeTdcId = (courses.find(c => c.type === 'TDC' && c.enrolled && (c.enrollment_status === 'active' || c.enrollment_status === 'completed')) || {}).id || null;
  const pendingTdcId = (courses.find(c => c.type === 'TDC' && c.enrolled && c.enrollment_status !== 'active' && c.enrollment_status !== 'completed') || {}).id || null;

  const tdcOpts = tdc.map(c => {
    const status = c.enrollment_status || '';
    const badge = status ? statusBadge(status) : '';
    const isActive = status === 'active' || status === 'completed';
    const disabled = (activeTdcId && c.id !== activeTdcId) ? 'disabled' : '';
    const checked = (ENROLL_SELECTED.tdc === c.id) || (activeTdcId === c.id);
    const cls = ['enroll-opt'];
    if (checked) cls.push('is-selected');
    if (isActive) cls.push('is-done');
    let hint = '';
    if (isActive) {
      hint = `<span class="enroll-opt-hint">Enrolled — your TDC is active.</span>`;
    } else if (pendingTdcId && c.id === pendingTdcId) {
      hint = `<span class="enroll-opt-hint">Payment is still being confirmed — finish paying to activate, or switch to the other option below.</span>`;
    } else if (pendingTdcId && c.id !== pendingTdcId) {
      hint = `<span class="enroll-opt-hint">Pick this one instead if you’d like to switch your TDC.</span>`;
    }
    return `<label class="${cls.join(' ')}">
      <input type="radio" name="tdc" value="${c.id}" ${checked ? 'checked' : ''} ${disabled} onchange="enrollSelectionChanged()">
      <span class="enroll-opt-main">
        <b>${escapeHtml(c.name)}</b>
        <span class="enroll-opt-sub">${escapeHtml(c.format || '')} · ${money(c.fee)} ${badge}</span>
        ${hint}
      </span>
    </label>`;
  }).join('');

  const pdcOpts = pdc.map(c => {
    const status = c.enrollment_status || '';
    const isActive = status === 'active' || status === 'completed';
    const checked = !!ENROLL_SELECTED.pdc[c.id] || isActive;
    const locked = !tdcCompleted;
    const disabled = (checked && isActive) || locked ? 'disabled' : '';
    const cls = ['enroll-opt'];
    if (checked) cls.push('is-selected');
    if (locked) cls.push('is-locked');
    if (isActive) cls.push('is-done');
    let hint;
    if (isActive) {
      hint = `<span class="enroll-opt-hint">Enrolled — your PDC is active.</span>`;
    } else if (Number(c.downpayment) < Number(c.fee)) {
      hint = `<span class="enroll-opt-hint">${money(c.downpayment)} downpayment to start scheduling · ${money(Number(c.fee) - Number(c.downpayment))} balance later</span>`;
    } else {
      hint = `<span class="enroll-opt-hint">Full payment ${money(c.fee)}</span>`;
    }
    const badge = c.enrollment_status ? statusBadge(c.enrollment_status) : '';
    return `<label class="${cls.join(' ')}">
      <input type="checkbox" value="${c.id}" ${checked ? 'checked' : ''} ${disabled} onchange="enrollSelectionChanged()">
      <span class="enroll-opt-main">
        <b>${escapeHtml(c.name)}</b>
        <span class="enroll-opt-sub">${escapeHtml(c.format || '')} · ${money(c.fee)} ${badge}</span>
        ${hint}
      </span>
    </label>`;
  }).join('');

  const selected = [];
  if (ENROLL_SELECTED.tdc) selected.push(ENROLL_SELECTED.tdc);
  Object.keys(ENROLL_SELECTED.pdc).forEach(id => { if (ENROLL_SELECTED.pdc[id]) selected.push(parseInt(id, 10)); });

  const lines = selected.map(id => {
    const c = courses.find(x => x.id === id);
    return c ? `<div class="cart-line"><span>${escapeHtml(c.name)}</span><span>${money(c.fee)}</span></div>` : '';
  }).filter(Boolean).join('');

  const total = selected.reduce((s, id) => { const c = courses.find(x => x.id === id); return s + (c ? Number(c.fee) : 0); }, 0);

  const pdcNote = tdcCompleted ? '' :
    '<div class="enroll-note">Finish your TDC and get assessed before you can enroll in any PDC course.</div>';

  const selectedCourses = selected.map(id => courses.find(x => x.id === id)).filter(Boolean);
  const hasReEnroll = selectedCourses.some(c => c.enrolled && c.enrollment_status !== 'active' && c.enrollment_status !== 'completed');
  const allActive = selectedCourses.length > 0 && selectedCourses.every(c => c.enrolled && (c.enrollment_status === 'active' || c.enrollment_status === 'completed'));

  let enrollSummary;
  if (!selected.length) {
    enrollSummary = '<div class="cart-empty">Select at least one course to continue.</div>';
  } else if (hasReEnroll) {
    enrollSummary = `
      <div class="cart-lines">${lines}</div>
      <div class="cart-total"><span>Total</span><span>${money(total)}</span></div>
      <button class="btn btn-primary" style="margin-top:14px;width:100%;" onclick="openPayModal([${selected.join(',')}])">Pay now</button>`;
  } else if (allActive) {
    enrollSummary = `
      <div class="cart-lines">${lines}</div>
      <div class="cart-total"><span>Total</span><span>${money(total)}</span></div>
      <div class="enroll-note">These enrollments are active. Check the Payments tab to see your balance.</div>`;
  } else {
    enrollSummary = `
      <div class="cart-lines">${lines}</div>
      <div class="cart-total"><span>Total</span><span>${money(total)}</span></div>
      <button class="btn btn-primary" style="margin-top:14px;width:100%;" onclick="submitEnroll()">Enroll &amp; Pay</button>`;
  }

  body.innerHTML = `
    <div class="card">
      <div class="card-head"><h3>Enroll in a Course</h3><span style="font-size:13px;color:var(--gray);">Pick your subjects — complete TDC before PDC</span></div>

      <div class="enroll-group">
        <div class="enroll-group-title">Theoretical Driving Course (TDC) <span>choose one</span></div>
        <div class="enroll-opts">${tdcOpts}</div>
      </div>

      <div class="enroll-group">
        <div class="enroll-group-title">Practical Driving Course (PDC) <span>choose any</span></div>
        <div class="enroll-opts">${pdcOpts}</div>
        ${pdcNote}
      </div>

      <div class="enroll-summary" id="enrollSummary">
        ${enrollSummary}
      </div>
    </div>`;
}

function enrollSelectionChanged(){
  const t = document.querySelector('input[name=tdc]:checked');
  ENROLL_SELECTED.tdc = t ? parseInt(t.value, 10) : null;
  (ENROLL_STATE.courses || []).forEach(c => {
    if (c.type === 'PDC') {
      const el = document.querySelector(`input[type=checkbox][value="${c.id}"]`);
      if (el) ENROLL_SELECTED.pdc[c.id] = el.checked;
    }
  });
  enrollRender();
}

async function loadStudentEnroll(){
  const { ok, data } = await RCDA.safeFetch(STUD_API() + '/student/enroll');
  const body = document.getElementById('studEnrollBody');
  if (!ok) { if (body) body.innerHTML = '<div class="card">We couldn’t load the courses right now. Please refresh.</div>'; return; }
  ENROLL_STATE = data;
  ENROLL_SELECTED = { tdc: null, pdc: {} };
  (data.courses || []).forEach(c => {
    if (c.enrolled && c.type === 'TDC') ENROLL_SELECTED.tdc = c.id;
    if (c.enrolled && c.type === 'PDC') ENROLL_SELECTED.pdc[c.id] = true;
  });
  enrollRender();
}

async function submitEnroll(){
  const data = ENROLL_STATE;
  if (!data) return;
  const selected = [];
  if (ENROLL_SELECTED.tdc) selected.push(ENROLL_SELECTED.tdc);
  Object.keys(ENROLL_SELECTED.pdc).forEach(id => { if (ENROLL_SELECTED.pdc[id]) selected.push(parseInt(id, 10)); });
  if (!selected.length) { RCDA.showToast('Select at least one course.'); return; }

  const { ok, data: res, error } = await RCDA.safeFetch(STUD_API() + '/student/enroll/cart', {
    method: 'POST', body: { course_ids: selected }
  });
  if (!ok) { RCDA.showToast(error || 'We couldn’t enroll you just now. Please try again.'); return; }
  RCDA.showToast('Enrollment started! Pay the downpayment to activate your slot.', 'success');
  const ids = (res.enrollments || []).map(e => e.course_id);
  openPayModal(ids);
}

/* ---- PAYMENT MODAL (cart) ---- */
let PAY_COURSE_IDS = [];

async function openPayModal(courseIds){
  PAY_COURSE_IDS = courseIds || [];
  if (!PAY_COURSE_IDS.length) { RCDA.showToast('No courses to pay for.'); return; }

  const { ok, data } = await RCDA.safeFetch(STUD_API() + '/student/enroll');
  if (!ok) { RCDA.showToast('Could not load payment details.'); return; }

  const courses = (data.courses || []).filter(c => PAY_COURSE_IDS.includes(c.id));
  const items = courses.map(c => `
    <div class="cart-line">
      <span>${escapeHtml(c.name)}</span>
      <span>${money(c.downpayment)} <small style="color:var(--gray);font-weight:400;">/ ${money(c.fee)}</small></span>
    </div>`).join('');
  const total = courses.reduce((s, c) => s + Number(c.downpayment), 0);

  const el = document.getElementById('payModalItems');
  if (el) el.innerHTML = items;
  const tot = document.getElementById('payModalTotal');
  if (tot) tot.textContent = money(total);

  openModal('payModal');
}

async function processPay(){
  if (!PAY_COURSE_IDS.length) { RCDA.showToast('No courses to pay for.'); return; }
  const btn = document.getElementById('payModalPayBtn');
  if (btn) btn.disabled = true;

  const { ok, data, error } = await RCDA.safeFetch(STUD_API() + '/student/payments/cart', {
    method: 'POST', body: { course_ids: PAY_COURSE_IDS }
  });
  if (btn) btn.disabled = false;
  if (!ok) { RCDA.showToast(error || 'Could not start payment.'); return; }

  if (data.checkout_url) {
    // Send the student straight to PayMongo's hosted page (GCash / Card / OTC).
    window.location.href = data.checkout_url;
    return;
  }
  RCDA.showToast('Payment started but no redirect was returned.', 'error');
}

/* After PayMongo redirects back (success_url), confirm the payment automatically. */
function handlePayMongoReturn(){
  const params = new URLSearchParams(window.location.search);
  const session = params.get('checkout_session_id') || params.get('session') || params.get('payment_id');
  if (!session) return;

  (async () => {
    const { ok, data } = await RCDA.safeFetch(STUD_API() + '/student/payments/return?session=' + encodeURIComponent(session));
    if (ok && data.verified) {
      RCDA.showToast(data.already ? 'Payment already confirmed.' : 'Payment confirmed — your enrollment is now active!', 'success');
      await Promise.all([loadStudentEnroll(), loadStudentPayments(), loadStudentProfile()]);
    } else {
      RCDA.showToast((data && data.message) || 'Payment not confirmed yet — it will activate shortly.', 'info');
    }
    history.replaceState({}, document.title, window.location.pathname);
  })();
}

/* ---- INIT ---- */
document.addEventListener('DOMContentLoaded', async () => {
  if (!await requireAuth('student')) return;
  handlePayMongoReturn();

  const sp = document.getElementById('studProfileBody');
  if (sp) sp.innerHTML = rcdaLoading('Loading your profile…');
  const ss = document.getElementById('studSessionsBody');
  if (ss) ss.innerHTML = rcdaLoading('Loading your sessions…');
  const sr = document.getElementById('studResultsBody');
  if (sr) sr.innerHTML = rcdaLoading('Loading your results…');
  const spay = document.getElementById('studPaymentBody');
  if (spay) spay.innerHTML = rcdaLoading('Loading your payments…');
  const se = document.getElementById('studEnrollBody');
  if (se) se.innerHTML = rcdaLoading('Loading courses…');

  try {
    await Promise.all([
      loadStudentProfile(),
      loadStudentSessions(),
      loadStudentResults(),
      loadStudentPayments(),
      loadStudentEnroll(),
    ]);
  } catch (e) {
    console.error('[RCDA student]', e);
    RCDA.showToast('Something went wrong while loading this page. Please refresh.');
  }
});
