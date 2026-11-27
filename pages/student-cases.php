<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/layout.php';
$user = requireLogin('student');
$db   = getDB();
$uid  = $user['id'];

$studentFullName = trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''));
$revStudentName  = trim(($user['last_name'] ?? '') . ' ' . ($user['first_name'] ?? ''));

// Self-heal: auto-link any referrals matching this student
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

        // Auto-link counseling sessions
        $updCs = $db->prepare("UPDATE counseling_sessions cs
            JOIN referrals r ON cs.referral_id = r.id
            SET cs.student_id = ?
            WHERE (cs.student_id IS NULL OR cs.student_id = 0)
              AND (r.student_id = ? OR LOWER(TRIM(r.student_name)) = LOWER(?) OR LOWER(TRIM(r.student_name)) = LOWER(?))");
        $updCs->execute([$uid, $uid, $studentFullName, $revStudentName]);
    } catch (Exception $e) {}
}

$filterStatus = $_GET['status'] ?? '';
if ($filterStatus === 'resolved') {
    header('Location: student-archive.php');
    exit;
}

// 1. Fetch Student-submitted Cases (Active only)
$caseWhere = "WHERE student_id=$uid AND status NOT IN ('resolved','closed')";
if ($filterStatus) $caseWhere = "WHERE student_id=$uid AND status='".addslashes($filterStatus)."'";
$cases = $db->query("SELECT * FROM cases $caseWhere ORDER BY submitted_at DESC, id DESC")->fetchAll();

// 2. Fetch Teacher Referrals filed for this student (Active only)
$refWhere = "WHERE (
    r.student_id = :uid 
    OR (
        (r.student_id IS NULL OR r.student_id = 0)
        AND (
            LOWER(TRIM(r.student_name)) = LOWER(:fullName)
            OR LOWER(TRIM(r.student_name)) = LOWER(:revFullName)
            OR LOWER(:fullName) LIKE CONCAT('%', LOWER(TRIM(r.student_name)), '%')
        )
    )
) AND r.status NOT IN ('resolved', 'closed')";

if ($filterStatus) {
    $refWhere .= " AND r.status = " . $db->quote($filterStatus);
}

