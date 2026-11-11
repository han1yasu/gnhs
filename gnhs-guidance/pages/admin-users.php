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
    $roleNames = ['student'=>'Student','teacher'=>'Teacher','admin'=>'Counselor'];
    $roleLabel = $roleNames[$u['role']] ?? ucfirst($u['role']);
    $gradeInfo    = $u['grade_section'] ? "<div style='font-size:12px;color:var(--text-3);margin-top:2px'><i class='fas fa-school' style='color:var(--maroon);width:12px'></i> ".htmlspecialchars($u['grade_section'])."</div>" : '';
    $advisoryInfo = (!empty($u['advisory_class']) && $u['role']==='teacher') ? "<div style='font-size:12px;color:var(--maroon);margin-top:2px;font-weight:700'><i class='fas fa-chalkboard-teacher' style='width:12px'></i> ".htmlspecialchars($u['advisory_class'])."</div>" : '';
    $activeLabel  = $u['is_active'] ? '' : "<span style='font-size:11px;color:#dc2626;font-weight:700'>[Inactive]</span>";
    $advisoryJson = json_encode($u['advisory_class'] ?? '', JSON_HEX_QUOT|JSON_HEX_APOS);
    
    $assignBtn = $u['role']==='teacher'
        ? "<button class='btn-icon btn-advisory' onclick='openAssignAdvisory({$u['id']},\"$name\",$advisoryJson)' title='Assign Advisory Class'><i class='fas fa-chalkboard-teacher'></i></button>"
        : '';
        
    $rawNameAttr  = htmlspecialchars($u['first_name'].' '.$u['last_name'], ENT_QUOTES);
    $rawEmailAttr = htmlspecialchars($u['email'], ENT_QUOTES);
    $rawRoleAttr  = htmlspecialchars($roleLabel, ENT_QUOTES);
    
    $resetBtn = "<button type='button' class='btn-icon btn-action-reset' id='btn-reset-pw-{$u['id']}' onclick='openAdminResetPasswordModal({$u['id']})' data-name=\"$rawNameAttr\" data-email=\"$rawEmailAttr\" data-role=\"$rawRoleAttr\" title='Reset Password'><i class='fas fa-key'></i></button>";
    
    $totpRequested = !empty($u['totp_reset_requested']);
    $totpEnabled   = !empty($u['totp_enabled']);
    $totpBadge = $totpRequested 
        ? "<div class='totp-req-tag' style='font-size:11px;background:rgba(245,158,11,0.18);border:1px solid rgba(245,158,11,0.4);color:#f59e0b;font-weight:700;padding:2px 8px;border-radius:6px;margin-top:5px;display:inline-flex;align-items:center;gap:4px;'><i class='fas fa-shield-virus' style='color:#f59e0b'></i> 2FA Reset Requested</div>"
        : '';
        
    $totpIcon  = $totpRequested ? 'fa-shield-virus' : 'fa-shield-alt';
    $totpClass = $totpRequested ? 'btn-action-totp totp-pending' : 'btn-action-totp';
    $totpTitle = $totpRequested ? '2FA Reset Requested by User - Click to Reset Authenticator' : 'Reset Two-Factor Authentication (2FA)';

    $totpBtn = "<button class='btn-icon $totpClass' id='btn-reset-totp-{$u['id']}' onclick='openResetTotpModal({$u['id']}, this.dataset.name, this.dataset.email, this.dataset.role, ".($totpEnabled?1:0).", ".($totpRequested?1:0).")' data-name=\"$rawNameAttr\" data-email=\"$rawEmailAttr\" data-role=\"$rawRoleAttr\" title='$totpTitle'><i class='fas $totpIcon'></i></button>";
    
    $statusBtn = $u['is_active']
        ? "<button class='btn-icon btn-action-deactivate danger' id='btn-status-{$u['id']}' onclick='openDeactivateConfirmModal({$u['id']},\"$name\",\"$email\")' title='Deactivate Account'><i class='fas fa-user-slash'></i></button>"
        : "<button class='btn-icon btn-action-activate' id='btn-status-{$u['id']}' onclick='openActivateConfirmModal({$u['id']},\"$name\",\"$email\")' title='Activate Account'><i class='fas fa-user-check'></i></button>";

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
/* Explicit dark mode overrides for user management cards */
[data-theme="dark"] .user-card {
  background: var(--bg2) !important;
  background-color: #1e1e1e !important;
  border-color: var(--border) !important;
  color: var(--text) !important;
}
[data-theme="dark"] .user-card-name {
  color: var(--text) !important;
}
[data-theme="dark"] .status-select {
  background: var(--bg) !important;
  color: var(--text) !important;
  border-color: var(--border) !important;
}

