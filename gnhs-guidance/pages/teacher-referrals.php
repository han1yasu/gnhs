<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/layout.php';
$user = requireLogin('teacher');
$db   = getDB();
$uid  = $user['id'];

$filterStatus  = $_GET['status']  ?? '';
$filterUrgency = $_GET['urgency'] ?? '';
$where = "WHERE r.teacher_id=$uid";
if ($filterStatus)  $where .= " AND r.status='".addslashes($filterStatus)."'";
if ($filterUrgency) $where .= " AND r.urgency='".addslashes($filterUrgency)."'";

$refs = $db->query("SELECT r.* FROM referrals r $where ORDER BY r.submitted_at DESC")->fetchAll();

function urgencyBadge(string $u): string {
    $map = ['urgent'=>'priority-high','moderate'=>'priority-medium','low'=>'priority-low'];
    return "<span class='priority-badge ".($map[$u]??'priority-low')."'><i class='fas fa-circle'></i>".ucfirst($u)."</span>";
}
function statusBadge(string $s): string {
    $map=['pending'=>'status-pending','under_review'=>'status-under_review','ongoing'=>'status-ongoing','resolved'=>'status-resolved'];
    return "<span class='status-badge ".($map[$s]??'status-pending')."'>".str_replace('_',' ',ucfirst($s))."</span>";
}

$rows = '';
foreach ($refs as $r) {
    $type = ucfirst(str_replace('_',' ',$r['concern_type']));
    $date = date('M d, Y', strtotime($r['submitted_at']));
    $rows .= "<tr>
        <td><strong style='color:var(--maroon)'>{$r['ref_number']}</strong></td>
        <td>".htmlspecialchars($r['student_name'])."</td>
        <td>".htmlspecialchars($r['grade_section'])."</td>
        <td>$type</td>
        <td>".urgencyBadge($r['urgency'])."</td>
        <td>".statusBadge($r['status'])."</td>
        <td style='color:var(--text-3);font-size:13px'>$date</td>
    </tr>";
}
if (!$rows) $rows = "<tr><td colspan='7' style='text-align:center;color:var(--text-3);padding:40px'>No referrals found. <a href='teacher-referral.php' style='color:var(--maroon);font-weight:700'>Submit one →</a></td></tr>";

$sSel1 = $filterStatus==='pending'      ? 'selected' : '';
$sSel2 = $filterStatus==='under_review' ? 'selected' : '';
$sSel3 = $filterStatus==='ongoing'      ? 'selected' : '';
$sSel4 = $filterStatus==='resolved'     ? 'selected' : '';
$uSel1 = $filterUrgency==='urgent'      ? 'selected' : '';
$uSel2 = $filterUrgency==='moderate'    ? 'selected' : '';
$uSel3 = $filterUrgency==='low'         ? 'selected' : '';

$content = <<<HTML
<div class="content-card">
  <div class="card-header">
    <h3><i class="fas fa-list-alt"></i> My Referrals</h3>
    <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap">
      <select class="status-select" onchange="applyFilter('status',this.value)">
        <option value="">All Statuses</option>
        <option value="pending"      $sSel1>Pending</option>
        <option value="under_review" $sSel2>Under Review</option>
        <option value="ongoing"      $sSel3>Ongoing</option>
        <option value="resolved"     $sSel4>Resolved</option>
      </select>
      <select class="status-select" onchange="applyFilter('urgency',this.value)">
        <option value="">All Urgency</option>
        <option value="urgent"   $uSel1>Urgent</option>
        <option value="moderate" $uSel2>Moderate</option>
        <option value="low"      $uSel3>Low</option>
      </select>
      <a href="teacher-referral.php" class="btn-sm-primary"><i class="fas fa-plus"></i> New Referral</a>
    </div>
  </div>
  <div class="table-wrap">
    <table class="data-table">
      <thead><tr><th>Ref #</th><th>Student Name</th><th>Grade/Section</th><th>Concern</th><th>Urgency</th><th>Status</th><th>Date</th></tr></thead>
      <tbody>$rows</tbody>
    </table>
  </div>
</div>
<script>
function applyFilter(key, val) {
  const url = new URL(window.location);
  if (val) url.searchParams.set(key, val); else url.searchParams.delete(key);
  window.location = url;
}
</script>
HTML;

renderLayout($user, 'My Referrals', 'referrals', $content);



