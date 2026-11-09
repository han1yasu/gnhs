<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/layout.php';
$user = requireLogin('student');
$db   = getDB();
$uid  = $user['id'];

$filterStatus = $_GET['status'] ?? '';
if ($filterStatus === 'resolved') {
    header('Location: student-archive.php');
    exit;
}

// Active cases only (not resolved/closed) — resolved concerns belong in Case Archive
$where = "WHERE student_id=$uid AND status NOT IN ('resolved','closed')";
if ($filterStatus) $where = "WHERE student_id=$uid AND status='".addslashes($filterStatus)."'";

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
    $mediaBadges = '';
    if (!empty($c['evidence_files']) && $c['evidence_files'] !== '[]') {
        $evArray = is_string($c['evidence_files']) ? json_decode($c['evidence_files'], true) : $c['evidence_files'];
        $cnt = is_array($evArray) ? count($evArray) : 1;
        $mediaBadges .= "<span class='badge-evidence' title='{$cnt} attached photo/video evidence file(s)'><i class='fas fa-paperclip'></i> {$cnt} Media</span>";
    }
    if (!empty($c['witnesses'])) {
        $mediaBadges .= "<span class='badge-witness' title='Witnesses mentioned in case report'><i class='fas fa-user-group'></i> Witness</span>";
    }
    if (!empty($c['is_peer_report'])) {
        $peerName = htmlspecialchars($c['reported_student_name'] ?: 'Peer');
        $mediaBadges .= "<span class='badge-witness' style='background:#fdf2f8;color:#be185d;border-color:#fbcfe8' title='Reported for: {$peerName}'><i class='fas fa-hand-holding-heart'></i> For: {$peerName}</span>";
    }
    $date = date('M d, Y', strtotime($c['submitted_at']));
    $anonBadge = !empty($c['is_anonymous']) ? "<span class='badge-anon' title='Your identity is anonymous to the guidance counselor'><i class='fas fa-user-secret'></i> Anon</span>" : "";
    $rows .= "<tr>
        <td class='cell-case-num'><span class='case-num-text'>{$c['case_number']}</span>$anonBadge</td>
        <td style='font-weight:600'>".htmlspecialchars($c['subject'])."</td>
        <td>
          <div class='case-type-wrap'>
            <span class='case-type-label'>$type</span>
            $mediaBadges
          </div>
        </td>
        <td>".badge($c['status'])."</td>
        <td>".prioBadge($c['priority'])."</td>
        <td class='cell-date'>$date</td>
        <td class='cell-actions'><button type='button' class='btn-row-action' onclick='openCaseModal({$c['id']})'><i class='fas fa-eye'></i> View</button></td>
    </tr>";
}
if (!$rows) $rows = "<tr><td colspan='7' style='text-align:center;color:var(--text-3);padding:40px'>No active cases found. <a href='student-submit.php' style='color:var(--maroon);font-weight:700'>Submit a concern →</a></td></tr>";

$selPending    = $filterStatus==='pending'      ? 'selected' : '';
$selReview     = $filterStatus==='under_review' ? 'selected' : '';
$selOngoing    = $filterStatus==='ongoing'      ? 'selected' : '';

$content = <<<HTML
<div class="page-info-banner" style="background:var(--maroon-pale);border:1px solid var(--maroon-tint);border-radius:10px;padding:12px 18px;margin-bottom:18px;display:flex;align-items:center;justify-content:space-between;gap:14px;flex-wrap:wrap">
  <div style="display:flex;align-items:center;gap:10px;font-size:13.5px;color:var(--text)">
    <i class="fas fa-archive" style="color:var(--maroon);font-size:18px"></i>
    <span>Completed and resolved cases are kept in your <strong>Case Archive</strong>. You can review past resolutions or file an appeal at any time.</span>
  </div>
  <a href="student-archive.php" class="btn-sm-primary" style="font-size:12px;padding:6px 14px;white-space:nowrap;display:inline-flex;align-items:center;gap:6px">
    <i class="fas fa-box-archive"></i> Go to Case Archive &rarr;
  </a>
</div>

<div class="content-card">
  <div class="card-header">
    <h3><i class="fas fa-folder-open"></i> My Cases</h3>
    <div class="card-header-actions">
      <select class="status-select" onchange="if(this.value==='resolved'){window.location='student-archive.php';}else{window.location='student-cases.php'+(this.value?'?status='+this.value:'');}">
        <option value="">All Active Cases</option>
        <option value="pending" $selPending>Pending</option>
        <option value="under_review" $selReview>Under Review</option>
        <option value="ongoing" $selOngoing>Ongoing</option>
        <option value="resolved">Resolved (Go to Archive)</option>
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
<script>
document.addEventListener('DOMContentLoaded', function() {
  const p = new URLSearchParams(window.location.search);
  const openId = p.get('open_case') || p.get('case_id');
  if (openId && typeof openCaseModal === 'function') {
    setTimeout(function() {
      openCaseModal(parseInt(openId));
    }, 200);
  }
});
</script>
HTML;

renderLayout($user, 'My Cases', 'cases', $content);



