<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/layout.php';
$user = requireLogin('admin');
$db   = getDB();

$statusFilter = $_GET['status'] ?? '';
$search       = trim($_GET['q'] ?? '');

$where = "WHERE 1=1";
if ($statusFilter) {
    $where .= " AND cs.status = '" . addslashes($statusFilter) . "'";
} else {
    // Show active sessions by default — completed sessions are kept in Sessions Archive!
    $where .= " AND cs.status != 'completed'";
}
if ($search) {
    $sq = addslashes($search);
    $sqUnderscore = addslashes(str_replace(' ', '_', $search));
    $where .= " AND (
        LOWER(c.case_number) LIKE LOWER('%$sq%')
        OR LOWER(c.subject) LIKE LOWER('%$sq%')
        OR LOWER(c.description) LIKE LOWER('%$sq%')
        OR LOWER(c.concern_type) LIKE LOWER('%$sq%')
        OR LOWER(c.concern_type) LIKE LOWER('%$sqUnderscore%')
        OR LOWER(REPLACE(c.concern_type, '_', ' ')) LIKE LOWER('%$sq%')
        OR LOWER(s.first_name) LIKE LOWER('%$sq%')
        OR LOWER(s.last_name) LIKE LOWER('%$sq%')
        OR LOWER(CONCAT(s.first_name, ' ', s.last_name)) LIKE LOWER('%$sq%')
        OR LOWER(s.grade_section) LIKE LOWER('%$sq%')
        OR LOWER(COALESCE(c.reported_student_name, '')) LIKE LOWER('%$sq%')
        OR LOWER(COALESCE(c.reported_student_grade, '')) LIKE LOWER('%$sq%')
        OR LOWER(cs.location) LIKE LOWER('%$sq%')
        OR (c.is_anonymous=1 AND LOWER('anonymous') LIKE LOWER('%$sq%'))
    )";
}

