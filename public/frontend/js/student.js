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
  enroll: 'Enroll', payment: 'Payment', requests: 'Requests',
  settings: 'Account Settings'
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
  if (panel === 'sessions') renderBookWizard();
  if (panel === 'requests') loadStudentRequests();
  if (panel === 'myassess') loadStudentResults().catch(e => console.error('[RCDA student] results', e));
  if (panel === 'settings' && window.RCDAProfile) {
    const c = document.getElementById('studSettingsPanel');
    if (c && !c.dataset.rpLoaded) {
      RCDAProfile.render({ container: c, mode: 'self' });
      c.dataset.rpLoaded = '1';
    }
  }
  if (typeof rememberPanel === 'function') rememberPanel(panel);
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
  return `<span class="badge ${cls}">${escapeHtml(label)}</span>`;
}

function classBadge(cls) {
  const map = {
    fast: ['fast', '⚡ Fast Learner'],
    average: ['avg', '📊 Average Learner'],
    avg: ['avg', '📊 Average Learner'],
    needs: ['needs', '⚠ Needs Improvement'],
  };
  const [c, l] = map[cls] || ['', '—'];
  return `<span class="badge ${c}">${escapeHtml(l)}</span>`;
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
  const body = document.getElementById('studProfileBody');
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

  if (body) body.innerHTML = `
    <div class="card">
      <div style="display:flex;align-items:center;gap:16px;margin-bottom:20px;">
        <div style="width:60px;height:60px;border-radius:50%;background:linear-gradient(135deg,var(--orange),#E35D4B);display:flex;align-items:center;justify-content:center;color:white;font-weight:800;font-size:20px;font-family:'Poppins';box-shadow:0 6px 16px rgba(192,57,43,0.3);">${initials}</div>
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
let SESSIONS_CACHE = { schedules: [], bookedIds: [], bookings: [], hasUpcoming: false };

async function loadStudentSessions() {
  const API = STUD_API();
  const [dash, sched] = await Promise.all([
    RCDA.safeFetch(API + '/student/dashboard'),
    RCDA.safeFetch(API + '/student/schedules'),
  ]);
  const bookings = (dash.ok && dash.data.bookings) || [];
  const enrollments = (dash.ok && dash.data.enrollments) || [];
  const schedules = (sched.ok && sched.data.schedules) || [];
  const bookedIds = (sched.ok && sched.data.bookedIds) || [];

  const isUpcoming = (b) => b.date && b.time_start
    ? new Date(b.date + 'T' + b.time_start).getTime() >= Date.now()
    : false;
  const hasUpcoming = bookings.some(isUpcoming);

  // Show a booking tab for every course type the student has an active
  // (or completed) enrollment in — so PDC appears right after they pay it,
  // even if no open session has been scheduled yet.
  const activeTypes = [...new Set(
    enrollments
      .filter(e => e.status === 'active' || e.status === 'completed')
      .map(e => e.course && e.course.type)
      .filter(t => t === 'TDC' || t === 'PDC')
  )];

  SESSIONS_CACHE = { schedules, bookedIds, bookings, hasUpcoming, activeTypes, tdc: (sched.ok && sched.data.tdc) || null };

  const bookedRows = bookings.length ? bookings.map(b => {
    const dateRange = b.end_date && b.end_date !== b.date
      ? `${b.date} – ${b.end_date}`
      : (b.date || '—');
    const sessionLabel = b.end_date ? '2-day block' : '1-on-1 lesson';
    return `
    <tr>
      <td>${escapeHtml(b.course ? b.course.name : '—')}</td>
      <td>${escapeHtml(sessionLabel)}</td>
      <td>${escapeHtml(dateRange)}</td>
      <td>${escapeHtml((b.time_start || '') + (b.time_end ? ' – ' + b.time_end : ''))}</td>
      <td>${escapeHtml(b.instructor_name || 'To be assigned')}</td>
      <td>${escapeHtml(b.venue || '—')}</td>
      <td>${b.status === 'pending'
        ? `<span class="badge pending">Pending acceptance</span>`
        : (b.cancelable
          ? `<button class="btn btn-sm" style="border:1px solid #dc2626;color:#dc2626;background:#fff;" onclick="cancelBooking(${b.schedule_id})">Cancel</button>`
          : `<span class="badge confirmed">Booked</span>`)}</td>
    </tr>`; }).join('')
    : '<tr><td colspan="7" style="text-align:center;color:var(--gray);padding:18px;">No sessions booked yet.</td></tr>';

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
      <div class="card-head"><h3>Book a Session</h3><span style="font-size:13px;color:var(--gray);">Pick a course, then choose an open slot</span></div>
      <div id="bookWizard"></div>
    </div>`;

  renderBookWizard();
}

/* Booking wizard: course-first. TDC -> weekly grid across all instructors.
   PDC -> pick an open date, then see instructors available that day. */
function renderBookWizard() {
  const host = document.getElementById('bookWizard');
  if (!host) return;
  const { hasUpcoming, activeTypes } = SESSIONS_CACHE;
  const types = (activeTypes && activeTypes.length) ? activeTypes : [];
  if (!types.length) {
    host.innerHTML = '<div style="font-size:13px;color:var(--gray);padding:8px 0;">Enroll and pay for a course to book a session.</div>';
    return;
  }

  const courseTabs = types.map(t => `<button class="btn btn-sm ${SESSIONS_CACHE._type === t ? 'btn-primary' : 'btn-ghost'}" onclick="setBookType('${t}')">${t === 'TDC' ? 'TDC (Theory)' : 'PDC (Practical)'}</button>`).join(' ');
  if (!SESSIONS_CACHE._type || !types.includes(SESSIONS_CACHE._type)) SESSIONS_CACHE._type = types[0];

  let inner = '';
  if (SESSIONS_CACHE._type === 'TDC') {
    inner = renderTdcWeek();
  } else {
    if (!SESSIONS_CACHE._pdcMonth) {
      const now = new Date();
      SESSIONS_CACHE._pdcMonth = now.getFullYear() + '-' + String(now.getMonth() + 1).padStart(2, '0');
    }
    if (SESSIONS_CACHE._pdcLoadedMonth !== SESSIONS_CACHE._pdcMonth) {
      SESSIONS_CACHE._pdcLoadedMonth = SESSIONS_CACHE._pdcMonth;
      loadPdcCalendar();
    }
    inner = renderPdcCalendar();
  }

  host.innerHTML = `
    <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:14px;">${courseTabs}</div>
    ${SESSIONS_CACHE._type === 'PDC' && hasUpcoming ? '<div class="enroll-note" style="margin:0 0 12px;">You’re already booked for an upcoming PDC lesson. Cancel it from “My Booked Sessions” first if you want to pick a different one.</div>' : ''}
    ${inner}`;
}

function setBookType(t) { SESSIONS_CACHE._type = t; renderBookWizard(); }

/* Local YYYY-MM-DD for a Date (avoids UTC shifting the day). */
function ymdLocal(d) {
  return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');
}

/* TDC is a group class completed with one fixed 2-day block. Several blocks
   are shown each week, with seats taken, so a learner can pick the week that
   suits them. */
