<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/layout.php';
$user = requireLogin('teacher');
$db   = getDB();
$uid  = $user['id'];

// Get teacher's advisory class
$advisoryClass = $user['advisory_class'] ?? null;

// Stats
$totalRefs = (int)$db->query("SELECT COUNT(*) FROM referrals WHERE teacher_id=$uid")->fetchColumn();
$pending   = (int)$db->query("SELECT COUNT(*) FROM referrals WHERE teacher_id=$uid AND status='pending'")->fetchColumn();
$resolved  = (int)$db->query("SELECT COUNT(*) FROM referrals WHERE teacher_id=$uid AND status='resolved'")->fetchColumn();
$urgent    = (int)$db->query("SELECT COUNT(*) FROM referrals WHERE teacher_id=$uid AND urgency='urgent'")->fetchColumn();

// Advisory class students with cases (if advisory class is set)
$advisoryStudents = [];
$advisoryCases    = [];
if ($advisoryClass) {
    $advisoryStudents = $db->query("SELECT * FROM users WHERE role='student' AND grade_section='".addslashes($advisoryClass)."' AND is_active=1 ORDER BY last_name ASC")->fetchAll();

    // Cases from students in advisory class
    $advisoryCases = $db->query("SELECT c.*,
        CONCAT(s.first_name,' ',s.last_name) AS student_name,
        s.grade_section
        FROM cases c JOIN users s ON c.student_id=s.id
        WHERE s.grade_section='".addslashes($advisoryClass)."'
        AND c.status NOT IN ('resolved','closed')
        ORDER BY FIELD(c.priority,'high','medium','low'), c.submitted_at DESC
        LIMIT 10")->fetchAll();
}

// Recent referrals by this teacher
$recentRefs = $db->query("SELECT * FROM referrals WHERE teacher_id=$uid ORDER BY submitted_at DESC LIMIT 5")->fetchAll();

function urgencyBadge(string $u): string {
    $map = ['urgent'=>'priority-high','moderate'=>'priority-medium','low'=>'priority-low'];
    return "<span class='priority-badge ".($map[$u]??'priority-low')."'><i class='fas fa-circle'></i> ".ucfirst($u)."</span>";
}
function statusBadge(string $s): string {
    $map = ['pending'=>'status-pending','under_review'=>'status-under_review','ongoing'=>'status-ongoing','resolved'=>'status-resolved'];
    return "<span class='status-badge ".($map[$s]??'status-pending')."'>".str_replace('_',' ',ucfirst($s))."</span>";
}
function prioBadge(string $p): string {
    $map = ['high'=>'priority-high','medium'=>'priority-medium','low'=>'priority-low'];
    return "<span class='priority-badge ".($map[$p]??'priority-low')."'><i class='fas fa-circle'></i> ".ucfirst($p)."</span>";
}

$refRows = '';
foreach ($recentRefs as $r) {
    $type = ucfirst(str_replace('_',' ',$r['concern_type']));
    $date = date('M d, Y', strtotime($r['submitted_at']));
    $refData = htmlspecialchars(json_encode($r), ENT_QUOTES, 'UTF-8');
    $refRows .= "<tr>
        <td><strong style='color:var(--maroon)'>{$r['ref_number']}</strong></td>
        <td>".htmlspecialchars($r['student_name'])."</td>
        <td>".htmlspecialchars($r['grade_section'])."</td>
        <td>$type</td>
        <td>".urgencyBadge($r['urgency'])."</td>
        <td>".statusBadge($r['status'])."</td>
        <td style='color:var(--text-3);font-size:12px'>$date</td>
        <td><button class='btn-sm-outline' onclick='openRefModal(this)' data-ref='$refData'><i class='fas fa-eye'></i> View</button></td>
    </tr>";
}
if (!$refRows) $refRows = "<tr><td colspan='8' style='text-align:center;color:var(--text-3);padding:32px'>No referrals yet. <a href='teacher-referral.php' style='color:var(--maroon);font-weight:700'>Submit one →</a></td></tr>";

// Advisory class section
$advisoryHtml = '';
if ($advisoryClass) {
    $studentCount = count($advisoryStudents);
    $caseCount    = count($advisoryCases);

    // Student cards
    $studentCards = '';
    foreach ($advisoryStudents as $s) {
        $init = strtoupper($s['first_name'][0].($s['last_name'][0]??''));
        $photo = $s['avatar_photo'] ?? null;
        $avatarEl = $photo
            ? "<img src='".htmlspecialchars($photo)."' style='width:40px;height:40px;border-radius:10px;object-fit:cover;'/>"
            : "<div style='width:40px;height:40px;border-radius:10px;background:linear-gradient(135deg,var(--maroon),var(--maroon-mid));color:#fff;font-size:13px;font-weight:900;display:flex;align-items:center;justify-content:center;flex-shrink:0'>$init</div>";
        $name = htmlspecialchars($s['first_name'].' '.$s['last_name']);
        $studentCards .= "<div style='display:flex;align-items:center;gap:10px;padding:10px 0;border-bottom:1px solid var(--border)'>
            $avatarEl
            <div>
                <div style='font-size:13px;font-weight:700'>$name</div>
                <div style='font-size:11px;color:var(--text-3)'>".htmlspecialchars($s['grade_section'])."</div>
            </div>
        </div>";
    }
    if (!$studentCards) $studentCards = "<div style='text-align:center;color:var(--text-3);padding:20px;font-size:13px'>No registered students in your advisory class yet.</div>";

    // Advisory cases table
    $casesRows = '';
    foreach ($advisoryCases as $c) {
        $type = ucfirst(str_replace('_',' ',$c['concern_type']));
        $date = date('M d', strtotime($c['submitted_at']));
        $casesRows .= "<tr>
            <td><strong style='color:var(--maroon)'>{$c['case_number']}</strong></td>
            <td>".htmlspecialchars($c['student_name'])."</td>
            <td>$type</td>
            <td>".prioBadge($c['priority'])."</td>
            <td>".statusBadge($c['status'])."</td>
            <td style='color:var(--text-3);font-size:12px'>$date</td>
        </tr>";
    }
    if (!$casesRows) $casesRows = "<tr><td colspan='6' style='text-align:center;color:var(--text-3);padding:24px'>No active cases from your advisory class. 🎉</td></tr>";

    $advisoryHtml = "
    <div style='display:grid;grid-template-columns:300px 1fr;gap:22px;margin-bottom:22px'>
      <div class='content-card'>
        <div class='card-header'>
          <h3><i class='fas fa-users'></i> My Advisory Class</h3>
          <span style='background:var(--maroon-pale);color:var(--maroon);padding:3px 10px;border-radius:50px;font-size:12px;font-weight:700'>$studentCount students</span>
        </div>
        <div style='font-size:12px;color:var(--text-3);margin-bottom:12px;padding:8px 12px;background:var(--maroon-pale);border-radius:8px;border:1px solid var(--maroon-tint)'>
          <i class='fas fa-chalkboard-teacher' style='color:var(--maroon)'></i> ".htmlspecialchars($advisoryClass)."
        </div>
        <div style='max-height:320px;overflow-y:auto'>$studentCards</div>
      </div>
      <div class='content-card'>
        <div class='card-header'>
          <h3><i class='fas fa-folder-open'></i> Active Cases — Advisory Class</h3>
          <span style='background:#fef2f2;color:#dc2626;padding:3px 10px;border-radius:50px;font-size:12px;font-weight:700'>$caseCount active</span>
        </div>
        <div class='table-wrap'>
          <table class='data-table'>
            <thead><tr><th>Case #</th><th>Student</th><th>Type</th><th>Priority</th><th>Status</th><th>Date</th></tr></thead>
            <tbody>$casesRows</tbody>
          </table>
        </div>
      </div>
    </div>";
} else {
    $advisoryHtml = "
    <div class='content-card' style='margin-bottom:22px;border:2px dashed var(--maroon-tint)'>
      <div style='text-align:center;padding:32px;color:var(--text-3)'>
        <i class='fas fa-chalkboard-teacher' style='font-size:40px;color:var(--maroon-tint);display:block;margin-bottom:12px'></i>
        <div style='font-size:15px;font-weight:700;color:var(--text-2);margin-bottom:8px'>No Advisory Class Assigned</div>
        <p style='font-size:13px'>Ask the Guidance Admin to assign your advisory class so you can view your students and their active concerns here.</p>
      </div>
    </div>";
}

$content = "
<div class='summary-cards'>
  <div class='sum-card'>
    <div class='sum-icon' style='background:#fff0f0;color:var(--maroon)'><i class='fas fa-exchange-alt'></i></div>
    <div><div class='sum-num'>$totalRefs</div><div class='sum-label'>Total Referrals</div></div>
  </div>
  <div class='sum-card'>
    <div class='sum-icon' style='background:#fffbeb;color:#d97706'><i class='fas fa-clock'></i></div>
    <div><div class='sum-num'>$pending</div><div class='sum-label'>Pending</div></div>
  </div>
  <div class='sum-card'>
    <div class='sum-icon' style='background:#f0fdf4;color:#16a34a'><i class='fas fa-check-circle'></i></div>
    <div><div class='sum-num'>$resolved</div><div class='sum-label'>Resolved</div></div>
  </div>
  <div class='sum-card'>
    <div class='sum-icon' style='background:#fef2f2;color:#dc2626'><i class='fas fa-exclamation-triangle'></i></div>
    <div><div class='sum-num'>$urgent</div><div class='sum-label'>Urgent</div></div>
  </div>
</div>

$advisoryHtml

<div class='content-card'>
  <div class='card-header'>
    <h3><i class='fas fa-list-alt'></i> My Recent Referrals</h3>
    <a href='teacher-referral.php' class='btn-sm-primary'><i class='fas fa-plus'></i> New Referral</a>
  </div>
  <div class='table-wrap'>
    <table class='data-table'>
      <thead><tr><th>Ref #</th><th>Student</th><th>Grade/Section</th><th>Concern</th><th>Urgency</th><th>Status</th><th>Date</th><th>Action</th></tr></thead>
      <tbody>$refRows</tbody>
    </table>
  </div>
</div>";

$content .= <<<HTML
<!-- Referral Detail Modal -->
<div class="modal-overlay hidden" id="refModal" onclick="if(event.target.id==='refModal')this.classList.add('hidden')">
  <div class="modal-box" style="max-width:620px">
    <button class="modal-close" onclick="document.getElementById('refModal').classList.add('hidden')"><i class="fas fa-times"></i></button>
    <h2 class="modal-title"><i class="fas fa-exchange-alt" style="color:var(--maroon);margin-right:8px"></i>Referral Detail</h2>
    <div id="refModalBody"></div>
  </div>
</div>
HTML;

$content .= '
<script>
function openRefModal(btn) {
  const ref = JSON.parse(btn.getAttribute("data-ref"));
  const urgMap   = {urgent:"priority-high", moderate:"priority-medium", low:"priority-low"};
  const statMap  = {pending:"status-pending", under_review:"status-under_review", ongoing:"status-ongoing", resolved:"status-resolved"};
  const statLabel = {pending:"Pending", under_review:"Under Review", ongoing:"Ongoing", resolved:"Resolved"};
  const typeLabel = ref.concern_type.replace(/_/g," ").replace(/\b\w/g, l=>l.toUpperCase());

  document.getElementById("refModalBody").innerHTML =
    "<div class=\"review-meta\" style=\"margin-bottom:16px\">"
    +"<div class=\"rm-row\"><label>Referral #</label><strong style=\"color:var(--maroon)\">"+ref.ref_number+"</strong></div>"
    +"<div class=\"rm-row\"><label>Student Name</label><strong>"+ref.student_name+"</strong></div>"
    +"<div class=\"rm-row\"><label>Grade / Section</label><span>"+ref.grade_section+"</span></div>"
    +"<div class=\"rm-row\"><label>Concern Type</label><span>"+typeLabel+"</span></div>"
    +"<div class=\"rm-row\"><label>Urgency</label><span class=\"priority-badge "+(urgMap[ref.urgency]||"priority-low")+"\"><i class=\"fas fa-circle\"></i> "+ref.urgency.charAt(0).toUpperCase()+ref.urgency.slice(1)+"</span></div>"
    +"<div class=\"rm-row\"><label>Status</label><span class=\"status-badge "+(statMap[ref.status]||"status-pending")+"\">"+statLabel[ref.status]+"</span></div>"
    +"<div class=\"rm-row\"><label>Date Submitted</label><span>"+ref.submitted_at+"</span></div>"
    +"</div>"
    +"<div class=\"form-group\">"
    +"<label style=\"font-size:13px;font-weight:800;color:var(--text-2);display:block;margin-bottom:8px\"><i class=\"fas fa-clipboard\" style=\"color:var(--maroon)\"></i> My Observations</label>"
    +"<div class=\"concern-text\" style=\"white-space:pre-wrap\">"+ref.observations+"</div>"
    +"</div>"
    +(ref.ai_summary ? "<div class=\"ai-box\" style=\"margin-top:14px\"><div class=\"ai-box-label\"><i class=\"fas fa-robot\"></i> AI Summary</div><div class=\"ai-box-text\">"+ref.ai_summary+"</div></div>" : "")
    +"<div class=\"review-actions\" style=\"margin-top:16px\">"
    +"<button class=\"btn-secondary\" onclick=\"document.getElementById(\'refModal\').classList.add(\'hidden\')\"><i class=\"fas fa-times\"></i> Close</button>"
    +"<a href=\"teacher-referrals-chat.php?ref_id="+ref.id+"\" class=\"btn-primary\" style=\"text-decoration:none\"><i class=\"fas fa-comments\"></i> Go to Chat</a>"
    +"</div>";

  document.getElementById("refModal").classList.remove("hidden");
}
</script>
';

renderLayout($user, 'Teacher Dashboard', 'dashboard', $content);