$sessions = $db->query("SELECT cs.*,
    CASE WHEN c.is_anonymous = 1 THEN 'Anonymous Student' ELSE CONCAT(s.first_name,' ',s.last_name) END AS student_name,
    CASE WHEN c.is_anonymous = 1 THEN 'Confidential' ELSE s.grade_section END AS grade_section,
    c.case_number, c.subject AS case_subject, c.concern_type, c.priority AS case_priority,
    c.is_peer_report, c.reported_student_name, c.reported_student_grade
    FROM counseling_sessions cs
    LEFT JOIN users s ON cs.student_id=s.id
    JOIN cases c ON cs.case_id=c.id
    $where
    ORDER BY 
      CASE WHEN cs.status = 'in_progress' THEN 1
           WHEN cs.status = 'scheduled' THEN 2
           WHEN cs.status = 'ongoing' THEN 3
           ELSE 4 END,
      cs.session_date DESC")->fetchAll();

// Counts for summary metrics
$countTotal     = (int)$db->query("SELECT COUNT(*) FROM counseling_sessions")->fetchColumn();
$countActive    = (int)$db->query("SELECT COUNT(*) FROM counseling_sessions WHERE status IN ('scheduled','in_progress','ongoing')")->fetchColumn();
$countScheduled = (int)$db->query("SELECT COUNT(*) FROM counseling_sessions WHERE status='scheduled'")->fetchColumn();
$countLive      = (int)$db->query("SELECT COUNT(*) FROM counseling_sessions WHERE status='in_progress'")->fetchColumn();
$countOngoing   = (int)$db->query("SELECT COUNT(*) FROM counseling_sessions WHERE status='ongoing'")->fetchColumn();
$countCompleted = (int)$db->query("SELECT COUNT(*) FROM counseling_sessions WHERE status='completed'")->fetchColumn();

$rows = '';
foreach ($sessions as $s) {
    $dt = new DateTime($s['session_date']);
    
    $statusBadge = match($s['status']) {
        'scheduled'   => "<span class='status-badge' style='background:#eff6ff;color:#1d4ed8;border:1px solid #bfdbfe;font-weight:700'><i class='fas fa-calendar-alt'></i> Scheduled</span>",
        'in_progress' => "<span class='status-badge' style='background:#dcfce7;color:#15803d;border:1px solid #86efac;font-weight:700;display:inline-flex;align-items:center;gap:6px'><span class='live-pulse-dot'></span> Live In-Session</span>",
        'ongoing'     => "<span class='status-badge status-ongoing' style='background:#fef3c7;color:#92400e;border:1px solid #fde68a;font-weight:700'><i class='fas fa-clock-rotate-left'></i> Ongoing Progress</span>",
        'completed'   => "<span class='status-badge status-resolved'><i class='fas fa-check-circle'></i> Completed</span>",
        'cancelled'   => "<span class='status-badge' style='background:#fef2f2;color:#991b1b'>Cancelled</span>",
        'no_show'     => "<span class='status-badge' style='background:#fffbeb;color:#92400e'>No Show</span>",
        default       => "<span class='status-badge status-pending'>".ucfirst($s['status'])."</span>"
    };

    $actionBtn = '';
    if ($s['status'] === 'scheduled') {
        $actionBtn = "<button type='button' class='btn-sm-primary' onclick='openSessionConsole({$s['id']})' style='display:inline-flex;align-items:center;gap:6px' title='Click to turn personal session on'>
            <i class='fas fa-play'></i> Start Session
        </button>";
    } elseif ($s['status'] === 'in_progress') {
        $actionBtn = "<button type='button' class='btn-sm-primary' style='background:#16a34a;display:inline-flex;align-items:center;gap:6px' onclick='openSessionConsole({$s['id']})' title='Session currently in progress'>
            <i class='fas fa-headset'></i> Active Console
        </button>";
    } elseif ($s['status'] === 'ongoing') {
        $actionBtn = "<button type='button' class='btn-sm-primary' style='background:#b45309;display:inline-flex;align-items:center;gap:6px' onclick='openSessionConsole({$s['id']})' title='Continuous progress tracking active'>
            <i class='fas fa-clock-rotate-left'></i> Progress
        </button>";
    } else {
        $actionBtn = "<button type='button' class='btn-sm-outline' onclick='openSessionConsole({$s['id']})' style='display:inline-flex;align-items:center;gap:6px'>
            <i class='fas fa-eye'></i> View Details
        </button>";
    }

    $studentCol = "<strong>".htmlspecialchars($s['student_name']??'—')."</strong>";
    if (!empty($s['is_peer_report'])) {
        $studentCol = "<strong>".htmlspecialchars($s['reported_student_name']?:'Target Student')."</strong>"
            . "<div style='font-size:11px;color:#be185d'><i class='fas fa-user-tag'></i> Rep by: ".htmlspecialchars($s['student_name']??'Anon')."</div>";
    }

    $rows .= "<tr style='cursor:pointer' onclick='if(!event.target.closest(\"select\") && !event.target.closest(\"button\")) openSessionConsole({$s['id']})'>
        <td><strong style='color:var(--maroon);font-size:14px'>{$s['case_number']}</strong></td>
        <td>$studentCol</td>
        <td>".htmlspecialchars($s['grade_section']??'—')."</td>
        <td>".$dt->format('M d, Y')."</td>
        <td style='font-weight:600'>".$dt->format('h:i A')."</td>
        <td><i class='fas fa-map-marker-alt' style='color:var(--maroon);font-size:11px'></i> ".htmlspecialchars($s['location'] ?? 'Guidance Office')."</td>
        <td>".ucfirst($s['session_type'] ?? 'In-person')."</td>
        <td>$statusBadge</td>
        <td>
          <div style='display:inline-flex;gap:6px;align-items:center'>
            $actionBtn
            <button type='button' class='btn-row-action btn-action-chat' onclick=\"event.stopPropagation(); window.location.href='admin-chats.php?case_id={$s['case_id']}'\" title='Open chat with student' style='font-size:11.5px;padding:5px 9px'>
              <i class='fas fa-comment-dots'></i> Chat
            </button>
            <select class='status-select' style='font-size:12px;padding:5px 8px' onchange='updateSessionStatus({$s['id']},this.value,event)'>
              <option value='scheduled' ".($s['status']==='scheduled'?'selected':'').">Scheduled</option>
              <option value='in_progress' ".($s['status']==='in_progress'?'selected':'').">In Progress</option>
              <option value='ongoing' ".($s['status']==='ongoing'?'selected':'').">Ongoing</option>
              <option value='completed' ".($s['status']==='completed'?'selected':'').">Completed</option>
              <option value='cancelled' ".($s['status']==='cancelled'?'selected':'').">Cancelled</option>
              <option value='no_show'   ".($s['status']==='no_show'?'selected':'').">No Show</option>
            </select>
          </div>
        </td>
    </tr>";
}
if (!$rows) $rows = "<tr><td colspan='9' style='text-align:center;color:var(--text-3);padding:40px'>No counseling sessions match the filter.</td></tr>";

$sqVal = htmlspecialchars($search);
$fAll   = $statusFilter === '' ? 'selected' : '';
$fSched = $statusFilter === 'scheduled' ? 'selected' : '';
$fProg  = $statusFilter === 'in_progress' ? 'selected' : '';
$fOng   = $statusFilter === 'ongoing' ? 'selected' : '';
$fComp  = $statusFilter === 'completed' ? 'selected' : '';
$fCanc  = $statusFilter === 'cancelled' ? 'selected' : '';

$content = <<<HTML
<!-- Top Tab Bar: Active Sessions vs Completed Sessions Archive -->
<div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;margin-bottom:18px">
  <div class="totp-tab-bar" style="background:var(--bg2);padding:4px;border-radius:12px;border:1px solid var(--border);display:inline-flex">
    <a href="admin-sessions.php" class="tab-pill active" style="text-decoration:none">
      <i class="fas fa-calendar-alt"></i> Active Sessions <span style="font-size:11px;font-weight:800;background:var(--maroon);color:#fff;padding:1px 6px;border-radius:10px;margin-left:4px">$countActive</span>
    </a>
    <a href="admin-sessions-archive.php" class="tab-pill" style="text-decoration:none">
      <i class="fas fa-archive"></i> Sessions Archive <span style="font-size:11px;opacity:0.85">($countCompleted)</span>
    </a>
  </div>

  <div style="display:inline-flex;gap:8px;align-items:center">
    <a href="admin-sessions-archive.php" class="btn-sm-primary" style="background:var(--maroon);text-decoration:none;display:inline-flex;align-items:center;gap:6px">
      <i class="fas fa-box-archive"></i> Go to Sessions Archive
    </a>
  </div>
</div>

<!-- Summary Metric Strip -->
<div class="summary-cards" style="grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); margin-bottom: 22px;">
  <div class="sum-card">
    <div class="sum-icon" style="background:rgba(85,0,0,0.1);color:var(--maroon)"><i class="fas fa-calendar-check"></i></div>
    <div><div class="sum-num">$countTotal</div><div class="sum-label">Total Sessions</div></div>
  </div>
  <div class="sum-card">
    <div class="sum-icon" style="background:rgba(37,99,235,0.12);color:#2563eb"><i class="fas fa-clock"></i></div>
    <div><div class="sum-num">$countScheduled</div><div class="sum-label">Appointed / Scheduled</div></div>
  </div>
  <div class="sum-card" style="border: 1px solid rgba(22,163,74,0.3); background: rgba(22,163,74,0.06);">
    <div class="sum-icon" style="background:rgba(22,163,74,0.15);color:#16a34a"><i class="fas fa-headset"></i></div>
    <div><div class="sum-num" style="color:#15803d">$countLive</div><div class="sum-label" style="font-weight:700;color:#166534">Live In-Session</div></div>
  </div>
  <div class="sum-card" style="border: 1px solid rgba(217,119,6,0.3); background: rgba(254,243,199,0.5);">
    <div class="sum-icon" style="background:rgba(217,119,6,0.15);color:#b45309"><i class="fas fa-clock-rotate-left"></i></div>
    <div><div class="sum-num" style="color:#b45309">$countOngoing</div><div class="sum-label" style="font-weight:700;color:#92400e">Ongoing Progress</div></div>
  </div>
  <div class="sum-card" style="border: 1px solid rgba(22,163,74,0.3); background: rgba(240,253,244,0.6); cursor:pointer" onclick="window.location.href='admin-sessions-archive.php'" title="Click to open Sessions Archive">
    <div class="sum-icon" style="background:rgba(22,163,74,0.15);color:#16a34a"><i class="fas fa-check-double"></i></div>
    <div>
      <div class="sum-num" style="color:#15803d">$countCompleted</div>
      <div class="sum-label" style="font-weight:700;color:#166534">Sessions Archive &rarr;</div>
    </div>
  </div>
</div>

<div class="content-card">
  <div class="card-header">
    <div>
      <h3><i class="fas fa-calendar-alt"></i> Counseling Sessions Console</h3>
      <span style="font-size:13px;color:var(--text-3)">Click any appointed session to start, write notes, and manage resolutions</span>
    </div>
    <a href="admin-sessions-archive.php" class="btn-sm-outline" style="display:inline-flex;align-items:center;gap:6px">
      <i class="fas fa-box-archive"></i> View Sessions Archive
    </a>
  </div>

  <!-- Search & Filter Bar -->
  <div class="filter-bar" style="margin-bottom:18px;flex-wrap:wrap">
    <div style="display:flex;gap:8px;flex:1;min-width:240px">
      <div class="input-wrap" style="flex:1">
        <i class="fas fa-search"></i>
        <input type="text" id="sessionSearchQ" value="$sqVal" placeholder="Search active sessions by case #, student name, concern, location…" onkeydown="if(event.key==='Enter') doSessionSearch()"/>
      </div>
      <button class="btn-sm-primary" onclick="doSessionSearch()"><i class="fas fa-search"></i> Search</button>
    </div>
    <select class="status-select" onchange="filterSessionStatus(this.value)">
      <option value="" $fAll>All Active Sessions</option>
      <option value="scheduled" $fSched>Scheduled (Appointed)</option>
      <option value="in_progress" $fProg>Live In-Session</option>
      <option value="ongoing" $fOng>Ongoing Progress</option>
      <option value="completed" $fComp>Completed (Archived)</option>
      <option value="cancelled" $fCanc>Cancelled</option>
    </select>
    <button class="btn-sm-outline" onclick="window.location='admin-sessions.php'"><i class="fas fa-times"></i> Reset</button>
  </div>

  <div class="table-wrap">
    <table class="data-table">
      <thead><tr><th>Case #</th><th>Student</th><th>Grade/Sec</th><th>Date</th><th>Time</th><th>Location</th><th>Mode</th><th>Status</th><th>Actions</th></tr></thead>
      <tbody>$rows</tbody>
    </table>
  </div>
</div>

<!-- ============================================================== -->
<!-- COUNSELING SESSION CONSOLE MODAL -->
<!-- ============================================================== -->
<div class="modal-overlay hidden" id="sessionConsoleModal" onclick="closeSessionConsoleOverlay(event)">
  <div class="modal-box" style="max-width:920px;width:95%;padding:26px;max-height:92vh;overflow-y:auto">
    <button type="button" class="modal-close" onclick="closeSessionConsole()"><i class="fas fa-times"></i></button>

    <div id="sessionConsoleContent">
      <div style="text-align:center;padding:50px">
        <span class="spinner" style="border-color:rgba(85,0,0,.2);border-top-color:var(--maroon);width:36px;height:36px;border-width:4px;display:inline-block"></span>
      </div>
    </div>
  </div>
</div>

<!-- ============================================================== -->
<!-- END SESSION DECISION MODAL -->
<!-- ============================================================== -->
<div class="modal-overlay hidden" id="endSessionModal" onclick="if(event.target===this) closeEndSessionModal()">
  <div class="modal-box" style="max-width:680px;width:95%;padding:28px;max-height:90vh;overflow-y:auto">
    <button type="button" class="modal-close" onclick="closeEndSessionModal()"><i class="fas fa-times"></i></button>
    
    <div style="margin-bottom:22px;padding-bottom:14px;border-bottom:1px solid var(--border)">
      <h2 style="font-size:20px;font-weight:800;color:var(--text);margin:0 0 6px 0">
        <i class="fas fa-clipboard-check" style="color:var(--maroon);margin-right:6px"></i> End Counseling Session
      </h2>
      <p style="font-size:13.5px;color:var(--text-2);margin:0">
        Please indicate whether this case has been resolved or if follow-up counseling is required.
      </p>
    </div>

    <!-- Choice Cards -->
    <div class="decision-grid">
      <div class="decision-choice-card" id="choiceCardNo" onclick="selectEndSessionChoice('no')">
        <div style="display:flex;align-items:center;gap:10px;margin-bottom:8px">
          <i class="fas fa-calendar-plus" style="color:#b45309;font-size:16px"></i>
          <strong style="font-size:15px;color:var(--text)">Needs Another Session</strong>
        </div>
        <p style="font-size:12.5px;color:var(--text-2);margin:0;line-height:1.45">The concern is still ongoing. Schedule a follow-up counseling session date and time for the student.</p>
      </div>

      <div class="decision-choice-card" id="choiceCardYes" onclick="selectEndSessionChoice('yes')">
        <div style="display:flex;align-items:center;gap:10px;margin-bottom:8px">
          <i class="fas fa-check-circle" style="color:#15803d;font-size:16px"></i>
          <strong style="font-size:15px;color:var(--text)">Case is Resolved</strong>
        </div>
        <p style="font-size:12.5px;color:var(--text-2);margin:0;line-height:1.45">The concern has been concluded. Record official resolution notes and any regulated school rule sanctions.</p>
      </div>
    </div>

    <!-- SUB-FORM: If NO (Needs another session) -->
    <div id="subFormScheduleNext" class="hidden" style="background:var(--bg2);border:1px solid var(--border);border-radius:12px;padding:20px;margin-bottom:20px">
      <h4 style="font-size:15px;font-weight:800;color:#b45309;margin-bottom:14px;display:flex;align-items:center;gap:8px">
        <i class="fas fa-calendar-alt"></i> Set Next Counseling Schedule
      </h4>
      <div class="form-row">
        <div class="form-group">
          <label>Next Session Date & Time <span class="req">*</span></label>
          <div class="input-wrap"><i class="fas fa-clock"></i><input type="datetime-local" id="nextSessionDate"></div>
        </div>
        <div class="form-group">
          <label>Session Mode</label>
          <div class="select-wrap"><i class="fas fa-video"></i>
            <select id="nextSessionType">
              <option value="in-person">In-Person</option>
              <option value="online">Online</option>
            </select>
          </div>
        </div>
      </div>
      <div class="form-group">
        <label>Location / Room</label>
        <div class="input-wrap"><i class="fas fa-map-marker-alt"></i><input type="text" id="nextSessionLocation" value="Guidance Office"></div>
      </div>
      <div class="form-group" style="margin-bottom:0">
        <label>Next Session Remarks / Objectives</label>
        <textarea id="nextSessionRemarks" rows="2" class="form-control" style="width:100%;padding:10px;border-radius:8px;border:1.5px solid var(--border);font-size:13.5px" placeholder="e.g. Follow-up on behavioral contract agreement, progress check-in, or parent conference update..."></textarea>
      </div>
    </div>

    <!-- SUB-FORM: If YES (Case is resolved) -->
    <div id="subFormResolution" class="hidden" style="background:var(--bg2);border:1px solid var(--border);border-radius:12px;padding:20px;margin-bottom:20px">
      <h4 style="font-size:15px;font-weight:800;color:#15803d;margin-bottom:14px;display:flex;align-items:center;gap:8px">
        <i class="fas fa-clipboard-check"></i> Case Resolution & Sanctions
      </h4>
      <div class="form-group">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:6px;flex-wrap:wrap;gap:8px">
          <label style="margin:0">Resolution Summary <span class="req">*</span></label>
          <button type="button" class="btn-sm-primary" id="btnAiSuggestEndSession" onclick="aiSuggestEndSessionResolution()" style="background:linear-gradient(135deg,#7c3aed,#4f46e5);border:none;display:inline-flex;align-items:center;gap:6px;font-size:12px;padding:5px 13px;border-radius:20px;font-weight:700;box-shadow:0 2px 8px rgba(124,58,237,0.3);color:#fff;cursor:pointer">
            <i class="fas fa-wand-magic-sparkles"></i> AI Suggest Resolution
          </button>
        </div>
        <div id="aiResolutionNotice" style="display:none;background:#f5f3ff;border:1px solid #ddd6fe;border-radius:8px;padding:8px 12px;margin-bottom:8px;font-size:12px;color:#5b21b6">
          <i class="fas fa-sparkles"></i> <strong>AI Recommendation:</strong> Synthesized from interview observations and counselor notes.
        </div>
        <textarea id="caseResolutionText" rows="3" class="form-control" style="width:100%;padding:12px;border-radius:8px;border:1.5px solid var(--border);font-size:14px" placeholder="Detail how the case was resolved (e.g. mutual agreement reached, guidance counseling concluded, restorative conference completed, student commitments established)..."></textarea>
      </div>
      
      <div class="form-group" style="margin-bottom:0">
        <label style="display:flex;justify-content:space-between;align-items:center">
          <span>Regulated School Rule Sanction / Disciplinary Action</span>
          <span style="font-size:11px;color:var(--text-3);font-weight:600">(if regulated school rules are affected)</span>
        </label>
        <p style="font-size:12px;color:var(--text-3);margin-bottom:8px">Select common school rule measures below or type custom sanctions according to GNHS student handbook:</p>
        
        <!-- School Rule Chips -->
        <div class="rule-chip-container">
          <span class="rule-chip" onclick="applyRuleChip('Parent / Guardian Conference Required')"><i class="fas fa-plus"></i> Parent Conference</span>
          <span class="rule-chip" onclick="applyRuleChip('Restorative Campus / Community Service')"><i class="fas fa-plus"></i> Restorative Service</span>
          <span class="rule-chip" onclick="applyRuleChip('Written Apology & Good Conduct Pledge')"><i class="fas fa-plus"></i> Written Apology</span>
          <span class="rule-chip" onclick="applyRuleChip('Behavioral Monitoring Agreement (2 Weeks)')"><i class="fas fa-plus"></i> Behavioral Agreement</span>
          <span class="rule-chip" onclick="applyRuleChip('Official Guidance Warning & Reprimand')"><i class="fas fa-plus"></i> Guidance Warning</span>
          <span class="rule-chip" onclick="applyRuleChip('No Sanctions (Resolved through Guidance Counseling Only)')"><i class="fas fa-check"></i> No Sanctions / Counseling Only</span>
        </div>

        <textarea id="caseSanctionsText" rows="2" class="form-control" style="width:100%;padding:10px;border-radius:8px;border:1.5px solid var(--border);font-size:13.5px" placeholder="Specify any regulated school rules sanctions or corrective actions imposed..."></textarea>
      </div>
    </div>

    <!-- Action Buttons -->
    <div style="display:flex;justify-content:flex-end;gap:12px">
      <button type="button" class="btn-secondary" onclick="closeEndSessionModal()">Back to Session</button>
      <button type="button" class="btn-primary" id="btnConfirmEndSession" onclick="submitEndSessionDecision()" disabled style="opacity:0.6">
        <i class="fas fa-check"></i> Select an Option
      </button>
    </div>
  </div>
</div>

<!-- ============================================================== -->
<!-- CANCEL LIVE SESSION CONFIRMATION MODAL                         -->
<!-- ============================================================== -->
<div class="modal-overlay hidden" id="cancelSessionConfirmModal" onclick="if(event.target===this) closeCancelSessionModal()">
  <div class="modal-box" style="max-width:460px;width:95%;padding:28px;text-align:center">
    <div style="width:64px;height:64px;border-radius:50%;background:#fee2e2;color:#dc2626;display:flex;align-items:center;justify-content:center;font-size:26px;margin:0 auto 16px auto">
      <i class="fas fa-triangle-exclamation"></i>
    </div>
    <h3 style="font-size:18px;font-weight:800;color:var(--text);margin-bottom:8px">Cancel Live Counseling Session?</h3>
    <p style="font-size:13.5px;color:var(--text-2);line-height:1.5;margin-bottom:24px">
      Are you sure you want to cancel this active session? This will stop the active live timer and revert the status back to <strong>Scheduled</strong> if it was started by accident.
    </p>
    <div style="display:flex;gap:12px;justify-content:center">
      <button type="button" class="btn-secondary" style="padding:10px 18px" onclick="closeCancelSessionModal()">Keep Session Active</button>
      <button type="button" class="btn-primary" id="btnConfirmCancelLiveSession" style="background:#dc2626;padding:10px 18px;display:inline-flex;align-items:center;gap:6px" onclick="executeCancelLiveSession()">
        <i class="fas fa-undo"></i> Yes, Cancel Session
      </button>
    </div>
  </div>
</div>

<script>
let _currentSessionData = null;
let _sessionTimerInterval = null;
let _selectedEndDecision = null; // 'no' or 'yes'

function doSessionSearch() {
  const q = document.getElementById('sessionSearchQ').value.trim();
  const url = new URL(window.location);
  if (q) url.searchParams.set('q', q);
  else url.searchParams.delete('q');
  window.location = url;
}

function filterSessionStatus(val) {
  const url = new URL(window.location);
  if (val) url.searchParams.set('status', val);
  else url.searchParams.delete('status');
  window.location = url;
}

async function updateSessionStatus(id, status, ev) {
  if (ev) ev.stopPropagation();
  try {
    const r = await fetch('/gnhs-guidance/api/update_session.php?id=' + id + '&status=' + status);
    const d = await r.json();
    if (d.success) {
      showToast('Session status updated!', 'success');
      setTimeout(() => window.location.reload(), 1000);
    } else {
      showToast(d.message || 'Failed to update.', 'error');
    }
  } catch {
    showToast('Updated.', 'success');
  }
}

function closeSessionConsoleOverlay(e) {
  if (e.target.id === 'sessionConsoleModal') {
    closeSessionConsole();
  }
}

function closeSessionConsole() {
  if (_sessionTimerInterval) clearInterval(_sessionTimerInterval);
  document.getElementById('sessionConsoleModal').classList.add('hidden');
}

// ── OPEN THE COUNSELING SESSION CONSOLE ─────────────────────────
async function openSessionConsole(sessionId) {
  const modal = document.getElementById('sessionConsoleModal');
  const container = document.getElementById('sessionConsoleContent');
  modal.classList.remove('hidden');
  
  container.innerHTML = '<div style="text-align:center;padding:50px"><span class="spinner" style="border-color:rgba(85,0,0,.2);border-top-color:var(--maroon);width:36px;height:36px;border-width:4px;display:inline-block"></span></div>';

  try {
    const res = await fetch('/gnhs-guidance/api/get_session_details.php?id=' + sessionId);
    const data = await res.json();
    if (!data.success) {
      container.innerHTML = '<div class="error-msg">' + (data.message || 'Could not load session.') + '</div>';
      return;
    }

    _currentSessionData = data;
    renderSessionConsole(data);

  } catch (err) {
    container.innerHTML = '<div class="error-msg">Failed to connect to session service.</div>';
  }
}

// ── RENDER SESSION CONSOLE DOM ─────────────────────────────────
function renderSessionConsole(data) {
  const s = data.session;
  const notes = data.notes || [];
  const container = document.getElementById('sessionConsoleContent');

  if (_sessionTimerInterval) clearInterval(_sessionTimerInterval);

  const isLive = (s.status === 'in_progress');
  const isScheduled = (s.status === 'scheduled');
  const isOngoing = (s.status === 'ongoing');
  const isCompleted = (s.status === 'completed');

  const dt = new Date(s.session_date);
  const formattedDate = dt.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
  const formattedTime = dt.toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit', hour12: true });

  let evidenceHtml = '';
  if (s.parsed_evidence && s.parsed_evidence.length > 0) {
    evidenceHtml = '<div style="margin-top:12px;padding-top:12px;border-top:1px dashed var(--border)">'
      + '<strong style="font-size:12px;color:var(--text-2);display:block;margin-bottom:6px"><i class="fas fa-photo-film" style="color:var(--maroon)"></i> Attached Case Evidence (' + s.parsed_evidence.length + ')</strong>'
      + '<div class="evidence-gallery-grid">' + renderEvidenceGallery(s.parsed_evidence) + '</div>'
      + '</div>';
  }

  // Session Control Strip based on Status
  let sessionControlBar = '';
  if (isScheduled) {
    sessionControlBar = `
      <div class="session-prompt-card">
        <div>
          <div style="font-size:15px;font-weight:800;color:var(--maroon);margin-bottom:4px">
            <i class="fas fa-user-clock"></i> Appointed Session Scheduled
          </div>
          <div style="font-size:13px;color:var(--text-2);line-height:1.4">
            Turn this counseling session ON when the student arrives personally. This marks the case session as live, activates the counseling timer, and enables real-time case note recording for school records.
          </div>
        </div>
        <button type="button" class="btn-primary" style="background:var(--maroon);white-space:nowrap;padding:12px 20px;font-size:14px;border-radius:10px;box-shadow:0 4px 14px rgba(85,0,0,0.25)" onclick="startPersonalSession(\${s.id})">
          <i class="fas fa-play-circle"></i> Turn Session On
        </button>
      </div>`;
  } else if (isLive) {
    sessionControlBar = `
      <div class="session-live-bar">
        <div class="session-live-info">
          <span class="live-pulse-dot"></span>
          <div>
            <div style="font-weight:900;font-size:15px;letter-spacing:0.5px">LIVE PERSONAL COUNSELING SESSION IN PROGRESS</div>
            <div style="font-size:12px;opacity:0.85">Started at \${s.started_at ? new Date(s.started_at).toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'}) : 'Just now'} &bull; Location: \${escapeHtml(s.location || 'Guidance Office')}</div>
          </div>
        </div>
        <div style="display:flex;align-items:center;gap:10px">
          <div class="session-timer-badge">
            <i class="fas fa-stopwatch" style="margin-right:6px"></i><span id="sessionTimerDisplay">00:00:00</span>
          </div>
          <button type="button" class="btn-secondary" style="background:rgba(255,255,255,0.18);border:1px solid rgba(255,255,255,0.45);color:#fff;padding:9px 14px;border-radius:10px;font-weight:700;font-size:13px;cursor:pointer;display:inline-flex;align-items:center;gap:6px;transition:.2s" onclick="cancelLiveSession(\${s.id})" title="Accidentally clicked start? Revert back to Scheduled">
            <i class="fas fa-undo"></i> Cancel Session
          </button>
          <button type="button" class="btn-danger" style="background:#dc2626;border:none;color:#fff;padding:10px 18px;border-radius:10px;font-weight:800;font-size:13.5px;cursor:pointer;display:inline-flex;align-items:center;gap:6px;box-shadow:0 4px 12px rgba(220,38,38,0.3)" onclick="openEndSessionModal()">
            <i class="fas fa-check-circle"></i> End Session
          </button>
        </div>
      </div>`;
  } else if (isOngoing) {
    sessionControlBar = `
      <div style="background:#fffbeb;border:1.5px solid #fde68a;border-radius:12px;padding:14px 18px;display:flex;align-items:center;justify-content:space-between;margin-bottom:20px">
        <div style="display:flex;align-items:center;gap:10px">
          <i class="fas fa-clock-rotate-left" style="color:#d97706;font-size:18px"></i>
          <div>
            <strong style="color:#b45309;font-size:15px;display:block">Counseling Session In Ongoing Progress</strong>
            <span style="font-size:12px;color:#92400e">Continuous case tracking is active. Session concluded on \${s.ended_at ? new Date(s.ended_at).toLocaleString([], {month:'short', day:'numeric', hour:'2-digit', minute:'2-digit'}) : 'Recently'}. Next steps are being tracked until complete resolution.</span>
          </div>
        </div>
        <div style="display:flex;align-items:center;gap:8px">
          <span class="status-badge status-ongoing" style="background:#fef3c7;color:#92400e;border:1px solid #fde68a;font-weight:700">Ongoing Progress</span>
          <button type="button" class="btn-primary" style="background:var(--maroon);padding:8px 14px;font-size:12.5px" onclick="startPersonalSession(\${s.id})">
            <i class="fas fa-play"></i> Resume Session
          </button>
        </div>
      </div>`;
  } else if (isCompleted) {
    sessionControlBar = `
      <div style="background:#f0fdf4;border:1.5px solid #bbf7d0;border-radius:12px;padding:14px 18px;display:flex;align-items:center;justify-content:space-between;margin-bottom:20px">
        <div style="display:flex;align-items:center;gap:10px">
          <i class="fas fa-check-circle" style="color:#15803d;font-size:18px"></i>
          <div>
            <strong style="color:#15803d;font-size:15px;display:block">Counseling Session Completed</strong>
            <span style="font-size:12px;color:#166534">Ended on \${s.ended_at ? new Date(s.ended_at).toLocaleString([], {month:'short', day:'numeric', hour:'2-digit', minute:'2-digit'}) : 'Archived'}</span>
          </div>
        </div>
        <div>
          <span class="status-badge status-resolved">Completed</span>
        </div>
      </div>`;
  }

  // Multi-Session Continuous Progress Tracker HTML
  const allSessions = data.all_sessions || [];
  let allSessionsHtml = '';
  if (allSessions.length > 0) {
    allSessionsHtml = `
      <div style="background:var(--bg2);border:1px solid var(--border);border-radius:12px;padding:18px;margin-bottom:18px">
        <div style="font-size:13px;font-weight:800;color:var(--text-2);text-transform:uppercase;letter-spacing:1px;margin-bottom:10px;display:flex;align-items:center;justify-content:space-between">
          <span><i class="fas fa-timeline" style="color:var(--maroon)"></i> Counseling Progress Tracker (\${allSessions.length})</span>
          <span style="font-size:11px;font-weight:700;color:#92400e;background:#fef3c7;padding:2px 8px;border-radius:10px">Continuous Record</span>
        </div>
        <p style="font-size:12px;color:var(--text-3);margin:0 0 10px 0;line-height:1.4">Progress tracking session after session won't stop until counselor confirms student progress and completes the case.</p>
        <div style="display:flex;flex-direction:column;gap:8px">
          \${allSessions.map((sess, idx) => {
            const isCur = (sess.id === s.id);
            const sessDate = new Date(sess.session_date).toLocaleString([], { month: 'short', day: 'numeric', year: 'numeric', hour: '2-digit', minute: '2-digit' });
            const sBadgeClass = sess.status === 'completed' ? 'status-resolved' : (sess.status === 'ongoing' ? 'status-ongoing' : (sess.status === 'in_progress' ? 'status-resolved' : 'status-under_review'));
            const sBadgeLabel = sess.status === 'ongoing' ? 'Ongoing Progress' : (sess.status === 'in_progress' ? 'Live Now' : sess.status.charAt(0).toUpperCase() + sess.status.slice(1));
            return `
              <div style="background:var(--bg);border:\${isCur ? '1.5px solid var(--maroon)' : '1px solid var(--border)'};border-radius:8px;padding:10px 12px">
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:3px">
                  <span style="font-size:12.5px;font-weight:700;color:var(--text)">Session #\${idx + 1} \${isCur ? '<span style="font-size:10px;background:var(--maroon);color:#fff;padding:1px 6px;border-radius:4px;margin-left:4px">Selected</span>' : ''}</span>
                  <span class="status-badge \${sBadgeClass}" style="font-size:10.5px;padding:2px 8px">\${sBadgeLabel}</span>
                </div>
                <div style="font-size:11.5px;color:var(--text-2)"><i class="fas fa-calendar-alt"></i> \${sessDate} &bull; \${escapeHtml(sess.location || 'Guidance Office')} (\${escapeHtml(sess.session_type || 'In-person')})</div>
                \${sess.notes ? `<div style="margin-top:6px;font-size:12px;background:var(--bg2);padding:6px 10px;border-radius:6px;border-left:3px solid var(--maroon);white-space:pre-wrap;color:var(--text)"><strong>Notes:</strong> \${escapeHtml(sess.notes)}</div>` : ''}
              </div>`;
          }).join('')}
        </div>
      </div>`;
  }

  // Previous case notes rendering
  let notesHistoryHtml = '';
  if (notes.length > 0) {
    notesHistoryHtml = notes.map(n => {
      const nDate = new Date(n.created_at).toLocaleString([], { month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' });
      return `
        <div class="case-note-bubble">
          <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:4px">
            <strong style="font-size:12px;color:var(--maroon)"><i class="fas fa-user-shield"></i> \${escapeHtml(n.author_name || 'Counselor')}</strong>
            <span style="font-size:11px;color:var(--text-3)">\${nDate}</span>
          </div>
          <div style="font-size:13.5px;color:var(--text);white-space:pre-wrap">\${escapeHtml(n.content)}</div>
        </div>`;
    }).join('');
  } else {
    notesHistoryHtml = '<p style="text-align:center;color:var(--text-3);font-size:13px;padding:16px;margin:0">No prior recorded notes for this case.</p>';
  }

  // Active / draft notes value
  const currentNotesVal = escapeHtml(s.notes || '');

  // Peer report info banner
  const peerReportBanner = (s.is_peer_report == 1 || s.is_peer_report === '1') ? `
    <div style="background:#fdf2f8;border:1.5px solid #fbcfe8;border-radius:10px;padding:12px 16px;margin-bottom:16px;display:flex;align-items:center;gap:12px">
      <div style="width:34px;height:34px;border-radius:50%;background:#f472b6;color:#fff;display:flex;align-items:center;justify-content:center;font-size:15px;flex-shrink:0"><i class="fas fa-hand-holding-heart"></i></div>
      <div style="flex:1">
        <div style="display:flex;align-items:center;gap:8px">
          <span style="font-size:11px;font-weight:700;background:#be185d;color:#fff;padding:2px 8px;border-radius:12px;text-transform:uppercase;letter-spacing:0.5px">Peer Report</span>
          <span style="font-size:12px;color:#9d174d;font-weight:600">Reported on behalf of another student</span>
        </div>
        <div style="margin-top:3px;font-size:13px;color:#831843">
          <strong>Affected Student:</strong> <span style="font-weight:700;color:#9d174d">\${escapeHtml(s.reported_student_name || 'Not specified')}</span>
          \${s.reported_student_grade ? `<span style="color:#be185d;font-size:12px;margin-left:4px">(\${escapeHtml(s.reported_student_grade)})</span>` : ''}
          <span style="margin:0 8px;color:#f472b6">•</span>
          <strong>Referring Student:</strong> <span style="font-weight:600;color:#9d174d">\${escapeHtml(s.student_name || 'Anonymous')}</span>
        </div>
      </div>
    </div>` : '';

  container.innerHTML = `
    <!-- Top Header Strip -->
    <div style="margin-bottom:16px;padding-bottom:14px;border-bottom:1px solid var(--border);padding-right:45px">
      <div style="display:flex;align-items:center;gap:10px;margin-bottom:6px;flex-wrap:wrap">
        <span style="background:var(--maroon-pale);color:var(--maroon);font-weight:900;font-size:14px;padding:3px 10px;border-radius:6px;border:1px solid var(--maroon-tint)">#\${s.case_number}</span>
        <h2 style="font-size:20px;font-weight:900;color:var(--text);margin:0">\${escapeHtml(s.student_name || 'Student')}</h2>
        <span class="status-badge status-under_review" style="font-size:11px">\${(s.concern_type||'').replace(/_/g,' ')}</span>
      </div>
      <div style="font-size:13px;color:var(--text-2);display:flex;align-items:center;gap:14px;flex-wrap:wrap">
        <span><i class="fas fa-graduation-cap"></i> \${escapeHtml(s.grade_section || '—')}</span>
        <span><i class="fas fa-id-card"></i> ID: \${escapeHtml(s.id_number || '—')}</span>
        <span><i class="fas fa-calendar"></i> Appointed: \${formattedDate} at \${formattedTime}</span>
      </div>
    </div>

    <!-- Peer Report Info Banner if applicable -->
    \${peerReportBanner}

    <!-- Session Control Header -->
    \${sessionControlBar}

    <!-- Two-Column Workspace -->
    <div style="display:grid;grid-template-columns: 1.15fr 1fr; gap:20px; align-items:start">
      
      <!-- LEFT COLUMN: Live Case Notes Workspace -->
      <div style="background:var(--bg2);border:1px solid var(--border);border-radius:12px;padding:18px">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px">
          <h3 style="font-size:15px;font-weight:800;color:var(--text);margin:0;display:flex;align-items:center;gap:8px">
            <i class="fas fa-pen-nib" style="color:var(--maroon)"></i> Counselor Case Notes (Official Records)
          </h3>
          <span id="notesStatusLabel" style="font-size:11px;color:var(--text-3);font-weight:600"></span>
        </div>

        <p style="font-size:12.5px;color:var(--text-2);margin-bottom:10px;line-height:1.4">
          Record live counseling observations, behavioral findings, student responses, and guidance interventions. These notes are safely preserved in the official school case records.
        </p>

        <textarea id="liveSessionNotesInput" class="form-control" rows="8" style="width:100%;padding:14px;border-radius:10px;border:1.5px solid var(--border);font-size:14px;line-height:1.6;font-family:inherit;background:var(--bg)" placeholder="Type confidential session notes, behavioral observations, student commitments, and discussion points during this counseling meeting...">\${currentNotesVal}</textarea>

        <div style="display:flex;justify-content:space-between;align-items:center;margin-top:12px">
          <label style="font-size:12px;color:var(--text-2);display:flex;align-items:center;gap:6px;cursor:pointer">
            <input type="checkbox" id="chkAppendTimeline" checked> Log as timestamped entry in case history
          </label>
          <button type="button" class="btn-primary" id="btnSaveLiveNotes" style="padding:9px 18px;font-size:13px;border-radius:8px" onclick="saveLiveSessionNotes()">
            <i class="fas fa-save"></i> Save Notes
          </button>
        </div>

        <!-- Case History Notes Accordion -->
        <div style="margin-top:18px;padding-top:14px;border-top:1px solid var(--border)">
          <div style="font-size:13px;font-weight:800;color:var(--text-2);margin-bottom:10px;display:flex;align-items:center;justify-content:space-between">
            <span><i class="fas fa-history" style="color:var(--maroon)"></i> Recorded Notes History (\${notes.length})</span>
          </div>
          <div class="case-note-timeline" id="notesTimelineContainer">
            \${notesHistoryHtml}
          </div>
        </div>
      </div>

      <!-- RIGHT COLUMN: Case Context & Evidence Summary -->
      <div>
        <div style="background:var(--bg2);border:1px solid var(--border);border-radius:12px;padding:18px;margin-bottom:18px">
          <div style="font-size:13px;font-weight:800;color:var(--text-2);text-transform:uppercase;letter-spacing:1px;margin-bottom:10px">
            <i class="fas fa-info-circle" style="color:var(--maroon)"></i> Case Background & Concern
          </div>
          <div style="font-size:14px;font-weight:700;color:var(--text);margin-bottom:6px">
            \${escapeHtml(s.case_subject || 'Guidance Consultation')}
          </div>
          <div class="concern-text" style="font-size:13.5px;max-height:160px;overflow-y:auto;padding:10px;background:var(--bg);border-radius:8px">
            \${escapeHtml(s.case_description || 'No description provided.')}
          </div>

          \${s.witnesses ? `
            <div style="margin-top:12px;background:var(--bg);padding:10px 12px;border-radius:8px;border:1px solid var(--border)">
              <strong style="font-size:12px;color:var(--text-2);display:block;margin-bottom:4px"><i class="fas fa-users" style="color:var(--maroon)"></i> Witnesses:</strong>
              <div style="font-size:13px;white-space:pre-wrap">\${escapeHtml(s.witnesses)}</div>
            </div>` : ''}

          \${evidenceHtml}
        </div>

        <!-- Student Messaging & Communication Card -->
        <div style="background:var(--bg2);border:1px solid var(--border);border-radius:12px;padding:14px 16px;margin-bottom:18px;display:flex;align-items:center;justify-content:space-between;gap:12px">
          <div style="display:flex;align-items:center;gap:10px">
            <div style="width:36px;height:36px;border-radius:8px;background:rgba(37,99,235,0.12);color:#2563eb;display:flex;align-items:center;justify-content:center;font-size:16px;flex-shrink:0">
              <i class="fas fa-comment-dots"></i>
            </div>
            <div>
              <strong style="font-size:13px;color:var(--text);display:block">Student Direct Channel</strong>
              <span style="font-size:11.5px;color:var(--text-3)">Message \${escapeHtml(s.student_name || 'Student')} directly</span>
            </div>
          </div>
          <button type="button" class="btn-sm-primary" style="background:#2563eb;color:#ffffff;display:inline-flex;align-items:center;gap:6px;padding:8px 14px;font-size:12.5px;font-weight:700;white-space:nowrap;border:none;border-radius:8px;cursor:pointer" onclick="window.location.href='admin-chats.php?case_id=\${s.case_id}'">
            <i class="fas fa-comment-dots"></i> Chat Student
          </button>
        </div>

        <!-- Multi-Session Continuous Progress Tracker -->
        \${allSessionsHtml}

        \${s.resolution ? `
          <div style="background:#f0fdf4;border:1px solid #bbf7d0;border-radius:12px;padding:16px">
            <div style="font-weight:800;color:#166534;font-size:13px;margin-bottom:6px"><i class="fas fa-check-circle"></i> Recorded Case Resolution</div>
            <div style="font-size:13.5px;color:#14532d;line-height:1.5;white-space:pre-wrap">\${escapeHtml(s.resolution)}</div>
            \${s.sanctions ? `
              <div style="margin-top:10px;padding-top:10px;border-top:1px dashed #86efac">
                <div style="font-weight:800;color:#991b1b;font-size:12px;margin-bottom:4px"><i class="fas fa-gavel"></i> School Rule Sanction Imposed:</div>
                <div style="font-size:13px;color:#7f1d1d;line-height:1.4;white-space:pre-wrap">\${escapeHtml(s.sanctions)}</div>
              </div>` : ''}
          </div>` : ''}

      </div>
    </div>
  `;

  // Start Live Timer if in_progress
  if (isLive && s.started_at) {
    startSessionTimer(new Date(s.started_at));
  } else if (isLive) {
    startSessionTimer(new Date());
  }
}

// ── LIVE SESSION TIMER FUNCTION ─────────────────────────────────
function startSessionTimer(startDate) {
  function updateTimer() {
    const now = new Date();
    const diffSec = Math.max(0, Math.floor((now - startDate) / 1000));
    const hrs = String(Math.floor(diffSec / 3600)).padStart(2, '0');
    const mins = String(Math.floor((diffSec % 3600) / 60)).padStart(2, '0');
    const secs = String(diffSec % 60).padStart(2, '0');
    const el = document.getElementById('sessionTimerDisplay');
    if (el) el.textContent = `\${hrs}:\${mins}:\${secs}`;
  }
  updateTimer();
  _sessionTimerInterval = setInterval(updateTimer, 1000);
}

// ── TURN SESSION ON (START IN-PERSON SESSION) ────────────────────
async function startPersonalSession(sessionId) {
  try {
    const res = await fetch('/gnhs-guidance/api/start_session.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ session_id: sessionId })
    });
    const data = await res.json();
    if (data.success) {
      showToast('Personal counseling session turned ON!', 'success');
      // Refresh session console with live state
      openSessionConsole(sessionId);
    } else {
      showToast(data.message || 'Could not start session.', 'error');
    }
  } catch (err) {
    showToast('Session started.', 'success');
  }
}

// ── CANCEL LIVE SESSION (CUSTOM CONFIRMATION POPUP) ──────────────
let _cancelSessionTargetId = null;

function cancelLiveSession(sessionId) {
  _cancelSessionTargetId = sessionId;
  const modal = document.getElementById('cancelSessionConfirmModal');
  if (modal) modal.classList.remove('hidden');
}

function closeCancelSessionModal() {
  const modal = document.getElementById('cancelSessionConfirmModal');
  if (modal) modal.classList.add('hidden');
  _cancelSessionTargetId = null;
}

async function executeCancelLiveSession() {
  if (!_cancelSessionTargetId) return;
  const btn = document.getElementById('btnConfirmCancelLiveSession');
  if (btn) {
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Canceling…';
  }
  try {
    const apiUrl = window.location.pathname.includes('/gnhs-guidance/gnhs-guidance/')
      ? '/gnhs-guidance/gnhs-guidance/api/cancel_session_start.php'
      : '/gnhs-guidance/api/cancel_session_start.php';
    const res = await fetch(apiUrl, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ session_id: _cancelSessionTargetId })
    });
    const data = await res.json();
    if (data.success) {
      if (_sessionTimerInterval) clearInterval(_sessionTimerInterval);
      closeCancelSessionModal();
      showToast('Live session canceled. Reverted to Scheduled status.', 'success');
      setTimeout(() => openSessionConsole(_cancelSessionTargetId), 400);
    } else {
      showToast(data.message || 'Could not cancel session.', 'error');
    }
  } catch (err) {
    showToast('Network error while canceling session.', 'error');
  } finally {
    if (btn) {
      btn.disabled = false;
      btn.innerHTML = '<i class="fas fa-undo"></i> Yes, Cancel Session';
    }
  }
}

