<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/layout.php';
$user = requireLogin('admin');
$db   = getDB();

$filterRole = $_GET['role'] ?? '';
$where = "WHERE 1=1";
if ($filterRole) $where .= " AND role='".addslashes($filterRole)."'";

$users = $db->query("SELECT * FROM users $where ORDER BY totp_reset_requested DESC, role, first_name ASC")->fetchAll();

$pendingTotpCount = 0;
foreach ($users as $u) {
    if (!empty($u['totp_reset_requested'])) $pendingTotpCount++;
}

$cards = '';
foreach ($users as $u) {
    $initials  = htmlspecialchars($u['avatar_initials'] ?? strtoupper($u['first_name'][0]));
    $name      = htmlspecialchars($u['first_name'].' '.$u['last_name']);
    $email     = htmlspecialchars($u['email']);
    $idNum     = htmlspecialchars($u['id_number']);
    $roleClass = 'role-'.$u['role'];
    $roleLabel = ucfirst($u['role']==='admin' ? 'Counselor' : $u['role']);
    $gradeInfo    = $u['grade_section'] ? "<div style='font-size:12px;color:var(--text-3);margin-top:2px'><i class='fas fa-school' style='color:var(--maroon);width:12px'></i> ".htmlspecialchars($u['grade_section'])."</div>" : '';
    $advisoryInfo = (!empty($u['advisory_class']) && $u['role']==='teacher') ? "<div style='font-size:12px;color:var(--maroon);margin-top:2px;font-weight:700'><i class='fas fa-chalkboard-teacher' style='width:12px'></i> ".htmlspecialchars($u['advisory_class'])."</div>" : '';
    $activeLabel  = $u['is_active'] ? '' : "<span style='font-size:11px;color:#dc2626;font-weight:700'>[Inactive]</span>";
    $advisoryJson = json_encode($u['advisory_class'] ?? '', JSON_HEX_QUOT|JSON_HEX_APOS);
    
    $assignBtn = $u['role']==='teacher'
        ? "<button class='btn-icon' onclick='openAssignAdvisory({$u['id']},\"$name\",$advisoryJson)' title='Assign Advisory Class'><i class='fas fa-chalkboard-teacher'></i></button>"
        : '';
        
    $rawNameAttr  = htmlspecialchars($u['first_name'].' '.$u['last_name'], ENT_QUOTES);
    $rawEmailAttr = htmlspecialchars($u['email'], ENT_QUOTES);
    $rawRoleAttr  = htmlspecialchars($roleLabel, ENT_QUOTES);
    
    $resetBtn = "<button class='btn-icon' onclick='openResetPasswordModal({$u['id']}, this.dataset.name, this.dataset.email, this.dataset.role)' data-name=\"$rawNameAttr\" data-email=\"$rawEmailAttr\" data-role=\"$rawRoleAttr\" title='Reset Password'><i class='fas fa-key'></i></button>";
    
    $totpRequested = !empty($u['totp_reset_requested']);
    $totpEnabled   = !empty($u['totp_enabled']);
    $totpBadge = $totpRequested 
        ? "<div class='totp-req-tag' style='font-size:11px;background:#fef3c7;border:1px solid #fde68a;color:#b45309;font-weight:700;padding:2px 8px;border-radius:6px;margin-top:5px;display:inline-flex;align-items:center;gap:4px;'><i class='fas fa-shield-virus' style='color:#d97706'></i> 2FA Reset Requested</div>"
        : '';
        
    $totpIcon = $totpRequested ? 'fa-shield-virus' : 'fa-shield-alt';
    $totpStyle = $totpRequested ? 'background:#fef3c7;border-color:#f59e0b;color:#d97706;animation:pulseTotp 2s infinite;' : '';
    $totpTitle = $totpRequested ? '2FA Reset Requested by User - Click to Reset Authenticator' : 'Reset Two-Factor Authentication (2FA)';

    $totpBtn = "<button class='btn-icon' style='$totpStyle' onclick='openResetTotpModal({$u['id']}, this.dataset.name, this.dataset.email, this.dataset.role, ".($totpEnabled?1:0).", ".($totpRequested?1:0).")' data-name=\"$rawNameAttr\" data-email=\"$rawEmailAttr\" data-role=\"$rawRoleAttr\" title='$totpTitle'><i class='fas $totpIcon'></i></button>";
    
    $statusBtn = $u['is_active']
        ? "<button class='btn-icon danger' onclick='deleteUser({$u['id']},\"$name\")' title='Deactivate Account'><i class='fas fa-user-slash'></i></button>"
        : "<button class='btn-icon' style='color:#16a34a;border-color:#bbf7d0;background:#f0fdf4' onclick='activateUser({$u['id']},\"$name\")' title='Activate Account'><i class='fas fa-user-check'></i></button>";

    $avatarEl = !empty($u['avatar_photo'])
        ? "<div class='user-card-avatar' style='background:none;padding:0;overflow:hidden'><img src='".htmlspecialchars($u['avatar_photo'])."' style='width:60px;height:60px;object-fit:cover;object-position:center top;border-radius:16px;display:block;'/></div>"
        : "<div class='user-card-avatar'>$initials</div>";
        
    $cards .= "<div class='user-card' data-user-id='{$u['id']}'>
        $avatarEl
        <div class='user-card-name'>$name $activeLabel</div>
        <span class='user-card-role $roleClass'>$roleLabel</span>
        $totpBadge
        $gradeInfo
        $advisoryInfo
        <div style='font-size:12px;color:var(--text-3)'>$email</div>
        <div style='font-size:12px;color:var(--text-3)'>ID: $idNum</div>
        <div class='user-card-actions'>
          $assignBtn
          $totpBtn
          $resetBtn
          $statusBtn
        </div>
    </div>";
}
if (!$cards) $cards = "<div style='grid-column:1/-1;text-align:center;color:var(--text-3);padding:40px'>No users found.</div>";

