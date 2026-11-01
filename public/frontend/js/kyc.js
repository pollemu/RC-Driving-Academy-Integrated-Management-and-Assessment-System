/* ============================================================
   kyc.js — loaded ONLY on kyc.html
   Identity verification (KYC): the student uploads a valid-ID
   photo and a selfie; an admin compares them by eye and approves.
   Requires: login, role = student. After approval the student is
   free to book lessons and pay (server-enforced).
   ============================================================ */

const KYC_API = () => `${RCDA_CONFIG.getApiBaseUrl()}/student/kyc`;

let KYC_TYPES = [];   // [{value,label}] from the API
let KYC_MAX_KB = 5120;
let KYC_CURRENT = null;
let KYC_FILE_CHANGED = { id: false, selfie: false };

/* Move BOTH file inputs into the same hidden store so form re-renders keep
   the chosen files: this map holds the File objects between renders. */
const KYC_PICKED = { id: null, selfie: null };

document.addEventListener('DOMContentLoaded', async () => {
  RCDA.showPageLoading('Loading…');
  try {
    if (!(await requireAuth('student'))) return;

    const res = await RCDA.safeFetch(KYC_API());
    if (!res.ok) {
      RCDA.showToast(res.error || 'Could not load your verification status.');
      renderErrorShell();
      return;
    }

    KYC_TYPES = (res.data && res.data.types) || [];
    KYC_MAX_KB = (res.data && res.data.max_kb) || 5120;
    KYC_CURRENT = (res.data && res.data.kyc) || null;
    KYC_FILE_CHANGED = { id: false, selfie: false };

    const status = KYC_CURRENT && KYC_CURRENT.status;

    if (status === 'verified') {
      // Already verified — KYC page has nothing more to do.
      window.location.href = 'student/dashboard.html';
      return;
    }

    if (status === 'pending') {
      renderPendingState();
      return;
    }

    if (status === 'rejected') {
      renderFormState(KYC_CURRENT.rejection_reason);
      return;
    }

    renderFormState(null);
  } finally {
    RCDA.hidePageLoading();
  }
});

/* ---------- STATES ---------- */

function renderErrorShell() {
  const root = document.getElementById('kycRoot');
  if (!root) return;
  root.innerHTML = `
    <div class="kyc-status-card">
      <div class="big-emoji">⚠️</div>
      <h1>Something went wrong</h1>
      <p>We couldn't load your verification status. Refresh the page to try again.</p>
      <button class="btn btn-primary" onclick="location.reload()">Refresh</button>
    </div>`;
}

function shellHead(emoji, title, sub) {
  return `
    <div class="lockup">
      <img src="images/RCDA logo.jpg" alt="RC Driving Academy" class="lockup-logo" onerror="this.style.display='none';this.nextElementSibling.style.display='flex'">
      <div class="lockup-fallback" style="display:none">RC</div>
      <div class="lockup-text"><b>RC Driving Academy</b>
        <small>Integrated Management and Assessment System</small></div>
    </div>
    <div class="eyebrow-orange">${emoji}</div>
    <h1 class="title">${title}</h1>
    <p class="sub">${sub}</p>`;
}

/* pending: documents are with the admin */
function renderPendingState() {
  const root = document.getElementById('kycRoot');
  if (!root) return;
  root.innerHTML = `${shellHead('🕐', 'Under review', 'Your documents were submitted successfully. An administrator usually reviews them within 1–2 business days.')}
    <div class="kyc-status-card">
      <div class="big-emoji">⏳</div>
      <h1 style="font-size:18px;">Documents in review</h1>
      <p>${KYC_CURRENT.id_type_label
          ? 'You submitted a <b class="kyc-orange">' + escapeHtml(KYC_CURRENT.id_type_label) + '</b>.'
          : ''} While you wait, you can explore your dashboard — but booking lessons and paying wait until verification is done.</p>
      <button class="btn btn-primary" style="width:100%;justify-content:center;" onclick="location.href='student/dashboard.html'">Go to Dashboard</button>
      <p style="font-size:12px;color:var(--gray);margin-top:12px;">Submitted: ${fmtStamp(KYC_CURRENT.submitted_at)}</p>
    </div>`;
}

