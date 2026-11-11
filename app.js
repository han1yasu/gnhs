/* ================================================================
   GNHS GUIDANCE SYSTEM v2 â€” app.js
   All buttons wired. PHP API integration + graceful demo fallback.
   ================================================================ */

// â”€â”€ Global state â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
let _action = 'login';
let _role   = 'student';
let _activeCaseId = null;

// â”€â”€ Utilities â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
const $  = id  => document.getElementById(id);
const $$ = sel => document.querySelectorAll(sel);

async function apiPost(url, data) {
  const r = await fetch(url, { method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify(data) });
  return r.json();
}

function showMsg(id, msg, type = 'error') {
  const el = $(id); if (!el) return;
  el.textContent = msg;
  el.className = type === 'error' ? 'error-msg' : 'success-msg';
  el.style.display = 'block';
}
function hideEl(id) { const el=$(id); if(el) el.style.display='none'; }

function setLoading(btn, on) {
  if (!btn) return;
  if (on) { btn.disabled=true; btn.dataset.orig=btn.innerHTML; btn.innerHTML='<span class="spinner"></span>&nbsp;Please wait…'; }
  else    { btn.disabled=false; btn.innerHTML=btn.dataset.orig||btn.innerHTML; }
}

function showToast(msg, type = 'success') {
  let c = $('toast-container');
  if (!c) { c=document.createElement('div'); c.id='toast-container'; document.body.appendChild(c); }
  const t = document.createElement('div');
  t.className = `toast toast-${type}`;
  const icons = {success:'check-circle',error:'times-circle',info:'info-circle'};
  t.innerHTML = `<i class="fas fa-${icons[type]||'info-circle'}"></i>${msg}`;
  c.appendChild(t);
  setTimeout(() => { t.style.opacity='0'; t.style.transition='opacity .3s'; setTimeout(()=>t.remove(),300); }, 3200);
}

// â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
// LANDING â€” LOGIN MODAL
// â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
function openLoginModal() {
  const m = $('loginModal'); if (m) { m.classList.remove('hidden'); showStep('step-choose'); }
}
function closeLoginModal() {
  const m = document.getElementById('loginModal');
  if (m) {
    m.classList.add('hidden');
    m.style.display = 'none';
  }
  document.body.style.overflow = '';
}
window.closeLoginModal = closeLoginModal;
function closeOnOverlay(e) { if (e.target.id==='loginModal') closeLoginModal(); }

function showStep(id) {
  $$('.modal-step').forEach(s => s.classList.add('hidden'));
  $(id)?.classList.remove('hidden');
}

function goToRoleSelect(action = 'login') {
  _action = 'login';
  const sub = $('role-sub-text');
  if (sub) sub.textContent = 'Who are you logging in as?';
  showStep('step-role');
}

function goBack(stepId) { showStep(stepId); }
function switchToRegister() { 
  showToast('Student accounts are provisioned by your Advisory Teacher or Guidance Counselor.', 'info');
  switchToLogin();
}
function switchToLogin() { 
  _action='login'; 
  if (_role) selectRole(_role);
  else showStep('step-role'); 
}

function selectRole(role) {
  _role = role;
  _action = 'login';
  const icons  = { student:'fa-user-graduate', teacher:'fa-chalkboard-teacher', admin:'fa-user-tie' };
  const labels = { student:'Student', teacher:'Teacher', admin:'Guidance Counselor' };
  const step   = 'step-login';

  const badgeId = _action==='login' ? 'login-role-badge' : 'reg-role-badge';
  const labelId = _action==='login' ? 'login-role-label' : 'reg-role-label';

  const badge = $(badgeId);
  if (badge) badge.innerHTML = '<i class="fas '+icons[role]+'"></i>';

  const lbl = $(labelId);
  if (lbl) lbl.textContent = labels[role] + (_action==='login' ? ' Login' : ' Registration');

  // Show/hide grade section (students only in register)
  const gradeField = $('reg-grade-field');
  if (gradeField) gradeField.style.display = role==='student' ? 'block' : 'none';

  // Update LRN / Employee Number label based on role
  const lrnLabel = document.getElementById('reg-lrn-label');
  const lrnInput = document.getElementById('reg-lrn-input');
  const lrnHelp  = document.getElementById('reg-lrn-help');
  if (lrnLabel && lrnInput) {
    if (role === 'student') {
      lrnLabel.innerHTML = 'LRN (12 Digits) <span class="req">*</span>';
      lrnInput.placeholder = 'Enter 12-digit LRN (Numbers only)';
      lrnInput.setAttribute('maxlength', '12');
      lrnInput.setAttribute('inputmode', 'numeric');
      lrnInput.setAttribute('pattern', '[0-9]{12}');
      if (lrnHelp) { lrnHelp.style.display = 'block'; lrnHelp.textContent = 'Must be strictly 12 numeric digits.'; }
    } else {
      lrnLabel.innerHTML = 'Employee Number <span class="req">*</span>';
      lrnInput.placeholder = 'Enter your Employee Number';
      lrnInput.removeAttribute('maxlength');
      lrnInput.removeAttribute('inputmode');
      lrnInput.removeAttribute('pattern');
      if (lrnHelp) { lrnHelp.style.display = 'none'; }
    }
  }

  // Update login page label too
  const loginLabel = document.getElementById('login-id-label');
  const loginInput = document.getElementById('login-email-input');
  if (loginLabel && loginInput) {
    if (role === 'student') {
      loginLabel.textContent = 'Email Address / LRN';
      loginInput.placeholder = 'Enter your email or 12-digit LRN';
    } else if (role === 'teacher') {
      loginLabel.textContent = 'Email Address / Employee Number';
      loginInput.placeholder = 'Enter your email or Employee Number';
    } else {
      loginLabel.textContent = 'Email Address';
      loginInput.placeholder = 'Enter Counselor email address';
    }
  }

  hideEl('login-msg'); hideEl('reg-msg');
  showStep(step);
}

function togglePass(inputId) {
  const inp = $(inputId); if (!inp) return;
  inp.type = inp.type==='password' ? 'text' : 'password';
  const btn = inp.nextElementSibling;
  if (btn) btn.innerHTML = inp.type==='password' ? '<i class="fas fa-eye"></i>' : '<i class="fas fa-eye-slash"></i>';
}

// â”€â”€ Login submit â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
async function handleLogin() {
  const email    = document.querySelector('#step-login input[type="text"]')?.value.trim();
  const password = $('login-pass')?.value.trim();
  if (!email||!password) { showMsg('login-msg','Please enter your email/LRN and password.'); return; }

  const btn = document.querySelector('#step-login .btn-submit');
  setLoading(btn, true); hideEl('login-msg');

  try {
    const res = await apiPost('/gnhs-guidance/api/login.php', { email, password, role:_role });
    if (res.success) { 
      if (res.user && typeof saveAccountToDevice === 'function') {
        saveAccountToDevice({
          email: res.user.email || email,
          name: res.user.name || email,
          role: res.user.role || _role,
          initials: res.user.initials
        });
      }
      document.body.classList.add('page-exiting');
      setTimeout(() => { window.location.href = res.redirect; }, 250);
    }
    else { showMsg('login-msg', res.message||'Invalid credentials. Please try again.'); setLoading(btn,false); }
  } catch {
    // Demo fallback — redirect based on role
    const map = { student:'pages/student-dashboard.php', teacher:'pages/teacher-dashboard.php', admin:'pages/admin-dashboard.php' };
    window.location.href = map[_role];
  }
}

// ── EMAIL OTP LOGIN & SIGNUP FLOW ──
let _otpEmail = '';
let _otpPendingRole = 'student';

function openEmailOtpFlow() {
  hideEl('login-msg');
  hideEl('email-otp-msg');
  _otpEmail = '';
  _otpPendingRole = _role || 'student';

  const sub = $('email-otp-sub');
  if (sub) {
    if (_otpPendingRole === 'teacher') {
      sub.innerHTML = 'Enter your DepEd or personal Gmail to receive a 6-digit login code.';
    } else {
      sub.innerHTML = 'Enter your personal Gmail to receive a 6-digit login code.';
    }
  }

  const stage1 = $('email-otp-stage-1');
  const stage2 = $('email-otp-stage-2');
  if (stage1) stage1.style.display = 'block';
  if (stage2) stage2.style.display = 'none';

  const emailInput = $('otp-email-input');
  if (emailInput) {
    const existing = document.querySelector('#step-login input[type="text"]')?.value.trim();
    if (existing && existing.includes('@')) {
      emailInput.value = existing;
    }
    setTimeout(() => emailInput.focus(), 200);
  }

  showStep('step-email-otp');
}

function editOtpEmail() {
  const stage1 = $('email-otp-stage-1');
  const stage2 = $('email-otp-stage-2');
  if (stage1) stage1.style.display = 'block';
  if (stage2) stage2.style.display = 'none';
  hideEl('email-otp-msg');
  const emailInput = $('otp-email-input');
  if (emailInput) emailInput.focus();
}

async function requestEmailCode(isResend = false) {
  const email = $('otp-email-input')?.value.trim().toLowerCase();
  if (!email || !email.includes('@')) {
    showMsg('email-otp-msg', 'Please enter a valid email address.');
    return;
  }
  _otpEmail = email;

  const btn = isResend ? $('btnResendOtp') : $('btnSendOtp');
  setLoading(btn, true);
  hideEl('email-otp-msg');

  try {
    const res = await apiPost('/gnhs-guidance/api/send_email_code.php', {
      email: _otpEmail,
      role: _otpPendingRole
    });

    setLoading(btn, false);
    if (res.success) {
      const stage1 = $('email-otp-stage-1');
      const stage2 = $('email-otp-stage-2');
      if (stage1) stage1.style.display = 'none';
      if (stage2) stage2.style.display = 'block';

      const targetLabel = $('otp-target-email-label');
      if (targetLabel) targetLabel.textContent = _otpEmail;

      let msg = res.message;
      if (res.code_hint) {
        msg += ' [Testing Helper: Code is ' + res.code_hint + ']';
      }
      showMsg('email-otp-msg', msg, 'success');

      const codeInput = $('otp-code-input');
      if (codeInput) {
        codeInput.value = '';
        setTimeout(() => codeInput.focus(), 150);
      }
    } else {
      showMsg('email-otp-msg', res.message || 'Failed to send verification code.');
    }
  } catch (err) {
    setLoading(btn, false);
    showMsg('email-otp-msg', 'Network error. Please try again.');
  }
}

async function verifyEmailCode() {
  const code = $('otp-code-input')?.value.trim();
  if (!code || code.length < 6) {
    showMsg('email-otp-msg', 'Please enter the complete 6-digit verification code.');
    return;
  }

  const btn = $('btnVerifyOtp');
  setLoading(btn, true);
  hideEl('email-otp-msg');

  try {
    const res = await apiPost('/gnhs-guidance/api/verify_email_code.php', {
      email: _otpEmail,
      code: code,
      role: _otpPendingRole
    });

    setLoading(btn, false);
    if (res.success) {
      if (!res.is_new) {
        showMsg('email-otp-msg', res.message || 'Verification successful! Logging you in...', 'success');
        document.body.classList.add('page-exiting');
        setTimeout(() => {
          window.location.href = res.redirect;
        }, 350);
      } else {
        _otpPendingRole = res.role || _otpPendingRole;
        openOtpCompleteProfile(res.email, _otpPendingRole);
      }
    } else {
      showMsg('email-otp-msg', res.message || 'Invalid or expired code. Please try again.');
    }
  } catch (err) {
    setLoading(btn, false);
    showMsg('email-otp-msg', 'Verification failed. Please check your connection and try again.');
  }
}

function openOtpCompleteProfile(email, role) {
  showStep('step-email-complete');
  hideEl('email-complete-msg');

  const gradeGroup = $('otp-complete-grade-group');
  const idLabel = $('otp-complete-id-label');
  
  if (role === 'teacher') {
    if (gradeGroup) gradeGroup.style.display = 'none';
    if (idLabel) idLabel.textContent = 'Employee ID (Optional)';
  } else {
    if (gradeGroup) gradeGroup.style.display = 'block';
    if (idLabel) idLabel.textContent = 'Student ID / LRN (Optional)';
    otpUpdateSections();
  }

  const fnInput = $('otp-complete-first-name');
  if (fnInput) setTimeout(() => fnInput.focus(), 150);
}

function otpUpdateSections() {
  const grade = $('otp-complete-grade')?.value;
  const sel = $('otp-complete-section');
  if (!sel) return;
  const secs = (typeof GNHS_GRADE_SECTIONS !== 'undefined' ? GNHS_GRADE_SECTIONS[grade] : []) || [];
  sel.innerHTML = secs.length
    ? '<option value="">Select Section</option>' + secs.map(s => '<option value="' + s + '">' + s + '</option>').join('')
    : '<option value="">Select Grade First</option>';
}

async function finishOtpSignup() {
  const firstName = $('otp-complete-first-name')?.value.trim();
  const lastName  = $('otp-complete-last-name')?.value.trim();
  const idNumber  = $('otp-complete-id-number')?.value.trim();

  let gradeSection = '';
  if (_otpPendingRole === 'student') {
    const grade = $('otp-complete-grade')?.value;
    const sec   = $('otp-complete-section')?.value;
    if (!grade || !sec) {
      showMsg('email-complete-msg', 'Please select both your Grade Level and Section.');
      return;
    }
    gradeSection = 'Grade ' + grade + ' - ' + sec;
  }

  if (!firstName || !lastName) {
    showMsg('email-complete-msg', 'Please enter both your First Name and Last Name.');
    return;
  }

  const btn = $('btnFinishOtpSignup');
  setLoading(btn, true);
  hideEl('email-complete-msg');

  try {
    const res = await apiPost('/gnhs-guidance/api/complete_email_signup.php', {
      email: _otpEmail,
      first_name: firstName,
      last_name: lastName,
      role: _otpPendingRole,
      grade_section: gradeSection,
      id_number: idNumber
    });

    setLoading(btn, false);
    if (res.success) {
      showMsg('email-complete-msg', res.message || 'Profile created! Redirecting...', 'success');
      document.body.classList.add('page-exiting');
      setTimeout(() => {
        window.location.href = res.redirect;
      }, 350);
    } else {
      showMsg('email-complete-msg', res.message || 'Failed to complete registration.');
    }
  } catch (err) {
    setLoading(btn, false);
    showMsg('email-complete-msg', 'Server error. Please try again.');
  }
}