$rAll  = $filterRole===''        ? 'selected' : '';
$rStu  = $filterRole==='student' ? 'selected' : '';
$rTea  = $filterRole==='teacher' ? 'selected' : '';
$rAdm  = $filterRole==='admin'   ? 'selected' : '';

$bannerHtml = '';
if ($pendingTotpCount > 0) {
    $bannerHtml = <<<HTML
<div style="background:#fffbeb;border:1px solid #fde68a;border-radius:12px;padding:12px 18px;margin-bottom:18px;display:flex;align-items:center;justify-content:space-between;gap:12px;color:#92400e;flex-wrap:wrap">
  <div style="display:flex;align-items:center;gap:12px">
    <div style="width:36px;height:36px;border-radius:10px;background:#fef3c7;color:#d97706;display:flex;align-items:center;justify-content:center;font-size:18px;flex-shrink:0">
      <i class="fas fa-shield-virus"></i>
    </div>
    <div>
      <div style="font-weight:700;font-size:14px;color:#92400e">{$pendingTotpCount} User(s) Requested 2FA Reset</div>
      <div style="font-size:12px;color:#b45309">Users who lost their authenticator app are waiting for their 2FA to be reset.</div>
    </div>
  </div>
  <div style="display:flex;gap:8px;align-items:center;">
    <a href="admin-2fa-requests.php" class="btn-sm-primary" style="background:#d97706;border-color:#b45309;padding:7px 14px;font-size:12px;text-decoration:none;display:inline-flex;align-items:center;gap:6px;"><i class="fas fa-shield-alt"></i> Open 2FA Requests Tab</a>
  </div>
</div>
HTML;
}

$content = <<<HTML
<style>
@keyframes pulseTotp {
  0% { box-shadow: 0 0 0 0 rgba(217, 119, 6, 0.4); }
  70% { box-shadow: 0 0 0 6px rgba(217, 119, 6, 0); }
  100% { box-shadow: 0 0 0 0 rgba(217, 119, 6, 0); }
}
</style>
$bannerHtml
<div class="content-card" style="margin-bottom:22px">
  <div class="card-header" style="flex-wrap:wrap;gap:12px">
    <h3><i class="fas fa-users"></i> User Management</h3>
    <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap">
      <div style="position:relative;display:flex;align-items:center">
        <i class="fas fa-search" style="position:absolute;left:12px;color:var(--text-3);font-size:13px;pointer-events:none"></i>
        <input type="text" id="userSearchInput" placeholder="Search user..." oninput="filterUsersClient(this.value)" style="padding:8px 12px 8px 34px;border:1px solid var(--border);border-radius:8px;font-size:13px;outline:none;background:var(--bg2);color:var(--text);width:190px;transition:.2s" />
      </div>
      <select class="status-select" onchange="window.location='admin-users.php?role='+this.value">
        <option value=""        $rAll>All Roles</option>
        <option value="student" $rStu>Students</option>
        <option value="teacher" $rTea>Teachers</option>
        <option value="admin"   $rAdm>Counselors</option>
      </select>
      <button class="btn-sm-primary" onclick="openAddUserModal()"><i class="fas fa-user-plus"></i> Add User</button>
    </div>
  </div>
  <div class="users-grid" style="padding-top:8px">$cards</div>
</div>