/* User Card Action Buttons — High Contrast & Clear Icons in Light & Dark Modes */
.user-card-actions {
  display: flex !important;
  gap: 10px !important;
  margin-top: 10px !important;
  align-items: center !important;
  justify-content: center !important;
}
.user-card-actions .btn-icon {
  width: 38px !important;
  height: 38px !important;
  border-radius: 10px !important;
  display: inline-flex !important;
  align-items: center !important;
  justify-content: center !important;
  font-size: 15px !important;
  cursor: pointer !important;
  transition: all 0.2s ease !important;
  position: relative !important;
  text-decoration: none !important;
  border: 1px solid var(--border) !important;
}
.user-card-actions .btn-icon i {
  font-size: 15px !important;
  display: inline-block !important;
  pointer-events: none !important;
  line-height: 1 !important;
}

/* Button color themes - Light mode */
.btn-action-totp {
  background: #eff6ff !important;
  border-color: #bfdbfe !important;
}
.btn-action-totp i {
  color: #2563eb !important;
}
.btn-action-reset {
  background: #fefce8 !important;
  border-color: #fef08a !important;
}
.btn-action-reset i {
  color: #ca8a04 !important;
}
.btn-action-deactivate {
  background: #fef2f2 !important;
  border-color: #fecaca !important;
}
.btn-action-deactivate i {
  color: #dc2626 !important;
}
.btn-action-activate {
  background: #f0fdf4 !important;
  border-color: #bbf7d0 !important;
}
.btn-action-activate i {
  color: #16a34a !important;
}
.btn-advisory {
  background: #faf5ff !important;
  border-color: #e9d5ff !important;
}
.btn-advisory i {
  color: #7c3aed !important;
}

/* Button color themes - Dark mode (Illuminated badges with vibrant icons) */
[data-theme="dark"] .user-card-actions .btn-icon {
  box-shadow: 0 2px 6px rgba(0,0,0,0.5) !important;
}
[data-theme="dark"] .btn-action-totp {
  background: rgba(37, 99, 235, 0.22) !important;
  border-color: rgba(96, 165, 250, 0.45) !important;
}
[data-theme="dark"] .btn-action-totp i {
  color: #60a5fa !important;
}
[data-theme="dark"] .btn-action-totp.totp-pending {
  background: rgba(245, 158, 11, 0.25) !important;
  border-color: #f59e0b !important;
  animation: pulseTotp 2s infinite !important;
}
[data-theme="dark"] .btn-action-totp.totp-pending i {
  color: #fbbf24 !important;
}
[data-theme="dark"] .btn-action-reset {
  background: rgba(234, 179, 8, 0.2) !important;
  border-color: rgba(250, 204, 21, 0.45) !important;
}
[data-theme="dark"] .btn-action-reset i {
  color: #facc15 !important;
}
[data-theme="dark"] .btn-action-deactivate {
  background: rgba(239, 68, 68, 0.22) !important;
  border-color: rgba(248, 113, 113, 0.45) !important;
}
[data-theme="dark"] .btn-action-deactivate i {
  color: #fca5a5 !important;
}
[data-theme="dark"] .btn-action-activate {
  background: rgba(34, 197, 94, 0.22) !important;
  border-color: rgba(74, 222, 128, 0.45) !important;
}
[data-theme="dark"] .btn-action-activate i {
  color: #86efac !important;
}
[data-theme="dark"] .btn-advisory {
  background: rgba(147, 51, 234, 0.22) !important;
  border-color: rgba(192, 132, 252, 0.45) !important;
}
[data-theme="dark"] .btn-advisory i {
  color: #c084fc !important;
}
.user-card-actions .btn-icon:hover {
  transform: translateY(-2px) scale(1.08) !important;
  filter: brightness(1.2) !important;
}

