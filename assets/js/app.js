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
const escapeHtml = value => String(value ?? '').replace(/[&<>"']/g, ch => ({
  '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
})[ch]);

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
  t.innerHTML = `<i class="fas fa-${icons[type]||'info-circle'}"></i>${escapeHtml(msg)}`;
  c.appendChild(t);
  setTimeout(() => { t.style.opacity='0'; t.style.transition='opacity .3s'; setTimeout(()=>t.remove(),300); }, 3200);
}

// â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
// LANDING â€” LOGIN MODAL
// â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
function openLoginModal() {
  const m = $('loginModal'); if (m) { m.classList.remove('hidden'); showStep('step-choose'); }
}
function closeLoginModal() { $('loginModal')?.classList.add('hidden'); }
function closeOnOverlay(e) { if (e.target.id==='loginModal') closeLoginModal(); }

function showStep(id) {
  $$('.modal-step').forEach(s => s.classList.add('hidden'));
  $(id)?.classList.remove('hidden');
}

function goToRoleSelect(action) {
  _action = action;
  const adminRole = document.getElementById('admin-role-card');
  if (adminRole) adminRole.style.display = action === 'register' ? 'none' : '';
  const sub = $('role-sub-text');
  if (sub) sub.textContent = action==='login' ? 'Who are you logging in as?' : 'Create an account as:';
  showStep('step-role');
}

function goBack(stepId) { showStep(stepId); }
function switchToRegister() { 
  _action='register'; 
  if (_role === 'admin') { goToRoleSelect('register'); return; }
  if (_role) selectRole(_role);
  else showStep('step-role'); 
}
function switchToLogin() { 
  _action='login'; 
  if (_role) selectRole(_role);
  else showStep('step-role'); 
}