function renderTdcWeek() {
  const { schedules, bookedIds } = SESSIONS_CACHE;
  const tdc = schedules.filter(s => s.course && s.course.type === 'TDC' && s.end_date);
  const info = SESSIONS_CACHE.tdc || { required: 1, booked: 0, remaining: 1 };

  if (!tdc.length) {
    return '<div style="font-size:13px;color:var(--gray);padding:8px 0;">No TDC class blocks are open right now. New blocks are published every week — check back soon.</div>';
  }

  // Group blocks by the Monday of their week.
  const groups = new Map();
  tdc.forEach(s => {
    const d = new Date(String(s.date).slice(0, 10) + 'T00:00:00');
    d.setDate(d.getDate() - ((d.getDay() + 6) % 7));
    const key = ymdLocal(d);
    if (!groups.has(key)) groups.set(key, []);
    groups.get(key).push(s);
  });

  const today = new Date(); today.setHours(0, 0, 0, 0);
  const thisMonday = (() => {
    const d = new Date(today);
    d.setDate(d.getDate() - ((d.getDay() + 6) % 7));
    return ymdLocal(d);
  })();

  const monthDay = (iso) => new Date(iso + 'T00:00:00')
    .toLocaleDateString('en-PH', { month: 'short', day: 'numeric' });

  const blockCard = (s) => {
    const start = String(s.date).slice(0, 10);
    const end = String(s.end_date).slice(0, 10);
    const booked = bookedIds.includes(s.id);
    const enrolled = Number(s.enrolled != null ? s.enrolled : (s.booked || 0));
    const cap = Number(s.max_students || 0);
    const full = s.full || (cap > 0 && enrolled >= cap);
    const done = !booked && info.remaining <= 0;
    const seatsLeft = Math.max(0, cap - enrolled);

    let action;
    if (booked) action = '<span class="badge confirmed">Booked ✓</span>';
    else if (full) action = '<span class="badge" style="background:#fee2e2;color:#b91c1c;">Full</span>';
    else if (done) action = '<span class="badge" style="background:#ede9fe;color:#5b21b6;">TDC class already booked</span>';
    else action = `<button class="btn btn-sm btn-primary" onclick="bookSession(${s.id})">Book this block</button>`;

    const pct = cap ? Math.min(100, Math.round((enrolled / cap) * 100)) : 0;
    const barColor = full ? '#dc2626' : (pct >= 75 ? '#f59e0b' : '#16a34a');

    return `<div style="border:1px solid var(--gray-light);border-radius:12px;padding:12px;background:var(--cream);display:flex;flex-direction:column;gap:6px;">
      <div style="display:flex;justify-content:space-between;gap:8px;align-items:baseline;">
        <strong style="font-size:13.5px;">${escapeHtml(s.course.name)}</strong>
        <span style="font-size:11.5px;color:var(--gray);">${cap ? `${enrolled}/${cap} enrolled` : ''}</span>
      </div>
      <div style="font-size:12.5px;color:var(--charcoal);font-weight:600;">${monthDay(start)} – ${monthDay(end)} <span style="font-weight:400;color:var(--gray);">(2 days)</span></div>
      <div style="font-size:11.5px;color:var(--gray);">${escapeHtml((s.time_start || '') + (s.time_end ? ' – ' + s.time_end : ''))} · ${escapeHtml(s.venue || '')}</div>
      <div style="font-size:11.5px;color:var(--gray);">Instructor: ${escapeHtml(s.instructor && s.instructor.name ? s.instructor.name : 'to be assigned')}</div>
      <div style="height:6px;border-radius:99px;background:var(--gray-light);overflow:hidden;">
        <div style="height:100%;width:${pct}%;background:${barColor};"></div>
      </div>
      <div style="font-size:11.5px;color:${full ? '#b91c1c' : 'var(--gray)'};">${full ? 'No seats left' : `${seatsLeft} seat${seatsLeft === 1 ? '' : 's'} left`}</div>
      <div style="margin-top:2px;">${action}</div>
    </div>`;
  };

  const sections = [...groups.keys()].sort().map(key => {
    const weekStart = new Date(key + 'T00:00:00');
    const weekEnd = new Date(weekStart); weekEnd.setDate(weekStart.getDate() + 6);
    const label = key === thisMonday ? 'This week' : 'Week of';
    const blocks = groups.get(key)
      .sort((a, b) => String(a.date).localeCompare(String(b.date)))
      .map(blockCard).join('');

    return `<div style="margin-bottom:16px;">
      <div style="display:flex;align-items:baseline;gap:8px;margin-bottom:8px;">
        <strong style="font-size:13.5px;">${label} ${monthDay(key)} – ${monthDay(ymdLocal(weekEnd))}</strong>
        <span style="font-size:11.5px;color:var(--gray);">Mon–Tue · Wed–Thu · Fri–Sat</span>
      </div>
      <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:10px;">${blocks}</div>
    </div>`;
  }).join('');

  return `
    <div style="border:1px solid var(--gray-light);border-radius:12px;padding:12px 14px;background:#fff;margin-bottom:16px;">
      <div style="display:flex;justify-content:space-between;flex-wrap:wrap;gap:8px;align-items:center;">
        <div>
          <strong style="font-size:13.5px;">TDC class progress</strong>
          <div style="font-size:11.5px;color:var(--gray);">TDC is one 2-day class block (2 class days). Pick any open block below.</div>
        </div>
        <div style="font-size:13px;font-weight:800;color:${info.remaining === 0 ? '#16a34a' : 'var(--charcoal)'};">${info.booked} of ${info.required} booked</div>
      </div>
      <div style="display:flex;gap:6px;margin-top:10px;">
        ${Array.from({ length: info.required }, (_, i) => `<div style="flex:1;height:8px;border-radius:99px;background:${i < info.booked ? '#16a34a' : 'var(--gray-light)'};"></div>`).join('')}
      </div>
      ${info.remaining === 0
        ? '<div style="font-size:12px;color:#16a34a;margin-top:8px;font-weight:600;">Your TDC class block is booked — see you in class!</div>'
        : '<div style="font-size:12px;color:var(--gray);margin-top:8px;">Pick one open block to complete your TDC class.</div>'}
    </div>
    ${sections}
    <p style="font-size:12px;color:var(--gray);margin-top:4px;">TDC is a group class — book one 2-day block and it is confirmed instantly. Your instructor is assigned before the first class day.</p>`;
}