/* main form (fresh signup or after rejection) */
function renderFormState(rejectionReason) {
  const root = document.getElementById('kycRoot');
  if (!root) return;

  const typeOptions = KYC_TYPES.map(t =>
    `<option value="${escapeHtml(t.value)}" ${KYC_CURRENT && KYC_CURRENT.id_type === t.value ? 'selected' : ''}>${escapeHtml(t.label)}</option>`
  ).join('');

  root.innerHTML = `
    ${shellHead('🪪', 'Verify your identity', rejectionReason ? 'Your previous submission was rejected — resubmit clearer documents below.' : 'One quick requirement before booking: prove you are you.')}
    ${rejectionReason ? `<div class="kyc-reason"><b>Why it was rejected:</b> ${escapeHtml(rejectionReason)}</div>` : ''}

    <div class="login-field">
      <label>ID Type</label>
      <div class="input-wrap">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="M7 15h4"/></svg>
        <select id="kycIdType" style="width:100%;border:none;background:transparent;font:inherit;color:inherit;padding:14px 4px 14px 0;outline:none;">
          <option value="">Select your ID…</option>
          ${typeOptions}
        </select>
      </div>
    </div>

    <div class="kyc-uploads">
      <div class="field">
        <label>Valid ID photo</label>
        <div class="kyc-drop" id="kycDropId" title="Choose or capture a clear photo of your ID">
          <svg width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="2" y="5" width="20" height="14" rx="2"/><circle cx="8.5" cy="10" r="2"/><path d="M4.5 16c1-2 2.5-2.6 4-2.6S11 14 12 16"/></svg>
          <div class="kyc-title">ID photo</div>
          <div class="kyc-hint">PSA / PhilSys National ID or another government-issued ID. Not a driver's license — that's what training here earns you. JPG/PNG, up to ${Math.round(KYC_MAX_KB / 1024)} MB.</div>
          <img class="kyc-preview" id="kycPreviewId" alt="ID preview">
          <input type="file" id="kycFileId" accept="image/jpeg,image/png,image/webp" onchange="onKycFile('id', this)">
        </div>
      </div>

      <div class="field">
        <label>Your selfie</label>
        <div class="kyc-drop" id="kycDropSelfie" title="Take a selfie now — the admin will compare it with your ID">
          <svg width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="8" r="4"/><path d="M4 21v-1a6 6 0 016-6h4a6 6 0 016 6v1"/></svg>
          <div class="kyc-title">Selfie</div>
          <div class="kyc-hint">Take a selfie now — your face must match the ID photo. JPG/PNG, up to ${Math.round(KYC_MAX_KB / 1024)} MB.</div>
          <img class="kyc-preview" id="kycPreviewSelfie" alt="Selfie preview">
          <input type="file" id="kycFileSelfie" accept="image/jpeg,image/png,image/webp" capture="user" onchange="onKycFile('selfie', this)">
        </div>
      </div>
    </div>

    <div class="kyc-consent">
      <input type="checkbox" id="kycConsent">
      <label for="kycConsent" style="margin:0;cursor:pointer;">I confirm that the documents I am uploading are mine and belong to me personally, and I allow RC Driving Academy to review them for identity verification.</label>
    </div>

    <p class="kyc-error" id="kycError"></p>

    <button class="btn btn-primary login-btn" id="kycSubmitBtn" onclick="submitKyc()">
      Submit for Verification
      <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
    </button>

    <p style="text-align:center;margin-top:14px;font-size:12px;color:var(--gray);">Verification by human review. Your documents stay private, visible only to administrators.<br>Driver's licenses and student permits are not accepted — they are issued by LTO only after your training here.</p>
  `;

  /* Restore previously-picked files into the inputs after a re-render. */
  if (KYC_PICKED.id) reflectPickedFile('id', KYC_PICKED.id);
  if (KYC_PICKED.selfie) reflectPickedFile('selfie', KYC_PICKED.selfie);
}