$refStmt = $db->prepare("SELECT r.*, 
    CONCAT(t.first_name, ' ', t.last_name) AS teacher_name, 
    t.email AS teacher_email,
    cs.id AS session_id, cs.session_date, cs.session_type, cs.location, cs.meeting_link, cs.status AS session_status, cs.notes AS session_notes,
    u.first_name AS counselor_first, u.last_name AS counselor_last
    FROM referrals r
    JOIN users t ON r.teacher_id = t.id
    LEFT JOIN counseling_sessions cs ON cs.id = (
        SELECT id FROM counseling_sessions 
        WHERE referral_id = r.id AND status NOT IN ('cancelled')
        ORDER BY session_date DESC LIMIT 1
    )
    LEFT JOIN users u ON cs.counselor_id = u.id
    $refWhere
    ORDER BY r.submitted_at DESC, r.id DESC");
$refStmt->execute([
    ':uid'         => $uid,
    ':fullName'    => $studentFullName,
    ':revFullName' => $revStudentName,
]);
$referrals = $refStmt->fetchAll();

function badge(string $s): string {
    $map=['pending'=>'status-pending','ongoing'=>'status-ongoing','resolved'=>'status-resolved'];
    return "<span class='status-badge ".($map[$s]??'status-pending')."'>".ucwords(str_replace('_',' ',$s))."</span>";
}
function prioBadge(string $p): string {
    $map=['urgent'=>'priority-high','high'=>'priority-high','moderate'=>'priority-medium','medium'=>'priority-medium','low'=>'priority-low'];
    return "<span class='priority-badge ".($map[$p]??'priority-low')."'><i class='fas fa-circle'></i>".ucfirst($p)."</span>";
}

// Build Cases Rows
$caseRows = '';
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
    $caseRows .= "<tr>
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
if (!$caseRows) $caseRows = "<tr><td colspan='7' class='empty-table-cell'>No active reported cases found. <a href='student-submit.php' class='empty-table-link'>Submit a concern &rarr;</a></td></tr>";

// Build Teacher Referrals Rows
$refRows = '';
foreach ($referrals as $r) {
    $type = ucfirst(str_replace('_',' ',$r['concern_type']));
    $date = date('M d, Y', strtotime($r['submitted_at']));

    // Counseling session indicator
    $sessionBadge = "<span style='color:var(--text-3);font-size:12px;display:inline-flex;align-items:center;gap:4px'><i class='fas fa-hourglass-half'></i> Pending Schedule</span>";
    if (!empty($r['session_id'])) {
        $sDt = date('M d, Y h:i A', strtotime($r['session_date']));
        $isVirtual = ($r['session_type'] === 'virtual' || $r['session_type'] === 'online');
        $modeIcon = $isVirtual ? "<i class='fas fa-video' style='color:#2563eb'></i>" : "<i class='fas fa-building' style='color:var(--maroon)'></i>";
        $joinPill = ($isVirtual && !empty($r['meeting_link'])) ? "<a href='".htmlspecialchars($r['meeting_link'])."' target='_blank' class='btn-sm-primary' style='padding:2px 7px;font-size:10.5px;background:#2563eb;text-decoration:none;display:inline-flex;align-items:center;gap:3px;margin-left:4px;color:#fff' onclick='event.stopPropagation()'><i class='fas fa-video'></i> Join Link</a>" : "";
        $sessionBadge = "<div style='font-size:12px;font-weight:600;color:var(--text)'>$modeIcon $sDt $joinPill</div>";
    }

    $refJson = htmlspecialchars(json_encode([
        'id'             => (int)$r['id'],
        'ref_number'     => $r['ref_number'],
        'student_name'   => $r['student_name'],
        'grade_section'  => $r['grade_section'],
        'concern_type'   => $r['concern_type'],
        'urgency'        => $r['urgency'],
        'status'         => $r['status'],
        'observations'   => $r['observations'],
        'ai_summary'     => $r['ai_summary'] ?? null,
        'teacher_name'   => $r['teacher_name'],
        'submitted_at'   => date('M d, Y g:i A', strtotime($r['submitted_at'])),
        'session_id'     => !empty($r['session_id']) ? (int)$r['session_id'] : null,
        'session_date'   => !empty($r['session_date']) ? date('M d, Y \a\t h:i A', strtotime($r['session_date'])) : null,
        'session_type'   => ucfirst($r['session_type'] ?? 'In-person'),
        'location'       => $r['location'] ?? 'Guidance Office',
        'meeting_link'   => $r['meeting_link'] ?? '',
        'session_notes'  => $r['session_notes'] ?? '',
        'counselor_name' => trim(($r['counselor_first'] ?? '') . ' ' . ($r['counselor_last'] ?? '')) ?: 'Guidance Counselor'
    ]), ENT_QUOTES, 'UTF-8');

    $refRows .= "<tr>
        <td class='cell-case-num'><span class='case-num-text' style='color:var(--maroon);font-weight:800'>{$r['ref_number']}</span></td>
        <td style='font-weight:600'><i class='fas fa-chalkboard-teacher' style='color:var(--maroon);margin-right:6px'></i>".htmlspecialchars($r['teacher_name'])."</td>
        <td><span class='case-type-label'>$type</span></td>
        <td>".prioBadge($r['urgency'])."</td>
        <td>".badge($r['status'])."</td>
        <td>$sessionBadge</td>
        <td class='cell-date'>$date</td>
        <td class='cell-actions'><button type='button' class='btn-row-action' onclick='openStudentRefModal(this)' data-ref='{$refJson}'><i class='fas fa-eye'></i> View Referral</button></td>
    </tr>";
}
if (!$refRows) $refRows = "<tr><td colspan='8' class='empty-table-cell'>No active teacher referrals on record. Referrals made by your teachers will appear here.</td></tr>";

$selPending = $filterStatus==='pending'      ? 'selected' : '';
$selOngoing = $filterStatus==='ongoing'      ? 'selected' : '';

$casesCount = count($cases);
$refsCount  = count($referrals);

$refBanner = '';
if ($refsCount > 0) {
    $refBanner = <<<HTML
<div style="background:#eff6ff;border:1.5px solid #bfdbfe;border-radius:10px;padding:12px 18px;margin-bottom:18px;display:flex;align-items:center;justify-content:space-between;gap:14px;flex-wrap:wrap">
  <div style="display:flex;align-items:center;gap:12px;font-size:13.5px;color:#1e3a8a">
    <div style="width:36px;height:36px;border-radius:50%;background:#3b82f6;color:#fff;display:flex;align-items:center;justify-content:center;font-size:16px;flex-shrink:0">
      <i class="fas fa-hand-holding-heart"></i>
    </div>
    <div>
      <strong style="display:block;font-size:14px;color:#1e40af">Teacher Referral Notice ({$refsCount} Active)</strong>
      <span style="color:#1d4ed8;font-size:13px">Your teacher has referred you to the Guidance Office for guidance and support. You can review referral details and upcoming counseling dates below.</span>
    </div>
  </div>
  <button type="button" class="btn-sm-primary" style="background:#2563eb;font-size:12px;padding:6px 14px;white-space:nowrap;display:inline-flex;align-items:center;gap:6px" onclick="switchCaseTab('referrals')">
    <i class="fas fa-eye"></i> View Teacher Referrals &rarr;
  </button>
</div>
HTML;
}

$content = <<<HTML
<style>
.tab-switch-bar {
  display: flex;
  gap: 10px;
  border-bottom: 2px solid var(--border);
  margin-bottom: 20px;
  padding-bottom: 4px;
}
.tab-switch-btn {
  background: transparent;
  border: none;
  border-bottom: 3px solid transparent;
  padding: 10px 18px;
  font-size: 14.5px;
  font-weight: 700;
  color: var(--text-2);
  cursor: pointer;
  display: inline-flex;
  align-items: center;
  gap: 8px;
  transition: all 0.2s ease;
  margin-bottom: -6px;
  border-radius: 6px 6px 0 0;
}
.tab-switch-btn:hover {
  color: var(--maroon);
  background: rgba(85,0,0,0.03);
}
.tab-switch-btn.active {
  color: var(--maroon);
  border-bottom: 3px solid var(--maroon);
  background: #fff;
}
.tab-badge {
  font-size: 11px;
  font-weight: 800;
  padding: 2px 7px;
  border-radius: 12px;
  background: var(--bg2);
  color: var(--text-2);
}
.tab-switch-btn.active .tab-badge {
  background: var(--maroon);
  color: #fff;
}
.tab-badge.ref-badge {
  background: #2563eb;
  color: #fff;
}
</style>

<div class="page-info-banner" style="background:var(--maroon-pale);border:1px solid var(--maroon-tint);border-radius:10px;padding:12px 18px;margin-bottom:18px;display:flex;align-items:center;justify-content:space-between;gap:14px;flex-wrap:wrap">
  <div style="display:flex;align-items:center;gap:10px;font-size:13.5px;color:var(--text)">
    <i class="fas fa-archive" style="color:var(--maroon);font-size:18px"></i>
    <span>Completed and resolved cases are kept in your <strong>Case Archive</strong>. You can review past resolutions or file an appeal at any time.</span>
  </div>
  <a href="student-archive.php" class="btn-sm-primary" style="font-size:12px;padding:6px 14px;white-space:nowrap;display:inline-flex;align-items:center;gap:6px">
    <i class="fas fa-box-archive"></i> Go to Case Archive &rarr;
  </a>
</div>

$refBanner

<div class="content-card">
  <div class="card-header" style="flex-wrap:wrap;gap:12px">
    <div class="tab-switch-bar" style="margin-bottom:0;border-bottom:none">
      <button type="button" class="tab-switch-btn active" id="btnTabCases" onclick="switchCaseTab('cases')">
        <i class="fas fa-folder-open"></i> My Reported Cases
        <span class="tab-badge">$casesCount</span>
      </button>
      <button type="button" class="tab-switch-btn" id="btnTabRefs" onclick="switchCaseTab('referrals')">
        <i class="fas fa-hand-holding-heart"></i> Teacher Referrals
        <span class="tab-badge ref-badge">$refsCount</span>
      </button>
    </div>

    <div class="card-header-actions">
      <select class="status-select" onchange="if(this.value==='resolved'){window.location='student-archive.php';}else{const t=getCurrentTab();window.location='student-cases.php?tab='+t+(this.value?'&status='+this.value:'');}">
        <option value="">All Active</option>
        <option value="pending" $selPending>Pending</option>
        <option value="ongoing" $selOngoing>Ongoing</option>
        <option value="resolved">Resolved (Go to Archive)</option>
      </select>
      <a href="student-submit.php" class="btn-sm-primary"><i class="fas fa-plus"></i> New Concern</a>
    </div>
  </div>

  <!-- Tab 1: Student Reported Cases -->
  <div id="paneCases" class="tab-pane">
    <div class="table-wrap">
      <table class="data-table">
        <thead><tr><th>Case #</th><th>Subject</th><th>Type</th><th>Status</th><th>Priority</th><th>Date</th><th>Action</th></tr></thead>
        <tbody>$caseRows</tbody>
      </table>
    </div>
  </div>

  <!-- Tab 2: Teacher Referrals -->
  <div id="paneRefs" class="tab-pane" style="display:none">
    <div class="table-wrap">
      <table class="data-table">
        <thead><tr><th>Ref #</th><th>Referred By</th><th>Concern</th><th>Urgency</th><th>Status</th><th>Counseling Session</th><th>Date</th><th>Action</th></tr></thead>
        <tbody>$refRows</tbody>
      </table>
    </div>
  </div>
</div>

<!-- Modal 1: Case Detail Modal -->
<div class="modal-overlay hidden" id="caseModal" onclick="if(event.target.id==='caseModal')this.classList.add('hidden')">
  <div class="modal-box" style="max-width:600px">
    <button type="button" class="modal-close" onclick="document.getElementById('caseModal').classList.add('hidden')" aria-label="Close" title="Close"><i class="fas fa-times"></i></button>
    <h2 class="modal-title">Case Detail</h2>
    <div id="caseModalBody"></div>
  </div>
</div>

<!-- Modal 2: Teacher Referral Detail Modal -->
<div class="modal-overlay hidden" id="studentRefModal" onclick="if(event.target.id==='studentRefModal')this.classList.add('hidden')">
  <div class="modal-box" style="max-width:700px;width:95%">
    <button type="button" class="modal-close" onclick="document.getElementById('studentRefModal').classList.add('hidden')" aria-label="Close" title="Close"><i class="fas fa-times"></i></button>
    <div id="studentRefModalBody"></div>
  </div>
</div>

<script>
function getCurrentTab() {
  const isRefs = document.getElementById('paneRefs')?.style.display === 'block';
  return isRefs ? 'referrals' : 'cases';
}

function switchCaseTab(tab) {
  const btnCases = document.getElementById('btnTabCases');
  const btnRefs  = document.getElementById('btnTabRefs');
  const paneCases = document.getElementById('paneCases');
  const paneRefs  = document.getElementById('paneRefs');

  if (tab === 'referrals') {
    btnCases.classList.remove('active');
    btnRefs.classList.add('active');
    paneCases.style.display = 'none';
    paneRefs.style.display  = 'block';
  } else {
    btnRefs.classList.remove('active');
    btnCases.classList.add('active');
    paneRefs.style.display  = 'none';
    paneCases.style.display = 'block';
  }
}

function openStudentRefModal(btnOrData) {
  let ref = null;
  if (btnOrData instanceof HTMLElement) {
    ref = JSON.parse(btnOrData.getAttribute('data-ref'));
  } else {
    ref = btnOrData;
  }
  if (!ref) return;

  const typeLabel = (ref.concern_type || '').replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase());
  const urgMap = { urgent: 'priority-high', moderate: 'priority-medium', low: 'priority-low' };
  const statMap = { pending: 'status-pending', resolved: 'status-resolved' };
  const statLabel = { pending: 'Pending', resolved: 'Resolved' };

  let sessionBlock = '';
  if (ref.session_id) {
    const isVirtual = (ref.session_type || '').toLowerCase().includes('virtual') || (ref.session_type || '').toLowerCase().includes('online');
    sessionBlock = `
      <div style="background:#eff6ff;border:1.5px solid #bfdbfe;border-radius:10px;padding:16px;margin-bottom:18px">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px;flex-wrap:wrap;gap:8px">
          <div style="font-size:12px;font-weight:800;color:#1e40af;text-transform:uppercase;letter-spacing:0.5px">
            <i class="fas fa-calendar-check" style="color:#2563eb"></i> Counseling Session Appointed
          </div>
          <span class="status-badge status-under_review" style="font-size:11px"><i class="fas fa-clock"></i> Scheduled</span>
        </div>
        <div style="display:grid;grid-template-columns:repeat(2,1fr);gap:12px;font-size:13.5px">
          <div><label style="font-size:11px;color:var(--text-3);text-transform:uppercase;display:block">Counselor</label><strong>\${ref.counselor_name}</strong></div>
          <div><label style="font-size:11px;color:var(--text-3);text-transform:uppercase;display:block">Date &amp; Time</label><strong>\${ref.session_date}</strong></div>
          <div><label style="font-size:11px;color:var(--text-3);text-transform:uppercase;display:block">Session Mode</label><strong>\${ref.session_type}</strong></div>
          <div><label style="font-size:11px;color:var(--text-3);text-transform:uppercase;display:block">Location</label><strong>\${ref.location || 'Guidance Office'}</strong></div>
        </div>
        \${ref.meeting_link ? `
          <div style="margin-top:14px;padding-top:12px;border-top:1px dashed #bfdbfe;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px">
            <div>
              <span style="font-size:11.5px;font-weight:700;color:#1e40af;display:block"><i class="fas fa-video"></i> Virtual Online Meeting Link</span>
              <span style="font-size:12.5px;color:var(--text);word-break:break-all">\${ref.meeting_link}</span>
            </div>
            <a href="\${ref.meeting_link}" target="_blank" class="btn-sm-primary" style="background:#2563eb;color:#fff;text-decoration:none;display:inline-flex;align-items:center;gap:6px;padding:7px 14px;font-size:12.5px;border-radius:6px;box-shadow:0 2px 6px rgba(37,99,235,0.3)">
              <i class="fas fa-video"></i> Join Virtual Session &rarr;
            </a>
          </div>` : ''}
        \${ref.session_notes ? `
          <div style="margin-top:10px;font-size:12.5px;color:#1e3a8a;background:rgba(255,255,255,0.7);padding:8px 12px;border-radius:6px">
            <strong>Counselor Note:</strong> \${ref.session_notes}
          </div>` : ''}
      </div>`;
  } else {
    sessionBlock = `
      <div style="background:#fffbeb;border:1px solid #fde68a;border-radius:10px;padding:14px 16px;margin-bottom:18px;display:flex;align-items:center;gap:12px">
        <div style="width:34px;height:34px;border-radius:50%;background:#f59e0b;color:#fff;display:flex;align-items:center;justify-content:center;font-size:15px;flex-shrink:0">
          <i class="fas fa-hourglass-half"></i>
        </div>
        <div style="font-size:13px;color:#92400e">
          <strong>Counseling Appointment Pending</strong><br>
          The Guidance Office is reviewing this referral. A guidance counselor will set an appointment schedule with you shortly.
        </div>
      </div>`;
  }

  document.getElementById('studentRefModalBody').innerHTML = `
    <div style="border-bottom:1px solid var(--border);padding-bottom:14px;margin-bottom:18px;padding-right:48px;position:relative">
      <div style="display:flex;align-items:center;gap:8px;margin-bottom:8px;flex-wrap:wrap">
        <span style="background:var(--maroon-pale);color:var(--maroon);font-weight:900;font-size:14px;padding:3px 10px;border-radius:6px;border:1px solid var(--maroon-tint)">#\${ref.ref_number}</span>
        <span class="status-badge \${statMap[ref.status] || 'status-pending'}">\${statLabel[ref.status] || ref.status}</span>
        <span class="priority-badge \${urgMap[ref.urgency] || 'priority-low'}"><i class="fas fa-circle"></i> \${ref.urgency.toUpperCase()}</span>
      </div>
      <h2 style="font-size:18px;font-weight:800;color:var(--text);margin:0 0 8px 0;display:flex;align-items:center;gap:8px">
        <i class="fas fa-chalkboard-teacher" style="color:var(--maroon)"></i> Teacher Referral from \${ref.teacher_name}
      </h2>
      <div style="font-size:12.5px;color:var(--text-3);display:flex;align-items:center;gap:18px;flex-wrap:wrap">
        <span><i class="fas fa-tag" style="color:var(--maroon);margin-right:4px"></i> Concern: <strong style="color:var(--text)">\${typeLabel}</strong></span>
        <span><i class="fas fa-calendar-alt" style="color:var(--maroon);margin-right:4px"></i> Submitted on: <strong style="color:var(--text)">\${ref.submitted_at}</strong></span>
      </div>
    </div>

    \${sessionBlock}

    \${ref.ai_summary ? `
      <div style="background:rgba(37,99,235,0.06);border-left:4px solid #2563eb;border-radius:8px;padding:12px 16px;margin-bottom:16px">
        <div style="font-size:11.5px;font-weight:700;color:#1d4ed8;text-transform:uppercase;margin-bottom:4px">
          <i class="fas fa-magic"></i> AI Referral Summary
        </div>
        <div style="font-size:13.5px;line-height:1.5;color:var(--text)">\${ref.ai_summary}</div>
      </div>` : ''}

    <div style="background:var(--bg2);border:1px solid var(--border);border-radius:10px;padding:16px;margin-bottom:18px">
      <div style="font-size:12px;font-weight:800;color:var(--maroon);text-transform:uppercase;letter-spacing:0.8px;margin-bottom:10px">
        <i class="fas fa-quote-left" style="color:var(--maroon)"></i> Teacher's Observations / Reason for Referral
      </div>
      <div style="font-size:14px;line-height:1.7;color:var(--text);white-space:pre-wrap;background:var(--bg);padding:14px;border-radius:8px;border-left:3px solid var(--maroon)">\${ref.observations}</div>
    </div>

    <div style="display:flex;gap:10px;justify-content:flex-end;margin-top:20px;padding-top:14px;border-top:1px solid var(--border)">
      <button type="button" class="btn-secondary" onclick="document.getElementById('studentRefModal').classList.add('hidden')">Close</button>
      <a href="student-sessions.php" class="btn-primary" style="text-decoration:none;display:inline-flex;align-items:center;gap:6px">
        <i class="fas fa-calendar-alt"></i> Go to Sessions Tab
      </a>
    </div>
  `;

  document.getElementById('studentRefModal').classList.remove('hidden');
}

document.addEventListener('DOMContentLoaded', function() {
  const p = new URLSearchParams(window.location.search);
  const tab = p.get('tab');
  if (tab === 'referrals') {
    switchCaseTab('referrals');
  }

  const openId = p.get('open_case') || p.get('case_id');
  if (openId && typeof openCaseModal === 'function') {
    setTimeout(function() {
      openCaseModal(parseInt(openId));
    }, 200);
  }

  const openRef = p.get('open_ref');
  if (openRef) {
    switchCaseTab('referrals');
    const refBtns = document.querySelectorAll('button[data-ref]');
    for (const b of refBtns) {
      try {
        const d = JSON.parse(b.getAttribute('data-ref'));
        if (d.id == openRef || d.ref_number == openRef) {
          setTimeout(function() { openStudentRefModal(b); }, 250);
          break;
        }
      } catch(e) {}
    }
  }
});
</script>
HTML;

renderLayout($user, 'My Cases & Referrals', 'cases', $content);