function renderPdcCalendar() {
  const { schedules } = SESSIONS_CACHE;
  const pdc = schedules.filter(s => s.course && s.course.type === 'PDC');
  const days = SESSIONS_CACHE._pdcDays || {};
  const preferred = SESSIONS_CACHE._pdcPreferred || null;
  if (!pdc.length && !Object.keys(days).length) return '<div style="font-size:13px;color:var(--gray);">No open PDC sessions scheduled right now. Check back soon — a green day means an instructor has an open slot.</div>';

  if (!SESSIONS_CACHE._pdcMonth) {
    const now = new Date();
    SESSIONS_CACHE._pdcMonth = now.getFullYear() + '-' + String(now.getMonth() + 1).padStart(2, '0');
  }

  const [y, m] = SESSIONS_CACHE._pdcMonth.split('-').map(Number);
  const today = new Date(); today.setHours(0, 0, 0, 0);
  const monthStart = new Date(y, m - 1, 1);
  const firstDow = monthStart.getDay();
  const daysInMonth = new Date(y, m, 0).getDate();

  const cellHtml = [];
  for (let i = 0; i < firstDow; i++) cellHtml.push('<div class="cal-cell cal-empty"></div>');
  for (let d = 1; d <= daysInMonth; d++) {
    const date = new Date(y, m - 1, d); date.setHours(0, 0, 0, 0);
    const key = ymdLocal(date);
    const isPast = date < today;
    const isSunday = date.getDay() === 0;
    const status = days[key] || (isSunday ? 'sunday' : null);
    const isGreen = status === 'available' && !isPast;
    const isTaken = status === 'taken' && !isPast;
    const isPref = status === 'preference' && !isPast;
    const isSel = SESSIONS_CACHE._pdcDay === key;

    const cls = ['cal-cell'];
    if (isSunday) cls.push('cal-sunday');
    else if (isPast) cls.push('cal-past');
    else if (isGreen) cls.push('cal-green');
    else if (isTaken) cls.push('cal-taken');
    else if (isPref) cls.push('cal-pref');
    if (isSel) cls.push('cal-sel');

    const label = (isGreen || isPref)
      ? `<button class="cal-day-btn" onclick="setPdcDay('${key}')">${d}</button>`
      : `<span class="cal-day">${d}</span>`;
    cellHtml.push(`<div class="${cls.join(' ')}">${label}</div>`);
  }

  const dow = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'].map(x => `<div class="cal-dow">${x}</div>`).join('');
  const prevDisabled = (y === today.getFullYear() && m === today.getMonth() + 1);
  const prevBtn = prevDisabled ? '<button class="btn btn-sm btn-ghost" disabled>‹ Prev</button>'
    : `<button class="btn btn-sm btn-ghost" onclick="shiftPdcMonth(-1)">‹ Prev</button>`;

  const dayPanel = SESSIONS_CACHE._pdcLoading
    ? '<div style="font-size:13px;color:var(--gray);">Loading instructors…</div>'
    : renderPdcDayInstructors();

  return `
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:8px;">
      <strong>${monthStart.toLocaleString('default', { month: 'long' })} ${y}</strong>
      <div style="display:flex;gap:6px;">${prevBtn}<button class="btn btn-sm btn-ghost" onclick="shiftPdcMonth(1)">Next ›</button></div>
    </div>
    ${preferred ? `<div class="enroll-note" style="margin:0 0 8px;">Showing availability for a <b>${preferred}</b> instructor. <span style="color:var(--gray);">Change it in Requests.</span></div>` : ''}
    <div class="cal-grid">${dow}${cellHtml.join('')}</div>
    <div style="display:flex;flex-wrap:wrap;gap:12px;font-size:12px;color:var(--gray);margin:8px 0 4px;">
      <span><span class="cal-dot cal-dot-green"></span>Available</span>
      <span><span class="cal-dot cal-dot-red"></span>Taken</span>
      ${preferred ? `<span><span class="cal-dot cal-dot-white"></span>Open, no ${preferred} instructor</span>` : ''}
      <span><span class="cal-dot cal-dot-gray"></span>Sunday (closed)</span>
      <span><span class="cal-dot cal-dot-muted"></span>Past</span>
    </div>
    <div id="pdcDayPanel" style="margin-top:10px;">${dayPanel}</div>`;
}

function renderPdcDayInstructors() {
  const list = SESSIONS_CACHE._pdcInstructors || [];
  const date = SESSIONS_CACHE._pdcDay;
  if (date == null) {
    return '<div style="font-size:13px;color:var(--gray);">Pick a green (free) or white (open, outside your preference) day to see instructors.</div>';
  }
  if (!list.length) {
    return `<div style="font-size:13px;color:var(--gray);">No PDC instructor is free for the 9–11 AM slot on ${formatDate(date)} — that day is fully booked. Try another day.</div>`;
  }

  const preferred = SESSIONS_CACHE._pdcPreferred || null;
  const hasMatch = list.some(s => s.matches_preference);
  const warning = preferred && !hasMatch
    ? `<div class="enroll-note" style="margin:0 0 10px;background:#fff8e6;border-color:#f1d18a;color:#7a5a00;">No <b>${preferred}</b> instructor is free on ${formatDate(date)}. You can still request one of the instructors below or pick another day.</div>`
    : '';

  const rec = SESSIONS_CACHE._pdcRecommended;
  const recName = rec ? rec.instructor_name : (list[0] ? list[0].instructor_name : '');
  const recGender = rec && rec.gender ? ' (' + rec.gender + ')' : '';
  const autoBtn = rec
    ? `<button class="btn btn-primary" style="width:100%;justify-content:center;margin-bottom:10px;" onclick="requestPdcBooking(null, '${date}')">Request this day — ${escapeHtml(recName)}${escapeHtml(recGender)} recommended</button>`
    : '';

  const rows = list.map(s => `<tr>
      <td>${escapeHtml(s.instructor_name)}</td>
      <td>${escapeHtml(s.gender ? (s.gender.charAt(0).toUpperCase() + s.gender.slice(1)) : '—')}</td>
      <td>${escapeHtml(s.specialization ? (s.specialization + ' instructor') : 'Instructor')}${s.matches_preference ? '' : ' <span style="font-size:11px;color:#92400e;">(outside preference)</span>'}</td>
      <td><button class="btn btn-sm btn-ghost" onclick="requestPdcBooking(${s.instructor_id}, '${date}')">Request</button></td>
    </tr>`).join('');

  return `${warning}${autoBtn}
    <div style="overflow-x:auto;">
      <table><thead><tr><th>Instructor</th><th>Gender</th><th>Specialization</th><th></th></tr></thead>
      <tbody>${rows}</tbody></table>
    </div>
    <p style="font-size:12px;color:var(--gray);margin-top:8px;">The academy assigns the recommended instructor when you request the day. The admin confirms the slot and can swap the instructor if needed.</p>`;
}

async function requestPdcBooking(instructorId, date) {
  const body = { preferred_date: date };
  if (instructorId) body.preferred_instructor_id = instructorId;

  const { ok, error, data } = await RCDA.safeFetch(STUD_API() + `/student/requests/booking`, {
    method: 'POST',
    body: JSON.stringify(body)
  });
  if (!ok) {
    RCDA.showToast(error || 'We couldn’t send that request. Please try again.');
    return;
  }
  const msg = (data && data.message) || 'Booking request sent. The admin will confirm your PDC slot.';
  RCDA.showToast(msg, 'success');
  SESSIONS_CACHE._pdcDay = null;
  SESSIONS_CACHE._pdcInstructors = [];
  SESSIONS_CACHE._pdcRecommended = null;
  renderBookWizard();
  loadPdcCalendar();
}

function shiftPdcMonth(dir) {
  const [y, m] = SESSIONS_CACHE._pdcMonth.split('-').map(Number);
  let ny = y, nm = m + dir;
  if (nm < 1) { nm = 12; ny--; } else if (nm > 12) { nm = 1; ny++; }
  const now = new Date(); now.setHours(0, 0, 0, 0);
  if (ny < now.getFullYear() || (ny === now.getFullYear() && nm < now.getMonth() + 1)) return;
  SESSIONS_CACHE._pdcMonth = ny + '-' + String(nm).padStart(2, '0');
  SESSIONS_CACHE._pdcDay = null;
  SESSIONS_CACHE._pdcInstructors = [];
  SESSIONS_CACHE._pdcRecommended = null;
  renderBookWizard();
  loadPdcCalendar();
}