<!-- Add User Modal -->
<div class="modal-overlay hidden" id="addUserModal" onclick="closeAddUser(event)">
  <div class="modal-box" style="max-width:520px">
    <button class="modal-close" onclick="document.getElementById('addUserModal').classList.add('hidden')"><i class="fas fa-times"></i></button>
    <div class="modal-logo"><i class="fas fa-user-plus"></i></div>
    <h2 class="modal-title">Add New User</h2>
    <div id="nuMsg" style="display:none"></div>
    <div id="addUserForm">
      <div class="form-row">
        <div class="form-group"><label>First Name <span class="req">*</span></label><div class="input-wrap"><i class="fas fa-user"></i><input type="text" id="nu-first" placeholder="First Name"/></div></div>
        <div class="form-group"><label>Last Name <span class="req">*</span></label><div class="input-wrap"><i class="fas fa-user"></i><input type="text" id="nu-last" placeholder="Last Name"/></div></div>
      </div>
      <div class="form-group"><label>Role <span class="req">*</span></label>
        <div class="select-wrap"><i class="fas fa-tag"></i>
          <select id="nu-role" onchange="toggleGrade(this.value)">
            <option value="student">Student</option>
            <option value="teacher">Teacher</option>
            <option value="admin">Guidance Counselor</option>
          </select>
        </div>
      </div>
      <div id="nu-grade-group">
        <div class="form-row">
          <div class="form-group">
            <label>Grade</label>
            <div class="select-wrap"><i class="fas fa-layer-group"></i>
              <select id="nu-grade-level" onchange="nuUpdateSections()">
                <option value="">Select Grade</option>
                <option value="7">Grade 7</option>
                <option value="8">Grade 8</option>
                <option value="9">Grade 9</option>
                <option value="10">Grade 10</option>
              </select>
            </div>
          </div>
          <div class="form-group">
            <label>Section</label>
            <div class="select-wrap"><i class="fas fa-users"></i>
              <select id="nu-section">
                <option value="">Select Grade First</option>
              </select>
            </div>
          </div>
        </div>
      </div>
      <div class="form-group"><label>ID Number / LRN <span class="req">*</span></label><div class="input-wrap"><i class="fas fa-id-card"></i><input type="text" id="nu-idnum" placeholder="Employee ID or LRN"/></div></div>
      <div class="form-group"><label>Email Address <span class="req">*</span></label><div class="input-wrap"><i class="fas fa-envelope"></i><input type="email" id="nu-email" placeholder="email@example.com"/></div></div>
      <div class="form-group"><label>Password <span class="req">*</span></label><div class="input-wrap"><i class="fas fa-lock"></i><input type="password" id="nu-pass" placeholder="Minimum 8 characters"/></div></div>
      <div class="form-actions">
        <button class="btn-secondary" onclick="document.getElementById('addUserModal').classList.add('hidden')">Cancel</button>
        <button class="btn-primary" id="saveUserBtn" onclick="saveNewUser()"><i class="fas fa-save"></i> Create User</button>
      </div>
    </div>
  </div>
</div>

<!-- Assign Advisory Modal -->
<div class="modal-overlay hidden" id="advisoryModal" onclick="if(event.target.id==='advisoryModal')this.classList.add('hidden')">
  <div class="modal-box" style="max-width:460px">
    <button class="modal-close" onclick="document.getElementById('advisoryModal').classList.add('hidden')"><i class="fas fa-times"></i></button>
    <div class="modal-logo"><i class="fas fa-chalkboard-teacher"></i></div>
    <h2 class="modal-title">Assign Advisory Class</h2>
    <p class="modal-sub" id="advisoryTeacherName"></p>
    <div id="advisoryMsg" style="display:none"></div>
    <div class="form-row">
      <div class="form-group">
        <label>Grade <span class="req">*</span></label>
        <div class="select-wrap"><i class="fas fa-layer-group"></i>
          <select id="advisory-grade" onchange="advisoryUpdateSections()">
            <option value="">Select Grade</option>
            <option value="7">Grade 7</option>
            <option value="8">Grade 8</option>
            <option value="9">Grade 9</option>
            <option value="10">Grade 10</option>
          </select>
        </div>
      </div>
      <div class="form-group">
        <label>Section <span class="req">*</span></label>
        <div class="select-wrap"><i class="fas fa-users"></i>
          <select id="advisory-section">
            <option value="">Select Grade First</option>
          </select>
        </div>
      </div>
    </div>
    <div class="form-actions">
      <button class="btn-secondary" onclick="document.getElementById('advisoryModal').classList.add('hidden')">Cancel</button>
      <button class="btn-primary" id="saveAdvisoryBtn" onclick="saveAdvisory()"><i class="fas fa-save"></i> Assign Class</button>
    </div>
  </div>
</div>

