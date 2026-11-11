<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/layout.php';
$user = requireLogin('teacher');

$advisoryClass = $user['advisory_class'] ?? '';
$defaultGradeNum = '';
$defaultSection = '';
if ($advisoryClass) {
    $parts = explode(' - ', $advisoryClass, 2);
    $defaultGradeNum = preg_replace('/[^0-9]/', '', $parts[0] ?? '');
    $defaultSection = trim($parts[1] ?? '');
}

$pageTitle = 'Add Student';
$activeNav = 'add_student';

$escapedAdvisory = htmlspecialchars($advisoryClass);
$escapedGradeNum = htmlspecialchars($defaultGradeNum);
$escapedSection = htmlspecialchars($defaultSection);

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
  .as-sidebar-card ul {
    margin: 10px 0 0;
    padding-left: 18px;
    font-size: 12px;
    color: var(--text-2, #475569);
    line-height: 1.6;
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
      font-size: 16px !important; /* Prevents iOS browser auto-zoom on form inputs */
      min-height: 46px;
    }
  }
</style>

<div class="as-wrapper">
  <!-- Breadcrumb -->
  <div class="as-breadcrumb">
    <a href="teacher-dashboard.php"><i class="fas fa-home"></i> Dashboard</a>
    <i class="fas fa-chevron-right"></i>
    <a href="teacher-advisory.php">Advisory Class</a>
    <i class="fas fa-chevron-right"></i>
    <span style="color:var(--text);font-weight:700">Add Student</span>
  </div>

  <!-- Success Notification Box (Appears after successful registration) -->
  <div class="as-success-card" id="regSuccessCard">
    <div class="as-success-header">
      <div class="as-success-badge"><i class="fas fa-check"></i></div>
      <div>
        <h3 style="margin:0 0 4px;font-size:18px;font-weight:800;color:#15803d">Student Account Created Successfully!</h3>
        <p style="margin:0;font-size:12.5px;color:#166534">The student can now log in using their credentials and complete their 2FA setup.</p>
      </div>
    </div>
    <div class="as-cred-box">
      <div class="as-cred-item">
        <label>Full Name</label>
        <span id="succName">-</span>
      </div>
      <div class="as-cred-item">
        <label>12-Digit LRN</label>
        <span id="succLrn" style="letter-spacing:1px;font-family:monospace">-</span>
      </div>
      <div class="as-cred-item">
        <label>Portal Email</label>
        <span id="succEmail">-</span>
      </div>
      <div class="as-cred-item">
        <label>Class Section</label>
        <span id="succClass">-</span>
      </div>
      <div class="as-cred-item" style="grid-column: 1 / -1">
        <label>Initial Temporary Password</label>
        <span id="succPass" style="font-family:monospace;color:var(--maroon)">-</span>
      </div>
    </div>
    <div style="display:flex;gap:10px;flex-wrap:wrap">
      <button type="button" class="btn-primary" onclick="copyStudentCredentials()" id="btnCopyCreds" style="display:inline-flex;align-items:center;gap:6px">
        <i class="fas fa-copy"></i> Copy Credentials to Clipboard
      </button>
      <button type="button" class="btn-secondary" onclick="resetFormForNewStudent()" style="display:inline-flex;align-items:center;gap:6px">
        <i class="fas fa-user-plus"></i> Register Another Student
      </button>
      <a href="teacher-advisory.php" class="btn-sm-outline" style="display:inline-flex;align-items:center;gap:6px;text-decoration:none;padding:10px 18px">
        <i class="fas fa-users"></i> View in Advisory Class
      </a>
    </div>
  </div>

  <div class="as-grid">
    <!-- Main Form Section -->
    <div class="as-main-card">
      <div class="as-card-header">
        <div class="as-icon-badge">
          <i class="fas fa-user-graduate"></i>
        </div>
        <div>
          <h2>Register Student Account</h2>
          <p>Create official student portal credentials with 12-digit LRN validation</p>
        </div>
      </div>

      <!-- Live Error / Notice Alert -->
      <div id="pageAlert" style="display:none;margin-bottom:18px"></div>

      <form id="studentEnrollForm" onsubmit="handleStudentEnroll(event)">
        <!-- 1. Personal Information -->
        <div class="as-section-tag">
          <i class="fas fa-id-card"></i> 1. Student Personal Information
        </div>

        <div class="as-form-row">
          <div class="as-form-group">
            <label>First Name <span class="req">*</span></label>
            <div class="as-input-wrap">
              <i class="fas fa-user"></i>
              <input type="text" id="stuFirst" placeholder="e.g. Juan" required />
            </div>
          </div>
          <div class="as-form-group">
            <label>Last Name <span class="req">*</span></label>
            <div class="as-input-wrap">
              <i class="fas fa-user"></i>
              <input type="text" id="stuLast" placeholder="e.g. Dela Cruz" required />
            </div>
          </div>
        </div>

        <div class="as-form-row-3">
          <div class="as-form-group">
            <label>Middle Name <span class="opt">(Optional)</span></label>
            <div class="as-input-wrap">
              <i class="fas fa-user"></i>
              <input type="text" id="stuMiddle" placeholder="e.g. Santos" />
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

        <!-- 2. DepEd LRN & Contact -->
        <div class="as-section-tag">
          <i class="fas fa-school"></i> 2. Official LRN &amp; Portal Account
        </div>

        <div class="as-form-group">
          <label>12-Digit Learner Reference Number (LRN) <span class="req">*</span></label>
          <div class="as-input-wrap">
            <i class="fas fa-id-badge"></i>
            <input type="text" id="stuLrn" placeholder="Enter strictly 12 digits (e.g. 109283746501)" maxlength="12" inputmode="numeric" pattern="[0-9]{12}" required oninput="this.value=this.value.replace(/[^0-9]/g,'').slice(0,12)" />
          </div>
          <div class="as-helper">
            <i class="fas fa-info-circle"></i> Permanent 12-digit DepEd identifier. Prevents duplicate and fake accounts.
          </div>
        </div>

        <div class="as-form-group">
          <label>Student Email Address <span class="req">*</span></label>
          <div class="as-input-wrap">
            <i class="fas fa-envelope"></i>
            <input type="email" id="stuEmail" placeholder="student@gmail.com or deped email" required />
          </div>
          <div class="as-helper">
            <i class="fas fa-shield-alt"></i> Used for Two-Factor Authentication and self-service password recovery.
          </div>
        </div>

        <!-- 3. Grade & Advisory Section -->
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

        <!-- 4. Security & Temporary Password -->
        <div class="as-section-tag">
          <i class="fas fa-lock"></i> 4. Initial Security &amp; Temporary Password
        </div>

        <div class="as-form-group">
          <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:6px">
            <label style="margin:0">Temporary Password <span class="req">*</span></label>
            <button type="button" onclick="setPresetPassword()" style="font-size:11.5px;color:var(--maroon);background:none;border:none;cursor:pointer;font-weight:700">
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
            <strong>Automated 2FA Security Protection:</strong> Upon their very first sign-in, the student will be guided to set up Google Authenticator (or pure OTP verification) before accessing their portal.
          </div>
        </div>

        <div class="as-actions">
          <a href="teacher-advisory.php" class="btn-secondary" style="text-decoration:none">
            Cancel
          </a>
          <button type="submit" class="btn-primary" id="btnSubmitStudent" style="display:inline-flex;align-items:center;gap:8px">
            <i class="fas fa-user-plus"></i> Register &amp; Create Student Account
          </button>
        </div>
      </form>
    </div>

    <!-- Sidebar Tips & Guidelines -->
    <div class="as-sidebar">
      <div class="as-sidebar-card">
        <h4><i class="fas fa-user-check" style="color:var(--maroon)"></i> Advisory Integration</h4>
        <p>Students created through this page are immediately enrolled into your Advisory Class records and can submit confidential guidance concerns.</p>
      </div>

      <div class="as-sidebar-card">
        <h4><i class="fas fa-shield-alt" style="color:#0284c7"></i> DepEd LRN Validation</h4>
        <p>The 12-digit LRN is strictly validated and uniquely enforced. Duplicate or fake student records are prohibited by DepEd security standards.</p>
      </div>

      <div class="as-sidebar-card">
        <h4><i class="fas fa-key" style="color:#16a34a"></i> Student Onboarding</h4>
        <p>Share the LRN, email address, and temporary password with the student. On their initial sign-in, they can immediately update their password and scan their 2FA QR code.</p>
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

