<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/layout.php';
$user = requireLogin('student');
$db   = getDB();
$uid  = $user['id'];

$sessions = $db->query("SELECT cs.*,u.first_name,u.last_name,c.case_number FROM counseling_sessions cs 
    JOIN users u ON cs.counselor_id=u.id 
    JOIN cases c ON cs.case_id=c.id
    WHERE cs.student_id=$uid ORDER BY cs.session_date DESC")->fetchAll();

$rows = '';
foreach ($sessions as $s) {
    $dt = new DateTime($s['session_date']);
    $statusBadge = match($s['status']) {
        'scheduled' => "<span class='status-badge status-under_review'>Scheduled</span>",
        'completed' => "<span class='status-badge status-resolved'>Completed</span>",
        'cancelled' => "<span class='status-badge' style='background:#fef2f2;color:#991b1b'>Cancelled</span>",
        'no_show'   => "<span class='status-badge' style='background:#fffbeb;color:#92400e'>No Show</span>",
        default     => "<span class='status-badge status-pending'>".ucfirst($s['status'])."</span>"
    };
    $rows .= "<tr>
        <td><strong style='color:var(--maroon)'>{$s['case_number']}</strong></td>
        <td>".$dt->format('M d, Y')."</td>
        <td>".$dt->format('h:i A')."</td>
        <td>".htmlspecialchars($s['first_name'].' '.$s['last_name'])."</td>
        <td>".htmlspecialchars($s['location'] ?? '')."</td>
        <td>".ucfirst($s['session_type'] ?? '')."</td>
        <td>$statusBadge</td>
    </tr>";
}
if (!$rows) $rows = "<tr><td colspan='7' style='text-align:center;color:var(--text-3);padding:40px'>No sessions found yet.</td></tr>";

$content = <<<HTML
<div class="content-card">
  <div class="card-header"><h3><i class="fas fa-calendar-alt"></i> My Counseling Sessions</h3></div>
  <div class="table-wrap">
    <table class="data-table">
      <thead><tr><th>Case #</th><th>Date</th><th>Time</th><th>Counselor</th><th>Location</th><th>Type</th><th>Status</th></tr></thead>
      <tbody>$rows</tbody>
    </table>
  </div>
</div>
HTML;

renderLayout($user, 'My Sessions', 'sessions', $content);