<!-- Reset Password Modal -->
<div class="modal-overlay hidden" id="resetPasswordModal" onclick="closeResetPasswordModal(event)">
  <div class="modal-box" style="max-width:480px">
    <button class="modal-close" onclick="closeResetPasswordModal()"><i class="fas fa-times"></i></button>
    <div class="modal-logo" style="background:var(--maroon-pale);color:var(--maroon)"><i class="fas fa-key"></i></div>
    <h2 class="modal-title">Reset User Password</h2>
    <p class="modal-sub" id="resetUserTargetText" style="margin-bottom:14px">Set a new password for this user</p>

    <!-- User Mini Badge Card inside modal -->
    <div style="background:var(--bg2);border:1px solid var(--border);border-radius:12px;padding:12px 14px;margin-bottom:18px;display:flex;align-items:center;gap:12px;text-align:left;">
      <div style="width:40px;height:40px;border-radius:10px;background:var(--maroon-pale);color:var(--maroon);display:flex;align-items:center;justify-content:center;font-size:16px;flex-shrink:0;">
        <i class="fas fa-user-shield"></i>
      </div>
      <div style="overflow:hidden;flex:1">
        <div id="resetTargetName" style="font-weight:700;font-size:14px;color:var(--text);white-space:nowrap;overflow:hidden;text-overflow:ellipsis">User Name</div>
        <div id="resetTargetSub" style="font-size:12px;color:var(--text-3);white-space:nowrap;overflow:hidden;text-overflow:ellipsis">user@example.com</div>
      </div>
      <span id="resetTargetRoleBadge" class="user-card-role role-student" style="font-size:11px;padding:3px 10px;flex-shrink:0">Student</span>
    </div>

    <div id="resetPwMsg" style="display:none;margin-bottom:14px"></div>

    <div id="resetPwForm">
      <div class="form-group" style="text-align:left;margin-bottom:14px">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:6px">
          <label style="font-weight:700;font-size:13px;margin:0">New Password <span class="req" style="color:var(--maroon)">*</span></label>
          <button type="button" onclick="generateRandomPassword()" style="font-size:12px;color:var(--maroon);background:none;border:none;cursor:pointer;font-weight:700;display:inline-flex;align-items:center;gap:5px;padding:0">
            <i class="fas fa-magic"></i> Generate Random
          </button>
        </div>
        <div class="input-wrap">
          <i class="fas fa-lock"></i>
          <input type="password" id="reset-new-pass" placeholder="Minimum 8 characters" autocomplete="new-password" oninput="checkPasswordMatch()" />
          <button class="toggle-pass" type="button" onclick="togglePass('reset-new-pass')" title="Show/Hide Password"><i class="fas fa-eye"></i></button>
        </div>
      </div>

      <div class="form-group" style="text-align:left;margin-bottom:14px">
        <label style="font-weight:700;font-size:13px;margin-bottom:6px;display:block">Confirm New Password <span class="req" style="color:var(--maroon)">*</span></label>
        <div class="input-wrap">
          <i class="fas fa-lock"></i>
          <input type="password" id="reset-confirm-pass" placeholder="Re-type new password" autocomplete="new-password" oninput="checkPasswordMatch()" />
          <button class="toggle-pass" type="button" onclick="togglePass('reset-confirm-pass')" title="Show/Hide Password"><i class="fas fa-eye"></i></button>
        </div>
        <div id="reset-match-hint" style="font-size:12px;margin-top:6px;display:none;font-weight:600"></div>
      </div>

      <!-- Quick Preset Badge -->
      <div style="background:var(--bg2);border:1px dashed var(--border);border-radius:10px;padding:10px 14px;margin-bottom:18px;display:flex;align-items:center;justify-content:space-between;font-size:12px;gap:8px">
        <span style="color:var(--text-2)"><i class="fas fa-lightbulb" style="color:#eab308;margin-right:4px"></i> Default suggestion: <strong style="font-family:monospace;background:var(--bg);padding:2px 6px;border-radius:4px;border:1px solid var(--border)">GNHS@2026</strong></span>
        <button type="button" onclick="useDefaultPassword('GNHS@2026')" style="padding:4px 10px;font-size:11px;border-radius:6px;border:1px solid var(--border);background:#fff;cursor:pointer;font-weight:700;color:var(--maroon);white-space:nowrap">Use Default</button>
      </div>

      <div class="form-actions" style="display:flex;gap:10px;justify-content:flex-end">
        <button type="button" class="btn-secondary" onclick="closeResetPasswordModal()">Cancel</button>
        <button type="button" class="btn-primary" id="saveResetPwBtn" onclick="submitResetPassword()"><i class="fas fa-key"></i> Reset Password</button>
      </div>
    </div>

    <!-- Success Feedback Card with Copy Option -->
    <div id="resetSuccessCard" style="display:none;text-align:left;margin-top:10px">
      <div style="background:#f0fdf4;border:1px solid #bbf7d0;border-radius:12px;padding:16px;">
        <div style="display:flex;align-items:center;gap:8px;color:#166534;font-weight:700;font-size:14px;margin-bottom:8px">
          <i class="fas fa-check-circle" style="font-size:16px"></i> Password Successfully Updated!
        </div>
        <p style="font-size:12px;color:#15803d;margin:0 0 10px 0">
          The user can now log in using the new password below. Make sure to share it with them:
        </p>
        <div style="display:flex;align-items:center;gap:8px;background:#fff;border:1px solid #86efac;border-radius:8px;padding:8px 12px;">
          <input type="text" id="resetSavedPasswordDisplay" readonly style="border:none;outline:none;background:transparent;font-family:monospace;font-size:15px;font-weight:800;color:#14532d;flex:1;letter-spacing:1px" />
          <button type="button" id="copyPasswordBtn" onclick="copyResetPassword()" class="btn-sm-primary" style="padding:6px 12px;font-size:12px;display:flex;align-items:center;gap:6px">
            <i class="fas fa-copy"></i> <span id="copyBtnLabel">Copy</span>
          </button>
        </div>
      </div>
      <div style="margin-top:14px;display:flex;justify-content:flex-end">
        <button type="button" class="btn-secondary" onclick="closeResetPasswordModal()">Done</button>
      </div>
    </div>

  </div>
