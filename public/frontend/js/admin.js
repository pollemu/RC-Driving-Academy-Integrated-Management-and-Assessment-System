/* ============================================================
   admin.js — loaded ONLY on admin/dashboard.html
   ============================================================ */

const ADMIN_TITLES = {
  dashboard:'Dashboard', students:'Students', schedule:'Schedule',
  attendance:'Instructor Attendance', instructors:'Instructors',
  payments:'Payments', certifications:'Certifications', reports:'Reports',
  notifications:'Notifications', bookings:'Pending Bookings', requests:'Student Requests', ai:'Grading AI',
  idverification:'ID Verification',
  profile:'My Profile', settings:'Account Settings'
};

function showPanel(panel, el){
  document.querySelectorAll('.panel').forEach(p => p.classList.remove('active'));
  const target = document.getElementById('admin-' + panel);
  if (target) target.classList.add('active');

  const titleEl = document.getElementById('adminTitle');
  if (titleEl && ADMIN_TITLES[panel]) titleEl.textContent = ADMIN_TITLES[panel];

  if (el) {
    const parentNav = el.closest ? el.closest('.sidebar-nav') : null;
    if (parentNav) parentNav.querySelectorAll('.nav-item').forEach(n => n.classList.remove('active'));
    if (el.classList) el.classList.add('active');
  }
  closeAllSidebars();

  if (panel === 'schedule') { highlightToday(); loadAdminSchedules(); }
  if (panel === 'reports') loadReports();
  if (panel === 'notifications') { loadNotifications(); loadNotifSummary(); }
  if (panel === 'requests') loadAdminRequests();
  if (panel === 'idverification') loadIdVerifications();
  if (panel === 'certifications') loadCertifications();
  if (panel === 'profile') {
    if (window.RCDAProfile) {
      const c = document.getElementById('adminProfileSummary');
      if (c && !c.dataset.rpLoaded) {
        RCDAProfile.renderSummary({ container: c });
        c.dataset.rpLoaded = '1';
      }
    }
  }
  if (panel === 'settings') {
    if (window.RCDAProfile) {
      const c = document.getElementById('adminSettingsPanel');
      if (c && !c.dataset.rpLoaded) {
        RCDAProfile.render({ container: c, mode: 'self' });
        c.dataset.rpLoaded = '1';
      }
    }
  }
  if (panel === 'students') loadStudents();
  if (panel === 'instructors') loadInstructors();
  if (panel === 'payments') loadPayments();
  if (panel === 'bookings') loadBookings();
  if (panel === 'attendance') loadInstructorAttendance();
  if (typeof rememberPanel === 'function') rememberPanel(panel);
}

/* Re-open the sidebar tab the admin was last on (see rememberPanel). */
function restoreAdminPanel() {
  const panel = typeof panelFromHash === 'function' ? panelFromHash() : '';
  if (!panel || !document.getElementById('admin-' + panel)) return;
  showPanel(panel, typeof navItemForPanel === 'function' ? navItemForPanel(panel) : null);
}

function markAttend(btn, type){
  const row = btn.closest('.attend-btns');
  if (!row) return;
  row.querySelectorAll('.attend-btn').forEach(b => {
    b.classList.remove('sel');
    if (b.classList.contains(type)) b.classList.add('sel');
  });
}

function sampleNLP(type){
  const el = document.getElementById('nlpInput');
  if (el) el.value = nlpData[type].text;
}

async function analyzeNLP(){
  const txt = (document.getElementById('nlpInput') || {}).value || '';
  if (!txt) return;

  const badge  = document.getElementById('nlpBadge');
  const score  = document.getElementById('nlpScore');
  const msg    = document.getElementById('nlpMsg');
  const tokens = document.getElementById('nlpTokens');
  const result = document.getElementById('nlpResult');

  const { ok, data } = await RCDA.safeFetch(`${RCDA_CONFIG.getApiBaseUrl()}/admin/nlp/classify`, {
    method: 'POST',
    body: { text: txt }
  });

  if (ok && data && data.classification) {
    const map = nlpData[data.classification] || nlpData.avg;
    if(badge){ badge.textContent = map.badge; badge.className = 'nlp-badge ' + map.cls; }
    if(score) score.textContent = Math.round((data.confidence || 0) * 100) + '%';
    if(msg)   msg.textContent = data.recommendation + ' — learned from ' + data.training_size + ' assessments';
    if(tokens) tokens.innerHTML = (data.tokens || []).map(t => `<span class="token-chip ${t.c}">${escapeHtml(t.t)}</span>`).join('') || '<span class="token-chip">—</span>';
    if(result) result.style.display = 'block';
  } else {
    if(badge){ badge.textContent = 'Untrained'; badge.className = 'nlp-badge'; }
    if(score) score.textContent = '—';
    if(msg)   msg.textContent = (data && data.recommendation) || 'The model has no training data yet.';
    if(tokens) tokens.innerHTML = '';
    if(result) result.style.display = 'block';
  }
}

function profileTab(tab){
  ['info','attend','assess','pay'].forEach(t => {
    const content = document.getElementById('ptab-' + t + '-content');
    const btn = document.getElementById('ptab-' + t);
    if (content) content.style.display = (t === tab ? 'block' : 'none');
    if (btn) btn.className = (t === tab ? 'btn btn-sm btn-primary' : 'btn btn-sm btn-ghost');
  });
}

/* ============================================================
   Weekly Schedule — highlight the column matching today's
   actual day of the week (0 = Sun ... 6 = Sat), so the
   "today" styling is never hardcoded to a fixed day.
   ============================================================ */
function highlightToday(){
  const todayIndex = new Date().getDay(); // 0=Sun ... 6=Sat
  document.querySelectorAll('.week-col[data-day]').forEach(col => {
    const isToday = Number(col.dataset.day) === todayIndex;
    col.classList.toggle('today', isToday);
  });
}

/* ============================================================
   Payments — verify or reject a pending payment. All actions are
   API-backed (see verifyPayment / rejectPayment below); the table
   is rendered from the server by loadPayments(), so there are no
   hard-coded demo rows to keep in sync.
   ============================================================ */

document.addEventListener('DOMContentLoaded', highlightToday);

/* ============================================================
   API-backed data loading (real Laravel backend)
   ============================================================ */
/* Cached instructor list for the Assign Instructor dropdown. */
let ADMIN_INSTRUCTORS = [];

/* Cached session list — used to show how full each TDC block already is. */
let ADMIN_SCHEDULES = [];

document.addEventListener('DOMContentLoaded', async () => {
  RCDA.showPageLoading('Loading…');
  const ok = await requireAuth('admin');
  if (!ok) return;

  // Same pattern as the student dashboard: keep the splash up only until the
  // default panel (Dashboard) has its numbers, then let the side panels fill
  // in from the background.
  try {
    await loadDashboardStats();
  } finally {
    RCDA.hidePageLoading();
  }
  restoreAdminPanel();
  Promise.allSettled([
    loadStudents(),
    loadPayments(),
    loadInstructors(),
    loadCertifications(),
    loadInstructorList(),
    loadBookings(),
    loadInstructorAttendance(),
    loadAdminSchedules(),
    loadNotifSummary(),
  ]);
});

async function loadInstructorList(){
  const { ok, data } = await RCDA.safeFetch(`${RCDA_CONFIG.getApiBaseUrl()}/admin/instructors?per_page=100`, { method: 'GET' });
  if (ok && data) ADMIN_INSTRUCTORS = data.instructors || [];
}

/* ---- SCHEDULES (admin creates sessions + assigns instructor) ---- */
let ADMIN_COURSES = [];

async function loadAdminCourses() {
  const { ok, data } = await RCDA.safeFetch(`${RCDA_CONFIG.getApiBaseUrl()}/admin/courses`, { method: 'GET' });
  ADMIN_COURSES = (ok && data && data.courses) || [];
  const sel = document.getElementById('schedCourse');
  if (sel) sel.innerHTML = ADMIN_COURSES.map(c => `<option value="${c.id}">${escapeHtml(c.name)} (${escapeHtml((c.type || '').toUpperCase())})</option>`).join('');
  syncScheduleForm();
}

/* TDC blocks always start Monday, Wednesday or Friday, run 08:00–12:00 and
   hold a whole class. PDC stays a single 1-on-1 slot. */
function syncScheduleForm() {
  const courseId = (document.getElementById('schedCourse') || {}).value;
  const course = ADMIN_COURSES.find(c => String(c.id) === String(courseId));
  const isTdc = course && String(course.type).toUpperCase() === 'TDC';

  const hint = document.getElementById('schedDateHint');
  if (hint) hint.textContent = '';

  const dateInput = document.getElementById('schedDate');
  const picker = document.getElementById('schedBlockPicker');
  const dateCell = document.getElementById('schedDateCell');
  const dateLabel = document.getElementById('schedDateLabel');
  if (dateInput) dateInput.style.display = isTdc ? 'none' : 'block';
  if (picker) picker.style.display = isTdc ? 'block' : 'none';
  if (dateLabel) dateLabel.textContent = isTdc ? 'Class block' : 'Date';
  // The 2-day picker needs the full row — three chips do not fit in one cell.
  if (dateCell) dateCell.style.gridColumn = isTdc ? '1 / -1' : '';

  const cap = document.getElementById('schedCap');
  if (cap) cap.value = isTdc ? 20 : 1;

  if (isTdc) {
    const start = document.getElementById('schedStart');
    const end = document.getElementById('schedEnd');
    if (start) start.value = '08:00';
    if (end) end.value = '12:00';
    syncTdcBlockPicker();
  }
}

/* ---- TDC block quick-pick: Mon–Tue / Wed–Thu / Fri–Sat for 4 weeks ---- */
const ADMIN_BLOCK_WEEKS = 4;
let ADMIN_BLOCK_WEEK = 0;

function adminBlockYmd(date) {
  return date.getFullYear() + '-' + String(date.getMonth() + 1).padStart(2, '0')
    + '-' + String(date.getDate()).padStart(2, '0');
}

function adminBlockAddDays(date, days) {
  const next = new Date(date);
  next.setDate(next.getDate() + days);
  return next;
}

function adminBlockMonday(date) {
  const monday = new Date(date);
  monday.setHours(0, 0, 0, 0);
  monday.setDate(monday.getDate() - ((monday.getDay() + 6) % 7));
  return monday;
}

function adminBlockShort(date) {
  return date.toLocaleDateString('en-PH', { month: 'short', day: 'numeric' });
}

function adminBlockWeekMonday() {
  return adminBlockAddDays(adminBlockMonday(new Date()), ADMIN_BLOCK_WEEK * 7);
}

/* Keep the hidden date input and the visible week in sync. */
function syncTdcBlockPicker() {
  const input = document.getElementById('schedDate');
  const today = new Date(); today.setHours(0, 0, 0, 0);

  let selected = input && input.value ? new Date(input.value + 'T00:00:00') : null;
  const valid = selected && !isNaN(selected) && [1, 3, 5].includes(selected.getDay()) && selected >= today;

  if (!valid) {
    // First block that has not started; roll into next week when this one is done.
    let monday = adminBlockMonday(today);
    let first = [0, 2, 4].map(offset => adminBlockAddDays(monday, offset)).find(day => day >= today);
    if (!first) {
      monday = adminBlockAddDays(monday, 7);
      first = monday;
    }
    if (input) input.value = adminBlockYmd(first);
    selected = first;
  }

  const daysAhead = Math.round((adminBlockMonday(selected) - adminBlockMonday(today)) / 86400000);
  ADMIN_BLOCK_WEEK = Math.max(0, Math.min(ADMIN_BLOCK_WEEKS - 1, Math.round(daysAhead / 7)));

  renderTdcBlockPicker();
}

function renderTdcBlockPicker() {
  const host = document.getElementById('schedBlockBtns');
  if (!host) return;

  const input = document.getElementById('schedDate');
  const selectedDate = input ? input.value : '';
  const monday = adminBlockWeekMonday();
  const today = new Date(); today.setHours(0, 0, 0, 0);
  const courseId = (document.getElementById('schedCourse') || {}).value;

  // Existing blocks for the selected TDC course, keyed by start date, so each
  // chip can show how many seats are already taken.
  const existing = {};
  ADMIN_SCHEDULES.forEach(s => {
    if (!s || s.status === 'cancelled') return;
    if (!s.course || String(s.course.type).toUpperCase() !== 'TDC') return;
    if (courseId && String(s.course.id) !== String(courseId)) return;
    existing[String(s.date).slice(0, 10)] = s;
  });

  host.innerHTML = [
    { offset: 0, label: 'Mon–Tue' },
    { offset: 2, label: 'Wed–Thu' },
    { offset: 4, label: 'Fri–Sat' },
  ].map(({ offset, label }) => {
    const start = adminBlockAddDays(monday, offset);
    const end = adminBlockAddDays(start, 1);
    const key = adminBlockYmd(start);
    const past = start < today;
    const selected = key === selectedDate;
    const block = existing[key];
    const seats = past
      ? 'Past'
      : (block
        ? `${block.booked || 0}/${block.max_students || 0} booked`
        : 'Open · no bookings');

    return `<button type="button" class="sched-block-chip${selected ? ' selected' : ''}" ${past ? 'disabled' : ''}
        onclick="pickTdcBlock('${key}')">
        <span class="chip-day">${label}</span>
        <span class="chip-range">${adminBlockShort(start)} – ${adminBlockShort(end)}</span>
        <span class="chip-seats">${seats}</span>
      </button>`;
  }).join('');

  const weekLabel = document.getElementById('schedBlockWeekLabel');
  if (weekLabel) {
    weekLabel.textContent = `Week of ${adminBlockShort(monday)} – ${adminBlockShort(adminBlockAddDays(monday, 6))}`;
  }

  const note = document.getElementById('schedBlockNote');
  if (note) {
    const chosen = existing[selectedDate];
    note.textContent = chosen
      ? `This block already exists — ${chosen.booked || 0}/${chosen.max_students || 0} booked. Cancel it in All Sessions if you need to replace it, or create a class at a different time.`
      : '';
  }

  const prev = document.getElementById('schedBlockPrev');
  const next = document.getElementById('schedBlockNext');
  if (prev) prev.disabled = ADMIN_BLOCK_WEEK <= 0;
  if (next) next.disabled = ADMIN_BLOCK_WEEK >= ADMIN_BLOCK_WEEKS - 1;
}