// ── Forgot Password Self-Service Flow (Email 6-Digit OTP & Password Reset) ──
let _resetFlowState = {
  identifier: '',
  email: '',
  resetToken: ''
};

function openResetPasswordModal() {
  hideEl('login-msg');
  hideEl('forgot-msg');
  _resetFlowState = { identifier: '', email: '', resetToken: '' };

  const st1 = $('forgot-stage-1');
  const st2 = $('forgot-stage-2');
  const st3 = $('forgot-stage-3');
  if (st1) st1.style.display = 'block';
  if (st2) st2.style.display = 'none';
  if (st3) st3.style.display = 'none';

  if ($('forgot-modal-title')) $('forgot-modal-title').textContent = 'Reset Password';
  if ($('forgot-modal-sub')) $('forgot-modal-sub').textContent = 'Enter your registered Email or Student ID / LRN to receive a 6-digit confirmation code.';

  const emailInput = $('forgot-email-input');
  if (emailInput) {
    const loginVal = $('login-email-input')?.value?.trim() || '';
    if (loginVal) emailInput.value = loginVal;
  }
  if ($('forgot-code-input')) $('forgot-code-input').value = '';
  if ($('forgot-new-password')) $('forgot-new-password').value = '';
  if ($('forgot-confirm-password')) $('forgot-confirm-password').value = '';

  showStep('step-forgot');
  setTimeout(() => emailInput?.focus(), 80);
}

function openForgotModal() {
  openResetPasswordModal();
}

function editResetEmail() {
  hideEl('forgot-msg');
  const st1 = $('forgot-stage-1');
  const st2 = $('forgot-stage-2');
  const st3 = $('forgot-stage-3');
  if (st1) st1.style.display = 'block';
  if (st2) st2.style.display = 'none';
  if (st3) st3.style.display = 'none';
  if ($('forgot-modal-title')) $('forgot-modal-title').textContent = 'Reset Password';
  if ($('forgot-modal-sub')) $('forgot-modal-sub').textContent = 'Enter your registered Email or Student ID / LRN to receive a 6-digit confirmation code.';
  $('forgot-email-input')?.focus();
}

async function sendResetEmailCode(isResend = false) {
  let identifier = isResend ? _resetFlowState.identifier : $('forgot-email-input')?.value?.trim();
  if (!identifier) {
    showMsg('forgot-msg', 'Please enter your registered Email, LRN, or Employee ID.');
    $('forgot-email-input')?.focus();
    return;
  }

  const btn = isResend ? $('btnResendResetCode') : $('btnSendResetCode');
  setLoading(btn, true);
  hideEl('forgot-msg');

  try {
    const isNested = window.location.pathname.includes('/gnhs-guidance/gnhs-guidance/');
    const apiUrl = isNested ? '/gnhs-guidance/gnhs-guidance/api/forgot_password.php' : '/gnhs-guidance/api/forgot_password.php';

    const res = await apiPost(apiUrl, { identifier });
    setLoading(btn, false);

    if (res.success) {
      _resetFlowState.identifier = identifier;
      _resetFlowState.email = res.email;

      const st1 = $('forgot-stage-1');
      const st2 = $('forgot-stage-2');
      const st3 = $('forgot-stage-3');
      if (st1) st1.style.display = 'none';
      if (st2) st2.style.display = 'block';
      if (st3) st3.style.display = 'none';

      if ($('forgot-modal-title')) $('forgot-modal-title').textContent = 'Verify Confirmation Code';
      if ($('forgot-modal-sub')) $('forgot-modal-sub').textContent = 'Check your email inbox or spam folder for the 6-digit verification code.';
      if ($('forgot-target-email-label')) $('forgot-target-email-label').textContent = res.masked_email || res.email;

      let msg = res.message;
      if (res.code_hint) {
        msg += ' [Testing Code: ' + res.code_hint + ']';
        if ($('forgot-code-input')) $('forgot-code-input').value = res.code_hint;
      }
      showMsg('forgot-msg', msg, 'info');

      if (isResend) {
        showToast('A new 6-digit verification code has been sent!', 'success');
      }

      setTimeout(() => $('forgot-code-input')?.focus(), 80);
    } else {
      showMsg('forgot-msg', res.message || 'Unable to process reset request.');
    }
  } catch (e) {
    setLoading(btn, false);
    showMsg('forgot-msg', 'Network error. Please try again.');
  }
}

async function verifyResetEmailCode() {
  const code = $('forgot-code-input')?.value?.trim();
  if (!code || code.length !== 6) {
    showMsg('forgot-msg', 'Please enter the 6-digit verification code.');
    $('forgot-code-input')?.focus();
    return;
  }

  const btn = $('btnVerifyResetCode');
  setLoading(btn, true);
  hideEl('forgot-msg');

  try {
    const isNested = window.location.pathname.includes('/gnhs-guidance/gnhs-guidance/');
    const apiUrl = isNested ? '/gnhs-guidance/gnhs-guidance/api/verify_reset_code.php' : '/gnhs-guidance/api/verify_reset_code.php';

    const res = await apiPost(apiUrl, {
      email: _resetFlowState.email || _resetFlowState.identifier,
      code: code
    });
    setLoading(btn, false);

    if (res.success && res.reset_token) {
      _resetFlowState.resetToken = res.reset_token;
      if (res.email) _resetFlowState.email = res.email;

      const st1 = $('forgot-stage-1');
      const st2 = $('forgot-stage-2');
      const st3 = $('forgot-stage-3');
      if (st1) st1.style.display = 'none';
      if (st2) st2.style.display = 'none';
      if (st3) st3.style.display = 'block';

      if ($('forgot-modal-title')) $('forgot-modal-title').textContent = 'Set New Password';
      if ($('forgot-modal-sub')) $('forgot-modal-sub').textContent = 'Create a new secure password that meets all standards.';

      validateResetPasswordStrength();
      setTimeout(() => $('forgot-new-password')?.focus(), 80);
    } else {
      showMsg('forgot-msg', res.message || 'Invalid or expired confirmation code.');
    }
  } catch (e) {
    setLoading(btn, false);
    showMsg('forgot-msg', 'Network error. Please try again.');
  }
}

function validateResetPasswordStrength() {
  const pass = $('forgot-new-password')?.value || '';
  const confirm = $('forgot-confirm-password')?.value || '';

  const lenOk = pass.length >= 8;
  const upperOk = /[A-Z]/.test(pass);
  const numOk = /[0-9]/.test(pass);
  const specialOk = /[^A-Za-z0-9]/.test(pass);
  const matchOk = pass.length > 0 && pass === confirm;

  const updateItem = (id, ok, text) => {
    const el = $(id);
    if (!el) return;
    el.style.color = ok ? '#16a34a' : '#94a3b8';
    el.innerHTML = `<i class="fas ${ok ? 'fa-check-circle' : 'fa-circle-xmark'}"></i> ${text}`;
  };

  updateItem('pwReqLen', lenOk, 'At least 8 characters');
  updateItem('pwReqUpper', upperOk, '1 uppercase letter');
  updateItem('pwReqNum', numOk, '1 number (0-9)');
  updateItem('pwReqSpecial', specialOk, '1 special char (!@#$)');
  updateItem('pwReqMatch', matchOk, 'Passwords must match');

  return lenOk && upperOk && numOk && specialOk && matchOk;
}

async function submitNewPasswordReset() {
  if (!_resetFlowState.resetToken) {
    showMsg('forgot-msg', 'Session expired. Please restart the reset process.');
    openResetPasswordModal();
    return;
  }

  const pass = $('forgot-new-password')?.value || '';
  const confirm = $('forgot-confirm-password')?.value || '';

  if (!validateResetPasswordStrength()) {
    showMsg('forgot-msg', 'Please ensure all password standards are met and passwords match.');
    return;
  }

  const btn = $('btnSaveNewPassword');
  setLoading(btn, true);
  hideEl('forgot-msg');

  try {
    const isNested = window.location.pathname.includes('/gnhs-guidance/gnhs-guidance/');
    const apiUrl = isNested ? '/gnhs-guidance/gnhs-guidance/api/reset_password.php' : '/gnhs-guidance/api/reset_password.php';

    const res = await apiPost(apiUrl, {
      email: _resetFlowState.email,
      reset_token: _resetFlowState.resetToken,
      token: _resetFlowState.resetToken,
      password: pass,
      new_password: pass,
      confirm_password: confirm
    });
    setLoading(btn, false);

    if (res.success) {
      showToast('Password reset successfully! You can now log in.', 'success');
      showStep('step-login');
      const emailInput = $('login-email-input') || document.querySelector('#step-login input[type="text"]');
      if (emailInput && _resetFlowState.email) {
        emailInput.value = _resetFlowState.email;
      }
      const passInput = $('login-pass');
      if (passInput) {
        passInput.value = '';
        passInput.focus();
      }
    } else {
      showMsg('forgot-msg', res.message || 'Failed to update password.');
    }
  } catch (e) {
    setLoading(btn, false);
    showMsg('forgot-msg', 'Network error. Please try again.');
  }
}

function checkRegPassStrength() {
  const p = $('reg-pass')?.value || '';
  const rLen = $('reg-rule-len');
  const rUpper = $('reg-rule-upper');
  const rNum = $('reg-rule-num');
  const rSym = $('reg-rule-sym');
  const container = $('regPassRules');

  const lenOk = p.length >= 8;
  const upperOk = /[A-Z]/.test(p);
  const numOk = /[0-9]/.test(p);
  const symOk = /[^A-Za-z0-9]/.test(p);

  const updateRule = (el, valid, text) => {
    if (!el) return;
    if (valid) {
      el.style.color = '#047857';
      el.style.fontWeight = '700';
      el.style.background = 'rgba(16, 185, 129, 0.12)';
      el.style.padding = '4px 8px';
      el.style.borderRadius = '6px';
      el.style.border = '1px solid rgba(16, 185, 129, 0.3)';
      el.style.transition = 'all 0.25s ease';
      el.innerHTML = `<i class="fas fa-circle-check" style="font-size:12px;color:#10b981;filter:drop-shadow(0 0 5px rgba(16,185,129,0.85))"></i> ${text}`;
    } else {
      el.style.color = 'var(--text-3)';
      el.style.fontWeight = 'normal';
      el.style.background = 'transparent';
      el.style.padding = '3px 0';
      el.style.borderRadius = '0';
      el.style.border = 'none';
      el.innerHTML = `<i class="fas fa-circle-xmark" style="font-size:11px"></i> ${text}`;
    }
  };

  updateRule(rLen, lenOk, 'Minimum 8 characters');
  updateRule(rUpper, upperOk, 'At least 1 uppercase letter (Caps Lock)');
  updateRule(rNum, numOk, 'At least 1 number (0-9)');
  updateRule(rSym, symOk, 'At least 1 special character (e.g. !@#$%^&*)');

  const allPassed = lenOk && upperOk && numOk && symOk;
  let successEl = $('reg-rules-success');

  if (container) {
    if (allPassed) {
      container.style.borderColor = '#10b981';
      container.style.background = 'rgba(16, 185, 129, 0.08)';
      container.style.boxShadow = '0 0 14px rgba(16, 185, 129, 0.35)';
      container.style.transition = 'all 0.3s ease';
      if (!successEl) {
        successEl = document.createElement('div');
        successEl.id = 'reg-rules-success';
        successEl.style.cssText = 'margin-top:5px;padding:6px 10px;background:#10b981;color:#fff;border-radius:6px;font-weight:700;font-size:11px;display:flex;align-items:center;gap:6px;box-shadow:0 2px 8px rgba(16,185,129,0.4);animation:fadeIn 0.3s ease';
        successEl.innerHTML = '<i class="fas fa-shield-check" style="font-size:13px"></i> Password meets all security requirements!';
        container.appendChild(successEl);
      }
    } else {
      container.style.borderColor = 'var(--border)';
      container.style.background = 'rgba(0,0,0,0.03)';
      container.style.boxShadow = 'none';
      if (successEl) {
        successEl.remove();
      }
    }
  }
}

// ——— Register submit —————————————————————————————————————————————
async function handleRegister() {
  const firstName = $('reg-fname')?.value.trim();
  const middleName= $('reg-mname')?.value.trim();
  const lastName  = $('reg-lname')?.value.trim();
  const suffix    = $('reg-suffix')?.value.trim();
  const lrn       = $('reg-lrn-input')?.value.trim();
  const email     = $('reg-email')?.value.trim();
  const pass      = $('reg-pass')?.value.trim();
  const confirm   = $('reg-confirm')?.value.trim();

  // Build grade_section from two separate dropdowns
  const gradeLevel  = $('reg-grade-level')?.value;
  const sectionName = $('reg-section')?.value;
  const grade = (gradeLevel && sectionName) ? 'Grade ' + gradeLevel + ' - ' + sectionName : '';

  if (!firstName||!lastName||!lrn||!email||!pass||!confirm) {
    showMsg('reg-msg','Please fill in all required fields.'); return;
  }
  if (_role==='student') {
    if (!/^\d{12}$/.test(lrn)) {
      showMsg('reg-msg','Learner Reference Number (LRN) must be strictly 12 numeric digits.');
      return;
    }
  }
  if (_role==='student' && !grade) {
    showMsg('reg-msg','Please select your grade and section.'); return;
  }
  if (pass!==confirm) { showMsg('reg-msg','Passwords do not match.'); return; }
  if (pass.length<8)  { showMsg('reg-msg','Password must be at least 8 characters long.'); return; }
  if (!/[A-Z]/.test(pass)) { showMsg('reg-msg','Password must contain at least 1 uppercase letter (Caps Lock).'); return; }
  if (!/[0-9]/.test(pass)) { showMsg('reg-msg','Password must contain at least 1 number (0-9).'); return; }
  if (!/[^A-Za-z0-9]/.test(pass)) { showMsg('reg-msg','Password must contain at least 1 special character (e.g. !@#$%^&*).'); return; }

  const btn = document.querySelector('#step-register .btn-submit');
  setLoading(btn, true); hideEl('reg-msg');

  try {
    const res = await apiPost('/gnhs-guidance/api/register.php', {
      first_name:firstName, middle_name:middleName, last_name:lastName, suffix:suffix, id_number:lrn,
      grade_section:grade, email, password:pass, confirm, role:_role
    });
    if (res.success) {
      showMsg('reg-msg','Account created! You may now log in.','success');
      setTimeout(() => { _action='login'; selectRole(_role); }, 2000);
    } else { showMsg('reg-msg', res.message||'Registration failed.'); }
  } catch (err) {
    showMsg('reg-msg','Unable to complete registration. Please check your connection and try again.');
  }
  setLoading(btn, false);
}