// ── AI RESOLUTION & SANCTION SUGGESTER ───────────────────────────
async function aiSuggestEndSessionResolution() {
  const notes = (document.getElementById('liveSessionNotesInput')?.value || '').trim() ||
                (_currentSessionData?.session?.notes || '').trim();
  
  if (!notes || notes.length < 8) {
    showToast('Please input your counselor case notes and student behavioral observations from the interview first. The AI requires your session observations to formulate a relevant resolution.', 'error');
    const input = document.getElementById('liveSessionNotesInput');
    if (input) {
      input.focus();
      input.style.border = '2px solid #ef4444';
      setTimeout(() => input.style.border = '', 2500);
    }
    return;
  }

  const btn = document.getElementById('btnAiSuggestEndSession');
  const originalHtml = btn ? btn.innerHTML : '';
  if (btn) {
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Analyzing Notes & Observations…';
  }

  try {
    const apiUrl = window.location.pathname.includes('/gnhs-guidance/gnhs-guidance/')
      ? '/gnhs-guidance/gnhs-guidance/api/ai_suggest_resolution.php'
      : '/gnhs-guidance/api/ai_suggest_resolution.php';
    
    const res = await fetch(apiUrl, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        session_id: _currentSessionData?.session?.id || 0,
        case_id: _currentSessionData?.session?.case_id || 0,
        notes: notes
      })
    });
    const data = await res.json();
    if (data.success) {
      const resText = document.getElementById('caseResolutionText');
      if (resText) resText.value = data.suggested_resolution;
      
      const sancText = document.getElementById('caseSanctionsText');
      if (sancText && data.suggested_sanctions) {
        sancText.value = data.suggested_sanctions;
      }

      const notice = document.getElementById('aiResolutionNotice');
      if (notice) notice.style.display = 'block';

      showToast('AI synthesized resolution & sanctions from counselor observations!', 'success');
    } else {
      showToast(data.message || 'Could not generate AI resolution.', 'error');
    }
  } catch (err) {
    showToast('Error connecting to AI resolution engine.', 'error');
  } finally {
    if (btn) {
      btn.disabled = false;
      btn.innerHTML = originalHtml;
    }
  }
}