</div>

<!-- Reset 2FA Modal -->
<div class="modal-overlay hidden" id="resetTotpModal" onclick="closeResetTotpModal(event)">
  <div class="modal-box" style="max-width:480px">
    <button class="modal-close" onclick="closeResetTotpModal()"><i class="fas fa-times"></i></button>
    <div class="modal-logo" style="background:#fffbeb;color:#d97706"><i class="fas fa-shield-alt"></i></div>
    <h2 class="modal-title">Reset Two-Factor Authentication</h2>
    <p class="modal-sub" id="resetTotpUserTargetText">Unlink authenticator app for this user</p>

    <!-- User Mini Badge Card inside modal -->
    <div style="background:var(--bg2);border:1px solid var(--border);border-radius:12px;padding:12px 14px;margin-bottom:16px;display:flex;align-items:center;gap:12px;text-align:left;">
      <div style="width:40px;height:40px;border-radius:10px;background:#fef3c7;color:#d97706;display:flex;align-items:center;justify-content:center;font-size:16px;flex-shrink:0;">
        <i class="fas fa-user-shield"></i>
      </div>
      <div style="overflow:hidden;flex:1">
        <div id="totpTargetName" style="font-weight:700;font-size:14px;color:var(--text);white-space:nowrap;overflow:hidden;text-overflow:ellipsis">User Name</div>
        <div id="totpTargetSub" style="font-size:12px;color:var(--text-3);white-space:nowrap;overflow:hidden;text-overflow:ellipsis">user@example.com</div>
      </div>
      <span id="totpTargetRoleBadge" class="user-card-role role-student" style="font-size:11px;padding:3px 10px;flex-shrink:0">Student</span>
    </div>

    <!-- 2FA Current Status Indicator -->
    <div id="totpStatusIndicator" style="background:#fff;border:1px solid var(--border);border-radius:10px;padding:10px 14px;margin-bottom:16px;text-align:left;font-size:13px;">
    </div>

    <div style="background:var(--bg2);border-radius:10px;padding:12px 14px;margin-bottom:18px;text-align:left;font-size:12px;color:var(--text-2);line-height:1.5;">
      <i class="fas fa-info-circle" style="color:var(--info);margin-right:4px;"></i>
      <strong>What happens when you reset:</strong><br>
      The user's current authenticator secret will be cleared. When they log in with their email and password, they will be shown a <strong>brand-new QR code</strong> to scan with Google Authenticator or Authy.
    </div>

    <div id="totpMsg" style="display:none;margin-bottom:14px"></div>

    <div class="form-actions" style="display:flex;gap:10px;justify-content:flex-end">
      <button type="button" class="btn-secondary" onclick="closeResetTotpModal()">Cancel</button>
      <button type="button" class="btn-primary" id="confirmResetTotpBtn" style="background:linear-gradient(135deg, #d97706, #b45309);" onclick="submitResetTotp()"><i class="fas fa-shield-alt"></i> Reset 2FA</button>
    </div>
  </div>
</div>

<script>

