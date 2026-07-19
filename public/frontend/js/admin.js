/* ============================================================
   admin.js — loaded ONLY on admin/dashboard.html
   ============================================================ */

const ADMIN_TITLES = {
  dashboard:'Dashboard', students:'Students', schedule:'Schedule',
  attendance:'Instructor Attendance', instructors:'Instructors',
  payments:'Payments', certifications:'Certifications', reports:'Reports',
  notifications:'Notifications', bookings:'Pending Bookings', requests:'Student Requests'
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

  if (panel === 'schedule') highlightToday();
  if (panel === 'reports') loadReports();
  if (panel === 'notifications') loadNotifications();
  if (panel === 'requests') loadAdminRequests();
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

function analyzeNLP(){
  const txt = (document.getElementById('nlpInput') || {}).value || '';
  const type = classifyNLP(txt);
  const d = nlpData[type];

  const badge = document.getElementById('nlpBadge');
  const score = document.getElementById('nlpScore');
  const msg   = document.getElementById('nlpMsg');
  const tokens= document.getElementById('nlpTokens');
  const result= document.getElementById('nlpResult');

  if(badge){ badge.textContent = d.badge; badge.className = 'nlp-badge ' + d.cls; }
  if(score) score.textContent = d.score + '%';
  if(msg)   msg.textContent = d.msg;
  if(tokens) tokens.innerHTML = d.tokens.map(t => `<span class="token-chip ${t.c}">${t.t}</span>`).join('');
  if(result) result.style.display = 'block';
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

document.addEventListener('DOMContentLoaded', async () => {
  const ok = await requireAuth('admin');
  if (!ok) return;
  loadDashboardStats();
  loadStudents();
  loadPayments();
  loadInstructors();
  loadCertifications();
  loadInstructorList();
  loadBookings();
  loadInstructorAttendance();
});

async function loadInstructorList(){
  const { ok, data } = await RCDA.safeFetch(`${RCDA_CONFIG.getApiBaseUrl()}/admin/instructors?per_page=100`, { method: 'GET' });
  if (ok && data) ADMIN_INSTRUCTORS = data.instructors || [];
}

async function loadDashboardStats(){
  const { ok, data } = await RCDA.safeFetch(`${RCDA_CONFIG.getApiBaseUrl()}/admin/dashboard`, { method: 'GET' });
  if (!ok || !data) return;
  const s = data.stats || {};
  const cards = document.querySelectorAll('#admin-dashboard .stat-card .value');
  if (cards.length >= 4) {
    cards[0].textContent = s.students ?? cards[0].textContent;
    cards[1].textContent = s.schedules ?? cards[1].textContent;
    cards[2].textContent = s.activeEnrollments ?? cards[2].textContent;
    cards[3].textContent = s.pendingPayments ?? cards[3].textContent;
  }
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
    return `<tr data-status="${status}" data-id="${st.id}">
      <td class="mono" style="color:var(--gray);font-size:12px;">#${st.id}</td>
      <td><b>${escapeHtml(st.name)}</b><br><span style="font-size:11.5px;color:var(--gray);">${maskEmail(st.email)}</span></td>
      <td>${escapeHtml(st.course)}</td>
      <td><span class="badge ${payBadge}">${escapeHtml(st.payment_status)}</span></td>
      <td><span class="badge ${statusBadge}">${status}</span></td>
      <td>${instructor}</td>
      <td><div style="display:flex;gap:6px;"><button class="btn btn-sm btn-ghost" onclick="viewStudent(this)">View</button><button class="btn btn-sm btn-primary" onclick="openAssignInstructorModal(${st.id})">Assign</button><button class="btn btn-sm btn-danger" onclick="deleteStudent(this)">Delete</button></div></td>
    </tr>`;
  }).join('') || `<tr><td colspan="7" style="text-align:center;color:var(--gray);padding:20px;">No students yet.</td></tr>`;
  filterStudents();
  renderPagination(document.getElementById('studentsTable'), 'studentsPagination', pagination, 'loadStudents');
}