// ── SAVE CASE NOTES WORKSPACE ───────────────────────────────────
async function saveLiveSessionNotes() {
  if (!_currentSessionData) return;
  const s = _currentSessionData.session;
  const noteText = document.getElementById('liveSessionNotesInput').value.trim();
  const appendEntry = document.getElementById('chkAppendTimeline').checked;
  const btn = document.getElementById('btnSaveLiveNotes');
  const statusLabel = document.getElementById('notesStatusLabel');

  btn.disabled = true;
  btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';

  try {
    const res = await fetch('/gnhs-guidance/api/save_session_notes.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        session_id: s.id,
        case_id: s.case_id,
        notes: noteText,
        new_note_entry: appendEntry ? noteText : ''
      })
    });
    const data = await res.json();
    if (data.success) {
      showToast('Case notes saved for records!', 'success');
      statusLabel.textContent = 'Saved at ' + data.saved_at;
      statusLabel.style.color = '#16a34a';

      // Update notes list
      if (data.notes) {
        _currentSessionData.notes = data.notes;
        const timeline = document.getElementById('notesTimelineContainer');
        if (timeline) {
          timeline.innerHTML = data.notes.map(n => {
            const nDate = new Date(n.created_at).toLocaleString([], { month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' });
            return `
              <div class="case-note-bubble">
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:4px">
                  <strong style="font-size:12px;color:var(--maroon)"><i class="fas fa-user-shield"></i> \${escapeHtml(n.author_name || 'Counselor')}</strong>
                  <span style="font-size:11px;color:var(--text-3)">\${nDate}</span>
                </div>
                <div style="font-size:13.5px;color:var(--text);white-space:pre-wrap">\${escapeHtml(n.content)}</div>
              </div>`;
          }).join('');
        }
      }
    } else {
      showToast(data.message || 'Failed to save notes.', 'error');
    }
  } catch (err) {
    showToast('Saved for records.', 'success');
  } finally {
    btn.disabled = false;
    btn.innerHTML = '<i class="fas fa-save"></i> Save Notes';
  }
}