function pickTdcBlock(start) {
  const input = document.getElementById('schedDate');
  if (input) input.value = start;
  syncTdcBlockPicker();
}

function shiftBlockWeek(direction) {
  const next = Math.max(0, Math.min(ADMIN_BLOCK_WEEKS - 1, ADMIN_BLOCK_WEEK + direction));
  if (next === ADMIN_BLOCK_WEEK) return;
  ADMIN_BLOCK_WEEK = next;

  // Select the first block in the new week that has not started yet.
  const today = new Date(); today.setHours(0, 0, 0, 0);
  const monday = adminBlockWeekMonday();
  const first = [0, 2, 4].map(offset => adminBlockAddDays(monday, offset)).find(day => day >= today)
    || adminBlockAddDays(monday, 0);

  const input = document.getElementById('schedDate');
  if (input) input.value = adminBlockYmd(first);
  renderTdcBlockPicker();
}

async function loadAdminSchedules() {
  await loadAdminCourses();
  if (!ADMIN_INSTRUCTORS.length) {
    const { ok, data } = await RCDA.safeFetch(`${RCDA_CONFIG.getApiBaseUrl()}/admin/instructors?per_page=100`, { method: 'GET' });
    ADMIN_INSTRUCTORS = (ok && data && data.instructors) || [];
  }
  const instSel = document.getElementById('schedInstructor');
  if (instSel && instSel.dataset.loaded !== '1') {
    instSel.innerHTML = '<option value="">No instructor (assign later)</option>'
      + ADMIN_INSTRUCTORS.map(i => `<option value="${i.id}">${escapeHtml(i.name)}</option>`).join('');
    instSel.dataset.loaded = '1';
  }

  const { ok, data } = await RCDA.safeFetch(`${RCDA_CONFIG.getApiBaseUrl()}/admin/schedules`, { method: 'GET' });
  const tb = document.getElementById('adminSessionsTbody');
  if (!tb) return;
  const sched = (ok && data && data.schedules) || [];
  ADMIN_SCHEDULES = sched;
  renderTdcBlockPicker();
  if (!sched.length) {
    tb.innerHTML = '<tr><td colspan="8" style="text-align:center;color:var(--gray);padding:20px;">No sessions created yet.</td></tr>';
    return;
  }
  tb.innerHTML = sched.map(s => {
    const type = (s.course && s.course.type || '').toUpperCase();
    const isTdc = type === 'TDC';
    const slots = `${s.booked || 0}/${s.max_students || 0}`;
    const dateLabel = s.end_date && s.end_date !== s.date
      ? `${escapeHtml(String(s.date))} – ${escapeHtml(String(s.end_date))}`
      : escapeHtml(String(s.date || '—'));
    const hasInstructor = s.instructor && s.instructor.name;
    const instCell = hasInstructor
      ? escapeHtml(s.instructor.name)
      : (isTdc && s.status !== 'cancelled'
        ? `<select onchange="assignAdminInstructor(${s.id}, this.value)" style="padding:6px 8px;border-radius:8px;border:1.5px solid var(--gray-light);font-size:12.5px;background:var(--cream);">
             <option value="">Unassigned — assign…</option>
             ${ADMIN_INSTRUCTORS.map(i => `<option value="${i.id}">${escapeHtml(i.name)}</option>`).join('')}
           </select>`
        : 'Unassigned');
    return `<tr data-id="${s.id}">
      <td><b>${escapeHtml(s.course ? s.course.name : '—')}</b> <span class="type-badge" style="font-size:10px;font-weight:800;padding:2px 7px;border-radius:6px;color:#fff;background:${type === 'PDC' ? 'var(--orange)' : '#6366f1'};">${type}</span>${s.auto_generated ? ' <span style="font-size:10px;color:var(--gray);">auto</span>' : ''}</td>
      <td>${dateLabel}</td>
      <td>${escapeHtml(String(s.time_start || ''))}–${escapeHtml(String(s.time_end || ''))}</td>
      <td>${escapeHtml(s.venue || '—')}</td>
      <td>${instCell}</td>
      <td>${slots}</td>
      <td>${statusBadge(s.status)}</td>
      <td>${s.status !== 'cancelled' ? `<button class="btn btn-sm btn-danger" onclick="cancelAdminSchedule(${s.id})">Cancel</button>` : ''}</td>
    </tr>`;
  }).join('');
}

async function assignAdminInstructor(id, instructorId) {
  if (!instructorId) return;
  const { ok, error } = await RCDA.safeFetch(`${RCDA_CONFIG.getApiBaseUrl()}/admin/schedules/${id}/instructor`, {
    method: 'POST',
    body: { instructor_id: instructorId }
  });
  if (!ok) { RCDA.showToast(error || 'Could not assign instructor.', 'error'); return; }
  RCDA.showToast('Instructor assigned.', 'success');
  loadAdminSchedules();
}

async function createAdminSchedule() {
  const course_id = (document.getElementById('schedCourse') || {}).value;
  const instructor_id = (document.getElementById('schedInstructor') || {}).value;
  const date = (document.getElementById('schedDate') || {}).value;
  const time_start = (document.getElementById('schedStart') || {}).value;
  const time_end = (document.getElementById('schedEnd') || {}).value;
  const venue = (document.getElementById('schedVenue') || {}).value || 'RC Driving Academy — Main Branch';
  const max_students = (document.getElementById('schedCap') || {}).value || 10;

  const course = ADMIN_COURSES.find(c => String(c.id) === String(course_id));
  const isTdc = course && String(course.type).toUpperCase() === 'TDC';

  if (!course_id || !date || !time_start || !time_end) {
    RCDA.showToast('Fill course, date and time.', 'error');
    return;
  }
  if (!isTdc && !instructor_id) {
    RCDA.showToast('PDC is a 1-on-1 lesson — choose an instructor.', 'error');
    return;
  }
  if (new Date(date + 'T00:00:00').getDay() === 0) {
    RCDA.showToast('The academy is closed on Sundays. Pick a Monday–Saturday date.', 'error');
    return;
  }
  if (isTdc && ![1, 3, 5].includes(new Date(date + 'T00:00:00').getDay())) {
    RCDA.showToast('TDC blocks start on Monday, Wednesday or Friday.', 'error');
    return;
  }
  const { ok, error } = await RCDA.safeFetch(`${RCDA_CONFIG.getApiBaseUrl()}/admin/schedules`, {
    method: 'POST',
    body: { course_id, instructor_id: instructor_id || null, date, time_start, time_end, venue, max_students }
  });
  if (!ok) { RCDA.showToast(error || 'Could not create session.', 'error'); return; }
  RCDA.showToast(isTdc ? 'TDC class block created and opened for booking.' : 'Session created and opened for booking.', 'success');
  loadAdminSchedules();
}

async function cancelAdminSchedule(id) {
  const { ok, error } = await RCDA.safeFetch(`${RCDA_CONFIG.getApiBaseUrl()}/admin/schedules/${id}/cancel`, { method: 'POST' });
  if (!ok) { RCDA.showToast(error || 'Could not cancel session.', 'error'); return; }
  RCDA.showToast('Session cancelled.', 'success');
  loadAdminSchedules();
}

async function loadDashboardStats(){
  const { ok, data } = await RCDA.safeFetch(`${RCDA_CONFIG.getApiBaseUrl()}/admin/dashboard`, { method: 'GET' });
  if (!ok || !data) return;

  const s = data.stats || {};
  const cards = document.querySelectorAll('#admin-dashboard .stat-card');
  const money = (n) => '₱' + Number(n || 0).toLocaleString('en-PH', { maximumFractionDigits: 0 });

  const revenueSub = (function (now, before) {
    const a = Number(now || 0), b = Number(before || 0);
    if (!b) return 'No revenue last month';
    const pct = Math.round(((a - b) / b) * 100);
    if (pct === 0) return 'Same as last month';
    return (pct > 0 ? '↑ ' + pct + '%' : '↓ ' + Math.abs(pct) + '%') + ' vs last month';
  })(s.revenueMonth, s.revenueLastMonth);

  setStatCard(cards[0], s.students, (s.activeEnrollments || 0) + ' active enrollments', 'up');
  setStatCard(cards[1], s.schedulesToday, 'Across ' + (s.instructors || 0) + ' instructors', '');
  setStatCard(cards[2], money(s.revenueMonth), revenueSub, 'up');
  setStatCard(cards[3], s.pendingActions,
    (s.pendingPayments || 0) + ' payments · ' + (s.pendingBookings || 0) + ' bookings · ' + (s.pendingIdVerifications || 0) + ' IDs', 'warn');

  renderRecentEnrollments(data.recentEnrollments || []);
  renderProficiency(data.proficiency || {});
  renderCourseBreakdown(data.courseBreakdown || {});
}

function setStatCard(card, value, sub, tone){
  if (!card) return;
  const v = card.querySelector('.value');
  if (v && value !== undefined && value !== null) v.textContent = value;
  const s = card.querySelector('.sub');
  if (s && sub) {
    s.textContent = sub;
    s.className = 'sub' + (tone ? ' ' + tone : '');
  }
}

function renderRecentEnrollments(list){
  const tb = document.getElementById('recentEnrollmentsBody');
  if (!tb) return;
  tb.innerHTML = list.length ? list.map(e => {
    const status = String(e.status || '').toLowerCase();
    const badge = (status === 'active' || status === 'completed') ? 'enrolled' : 'pending';
    const label = status ? status.charAt(0).toUpperCase() + status.slice(1) : '—';
    const date = e.date ? new Date(e.date).toLocaleDateString('en-US', { month: 'short', day: 'numeric' }) : '—';
    return `<tr>
      <td><b>${escapeHtml(e.student)}</b></td>
      <td>${escapeHtml(e.course)}</td>
      <td>${escapeHtml(date)}</td>
      <td><span class="badge ${badge}">${escapeHtml(label)}</span></td>
    </tr>`;
  }).join('') : '<tr><td colspan="4" style="text-align:center;color:var(--gray);padding:18px;">No enrollments yet.</td></tr>';
}

function renderProficiency(p){
  const wrap = document.getElementById('proficiencyBars');
  if (!wrap) return;

  const total = (p.fast || 0) + (p.average || 0) + (p.needs || 0);
  const share = (n) => total ? Math.round((n / total) * 100) : 0;
  const rows = [
    ['Fast Learners', p.fast || 0, 'fast'],
    ['Average Learners', p.average || 0, 'avg'],
    ['Needs Improvement', p.needs || 0, 'needs'],
  ];

  wrap.innerHTML = rows.map(([label, count, cls]) => {
    const pct = share(count);
    return `<div class="prof-bar-item"><label>${label} <span>${pct}% · ${count}</span></label>
      <div class="prof-bar-track"><div class="prof-bar-fill ${cls}" style="width:${pct}%"></div></div></div>`;
  }).join('');
}

function renderCourseBreakdown(c){
  const tdc = document.getElementById('courseTdcCount');
  const pdc = document.getElementById('coursePdcCount');
  if (tdc) tdc.textContent = c.tdc ?? 0;
  if (pdc) pdc.textContent = c.pdc ?? 0;
}

async function loadStudents(page){
  page = page || 1;
  const { ok, data } = await RCDA.safeFetch(`${RCDA_CONFIG.getApiBaseUrl()}/admin/students?page=${page}`, { method: 'GET' });
  if (ok && data) renderStudents(data.students || [], data.pagination);
}

