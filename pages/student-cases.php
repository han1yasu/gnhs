<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/layout.php';
$user = requireLogin('student');
$db   = getDB();
$uid  = $user['id'];

$filterStatus = $_GET['status'] ?? '';
$where = "WHERE student_id=$uid";
if ($filterStatus) $where .= " AND status='".addslashes($filterStatus)."'";

$cases = $db->query("SELECT * FROM cases $where ORDER BY submitted_at DESC")->fetchAll();

function badge(string $s): string {
    $map=['pending'=>'status-pending','under_review'=>'status-under_review','ongoing'=>'status-ongoing','resolved'=>'status-resolved','closed'=>'status-resolved'];
    return "<span class='status-badge ".($map[$s]??'status-pending')."'>".str_replace('_',' ',ucfirst($s))."</span>";
}
function prioBadge(string $p): string {
    $map=['high'=>'priority-high','medium'=>'priority-medium','low'=>'priority-low'];
    return "<span class='priority-badge ".($map[$p]??'priority-low')."'><i class='fas fa-circle'></i>".ucfirst($p)."</span>";
}

$rows = '';
foreach ($cases as $c) {
    $type = ucfirst(str_replace('_',' ',$c['concern_type']));
    $date = date('M d, Y', strtotime($c['submitted_at']));
    $anonBadge = !empty($c['is_anonymous']) ? " <span style='font-size:11px;background:#f1f5f9;color:#475569;border:1px solid #cbd5e1;padding:2px 7px;border-radius:6px;font-weight:700;display:inline-flex;align-items:center;gap:4px;' title='Your identity is anonymous to the guidance counselor'><i class='fas fa-user-secret'></i> Anonymous</span>" : "";
    $rows .= "<tr>
        <td><strong style='color:var(--maroon)'>{$c['case_number']}</strong>$anonBadge</td>
        <td>".htmlspecialchars($c['subject'])."</td>
        <td>$type</td>
        <td>".badge($c['status'])."</td>
        <td>".prioBadge($c['priority'])."</td>
        <td style='color:var(--text-3);font-size:13px'>$date</td>
        <td><button class='btn-sm-outline' onclick='openCaseModal({$c['id']})'><i class='fas fa-eye'></i> View</button></td>
    </tr>";
}
if (!$rows) $rows = "<tr><td colspan='7' style='text-align:center;color:var(--text-3);padding:40px'>No cases found. <a href='student-submit.php' style='color:var(--maroon);font-weight:700'>Submit a concern →</a></td></tr>";

$selPending    = $filterStatus==='pending'      ? 'selected' : '';
$selReview     = $filterStatus==='under_review' ? 'selected' : '';
$selOngoing    = $filterStatus==='ongoing'      ? 'selected' : '';
$selResolved   = $filterStatus==='resolved'     ? 'selected' : '';

$content = <<<HTML
<div class="content-card">
  <div class="card-header">
    <h3><i class="fas fa-folder-open"></i> My Cases</h3>
    <div style="display:flex;gap:10px;align-items:center">
      <select class="status-select" onchange="window.location='student-cases.php?status='+this.value">
        <option value="">All Statuses</option>
        <option value="pending" $selPending>Pending</option>
        <option value="under_review" $selReview>Under Review</option>
        <option value="ongoing" $selOngoing>Ongoing</option>
        <option value="resolved" $selResolved>Resolved</option>
      </select>
      <a href="student-submit.php" class="btn-sm-primary"><i class="fas fa-plus"></i> New Concern</a>
    </div>
  </div>
  <div class="table-wrap">
    <table class="data-table">
      <thead><tr><th>Case #</th><th>Subject</th><th>Type</th><th>Status</th><th>Priority</th><th>Date</th><th>Action</th></tr></thead>
      <tbody>$rows</tbody>
    </table>
  </div>
</div>

<div class="modal-overlay hidden" id="caseModal" onclick="if(event.target.id==='caseModal')this.classList.add('hidden')">
  <div class="modal-box" style="max-width:600px">
    <button class="modal-close" onclick="document.getElementById('caseModal').classList.add('hidden')"><i class="fas fa-times"></i></button>
    <h2 class="modal-title">Case Detail</h2>
    <div id="caseModalBody"></div>
  </div>
</div>
HTML;

renderLayout($user, 'My Cases', 'cases', $content);