/* Modal Viewport Centering & Leveling */
.modal-overlay {
  position: fixed !important;
  top: 0 !important;
  left: 0 !important;
  right: 0 !important;
  bottom: 0 !important;
  width: 100vw !important;
  height: 100vh !important;
  z-index: 99999 !important;
  display: flex !important;
  align-items: center !important;
  justify-content: center !important;
  padding: 20px !important;
  box-sizing: border-box !important;
  overflow-y: auto !important;
}
.modal-overlay.hidden {
  display: none !important;
}
.modal-overlay .modal-box {
  margin: auto !important;
  max-height: calc(100vh - 40px) !important;
  overflow-y: auto !important;
  box-sizing: border-box !important;
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
      <button class="btn-sm-primary" style="background:linear-gradient(135deg, #1e40af, #2563eb);border-color:#1e40af;font-weight:700" onclick="openAddStudentModal()"><i class="fas fa-user-graduate"></i> Register Walk-in Student</button>
      <button class="btn-sm-outline" onclick="openAddUserModal()"><i class="fas fa-user-plus"></i> Add User</button>
    </div>
  </div>
  <div class="users-grid" style="padding-top:8px">$cards</div>
</div>

<!-- Add User Modal -->
<div class="modal-overlay hidden" id="addUserModal" onclick="closeAddUser(event)">
  <div class="modal-box" style="max-width:520px">
    <button class="modal-close" onclick="closeAddUser(event)"><i class="fas fa-times"></i></button>
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
      <div class="form-group">
        <label id="nu-idnum-label">Student LRN (12 Digits) <span class="req">*</span></label>
        <div class="input-wrap"><i class="fas fa-id-card"></i>
          <input type="text" id="nu-idnum" placeholder="Enter 12-digit LRN (Numbers only)" maxlength="12" inputmode="numeric" pattern="[0-9]{12}" oninput="if(document.getElementById('nu-role').value==='student') this.value = this.value.replace(/[^0-9]/g, '').slice(0, 12);"/>
        </div>
        <small id="nu-idnum-help" style="font-size:11px;color:var(--text-3);margin-top:4px;display:block;">Must be strictly 12 numeric digits.</small>
      </div>
      <div class="form-group"><label>Email Address <span class="req">*</span></label><div class="input-wrap"><i class="fas fa-envelope"></i><input type="email" id="nu-email" placeholder="email@example.com"/></div></div>
      <div class="form-group">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:6px">
          <label style="margin:0">Temporary Password <span class="req">*</span></label>
          <button type="button" onclick="document.getElementById('nu-pass').value='Student@2026'" style="font-size:11px;color:var(--maroon);background:none;border:none;cursor:pointer;font-weight:700">Preset: Student@2026</button>
        </div>
        <div class="input-wrap"><i class="fas fa-lock"></i><input type="text" id="nu-pass" placeholder="Min 8 chars, 1 uppercase, 1 number, 1 special char"/></div>
        <small style="font-size:11px;color:var(--text-3);margin-top:4px;display:block;">Students will complete initial 2FA authenticator setup on their first sign-in.</small>
      </div>
      <div class="form-actions">
        <button type="button" class="btn-secondary" onclick="closeAddUser(event)">Cancel</button>
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
      <div style="background:var(--bg2);border:1px dashed var(--border);border-radius:10px;padding:10px 14px;margin-bottom:14px;display:flex;align-items:center;justify-content:space-between;font-size:12px;gap:8px">
        <span style="color:var(--text-2)"><i class="fas fa-lightbulb" style="color:#eab308;margin-right:4px"></i> Default suggestion: <strong style="font-family:monospace;background:var(--bg);padding:2px 6px;border-radius:4px;border:1px solid var(--border)">GNHS@2026</strong></span>
        <button type="button" onclick="useDefaultPassword('GNHS@2026')" style="padding:4px 10px;font-size:11px;border-radius:6px;border:1px solid var(--border);background:var(--bg2);cursor:pointer;font-weight:700;color:var(--maroon);white-space:nowrap">Use Default</button>
      </div>

      <!-- Backup for Forgotten Startup Questions Checkbox -->
      <div style="background:var(--bg2);border:1px solid var(--border);border-radius:10px;padding:12px 14px;margin-bottom:18px;text-align:left">
        <label style="display:flex;align-items:flex-start;gap:10px;cursor:pointer;margin:0;font-size:13px;font-weight:600;color:var(--text)">
          <input type="checkbox" id="reset-clear-sec-questions" style="margin-top:3px;accent-color:var(--maroon);width:16px;height:16px" checked />
          <div>
            <div style="display:flex;align-items:center;gap:6px">
              <i class="fas fa-shield-halved" style="color:var(--maroon);font-size:13px"></i>
              <span id="resetClearQuestionsTitle">Reset Startup Security Questions (Backup)</span>
            </div>
            <div id="resetClearQuestionsSub" style="font-size:11.5px;color:var(--text-3);font-weight:400;margin-top:3px;line-height:1.4">
              Backup for students who forgot their password and security questions on startup. Clears their forgotten questions and prompts them to choose fresh questions upon next login.
            </div>
          </div>
        </label>
      </div>

      <div class="form-actions" style="display:flex;gap:10px;justify-content:flex-end">
        <button type="button" class="btn-secondary" onclick="closeResetPasswordModal()">Cancel</button>
        <button type="button" class="btn-primary" id="saveResetPwBtn" onclick="submitResetPassword()"><i class="fas fa-key"></i> Reset Password</button>
      </div>
    </div>

    <!-- Success Feedback Card with Copy Option -->
    <div id="resetSuccessCard" style="display:none;text-align:left;margin-top:10px">
      <div style="background:rgba(22,163,74,0.12);border:1px solid rgba(22,163,74,0.3);border-radius:12px;padding:16px;">
        <div style="display:flex;align-items:center;gap:8px;color:#22c55e;font-weight:700;font-size:14px;margin-bottom:8px">
          <i class="fas fa-check-circle" style="font-size:16px"></i> Password Successfully Updated!
        </div>
        <p style="font-size:12px;color:var(--text-2);margin:0 0 10px 0">
          The user can now log in using the new password below. Make sure to share it with them:
        </p>
        <div style="display:flex;align-items:center;gap:8px;background:var(--bg2);border:1px solid var(--border);border-radius:8px;padding:8px 12px;">
          <input type="text" id="resetSavedPasswordDisplay" readonly style="border:none;outline:none;background:transparent;font-family:monospace;font-size:15px;font-weight:800;color:var(--text);flex:1;letter-spacing:1px" />
          <button type="button" id="copyPasswordBtn" onclick="copyResetPassword()" class="btn-sm-primary" style="padding:6px 12px;font-size:12px;display:flex;align-items:center;gap:6px">
            <i class="fas fa-copy"></i> <span id="copyBtnLabel">Copy</span>
          </button>
        </div>
        <div id="resetSecQuestionsNotice" style="font-size:11.5px;color:var(--text-2);background:rgba(22,163,74,0.08);padding:9px 12px;border-radius:8px;margin-top:10px;display:none;line-height:1.4">
          <i class="fas fa-info-circle"></i> <strong>Startup Questions Reset:</strong> The student's forgotten recovery questions have been cleared. When they log in with this password, they will be prompted on startup to set new recovery questions they will remember.
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
    <div class="modal-logo" style="background:rgba(217,119,6,0.15);color:#d97706"><i class="fas fa-shield-alt"></i></div>
    <h2 class="modal-title">Reset Two-Factor Authentication</h2>
    <p class="modal-sub" id="resetTotpUserTargetText">Unlink authenticator app for this user</p>

    <!-- User Mini Badge Card inside modal -->
    <div style="background:var(--bg2);border:1px solid var(--border);border-radius:12px;padding:12px 14px;margin-bottom:16px;display:flex;align-items:center;gap:12px;text-align:left;">
      <div style="width:40px;height:40px;border-radius:10px;background:rgba(217,119,6,0.15);color:#d97706;display:flex;align-items:center;justify-content:center;font-size:16px;flex-shrink:0;">
        <i class="fas fa-user-shield"></i>
      </div>
      <div style="overflow:hidden;flex:1">
        <div id="totpTargetName" style="font-weight:700;font-size:14px;color:var(--text);white-space:nowrap;overflow:hidden;text-overflow:ellipsis">User Name</div>
        <div id="totpTargetSub" style="font-size:12px;color:var(--text-3);white-space:nowrap;overflow:hidden;text-overflow:ellipsis">user@example.com</div>
      </div>
      <span id="totpTargetRoleBadge" class="user-card-role role-student" style="font-size:11px;padding:3px 10px;flex-shrink:0">Student</span>
    </div>

    <!-- 2FA Current Status Indicator -->
    <div id="totpStatusIndicator" style="background:var(--bg2);border:1px solid var(--border);border-radius:10px;padding:10px 14px;margin-bottom:16px;text-align:left;font-size:13px;">
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

<!-- Deactivate Account Confirmation Modal (Replaces browser alert) -->
<div class="modal-overlay hidden" id="deactivateConfirmModal" onclick="if(event.target.id==='deactivateConfirmModal')closeDeactivateConfirmModal()">
  <div class="modal-box" style="max-width:440px;text-align:center;padding:28px 24px;">
    <button class="modal-close" onclick="closeDeactivateConfirmModal()"><i class="fas fa-times"></i></button>
    <div style="width:60px;height:60px;border-radius:20px;background:rgba(220,38,38,0.14);color:#ef4444;display:flex;align-items:center;justify-content:center;font-size:26px;margin:0 auto 16px;border:1px solid rgba(220,38,38,0.25);">
      <i class="fas fa-user-slash"></i>
    </div>
    <h3 style="font-size:19px;font-weight:800;color:var(--text);margin-bottom:8px">Deactivate Account</h3>
    <p style="font-size:13.5px;color:var(--text-2);line-height:1.5;margin-bottom:20px" id="deactivateConfirmText">
      Are you sure you want to deactivate this account?
    </p>
    <div style="background:var(--bg2);border:1px solid var(--border);border-radius:10px;padding:12px;margin-bottom:22px;display:flex;align-items:center;gap:12px;text-align:left;">
      <div id="deactivateUserAvatar" class="avatar-circle" style="width:40px;height:40px;font-size:14px;flex-shrink:0;">U</div>
      <div style="overflow:hidden;flex:1">
        <div id="deactivateUserName" style="font-weight:700;font-size:14px;color:var(--text);white-space:nowrap;overflow:hidden;text-overflow:ellipsis">User Name</div>
        <div id="deactivateUserEmail" style="font-size:12px;color:var(--text-3);white-space:nowrap;overflow:hidden;text-overflow:ellipsis">user@example.com</div>
      </div>
    </div>
    <div style="display:flex;gap:10px;justify-content:center">
      <button type="button" class="btn-secondary" style="flex:1;padding:11px" onclick="closeDeactivateConfirmModal()">Cancel</button>
      <button type="button" class="btn-primary" id="confirmDeactivateBtn" style="flex:1;padding:11px;background:#dc2626;border-color:#b91c1c" onclick="submitDeactivateUser()">
        <i class="fas fa-user-slash"></i> Deactivate
      </button>
    </div>
  </div>
</div>

<!-- Activate Account Confirmation Modal -->
<div class="modal-overlay hidden" id="activateConfirmModal" onclick="if(event.target.id==='activateConfirmModal')closeActivateConfirmModal()">
  <div class="modal-box" style="max-width:440px;text-align:center;padding:28px 24px;">
    <button class="modal-close" onclick="closeActivateConfirmModal()"><i class="fas fa-times"></i></button>
    <div style="width:60px;height:60px;border-radius:20px;background:rgba(22,163,74,0.14);color:#22c55e;display:flex;align-items:center;justify-content:center;font-size:26px;margin:0 auto 16px;border:1px solid rgba(22,163,74,0.25);">
      <i class="fas fa-user-check"></i>
    </div>
    <h3 style="font-size:19px;font-weight:800;color:var(--text);margin-bottom:8px">Activate Account</h3>
    <p style="font-size:13.5px;color:var(--text-2);line-height:1.5;margin-bottom:20px" id="activateConfirmText">
      Are you sure you want to reactivate this account?
    </p>
    <div style="background:var(--bg2);border:1px solid var(--border);border-radius:10px;padding:12px;margin-bottom:22px;display:flex;align-items:center;gap:12px;text-align:left;">
      <div id="activateUserAvatar" class="avatar-circle" style="width:40px;height:40px;font-size:14px;flex-shrink:0;">U</div>
      <div style="overflow:hidden;flex:1">
        <div id="activateUserName" style="font-weight:700;font-size:14px;color:var(--text);white-space:nowrap;overflow:hidden;text-overflow:ellipsis">User Name</div>
        <div id="activateUserEmail" style="font-size:12px;color:var(--text-3);white-space:nowrap;overflow:hidden;text-overflow:ellipsis">user@example.com</div>
      </div>
    </div>
    <div style="display:flex;gap:10px;justify-content:center">
      <button type="button" class="btn-secondary" style="flex:1;padding:11px" onclick="closeActivateConfirmModal()">Cancel</button>
      <button type="button" class="btn-primary" id="confirmActivateBtn" style="flex:1;padding:11px;background:#16a34a;border-color:#15803d" onclick="submitActivateUser()">
        <i class="fas fa-user-check"></i> Activate Account
      </button>
    </div>
  </div>
</div>

<script>
// ── Local UI Helpers (self-contained to prevent any load order issues) ──
function hideEl(id) {
  const el = document.getElementById(id);
  if (el) el.style.display = 'none';
}
function showMsg(id, msg, type = 'error') {
  const el = document.getElementById(id);
  if (!el) return;
  el.textContent = msg;
  el.className = type === 'error' ? 'error-msg' : 'success-msg';
  el.style.display = 'block';
}
function setLoading(btn, on) {
  if (!btn) return;
  if (on) {
    btn.disabled = true;
    btn.dataset.orig = btn.innerHTML;
    btn.innerHTML = '<span class="spinner"></span>&nbsp;Please wait…';
  } else {
    btn.disabled = false;
    btn.innerHTML = btn.dataset.orig || btn.innerHTML;
  }
}
function showToast(msg, type) {
  type = type || 'success';
  let c = document.getElementById('toast-container');
  if (!c) {
    c = document.createElement('div');
    c.id = 'toast-container';
    document.body.appendChild(c);
  }
  const t = document.createElement('div');
  t.className = 'toast toast-' + type;
  const icons = { success: 'check-circle', error: 'times-circle', info: 'info-circle' };
  const iconName = icons[type] || 'info-circle';
  t.innerHTML = '<i class="fas fa-' + iconName + '"></i>' + msg;
  c.appendChild(t);
  setTimeout(() => {
    t.style.opacity = '0';
    t.style.transition = 'opacity .3s';
    setTimeout(() => t.remove(), 300);
  }, 3200);
}
function togglePass(inputId) {
  const inp = document.getElementById(inputId);
  if (!inp) return;
  const isPass = inp.type === 'password';
  inp.type = isPass ? 'text' : 'password';
  const btn = inp.nextElementSibling;
  if (btn) {
    const icon = btn.querySelector('i') || btn;
    if (icon) icon.className = isPass ? 'fas fa-eye-slash' : 'fas fa-eye';
  }
}

// ── Deactivate / Activate Confirmation Modal Handlers ──
let _targetDeactivateUserId = null;
let _targetActivateUserId = null;

function openDeactivateConfirmModal(userId, name, email) {
  _targetDeactivateUserId = userId;
  const userCard = document.querySelector('[data-user-id="' + userId + '"]');
  email = email || userCard?.querySelector('div[style*="font-size:12px;color:var(--text-3)"]')?.textContent || '';
  
  const nameEl = document.getElementById('deactivateUserName');
  const emailEl = document.getElementById('deactivateUserEmail');
  const descEl = document.getElementById('deactivateConfirmText');
  const avatarEl = document.getElementById('deactivateUserAvatar');
  
  if (nameEl) nameEl.textContent = name;
  if (emailEl) emailEl.textContent = email;
  if (descEl) descEl.innerHTML = 'Are you sure you want to deactivate the account of <strong>' + (name || 'this user') + '</strong>? They will be barred from signing into the system until reactivated.';
  if (avatarEl) avatarEl.textContent = name ? name[0].toUpperCase() : 'U';

  const modal = document.getElementById('deactivateConfirmModal');
  if (modal) {
    if (modal.parentElement !== document.body) document.body.appendChild(modal);
    modal.classList.remove('hidden');
    modal.style.display = 'flex';
    document.body.style.overflow = 'hidden';
  }
}

function closeDeactivateConfirmModal() {
  const modal = document.getElementById('deactivateConfirmModal');
  if (modal) {
    modal.classList.add('hidden');
    modal.style.display = 'none';
    document.body.style.overflow = '';
  }
  _targetDeactivateUserId = null;
}

async function submitDeactivateUser() {
  if (!_targetDeactivateUserId) return;
  const btn = document.getElementById('confirmDeactivateBtn');
  if (btn) btn.disabled = true;
  try {
    const isNested = window.location.pathname.includes('/gnhs-guidance/gnhs-guidance/');
    const url = (isNested ? '/gnhs-guidance/gnhs-guidance' : '/gnhs-guidance') + '/api/update_user.php?action=deactivate&id=' + _targetDeactivateUserId;
    const r = await fetch(url);
    const d = await r.json();
    if (d.success) {
      showToast('Account deactivated successfully.', 'info');
      closeDeactivateConfirmModal();
      setTimeout(() => location.reload(), 1000);
    } else {
      showToast(d.message || 'Failed to deactivate account.', 'error');
    }
  } catch (err) {
    showToast('Network error while deactivating.', 'error');
  } finally {
    if (btn) btn.disabled = false;
  }
}

function openActivateConfirmModal(userId, name, email) {
  _targetActivateUserId = userId;
  const userCard = document.querySelector('[data-user-id="' + userId + '"]');
  email = email || userCard?.querySelector('div[style*="font-size:12px;color:var(--text-3)"]')?.textContent || '';
  
  const nameEl = document.getElementById('activateUserName');
  const emailEl = document.getElementById('activateUserEmail');
  const descEl = document.getElementById('activateConfirmText');
  const avatarEl = document.getElementById('activateUserAvatar');
  
  if (nameEl) nameEl.textContent = name;
  if (emailEl) emailEl.textContent = email;
  if (descEl) descEl.innerHTML = 'Are you sure you want to reactivate the account of <strong>' + (name || 'this user') + '</strong>? They will immediately regain sign-in access.';
  if (avatarEl) avatarEl.textContent = name ? name[0].toUpperCase() : 'U';

  const modal = document.getElementById('activateConfirmModal');
  if (modal) {
    if (modal.parentElement !== document.body) document.body.appendChild(modal);
    modal.classList.remove('hidden');
    modal.style.display = 'flex';
    document.body.style.overflow = 'hidden';
  }
}

function closeActivateConfirmModal() {
  const modal = document.getElementById('activateConfirmModal');
  if (modal) {
    modal.classList.add('hidden');
    modal.style.display = 'none';
    document.body.style.overflow = '';
  }
  _targetActivateUserId = null;
}

async function submitActivateUser() {
  if (!_targetActivateUserId) return;
  const btn = document.getElementById('confirmActivateBtn');
  if (btn) btn.disabled = true;
  try {
    const isNested = window.location.pathname.includes('/gnhs-guidance/gnhs-guidance/');
    const url = (isNested ? '/gnhs-guidance/gnhs-guidance' : '/gnhs-guidance') + '/api/update_user.php?action=activate&id=' + _targetActivateUserId;
    const r = await fetch(url);
    const d = await r.json();
    if (d.success) {
      showToast('Account activated successfully.', 'success');
      closeActivateConfirmModal();
      setTimeout(() => location.reload(), 1000);
    } else {
      showToast(d.message || 'Failed to activate account.', 'error');
    }
  } catch (err) {
    showToast('Network error while activating.', 'error');
  } finally {
    if (btn) btn.disabled = false;
  }
}

window.openDeactivateConfirmModal = openDeactivateConfirmModal;
window.deleteUser = openDeactivateConfirmModal;
window.openActivateConfirmModal = openActivateConfirmModal;
window.activateUser = openActivateConfirmModal;

// ── Modal Positioning & Viewport Safeguards ──
function attachModalsToBody() {
  const modalIds = ['addUserModal', 'advisoryModal', 'resetPasswordModal', 'resetTotpModal', 'deactivateConfirmModal', 'activateConfirmModal'];
  modalIds.forEach(function(id) {
    const el = document.getElementById(id);
    if (el && el.parentElement !== document.body) {
      document.body.appendChild(el);
    }
  });
}
if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', attachModalsToBody);
} else {
  attachModalsToBody();
}