function renderStudents(students, pagination){
  const tbody = document.querySelector('#studentsTable tbody');
  if (!tbody) return;
  tbody.innerHTML = students.map(st => {
    const status = st.is_active ? 'Active' : 'Inactive';
    const statusBadge = st.is_active ? 'active' : 'pending';
    const payBadge = st.payment_status === 'Paid' ? 'verified' : (st.payment_status === 'Pending' ? 'pending' : 'pending');
    const instructor = st.instructor_name ? escapeHtml(st.instructor_name) : '<span style="color:var(--gray);font-size:12px;">—</span>';
    const idMeta = {
      approved: ['verified', 'Verified'],
      pending: ['pending', 'Pending'],
      rejected: ['rejected', 'Rejected'],
      none: ['upcoming', 'Not submitted']
    }[st.id_status || 'none'] || ['upcoming', 'Not submitted'];
    return `<tr data-status="${status}" data-id="${st.id}">
      <td class="mono" style="color:var(--gray);font-size:12px;">#${st.id}</td>
      <td><b>${escapeHtml(st.name)}</b><br><span style="font-size:11.5px;color:var(--gray);">${maskEmail(st.email)}</span></td>
      <td>${escapeHtml(st.course)}</td>
      <td><span class="badge ${payBadge}">${escapeHtml(st.payment_status)}</span></td>
      <td><span class="badge ${statusBadge}">${status}</span></td>
      <td><span class="badge ${idMeta[0]}">${idMeta[1]}</span></td>
      <td>${instructor}</td>
      <td><div style="display:flex;gap:6px;"><button class="btn btn-sm btn-ghost" onclick="viewStudent(this)">View</button><button class="btn btn-sm btn-ghost" onclick="openUserProfile(${st.user_id || 0},'${escapeHtml(st.name).replace(/'/g, "\\'")}')">Edit Profile</button><button class="btn btn-sm btn-primary" onclick="openAssignInstructorModal(${st.id})">Assign</button><button class="btn btn-sm btn-ghost" onclick="toggleStudentActive(${st.id})">${st.is_active ? 'Deactivate' : 'Activate'}</button><button class="btn btn-sm btn-danger" onclick="deleteStudent(this)">Delete</button></div></td>
    </tr>`;
  }).join('') || `<tr><td colspan="8" style="text-align:center;color:var(--gray);padding:20px;">No students yet.</td></tr>`;
  filterStudents();
  renderPagination(document.getElementById('studentsTable'), 'studentsPagination', pagination, 'loadStudents');
}

async function loadInstructors(page){
  page = page || 1;
  const { ok, data } = await RCDA.safeFetch(`${RCDA_CONFIG.getApiBaseUrl()}/admin/instructors?page=${page}`, { method: 'GET' });
  if (ok && data) renderInstructors(data.instructors || [], data.pagination);
}

async function toggleStudentActive(id){
  const { ok } = await RCDA.safeFetch(`${RCDA_CONFIG.getApiBaseUrl()}/admin/students/${id}/toggle-active`, { method: 'POST' });
  if (!ok) return;
  RCDA.showToast('Student status updated.', 'success');
  loadStudents(1);
}

async function toggleInstructorActive(id){
  const { ok } = await RCDA.safeFetch(`${RCDA_CONFIG.getApiBaseUrl()}/admin/instructors/${id}/toggle-active`, { method: 'POST' });
  if (!ok) return;
  RCDA.showToast('Instructor status updated.', 'success');
  loadInstructors(1);
}

function renderInstructors(instructors, pagination){
  const tbody = document.querySelector('#instructorsTable tbody');
  if (!tbody) return;
  tbody.innerHTML = instructors.map(ins => {
    const status = ins.is_active ? 'Active' : 'On Leave';
    const statusBadge = ins.is_active ? 'active' : 'pending';
    const spec = ins.specialization || '—';
    return `<tr data-status="${status}" data-id="${ins.id}">
      <td><b>${escapeHtml(ins.name)}</b><br><span style="font-size:11.5px;color:var(--gray);">${maskEmail(ins.email)}</span></td>
      <td>${escapeHtml(spec)}</td>
      <td>—</td>
      <td>—</td>
      <td><span class="badge ${statusBadge}">${status}</span></td>
      <td><div style="display:flex;gap:6px;"><button class="btn btn-sm btn-ghost" onclick="openUserProfile(${ins.user_id || 0},'${escapeHtml(ins.name).replace(/'/g, "\\'")}')">Edit Profile</button><button class="btn btn-sm btn-ghost" onclick="toggleInstructorActive(${ins.id})">${ins.is_active ? 'Deactivate' : 'Activate'}</button><button class="btn btn-sm btn-danger" onclick="deleteInstructor(this)">Delete</button></div></td>
    </tr>`;
  }).join('') || `<tr><td colspan="6" style="text-align:center;color:var(--gray);padding:20px;">No instructors yet.</td></tr>`;
  filterInstructors();
  renderPagination(document.getElementById('instructorsTable'), 'instructorsPagination', pagination, 'loadInstructors');
}

function maskEmail(email){
  email = String(email || '');
  const at = email.indexOf('@');
  if (at <= 1) return email;
  const name = email.slice(0, at);
  const domain = email.slice(at);
  const visible = name.slice(0, 1);
  return visible + '***' + domain;
}

/* Renders a pagination footer after a table (created once, refreshed after). */
function renderPagination(tableEl, containerId, pagination, onPage){
  if (!tableEl) return;
  const existing = document.getElementById(containerId);
  if (!pagination || pagination.last_page <= 1) {
    if (existing) existing.remove();
    return;
  }
  let box = existing;
  if (!box) {
    box = document.createElement('div');
    box.id = containerId;
    box.className = 'rcda-pagination';
    tableEl.parentNode.insertBefore(box, tableEl.nextSibling);
  }
  const cur = pagination.current_page, last = pagination.last_page;
  let html = `<span class="rcda-page-info">Page ${cur} of ${last} · ${pagination.total} total</span><div class="rcda-page-btns">`;
  html += `<button class="btn btn-sm btn-ghost" ${cur <= 1 ? 'disabled' : ''} onclick="${onPage}(${cur - 1})">‹ Prev</button>`;
  const pages = [];
  for (let p = 1; p <= last; p++) {
    if (p === 1 || p === last || Math.abs(p - cur) <= 1) pages.push(p);
  }
  let prev = null;
  pages.forEach(p => {
    if (prev && p - prev > 1) html += '<span class="rcda-page-gap">…</span>';
    html += `<button class="btn btn-sm ${p === cur ? 'btn-primary' : 'btn-ghost'}" onclick="${onPage}(${p})">${p}</button>`;
    prev = p;
  });
  html += `<button class="btn btn-sm btn-ghost" ${cur >= last ? 'disabled' : ''} onclick="${onPage}(${cur + 1})">Next ›</button>`;
  html += '</div>';
  box.innerHTML = html;
}

function escapeHtml(str){
  return String(str == null ? '' : str).replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
}

function statusBadge(status) {
  const map = {
    open: ['confirmed', 'Open'], done: ['done', 'Done'], full: ['', 'Full'],
    cancelled: ['', 'Cancelled'], pending: ['pending', 'Pending'],
    active: ['enrolled', 'Active'], completed: ['done', 'Completed'],
  };
  const [cls, label] = map[status] || ['', (status || '').charAt(0).toUpperCase() + (status || '').slice(1)];
  return `<span class="badge ${cls}">${escapeHtml(label)}</span>`;
}

/* ============================================================
   Assign Instructor — admin gives a student to an instructor.
   ============================================================ */
async function openAssignInstructorModal(studentId){
  window._assignStudentId = studentId;
  const sel = document.getElementById('assignInstrSelect');
  if (sel && ADMIN_INSTRUCTORS.length) {
    sel.innerHTML = '<option value="">— Unassigned —</option>' +
      ADMIN_INSTRUCTORS.map(i => `<option value="${i.id}">${escapeHtml(i.name)}</option>`).join('');
  }
  openModal('assignInstructorModal');
}

/* Admin edits the full profile (name, email, details) of any
   student or instructor, and can reset their password. */
function openUserProfile(userId, name){
  if (!userId) { RCDA.showToast('Could not identify that user.', 'error'); return; }
  const title = document.getElementById('userProfileTitle');
  if (title) title.textContent = 'Edit Profile — ' + (name || 'User');
  openModal('userProfileModal');
  if (window.RCDAProfile) {
    RCDAProfile.render({
      container: document.getElementById('userProfileBody'),
      mode: 'adminEdit',
      userId: userId
    });
  }
}

async function submitAssignInstructor(){
  const id = window._assignStudentId;
  const sel = document.getElementById('assignInstrSelect');
  if (!id || !sel) return;
  const instructorId = sel.value ? Number(sel.value) : null;
  const { ok, error } = await RCDA.safeFetch(
    `${RCDA_CONFIG.getApiBaseUrl()}/admin/students/${id}/assign-instructor`,
    { method: 'POST', body: { instructor_id: instructorId } }
  );
  if (!ok) { RCDA.showToast(error || 'Could not assign instructor.', 'error'); return; }
  RCDA.showToast('Instructor assigned.', 'success');
  closeModal('assignInstructorModal');
  loadStudents();
}

/* ============================================================
   Record Payment — admin takes a cash / OTC payment at the
   counter and verifies it immediately so the student can book.
   ============================================================ */
let RECORD_PAYMENT_STUDENTS = [];
let RECORD_PAYMENT_COURSES = [];

async function openRecordPaymentModal(){
  const err = document.getElementById('payError');
  if (err) err.style.display = 'none';
  const [stuRes, courseRes] = await Promise.all([
    RCDA.safeFetch(`${RCDA_CONFIG.getApiBaseUrl()}/admin/students?per_page=100`, { method: 'GET' }),
    RCDA.safeFetch(`${RCDA_CONFIG.getApiBaseUrl()}/admin/courses`, { method: 'GET' }).catch(() => ({ ok: false })),
  ]);
  RECORD_PAYMENT_STUDENTS = (stuRes.ok && stuRes.data.students) || [];
  RECORD_PAYMENT_COURSES = (courseRes.ok && courseRes.data && courseRes.data.courses) || [];
  const stuSel = document.getElementById('payStudent');
  const courseSel = document.getElementById('payCourse');
  if (stuSel) stuSel.innerHTML = RECORD_PAYMENT_STUDENTS.map(s => `<option value="${s.id}">${escapeHtml(s.name)}</option>`).join('');
  if (courseSel) {
    if (RECORD_PAYMENT_COURSES.length) {
      courseSel.innerHTML = RECORD_PAYMENT_COURSES.map(c => `<option value="${c.id}" data-fee="${c.fee}">${escapeHtml(c.name)} — ₱${Number(c.fee).toFixed(2)}</option>`).join('');
    } else {
      courseSel.innerHTML = '<option value="">— no courses —</option>';
    }
  }
  const amt = document.getElementById('payAmount');
  if (amt && courseSel && courseSel.selectedOptions[0]) amt.value = courseSel.selectedOptions[0].dataset.fee || '';
  openModal('recordPaymentModal');
}

async function submitRecordPayment(){
  const err = document.getElementById('payError');
  if (err) err.style.display = 'none';
  const studentId = Number(document.getElementById('payStudent').value);
  const courseId = Number(document.getElementById('payCourse').value);
  const amount = parseFloat(document.getElementById('payAmount').value);
  const method = document.getElementById('payMethod').value;
  const reference = document.getElementById('payRef').value || ('CASH-' + Date.now());
  if (!studentId || !courseId || isNaN(amount) || amount < 0) {
    if (err) { err.textContent = 'Please choose a student, course, and a valid amount.'; err.style.display = 'block'; }
    return;
  }
  const { ok, error, data } = await RCDA.safeFetch(
    `${RCDA_CONFIG.getApiBaseUrl()}/admin/payments`,
    { method: 'POST', body: { student_id: studentId, course_id: courseId, amount, method, reference_no: reference } }
  );
  if (!ok) { if (err) { err.textContent = error || 'Could not record payment.'; err.style.display = 'block'; } return; }
  RCDA.showToast('Payment recorded and verified. Student can now book.', 'success');
  closeModal('recordPaymentModal');
  loadPayments();
}

/* ============================================================
   Pending Bookings — admin accepts the instructor assigned to
   each session (booking sits 'pending' until accepted).
   ============================================================ */
async function loadBookings(){
  const { ok, data } = await RCDA.safeFetch(`${RCDA_CONFIG.getApiBaseUrl()}/admin/bookings`, { method: 'GET' });
  if (ok && data) renderBookings(data.bookings || []);
}

function renderBookings(list){
  const tb = document.getElementById('bookingsTbody');
  if (!tb) return;
  if (!list.length) {
    tb.innerHTML = '<tr><td colspan="6" style="text-align:center;color:var(--gray);padding:20px;">No pending bookings.</td></tr>';
    return;
  }
  tb.innerHTML = list.map(b => {
    const s = b.schedule || {};
    const session = `${escapeHtml(String(s.date || ''))} ${escapeHtml(String(s.time_start || ''))}–${escapeHtml(String(s.time_end || ''))}`;
    return `<tr data-id="${b.id}">
      <td><b>${escapeHtml(b.student?.name || '')}</b><br><span style="font-size:11.5px;color:var(--gray);">${escapeHtml(b.student?.email || '')}</span></td>
      <td>${escapeHtml(s.course || '')}</td>
      <td>${session}</td>
      <td>${escapeHtml(s.venue || '—')}</td>
      <td>${escapeHtml(s.instructor?.name || '—')}</td>
      <td><div style="display:flex;gap:6px;">
        <button class="btn btn-sm btn-success" onclick="acceptBooking(${b.id},this)">Accept</button>
        <button class="btn btn-sm btn-danger" onclick="rejectBooking(${b.id},this)">Reject</button>
      </div></td>
    </tr>`;
  }).join('');
}

