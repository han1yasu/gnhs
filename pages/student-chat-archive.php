<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/layout.php';
$user = requireLogin('student');

$db = getDB();
// Fetch resolved cases for this student
$stmt = $db->prepare("SELECT * FROM cases WHERE student_id=? AND status='resolved' ORDER BY updated_at DESC, submitted_at DESC");
$stmt->execute([$user['id']]);
$cases = $stmt->fetchAll();

ob_start();
?>
<div class="header-card">
  <h2><i class="fas fa-comments" style="color:var(--maroon);margin-right:10px"></i> Chat Archive</h2>
  <p style="color:var(--text-2);margin-top:6px;">View your past chat conversations for resolved cases.</p>
</div>

<div class="content-card" style="margin-top:20px; border:none; box-shadow:var(--shadow-sm)">
  <?php if (empty($cases)): ?>
    <div style="text-align:center;padding:80px 20px;display:flex;flex-direction:column;align-items:center;justify-content:center;">
      <div style="width:120px;height:120px;background:rgba(128,0,0,0.05);border-radius:50%;display:flex;align-items:center;justify-content:center;margin-bottom:24px;">
        <i class="fas fa-archive" style="font-size:54px;color:var(--maroon);"></i>
      </div>
      <h3 style="font-size:22px;font-weight:800;color:var(--text);margin-bottom:10px;">Archive is Empty</h3>
      <p style="color:var(--text-3);max-width:400px;line-height:1.6;">You don't have any resolved chats yet. Once a guidance counselor resolves your case, the conversation history will be safely stored here for your reference.</p>
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
          <?php foreach ($cases as $c): 
             $typeLabel = ucwords(str_replace('_',' ',$c['concern_type']));
             $dateLabel = $c['updated_at'] ? date('M j, Y g:i A', strtotime($c['updated_at'])) : 'Unknown';
          ?>
            <tr>
              <td><strong style="color:var(--maroon)">#<?= htmlspecialchars($c['case_number']) ?></strong></td>
              <td><?= htmlspecialchars($c['subject']) ?></td>
              <td><?= htmlspecialchars($typeLabel) ?></td>
              <td><span style="color:var(--text-2);font-size:13px;"><?= $dateLabel ?></span></td>
              <td>
                  <button class="btn-secondary" style="padding:6px 14px;font-size:13px;border-radius:8px;font-weight:700;" onclick="window.location.href='student-chats.php?archive_id=<?= $c['id'] ?>'">
                    <i class="fas fa-comments"></i> View Chat
                  </button>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>
<?php
$content = ob_get_clean();

renderLayout($user, 'Chat Archive', 'chat_archive', $content);