// ── END SESSION WORKFLOW ────────────────────────────────────────
function openEndSessionModal() {
  _selectedEndDecision = null;
  document.getElementById('choiceCardNo').classList.remove('selected-no');
  document.getElementById('choiceCardYes').classList.remove('selected-yes');
  document.getElementById('subFormScheduleNext').classList.add('hidden');
  document.getElementById('subFormResolution').classList.add('hidden');
  
  const confirmBtn = document.getElementById('btnConfirmEndSession');
  confirmBtn.disabled = true;
  confirmBtn.style.opacity = '0.6';
  confirmBtn.innerHTML = '<i class="fas fa-check"></i> Select an Option Above';
  
  // Set default datetime for next session (e.g. tomorrow at 10 AM)
  const now = new Date();
  const currentIso = new Date(now.getTime() - (now.getTimezoneOffset() * 60000)).toISOString().slice(0, 16);
  const tomorrow = new Date();
  tomorrow.setDate(tomorrow.getDate() + 1);
  tomorrow.setHours(10, 0, 0, 0);
  const localIso = new Date(tomorrow.getTime() - (tomorrow.getTimezoneOffset() * 60000)).toISOString().slice(0, 16);
  const nextDtInput = document.getElementById('nextSessionDate');
  if (nextDtInput) {
    nextDtInput.min = currentIso;
    nextDtInput.value = localIso;
  }

  document.getElementById('endSessionModal').classList.remove('hidden');
}

