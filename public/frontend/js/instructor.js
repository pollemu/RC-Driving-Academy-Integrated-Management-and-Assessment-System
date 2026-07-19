/* ============================================================
   instructor.js — loaded ONLY on instructor/dashboard.html
   ============================================================ */

const INSTR_TITLES = {
  dashboard: 'My Dashboard', mystudents: 'My Students',
  myschedule: 'My Schedule', myassessment: 'Assess Students',
  attendance: 'Session Attendance'
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
  return `<span class="badge ${cls}">${label}</span>`;
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

/* ---- ASSESS FORM ---- */
async function setupAssessForm() {
  const { ok, data } = await RCDA.safeFetch(INSTR_API() + '/instructor/feedback');
  const schedules = (ok && data.schedules) || [];
  const sel = document.getElementById('assessSchedule');
  if (!sel) return;
  if (!schedules.length) { sel.innerHTML = '<option value="">No sessions available</option>'; return; }
  sel.innerHTML = schedules.map(s => `<option value="${s.id}">${escapeHtml((s.course ? s.course.name : 'Session') + ' · ' + (s.date || ''))}</option>`).join('');
  populateAssessStudents(schedules, schedules[0].id);
  sel.onchange = () => populateAssessStudents(schedules, sel.value);
}

function populateAssessStudents(schedules, scheduleId) {
  const sel = document.getElementById('assessStudent');
  if (!sel) return;
  const sch = schedules.find(s => String(s.id) === String(scheduleId));
  const students = sch ? (sch.students || []) : [];
  sel.innerHTML = students.length
    ? students.map(st => `<option value="${st.student_id}">${escapeHtml(st.student_name)}</option>`).join('')
    : '<option value="">No students booked</option>';
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

const CLASSIFICATION_LABELS = { fast: 'Fast Learner', avg: 'Average', needs: 'Needs Help' };

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
  if (e.key === 'Escape') closeAddStudentModal();
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

function saveAssessment() {
  const scheduleId = (document.getElementById('assessSchedule') || {}).value;
  const studentId = (document.getElementById('assessStudent') || {}).value;
  if (!scheduleId || !studentId) { RCDA.showToast('Select a session and student first.'); return; }

  const txt = (document.getElementById('nlpInput2') || {}).value || '';
  if (txt.trim().length < 10) { RCDA.showToast('Please enter at least 10 characters of feedback.'); return; }

  const stars = readStars();
  const notes = readNotes();

  RCDA.safeFetch(INSTR_API() + `/instructor/feedback/${scheduleId}/${studentId}`, {
    method: 'POST',
    body: {
      raw_text: txt,
      star_steering: stars.steering || null,
      star_speed: stars.speed || null,
      star_lane: stars.lane || null,
      star_parking: stars.parking || null,
      star_safety: stars.safety || null,
      star_mirror: stars.mirror || null,
      note_steering: notes.steering || null,
      note_speed: notes.speed || null,
      note_lane: notes.lane || null,
      note_parking: notes.parking || null,
      note_safety: notes.safety || null,
      note_mirror: notes.mirror || null,
    }
  }).then(({ ok, data, error }) => {
    if (!ok) { RCDA.showToast(error || 'Could not save assessment.'); return; }
    RCDA.showToast('Assessment saved — training suggestion generated.', 'success');
    loadRecentAssessments();
  });
}

function loadRecentAssessments() {
  const body = document.getElementById('recentAssessBody');
  if (!body) return;
  const schedSel = document.getElementById('assessSchedule');
  const stuSel = document.getElementById('assessStudent');
  const schedTxt = schedSel ? (schedSel.options[schedSel.selectedIndex]?.text || 'this session') : 'this session';
  const stuTxt = stuSel ? (stuSel.options[stuSel.selectedIndex]?.text || 'student') : 'student';
  const now = new Date().toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
  const row = `<tr><td>${escapeHtml(stuTxt)}</td><td>${now}</td><td>—</td><td><span class="badge badge-pill" style="background:var(--green-light);color:#15803d;">Saved</span></td></tr>`;
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
  list.innerHTML = data.schedules.map(s => `
    <div class="cert-row" style="display:flex;align-items:center;justify-content:space-between;gap:10px;padding:12px 14px;border:1px solid var(--gray-light);border-radius:12px;background:var(--cream);">
      <div>
        <b>${escapeHtml(s.course?.name || '')}</b><br>
        <span style="font-size:12px;color:var(--gray);">${escapeHtml(String(s.date || ''))} · ${statusBadge(s.status)}</span>
      </div>
      <button class="btn btn-sm btn-primary" onclick="openInstructorAttendance(${s.id})">Open</button>
    </div>`).join('');
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
  const { ok, error } = await RCDA.safeFetch(INSTR_API() + `/instructor/attendance/${INSTR_ATTEND_SCHEDULE}`, {
    method: 'POST', body: { status, mark_done: true }
  });
  if (!ok) { RCDA.showToast(error || 'Could not save attendance.', 'error'); return; }
  RCDA.showToast('Attendance saved.', 'success');
  loadInstructorAttendance();
}

/* ---- INIT ---- */
document.addEventListener('DOMContentLoaded', async () => {
  if (!await requireAuth('instructor')) return;
  try {
    await Promise.all([
      loadInstructorDashboard(),
      loadInstructorStudents(),
      loadInstructorSchedule(),
    ]);
    setupAssessForm();
    loadInstructorAttendance();
  } catch (e) {
    console.error('[RCDA instructor]', e);
    RCDA.showToast('Some of your data failed to load.');
  }
});
