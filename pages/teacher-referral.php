<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/layout.php';
$user = requireLogin('teacher');

$content = <<<HTML
<div class="form-layout">
  <div>
    <div class="content-card">
      <div class="card-header"><h3><i class="fas fa-user-plus"></i> Submit a Student Referral</h3></div>
      <div id="refMsg" style="display:none"></div>

      <div class="form-group">
        <label>Student Full Name <span class="req">*</span></label>
        <div class="input-wrap"><i class="fas fa-user-graduate"></i>
          <input type="text" id="studentName" placeholder="e.g. Juan Dela Cruz"/>
        </div>
      </div>

      <div class="form-row">
        <div class="form-group">
          <label>Grade <span class="req">*</span></label>
          <div class="select-wrap"><i class="fas fa-layer-group"></i>
            <select id="gradeLevel" onchange="updateSections('sectionName')">
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
            <select id="sectionName">
              <option value="">Select Grade First</option>
            </select>
          </div>
        </div>
      </div>

      <div class="form-row">
        <div class="form-group">
          <label>Type of Concern <span class="req">*</span></label>
          <div class="select-wrap"><i class="fas fa-tag"></i>
            <select id="concernType">
              <option value="">Select</option>
              <option value="bullying">Bullying</option>
              <option value="academic">Academic</option>
              <option value="behavioral">Behavioral</option>
              <option value="emotional">Emotional / Mental Health</option>
              <option value="family">Family Issues</option>
              <option value="attendance">Attendance</option>
              <option value="other">Other</option>
            </select>
          </div>
        </div>
        <div class="form-group">
          <label>Urgency Level <span class="req">*</span></label>
          <div class="select-wrap"><i class="fas fa-exclamation-circle"></i>
            <select id="urgency">
              <option value="low">Low — Not time-sensitive</option>
              <option value="moderate" selected>Moderate — Needs attention soon</option>
              <option value="urgent">Urgent — Immediate intervention needed</option>
            </select>
          </div>
        </div>
      </div>

      <div class="form-group">
        <label>Observations / Reason for Referral <span class="req">*</span></label>
        <textarea id="observations" rows="6" maxlength="4000" placeholder="Describe your observations (max 400 words). Be as specific as possible."></textarea>
        <div class="char-count"><span id="charCount">0</span> / 400 words</div>
      </div>

      <div class="form-actions">
        <button class="btn-secondary" onclick="history.back()">Cancel</button>
        <button class="btn-primary" id="submitBtn" onclick="submitReferral()">
          <i class="fas fa-paper-plane"></i> Submit Referral
        </button>
      </div>
    </div>
  </div>

  <div class="form-sidebar">
    <div class="content-card info-card">
      <h4><i class="fas fa-info-circle" style="color:var(--maroon)"></i> About Referrals</h4>
      <p>Referrals are reviewed by the guidance counselor. You will be notified once the student has been contacted.</p>
    </div>
    <div class="content-card info-card urgent-card">
      <h4><i class="fas fa-exclamation-triangle"></i> Urgent Cases</h4>
      <p>For cases requiring immediate intervention, mark the urgency as <strong>Urgent</strong> so the counselor can prioritize.</p>
    </div>
    <div class="content-card info-card">
      <h4><i class="fas fa-lock" style="color:var(--maroon)"></i> Confidentiality</h4>
      <p>All referral information is kept confidential and only shared with authorized guidance personnel.</p>
    </div>
  </div>
</div>

