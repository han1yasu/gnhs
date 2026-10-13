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
            <select id="concernType" onchange="document.getElementById('otherConcernGroup').style.display = this.value === 'other' ? 'block' : 'none'">
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
        <div class="form-group" id="otherConcernGroup" style="display:none; width: 100%;">
          <label>Please Specify <span class="req">*</span></label>
          <div class="input-wrap"><i class="fas fa-edit"></i><input type="text" id="otherConcern" placeholder="Specify your concern type"/></div>
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
        <textarea id="observations" rows="6" maxlength="4000" placeholder="Describe your observations in detail (at least 30 characters, max 400 words). Be as specific as possible."></textarea>
        <div class="char-count" style="display:flex;justify-content:space-between;align-items:center;font-size:12px;margin-top:4px">
          <span id="charCountNotice" style="color:var(--text-3)"><i class="fas fa-info-circle"></i> Minimum 30 characters required (<span id="charCount">0</span> chars)</span>
          <span id="wordCountBadge" style="font-weight:700;color:var(--text-2)"><span id="wordCount">0</span> / 400 words</span>
        </div>
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
  '7':  ['Bonifacio', 'Burgos', 'Del Pilar', 'Diego Silang', 'Luna', 'Mabini', 'Malvar', 'Rizal'],
  '8':  ['Abrahams', 'Basho', 'Confucius', 'Ghandi', 'Goswami', 'Kalidasa', 'Mandela', 'Mencius', 'Tagore', 'Valmiki', 'Voltaire'],
  '9':  ['Bohr', 'Curie', 'Dalton', 'Darwin', 'Einstein', 'Faraday', 'Galileo', 'Newton'],
  '10': ['Aguinaldo', 'Aquino', 'Garcia', 'Laurel', 'Macapagal', 'Magsaysay']
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
  const val = this.value;
  const chars = val.length;
  const words = val.trim().split(/\s+/).filter(w => w.length > 0);
  const wordCount = words.length;

  const charEl = document.getElementById('charCount');
  const wordEl = document.getElementById('wordCount');
  const noticeEl = document.getElementById('charCountNotice');
  const badgeEl = document.getElementById('wordCountBadge');

  if (charEl) charEl.textContent = chars;
  if (wordEl) wordEl.textContent = wordCount;

  if (noticeEl) {
    if (chars < 30) {
      noticeEl.style.color = '#dc2626';
      noticeEl.innerHTML = '<i class="fas fa-exclamation-circle"></i> Minimum 30 characters required (' + chars + '/30)';
    } else {
      noticeEl.style.color = '#16a34a';
      noticeEl.innerHTML = '<i class="fas fa-check-circle"></i> Length requirement met (' + chars + ' characters)';
    }
  }

  if (badgeEl) {
    if (wordCount > 400) {
      badgeEl.style.color = '#dc2626';
    } else {
      badgeEl.style.color = 'var(--text-2)';
    }
  }
});