/* ---------- HELPERS ---------- */

function onKycFile(which, input) {
  const file = input && input.files && input.files[0];
  if (!file) return;

  if (file.size > KYC_MAX_KB * 1024) {
    RCDA.showToast(`The photo must be ${Math.round(KYC_MAX_KB / 1024)} MB or smaller.`);
    input.value = '';
    return;
  }
  if (!/image\/(jpeg|png|webp)/.test(file.type || '')) {
    RCDA.showToast('Please choose a JPG or PNG photo.');
    input.value = '';
    return;
  }

  KYC_PICKED[which] = file;
  reflectPickedFile(which, file);
}

function reflectPickedFile(which, file) {
  const drop = document.getElementById(which === 'id' ? 'kycDropId' : 'kycDropSelfie');
  const input = document.getElementById(which === 'id' ? 'kycFileId' : 'kycFileSelfie');
  if (!drop || !input) return;

  if (!(input.files && input.files[0])) {
    try {
      const dt = new DataTransfer();
      dt.items.add(file);
      input.files = dt.files;
    } catch (e) {
      /* older browsers: the FileList can't be rebuilt; the kept copy is used
         directly at submit time by submitKyc(), so this is cosmetic only */
      input.missingFile = file;
    }
  }

  drop.classList.add('has-file');
  const preview = drop.querySelector('.kyc-preview');
  if (preview) preview.src = URL.createObjectURL(file);
  const title = drop.querySelector('.kyc-title');
  if (title) title.textContent = file.name.length > 26 ? file.name.slice(0, 24) + '…' : file.name;
}

function fmtStamp(iso) {
  if (!iso) return '—';
  const d = new Date(iso);
  return d.toLocaleDateString('en-PH', { month: 'short', day: 'numeric', year: 'numeric' })
    + ' ' + d.toLocaleTimeString('en-PH', { hour: 'numeric', minute: '2-digit' });
}

/* ---------- SUBMIT ---------- */

function submitKyc() {
  const errEl = document.getElementById('kycError');
  const btn = document.getElementById('kycSubmitBtn');
  const show = msg => { if (errEl) { errEl.textContent = msg; errEl.style.display = 'block'; } };

  const idType = (document.getElementById('kycIdType') || {}).value;
  const idFile = KYC_PICKED.id;
  const selfie = KYC_PICKED.selfie;
  const consent = !!(document.getElementById('kycConsent') || {}).checked;

  if (!idType) return show('Please choose which ID you are uploading.');
  if (!idFile) return show('Upload a photo of your valid ID.');
  if (!selfie) return show('Upload a selfie of yourself.');
  if (!consent) return show('Please confirm that the documents are yours and may be reviewed.');

  const fd = new FormData();
  fd.append('id_type', idType);
  fd.append('id_document', idFile, idFile.name || 'id.jpg');
  fd.append('selfie', selfie, selfie.name || 'selfie.jpg');
  fd.append('consent', '1');

  if (btn) { btn.disabled = true; btn.classList.add('loading'); }
  if (errEl) errEl.style.display = 'none';

  RCDA.safeUpload(KYC_API(), fd).then(res => {
    if (btn) { btn.disabled = false; btn.classList.remove('loading'); }
    if (!res.ok) { show(res.error || 'Upload failed. Please try again.'); return; }
    if (res.data && res.data.kyc) KYC_CURRENT = res.data.kyc;
    RCDA.showToast('Documents submitted! An administrator will review them shortly.', 'success');
    renderPendingState();
  });
}

function escapeHtml(s) {
  return String(s == null ? '' : s)
    .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
}