// â”€â”€ Nav â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
function toggleMenu() {
  const nl = document.querySelector('.nav-links');
  nl?.classList.toggle('open');
}

// â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
// DASHBOARD â€” Shared UI
// â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
function toggleSidebar() { $('sidebar')?.classList.toggle('open'); }

function toggleNotif() {
  const p = $('notifPanel'); if (!p) return;
  p.classList.toggle('hidden');
  $('userDropdown')?.classList.add('hidden');
}
function toggleDropdown() {
  const d = $('userDropdown'); if (!d) return;
  d.classList.toggle('hidden');
  $('notifPanel')?.classList.add('hidden');
}

document.addEventListener('click', e => {
  if (!e.target.closest('#notifPanel')  && !e.target.closest('.notif-btn'))       $('notifPanel')?.classList.add('hidden');
  if (!e.target.closest('#userDropdown') && !e.target.closest('.user-avatar-wrap')) $('userDropdown')?.classList.add('hidden');
});

async function markAllRead() {
  try { await fetch('/gnhs-guidance/api/get_notifications.php?mark_read=1'); } catch {}
  $$('.np-item.unread').forEach(el => el.classList.remove('unread'));
  $$('.notif-dot').forEach(d => d.classList.add('hidden'));
  showToast('All notifications marked as read.','info');
}

// â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
// STUDENT â€” Submit concern
// â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
function updateCharCount() {
  const ta=$('concernText'); const num=$('charNum');
  if (ta&&num) { num.textContent=ta.value.length; }
}

async function submitConcern() {
  if (typeof window.submitStudentConcern === 'function') {
    return window.submitStudentConcern();
  }
  let concernType  = $('concernType')?.value;
  if (concernType === 'other') {
    const otherVal = $('otherConcern')?.value?.trim();
    if (!otherVal) { showMsg('submitMsg', 'Please specify your other concern.'); return; }
    concernType = 'Other: ' + otherVal;
  }
  const subject      = $('subject')?.value?.trim();
  const desc         = $('description')?.value?.trim();
  const isAnonymous  = $('isAnonymous')?.checked ? 1 : 0;
  const incidentDate = $('incidentDate')?.value;
  const contact      = $('preferredContact')?.value || 'in_person';
  const witnesses    = $('witnesses')?.value?.trim() || '';
  const priority     = document.querySelector('.prio-opt.selected')?.classList.contains('high-opt') ? 'high' :
                       document.querySelector('.prio-opt.selected')?.classList.contains('low-opt')  ? 'low' : 'medium';

  if (!concernType) { showMsg('submitMsg','Please select a concern type.'); return; }
  if (!subject)     { showMsg('submitMsg','Please enter a subject / title.'); return; }
  if (!desc)        { showMsg('submitMsg','Please describe your concern.'); return; }

  const today = new Date().toISOString().split('T')[0];
  if (incidentDate && incidentDate > today) {
    showMsg('submitMsg', 'Incident date cannot be in the future. Please select today (' + today + ') or an earlier date.');
    return;
  }

  if (witnesses) {
    if (witnesses.length > 100) {
      showMsg('submitMsg', 'Witnesses field cannot exceed 100 characters.');
      return;
    }
    const witWords = witnesses.trim().split(/\s+/).filter(w => w.length > 0).length;
    if (witWords > 15) {
      showMsg('submitMsg', 'Witnesses field cannot exceed 15 words.');
      return;
    }
  }

  const btn = $('submitBtn');
  setLoading(btn, true); hideEl('submitMsg');

  try {
    const formData = new FormData();
    formData.append('concern_type', concernType);
    formData.append('subject', subject);
    formData.append('description', desc);
    formData.append('priority', priority);
    formData.append('is_anonymous', isAnonymous ? '1' : '0');
    formData.append('incident_date', incidentDate || '');
    formData.append('preferred_contact', contact);
    if (witnesses) formData.append('witnesses', witnesses);

    const filesToUpload = Array.isArray(window.uploadedFiles) ? window.uploadedFiles : [];
    const inputEl = $('evidenceFiles');
    if (inputEl && inputEl.files && inputEl.files.length > 0) {
      for (let i = 0; i < inputEl.files.length; i++) {
        const f = inputEl.files[i];
        if (!filesToUpload.some(u => u.name === f.name && u.size === f.size)) {
          filesToUpload.push(f);
        }
      }
    }
    for (let i = 0; i < filesToUpload.length; i++) {
      formData.append('evidence[]', filesToUpload[i]);
    }

    const apiUrl = window.location.pathname.includes('/gnhs-guidance/gnhs-guidance/')
      ? '/gnhs-guidance/gnhs-guidance/api/submit_case.php'
      : '/gnhs-guidance/api/submit_case.php';

    const response = await fetch(apiUrl, { method: 'POST', body: formData });
    const res = await response.json();

    if (res.success) {
      const el = $('successCaseNum'); if (el) el.textContent = res.case_number;
      if (res.evidence_count > 0) {
        const siRow = $('siEvidenceRow'); if (siRow) siRow.style.display = 'flex';
        const siCount = $('siEvidenceCount'); if (siCount) siCount.textContent = res.evidence_count + ' file(s) attached';
      }
      if (res.has_witnesses) {
        const siWit = $('siWitnessRow'); if (siWit) siWit.style.display = 'flex';
      }
      $('successModal')?.classList.remove('hidden');
    } else {
      showMsg('submitMsg', res.message || 'Submission failed. Please try again.');
      setLoading(btn, false);
    }
  } catch {
    showMsg('submitMsg','Connection error. Please check your server.');
    setLoading(btn, false);
  }
}

function showSuccessModal(caseNum) {
  const el = $('newCaseNum'); if (el) el.textContent = '#' + caseNum;
  $('successModal')?.classList.remove('hidden');
}

// â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
// TEACHER â€” Submit referral
// Uses split grade (gradeLevel) + section (sectionName) dropdowns
// â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
async function submitReferral() {
  const studentName  = $('studentName')?.value.trim();
  const gradeLevel   = $('gradeLevel')?.value;
  const sectionName  = $('sectionName')?.value;
  const concernType  = $('concernType')?.value;
  const urgency      = $('urgency')?.value || 'moderate';
  const observations = $('observations')?.value.trim();

  if (!studentName)  { showMsg('refMsg','Please enter the student name.'); return; }
  if (!gradeLevel)   { showMsg('refMsg','Please select a grade.'); return; }
  if (!sectionName)  { showMsg('refMsg','Please select a section.'); return; }
  if (!concernType)  { showMsg('refMsg','Please select a concern type.'); return; }
  if (!observations) { showMsg('refMsg','Please describe your observations.'); return; }

  const gradeSection = 'Grade ' + gradeLevel + ' - ' + sectionName;

  const btn = $('submitBtn');
  setLoading(btn, true); hideEl('refMsg');

  try {
    const res = await apiPost('/gnhs-guidance/api/submit_referral.php', {
      student_name: studentName, grade_section: gradeSection,
      concern_type: concernType, urgency, observations
    });
    if (res.success) {
      const el = $('successRefNum'); if (el) el.textContent = res.ref_number;
      $('successModal')?.classList.remove('hidden');
    } else {
      showMsg('refMsg', res.message || 'Submission failed.');
      setLoading(btn, false);
    }
  } catch {
    showMsg('refMsg', 'Connection error. Please check your server.');
    setLoading(btn, false);
  }
}

// â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
// ADMIN â€” Case filter
// â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
function filterCases(status, btn) {
  $$('.filter-btn').forEach(b => b.classList.remove('active'));
  btn.classList.add('active');
  $$('.ct-row[data-status]').forEach(row => {
    row.style.display = (status==='all' || row.dataset.status===status) ? '' : 'none';
  });
}

// â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
// ADMIN â€” Case review modal
// â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
async function openCaseReview(caseId, caseNum, status, priority, subject, description) {
  _activeCaseId = caseId;
  $('caseReviewModal')?.classList.remove('hidden');
  const body = $('caseReviewBody') || $('reviewBody');
  const spinner = '<div style="text-align:center;padding:60px"><span class="spinner" style="border-color:rgba(85,0,0,.2);border-top-color:var(--maroon);width:36px;height:36px;border-width:4px;display:inline-block"></span></div>';
  if (body) body.innerHTML = spinner;

  try {
    const res = await fetch('/gnhs-guidance/api/get_case.php?id='+caseId);
    const d   = await res.json();
    if (d.success) buildReviewModal(d.case, d.notes, d.session);
    else {
      // Fallback to passed params if API fails
      buildReviewModal({
        case_number: caseNum||caseId, concern_type:'â€”', subject: subject||'â€”',
        description: description||'â€”', ai_summary: null,
        priority: priority||'medium', status: status||'pending',
        student_name:'â€”', grade_section:'â€”', submitted_at:'â€”'
      },[],null);
    }
  } catch {
    buildReviewModal({
      case_number: caseNum||caseId, concern_type:'—', subject: subject||'—',
      description: description||'(Connect to PHP server to view full details)',
      ai_summary: null, priority: priority||'medium', status: status||'pending',
      student_name:'—', grade_section:'—', submitted_at:'—'
    },[],null);
  }
}

function escapeHtml(str) {
  if (!str) return '';
  return String(str).replace(/[&<>"']/g, function(m) {
    return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m];
  });
}

function formatBytesJs(bytes) {
  if (!bytes) return '';
  const k = 1024, sizes = ['B', 'KB', 'MB', 'GB'];
  const i = Math.floor(Math.log(bytes) / Math.log(k));
  return parseFloat((bytes / Math.pow(k, i)).toFixed(1)) + ' ' + sizes[i];
}

function resolveEvidenceUrl(path) {
  if (!path) return '';
  if (path.startsWith('http://') || path.startsWith('https://') || path.startsWith('data:') || path.startsWith('blob:')) {
    return path;
  }
  const loc = window.location.pathname;
  let basePrefix = '/gnhs-guidance';
  if (loc.startsWith('/gnhs-guidance/gnhs-guidance/')) {
    basePrefix = '/gnhs-guidance/gnhs-guidance';
  }
  if (path.startsWith('/gnhs-guidance/uploads/')) {
    return basePrefix + path.substring('/gnhs-guidance'.length);
  }
  if (path.startsWith('uploads/')) {
    return basePrefix + '/' + path;
  }
  if (!path.startsWith('/')) {
    return basePrefix + '/' + path;
  }
  return path;
}

function renderEvidenceGallery(list) {
  if (!list || !list.length) return '';
  return list.map((item, idx) => {
    const isVideo = item.file_type === 'video' || (item.mime_type && item.mime_type.startsWith('video/'));
    const safeName = escapeHtml(item.name || ('Attachment ' + (idx + 1)));
    const safePath = escapeHtml(resolveEvidenceUrl(item.file_path));
    const sizeStr = item.size ? formatBytesJs(item.size) : '';

    if (isVideo) {
      return `
        <div class="evidence-gallery-card">
          <div class="egc-media-wrap">
            <video src="${safePath}" controls preload="metadata"></video>
            <button type="button" class="egc-play-expand" data-src="${safePath}" data-title="${safeName}" data-type="video" onclick="openEvidenceLightboxFromEl(this)" title="Play Full Video">
              <i class="fas fa-expand"></i>
            </button>
          </div>
          <div class="egc-body">
            <div class="egc-title" title="${safeName}"><i class="fas fa-video" style="color:#ef4444;margin-right:4px"></i>${safeName}</div>
            <div class="egc-footer">
              <span class="egc-size">${sizeStr}</span>
              <div class="egc-actions">
                <button type="button" class="egc-btn" data-src="${safePath}" data-title="${safeName}" data-type="video" onclick="openEvidenceLightboxFromEl(this)"><i class="fas fa-expand"></i> View</button>
                <a href="${safePath}" download="${safeName}" class="egc-btn" title="Download Video"><i class="fas fa-download"></i> Save</a>
              </div>
            </div>
          </div>
        </div>`;
    } else {
      return `
        <div class="evidence-gallery-card">
          <div class="egc-media-wrap" data-src="${safePath}" data-title="${safeName}" data-type="image" onclick="openEvidenceLightboxFromEl(this)" style="cursor:pointer">
            <img src="${safePath}" alt="${safeName}" loading="lazy"/>
            <div class="egc-zoom-overlay"><i class="fas fa-search-plus"></i></div>
          </div>
          <div class="egc-body">
            <div class="egc-title" title="${safeName}"><i class="fas fa-image" style="color:#3b82f6;margin-right:4px"></i>${safeName}</div>
            <div class="egc-footer">
              <span class="egc-size">${sizeStr}</span>
              <div class="egc-actions">
                <button type="button" class="egc-btn" data-src="${safePath}" data-title="${safeName}" data-type="image" onclick="openEvidenceLightboxFromEl(this)"><i class="fas fa-expand"></i> View</button>
                <a href="${safePath}" download="${safeName}" class="egc-btn" title="Download Image"><i class="fas fa-download"></i> Save</a>
              </div>
            </div>
          </div>
        </div>`;
    }
  }).join('');
}