async function acceptBooking(id, btn){
  const { ok, error } = await RCDA.safeFetch(`${RCDA_CONFIG.getApiBaseUrl()}/admin/bookings/${id}/accept`, { method: 'POST' });
  if (!ok) { RCDA.showToast(error || 'Could not accept booking.', 'error'); return; }
  RCDA.showToast('Booking accepted. Slot confirmed.', 'success');
  if (btn) btn.closest('tr')?.remove();
}

async function rejectBooking(id, btn){
  const { ok, error } = await RCDA.safeFetch(`${RCDA_CONFIG.getApiBaseUrl()}/admin/bookings/${id}/reject`, { method: 'POST' });
  if (!ok) { RCDA.showToast(error || 'Could not reject booking.', 'error'); return; }
  RCDA.showToast('Booking rejected.', 'success');
  if (btn) btn.closest('tr')?.remove();
}

/* ============================================================
   Instructor Attendance Oversight — admin views the attendance
   that instructors record, per session.
   ============================================================ */
async function loadInstructorAttendance(){
  const { ok, data } = await RCDA.safeFetch(`${RCDA_CONFIG.getApiBaseUrl()}/admin/attendance`, { method: 'GET' });
  const list = document.getElementById('attendanceScheduleList');
  if (!list) return;
  if (!ok || !data || !data.schedules || !data.schedules.length) {
    list.innerHTML = '<div style="color:var(--gray);padding:14px;">No sessions yet.</div>';
    return;
  }
  list.innerHTML = data.schedules.map(s => {
    const a = s.attendance || {};
    return `<div class="cert-row" style="display:flex;align-items:center;justify-content:space-between;gap:10px;padding:12px 14px;border:1px solid var(--gray-light);border-radius:12px;background:var(--cream);">
      <div>
        <b>${escapeHtml(s.course?.name || '')}</b> · ${escapeHtml(s.instructor?.name || '')}<br>
        <span style="font-size:12px;color:var(--gray);">${escapeHtml(String(s.date || ''))} · ${a.present || 0}P / ${a.late || 0}L / ${a.absent || 0}A</span>
      </div>
      <button class="btn btn-sm btn-primary" onclick="viewScheduleAttendance(${s.id})">View</button>
    </div>`;
  }).join('');
}

async function viewScheduleAttendance(id){
  const { ok, data } = await RCDA.safeFetch(`${RCDA_CONFIG.getApiBaseUrl()}/admin/attendance/${id}`, { method: 'GET' });
  const el = document.getElementById('attendanceDetail');
  if (!el) return;
  if (!ok || !data) { el.innerHTML = '<div style="color:var(--gray);padding:12px;">Could not load attendance.</div>'; return; }
  const recs = data.attendance || [];
  const sch = data.schedule || {};
  let html = `<div style="margin-bottom:8px;"><b>${escapeHtml(sch.course?.name || '')}</b> — ${escapeHtml(sch.instructor?.name || '')}<br>
    <span style="font-size:12px;color:var(--gray);">${escapeHtml(String(sch.date || ''))} · ${escapeHtml(String(sch.time_start || ''))}–${escapeHtml(String(sch.time_end || ''))} · ${escapeHtml(sch.venue || '')}</span></div>`;
  if (!recs.length) {
    html += '<div style="color:var(--gray);padding:12px;">No attendance recorded yet.</div>';
  } else {
    html += recs.map(r => {
      const cls = r.status === 'present' ? 'active' : (r.status === 'late' ? 'pending' : 'needs');
      return `<div class="attend-row" style="display:flex;align-items:center;justify-content:space-between;padding:10px 12px;border:1px solid var(--gray-light);border-radius:10px;">
        <div class="attend-name"><b>${escapeHtml(r.student_name || 'Unknown')}</b></div>
        <span class="badge ${cls}">${escapeHtml(r.status || '—')}</span>
      </div>`;
    }).join('');
  }
  el.innerHTML = html;
}

async function loadPayments(page){
  page = page || 1;
  const { ok, data } = await RCDA.safeFetch(`${RCDA_CONFIG.getApiBaseUrl()}/admin/payments?page=${page}`, { method: 'GET' });
  if (ok && data) renderPayments(data.payments || [], data.pagination);
}

function renderPayments(payments, pagination){
  const tbody = document.getElementById('paymentsTbody');
  if (!tbody) return;
  tbody.innerHTML = payments.map(p => paymentRowHtml(p)).join('') ||
    `<tr><td colspan="8" style="text-align:center;color:var(--gray);padding:20px;">No payments recorded.</td></tr>`;
  filterPayments();
  renderPagination(document.getElementById('paymentsTable'), 'paymentsPagination', pagination, 'loadPayments');
}

function paymentRowHtml(p){
  const status = p.status || 'pending';
  const badgeClass = status === 'verified' ? 'verified' : (status === 'rejected' ? 'needs' : 'pending');
  const amount = '₱' + Number(p.amount || 0).toLocaleString('en-PH');
  const method = (p.method || '—');
  const ref = (p.reference_no || '—');
  const date = p.created_at ? new Date(p.created_at).toLocaleDateString('en-PH', { month: 'short', day: 'numeric' }) : '—';
  const actions = status === 'pending'
    ? `<div class="pay-actions"><button class="btn btn-sm btn-success" title="Verify payment" onclick="verifyPayment(this,${p.id})">Accept</button><button class="btn btn-sm btn-danger" title="Reject payment" onclick="rejectPayment(this,${p.id})">Reject</button></div>`
    : `<div class="pay-actions"><button class="btn btn-sm ${status === 'verified' ? 'btn-success' : 'btn-danger'}" disabled>${status === 'verified' ? 'Accepted' : 'Rejected'}</button></div>`;
  return `<tr data-status="${badgeClass === 'verified' ? 'Verified' : status === 'rejected' ? 'Rejected' : 'Pending'}" data-id="${p.id}">
    <td><b>${escapeHtml(p.student_name)}</b></td>
    <td>${escapeHtml(p.course ? p.course.name : '—')}</td>
    <td class="mono"><b>${amount}</b></td>
    <td>${escapeHtml(method)}</td>
    <td class="mono" style="font-size:12px;color:var(--gray);">${escapeHtml(ref)}</td>
    <td>${date}</td>
    <td><span class="badge ${badgeClass}">${status.charAt(0).toUpperCase() + status.slice(1)}</span></td>
    <td>${actions}</td>
  </tr>`;
}

async function verifyPayment(btn, id){
  const { ok, data } = await RCDA.safeFetch(`${RCDA_CONFIG.getApiBaseUrl()}/admin/payments/${id}/verify`, { method: 'POST' });
  if (!ok) { RCDA.showToast(data && data.message ? data.message : 'Could not verify payment.', 'error'); return; }
  const row = btn.closest('tr'); if (row) row.outerHTML = paymentRowHtml(data.payment);
  filterPayments();
}

async function rejectPayment(btn, id){
  const { ok, data } = await RCDA.safeFetch(`${RCDA_CONFIG.getApiBaseUrl()}/admin/payments/${id}/reject`, { method: 'POST' });
  if (!ok) { RCDA.showToast(data && data.message ? data.message : 'Could not reject payment.', 'error'); return; }
  const row = btn.closest('tr'); if (row) row.outerHTML = paymentRowHtml(data.payment);
  filterPayments();
}

function undoPayment(){ /* no server-side un-verify; refresh list instead */ loadPayments(); }

document.addEventListener('DOMContentLoaded', updateTopbarScrollState);

/* ============================================================
   Topbar — turns from orange to solid white once the page
   is scrolled down, so it stays crisp/readable over content.
   ============================================================ */
function updateTopbarScrollState(){
  const topbar = document.querySelector('.dash-topbar');
  if (!topbar) return;
  topbar.classList.toggle('scrolled', window.scrollY > 8);
}
window.addEventListener('scroll', updateTopbarScrollState);
document.addEventListener('DOMContentLoaded', updateTopbarScrollState);

/* ============================================================
   Filters — dropdown toggle + search/status filtering for the
   Students and Instructors tables.
   ============================================================ */
function toggleFilterMenu(id){
  document.querySelectorAll('.filter-menu.open').forEach(m => {
    if (m.id !== id) m.classList.remove('open');
  });
  const menu = document.getElementById(id);
  if (menu) menu.classList.toggle('open');
}

document.addEventListener('click', function(e){
  if (!e.target.closest('.filter-wrap')) {
    document.querySelectorAll('.filter-menu.open').forEach(m => m.classList.remove('open'));
  }
});

function filterStudents(){
  const query = ((document.getElementById('studentSearchInput') || {}).value || '').toLowerCase();
  const menu = document.getElementById('studentFilterMenu');
  const checked = menu ? Array.from(menu.querySelectorAll('input[type=checkbox]:checked')).map(cb => cb.value) : [];
  const sortMode = menu ? (menu.querySelector('input[name=studentSort]:checked') || {}).value : '';

  const tbody = document.querySelector('#studentsTable tbody');
  if (!tbody) return;
  const rows = Array.from(tbody.querySelectorAll('tr'));

  rows.forEach(row => {
    const matchesText = row.textContent.toLowerCase().includes(query);
    const matchesStatus = checked.length === 0 || checked.includes(row.dataset.status);
    row.style.display = (matchesText && matchesStatus) ? '' : 'none';
  });

  if (sortMode === 'az' || sortMode === 'za') {
    const getName = row => (row.querySelector('td:nth-child(2)') || {}).textContent.trim().toLowerCase();
    rows.sort((a, b) => {
      const cmp = getName(a).localeCompare(getName(b));
      return sortMode === 'az' ? cmp : -cmp;
    });
    rows.forEach(row => tbody.appendChild(row));
  }
}

function filterInstructors(){
  const query = ((document.getElementById('instructorSearchInput') || {}).value || '').toLowerCase();
  const menu = document.getElementById('instructorFilterMenu');
  const checked = menu ? Array.from(menu.querySelectorAll('input[type=checkbox]:checked')).map(cb => cb.value) : [];
  const sortMode = menu ? (menu.querySelector('input[name=instructorSort]:checked') || {}).value : '';

  const tbody = document.querySelector('#instructorsTable tbody');
  if (!tbody) return;
  const rows = Array.from(tbody.querySelectorAll('tr'));

  rows.forEach(row => {
    const matchesText = row.textContent.toLowerCase().includes(query);
    const matchesStatus = checked.length === 0 || checked.includes(row.dataset.status);
    row.style.display = (matchesText && matchesStatus) ? '' : 'none';
  });

  if (sortMode === 'az' || sortMode === 'za') {
    const getName = row => (row.querySelector('td:first-child') || {}).textContent.trim().toLowerCase();
    rows.sort((a, b) => {
      const cmp = getName(a).localeCompare(getName(b));
      return sortMode === 'az' ? cmp : -cmp;
    });
    rows.forEach(row => tbody.appendChild(row));
  }
}

/* ============================================================
   Add Instructor — reads the modal form, creates the
   instructor + login account via the API, refreshes the
   roster, then resets and closes the modal.
   ============================================================ */
async function addInstructor(){
  const name = (document.getElementById('newInstrName') || {}).value.trim();
  const email = (document.getElementById('newInstrEmail') || {}).value.trim();
  const spec = (document.getElementById('newInstrSpec') || {}).value;
  const phone = (document.getElementById('newInstrPhone') || {}).value.trim();
  const license = (document.getElementById('newInstrLicense') || {}).value.trim();
  const password = (document.getElementById('newInstrPassword') || {}).value;

  const errorEl = document.getElementById('newInstrError');

  if (!name || !email || !password) {
    if (errorEl) errorEl.style.display = 'block';
    return;
  }
  if (errorEl) errorEl.style.display = 'none';

  const submitBtn = document.querySelector('#addInstructorModal .pay-modal-footer .btn-primary');
  if (submitBtn) { submitBtn.disabled = true; submitBtn.textContent = 'Saving…'; }

  const { ok, data } = await RCDA.safeFetch(`${RCDA_CONFIG.getApiBaseUrl()}/admin/instructors`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ name, email, specialization: spec, phone: phone || null, license_no: license || null, password })
  });

  if (submitBtn) { submitBtn.disabled = false; submitBtn.textContent = '+ Add Instructor'; }

  if (!ok || !data) return;

  RCDA.showToast('Instructor added.', 'success');

  // Reset form
  document.getElementById('newInstrName').value = '';
  document.getElementById('newInstrEmail').value = '';
  document.getElementById('newInstrPhone').value = '';
  document.getElementById('newInstrLicense').value = '';
  document.getElementById('newInstrPassword').value = '';

  closeModal('addInstructorModal');
  loadInstructors(1);
}

/* ============================================================
   Add Student — reads the modal form, creates the student +
   login account via the API, refreshes the All Students
   table, then resets and closes the modal.
   ============================================================ */
