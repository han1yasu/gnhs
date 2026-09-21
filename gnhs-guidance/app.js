/* ================================================================
   GNHS GUIDANCE SYSTEM v2 — app.js
   All buttons wired. PHP API integration + graceful demo fallback.
   ================================================================ */

// ── Global state ─────────────────────────────────────────────────
let _action = 'login';
let _role   = 'student';
let _activeCaseId = null;

// ── Utilities ────────────────────────────────────────────────────
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

// ════════════════════════════════════════════════════════════════
// LANDING — LOGIN MODAL
// ════════════════════════════════════════════════════════════════
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
  const sub = $('role-sub-text');
  if (sub) sub.textContent = action==='login' ? 'Who are you logging in as?' : 'Create an account as:';
  showStep('step-role');
}

function goBack(stepId) { showStep(stepId); }
function switchToRegister() { _action='register'; showStep('step-role'); }
function switchToLogin()    { _action='login';    showStep('step-role'); }

function selectRole(role) {
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

// ── Login submit ─────────────────────────────────────────────────
async function handleLogin() {
  const email    = document.querySelector('#step-login input[type="text"]')?.value.trim();
  const password = $('login-pass')?.value.trim();
  if (!email||!password) { showMsg('login-msg','Please enter your email/LRN and password.'); return; }

  const btn = document.querySelector('#step-login .btn-submit');
  setLoading(btn, true); hideEl('login-msg');

  try {
    const res = await apiPost('/gnhs-guidance/api/login.php', { email, password, role:_role });
    if (res.success) { window.location.href = res.redirect; }
    else { showMsg('login-msg', res.message||'Invalid credentials. Please try again.'); setLoading(btn,false); }
  } catch {
    // Demo fallback — redirect based on role
    const map = { student:'pages/student-dashboard.php', teacher:'pages/teacher-dashboard.php', admin:'pages/admin-dashboard.php' };
    window.location.href = map[_role];
  }
}

// ── Register submit ──────────────────────────────────────────────
async function handleRegister() {
  const inputs    = $$('#step-register .input-wrap input');
  const firstName = inputs[0]?.value.trim();
  const lastName  = inputs[1]?.value.trim();
  const lrn       = $('reg-lrn-input')?.value.trim() || inputs[2]?.value.trim();
  const email     = inputs[3]?.value.trim() || inputs[2]?.value.trim();
  const pass      = $('reg-pass')?.value.trim();
  const confirm   = inputs[5]?.value.trim() || inputs[4]?.value.trim();

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
    const res = await apiPost('/gnhs-guidance/api/register.php', {
      first_name:firstName, last_name:lastName, id_number:lrn,
      grade_section:grade, email, password:pass, confirm, role:_role
    });
    if (res.success) {
      showMsg('reg-msg','Account created! You may now log in.','success');
      setTimeout(() => { _action='login'; selectRole(_role); }, 2000);
    } else { showMsg('reg-msg', res.message||'Registration failed.'); }
  } catch {
    showMsg('reg-msg','Account created! Please log in.','success');
    setTimeout(() => { _action='login'; selectRole(_role); }, 2000);
  }
  setLoading(btn, false);
}

// ── Nav ──────────────────────────────────────────────────────────
function toggleMenu() {
  const nl = document.querySelector('.nav-links');
  nl?.classList.toggle('open');
}

// ════════════════════════════════════════════════════════════════
// DASHBOARD — Shared UI
// ════════════════════════════════════════════════════════════════
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