function openEvidenceLightboxFromEl(el) {
  const src = el.getAttribute('data-src');
  const title = el.getAttribute('data-title') || 'Evidence View';
  const type = el.getAttribute('data-type') || 'image';
  openEvidenceLightbox(src, title, type);
}

function openEvidenceLightbox(src, title, type = 'image') {
  let modal = document.getElementById('evidenceLightboxModal');
  if (!modal) {
    modal = document.createElement('div');
    modal.id = 'evidenceLightboxModal';
    modal.className = 'evidence-lightbox-overlay';
    modal.onclick = function(e) {
      if (e.target === modal || e.target.classList.contains('evidence-lightbox-close')) {
        closeEvidenceLightbox();
      }
    };
    modal.innerHTML = `
      <div class="evidence-lightbox-content">
        <button type="button" class="evidence-lightbox-close" onclick="closeEvidenceLightbox()" title="Close Viewer">&times;</button>
        <div id="evidenceLightboxMediaWrap" style="max-width:88vw;max-height:80vh;display:flex;align-items:center;justify-content:center"></div>
        <div class="evidence-lightbox-bar">
          <span id="evidenceLightboxTitle" style="font-weight:700;font-size:13px"></span>
          <a id="evidenceLightboxDownload" href="" download="" class="egc-btn" style="background:#fff;color:var(--maroon);padding:5px 14px;border-radius:20px;font-weight:800"><i class="fas fa-download"></i> Download</a>
        </div>
      </div>`;
  }

  // Ensure lightbox is always appended to document.body so it is completely immune to any parent stacking contexts
  document.body.appendChild(modal);
  modal.style.zIndex = '999999';

  const wrap = document.getElementById('evidenceLightboxMediaWrap');
  if (type === 'video') {
    wrap.innerHTML = `<video id="evidenceLightboxPlayer" src="${src}" controls autoplay playsinline style="max-width:88vw;max-height:78vh;border-radius:8px;box-shadow:0 10px 40px rgba(0,0,0,0.8);background:#000"></video>`;
  } else {
    wrap.innerHTML = `<img id="evidenceLightboxImg" src="${src}" alt="${escapeHtml(title)}" style="max-width:88vw;max-height:78vh;object-fit:contain;border-radius:8px;box-shadow:0 10px 40px rgba(0,0,0,0.8)"/>`;
  }

  document.getElementById('evidenceLightboxTitle').textContent = title || 'Evidence View';
  const dlBtn = document.getElementById('evidenceLightboxDownload');
  if (dlBtn) {
    dlBtn.href = src;
    dlBtn.download = title || 'evidence_file';
  }
  modal.classList.remove('hidden');
  modal.style.display = 'flex';
}

function closeEvidenceLightbox() {
  const modal = document.getElementById('evidenceLightboxModal');
  if (modal) {
    const player = document.getElementById('evidenceLightboxPlayer');
    if (player) {
      try { player.pause(); player.src = ''; } catch(e){}
    }
    modal.classList.add('hidden');
    modal.style.display = 'none';
  }
}

// Global escape key handler for lightbox
if (!window._evidenceLightboxEscBound) {
  window._evidenceLightboxEscBound = true;
  document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
      const lb = document.getElementById('evidenceLightboxModal');
      if (lb && !lb.classList.contains('hidden') && lb.style.display !== 'none') {
        closeEvidenceLightbox();
      }
    }
  });
}

function triggerCaseEvidenceUpload(caseId, caseNum) {
  let input = document.getElementById('caseEvidenceQuickUploadInput');
  if (!input) {
    input = document.createElement('input');
    input.type = 'file';
    input.id = 'caseEvidenceQuickUploadInput';
    input.accept = 'image/*,video/*,.jpg,.jpeg,.png,.webp,.gif,.mp4,.mov,.webm';
    input.multiple = true;
    input.style.display = 'none';
    document.body.appendChild(input);
  }
  input.onchange = async function() {
    if (!this.files || !this.files.length) return;
    const fd = new FormData();
    fd.append('case_id', caseId);
    fd.append('case_number', caseNum);
    for (let i = 0; i < this.files.length; i++) {
      fd.append('evidence[]', this.files[i]);
    }
    const apiUrl = window.location.pathname.includes('/gnhs-guidance/gnhs-guidance/')
      ? '/gnhs-guidance/gnhs-guidance/api/upload_case_evidence.php'
      : '/gnhs-guidance/api/upload_case_evidence.php';
    try {
      const res = await fetch(apiUrl, { method: 'POST', body: fd });
      const data = await res.json();
      if (data.success) {
        if (typeof showToast === 'function') showToast(data.message || 'Evidence uploaded!');
        const reviewModal = document.getElementById('caseReviewModal');
        if (reviewModal && !reviewModal.classList.contains('hidden') && typeof openCaseReview === 'function') {
          openCaseReview(caseId, caseNum);
        } else if (typeof openCaseModal === 'function') {
          openCaseModal(caseId);
        }
      } else {
        alert(data.message || 'Upload failed');
      }
    } catch(err) {
      alert('Upload failed. Please check server connection.');
    }
    this.value = '';
  };
  input.click();
}

let _currentReviewCase = null;
let _currentReviewSession = null;
let _originalCaseStatus = 'pending';
let _selectedReviewProcess = 'Settled through Counseling Session';

function buildReviewModal(c, notes, session) {
  _currentReviewCase = c;
  _currentReviewSession = session;
  _originalCaseStatus = c.status || 'pending';
  _selectedReviewProcess = c.resolution_process || 'Settled through Counseling Session';

  const selected = p => p===c.priority ? ' selected' : '';
  const sel = s => s===c.status ? ' selected' : '';
  const body = $('caseReviewBody') || $('reviewBody');

  let evidenceList = [];
  if (c.evidence_files) {
    try {
      evidenceList = typeof c.evidence_files === 'string' ? JSON.parse(c.evidence_files) : c.evidence_files;
    } catch(e) { evidenceList = []; }
  }
  if (!Array.isArray(evidenceList)) evidenceList = [];

  const isPeer = (c.is_peer_report == 1 || c.is_peer_report === '1');
  const studentDisplay = isPeer
    ? `<div><span style="font-size:15px;font-weight:700;color:#9d174d">${escapeHtml(c.reported_student_name||'Target Student')}</span> <span style="font-size:11.5px;color:#be185d">(${escapeHtml(c.reported_student_grade||'Grade —')})</span><div style="font-size:11px;color:var(--text-3);margin-top:2px"><i class="fas fa-user-tag"></i> Rep by: ${escapeHtml(c.student_name||'Anonymous')}</div></div>`
    : `<span style="font-size:15px;font-weight:600">${escapeHtml(c.student_name||'Anonymous')}</span>`;

  const peerReportCard = isPeer ? `
    <div style="background:#fdf2f8;border:1.5px solid #fbcfe8;border-radius:8px;padding:12px 16px;margin-bottom:18px;display:flex;align-items:center;gap:12px">
      <div style="width:36px;height:36px;border-radius:50%;background:#f472b6;color:#fff;display:flex;align-items:center;justify-content:center;font-size:16px;flex-shrink:0"><i class="fas fa-hand-holding-heart"></i></div>
      <div style="flex:1">
        <div style="display:flex;align-items:center;gap:8px">
          <span style="font-size:11px;font-weight:700;background:#be185d;color:#fff;padding:2px 8px;border-radius:12px;text-transform:uppercase;letter-spacing:0.5px">Peer Report</span>
          <span style="font-size:12px;color:#9d174d;font-weight:600">Case submitted on behalf of another student</span>
        </div>
        <div style="margin-top:4px;font-size:13.5px;color:#831843">
          <strong>Affected Student:</strong> <span style="font-weight:700;color:#9d174d">${escapeHtml(c.reported_student_name || 'Not specified')}</span>
          ${c.reported_student_grade ? `<span style="color:#be185d;font-size:12px;margin-left:6px">(${escapeHtml(c.reported_student_grade)})</span>` : ''}
          <span style="margin:0 8px;color:#f472b6">•</span>
          <strong>Reported by (Referring Student):</strong> <span style="font-weight:600;color:#9d174d">${escapeHtml(c.student_name || 'Anonymous')}</span>
        </div>
      </div>
    </div>` : '';

  const followUpCard = (c.follow_up_msg) ? `
    <div style="background:#fdf4ff;border:1.5px solid #d8b4fe;border-radius:8px;padding:12px 16px;margin-bottom:18px;display:flex;align-items:flex-start;gap:12px">
      <div style="width:36px;height:36px;border-radius:50%;background:#a855f7;color:#fff;display:flex;align-items:center;justify-content:center;font-size:16px;flex-shrink:0"><i class="fas fa-reply-all"></i></div>
      <div style="flex:1">
        <div style="display:flex;align-items:center;gap:8px">
          <span style="font-size:11px;font-weight:700;background:#7e22ce;color:#fff;padding:2px 8px;border-radius:12px;text-transform:uppercase;letter-spacing:0.5px">Student Appeal / Follow-Up</span>
          <span style="font-size:12px;color:#7e22ce;font-weight:600">Case re-evaluated upon student appeal</span>
        </div>
        <div style="margin-top:6px;font-size:13.5px;color:#581c87;background:rgba(255,255,255,0.7);padding:10px 12px;border-radius:6px;border:1px solid #f3e8ff;line-height:1.5">
          <strong>Student's Explanation:</strong> "${escapeHtml(c.follow_up_msg)}"
        </div>
      </div>
    </div>` : '';

  body.innerHTML = `
  <div class="review-header-strip" style="display:flex;flex-wrap:wrap;gap:15px;background:var(--bg2);padding:15px;border-radius:8px;margin-bottom:20px;border:1px solid var(--border)">
      <div style="flex:1;min-width:120px"><label style="font-size:11px;color:var(--text-3);text-transform:uppercase;letter-spacing:1px;display:block;margin-bottom:4px">Case ID</label><strong style="color:var(--maroon);font-size:16px">#${escapeHtml(c.case_number)}</strong></div>
      <div style="flex:2;min-width:150px"><label style="font-size:11px;color:var(--text-3);text-transform:uppercase;letter-spacing:1px;display:block;margin-bottom:4px">Student</label>${studentDisplay}</div>
      <div style="flex:1.5;min-width:130px"><label style="font-size:11px;color:var(--text-3);text-transform:uppercase;letter-spacing:1px;display:block;margin-bottom:4px">Type</label><span class="status-badge status-ongoing">${escapeHtml((c.concern_type||'').replace(/_/g,' ').replace(/\b\w/g,l=>l.toUpperCase()))}</span></div>
      <div style="flex:1.5;min-width:130px"><label style="font-size:11px;color:var(--text-3);text-transform:uppercase;letter-spacing:1px;display:block;margin-bottom:4px">Grade/Sec</label><span style="font-size:14px">${escapeHtml(c.grade_section||'—')}</span></div>
      <div style="flex:1.5;min-width:130px"><label style="font-size:11px;color:var(--text-3);text-transform:uppercase;letter-spacing:1px;display:block;margin-bottom:4px">Submitted</label><span style="font-size:13px;color:var(--text-2)">${escapeHtml(c.submitted_at||'—')}</span></div>
    </div>
    ${peerReportCard}
    ${followUpCard}
    <div class="review-grid" style="grid-template-columns: 1.2fr 1fr; gap:25px; align-items:start">
    <div>
      ${c.ai_summary ? `<div class="ai-box" style="margin-bottom:20px;border-left:4px solid var(--blue);background:rgba(37,99,235,0.05)"><div class="ai-box-label" style="color:var(--blue);font-size:14px;margin-bottom:8px"><i class="fas fa-magic"></i> AI Summary</div><div class="ai-box-text" style="font-size:15px;line-height:1.6">${c.ai_summary}</div></div>` : ''}
      <div style="margin-bottom:14px;background:var(--bg2);padding:20px;border-radius:8px;border:1px solid var(--border)">
        <div style="font-size:13px;font-weight:800;color:var(--text-2);margin-bottom:12px;text-transform:uppercase;letter-spacing:1px"><i class="fas fa-quote-left" style="color:var(--maroon)"></i> Student's Description</div>
        <div class="concern-text" style="font-size:15px;line-height:1.7;color:var(--text);background:transparent;padding:0;border:none;white-space:pre-wrap">${escapeHtml(c.description)}</div>
      </div>

      ${c.witnesses ? `
      <div style="margin-bottom:14px;background:var(--bg2);padding:18px 20px;border-radius:8px;border:1px solid var(--border)">
        <div style="font-size:13px;font-weight:800;color:var(--text-2);margin-bottom:8px;text-transform:uppercase;letter-spacing:1px">
          <i class="fas fa-users" style="color:var(--maroon)"></i> Witnesses Mentioned
        </div>
        <div style="font-size:14px;line-height:1.6;color:var(--text);background:var(--bg);padding:12px 14px;border-radius:8px;border:1px solid var(--border);white-space:pre-wrap">
          ${escapeHtml(c.witnesses)}
        </div>
      </div>` : ''}

      <div style="margin-bottom:14px;background:var(--bg2);padding:18px 20px;border-radius:8px;border:1px solid var(--border)">
        <div style="font-size:13px;font-weight:800;color:var(--text-2);margin-bottom:12px;text-transform:uppercase;letter-spacing:1px;display:flex;align-items:center;justify-content:space-between">
          <span><i class="fas fa-photo-film" style="color:var(--maroon)"></i> Attached Student Evidence (${evidenceList.length})</span>
          <span style="font-size:11px;font-weight:700;color:var(--text-3);text-transform:none"><i class="fas fa-lock"></i> Student Submitted Media</span>
        </div>
        ${evidenceList.length > 0 
          ? `<div class="evidence-gallery-grid">${renderEvidenceGallery(evidenceList)}</div>`
          : `<div style="font-size:13px;color:var(--text-3);padding:10px 0"><i class="fas fa-info-circle"></i> No photo or video evidence was attached by the student.</div>`
        }
      </div>

    </div>
    <div style="background:var(--bg2);padding:20px;border-radius:8px;border:1px solid var(--border)">
      <h3 style="margin-bottom:20px;font-size:16px;color:var(--text);border-bottom:1px solid var(--border);padding-bottom:10px"><i class="fas fa-sliders-h" style="color:var(--maroon)"></i> Action Panel</h3>
      <div class="form-group">
        <label>Set Priority</label>
        <div class="prio-group">
          <div class="prio-opt high-opt${selected('high')}" onclick="selectPrio(this,'high')"><i class="fas fa-circle"></i>High</div>
          <div class="prio-opt med-opt${selected('medium')}"  onclick="selectPrio(this,'medium')"><i class="fas fa-circle"></i>Medium</div>
          <div class="prio-opt low-opt${selected('low')}"   onclick="selectPrio(this,'low')"><i class="fas fa-circle"></i>Low</div>
        </div>
      </div>
      <div class="form-group">
        <label>Update Status</label>
        <div class="select-wrap"><i class="fas fa-tag"></i>
          <select id="case-status-sel" onchange="onCaseStatusSelectChange(this)">
            <option value="pending"${sel('pending')}>Pending</option>
            <option value="under_review"${sel('under_review')}>Under Review</option>
            <option value="ongoing"${sel('ongoing')}>Ongoing</option>
            <option value="resolved"${sel('resolved')}>Resolved</option>
          </select>
        </div>
        ${c.status !== 'resolved' ? `
          <button type="button" class="btn-primary" style="background:#16a34a;margin-top:8px;width:100%;display:inline-flex;align-items:center;justify-content:center;gap:8px;font-size:12.5px;font-weight:700;padding:9px 12px;border-radius:8px;border:none;cursor:pointer;box-shadow:0 2px 8px rgba(22,163,74,0.25)" onclick="openCaseResolutionModal()">
            <i class="fas fa-check-circle"></i> Resolve Case & Set Process
          </button>` : `
          <button type="button" class="btn-sm-outline" style="margin-top:8px;width:100%;display:inline-flex;align-items:center;justify-content:center;gap:6px;font-size:12px;padding:7px 10px" onclick="openCaseResolutionModal()">
            <i class="fas fa-edit"></i> Edit Resolution & Sanctions
          </button>`
        }
      </div>

      ${session ? `
      <div style="background:#eff6ff;border:1px solid #bfdbfe;border-radius:10px;padding:14px;margin-bottom:16px">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:6px">
          <strong style="color:#1d4ed8;font-size:13px"><i class="fas fa-calendar-check"></i> Appointed Session</strong>
          <span class="status-badge ${session.status==='in_progress'?'status-resolved':(session.status==='completed'?'status-resolved':'status-under_review')}">${session.status.replace('_',' ')}</span>
        </div>
        <div style="font-size:12.5px;color:#1e40af;margin-bottom:10px;line-height:1.4">
          &bull; Date: ${session.session_date}<br>
          &bull; Location: ${escapeHtml(session.location || 'Guidance Office')} (${session.session_type || 'in-person'})
        </div>
        <button type="button" class="btn-sm-primary" style="width:100%;font-size:12px;justify-content:center" onclick="window.location.href='admin-sessions.php?q=' + encodeURIComponent('${c.case_number}')">
          <i class="fas fa-play"></i> Open Session Console
        </button>
      </div>` : ''}

      ${c.resolution ? `
      <div style="background:#f0fdf4;border:1.5px solid #86efac;border-radius:10px;padding:14px;margin-bottom:16px">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:6px;flex-wrap:wrap;gap:6px">
          <strong style="color:#15803d;font-size:13px;display:inline-flex;align-items:center;gap:6px"><i class="fas fa-check-circle"></i> Official Case Resolution</strong>
          ${c.resolution_process ? `<span style="font-size:10.5px;font-weight:700;background:#dcfce7;color:#166534;border:1px solid #86efac;padding:2px 8px;border-radius:12px"><i class="fas fa-hand-holding-heart"></i> ${escapeHtml(c.resolution_process)}</span>` : ''}
        </div>
        <div style="font-size:13px;color:#166534;line-height:1.45;white-space:pre-wrap">${escapeHtml(c.resolution)}</div>
        ${c.sanctions ? `
          <div style="margin-top:8px;padding-top:8px;border-top:1px dashed #86efac">
            <strong style="color:#991b1b;font-size:12px;display:block;margin-bottom:2px"><i class="fas fa-gavel"></i> School Rule Sanction / Action:</strong>
            <div style="font-size:12.5px;color:#7f1d1d;line-height:1.4;white-space:pre-wrap">${escapeHtml(c.sanctions)}</div>
          </div>` : ''}
        ${(c.resolution_process && c.resolution_process.toLowerCase().includes('counseling')) ? `
          <div style="margin-top:10px">
            <a href="admin-sessions-archive.php?q=${encodeURIComponent(c.case_number)}" class="btn-sm-outline" style="display:inline-flex;align-items:center;gap:5px;font-size:11px;padding:4px 9px;background:#fff;text-decoration:none">
              <i class="fas fa-archive"></i> View in Sessions Archive &rarr;
            </a>
          </div>` : ''}
      </div>` : ''}

      <div class="form-group">
        <label>Schedule Counseling Session <span style="font-size:11px;font-weight:normal;color:var(--text-3)">(Present or future date/time only)</span></label>
        <div class="input-wrap"><i class="fas fa-calendar"></i><input type="datetime-local" id="session-dt" min="${new Date(Date.now() - new Date().getTimezoneOffset()*60000).toISOString().slice(0, 16)}"></div>
      </div>
      <div class="form-group" style="margin-top:10px">
        <label>Session Type</label>
        <div class="select-wrap"><i class="fas fa-video"></i>
          <select id="session-type-sel">
            <option value="in-person">In-Person</option>
            <option value="online">Online</option>
          </select>
        </div>
      </div>
      <div class="form-group" style="margin-top:10px">
        <label>Location / Link</label>
        <div class="input-wrap"><i class="fas fa-map-marker-alt"></i><input type="text" id="session-location" value="Guidance Office" placeholder="Guidance Office or Zoom Link"></div>
      </div>
      <div class="form-group" style="margin-top:20px;border-top:1px solid var(--border);padding-top:20px">
        <button class="btn-primary" style="width:100%;background:var(--blue)" onclick="window.location.href=(window.location.pathname.startsWith('/gnhs-guidance')?'/gnhs-guidance/pages/':'/pages/')+(window.location.href.includes('admin')?'admin-chats.php':'student-chats.php')+'?case_id='+(c.id || _activeCaseId)">
          <i class="fas fa-comment-dots"></i> Message Student
        </button>
      </div>
      <div class="review-actions">
        <button class="btn-secondary" onclick="$('caseReviewModal').classList.add('hidden')">Cancel</button>
        <button class="btn-primary" id="saveCaseBtn" onclick="saveCase()"><i class="fas fa-save"></i> Save Status</button>
      </div>
    </div>
  </div>`;
}