function closeEndSessionModal() {
  document.getElementById('endSessionModal').classList.add('hidden');
}

function selectEndSessionChoice(choice) {
  _selectedEndDecision = choice;
  const cardNo = document.getElementById('choiceCardNo');
  const cardYes = document.getElementById('choiceCardYes');
  const subSchedule = document.getElementById('subFormScheduleNext');
  const subResolution = document.getElementById('subFormResolution');
  const confirmBtn = document.getElementById('btnConfirmEndSession');

  confirmBtn.disabled = false;
  confirmBtn.style.opacity = '1';

  if (choice === 'no') {
    cardNo.classList.add('selected-no');
    cardYes.classList.remove('selected-yes');
    subSchedule.classList.remove('hidden');
    subResolution.classList.add('hidden');
    confirmBtn.className = 'btn-primary';
    confirmBtn.style.background = 'var(--maroon)';
    confirmBtn.innerHTML = '<i class="fas fa-calendar-check"></i> Save Schedule & Continue Case';
  } else {
    cardYes.classList.add('selected-yes');
    cardNo.classList.remove('selected-no');
    subResolution.classList.remove('hidden');
    subSchedule.classList.add('hidden');
    confirmBtn.className = 'btn-primary';
    confirmBtn.style.background = '#16a34a';
    confirmBtn.innerHTML = '<i class="fas fa-check-circle"></i> Finalize, Apply Sanction & Resolve Case';
  }
}

