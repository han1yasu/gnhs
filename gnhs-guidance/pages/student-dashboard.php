<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/layout.php';
$user = requireLogin('student');
$db   = getDB();

$uid = $user['id'];
$studentFullName = trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''));
$revStudentName  = trim(($user['last_name'] ?? '') . ' ' . ($user['first_name'] ?? ''));

// Self-heal: auto-link any referrals or sessions matching this student
if (!empty($studentFullName)) {
    try {
        $updRef = $db->prepare("UPDATE referrals SET student_id = ? 
            WHERE (student_id IS NULL OR student_id = 0) 
              AND (
                  LOWER(TRIM(student_name)) = LOWER(?) 
                  OR LOWER(TRIM(student_name)) = LOWER(?)
                  OR LOWER(?) LIKE CONCAT('%', LOWER(TRIM(student_name)), '%')
              )");
        $updRef->execute([$uid, $studentFullName, $revStudentName, $studentFullName]);

        $updCs = $db->prepare("UPDATE counseling_sessions cs
            JOIN referrals r ON cs.referral_id = r.id
            SET cs.student_id = ?
            WHERE (cs.student_id IS NULL OR cs.student_id = 0)
              AND (r.student_id = ? OR LOWER(TRIM(r.student_name)) = LOWER(?) OR LOWER(TRIM(r.student_name)) = LOWER(?))");
        $updCs->execute([$uid, $uid, $studentFullName, $revStudentName]);
    } catch (Exception $e) {}
}

// Stats
$totalCases  = (int)$db->query("SELECT COUNT(*) FROM cases WHERE student_id=$uid")->fetchColumn();
$pending     = (int)$db->query("SELECT COUNT(*) FROM cases WHERE student_id=$uid AND status IN ('pending','under_review')")->fetchColumn();
$resolved    = (int)$db->query("SELECT COUNT(*) FROM cases WHERE student_id=$uid AND status='resolved'")->fetchColumn();
$unread      = (int)$db->query("SELECT COUNT(*) FROM notifications WHERE user_id=$uid AND is_read=0")->fetchColumn();

// Active referrals count
$refCountStmt = $db->prepare("SELECT COUNT(*) FROM referrals 
    WHERE (
        student_id = :uid 
        OR LOWER(TRIM(student_name)) = LOWER(:fullName) 
        OR LOWER(TRIM(student_name)) = LOWER(:revFullName)
    ) AND status NOT IN ('resolved','closed')");
$refCountStmt->execute([':uid' => $uid, ':fullName' => $studentFullName, ':revFullName' => $revStudentName]);
$activeRefsCount = (int)$refCountStmt->fetchColumn();

// Recent cases
$recentCases = $db->query("SELECT * FROM cases WHERE student_id=$uid ORDER BY submitted_at DESC LIMIT 5")->fetchAll();

// Upcoming session (from cases or referrals)
$sessStmt = $db->prepare("SELECT cs.*, u.first_name, u.last_name,
    COALESCE(c.case_number, r.ref_number, CONCAT('CS-', cs.id)) AS case_number,
    COALESCE(c.subject, CONCAT('Teacher Referral: ', REPLACE(r.concern_type, '_', ' '))) AS case_subject,
    r.id AS referral_id,
    CONCAT(t.first_name, ' ', t.last_name) AS teacher_name
    FROM counseling_sessions cs 
    LEFT JOIN users u ON cs.counselor_id=u.id 
    LEFT JOIN cases c ON cs.case_id=c.id
    LEFT JOIN referrals r ON cs.referral_id=r.id
    LEFT JOIN users t ON r.teacher_id=t.id
    WHERE (
        cs.student_id = :uid
        OR cs.referral_id IN (
            SELECT id FROM referrals WHERE student_id = :uid 
            OR LOWER(TRIM(student_name)) = LOWER(:fullName)
            OR LOWER(TRIM(student_name)) = LOWER(:revFullName)
        )
    )
    AND cs.status = 'scheduled' 
    AND cs.session_date >= NOW() 
    ORDER BY cs.session_date ASC LIMIT 1");
$sessStmt->execute([':uid' => $uid, ':fullName' => $studentFullName, ':revFullName' => $revStudentName]);
$session = $sessStmt->fetch();

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
    $mediaBadges = '';
    if (!empty($c['evidence_files']) && $c['evidence_files'] !== '[]') {
        $evArray = is_string($c['evidence_files']) ? json_decode($c['evidence_files'], true) : $c['evidence_files'];
        $cnt = is_array($evArray) ? count($evArray) : 1;
        $mediaBadges .= "<span class='badge-evidence' title='{$cnt} attached photo/video evidence file(s)'><i class='fas fa-paperclip'></i> {$cnt} Media</span>";
    }
    if (!empty($c['witnesses'])) {
        $mediaBadges .= "<span class='badge-witness' title='Witnesses mentioned in case report'><i class='fas fa-user-group'></i> Witness</span>";
    }
    $caseRows .= "<tr>
        <td class='cell-case-num'><span class='case-num-text'>{$c['case_number']}</span></td>
        <td style='font-weight:600'>".htmlspecialchars($c['subject'])."</td>
        <td>
          <div class='case-type-wrap'>
            <span class='case-type-label'>$type</span>
            $mediaBadges
          </div>
        </td>
        <td>".badge($c['status'])."</td>
        <td>".prioBadge($c['priority'])."</td>
        <td class='cell-actions'><button type='button' class='btn-row-action' onclick='openCaseModal({$c['id']})'><i class='fas fa-eye'></i> View</button></td>
    </tr>";
}
if (!$caseRows) $caseRows = "<tr><td colspan='6' class='empty-table-cell'>No cases submitted yet. <a href='student-submit.php' class='empty-table-link'>Submit your first concern</a></td></tr>";

$sessionHtml = '';
if ($session) {
    $dt = new DateTime($session['session_date']);
    $joinBtn = !empty($session['meeting_link']) 
        ? "<div style='margin-top:8px'><a href='".htmlspecialchars($session['meeting_link'])."' target='_blank' class='btn-sm-primary' style='padding:5px 12px;font-size:12px;background:#2563eb;text-decoration:none;display:inline-flex;align-items:center;gap:6px;border-radius:6px;box-shadow:0 2px 6px rgba(37,99,235,0.25);color:#fff'><i class='fas fa-video'></i> Join Virtual Meeting</a></div>" 
        : "";
    $counselorName = trim(($session['first_name'] ?? '') . ' ' . ($session['last_name'] ?? '')) ?: 'Guidance Staff';
    $refSessionBadge = !empty($session['referral_id']) 
        ? "<div style='font-size:11px;font-weight:700;color:#1e40af;margin-bottom:3px'><i class='fas fa-hand-holding-heart'></i> Teacher Referral Session</div>" 
        : "";

    $sessionHtml = "<div class='session-card'>
        <div class='session-date'><span class='s-month'>".$dt->format('M')."</span><span class='s-day'>".$dt->format('d')."</span></div>
        <div class='session-info'>
          $refSessionBadge
          <strong>Counseling Session</strong>
          <p>with ".htmlspecialchars($counselorName)."</p>
          <div class='s-time'><i class='fas fa-clock'></i> ".$dt->format('h:i A')."</div>
          <div class='s-loc'><i class='fas fa-map-marker-alt'></i> ".htmlspecialchars($session['location'] ?: 'Guidance Office')."</div>
          $joinBtn
          <div style='margin-top:8px'>
            <a href='student-sessions.php' style='font-size:12px;color:var(--maroon);font-weight:700;text-decoration:none'>View in Sessions &rarr;</a>
          </div>
        </div>
    </div>";
} else {
    $sessionHtml = "<div style='text-align:center;color:var(--text-3);padding:24px;font-size:14px'><i class='fas fa-calendar' style='font-size:28px;margin-bottom:8px;display:block'></i>No upcoming sessions</div>";
}

$refAlertBanner = '';
if ($activeRefsCount > 0) {
    $refAlertBanner = <<<BANNER
<div style="background:#eff6ff;border:1.5px solid #bfdbfe;border-radius:10px;padding:12px 18px;margin-bottom:18px;display:flex;align-items:center;justify-content:space-between;gap:14px;flex-wrap:wrap">
  <div style="display:flex;align-items:center;gap:12px">
    <div style="width:34px;height:34px;border-radius:50%;background:#3b82f6;color:#fff;display:flex;align-items:center;justify-content:center;font-size:15px;flex-shrink:0"><i class="fas fa-hand-holding-heart"></i></div>
    <div>
      <strong style="color:#1e40af;font-size:13.5px">Active Teacher Referral on Record ($activeRefsCount)</strong>
      <div style="color:#1d4ed8;font-size:12.5px">A teacher referral has been filed on your behalf. Guidance counselors are available to support and guide you.</div>
    </div>
  </div>
  <a href="student-cases.php?tab=referrals" class="btn-sm-primary" style="background:#2563eb;text-decoration:none;font-size:12px;padding:6px 14px;display:inline-flex;align-items:center;gap:6px"><i class="fas fa-eye"></i> View Referral &rarr;</a>
</div>
BANNER;
}

$content = <<<HTML
$refAlertBanner

<div class="summary-cards">
  <div class="sum-card">
    <div class="sum-icon" style="background:#fff0f0;color:var(--maroon)"><i class="fas fa-folder-open"></i></div>
    <div><div class="sum-num">$totalCases</div><div class="sum-label">My Cases</div></div>
  </div>
  <div class="sum-card">
    <div class="sum-icon" style="background:#eff6ff;color:#2563eb"><i class="fas fa-hand-holding-heart"></i></div>
    <div><div class="sum-num">$activeRefsCount</div><div class="sum-label">Teacher Referrals</div></div>
  </div>
  <div class="sum-card">
    <div class="sum-icon" style="background:#f0fdf4;color:#16a34a"><i class="fas fa-check-circle"></i></div>
    <div><div class="sum-num">$resolved</div><div class="sum-label">Resolved Cases</div></div>
  </div>
  <div class="sum-card">
    <div class="sum-icon" style="background:#fffbeb;color:#d97706"><i class="fas fa-bell"></i></div>
    <div><div class="sum-num">$unread</div><div class="sum-label">Unread Notifs</div></div>
  </div>
</div>
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



