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
        $counselorResponse = trim($_POST['counselor_response'] ?? '');
        
        $db->prepare("UPDATE cases SET is_follow_up=0, updated_at=NOW() WHERE id=?")->execute([$caseId]);
        
        // Log counselor remarks if provided
        if (!empty($counselorResponse)) {
            $db->prepare("INSERT INTO case_notes (case_id, author_id, note_type, content, is_visible_to_student, created_at) VALUES (?, ?, 'follow_up_addressed', ?, 1, NOW())")
               ->execute([$caseId, $user['id'], "Follow-Up / Appeal Addressed: " . $counselorResponse]);
        }
        
        // Notify student that their follow-up / appeal has been addressed
        if ($cRow && !empty($cRow['student_id'])) {
            $counselorName = trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')) ?: 'Guidance Counselor';
            $notifTitle = "Follow-Up Addressed: Case #{$cRow['case_number']}";
            $notifMsg = "Your follow-up / appeal for Case #{$cRow['case_number']} has been reviewed and marked as addressed by Counselor {$counselorName}.";
            if (!empty($counselorResponse)) {
                $notifMsg .= "\n\nCounselor Remarks: \"{$counselorResponse}\"";
            }
            $notifMsg .= "\n\nThis case remains settled in your Case Archive.";
            
            $db->prepare("INSERT INTO notifications (user_id, title, message, type, is_read, created_at) VALUES (?, ?, ?, 'case_update', 0, NOW())")
               ->execute([$cRow['student_id'], $notifTitle, $notifMsg]);
        }
        
        header('Location: admin-followups.php?addressed=1');
        exit;
    } elseif ($_POST['action'] === 'reopen_case') {
        $counselorResponse = trim($_POST['counselor_response'] ?? '');
        if (mb_strlen($counselorResponse) < 5) {
            $counselorResponse = 'Reopened by Guidance Counselor after appeal review.';
        }
        $db->prepare("UPDATE cases SET status='pending', is_follow_up=0, reopen_reason=?, reopened_at=NOW(), updated_at=NOW() WHERE id=?")
           ->execute([$counselorResponse, $caseId]);
        
        $db->prepare("INSERT INTO case_notes (case_id, author_id, note_type, content, is_visible_to_student, created_at) VALUES (?, ?, 'case_reopened', ?, 1, NOW())")
           ->execute([$caseId, $user['id'], "Case Reopened upon Appeal: " . $counselorResponse]);
        
        // Notify student that their appeal has been accepted and case is reopened
        if ($cRow && !empty($cRow['student_id'])) {
            $notifTitle = "Case Reopened: Case #{$cRow['case_number']}";
            $notifMsg = "Your appeal for Case #{$cRow['case_number']} has been accepted by the Guidance Counselor. The case is now active under Pending status.\n\nReopen Reason: \"{$counselorResponse}\"";
            $db->prepare("INSERT INTO notifications (user_id, title, message, type, is_read, created_at) VALUES (?, ?, ?, 'case_update', 0, NOW())")
               ->execute([$cRow['student_id'], $notifTitle, $notifMsg]);
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
             $isAnon = !empty($f['is_anonymous']);
             $studentName = $isAnon ? 'Anonymous' : htmlspecialchars($f['first_name'].' '.$f['last_name']);
             $dateLabel = $f['follow_up_date'] ? date('M j, Y g:i A', strtotime($f['follow_up_date'])) : 'Unknown';
             $followUpPayload = $f;
             if ($isAnon) {
                 $followUpPayload['student_id'] = null;
                 $followUpPayload['first_name'] = 'Anonymous';
                 $followUpPayload['last_name'] = '';
                 $followUpPayload['grade_section'] = 'Confidential';
             }
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
                  <button type="button" class="btn-row-action" onclick="viewFollowUp(<?= htmlspecialchars(json_encode($followUpPayload)) ?>)"><i class="fas fa-eye"></i> Review Appeal</button>
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
    
    <div style="margin-bottom:16px;">
      <label style="font-size:12px;font-weight:700;color:#991b1b;text-transform:uppercase;display:block;margin-bottom:4px">Student's Appeal Explanation</label>
      <div style="background:#fef2f2;padding:14px;border-radius:8px;border:1.5px solid #fecaca;font-size:13.5px;color:#7f1d1d;line-height:1.5" id="vMessage"></div>
    </div>
    
    <form method="POST" id="followupActionForm">
      <input type="hidden" name="case_id" id="vCaseId">
      <div id="followupErrMsg" class="error-msg" style="display:none;margin-bottom:12px"></div>
      <div id="followupMainStep">
        <div style="margin-bottom:16px;">
          <label style="font-size:12px;font-weight:700;color:var(--text-2);text-transform:uppercase;display:block;margin-bottom:4px">Counselor Reason / Remarks <span class="req">*</span></label>
          <textarea name="counselor_response" id="vResponse" rows="3" class="form-control" style="width:100%;padding:10px;border-radius:8px;border:1.5px solid var(--border);font-size:13.5px;box-sizing:border-box" placeholder="Enter your reason for reopening or remarks for this appeal..."></textarea>
        </div>
        <div style="display:flex;gap:12px;">
          <button type="button" onclick="openFollowupReopenConfirm()" class="btn-primary" style="flex:1;display:inline-flex;align-items:center;justify-content:center;gap:6px"><i class="fas fa-folder-open"></i> Re-open Case (Pending)</button>
          <button type="submit" name="action" value="resolve_followup" class="btn-secondary" style="flex:1;display:inline-flex;align-items:center;justify-content:center;gap:6px"><i class="fas fa-check"></i> Mark Addressed</button>
        </div>
      </div>
      <div id="followupConfirmStep" style="display:none">
        <div style="background:#fdf4ff;border:1.5px solid #d8b4fe;border-radius:10px;padding:14px;margin-bottom:16px">
          <div style="font-size:12px;font-weight:800;color:#7e22ce;text-transform:uppercase;margin-bottom:6px"><i class="fas fa-check-double"></i> Confirm Reopening Case</div>
          <div style="font-size:13px;color:var(--text);margin-bottom:6px"><strong>Reason to be recorded &amp; sent to student:</strong></div>
          <div id="vConfirmReasonPreview" style="font-size:13px;background:#fff;padding:8px 10px;border-radius:6px;border:1px solid #e9d5ff;color:#581c87;white-space:pre-wrap"></div>
        </div>
        <div style="display:flex;gap:12px;">
          <button type="button" onclick="cancelFollowupReopenConfirm()" class="btn-secondary" style="flex:1">&larr; Back</button>
          <button type="submit" name="action" value="reopen_case" class="btn-primary" style="flex:1;background:#7e22ce;border-color:#6b21a8"><i class="fas fa-folder-open"></i> Confirm &amp; Reopen</button>
        </div>
      </div>
    </form>
  </div>
</div>

<script>
function viewFollowUp(data) {
    document.getElementById('vCaseId').value = data.id;
    document.getElementById('vCaseNum').textContent = 'Case #' + data.case_number;
    document.getElementById('vOriginal').textContent = data.description || '(No description provided)';
    document.getElementById('vMessage').textContent = data.follow_up_msg || '(No explanation)';
    const respEl = document.getElementById('vResponse');
    if (respEl) respEl.value = '';
    const errEl = document.getElementById('followupErrMsg');
    if (errEl) { errEl.style.display = 'none'; errEl.textContent = ''; }
    cancelFollowupReopenConfirm();
    document.getElementById('viewModal').classList.remove('hidden');
}

function openFollowupReopenConfirm() {
    const reason = (document.getElementById('vResponse')?.value || '').trim();
    const errEl = document.getElementById('followupErrMsg');
    if (reason.length < 5) {
        if (errEl) {
            errEl.textContent = 'Please enter the counselor reason for reopening this case (at least 5 characters).';
            errEl.style.display = 'block';
        }
        document.getElementById('vResponse')?.focus();
        return;
    }
    if (errEl) errEl.style.display = 'none';
    document.getElementById('vConfirmReasonPreview').textContent = reason;
    document.getElementById('followupMainStep').style.display = 'none';
    document.getElementById('followupConfirmStep').style.display = 'block';
}

function cancelFollowupReopenConfirm() {
    document.getElementById('followupMainStep').style.display = 'block';
    document.getElementById('followupConfirmStep').style.display = 'none';
}
</script>
<?php
$content = ob_get_clean();

renderLayout($user, 'Follow Ups', 'followups', $content);