let _advisoryUserId = null;
const ADVISORY_SECTIONS = {
  '7':  ['Bonifacio', 'Burgos', 'Del Pilar', 'Diego Silang', 'Luna', 'Mabini', 'Malvar', 'Rizal'],
  '8':  ['Abrahams', 'Basho', 'Confucius', 'Ghandi', 'Goswami', 'Kalidasa', 'Mandela', 'Mencius', 'Tagore', 'Valmiki', 'Voltaire'],
  '9':  ['Bohr', 'Curie', 'Dalton', 'Darwin', 'Einstein', 'Faraday', 'Galileo', 'Newton'],
  '10': ['Aguinaldo', 'Aquino', 'Garcia', 'Laurel', 'Macapagal', 'Magsaysay']
};
function advisoryUpdateSections() {
  const grade = document.getElementById('advisory-grade').value;
  const sel   = document.getElementById('advisory-section');
  const secs  = ADVISORY_SECTIONS[grade]||[];
  sel.innerHTML = secs.length
    ? '<option value="">Select Section</option>'+secs.map(s=>'<option value="'+s+'">'+s+'</option>').join('')
    : '<option value="">Select Grade First</option>';
}
function openAssignAdvisory(userId, name, current) {
  _advisoryUserId = userId;
  document.getElementById('advisoryTeacherName').textContent = 'Teacher: '+name;
  if (current && current.includes(' - ')) {
    const parts = current.split(' - ');
    const grade = parts[0].replace('Grade ','');
    document.getElementById('advisory-grade').value = grade;
    advisoryUpdateSections();
    setTimeout(()=>{ document.getElementById('advisory-section').value=parts[1]||''; },80);
  } else {
    document.getElementById('advisory-grade').value='';
    document.getElementById('advisory-section').innerHTML='<option value="">Select Grade First</option>';
  }
  hideEl('advisoryMsg');
  document.getElementById('advisoryModal').classList.remove('hidden');
}
async function saveAdvisory() {
  const grade=document.getElementById('advisory-grade').value;
  const section=document.getElementById('advisory-section').value;
  if(!grade||!section){showMsg('advisoryMsg','Please select both grade and section.');return;}
  const cls='Grade '+grade+' - '+section;
  const btn=document.getElementById('saveAdvisoryBtn');
  setLoading(btn,true); hideEl('advisoryMsg');
  try {
    const r=await fetch('/gnhs-guidance/api/assign_advisory.php?user_id='+_advisoryUserId+'&class='+encodeURIComponent(cls));
    const d=await r.json();
    if(d.success){showToast('Advisory class assigned!','success');document.getElementById('advisoryModal').classList.add('hidden');setTimeout(()=>location.reload(),1200);}
    else{showMsg('advisoryMsg',d.message||'Failed.');setLoading(btn,false);}
  }catch{showToast('Error. Check server.','error');setLoading(btn,false);}
}

const NU_GRADE_SECTIONS = {
  '7':  ['Bonifacio', 'Burgos', 'Del Pilar', 'Diego Silang', 'Luna', 'Mabini', 'Malvar', 'Rizal'],
  '8':  ['Abrahams', 'Basho', 'Confucius', 'Ghandi', 'Goswami', 'Kalidasa', 'Mandela', 'Mencius', 'Tagore', 'Valmiki', 'Voltaire'],
  '9':  ['Bohr', 'Curie', 'Dalton', 'Darwin', 'Einstein', 'Faraday', 'Galileo', 'Newton'],
  '10': ['Aguinaldo', 'Aquino', 'Garcia', 'Laurel', 'Macapagal', 'Magsaysay']
};
function toggleGrade(role) {
  document.getElementById('nu-grade-group').style.display = role==='student' ? 'block' : 'none';
}
function nuUpdateSections() {
  const grade = document.getElementById('nu-grade-level').value;
  const sel   = document.getElementById('nu-section');
  const secs  = NU_GRADE_SECTIONS[grade] || [];
  sel.innerHTML = secs.length
    ? '<option value="">Select Section</option>' + secs.map(s=>'<option value="'+s+'">'+s+'</option>').join('')
    : '<option value="">Select Grade First</option>';
}

// ── Reset Password Functionality ─────────────────────────
let _resetTargetUserId = null;
let _resetLastPassword = '';

function openResetPasswordModal(userId, name, email, role) {
  _resetTargetUserId = userId;
  _resetLastPassword = '';

  const targetText = document.getElementById('resetUserTargetText');
  if (targetText) targetText.textContent = 'Set a new password for ' + name;

  const targetName = document.getElementById('resetTargetName');
  if (targetName) targetName.textContent = name;

  const targetSub = document.getElementById('resetTargetSub');
  if (targetSub) targetSub.textContent = email;

  const badge = document.getElementById('resetTargetRoleBadge');
  if (badge) {
    badge.textContent = role || 'User';
    badge.className = 'user-card-role role-' + (role ? role.toLowerCase() : 'student');
  }

  const p1 = document.getElementById('reset-new-pass');
  const p2 = document.getElementById('reset-confirm-pass');
  if (p1) { p1.value = ''; p1.type = 'password'; }
  if (p2) { p2.value = ''; p2.type = 'password'; }

  document.querySelectorAll('#resetPwForm .toggle-pass i').forEach(function(i) {
    i.className = 'fas fa-eye';
  });

  hideEl('resetPwMsg');
  const hint = document.getElementById('reset-match-hint');
  if (hint) { hint.style.display = 'none'; hint.textContent = ''; }

  const form = document.getElementById('resetPwForm');
  if (form) form.style.display = 'block';
  const sc = document.getElementById('resetSuccessCard');
  if (sc) sc.style.display = 'none';

  const modal = document.getElementById('resetPasswordModal');
  if (modal) {
    modal.classList.remove('hidden');
    setTimeout(() => p1?.focus(), 150);
  }
}

