<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/layout.php';
$user = requireLogin('admin');
$isTopAdmin = isTopAdminRole($user['role'] ?? '');

$pageTitle = $isTopAdmin ? 'Add Account' : 'Add Student';
$activeNav = 'add_student';

$pageCrumbLabel   = $isTopAdmin ? 'Add Account' : 'Add Student';
$headerIconClass  = $isTopAdmin ? 'fa-user-plus' : 'fa-user-graduate';
$headerTitle      = $isTopAdmin ? 'Create System / User Account' : 'Register Walk-in / New Student';
$headerSub        = $isTopAdmin
    ? 'Principal &amp; Developer Account Provisioning &bull; Student, Teacher, Guidance Admin &amp; Top Admin'
    : 'Guidance Office Student Enrollment Portal &bull; Official 12-Digit LRN Verification';
$singleModeBtnLbl = $isTopAdmin ? 'Single Account Creation' : 'Single Student Enrollment';
$sectionOneTitle  = $isTopAdmin ? '1. Account Personal Information' : '1. Student Personal Information';
$sectionTwoTitle  = $isTopAdmin ? '2. Role, ID / LRN &amp; Portal Account' : '2. DepEd LRN &amp; Portal Account';
$submitBtnLabel   = $isTopAdmin ? 'Create Account' : 'Register &amp; Create Student Account';
$succCardTitle    = $isTopAdmin ? 'Account Created Successfully!' : 'Student Enrolled Successfully!';
$succCardSub      = $isTopAdmin
    ? 'The new user account has been registered in the GNHS Guidance System.'
    : 'The student account has been registered into the GNHS Guidance System.';
$sidebarTitle     = $isTopAdmin ? 'Account Provisioning' : 'Guidance Enrollment';
$sidebarBody      = $isTopAdmin
    ? 'As Principal or Developer, you can create Student, Teacher, Guidance Counselor, or Top Admin accounts directly with immediate portal access.'
    : 'Walk-in students enrolled through this portal are instantly registered in official database records and can submit cases or request counseling sessions.';
$isTopAdminJs     = $isTopAdmin ? 'true' : 'false';

$roleSelectorHtml = $isTopAdmin ? <<<ROLEHTML
        <div class="as-form-row">
          <div class="as-form-group" style="margin-bottom:0">
            <label>Account Role <span class="req">*</span></label>
            <div class="as-input-wrap">
              <i class="fas fa-user-tag"></i>
              <select id="acctRole" onchange="onAccountRoleChange()" required>
                <option value="student">Student</option>
                <option value="teacher">Teacher</option>
                <option value="admin">Guidance Counselor (Guidance Admin)</option>
                <option value="super_admin">Top Admin (Principal / Developer)</option>
              </select>
              <i class="fas fa-chevron-down select-arrow"></i>
            </div>
          </div>
          <div class="as-form-group" id="acctPositionWrap" style="margin-bottom:0;display:none">
            <label>Top Admin Title / Position <span class="req">*</span></label>
            <div class="as-input-wrap">
              <i class="fas fa-crown"></i>
              <select id="acctPosition">
                <option value="Principal">Principal</option>
                <option value="Developer">Developer</option>
                <option value="IT Administrator">IT Administrator</option>
              </select>
              <i class="fas fa-chevron-down select-arrow"></i>
            </div>
          </div>
        </div>
ROLEHTML
: '<input type="hidden" id="acctRole" value="student" />';

