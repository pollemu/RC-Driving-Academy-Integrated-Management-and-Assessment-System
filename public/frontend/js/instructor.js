/* ============================================================
   instructor.js — loaded ONLY on instructor/dashboard.html
   ============================================================ */

const INSTR_TITLES = {
  dashboard: 'My Dashboard', mystudents: 'My Students',
  myschedule: 'My Schedule', myassessment: 'Assess Students',
  attendance: 'Session Attendance', profile: 'My Profile', settings: 'Account Settings'
};

function showPanel(panel, el) {
  document.querySelectorAll('.panel').forEach(p => p.classList.remove('active'));
  const target = document.getElementById('instr-' + panel);
  if (target) target.classList.add('active');

  const titleEl = document.getElementById('instrTitle');
  if (titleEl && INSTR_TITLES[panel]) titleEl.textContent = INSTR_TITLES[panel];

  if (el) {
    const parentNav = el.closest ? el.closest('.sidebar-nav') : null;
    if (parentNav) parentNav.querySelectorAll('.nav-item').forEach(n => n.classList.remove('active'));
    if (el.classList) el.classList.add('active');
  }
  closeAllSidebars();

  if (panel === 'myschedule') highlightToday();
  if (panel === 'profile' && window.RCDAProfile) {
    const c = document.getElementById('instrProfileSummary');
    if (c && !c.dataset.rpLoaded) {
      RCDAProfile.renderSummary({ container: c });
      c.dataset.rpLoaded = '1';
    }
  }
  if (panel === 'settings' && window.RCDAProfile) {
    const c = document.getElementById('instrSettingsPanel');
    if (c && !c.dataset.rpLoaded) {
      RCDAProfile.render({ container: c, mode: 'self' });
      c.dataset.rpLoaded = '1';
    }
  }
  if (typeof rememberPanel === 'function') rememberPanel(panel);
}

/* ============================================================
   Weekly Schedule — highlight the column matching today's
   actual day of the week (kept for any week-style views).
   ============================================================ */
function highlightToday() {
  const todayIndex = new Date().getDay();
  document.querySelectorAll('.week-col[data-day]').forEach(col => {
    col.classList.toggle('today', Number(col.dataset.day) === todayIndex);
  });
}

document.addEventListener('DOMContentLoaded', highlightToday);

/* ============================================================
   Topbar — turns from orange to solid white once the page
   is scrolled down.
   ============================================================ */
function updateTopbarScrollState() {
  const topbar = document.querySelector('.dash-topbar');
  if (!topbar) return;
  topbar.classList.toggle('scrolled', window.scrollY > 8);
}
window.addEventListener('scroll', updateTopbarScrollState);
document.addEventListener('DOMContentLoaded', updateTopbarScrollState);

/* ============================================================
   API WIRING
   ============================================================ */
const INSTR_API = () => RCDA_CONFIG.getApiBaseUrl();

