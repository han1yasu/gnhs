<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/layout.php';
$user = requireLogin('admin');
$db   = getDB();

// Summary stats
$totalCases   = (int)$db->query("SELECT COUNT(*) FROM cases")->fetchColumn();
$pendingCases = (int)$db->query("SELECT COUNT(*) FROM cases WHERE status IN ('pending','under_review')")->fetchColumn();
$resolved     = (int)$db->query("SELECT COUNT(*) FROM cases WHERE status='resolved'")->fetchColumn();
$highPrio     = (int)$db->query("SELECT COUNT(*) FROM cases WHERE priority='high' AND status NOT IN ('resolved','closed')")->fetchColumn();
$totalRefs    = (int)$db->query("SELECT COUNT(*) FROM referrals WHERE status='pending'")->fetchColumn();
$students     = (int)$db->query("SELECT COUNT(*) FROM users WHERE role='student' AND is_active=1")->fetchColumn();

// Recent cases
$recentCases = $db->query("SELECT c.*,
    CASE WHEN c.is_anonymous=1 THEN 'Anonymous' ELSE CONCAT(s.first_name,' ',s.last_name) END AS student_name,
    s.grade_section
    FROM cases c LEFT JOIN users s ON c.student_id=s.id
    ORDER BY c.submitted_at DESC LIMIT 8")->fetchAll();

// Recent referrals
$recentRefs = $db->query("SELECT r.*,CONCAT(t.first_name,' ',t.last_name) AS teacher_name
    FROM referrals r JOIN users t ON r.teacher_id=t.id
    ORDER BY r.submitted_at DESC LIMIT 5")->fetchAll();

function badge(string $s): string {
    $map=['pending'=>'status-pending','under_review'=>'status-under_review','ongoing'=>'status-ongoing','resolved'=>'status-resolved','closed'=>'status-resolved'];
    return "<span class='status-badge ".($map[$s]??'status-pending')."'>".str_replace('_',' ',ucfirst($s))."</span>";
}
function prioBadge(string $p): string {
    $map=['high'=>'priority-high','medium'=>'priority-medium','low'=>'priority-low'];
    return "<span class='priority-badge ".($map[$p]??'priority-low')."'><i class='fas fa-circle'></i>".ucfirst($p)."</span>";
}
function urgencyBadge(string $u): string {
    $map=['urgent'=>'priority-high','moderate'=>'priority-medium','low'=>'priority-low'];
    return "<span class='priority-badge ".($map[$u]??'priority-low')."'><i class='fas fa-circle'></i>".ucfirst($u)."</span>";
}

$caseRows = '';
foreach ($recentCases as $c) {
    $type = ucfirst(str_replace('_',' ',$c['concern_type']));
    $date = date('M d, Y', strtotime($c['submitted_at']));
    $student = htmlspecialchars($c['student_name']);
    $gs = htmlspecialchars($c['grade_section'] ?? '—');
    $caseRows .= "<tr>
        <td><strong style='color:var(--maroon)'>{$c['case_number']}</strong></td>
        <td>$student</td>
        <td>$gs</td>
        <td>$type</td>
        <td>".badge($c['status'])."</td>
        <td>".prioBadge($c['priority'])."</td>
        <td style='color:var(--text-3);font-size:12px'>$date</td>
        <td><button class='btn-sm-outline' onclick='openCaseReview({$c['id']},\"{$c['case_number']}\",\"{$c['status']}\",\"{$c['priority']}\",".json_encode($c['subject']).",".json_encode($c['description']).")'>
          <i class='fas fa-edit'></i> Review</button></td>
    </tr>";
}
if (!$caseRows) $caseRows = "<tr><td colspan='8' style='text-align:center;color:var(--text-3);padding:32px'>No cases submitted yet.</td></tr>";

$refRows = '';
foreach ($recentRefs as $r) {
    $type = ucfirst(str_replace('_',' ',$r['concern_type']));
    $date = date('M d, Y', strtotime($r['submitted_at']));
    $refRows .= "<tr>
        <td><strong style='color:var(--maroon)'>{$r['ref_number']}</strong></td>
        <td>".htmlspecialchars($r['student_name'])."</td>
        <td>".htmlspecialchars($r['grade_section'])."</td>
        <td>$type</td>
        <td>".urgencyBadge($r['urgency'])."</td>
        <td>".htmlspecialchars($r['teacher_name'])."</td>
        <td style='color:var(--text-3);font-size:12px'>$date</td>
    </tr>";
}
if (!$refRows) $refRows = "<tr><td colspan='7' style='text-align:center;color:var(--text-3);padding:24px'>No referrals yet.</td></tr>";

$content = <<<HTML
<div class="summary-cards">
  <div class="sum-card">
    <div class="sum-icon" style="background:#fff0f0;color:var(--maroon)"><i class="fas fa-folder-open"></i></div>
    <div><div class="sum-num">$totalCases</div><div class="sum-label">Total Cases</div></div>
  </div>
  <div class="sum-card">
    <div class="sum-icon" style="background:#fffbeb;color:#d97706"><i class="fas fa-clock"></i></div>
    <div><div class="sum-num">$pendingCases</div><div class="sum-label">Pending Review</div></div>
  </div>
  <div class="sum-card">
    <div class="sum-icon" style="background:#fef2f2;color:#dc2626"><i class="fas fa-exclamation-triangle"></i></div>
    <div><div class="sum-num">$highPrio</div><div class="sum-label">High Priority</div></div>
  </div>
  <div class="sum-card">
    <div class="sum-icon" style="background:#f0fdf4;color:#16a34a"><i class="fas fa-check-circle"></i></div>
    <div><div class="sum-num">$resolved</div><div class="sum-label">Resolved</div></div>
  </div>
  <div class="sum-card">
    <div class="sum-icon" style="background:#eff6ff;color:#2563eb"><i class="fas fa-exchange-alt"></i></div>
    <div><div class="sum-num">$totalRefs</div><div class="sum-label">Pending Referrals</div></div>
  </div>
  <div class="sum-card">
    <div class="sum-icon" style="background:#fdf4ff;color:#7c3aed"><i class="fas fa-user-graduate"></i></div>
    <div><div class="sum-num">$students</div><div class="sum-label">Active Students</div></div>
  </div>
</div>

<div class="content-card" style="margin-bottom:22px">
  <div class="card-header">
    <h3><i class="fas fa-folder-open"></i> Recent Cases</h3>
    <a href="admin-cases.php" class="btn-sm-outline">View All <i class="fas fa-arrow-right"></i></a>
  </div>
  <div class="table-wrap">
    <table class="data-table">
      <thead><tr><th>Case #</th><th>Student</th><th>Grade</th><th>Type</th><th>Status</th><th>Priority</th><th>Date</th><th>Action</th></tr></thead>
      <tbody>$caseRows</tbody>
    </table>
  </div>
</div>

<div class="content-card">
  <div class="card-header">
    <h3><i class="fas fa-exchange-alt"></i> Recent Referrals</h3>
    <a href="admin-referrals.php" class="btn-sm-outline">View All <i class="fas fa-arrow-right"></i></a>
  </div>
  <div class="table-wrap">
    <table class="data-table">
      <thead><tr><th>Ref #</th><th>Student</th><th>Grade</th><th>Concern</th><th>Urgency</th><th>Referred By</th><th>Date</th></tr></thead>
      <tbody>$refRows</tbody>
    </table>
  </div>
</div>

<!-- Case Review Modal -->
<div class="modal-overlay hidden" id="caseReviewModal" onclick="closeCaseReview(event)">
  <div class="modal-box" style="max-width:700px">
    <button class="modal-close" onclick="document.getElementById('caseReviewModal').classList.add('hidden')"><i class="fas fa-times"></i></button>
    <h2 class="modal-title">Review Case</h2>
    <div id="caseReviewBody"></div>
  </div>
</div>
HTML;

renderLayout($user, 'Admin Dashboard', 'dashboard', $content);