function openAddUserModal() {
  const m = document.getElementById('addUserModal');
  if (m) {
    if (m.parentElement !== document.body) document.body.appendChild(m);
    m.classList.remove('hidden');
    m.style.display = 'flex';
    document.body.style.overflow = 'hidden';
    const box = m.querySelector('.modal-box');
    if (box) box.scrollTop = 0;
    const modalTitle = m.querySelector('.modal-title');
    if (modalTitle) modalTitle.innerHTML = '<i class="fas fa-user-plus" style="margin-right:6px"></i> Add New User';
  }
}

function closeAddUser(e) {
  if (!e || e.target.id === 'addUserModal' || e.target.closest('.modal-close') || (e.target.tagName === 'BUTTON' && e.target.innerText.trim() === 'Cancel')) {
    const m = document.getElementById('addUserModal');
    if (m) {
      m.classList.add('hidden');
      m.style.display = 'none';
      document.body.style.overflow = '';
    }
  }
}
window.openAddUserModal = openAddUserModal;
window.closeAddUser = closeAddUser;

// ── Walk-in Student Registration Helper ──
function openAddStudentModal() {
  openAddUserModal();
  const roleSel = document.getElementById('nu-role');
  if (roleSel) {
    roleSel.value = 'student';
    toggleGrade('student');
  }
  const modalTitle = document.querySelector('#addUserModal .modal-title');
  if (modalTitle) modalTitle.innerHTML = '<i class="fas fa-user-graduate" style="color:#2563eb;margin-right:6px"></i> Register Walk-in Student';
  
  const passInp = document.getElementById('nu-pass');
  if (passInp && !passInp.value) {
    passInp.value = 'Student@2026';
  }
  const box = document.querySelector('#addUserModal .modal-box');
  if (box) box.scrollTop = 0;
  setTimeout(() => document.getElementById('nu-first')?.focus(), 150);
}
window.openAddStudentModal = openAddStudentModal;

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
  const modal = document.getElementById('advisoryModal');
  if (modal) {
    if (modal.parentElement !== document.body) document.body.appendChild(modal);
    modal.classList.remove('hidden');
    modal.style.display = 'flex';
    document.body.style.overflow = 'hidden';
  }
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
  const idLabel = document.getElementById('nu-idnum-label');
  const idInput = document.getElementById('nu-idnum');
  const idHelp  = document.getElementById('nu-idnum-help');
  if (idInput) {
    if (role === 'student') {
      if (idLabel) idLabel.innerHTML = 'Student LRN (12 Digits) <span class="req">*</span>';
      idInput.placeholder = 'Enter 12-digit LRN (Numbers only)';
      idInput.setAttribute('maxlength', '12');
      idInput.setAttribute('inputmode', 'numeric');
      idInput.setAttribute('pattern', '[0-9]{12}');
      if (idHelp) { idHelp.style.display = 'block'; }
    } else {
      if (idLabel) idLabel.innerHTML = (role === 'teacher' ? 'Employee Number' : 'Employee / Staff ID') + ' <span class="req">*</span>';
      idInput.placeholder = 'e.g. EMP-001 or IT-001';
      idInput.removeAttribute('maxlength');
      idInput.removeAttribute('inputmode');
      idInput.removeAttribute('pattern');
      if (idHelp) { idHelp.style.display = 'none'; }
    }
  }
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