function escapeHtml(str) {
  const div = document.createElement('div');
  div.textContent = str == null ? '' : String(str);
  return div.innerHTML;
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

function setText(id, value) {
  const el = document.getElementById(id);
  if (el) el.textContent = value;
}

/* Aggregate the instructor's students from their scheduled sessions. */
function buildStudents(schedules) {
  const map = new Map();
  (schedules || []).forEach(s => {
    (s.students || []).forEach(st => {
      if (!map.has(st.student_id)) {
        map.set(st.student_id, { id: st.student_id, name: st.student_name, courses: new Set(), lastDate: s.date });
      } else {
        const cur = map.get(st.student_id);
        if (s.date > cur.lastDate) cur.lastDate = s.date;
      }
      map.get(st.student_id).courses.add(s.course ? s.course.name : 'Course');
    });
  });
  return Array.from(map.values());
}

function renderStudentRows(tbodyId, students) {
  const tbody = document.getElementById(tbodyId);
  if (!tbody) return;
  if (!students.length) {
    tbody.innerHTML = '<tr><td colspan="5" style="text-align:center;color:var(--gray);padding:18px;">No students assigned yet.</td></tr>';
    return;
  }
  tbody.innerHTML = students.map(st => {
    const pref = st.preferred_instructor_gender === 'male' ? 'Prefers male'
      : (st.preferred_instructor_gender === 'female' ? 'Prefers female' : '');
    const prefBadge = pref ? `<span class="badge pending" style="font-size:11px;">${escapeHtml(pref)}</span>` : '';
    return `<tr data-name="${escapeHtml((st.name || '').toLowerCase())}" data-classification="unknown">
      <td><b>${escapeHtml(st.name || 'Student')}</b></td>
      <td>${escapeHtml(Array.from(st.courses).join(', ') || '—')}</td>
      <td>${prefBadge || '<span class="badge">—</span>'}</td>
      <td>${escapeHtml(st.lastDate || '—')}</td>
      <td><button class="btn btn-sm btn-primary" onclick="showPanel('myassessment',this)">Assess</button></td>
    </tr>`;
  }).join('');
}

/* ---- DASHBOARD ---- */
async function loadInstructorDashboard() {
  const API = INSTR_API();
  const [dash, fb] = await Promise.all([
    RCDA.safeFetch(API + '/instructor/dashboard'),
    RCDA.safeFetch(API + '/instructor/feedback'),
  ]);
  const today = (dash.ok && dash.data.today) || [];
  const upcoming = (dash.ok && dash.data.upcoming) || [];
  const schedules = (fb.ok && fb.data.schedules) || [];
  const students = buildStudents(schedules);

  setText('statStudents', students.length);
  setText('statToday', today.length);
  setText('statUpcoming', upcoming.length);

  renderStudentRows('dashStudentsBody', students.slice(0, 5));
}

/* ---- MY STUDENTS ---- */
async function loadInstructorStudents() {
  const { ok, data } = await RCDA.safeFetch(INSTR_API() + '/instructor/students');
  const students = (ok && data.students) || [];
  const rows = students.map(s => ({
    id: s.id,
    name: s.name,
    preferred_instructor_gender: s.preferred_instructor_gender,
    courses: new Set([s.source === 'assigned' ? 'Assigned' : 'Session']),
    lastDate: '—',
  }));
  renderStudentRows('studentsTableBody', rows);
  filterStudents();
}

/* ---- MY SCHEDULE ---- */
async function loadInstructorSchedule() {
  const { ok, data } = await RCDA.safeFetch(INSTR_API() + '/instructor/dashboard');
  const today = (ok && data.today) || [];
  const upcoming = (ok && data.upcoming) || [];
  const all = [...today, ...upcoming];
  const tbody = document.getElementById('instrScheduleBody');
  if (!tbody) return;
  if (!all.length) {
    tbody.innerHTML = '<tr><td colspan="5" style="text-align:center;color:var(--gray);padding:18px;">No sessions scheduled.</td></tr>';
    return;
  }
  tbody.innerHTML = all.map(s => `
    <tr>
      <td>${escapeHtml(s.date || '—')}</td>
      <td>${escapeHtml(s.course ? s.course.name : '—')}</td>
      <td>${escapeHtml((s.time_start || '') + ' – ' + (s.time_end || ''))}</td>
      <td>${escapeHtml(s.venue || '—')}</td>
      ${statusBadge(s.status)}
    </tr>`).join('');
}

/* ---- ASSESS: session browser (tabs + search + date filter) → students
   modal → assessment modal. Same interaction pattern as the admin's
   All Sessions grid: pick a date, view the students, click Assess. ---- */
let ASSESS_SCHEDULES = [];
let ASSESS_VIEW = { tab: 'ALL', search: '', date: null, calMonth: null };
let ASSESS_ASSESSED = new Set(); // "scheduleId:studentId" pairs done this visit
let ASSESS_CURRENT = null;       // { scheduleId, studentId, studentName, schedule }

/* Students' names are searchable too — the instructor often remembers the
   student, not the session date. */
function assessSearchable(s) {
  return [
    (s.students || []).map(st => st.student_name).join(' '),
    s.course ? s.course.name : '',
    s.course ? s.course.type : '',
    s.venue || '',
    s.status || '',
    s.date || '',
    s.end_date || ''
  ].join(' ').toLowerCase();
}

/* Long date label used by the date-filter chip. */
function fmtYmdLong(ymd) {
  const d = new Date(String(ymd) + 'T00:00:00');
  if (isNaN(d)) return String(ymd);
  return d.toLocaleDateString('en-PH', { month: 'short', day: 'numeric', year: 'numeric' });
}

function setAssessViewTab(tab) { ASSESS_VIEW.tab = tab; renderAssessBrowser(); }
function onAssessSearch(value) { ASSESS_VIEW.search = (value || '').trim().toLowerCase(); renderAssessBrowser(); }

/* ---------- Date-filter calendar popup ---------- */
function toggleAssessCalendar() {
  const cal = document.getElementById('assessCalendar');
  if (!cal) return;
  if (cal.style.display === 'none') {
    if (!ASSESS_VIEW.calMonth) {
      ASSESS_VIEW.calMonth = (ASSESS_VIEW.date || new Date().toISOString().slice(0, 10)).slice(0, 7);
    }
    renderAssessCalendar();
    cal.style.display = 'block';
  } else {
    cal.style.display = 'none';
  }
}

/* Clicking anywhere outside the filter row closes the calendar. */
document.addEventListener('click', function (e) {
  if (!e.target.closest('.sessions-filter-wrap')) {
    const cal = document.getElementById('assessCalendar');
    if (cal) cal.style.display = 'none';
  }
});

function assessCalNav(dir) {
  if (!ASSESS_VIEW.calMonth) return;
  const [y, m] = ASSESS_VIEW.calMonth.split('-').map(Number);
  const d = new Date(y, m - 1 + dir, 1);
  ASSESS_VIEW.calMonth = d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0');
  renderAssessCalendar();
}

function pickAssessDay(ymd) {
  ASSESS_VIEW.date = (ASSESS_VIEW.date === ymd) ? null : ymd; // click again to clear
  const btn = document.getElementById('assessDateFilterBtn');
  if (btn) btn.classList.toggle('active', !!ASSESS_VIEW.date);
  const cal = document.getElementById('assessCalendar');
  if (cal) cal.style.display = 'none';
  renderAssessBrowser();
}

function clearAssessDate() {
  ASSESS_VIEW.date = null;
  const btn = document.getElementById('assessDateFilterBtn');
  if (btn) btn.classList.remove('active');
  renderAssessBrowser();
}

function renderAssessCalendar() {
  const cal = document.getElementById('assessCalendar');
  if (!cal) return;

  /* Persistent skeleton once: nav buttons must stay attached between renders
     or the bubbling click outside-detector closes the calendar mid-click. */
  if (!cal.querySelector('.sessions-cal-head')) {
    cal.innerHTML = '<div class="sessions-cal-head">'
      + '<button type="button" class="sessions-cal-nav" onclick="assessCalNav(-1)">‹</button>'
      + '<span class="sessions-cal-title"></span>'
      + '<button type="button" class="sessions-cal-nav" onclick="assessCalNav(1)">›</button>'
      + '</div><div class="sessions-cal-grid"></div>';
  }

  const byDate = {};
  (ASSESS_SCHEDULES || []).forEach(s => {
    if (s.date) byDate[String(s.date).slice(0, 10)] = (byDate[String(s.date).slice(0, 10)] || 0) + 1;
  });

  const [y, m] = ASSESS_VIEW.calMonth.split('-').map(Number);
  const first = new Date(y, m - 1, 1);
  const monthLabel = first.toLocaleDateString('en-PH', { month: 'long', year: 'numeric' });
  const today = new Date().toISOString().slice(0, 10);
  const daysInMonth = new Date(y, m, 0).getDate();
  const leading = first.getDay(); // 0 = Sunday

  const titleEl = cal.querySelector('.sessions-cal-title');
  if (titleEl) titleEl.textContent = monthLabel;

  const gridEl = cal.querySelector('.sessions-cal-grid');
  if (!gridEl) return;
  let html = '';
  ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'].forEach(d => { html += '<span class="sessions-cal-dow">' + d + '</span>'; });
  for (let i = 0; i < leading; i++) html += '<span></span>';
  for (let d = 1; d <= daysInMonth; d++) {
    const ymd = ASSESS_VIEW.calMonth + '-' + String(d).padStart(2, '0');
    const n = byDate[ymd] || 0;
    html += '<button type="button" class="sessions-cal-day' + (ASSESS_VIEW.date === ymd ? ' selected' : '') + (ymd === today ? ' today' : '') + '" '
      + 'onclick="pickAssessDay(\'' + ymd + '\')" title="' + (n ? n + ' session(s)' : 'No sessions') + '">'
      + d + (n ? '<span class="dot"></span>' : '') + '</button>';
  }
  gridEl.innerHTML = html;
}

/* ---------- Session cards ---------- */
function assessStatusLabel(st) {
  if (st === 'open') return 'Open';
  if (st === 'done' || st === 'completed') return 'Completed';
  if (st === 'full') return 'Full';
  if (st === 'cancelled') return 'Cancelled';
  if (st === 'active') return 'Ongoing';
  return (st || 'Pending').charAt(0).toUpperCase() + (st || 'pending').slice(1);
}

function assessStatusCls(st) {
  return ({
    open: 'confirmed', done: 'done', full: 'pending',
    cancelled: 'rejected', pending: 'pending', completed: 'done', active: 'enrolled'
  }[st] || 'pending');
}

function assessFmtDate(d) {
  if (!d) return '—';
  const dt = new Date(String(d) + 'T00:00:00');
  if (isNaN(dt)) return escapeHtml(String(d));
  return dt.toLocaleDateString('en-PH', { month: 'short', day: 'numeric' });
}

function renderAssessBrowser() {
  const grid = document.getElementById('assessSessionsGrid');
  if (!grid) return;
  const sched = ASSESS_SCHEDULES || [];
  const count = type => sched.filter(s => ((s.course && s.course.type) || '').toUpperCase() === type).length;

  /* Tab highlight + live counts */
  [['assessTabAll', 'ALL'], ['assessTabTDC', 'TDC'], ['assessTabPDC', 'PDC']].forEach(([id, key]) => {
    const el = document.getElementById(id);
    if (!el) return;
    el.classList.toggle('active', ASSESS_VIEW.tab === key);
    el.innerHTML = (key === 'ALL' ? 'All' : key + ' Sessions')
      + ' <span class="tab-count">(' + (key === 'ALL' ? sched.length : count(key)) + ')</span>';
  });

  /* Active date-filter chip */
  const chips = document.getElementById('assessActiveFilters');
  if (chips) {
    chips.innerHTML = ASSESS_VIEW.date
      ? '<span class="sessions-chip">📅 ' + escapeHtml(fmtYmdLong(ASSESS_VIEW.date))
        + '<button title="Clear date filter" onclick="clearAssessDate()">✕</button></span>'
      : '';
  }

  /* Filters: tab → date → search; ordered first (earliest) → last */
  const items = sched
    .filter(s => ASSESS_VIEW.tab === 'ALL' || ((s.course && s.course.type) || '').toUpperCase() === ASSESS_VIEW.tab)
    .filter(s => !ASSESS_VIEW.date || String(s.date || '').slice(0, 10) === ASSESS_VIEW.date)
    .filter(s => !ASSESS_VIEW.search || assessSearchable(s).includes(ASSESS_VIEW.search))
    .sort((x, y) => String(x.date || '').localeCompare(String(y.date || '')));

  if (!items.length) {
    const filtering = ASSESS_VIEW.search || ASSESS_VIEW.date;
    grid.innerHTML = '<div style="grid-column:1/-1;text-align:center;color:var(--gray);padding:20px;">'
      + (filtering ? 'No sessions match your search or filters.' : 'No sessions available for assessment.') + '</div>';
    return;
  }

  const beforeSearch = items.length;
  const base = sched.filter(s => ASSESS_VIEW.tab === 'ALL' || ((s.course && s.course.type) || '').toUpperCase() === ASSESS_VIEW.tab)
    .filter(s => !ASSESS_VIEW.date || String(s.date || '').slice(0, 10) === ASSESS_VIEW.date);
  let html = '';
  if (ASSESS_VIEW.search && base.length !== items.length) {
    html += '<div style="grid-column:1/-1;font-size:12px;color:var(--gray);">Showing ' + items.length + ' of ' + base.length + ' session(s)</div>';
  }
  html += items.map(s => {
    const type = ((s.course && s.course.type) || '').toUpperCase();
    const dateLabel = s.end_date && s.end_date !== s.date
      ? `${assessFmtDate(s.date)} – ${assessFmtDate(s.end_date)}`
      : assessFmtDate(s.date);
    const nStudents = (s.students || []).length;
    const done = (s.students || []).filter(st => ASSESS_ASSESSED.has(s.id + ':' + st.student_id)).length;
    const open = Math.max(0, nStudents - done);
    return `<div class="session-card" data-id="${s.id}" onclick="openAssessStudents(${s.id})">
      <div class="session-card-date">${dateLabel}</div>
      <div class="session-card-badges">
        <span class="type-badge" style="font-size:10px;font-weight:800;padding:2px 7px;border-radius:6px;color:#fff;background:${type === 'PDC' ? 'var(--orange)' : '#6366f1'};">${type}</span>
        <span class="badge ${assessStatusCls(s.status)}">${assessStatusLabel(s.status)}</span>
      </div>
      <div class="session-card-row"><span class="ico">👥</span><span>${done}/${nStudents} assessed</span></div>
      <div class="session-card-row" style="color:var(--gray);"><span class="ico">🕐</span><span>${escapeHtml(String(s.time_start || ''))}–${escapeHtml(String(s.time_end || ''))}</span></div>
      <div class="session-card-row" style="color:var(--gray);"><span class="ico">📌</span><span>${escapeHtml(String(s.venue || ''))}</span></div>
      ${open === 0 && nStudents > 0 ? '<span class="badge done" style="align-self:flex-start;">✓ All assessed</span>' : ''}
    </div>`;
  }).join('');
  grid.innerHTML = html;
}

/* ---------- Session students modal ---------- */
function openAssessStudents(scheduleId) {
  const sch = (ASSESS_SCHEDULES || []).find(s => String(s.id) === String(scheduleId));
  if (!sch) return;
  const overlay = document.getElementById('assessStudentsOverlay');
  if (!overlay) return;

  const dateLabel = sch.end_date && sch.end_date !== sch.date
    ? `${assessFmtDate(sch.date)} – ${assessFmtDate(sch.end_date)}`
    : assessFmtDate(sch.date);
  document.getElementById('assessStudentsTitle').textContent
    = (sch.course ? sch.course.name + ' — ' : '') + dateLabel;
  document.getElementById('assessStudentsMeta').textContent
    = [sch.time_start && sch.time_end ? `${sch.time_start}–${sch.time_end}` : '', sch.venue || '']
      .filter(Boolean).join(' · ');

  const list = document.getElementById('assessStudentsList');
  const students = sch.students || [];
  if (!students.length) {
    list.innerHTML = '<div style="text-align:center;color:var(--gray);padding:18px;">No students booked in this session yet.</div>';
  } else {
    list.innerHTML = students.map(st => {
      const key = sch.id + ':' + st.student_id;
      const done = ASSESS_ASSESSED.has(key);
      return `<div class="cert-row" style="display:flex;align-items:center;justify-content:space-between;gap:10px;padding:12px 14px;border:1px solid var(--gray-light);border-radius:12px;background:var(--cream);margin-bottom:0;">
        <div style="display:flex;align-items:center;gap:10px;min-width:0;">
          <span style="width:34px;height:34px;border-radius:50%;background:linear-gradient(135deg,var(--orange),#E35D4B);color:#fff;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:12px;flex-shrink:0;">${escapeHtml((st.student_name || '?').trim().charAt(0).toUpperCase())}</span>
          <div style="min-width:0;">
            <b style="font-size:14px;">${escapeHtml(st.student_name)}</b><br>
            <span style="font-size:12px;color:var(--gray);">${sch.course ? escapeHtml(sch.course.name) + ' · ' : ''}${done ? 'already assessed this visit' : 'not yet assessed'}</span>
          </div>
        </div>
        ${done ? '<span class="badge done">✓ Assessed</span>' : ''}
        <button class="btn btn-sm btn-primary" onclick="openAssessForm(${sch.id}, ${st.student_id})">${done ? 'Re-assess' : 'Assess'}</button>
      </div>`;
    }).join('');
  }
  overlay.classList.add('open');
}

/* ---------- Assessment form modal ---------- */
function openAssessForm(scheduleId, studentId) {
  const sch = (ASSESS_SCHEDULES || []).find(s => String(s.id) === String(scheduleId));
  if (!sch) return;
  const st = (sch.students || []).find(x => String(x.student_id) === String(studentId));
  ASSESS_CURRENT = { scheduleId, studentId, schedule: sch, studentName: st ? st.student_name : 'Student' };

  const dateLabel = sch.end_date && sch.end_date !== sch.date
    ? `${assessFmtDate(sch.date)} – ${assessFmtDate(sch.end_date)}`
    : assessFmtDate(sch.date);
  const title = document.getElementById('assessFormTitle');
  if (title) title.textContent = 'Assess: ' + ASSESS_CURRENT.studentName;
  const meta = document.getElementById('assessFormMeta');
  if (meta) meta.textContent = (sch.course ? sch.course.name + ' · ' : '') + dateLabel;

  /* TDC is graded by exam score only; PDC by the five star categories. */
  toggleAssessMode(sch);
  const ta = document.getElementById('nlpInput2');
  if (ta) ta.value = '';
  const exam = document.getElementById('assessExam');
  if (exam) exam.value = '';

  const overlay = document.getElementById('assessmentOverlay');
  if (overlay) overlay.classList.add('open');
}

function closeAssessModals() {
  ['assessStudentsOverlay', 'assessmentOverlay'].forEach(id => {
    const el = document.getElementById(id);
    if (el) el.classList.remove('open');
  });
}
async function setupAssessForm() {
  const { ok, data } = await RCDA.safeFetch(INSTR_API() + '/instructor/feedback');
  ASSESS_SCHEDULES = (ok && data && data.schedules) || [];
  renderAssessBrowser();
}

/* TDC is graded by exam score only; PDC by driving star ratings. The
   qualitative-feedback example must match the course type too — the TDC
   placeholder talks about the WRITTEN exam / classroom participation, never
   about gears and parking (that is PDC). */
function toggleAssessMode(sch) {
  const isTdc = sch && sch.course && sch.course.type === 'TDC';
  const grid = document.getElementById('skillsGrid');
  const exam = document.getElementById('examField');
  const label = document.getElementById('ratingLabel');
  const hint = document.getElementById('assessHintLine');
  const ta = document.getElementById('nlpInput2');
  if (grid) grid.style.display = isTdc ? 'none' : 'grid';
  if (exam) exam.style.display = isTdc ? 'block' : 'none';
  if (label) label.textContent = isTdc ? 'Exam Score (TDC written exam)' : 'PDC Skills Rating';
  if (hint) hint.textContent = isTdc
    ? 'TDC needs only the exam score — the pass mark is 75%.'
    : 'Tap a star to rate 1–5. All five categories are required for PDC.';
  if (ta) ta.placeholder = isTdc
    ? (ta.dataset.placeholderTdc || 'e.g. Scored well on the written exam and joined the class discussions actively.')
    : (ta.dataset.placeholderPdc || '');
}

/* ============================================================
   My Students — search, filter, add student
   ============================================================ */
function toggleStudentFilter(evt) {
  if (evt) evt.stopPropagation();
  const menu = document.getElementById('studentFilterMenu');
  if (!menu) return;
  const willOpen = !menu.classList.contains('open');
  menu.classList.toggle('open', willOpen);
  if (willOpen) document.addEventListener('click', closeStudentFilterOnOutsideClick);
}

function closeStudentFilterOnOutsideClick(evt) {
  const wrap = document.querySelector('#instr-mystudents .filter-wrap');
  const menu = document.getElementById('studentFilterMenu');
  if (wrap && !wrap.contains(evt.target)) {
    if (menu) menu.classList.remove('open');
    document.removeEventListener('click', closeStudentFilterOnOutsideClick);
  }
}

function filterStudents() {
  const searchTerm = (document.getElementById('studentSearch') || {}).value || '';
  const q = searchTerm.trim().toLowerCase();
  const checked = Array.from(document.querySelectorAll('#studentFilterMenu input[type=checkbox]:checked')).map(cb => cb.value);
  const sortMode = (document.querySelector('#studentFilterMenu input[name=studentSort]:checked') || {}).value || '';
  const tbody = document.getElementById('studentsTableBody');
  if (!tbody) return;
  const rows = Array.from(tbody.querySelectorAll('tr'));
  let visibleCount = 0;

  rows.forEach(row => {
    const name = row.dataset.name || '';
    const cls = row.dataset.classification || '';
    const matchesSearch = !q || name.includes(q);
    const matchesFilter = checked.length === 0 || checked.includes(cls);
    const show = matchesSearch && matchesFilter;
    row.style.display = show ? '' : 'none';
    if (show) visibleCount++;
  });

  if (sortMode === 'az' || sortMode === 'za') {
    rows.sort((a, b) => {
      const cmp = (a.dataset.name || '').localeCompare(b.dataset.name || '');
      return sortMode === 'az' ? cmp : -cmp;
    });
    rows.forEach(row => tbody.appendChild(row));
  }

  const empty = document.getElementById('studentsEmptyState');
  if (empty) empty.style.display = visibleCount === 0 ? 'block' : 'none';
}

function openAddStudentModal() {
  const overlay = document.getElementById('addStudentOverlay');
  if (!overlay) return;
  overlay.classList.add('open');
  const nameInput = document.getElementById('newStudentName');
  if (nameInput) { nameInput.value = ''; nameInput.focus(); }
  const err = document.getElementById('addStudentError');
  if (err) err.style.display = 'none';
}

function closeAddStudentModal() {
  const overlay = document.getElementById('addStudentOverlay');
  if (overlay) overlay.classList.remove('open');
}

const CLASSIFICATION_LABELS = {
  fast: 'Fast Learner',
  average: 'Average Learner',
  avg: 'Average Learner',
  needs: 'Needs Improvement',
};

function classificationLabel(cls) {
  return CLASSIFICATION_LABELS[cls] || '—';
}

/* Local-only helper: the instructor has no "create student" endpoint,
   so adding here is a client-side convenience that preps an Assess row. */
function submitAddStudent() {
  const nameInput = document.getElementById('newStudentName');
  const name = (nameInput && nameInput.value || '').trim();
  const err = document.getElementById('addStudentError');
  if (!name) {
    if (err) err.style.display = 'block';
    if (nameInput) nameInput.focus();
    return;
  }
  if (err) err.style.display = 'none';
  const course = (document.getElementById('newStudentCourse') || {}).value || 'TDC + PDC';
  const classification = (document.getElementById('newStudentClassification') || {}).value || 'avg';
  const dateLabel = new Date().toLocaleDateString('en-US', { month: 'short', day: 'numeric' });

  const tbody = document.getElementById('studentsTableBody');
  if (!tbody) return;
  const tr = document.createElement('tr');
  tr.className = 'row-new';
  tr.dataset.name = name.toLowerCase();
  tr.dataset.classification = classification;
  tr.innerHTML =
    `<td><b>${escapeHtml(name)}</b></td>` +
    `<td>${escapeHtml(course)}</td>` +
    `<td><span class="badge ${classification}">${CLASSIFICATION_LABELS[classification]}</span></td>` +
    `<td>${dateLabel}</td>` +
    `<td><button class="btn btn-sm btn-primary" onclick="showPanel('myassessment',this)">Assess</button></td>`;
  tbody.prepend(tr);
  closeAddStudentModal();
  filterStudents();
  tr.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
}

document.addEventListener('keydown', function (e) {
  if (e.key === 'Escape') { closeAddStudentModal(); closeAssessModals(); }
});

/* ============================================================
   Assessment — per-category star rating + preset notes,
   saved to the backend (training suggestion is rule-based).
   ============================================================ */
const NOTE_LABELS = { perfect: 'Perfect driving skills', average: 'Average driving skills', low: 'Low driving skills' };

function pickNote(btn, cat) {
  const row = btn.closest('.skill-rate-row');
  if (!row) return;
  row.querySelectorAll('.pbtn').forEach(b => b.classList.remove('active'));
  btn.classList.add('active');
}

function readStars() {
  const map = {};
  document.querySelectorAll('.skills-rating-grid .skill-rate-row').forEach(row => {
    const cat = row.getAttribute('data-cat');
    map[cat] = row.querySelectorAll('.star-row .star.lit').length;
  });
  return map;
}

function readNotes() {
  const map = {};
  document.querySelectorAll('.skills-rating-grid .skill-rate-row').forEach(row => {
    const cat = row.getAttribute('data-cat');
    const active = row.querySelector('.pbtn.active');
    map[cat] = active ? NOTE_LABELS[active.textContent.trim().toLowerCase()] || null : null;
  });
  return map;
}

async function saveAssessment() {
  if (!ASSESS_CURRENT) { RCDA.showToast('Pick a session and student first.'); return; }
  const { scheduleId, studentId, schedule: sch, studentName } = ASSESS_CURRENT;

  const txt = (document.getElementById('nlpInput2') || {}).value || '';
  if (txt.trim().length < 10) { RCDA.showToast('Please enter at least 10 characters of feedback.'); return; }

  const isTdc = sch && sch.course && sch.course.type === 'TDC';

  const body = { raw_text: txt };

  if (isTdc) {
    const exam = (document.getElementById('assessExam') || {}).value;
    if (exam === '' || exam == null) { RCDA.showToast('Enter the exam score (0–100).', 'error'); return; }
    body.exam_score = Number(exam);
  } else {
    const stars = readStars();
    const CATS = ['engine', 'brake', 'turning', 'parking', 'traffic'];
    if (!CATS.every(c => stars[c])) {
      RCDA.showToast('Rate all five categories (1–5 stars) first.');
      return;
    }
    Object.assign(body, {
      star_engine: stars.engine,
      star_brake: stars.brake,
      star_turning: stars.turning,
      star_parking: stars.parking,
      star_traffic: stars.traffic,
    });
  }

  const res = await RCDA.safeFetch(INSTR_API() + `/instructor/feedback/${scheduleId}/${studentId}`, {
    method: 'POST',
    body
  });

  /* No network in the field? Queue it on the device — it posts itself the
     moment the instructor is back in coverage. The endpoint is idempotent. */
  if (res.status === 0) {
    RCDA.offlineQueue.push(
      INSTR_API() + `/instructor/feedback/${scheduleId}/${studentId}`,
      { method: 'POST', body },
      { label: 'Assessment — ' + studentName, kind: 'feedback', scheduleId, studentId }
    );
    ASSESS_ASSESSED.add(scheduleId + ':' + studentId);
    closeAssessModals();
    renderAssessBrowser();
    RCDA.showToast('No signal — assessment saved on this device and will sync automatically.', 'info');
    loadRecentAssessments({ classification: null, score: null }, studentName, true);
    return;
  }

  const { ok, data, error } = res;
  if (!ok) { RCDA.showToast(error || 'Could not save assessment.'); return; }
  const result = (data && data.assessment) || null;
  const verdict = result ? classificationLabel(result.classification) : '';
  ASSESS_ASSESSED.add(scheduleId + ':' + studentId);
  closeAssessModals();
  renderAssessBrowser();
  RCDA.showToast(
    'Assessment saved — ' + studentName + (verdict ? ' — ' + verdict : '') + '.',
    'success'
  );
  loadRecentAssessments(result, studentName);
}

function loadRecentAssessments(result, studentName) {
  const body = document.getElementById('recentAssessBody');
  if (!body) return;
  const name = studentName || 'student';
  const now = new Date().toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
  const scoreTxt = result && result.score != null ? Math.round(Number(result.score)) + '%' : '—';
  const levelTxt = result && result.classification
    ? `<span class="badge ${escapeHtml(result.classification)}">${escapeHtml(classificationLabel(result.classification))}</span>`
    : '—';
  const row = `<tr><td>${escapeHtml(name)}</td><td>${now}</td><td>${scoreTxt}</td><td>${levelTxt}</td></tr>`;
  const placeholder = body.querySelector('td[colspan="4"]');
  if (placeholder) body.innerHTML = row;
  else body.insertAdjacentHTML('afterbegin', row);
}

/* ---- ATTENDANCE (instructor records their own students) ---- */
let INSTR_ATTEND_SCHEDULE = null;

async function loadInstructorAttendance(){
  const { ok, data } = await RCDA.safeFetch(INSTR_API() + '/instructor/attendance');
  const list = document.getElementById('instrSessionList');
  if (!list) return;
  if (!ok || !data || !data.schedules || !data.schedules.length) {
    list.innerHTML = '<div style="color:var(--gray);padding:14px;">No sessions yet.</div>';
    return;
  }
  list.innerHTML = data.schedules.map(s => {
    const type = (((s.course && s.course.type) || '') || '').toUpperCase();
    const dateLabel = s.end_date && s.end_date !== s.date
      ? `${String(s.date || '').slice(0, 10)} – ${String(s.end_date || '').slice(0, 10)}`
      : String(s.date || '');
    return `
    <div class="cert-row" style="display:flex;align-items:center;justify-content:space-between;gap:10px;padding:12px 14px;border:1px solid var(--gray-light);border-radius:12px;background:var(--cream);">
      <div>
        <b>${escapeHtml((s.course && s.course.name) || '')}</b>${type ? ` <span class="badge ${type === 'PDC' ? 'needs' : 'upcoming'}" style="font-size:10px;padding:2px 7px;">${type}</span>` : ''}<br>
        <span style="font-size:12px;color:var(--gray);">${escapeHtml(dateLabel)}${type === 'TDC' ? ' · 2-day theory class' : ''} · ${statusBadge(s.status)}</span>
      </div>
      <button class="btn btn-sm btn-primary" onclick="openInstructorAttendance(${s.id})">Open</button>
    </div>`;
  }).join('');
}

async function openInstructorAttendance(id){
  const { ok, data } = await RCDA.safeFetch(INSTR_API() + `/instructor/attendance/${id}`);
  const el = document.getElementById('instrAttendanceBody');
  if (!el) return;
  if (!ok || !data) { el.innerHTML = '<div style="color:var(--gray);padding:12px;">Could not load session.</div>'; return; }
  INSTR_ATTEND_SCHEDULE = id;
  const bookings = data.bookings || [];
  const records = data.records || {};
  if (!bookings.length) {
    el.innerHTML = '<div style="color:var(--gray);padding:12px;">No students booked for this session.</div>';
    return;
  }
  const opts = (cur) => ['present','late','absent'].map(st =>
    `<button type="button" class="attend-btn ${st} ${cur === st ? 'sel' : ''}" onclick="markInstrAttend(this,'${st}',${id})">${st.charAt(0).toUpperCase()+st.slice(1)}</button>`
  ).join('');
  el.innerHTML = bookings.map(b => `
    <div class="attend-row" data-student="${b.student_id}" style="display:flex;align-items:center;justify-content:space-between;gap:10px;padding:10px 12px;border:1px solid var(--gray-light);border-radius:10px;">
      <div class="attend-name"><b>${escapeHtml(b.student_name || 'Student')}</b></div>
      <div class="attend-btns" data-student="${b.student_id}">${opts(records[b.student_id] || 'present')}</div>
    </div>`).join('');
}

function markInstrAttend(btn, type, scheduleId){
  const row = btn.closest('.attend-btns');
  if (!row) return;
  row.querySelectorAll('.attend-btn').forEach(b => {
    b.classList.remove('sel');
    if (b.classList.contains(type)) b.classList.add('sel');
  });
}

async function saveInstructorAttendance(){
  if (INSTR_ATTEND_SCHEDULE == null) { RCDA.showToast('Open a session first.', 'error'); return; }
  const body = document.getElementById('instrAttendanceBody');
  if (!body) return;
  const status = {};
  body.querySelectorAll('.attend-btns').forEach(btns => {
    const sid = btns.dataset.student;
    const sel = btns.querySelector('.attend-btn.sel');
    if (sid && sel) status[sid] = sel.classList.contains('present') ? 'present' : (sel.classList.contains('late') ? 'late' : 'absent');
  });
  const res = await RCDA.safeFetch(INSTR_API() + `/instructor/attendance/${INSTR_ATTEND_SCHEDULE}`, {
    method: 'POST', body: { status, mark_done: true }
  });

  /* Offline? Keep it on the device — attendance replays automatically and
     the endpoint is updateOrCreate, so a replay cannot double-save. */
  if (res.status === 0) {
    RCDA.offlineQueue.push(
      INSTR_API() + `/instructor/attendance/${INSTR_ATTEND_SCHEDULE}`,
      { method: 'POST', body: { status, mark_done: true } },
      { label: 'Attendance — session #' + INSTR_ATTEND_SCHEDULE, kind: 'attendance', scheduleId: INSTR_ATTEND_SCHEDULE }
    );
    RCDA.showToast('No signal — attendance saved on this device and will sync automatically.', 'info');
    return;
  }

  const { ok, error } = res;
  if (!ok) { RCDA.showToast(error || 'Could not save attendance.', 'error'); return; }
  RCDA.showToast('Attendance saved.', 'success');
  loadInstructorAttendance();
}

/* Re-open the sidebar tab the instructor was last on (see rememberPanel). */
function restoreInstructorPanel() {
  const panel = typeof panelFromHash === 'function' ? panelFromHash() : '';
  if (!panel || !document.getElementById('instr-' + panel)) return;
  showPanel(panel, typeof navItemForPanel === 'function' ? navItemForPanel(panel) : null);
}

/* ---- OFFLINE MODE (PWA) ----
   Registers the service worker when the page runs over http(s), seeds the
   offline badge in the topbar and refreshes the panels once queued entries
   have synced. */
function initInstructorOffline(){
  if ('serviceWorker' in navigator && location.protocol.startsWith('http')) {
    navigator.serviceWorker.register('/frontend/sw.js').catch(() => { /* SW is an enhancement — never block the page */ });
  }
  if (window.RCDA.offlineQueue) {
    RCDA.offlineQueue.initBadge();
    RCDA.offlineQueue.onUpdated(count => {
      const b = document.getElementById('rcdaOfflineBadge');
      if (b && !navigator.onLine) {
        b.innerHTML = '<span style="width:8px;height:8px;border-radius:50%;background:#d97706;margin-right:7px;"></span>' +
          'Offline — saving on device' + (count > 0 ? ' (' + count + ')' : '');
      }
    });
  }
  window.addEventListener('rcda:offlinesync', () => {
    /* Queued attendance/feedback reached the server — re-render so the lists
       reflect what was stored. */
    loadInstructorAttendance();
    if (window.ASSESS_CURRENT !== undefined) setupAssessForm();
    if (!navigator.onLine && document.getElementById('rcdaOfflineBadge')) {
      document.getElementById('rcdaOfflineBadge').style.display = 'none';
    }
  });
}

/* ---- INIT ---- */
document.addEventListener('DOMContentLoaded', async () => {
  RCDA.showPageLoading('Loading…');
  if (!await requireAuth('instructor')) return;

  initInstructorOffline();

  /* Keep the splash up until EVERY panel's data is in — the instructor asked
     for "spinner only, then the finished page", not a half-populated one. */
  try {
    await Promise.allSettled([
      loadInstructorDashboard(),
      loadInstructorStudents(),
      loadInstructorSchedule(),
      setupAssessForm(),
      loadInstructorAttendance(),
    ]);
  } catch (e) {
    console.error('[RCDA instructor]', e);
    RCDA.showToast('Some of your data failed to load.');
  } finally {
    RCDA.hidePageLoading();
  }
  restoreInstructorPanel();

  /* Anything queued offline on a previous visit syncs right away. */
  if (navigator.onLine && window.RCDA.offlineQueue) RCDA.offlineQueue.flush();
});