async function setPdcDay(date) {
  SESSIONS_CACHE._pdcDay = date;
  SESSIONS_CACHE._pdcInstructors = [];
  SESSIONS_CACHE._pdcRecommended = null;
  SESSIONS_CACHE._pdcLoading = true;
  renderBookWizard();
  const r = await RCDA.safeFetch(STUD_API() + `/student/schedules/pdc-day?date=${date}`);
  SESSIONS_CACHE._pdcLoading = false;
  const data = r && r.data ? r.data : null;
  SESSIONS_CACHE._pdcInstructors = (data && data.instructors) || [];
  SESSIONS_CACHE._pdcRecommended = (data && data.recommended) || null;
  if (data && data.preferred_instructor_gender !== undefined) {
    SESSIONS_CACHE._pdcPreferred = data.preferred_instructor_gender || null;
  }
  renderBookWizard();
}

async function loadPdcCalendar() {
  const r = await RCDA.safeFetch(STUD_API() + `/student/schedules/pdc-calendar?month=${SESSIONS_CACHE._pdcMonth}`);
  const data = r && r.data ? r.data : null;

  const days = {};
  ((data && data.days) || []).forEach(d => { if (d && d.date) days[d.date] = d.status; });
  SESSIONS_CACHE._pdcDays = days;
  SESSIONS_CACHE._pdcGreen = (data && data.green_days) || [];
  SESSIONS_CACHE._pdcPreferred = (data && data.preferred_instructor_gender) || null;
  renderBookWizard();
}

async function bookSession(id) {
  const { ok, error, data } = await RCDA.safeFetch(STUD_API() + `/student/schedules/${id}/book`, { method: 'POST' });
  if (!ok) { RCDA.showToast(error || 'We couldn’t book that session. Please try again.'); return; }
  const msg = (data && data.message)
    ? data.message
    : 'Booking submitted — the admin must accept the instructor before your slot is confirmed.';
  RCDA.showToast(msg, 'success');
  loadStudentSessions();
}

async function cancelBooking(id) {
  const { ok, error } = await RCDA.safeFetch(STUD_API() + `/student/schedules/${id}/book`, { method: 'DELETE' });
  if (!ok) { RCDA.showToast(error || 'We couldn’t cancel that booking. Please try again.'); return; }
  RCDA.showToast('Booking cancelled. You can book another session.', 'success');
  loadStudentSessions();
}

/* ---- RESULTS ---- */
let STUD_ASSESSMENTS = [];
let STUD_SELECTED_ASSESSMENT = 0;
let STUDENT_CERTS = [];
let STUDENT_CURRENT_CERT = null;

function assessmentScoreText(a) {
  if (!a) return '—';
  if (a.score != null) return Math.round(Number(a.score)) + '%';
  if (a.exam_score != null) return Math.round(Number(a.exam_score)) + '% exam';
  return '—';
}

function renderStarCategories(cats) {
  return (cats || []).map(c => {
    const n = Number(c.score) || 0;
    const unrated = !n;
    const stars = '★★★★★'.split('').map((s, i) =>
      `<span style="color:${i < n ? '#f59e0b' : '#d1d5db'};font-size:15px;">★</span>`).join('');
    return `<div style="display:flex;align-items:center;justify-content:space-between;padding:7px 0;border-bottom:1px solid var(--gray-light);">
      <span style="font-size:13px;font-weight:600;color:var(--charcoal);">${escapeHtml(c.label)}</span>
      <span style="display:flex;align-items:center;gap:8px;">${unrated ? '<span style="font-size:12px;color:var(--gray);">Not rated</span>' : stars}${c.note ? `<span style="font-size:11.5px;color:var(--gray);">${escapeHtml(c.note)}</span>` : ''}</span>
    </div>`;
  }).join('');
}

function renderTrainingSuggestions(sugs) {
  return (sugs && sugs.length) ? sugs.map(s => `
    <div style="display:flex;gap:10px;padding:10px 0;border-bottom:1px solid var(--gray-light);align-items:flex-start;">
      <span style="font-size:16px;line-height:1.2;">📘</span>
      <div><b style="font-size:13px;color:var(--charcoal);">${escapeHtml(s.label || 'Training')}</b><br>
      <span style="font-size:12.5px;color:var(--gray);">${escapeHtml(s.module || '')}</span></div>
    </div>`).join('')
    : '<div style="font-size:12px;color:var(--gray);">No extra training needed — great work!</div>';
}

/* One assessment, fully expanded. Used for the default (latest) result and
   whenever the student clicks a row in the history list. */
function assessmentSummaryHtml(a) {
  if (!a) {
    return '<div style="text-align:center;padding:20px 0;color:var(--gray);">No assessments yet.</div>';
  }

  const isTdc = a.course_type === 'TDC';
  const scoreNum = a.score != null ? Math.round(Number(a.score)) : null;
  const examNum = a.exam_score != null ? Math.round(Number(a.exam_score)) : null;
  const confidenceNum = a.confidence_score != null ? Math.round(Number(a.confidence_score) * 100) : null;
  const ringPct = scoreNum != null ? scoreNum : (confidenceNum || 0);
  const passBadge = a.passed != null
    ? (a.passed
        ? '<span class="badge enrolled">Passed</span>'
        : '<span class="badge needs">Not yet passed</span>')
    : '';
  const rated = (a.categories || []).filter(c => Number(c.score) > 0).length;

  return `
    <div style="padding:20px 0;">
      <div style="text-align:center;font-size:12.5px;font-weight:700;color:var(--gray);text-transform:uppercase;letter-spacing:.5px;">
        ${escapeHtml(a.course_name || 'Assessment')}${a.course_type ? ' · ' + escapeHtml(a.course_type) : ''}
      </div>
      <div style="text-align:center;font-size:11.5px;color:var(--gray);margin-top:4px;">
        ${a.date ? 'Session ' + escapeHtml(formatDate(a.date)) : ''}${a.assessed_at ? (a.date ? ' · ' : '') + 'Assessed ' + escapeHtml(formatDate(a.assessed_at)) : ''}
      </div>
      <div style="display:flex;align-items:center;justify-content:center;gap:8px;flex-wrap:wrap;margin-top:10px;">
        ${classBadge(a.classification)}${passBadge}
      </div>
      <div class="score-ring-wrap" style="margin:14px auto 0;">
        <svg viewBox="0 0 160 160">
          <circle class="score-ring-bg" cx="80" cy="80" r="68"/>
          <circle class="score-ring-fill" cx="80" cy="80" r="68" stroke-dasharray="427.26" stroke-dashoffset="${Math.round(427.26 * (1 - (ringPct / 100)))}"/>
        </svg>
        <div class="score-ring-label"><span class="num">${ringPct}%</span><span class="cap">${scoreNum != null ? 'Score' : 'Confidence'}</span></div>
      </div>
      ${confidenceNum != null ? `<p style="font-size:12px;color:var(--gray);text-align:center;margin-top:6px;">AI confidence: ${confidenceNum}%</p>` : ''}
      <p style="font-size:14px;margin-top:16px;text-align:center;">${escapeHtml(a.recommendation || 'Your instructor will advise next steps.')}</p>
      ${a.feedback ? `<div style="margin-top:14px;padding:12px 14px;background:var(--cream);border:1px solid var(--gray-light);border-radius:12px;font-size:13px;color:var(--gray);"><b style="color:var(--charcoal);">Instructor feedback:</b> ${escapeHtml(a.feedback)}</div>` : ''}
      ${isTdc
        ? `<div style="margin-top:14px;text-align:center;font-size:13.5px;">Theory exam score: <b>${examNum != null ? examNum + '%' : '—'}</b> <span style="color:var(--gray);">(passing mark 75%)</span></div>`
        : `<div style="margin-top:14px;">
             ${renderStarCategories(a.categories)}
             <p style="font-size:11.5px;color:var(--gray);margin-top:8px;">Steering, braking and side-mirror observation · ${rated}/3 skills rated</p>
           </div>
           <div class="card-head" style="margin-top:18px;"><h3>Suggested Training</h3></div>
           <div>${renderTrainingSuggestions(a.suggestions)}</div>`}
    </div>`;
}