function openAdminResetPasswordModal(userId, name, email, role) {
  try {
    _resetTargetUserId = userId;
    _resetLastPassword = '';

    const btn = document.getElementById('btn-reset-pw-' + userId);
    if (btn) {
      name = name || btn.getAttribute('data-name') || btn.dataset.name;
      email = email || btn.getAttribute('data-email') || btn.dataset.email;
      role = role || btn.getAttribute('data-role') || btn.dataset.role;
    }
    name = name || 'User';
    email = email || '';
    role = role || 'Student';

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

    const chkQuestions = document.getElementById('reset-clear-sec-questions');
    const qTitle = document.getElementById('resetClearQuestionsTitle');
    const qSub = document.getElementById('resetClearQuestionsSub');
    const isStudent = !role || role.toLowerCase() === 'student';
    if (chkQuestions) {
      chkQuestions.checked = isStudent;
    }
    if (qTitle && qSub) {
      if (isStudent) {
        qTitle.textContent = 'Reset Startup Security Questions (Backup)';
        qSub.textContent = 'Backup for students who forgot their password and security questions on startup. Clears their forgotten questions and prompts them to choose fresh questions upon next login.';
      } else {
        qTitle.textContent = 'Reset Security Recovery Questions';
        qSub.textContent = 'Clears security recovery questions so user can configure new ones upon their next login.';
      }
    }

    const p1 = document.getElementById('reset-new-pass');
    const p2 = document.getElementById('reset-confirm-pass');
    if (p1) { p1.value = ''; p1.type = 'password'; }
    if (p2) { p2.value = ''; p2.type = 'password'; }

    document.querySelectorAll('#resetPwForm .toggle-pass i').forEach(function(i) {
      i.className = 'fas fa-eye';
    });

    const msgEl = document.getElementById('resetPwMsg');
    if (msgEl) { msgEl.style.display = 'none'; msgEl.textContent = ''; }
    const hint = document.getElementById('reset-match-hint');
    if (hint) { hint.style.display = 'none'; hint.textContent = ''; }

    const form = document.getElementById('resetPwForm');
    if (form) form.style.display = 'block';
    const sc = document.getElementById('resetSuccessCard');
    if (sc) sc.style.display = 'none';

    const modal = document.getElementById('resetPasswordModal');
    if (modal) {
      if (modal.parentElement !== document.body) document.body.appendChild(modal);
      modal.classList.remove('hidden');
      modal.style.display = 'flex';
      document.body.style.overflow = 'hidden';
      setTimeout(() => p1?.focus(), 150);
    }
  } catch (err) {
    console.error('Error opening reset password modal:', err);
    const modal = document.getElementById('resetPasswordModal');
    if (modal) {
      if (modal.parentElement !== document.body) document.body.appendChild(modal);
      modal.classList.remove('hidden');
      modal.style.display = 'flex';
      document.body.style.overflow = 'hidden';
    }
  }
}
window.openAdminResetPasswordModal = openAdminResetPasswordModal;

