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
$studentActiveCasesMap = [];

if (!function_exists('getConcernMeta')) {
function getConcernMeta(string $type): array {
    $typeLower = strtolower(trim($type));
    $map = [
        'bullying' => [
            'label' => 'Bullying Concern',
            'short' => 'Bullying',
            'icon'  => 'fa-shield-alt',
            'color' => '#dc2626',
            'guidance_note' => 'Student reported bullying or harassment concerns. The Guidance Office is actively handling this matter. Advisory teachers are requested to maintain observant, vigilant supervision in classrooms and common areas without singling out the student.'
        ],
        'peer_conflict' => [
            'label' => 'Peer Conflict / Peer Problem',
            'short' => 'Peer Problem',
            'icon'  => 'fa-user-friends',
            'color' => '#0284c7',
            'guidance_note' => 'Student reported interpersonal or peer friction. Guidance recommends fostering inclusive group activities and monitoring social dynamics without directly interrogating students.'
        ],
        'academic_stress' => [
            'label' => 'Academic Stress & Workload',
            'short' => 'Academic Stress',
            'icon'  => 'fa-book-open',
            'color' => '#d97706',
            'guidance_note' => 'Student indicated academic distress or study pressure. Advisory teachers may check in gently regarding deadline pacing, understanding of lessons, or study habits.'
        ],
        'emotional' => [
            'label' => 'Emotional / Mental Well-being',
            'short' => 'Emotional Well-being',
            'icon'  => 'fa-heart',
            'color' => '#9333ea',
            'guidance_note' => 'Student reported emotional or well-being difficulties. Please offer a supportive, non-judgmental classroom environment and be attentive to sudden behavioral or emotional shifts.'
        ],
        'family' => [
            'label' => 'Family / Domestic Concern',
            'short' => 'Family Concern',
            'icon'  => 'fa-home',
            'color' => '#059669',
            'guidance_note' => 'Student reported personal or family-related concerns impacting their school experience. Exercise empathy and supportive flexibility when feasible.'
        ],
        'behavioral' => [
            'label' => 'Behavioral Adjustment',
            'short' => 'Behavioral',
            'icon'  => 'fa-user-clock',
            'color' => '#ea580c',
            'guidance_note' => 'Guidance is supporting the student with behavioral adjustment. Consistent classroom routines, positive reinforcement, and clear expectations are recommended.'
        ],
        'other' => [
            'label' => 'General Guidance Concern',
            'short' => 'General Concern',
            'icon'  => 'fa-tag',
            'color' => '#475569',
            'guidance_note' => 'Student has raised an advisory guidance matter. Guidance staff are reviewing the situation.'
        ]
    ];
    return $map[$typeLower] ?? [
        'label' => ucfirst(str_replace('_', ' ', $type)),
        'short' => ucfirst(str_replace('_', ' ', $type)),
        'icon'  => 'fa-clipboard-list',
        'color' => '#550000',
        'guidance_note' => 'Guidance staff are handling this student concern. Please maintain an encouraging and supportive classroom posture.'
    ];
}
}

