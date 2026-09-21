<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/layout.php';
$user = requireLogin('student');

$content = <<<HTML
<div class="form-layout">
  <div>
    <div class="content-card">
      <div class="card-header"><h3><i class="fas fa-file-medical"></i> Submit a Concern</h3></div>
      <div id="submitMsg" style="display:none"></div>

      <div class="form-group">
        <label>Type of Concern <span class="req">*</span></label>
        <div class="select-wrap"><i class="fas fa-tag"></i>
          <select id="concernType">
            <option value="">Select Concern Type</option>
            <option value="bullying">Bullying</option>
            <option value="academic_stress">Academic Stress</option>
            <option value="emotional">Emotional / Mental Health</option>
            <option value="family">Family Issues</option>
            <option value="peer_conflict">Peer Conflict</option>
            <option value="behavioral">Behavioral</option>
            <option value="other">Other</option>
          </select>
        </div>
      </div>

      <div class="form-group">
        <label>Subject / Title <span class="req">*</span></label>
        <div class="input-wrap"><i class="fas fa-heading"></i><input type="text" id="subject" placeholder="Brief title of your concern"/></div>
      </div>

      <div class="form-group">
        <label>Incident Date</label>
        <div class="input-wrap"><i class="fas fa-calendar"></i><input type="date" id="incidentDate"/></div>
      </div>

      <div class="form-group">
        <label>Describe Your Concern <span class="req">*</span></label>
        <textarea id="description" rows="6" maxlength="4000" placeholder="Please describe your concern in detail (max 400 words). Everything you share is confidential."></textarea>
        <div class="char-count"><span id="charCount">0</span> / 400 words</div>
      </div>

      <div class="form-group">
        <label>Priority Level</label>
        <div class="prio-group">
          <div class="prio-opt low-opt" onclick="selectPrio(this,'low')"><i class="fas fa-circle"></i> Low</div>
          <div class="prio-opt med-opt selected" onclick="selectPrio(this,'medium')"><i class="fas fa-circle"></i> Medium</div>
          <div class="prio-opt high-opt" onclick="selectPrio(this,'high')"><i class="fas fa-circle"></i> High</div>
        </div>
      </div>

      <div class="form-group">
        <label>Preferred Contact Method</label>
        <div class="select-wrap"><i class="fas fa-comments"></i>
          <select id="preferredContact">
            <option value="in_person">In Person</option>
            <option value="online">Online</option>
            <option value="written">Written</option>
          </select>
        </div>
      </div>

      <div class="form-group">
        <label class="checkbox-label" style="font-size:14px">
          <input type="checkbox" id="isAnonymous"> Submit anonymously (your name will not be attached)
        </label>
      </div>

      <div class="form-actions">
        <button class="btn-secondary" onclick="history.back()">Cancel</button>
        <button class="btn-primary" id="submitBtn" onclick="submitConcern()"><i class="fas fa-paper-plane"></i> Submit Concern</button>
      </div>
    </div>
  </div>

  <div class="form-sidebar">
    <div class="content-card info-card">
      <h4><i class="fas fa-shield-heart" style="color:var(--maroon)"></i> Confidentiality</h4>
      <p>Everything you share here is kept strictly confidential and only accessible to guidance counselors.</p>
    </div>
    <div class="content-card info-card urgent-card">
      <h4><i class="fas fa-exclamation-triangle"></i> In Immediate Danger?</h4>
      <p>If you or someone else is in immediate danger, please seek help right away.</p>
      <div class="urgent-contact"><i class="fas fa-phone"></i> Contact a teacher immediately</div>
    </div>
    <div class="content-card info-card">
      <h4><i class="fas fa-question-circle" style="color:var(--maroon)"></i> What Happens Next?</h4>
      <p>A counselor will review your concern and respond within 1-3 school days. You'll receive a notification when there's an update.</p>
    </div>
  </div>
</div>

<!-- Success Modal -->
<div class="modal-overlay hidden" id="successModal">
  <div class="modal-box success-modal" style="max-width:480px">
    <div class="success-icon"><i class="fas fa-check-circle"></i></div>
    <h2 class="modal-title">Concern Submitted!</h2>
    <p style="color:var(--text-2);margin-bottom:16px">Your concern has been submitted and a guidance counselor will review it shortly.</p>
    <div class="success-info">
      <div class="si-row"><label>Case Number</label><strong id="successCaseNum" style="color:var(--maroon)">—</strong></div>
      <div class="si-row"><label>Status</label><span class="status-badge status-pending">Pending</span></div>
    </div>
    <button class="btn-primary" style="width:100%" onclick="window.location.href='student-cases.php'">View My Cases <i class="fas fa-arrow-right"></i></button>
  </div>
</div>

<script>
document.getElementById('description')?.addEventListener('input', function(){
  let words = this.value.trim().split(/\s+/).filter(w => w.length > 0);
  document.getElementById('charCount').textContent = words.length;
});

async function submitConcern() {
  const concernType = document.getElementById('concernType').value;
  const subject     = document.getElementById('subject').value.trim();
  const description = document.getElementById('description').value.trim();
  const priority    = document.querySelector('.prio-opt.selected')?.classList.contains('high-opt') ? 'high' :
                      document.querySelector('.prio-opt.selected')?.classList.contains('low-opt')  ? 'low' : 'medium';
  const isAnonymous = document.getElementById('isAnonymous').checked;
  const incidentDate= document.getElementById('incidentDate').value;
  const preferredContact = document.getElementById('preferredContact').value;

  if (!concernType || !subject || !description) {
    showMsg('submitMsg', 'Please fill in all required fields.'); return;
  }

  const btn = document.getElementById('submitBtn');
  setLoading(btn, true); hideEl('submitMsg');

  try {
    const res = await apiPost('/gnhs-guidance/api/submit_case.php', {
      concern_type: concernType, subject, description, priority,
      is_anonymous: isAnonymous, incident_date: incidentDate, preferred_contact: preferredContact
    });
    if (res.success) {
      document.getElementById('successCaseNum').textContent = res.case_number;
      document.getElementById('successModal').classList.remove('hidden');
    } else {
      showMsg('submitMsg', res.message || 'Submission failed. Please try again.');
      setLoading(btn, false);
    }
  } catch(e) {
    showMsg('submitMsg', 'Connection error. Please check your server and try again.');
    setLoading(btn, false);
  }
}
</script>
HTML;

renderLayout($user, 'Submit a Concern', 'submit', $content);