function closeResetPasswordModal(e) {
  if (!e || e.target.id === 'resetPasswordModal' || e.target.closest('.modal-close') || (e.target.tagName==='BUTTON' && (e.target.innerText.trim()==='Cancel' || e.target.innerText.trim()==='Done'))) {
    document.getElementById('resetPasswordModal')?.classList.add('hidden');
  }
}

function generateRandomPassword() {
  const chars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnpqrstuvwxyz23456789!@#$%';
  const uppers = 'ABCDEFGHJKLMNPQRSTUVWXYZ';
  const lowers = 'abcdefghijkmnpqrstuvwxyz';
  const nums   = '23456789';
  const syms   = '!@#$%';
  let pwd = '';
  pwd += uppers[Math.floor(Math.random() * uppers.length)];
  pwd += lowers[Math.floor(Math.random() * lowers.length)];
  pwd += nums[Math.floor(Math.random() * nums.length)];
  pwd += syms[Math.floor(Math.random() * syms.length)];
  for (let i = 0; i < 6; i++) {
    pwd += chars[Math.floor(Math.random() * chars.length)];
  }
  pwd = pwd.split('').sort(() => 0.5 - Math.random()).join('');

  const p1 = document.getElementById('reset-new-pass');
  const p2 = document.getElementById('reset-confirm-pass');
  if (p1 && p2) {
    p1.value = pwd;
    p2.value = pwd;
    p1.type = 'text';
    p2.type = 'text';
    document.querySelectorAll('#resetPwForm .toggle-pass i').forEach(function(i) {
      i.className = 'fas fa-eye-slash';
    });
    checkPasswordMatch();
  }
}

function useDefaultPassword(pwd) {
  const p1 = document.getElementById('reset-new-pass');
  const p2 = document.getElementById('reset-confirm-pass');
  if (p1 && p2) {
    p1.value = pwd;
    p2.value = pwd;
    p1.type = 'text';
    p2.type = 'text';
    document.querySelectorAll('#resetPwForm .toggle-pass i').forEach(function(i) {
      i.className = 'fas fa-eye-slash';
    });
    checkPasswordMatch();
  }
}

function checkPasswordMatch() {
  const p1 = document.getElementById('reset-new-pass')?.value || '';
  const p2 = document.getElementById('reset-confirm-pass')?.value || '';
  const hint = document.getElementById('reset-match-hint');
  if (!hint) return;

  if (!p1 && !p2) {
    hint.style.display = 'none';
    return;
  }

  hint.style.display = 'block';
  if (p1.length < 8) {
    hint.style.color = '#dc2626';
    hint.innerHTML = '<i class="fas fa-exclamation-circle"></i> Password must be at least 8 characters.';
    return;
  }

  if (!p2) {
    hint.style.color = 'var(--text-3)';
    hint.innerHTML = 'Please confirm the password.';
    return;
  }

  if (p1 === p2) {
    hint.style.color = '#16a34a';
    hint.innerHTML = '<i class="fas fa-check-circle"></i> Passwords match.';
  } else {
    hint.style.color = '#dc2626';
    hint.innerHTML = '<i class="fas fa-times-circle"></i> Passwords do not match.';
  }
}

async function submitResetPassword() {
  const p1 = document.getElementById('reset-new-pass')?.value.trim();
  const p2 = document.getElementById('reset-confirm-pass')?.value.trim();
  const btn = document.getElementById('saveResetPwBtn');

  hideEl('resetPwMsg');

  if (!_resetTargetUserId) {
    showMsg('resetPwMsg', 'No user selected.');
    return;
  }
  if (!p1 || !p2) {
    showMsg('resetPwMsg', 'Please fill in both password fields.');
    return;
  }
  if (p1.length < 8) {
    showMsg('resetPwMsg', 'Password must be at least 8 characters long.');
    return;
  }
  if (p1 !== p2) {
    showMsg('resetPwMsg', 'Passwords do not match.');
    return;
  }

  setLoading(btn, true);

  try {
    const res = await apiPost('/gnhs-guidance/api/admin_reset_password.php', {
      user_id: _resetTargetUserId,
      password: p1
    });

    if (res.success) {
      showToast('Password reset successfully! ✅', 'success');
      _resetLastPassword = p1;

      document.getElementById('resetPwForm').style.display = 'none';
      const sc = document.getElementById('resetSuccessCard');
      if (sc) {
        sc.style.display = 'block';
        document.getElementById('resetSavedPasswordDisplay').value = p1;
        document.getElementById('copyBtnLabel').textContent = 'Copy';
      }
    } else {
      showMsg('resetPwMsg', res.message || 'Failed to reset password.');
    }
  } catch (err) {
    showMsg('resetPwMsg', 'Failed to connect to the server. Please try again.');
  } finally {
    setLoading(btn, false);
  }
}