async function addStudent(){
  const name = (document.getElementById('newStudName') || {}).value.trim();
  const email = (document.getElementById('newStudEmail') || {}).value.trim();
  const phone = (document.getElementById('newStudPhone') || {}).value.trim();
  const license = (document.getElementById('newStudLicense') || {}).value;
  const password = (document.getElementById('newStudPassword') || {}).value;

  const errorEl = document.getElementById('newStudError');

  if (!name || !email || !password) {
    if (errorEl) errorEl.style.display = 'block';
    return;
  }
  if (errorEl) errorEl.style.display = 'none';

  const submitBtn = document.querySelector('#addStudentModal .pay-modal-footer .btn-primary');
  if (submitBtn) { submitBtn.disabled = true; submitBtn.textContent = 'Saving…'; }

  const { ok, data } = await RCDA.safeFetch(`${RCDA_CONFIG.getApiBaseUrl()}/admin/students`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ name, email, phone: phone || null, license_type: license || null, password })
  });

  if (submitBtn) { submitBtn.disabled = false; submitBtn.textContent = '+ Add Student'; }

  if (!ok || !data) return;

  RCDA.showToast('Student added.', 'success');

  // Reset form
  document.getElementById('newStudName').value = '';
  document.getElementById('newStudEmail').value = '';
  document.getElementById('newStudPhone').value = '';
  document.getElementById('newStudLicense').value = '';
  document.getElementById('newStudPassword').value = '';

  closeModal('addStudentModal');
  loadStudents(1);
}

/* ============================================================
   View Student — reads the clicked row's actual data and
   populates the Student Profile modal with it, instead of
   always showing the same hardcoded demo student.
   ============================================================ */
async function viewStudent(btn){
  const row = btn.closest('tr');
  if (!row) return;
  const id = row.dataset.id;
  if (!id) return;

  const { ok, data } = await RCDA.safeFetch(`${RCDA_CONFIG.getApiBaseUrl()}/admin/students/${id}`, { method: 'GET' });
  if (!ok || !data) { RCDA.showToast('Could not load student.', 'error'); return; }

  const s = data.student;
  const user = s.user || {};
  window._viewUserId = user.id;
  window._viewUserName = user.name || '';
  const name = user.name || 'Student';
  const initials = name.split(' ').filter(Boolean).map(w => w[0]).slice(0, 2).join('').toUpperCase();
  const isActive = !!(user.is_active);
  const status = isActive ? 'Active' : 'Inactive';
  const statusClass = isActive ? 'active' : 'pending';

  const courses = (s.enrollments || []).map(e => (e.course ? e.course.name : '—'));
  const courseLabel = courses.length ? courses.join(', ') : '—';

  const titleEl = document.getElementById('modalStudentTitle');
  const avatarEl = document.getElementById('modalStudentAvatar');
  const nameEl = document.getElementById('modalStudentName');
  const metaEl = document.getElementById('modalStudentMeta');
  const courseEl = document.getElementById('modalStudentCourse');
  const instructorEl = document.getElementById('modalStudentInstructor');
  const genderEl = document.getElementById('modalStudentGender');
  const prefEl = document.getElementById('modalStudentPref');
  const tdcEl = document.getElementById('modalStudentTDC');
  const pdcEl = document.getElementById('modalStudentPDC');
  const statusEl = document.getElementById('modalStudentStatus');
  const paymentEl = document.getElementById('modalStudentPayment');

  if (titleEl) titleEl.textContent = 'Student Profile — ' + name;
  if (avatarEl) avatarEl.textContent = initials || '—';
  if (nameEl) nameEl.textContent = name;
  if (metaEl) metaEl.textContent = '#' + s.id + ' · ' + (user.email || '');
  if (courseEl) courseEl.textContent = courseLabel;
  if (instructorEl) instructorEl.textContent = '—';
  if (genderEl) genderEl.textContent = s.gender ? (s.gender.charAt(0).toUpperCase() + s.gender.slice(1)) : '—';
  if (prefEl) prefEl.textContent = s.preferred_instructor_gender ? (s.preferred_instructor_gender === 'male' ? 'Male' : 'Female') : 'No preference';
  if (tdcEl) tdcEl.textContent = '—';
  if (pdcEl) pdcEl.textContent = '—';
  if (statusEl) { statusEl.textContent = status; statusEl.className = 'badge ' + statusClass; }
  if (paymentEl) { paymentEl.textContent = '—'; paymentEl.className = 'badge pending'; }

  const roleSel = document.getElementById('modalStudentRole');
  if (roleSel) roleSel.value = user.role || 'student';
  const specSel = document.getElementById('modalStudentSpec');
  if (specSel) specSel.value = s.instructor?.specialization || 'TDC';
  syncRoleSpecialization();

  profileTab('info');
  openModal('studentModal');
  loadRoleChangeLog(user.id);
}

/* Instructors are created by promoting a student, not by direct sign-up. */
function promoteInstructorHint(){
  showPanel('students');
  RCDA.showToast('Open a student profile and use “Set Role → Instructor” to make them an instructor.', 'info');
}

/* Promote/demote the viewed user (admin only). */
async function saveStudentRole(){
  const sel = document.getElementById('modalStudentRole');
  const role = sel ? sel.value : 'student';
  const specSel = document.getElementById('modalStudentSpec');
  const reasonEl = document.getElementById('modalStudentRoleReason');
  const id = window._viewUserId;
  if (!id) return;
  const body = { role };
  if (role === 'instructor' && specSel) body.specialization = specSel.value;
  if (reasonEl && reasonEl.value.trim()) body.reason = reasonEl.value.trim();
  const { ok, error } = await RCDA.safeFetch(`${RCDA_CONFIG.getApiBaseUrl()}/admin/users/${id}/set-role`, {
    method: 'POST',
    body
  });
  if (!ok) { RCDA.showToast(error || 'Could not update role.', 'error'); return; }
  RCDA.showToast('Role updated to ' + role + '.', 'success');
  loadStudents();
  loadInstructors();
  loadRoleChangeLog(id);
}

/* Toggle the specialization field depending on the chosen role. */
function syncRoleSpecialization(){
  const sel = document.getElementById('modalStudentRole');
  const wrap = document.getElementById('specializationWrap');
  if (sel && wrap) wrap.style.display = sel.value === 'instructor' ? 'block' : 'none';
}
if (document.getElementById('modalStudentRole')) {
  document.getElementById('modalStudentRole').addEventListener('change', syncRoleSpecialization);
}

/* Load the audit trail of role changes for the viewed user. */
async function loadRoleChangeLog(userId){
  const el = document.getElementById('roleChangeLog');
  if (!el || !userId) return;
  const { ok, data } = await RCDA.safeFetch(`${RCDA_CONFIG.getApiBaseUrl()}/admin/users/role-changes`, { method: 'GET' });
  if (!ok || !data) { el.innerHTML = ''; return; }
  const mine = (data.changes || []).filter(c => c.user_name && window._viewUserName === c.user_name);
  if (!mine.length) { el.innerHTML = '<i>No prior role changes recorded.</i>'; return; }
  el.innerHTML = '<b>Role change history</b>' + mine.map(c =>
    `<div style="padding:5px 0;border-top:1px solid var(--gray-light);margin-top:6px;">
      ${escapeHtml(c.from_role)} → <b>${escapeHtml(c.to_role)}</b> by ${escapeHtml(c.changed_by || '—')}
      <span style="color:var(--gray);">· ${c.created_at ? new Date(c.created_at).toLocaleDateString('en-PH') : ''}</span>
      ${c.reason ? `<br><span style="color:var(--gray);">"${escapeHtml(c.reason)}"</span>` : ''}
    </div>`).join('');
}

/* ============================================================
   Delete Student / Delete Instructor — confirm before removing
   the row from the table.
   ============================================================ */
/* Bootstrap 5 Modal instance for the Delete Student confirmation ONLY */
let _deleteStudentModal = null;
let _pendingDeleteStudentRow = null;

function deleteStudent(btn){
  const row = btn.closest('tr');
  if (!row) return;
  const name = (row.querySelector('td:nth-child(2)') || {}).textContent.trim() || 'this student';

  const el = document.getElementById('deleteStudentModal');
  if (!el) { if (confirm(`Remove ${name} from the students list?`)) row.remove(); return; }

  if (!_deleteStudentModal) {
    _deleteStudentModal = new bootstrap.Modal(el);
    document.getElementById('deleteStudentConfirmBtn').addEventListener('click', async () => {
      const row = _pendingDeleteStudentRow;
      _pendingDeleteStudentRow = null;
      _deleteStudentModal.hide();
      if (!row) return;
      const id = row.dataset.id;
      if (id) {
        const { ok } = await RCDA.safeFetch(`${RCDA_CONFIG.getApiBaseUrl()}/admin/students/${id}`, { method: 'DELETE' });
        if (!ok) { RCDA.showToast('Could not delete student.', 'error'); loadStudents(); return; }
      }
      row.remove();
    });
  }
  _pendingDeleteStudentRow = row;
  const msgEl = document.getElementById('deleteStudentMessage');
  if (msgEl) msgEl.textContent = `Remove ${name} from the students list?`;
  _deleteStudentModal.show();
}

/* Bootstrap 5 Modal instance for the Delete Instructor confirmation ONLY */
let _deleteInstructorModal = null;
let _pendingDeleteInstructorRow = null;

function deleteInstructor(btn){
  const row = btn.closest('tr');
  if (!row) return;
  const name = (row.querySelector('td:first-child') || {}).textContent.trim() || 'this instructor';

  const el = document.getElementById('deleteInstructorModal');
  if (!el) { if (confirm(`Remove ${name} from the instructor roster?`)) row.remove(); return; }

  if (!_deleteInstructorModal) {
    _deleteInstructorModal = new bootstrap.Modal(el);
    document.getElementById('deleteInstructorConfirmBtn').addEventListener('click', async () => {
      const row = _pendingDeleteInstructorRow;
      _pendingDeleteInstructorRow = null;
      _deleteInstructorModal.hide();
      if (!row) return;
      const id = row.dataset.id;
      if (id) {
        const { ok } = await RCDA.safeFetch(`${RCDA_CONFIG.getApiBaseUrl()}/admin/instructors/${id}`, { method: 'DELETE' });
        if (!ok) { RCDA.showToast('Could not delete instructor.', 'error'); loadInstructors(); return; }
      }
      row.remove();
    });
  }
  _pendingDeleteInstructorRow = row;
  const msgEl = document.getElementById('deleteInstructorMessage');
  if (msgEl) msgEl.textContent = `Remove ${name} from the instructor roster?`;
  _deleteInstructorModal.show();
}

/* ============================================================
   Payments — search + status filter + sort, same pattern as
   the Students/Instructors tables.
   ============================================================ */
function filterPayments(){
  const query = ((document.getElementById('paymentSearchInput') || {}).value || '').toLowerCase();
  const menu = document.getElementById('paymentFilterMenu');
  const checked = menu ? Array.from(menu.querySelectorAll('input[type=checkbox]:checked')).map(cb => cb.value) : [];
  const sortMode = menu ? (menu.querySelector('input[name=paymentSort]:checked') || {}).value : '';

  const tbody = document.querySelector('#paymentsTable tbody');
  if (!tbody) return;
  const rows = Array.from(tbody.querySelectorAll('tr'));

  rows.forEach(row => {
    const matchesText = row.textContent.toLowerCase().includes(query);
    const matchesStatus = checked.length === 0 || checked.includes(row.dataset.status);
    row.style.display = (matchesText && matchesStatus) ? '' : 'none';
  });

  if (sortMode === 'az' || sortMode === 'za') {
    const getName = row => (row.querySelector('td:first-child') || {}).textContent.trim().toLowerCase();
    rows.sort((a, b) => {
      const cmp = getName(a).localeCompare(getName(b));
      return sortMode === 'az' ? cmp : -cmp;
    });
    rows.forEach(row => tbody.appendChild(row));
  }
}

/* ============================================================
   Export PDF — downloads the currently visible (i.e. filtered)
   payment rows as a .pdf table, skipping the Actions column.
   ============================================================ */
function exportPaymentsPDF(){
  const table = document.getElementById('paymentsTable');
  if (!table || !window.jspdf) return;

  const headerCells = Array.from(table.querySelectorAll('thead th')).slice(0, -1);
  const headers = headerCells.map(th => th.textContent.trim());

  const rows = Array.from(table.querySelectorAll('tbody tr')).filter(row => row.style.display !== 'none');
  const body = rows.map(row =>
    Array.from(row.querySelectorAll('td')).slice(0, -1).map(td =>
      td.textContent.replace(/\s+/g, ' ').trim().replace(/₱/g, 'PHP ')
    )
  );

  const { jsPDF } = window.jspdf;
  const doc = new jsPDF({ orientation: 'landscape' });

  doc.setFontSize(14);
  doc.text('RC Driving Academy — Payment Records', 14, 15);
  doc.setFontSize(10);
  doc.text('Generated ' + new Date().toLocaleDateString('en-PH', { year: 'numeric', month: 'long', day: 'numeric' }), 14, 21);

  doc.autoTable({
    head: [headers],
    body: body,
    startY: 27,
    styles: { fontSize: 9, cellPadding: 3 },
    headStyles: { fillColor: [242, 106, 33] }
  });

  const dateStr = new Date().toISOString().slice(0, 10);
  doc.save(`payment-records-${dateStr}.pdf`);
}