function selectPrio(el, val) {
  $$('.prio-opt').forEach(o => o.classList.remove('selected'));
  el.classList.add('selected');
  el.dataset.prio = val;
}

async function saveCase() {
  const priority = document.querySelector('.prio-opt.selected')?.dataset.prio || document.querySelector('.prio-opt.selected')?.textContent.trim().toLowerCase().replace(/\s+/,'');
  const status   = $('case-status-sel')?.value;
  const session  = $('session-dt')?.value;
  const sessionType = $('session-type-sel')?.value || 'in-person';
  const location    = $('session-location')?.value || 'Guidance Office';

  if (status === 'resolved' && (!_currentReviewCase || !_currentReviewCase.resolution)) {
    openCaseResolutionModal();
    return;
  }

  if (session) {
    const selectedDate = new Date(session);
    const now = new Date();
    if (selectedDate < now) {
      showToast('Counseling session cannot be scheduled in the past. Please select a present or future date and time.', 'error');
      $('session-dt')?.focus();
      return;
    }
  }

  const btn = $('saveCaseBtn');
  setLoading(btn,true);

  try {
    const apiUrl = window.location.pathname.includes('/gnhs-guidance/gnhs-guidance/')
      ? '/gnhs-guidance/gnhs-guidance/api/update_case.php'
      : '/gnhs-guidance/api/update_case.php';
    const res = await apiPost(apiUrl, {
      case_id:_activeCaseId, priority, status, session_date:session, session_type:sessionType, location:location
    });
    if (res.success) {
      $('caseReviewModal').classList.add('hidden');
      showToast('Case updated successfully!','success');
      setTimeout(() => location.reload(), 1500);
    } else { showToast(res.message||'Update failed.','error'); setLoading(btn,false); }
  } catch {
    $('caseReviewModal').classList.add('hidden');
    showToast('Case updated! (demo mode)','success');
  }
}

function closeCaseReview(e) {
  if (!e || e.target.id==='caseReviewModal') $('caseReviewModal')?.classList.add('hidden');
}

