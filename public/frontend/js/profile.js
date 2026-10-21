/* ============================================================
   profile.js — shared across all dashboards.
   Injects a self-contained "My Profile" modal that lets the
   logged-in user (admin / instructor / student) edit their own
   details and change their password.

   Open it with: openProfileModal()
   ============================================================ */

(function () {
  function esc(v) {
    return (v === null || v === undefined) ? '' : String(v)
      .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');
  }

  var FIELDS = {
    student: [
      { key: 'phone', label: 'Phone', type: 'text' },
      { key: 'address', label: 'Address', type: 'text' },
      { key: 'birthdate', label: 'Birthdate', type: 'date' },
      { key: 'license_type', label: 'License Type', type: 'select', options: [['', '—'], ['student-permit', 'Student Permit'], ['non-pro', 'Non-Professional'], ['pro', 'Professional']] },
      { key: 'gender', label: 'Gender', type: 'select', options: [['', '—'], ['male', 'Male'], ['female', 'Female'], ['other', 'Other']] }
    ],
    instructor: [
      { key: 'phone', label: 'Phone', type: 'text' },
      { key: 'specialization', label: 'Specialization', type: 'text' },
      { key: 'license_no', label: 'License No.', type: 'text' },
      { key: 'gender', label: 'Gender', type: 'select', options: [['', '—'], ['male', 'Male'], ['female', 'Female'], ['other', 'Other']] }
    ]
  };

  function fieldHtml(f, value) {
    var input;
    if (f.type === 'select') {
      input = '<select id="pf_' + f.key + '">' + f.options.map(function (o) {
        return '<option value="' + esc(o[0]) + '"' + (String(value || '') === String(o[0]) ? ' selected' : '') + '>' + esc(o[1]) + '</option>';
      }).join('') + '</select>';
    } else {
      input = '<input id="pf_' + f.key + '" type="' + f.type + '" value="' + esc(value || '') + '">';
    }
    return '<div class="pf-field"><label>' + esc(f.label) + '</label>' + input + '</div>';
  }

  function buildModal(data) {
    var role = data.user.role;
    var detail = role === 'instructor' ? data.instructor : (role === 'student' ? data.student : null);
    var extra = (FIELDS[role] && detail)
      ? FIELDS[role].map(function (f) { return fieldHtml(f, detail[f.key]); }).join('')
      : '';

    var html =
      '<div class="modal-overlay" id="profileModal">' +
        '<div class="modal-box" style="max-width:560px;">' +
          '<div class="modal-head"><h3>My Profile</h3>' +
            '<button class="modal-close" onclick="closeModal(\'profileModal\')" aria-label="Close">✕</button></div>' +

          '<div class="pf-section">' +
            '<div class="pf-grid">' +
              '<div class="pf-field"><label>Full Name</label><input id="pf_name" type="text" value="' + esc(data.user.name) + '"></div>' +
              '<div class="pf-field"><label>Email</label><input id="pf_email" type="email" value="' + esc(data.user.email) + '"></div>' +
            '</div>' + extra +
            '<button class="btn btn-primary" style="margin-top:6px;" onclick="saveProfile()">Save Changes</button>' +
          '</div>' +

          '<hr style="border:none;border-top:1px solid var(--gray-light);margin:24px 0;">' +

          '<div class="pf-section">' +
            '<h4 style="font-size:15px;font-weight:700;margin-bottom:14px;">Change Password</h4>' +
            '<div class="pf-grid">' +
              '<div class="pf-field"><label>Current Password</label><input id="pf_current" type="password" autocomplete="current-password"></div>' +
            '</div>' +
            '<div class="pf-grid">' +
              '<div class="pf-field"><label>New Password</label><input id="pf_new" type="password" autocomplete="new-password"></div>' +
              '<div class="pf-field"><label>Confirm New Password</label><input id="pf_confirm" type="password" autocomplete="new-password"></div>' +
            '</div>' +
            '<div style="display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;">' +
              '<button class="btn btn-primary" onclick="changePassword()">Update Password</button>' +
              '<a href="#" onclick="event.preventDefault();requestForgotPassword()" style="font-size:13px;color:var(--orange);font-weight:600;">Forgot password?</a>' +
            '</div>' +
          '</div>' +
        '</div>' +
      '</div>';

    return html;
  }

  function ensureStyles() {
    if (document.getElementById('profileStyles')) return;
    var s = document.createElement('style');
    s.id = 'profileStyles';
    s.textContent =
      '.pf-grid{display:grid;grid-template-columns:1fr 1fr;gap:12px;}' +
      '.pf-field{display:flex;flex-direction:column;gap:6px;margin-bottom:12px;}' +
      '.pf-field label{font-size:12.5px;font-weight:600;color:var(--gray);}' +
      '.pf-field input,.pf-field select{padding:10px 12px;border-radius:10px;border:1.5px solid var(--gray-light);font-size:14px;background:white;width:100%;box-sizing:border-box;font-family:inherit;color:var(--charcoal);}' +
      '.pf-field input:focus,.pf-field select:focus{outline:none;border-color:var(--orange);}' +
      '@media(max-width:520px){.pf-grid{grid-template-columns:1fr;}}';
    document.head.appendChild(s);
  }

  function API() { return RCDA_CONFIG.getApiBaseUrl(); }

  window.openProfileModal = async function () {
    ensureStyles();
    var res = await RCDA.safeFetch(API() + '/profile', { method: 'GET' });
    if (!res.ok || !res.data || !res.data.user) {
      RCDA.showToast(res.error || 'Could not load your profile.');
      return;
    }
    var existing = document.getElementById('profileModal');
    if (existing) existing.remove();
    document.body.insertAdjacentHTML('beforeend', buildModal(res.data));
    openModal('profileModal');
  };

  window.saveProfile = async function () {
    var role = (RCDA_AUTH.getUser() || {}).role;
    var body = {
      name: (document.getElementById('pf_name') || {}).value || '',
      email: (document.getElementById('pf_email') || {}).value || ''
    };
    (FIELDS[role] || []).forEach(function (f) {
      var el = document.getElementById('pf_' + f.key);
      if (el) body[f.key] = el.value;
    });

    var res = await RCDA.safeFetch(API() + '/profile', { method: 'PUT', body: body });
    if (!res.ok) { RCDA.showToast(res.error || 'Could not save your profile.'); return; }
    RCDA_AUTH.setUser(res.data.user);
    applyUserInfo();
    RCDA.showToast('Profile updated.', 'success');
  };

  window.changePassword = async function () {
    var current = (document.getElementById('pf_current') || {}).value || '';
    var pass = (document.getElementById('pf_new') || {}).value || '';
    var confirm = (document.getElementById('pf_confirm') || {}).value || '';
    if (!current || !pass) { RCDA.showToast('Please fill in your current and new password.'); return; }
    if (pass !== confirm) { RCDA.showToast('New passwords do not match.'); return; }

    var res = await RCDA.safeFetch(API() + '/profile/password', {
      method: 'POST',
      body: { current_password: current, password: pass, password_confirmation: confirm }
    });
    if (!res.ok) { RCDA.showToast(res.error || 'Could not update your password.'); return; }
    document.getElementById('pf_current').value = '';
    document.getElementById('pf_new').value = '';
    document.getElementById('pf_confirm').value = '';
    RCDA.showToast('Password updated.', 'success');
  };

  window.requestForgotPassword = async function () {
    var email = (document.getElementById('pf_email') || {}).value || (RCDA_AUTH.getUser() || {}).email || '';
    var res = await RCDA.safeFetch(API() + '/password/forgot', { method: 'POST', body: { email: email } });
    RCDA.showToast((res.data && res.data.message) || 'Password reset delivery is not available yet.', 'info');
  };
})();