function copyResetPassword() {
  const display = document.getElementById('resetSavedPasswordDisplay');
  if (!display) return;
  display.select();
  display.setSelectionRange(0, 99999);
  if (navigator.clipboard && navigator.clipboard.writeText) {
    navigator.clipboard.writeText(display.value).then(() => {
      document.getElementById('copyBtnLabel').textContent = 'Copied!';
      showToast('Password copied to clipboard!', 'info');
      setTimeout(() => {
        const lbl = document.getElementById('copyBtnLabel');
        if (lbl) lbl.textContent = 'Copy';
      }, 2500);
    });
  } else {
    document.execCommand('copy');
    document.getElementById('copyBtnLabel').textContent = 'Copied!';
    showToast('Password copied to clipboard!', 'info');
  }
}

// ── Reset 2FA Functionality ──────────────────────────────
let _totpTargetUserId = null;

function openResetTotpModal(userId, name, email, role, isEnabled, isRequested) {
  _totpTargetUserId = userId;

  const targetText = document.getElementById('resetTotpUserTargetText');
  if (targetText) targetText.textContent = 'Unlink authenticator app for ' + name;

  const targetName = document.getElementById('totpTargetName');
  if (targetName) targetName.textContent = name;

  const targetSub = document.getElementById('totpTargetSub');
  if (targetSub) targetSub.textContent = email;

  const badge = document.getElementById('totpTargetRoleBadge');
  if (badge) {
    badge.textContent = role || 'User';
    badge.className = 'user-card-role role-' + (role ? role.toLowerCase() : 'student');
  }

  const statusEl = document.getElementById('totpStatusIndicator');
  if (statusEl) {
    if (isRequested) {
      statusEl.style.borderColor = '#f59e0b';
      statusEl.style.background = '#fffbeb';
      statusEl.innerHTML = '<span style="color:#d97706;font-weight:700"><i class="fas fa-exclamation-triangle"></i> Reset Requested:</span> <span style="color:#92400e">This user reported losing access to their authenticator and requested a reset.</span>';
    } else if (isEnabled) {
      statusEl.style.borderColor = '#86efac';
      statusEl.style.background = '#f0fdf4';
      statusEl.innerHTML = '<span style="color:#16a34a;font-weight:700"><i class="fas fa-check-circle"></i> 2FA is Active:</span> <span style="color:#15803d">User has an authenticator app currently linked.</span>';
    } else {
      statusEl.style.borderColor = 'var(--border)';
      statusEl.style.background = 'var(--bg)';
      statusEl.innerHTML = '<span style="color:var(--text-3);font-weight:700"><i class="fas fa-info-circle"></i> 2FA Not Active:</span> <span style="color:var(--text-2)">User has not completed 2FA setup yet.</span>';
    }
  }

  hideEl('totpMsg');
  const modal = document.getElementById('resetTotpModal');
  if (modal) modal.classList.remove('hidden');
}

function closeResetTotpModal(e) {
  if (!e || e.target.id === 'resetTotpModal' || e.target.closest('.modal-close') || (e.target.tagName==='BUTTON' && e.target.innerText.trim()==='Cancel')) {
    document.getElementById('resetTotpModal')?.classList.add('hidden');
  }
}

async function submitResetTotp() {
  if (!_totpTargetUserId) return;
  const btn = document.getElementById('confirmResetTotpBtn');
  setLoading(btn, true);
  hideEl('totpMsg');

  try {
    const res = await apiPost('/gnhs-guidance/api/admin_reset_totp.php', {
      user_id: _totpTargetUserId
    });

    if (res.success) {
      showToast('2FA has been successfully reset! User can now scan a new QR code.', 'success');
      document.getElementById('resetTotpModal')?.classList.add('hidden');
      setTimeout(() => location.reload(), 1200);
    } else {
      showMsg('totpMsg', res.message || 'Failed to reset 2FA.');
    }
  } catch (err) {
    showMsg('totpMsg', 'Failed to connect to the server. Please try again.');
  } finally {
    setLoading(btn, false);
  }
}

function filterUsersClient(val) {
  val = val.toLowerCase().trim();
  const cards = document.querySelectorAll('.users-grid .user-card');
  let visibleCount = 0;
  cards.forEach(function(c) {
    const text = c.textContent.toLowerCase();
    const match = !val || text.includes(val);
    c.style.display = match ? '' : 'none';
    if (match) visibleCount++;
  });
  let noMatch = document.getElementById('noUserMatchNotice');
  if (visibleCount === 0) {
    if (!noMatch) {
      noMatch = document.createElement('div');
      noMatch.id = 'noUserMatchNotice';
      noMatch.style.gridColumn = '1/-1';
      noMatch.style.textAlign = 'center';
      noMatch.style.color = 'var(--text-3)';
      noMatch.style.padding = '40px';
      noMatch.textContent = 'No matching users found.';
      document.querySelector('.users-grid').appendChild(noMatch);
    }
    noMatch.style.display = 'block';
  } else if (noMatch) {
    noMatch.style.display = 'none';
  }
}
</script>
HTML;

renderLayout($user, 'User Management', 'users', $content);
