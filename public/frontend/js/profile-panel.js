/* ============================================================
   profile-panel.js — shared editable profile panel used by the
   admin, instructor and student dashboards.

   RCDAProfile.render({ container, mode: 'self' | 'adminEdit', userId })
   ============================================================ */

(function () {
  'use strict';

  function esc(s) {
    if (s === null || s === undefined) return '';
    return String(s).replace(/[&<>"']/g, function (c) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
    });
  }

  function apiBase() {
    return (window.RCDA_CONFIG && RCDA_CONFIG.getApiBaseUrl) ? RCDA_CONFIG.getApiBaseUrl() : '';
  }

  function token() {
    if (window.RCDA_AUTH && RCDA_AUTH.getToken) return RCDA_AUTH.getToken();
    return localStorage.getItem('rcda_token');
  }

  function toast(message, kind) {
    if (window.RCDA && RCDA.showToast) RCDA.showToast(message, kind || 'success');
    else alert(message);
  }

  /* Human labels for the valid-ID workflow (matches the server's states). */
  var ID_LABELS = {
    none: 'Not submitted',
    pending: 'Awaiting review',
    approved: 'Verified',
    rejected: 'Needs attention'
  };

  var ID_PILL_CLASS = {
    none: '',
    pending: 'warn',
    approved: 'ok',
    rejected: 'bad'
  };

  function idStatusLabel(status) {
    return ID_LABELS[status] || ID_LABELS.none;
  }

  /* Laravel returns validation failures as { errors: { field: [msg] } }. */
  function firstError(data, fallback) {
    if (data && data.errors) {
      var keys = Object.keys(data.errors);
      if (keys.length && data.errors[keys[0]] && data.errors[keys[0]][0]) {
        return data.errors[keys[0]][0];
      }
    }
    return (data && data.message) || fallback;
  }

  function fmtDate(value) {
    if (!value) return '';
    var d = new Date(value);
    if (isNaN(d.getTime())) return '';
    return d.toLocaleDateString('en-PH', { year: 'numeric', month: 'short', day: 'numeric' });
  }

  function field(id, label, value, type, extra) {
    return '<div class="rp-field"><label for="' + id + '">' + label + '</label>' +
      '<input id="' + id + '" type="' + (type || 'text') + '" value="' + esc(value || '') + '"' + (extra || '') + '></div>';
  }

  function select(id, label, value, options) {
    var opts = options.map(function (o) {
      var val = o[0], text = o[1];
      return '<option value="' + esc(val) + '"' + (String(value || '') === String(val) ? ' selected' : '') + '>' + esc(text) + '</option>';
    }).join('');
    return '<div class="rp-field"><label for="' + id + '">' + label + '</label>' +
      '<select id="' + id + '">' + opts + '</select></div>';
  }

  function pwField(id, label, autocomplete) {
    return '<div class="rp-field"><label for="' + id + '">' + label + '</label>' +
      '<div class="rp-pw"><input id="' + id + '" type="password" autocomplete="' + autocomplete + '">' +
      '<button type="button" class="rp-eye" onclick="RCDAProfile.togglePw(\'' + id + '\',this)" aria-label="Show password" tabindex="-1">' +
      '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">' +
      '<path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7-11-7-11-7z"/><circle cx="12" cy="12" r="3"/></svg>' +
      '</button></div></div>';
  }

  /* Each rendered panel gets its own id prefix so several panels can live in
     the DOM at once (the admin has both their own profile and the
     "edit another user" modal open at the same time). */
  var seq = 0;

  function nextPrefix() {
    seq += 1;
    return 'rp' + seq + '_';
  }

  window.RCDAProfile = {
    /* Read-only view of who you are. The editable form lives in
       "Account Settings" so the two pages are not duplicates. */
    renderSummary: function (opts) {
      opts = opts || {};
      var container = typeof opts.container === 'string' ? document.getElementById(opts.container) : opts.container;
      if (!container) return;

      container.innerHTML = '<div class="rp-loading">Loading profile...</div>';

      fetch(apiBase() + '/profile', { headers: { 'Accept': 'application/json', 'Authorization': token() ? 'Bearer ' + token() : '' } })
        .then(function (res) { return res.json().then(function (d) { return { ok: res.ok, data: d }; }); })
        .then(function (r) {
          if (!r.ok) throw new Error(r.data && r.data.message ? r.data.message : 'Could not load profile.');
          this.paintSummary(container, r.data);
        }.bind(this))
        .catch(function (e) {
          container.innerHTML = '<div class="rp-error">' + esc(e.message) + '</div>';
        }.bind(this));
    },

    paintSummary: function (container, data) {
      var user = data.user || {};
      var student = data.student || {};
      var instructor = data.instructor || {};
      var name = user.name || 'User';
      var initials = name.split(' ').filter(Boolean).map(function (w) { return w[0]; }).slice(0, 2).join('').toUpperCase();
      var roleLabel = user.role ? user.role.charAt(0).toUpperCase() + user.role.slice(1) : '—';

      var rows = [];
      function row(label, value) {
        rows.push('<div class="rp-row"><span>' + esc(label) + '</span><b>' + esc(value || '—') + '</b></div>');
      }
      row('Email', user.email);
      row('Role', roleLabel);
      row('Email status', user.email_verified ? 'Verified' : 'Not verified yet');
      if (user.role === 'student') {
        row('Phone', student.phone);
        row('Address', student.address);
        row('License type', student.license_type);
        row('Gender', student.gender);
        row('Valid ID', idStatusLabel(student.id_status));
      } else if (user.role === 'instructor') {
        row('Phone', instructor.phone);
        row('Specialization', instructor.specialization);
        row('License no.', instructor.license_no);
        row('Gender', instructor.gender);
      }

      var html = '<div class="card rp-summary-card">';
      html += '<div class="card-head"><h3>My Profile</h3>' +
        '<span class="rp-pill' + (user.email_verified ? ' ok' : '') + '">' +
        (user.email_verified ? 'Email verified' : 'Email not verified') + '</span></div>';
      html += '<div class="rp-identity">';
      html += '<div class="rp-avatar">' + esc(initials) + '</div>';
      html += '<div><h4>' + esc(name) + '</h4><span>' + esc(user.email) + '</span></div>';
      html += '</div>';
      html += '<div class="rp-rows">' + rows.join('') + '</div>';
      html += '<p class="rp-hint">Use <b>Account Settings</b> to change your name, email, phone, password and email verification.</p>';
      html += '</div>';

      container.innerHTML = html;
      this.styles();
    },

    render: function (opts) {
      opts = opts || {};
      var container = typeof opts.container === 'string' ? document.getElementById(opts.container) : opts.container;
      if (!container) return;

      var mode = opts.mode || 'self';
      var userId = opts.userId || null;
      var url = (mode === 'adminEdit' && userId)
        ? apiBase() + '/admin/users/' + userId + '/profile'
        : apiBase() + '/profile';

      container.innerHTML = '<div class="rp-loading">Loading profile...</div>';

      fetch(url, { headers: { 'Accept': 'application/json', 'Authorization': token() ? 'Bearer ' + token() : '' } })
        .then(function (res) { return res.json().then(function (d) { return { ok: res.ok, data: d }; }); })
        .then(function (r) {
          if (!r.ok) throw new Error(r.data && r.data.message ? r.data.message : 'Could not load profile.');
          this.paint(container, r.data, opts);
        }.bind(this))
        .catch(function (e) {
          container.innerHTML = '<div class="rp-error">' + esc(e.message) + '</div>';
        }.bind(this));
    },

    /* The Valid ID card is used in two places: inside the full profile
       form and stand-alone on the dashboard (via renderIdCard). */
    idCardHtml: function (p) {
      var html = '<div class="card rp-id-card">';
      html += '<div class="card-head"><h3>Valid ID Verification</h3><span class="rp-pill" id="' + p + 'idPill">Checking…</span></div>';
      html += '<p class="rp-hint">Upload one clear photo or PDF of a government-issued ID so the registrar can verify your identity. The file is stored privately and is only visible to you and the academy admin.</p>';
      html += '<div class="rp-id-body"><div class="rp-loading">Loading verification status...</div></div>';
      html += '</div>';
      return html;
    },

    /* Stand-alone mount: RCDAProfile.renderIdCard({ container }) */
    renderIdCard: function (opts) {
      opts = opts || {};
      var container = typeof opts.container === 'string' ? document.getElementById(opts.container) : opts.container;
      if (!container) return;

      var p = nextPrefix();
      container.dataset.rpRole = 'student';
      container.dataset.rpPrefix = p;
      container.innerHTML = this.idCardHtml(p);
      this.styles();
      this.mountIdCard(container);
    },

    paint: function (container, data, opts) {
      var mode = opts.mode || 'self';
      var user = data.user || {};
      var student = data.student || {};
      var instructor = data.instructor || {};
      var role = user.role;
      var userId = opts.userId || user.id;
      var isSelf = mode !== 'adminEdit';
      var p = nextPrefix();

      var html = '<div class="two-col rp-wrap">';

      /* ---------- Profile details ---------- */
      html += '<div class="card">';
      html += '<div class="card-head"><h3>' + (isSelf ? 'My Profile' : 'Edit Profile') + '</h3>' +
        '<span class="rp-pill' + (user.email_verified ? ' ok' : '') + '">' + (user.email_verified ? 'Email verified' : 'Email not verified') + '</span></div>';
      html += '<div class="rp-form">';
      html += field(p + 'name', 'Full Name', user.name, 'text', ' required maxlength="60" oninput="sanitizeNameInput(this)"');
      html += field(p + 'email', 'Email', user.email, 'email', ' required');

      if (role === 'student') {
        html += field(p + 'phone', 'Phone', student.phone, 'text', ' inputmode="numeric" maxlength="11" placeholder="09XXXXXXXXX" oninput="sanitizePhoneInput(this)"');
        html += field(p + 'address', 'Address', student.address, 'text', ' maxlength="500"');
        html += field(p + 'birthdate', 'Birthdate', student.birthdate, 'date');
        html += select(p + 'license_type', 'License Type', student.license_type, [
          ['', '— Not set —'], ['student-permit', 'Student Permit'], ['non-pro', 'Non-Pro'], ['pro', 'Pro']
        ]);
        html += select(p + 'gender', 'Gender', student.gender, [
          ['', '— Not set —'], ['male', 'Male'], ['female', 'Female'], ['other', 'Other']
        ]);
      } else if (role === 'instructor') {
        html += field(p + 'phone', 'Phone', instructor.phone, 'text', ' inputmode="numeric" maxlength="11" placeholder="09XXXXXXXXX" oninput="sanitizePhoneInput(this)"');
        html += field(p + 'specialization', 'Specialization', instructor.specialization, 'text');
        html += field(p + 'license_no', 'License No.', instructor.license_no, 'text');
        html += select(p + 'gender', 'Gender', instructor.gender, [
          ['', '— Not set —'], ['male', 'Male'], ['female', 'Female'], ['other', 'Other']
        ]);
      }

      html += '<div class="rp-actions">' +
        '<button class="btn btn-primary" onclick="RCDAProfile.saveProfile(this)">Save Changes</button>' +
        '</div>';

      if (isSelf && !user.email_verified) {
        html += '<div class="rp-verify">' +
          '<p class="rp-hint">Verify your email to secure your account. We will send a 6-digit code to ' +
          esc(user.email) + '.</p>' +
          '<div class="rp-field"><label for="' + p + 'code">Verification Code</label>' +
          '<input id="' + p + 'code" inputmode="numeric" autocomplete="one-time-code" placeholder="6-digit code"></div>' +
          '<div class="rp-actions">' +
          '<button class="btn btn-ghost" onclick="RCDAProfile.sendVerifyCode(this)">Send Code</button>' +
          '<button class="btn btn-primary" onclick="RCDAProfile.confirmVerifyCode(this)">Verify Email</button>' +
          '</div></div>';
      }
      html += '</div></div>';

      /* ---------- Password ---------- */
      html += '<div class="card">';
      html += '<div class="card-head"><h3>' + (isSelf ? 'Change Password' : 'Reset Password') + '</h3></div>';
      html += '<div class="rp-form">';
      if (isSelf) {
        html += pwField(p + 'current', 'Current Password', 'current-password');
      }
      html += pwField(p + 'new', isSelf ? 'New Password' : 'New Password (leave blank to keep)', 'new-password');
      if (isSelf) {
        html += pwField(p + 'confirm', 'Confirm New Password', 'new-password');
      }
      html += '<p class="rp-hint">Password must be at least 8 characters.</p>';
      html += '<div class="rp-actions">' +
        '<button class="btn btn-primary" onclick="RCDAProfile.savePassword(this)">' +
        (isSelf ? 'Update Password' : 'Reset Password') + '</button>' +
        (isSelf ? '<a href="#" class="rp-link" onclick="RCDAProfile.requestForgotPassword(event)">Forgot password?</a>' : '') +
        '</div>';
      html += '</div></div>';

      /* ---------- Valid ID (students, self-service only) ---------- */
      if (isSelf && role === 'student') {
        html += this.idCardHtml(p);
      }

      html += '</div>';

      container.innerHTML = html;
      container.dataset.rpMode = mode;
      container.dataset.rpUserId = userId || '';
      container.dataset.rpRole = role || '';
      container.dataset.rpPrefix = p;
      this.styles();
      this.mountIdCard(container);
    },

    togglePw: function (id, btn) {
      var input = document.getElementById(id);
      if (!input) return;
      var show = input.type === 'password';
      input.type = show ? 'text' : 'password';
      if (btn) btn.classList.toggle('showing', show);
    },

    _collect: function (host) {
      var scope = host || document;
      var p = (scope.getAttribute && scope.getAttribute('data-rp-prefix')) || '';
      function val(key) {
        var el = scope.querySelector('#' + p + key);
        return el ? el.value : '';
      }
      var payload = {
        name: val('name'),
        email: val('email')
      };
      payload.name = payload.name.trim();
      payload.email = payload.email.trim();

      var role = scope.getAttribute ? (scope.getAttribute('data-rp-role') || '') : '';

      if (role === 'student') {
        payload.phone = val('phone');
        payload.address = val('address');
        payload.birthdate = val('birthdate') || null;
        payload.license_type = val('license_type') || null;
        payload.gender = val('gender') || null;
      } else if (role === 'instructor') {
        payload.phone = val('phone');
        payload.specialization = val('specialization');
        payload.license_no = val('license_no');
        payload.gender = val('gender') || null;
      }
      return payload;
    },

    saveProfile: function (btn) {
      var host = btn.closest('[data-rp-mode]');
      var mode = host ? host.getAttribute('data-rp-mode') : 'self';
      var userId = host ? host.getAttribute('data-rp-user-id') : '';
      var payload = this._collect(host);

      /* Keep the client in lock-step with the server rules so a bad name or
         phone never costs a round-trip (and the message is friendlier). */
      if (typeof isValidPersonName === 'function' && payload.name && !isValidPersonName(payload.name)) {
        toast('Names may only use letters, spaces, periods, apostrophes and hyphens (2–60 characters).', 'error');
        return;
      }
      if (typeof isValidPhMobile === 'function' && payload.phone && !isValidPhMobile(payload.phone)) {
        toast('Phone must be an 11-digit mobile number starting with 09.', 'error');
        return;
      }

      var url = (mode === 'adminEdit' && userId)
        ? apiBase() + '/admin/users/' + userId + '/profile'
        : apiBase() + '/profile';

      this._send(btn, 'PUT', url, payload, 'Profile saved.', function () {
        if (mode === 'self' && window.RCDA_AUTH && RCDA_AUTH.setUser) {
          var u = RCDA_AUTH.getUser() || {};
          u.name = payload.name; u.email = payload.email;
          RCDA_AUTH.setUser(u);
          if (typeof applyUserInfo === 'function') applyUserInfo();
        }
      });
    },

    savePassword: function (btn) {
      var host = btn.closest('[data-rp-mode]');
      var mode = host ? host.getAttribute('data-rp-mode') : 'self';
      var userId = host ? host.getAttribute('data-rp-user-id') : '';
      var scope = host || document;
      var p = (scope.getAttribute && scope.getAttribute('data-rp-prefix')) || '';
      function val(key) { var el = scope.querySelector('#' + p + key); return el ? el.value : ''; }
      var payload = {};

      if (mode === 'self') {
        payload.current_password = val('current');
        payload.password = val('new');
        payload.password_confirmation = val('confirm');
        if (!payload.password) { toast('Enter the new password.', 'error'); return; }
        if (payload.password !== payload.password_confirmation) { toast('New passwords do not match.', 'error'); return; }
        this._send(btn, 'POST', apiBase() + '/profile/password', payload, 'Password updated.', function () {
          ['current', 'new', 'confirm'].forEach(function (key) {
            var el = scope.querySelector('#' + p + key); if (el) el.value = '';
          });
        });
        return;
      }

      var newPass = val('new');
      if (!newPass) { toast('Enter the new password.', 'error'); return; }
      payload.password = newPass;

      var details = this._collect(host);
      payload.name = details.name;
      payload.email = details.email;
      if (details.phone !== undefined) payload.phone = details.phone;
      if (details.address !== undefined) payload.address = details.address;
      if (details.birthdate !== undefined) payload.birthdate = details.birthdate;
      if (details.license_type !== undefined) payload.license_type = details.license_type;
      if (details.specialization !== undefined) payload.specialization = details.specialization;
      if (details.license_no !== undefined) payload.license_no = details.license_no;
      if (details.gender !== undefined) payload.gender = details.gender;

      this._send(btn, 'PUT', apiBase() + '/admin/users/' + userId + '/profile', payload, 'Password reset.', function () {
        var el = scope.querySelector('#' + p + 'new'); if (el) el.value = '';
      });
    },

    sendVerifyCode: function (btn) {
      this._send(btn, 'POST', apiBase() + '/profile/verify-email', {}, 'Verification code sent.', null, true);
    },

    requestForgotPassword: function (e) {
      if (e && e.preventDefault) e.preventDefault();
      var host = e && e.target ? e.target.closest('[data-rp-mode]') : null;
      var scope = host || document;
      var p = (scope.getAttribute && scope.getAttribute('data-rp-prefix')) || '';
      var el = scope.querySelector('#' + p + 'email');
      var email = el ? el.value : '';
      fetch(apiBase() + '/password/forgot', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
        body: JSON.stringify({ email: email })
      })
        .then(function (res) { return res.json().then(function (d) { return { ok: res.ok, data: d }; }); })
        .then(function (r) {
          toast((r.data && r.data.message) || 'Password reset delivery is not available yet.', r.ok ? 'info' : 'error');
        })
        .catch(function (err) { toast(err.message, 'error'); });
    },

    confirmVerifyCode: function (btn) {
      var host = btn.closest('[data-rp-mode]');
      var scope = host || document;
      var p = (scope.getAttribute && scope.getAttribute('data-rp-prefix')) || '';
      var el = scope.querySelector('#' + p + 'code');
      var code = el ? el.value.trim() : '';
      if (!code) { toast('Enter the verification code.', 'error'); return; }
      this._send(btn, 'POST', apiBase() + '/profile/verify-email/confirm', { code: code }, 'Email verified.', function () {
        if (el) el.value = '';
      });
    },

    /* ---------- Valid ID verification ---------- */

    _headers: function (json) {
      var h = { 'Accept': 'application/json' };
      if (token()) h['Authorization'] = 'Bearer ' + token();
      if (json) h['Content-Type'] = 'application/json';
      return h;
    },

    mountIdCard: function (container) {
      var card = container ? container.querySelector('.rp-id-card') : null;
      if (!card) return;

      var p = (container.getAttribute && container.getAttribute('data-rp-prefix')) || '';
      var self = this;

      fetch(apiBase() + '/student/id-document', { headers: this._headers(false) })
        .then(function (res) { return res.json().then(function (d) { return { ok: res.ok, data: d }; }); })
        .then(function (r) {
          if (!r.ok) throw new Error(firstError(r.data, 'Could not load your verification status.'));
          self.paintIdCard(card, r.data || {}, p);
        })
        .catch(function (e) {
          var body = card.querySelector('.rp-id-body');
          if (body) body.innerHTML = '<div class="rp-error">' + esc(e.message) + '</div>';
        });
    },

    paintIdCard: function (card, data, p) {
      if (!card) return;

      var doc = data.id || {};
      var status = doc.status || 'none';
      var types = data.types || [];
      var maxKb = data.max_kb || 5120;

      var pill = card.querySelector('#' + p + 'idPill');
      if (pill) {
        pill.textContent = idStatusLabel(status);
        pill.className = 'rp-pill ' + (ID_PILL_CLASS[status] || '');
      }

      var html = '';

      if (doc.has_document) {
        html += '<div class="rp-id-doc">';
        html += '<div class="rp-id-meta">';
        html += '<b>' + esc(doc.document_type_label || 'Valid ID') + '</b>';
        html += '<span>' + esc(doc.document_name || 'Uploaded file') + '</span>';
        html += '</div>';
        html += '<button type="button" class="btn btn-sm btn-ghost" onclick="RCDAProfile.viewIdDocument(this)">View file</button>';
        html += '</div>';

        var meta = [];
        if (doc.submitted_at) meta.push('Submitted ' + fmtDate(doc.submitted_at));
        if (doc.reviewed_at) meta.push('Reviewed ' + fmtDate(doc.reviewed_at));
        if (meta.length) html += '<p class="rp-hint">' + esc(meta.join(' · ')) + '</p>';
      }

      if (status === 'approved') {
        html += '<div class="rp-id-note ok">Your ID has been verified by the academy. Nothing else to do.</div>';
      } else if (status === 'pending') {
        html += '<div class="rp-id-note warn">Your ID is waiting for admin review. Uploading a new file restarts the review.</div>';
      } else if (status === 'rejected') {
        html += '<div class="rp-id-note bad"><b>This ID was not accepted.</b> ' +
          esc(doc.rejection_reason || 'Please upload a clearer copy of a valid government ID.') + '</div>';
      } else {
        html += '<div class="rp-id-note">No ID on file yet. Upload one below to get verified.</div>';
      }

      var form = '<div class="rp-id-form">';
      form += '<div class="rp-field"><label for="' + p + 'idType">ID type</label><select id="' + p + 'idType">';
      types.forEach(function (t) {
        form += '<option value="' + esc(t.value) + '"' +
          (doc.document_type === t.value ? ' selected' : '') + '>' + esc(t.label) + '</option>';
      });
      form += '</select></div>';
      form += '<div class="rp-field"><label for="' + p + 'idFile">ID photo or PDF</label>';
      form += '<input id="' + p + 'idFile" type="file" accept="image/jpeg,image/png,image/webp,application/pdf">';
      form += '<span class="rp-hint">JPG, PNG, WEBP or PDF — up to ' + Math.round(maxKb / 1024) + ' MB.</span></div>';
      form += '<label class="rp-id-consent"><input id="' + p + 'idConsent" type="checkbox"> ' +
        'This ID is mine and I allow RC Driving Academy to review it.</label>';
      form += '<div class="rp-actions"><button type="button" class="btn btn-primary" onclick="RCDAProfile.uploadId(this)">' +
        (doc.has_document ? 'Submit replacement' : 'Submit for review') + '</button></div>';
      form += '</div>';

      if (doc.has_document) {
        html += '<details class="rp-id-replace"><summary>Replace this ID</summary>' + form + '</details>';
      } else {
        html += form;
      }

      var body = card.querySelector('.rp-id-body');
      if (body) body.innerHTML = html;
    },

    uploadId: function (btn) {
      var card = btn.closest('.rp-id-card');
      var container = btn.closest('[data-rp-prefix]');
      var p = (container && container.getAttribute && container.getAttribute('data-rp-prefix')) || '';

      var fileEl = document.getElementById(p + 'idFile');
      var typeEl = document.getElementById(p + 'idType');
      var consentEl = document.getElementById(p + 'idConsent');

      var file = fileEl && fileEl.files ? fileEl.files[0] : null;
      if (!file) { toast('Choose the ID file to upload first.', 'error'); return; }

      var allowed = ['image/jpeg', 'image/png', 'image/webp', 'application/pdf'];
      if (file.type && allowed.indexOf(file.type) === -1) {
        toast('Upload a JPG, PNG, WEBP or PDF file.', 'error'); return;
      }
      if (file.size > 5 * 1024 * 1024) {
        toast('The file must be 5 MB or smaller.', 'error'); return;
      }
      if (!consentEl || !consentEl.checked) {
        toast('Please confirm the ID is yours and may be reviewed.', 'error'); return;
      }

      var form = new FormData();
      form.append('document', file);
      form.append('document_type', typeEl ? typeEl.value : '');
      form.append('consent', '1');

      var old = btn.textContent;
      btn.disabled = true;
      btn.textContent = 'Uploading...';

      fetch(apiBase() + '/student/id-document', {
        method: 'POST',
        headers: this._headers(false),
        body: form
      })
        .then(function (res) {
          return res.json()
            .then(function (d) { return { ok: res.ok, data: d }; })
            .catch(function () { return { ok: res.ok, data: null }; });
        })
        .then(function (r) {
          if (!r.ok) throw new Error(firstError(r.data, 'The upload did not go through. Please try again.'));
          toast((r.data && r.data.message) || 'Valid ID submitted for review.', 'success');
          if (container) this.mountIdCard(container);
          document.dispatchEvent(new CustomEvent('rcda:id-updated'));
        }.bind(this))
        .catch(function (e) { toast(e.message, 'error'); })
        .finally(function () { btn.disabled = false; btn.textContent = old; });
    },

    viewIdDocument: function () {
      var win = window.open('', '_blank');
      if (win) {
        win.document.write('<p style="font-family:sans-serif;padding:24px;color:#555;">Opening your ID…</p>');
      }

      fetch(apiBase() + '/student/id-document/file', { headers: this._headers(false) })
        .then(function (res) {
          if (!res.ok) throw new Error('Could not open the document.');
          return res.blob();
        })
        .then(function (blob) {
          var url = URL.createObjectURL(blob);
          if (win) win.location = url;
          else window.open(url, '_blank');
          setTimeout(function () { URL.revokeObjectURL(url); }, 600000);
        })
        .catch(function (e) {
          if (win) win.close();
          toast(e.message, 'error');
        });
    },

    _send: function (btn, method, url, payload, okMessage, after, quiet) {
      var old = btn.textContent;
      btn.disabled = true;
      btn.textContent = 'Saving...';
      fetch(url, {
        method: method,
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'Authorization': token() ? 'Bearer ' + token() : '' },
        body: JSON.stringify(payload)
      })
        .then(function (res) { return res.json().then(function (d) { return { ok: res.ok, data: d }; }); })
        .then(function (r) {
          if (!r.ok) throw new Error(r.data && r.data.message ? r.data.message : 'Request failed.');
          toast(r.data && r.data.message ? r.data.message : okMessage, 'success');
          if (after) after(r.data);
        })
        .catch(function (e) { toast(e.message, 'error'); })
        .finally(function () { btn.disabled = false; btn.textContent = old; });
    },

    styles: function () {
      if (document.getElementById('rp-style')) return;
      var s = document.createElement('style');
      s.id = 'rp-style';
      s.textContent = [
        '.rp-wrap{align-items:start;}',
        '.rp-wrap > .card{margin-bottom:0;}',
        '.rp-form{display:flex;flex-direction:column;gap:14px;}',
        '.rp-field{display:flex;flex-direction:column;gap:6px;}',
        '.rp-field label{font-size:12.5px;font-weight:600;color:var(--gray);letter-spacing:.2px;}',
        '.rp-field input,.rp-field select{width:100%;padding:12px 14px;border-radius:11px;border:1.5px solid var(--gray-light);font-size:14px;background:var(--cream);color:var(--charcoal);font-family:inherit;}',
        '.rp-field input:focus,.rp-field select:focus{outline:none;border-color:var(--orange);}',
        '.rp-pw{position:relative;display:flex;align-items:center;}',
        '.rp-pw input{padding-right:42px;}',
        '.rp-eye{position:absolute;right:6px;top:50%;transform:translateY(-50%);background:transparent;border:none;padding:6px;cursor:pointer;color:var(--gray);display:flex;border-radius:8px;}',
        '.rp-eye:hover{color:var(--charcoal);}',
        '.rp-eye.showing{color:var(--orange);}',
        '.rp-actions{display:flex;gap:10px;flex-wrap:wrap;margin-top:4px;}',
        '.rp-link{font-size:13px;color:var(--orange);font-weight:600;text-decoration:none;align-self:center;}',
        '.rp-link:hover{text-decoration:underline;}',
        '.rp-verify{margin-top:16px;padding-top:16px;border-top:1px solid var(--gray-light);display:flex;flex-direction:column;gap:12px;}',
        '.rp-hint{font-size:12px;color:var(--gray);margin:0;}',
        '.rp-summary-card{max-width:760px;}',
        '.rp-identity{display:flex;align-items:center;gap:14px;margin-bottom:18px;}',
        '.rp-avatar{width:56px;height:56px;border-radius:50%;background:var(--orange);color:#fff;display:flex;align-items:center;justify-content:center;font-weight:800;font-size:20px;flex:0 0 auto;letter-spacing:.5px;}',
        '.rp-identity h4{margin:0 0 3px;font-size:18px;color:var(--charcoal);}',
        '.rp-identity span{font-size:13px;color:var(--gray);word-break:break-all;}',
        '.rp-rows{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px 18px;}',
        '.rp-row{display:flex;flex-direction:column;gap:2px;padding:10px 12px;background:var(--cream);border-radius:10px;border:1px solid var(--gray-light);}',
        '.rp-row span{font-size:11.5px;font-weight:600;color:var(--gray);text-transform:uppercase;letter-spacing:.3px;}',
        '.rp-row b{font-size:14px;color:var(--charcoal);font-weight:600;word-break:break-word;}',
        '.rp-summary-card .rp-hint{margin-top:16px;}',
        '.rp-pill{font-size:11.5px;font-weight:700;padding:5px 12px;border-radius:999px;background:var(--gray-light);color:var(--gray);}',
        '.rp-pill.ok{background:var(--success-bg);color:var(--success);}',
        '.rp-pill.warn{background:var(--warn2-bg);color:var(--warn2);}',
        '.rp-pill.bad{background:var(--danger2-bg);color:var(--danger2);}',
        '.rp-id-card{display:flex;flex-direction:column;gap:14px;}',
        '.rp-id-body{display:flex;flex-direction:column;gap:12px;}',
        '.rp-id-doc{display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;padding:12px 14px;border:1px solid var(--gray-light);border-radius:12px;background:var(--cream);}',
        '.rp-id-meta{display:flex;flex-direction:column;gap:2px;min-width:0;}',
        '.rp-id-meta b{font-size:14px;color:var(--charcoal);}',
        '.rp-id-meta span{font-size:12px;color:var(--gray);word-break:break-all;}',
        '.rp-id-note{font-size:13px;color:var(--charcoal);padding:11px 14px;border-radius:11px;background:var(--cream);border:1px solid var(--gray-light);}',
        '.rp-id-note.ok{background:var(--success-bg);border-color:transparent;color:#146237;}',
        '.rp-id-note.warn{background:var(--warn2-bg);border-color:transparent;color:#7a4d05;}',
        '.rp-id-note.bad{background:var(--danger2-bg);border-color:transparent;color:#8f1717;}',
        '.rp-id-form{display:flex;flex-direction:column;gap:12px;}',
        '.rp-id-form input[type=file]{padding:10px;font-size:13px;}',
        '.rp-id-consent{display:flex;align-items:flex-start;gap:8px;font-size:12.5px;color:var(--gray);line-height:1.5;}',
        '.rp-id-consent input{margin-top:2px;}',
        '.rp-id-replace{margin-top:4px;border-top:1px solid var(--gray-light);padding-top:12px;}',
        '.rp-id-replace summary{cursor:pointer;font-size:12.5px;font-weight:700;color:var(--orange);margin-bottom:12px;}',
        '.rp-id-replace[open] summary{margin-bottom:14px;}',
        '.rp-loading,.rp-error{padding:18px;font-size:13.5px;color:var(--gray);}',
        '.rp-error{color:var(--orange);}',
        '@media(max-width:900px){.rp-wrap.two-col{grid-template-columns:1fr;}.rp-rows{grid-template-columns:1fr;}}',
        '@media(max-width:600px){.rp-field input,.rp-field select{font-size:16px;}.rp-actions .btn{flex:1 1 100%;}}'
      ].join('');
      document.head.appendChild(s);
    }
  };
})();