function closeResetPasswordModal(e) {
  if (!e || e.target.id === 'resetPasswordModal' || e.target.closest('.modal-close') || (e.target.tagName==='BUTTON' && (e.target.innerText.trim()==='Cancel' || e.target.innerText.trim()==='Done'))) {
    const modal = document.getElementById('resetPasswordModal');
    if (modal) {
      modal.classList.add('hidden');
      modal.style.display = 'none';
      document.body.style.overflow = '';
    }
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
  const clearQuestions = document.getElementById('reset-clear-sec-questions')?.checked ? 1 : 0;
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

  const isNested = window.location.pathname.includes('/gnhs-guidance/gnhs-guidance/');
  const endpoint = isNested
    ? '/gnhs-guidance/gnhs-guidance/api/admin_reset_password.php'
    : '/gnhs-guidance/api/admin_reset_password.php';

  try {
    const postFn = typeof apiPost === 'function' ? apiPost : async (url, data) => {
      const r = await fetch(url, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(data) });
      return r.json();
    };

    const res = await postFn(endpoint, {
      user_id: _resetTargetUserId,
      password: p1,
      reset_questions: clearQuestions
    });

    if (res && res.success) {
      showToast('Password reset successfully.', 'success');
      _resetLastPassword = p1;

      document.getElementById('resetPwForm').style.display = 'none';
      const sc = document.getElementById('resetSuccessCard');
      if (sc) {
        sc.style.display = 'block';
        document.getElementById('resetSavedPasswordDisplay').value = p1;
        document.getElementById('copyBtnLabel').textContent = 'Copy';
        const qNotice = document.getElementById('resetSecQuestionsNotice');
        if (qNotice) {
          qNotice.style.display = clearQuestions ? 'block' : 'none';
        }
      }
    } else {
      showMsg('resetPwMsg', (res && res.message) || 'Failed to reset password.');
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
  try {
    _totpTargetUserId = userId;

    const btn = document.getElementById('btn-reset-totp-' + userId);
    if (btn) {
      name = name || btn.getAttribute('data-name') || btn.dataset.name;
      email = email || btn.getAttribute('data-email') || btn.dataset.email;
      role = role || btn.getAttribute('data-role') || btn.dataset.role;
    }
    name = name || 'User';
    email = email || '';
    role = role || 'Student';

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
      statusEl.style.borderColor = 'rgba(245, 158, 11, 0.45)';
      statusEl.style.background = 'rgba(245, 158, 11, 0.15)';
      statusEl.innerHTML = '<span style="color:#f59e0b;font-weight:700"><i class="fas fa-exclamation-triangle"></i> Reset Requested:</span> <span style="color:var(--text-2)">This user reported losing access to their authenticator and requested a reset.</span>';
    } else if (isEnabled) {
      statusEl.style.borderColor = 'rgba(34, 197, 94, 0.45)';
      statusEl.style.background = 'rgba(34, 197, 94, 0.15)';
      statusEl.innerHTML = '<span style="color:#22c55e;font-weight:700"><i class="fas fa-check-circle"></i> 2FA is Active:</span> <span style="color:var(--text-2)">User has an authenticator app currently linked.</span>';
    } else {
      statusEl.style.borderColor = 'var(--border)';
      statusEl.style.background = 'var(--bg)';
      statusEl.innerHTML = '<span style="color:var(--text-3);font-weight:700"><i class="fas fa-info-circle"></i> 2FA Not Active:</span> <span style="color:var(--text-2)">User has not completed 2FA setup yet.</span>';
    }
  }

    const msgEl = document.getElementById('totpMsg');
    if (msgEl) { msgEl.style.display = 'none'; msgEl.textContent = ''; }
    const modal = document.getElementById('resetTotpModal');
    if (modal) {
      if (modal.parentElement !== document.body) document.body.appendChild(modal);
      modal.classList.remove('hidden');
      modal.style.display = 'flex';
      document.body.style.overflow = 'hidden';
    }
  } catch (err) {
    console.error('Error in openResetTotpModal:', err);
    const modal = document.getElementById('resetTotpModal');
    if (modal) {
      if (modal.parentElement !== document.body) document.body.appendChild(modal);
      modal.classList.remove('hidden');
      modal.style.display = 'flex';
      document.body.style.overflow = 'hidden';
    }
  }
}

function closeResetTotpModal(e) {
  if (!e || e.target.id === 'resetTotpModal' || e.target.closest('.modal-close') || (e.target.tagName==='BUTTON' && e.target.innerText.trim()==='Cancel')) {
    const modal = document.getElementById('resetTotpModal');
    if (modal) {
      modal.classList.add('hidden');
      modal.style.display = 'none';
      document.body.style.overflow = '';
    }
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