async function loadInstructors(page){
  page = page || 1;
  const { ok, data } = await RCDA.safeFetch(`${RCDA_CONFIG.getApiBaseUrl()}/admin/instructors?page=${page}`, { method: 'GET' });
  if (ok && data) renderInstructors(data.instructors || [], data.pagination);
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
      <td><button class="btn btn-sm btn-danger" onclick="deleteInstructor(this)">Delete</button></td>
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
   Add Instructor — reads the modal form, validates required
   fields, appends a new row to the Instructor Roster table,
   then resets and closes the modal.
   ============================================================ */
function addInstructor(){
  const name = (document.getElementById('newInstrName') || {}).value.trim();
  const spec = (document.getElementById('newInstrSpec') || {}).value.trim();
  const students = (document.getElementById('newInstrStudents') || {}).value.trim() || '0';
  const sessions = (document.getElementById('newInstrSessions') || {}).value.trim() || '0';
  const status = (document.getElementById('newInstrStatus') || {}).value || 'Active';

  const errorEl = document.getElementById('newInstrError');

  if (!name || !spec) {
    if (errorEl) errorEl.style.display = 'block';
    return;
  }
  if (errorEl) errorEl.style.display = 'none';

  const tbody = document.querySelector('#instructorsTable tbody');
  if (!tbody) return;

  const badgeClass = status === 'On Leave' ? 'pending' : 'active';

  const row = document.createElement('tr');
  row.dataset.status = status;
  row.innerHTML = `<td><b>${name}</b></td><td>${spec}</td><td>${students}</td><td>${sessions}</td><td><span class="badge ${badgeClass}">${status}</span></td><td><button class="btn btn-sm btn-danger" onclick="deleteInstructor(this)">Delete</button></td>`;
  tbody.appendChild(row);

  // Reset form
  document.getElementById('newInstrName').value = '';
  document.getElementById('newInstrSpec').value = '';
  document.getElementById('newInstrStudents').value = '';
  document.getElementById('newInstrSessions').value = '';
  document.getElementById('newInstrStatus').value = 'Active';

  closeModal('addInstructorModal');
  filterInstructors();
}

/* ============================================================
   Add Student — reads the modal form, validates the name,
   auto-generates the next RC-### ID, appends a new row to the
   All Students table, then resets and closes the modal.
   ============================================================ */
function addStudent(){
  const name = (document.getElementById('newStudName') || {}).value.trim();
  const course = (document.getElementById('newStudCourse') || {}).value || 'TDC+PDC';
  const instructor = (document.getElementById('newStudInstructor') || {}).value || '';
  const payment = (document.getElementById('newStudPayment') || {}).value || 'Pending';
  const status = (document.getElementById('newStudStatus') || {}).value || 'Active';

  const errorEl = document.getElementById('newStudError');

  if (!name) {
    if (errorEl) errorEl.style.display = 'block';
    return;
  }
  if (errorEl) errorEl.style.display = 'none';

  const tbody = document.querySelector('#studentsTable tbody');
  if (!tbody) return;

  const existingIds = Array.from(tbody.querySelectorAll('td.mono'))
    .map(td => parseInt((td.textContent.match(/\d+/) || [0])[0], 10));
  const nextNum = (existingIds.length ? Math.max(...existingIds) : 0) + 1;
  const newId = 'RC-' + String(nextNum).padStart(3, '0');

  const tdc = course.includes('TDC') ? '0/5' : '—';
  const pdc = course.includes('PDC') ? '0/8' : '—';
  const paymentBadge = payment === 'Paid' ? 'verified' : 'pending';
  const statusBadge = status === 'Active' ? 'active' : 'pending';
  const courseLabel = course === 'TDC+PDC' ? 'TDC+PDC' : course;

  const row = document.createElement('tr');
  row.dataset.status = status;
  row.innerHTML = `<td class="mono" style="color:var(--gray);font-size:12px;">${newId}</td><td><b>${escapeHtml(name)}</b></td><td>${escapeHtml(courseLabel)}</td><td><span class="badge ${paymentBadge}">${payment}</span></td><td><span class="badge ${statusBadge}">${status}</span></td><td><div style="display:flex;gap:6px;"><button class="btn btn-sm btn-ghost" onclick="viewStudent(this)">View</button><button class="btn btn-sm btn-danger" onclick="deleteStudent(this)">Delete</button></div></td>`;
  tbody.appendChild(row);

  // Reset form
  document.getElementById('newStudName').value = '';
  document.getElementById('newStudCourse').value = 'TDC+PDC';
  document.getElementById('newStudInstructor').value = 'Mr. Santos';
  document.getElementById('newStudPayment').value = 'Pending';
  document.getElementById('newStudStatus').value = 'Active';

  closeModal('addStudentModal');
  filterStudents();
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
   Certifications — issuing a certificate moves its row from
   "Ready to Issue" to "Recently Issued", swaps the badge, and
   generates a Valid ID (RCDA-<COURSE>-2026-####).
   ============================================================ */
/* Loads the "Ready to Issue" and "Recently Issued" lists from the
   API so certificates actually persist (previously client-only). */
async function loadCertifications(){
  const base = `${RCDA_CONFIG.getApiBaseUrl()}/admin/certifications`;
  const [ready, issued] = await Promise.all([
    RCDA.safeFetch(`${base}/ready`, { method: 'GET' }),
    RCDA.safeFetch(base, { method: 'GET' }),
  ]);
  if (ready.ok && ready.data) renderReadyCerts(ready.data.ready || []);
  if (issued.ok && issued.data) renderIssuedCerts(issued.data.certifications || []);
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
  el.innerHTML = list.length ? list.map(c => `
    <div class="cert-row issued">
      <div class="cert-info"><h4>${escapeHtml(c.student_name)} — ${escapeHtml(c.course.name)}</h4><span>Issued ${new Date(c.issued_at).toLocaleDateString('en-US', { month: 'short', day: 'numeric' })} · Valid ID: ${escapeHtml(c.certificate_no)}</span></div>
      <div><span class="badge verified">Issued</span></div>
    </div>`).join('')
    : '<div style="font-size:12px;color:var(--gray);padding:14px 0;">No certificates issued yet.</div>';
}

async function issueCertificate(btn, force = false){
  const row = btn.closest('.cert-row');
  if (!row) return;

  const studentId = row.dataset.studentId;
  const courseId = row.dataset.courseId;
  if (!studentId || !courseId) { RCDA.showToast('Missing student or course for this certificate.', 'error'); return; }

  const titleEl = row.querySelector('h4');
  const title = titleEl ? titleEl.textContent.trim() : 'Student — Certificate';
  const courseMatch = title.match(/(TDC|PDC)/);
  const courseCode = courseMatch ? courseMatch[1] : 'CERT';
  const studentName = title.split('—')[0].trim();

  const { ok, data, error } = await RCDA.safeFetch(`${RCDA_CONFIG.getApiBaseUrl()}/admin/certifications`, {
    method: 'POST',
    body: { student_id: Number(studentId), course_id: Number(courseId), force: force ? 1 : 0 },
  });

  if (!ok) { RCDA.showToast(error || 'Could not issue certificate.', 'error'); return; }

  const cert = data.certification || {};
  const validId = cert.certificate_no || `RCDA-${courseCode}-${new Date().getFullYear()}`;
  const issuedDate = new Date().toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
  const issuedDateFull = new Date().toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' });

  const issuedRow = document.createElement('div');
  issuedRow.className = 'cert-row issued';
  issuedRow.innerHTML = `<div class="cert-info"><h4>${escapeHtml(title)}</h4><span>Issued ${issuedDate} · Valid ID: ${escapeHtml(validId)}</span></div><div><span class="badge verified">Issued</span></div>`;

  const issuedList = document.getElementById('certsIssuedList');
  if (issuedList) issuedList.insertBefore(issuedRow, issuedList.firstChild);

  row.remove();
  loadCertifications();

  printCertificate(studentName, courseCode, validId, issuedDateFull, 'RC Driving Academy');
}

/* ============================================================
   Load an image (by URL) as a base64 data URL so it can be
   embedded into a jsPDF document via doc.addImage().
   ============================================================ */
function loadImageAsDataURL(url){
  return new Promise((resolve, reject) => {
    const img = new Image();
    img.crossOrigin = 'anonymous';
    img.onload = () => {
      try {
        const canvas = document.createElement('canvas');
        canvas.width = img.naturalWidth;
        canvas.height = img.naturalHeight;
        canvas.getContext('2d').drawImage(img, 0, 0);
        resolve(canvas.toDataURL('image/png'));
      } catch (err) {
        reject(err);
      }
    };
    img.onerror = reject;
    img.src = url;
  });
}

/* ============================================================
   Print Certificate — builds a designed certificate PDF with
   jsPDF (academy logo, seal, signature line) and opens the
   browser's print dialog for it.
   ============================================================ */
async function printCertificate(studentName, courseCode, validId, issuedDateFull, instructor){
  if (!window.jspdf) return;
  const { jsPDF } = window.jspdf;
  const doc = new jsPDF({ orientation: 'landscape', unit: 'mm', format: 'a4' });

  const pageW = doc.internal.pageSize.getWidth();
  const pageH = doc.internal.pageSize.getHeight();
  const orange = [242, 106, 33];
  const charcoal = [40, 40, 40];
  const gray = [120, 120, 120];

  const courseName = courseCode === 'TDC' ? 'Theoretical Driving Course (TDC)' : 'Practical Driving Course (PDC)';

  // Outer decorative border
  doc.setDrawColor(...orange);
  doc.setLineWidth(1.2);
  doc.rect(10, 10, pageW - 20, pageH - 20);
  doc.setLineWidth(0.4);
  doc.rect(14, 14, pageW - 28, pageH - 28);

  // Academy logo (falls back gracefully to text-only header if it can't load)
  let hasLogo = false;
  try {
    const logoDataUrl = await loadImageAsDataURL('../images/RCDA logo.jpg');
    const logoSize = 20;
    doc.addImage(logoDataUrl, 'JPEG', pageW / 2 - logoSize / 2, 18, logoSize, logoSize);
    hasLogo = true;
  } catch (err) {
    hasLogo = false;
  }

  const headerY = hasLogo ? 44 : 30;

  // Header
  doc.setTextColor(...orange);
  doc.setFont('helvetica', 'bold');
  doc.setFontSize(14);
  doc.text('RC DRIVING ACADEMY', pageW / 2, headerY, { align: 'center' });

  doc.setTextColor(...charcoal);
  doc.setFontSize(28);
  doc.text('Certificate of Completion', pageW / 2, headerY + 16, { align: 'center' });

  doc.setFont('helvetica', 'normal');
  doc.setFontSize(12);
  doc.setTextColor(...gray);
  doc.text('This certifies that', pageW / 2, headerY + 32, { align: 'center' });

  doc.setFont('helvetica', 'bold');
  doc.setFontSize(26);
  doc.setTextColor(...charcoal);
  doc.text(studentName, pageW / 2, headerY + 46, { align: 'center' });

  doc.setFont('helvetica', 'normal');
  doc.setFontSize(12);
  doc.setTextColor(...gray);
  doc.text('has successfully completed the', pageW / 2, headerY + 58, { align: 'center' });

  doc.setFont('helvetica', 'bold');
  doc.setFontSize(16);
  doc.setTextColor(...orange);
  doc.text(courseName, pageW / 2, headerY + 68, { align: 'center' });

  doc.setFont('helvetica', 'normal');
  doc.setFontSize(11);
  doc.setTextColor(...gray);
  doc.text(`Issued on ${issuedDateFull}`, pageW / 2, headerY + 80, { align: 'center' });

  // Signature line (left of center)
  const sigY = pageH - 28;
  const sigX = pageW / 2 - 55;
  doc.setDrawColor(...gray);
  doc.setLineWidth(0.3);
  doc.line(sigX - 35, sigY, sigX + 35, sigY);
  doc.setFont('helvetica', 'bold');
  doc.setFontSize(10);
  doc.setTextColor(...charcoal);
  doc.text(instructor, sigX, sigY + 6, { align: 'center' });
  doc.setFont('helvetica', 'normal');
  doc.setTextColor(...gray);
  doc.setFontSize(8.5);
  doc.text('Instructor / Program Signatory', sigX, sigY + 11, { align: 'center' });

  // Official seal (right of center) — drawn since we have no seal image
  const sealX = pageW / 2 + 55;
  const sealY = sigY - 6;
  doc.setDrawColor(...orange);
  doc.setLineWidth(1);
  doc.circle(sealX, sealY, 12);
  doc.setLineWidth(0.4);
  doc.circle(sealX, sealY, 9.5);
  doc.setFont('helvetica', 'bold');
  doc.setFontSize(7);
  doc.setTextColor(...orange);
  doc.text('RCDA', sealX, sealY - 1, { align: 'center' });
  doc.setFontSize(5.5);
  doc.text('OFFICIAL SEAL', sealX, sealY + 3.5, { align: 'center' });

  // Valid ID footer
  doc.setFont('helvetica', 'normal');
  doc.setFontSize(9);
  doc.setTextColor(...gray);
  doc.text(`Valid ID: ${validId}`, pageW - 20, pageH - 16, { align: 'right' });

  doc.autoPrint();
  window.open(doc.output('bloburl'), '_blank');
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
   Notifications — show channel status + recent log from
   /admin/notifications (works without provider keys; the log
   records every attempt so delivery can be verified later).
   ============================================================ */
async function loadNotifications(){
  const { ok, data } = await RCDA.safeFetch(`${RCDA_CONFIG.getApiBaseUrl()}/admin/notifications`, { method: 'GET' });
  if (!ok || !data) return;

  const ch = data.channels || {};
  const badge = on => on ? '<span class="badge active">Live</span>' : '<span class="badge pending">Disabled</span>';
  document.getElementById('notifChannels').innerHTML = `
    <div style="background:var(--cream);border:1px solid var(--gray-light);border-radius:10px;padding:8px 14px;">SMS ${badge(ch.sms_enabled)}</div>
    <div style="background:var(--cream);border:1px solid var(--gray-light);border-radius:10px;padding:8px 14px;">Email ${badge(ch.mail_enabled)}</div>
    <div style="background:var(--cream);border:1px solid var(--gray-light);border-radius:10px;padding:8px 14px;">Maps ${badge(ch.maps_enabled)}</div>`;

  const logs = data.logs || [];
  document.getElementById('notifLog').innerHTML = logs.length ? logs.map(l => {
    const sent = l.sent ? '<span class="badge verified">Sent</span>' : '<span class="badge pending">Logged</span>';
    const when = l.created_at ? new Date(l.created_at).toLocaleString('en-PH') : '';
    return `<div style="display:flex;gap:10px;align-items:center;justify-content:space-between;padding:10px 12px;border:1px solid var(--gray-light);border-radius:10px;margin-bottom:8px;">
      <div><b>${escapeHtml((l.channel || '').toUpperCase())} · ${escapeHtml(l.event || '')}</b><br>
      <span style="font-size:12px;color:var(--gray);">${escapeHtml(l.recipient || '')} · ${escapeHtml(when)}</span></div>
      ${sent}</div>`;
  }).join('') : '<div style="color:var(--gray);padding:14px 0;">No notifications sent yet. They fire on registration, payment, and certificate issuance.</div>';
}

/* ---- STUDENT REQUESTS (instructor preference + reschedule) ---- */
async function loadAdminRequests() {
  const filter = document.getElementById('reqStatusFilter') ? document.getElementById('reqStatusFilter').value : 'pending';
  const qs = filter ? ('?status=' + encodeURIComponent(filter)) : '';
  const { ok, data, error } = await RCDA.safeFetch(`${RCDA_CONFIG.getApiBaseUrl()}/admin/requests` + qs);
  const body = document.getElementById('adminRequestsBody');
  if (!body) return;
  if (!ok) { body.innerHTML = '<div style="color:var(--gray);">Could not load requests.</div>'; return; }

  const list = data.requests || [];
  if (!list.length) { body.innerHTML = '<div style="color:var(--gray);padding:14px 0;">No requests found.</div>'; return; }

  body.innerHTML = list.map(r => {
    const typeLabel = r.type === 'reschedule' ? 'Reschedule' : 'Instructor preference';
    const badge = r.status === 'pending' ? 'pending' : (r.status === 'accepted' ? 'verified' : 'needs');
    let detail = '';
    if (r.type === 'reschedule') {
      detail = `From: ${r.from_schedule ? escapeHtml((r.from_schedule.course || '') + ' · ' + r.from_schedule.date + ' ' + r.from_schedule.time_start) : '—'}
      <br>To: ${r.to_schedule ? escapeHtml((r.to_schedule.course || '') + ' · ' + r.to_schedule.date + ' ' + r.to_schedule.time_start) : '—'}`;
    } else {
      const pref = r.preferred_gender === 'male' ? 'Male' : (r.preferred_gender === 'female' ? 'Female' : 'No preference');
      detail = `Requested instructor gender: <b>${escapeHtml(pref)}</b>`;
    }
    const actions = r.status === 'pending'
      ? `<div style="display:flex;gap:8px;margin-top:10px;">
           <button class="btn btn-sm btn-primary" onclick="acceptRequest(${r.id})">Accept</button>
           <button class="btn btn-sm btn-ghost" onclick="rejectRequest(${r.id})">Reject</button>
         </div>`
      : `<div class="req-note" style="margin-top:8px;">${escapeHtml((r.admin_note || (r.status === 'accepted' ? 'Accepted' : 'Rejected')).toString())}</div>`;
    const studentName = r.student_name ? escapeHtml(r.student_name) : ('#' + r.student_id);
    return `<div class="req-card">
      <h4>${escapeHtml(typeLabel)} <span class="badge ${badge}" style="margin-left:6px;">${escapeHtml(r.status)}</span></h4>
      <div class="req-meta">Student: ${studentName} · ${r.note ? escapeHtml(r.note) : ''}</div>
      <div style="font-size:13px;line-height:1.5;">${detail}</div>
      ${actions}
    </div>`;
  }).join('');
}

async function acceptRequest(id) {
  const { ok, error } = await RCDA.safeFetch(`${RCDA_CONFIG.getApiBaseUrl()}/admin/requests/${id}/accept`, { method: 'POST' });
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