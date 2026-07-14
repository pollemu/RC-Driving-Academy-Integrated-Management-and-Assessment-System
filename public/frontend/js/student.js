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
  sessions: 'My Sessions', myassess: 'My Results', payment: 'Payment'
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

  const bookedRows = bookings.length ? bookings.map(b => `
    <tr>
      <td>${escapeHtml(b.course ? b.course.name : '—')}</td>
      <td>—</td>
      <td>${escapeHtml(b.date || '—')}</td>
      <td>${escapeHtml((b.time_start || '') + (b.time_end ? ' – ' + b.time_end : ''))}</td>
      <td>${escapeHtml(b.instructor_name || '—')}</td>
      <td>—</td>
      <td><span class="badge confirmed">Booked</span></td>
    </tr>`).join('')
    : '<tr><td colspan="7" style="text-align:center;color:var(--gray);padding:18px;">No sessions booked yet.</td></tr>';

  const availRows = schedules.length ? schedules.map(s => {
    const full = s.booked >= s.max_students;
    const isBooked = bookedIds.includes(s.id);
    const btn = isBooked ? '<span class="badge enrolled">Booked</span>'
      : full ? '<span class="badge">Full</span>'
      : `<button class="btn btn-sm btn-primary" onclick="bookSession(${s.id})">Book</button>`;
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
      <div style="overflow-x:auto;">
        <table><thead><tr><th>Course</th><th>Date</th><th>Time</th><th>Venue</th><th>Instructor</th><th>Slots</th><th></th></tr></thead>
        <tbody>${availRows}</tbody></table>
      </div>
    </div>`;
}

async function bookSession(id) {
  const { ok, error } = await RCDA.safeFetch(STUD_API() + `/student/schedules/${id}/book`, { method: 'POST' });
  if (!ok) { RCDA.showToast(error || 'Could not book session.'); return; }
  RCDA.showToast('Session booked!', 'success');
  loadStudentSessions();
}

/* ---- RESULTS ---- */
async function loadStudentResults() {
  const { ok, data } = await RCDA.safeFetch(STUD_API() + '/student/progress');
  const body = document.getElementById('studResultsBody');
  if (!ok) { if (body) body.innerHTML = '<div class="card">Could not load results.</div>'; return; }

  const assessments = data.assessments || [];
  const certs = data.certifications || [];
  const latest = assessments[0];

  const summary = latest ? `
    <div style="text-align:center;padding:20px 0;">
      ${classBadge(latest.classification)}
      <div class="score-ring-wrap">
        <svg viewBox="0 0 160 160">
          <circle class="score-ring-bg" cx="80" cy="80" r="68"/>
          <circle class="score-ring-fill" cx="80" cy="80" r="68" stroke-dasharray="427.26" stroke-dashoffset="${Math.round(427.26 * (1 - (Number(latest.confidence_score) || 0)))}"/>
        </svg>
        <div class="score-ring-label"><span class="num">${Math.round((Number(latest.confidence_score) || 0) * 100)}%</span><span class="cap">Confidence</span></div>
      </div>
      <p style="font-size:14px;margin-top:16px;">${escapeHtml(latest.recommendation || 'Your instructor will advise next steps.')}</p>
    </div>`
    : '<div style="text-align:center;padding:20px 0;color:var(--gray);">No assessments yet.</div>';

  const list = assessments.length ? assessments.map(a => `
    <div class="session-row">
      <div class="session-dot done"></div>
      <div><b style="font-size:13.5px;">${escapeHtml(a.course_name || 'Session')}</b><br>
      <span style="font-size:12px;color:var(--gray);">${classBadge(a.classification)} · ${Math.round((Number(a.confidence_score) || 0) * 100)}% confidence</span></div>
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
  const duesRows = dues.length ? dues.map(e => `
    <tr>
      <td>${escapeHtml(e.course ? e.course.name : 'Course')}</td>
      <td><b>${money(e.course ? e.course.fee : 0)}</b></td>
      <td>${statusBadge(e.status)}</td>
      <td><button class="btn btn-sm btn-primary" onclick="payEnrollment(${e.id})">Pay Now</button></td>
    </tr>`).join('')
    : '<tr><td colspan="4" style="text-align:center;color:var(--gray);padding:18px;">No pending dues. 🎉</td></tr>';

  const body = document.getElementById('studPaymentBody');
  if (body) body.innerHTML = `
    <div class="payment-summary">
      <div class="pay-stat"><img src="../images/increased-revenue.png" alt="Total Paid" class="pay-stat-icon"><div class="pay-stat-text"><div class="label">Total Paid</div><div class="amount" style="color:#16a34a;">${money(totalPaid)}</div><div class="hint">Verified payments</div></div></div>
      <div class="pay-stat"><img src="../images/pending-payment.png" alt="Balance" class="pay-stat-icon"><div class="pay-stat-text"><div class="label">Balance</div><div class="amount">${money(balance)}</div><div class="hint">${balance > 0 ? 'Outstanding' : 'Fully paid'}</div></div></div>
      <div class="pay-stat"><img src="../images/payment-day.png" alt="Next Payment" class="pay-stat-icon"><div class="pay-stat-text"><div class="label">Pending Dues</div><div class="amount" style="color:var(--gray);">${dues.length}</div><div class="hint">${dues.length ? 'Awaiting payment' : 'None'}</div></div></div>
    </div>
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

async function payEnrollment(id) {
  const { ok, error } = await RCDA.safeFetch(STUD_API() + `/student/payments/${id}`, {
    method: 'POST',
    body: { method: 'gcash', reference_no: 'RC' + Date.now() }
  });
  if (!ok) { RCDA.showToast(error || 'Payment failed.'); return; }
  RCDA.showToast('Payment submitted. Awaiting admin verification.', 'success');
  loadStudentPayments();
}

/* ---- INIT ---- */
document.addEventListener('DOMContentLoaded', async () => {
  if (!await requireAuth('student')) return;
  try {
    await Promise.all([
      loadStudentProfile(),
      loadStudentSessions(),
      loadStudentResults(),
      loadStudentPayments(),
    ]);
  } catch (e) {
    console.error('[RCDA student]', e);
    RCDA.showToast('Some of your data failed to load.');
  }
});