// ════════════════════════════════════════════════════════════════
// STUDENT — Submit concern
// ════════════════════════════════════════════════════════════════
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
    const res = await apiPost('/gnhs-guidance/api/submit_case.php', {
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

// ════════════════════════════════════════════════════════════════
// TEACHER — Submit referral
// Uses split grade (gradeLevel) + section (sectionName) dropdowns
// ════════════════════════════════════════════════════════════════
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

// ════════════════════════════════════════════════════════════════
// ADMIN — Case filter
// ════════════════════════════════════════════════════════════════
function filterCases(status, btn) {
  $$('.filter-btn').forEach(b => b.classList.remove('active'));
  btn.classList.add('active');
  $$('.ct-row[data-status]').forEach(row => {
    row.style.display = (status==='all' || row.dataset.status===status) ? '' : 'none';
  });
}

// ════════════════════════════════════════════════════════════════
// ADMIN — Case review modal
// ════════════════════════════════════════════════════════════════
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
        case_number: caseNum||caseId, concern_type:'—', subject: subject||'—',
        description: description||'—', ai_summary: null,
        priority: priority||'medium', status: status||'pending',
        student_name:'—', grade_section:'—', submitted_at:'—'
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
  <div class="review-grid">
    <div>
      <div class="review-meta">
        <div class="rm-row"><label>Case ID:</label><strong style="color:var(--maroon)">#${c.case_number}</strong></div>
        <div class="rm-row"><label>Student:</label><span>${c.student_name||'Anonymous'}</span></div>
        <div class="rm-row"><label>Type:</label><span>${(c.concern_type||'').replace(/_/g,' ').replace(/\b\w/g,l=>l.toUpperCase())}</span></div>
        <div class="rm-row"><label>Grade/Sec:</label><span>${c.grade_section||'—'}</span></div>
        <div class="rm-row"><label>Submitted:</label><span>${c.submitted_at||'—'}</span></div>
      </div>
      <div style="margin-bottom:14px">
        <div style="font-size:13px;font-weight:800;color:var(--text-2);margin-bottom:8px"><i class="fas fa-quote-left" style="color:var(--maroon)"></i> Student's Description</div>
        <div class="concern-text">${c.description}</div>
      </div>
      ${c.ai_summary ? `<div class="ai-box"><div class="ai-box-label"><i class="fas fa-robot"></i>AI Summary</div><div class="ai-box-text">${c.ai_summary}</div></div>` : ''}
      ${notes?.length ? `<div style="margin-top:14px"><div style="font-size:13px;font-weight:800;color:var(--text-2);margin-bottom:8px">Previous Notes</div>${notes.map(n=>`<div class="case-note-item ${n.is_visible_to_student=='1'?'note-response':'note-counselor'}">${n.content}</div>`).join('')}</div>` : ''}
    </div>
    <div>
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
      <div class="form-group">
        <label>Counselor Notes <span style="font-size:11px;color:var(--text-3)">(private)</span></label>
        <textarea id="counselor-note" rows="3" placeholder="Internal notes — not visible to student…"></textarea>
      </div>
      <div class="form-group">
        <label>Response to Student</label>
        <textarea id="student-response" rows="3" placeholder="Message to be shown to the student…"></textarea>
      </div>
      <div class="review-actions">
        <button class="btn-secondary" onclick="$('caseReviewModal').classList.add('hidden')">Cancel</button>
        <button class="btn-primary" id="saveCaseBtn" onclick="saveCase()"><i class="fas fa-save"></i> Save & Update</button>
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
  const note     = $('counselor-note')?.value.trim();
  const response = $('student-response')?.value.trim();
  const session  = $('session-dt')?.value;

  const btn = $('saveCaseBtn');
  setLoading(btn,true);

  try {
    const res = await apiPost('/gnhs-guidance/api/update_case.php', {
      case_id:_activeCaseId, priority, status, note, response, session_date:session
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

// ════════════════════════════════════════════════════════════════
// STUDENT — View case modal
// ════════════════════════════════════════════════════════════════
async function openCaseModal(caseId) {
  $('caseModal')?.classList.remove('hidden');
  const body = $('caseModalBody');
  if (body) body.innerHTML='<div style="text-align:center;padding:40px"><span class="spinner" style="border-color:rgba(85,0,0,.2);border-top-color:var(--maroon);display:inline-block"></span></div>';

  try {
    const res = await fetch('/gnhs-guidance/api/get_case.php?id='+caseId);
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
          ${notes.length?`<div><label style="font-size:13px;font-weight:800;color:var(--text-2);display:block;margin-bottom:8px">Counselor Response</label>${notes.map(n=>`<div class="case-note-item note-response">${n.content}</div>`).join('')}</div>`:'<div style="color:var(--text-3);font-size:14px;text-align:center;padding:12px">No response yet. The counselor will reply soon.</div>'}
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

// ════════════════════════════════════════════════════════════════
// USER MANAGEMENT (admin)
// ════════════════════════════════════════════════════════════════
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
    const res=await apiPost('/gnhs-guidance/api/register.php',{first_name:first,last_name:last,email,password:pass,confirm:pass,role,id_number:idnum,grade_section:grade});
    if(res.success){showToast('User added!','success');$('addUserModal').classList.add('hidden');setTimeout(()=>location.reload(),1200);}
    else{showMsg('nuMsg',res.message||'Failed.');}
  }catch{showToast('User added (demo)','success');$('addUserModal')?.classList.add('hidden');}
  setLoading(btn,false);
}

async function deleteUser(userId, name) {
  if(!confirm(`Are you sure you want to deactivate the account of ${name}?`)) return;
  try{
    const r=await fetch('/gnhs-guidance/api/update_user.php?action=deactivate&id='+userId);
    const d=await r.json();
    if(d.success){showToast('User deactivated.','info');setTimeout(()=>location.reload(),1200);}
    else showToast(d.message||'Failed.','error');
  }catch{showToast('Done (demo).','info');document.querySelector(`[data-user-id="${userId}"]`)?.closest('.user-card')?.remove();}
}

// ════════════════════════════════════════════════════════════════
// DOM READY
// ════════════════════════════════════════════════════════════════
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
});
