<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/layout.php';
$user = requireLogin('admin');
$db   = getDB();

$sessions = $db->query("SELECT cs.*,
    CONCAT(s.first_name,' ',s.last_name) AS student_name, s.grade_section,
    c.case_number
    FROM counseling_sessions cs
    LEFT JOIN users s ON cs.student_id=s.id
    JOIN cases c ON cs.case_id=c.id
    ORDER BY cs.session_date DESC")->fetchAll();

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
        <td>".htmlspecialchars($s['student_name']??'—')."</td>
        <td>".htmlspecialchars($s['grade_section']??'—')."</td>
        <td>".$dt->format('M d, Y')."</td>
        <td>".$dt->format('h:i A')."</td>
        <td>".htmlspecialchars($s['location'])."</td>
        <td>".ucfirst($s['session_type'])."</td>
        <td>$statusBadge</td>
        <td>
          <select class='status-select' style='font-size:12px;padding:6px 10px' onchange='updateSessionStatus({$s['id']},this.value)'>
            <option value='scheduled' ".($s['status']==='scheduled'?'selected':'').">Scheduled</option>
            <option value='completed' ".($s['status']==='completed'?'selected':'').">Completed</option>
            <option value='cancelled' ".($s['status']==='cancelled'?'selected':'').">Cancelled</option>
            <option value='no_show'   ".($s['status']==='no_show'?'selected':'').">No Show</option>
          </select>
        </td>
    </tr>";
}
if (!$rows) $rows = "<tr><td colspan='9' style='text-align:center;color:var(--text-3);padding:40px'>No sessions scheduled yet.</td></tr>";

$content = <<<HTML
<div class="content-card">
  <div class="card-header"><h3><i class="fas fa-calendar-alt"></i> Counseling Sessions</h3></div>
  <div class="table-wrap">
    <table class="data-table">
      <thead><tr><th>Case #</th><th>Student</th><th>Grade</th><th>Date</th><th>Time</th><th>Location</th><th>Type</th><th>Status</th><th>Update</th></tr></thead>
      <tbody>$rows</tbody>
    </table>
  </div>
</div>
<script>
async function updateSessionStatus(id, status) {
  try {
    const r = await fetch('/gnhs-guidance/api/update_session.php?id='+id+'&status='+status);
    const d = await r.json();
    if (d.success) showToast('Session updated!','success');
    else showToast(d.message||'Failed.','error');
  } catch { showToast('Updated (demo).','success'); }
}
</script>
HTML;

renderLayout($user, 'Sessions', 'sessions', $content);
