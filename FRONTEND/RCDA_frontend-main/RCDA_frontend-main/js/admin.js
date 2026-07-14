/* ============================================================
   admin.js — loaded ONLY on admin/dashboard.html
   ============================================================ */

const ADMIN_TITLES = {
  dashboard:'Dashboard', students:'Students', schedule:'Schedule',
  attendance:'Attendance', instructors:'Instructors',
  payments:'Payments', certifications:'Certifications', reports:'Reports'
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
   Payments — verify or reject a pending payment. Updates the
   row's status badge and swaps the action buttons for a single
   confirmation icon. Clicking that icon again undoes it, putting
   the row back to Pending with both action buttons restored —
   protects against accidental clicks.
   ============================================================ */
const PENDING_ACTIONS_HTML = '<button class="btn btn-sm btn-success" title="Verify payment" onclick="verifyPayment(this)">Accept</button><button class="btn btn-sm btn-danger" title="Reject payment" onclick="rejectPayment(this)">Reject</button>';

function verifyPayment(btn){
  const row = btn.closest('tr');
  if (!row) return;
  const badge = row.querySelector('.badge');
  if (badge){ badge.textContent = 'Verified'; badge.className = 'badge verified'; }
  row.dataset.status = 'Verified';
  const actions = btn.closest('.pay-actions') || btn.parentElement;
  if (actions) actions.innerHTML = '<button class="btn btn-sm btn-success" title="Click to undo" onclick="undoPayment(this)">Accepted</button>';
}

function rejectPayment(btn){
  const row = btn.closest('tr');
  if (!row) return;
  const badge = row.querySelector('.badge');
  if (badge){ badge.textContent = 'Rejected'; badge.className = 'badge needs'; }
  row.dataset.status = 'Rejected';
  const actions = btn.closest('.pay-actions') || btn.parentElement;
  if (actions) actions.innerHTML = '<button class="btn btn-sm btn-danger" title="Click to undo" onclick="undoPayment(this)">Rejected</button>';
}

function undoPayment(btn){
  const row = btn.closest('tr');
  if (!row) return;
  const badge = row.querySelector('.badge');
  if (badge){ badge.textContent = 'Pending'; badge.className = 'badge pending'; }
  row.dataset.status = 'Pending';
  const actions = btn.closest('.pay-actions') || btn.parentElement;
  if (actions) actions.innerHTML = PENDING_ACTIONS_HTML;
}

document.addEventListener('DOMContentLoaded', highlightToday);

/* ============================================================
   API-backed data loading (real Laravel backend)
   ============================================================ */
document.addEventListener('DOMContentLoaded', async () => {
  const ok = await requireAuth('admin');
  if (!ok) return;
  loadDashboardStats();
  loadStudents();
  loadPayments();
  loadInstructors();
});

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
    return `<tr data-status="${status}" data-id="${st.id}">
      <td class="mono" style="color:var(--gray);font-size:12px;">#${st.id}</td>
      <td><b>${escapeHtml(st.name)}</b><br><span style="font-size:11.5px;color:var(--gray);">${maskEmail(st.email)}</span></td>
      <td>${escapeHtml(st.course)}</td>
      <td><span class="badge ${payBadge}">${escapeHtml(st.payment_status)}</span></td>
      <td><span class="badge ${statusBadge}">${status}</span></td>
      <td><div style="display:flex;gap:6px;"><button class="btn btn-sm btn-ghost" onclick="viewStudent(this)">View</button><button class="btn btn-sm btn-danger" onclick="deleteStudent(this)">Delete</button></div></td>
    </tr>`;
  }).join('') || `<tr><td colspan="6" style="text-align:center;color:var(--gray);padding:20px;">No students yet.</td></tr>`;
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

async function loadPayments(page){
  page = page || 1;
  const { ok, data } = await RCDA.safeFetch(`${RCDA_CONFIG.getApiBaseUrl()}/admin/payments?page=${page}`, { method: 'GET' });
  if (ok && data) renderPayments(data.payments || [], data.pagination);
}

function renderPayments(payments, pagination){
  const tbody = document.querySelector('#paymentsTable tbody');
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
  if (tdcEl) tdcEl.textContent = '—';
  if (pdcEl) pdcEl.textContent = '—';
  if (statusEl) { statusEl.textContent = status; statusEl.className = 'badge ' + statusClass; }
  if (paymentEl) { paymentEl.textContent = '—'; paymentEl.className = 'badge pending'; }

  const roleSel = document.getElementById('modalStudentRole');
  if (roleSel) roleSel.value = user.role || 'student';

  profileTab('info');
  openModal('studentModal');
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
  const id = window._viewUserId;
  if (!id) return;
  const { ok, error } = await RCDA.safeFetch(`${RCDA_CONFIG.getApiBaseUrl()}/admin/users/${id}/set-role`, {
    method: 'POST',
    body: { role }
  });
  if (!ok) { RCDA.showToast(error || 'Could not update role.', 'error'); return; }
  RCDA.showToast('Role updated to ' + role + '.', 'success');
  loadStudents();
  loadInstructors();
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
function issueCertificate(btn){
  const row = btn.closest('.cert-row');
  if (!row) return;

  const titleEl = row.querySelector('h4');
  const title = titleEl ? titleEl.textContent.trim() : 'Student — Certificate';
  const courseMatch = title.match(/(TDC|PDC)/);
  const courseCode = courseMatch ? courseMatch[1] : 'CERT';
  const studentName = title.split('—')[0].trim();

  const infoSpan = row.querySelector('.cert-info span');
  const infoText = infoSpan ? infoSpan.textContent.trim() : '';
  const instructorMatch = infoText.match(/Instructor:\s*(.+)$/);
  const instructor = instructorMatch ? instructorMatch[1].trim() : 'RC Driving Academy';

  const validId = `RCDA-${courseCode}-2026-${String(Math.floor(1000 + Math.random() * 9000))}`;
  const issuedDate = new Date().toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
  const issuedDateFull = new Date().toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' });

  const issuedRow = document.createElement('div');
  issuedRow.className = 'cert-row issued';
  issuedRow.innerHTML = `<div class="cert-info"><h4>${title}</h4><span>Issued ${issuedDate} · Valid ID: ${validId}</span></div><div><span class="badge verified">Issued</span></div>`;

  const issuedList = document.getElementById('certsIssuedList');
  if (issuedList) issuedList.insertBefore(issuedRow, issuedList.firstChild);

  row.remove();

  printCertificate(studentName, courseCode, validId, issuedDateFull, instructor);
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