const DEFAULT_GRADE = '{$escapedGradeNum}';
const DEFAULT_SECTION = '{$escapedSection}';

function initGradeAndSections() {
  const gSel = document.getElementById('stuGradeLevel');
  if (DEFAULT_GRADE && SECTIONS_MAP[DEFAULT_GRADE]) {
    gSel.value = DEFAULT_GRADE;
    populateSections(DEFAULT_GRADE, DEFAULT_SECTION);
  } else {
    gSel.value = '10';
    populateSections('10', '');
  }
}

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

function setPresetPassword() {
  const p = document.getElementById('stuPass');
  p.value = 'Student@2026';
  validatePasswordStandards();
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

  const first = document.getElementById('stuFirst').value.trim();
  const last = document.getElementById('stuLast').value.trim();
  const middle = document.getElementById('stuMiddle').value.trim();
  const suffix = document.getElementById('stuSuffix').value.trim();
  const lrn = document.getElementById('stuLrn').value.trim();
  const email = document.getElementById('stuEmail').value.trim();
  const gradeLevel = document.getElementById('stuGradeLevel').value;
  const section = document.getElementById('stuSection').value;
  const pass = document.getElementById('stuPass').value.trim();

  if (!first || !last || !lrn || !email || !gradeLevel || !section || !pass) {
    showAlert('Please fill in all required fields.', 'error');
    return;
  }

  if (!/^\d{12}$/.test(lrn)) {
    showAlert('Learner Reference Number (LRN) must be strictly 12 numeric digits.', 'error');
    document.getElementById('stuLrn').focus();
    return;
  }

  if (pass.length < 8 || !/[A-Z]/.test(pass) || !/[0-9]/.test(pass) || !/[^A-Za-z0-9]/.test(pass)) {
    showAlert('Password does not meet the security standards (min 8 chars, 1 uppercase, 1 number, 1 special character).', 'error');
    document.getElementById('stuPass').focus();
    return;
  }

  const fullGrade = 'Grade ' + gradeLevel + ' - ' + section;
  const btn = document.getElementById('btnSubmitStudent');
  btn.disabled = true;
  btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Enrolling Student...';

  try {
    const isNested = window.location.pathname.startsWith('/gnhs-guidance');
    const apiPath = isNested ? '/gnhs-guidance/api/register.php' : '/api/register.php';

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
        role: 'student',
        id_number: lrn,
        grade_section: fullGrade
      })
    });

    const data = await resp.json();

    if (data.success) {
      _lastCreatedCreds = {
        name: first + (middle ? ' ' + middle : '') + ' ' + last + (suffix ? ' ' + suffix : ''),
        lrn: lrn,
        email: email,
        section: fullGrade,
        pass: pass
      };

      document.getElementById('succName').textContent = _lastCreatedCreds.name;
      document.getElementById('succLrn').textContent = _lastCreatedCreds.lrn;
      document.getElementById('succEmail').textContent = _lastCreatedCreds.email;
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
        showToast(data.message || 'Student enrolled successfully!', 'success');
      }
    } else {
      showAlert(data.message || 'Failed to create student account.', 'error');
    }
  } catch (err) {
    showAlert('Server communication error. Please check your network and try again.', 'error');
  }

  btn.disabled = false;
  btn.innerHTML = '<i class="fas fa-user-plus"></i> Register & Create Student Account';
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
  const text = "GNHS Guidance Portal - Student Account Credentials\\n"
    + "Name: " + _lastCreatedCreds.name + "\\n"
    + "12-Digit LRN: " + _lastCreatedCreds.lrn + "\\n"
    + "Email: " + _lastCreatedCreds.email + "\\n"
    + "Class: " + _lastCreatedCreds.section + "\\n"
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

document.addEventListener('DOMContentLoaded', function() {
  initGradeAndSections();
  validatePasswordStandards();
});
</script>
HTML;

renderLayout($user, $pageTitle, $activeNav, $content);