<!-- Success Modal -->
<div class="modal-overlay hidden" id="successModal">
  <div class="modal-box success-modal" style="max-width:460px">
    <div class="success-icon"><i class="fas fa-check-circle"></i></div>
    <h2 class="modal-title">Referral Submitted!</h2>
    <p style="color:var(--text-2);margin-bottom:16px">The guidance counselor has been notified and will act on this referral shortly.</p>
    <div class="success-info">
      <div class="si-row"><label>Referral #</label><strong id="successRefNum" style="color:var(--maroon)">—</strong></div>
      <div class="si-row"><label>Status</label><span class="status-badge status-pending">Pending</span></div>
    </div>
    <div style="display:flex;gap:10px;margin-top:4px">
      <button class="btn-secondary" style="flex:1" onclick="resetForm()">Submit Another</button>
      <button class="btn-primary" style="flex:1" onclick="window.location.href='teacher-referrals.php'">
        View Referrals <i class="fas fa-arrow-right"></i>
      </button>
    </div>
  </div>
</div>

<script>
const GNHS_GRADE_SECTIONS = {
  '7':  ['Bonifacio', 'Luna', 'Rizal', 'Mabini', 'Aguinaldo', 'Jacinto'],
  '8':  ['Confucius', 'Goswami', 'Mandela', 'Socrates', 'Aristotle', 'Plato'],
  '9':  ['Dalton', 'Bohr', 'Lavoisier', 'Newton', 'Curie', 'Einstein'],
  '10': ['Osmena', 'Quezon', 'Magsaysay', 'Aguinaldo', 'Macapagal', 'Marcos']
};

function updateSections(sectionId) {
  const grade = document.getElementById('gradeLevel').value;
  const sel   = document.getElementById(sectionId);
  const secs  = GNHS_GRADE_SECTIONS[grade] || [];
  sel.innerHTML = secs.length
    ? '<option value="">Select Section</option>' + secs.map(s => '<option value="'+s+'">'+s+'</option>').join('')
    : '<option value="">Select Grade First</option>';
}

document.getElementById('observations')?.addEventListener('input', function(){
  let words = this.value.trim().split(/\s+/).filter(w => w.length > 0);
  document.getElementById('charCount').textContent = words.length;
});

async function submitReferral() {
  const studentName  = document.getElementById('studentName').value.trim();
  const grade        = document.getElementById('gradeLevel').value;
  const section      = document.getElementById('sectionName').value;
  const concernType  = document.getElementById('concernType').value;
  const urgency      = document.getElementById('urgency').value;
  const observations = document.getElementById('observations').value.trim();

  if (!studentName)  { showMsg('refMsg','Please enter the student name.'); return; }
  if (!grade)        { showMsg('refMsg','Please select a grade.'); return; }
  if (!section)      { showMsg('refMsg','Please select a section.'); return; }
  if (!concernType)  { showMsg('refMsg','Please select a concern type.'); return; }
  if (!observations) { showMsg('refMsg','Please describe your observations.'); return; }

  const gradeSection = 'Grade ' + grade + ' - ' + section;

  const btn = document.getElementById('submitBtn');
  setLoading(btn, true); hideEl('refMsg');

  try {
    const res = await apiPost('/gnhs-guidance/api/submit_referral.php', {
      student_name: studentName, grade_section: gradeSection,
      concern_type: concernType, urgency, observations
    });
    if (res.success) {
      document.getElementById('successRefNum').textContent = res.ref_number;
      document.getElementById('successModal').classList.remove('hidden');
    } else {
      showMsg('refMsg', res.message || 'Submission failed. Please try again.');
      setLoading(btn, false);
    }
  } catch(e) {
    showMsg('refMsg', 'Connection error. Please ensure the server is running.');
    setLoading(btn, false);
  }
}

function resetForm() {
  document.getElementById('successModal').classList.add('hidden');
  document.getElementById('studentName').value = '';
  document.getElementById('gradeLevel').value  = '';
  document.getElementById('sectionName').innerHTML = '<option value="">Select Grade First</option>';
  document.getElementById('concernType').value = '';
  document.getElementById('urgency').value     = 'moderate';
  document.getElementById('observations').value = '';
  document.getElementById('charCount').textContent = '0';
}
</script>
HTML;

renderLayout($user, 'Submit Referral', 'referral', $content);



