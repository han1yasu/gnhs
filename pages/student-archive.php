<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/layout.php';
$user = requireLogin('student');

$db = getDB();
// Fetch resolved cases for this student
$stmt = $db->prepare("SELECT * FROM cases WHERE student_id=? AND status='resolved' ORDER BY updated_at DESC, submitted_at DESC");
$stmt->execute([$user['id']]);
$cases = $stmt->fetchAll();

$content = <<<HTML
<div class="header-card">
  <h2><i class="fas fa-archive" style="color:var(--maroon);margin-right:10px"></i> Case Archive</h2>
  <p style="color:var(--text-2);margin-top:6px;">View your past resolved cases. If you feel a concern was not properly resolved, you may submit an appeal or follow up.</p>
</div>

<div class="content-card" style="margin-top:20px">
  <?php if (empty(\$cases)): ?>
    <div style="text-align:center;padding:40px 20px;color:var(--text-3)">
      <i class="fas fa-box-open" style="font-size:48px;color:var(--border);margin-bottom:16px;"></i>
      <p>You have no resolved cases in your archive.</p>
    </div>
  <?php else: ?>
    <div class="table-responsive">
      <table class="data-table">
        <thead>
          <tr>
            <th>Case No.</th>
            <th>Subject</th>
            <th>Type</th>
            <th>Resolved Date</th>
            <th>Action</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach (\$cases as \$c): 
             \$typeLabel = ucwords(str_replace('_',' ',\$c['concern_type']));
             \$dateLabel = \$c['updated_at'] ? date('M j, Y g:i A', strtotime(\$c['updated_at'])) : 'Unknown';
          ?>
            <tr>
              <td><strong><?= htmlspecialchars(\$c['case_number']) ?></strong></td>
              <td><?= htmlspecialchars(\$c['subject']) ?></td>
              <td><?= htmlspecialchars(\$typeLabel) ?></td>
              <td><span style="color:var(--text-2);font-size:13px;"><?= \$dateLabel ?></span></td>
              <td>
                <?php if (\$c['is_follow_up']): ?>
                  <span style="font-size:12px;color:var(--text-3);background:var(--bg2);padding:4px 8px;border-radius:4px;font-weight:600;"><i class="fas fa-reply"></i> Appealed</span>
                <?php else: ?>
                  <button class="btn-secondary" style="padding:6px 12px;font-size:12px;" onclick="openAppealModal(<?= \$c['id'] ?>, '<?= htmlspecialchars(\$c['case_number']) ?>')">
                    <i class="fas fa-reply"></i> Appeal
                  </button>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

<!-- Appeal Modal -->
<div class="modal-overlay hidden" id="appealModal" onclick="if(event.target.id==='appealModal')this.classList.add('hidden')">
  <div class="modal-box">
    <button class="modal-close" onclick="document.getElementById('appealModal').classList.add('hidden')"><i class="fas fa-times"></i></button>
    <div class="modal-logo"><i class="fas fa-reply-all"></i></div>
    <h2 class="modal-title">Appeal Case <span id="appealCaseNum"></span></h2>
    <p class="modal-sub">Submit a follow-up if your concern wasn't fully resolved.</p>
    
    <div id="appealMsg" style="display:none;margin-bottom:16px;"></div>
    
    <input type="hidden" id="appealCaseId">
    
    <div class="form-group">
      <label>Reason for Appeal / Follow up <span class="req">*</span></label>
      <textarea id="appealDescription" rows="5" maxlength="4000" placeholder="Please describe why you are reopening or following up on this case (max 400 words)." style="width:100%;padding:14px;border-radius:12px;border:2px solid var(--border);resize:vertical;font-family:inherit;"></textarea>
    </div>
    
    <button class="btn-primary" style="width:100%" onclick="submitAppeal()">Submit Appeal</button>
  </div>
</div>

<script>
function openAppealModal(id, caseNum) {
    document.getElementById('appealCaseId').value = id;
    document.getElementById('appealCaseNum').textContent = caseNum;
    document.getElementById('appealDescription').value = '';
    document.getElementById('appealMsg').style.display = 'none';
    document.getElementById('appealModal').classList.remove('hidden');
}

async function submitAppeal() {
    const id = document.getElementById('appealCaseId').value;
    const desc = document.getElementById('appealDescription').value.trim();
    const msgBox = document.getElementById('appealMsg');
    
    if(!desc) {
        msgBox.innerHTML = '<div class="error-msg">Please provide a reason for your appeal.</div>';
        msgBox.style.display = 'block';
        return;
    }
    
    try {
        const res = await fetch('/gnhs-guidance/api/submit_appeal.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({ case_id: id, message: desc })
        });
        const data = await res.json();
        if(data.success) {
            msgBox.innerHTML = '<div class="success-msg">Appeal submitted successfully.</div>';
            msgBox.style.display = 'block';
            setTimeout(() => location.reload(), 1500);
        } else {
            msgBox.innerHTML = '<div class="error-msg">' + (data.message || 'Error occurred') + '</div>';
            msgBox.style.display = 'block';
        }
    } catch(e) {
        msgBox.innerHTML = '<div class="error-msg">Server error.</div>';
        msgBox.style.display = 'block';
    }
}
</script>
HTML;

renderLayout($user, 'Case Archive', 'archive', $content);
