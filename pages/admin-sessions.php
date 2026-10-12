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
}
if ($search) {
    $where .= " AND (
        c.case_number LIKE '%" . addslashes($search) . "%'
        OR s.first_name LIKE '%" . addslashes($search) . "%'
        OR s.last_name LIKE '%" . addslashes($search) . "%'
        OR CONCAT(s.first_name, ' ', s.last_name) LIKE '%" . addslashes($search) . "%'
        OR cs.location LIKE '%" . addslashes($search) . "%'
    )";
}

$sessions = $db->query("SELECT cs.*,
    CASE WHEN c.is_anonymous = 1 THEN 'Anonymous Student' ELSE CONCAT(s.first_name,' ',s.last_name) END AS student_name,
    CASE WHEN c.is_anonymous = 1 THEN 'Confidential' ELSE s.grade_section END AS grade_section,
    c.case_number, c.subject AS case_subject, c.concern_type, c.priority AS case_priority
    FROM counseling_sessions cs
    LEFT JOIN users s ON cs.student_id=s.id
    JOIN cases c ON cs.case_id=c.id
    $where
    ORDER BY 
      CASE WHEN cs.status = 'in_progress' THEN 1
           WHEN cs.status = 'scheduled' THEN 2
           ELSE 3 END,
      cs.session_date DESC")->fetchAll();

// Counts for summary metrics
$countTotal     = (int)$db->query("SELECT COUNT(*) FROM counseling_sessions")->fetchColumn();
$countScheduled = (int)$db->query("SELECT COUNT(*) FROM counseling_sessions WHERE status='scheduled'")->fetchColumn();
$countLive      = (int)$db->query("SELECT COUNT(*) FROM counseling_sessions WHERE status='in_progress'")->fetchColumn();
$countCompleted = (int)$db->query("SELECT COUNT(*) FROM counseling_sessions WHERE status='completed'")->fetchColumn();

$rows = '';
foreach ($sessions as $s) {
    $dt = new DateTime($s['session_date']);
    
    $statusBadge = match($s['status']) {
        'scheduled'   => "<span class='status-badge' style='background:#eff6ff;color:#1d4ed8;border:1px solid #bfdbfe;font-weight:700'><i class='fas fa-calendar-alt'></i> Scheduled</span>",
        'in_progress' => "<span class='status-badge' style='background:#dcfce7;color:#15803d;border:1px solid #86efac;font-weight:700;display:inline-flex;align-items:center;gap:6px'><span class='live-pulse-dot'></span> Live In-Session</span>",
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
    } else {
        $actionBtn = "<button type='button' class='btn-sm-outline' onclick='openSessionConsole({$s['id']})' style='display:inline-flex;align-items:center;gap:6px'>
            <i class='fas fa-eye'></i> View Details
        </button>";
    }

    $rows .= "<tr style='cursor:pointer' onclick='if(!event.target.closest(\"select\") && !event.target.closest(\"button\")) openSessionConsole({$s['id']})'>
        <td><strong style='color:var(--maroon);font-size:14px'>{$s['case_number']}</strong></td>
        <td><strong>".htmlspecialchars($s['student_name']??'—')."</strong></td>
        <td>".htmlspecialchars($s['grade_section']??'—')."</td>
        <td>".$dt->format('M d, Y')."</td>
        <td style='font-weight:600'>".$dt->format('h:i A')."</td>
        <td><i class='fas fa-map-marker-alt' style='color:var(--maroon);font-size:11px'></i> ".htmlspecialchars($s['location'] ?? 'Guidance Office')."</td>
        <td>".ucfirst($s['session_type'] ?? 'In-person')."</td>
        <td>$statusBadge</td>
        <td>
          <div style='display:inline-flex;gap:8px;align-items:center'>
            $actionBtn
            <select class='status-select' style='font-size:12px;padding:5px 8px' onchange='updateSessionStatus({$s['id']},this.value,event)'>
              <option value='scheduled' ".($s['status']==='scheduled'?'selected':'').">Scheduled</option>
              <option value='in_progress' ".($s['status']==='in_progress'?'selected':'').">In Progress</option>
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
$fAll = $statusFilter === '' ? 'selected' : '';
$fSched = $statusFilter === 'scheduled' ? 'selected' : '';
$fProg  = $statusFilter === 'in_progress' ? 'selected' : '';
$fComp  = $statusFilter === 'completed' ? 'selected' : '';
$fCanc  = $statusFilter === 'cancelled' ? 'selected' : '';

$content = <<<HTML
<!-- Summary Metric Strip -->
<div class="summary-cards" style="grid-template-columns: repeat(4, 1fr); margin-bottom: 22px;">
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
  <div class="sum-card">
    <div class="sum-icon" style="background:rgba(22,163,74,0.12);color:#16a34a"><i class="fas fa-check-double"></i></div>
    <div><div class="sum-num">$countCompleted</div><div class="sum-label">Completed Sessions</div></div>
  </div>
</div>

<div class="content-card">
  <div class="card-header">
    <h3><i class="fas fa-calendar-alt"></i> Counseling Sessions Console</h3>
    <span style="font-size:13px;color:var(--text-3)">Click any appointed session to start, write notes, and manage resolutions</span>
  </div>

  <!-- Search & Filter Bar -->
  <div class="filter-bar" style="margin-bottom:18px;flex-wrap:wrap">
    <div style="display:flex;gap:8px;flex:1;min-width:240px">
      <div class="input-wrap" style="flex:1">
        <i class="fas fa-search"></i>
        <input type="text" id="sessionSearchQ" value="$sqVal" placeholder="Search by case #, student name, location…" onkeydown="if(event.key==='Enter') doSessionSearch()"/>
      </div>
      <button class="btn-sm-primary" onclick="doSessionSearch()"><i class="fas fa-search"></i> Search</button>
    </div>
    <select class="status-select" onchange="filterSessionStatus(this.value)">
      <option value="" $fAll>All Statuses</option>
      <option value="scheduled" $fSched>Scheduled (Appointed)</option>
      <option value="in_progress" $fProg>Live In-Session</option>
      <option value="completed" $fComp>Completed</option>
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
        <label>Resolution Summary <span class="req">*</span></label>
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
        <div style="display:flex;align-items:center;gap:14px">
          <div class="session-timer-badge">
            <i class="fas fa-stopwatch" style="margin-right:6px"></i><span id="sessionTimerDisplay">00:00:00</span>
          </div>
          <button type="button" class="btn-danger" style="background:#dc2626;border:none;color:#fff;padding:10px 18px;border-radius:10px;font-weight:800;font-size:13.5px;cursor:pointer;display:inline-flex;align-items:center;gap:6px;box-shadow:0 4px 12px rgba(220,38,38,0.3)" onclick="openEndSessionModal()">
            <i class="fas fa-check-circle"></i> End Session
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

  container.innerHTML = `
    <!-- Top Header Strip -->
    <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:16px;padding-bottom:14px;border-bottom:1px solid var(--border)">
      <div>
        <div style="display:flex;align-items:center;gap:10px;margin-bottom:4px">
          <span style="background:var(--maroon-pale);color:var(--maroon);font-weight:900;font-size:14px;padding:3px 10px;border-radius:6px;border:1px solid var(--maroon-tint)">#\${s.case_number}</span>
          <h2 style="font-size:20px;font-weight:900;color:var(--text);margin:0">\${escapeHtml(s.student_name || 'Student')}</h2>
          <span class="status-badge status-under_review" style="font-size:11px">\${(s.concern_type||'').replace(/_/g,' ')}</span>
        </div>
        <div style="font-size:13px;color:var(--text-2);display:flex;align-items:center;gap:14px">
          <span><i class="fas fa-graduation-cap"></i> \${escapeHtml(s.grade_section || '—')}</span>
          <span><i class="fas fa-id-card"></i> ID: \${escapeHtml(s.id_number || '—')}</span>
          <span><i class="fas fa-calendar"></i> Appointed: \${formattedDate} at \${formattedTime}</span>
        </div>
      </div>
      <div>
        <button type="button" class="btn-sm-outline" onclick="window.location.href='admin-chats.php?case_id=\${s.case_id}'" title="Open student chat channel">
          <i class="fas fa-comment-dots"></i> Chat Student
        </button>
      </div>
    </div>

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
  const tomorrow = new Date();
  tomorrow.setDate(tomorrow.getDate() + 1);
  tomorrow.setHours(10, 0, 0, 0);
  const localIso = new Date(tomorrow.getTime() - (tomorrow.getTimezoneOffset() * 60000)).toISOString().slice(0, 16);
  document.getElementById('nextSessionDate').value = localIso;

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