// ── CASE RESOLUTION PROCESS & POPUP MODAL (REVIEW CASE INTEGRATION) ──
function ensureCaseResolutionModal() {
  let modal = document.getElementById('caseResolutionModal');
  if (modal) return modal;

  modal = document.createElement('div');
  modal.id = 'caseResolutionModal';
  modal.className = 'modal-overlay hidden';
  modal.style.zIndex = '10020';
  modal.onclick = function(e) {
    if (e.target === modal) closeCaseResolutionModal();
  };

  modal.innerHTML = `
    <div class="modal-box" style="max-width:720px;width:95%;padding:26px;max-height:92vh;overflow-y:auto;border-radius:14px">
      <button type="button" class="modal-close" onclick="closeCaseResolutionModal()"><i class="fas fa-times"></i></button>

      <div style="margin-bottom:18px;padding-bottom:12px;border-bottom:1px solid var(--border)">
        <div style="display:flex;align-items:center;gap:10px;margin-bottom:4px">
          <span id="crCaseBadge" style="background:var(--maroon-pale);color:var(--maroon);font-weight:800;font-size:13px;padding:3px 10px;border-radius:6px">#C-0000</span>
          <h2 style="font-size:19px;font-weight:800;color:var(--text);margin:0">
            <i class="fas fa-clipboard-check" style="color:var(--maroon);margin-right:6px"></i> Case Resolution & Settlement Process
          </h2>
        </div>
        <p style="font-size:13px;color:var(--text-2);margin:0">
          Please record the official process used to settle this case, provide the resolution summary, and specify any school rule sanctions.
        </p>
      </div>

      <!-- Step 1: Settlement Process Selection -->
      <div style="margin-bottom:18px">
        <label style="font-size:13.5px;font-weight:800;color:var(--text);display:flex;align-items:center;justify-content:space-between;margin-bottom:8px">
          <span>1. How was this case settled? <span style="color:#dc2626">*</span></span>
          <span style="font-size:11.5px;color:var(--text-3);font-weight:600">Select the primary guidance process</span>
        </label>

        <div class="decision-grid" style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:10px">
          
          <div class="decision-choice-card selected-yes" id="crChoiceCounseling" onclick="selectReviewProcess('Settled through Counseling Session')">
            <div style="display:flex;align-items:center;gap:8px;margin-bottom:4px">
              <i class="fas fa-comments" style="color:#15803d;font-size:16px"></i>
              <strong style="font-size:13.5px;color:var(--text)">Settled through Counseling Session</strong>
            </div>
            <p style="font-size:12px;color:var(--text-2);margin:0;line-height:1.4">Concluded through personal counseling dialogue, student guidance, and counseling sessions.</p>
            <div style="font-size:10.5px;color:#15803d;font-weight:700;margin-top:6px;display:flex;align-items:center;gap:4px">
              <i class="fas fa-archive"></i> Completes & moves session to Archive
            </div>
          </div>

          <div class="decision-choice-card" id="crChoiceParent" onclick="selectReviewProcess('Settled through Parent / Guardian Conference')">
            <div style="display:flex;align-items:center;gap:8px;margin-bottom:4px">
              <i class="fas fa-users" style="color:#2563eb;font-size:16px"></i>
              <strong style="font-size:13.5px;color:var(--text)">Parent / Guardian Conference</strong>
            </div>
            <p style="font-size:12px;color:var(--text-2);margin:0;line-height:1.4">Concluded through formal guidance conference with parents/guardians and mutual agreement.</p>
          </div>

          <div class="decision-choice-card" id="crChoicePeer" onclick="selectReviewProcess('Settled through Restorative Peer Mediation')">
            <div style="display:flex;align-items:center;gap:8px;margin-bottom:4px">
              <i class="fas fa-handshake" style="color:#d97706;font-size:16px"></i>
              <strong style="font-size:13.5px;color:var(--text)">Restorative Peer Mediation</strong>
            </div>
            <p style="font-size:12px;color:var(--text-2);margin:0;line-height:1.4">Concluded through restorative dialogue, mutual understanding, and student reconciliation pledge.</p>
          </div>

          <div class="decision-choice-card" id="crChoiceContract" onclick="selectReviewProcess('Settled through Behavioral Agreement & Monitoring')">
            <div style="display:flex;align-items:center;gap:8px;margin-bottom:4px">
              <i class="fas fa-file-contract" style="color:#7c3aed;font-size:16px"></i>
              <strong style="font-size:13.5px;color:var(--text)">Behavioral Agreement / Contract</strong>
            </div>
            <p style="font-size:12px;color:var(--text-2);margin:0;line-height:1.4">Concluded with signed behavioral contract, conduct commitments, and regular follow-up.</p>
          </div>

          <div class="decision-choice-card" id="crChoiceDirect" onclick="selectReviewProcess('Settled through Direct Guidance Intervention')">
            <div style="display:flex;align-items:center;gap:8px;margin-bottom:4px">
              <i class="fas fa-user-shield" style="color:#b91c1c;font-size:16px"></i>
              <strong style="font-size:13.5px;color:var(--text)">Direct Guidance Intervention</strong>
            </div>
            <p style="font-size:12px;color:var(--text-2);margin:0;line-height:1.4">Concluded via counselor direct intervention, guidance consultation, and student understanding.</p>
          </div>

          <div class="decision-choice-card" id="crChoiceOther" onclick="selectReviewProcess('Other Guidance Process')">
            <div style="display:flex;align-items:center;gap:8px;margin-bottom:4px">
              <i class="fas fa-pen-to-square" style="color:#475569;font-size:16px"></i>
              <strong style="font-size:13.5px;color:var(--text)">Custom Settlement Process</strong>
            </div>
            <p style="font-size:12px;color:var(--text-2);margin:0;line-height:1.4">Specify custom guidance remediation, faculty coordination, or administrative settlement.</p>
          </div>

        </div>

        <div id="crCustomProcessWrap" class="hidden" style="margin-top:10px;display:none">
          <input type="text" id="crCustomProcessInput" class="form-control" style="width:100%;padding:9px 12px;border-radius:8px;border:1.5px solid var(--border);font-size:13px" placeholder="Type custom guidance resolution process or method...">
        </div>
      </div>

      <!-- Active Counseling Sessions Handler / Notification -->
      <div id="crSessionsArchiveNotice" style="background:#f0fdf4;border:1px solid #bbf7d0;border-radius:10px;padding:12px 14px;margin-bottom:18px;display:flex;align-items:center;justify-content:space-between;gap:12px">
        <div style="display:flex;align-items:center;gap:10px">
          <i class="fas fa-archive" style="color:#15803d;font-size:18px"></i>
          <div>
            <strong style="color:#15803d;font-size:13px;display:block">Sessions Archive Integration</strong>
            <span style="font-size:12px;color:#166534">Associated counseling session(s) will be concluded and placed in the Sessions Archive.</span>
          </div>
        </div>
        <label style="font-size:12px;font-weight:700;color:#166534;display:flex;align-items:center;gap:6px;cursor:pointer;white-space:nowrap">
          <input type="checkbox" id="crCompleteSessionsChk" checked> Conclude & Archive
        </label>
      </div>

      <!-- Step 2: Resolution Summary -->
      <div style="margin-bottom:18px">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:6px;flex-wrap:wrap;gap:8px">
          <label style="font-size:13.5px;font-weight:800;color:var(--text);margin:0">
            2. Resolution Summary <span style="color:#dc2626">*</span>
          </label>
          <button type="button" class="btn-sm-primary" id="btnAiSuggestCaseResolution" onclick="aiSuggestCaseResolution()" style="background:linear-gradient(135deg,#7c3aed,#4f46e5);border:none;display:inline-flex;align-items:center;gap:6px;font-size:12px;padding:5px 13px;border-radius:20px;font-weight:700;box-shadow:0 2px 8px rgba(124,58,237,0.3);color:#fff;cursor:pointer">
            <i class="fas fa-wand-magic-sparkles"></i> AI Suggest Resolution
          </button>
        </div>
        <div id="crAiResolutionNotice" style="display:none;background:#f5f3ff;border:1px solid #ddd6fe;border-radius:8px;padding:8px 12px;margin-bottom:8px;font-size:12px;color:#5b21b6">
          <i class="fas fa-sparkles"></i> <strong>AI Recommendation:</strong> Synthesized from interview observations and counselor notes.
        </div>
        <textarea id="crResolutionSummary" rows="3" class="form-control" style="width:100%;padding:12px;border-radius:8px;border:1.5px solid var(--border);font-size:13.5px;line-height:1.5;background:var(--bg)" placeholder="Detail how the case was resolved (e.g. mutual agreement reached, guidance counseling concluded, restorative conference completed, student commitments established)..."></textarea>
      </div>

      <!-- Step 3: Guidance Recommendations for Student -->
      <div style="margin-bottom:18px">
        <label style="font-size:13.5px;font-weight:800;color:var(--text);display:flex;justify-content:space-between;align-items:center;margin-bottom:4px">
          <span>3. Guidance Recommendations for Student <span style="font-size:11px;color:var(--text-3);font-weight:600">(Visible to Student)</span></span>
        </label>
        <textarea id="crGuidanceRecommendations" rows="2" class="form-control" style="width:100%;padding:10px;border-radius:8px;border:1.5px solid var(--border);font-size:13px;line-height:1.4;background:var(--bg)" placeholder="Official guidance recommendations, coping advice, or next steps that the student can view..."></textarea>
      </div>

      <!-- Step 4: School Rule Sanctions -->
      <div style="margin-bottom:20px">
        <label style="font-size:13.5px;font-weight:800;color:var(--text);display:flex;justify-content:space-between;align-items:center;margin-bottom:4px">
          <span>4. Regulated School Rule Sanction / Disciplinary Action</span>
          <span style="font-size:11px;color:var(--text-3);font-weight:600">(if regulated school rules are affected)</span>
        </label>
        <p style="font-size:12px;color:var(--text-3);margin:0 0 8px 0">Click common school rule measures below or type custom sanctions according to GNHS student handbook:</p>
        
        <div class="rule-chip-container">
          <span class="rule-chip" onclick="applyCaseReviewRuleChip('Parent / Guardian Conference Required')"><i class="fas fa-plus"></i> Parent Conference</span>
          <span class="rule-chip" onclick="applyCaseReviewRuleChip('Restorative Campus / Community Service')"><i class="fas fa-plus"></i> Restorative Service</span>
          <span class="rule-chip" onclick="applyCaseReviewRuleChip('Written Apology & Good Conduct Pledge')"><i class="fas fa-plus"></i> Written Apology</span>
          <span class="rule-chip" onclick="applyCaseReviewRuleChip('Behavioral Monitoring Agreement (2 Weeks)')"><i class="fas fa-plus"></i> Behavioral Agreement</span>
          <span class="rule-chip" onclick="applyCaseReviewRuleChip('Official Guidance Warning & Reprimand')"><i class="fas fa-plus"></i> Guidance Warning</span>
          <span class="rule-chip" onclick="applyCaseReviewRuleChip('No Sanctions (Resolved through Guidance Counseling Only)')"><i class="fas fa-check"></i> No Sanctions / Counseling Only</span>
        </div>

        <textarea id="crSanctionsText" rows="2" class="form-control" style="width:100%;padding:10px;border-radius:8px;border:1.5px solid var(--border);font-size:13px;line-height:1.4;background:var(--bg)" placeholder="Specify any regulated school rules sanctions or corrective actions imposed..."></textarea>
      </div>

      <!-- Footer Actions -->
      <div style="display:flex;justify-content:flex-end;gap:12px;padding-top:14px;border-top:1px solid var(--border)">
        <button type="button" class="btn-secondary" onclick="closeCaseResolutionModal()">Cancel / Keep Active</button>
        <button type="button" class="btn-primary" id="btnSubmitCaseResolution" style="background:#16a34a;display:inline-flex;align-items:center;gap:6px;font-weight:800;padding:10px 20px" onclick="submitCaseResolution()">
          <i class="fas fa-check-circle"></i> Finalize, Apply Sanction & Resolve Case
        </button>
      </div>

    </div>
  `;

  document.body.appendChild(modal);
  return modal;
}

function onCaseStatusSelectChange(sel) {
  if (sel.value === 'resolved') {
    openCaseResolutionModal();
  }
}

function openCaseResolutionModal() {
  const modal = ensureCaseResolutionModal();
  const c = _currentReviewCase || {};
  const s = _currentReviewSession;

  $('crCaseBadge').textContent = '#' + (c.case_number || _activeCaseId || 'CASE');
  $('crResolutionSummary').value = c.resolution || '';
  $('crGuidanceRecommendations').value = c.guidance_recommendations || '';
  $('crSanctionsText').value = c.sanctions || '';

  // Pre-select process
  const proc = c.resolution_process || 'Settled through Counseling Session';
  selectReviewProcess(proc);

  const sessionNotice = $('crSessionsArchiveNotice');
  if (sessionNotice) {
    sessionNotice.style.display = 'flex';
  }

  const btn = $('btnSubmitCaseResolution');
  if (btn) {
    btn.disabled = false;
    btn.innerHTML = '<i class="fas fa-check-circle"></i> Finalize, Apply Sanction & Resolve Case';
  }

  modal.classList.remove('hidden');
}

function selectReviewProcess(processName) {
  _selectedReviewProcess = processName;
  const cards = {
    'Settled through Counseling Session': 'crChoiceCounseling',
    'Settled through Parent / Guardian Conference': 'crChoiceParent',
    'Settled through Restorative Peer Mediation': 'crChoicePeer',
    'Settled through Behavioral Agreement & Monitoring': 'crChoiceContract',
    'Settled through Direct Guidance Intervention': 'crChoiceDirect',
    'Other Guidance Process': 'crChoiceOther'
  };

  Object.values(cards).forEach(id => {
    $(id)?.classList.remove('selected-yes');
  });

  const targetId = cards[processName] || 'crChoiceOther';
  $(targetId)?.classList.add('selected-yes');

  const customWrap = $('crCustomProcessWrap');
  if (processName === 'Other Guidance Process' || (!cards[processName] && processName)) {
    if (customWrap) {
      customWrap.classList.remove('hidden');
      customWrap.style.display = 'block';
      if (processName !== 'Other Guidance Process') $('crCustomProcessInput').value = processName;
      $('crCustomProcessInput')?.focus();
    }
  } else {
    if (customWrap) {
      customWrap.classList.add('hidden');
      customWrap.style.display = 'none';
    }
  }
}

function applyCaseReviewRuleChip(chipText) {
  const textarea = $('crSanctionsText');
  if (!textarea) return;
  if (!textarea.value.trim()) {
    textarea.value = chipText;
  } else {
    if (!textarea.value.includes(chipText)) {
      textarea.value = textarea.value.trim() + '; ' + chipText;
    }
  }
}

function closeCaseResolutionModal() {
  const modal = $('caseResolutionModal');
  if (modal) modal.classList.add('hidden');
  const sel = $('case-status-sel');
  if (sel && _currentReviewCase && _currentReviewCase.status !== 'resolved') {
    sel.value = _originalCaseStatus || 'under_review';
  }
}

async function submitCaseResolution() {
  let proc = _selectedReviewProcess;
  if (proc === 'Other Guidance Process') {
    proc = $('crCustomProcessInput')?.value.trim() || 'Custom Guidance Settlement';
  }

  const summary = $('crResolutionSummary')?.value.trim();
  if (!summary) {
    showToast('Please provide the resolution summary for this case.', 'error');
    $('crResolutionSummary')?.focus();
    return;
  }

  const sanctions = $('crSanctionsText')?.value.trim() || '';
  const guidanceRecommendations = $('crGuidanceRecommendations')?.value.trim() || '';
  const completeSessions = $('crCompleteSessionsChk')?.checked ?? true;
  const priority = document.querySelector('.prio-opt.selected')?.dataset.prio || (_currentReviewCase?.priority || 'medium');

  const btn = $('btnSubmitCaseResolution');
  if (btn) {
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner"></span> Processing Resolution & Archiving…';
  }

  try {
    const apiUrl = window.location.pathname.includes('/gnhs-guidance/gnhs-guidance/')
      ? '/gnhs-guidance/gnhs-guidance/api/update_case.php'
      : '/gnhs-guidance/api/update_case.php';

    const res = await apiPost(apiUrl, {
      case_id: _activeCaseId,
      status: 'resolved',
      priority: priority,
      resolution: summary,
      guidance_recommendations: guidanceRecommendations,
      resolution_process: proc,
      sanctions: sanctions,
      complete_sessions: completeSessions
    });

    if (res.success) {
      closeCaseResolutionModal();
      $('caseReviewModal')?.classList.add('hidden');
      showToast('Case officially resolved and sessions archived!', 'success');
      setTimeout(() => location.reload(), 1400);
    } else {
      showToast(res.message || 'Failed to resolve case.', 'error');
      if (btn) {
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-check-circle"></i> Finalize, Apply Sanction & Resolve Case';
      }
    }
  } catch (err) {
    showToast('Case resolved and archived.', 'success');
    closeCaseResolutionModal();
    $('caseReviewModal')?.classList.add('hidden');
    setTimeout(() => location.reload(), 1400);
  }
}

async function aiSuggestCaseResolution() {
  const c = _currentReviewCase || {};
  const s = _currentReviewSession;
  
  let notes = (s && s.notes ? s.notes : '').trim();
  if (!notes && c.notes && Array.isArray(c.notes)) {
    notes = c.notes.map(n => n.content).join('\n');
  }

  if (!notes || notes.length < 8) {
    showToast('Please input your counselor case notes and student behavioral observations from the interview first. The AI requires your session observations to formulate a relevant resolution.', 'error');
    return;
  }

  const btn = $('btnAiSuggestCaseResolution');
  const origHtml = btn ? btn.innerHTML : '';
  if (btn) {
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Analyzing Notes & Observations…';
  }

  try {
    const apiUrl = window.location.pathname.includes('/gnhs-guidance/gnhs-guidance/')
      ? '/gnhs-guidance/gnhs-guidance/api/ai_suggest_resolution.php'
      : '/gnhs-guidance/api/ai_suggest_resolution.php';

    const res = await apiPost(apiUrl, {
      case_id: _activeCaseId || c.id || 0,
      session_id: s ? s.id : 0,
      notes: notes
    });

    if (res.success) {
      if ($('crResolutionSummary')) $('crResolutionSummary').value = res.suggested_resolution;
      if ($('crSanctionsText') && res.suggested_sanctions) $('crSanctionsText').value = res.suggested_sanctions;
      if (res.suggested_process) selectReviewProcess(res.suggested_process);

      const notice = $('crAiResolutionNotice');
      if (notice) notice.style.display = 'block';

      showToast('AI synthesized resolution, process, and sanctions from counselor observations!', 'success');
    } else {
      showToast(res.message || 'Could not generate AI resolution.', 'error');
    }
  } catch (err) {
    showToast('Failed to connect to AI resolution engine.', 'error');
  } finally {
    if (btn) {
      btn.disabled = false;
      btn.innerHTML = origHtml;
    }
  }
}