/* Clicking a history row swaps the summary card to that assessment. */
function viewStudentAssessment(index) {
  const a = STUD_ASSESSMENTS[index];
  if (!a) return;
  STUD_SELECTED_ASSESSMENT = index;

  const summary = document.getElementById('studAssessSummary');
  if (summary) summary.innerHTML = assessmentSummaryHtml(a);

  const rows = document.querySelectorAll('[data-assessment-index]');
  rows.forEach(row => {
    row.classList.toggle('selected', Number(row.getAttribute('data-assessment-index')) === index);
  });
}

function assessmentHistoryHtml(a, i) {
  const aPass = a.passed == null ? '' : (a.passed ? ' · Passed' : ' · Not yet passed');
  const skills = a.course_type === 'PDC'
    ? ` · ${(a.categories || []).filter(c => Number(c.score) > 0).length}/3 skills rated`
    : ' · Exam only';
  const selected = i === STUD_SELECTED_ASSESSMENT;
  return `
    <div class="session-row assessment-row${selected ? ' selected' : ''}" data-assessment-index="${i}" role="button" tabindex="0"
         onclick="viewStudentAssessment(${i})"
         onkeydown="if(event.key==='Enter'||event.key===' '){event.preventDefault();viewStudentAssessment(${i});}"
         title="View this assessment">
      <div class="session-dot done"></div>
      <div><b style="font-size:13.5px;">${escapeHtml(a.course_name || 'Session')}${a.course_type ? ' · ' + escapeHtml(a.course_type) : ''}</b><br>
      <span style="font-size:12px;color:var(--gray);">${classBadge(a.classification)} · ${assessmentScoreText(a)}${aPass}${skills}${a.assessed_at ? ' · ' + escapeHtml(formatDate(a.assessed_at)) : ''}</span></div>
    </div>`;
}

async function loadStudentResults() {
  const { ok, data } = await RCDA.safeFetch(STUD_API() + '/student/progress');
  const body = document.getElementById('studResultsBody');
  if (!ok) { if (body) body.innerHTML = '<div class="card">We couldn’t load this right now. Please refresh.</div>'; return; }

  const assessments = data.assessments || [];
  const certs = data.certifications || [];

  STUD_ASSESSMENTS = assessments;
  STUDENT_CERTS = certs;
  // Keep the student's selection across reloads when it still exists.
  if (STUD_SELECTED_ASSESSMENT >= assessments.length) STUD_SELECTED_ASSESSMENT = 0;

  const selected = assessments[STUD_SELECTED_ASSESSMENT] || assessments[0] || null;

  const list = assessments.length ? assessments.map((a, i) => assessmentHistoryHtml(a, i)).join('')
    : '<div style="font-size:12px;color:var(--gray);">No assessment records.</div>';

  const certHtml = certs.length ? certs.map((c, i) => `
    <div style="display:flex;justify-content:space-between;align-items:center;gap:10px;padding:10px 0;border-bottom:1px solid var(--gray-light);font-size:13.5px;">
      <span>${escapeHtml(c.course ? c.course.name : 'Course')}</span>
      <div style="display:flex;align-items:center;gap:8px;flex-shrink:0;">
        ${statusBadge(c.status)}
        <button class="btn btn-sm btn-ghost" onclick="viewStudentCertificate(${i})">View Certificate</button>
      </div>
    </div>`).join('')
    : '<div style="font-size:12px;color:var(--gray);">No certificates issued yet.</div>';

  if (body) body.innerHTML = `
    <div class="two-col" style="align-items:start;">
      <div class="card">
        <div class="card-head"><h3>My Assessment Results</h3></div>
        <div id="studAssessSummary">${assessmentSummaryHtml(selected)}</div>
      </div>
      <div class="card">
        <div class="card-head">
          <h3>Progress Over Sessions</h3>
          ${assessments.length > 1 ? '<p style="font-size:12px;color:var(--gray);margin-top:3px;">Select a session to view its full result.</p>' : ''}
        </div>
        <div style="display:flex;flex-direction:column;gap:12px;">${list}</div>
        <div class="card-head" style="margin-top:18px;"><h3>Certifications</h3></div>
        ${certHtml}
      </div>
    </div>`;
}

/* ---- CERTIFICATE VIEWER / PRINTER (student) ---- */
const STUDENT_CERT_CSS = `
.rcda-cert{position:relative;background:#fffdf8;border:2px solid #C0392B;border-radius:8px;padding:34px 42px 30px;text-align:center;color:#1F1F1F;font-family:'Poppins','Segoe UI',sans-serif;box-shadow:0 12px 34px rgba(0,0,0,.10);}
.rcda-cert.revoked{opacity:.72;}
.rcda-cert .cert-logo{width:62px;height:62px;object-fit:cover;border-radius:50%;margin:0 auto 10px;display:block;}
.rcda-cert .cert-brand{font-size:12px;letter-spacing:3px;text-transform:uppercase;color:#C0392B;font-weight:700;}
.rcda-cert h1{font-size:27px;margin:12px 0 6px;letter-spacing:.5px;}
.rcda-cert .cert-sub{font-size:12.5px;color:#707070;margin:6px 0;}
.rcda-cert .cert-name{font-size:31px;font-weight:800;border-bottom:2px solid #E7E5E2;padding:0 10px 10px;margin:6px auto 10px;max-width:560px;}
.rcda-cert .cert-course{font-size:19px;font-weight:700;color:#C0392B;margin:8px 0 22px;}
.rcda-cert .cert-meta{display:flex;justify-content:center;gap:34px;flex-wrap:wrap;margin-top:12px;padding-top:18px;border-top:1px solid #E7E5E2;}
.rcda-cert .cert-meta div{min-width:140px;}
.rcda-cert .cert-meta span{display:block;font-size:10.5px;text-transform:uppercase;letter-spacing:1.4px;color:#707070;margin-bottom:3px;}
.rcda-cert .cert-meta b{font-size:13.5px;}
.rcda-cert .cert-foot{font-size:11px;color:#707070;margin-top:22px;}
.rcda-cert .cert-revoked{position:absolute;top:38%;left:50%;transform:translate(-50%,-50%) rotate(-18deg);font-size:58px;font-weight:800;color:rgba(192,57,43,.16);letter-spacing:8px;border:6px solid rgba(192,57,43,.16);padding:4px 22px;border-radius:12px;pointer-events:none;}
@media print{body{margin:0;}.rcda-cert{border-radius:0;box-shadow:none;}}
`;

