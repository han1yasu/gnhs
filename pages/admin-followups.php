<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/layout.php';
$user = requireLogin('admin');

$db = getDB();

// Handle resolving/reopening follow-ups
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $caseId = (int)$_POST['case_id'];
    if ($_POST['action'] === 'resolve_followup') {
        $db->prepare("UPDATE cases SET is_follow_up=0, updated_at=NOW() WHERE id=?")->execute([$caseId]);
    } elseif ($_POST['action'] === 'reopen_case') {
        $db->prepare("UPDATE cases SET status='pending', is_follow_up=0, updated_at=NOW() WHERE id=?")->execute([$caseId]);
    }
    header('Location: admin-followups.php');
    exit;
}

// Fetch follow-ups
$stmt = $db->prepare("SELECT c.*, u.first_name, u.last_name, u.grade_section 
                      FROM cases c 
                      LEFT JOIN users u ON c.student_id = u.id 
                      WHERE c.is_follow_up=1 
                      ORDER BY c.follow_up_date ASC");
$stmt->execute();
$followups = $stmt->fetchAll();

$content = <<<HTML
<div class="header-card">
  <h2><i class="fas fa-reply-all" style="color:var(--maroon);margin-right:10px"></i> Case Follow Ups</h2>
  <p style="color:var(--text-2);margin-top:6px;">Review cases that students have appealed or followed up on after being resolved.</p>
</div>

<div class="content-card" style="margin-top:20px">
  <?php if (empty(\$followups)): ?>
    <div style="text-align:center;padding:40px 20px;color:var(--text-3)">
      <i class="fas fa-check-circle" style="font-size:48px;color:var(--success);margin-bottom:16px;"></i>
      <p>No active follow-ups. All clear!</p>
    </div>
  <?php else: ?>
    <div class="table-responsive">
      <table class="data-table">
        <thead>
          <tr>
            <th>Case No.</th>
            <th>Student</th>
            <th>Original Subject</th>
            <th>Follow Up Message</th>
            <th>Date Appealed</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach (\$followups as \$f): 
             \$studentName = \$f['is_anonymous'] ? 'Anonymous' : htmlspecialchars(\$f['first_name'].' '.\$f['last_name']);
             \$dateLabel = \$f['follow_up_date'] ? date('M j, Y g:i A', strtotime(\$f['follow_up_date'])) : 'Unknown';
          ?>
            <tr>
              <td><strong><?= htmlspecialchars(\$f['case_number']) ?></strong></td>
              <td><?= \$studentName ?></td>
              <td><?= htmlspecialchars(\$f['subject']) ?></td>
              <td>
                <div style="max-width:250px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;font-size:13px;color:var(--text-2);" title="<?= htmlspecialchars(\$f['follow_up_msg']) ?>">
                  <?= htmlspecialchars(\$f['follow_up_msg']) ?>
                </div>
              </td>
              <td><span style="font-size:13px;color:var(--text-2)"><?= \$dateLabel ?></span></td>
              <td>
                <div style="display:flex;gap:8px;">
                  <button class="btn-secondary" style="padding:4px 8px;font-size:12px;" onclick="viewFollowUp(<?= htmlspecialchars(json_encode(\$f)) ?>)"><i class="fas fa-eye"></i> View</button>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

<!-- View Follow Up Modal -->
<div class="modal-overlay hidden" id="viewModal" onclick="if(event.target.id==='viewModal')this.classList.add('hidden')">
  <div class="modal-box">
    <button class="modal-close" onclick="document.getElementById('viewModal').classList.add('hidden')"><i class="fas fa-times"></i></button>
    <div class="modal-logo"><i class="fas fa-reply-all"></i></div>
    <h2 class="modal-title">Follow Up Details</h2>
    <p class="modal-sub" id="vCaseNum"></p>
    
    <div style="margin-bottom:16px;">
      <label style="font-size:12px;font-weight:700;color:var(--text-3);text-transform:uppercase">Original Concern</label>
      <div style="background:var(--bg);padding:12px;border-radius:8px;font-size:14px;color:var(--text);margin-top:4px;" id="vOriginal"></div>
    </div>
    
    <div style="margin-bottom:20px;">
      <label style="font-size:12px;font-weight:700;color:var(--danger);text-transform:uppercase">Student's Appeal Message</label>
      <div style="background:#fff3f3;padding:12px;border-radius:8px;border:1px solid #ffcccc;font-size:14px;color:var(--text);margin-top:4px;" id="vMessage"></div>
    </div>
    
    <form method="POST" style="display:flex;gap:12px;">
      <input type="hidden" name="case_id" id="vCaseId">
      <button type="submit" name="action" value="reopen_case" class="btn-primary" style="flex:1"><i class="fas fa-folder-open"></i> Re-open Case</button>
      <button type="submit" name="action" value="resolve_followup" class="btn-secondary" style="flex:1"><i class="fas fa-check"></i> Mark Addressed</button>
    </form>
  </div>
</div>

<script>
function viewFollowUp(data) {
    document.getElementById('vCaseId').value = data.id;
    document.getElementById('vCaseNum').textContent = 'Case ' + data.case_number;
    document.getElementById('vOriginal').textContent = data.description;
    document.getElementById('vMessage').textContent = data.follow_up_msg;
    document.getElementById('viewModal').classList.remove('hidden');
}
</script>
HTML;

renderLayout($user, 'Follow Ups', 'followups', $content);