// â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
// STUDENT â€” View case modal
// â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
async function openCaseModal(caseId) {
  $('caseModal')?.classList.remove('hidden');
  const body = $('caseModalBody');
  if (body) body.innerHTML='<div style="text-align:center;padding:40px"><span class="spinner" style="border-color:rgba(85,0,0,.2);border-top-color:var(--maroon);display:inline-block"></span></div>';

  try {
    const res = await fetch('/gnhs-guidance/api/get_case.php?id='+caseId);
    const d   = await res.json();
    if (d.success) {
      const c=d.case, notes=d.notes||[], session=d.session;

      let evidenceList = [];
      if (c.evidence_files) {
        try {
          evidenceList = typeof c.evidence_files === 'string' ? JSON.parse(c.evidence_files) : c.evidence_files;
        } catch(e) { evidenceList = []; }
      }
      if (!Array.isArray(evidenceList)) evidenceList = [];

      body.innerHTML=`
        <div class="case-detail">
          <div style="display:grid;grid-template-columns:repeat(2,1fr);gap:12px;margin-bottom:14px">
            <div><label style="font-size:12px;font-weight:800;color:var(--text-3);display:block">CASE ID</label><strong style="color:var(--maroon)">#${c.case_number}</strong></div>
            <div><label style="font-size:12px;font-weight:800;color:var(--text-3);display:block">SUBMITTED</label><span>${c.submitted_at}</span></div>
            <div><label style="font-size:12px;font-weight:800;color:var(--text-3);display:block">STATUS</label>${buildBadge(c.status)}</div>
            <div><label style="font-size:12px;font-weight:800;color:var(--text-3);display:block">PRIORITY</label>${buildPrioBadge(c.priority)}</div>
          </div>

          ${(c.is_peer_report == 1 || c.is_peer_report === '1') ? `
            <div style="background:#fdf2f8;border:1.5px solid #fbcfe8;border-radius:8px;padding:12px 14px;margin-bottom:14px;display:flex;align-items:center;gap:10px">
              <div style="width:32px;height:32px;border-radius:50%;background:#f472b6;color:#fff;display:flex;align-items:center;justify-content:center;font-size:14px;flex-shrink:0"><i class="fas fa-hand-holding-heart"></i></div>
              <div style="font-size:13px;color:#9d174d">
                <strong style="display:block">Reported on behalf of: ${escapeHtml(c.reported_student_name || 'Classmate')} ${c.reported_student_grade ? '(' + escapeHtml(c.reported_student_grade) + ')' : ''}</strong>
                <span style="font-size:11.5px;color:#be185d">You submitted this concern to help another student receive guidance office support.</span>
              </div>
            </div>` : ''}

          <div style="margin-bottom:14px"><label style="font-size:13px;font-weight:800;color:var(--text-2);display:block;margin-bottom:8px">Your Description</label><div class="concern-text">${c.description}</div></div>

          ${c.witnesses ? `
            <div style="margin-bottom:14px;background:var(--bg2);padding:14px;border-radius:8px;border:1px solid var(--border)">
              <label style="font-size:12px;font-weight:800;color:var(--text-2);display:block;margin-bottom:6px"><i class="fas fa-users" style="color:var(--maroon)"></i> Witnesses Provided</label>
              <div style="font-size:14px;color:var(--text);white-space:pre-wrap">${escapeHtml(c.witnesses)}</div>
            </div>` : ''}

          <div style="margin-bottom:14px;background:var(--bg2);padding:14px;border-radius:8px;border:1px solid var(--border)">
            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:10px">
              <label style="font-size:12px;font-weight:800;color:var(--text-2);margin-bottom:0"><i class="fas fa-photo-film" style="color:var(--maroon)"></i> Attached Evidence (${evidenceList.length})</label>
              <button type="button" class="btn-sm-outline" onclick="triggerCaseEvidenceUpload(${c.id},'${escapeHtml(c.case_number)}')" style="font-size:11px;padding:2px 8px"><i class="fas fa-plus"></i> Add File</button>
            </div>
            ${evidenceList.length 
              ? `<div class="evidence-gallery-grid">${renderEvidenceGallery(evidenceList)}</div>`
              : `<div style="font-size:12px;color:var(--text-3);padding:6px 0"><i class="fas fa-info-circle"></i> No photo or video evidence attached. <button type="button" onclick="triggerCaseEvidenceUpload(${c.id},'${escapeHtml(c.case_number)}')" style="color:var(--maroon);background:none;border:none;cursor:pointer;font-weight:700;text-decoration:underline">Attach media</button></div>`
            }
          </div>

          ${c.ai_summary?`<div class="ai-box" style="margin-bottom:14px"><div class="ai-box-label"><i class="fas fa-robot"></i>AI Summary</div><div class="ai-box-text">${c.ai_summary}</div></div>`:''}

          ${c.resolution ? `
            <div style="margin-bottom:14px;background:#f0fdf4;border:1.5px solid #bbf7d0;border-radius:10px;padding:16px">
              <div style="font-weight:800;color:#166534;font-size:13.5px;margin-bottom:6px"><i class="fas fa-check-circle"></i> Official Case Resolution</div>
              <div style="font-size:14px;color:#14532d;line-height:1.6;white-space:pre-wrap">${escapeHtml(c.resolution)}</div>
              ${c.sanctions ? `
                <div style="margin-top:10px;padding-top:10px;border-top:1px dashed #86efac">
                  <div style="font-weight:800;color:#991b1b;font-size:12px;margin-bottom:4px"><i class="fas fa-gavel"></i> School Rule Sanction / Action:</div>
                  <div style="font-size:13px;color:#7f1d1d;line-height:1.5;white-space:pre-wrap">${escapeHtml(c.sanctions)}</div>
                </div>` : ''}
            </div>` : ''}

          <div style="margin-top:14px">
            <button class="btn-primary" style="width:100%;background:var(--blue)" onclick="window.location.href=(window.location.pathname.startsWith('/gnhs-guidance')?'/gnhs-guidance/pages/':'/pages/')+(window.location.href.includes('admin')?'admin-chats.php':'student-chats.php')+'?case_id='+(c.id || _activeCaseId)">
              <i class="fas fa-comment-dots"></i> Message Counselor
            </button>
          </div>
          ${session?`
            <div style="margin-top:14px;padding:14px;background:${session.status==='in_progress'?'#dcfce7':'var(--maroon-pale)'};border-radius:10px;border:1px solid ${session.status==='in_progress'?'#86efac':'var(--maroon-tint)'}">
              <div style="font-weight:800;color:${session.status==='in_progress'?'#15803d':'var(--maroon)'};margin-bottom:4px;display:flex;align-items:center;gap:6px">
                <i class="fas ${session.status==='in_progress'?'fa-headset':'fa-calendar-check'}"></i>
                ${session.status==='in_progress'?'Counseling Session In Progress':'Counseling Session Scheduled'}
              </div>
              <div style="font-size:13px;color:var(--text-2)">
                &bull; Date: ${session.session_date}<br>
                &bull; Location: ${escapeHtml(session.location||'Guidance Office')} (${session.session_type||'in-person'})
              </div>
            </div>`:''}
        </div>`;
    }
  } catch {
    if (body) body.innerHTML='<p style="text-align:center;color:var(--text-3);padding:24px">Case detail view works when connected to the PHP server.</p>';
  }
}

function buildBadge(s) {
  const m={pending:['Pending','status-pending'],under_review:['Under Review','status-under_review'],ongoing:['Ongoing','status-ongoing'],resolved:['Resolved','status-resolved']};
  const [l,c]=m[s]||[s,'status-pending'];
  return `<span class="status-badge ${c}">${l}</span>`;
}
function buildPrioBadge(p) {
  const m={high:['High','priority-high'],medium:['Medium','priority-medium'],low:['Low','priority-low']};
  const [l,c]=m[p]||[p,'priority-low'];
  return `<span class="priority-badge ${c}"><i class="fas fa-circle"></i>${l}</span>`;
}

// ══════════════════════════════════════════════════════════════════════
// USER MANAGEMENT (admin)
// ══════════════════════════════════════════════════════════════════════
function openAddUserModal() {
  const m = $('addUserModal');
  if (m) {
    if (m.parentElement !== document.body) document.body.appendChild(m);
    m.classList.remove('hidden');
    m.style.display = 'flex';
    document.body.style.overflow = 'hidden';
    const box = m.querySelector('.modal-box');
    if (box) box.scrollTop = 0;
  }
}
function closeAddUser(e) {
  if (!e || e.target.id === 'addUserModal' || e.target.closest('.modal-close') || (e.target.tagName === 'BUTTON' && e.target.innerText.trim() === 'Cancel')) {
    const m = $('addUserModal');
    if (m) {
      m.classList.add('hidden');
      m.style.display = 'none';
      document.body.style.overflow = '';
    }
  }
}

async function saveNewUser() {
  const first  = $('nu-first')?.value.trim();
  const last   = $('nu-last')?.value.trim();
  const email  = $('nu-email')?.value.trim();
  const pass   = $('nu-pass')?.value.trim();
  const role   = $('nu-role')?.value;
  const idnum  = $('nu-idnum')?.value.trim();
  const glevel = $('nu-grade-level')?.value;
  const gsect  = $('nu-section')?.value;
  const grade  = (glevel && gsect) ? 'Grade ' + glevel + ' - ' + gsect : '';

  if(!first||!last||!email||!pass||!role||!idnum){showMsg('nuMsg','Please fill all required fields.');return;}
  if(role==='student') {
    if (!/^\d{12}$/.test(idnum)) {
      showMsg('nuMsg', 'Student LRN must be strictly 12 numeric digits.');
      return;
    }
  }
  if(role==='student' && !grade){showMsg('nuMsg','Please select a grade and section for the student.');return;}
  if (pass.length<8) { showMsg('nuMsg','Password must be at least 8 characters long.'); return; }
  if (!/[A-Z]/.test(pass)) { showMsg('nuMsg','Password must contain at least 1 uppercase letter (Caps Lock).'); return; }
  if (!/[0-9]/.test(pass)) { showMsg('nuMsg','Password must contain at least 1 number (0-9).'); return; }
  if (!/[^A-Za-z0-9]/.test(pass)) { showMsg('nuMsg','Password must contain at least 1 special character (e.g. !@#$%^&*).'); return; }

  const btn=$('saveUserBtn'); setLoading(btn,true); hideEl('nuMsg');
  try{
    const res=await apiPost('/gnhs-guidance/api/register.php',{first_name:first,last_name:last,email,password:pass,confirm:pass,role,id_number:idnum,grade_section:grade});
    if(res.success){showToast('User added!','success');$('addUserModal').classList.add('hidden');setTimeout(()=>location.reload(),1200);}
    else{showMsg('nuMsg',res.message||'Failed.');}
  }catch{showMsg('nuMsg','Network or server error. Please try again.');}
  setLoading(btn,false);
}

async function deleteUser(userId, name, email) {
  if (typeof window.openDeactivateConfirmModal === 'function') {
    window.openDeactivateConfirmModal(userId, name, email);
    return;
  }
  const modal = document.getElementById('deactivateConfirmModal');
  if (modal) {
    _targetDeactivateUserId = userId;
    const nameEl = document.getElementById('deactivateUserName');
    const emailEl = document.getElementById('deactivateUserEmail');
    const descEl = document.getElementById('deactivateConfirmText');
    const avatarEl = document.getElementById('deactivateUserAvatar');
    if (nameEl) nameEl.textContent = name;
    if (emailEl) emailEl.textContent = email || '';
    if (descEl) descEl.innerHTML = `Are you sure you want to deactivate the account of <strong>${name}</strong>? They will be barred from signing into the system until reactivated.`;
    if (avatarEl) avatarEl.textContent = name ? name[0].toUpperCase() : 'U';
    modal.classList.remove('hidden');
    modal.style.display = 'flex';
    return;
  }
  try{
    const r=await fetch('/gnhs-guidance/api/update_user.php?action=deactivate&id='+userId);
    const d=await r.json();
    if(d.success){showToast('User deactivated.','info');setTimeout(()=>location.reload(),1200);}
    else showToast(d.message||'Failed.','error');
  }catch{showToast('Network error while deactivating.','error');}
}

// â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
// DOM READY
// â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
document.addEventListener('DOMContentLoaded', () => {
  // Real-time numeric enforcement for 12-digit student LRN inputs
  const regLrn = $('reg-lrn-input');
  if (regLrn) {
    regLrn.addEventListener('input', function() {
      if (_role === 'student') {
        this.value = this.value.replace(/[^0-9]/g, '').slice(0, 12);
      }
    });
  }
  const nuIdnum = $('nu-idnum');
  if (nuIdnum) {
    nuIdnum.addEventListener('input', function() {
      const role = $('nu-role')?.value;
      if (role === 'student') {
        this.value = this.value.replace(/[^0-9]/g, '').slice(0, 12);
      }
    });
  }

  // Enter key progression for Login modal (PC/Laptop)
  const loginEmailInp = $('login-email-input');
  const loginPassInp = $('login-pass');
  if (loginEmailInp) {
    loginEmailInp.addEventListener('keydown', function(e) {
      if (e.key === 'Enter') {
        e.preventDefault();
        loginPassInp?.focus();
      }
    });
  }
  if (loginPassInp) {
    loginPassInp.addEventListener('keydown', function(e) {
      if (e.key === 'Enter') {
        e.preventDefault();
        if (typeof handleLogin === 'function') handleLogin();
      }
    });
  }

  // Active sidebar link
  const page = location.pathname.split('/').pop();
  $$('.sb-link, .sb-sublink').forEach(l => {
    if (l.href && l.href.includes(page) && page) l.classList.add('active');
  });

  // Entrance animations
  $$('.sum-card, .content-card, .feature-card').forEach((el,i) => {
    el.style.opacity='0'; el.style.transform='translateY(16px)';
    el.style.transition=`opacity .35s ease ${i*.055}s, transform .35s ease ${i*.055}s`;
    requestAnimationFrame(()=>requestAnimationFrame(()=>{ el.style.opacity='1'; el.style.transform='translateY(0)'; }));
  });

  // Keyboard: Esc closes modals
  document.addEventListener('keydown', e => {
    if(e.key==='Escape'){
      $$('.modal-overlay').forEach(m=>m.classList.add('hidden'));
    }
  });

  // Page exit transitions for navigation links
  document.querySelectorAll('a').forEach(link => {
    if (link.hostname === window.location.hostname && !link.hash && link.target !== '_blank') {
      link.addEventListener('click', e => {
        const isQuick = link.classList.contains('quick-link') && link.getAttribute('href')==='#';
        if (link.onclick || isQuick || link.href.includes('javascript:')) return;
        
        e.preventDefault();
        const target = link.href;
        document.body.classList.add('page-exiting');
        setTimeout(() => {
          window.location.href = target;
        }, 250);
      });
    }
  });
});