function ensureStudentCertStyles() {
  if (document.getElementById('student-cert-style')) return;
  const s = document.createElement('style');
  s.id = 'student-cert-style';
  s.textContent = STUDENT_CERT_CSS;
  document.head.appendChild(s);
}

function certLongDate(value) {
  if (!value) return '—';
  const d = new Date(value);
  if (isNaN(d.getTime())) return '—';
  return d.toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' });
}

function studentCertSheet(cert) {
  const logo = new URL('../images/RCDA logo.jpg', window.location.href).href;
  const courseName = (cert.course && cert.course.name) || 'Driving Course';
  const revoked = cert.status === 'revoked';

  return `<div class="rcda-cert${revoked ? ' revoked' : ''}" id="studentCertSheet">
    <img src="${logo}" alt="RC Driving Academy" class="cert-logo" onerror="this.style.display='none'">
    <div class="cert-brand">RC Driving Academy</div>
    <h1>Certificate of Completion</h1>
    <div class="cert-sub">This certifies that</div>
    <div class="cert-name">${escapeHtml(cert.student_name || 'Student')}</div>
    <div class="cert-sub">has successfully completed</div>
    <div class="cert-course">${escapeHtml(courseName)}</div>
    <div class="cert-meta">
      <div><span>Certificate No.</span><b>${escapeHtml(cert.certificate_no || '—')}</b></div>
      <div><span>Date Issued</span><b>${escapeHtml(certLongDate(cert.issued_at))}</b></div>
      <div><span>Issued By</span><b>${escapeHtml(cert.issued_by || 'RC Driving Academy')}</b></div>
    </div>
    ${revoked ? '<div class="cert-revoked">REVOKED</div>' : ''}
    <div class="cert-foot">Verify this certificate with RC Driving Academy using the certificate number above.</div>
  </div>`;
}

function viewStudentCertificate(index) {
  const cert = STUDENT_CERTS[index];
  if (!cert) { RCDA.showToast('Certificate not available.', 'error'); return; }

  STUDENT_CURRENT_CERT = cert;
  ensureStudentCertStyles();

  const body = document.getElementById('studentCertBody');
  if (body) body.innerHTML = studentCertSheet(cert);

  const title = document.getElementById('studentCertTitle');
  if (title) title.textContent = 'Certificate ' + (cert.certificate_no || '');

  openModal('studentCertModal');
}

function printStudentCertificate() {
  const sheet = document.getElementById('studentCertSheet');
  if (!sheet) return;

  const win = window.open('', '_blank');
  if (!win) {
    RCDA.showToast('Allow pop-ups to print your certificate.', 'error');
    return;
  }

  const docTitle = STUDENT_CURRENT_CERT && STUDENT_CURRENT_CERT.certificate_no
    ? STUDENT_CURRENT_CERT.certificate_no
    : 'Certificate';

  win.document.write('<!DOCTYPE html><html><head><meta charset="utf-8"><title>' + escapeHtml(docTitle) + '</title>'
    + '<style>@page{size:A4 landscape;margin:14mm;}' + STUDENT_CERT_CSS + '</style></head><body>'
    + sheet.outerHTML + '</body></html>');
  win.document.close();
  win.focus();
  setTimeout(() => win.print(), 400);
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

  const alreadyActive = c => c && c.enrolled && (c.enrollment_status === 'active' || c.enrollment_status === 'completed');
  const selected = [];
  if (ENROLL_SELECTED.tdc) {
    const c = courses.find(x => x.id === ENROLL_SELECTED.tdc);
    if (!alreadyActive(c)) selected.push(ENROLL_SELECTED.tdc);
  }
  Object.keys(ENROLL_SELECTED.pdc).forEach(id => {
    const cid = parseInt(id, 10);
    const c = courses.find(x => x.id === cid);
    if (ENROLL_SELECTED.pdc[id] && !alreadyActive(c)) selected.push(cid);
  });

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
  const alreadyActive = c => c && c.enrolled && (c.enrollment_status === 'active' || c.enrollment_status === 'completed');
  const selected = [];
  if (ENROLL_SELECTED.tdc) {
    const c = (data.courses || []).find(x => x.id === ENROLL_SELECTED.tdc);
    if (!alreadyActive(c)) selected.push(ENROLL_SELECTED.tdc);
  }
  Object.keys(ENROLL_SELECTED.pdc).forEach(id => {
    const cid = parseInt(id, 10);
    const c = (data.courses || []).find(x => x.id === cid);
    if (ENROLL_SELECTED.pdc[id] && !alreadyActive(c)) selected.push(cid);
  });
  if (!selected.length) { RCDA.showToast('Your selected courses are already active — nothing to pay for.'); return; }

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
    // Remember the session id so the payment can be confirmed on return —
    // PayMongo does not append it to the success_url.
    if (data.session_id) {
      try { localStorage.setItem('rcda_pending_session', JSON.stringify({ id: data.session_id, at: Date.now() })); } catch (e) {}
    }
    window.location.href = data.checkout_url;
    return;
  }
  RCDA.showToast('Payment started but no redirect was returned.', 'error');
}

async function processFakePay(){
  if (!PAY_COURSE_IDS.length) { RCDA.showToast('No courses to pay for.'); return; }
  const btn = document.getElementById('payModalPayBtn');
  if (btn) btn.disabled = true;

  const { ok, data, error } = await RCDA.safeFetch(STUD_API() + '/student/payments/cart', {
    method: 'POST', body: { course_ids: PAY_COURSE_IDS, fake: true }
  });
  if (btn) btn.disabled = false;
  if (!ok) { RCDA.showToast(error || 'Could not process demo payment.'); return; }

  closeModal('payModal');
  RCDA.showToast('Demo payment applied — your enrollment is now active!', 'success');
  await Promise.all([loadStudentEnroll(), loadStudentPayments(), loadStudentProfile(), loadStudentSessions()]);
}

/* After PayMongo redirects back (success_url), confirm the payment automatically. */
function handlePayMongoReturn(){
  const params = new URLSearchParams(window.location.search);
  let session = params.get('checkout_session_id') || params.get('session') || params.get('payment_id');

  // Fall back to a remembered session id (recent only) if PayMongo did not
  // include one in the redirect URL.
  if (!session) {
    try {
      const saved = JSON.parse(localStorage.getItem('rcda_pending_session') || 'null');
      if (saved && saved.id && (Date.now() - (saved.at || 0)) < 86400000) session = saved.id;
    } catch (e) {}
  }
  if (!session) return;

  (async () => {
    const { ok, data } = await RCDA.safeFetch(STUD_API() + '/student/payments/return?session=' + encodeURIComponent(session));
    if (ok && data.verified) {
      try { localStorage.removeItem('rcda_pending_session'); } catch (e) {}
      RCDA.showToast(data.already ? 'Payment already confirmed.' : 'Payment confirmed — your enrollment is now active!', 'success');
      await Promise.all([loadStudentEnroll(), loadStudentPayments(), loadStudentProfile(), loadStudentSessions()]);
    } else {
      RCDA.showToast((data && data.message) || 'Payment not confirmed yet — it will activate shortly.', 'info');
    }
    history.replaceState({}, document.title, window.location.pathname);
  })();
}