/* ============================================================
   Valid ID verification queue — the admin opens the private file,
   then approves or rejects it. Reviewing is status-only: an
   unverified ID never blocks enrolment, booking or payment.
   ============================================================ */
let ADMIN_ID_DOC_URL = '';

async function loadIdVerifications(){
  const status = (document.getElementById('idStatusFilter') || {}).value || 'pending';
  const search = ((document.getElementById('idSearchInput') || {}).value || '').trim();

  const params = new URLSearchParams({ status });
  if (search) params.set('search', search);

  const { ok, data } = await RCDA.safeFetch(`${RCDA_CONFIG.getApiBaseUrl()}/admin/id-verifications?${params.toString()}`, { method: 'GET' });
  if (!ok || !data) return;

  renderIdCounts(data.counts || {});
  renderIdVerifications(data.verifications || []);
}

function renderIdCounts(counts){
  const el = document.getElementById('idCounts');
  if (!el) return;

  const boxes = [
    ['Pending review', counts.pending || 0],
    ['Approved', counts.approved || 0],
    ['Rejected', counts.rejected || 0],
    ['Not submitted', counts.none || 0],
  ];

  el.innerHTML = boxes.map(([label, value]) =>
    `<div style="background:var(--cream);border:1px solid var(--gray-light);border-radius:12px;padding:10px 16px;min-width:120px;">
      <div style="font-size:11.5px;color:var(--gray);text-transform:uppercase;letter-spacing:.4px;">${label}</div>
      <div style="font-size:20px;font-weight:800;color:var(--charcoal);">${value}</div>
    </div>`).join('');
}

function idStatusText(status){
  return { approved: 'Approved', pending: 'Pending', rejected: 'Rejected', none: 'Not submitted' }[status] || '—';
}

function idDate(value){
  if (!value) return '—';
  const d = new Date(value);
  if (isNaN(d.getTime())) return '—';
  return d.toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' });
}

function renderIdVerifications(list){
  const tb = document.querySelector('#idVerificationsTable tbody');
  if (!tb) return;

  tb.innerHTML = list.length ? list.map(v => {
    const badge = { approved: 'verified', pending: 'pending', rejected: 'rejected' }[v.status] || 'upcoming';
    const actions = [];

    if (v.has_document) {
      actions.push(`<button class="btn btn-sm btn-ghost" onclick="viewIdDocument(${v.student_id})">View ID</button>`);
    }
    if (v.has_document && v.status !== 'approved') {
      actions.push(`<button class="btn btn-sm btn-primary" onclick="approveId(${v.student_id},this)">Approve</button>`);
    }
    if (v.has_document && v.status !== 'rejected') {
      actions.push(`<button class="btn btn-sm btn-danger" onclick="rejectId(${v.student_id},this)">Reject</button>`);
    }

    const reason = (v.status === 'rejected' && v.rejection_reason)
      ? `<div style="font-size:11px;color:var(--gray);margin-top:4px;max-width:220px;">${escapeHtml(v.rejection_reason)}</div>`
      : '';

    return `<tr data-student-id="${v.student_id}">
      <td><b>${escapeHtml(v.student_name || 'Student')}</b><br><span style="font-size:11.5px;color:var(--gray);">${escapeHtml(maskEmail(v.email || ''))}</span></td>
      <td>${escapeHtml(v.document_type_label || '—')}<br><span style="font-size:11px;color:var(--gray);">${escapeHtml(v.document_name || '')}</span></td>
      <td>${escapeHtml(idDate(v.submitted_at))}</td>
      <td><span class="badge ${badge}">${escapeHtml(idStatusText(v.status))}</span>${reason}</td>
      <td>${v.reviewed_by ? escapeHtml(v.reviewed_by) : '—'}${v.reviewed_at ? `<br><span style="font-size:11px;color:var(--gray);">${escapeHtml(idDate(v.reviewed_at))}</span>` : ''}</td>
      <td><div style="display:flex;gap:6px;flex-wrap:wrap;">${actions.join('') || '<span style="font-size:12px;color:var(--gray);">—</span>'}</div></td>
    </tr>`;
  }).join('') : '<tr><td colspan="6" style="text-align:center;color:var(--gray);padding:20px;">Nothing to review here.</td></tr>';
}

async function viewIdDocument(studentId){
  const body = document.getElementById('idDocumentBody');
  const title = document.getElementById('idDocumentTitle');

  clearIdDocument();
  if (title) title.textContent = 'Loading valid ID…';
  if (body) body.innerHTML = '<div style="color:var(--gray);font-size:13px;">Loading document…</div>';
  openModal('idDocumentModal');

  const res = await fetch(`${RCDA_CONFIG.getApiBaseUrl()}/admin/id-verifications/${studentId}/file`, {
    headers: {
      'Accept': '*/*',
      'Authorization': 'Bearer ' + ((window.RCDA_AUTH && RCDA_AUTH.getToken && RCDA_AUTH.getToken()) || localStorage.getItem('rcda_token') || '')
    }
  });

  if (!res.ok) {
    if (body) body.innerHTML = '<div style="color:var(--danger2);font-size:13px;">Could not open this document.</div>';
    return;
  }

  const blob = await res.blob();
  ADMIN_ID_DOC_URL = URL.createObjectURL(blob);

  if (title) title.textContent = 'Valid ID';
  if (body) {
    body.innerHTML = blob.type === 'application/pdf'
      ? `<iframe src="${ADMIN_ID_DOC_URL}" title="Valid ID" style="width:100%;height:65vh;border:0;border-radius:10px;background:#fff;"></iframe>`
      : `<img src="${ADMIN_ID_DOC_URL}" alt="Valid ID" style="max-width:100%;max-height:70vh;border-radius:10px;">`;
  }
}

function clearIdDocument(){
  if (ADMIN_ID_DOC_URL) {
    URL.revokeObjectURL(ADMIN_ID_DOC_URL);
    ADMIN_ID_DOC_URL = '';
  }
  const body = document.getElementById('idDocumentBody');
  if (body) body.innerHTML = '';
}

function closeIdDocumentModal(){
  clearIdDocument();
  closeModal('idDocumentModal');
}

async function approveId(studentId, btn){
  if (btn) { btn.disabled = true; btn.textContent = 'Approving…'; }

  const { ok, error } = await RCDA.safeFetch(`${RCDA_CONFIG.getApiBaseUrl()}/admin/id-verifications/${studentId}/approve`, { method: 'POST' });

  if (!ok) {
    RCDA.showToast(error || 'Could not approve the ID.', 'error');
    if (btn) { btn.disabled = false; btn.textContent = 'Approve'; }
    return;
  }

  RCDA.showToast('Valid ID approved.', 'success');
  loadIdVerifications();
  loadDashboardStats();
}

async function rejectId(studentId, btn){
  const reason = prompt('Why is this ID being rejected? The student will see this message.');
  if (reason === null) return;
  if (reason.trim().length < 5) {
    RCDA.showToast('Write a short reason (at least 5 characters).', 'error');
    return;
  }

  if (btn) { btn.disabled = true; btn.textContent = 'Rejecting…'; }

  const { ok, error } = await RCDA.safeFetch(`${RCDA_CONFIG.getApiBaseUrl()}/admin/id-verifications/${studentId}/reject`, {
    method: 'POST',
    body: { reason: reason.trim() },
  });

  if (!ok) {
    RCDA.showToast(error || 'Could not reject the ID.', 'error');
    if (btn) { btn.disabled = false; btn.textContent = 'Reject'; }
    return;
  }

  RCDA.showToast('Valid ID rejected.', 'success');
  loadIdVerifications();
  loadDashboardStats();
}

/* ============================================================
   Certifications — issuing a certificate moves its row from
   "Ready to Issue" to "Recently Issued". Every issued certificate
   can be re-opened, previewed and printed at any time from here.
   ============================================================ */
let ADMIN_CERTS = [];
let CURRENT_CERT = null;

/* Loads the "Ready to Issue" and "Recently Issued" lists from the
   API so certificates actually persist (previously client-only). */
async function loadCertifications(){
  const base = `${RCDA_CONFIG.getApiBaseUrl()}/admin/certifications`;
  const [ready, issued] = await Promise.all([
    RCDA.safeFetch(`${base}/ready`, { method: 'GET' }),
    RCDA.safeFetch(base, { method: 'GET' }),
  ]);
  if (ready.ok && ready.data) renderReadyCerts(ready.data.ready || []);
  if (issued.ok && issued.data) {
    ADMIN_CERTS = issued.data.certifications || [];
    renderIssuedCerts(ADMIN_CERTS);
  }
}

function renderReadyCerts(list){
  const el = document.getElementById('certsReadyList');
  if (!el) return;
  el.innerHTML = list.length ? list.map(c => {
    const reqs = (c.requirements || []).map(r =>
      `<div style="font-size:12px;${r.met ? 'color:#2e7d32;' : 'color:var(--gray);'}">${r.met ? '✓' : '✗'} ${escapeHtml(r.label)}</div>`
    ).join('');
    const ready = c.eligible
      ? '<span class="badge completed">Ready</span>'
      : '<span class="badge pending">Not eligible</span>';
    const btn = c.eligible
      ? `<button class="btn btn-sm btn-primary" onclick="issueCertificate(this)">Issue</button>`
      : `<button class="btn btn-sm btn-ghost" onclick="issueCertificate(this,true)" title="Force issue despite unmet requirements">Force</button>`;
    return `<div class="cert-row" data-student-id="${c.student_id}" data-course-id="${c.course_id}">
      <div class="cert-info"><h4>${escapeHtml(c.student_name)} — ${escapeHtml(c.course_name)}</h4>
        <span>${escapeHtml(c.course_type)}</span>${reqs ? `<div style="margin-top:4px;">${reqs}</div>` : ''}</div>
      <div style="display:flex;gap:10px;align-items:center;">${ready}${btn}</div>
    </div>`;
  }).join('')
    : '<div style="font-size:12px;color:var(--gray);padding:14px 0;">No certificates ready to issue.</div>';
}

function renderIssuedCerts(list){
  const el = document.getElementById('certsIssuedList');
  if (!el) return;
  el.innerHTML = list.length ? list.map(c => {
    const issued = c.issued_at
      ? new Date(c.issued_at).toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' })
      : '—';
    const badge = c.status === 'revoked'
      ? '<span class="badge needs">Revoked</span>'
      : '<span class="badge verified">Issued</span>';
    const courseName = (c.course && c.course.name) || '—';
    return `<div class="cert-row issued" data-cert-id="${c.id}">
      <div class="cert-info">
        <h4>${escapeHtml(c.student_name || 'Student')} — ${escapeHtml(courseName)}</h4>
        <span>Issued ${escapeHtml(issued)} · ${escapeHtml(c.certificate_no || '—')}${c.issued_by ? ' · by ' + escapeHtml(c.issued_by) : ''}</span>
      </div>
      <div style="display:flex;gap:8px;align-items:center;">${badge}
        <button class="btn btn-sm btn-ghost" onclick="viewCertificate(${c.id})">View Certificate</button>
        <button class="btn btn-sm btn-primary" onclick="viewCertificate(${c.id}, true)">Print</button>
      </div>
    </div>`;
  }).join('')
    : '<div style="font-size:12px;color:var(--gray);padding:14px 0;">No certificates issued yet.</div>';
}

async function issueCertificate(btn, force = false){
  const row = btn.closest('.cert-row');
  if (!row) return;

  const studentId = row.dataset.studentId;
  const courseId = row.dataset.courseId;
  if (!studentId || !courseId) { RCDA.showToast('Missing student or course for this certificate.', 'error'); return; }

  const { ok, data, error } = await RCDA.safeFetch(`${RCDA_CONFIG.getApiBaseUrl()}/admin/certifications`, {
    method: 'POST',
    body: { student_id: Number(studentId), course_id: Number(courseId), force: force ? 1 : 0 },
  });

  if (!ok) { RCDA.showToast(error || 'Could not issue certificate.', 'error'); return; }

  const cert = data.certification || {};
  const fallbackNo = cert.certificate_no || 'RC-' + new Date().getFullYear();

  RCDA.showToast('Certificate issued: ' + fallbackNo, 'success');

  await loadCertifications();
  loadDashboardStats();

  if (cert.id) {
    viewCertificate(cert.id, true);
  } else {
    row.remove();
  }
}

/* ---------- Issued-certificate viewer / printer ---------- */

function certDate(value, long){
  if (!value) return '—';
  const d = new Date(value);
  if (isNaN(d.getTime())) return '—';
  return d.toLocaleDateString('en-US', long
    ? { year: 'numeric', month: 'long', day: 'numeric' }
    : { year: 'numeric', month: 'short', day: 'numeric' });
}