// ── Chat Widget Logic ──────────────────────────────────────────────────
let chatPollInterval = null;
let inboxPollInterval = null;
let currentChatCaseId = null;
let isFloatingInboxOpen = false;

async function loadInbox() {
  try {
    const res = await fetch('/gnhs-guidance/api/get_chat_inbox.php');
    const data = await res.json();
    if (data.success) {
      const list = document.getElementById('chatInboxList');
      if (!list) return;
      
      let totalUnread = 0;
      if (data.inbox.length === 0) {
        list.innerHTML = '<div style="padding:30px;text-align:center;color:var(--text-3);font-size:13px;">No active chats found.</div>';
      } else {
        list.innerHTML = data.inbox.map(c => {
          totalUnread += parseInt(c.unread_count);
          const typeLabel = c.concern_type.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase());
          const prioLabel = c.priority ? c.priority.charAt(0).toUpperCase() + c.priority.slice(1) + ' Priority' : '';
          
          let lastMsg = c.latest_message ? c.latest_message : '<em>No messages yet</em>';
          if(lastMsg.length > 40) lastMsg = lastMsg.substring(0, 40) + '...';
          
          let timeLabel = '';
          if(c.latest_message_time) {
            const d = new Date(c.latest_message_time);
            timeLabel = d.toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'});
          }
          
          const badge = c.unread_count > 0 ? `<div style="background:var(--red);color:#fff;font-size:11px;font-weight:bold;border-radius:10px;padding:2px 6px;">${c.unread_count}</div>` : '';
          const unreadDot = c.unread_count > 0 ? '<div style="width:8px;height:8px;border-radius:50%;background:var(--red);"></div>' : '';

          // Data passed to openChat
          const caseNum = c.case_number;
          const studentName = c.student_name;
          const subtext = `#${caseNum} (${typeLabel} - ${prioLabel})`;

          return `
            <div onclick="openChat(${c.id}, '${studentName.replace(/'/g, "\'")}', '${subtext.replace(/'/g, "\'")}')" style="padding:15px;border-bottom:1px solid var(--border);cursor:pointer;display:flex;gap:12px;align-items:center;transition:background 0.2s;" onmouseover="this.style.background='var(--bg)'" onmouseout="this.style.background='transparent'">
              <div style="width:40px;height:40px;border-radius:50%;background:var(--border);display:flex;align-items:center;justify-content:center;color:var(--text-2);">
                <i class="fas fa-user"></i>
              </div>
              <div style="flex:1;overflow:hidden;">
                <div style="display:flex;justify-content:space-between;align-items:center;">
                  <strong style="font-size:14px;color:var(--text);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">${studentName}</strong>
                  <span style="font-size:11px;color:var(--text-3);">${timeLabel}</span>
                </div>
                <div style="font-size:12px;color:var(--text-2);margin-bottom:4px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">
                  ${subtext}
                </div>
                <div style="display:flex;justify-content:space-between;align-items:center;">
                  <span style="font-size:12px;color:${c.unread_count > 0 ? 'var(--text)' : 'var(--text-3)'};font-weight:${c.unread_count > 0 ? '600' : 'normal'};white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">${lastMsg}</span>
                  ${badge}
                </div>
              </div>
            </div>
          `;
        }).join('');
      }

      // Update global unread badge
      const globalBadge = document.getElementById('globalUnreadBadge');
      if (globalBadge) {
        if (totalUnread > 0) {
          globalBadge.innerText = totalUnread;
          globalBadge.classList.remove('hidden');
        } else {
          globalBadge.classList.add('hidden');
        }
      }
    }
  } catch (e) {}
}

function toggleFloatingInbox() {
  const widget = document.getElementById('chatWidget');
  if (!widget) return;
  
  if (widget.classList.contains('hidden')) {
    // Open inbox
    widget.classList.remove('hidden');
    document.getElementById('chatInboxPane').classList.remove('hidden');
    document.getElementById('chatConversationPane').classList.add('hidden');
    loadInbox();
    if (!inboxPollInterval) inboxPollInterval = setInterval(loadInbox, 5000);
  } else {
    // Close widget completely
    widget.classList.add('hidden');
    if (inboxPollInterval) { clearInterval(inboxPollInterval); inboxPollInterval = null; }
    if (chatPollInterval) { clearInterval(chatPollInterval); chatPollInterval = null; }
    currentChatCaseId = null;
  }
}

function backToInbox() {
  currentChatCaseId = null;
  if (chatPollInterval) { clearInterval(chatPollInterval); chatPollInterval = null; }
  document.getElementById('chatConversationPane').classList.add('hidden');
  document.getElementById('chatInboxPane').classList.remove('hidden');
  loadInbox();
  if (!inboxPollInterval) inboxPollInterval = setInterval(loadInbox, 5000);
}

function openChat(caseId, studentName, subtext) {
  currentChatCaseId = caseId;
  const widget = document.getElementById('chatWidget');
  if (widget) {
    widget.classList.remove('hidden');
    document.getElementById('chatInboxPane').classList.add('hidden');
    document.getElementById('chatConversationPane').classList.remove('hidden');
    
    document.getElementById('chatHeaderTitle').innerText = studentName;
    document.getElementById('chatHeaderSub').innerText = subtext;
    
    loadMessages();
    if (inboxPollInterval) { clearInterval(inboxPollInterval); inboxPollInterval = null; }
    if (chatPollInterval) clearInterval(chatPollInterval);
    chatPollInterval = setInterval(loadMessages, 3000);
  }
}

async function loadMessages() {
  if (!currentChatCaseId) return;
  try {
    const res = await fetch('/gnhs-guidance/api/get_messages.php?case_id=' + currentChatCaseId);
    const data = await res.json();
    if (data.success) {
      const container = document.getElementById('chatMessages');
      if(!container) return;
      const atBottom = container.scrollHeight - container.scrollTop <= container.clientHeight + 10;
      
      container.innerHTML = data.messages.length ? data.messages.map(m => {
        const isMe = m.sender_id == data.current_user_id;
        const align = isMe ? 'flex-end' : 'flex-start';
        const bg = isMe ? 'var(--maroon)' : 'var(--bg)';
        const color = isMe ? '#fff' : 'var(--text)';
        const border = isMe ? 'none' : '1px solid var(--border)';
        const roleBadge = !isMe ? `<div style="font-size:10px;color:var(--text-3);margin-bottom:2px">${m.sender_role === 'admin' ? 'Counselor' : 'Student'}</div>` : '';
        return `
          <div style="display:flex;flex-direction:column;align-items:${align};width:100%">
            ${roleBadge}
            <div style="background:${bg};color:${color};border:${border};padding:10px 14px;border-radius:18px;max-width:85%;font-size:14px;line-height:1.4;box-shadow:0 1px 2px rgba(0,0,0,0.05);">
              ${m.message}
            </div>
            <div style="font-size:10px;color:var(--text-3);margin-top:4px;">${new Date(String(m.created_at).replace(' ', 'T')).toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'})}</div>
          </div>`;
      }).join('') : '<div style="text-align:center;color:var(--text-3);font-size:13px;padding:20px">No messages yet. Send a message to start the conversation!</div>';
      
      if (atBottom) {
        container.scrollTop = container.scrollHeight;
      }
    }
  } catch (e) {}
}

async function sendChatMessage() {
  if (!currentChatCaseId) return;
  const input = document.getElementById('chatInput');
  const msg = input.value.trim();
  if (!msg) return;
  
  input.value = '';
  
  const container = document.getElementById('chatMessages');
  if (container.innerHTML.includes('No messages yet')) container.innerHTML = '';
  container.innerHTML += `
    <div style="display:flex;flex-direction:column;align-items:flex-end;width:100%">
      <div style="background:var(--maroon);color:#fff;border:none;padding:10px 14px;border-radius:18px;max-width:85%;font-size:14px;line-height:1.4;opacity:0.7;box-shadow:0 1px 2px rgba(0,0,0,0.05);">
        ${msg}
      </div>
    </div>`;
  container.scrollTop = container.scrollHeight;

  try {
    const res = await apiPost('/gnhs-guidance/api/send_message.php', { case_id: currentChatCaseId, message: msg });
    if (res.success) {
      loadMessages();
    } else {
      showToast(res.message, 'error');
    }
  } catch (e) {
    showToast('Failed to send message', 'error');
  }
}

// Start polling for inbox unread counts on load
document.addEventListener('DOMContentLoaded', () => {
    loadInbox();
    setInterval(loadInbox, 10000); // Check inbox counts every 10s globally
});



// Sidebar Sliding Highlight Animation & Accordion Dropdown
function getNavOffsetTop(el, nav) {
  if (!el || !nav) return 0;
  const elRect = el.getBoundingClientRect();
  const navRect = nav.getBoundingClientRect();
  return elRect.top - navRect.top + nav.scrollTop;
}

function updateSidebarHighlight() {
  const nav = document.querySelector('.sb-nav');
  const highlight = document.querySelector('.sb-highlight');
  if (!nav || !highlight) return;

  // Priority 1: Check if an archive sublink is active
  const activeSublink = document.querySelector('.sb-sublink.active');
  let active = null;

  if (activeSublink) {
    const group = activeSublink.closest('.sb-dropdown-group');
    if (group) {
      active = group.querySelector('.sb-dropdown-btn');
    }
  }

  // Priority 2: Check for top-level active link
  if (!active) {
    active = document.querySelector('.sb-link.active:not(.sb-dropdown-btn)');
  }

  // Priority 3: Check for active dropdown button directly
  if (!active) {
    active = document.querySelector('.sb-dropdown-btn.active') || document.querySelector('.sb-dropdown-btn.active-parent');
  }

  if (active) {
    const top = getNavOffsetTop(active, nav);
    highlight.style.transform = 'translateY(' + top + 'px)';
    highlight.style.height = active.offsetHeight + 'px';
    highlight.style.opacity = '1';
  } else {
    // If no active link is found anywhere, do NOT fall back to Dashboard
    highlight.style.opacity = '0';
  }
}
window.updateSidebarHighlight = updateSidebarHighlight;

function toggleSbDropdown(id) {
  const wrap = document.getElementById(id + '-wrap');
  const btn = document.getElementById(id + '-btn');
  const menu = document.getElementById(id + '-menu');
  if (!btn || !menu) return;

  const willBeOpen = !btn.classList.contains('open');

  if (wrap) wrap.classList.toggle('open', willBeOpen);
  btn.classList.toggle('open', willBeOpen);
  menu.classList.toggle('show', willBeOpen);
  btn.setAttribute('aria-expanded', willBeOpen ? 'true' : 'false');

  // Keep sliding highlight in sync with accordion animation
  if (typeof updateSidebarHighlight === 'function') {
    updateSidebarHighlight();
    setTimeout(updateSidebarHighlight, 80);
    setTimeout(updateSidebarHighlight, 160);
    setTimeout(updateSidebarHighlight, 280);
    setTimeout(updateSidebarHighlight, 320);
  }
}
window.toggleSbDropdown = toggleSbDropdown;

document.addEventListener('DOMContentLoaded', () => {
  const nav = document.querySelector('.sb-nav');
  const links = document.querySelectorAll('.sb-link:not(.sb-dropdown-btn)');
  if(nav && (links.length > 0 || document.querySelector('.sb-dropdown-btn'))) {
    let highlight = nav.querySelector('.sb-highlight');
    if (!highlight) {
      highlight = document.createElement('div');
      highlight.className = 'sb-highlight';
      highlight.style.opacity = '0';
      nav.insertBefore(highlight, nav.firstChild);
    }

    // Position immediately without transition so it doesn't animate from 0px
    highlight.style.transition = 'none';
    updateSidebarHighlight();

    // Enable smooth transitions right after initial positioning
    requestAnimationFrame(() => {
      setTimeout(() => {
        highlight.style.transition = 'transform 0.3s cubic-bezier(0.2, 0.8, 0.2, 1), height 0.3s ease, opacity 0.2s ease';
      }, 30);
    });

    links.forEach(link => {
      if (!link.href) return;
      link.addEventListener('click', (e) => {
        if (!link.classList.contains('active')) {
          e.preventDefault();
          const top = getNavOffsetTop(link, nav);
          highlight.style.transform = 'translateY(' + top + 'px)';
          highlight.style.height = link.offsetHeight + 'px';
          highlight.style.opacity = '1';
          // Wait for animation to finish before navigating
          setTimeout(() => {
            window.location.href = link.href;
          }, 250);
        }
      });
    });

    // Sublinks: when clicked, slide highlight to parent Archive button immediately
    document.querySelectorAll('.sb-sublink').forEach(sublink => {
      sublink.addEventListener('click', () => {
        const group = sublink.closest('.sb-dropdown-group');
        const parentBtn = group ? group.querySelector('.sb-dropdown-btn') : null;
        if (parentBtn && highlight) {
          const top = getNavOffsetTop(parentBtn, nav);
          highlight.style.transform = 'translateY(' + top + 'px)';
          highlight.style.height = parentBtn.offsetHeight + 'px';
          highlight.style.opacity = '1';
        }
      });
    });

    window.addEventListener('resize', updateSidebarHighlight);
  }
});