/* ---- REQUESTS (instructor preference + reschedule) ---- */
async function loadStudentRequests() {
  const [reqs, sched] = await Promise.all([
    RCDA.safeFetch(STUD_API() + '/student/requests'),
    RCDA.safeFetch(STUD_API() + '/student/schedules'),
  ]);
  const list = (reqs.ok && reqs.data && reqs.data.requests) || [];
  const preferredGender = (reqs.ok && reqs.data && reqs.data.preferred_instructor_gender) || null;
  const schedules = (sched.ok && sched.data.schedules) || [];
  const bookedIds = (sched.ok && sched.data.bookedIds) || [];

  const reqRows = list.length ? list.map(r => {
    const typeLabel = r.type === 'reschedule' ? 'Reschedule' : (r.type === 'booking' ? 'PDC booking' : 'Instructor preference');
    const detail = r.type === 'reschedule'
      ? `${r.from_schedule ? formatDate(r.from_schedule.date) : '?'} → ${r.to_schedule ? formatDate(r.to_schedule.date) : '?'}`
      : (r.type === 'booking'
        ? `${r.preferred_date ? formatDate(r.preferred_date) : '?'} · ${r.preferred_instructor ? r.preferred_instructor.name : 'instructor to be assigned'}`
        : `Preferred: ${r.preferred_gender || '—'}`);
    const badge = r.status === 'pending' ? 'pending' : (r.status === 'accepted' ? 'verified' : 'needs');
    return `<div class="req-card">
      <h4>${escapeHtml(typeLabel)}</h4>
      <div class="req-meta">${escapeHtml(detail)}${r.note ? ' · ' + escapeHtml(r.note) : ''}</div>
      <div class="req-row"><span class="badge ${badge}">${escapeHtml(r.status.charAt(0).toUpperCase() + r.status.slice(1))}</span>
      ${r.admin_note ? `<span class="req-note">Admin: ${escapeHtml(r.admin_note)}</span>` : ''}</div>
      ${r.status === 'pending' ? `<button class="btn btn-sm btn-ghost" onclick="cancelRequest(${r.id})">Cancel request</button>` : ''}
    </div>`;
  }).join('')
    : '<div class="req-card" style="color:var(--gray);font-size:13px;">No requests yet.</div>';

  const availOpts = reschedTargetOptions('');
  SESSIONS_CACHE._schedAll = schedules;
  SESSIONS_CACHE._bookedIds = bookedIds;

  const body = document.getElementById('studRequestsBody');
  if (body) body.innerHTML = `
    <div class="card">
      <div class="card-head"><h3>Instructor Preference</h3><span style="font-size:13px;color:var(--gray);">Saved instantly — no approval needed</span></div>
      <div class="pref-opts" id="prefOpts">
        <div class="pref-opt ${preferredGender === 'male' ? 'sel' : ''}" data-v="male" onclick="pickPref(this)">Male</div>
        <div class="pref-opt ${preferredGender === 'female' ? 'sel' : ''}" data-v="female" onclick="pickPref(this)">Female</div>
        <div class="pref-opt ${!preferredGender ? 'sel' : ''}" data-v="any" onclick="pickPref(this)">No preference</div>
      </div>
      <p style="font-size:12px;color:var(--gray);margin-top:8px;">Applies to PDC only. Green days have a free instructor of your preferred gender, white days are open but only for the other gender, and red days are fully booked.</p>
      <button class="btn btn-primary" style="margin-top:8px;width:100%;" onclick="submitInstructorPref()">Save Preference</button>
    </div>
    <div class="card" style="margin-top:18px;">
      <div class="card-head"><h3>Request a Reschedule</h3><span style="font-size:13px;color:var(--gray);">Pick a new open session</span></div>
      <label style="font-size:12.5px;color:var(--gray);">Your booked session</label>
      <select id="fromSched" class="sched-pick" onchange="syncRescheduleTargets()">
        <option value="">— select a booked session —</option>
        ${bookedIds.map(id => { const s = schedules.find(x => x.id === id); return s ? `<option value="${s.id}">${escapeHtml(s.course ? s.course.name : '')} · ${escapeHtml(s.date)} ${escapeHtml(s.time_start)}</option>` : ''; }).join('')}
      </select>
      <label style="font-size:12.5px;color:var(--gray);">Move to</label>
      <select id="toSched" class="sched-pick">${availOpts}</select>
      <textarea id="reschedNote" class="sched-pick" style="height:60px;" placeholder="Optional reason"></textarea>
      <button class="btn btn-primary" style="margin-top:8px;width:100%;" onclick="submitReschedule()">Submit Reschedule Request</button>
      <p style="font-size:12px;color:var(--gray);margin-top:8px;">Your current booking stays until admin accepts the request.</p>
    </div>
    <div class="card" style="margin-top:18px;">
      <div class="card-head"><h3>My Requests</h3></div>
      ${reqRows}
    </div>`;
}

/* Only offer sessions of the same course type as the booking being moved,
   so a PDC lesson can never be rescheduled into a TDC group block. */
function reschedTargetOptions(fromId) {
  const all = SESSIONS_CACHE._schedAll || [];
  const booked = SESSIONS_CACHE._bookedIds || [];
  const from = all.find(s => String(s.id) === String(fromId));
  if (!from) return '<option value="">— pick your booked session first —</option>';
  const type = from.course ? from.course.type : null;
  const opts = all
    .filter(s => !booked.includes(s.id) && !s.full && s.course && s.course.type === type)
    .map(s => `<option value="${s.id}">${escapeHtml(s.course.name)} · ${escapeHtml(s.date)} ${escapeHtml(s.time_start)} – ${escapeHtml(s.venue || '')}</option>`)
    .join('');
  return opts || `<option value="">No open ${escapeHtml(type || '')} sessions to move to</option>`;
}

function syncRescheduleTargets() {
  const from = document.getElementById('fromSched');
  const to = document.getElementById('toSched');
  if (!to) return;
  to.innerHTML = reschedTargetOptions(from ? from.value : '');
}

function pickPref(el) {
  document.querySelectorAll('#prefOpts .pref-opt').forEach(o => o.classList.remove('sel'));
  el.classList.add('sel');
}

async function submitInstructorPref() {
  const sel = document.querySelector('#prefOpts .pref-opt.sel');
  const pref = sel ? sel.dataset.v : 'any';
  const { ok, error, data } = await RCDA.safeFetch(STUD_API() + '/student/requests/instructor-preference', {
    method: 'POST', body: { preferred_gender: pref }
  });
  if (!ok) { RCDA.showToast(error || 'Could not save preference.'); return; }

  // The calendar is cached per month — drop it and repaint the booking wizard
  // so green/white days update straight away (even if it is on another panel).
  SESSIONS_CACHE._pdcPreferred = (data && data.preferred_instructor_gender) || null;
  SESSIONS_CACHE._pdcDays = {};
  SESSIONS_CACHE._pdcLoadedMonth = null;
  RCDA.showToast((data && data.message) || 'Preference saved.', 'success');
  renderBookWizard();
  loadStudentRequests();
}

