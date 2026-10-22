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
    render: function (opts) {
      opts = opts || {};
      var container = typeof opts.container === 'string' ? document.getElementById(opts.container) : opts.container;
      if (!container) return;

      var mode = opts.mode || 'self';
      var userId = opts.userId || null;
      var url = (mode === 'adminEdit' && userId)
        ? apiBase() + '/api/admin/users/' + userId + '/profile'
        : apiBase() + '/api/profile';

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
      html += field(p + 'name', 'Full Name', user.name, 'text', ' required');
      html += field(p + 'email', 'Email', user.email, 'email', ' required');

      if (role === 'student') {
        html += field(p + 'phone', 'Phone', student.phone, 'text');
        html += field(p + 'address', 'Address', student.address, 'text');
        html += field(p + 'birthdate', 'Birthdate', student.birthdate, 'date');
        html += select(p + 'license_type', 'License Type', student.license_type, [
          ['', '— Not set —'], ['student-permit', 'Student Permit'], ['non-pro', 'Non-Pro'], ['pro', 'Pro']
        ]);
        html += select(p + 'gender', 'Gender', student.gender, [
          ['', '— Not set —'], ['male', 'Male'], ['female', 'Female'], ['other', 'Other']
        ]);
      } else if (role === 'instructor') {
        html += field(p + 'phone', 'Phone', instructor.phone, 'text');
        html += field(p + 'specialization', 'Specialization', instructor.specialization, 'text');
        html += field(p + 'license_no', 'License No.', instructor.license_no, 'text');
        html += select(p + 'gender', 'Gender', instructor.gender, [
          ['', '— Not set —'], ['male', 'Male'], ['female', 'Female'], ['other', 'Other']
        ]);
      }

      html += '<div class="rp-actions">' +
        '<button class="btn btn-primary" onclick="RCDAProfile.saveProfile(this)">Save Changes</button>' +
        '<a href="#" class="rp-link" onclick="RCDAProfile.requestForgotPassword(event)">Forgot password?</a>' +
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
        (isSelf ? 'Update Password' : 'Reset Password') + '</button></div>';
      html += '</div></div>';

      html += '</div>';

      container.innerHTML = html;
      container.dataset.rpMode = mode;
      container.dataset.rpUserId = userId || '';
      container.dataset.rpRole = role || '';
      container.dataset.rpPrefix = p;
      this.styles();
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
      var url = (mode === 'adminEdit' && userId)
        ? apiBase() + '/api/admin/users/' + userId + '/profile'
        : apiBase() + '/api/profile';

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
        this._send(btn, 'POST', apiBase() + '/api/profile/password', payload, 'Password updated.', function () {
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

      this._send(btn, 'PUT', apiBase() + '/api/admin/users/' + userId + '/profile', payload, 'Password reset.', function () {
        var el = scope.querySelector('#' + p + 'new'); if (el) el.value = '';
      });
    },

    sendVerifyCode: function (btn) {
      this._send(btn, 'POST', apiBase() + '/api/profile/verify-email', {}, 'Verification code sent.', null, true);
    },

    requestForgotPassword: function (e) {
      if (e && e.preventDefault) e.preventDefault();
      var host = e && e.target ? e.target.closest('[data-rp-mode]') : null;
      var scope = host || document;
      var p = (scope.getAttribute && scope.getAttribute('data-rp-prefix')) || '';
      var el = scope.querySelector('#' + p + 'email');
      var email = el ? el.value : '';
      fetch(apiBase() + '/api/password/forgot', {
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
      this._send(btn, 'POST', apiBase() + '/api/profile/verify-email/confirm', { code: code }, 'Email verified.', function () {
        if (el) el.value = '';
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
        '.rp-pill{font-size:11.5px;font-weight:700;padding:5px 12px;border-radius:999px;background:var(--gray-light);color:var(--gray);}',
        '.rp-pill.ok{background:var(--success-bg);color:var(--success);}',
        '.rp-loading,.rp-error{padding:18px;font-size:13.5px;color:var(--gray);}',
        '.rp-error{color:var(--orange);}',
        '@media(max-width:900px){.rp-wrap.two-col{grid-template-columns:1fr;}}',
        '@media(max-width:600px){.rp-field input,.rp-field select{font-size:16px;}.rp-actions .btn{flex:1 1 100%;}}'
      ].join('');
      document.head.appendChild(s);
    }
  };
})();