if ($advisoryClass) {
    $stmtStudents = $db->prepare("SELECT * FROM users 
        WHERE role='student' 
          AND REPLACE(grade_section, ' – ', ' - ') = REPLACE(?, ' – ', ' - ') 
          AND (is_active=1 OR is_active IS NULL) 
        ORDER BY last_name ASC");
    $stmtStudents->execute([$advisoryClass]);
    $advisoryStudents = $stmtStudents->fetchAll();

    // Active cases from students in advisory class - high-level summary fields only (no description or notes)
    $stmtCases = $db->prepare("SELECT c.id, c.case_number, c.student_id, c.is_anonymous, c.concern_type, c.priority, c.status, c.submitted_at,
        c.subject, c.description, c.reported_teacher_name, c.is_against_adviser,
        CASE WHEN c.is_anonymous=1 THEN 'Anonymous Student' ELSE CONCAT(s.first_name,' ',s.last_name) END AS student_name,
        s.grade_section, s.id_number, s.avatar_photo, s.avatar_initials,
        CONCAT(COALESCE(a.first_name,'Guidance'), ' ', COALESCE(a.last_name,'Counselor')) AS counselor_name
        FROM cases c 
        JOIN users s ON c.student_id=s.id
        LEFT JOIN users a ON c.assigned_to=a.id
        WHERE REPLACE(s.grade_section, ' – ', ' - ') = REPLACE(?, ' – ', ' - ')
          AND c.status NOT IN ('resolved','closed')
          AND (c.is_against_adviser = 0 OR c.is_against_adviser IS NULL)
          AND LOWER(c.concern_type) NOT LIKE '%teacher%'
          AND LOWER(c.concern_type) NOT LIKE '%adviser%'
          AND LOWER(c.concern_type) NOT LIKE '%faculty%'
          AND LOWER(c.concern_type) NOT LIKE '%instructor%'
        ORDER BY FIELD(c.priority,'high','medium','low'), c.submitted_at DESC
        LIMIT 10");
    $stmtCases->execute([$advisoryClass]);
    $rawAdvisoryCases = $stmtCases->fetchAll();

    $teacherLastName  = strtolower(trim($user['last_name'] ?? ''));
    $teacherFirstName = strtolower(trim($user['first_name'] ?? ''));
    $advisoryCases    = [];

    foreach ($rawAdvisoryCases as $ac) {
        $cSub = strtolower($ac['subject'] ?? '');
        $cDesc = strtolower($ac['description'] ?? '');
        $cRep = strtolower($ac['reported_teacher_name'] ?? '');
        if (($teacherLastName && strlen($teacherLastName) > 2 && (strpos($cSub, $teacherLastName) !== false || strpos($cDesc, $teacherLastName) !== false || strpos($cRep, $teacherLastName) !== false)) ||
            ($teacherFirstName && strlen($teacherFirstName) > 2 && (strpos($cSub, $teacherFirstName) !== false || strpos($cDesc, $teacherFirstName) !== false || strpos($cRep, $teacherFirstName) !== false))) {
            continue; // Adviser is strictly forbidden from seeing concerns about themselves
        }
        $advisoryCases[] = $ac;
        if (empty($ac['is_anonymous'])) {
            $studentActiveCasesMap[(int)$ac['student_id']][] = $ac;
        }
    }
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
        
        $hasConcern = !empty($studentActiveCasesMap[(int)$s['id']]);
        $concernTag = $hasConcern 
            ? "<span class='concern-pill-active' style='font-size:10px;padding:2px 7px;margin-top:3px;display:inline-flex'><i class='fas fa-exclamation-circle'></i> Concern Raised</span>" 
            : "";

        $studentCards .= "<div style='display:flex;align-items:center;gap:10px;padding:10px 0;border-bottom:1px solid var(--border)'>
            $avatarEl
            <div style='flex:1'>
                <div style='font-size:13px;font-weight:700'>$name</div>
                <div style='font-size:11px;color:var(--text-3)'>".htmlspecialchars($s['grade_section'])."</div>
                $concernTag
            </div>
        </div>";
    }
    if (!$studentCards) {
        $studentCards = "<div style='text-align:center;color:var(--text-3);padding:20px;font-size:13px'>No registered students in your advisory class yet.</div>";
    } else {
        $studentCards .= "<div style='padding-top:12px;text-align:center'>
            <a href='teacher-advisory.php' style='color:var(--maroon);font-weight:700;font-size:12px;text-decoration:none'>
              <i class='fas fa-users'></i> View Full Advisory Roster & Concerns →
            </a>
          </div>";
    }

    // Advisory cases table
    $casesRows = '';
    foreach ($advisoryCases as $c) {
        $meta = getConcernMeta($c['concern_type']);
        $date = date('M d, Y', strtotime($c['submitted_at']));
        $cSummary = [
            'id' => (int)$c['id'],
            'case_number' => $c['case_number'],
            'is_anonymous' => !empty($c['is_anonymous']),
            'student_name' => $c['student_name'],
            'id_number' => !empty($c['is_anonymous']) ? 'Confidential' : ($c['id_number'] ?? ''),
            'grade_section' => $c['grade_section'],
            'concern_type' => $c['concern_type'],
            'concern_label' => $meta['label'],
            'concern_icon' => $meta['icon'],
            'concern_color' => $meta['color'],
            'guidance_note' => $meta['guidance_note'],
            'status' => $c['status'],
            'status_label' => str_replace('_', ' ', ucfirst($c['status'])),
            'submitted_at' => $date,
            'counselor_name' => $c['counselor_name']
        ];
        $cJson = htmlspecialchars(json_encode($cSummary), ENT_QUOTES, 'UTF-8');

        $casesRows .= "<tr>
            <td><strong style='color:var(--maroon)'>{$c['case_number']}</strong></td>
            <td>".htmlspecialchars($c['student_name'])."</td>
            <td>
              <span style='color:{$meta['color']};font-weight:700;display:inline-flex;align-items:center;gap:6px;font-size:13px'>
                <i class='fas {$meta['icon']}'></i> {$meta['label']}
              </span>
            </td>
            <td>".statusBadge($c['status'])."</td>
            <td style='color:var(--text-3);font-size:12px'>$date</td>
            <td style='text-align:right'>
              <button type='button' class='btn-sm-outline' onclick='openAdvisoryConcernModal(this)' data-concern='$cJson'>
                <i class='fas fa-eye'></i> View Summary
              </button>
            </td>
        </tr>";
    }
    if (!$casesRows) $casesRows = "<tr><td colspan='6' style='text-align:center;color:var(--text-3);padding:24px'>No active guidance concerns from your advisory class.</td></tr>";

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
        <div style='max-height:340px;overflow-y:auto'>$studentCards</div>
      </div>
      <div class='content-card'>
        <div class='card-header'>
          <h3><i class='fas fa-folder-open'></i> Active Cases — Advisory Class</h3>
          <div style='display:flex;align-items:center;gap:8px'>
            <span style='background:#fef2f2;color:#dc2626;padding:3px 10px;border-radius:50px;font-size:12px;font-weight:700'>$caseCount active</span>
            <a href='teacher-advisory.php' class='btn-sm-primary' style='text-decoration:none;font-size:12px'>
              <i class='fas fa-external-link-alt'></i> View All Concerns
            </a>
          </div>
        </div>
        <div class='table-wrap'>
          <table class='data-table'>
            <thead><tr><th>Case #</th><th>Student</th><th>Concern Summary</th><th>Status</th><th>Date</th><th style='text-align:right'>Action</th></tr></thead>
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

<!-- Advisory Concern Summary Modal (Teacher View) -->
<div class="modal-overlay hidden" id="advisoryConcernModal" onclick="if(event.target.id==='advisoryConcernModal')this.classList.add('hidden')">
  <div class="modal-box wide-modal" style="max-width:640px">
    <button class="modal-close" onclick="document.getElementById('advisoryConcernModal').classList.add('hidden')"><i class="fas fa-times"></i></button>
    <div style="display:flex;align-items:center;gap:12px;margin-bottom:16px;border-bottom:1px solid var(--border);padding-bottom:14px">
      <div style="width:40px;height:40px;border-radius:10px;background:var(--maroon-pale);color:var(--maroon);display:flex;align-items:center;justify-content:center;font-size:18px;flex-shrink:0">
        <i class="fas fa-info-circle"></i>
      </div>
      <div>
        <h2 class="modal-title" style="text-align:left;margin:0;font-size:18px">Advisory Student Guidance Summary</h2>
        <div style="font-size:12px;color:var(--text-3);margin-top:2px">High-level advisory awareness • Full details confidential</div>
      </div>
    </div>
    <div id="advisoryConcernModalBody"></div>
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

function openAdvisoryConcernModal(btn) {
  const c = JSON.parse(btn.getAttribute("data-concern"));
  const referralBtn = c.is_anonymous ? "" :
    "<div style=\"margin-top:12px;display:flex;justify-content:flex-end\">"
    +"<a href=\"teacher-referral.php?student_name="+encodeURIComponent(c.student_name)+"&concern_type="+encodeURIComponent(c.concern_type)+"\" class=\"btn-sm-primary\" style=\"text-decoration:none\">"
    +"<i class=\"fas fa-clipboard-check\"></i> Submit Teacher Observation Referral</a></div>";

  document.getElementById("advisoryConcernModalBody").innerHTML =
    "<div style=\"display:flex;align-items:center;gap:12px;padding:12px;background:var(--bg2);border-radius:10px;margin-bottom:14px\">"
    +"<div style=\"flex:1\">"
    +"<div style=\"font-size:15px;font-weight:800;color:var(--text)\">"+c.student_name+"</div>"
    +"<div style=\"font-size:12px;color:var(--text-3);margin-top:2px\">ID: <strong>"+c.id_number+"</strong> • Section: <strong>"+c.grade_section+"</strong></div>"
    +"</div>"
    +"<div><strong style=\"color:var(--maroon);font-size:14px\">"+c.case_number+"</strong></div>"
    +"</div>"
    +"<div class=\"concern-summary-card\">"
    +"<div class=\"csc-header\">"
    +"<div class=\"csc-title\"><span style=\"color:"+c.concern_color+";font-size:18px\"><i class=\"fas "+c.concern_icon+"\"></i></span><span style=\"color:var(--text);font-weight:800\">"+c.concern_label+"</span></div>"
    +"<div><span class=\"status-badge status-"+c.status+"\">"+c.status_label+"</span></div>"
    +"</div>"
    +"<div style=\"display:grid;grid-template-columns:1fr 1fr;gap:10px;font-size:12px;color:var(--text-2);margin-bottom:10px;background:var(--bg2);padding:10px;border-radius:8px\">"
    +"<div><i class=\"fas fa-calendar-alt\" style=\"color:var(--maroon);margin-right:6px\"></i> <strong>Raised:</strong> "+c.submitted_at+"</div>"
    +"<div><i class=\"fas fa-user-tie\" style=\"color:var(--maroon);margin-right:6px\"></i> <strong>Counselor:</strong> "+c.counselor_name+"</div>"
    +"</div>"
    +"<div class=\"csc-guidance-box\"><strong><i class=\"fas fa-lightbulb\" style=\"color:var(--maroon)\"></i> Teacher Classroom Recommendation:</strong> "+c.guidance_note+"</div>"
    +"<div class=\"csc-confidential-tag\"><i class=\"fas fa-lock\" style=\"color:var(--maroon)\"></i> <span><strong>Student Privacy Notice:</strong> Full incident descriptions and counselor interview notes are confidential between the student and Guidance Counselor. Advisory teachers are notified so you can foster a supportive classroom environment.</span></div>"
    +referralBtn
    +"</div>"
    +"<div style=\"display:flex;justify-content:flex-end;margin-top:16px;padding-top:12px;border-top:1px solid var(--border)\">"
    +"<button class=\"btn-secondary\" onclick=\"document.getElementById(\'advisoryConcernModal\').classList.add(\'hidden\')\">Close</button>"
    +"</div>";

  document.getElementById("advisoryConcernModal").classList.remove("hidden");
}
</script>
';

renderLayout($user, 'Teacher Dashboard', 'dashboard', $content);