$content = <<<HTML
<style>
  .as-wrapper {
    width: 100%;
    max-width: 1140px;
    margin: 0 auto;
    padding-bottom: 30px;
  }
  .as-breadcrumb {
    font-size: 13px;
    color: var(--text-3, #64748b);
    margin-bottom: 16px;
    display: flex;
    align-items: center;
    gap: 8px;
  }
  .as-breadcrumb a {
    color: var(--text-2, #475569);
    text-decoration: none;
    transition: color .2s;
  }
  .as-breadcrumb a:hover {
    color: var(--maroon, #550000);
  }
  .as-breadcrumb i {
    font-size: 11px;
    opacity: 0.6;
  }
  .as-grid {
    display: grid;
    grid-template-columns: minmax(0, 1fr) 320px;
    gap: 24px;
    align-items: start;
  }
  .as-main-card {
    background: #ffffff;
    border-radius: 14px;
    border: 1px solid var(--border, #e2e8f0);
    box-shadow: 0 4px 14px rgba(0,0,0,0.03);
    padding: 28px;
    position: relative;
  }
  .as-card-header {
    display: flex;
    align-items: center;
    gap: 16px;
    margin-bottom: 22px;
    padding-bottom: 18px;
    border-bottom: 1px solid var(--border, #e2e8f0);
  }
  .as-icon-badge {
    width: 52px;
    height: 52px;
    border-radius: 14px;
    background: var(--maroon-pale, #f8e8e6);
    color: var(--maroon, #550000);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 24px;
    flex-shrink: 0;
  }
  .as-card-header h2 {
    font-size: 21px;
    font-weight: 800;
    color: var(--text, #1e293b);
    margin: 0 0 4px;
  }
  .as-card-header p {
    font-size: 13px;
    color: var(--text-3, #64748b);
    margin: 0;
  }
  .as-section-tag {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 12.5px;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: .6px;
    color: var(--maroon, #550000);
    margin: 22px 0 12px;
    padding-bottom: 6px;
    border-bottom: 1px solid var(--border, #f1f5f9);
  }
  .as-section-tag:first-of-type {
    margin-top: 4px;
  }
  .as-form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 14px;
    margin-bottom: 14px;
  }
  .as-form-row-3 {
    display: grid;
    grid-template-columns: 2fr 1fr;
    gap: 14px;
    margin-bottom: 14px;
  }
  .as-form-group {
    margin-bottom: 14px;
  }
  .as-form-group label {
    display: block;
    font-size: 12.5px;
    font-weight: 700;
    color: var(--text, #1e293b);
    margin-bottom: 6px;
  }
  .as-form-group label .req {
    color: #dc2626;
    font-weight: 800;
  }
  .as-form-group label .opt {
    color: var(--text-3, #94a3b8);
    font-weight: 500;
    font-size: 11.5px;
  }
  .as-input-wrap {
    position: relative;
    display: flex;
    align-items: center;
  }
  .as-input-wrap > i {
    position: absolute;
    left: 14px;
    color: var(--text-3, #94a3b8);
    font-size: 14px;
    pointer-events: none;
    z-index: 1;
  }
  .as-input-wrap input,
  .as-input-wrap select {
    width: 100%;
    padding: 12px 14px 12px 42px;
    border: 1.5px solid var(--border, #cbd5e1);
    border-radius: 10px;
    font-size: 14px;
    color: var(--text, #1e293b);
    background: #ffffff;
    transition: all .2s ease;
    box-sizing: border-box;
    font-family: inherit;
  }
  .as-input-wrap select {
    appearance: none;
    cursor: pointer;
  }
  .as-input-wrap .select-arrow {
    position: absolute;
    right: 14px;
    color: var(--text-3, #94a3b8);
    pointer-events: none;
    font-size: 12px;
  }
  .as-input-wrap input:focus,
  .as-input-wrap select:focus {
    outline: none;
    border-color: var(--maroon, #550000);
    box-shadow: 0 0 0 3.5px rgba(85,0,0,0.08);
  }
  .as-helper {
    font-size: 11.5px;
    color: var(--text-3, #64748b);
    margin-top: 5px;
    display: flex;
    align-items: center;
    gap: 5px;
    line-height: 1.4;
  }
  .as-pwd-toggle {
    position: absolute;
    right: 12px;
    background: none;
    border: none;
    color: var(--text-3, #94a3b8);
    cursor: pointer;
    padding: 6px;
    font-size: 14px;
    transition: color .2s;
  }
  .as-pwd-toggle:hover {
    color: var(--maroon, #550000);
  }
  .as-pwd-checklist {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 8px 14px;
    padding: 12px 14px;
    background: var(--bg2, #f8fafc);
    border: 1px solid var(--border, #e2e8f0);
    border-radius: 10px;
    margin-top: 10px;
    font-size: 12px;
  }
  .as-pwd-rule {
    display: flex;
    align-items: center;
    gap: 7px;
    color: var(--text-3, #94a3b8);
    transition: color .2s;
  }
  .as-pwd-rule.valid {
    color: #16a34a;
    font-weight: 700;
  }
  .as-pwd-rule.valid i {
    color: #16a34a;
  }
  .as-pwd-rule.invalid i {
    color: var(--text-3, #cbd5e1);
  }
  .as-notice-banner {
    display: flex;
    gap: 12px;
    align-items: flex-start;
    padding: 13px 16px;
    background: #f0fdf4;
    border: 1px solid #bbf7d0;
    border-radius: 10px;
    color: #166534;
    font-size: 12.5px;
    line-height: 1.5;
    margin-bottom: 20px;
  }
  .as-notice-banner i {
    font-size: 16px;
    color: #16a34a;
    margin-top: 2px;
    flex-shrink: 0;
  }
  .as-actions {
    display: flex;
    align-items: center;
    justify-content: flex-end;
    gap: 12px;
    margin-top: 24px;
    padding-top: 20px;
    border-top: 1px solid var(--border, #e2e8f0);
  }
  .as-sidebar-card {
    background: #ffffff;
    border-radius: 14px;
    border: 1px solid var(--border, #e2e8f0);
    padding: 20px;
    margin-bottom: 18px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.02);
  }
  .as-sidebar-card h4 {
    font-size: 14px;
    font-weight: 800;
    margin: 0 0 10px;
    display: flex;
    align-items: center;
    gap: 9px;
    color: var(--text, #1e293b);
  }
  .as-sidebar-card p {
    font-size: 12.5px;
    color: var(--text-2, #475569);
    line-height: 1.6;
    margin: 0;
  }

  /* Result card after successful account creation */
  .as-success-card {
    display: none;
    background: #ffffff;
    border-radius: 14px;
    border: 1.5px solid #86efac;
    box-shadow: 0 10px 25px rgba(22, 163, 74, 0.08);
    padding: 28px;
    margin-bottom: 24px;
  }
  .as-success-header {
    display: flex;
    align-items: center;
    gap: 14px;
    margin-bottom: 18px;
    padding-bottom: 16px;
    border-bottom: 1px solid #dcfce7;
  }
  .as-success-badge {
    width: 48px;
    height: 48px;
    border-radius: 50%;
    background: #dcfce7;
    color: #15803d;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 22px;
    flex-shrink: 0;
  }
  .as-cred-box {
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    padding: 16px;
    margin: 16px 0;
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 12px;
  }
  .as-cred-item {
    font-size: 12.5px;
  }
  .as-cred-item label {
    display: block;
    font-size: 11px;
    color: var(--text-3, #64748b);
    text-transform: uppercase;
    font-weight: 700;
    margin-bottom: 2px;
  }
  .as-cred-item span {
    font-size: 13.5px;
    font-weight: 800;
    color: var(--text, #1e293b);
  }

  /* ── Fully Responsive Layout for Mobile, Tablet, Laptop, Desktop ── */
  @media (max-width: 1024px) {
    .as-grid {
      grid-template-columns: 1fr;
      gap: 20px;
    }
    .as-sidebar {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 16px;
    }
  }
  @media (max-width: 768px) {
    .as-main-card {
      padding: 20px 18px;
    }
    .as-sidebar {
      grid-template-columns: 1fr;
      gap: 14px;
    }
    .as-cred-box {
      grid-template-columns: 1fr;
      gap: 10px;
    }
    .as-pwd-checklist {
      grid-template-columns: 1fr;
      gap: 8px;
    }
  }
  @media (max-width: 600px) {
    .as-form-row,
    .as-form-row-3 {
      grid-template-columns: 1fr !important;
      gap: 12px;
    }
    .as-card-header {
      flex-direction: column;
      align-items: flex-start;
      gap: 12px;
    }
    .as-actions {
      flex-direction: column-reverse;
      gap: 10px;
    }
    .as-actions button,
    .as-actions a {
      width: 100%;
      text-align: center;
      justify-content: center;
      padding: 13px 16px;
    }
    .as-input-wrap input,
    .as-input-wrap select {
      font-size: 16px !important;
      min-height: 46px;
    }
  }

  /* Bulk CSV Import Styles */
  .as-mode-nav {
    display: flex;
    gap: 10px;
    margin-bottom: 22px;
    border-bottom: 1.5px solid var(--border, #e2e8f0);
    padding-bottom: 14px;
    flex-wrap: wrap;
  }
  .as-mode-btn {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 10px 18px;
    border-radius: 8px;
    font-size: 13.5px;
    font-weight: 700;
    border: 1px solid var(--border, #e2e8f0);
    background: var(--bg2, #f8fafc);
    color: var(--text-2, #475569);
    cursor: pointer;
    transition: all .2s;
  }
  .as-mode-btn:hover {
    background: #f1f5f9;
    color: var(--maroon, #550000);
  }
  .as-mode-btn.active {
    background: var(--maroon, #550000);
    color: #ffffff;
    border-color: var(--maroon, #550000);
    box-shadow: 0 3px 10px rgba(85,0,0,0.18);
  }
  .csv-dropzone {
    border: 2px dashed #cbd5e1;
    border-radius: 14px;
    padding: 40px 24px;
    text-align: center;
    background: var(--bg2, #f8fafc);
    cursor: pointer;
    transition: all .2s;
    user-select: none;
  }
  .csv-dropzone:hover, .csv-dropzone.dragover {
    border-color: var(--maroon, #550000);
    background: #fff5f5;
  }
  .csv-table-wrap {
    max-height: 420px;
    overflow-y: auto;
    border: 1px solid var(--border, #e2e8f0);
    border-radius: 10px;
    margin-top: 14px;
  }
  .csv-preview-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 12px;
  }
  .csv-preview-table th, .csv-preview-table td {
    padding: 9px 12px;
    border-bottom: 1px solid var(--border, #e2e8f0);
    text-align: left;
  }
  .csv-preview-table th {
    position: sticky;
    top: 0;
    background: #f8fafc;
    font-weight: 800;
    color: var(--text-2, #475569);
    z-index: 2;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    font-size: 11px;
  }
  .badge-valid {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 3px 8px;
    border-radius: 4px;
    font-size: 11px;
    font-weight: 700;
    background: #dcfce7;
    color: #15803d;
  }
  .badge-invalid {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 3px 8px;
    border-radius: 4px;
    font-size: 11px;
    font-weight: 700;
    background: #fee2e2;
    color: #b91c1c;
  }
</style>

<div class="as-wrapper">
  <!-- Breadcrumb -->
  <div class="as-breadcrumb">
    <a href="admin-dashboard.php"><i class="fas fa-home"></i> Dashboard</a>
    <i class="fas fa-chevron-right"></i>
    <a href="admin-users.php">User Accounts</a>
    <i class="fas fa-chevron-right"></i>
    <span style="color:var(--text);font-weight:700">{$pageCrumbLabel}</span>
  </div>

  <!-- Success Notification Box -->
  <div class="as-success-card" id="regSuccessCard">
    <div class="as-success-header">
      <div class="as-success-badge"><i class="fas fa-check"></i></div>
      <div>
        <h3 style="margin:0 0 4px;font-size:18px;font-weight:800;color:#15803d">{$succCardTitle}</h3>
        <p style="margin:0;font-size:12.5px;color:#166534">{$succCardSub}</p>
      </div>
    </div>
    <div class="as-cred-box">
      <div class="as-cred-item">
        <label>Full Name</label>
        <span id="succName">-</span>
      </div>
      <div class="as-cred-item">
        <label id="succRoleLabel">Account Role</label>
        <span id="succRole">Student</span>
      </div>
      <div class="as-cred-item">
        <label id="succIdLabel">12-Digit LRN / ID</label>
        <span id="succLrn" style="letter-spacing:1px;font-family:monospace">-</span>
      </div>
      <div class="as-cred-item">
        <label>Portal Email</label>
        <span id="succEmail">-</span>
      </div>
      <div class="as-cred-item" id="succClassWrap">
        <label>Grade &amp; Section</label>
        <span id="succClass">-</span>
      </div>
      <div class="as-cred-item">
        <label>Temporary Password</label>
        <span id="succPass" style="font-family:monospace;color:var(--maroon)">-</span>
      </div>
    </div>
    <div style="display:flex;gap:10px;flex-wrap:wrap">
      <button type="button" class="btn-primary" onclick="copyStudentCredentials()" id="btnCopyCreds" style="display:inline-flex;align-items:center;gap:6px">
        <i class="fas fa-copy"></i> Copy Credentials to Clipboard
      </button>
      <button type="button" class="btn-secondary" onclick="resetFormForNewStudent()" style="display:inline-flex;align-items:center;gap:6px">
        <i class="fas fa-user-plus"></i> Create Another Account
      </button>
      <a href="admin-users.php" class="btn-sm-outline" style="display:inline-flex;align-items:center;gap:6px;text-decoration:none;padding:10px 18px">
        <i class="fas fa-users-cog"></i> View in User Management
      </a>
    </div>
  </div>

  <div class="as-grid">
    <!-- Main Form Section -->
    <div class="as-main-card">
      <div class="as-card-header">
        <div class="as-icon-badge">
          <i class="fas {$headerIconClass}"></i>
        </div>
        <div>
          <h2>{$headerTitle}</h2>
          <p>{$headerSub}</p>
        </div>
      </div>

      <!-- Live Error / Notice Alert -->
      <div id="pageAlert" style="display:none;margin-bottom:18px"></div>

      <!-- Enrollment Mode Tabs -->
      <div class="as-mode-nav">
        <button type="button" class="as-mode-btn active" id="btnModeSingle" onclick="switchEnrollMode('single')">
          <i class="fas fa-user-plus"></i> {$singleModeBtnLbl}
        </button>
        <button type="button" class="as-mode-btn" id="btnModeBulk" onclick="openCsvImportModal()">
          <i class="fas fa-file-csv"></i> Bulk Import via CSV / Excel
        </button>
      </div>

      <!-- Single Account / Student Section -->
      <div id="singleEnrollSection">
      <form id="studentEnrollForm" onsubmit="handleStudentEnroll(event)">
        <!-- 1. Personal Information -->
        <div class="as-section-tag">
          <i class="fas fa-id-card"></i> {$sectionOneTitle}
        </div>

        <div class="as-form-row">
          <div class="as-form-group">
            <label>First Name <span class="req">*</span></label>
            <div class="as-input-wrap">
              <i class="fas fa-user"></i>
              <input type="text" id="stuFirst" placeholder="e.g. Maria" required />
            </div>
          </div>
          <div class="as-form-group">
            <label>Last Name <span class="req">*</span></label>
            <div class="as-input-wrap">
              <i class="fas fa-user"></i>
              <input type="text" id="stuLast" placeholder="e.g. Santos" required />
            </div>
          </div>
        </div>

        <div class="as-form-row-3">
          <div class="as-form-group">
            <label>Middle Name <span class="opt">(Optional)</span></label>
            <div class="as-input-wrap">
              <i class="fas fa-user"></i>
              <input type="text" id="stuMiddle" placeholder="e.g. Cruz" />
            </div>
          </div>
          <div class="as-form-group">
            <label>Suffix <span class="opt">(Optional)</span></label>
            <div class="as-input-wrap">
              <i class="fas fa-signature"></i>
              <input type="text" id="stuSuffix" placeholder="Jr., III" maxlength="10" />
            </div>
          </div>
        </div>

        <!-- 2. Role, ID / LRN & Contact -->
        <div class="as-section-tag">
          <i class="fas fa-school"></i> {$sectionTwoTitle}
        </div>

        {$roleSelectorHtml}

        <div class="as-form-group">
          <label id="stuIdLabel">12-Digit Learner Reference Number (LRN) <span class="req">*</span></label>
          <div class="as-input-wrap">
            <i class="fas fa-id-badge"></i>
            <input type="text" id="stuLrn" placeholder="Enter strictly 12 digits (e.g. 109283746501)" maxlength="12" inputmode="numeric" pattern="[0-9]{12}" required oninput="if((document.getElementById('acctRole')?.value||'student')==='student'){this.value=this.value.replace(/[^0-9]/g,'').slice(0,12);}" />
          </div>
          <div class="as-helper" id="stuIdHelper">
            <i class="fas fa-info-circle"></i> Permanent 12-digit DepEd identifier. Prevents duplicate and fake accounts.
          </div>
        </div>

        <div class="as-form-group">
          <label id="stuEmailLabel">Email Address <span class="req">*</span></label>
          <div class="as-input-wrap">
            <i class="fas fa-envelope"></i>
            <input type="email" id="stuEmail" placeholder="email@gmail.com or deped.gov.ph" required />
          </div>
          <div class="as-helper">
            <i class="fas fa-shield-alt"></i> Used for Two-Factor Authentication (2FA) and self-service password recovery.
          </div>
        </div>

        <!-- 3. Grade & Section (Students Only) -->
        <div id="stuGradeSectionBlock">
        <div class="as-section-tag">
          <i class="fas fa-layer-group"></i> 3. Class Grade &amp; Section Assignment
        </div>

        <div class="as-form-row">
          <div class="as-form-group">
            <label>Grade Level <span class="req">*</span></label>
            <div class="as-input-wrap">
              <i class="fas fa-graduation-cap"></i>
              <select id="stuGradeLevel" onchange="onGradeChange()" required>
                <option value="">Select Grade</option>
                <option value="7">Grade 7</option>
                <option value="8">Grade 8</option>
                <option value="9">Grade 9</option>
                <option value="10">Grade 10</option>
              </select>
              <i class="fas fa-chevron-down select-arrow"></i>
            </div>
          </div>
          <div class="as-form-group">
            <label>Section <span class="req">*</span></label>
            <div class="as-input-wrap">
              <i class="fas fa-users"></i>
              <select id="stuSection" required>
                <option value="">Select Grade First</option>
              </select>
              <i class="fas fa-chevron-down select-arrow"></i>
            </div>
          </div>
        </div>
        </div>

        <!-- 4. Security & Temporary Password -->
        <div class="as-section-tag">
          <i class="fas fa-lock"></i> <span id="sec4StepLabel">4</span>. Initial Security &amp; Temporary Password
        </div>

        <div class="as-form-group">
          <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:6px">
            <label style="margin:0">Temporary Password <span class="req">*</span></label>
            <button type="button" id="btnPresetPwd" onclick="setPresetPassword()" style="font-size:11.5px;color:var(--maroon);background:none;border:none;cursor:pointer;font-weight:700">
              <i class="fas fa-magic"></i> Preset: Student@2026
            </button>
          </div>
          <div class="as-input-wrap">
            <i class="fas fa-key"></i>
            <input type="text" id="stuPass" value="Student@2026" placeholder="Enter temporary password" required oninput="validatePasswordStandards()" />
            <button type="button" class="as-pwd-toggle" onclick="togglePassVisibility('stuPass', this)" title="Toggle visibility">
              <i class="fas fa-eye"></i>
            </button>
          </div>

          <div class="as-pwd-checklist">
            <div class="as-pwd-rule" id="ruleLen"><i class="fas fa-circle-notch"></i> At least 8 characters</div>
            <div class="as-pwd-rule" id="ruleUpper"><i class="fas fa-circle-notch"></i> 1 uppercase letter</div>
            <div class="as-pwd-rule" id="ruleNum"><i class="fas fa-circle-notch"></i> 1 number (0–9)</div>
            <div class="as-pwd-rule" id="ruleSpec"><i class="fas fa-circle-notch"></i> 1 special char (!@#$%)</div>
          </div>
        </div>

        <div class="as-notice-banner">
          <i class="fas fa-shield-halved"></i>
          <div>
            <strong>Automated 2FA Security Protection:</strong> Upon their very first sign-in, the user will be guided to set up Google Authenticator (or pure OTP verification) before accessing their portal.
          </div>
        </div>

        <div class="as-actions">
          <a href="admin-users.php" class="btn-secondary" style="text-decoration:none">
            Cancel
          </a>
          <button type="submit" class="btn-primary" id="btnSubmitStudent" style="display:inline-flex;align-items:center;gap:8px">
            <i class="fas fa-user-plus"></i> {$submitBtnLabel}
          </button>
        </div>
      </form>
      </div> <!-- /#singleEnrollSection -->

      <!-- Bulk CSV Enrollment Section -->
      <div id="bulkEnrollSection" style="display:none">
        <div style="background:var(--maroon-pale, #fff0f0);border:1px solid rgba(85,0,0,0.12);border-radius:12px;padding:16px 20px;margin-bottom:20px;display:flex;align-items:flex-start;justify-content:space-between;flex-wrap:wrap;gap:14px">
          <div style="display:flex;gap:12px;align-items:flex-start;max-width:650px">
            <i class="fas fa-info-circle" style="color:var(--maroon);font-size:20px;margin-top:2px"></i>
            <div>
              <strong style="color:var(--maroon);font-size:14px;display:block;margin-bottom:4px">Bulk Student Account Import Guidelines</strong>
              <div style="font-size:12.5px;color:var(--text-2);line-height:1.5">
                Upload a standard <code>.csv</code> spreadsheet to enroll multiple students at once. 
                <strong>Grade Level</strong> (e.g. <code>9</code>) and <strong>Section</strong> (e.g. <code>Dalton</code>) are placed in <strong>separate columns</strong>.
                Each student must have a valid 12-digit DepEd LRN and email. Initial passwords default to <code>Student@2026</code>.
              </div>
            </div>
          </div>
          <a href="../api/download_student_template.php" class="btn-primary" style="display:inline-flex;align-items:center;gap:8px;padding:9px 16px;font-size:13px;text-decoration:none;background:#2563eb;white-space:nowrap">
            <i class="fas fa-file-download"></i> Download CSV Template
          </a>
        </div>

        <!-- File Upload Dropzone -->
        <label for="csvFileInput" id="csvDropzone" class="csv-dropzone" ondragover="handleCsvDragOver(event)" ondragleave="handleCsvDragLeave(event)" ondrop="handleCsvDrop(event)" style="display:block;cursor:pointer">
          <i class="fas fa-cloud-upload-alt" style="font-size:44px;color:var(--maroon);margin-bottom:12px;display:block"></i>
          <div style="font-size:16px;font-weight:800;color:var(--text);margin-bottom:4px">Click to Select CSV File or Drag &amp; Drop Here</div>
          <div style="font-size:13px;color:var(--text-3);margin-bottom:14px">Accepts comma-separated <code>.csv</code> spreadsheet files</div>
          <button type="button" class="btn-primary" onclick="event.preventDefault(); document.getElementById('csvFileInput').click();" style="display:inline-flex;align-items:center;gap:8px;padding:9px 20px;font-size:13.5px;pointer-events:auto;background:var(--maroon);color:#fff">
            <i class="fas fa-folder-open"></i> Browse CSV File
          </button>
        </label>
        <input type="file" id="csvFileInput" accept=".csv,text/csv" style="position:absolute;width:1px;height:1px;padding:0;margin:-1px;overflow:hidden;clip:rect(0,0,0,0);border:0;opacity:0" onchange="handleCsvFileSelected(event)" />

        <!-- Live Preview & Validation Table Container -->
        <div id="csvPreviewContainer" style="display:none;margin-top:22px">
          <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;margin-bottom:12px">
            <div>
              <h3 style="font-size:15px;font-weight:800;margin:0;color:var(--text);display:flex;align-items:center;gap:8px">
                <i class="fas fa-table" style="color:var(--maroon)"></i>
                <span>File Preview: <span id="csvFileNameText" style="color:var(--maroon)"></span></span>
              </h3>
              <div style="font-size:12px;color:var(--text-3);margin-top:2px" id="csvFileMetaText"></div>
            </div>
            <div style="display:flex;align-items:center;gap:8px;font-size:12px">
              <span class="badge-valid" id="csvValidCountBadge"><i class="fas fa-check-circle"></i> 0 Valid</span>
              <span class="badge-invalid" id="csvErrorCountBadge"><i class="fas fa-exclamation-circle"></i> 0 Errors</span>
            </div>
          </div>

          <div class="csv-table-wrap">
            <table class="csv-preview-table" id="csvPreviewTable">
              <thead>
                <tr>
                  <th style="width:40px;text-align:center">#</th>
                  <th>Student Name</th>
                  <th style="width:115px">12-Digit LRN</th>
                  <th>Email Address</th>
                  <th style="width:75px">Grade</th>
                  <th style="width:100px">Section</th>
                  <th style="width:110px">Initial Pass</th>
                  <th style="width:140px">Validation Status</th>
                </tr>
              </thead>
              <tbody id="csvPreviewTbody"></tbody>
            </table>
          </div>

          <!-- Bottom Action Buttons -->
          <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;margin-top:18px;padding-top:16px;border-top:1px solid var(--border)">
            <button type="button" class="btn-secondary" onclick="resetCsvUpload()">
              <i class="fas fa-redo"></i> Choose Different File
            </button>
            <button type="button" class="btn-primary" id="btnConfirmBatchImport" onclick="executeBatchImport()" style="display:inline-flex;align-items:center;gap:8px;padding:11px 22px;font-size:14px;background:linear-gradient(135deg,var(--maroon) 0%,#7a0c0c 100%)">
              <i class="fas fa-user-check"></i> Import Valid Students (<span id="btnImportCountText">0</span>)
            </button>
          </div>
        </div>

        <!-- Batch Result Success Card -->
        <div id="csvResultCard" style="display:none;margin-top:20px;padding:22px;border-radius:12px;border:1px solid #bbf7d0;background:#f0fdf4">
          <div style="display:flex;align-items:center;gap:14px;margin-bottom:12px">
            <div style="width:42px;height:42px;border-radius:50%;background:#dcfce7;color:#16a34a;display:flex;align-items:center;justify-content:center;font-size:20px">
              <i class="fas fa-check-double"></i>
            </div>
            <div>
              <h4 style="margin:0;font-size:16px;font-weight:800;color:#166534" id="csvResultTitle">Bulk Import Complete!</h4>
              <p style="margin:2px 0 0;font-size:13px;color:#15803d" id="csvResultSubtitle"></p>
            </div>
          </div>
          <div style="display:flex;gap:10px;margin-top:14px">
            <a href="admin-users.php" class="btn-primary" style="text-decoration:none;background:#16a34a;padding:8px 16px;font-size:13px;display:inline-flex;align-items:center;gap:6px">
              <i class="fas fa-users-cog"></i> View Enrolled Students
            </a>
            <button type="button" class="btn-secondary" onclick="resetCsvUpload()" style="padding:8px 16px;font-size:13px">
              <i class="fas fa-plus"></i> Import Another File
            </button>
          </div>
        </div>
      </div> <!-- /#bulkEnrollSection -->
    </div>

    <!-- Sidebar Tips & Guidelines -->
    <div class="as-sidebar">
      <div class="as-sidebar-card">
        <h4><i class="fas fa-school" style="color:var(--maroon)"></i> {$sidebarTitle}</h4>
        <p>{$sidebarBody}</p>
      </div>

      <div class="as-sidebar-card">
        <h4><i class="fas fa-shield-alt" style="color:#0284c7"></i> ID &amp; LRN Verification</h4>
        <p>Student 12-digit LRNs and Staff IDs are strictly validated against existing school records to prevent duplicate accounts.</p>
      </div>

      <div class="as-sidebar-card">
        <h4><i class="fas fa-key" style="color:#16a34a"></i> Account Onboarding</h4>
        <p>Share the generated ID / LRN, portal email, and temporary password with the user so they can log in and complete their 2FA setup.</p>
      </div>
    </div>
  </div>
</div>

<script>
const SECTIONS_MAP = {
  '7':  ['Bonifacio', 'Burgos', 'Del Pilar', 'Diego Silang', 'Luna', 'Mabini', 'Malvar', 'Rizal'],
  '8':  ['Abrahams', 'Basho', 'Confucius', 'Ghandi', 'Goswami', 'Kalidasa', 'Mandela', 'Mencius', 'Tagore', 'Valmiki', 'Voltaire'],
  '9':  ['Bohr', 'Curie', 'Dalton', 'Darwin', 'Einstein', 'Faraday', 'Galileo', 'Newton'],
  '10': ['Aguinaldo', 'Aquino', 'Garcia', 'Laurel', 'Macapagal', 'Magsaysay']
};

function onGradeChange() {
  const grade = document.getElementById('stuGradeLevel').value;
  populateSections(grade, '');
}

function populateSections(grade, preselect) {
  const sSel = document.getElementById('stuSection');
  sSel.innerHTML = '';
  const list = SECTIONS_MAP[grade] || [];
  if (list.length === 0) {
    sSel.innerHTML = '<option value="">Select Grade First</option>';
    return;
  }
  sSel.innerHTML = '<option value="">Select Section</option>';
  list.forEach(sec => {
    const opt = document.createElement('option');
    opt.value = sec;
    opt.textContent = sec;
    if (preselect && sec.toLowerCase() === preselect.toLowerCase()) {
      opt.selected = true;
    }
    sSel.appendChild(opt);
  });
}

function getSelectedPresetPwd() {
  const role = document.getElementById('acctRole')?.value || 'student';
  return role === 'student' ? 'Student@2026' : 'Staff@2026';
}

function setPresetPassword() {
  const p = document.getElementById('stuPass');
  p.value = getSelectedPresetPwd();
  validatePasswordStandards();
}

function onAccountRoleChange() {
  const role = document.getElementById('acctRole')?.value || 'student';
  const posWrap = document.getElementById('acctPositionWrap');
  const gradeBlock = document.getElementById('stuGradeSectionBlock');
  const gradeSel = document.getElementById('stuGradeLevel');
  const secSel = document.getElementById('stuSection');
  const idLabel = document.getElementById('stuIdLabel');
  const idInput = document.getElementById('stuLrn');
  const idHelp = document.getElementById('stuIdHelper');
  const btnPreset = document.getElementById('btnPresetPwd');
  const sec4 = document.getElementById('sec4StepLabel');
  const btnSubmit = document.getElementById('btnSubmitStudent');

  if (posWrap) posWrap.style.display = (role === 'super_admin') ? 'block' : 'none';

  const isStu = (role === 'student');
  if (gradeBlock) gradeBlock.style.display = isStu ? 'block' : 'none';
  if (gradeSel) gradeSel.required = isStu;
  if (secSel) secSel.required = isStu;
  if (sec4) sec4.textContent = isStu ? '4' : '3';

  if (idInput && idLabel) {
    if (isStu) {
      idLabel.innerHTML = '12-Digit Learner Reference Number (LRN) <span class="req">*</span>';
      idInput.placeholder = 'Enter strictly 12 digits (e.g. 109283746501)';
      idInput.setAttribute('maxlength', '12');
      idInput.setAttribute('inputmode', 'numeric');
      idInput.setAttribute('pattern', '[0-9]{12}');
      if (idHelp) idHelp.innerHTML = '<i class="fas fa-info-circle"></i> Permanent 12-digit DepEd identifier. Prevents duplicate and fake accounts.';
    } else {
      const idTitle = role === 'super_admin' ? 'Top Admin ID Number' : (role === 'admin' ? 'Guidance Counselor / Staff ID' : 'Employee Number');
      const idPh = role === 'super_admin' ? 'e.g. ADMIN-PRINCIPAL-02' : (role === 'admin' ? 'e.g. GUID-2026-01' : 'e.g. EMP-2026-01');
      idLabel.innerHTML = idTitle + ' <span class="req">*</span>';
      idInput.placeholder = idPh;
      idInput.removeAttribute('maxlength');
      idInput.removeAttribute('inputmode');
      idInput.removeAttribute('pattern');
      if (idHelp) idHelp.innerHTML = '<i class="fas fa-info-circle"></i> Unique school employee or administrator identifier.';
    }
  }

  const preset = getSelectedPresetPwd();
  const p = document.getElementById('stuPass');
  if (p && (p.value === 'Student@2026' || p.value === 'Staff@2026' || !p.value)) {
    p.value = preset;
    validatePasswordStandards();
  }
  if (btnPreset) btnPreset.innerHTML = '<i class="fas fa-magic"></i> Preset: ' + preset;
  if (btnSubmit) {
    const labelMap = {
      student: 'Create Student Account',
      teacher: 'Create Teacher Account',
      admin: 'Create Guidance Admin Account',
      super_admin: 'Create Top Admin Account'
    };
    btnSubmit.innerHTML = '<i class="fas fa-user-plus"></i> ' + ({$isTopAdminJs} ? (labelMap[role] || 'Create Account') : 'Register & Create Student Account');
  }
}

function togglePassVisibility(inputId, btn) {
  const inp = document.getElementById(inputId);
  if (!inp) return;
  const isPass = inp.type === 'password';
  inp.type = isPass ? 'text' : 'password';
  const icon = btn.querySelector('i');
  if (icon) {
    icon.className = isPass ? 'fas fa-eye-slash' : 'fas fa-eye';
  }
}

function validatePasswordStandards() {
  const p = document.getElementById('stuPass').value;
  const setRule = (id, valid) => {
    const el = document.getElementById(id);
    if (!el) return;
    if (valid) {
      el.className = 'as-pwd-rule valid';
      el.querySelector('i').className = 'fas fa-check-circle';
    } else {
      el.className = 'as-pwd-rule invalid';
      el.querySelector('i').className = 'fas fa-circle-notch';
    }
  };

  setRule('ruleLen', p.length >= 8);
  setRule('ruleUpper', /[A-Z]/.test(p));
  setRule('ruleNum', /[0-9]/.test(p));
  setRule('ruleSpec', /[^A-Za-z0-9]/.test(p));
}

let _lastCreatedCreds = null;

async function handleStudentEnroll(e) {
  e.preventDefault();
  const alertEl = document.getElementById('pageAlert');
  alertEl.style.display = 'none';

  const role = document.getElementById('acctRole')?.value || 'student';
  const position = role === 'super_admin'
    ? (document.getElementById('acctPosition')?.value || 'Principal')
    : (role === 'admin' ? 'Guidance Counselor' : '');
  const first = document.getElementById('stuFirst').value.trim();
  const last = document.getElementById('stuLast').value.trim();
  const middle = document.getElementById('stuMiddle').value.trim();
  const suffix = document.getElementById('stuSuffix').value.trim();
  const lrn = document.getElementById('stuLrn').value.trim();
  const email = document.getElementById('stuEmail').value.trim();
  const gradeLevel = document.getElementById('stuGradeLevel').value;
  const section = document.getElementById('stuSection').value;
  const pass = document.getElementById('stuPass').value.trim();

  if (!first || !last || !lrn || !email || !pass || (role === 'student' && (!gradeLevel || !section))) {
    showAlert('Please fill in all required fields.', 'error');
    return;
  }

  if (role === 'student' && !/^\d{12}$/.test(lrn)) {
    showAlert('Learner Reference Number (LRN) must be strictly 12 numeric digits.', 'error');
    document.getElementById('stuLrn').focus();
    return;
  }

  if (pass.length < 8 || !/[A-Z]/.test(pass) || !/[0-9]/.test(pass) || !/[^A-Za-z0-9]/.test(pass)) {
    showAlert('Password does not meet the security standards (min 8 chars, 1 uppercase, 1 number, 1 special character).', 'error');
    document.getElementById('stuPass').focus();
    return;
  }

  const fullGrade = role === 'student' ? ('Grade ' + gradeLevel + ' - ' + section) : '';
  const btn = document.getElementById('btnSubmitStudent');
  const prevBtnHtml = btn.innerHTML;
  btn.disabled = true;
  btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Creating Account...';

  try {
    const apiPath = getApiPath('register.php');

    const resp = await fetch(apiPath, {
      method: 'POST',
      headers: {'Content-Type': 'application/json'},
      body: JSON.stringify({
        first_name: first,
        last_name: last,
        middle_name: middle,
        suffix: suffix,
        email: email,
        password: pass,
        confirm: pass,
        role: role,
        position: position,
        id_number: lrn,
        grade_section: fullGrade
      })
    });

    const data = await resp.json();

    if (data.success) {
      const roleDisp = role === 'super_admin' ? position : (role === 'admin' ? 'Guidance Counselor' : (role === 'teacher' ? 'Teacher' : 'Student'));
      _lastCreatedCreds = {
        name: first + (middle ? ' ' + middle : '') + ' ' + last + (suffix ? ' ' + suffix : ''),
        role: roleDisp,
        lrn: lrn,
        email: email,
        section: fullGrade || '—',
        pass: pass
      };

      document.getElementById('succName').textContent = _lastCreatedCreds.name;
      const succRoleEl = document.getElementById('succRole');
      if (succRoleEl) succRoleEl.textContent = _lastCreatedCreds.role;
      const succIdLbl = document.getElementById('succIdLabel');
      if (succIdLbl) succIdLbl.textContent = role === 'student' ? '12-Digit LRN' : 'Employee / Admin ID';
      document.getElementById('succLrn').textContent = _lastCreatedCreds.lrn;
      document.getElementById('succEmail').textContent = _lastCreatedCreds.email;
      const succClassWrap = document.getElementById('succClassWrap');
      if (succClassWrap) succClassWrap.style.display = role === 'student' ? '' : 'none';
      document.getElementById('succClass').textContent = _lastCreatedCreds.section;
      document.getElementById('succPass').textContent = _lastCreatedCreds.pass;

      document.getElementById('regSuccessCard').style.display = 'block';
      document.getElementById('regSuccessCard').scrollIntoView({ behavior: 'smooth', block: 'start' });

      // Reset form fields
      document.getElementById('stuFirst').value = '';
      document.getElementById('stuLast').value = '';
      document.getElementById('stuMiddle').value = '';
      document.getElementById('stuSuffix').value = '';
      document.getElementById('stuLrn').value = '';
      document.getElementById('stuEmail').value = '';
      setPresetPassword();

      if (typeof showToast === 'function') {
        showToast(data.message || 'Account created successfully!', 'success');
      }
    } else {
      showAlert(data.message || 'Failed to create account.', 'error');
    }
  } catch (err) {
    showAlert('Server communication error. Please check your network and try again.', 'error');
  }

  btn.disabled = false;
  btn.innerHTML = prevBtnHtml;
}

function showAlert(text, type) {
  const el = document.getElementById('pageAlert');
  if (!el) return;
  el.className = type === 'error' ? 'error-msg' : 'success-msg';
  el.style.display = 'block';
  el.style.padding = '12px 16px';
  el.style.borderRadius = '10px';
  el.style.fontSize = '13px';
  el.style.fontWeight = '600';
  if (type === 'error') {
    el.style.background = '#fee2e2';
    el.style.border = '1px solid #f87171';
    el.style.color = '#991b1b';
  } else {
    el.style.background = '#dcfce7';
    el.style.border = '1px solid #86efac';
    el.style.color = '#166534';
  }
  el.innerHTML = '<i class="fas fa-exclamation-triangle" style="margin-right:6px"></i> ' + text;
  el.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
}

function copyStudentCredentials() {
  if (!_lastCreatedCreds) return;
  const text = "GNHS Guidance Portal - Account Credentials\\n"
    + "Name: " + _lastCreatedCreds.name + "\\n"
    + "Role: " + (_lastCreatedCreds.role || 'Student') + "\\n"
    + "ID / LRN: " + _lastCreatedCreds.lrn + "\\n"
    + "Email: " + _lastCreatedCreds.email + "\\n"
    + (_lastCreatedCreds.section && _lastCreatedCreds.section !== '—' ? ("Class: " + _lastCreatedCreds.section + "\\n") : "")
    + "Temporary Password: " + _lastCreatedCreds.pass + "\\n"
    + "Login at: " + window.location.origin + "/gnhs-guidance/index.html";

  navigator.clipboard.writeText(text).then(() => {
    const btn = document.getElementById('btnCopyCreds');
    btn.innerHTML = '<i class="fas fa-check"></i> Copied to Clipboard!';
    btn.style.background = '#16a34a';
    setTimeout(() => {
      btn.innerHTML = '<i class="fas fa-copy"></i> Copy Credentials to Clipboard';
      btn.style.background = '';
    }, 2500);
    if (typeof showToast === 'function') showToast('Credentials copied to clipboard!', 'success');
  }).catch(() => {
    alert(text);
  });
}

function resetFormForNewStudent() {
  document.getElementById('regSuccessCard').style.display = 'none';
  document.getElementById('stuFirst').focus();
}

function getApiPath(endpoint) {
  const p = window.location.pathname;
  const idx = p.lastIndexOf('/pages/');
  const base = idx !== -1 ? p.substring(0, idx) : '';
  return base + '/api/' + endpoint;
}

// ─────────────────────────────────────────────────────────────────────────────
// BULK STUDENT CSV IMPORT ENGINE
// ─────────────────────────────────────────────────────────────────────────────
let _parsedCsvStudents = [];
let _validCsvStudents = [];

function switchEnrollMode(mode) {
  const btnSingle = document.getElementById('btnModeSingle');
  const btnBulk = document.getElementById('btnModeBulk');
  const secSingle = document.getElementById('singleEnrollSection');
  const secBulk = document.getElementById('bulkEnrollSection');

  if (mode === 'bulk') {
    btnSingle?.classList.remove('active');
    btnBulk?.classList.add('active');
    if (secSingle) secSingle.style.display = 'none';
    if (secBulk) secBulk.style.display = 'block';
  } else {
    btnBulk?.classList.remove('active');
    btnSingle?.classList.add('active');
    if (secBulk) secBulk.style.display = 'none';
    if (secSingle) secSingle.style.display = 'block';
  }
}

function handleCsvDragOver(e) {
  e.preventDefault();
  document.getElementById('csvDropzone')?.classList.add('dragover');
}

function handleCsvDragLeave(e) {
  document.getElementById('csvDropzone')?.classList.remove('dragover');
}

function handleCsvDrop(e) {
  e.preventDefault();
  document.getElementById('csvDropzone')?.classList.remove('dragover');
  if (e.dataTransfer && e.dataTransfer.files && e.dataTransfer.files.length > 0) {
    processCsvFile(e.dataTransfer.files[0]);
  }
}

function handleCsvFileSelected(e) {
  if (e.target && e.target.files && e.target.files.length > 0) {
    processCsvFile(e.target.files[0]);
  }
}

function processCsvFile(file) {
  if (!file) return;
  if (!file.name.toLowerCase().endsWith('.csv') && file.type !== 'text/csv') {
    alert('Please select a valid .csv spreadsheet file.');
    return;
  }

  const reader = new FileReader();
  reader.onload = function(e) {
    const text = e.target.result;
    parseAndPreviewCsv(text, file.name, file.size);
  };
  reader.readAsText(file);
}

function parseCsvText(text) {
  const lines = [];
  let row = [];
  let current = '';
  let insideQuote = false;

  for (let i = 0; i < text.length; i++) {
    const char = text[i];
    const nextChar = text[i + 1];

    if (char === '"') {
      if (insideQuote && nextChar === '"') {
        current += '"';
        i++;
      } else {
        insideQuote = !insideQuote;
      }
    } else if (char === ',' && !insideQuote) {
      row.push(current.trim());
      current = '';
    } else if ((char === '\\r' || char === '\\n') && !insideQuote) {
      if (char === '\\r' && nextChar === '\\n') {
        i++;
      }
      row.push(current.trim());
      if (row.length > 1 || (row.length === 1 && row[0] !== '')) {
        lines.push(row);
      }
      row = [];
      current = '';
    } else {
      current += char;
    }
  }
  if (current.length > 0 || row.length > 0) {
    row.push(current.trim());
    if (row.length > 1 || (row.length === 1 && row[0] !== '')) {
      lines.push(row);
    }
  }
  return lines;
}

function parseAndPreviewCsv(csvText, fileName, fileSize) {
  const rawRows = parseCsvText(csvText);
  if (!rawRows || rawRows.length < 2) {
    alert('The uploaded CSV file is empty or missing data rows.');
    return;
  }

  // Header mapping
  const headers = rawRows[0].map(h => h.toLowerCase().replace(/[^a-z0-9_]/g, ''));
  const findIdx = (names) => {
    for (let n of names) {
      const idx = headers.indexOf(n);
      if (idx !== -1) return idx;
    }
    return -1;
  };

  let idxFirst   = findIdx(['firstname', 'first_name', 'first', 'givenname', 'pangalan']);
  let idxLast    = findIdx(['lastname', 'last_name', 'last', 'surname', 'familyname', 'apelyido']);
  let idxMiddle  = findIdx(['middlename', 'middle_name', 'middle', 'mid']);
  let idxSuffix  = findIdx(['suffix', 'ext']);
  let idxLrn     = findIdx(['lrn', 'idnumber', 'id_number', 'student_lrn', 'studentlrn', 'learnerreferencenumber']);
  let idxEmail   = findIdx(['email', 'emailaddress', 'email_address', 'student_email', 'studentemail']);
  let idxGrade   = findIdx(['gradelevel', 'grade_level', 'grade', 'level', 'baitang']);
  let idxSection = findIdx(['section', 'pangkat', 'sec']);
  let idxPass    = findIdx(['temporarypassword', 'temporary_password', 'password', 'pass']);
  let idxFullName = findIdx(['name', 'fullname', 'studentname', 'student_name']);
  let idxGradeSec = findIdx(['gradesection', 'grade_section', 'class', 'gradeandsection']);

  if (idxFirst === -1 && idxFullName === -1) {
    alert('CSV is missing student name column! Make sure your file contains first_name, last_name, lrn, and email. You can download the sample template.');
    return;
  }
  if (idxLrn === -1 || idxEmail === -1) {
    alert('CSV is missing required column headers! Make sure your file contains lrn and email. You can download the sample template.');
    return;
  }

  _parsedCsvStudents = [];
  _validCsvStudents = [];

  const tbody = document.getElementById('csvPreviewTbody');
  if (tbody) tbody.innerHTML = '';

  let validCount = 0;
  let errorCount = 0;
  const seenBatchLrns = new Set();
  const seenBatchEmails = new Set();

  for (let i = 1; i < rawRows.length; i++) {
    const r = rawRows[i];
    if (r.length === 1 && !r[0]) continue; // skip blank row

    let first  = idxFirst !== -1 ? (r[idxFirst] || '').trim() : '';
    let last   = idxLast !== -1 ? (r[idxLast] || '').trim() : '';
    let middle = idxMiddle !== -1 ? (r[idxMiddle] || '').trim() : '';
    let suffix = idxSuffix !== -1 ? (r[idxSuffix] || '').trim() : '';
    const lrn    = (r[idxLrn] || '').trim();
    const email  = (r[idxEmail] || '').trim();
    let grade    = idxGrade !== -1 ? (r[idxGrade] || '').trim() : '';
    let section  = idxSection !== -1 ? (r[idxSection] || '').trim() : '';
    let pass     = idxPass !== -1 ? (r[idxPass] || '').trim() : 'Student@2026';
    if (!pass) pass = 'Student@2026';

    // Fallback: parse Full Name if separate first/last missing
    if ((!first || !last) && idxFullName !== -1) {
      const full = (r[idxFullName] || '').trim();
      if (full.includes(',')) {
        const parts = full.split(',').map(p => p.trim());
        last = parts[0] || last;
        const firstParts = (parts[1] || '').split(' ').filter(Boolean);
        first = firstParts[0] || first;
        if (firstParts.length > 1) middle = firstParts.slice(1).join(' ');
      } else {
        const parts = full.split(' ').filter(Boolean);
        first = parts[0] || '';
        last = parts.length > 1 ? parts.slice(1).join(' ') : 'Student';
      }
    }

    // Fallback: parse combined Grade & Section if separate columns missing
    if ((!grade || !section) && idxGradeSec !== -1) {
      const gs = (r[idxGradeSec] || '').trim();
      const parts = gs.split('-');
      if (parts.length >= 2) {
        if (!grade) grade = parts[0].replace(/[^0-9]/g, '');
        if (!section) section = parts.slice(1).join('-').trim();
      }
    }

    // Normalize grade
    let gradeNum = grade.replace(/[^0-9]/g, '');

    // Validation checks
    const errors = [];
    if (!first) errors.push('Missing First Name');
    if (!last)  errors.push('Missing Last Name');
    
    if (!lrn) {
      errors.push('Missing LRN');
    } else if (!/^\d{12}$/.test(lrn)) {
      errors.push('LRN must be strictly 12 digits');
    } else if (seenBatchLrns.has(lrn)) {
      errors.push('Duplicate LRN in file');
    }

    if (!email) {
      errors.push('Missing Email');
    } else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
      errors.push('Invalid email format');
    } else if (seenBatchEmails.has(email.toLowerCase())) {
      errors.push('Duplicate email in file');
    }

    if (!gradeNum || !['7', '8', '9', '10'].includes(gradeNum)) {
      errors.push('Grade must be 7, 8, 9, or 10');
    }

    if (!section) {
      errors.push('Missing Section');
    }

    const isValid = errors.length === 0;
    if (isValid) {
      validCount++;
      seenBatchLrns.add(lrn);
      seenBatchEmails.add(email.toLowerCase());
      _validCsvStudents.push({
        first_name: first,
        last_name: last,
        middle_name: middle,
        suffix: suffix,
        lrn: lrn,
        email: email,
        grade_level: gradeNum,
        section: section,
        temporary_password: pass
      });
    } else {
      errorCount++;
    }

    const studentObj = { first, last, middle, suffix, lrn, email, gradeNum, section, pass, isValid, errors };
    _parsedCsvStudents.push(studentObj);

    // Build row
    if (tbody) {
      const tr = document.createElement('tr');
      tr.className = isValid ? 'csv-row-valid' : 'csv-row-invalid';
      const fullName = [last, first, middle, suffix].filter(Boolean).join(', ');
      
      tr.innerHTML = '<td style="text-align:center;font-weight:700;color:var(--text-3)">' + i + '</td>'
        + '<td><strong>' + escapeHtml(first) + ' ' + escapeHtml(last) + '</strong> ' + (suffix ? escapeHtml(suffix) : '') + '</td>'
        + '<td><code style="font-size:11.5px">' + escapeHtml(lrn || '—') + '</code></td>'
        + '<td style="font-size:11.5px">' + escapeHtml(email || '—') + '</td>'
        + '<td>Grade ' + escapeHtml(gradeNum || '—') + '</td>'
        + '<td>' + escapeHtml(section || '—') + '</td>'
        + '<td><code style="font-size:11px">' + escapeHtml(pass) + '</code></td>'
        + '<td>'
        + (isValid 
            ? '<span class="badge-valid"><i class="fas fa-check-circle"></i> Ready</span>' 
            : '<span class="badge-invalid" title="' + escapeHtml(errors.join(', ')) + '"><i class="fas fa-times-circle"></i> ' + escapeHtml(errors[0]) + '</span>')
        + '</td>';
      tbody.appendChild(tr);
    }
  }

  // Update header and badges
  document.getElementById('csvFileNameText').textContent = fileName;
  const kbSize = Math.round(fileSize / 1024);
  document.getElementById('csvFileMetaText').textContent = (rawRows.length - 1) + ' rows parsed • ' + kbSize + ' KB';
  document.getElementById('csvValidCountBadge').innerHTML = '<i class="fas fa-check-circle"></i> ' + validCount + ' Valid';
  document.getElementById('csvErrorCountBadge').innerHTML = '<i class="fas fa-exclamation-circle"></i> ' + errorCount + ' Errors';
  document.getElementById('btnImportCountText').textContent = validCount;

  const btnImport = document.getElementById('btnConfirmBatchImport');
  if (btnImport) btnImport.disabled = (validCount === 0);

  document.getElementById('csvPreviewContainer').style.display = 'block';
  document.getElementById('csvResultCard').style.display = 'none';
  document.getElementById('csvPreviewContainer').scrollIntoView({ behavior: 'smooth', block: 'start' });
}

async function executeBatchImport() {
  if (!_validCsvStudents || _validCsvStudents.length === 0) {
    alert('There are no valid student records to import.');
    return;
  }

  const btn = document.getElementById('btnConfirmBatchImport');
  if (!confirm('Are you sure you want to enroll and create accounts for ' + _validCsvStudents.length + ' valid students?')) {
    return;
  }

  btn.disabled = true;
  btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Enrolling Accounts...';

  try {
    const apiPath = getApiPath('import_students.php');

    const resp = await fetch(apiPath, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ students: _validCsvStudents })
    });

    const res = await resp.json();
    if (res.success) {
      document.getElementById('csvPreviewContainer').style.display = 'none';
      const resultCard = document.getElementById('csvResultCard');
      resultCard.style.display = 'block';
      document.getElementById('csvResultTitle').textContent = 'Success! ' + res.imported + ' Student Accounts Created.';
      
      let subMsg = 'Successfully enrolled ' + res.imported + ' out of ' + res.total + ' students into the Guidance system database.';
      if (res.skipped > 0) {
        subMsg += ' Note: ' + res.skipped + ' students were skipped (already registered LRN or email).';
      }
      document.getElementById('csvResultSubtitle').textContent = subMsg;
      resultCard.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    } else {
      alert('Import failed: ' + (res.message || 'Unknown server error.'));
      btn.disabled = false;
      btn.innerHTML = '<i class="fas fa-user-check"></i> Import Valid Students (' + _validCsvStudents.length + ')';
    }
  } catch(err) {
    alert('Network or server error while executing import: ' + err.message);
    btn.disabled = false;
    btn.innerHTML = '<i class="fas fa-user-check"></i> Import Valid Students (' + _validCsvStudents.length + ')';
  }
}

function resetCsvUpload() {
  const fileInp = document.getElementById('csvFileInput');
  if (fileInp) fileInp.value = '';
  _parsedCsvStudents = [];
  _validCsvStudents = [];
  document.getElementById('csvPreviewContainer').style.display = 'none';
  document.getElementById('csvResultCard').style.display = 'none';
}

// Global Exports
window.switchEnrollMode = switchEnrollMode;
window.handleCsvFileSelected = handleCsvFileSelected;
window.handleCsvFile = function(f) { if (f) processCsvFile(f); };
window.executeBatchImport = executeBatchImport;
window.resetCsvUpload = resetCsvUpload;

// Initialize on page readiness
function initAdminAddStudentPage() {
  document.getElementById('stuGradeLevel').value = '10';
  populateSections('10', '');
  onAccountRoleChange();
  validatePasswordStandards();
  const tab = new URLSearchParams(window.location.search).get('tab');
  if (tab === 'bulk') {
    switchEnrollMode('bulk');
  }
}

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', initAdminAddStudentPage);
} else {
  initAdminAddStudentPage();
}
</script>
HTML;

renderLayout($user, $pageTitle, $activeNav, $content);