function validateReferralSpam(text) {
  const clean = (text || '').trim();
  if (clean.length < 30) {
    return { valid: false, message: 'Observations must be at least 30 characters long to provide sufficient detail (currently ' + clean.length + ' characters).' };
  }
  const words = clean.split(/\s+/).filter(w => w.length > 0);
  if (words.length > 400) {
    return { valid: false, message: 'Observations exceed the 400-word ceiling (currently ' + words.length + ' words). Please shorten your observations.' };
  }
  // Repeated characters 5+ consecutively
  if (/(.)\1{4,}/u.test(clean)) {
    return { valid: false, message: 'Referral rejected: Observations contain repeated characters or symbols. Please provide meaningful observations.' };
  }
  // Repeating patterns (e.g. asdfasdfasdf)
  if (/(.{2,8})\1{3,}/i.test(clean)) {
    return { valid: false, message: 'Referral rejected: Observations contain repeated patterns or spam text. Please describe your observations in natural words.' };
  }
  // Vowel ratio
  const letters = clean.replace(/[^a-zA-Z]/g, '');
  if (letters.length >= 20) {
    const vowels = (letters.match(/[aeiouAEIOU]/g) || []).length;
    const ratio = vowels / letters.length;
    if (ratio < 0.15) {
      return { valid: false, message: 'Referral rejected: Observations contain random consonants or keyboard mash. Please write meaningful sentences.' };
    }
    if (ratio > 0.85) {
      return { valid: false, message: 'Referral rejected: Observations contain abnormal character patterns. Please describe your observations clearly.' };
    }
  }
  // Repeated words
  if (words.length >= 6) {
    const unique = new Set(words.map(w => w.toLowerCase()));
    if (unique.size / words.length < 0.35) {
      return { valid: false, message: 'Referral rejected: Observations contain excessively repeated words. Please provide detailed observations.' };
    }
  }
  // Gibberish words & keyboard mash
  const keyboardPatterns = ['asdfgh','sdfghj','dfghjk','fghjkl','qwerty','wertyu','ertyui','rtyuio','tyuiop','zxcvbn','xcvbnm','lkjhgf','kjhgfd','jhgfdsa'];
  let gibberishWords = 0;
  let checkedWords = 0;
  for (const w of words) {
    const cleanW = w.replace(/[^a-zA-Z]/g, '').toLowerCase();
    if (cleanW.length >= 4) {
      checkedWords++;
      let isGibberish = false;
      for (const kp of keyboardPatterns) {
        if (cleanW.includes(kp)) { isGibberish = true; break; }
      }
      if (/[bcdfghjklmnpqrstvwxyz]{5,}/i.test(cleanW)) isGibberish = true;
      if (cleanW.length >= 5 && !/[aeiouy]/i.test(cleanW)) isGibberish = true;
      if (isGibberish) gibberishWords++;
    }
  }
  if (checkedWords >= 2 && (gibberishWords / checkedWords) >= 0.40) {
    return { valid: false, message: 'Referral rejected: Observations contain random letters, gibberish words, or meaningless keyboard mash. Please write valid observations.' };
  }
  if (checkedWords === 1 && gibberishWords === 1) {
    return { valid: false, message: 'Referral rejected: Observations contain gibberish or meaningless text. Please write valid observations.' };
  }
  return { valid: true, message: '' };
}

async function submitReferral() {
  const studentName  = document.getElementById('studentName').value.trim();
  const grade        = document.getElementById('gradeLevel').value;
  const section      = document.getElementById('sectionName').value;
  let concernType  = document.getElementById('concernType').value;
  if (concernType === 'other') {
    const otherVal = document.getElementById('otherConcern').value.trim();
    if (!otherVal) { showMsg('refMsg', 'Please specify your other concern.'); return; }
    concernType = 'Other: ' + otherVal;
  }
  const urgency      = document.getElementById('urgency').value;
  const observations = document.getElementById('observations').value.trim();

  if (!studentName)  { showMsg('refMsg','Please enter the student name.'); return; }
  if (studentName.length < 3) { showMsg('refMsg','Student name must be at least 3 characters.'); document.getElementById('studentName').focus(); return; }
  if (!grade)        { showMsg('refMsg','Please select a grade.'); return; }
  if (!section)      { showMsg('refMsg','Please select a section.'); return; }
  if (!concernType)  { showMsg('refMsg','Please select a concern type.'); return; }
  if (!observations) { showMsg('refMsg','Please describe your observations.'); return; }

  // Quality check: 30 chars min, 400 words max, spam / nonexistent wordings check
  const quality = validateReferralSpam(observations);
  if (!quality.valid) {
    showMsg('refMsg', quality.message);
    document.getElementById('observations').focus();
    return;
  }

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

// Auto-fill from URL params (e.g. from Advisory Concern summary modal)
window.addEventListener('DOMContentLoaded', () => {
  const p = new URLSearchParams(window.location.search);
  const name = p.get('student_name');
  const grade = p.get('grade');
  const section = p.get('section');
  const concern = p.get('concern_type');
  if (name) {
    const nameEl = document.getElementById('studentName');
    if (nameEl) nameEl.value = name;
  }
  if (grade) {
    const gEl = document.getElementById('gradeLevel');
    if (gEl) {
      gEl.value = grade;
      updateSections('sectionName');
      if (section) {
        const sEl = document.getElementById('sectionName');
        if (sEl) sEl.value = section;
      }
    }
  }
  if (concern) {
    const cEl = document.getElementById('concernType');
    if (cEl) {
      // Map possible concern types
      const opt = cEl.querySelector('option[value="' + concern + '"]');
      if (opt) {
        cEl.value = concern;
      } else if (concern.includes('peer')) {
        cEl.value = 'behavioral';
      }
    }
  }
});
</script>
HTML;

renderLayout($user, 'Submit Referral', 'referral', $content);