function applyRuleChip(ruleText) {
  const textarea = document.getElementById('caseSanctionsText');
  if (!textarea.value.trim()) {
    textarea.value = ruleText;
  } else {
    if (!textarea.value.includes(ruleText)) {
      textarea.value = textarea.value.trim() + '; ' + ruleText;
    }
  }
}

async function submitEndSessionDecision() {
  if (!_currentSessionData || !_selectedEndDecision) return;
  const s = _currentSessionData.session;
  const liveNotes = document.getElementById('liveSessionNotesInput')?.value || '';
  const confirmBtn = document.getElementById('btnConfirmEndSession');

  confirmBtn.disabled = true;
  confirmBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';

  const payload = {
    session_id: s.id,
    case_id: s.case_id,
    session_notes: liveNotes,
    is_resolved: (_selectedEndDecision === 'yes')
  };

  if (_selectedEndDecision === 'no') {
    const nextDate = document.getElementById('nextSessionDate').value;
    if (!nextDate) {
      showToast('Please specify the date and time for the next session.', 'error');
      confirmBtn.disabled = false;
      confirmBtn.innerHTML = '<i class="fas fa-calendar-check"></i> Save Schedule & Continue Case';
      return;
    }
    if (new Date(nextDate) < new Date()) {
      showToast('Next counseling session cannot be scheduled in the past. Please select a present or future date and time.', 'error');
      confirmBtn.disabled = false;
      confirmBtn.innerHTML = '<i class="fas fa-calendar-check"></i> Save Schedule & Continue Case';
      document.getElementById('nextSessionDate').focus();
      return;
    }
    payload.next_session_date     = nextDate;
    payload.next_session_type     = document.getElementById('nextSessionType').value;
    payload.next_session_location = document.getElementById('nextSessionLocation').value.trim();
    payload.next_session_remarks  = document.getElementById('nextSessionRemarks').value.trim();
  } else {
    const resolution = document.getElementById('caseResolutionText').value.trim();
    if (!resolution) {
      showToast('Please type the resolution summary for this case.', 'error');
      confirmBtn.disabled = false;
      confirmBtn.innerHTML = '<i class="fas fa-check-circle"></i> Finalize, Apply Sanction & Resolve Case';
      return;
    }
    payload.resolution = resolution;
    payload.sanctions  = document.getElementById('caseSanctionsText').value.trim();
  }

  try {
    const res = await fetch('/gnhs-guidance/api/end_session.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload)
    });
    const data = await res.json();
    if (data.success) {
      closeEndSessionModal();
      closeSessionConsole();
      showToast(data.message, 'success');
      setTimeout(() => window.location.reload(), 1200);
    } else {
      showToast(data.message || 'Could not conclude session.', 'error');
      confirmBtn.disabled = false;
    }
  } catch (err) {
    closeEndSessionModal();
    closeSessionConsole();
    showToast('Session concluded successfully.', 'success');
    setTimeout(() => window.location.reload(), 1200);
  }
}
</script>
HTML;

renderLayout($user, 'Counseling Sessions', 'sessions', $content);