async function submitReschedule() {
  const from = document.getElementById('fromSched') ? document.getElementById('fromSched').value : '';
  const to = document.getElementById('toSched') ? document.getElementById('toSched').value : '';
  const note = document.getElementById('reschedNote') ? document.getElementById('reschedNote').value : '';
  if (!from || !to) { RCDA.showToast('Select both your current and new session.'); return; }
  const { ok, error } = await RCDA.safeFetch(STUD_API() + '/student/requests/reschedule', {
    method: 'POST', body: { from_schedule_id: Number(from), schedule_id: Number(to), note }
  });
  if (!ok) { RCDA.showToast(error || 'Could not submit reschedule request.'); return; }
  RCDA.showToast('Reschedule requested. Admin will confirm.', 'success');
  loadStudentRequests();
}

async function cancelRequest(id) {
  const { ok, error } = await RCDA.safeFetch(STUD_API() + `/student/requests/${id}`, { method: 'DELETE' });
  if (!ok) { RCDA.showToast(error || 'Could not cancel request.'); return; }
  RCDA.showToast('Request cancelled.', 'success');
  loadStudentRequests();
}

/* ---- AI SUPPORT CHATBOT ---- */
let CHAT_LOADED = false;

function toggleChat() {
  const panel = document.getElementById('chatPanel');
  if (!panel) return;
  const open = panel.style.display === 'none' || !panel.style.display;
  panel.style.display = open ? 'flex' : 'none';
  if (open && !CHAT_LOADED) { loadChat(); CHAT_LOADED = true; }
  const log = document.getElementById('chatLog');
  if (log) log.scrollTop = log.scrollHeight;
}

function chatBubble(sender, text) {
  const log = document.getElementById('chatLog');
  if (!log) return;
  const div = document.createElement('div');
  div.className = 'chat-msg ' + (sender === 'user' ? 'user' : 'bot');
  div.textContent = text;
  log.appendChild(div);
  log.scrollTop = log.scrollHeight;
}

async function loadChat() {
  const { ok, data } = await RCDA.safeFetch(STUD_API() + '/student/chat');
  const log = document.getElementById('chatLog');
  if (!log) return;
  if (ok && data.messages && data.messages.length) {
    data.messages.forEach(m => chatBubble(m.sender === 'student' ? 'user' : 'bot', m.body));
  } else {
    chatBubble('bot', "Hi! I'm the RCDA support bot. Ask me what to do after signing up, or about enrolling, TDC/PDC, payments, bookings, rescheduling, instructor preferences, or certificates — or type 'talk to human' to reach our admin team.");
  }
}

async function sendChat(e) {
  e.preventDefault();
  const input = document.getElementById('chatText');
  if (!input || !input.value.trim()) return;
  const text = input.value.trim();
  input.value = '';
  chatBubble('user', text);
  const { ok, data, error } = await RCDA.safeFetch(STUD_API() + '/student/chat', { method: 'POST', body: { body: text } });
  if (!ok) { chatBubble('bot', 'Sorry, I could not respond right now. Please try again.'); return; }
  (data.messages || []).forEach(m => { if (m.sender !== 'student') chatBubble('bot', m.body); });
}

/* ---- VALID ID (dashboard reminder + inline card) ---- */
let STUD_ID_STATUS = 'none';

async function loadStudentIdCard() {
  const panel = document.getElementById('studIdPanel');
  if (panel && window.RCDAProfile && !panel.dataset.rpMounted) {
    RCDAProfile.renderIdCard({ container: panel });
    panel.dataset.rpMounted = '1';
  }
  await refreshStudentIdStatus();
}

async function refreshStudentIdStatus() {
  const { ok, data } = await RCDA.safeFetch(STUD_API() + '/student/id-document');
  if (!ok || !data) return;

  const doc = data.id || {};
  STUD_ID_STATUS = doc.status || 'none';
  renderIdReminder(doc);
}

function renderIdReminder(doc) {
  const banner = document.getElementById('idReminderBanner');
  if (!banner) return;

  const status = doc.status || 'none';
  const title = document.getElementById('idReminderTitle');
  const text = document.getElementById('idReminderText');
  const btn = document.getElementById('idReminderBtn');

  // Verified students don't need a prompt — the card on the profile panel
  // still shows the approved file and its review date.
  if (status === 'approved') {
    banner.style.display = 'none';
    return;
  }

  banner.style.display = '';
  banner.classList.toggle('is-bad', status === 'rejected');

  if (status === 'pending') {
    if (title) title.textContent = 'Your ID is being reviewed';
    if (text) text.textContent = 'Thanks for uploading. The academy will verify it shortly — you can keep enrolling and booking while you wait.';
    if (btn) btn.textContent = 'View submission';
  } else if (status === 'rejected') {
    if (title) title.textContent = 'Your ID needs another look';
    if (text) {
      const reason = doc.rejection_reason || 'The copy we received was not clear enough.';
      text.textContent = reason + (/upload/i.test(reason) ? '' : ' Please upload a clearer photo or PDF.');
    }
    if (btn) btn.textContent = 'Re-upload valid ID';
  } else {
    if (title) title.textContent = 'Verify your identity';
    if (text) text.textContent = 'Upload a valid government ID so the academy can verify your account. This does not block your lessons — you can enrol and book while it is being reviewed.';
    if (btn) btn.textContent = 'Upload valid ID';
  }
}

function goToIdUpload() {
  // Keep the sidebar highlight in sync when jumping here from the banner.
  const navItem = document.querySelector('.sidebar-nav .nav-item[onclick*="showPanel(\'profile\'"]');
  showPanel('profile', navItem || undefined);

  const panel = document.getElementById('studIdPanel');
  if (!panel) return;

  setTimeout(() => {
    const card = panel.querySelector('.rp-id-card');
    const file = panel.querySelector('input[type=file]');
    const target = card || panel;
    if (target && target.scrollIntoView) target.scrollIntoView({ behavior: 'smooth', block: 'center' });
    if (file) file.focus({ preventScroll: true });
  }, 250);
}

// The shared profile panel fires this after a successful upload so the
// dashboard banner updates without a page reload.
document.addEventListener('rcda:id-updated', () => { refreshStudentIdStatus(); });

/* Re-open the sidebar tab the student was last on (see rememberPanel). */
function restoreStudentPanel() {
  const panel = typeof panelFromHash === 'function' ? panelFromHash() : '';
  if (!panel || !document.getElementById('stud-' + panel)) return;
  showPanel(panel, typeof navItemForPanel === 'function' ? navItemForPanel(panel) : null);
}

/* ---- INIT ---- */
document.addEventListener('DOMContentLoaded', async () => {
  RCDA.showPageLoading('Loading…');
  if (!await requireAuth('student')) return;
  handlePayMongoReturn();

  try {
    await loadStudentProfile();
  } catch (e) {
    console.error('[RCDA student]', e);
    RCDA.showToast('Something went wrong while loading this page. Please refresh.');
  } finally {
    RCDA.hidePageLoading();
  }
  restoreStudentPanel();
  loadStudentSessions().catch(e => console.error('[RCDA student] sessions', e));
  loadStudentResults().catch(e => console.error('[RCDA student] results', e));
  loadStudentPayments().catch(e => console.error('[RCDA student] payments', e));
  loadStudentEnroll().catch(e => console.error('[RCDA student] enroll', e));
  loadStudentIdCard().catch(e => console.error('[RCDA student] id', e));
});