function selectRole(role) {
  if (_action === 'register' && role === 'admin') return;
  _role = role;
  const icons  = { student:'fa-user-graduate', teacher:'fa-chalkboard-teacher', admin:'fa-user-tie' };
  const labels = { student:'Student', teacher:'Teacher', admin:'Guidance Counselor' };
  const step   = _action==='login' ? 'step-login' : 'step-register';

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
  if (lrnLabel && lrnInput) {
    if (role === 'student') {
      lrnLabel.innerHTML = 'LRN <span class="req">*</span>';
      lrnInput.placeholder = 'Enter your 12-digit LRN';
    } else {
      lrnLabel.innerHTML = 'Employee Number <span class="req">*</span>';
      lrnInput.placeholder = 'Enter your Employee Number';
    }
  }

  // Update login page label too
  const loginLabel = document.getElementById('login-id-label');
  const loginInput = document.getElementById('login-email-input');
  if (loginLabel && loginInput) {
    if (role === 'student') {
      loginLabel.textContent = 'Email Address / LRN';
      loginInput.placeholder = 'Enter your email or LRN';
    } else if (role === 'teacher') {
      loginLabel.textContent = 'Email Address / Employee Number';
      loginInput.placeholder = 'Enter your email or Employee Number';
    } else {
      loginLabel.textContent = 'Email Address';
      loginInput.placeholder = 'Enter your email address';
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
    const res = await apiPost('/api/login.php', { email, password, role:_role });
    if (res.success) { 
      document.body.classList.add('page-exiting');
      setTimeout(() => { window.location.href = res.redirect; }, 250);
    }
    else { showMsg('login-msg', res.message||'Invalid credentials. Please try again.'); setLoading(btn,false); }
  } catch {
    showMsg('login-msg', 'Cannot reach the server. Please try again.');
    setLoading(btn, false);
  }
}

// ── Forgot / Reset Password ──
function openForgotModal() {
  hideEl('login-msg');
  showStep('step-forgot');
}

async function handleForgot() {
  const email = $('forgot-email')?.value.trim();
  if (!email) { showMsg('forgot-msg', 'Please enter your email.'); return; }
  
  const btn = document.querySelector('#step-forgot .btn-submit');
  setLoading(btn, true); hideEl('forgot-msg');
  
  try {
    const res = await apiPost('/api/forgot_password.php', { email });
    setLoading(btn, false);
    if (res.success) {
      if (res.dev_token) alert(`Local reset code: ${res.dev_token}`);
      showStep('step-reset');
    } else {
      showMsg('forgot-msg', res.message || 'Error occurred.');
    }
  } catch (e) {
    setLoading(btn, false);
    showMsg('forgot-msg', 'Network error. Please try again.');
  }
}

async function handleReset() {
  const code = $('reset-code')?.value.trim();
  const pass = $('reset-pass')?.value.trim();
  const email = $('forgot-email')?.value.trim();
  
  if (!code || !pass) { showMsg('reset-msg', 'Please enter code and new password.'); return; }
  
  const btn = document.querySelector('#step-reset .btn-submit');
  setLoading(btn, true); hideEl('reset-msg');
  
  try {
    const res = await apiPost('/api/reset_password.php', { email, token: code, password: pass });
    setLoading(btn, false);
    if (res.success) {
      alert("Password reset successfully! You can now log in.");
      showStep('step-login');
    } else {
      showMsg('reset-msg', res.message || 'Error occurred.');
    }
  } catch (e) {
    setLoading(btn, false);
    showMsg('reset-msg', 'Network error. Please try again.');
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
  if (_role==='student' && !grade) {
    showMsg('reg-msg','Please select your grade and section.'); return;
  }
  if (pass!==confirm) { showMsg('reg-msg','Passwords do not match.'); return; }
  if (pass.length<8)  { showMsg('reg-msg','Password must be at least 8 characters.'); return; }

  const btn = document.querySelector('#step-register .btn-submit');
  setLoading(btn, true); hideEl('reg-msg');

  try {
    const res = await apiPost('/api/register.php', {
      first_name:firstName, middle_name:middleName, last_name:lastName, suffix:suffix, id_number:lrn,
      grade_section:grade, email, password:pass, confirm, role:_role
    });
    if (res.success) {
      showMsg('reg-msg','Account created! You may now log in.','success');
      setTimeout(() => { _action='login'; selectRole(_role); }, 2000);
    } else { showMsg('reg-msg', res.message||'Registration failed.'); }
  } catch {
    showMsg('reg-msg','Cannot reach the server. Please try again.');
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
  try { await fetch('/api/get_notifications.php?mark_read=1'); } catch {}
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
  const concernType  = $('concernType')?.value;
  const subject      = $('subject')?.value.trim();
  const desc         = $('description')?.value.trim();
  const isAnonymous  = $('isAnonymous')?.checked ? 1 : 0;
  const incidentDate = $('incidentDate')?.value;
  const contact      = $('preferredContact')?.value || 'in_person';
  const priority     = document.querySelector('.prio-opt.selected')?.classList.contains('high-opt') ? 'high' :
                       document.querySelector('.prio-opt.selected')?.classList.contains('low-opt')  ? 'low' : 'medium';

  if (!concernType) { showMsg('submitMsg','Please select a concern type.'); return; }
  if (!subject)     { showMsg('submitMsg','Please enter a subject / title.'); return; }
  if (!desc)        { showMsg('submitMsg','Please describe your concern.'); return; }

  const btn = $('submitBtn');
  setLoading(btn, true); hideEl('submitMsg');

  try {
    const res = await apiPost('/api/submit_case.php', {
      concern_type:concernType, subject, description:desc,
      is_anonymous:isAnonymous, incident_date:incidentDate,
      preferred_contact:contact, priority
    });
    if (res.success) {
      const el = $('successCaseNum'); if (el) el.textContent = res.case_number;
      $('successModal')?.classList.remove('hidden');
    } else {
      showMsg('submitMsg', res.message||'Submission failed. Please try again.');
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
    const res = await apiPost('/api/submit_referral.php', {
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
    const res = await fetch('/api/get_case.php?id='+caseId);
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

function buildReviewModal(c, notes, session) {
  const selected = p => p===c.priority ? ' selected' : '';
  const sel = s => s===c.status ? ' selected' : '';
  const body = $('caseReviewBody') || $('reviewBody');
  body.innerHTML = `
  <div class="review-header-strip" style="display:flex;flex-wrap:wrap;gap:15px;background:var(--bg2);padding:15px;border-radius:8px;margin-bottom:20px;border:1px solid var(--border)">
      <div style="flex:1;min-width:120px"><label style="font-size:11px;color:var(--text-3);text-transform:uppercase;letter-spacing:1px;display:block;margin-bottom:4px">Case ID</label><strong style="color:var(--maroon);font-size:16px">#${c.case_number}</strong></div>
      <div style="flex:2;min-width:150px"><label style="font-size:11px;color:var(--text-3);text-transform:uppercase;letter-spacing:1px;display:block;margin-bottom:4px">Student</label><span style="font-size:15px;font-weight:600">${c.student_name||'Anonymous'}</span></div>
      <div style="flex:1.5;min-width:130px"><label style="font-size:11px;color:var(--text-3);text-transform:uppercase;letter-spacing:1px;display:block;margin-bottom:4px">Type</label><span class="status-badge status-ongoing">${(c.concern_type||'').replace(/_/g,' ').replace(/\b\w/g,l=>l.toUpperCase())}</span></div>
      <div style="flex:1.5;min-width:130px"><label style="font-size:11px;color:var(--text-3);text-transform:uppercase;letter-spacing:1px;display:block;margin-bottom:4px">Grade/Sec</label><span style="font-size:14px">${c.grade_section||'—'}</span></div>
      <div style="flex:1.5;min-width:130px"><label style="font-size:11px;color:var(--text-3);text-transform:uppercase;letter-spacing:1px;display:block;margin-bottom:4px">Submitted</label><span style="font-size:13px;color:var(--text-2)">${c.submitted_at||'—'}</span></div>
    </div>
    <div class="review-grid" style="grid-template-columns: 1.2fr 1fr; gap:25px; align-items:start">
    <div>
      ${c.ai_summary ? `<div class="ai-box" style="margin-bottom:20px;border-left:4px solid var(--blue);background:rgba(37,99,235,0.05)"><div class="ai-box-label" style="color:var(--blue);font-size:14px;margin-bottom:8px"><i class="fas fa-magic"></i> AI Summary</div><div class="ai-box-text" style="font-size:15px;line-height:1.6">${c.ai_summary}</div></div>` : ''}
      <div style="margin-bottom:14px;background:var(--bg2);padding:20px;border-radius:8px;border:1px solid var(--border)">
        <div style="font-size:13px;font-weight:800;color:var(--text-2);margin-bottom:12px;text-transform:uppercase;letter-spacing:1px"><i class="fas fa-quote-left" style="color:var(--maroon)"></i> Student's Description</div>
        <div class="concern-text" style="font-size:15px;line-height:1.7;color:var(--text);background:transparent;padding:0;border:none">${c.description}</div>
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
          <select id="case-status-sel">
            <option value="pending"${sel('pending')}>Pending</option>
            <option value="under_review"${sel('under_review')}>Under Review</option>
            <option value="ongoing"${sel('ongoing')}>Ongoing</option>
            <option value="resolved"${sel('resolved')}>Resolved</option>
          </select>
        </div>
      </div>
      <div class="form-group">
        <label>Schedule Counseling Session</label>
        <div class="input-wrap"><i class="fas fa-calendar"></i><input type="datetime-local" id="session-dt"></div>
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
        <button class="btn-primary" style="width:100%;background:var(--blue)" onclick="window.location.href=window.location.href.includes('admin')?'admin-chats.php':'student-chats.php'">
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

  const btn = $('saveCaseBtn');
  setLoading(btn,true);

  try {
    const res = await apiPost('/api/update_case.php', {
      case_id:_activeCaseId, priority, status, session_date:session, session_type:sessionType, location:location
    });
    if (res.success) {
      $('caseReviewModal').classList.add('hidden');
      showToast('Case updated successfully!','success');
      setTimeout(() => location.reload(), 1500);
    } else { showToast(res.message||'Update failed.','error'); setLoading(btn,false); }
  } catch {
    showToast('Cannot reach the server. Please try again.','error');
    setLoading(btn,false);
  }
}

function closeCaseReview(e) {
  if (!e || e.target.id==='caseReviewModal') $('caseReviewModal')?.classList.add('hidden');
}


// â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
// STUDENT â€” View case modal
// â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
async function openCaseModal(caseId) {
  $('caseModal')?.classList.remove('hidden');
  const body = $('caseModalBody');
  if (body) body.innerHTML='<div style="text-align:center;padding:40px"><span class="spinner" style="border-color:rgba(85,0,0,.2);border-top-color:var(--maroon);display:inline-block"></span></div>';

  try {
    const res = await fetch('/api/get_case.php?id='+caseId);
    const d   = await res.json();
    if (d.success) {
      const c=d.case, notes=d.notes||[], session=d.session;
      body.innerHTML=`
        <div class="case-detail">
          <div style="display:grid;grid-template-columns:repeat(2,1fr);gap:12px;margin-bottom:14px">
            <div><label style="font-size:12px;font-weight:800;color:var(--text-3);display:block">CASE ID</label><strong style="color:var(--maroon)">#${c.case_number}</strong></div>
            <div><label style="font-size:12px;font-weight:800;color:var(--text-3);display:block">SUBMITTED</label><span>${c.submitted_at}</span></div>
            <div><label style="font-size:12px;font-weight:800;color:var(--text-3);display:block">STATUS</label>${buildBadge(c.status)}</div>
            <div><label style="font-size:12px;font-weight:800;color:var(--text-3);display:block">PRIORITY</label>${buildPrioBadge(c.priority)}</div>
          </div>
          <div style="margin-bottom:14px"><label style="font-size:13px;font-weight:800;color:var(--text-2);display:block;margin-bottom:8px">Your Description</label><div class="concern-text">${c.description}</div></div>
          ${c.ai_summary?`<div class="ai-box" style="margin-bottom:14px"><div class="ai-box-label"><i class="fas fa-robot"></i>AI Summary</div><div class="ai-box-text">${c.ai_summary}</div></div>`:''}
          <div style="margin-top:14px">
            <button class="btn-primary" style="width:100%;background:var(--blue)" onclick="window.location.href=window.location.href.includes('admin')?'admin-chats.php':'student-chats.php'">
              <i class="fas fa-comment-dots"></i> Message Counselor
            </button>
          </div>
          ${session?`<div style="margin-top:14px;padding:14px;background:var(--maroon-pale);border-radius:10px;border:1px solid var(--maroon-tint)"><i class="fas fa-calendar-check" style="color:var(--maroon)"></i> <strong>Session scheduled:</strong> ${session.session_date} at ${session.location}</div>`:''}
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
function openAddUserModal() { $('addUserModal')?.classList.remove('hidden'); }
function closeAddUser(e)    { if(!e||e.target.id==='addUserModal') $('addUserModal')?.classList.add('hidden'); }

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
  if(role==='student' && !grade){showMsg('nuMsg','Please select a grade and section for the student.');return;}

  const btn=$('saveUserBtn'); setLoading(btn,true); hideEl('nuMsg');
  try{
    const res=await apiPost('/api/register.php',{first_name:first,last_name:last,email,password:pass,confirm:pass,role,id_number:idnum,grade_section:grade});
    if(res.success){showToast('User added!','success');$('addUserModal').classList.add('hidden');setTimeout(()=>location.reload(),1200);}
    else{showMsg('nuMsg',res.message||'Failed.');}
  }catch{showMsg('nuMsg','Cannot reach the server. Please try again.');}
  setLoading(btn,false);
}

async function deleteUser(userId, name) {
  if(!confirm(`Are you sure you want to deactivate the account of ${name}?`)) return;
  try{
    const r=await fetch('/api/update_user.php?action=deactivate&id='+userId);
    const d=await r.json();
    if(d.success){showToast('User deactivated.','info');setTimeout(()=>location.reload(),1200);}
    else showToast(d.message||'Failed.','error');
  }catch{showToast('Cannot reach the server. Please try again.','error');}
}

// â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
// DOM READY
// â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
document.addEventListener('DOMContentLoaded', () => {
  // Active sidebar link
  const page = location.pathname.split('/').pop();
  $$('.sb-link').forEach(l => {
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
    const res = await fetch('/api/get_chat_inbox.php');
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
    const res = await fetch('/api/get_messages.php?case_id=' + currentChatCaseId);
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
              ${escapeHtml(m.message)}
            </div>
            <div style="font-size:10px;color:var(--text-3);margin-top:4px;">${new Date(m.created_at).toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'})}</div>
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
        ${escapeHtml(msg)}
      </div>
    </div>`;
  container.scrollTop = container.scrollHeight;

  try {
    const res = await apiPost('/api/send_message.php', { case_id: currentChatCaseId, message: msg });
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



// Sidebar Sliding Highlight Animation
document.addEventListener('DOMContentLoaded', () => {
  const nav = document.querySelector('.sb-nav');
  const links = document.querySelectorAll('.sb-link');
  if(nav && links.length > 0) {
    let highlight = document.createElement('div');
    highlight.className = 'sb-highlight';
    nav.insertBefore(highlight, nav.firstChild);

    const active = document.querySelector('.sb-link.active') || links[0];
    highlight.style.transform = 'translateY(' + active.offsetTop + 'px)';
    highlight.style.height = active.offsetHeight + 'px';

    setTimeout(() => {
      highlight.style.transition = 'transform 0.3s cubic-bezier(0.2, 0.8, 0.2, 1), height 0.3s ease';
    }, 50);

    links.forEach(link => {
      link.addEventListener('click', (e) => {
        if (!link.classList.contains('active')) {
          e.preventDefault();
          highlight.style.transform = 'translateY(' + link.offsetTop + 'px)';
          highlight.style.height = link.offsetHeight + 'px';
          // Wait for animation to finish before navigating
          setTimeout(() => {
            window.location.href = link.href;
          }, 250);
        }
      });
    });
  }
});