const CERT_CSS = `
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

function ensureCertStyles(){
  if (document.getElementById('rcda-cert-style')) return;
  const s = document.createElement('style');
  s.id = 'rcda-cert-style';
  s.textContent = CERT_CSS;
  document.head.appendChild(s);
}

function certificateMarkup(cert){
  const logo = new URL('../images/RCDA logo.jpg', window.location.href).href;
  const courseName = (cert.course && cert.course.name) || 'Driving Course';
  const revoked = cert.status === 'revoked';

  return `<div class="rcda-cert${revoked ? ' revoked' : ''}" id="certSheet">
    <img src="${logo}" alt="RC Driving Academy" class="cert-logo" onerror="this.style.display='none'">
    <div class="cert-brand">RC Driving Academy</div>
    <h1>Certificate of Completion</h1>
    <div class="cert-sub">This certifies that</div>
    <div class="cert-name">${escapeHtml(cert.student_name || 'Student')}</div>
    <div class="cert-sub">has successfully completed</div>
    <div class="cert-course">${escapeHtml(courseName)}</div>
    <div class="cert-meta">
      <div><span>Certificate No.</span><b>${escapeHtml(cert.certificate_no || '—')}</b></div>
      <div><span>Date Issued</span><b>${escapeHtml(certDate(cert.issued_at, true))}</b></div>
      <div><span>Issued By</span><b>${escapeHtml(cert.issued_by || 'RC Driving Academy')}</b></div>
    </div>
    ${revoked ? '<div class="cert-revoked">REVOKED</div>' : ''}
    <div class="cert-foot">Verify this certificate with RC Driving Academy using the certificate number above.</div>
  </div>`;
}

async function viewCertificate(id, autoPrint){
  let cert = ADMIN_CERTS.find(c => Number(c.id) === Number(id)) || null;

  // Always pull a fresh copy so the preview reflects the stored record,
  // not a cached list row.
  const { ok, data } = await RCDA.safeFetch(`${RCDA_CONFIG.getApiBaseUrl()}/admin/certifications/${id}`, { method: 'GET' });
  if (ok && data && data.certification) cert = data.certification;

  if (!cert) { RCDA.showToast('Could not load this certificate.', 'error'); return; }

  CURRENT_CERT = cert;
  ensureCertStyles();

  const body = document.getElementById('certificateModalBody');
  if (body) body.innerHTML = certificateMarkup(cert);

  const title = document.getElementById('certificateModalTitle');
  if (title) title.textContent = 'Certificate ' + (cert.certificate_no || '');

  const revoke = document.getElementById('certRevokeBtn');
  if (revoke) {
    revoke.style.display = cert.status === 'revoked' ? 'none' : 'inline-flex';
  }

  openModal('certificateModal');

  if (autoPrint) setTimeout(printCertificateSheet, 350);
}

function printCertificateSheet(){
  const sheet = document.getElementById('certSheet');
  if (!sheet) return;

  const win = window.open('', '_blank');
  if (!win) {
    RCDA.showToast('Allow pop-ups to print the certificate.', 'error');
    return;
  }

  const docTitle = CURRENT_CERT && CURRENT_CERT.certificate_no ? CURRENT_CERT.certificate_no : 'Certificate';
  win.document.write('<!DOCTYPE html><html><head><meta charset="utf-8"><title>' + escapeHtml(docTitle) + '</title>'
    + '<style>@page{size:A4 landscape;margin:14mm;}' + CERT_CSS + '</style></head><body>'
    + sheet.outerHTML + '</body></html>');
  win.document.close();
  win.focus();
  setTimeout(() => win.print(), 400);
}

async function revokeCertificate(){
  if (!CURRENT_CERT || !CURRENT_CERT.id) return;
  if (!confirm('Revoke certificate ' + (CURRENT_CERT.certificate_no || '') + '? It will be marked as no longer valid.')) return;

  const { ok, error } = await RCDA.safeFetch(`${RCDA_CONFIG.getApiBaseUrl()}/admin/certifications/${CURRENT_CERT.id}/revoke`, { method: 'POST' });
  if (!ok) { RCDA.showToast(error || 'Could not revoke the certificate.', 'error'); return; }

  RCDA.showToast('Certificate revoked.', 'success');
  closeModal('certificateModal');
  CURRENT_CERT = null;
  loadCertifications();
  loadDashboardStats();
}

/* ============================================================
   Reports — render the six live report cards from /admin/reports.
   ============================================================ */
async function loadReports(){
  const { ok, data } = await RCDA.safeFetch(`${RCDA_CONFIG.getApiBaseUrl()}/admin/reports`, { method: 'GET' });
  if (!ok || !data) return;

  const totals = data.totals || {};
  document.getElementById('reportTotals').innerHTML = [
    ['Students', totals.students],
    ['Instructors', totals.instructors],
    ['Enrollments', totals.enrollments],
    ['Revenue', '₱' + Number(totals.revenue || 0).toLocaleString('en-PH')],
    ['Certifications', totals.certifications],
  ].map(([k, v]) => `<div style="background:var(--cream);border:1px solid var(--gray-light);border-radius:12px;padding:12px 18px;min-width:120px;">
      <div style="font-size:12px;color:var(--gray);">${k}</div>
      <div style="font-size:20px;font-weight:700;color:var(--charcoal);">${v}</div></div>`).join('');

  document.getElementById('repEnrollments').innerHTML = (data.enrollmentsByCourse || [])
    .map(c => `<div style="display:flex;justify-content:space-between;padding:7px 0;border-bottom:1px solid var(--gray-light);">
      <b>${escapeHtml(c.course)}</b><span>${c.enrollments} enrolled · ${c.active} active · ${c.completed} done</span></div>`).join('')
    || '<div style="color:var(--gray);">No data.</div>';

  document.getElementById('repRevenue').innerHTML = (data.revenueByMonth || [])
    .map(r => `<div style="display:flex;justify-content:space-between;padding:7px 0;border-bottom:1px solid var(--gray-light);">
      <b>${escapeHtml(r.month)}</b><span>₱${Number(r.revenue || 0).toLocaleString('en-PH')}</span></div>`).join('')
    || '<div style="color:var(--gray);">No revenue yet.</div>';

  const ps = data.paymentStatus || {};
  document.getElementById('repPayments').innerHTML = `
    <div style="display:flex;justify-content:space-between;padding:7px 0;border-bottom:1px solid var(--gray-light);"><b>Verified</b><span class="badge verified">${ps.verified || 0}</span></div>
    <div style="display:flex;justify-content:space-between;padding:7px 0;border-bottom:1px solid var(--gray-light);"><b>Pending</b><span class="badge pending">${ps.pending || 0}</span></div>
    <div style="display:flex;justify-content:space-between;padding:7px 0;"><b>Rejected</b><span class="badge needs">${ps.rejected || 0}</span></div>`;

  document.getElementById('repCerts').innerHTML = (data.certificationsByCourse || [])
    .map(c => `<div style="display:flex;justify-content:space-between;padding:7px 0;border-bottom:1px solid var(--gray-light);">
      <b>${escapeHtml(c.course)}</b><span>${c.issued} issued · ${c.revoked} revoked</span></div>`).join('')
    || '<div style="color:var(--gray);">No data.</div>';

  const a = data.attendance || {};
  document.getElementById('repAttendance').innerHTML = `
    <div style="display:flex;justify-content:space-between;padding:7px 0;border-bottom:1px solid var(--gray-light);"><b>Present</b><span class="badge active">${a.present || 0}</span></div>
    <div style="display:flex;justify-content:space-between;padding:7px 0;border-bottom:1px solid var(--gray-light);"><b>Late</b><span class="badge pending">${a.late || 0}</span></div>
    <div style="display:flex;justify-content:space-between;padding:7px 0;"><b>Absent</b><span class="badge needs">${a.absent || 0}</span></div>
    <div style="font-size:12px;color:var(--gray);margin-top:6px;">${a.sessions_done || 0} sessions completed</div>`;

  const s = data.assessments || {};
  document.getElementById('repAssess').innerHTML = `
    <div style="display:flex;justify-content:space-between;padding:7px 0;border-bottom:1px solid var(--gray-light);"><b>Passed / Failed</b><span>${s.passed || 0} / ${s.failed || 0}</span></div>
    <div style="display:flex;justify-content:space-between;padding:7px 0;border-bottom:1px solid var(--gray-light);"><b>Fast / Average / Needs</b><span>${s.fast || 0} / ${s.average || 0} / ${s.needs || 0}</span></div>
    <div style="display:flex;justify-content:space-between;padding:7px 0;"><b>Safety flagged</b><span class="badge needs">${s.safety_flagged || 0}</span></div>`;
}

/* ============================================================
   Notifications — one tab per category (payments, accounts,
   requests, chat escalations, certificates) plus the SMS /
   email / maps delivery log. Feed comes from /admin/notifications.
   ============================================================ */
let NOTIF_CATEGORY = '';   // '' = all feed entries, 'log' = delivery log
let NOTIF_PAGE = 1;

const NOTIF_FEED_TABS = [
  { key: '', label: 'All' },
  { key: 'payment', label: 'Payments' },
  { key: 'account', label: 'Accounts' },
  { key: 'request', label: 'Requests' },
  { key: 'chat', label: 'Chat' },
  { key: 'certificate', label: 'Certificates' },
  { key: 'log', label: 'Delivery log' },
];

const NOTIF_META = {
  payment: { label: 'Payment', cls: 'payment' },
  account: { label: 'Account', cls: 'account' },
  request: { label: 'Request', cls: 'request' },
  chat: { label: 'Chat escalation', cls: 'chat' },
  certificate: { label: 'Certificate', cls: 'certificate' },
};

const NOTIF_ICONS = {
  payment: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2.5" y="5.5" width="19" height="13" rx="2.5"/><path d="M2.5 10h19"/><path d="M6 15h4"/></svg>',
  account: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>',
  request: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="9" y1="15" x2="15" y2="15"/></svg>',
  chat: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 11.5a8.38 8.38 0 01-.9 3.8 8.5 8.5 0 01-7.6 4.7 8.38 8.38 0 01-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 01-.9-3.8 8.5 8.5 0 014.7-7.6 8.38 8.38 0 013.8-.9h.5a8.48 8.48 0 018 8v.5z"/></svg>',
  certificate: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="8" r="6"/><path d="M15.477 12.89L17 22l-5-3-5 3 1.523-9.11"/></svg>',
};

function notifTimeAgo(iso) {
  if (!iso) return '';
  const date = new Date(iso);
  if (isNaN(date)) return '';
  const diff = Math.max(0, Math.round((Date.now() - date.getTime()) / 1000));
  if (diff < 60) return 'just now';
  if (diff < 3600) return Math.floor(diff / 60) + 'm ago';
  if (diff < 86400) return Math.floor(diff / 3600) + 'h ago';
  if (diff < 604800) return Math.floor(diff / 86400) + 'd ago';
  return date.toLocaleDateString('en-PH', { month: 'short', day: 'numeric' });
}

function updateNotifNavBadge(counts) {
  const badge = document.getElementById('notifNavBadge');
  if (!badge) return;
  const unread = (counts && counts.unread) || 0;
  badge.textContent = unread > 99 ? '99+' : String(unread);
  badge.hidden = unread < 1;
}

function toggleNotifPanes() {
  const log = NOTIF_CATEGORY === 'log';
  const feedPane = document.getElementById('notifFeedPane');
  const logPane = document.getElementById('notifLogPane');
  if (feedPane) feedPane.hidden = log;
  if (logPane) logPane.hidden = !log;

  const markAll = document.getElementById('notifMarkAll');
  if (markAll) {
    markAll.disabled = log;
    markAll.style.opacity = log ? '.45' : '';
  }
}

function renderNotifTabs(counts) {
  const host = document.getElementById('notifTabs');
  if (!host) return;

  const cats = (counts && counts.categories) || {};
  const totalUnread = (counts && counts.unread) || 0;

  host.innerHTML = NOTIF_FEED_TABS.map(tab => {
    const active = tab.key === NOTIF_CATEGORY;
    const unread = tab.key === 'log'
      ? 0
      : (tab.key ? (cats[tab.key] || {}).unread || 0 : totalUnread);

    return `<button type="button" class="notif-tab${active ? ' active' : ''}" role="tab"
        aria-selected="${active}" onclick="setNotifTab('${tab.key}')">
        ${tab.label}${unread ? `<span class="notif-count">${unread}</span>` : ''}
      </button>`;
  }).join('');
}

function renderNotifSummary(counts) {
  const el = document.getElementById('notifUnreadSummary');
  if (!el) return;
  const total = (counts && counts.total) || 0;
  const unread = (counts && counts.unread) || 0;
  el.textContent = total ? `${unread} unread · ${total} total` : '';
}

function renderNotifFeed(feed, pagination) {
  const host = document.getElementById('notifFeed');
  const pager = document.getElementById('notifPager');
  if (!host) return;

  if (!Array.isArray(feed)) {
    host.innerHTML = `<div class="notif-loading">
      <div class="notif-spin"></div>
      <span>Loading notifications…</span>
    </div>`;
    if (pager) pager.innerHTML = '';
    return;
  }

  if (!feed.length) {
    const tab = NOTIF_FEED_TABS.find(t => t.key === NOTIF_CATEGORY);
    const label = tab && tab.key ? tab.label.toLowerCase() : '';
    host.innerHTML = `<div class="notif-empty">
      <b>You're all caught up.</b>
      <span>${label ? `No ${escapeHtml(label)} notifications yet.` : 'New payments, accounts, requests, chat escalations and certificates will appear here.'}</span>
    </div>`;
    if (pager) pager.innerHTML = '';
    return;
  }

  host.innerHTML = feed.map(n => {
    const meta = NOTIF_META[n.category] || { label: 'Notification', cls: 'system' };
    const panel = (n.meta && n.meta.panel) || '';
    const unread = !n.read;

    return `<button type="button" class="notif-item ${meta.cls}${unread ? ' unread' : ''}"
        onclick="openNotif(${n.id}, '${escapeHtml(panel)}')">
        <span class="notif-ico">${NOTIF_ICONS[n.category] || ''}</span>
        <span class="notif-main">
          <span class="notif-top">
            <b>${escapeHtml(n.title || meta.label)}</b>
            <span class="notif-time">${escapeHtml(notifTimeAgo(n.created_at))}</span>
          </span>
          ${n.body ? `<span class="notif-body">${escapeHtml(n.body)}</span>` : ''}
          <span class="notif-foot">
            <span class="notif-cat">${meta.label}</span>
            ${n.user_name ? `<span class="notif-user">${escapeHtml(n.user_name)}</span>` : ''}
            ${panel ? '<span class="notif-open">Open ›</span>' : ''}
          </span>
        </span>
        ${unread ? '<span class="notif-dot" aria-label="Unread"></span>' : ''}
      </button>`;
  }).join('');

  if (!pager) return;
  const page = (pagination && pagination.current_page) || 1;
  const last = (pagination && pagination.last_page) || 1;
  pager.innerHTML = last > 1
    ? `<button type="button" class="btn btn-sm btn-ghost" ${page <= 1 ? 'disabled' : ''} onclick="loadNotifications(${page - 1})">‹ Prev</button>
       <span>Page ${page} of ${last}</span>
       <button type="button" class="btn btn-sm btn-ghost" ${page >= last ? 'disabled' : ''} onclick="loadNotifications(${page + 1})">Next ›</button>`
    : '';
}

function renderNotifLog(channels, logs) {
  const ch = channels || {};
  const badge = on => on ? '<span class="badge active">Live</span>' : '<span class="badge pending">Disabled</span>';
  const channelHost = document.getElementById('notifChannels');
  if (channelHost) {
    channelHost.innerHTML = `
      <div class="notif-channel">SMS ${badge(ch.sms_enabled)}</div>
      <div class="notif-channel">Email ${badge(ch.mail_enabled)}</div>
      <div class="notif-channel">Maps ${badge(ch.maps_enabled)}</div>`;
  }

  const logHost = document.getElementById('notifLog');
  if (!logHost) return;

  if (!Array.isArray(logs)) {
    logHost.innerHTML = `<div class="notif-loading"><div class="notif-spin"></div><span>Loading delivery log…</span></div>`;
    return;
  }

  logHost.innerHTML = logs.length ? logs.map(l => {
    const sent = l.sent ? '<span class="badge verified">Sent</span>' : '<span class="badge pending">Logged</span>';
    const when = l.created_at ? new Date(l.created_at).toLocaleString('en-PH') : '';
    return `<div class="notif-log-row">
      <div><b>${escapeHtml((l.channel || '').toUpperCase())} · ${escapeHtml(l.event || '')}</b><br>
      <span style="font-size:12px;color:var(--gray);">${escapeHtml(l.recipient || '')} · ${escapeHtml(when)}</span></div>
      ${sent}</div>`;
  }).join('') : '<div class="notif-empty"><b>No delivery attempts yet.</b><span>They fire on registration, payment verification and certificate issuance.</span></div>';
}

async function loadNotifications(page) {
  NOTIF_PAGE = page || 1;
  toggleNotifPanes();

  const feedHost = document.getElementById('notifFeed');
  const logHost = document.getElementById('notifLog');
  if (feedHost && (!Array.isArray(window._notifFeedCache) || page)) {
    feedHost.innerHTML = `<div class="notif-loading"><div class="notif-spin"></div><span>Loading notifications…</span></div>`;
  }
  if (logHost && NOTIF_CATEGORY === 'log') {
    logHost.innerHTML = `<div class="notif-loading"><div class="notif-spin"></div><span>Loading delivery log…</span></div>`;
  }

  const params = new URLSearchParams();
  if (NOTIF_CATEGORY && NOTIF_CATEGORY !== 'log') params.set('category', NOTIF_CATEGORY);
  if (NOTIF_CATEGORY === 'log') params.set('load_log', '1');
  params.set('feed_page', NOTIF_PAGE);

  const { ok, data } = await RCDA.safeFetch(
    `${RCDA_CONFIG.getApiBaseUrl()}/admin/notifications?${params.toString()}`,
    { method: 'GET' }
  );

  if (!ok || !data) {
    const host = document.getElementById('notifFeed');
    if (host) host.innerHTML = '<div class="notif-empty"><b>Could not load notifications.</b><span>Please try again in a moment.</span></div>';
    return;
  }

  renderNotifTabs(data.counts);
  renderNotifSummary(data.counts);
  window._notifFeedCache = data.feed || [];
  renderNotifFeed(window._notifFeedCache, data.feed_pagination || {});
  if (NOTIF_CATEGORY === 'log') {
    renderNotifLog(data.channels, data.logs || []);
  }
  updateNotifNavBadge(data.counts);
}

async function loadNotifSummary() {
  const { ok, data } = await RCDA.safeFetch(`${RCDA_CONFIG.getApiBaseUrl()}/admin/notifications/summary`, { method: 'GET' });
  if (ok && data) updateNotifNavBadge(data.counts);
}

function setNotifTab(key) {
  NOTIF_CATEGORY = key || '';
  NOTIF_PAGE = 1;
  toggleNotifPanes();
  loadNotifications(1);
}

async function markNotificationsRead(payload) {
  const { ok, data } = await RCDA.safeFetch(`${RCDA_CONFIG.getApiBaseUrl()}/admin/notifications/read`, {
    method: 'POST',
    body: JSON.stringify(payload),
  });
  if (ok && data && data.counts) updateNotifNavBadge(data.counts);
  return ok ? data : null;
}

async function openNotif(id, panel) {
  await markNotificationsRead({ ids: [id] });

  if (panel && ADMIN_TITLES[panel]) {
    showPanel(panel, typeof navItemForPanel === 'function' ? navItemForPanel(panel) : null);
    return;
  }

  loadNotifications(NOTIF_PAGE);
}

async function markAllNotificationsRead() {
  if (NOTIF_CATEGORY === 'log') return;
  await markNotificationsRead(NOTIF_CATEGORY ? { category: NOTIF_CATEGORY } : { all: true });
  loadNotifications(NOTIF_PAGE);
}

/* ---- STUDENT REQUESTS (booking + instructor preference + reschedule) ---- */
async function loadAdminRequests(page) {
  page = page || 1;
  const filter = document.getElementById('reqStatusFilter') ? document.getElementById('reqStatusFilter').value : 'pending';
  const qs = filter ? ('?status=' + encodeURIComponent(filter)) : '';
  const { ok, data } = await RCDA.safeFetch(`${RCDA_CONFIG.getApiBaseUrl()}/admin/requests` + (qs ? (qs + '&page=' + page) : ('?page=' + page)));
  const tb = document.getElementById('requestsTbody');
  if (!tb) return;

  // The override dropdown needs the instructor list; it may not have loaded yet.
  if (!ADMIN_INSTRUCTORS.length) {
    try { await loadInstructorList(); } catch (e) { /* keep the page usable */ }
  }
  const pdcInstructors = ADMIN_INSTRUCTORS.filter(i => !i.specialization || String(i.specialization).includes('PDC'));
  const instructorOptions = pdcInstructors.length ? pdcInstructors : ADMIN_INSTRUCTORS;

  if (!ok || !data) { tb.innerHTML = '<tr><td colspan="6" style="text-align:center;color:var(--gray);padding:20px;">Could not load requests.</td></tr>'; return; }

  const list = data.requests || [];
  if (!list.length) { tb.innerHTML = '<tr><td colspan="6" style="text-align:center;color:var(--gray);padding:20px;">No requests found.</td></tr>'; return; }

  tb.innerHTML = list.map(r => {
    const typeLabel = r.type === 'reschedule' ? 'Reschedule' : (r.type === 'booking' ? 'PDC Booking' : 'Instructor Preference');
    const typeCls = r.type === 'reschedule' ? 'needs' : (r.type === 'booking' ? 'verified' : 'upcoming');
    const statusCls = r.status === 'pending' ? 'pending' : (r.status === 'accepted' ? 'verified' : 'needs');

    let detail = '';
    if (r.type === 'reschedule') {
      detail = `From: ${r.from_schedule ? escapeHtml((r.from_schedule.course || '') + ' · ' + r.from_schedule.date + ' ' + r.from_schedule.time_start) : '—'}<br>To: ${r.to_schedule ? escapeHtml((r.to_schedule.course || '') + ' · ' + r.to_schedule.date + ' ' + r.to_schedule.time_start) : '—'}`;
    } else if (r.type === 'booking') {
      const inst = r.preferred_instructor ? r.preferred_instructor.name : 'Any available';
      const pref = r.preferred_instructor_gender ? ` · student prefers ${r.preferred_instructor_gender}` : '';
      detail = `Preferred date: <b>${escapeHtml(String(r.preferred_date || '—'))}</b><br>Auto-assigned: ${escapeHtml(inst)}${escapeHtml(pref)}`;

      if (r.status === 'pending' && instructorOptions.length) {
        detail += `<div style="margin-top:6px;display:flex;align-items:center;gap:6px;">
          <span style="font-size:11.5px;color:var(--gray);white-space:nowrap;">Assign:</span>
          <select id="reqInst-${r.id}" style="padding:5px 8px;border-radius:8px;border:1.5px solid var(--gray-light);font-size:12px;background:var(--cream);max-width:240px;">
            ${instructorOptions.map(i => `<option value="${i.id}" ${r.preferred_instructor && i.id === r.preferred_instructor.id ? 'selected' : ''}>${escapeHtml(i.name)}${i.gender ? ' (' + escapeHtml(i.gender) + ')' : ''}${i.specialization ? ' · ' + escapeHtml(i.specialization) : ''}</option>`).join('')}
          </select>
        </div>`;
      }
    } else {
      const pref = r.preferred_gender === 'male' ? 'Male' : (r.preferred_gender === 'female' ? 'Female' : 'No preference');
      detail = `Requested instructor gender: <b>${escapeHtml(pref)}</b>`;
    }
    if (r.note) detail += `<br><span style="color:var(--gray);">Note: ${escapeHtml(r.note)}</span>`;

    const when = r.created_at ? new Date(r.created_at).toLocaleDateString('en-PH') : '—';

    const actions = r.status === 'pending'
      ? `<div style="display:flex;gap:6px;justify-content:flex-end;">
           <button class="btn btn-sm btn-success" onclick="acceptRequest(${r.id})">Accept</button>
           <button class="btn btn-sm btn-danger" onclick="rejectRequest(${r.id})">Reject</button>
         </div>`
      : `<span style="font-size:11.5px;color:var(--gray);${r.admin_note ? '' : 'display:none;'}">${escapeHtml(String(r.admin_note || ''))}</span>`;

    return `<tr data-id="${r.id}">
      <td><b>${escapeHtml(r.student_name ? r.student_name : ('#' + r.student_id))}</b></td>
      <td><span class="badge ${typeCls}">${typeLabel}</span></td>
      <td style="font-size:12.5px;line-height:1.5;">${detail}</td>
      <td style="white-space:nowrap;color:var(--gray);">${when}</td>
      <td><span class="badge ${statusCls}">${escapeHtml(r.status)}</span></td>
      <td>${actions}</td>
    </tr>`;
  }).join('');
  renderPagination(document.getElementById('requestsTable'), 'requestsPagination', data.pagination, 'loadAdminRequests');
}

async function acceptRequest(id) {
  const sel = document.getElementById('reqInst-' + id);
  const body = {};
  if (sel && sel.value) body.instructor_id = sel.value;

  const { ok, error } = await RCDA.safeFetch(`${RCDA_CONFIG.getApiBaseUrl()}/admin/requests/${id}/accept`, { method: 'POST', body });
  if (!ok) { RCDA.showToast(error || 'Could not accept request.'); return; }
  RCDA.showToast('Request accepted. Student notified.', 'success');
  loadAdminRequests();
}

async function rejectRequest(id) {
  const note = prompt('Optional note for the student (reason for rejection):') || '';
  const { ok, error } = await RCDA.safeFetch(`${RCDA_CONFIG.getApiBaseUrl()}/admin/requests/${id}/reject`, { method: 'POST', body: { admin_note: note } });
  if (!ok) { RCDA.showToast(error || 'Could not reject request.'); return; }
  RCDA.showToast('Request rejected. Student notified.', 'success');
  loadAdminRequests();
}
