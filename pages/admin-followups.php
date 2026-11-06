<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/layout.php';
$user = requireLogin('admin');

$db = getDB();

// Handle resolving/reopening follow-ups
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['action'])) {
    $caseId = (int)$_POST['case_id'];
    
    // Fetch case details for notification & redirection
    $cStmt = $db->prepare("SELECT id, case_number, student_id FROM cases WHERE id=?");
    $cStmt->execute([$caseId]);
    $cRow = $cStmt->fetch();

    if ($_POST['action'] === 'resolve_followup') {
        $db->prepare("UPDATE cases SET is_follow_up=0, updated_at=NOW() WHERE id=?")->execute([$caseId]);
        header('Location: admin-followups.php?addressed=1');
        exit;
    } elseif ($_POST['action'] === 'reopen_case') {
        $db->prepare("UPDATE cases SET status='pending', is_follow_up=0, updated_at=NOW() WHERE id=?")->execute([$caseId]);
        
        // Notify student that their appeal has been accepted and case is reopened
        if ($cRow) {
            $db->prepare("INSERT INTO notifications (user_id, title, message, type) VALUES (?, ?, ?, 'case_update')")
               ->execute([
                   $cRow['student_id'],
                   "Case Reopened: {$cRow['case_number']}",
                   "Your appeal for Case {$cRow['case_number']} has been accepted by the Guidance Counselor. The case is now active under review."
               ]);
            header('Location: admin-cases.php?reopened=1&case=' . urlencode($cRow['case_number']));
            exit;
        }
        header('Location: admin-cases.php?reopened=1');
        exit;
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

ob_start();
?>
<div class="header-card">
  <h2><i class="fas fa-reply-all" style="color:var(--maroon);margin-right:10px"></i> Case Follow Ups & Appeals</h2>
  <p style="color:var(--text-2);margin-top:6px;">Review cases that students have appealed or followed up on after being resolved.</p>
</div>

<div class="content-card" style="margin-top:20px">
  <?php if (empty($followups)): ?>
    <div style="text-align:center;padding:50px 20px;color:var(--text-3)">
      <div style="width:64px;height:64px;border-radius:50%;background:#f0fdf4;color:#16a34a;display:flex;align-items:center;justify-content:center;margin:0 auto 14px;font-size:26px">
        <i class="fas fa-check-circle"></i>
      </div>
      <h3 style="font-size:16px;font-weight:700;color:var(--text);margin-bottom:4px">No Active Appeals</h3>
      <p style="font-size:13.5px">All resolved case follow-ups are clear. Any student appeals will appear here immediately.</p>
    </div>
  <?php else: ?>
    <div class="table-wrap">
      <table class="data-table">
        <thead>
          <tr>
            <th>Case No.</th>
            <th>Student</th>
            <th>Original Subject</th>
            <th>Follow Up / Appeal Reason</th>
            <th>Date Appealed</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($followups as $f): 
             $studentName = $f['is_anonymous'] ? 'Anonymous' : htmlspecialchars($f['first_name'].' '.$f['last_name']);
             $dateLabel = $f['follow_up_date'] ? date('M j, Y g:i A', strtotime($f['follow_up_date'])) : 'Unknown';
          ?>
            <tr>
              <td><strong style="color:var(--maroon)">#<?= htmlspecialchars($f['case_number']) ?></strong></td>
              <td><?= $studentName ?></td>
              <td style="font-weight:600"><?= htmlspecialchars($f['subject']) ?></td>
              <td>
                <div style="max-width:280px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;font-size:13px;color:var(--text-2);" title="<?= htmlspecialchars($f['follow_up_msg']) ?>">
                  <?= htmlspecialchars($f['follow_up_msg']) ?>
                </div>
              </td>
              <td><span style="font-size:13px;color:var(--text-2)"><?= $dateLabel ?></span></td>
              <td>
                <div style="display:flex;gap:8px;">
                  <button type="button" class="btn-row-action" onclick="viewFollowUp(<?= htmlspecialchars(json_encode($f)) ?>)"><i class="fas fa-eye"></i> Review Appeal</button>
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
  <div class="modal-box" style="max-width:560px">
    <button class="modal-close" onclick="document.getElementById('viewModal').classList.add('hidden')"><i class="fas fa-times"></i></button>
    <div style="width:48px;height:48px;border-radius:12px;background:rgba(85,0,0,0.08);color:var(--maroon);display:flex;align-items:center;justify-content:center;margin:0 auto 12px;font-size:20px">
      <i class="fas fa-reply-all"></i>
    </div>
    <h2 class="modal-title" style="text-align:center">Student Case Appeal</h2>
    <p class="modal-sub" id="vCaseNum" style="text-align:center;margin-bottom:16px"></p>
    
    <div style="margin-bottom:14px;">
      <label style="font-size:12px;font-weight:700;color:var(--text-3);text-transform:uppercase;display:block;margin-bottom:4px">Original Concern</label>
      <div style="background:var(--bg2);padding:12px;border-radius:8px;font-size:13.5px;color:var(--text);border:1px solid var(--border);max-height:140px;overflow-y:auto" id="vOriginal"></div>
    </div>
    
    <div style="margin-bottom:20px;">
      <label style="font-size:12px;font-weight:700;color:#991b1b;text-transform:uppercase;display:block;margin-bottom:4px">Student's Appeal Explanation</label>
      <div style="background:#fef2f2;padding:14px;border-radius:8px;border:1.5px solid #fecaca;font-size:13.5px;color:#7f1d1d;line-height:1.5" id="vMessage"></div>
    </div>
    
    <form method="POST" style="display:flex;gap:12px;">
      <input type="hidden" name="case_id" id="vCaseId">
      <button type="submit" name="action" value="reopen_case" class="btn-primary" style="flex:1;display:inline-flex;align-items:center;justify-content:center;gap:6px"><i class="fas fa-folder-open"></i> Re-open Case (Pending)</button>
      <button type="submit" name="action" value="resolve_followup" class="btn-secondary" style="flex:1;display:inline-flex;align-items:center;justify-content:center;gap:6px"><i class="fas fa-check"></i> Mark Addressed</button>
    </form>
  </div>
</div>

<script>
function viewFollowUp(data) {
    document.getElementById('vCaseId').value = data.id;
    document.getElementById('vCaseNum').textContent = 'Case #' + data.case_number;
    document.getElementById('vOriginal').textContent = data.description || '(No description provided)';
    document.getElementById('vMessage').textContent = data.follow_up_msg || '(No explanation)';
    document.getElementById('viewModal').classList.remove('hidden');
}
</script>
<?php
$content = ob_get_clean();

renderLayout($user, 'Follow Ups', 'followups', $content);
