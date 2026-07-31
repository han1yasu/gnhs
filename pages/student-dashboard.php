<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/layout.php';
$user = requireLogin('student');
$db   = getDB();

// Stats
$uid = $user['id'];
$totalCases  = (int)$db->prepare("SELECT COUNT(*) FROM cases WHERE student_id=?")->execute([$uid]) ? $db->query("SELECT COUNT(*) FROM cases WHERE student_id=$uid")->fetchColumn() : 0;
$pending     = (int)$db->query("SELECT COUNT(*) FROM cases WHERE student_id=$uid AND status IN ('pending','under_review')")->fetchColumn();
$resolved    = (int)$db->query("SELECT COUNT(*) FROM cases WHERE student_id=$uid AND status='resolved'")->fetchColumn();
$unread      = (int)$db->query("SELECT COUNT(*) FROM notifications WHERE user_id=$uid AND is_read=0")->fetchColumn();

// Recent cases
$recentCases = $db->query("SELECT * FROM cases WHERE student_id=$uid ORDER BY submitted_at DESC LIMIT 5")->fetchAll();

// Upcoming session
$session = $db->query("SELECT cs.*,u.first_name,u.last_name FROM counseling_sessions cs JOIN users u ON cs.counselor_id=u.id WHERE cs.student_id=$uid AND cs.status='scheduled' AND cs.session_date>=NOW() ORDER BY cs.session_date ASC LIMIT 1")->fetch();

function badge(string $status): string {
    $map = ['pending'=>'status-pending','under_review'=>'status-under_review','ongoing'=>'status-ongoing','resolved'=>'status-resolved','closed'=>'status-resolved'];
    $label = str_replace('_',' ', ucfirst($status));
    return "<span class='status-badge ".($map[$status]??'status-pending')."'>$label</span>";
}
function prioBadge(string $p): string {
    $map = ['high'=>'priority-high','medium'=>'priority-medium','low'=>'priority-low'];
    return "<span class='priority-badge ".($map[$p]??'priority-low')."'><i class='fas fa-circle'></i>".ucfirst($p)."</span>";
}

$caseRows = '';
foreach ($recentCases as $c) {
    $type = ucfirst(str_replace('_',' ',$c['concern_type']));
    $caseRows .= "<tr>
        <td><strong style='color:var(--maroon)'>{$c['case_number']}</strong></td>
        <td>".htmlspecialchars($c['subject'])."</td>
        <td>$type</td>
        <td>".badge($c['status'])."</td>
        <td>".prioBadge($c['priority'])."</td>
        <td><button class='btn-sm-outline' onclick='openCaseModal({$c['id']})'><i class='fas fa-eye'></i> View</button></td>
    </tr>";
}
if (!$caseRows) $caseRows = "<tr><td colspan='6' style='text-align:center;color:var(--text-3);padding:32px'>No cases submitted yet. <a href='student-submit.php' style='color:var(--maroon);font-weight:700'>Submit your first concern</a></td></tr>";

$sessionHtml = '';
if ($session) {
    $dt = new DateTime($session['session_date']);
    $sessionHtml = "<div class='session-card'>
        <div class='session-date'><span class='s-month'>".$dt->format('M')."</span><span class='s-day'>".$dt->format('d')."</span></div>
        <div class='session-info'>
          <strong>Counseling Session</strong>
          <p>with ".htmlspecialchars($session['first_name'].' '.$session['last_name'])."</p>
          <div class='s-time'><i class='fas fa-clock'></i> ".$dt->format('h:i A')."</div>
          <div class='s-loc'><i class='fas fa-map-marker-alt'></i> ".htmlspecialchars($session['location'])."</div>
        </div>
    </div>";
} else {
    $sessionHtml = "<div style='text-align:center;color:var(--text-3);padding:24px;font-size:14px'><i class='fas fa-calendar' style='font-size:28px;margin-bottom:8px;display:block'></i>No upcoming sessions</div>";
}

$content = <<<HTML
<div class="summary-cards">
  <div class="sum-card">
    <div class="sum-icon" style="background:#fff0f0;color:var(--maroon)"><i class="fas fa-folder-open"></i></div>
    <div><div class="sum-num">$totalCases</div><div class="sum-label">Total Cases</div></div>
  </div>
  <div class="sum-card">
    <div class="sum-icon" style="background:#fffbeb;color:#d97706"><i class="fas fa-clock"></i></div>
    <div><div class="sum-num">$pending</div><div class="sum-label">Pending Review</div></div>
  </div>
  <div class="sum-card">
    <div class="sum-icon" style="background:#f0fdf4;color:#16a34a"><i class="fas fa-check-circle"></i></div>
    <div><div class="sum-num">$resolved</div><div class="sum-label">Resolved</div></div>
  </div>
  <div class="sum-card">
    <div class="sum-icon" style="background:#eff6ff;color:#2563eb"><i class="fas fa-bell"></i></div>
    <div><div class="sum-num">$unread</div><div class="sum-label">Unread Notifs</div></div>
  </div>
</div>

<div class="content-grid">
  <div class="left-col">
    <div class="content-card">
      <div class="card-header">
        <h3><i class="fas fa-folder-open"></i> My Cases</h3>
        <a href="student-submit.php" class="btn-sm-primary"><i class="fas fa-plus"></i> New Concern</a>
      </div>
      <div class="table-wrap">
        <table class="data-table">
          <thead><tr><th>Case #</th><th>Subject</th><th>Type</th><th>Status</th><th>Priority</th><th>Action</th></tr></thead>
          <tbody>$caseRows</tbody>
        </table>
      </div>
    </div>
  </div>
  <div class="right-col">
    <div class="content-card">
      <div class="card-header"><h3><i class="fas fa-calendar-alt"></i> Upcoming Session</h3></div>
      $sessionHtml
    </div>
    <div class="content-card">
      <div class="card-header"><h3><i class="fas fa-lightbulb"></i> Quick Help</h3></div>
      <div style="padding:6px 0">
        <a href="student-submit.php" class="quick-link"><i class="fas fa-file-medical"></i><span>Submit a New Concern</span><i class="fas fa-chevron-right"></i></a>
        <a href="student-cases.php" class="quick-link"><i class="fas fa-list"></i><span>View All My Cases</span><i class="fas fa-chevron-right"></i></a>
        <a href="student-sessions.php" class="quick-link"><i class="fas fa-calendar"></i><span>My Sessions</span><i class="fas fa-chevron-right"></i></a>
      </div>
    </div>
  </div>
</div>

<!-- Case Detail Modal -->
<div class="modal-overlay hidden" id="caseModal" onclick="if(event.target.id==='caseModal')this.classList.add('hidden')">
  <div class="modal-box" style="max-width:600px">
    <button class="modal-close" onclick="document.getElementById('caseModal').classList.add('hidden')"><i class="fas fa-times"></i></button>
    <h2 class="modal-title">Case Detail</h2>
    <div id="caseModalBody"><div style="text-align:center;padding:40px"><span class="spinner" style="border-color:rgba(85,0,0,.2);border-top-color:var(--maroon);display:inline-block"></span></div></div>
  </div>
</div>
HTML;

renderLayout($user, 'Student Dashboard', 'dashboard', $content);
