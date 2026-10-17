<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/layout.php';
$user = requireLogin('admin');
$db   = getDB();

$filterType      = $_GET['type'] ?? '';
$filterSanctions = $_GET['sanction'] ?? '';
$search          = trim($_GET['q'] ?? '');

$where = "WHERE cs.status = 'completed'";
if ($filterType) {
    $where .= " AND c.concern_type = '" . addslashes($filterType) . "'";
}
if ($filterSanctions === 'yes') {
    $where .= " AND c.sanctions IS NOT NULL AND c.sanctions != '' AND c.sanctions NOT LIKE '%No Sanctions%'";
} elseif ($filterSanctions === 'no') {
    $where .= " AND (c.sanctions IS NULL OR c.sanctions = '' OR c.sanctions LIKE '%No Sanctions%')";
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
        OR LOWER(COALESCE(cs.notes, '')) LIKE LOWER('%$sq%')
        OR LOWER(COALESCE(c.resolution, '')) LIKE LOWER('%$sq%')
        OR LOWER(COALESCE(c.sanctions, '')) LIKE LOWER('%$sq%')
        OR LOWER(COALESCE(c.resolution_process, '')) LIKE LOWER('%$sq%')
    )";
}

$sessions = $db->query("SELECT cs.*,
    CASE WHEN c.is_anonymous = 1 THEN 'Anonymous Student' ELSE CONCAT(s.first_name,' ',s.last_name) END AS student_name,
    CASE WHEN c.is_anonymous = 1 THEN 'Confidential' ELSE s.grade_section END AS grade_section,
    s.id_number,
    c.case_number, c.subject AS case_subject, c.concern_type, c.priority AS case_priority,
    c.is_peer_report, c.reported_student_name, c.reported_student_grade,
    c.resolution, c.sanctions, c.resolution_process, c.resolved_at
    FROM counseling_sessions cs
    LEFT JOIN users s ON cs.student_id=s.id
    JOIN cases c ON cs.case_id=c.id
    $where
    ORDER BY COALESCE(cs.ended_at, cs.session_date) DESC, cs.session_date DESC")->fetchAll();

// Metrics
$countCompleted     = (int)$db->query("SELECT COUNT(*) FROM counseling_sessions WHERE status='completed'")->fetchColumn();
$countActive        = (int)$db->query("SELECT COUNT(*) FROM counseling_sessions WHERE status IN ('scheduled','in_progress','ongoing')")->fetchColumn();
$countSettledCouns  = (int)$db->query("SELECT COUNT(*) FROM cases WHERE status IN ('resolved','closed') AND (resolution_process LIKE '%Counseling%' OR resolution LIKE '%counsel%')")->fetchColumn();
$countWithSanctions = (int)$db->query("SELECT COUNT(DISTINCT cs.id) FROM counseling_sessions cs JOIN cases c ON cs.case_id=c.id WHERE cs.status='completed' AND c.sanctions IS NOT NULL AND c.sanctions != '' AND c.sanctions NOT LIKE '%No Sanctions%'")->fetchColumn();

$rows = '';
foreach ($sessions as $s) {
    $dt = new DateTime($s['session_date']);
    $endedDisplay = '—';
    if (!empty($s['ended_at'])) {
        $endDt = new DateTime($s['ended_at']);
        $endedDisplay = $endDt->format('M d, Y h:i A');
    }

    $rawType = strtolower($s['concern_type']);
    $typeLabel = ucfirst(str_replace('_', ' ', $s['concern_type']));

    $studentCol = "<strong>" . htmlspecialchars($s['student_name'] ?? '—') . "</strong>";
    if (!empty($s['is_peer_report'])) {
        $studentCol = "<strong>" . htmlspecialchars($s['reported_student_name'] ?: 'Target Student') . "</strong>"
            . "<div style='font-size:11px;color:#be185d'><i class='fas fa-user-tag'></i> Rep by: " . htmlspecialchars($s['student_name'] ?? 'Anon') . "</div>";
    }

    // Process & Resolution preview
    $procBadge = !empty($s['resolution_process'])
        ? "<span style='display:inline-block;font-size:10.5px;font-weight:700;background:#dcfce7;color:#166534;border:1px solid #86efac;padding:2px 7px;border-radius:10px;margin-bottom:3px'><i class='fas fa-check-circle'></i> " . htmlspecialchars($s['resolution_process']) . "</span>"
        : "<span style='display:inline-block;font-size:10.5px;font-weight:700;background:#f3f4f6;color:#374151;border:1px solid #e5e7eb;padding:2px 7px;border-radius:10px;margin-bottom:3px'><i class='fas fa-clipboard-check'></i> Resolved</span>";

    $resSnippet = !empty($s['resolution'])
        ? "<div style='font-size:12px;color:var(--text);line-height:1.35;max-width:280px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis' title='" . htmlspecialchars($s['resolution'], ENT_QUOTES) . "'>" . htmlspecialchars($s['resolution']) . "</div>"
        : "<div style='font-size:12px;color:var(--text-3);font-style:italic'>Session concluded successfully</div>";

    // Sanction preview
    $sanctBadge = '';
    if (!empty($s['sanctions']) && stripos($s['sanctions'], 'No Sanctions') === false) {
        $sanctBadge = "<span class='rule-chip applied' style='font-size:11px;padding:3px 8px;cursor:default' title='" . htmlspecialchars($s['sanctions'], ENT_QUOTES) . "'><i class='fas fa-gavel'></i> Sanctions Imposed</span>";
    } else {
        $sanctBadge = "<span style='font-size:11px;color:var(--text-3);font-weight:600'><i class='fas fa-check'></i> Counseling Only</span>";
    }

    $searchTokens = [
        $s['case_number'],
        $s['student_name'],
        $s['reported_student_name'] ?? '',
        $s['grade_section'] ?? '',
        $s['concern_type'],
        $s['resolution'] ?? '',
        $s['sanctions'] ?? '',
        $s['resolution_process'] ?? '',
        $s['location'] ?? ''
    ];
    $searchData = htmlspecialchars(strtolower(implode(' ', array_filter($searchTokens))), ENT_QUOTES);

    $rows .= "<tr class='session-row' data-search=\"$searchData\" style='cursor:pointer' onclick='if(!event.target.closest(\"button\")) openSessionConsole({$s['id']})'>
        <td><strong style='color:var(--maroon);font-size:14px'>#{$s['case_number']}</strong></td>
        <td>$studentCol</td>
        <td>" . htmlspecialchars($s['grade_section'] ?? '—') . "</td>
        <td>" . $dt->format('M d, Y') . "<div style='font-size:11px;color:var(--text-3);font-weight:600'>" . $dt->format('h:i A') . "</div></td>
        <td>" . ucfirst($s['session_type'] ?? 'In-person') . "<div style='font-size:11px;color:var(--text-3)'><i class='fas fa-map-marker-alt'></i> " . htmlspecialchars($s['location'] ?? 'Guidance Office') . "</div></td>
        <td>$procBadge $resSnippet</td>
        <td>$sanctBadge</td>
        <td><span class='status-badge status-resolved'><i class='fas fa-check-circle'></i> Completed</span></td>
        <td>
          <div style='display:inline-flex;gap:6px;align-items:center'>
            <button type='button' class='btn-sm-primary' onclick='openSessionConsole({$s['id']})' style='display:inline-flex;align-items:center;gap:6px;font-size:12px;padding:6px 11px'>
              <i class='fas fa-clipboard-list'></i> View Record
            </button>
            <button type='button' class='btn-row-action btn-action-chat' onclick=\"event.stopPropagation(); window.location.href='admin-chats.php?case_id={$s['case_id']}'\" title='Chat with student' style='font-size:11.5px;padding:6px 9px'>
              <i class='fas fa-comment-dots'></i>
            </button>
          </div>
        </td>
    </tr>";
}

if (!$rows) {
    $rows = "<tr class='no-sessions-row'><td colspan='9' style='text-align:center;color:var(--text-3);padding:45px'>" .
        ($search ? "No completed counseling sessions found matching \"<strong>" . htmlspecialchars($search) . "</strong>\"." : "No archived counseling sessions yet.") . "</td></tr>";
}

$sqVal   = htmlspecialchars($search);
$stAll   = $filterType === '' ? 'selected' : '';
$stB     = $filterType === 'bullying' ? 'selected' : '';
$stA     = $filterType === 'academic_stress' ? 'selected' : '';
$stE     = $filterType === 'emotional' ? 'selected' : '';
$stF     = $filterType === 'family' ? 'selected' : '';
$stP     = $filterType === 'peer_conflict' ? 'selected' : '';
$stBeh   = $filterType === 'behavioral' ? 'selected' : '';
$stO     = $filterType === 'other' ? 'selected' : '';
$stSancYes = $filterSanctions === 'yes' ? 'selected' : '';
$stSancNo  = $filterSanctions === 'no' ? 'selected' : '';

$content = <<<HTML
<!-- Top Tab Bar: Active Sessions vs Completed Sessions Archive -->
<div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;margin-bottom:18px">
  <div class="totp-tab-bar" style="background:var(--bg2);padding:4px;border-radius:12px;border:1px solid var(--border);display:inline-flex">
    <a href="admin-sessions.php" class="tab-pill" style="text-decoration:none">
      <i class="fas fa-calendar-alt"></i> Active Sessions <span style="font-size:11px;opacity:0.8">($countActive)</span>
    </a>
    <a href="admin-sessions-archive.php" class="tab-pill active" style="text-decoration:none">
      <i class="fas fa-archive"></i> Sessions Archive <span style="font-size:11px;font-weight:800;background:var(--maroon);color:#fff;padding:1px 6px;border-radius:10px;margin-left:4px">$countCompleted</span>
    </a>
  </div>

  <div style="display:inline-flex;gap:8px;align-items:center">
    <a href="admin-archive.php" class="btn-sm-outline" title="View Cases Archive"><i class="fas fa-folder"></i> Cases Archive</a>
    <a href="admin-referrals-archive.php" class="btn-sm-outline" title="View Referrals Archive"><i class="fas fa-exchange-alt"></i> Referrals Archive</a>
  </div>
</div>

<!-- Metric Strip for Completed Sessions -->
<div class="summary-cards" style="grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); margin-bottom: 22px;">
  <div class="sum-card" style="border: 1px solid rgba(22,163,74,0.3); background: rgba(240,253,244,0.6);">
    <div class="sum-icon" style="background:rgba(22,163,74,0.15);color:#16a34a"><i class="fas fa-calendar-check"></i></div>
    <div>
      <div class="sum-num" style="color:#15803d">$countCompleted</div>
      <div class="sum-label" style="font-weight:700;color:#166534">Archived Sessions</div>
    </div>
  </div>
  <div class="sum-card">
    <div class="sum-icon" style="background:rgba(37,99,235,0.12);color:#2563eb"><i class="fas fa-hand-holding-heart"></i></div>
    <div>
      <div class="sum-num">$countSettledCouns</div>
      <div class="sum-label">Settled via Counseling</div>
    </div>
  </div>
  <div class="sum-card">
    <div class="sum-icon" style="background:rgba(180,83,9,0.12);color:#b45309"><i class="fas fa-gavel"></i></div>
    <div>
      <div class="sum-num">$countWithSanctions</div>
      <div class="sum-label">With Sanctions Imposed</div>
    </div>
  </div>
  <div class="sum-card" style="cursor:pointer" onclick="window.location.href='admin-sessions.php'">
    <div class="sum-icon" style="background:rgba(85,0,0,0.1);color:var(--maroon)"><i class="fas fa-clock"></i></div>
    <div>
      <div class="sum-num">$countActive</div>
      <div class="sum-label">Active Ongoing &rarr;</div>
    </div>
  </div>
</div>

<div class="content-card">
  <div class="card-header">
    <div>
      <h3 style="display:flex;align-items:center;gap:8px">
        <i class="fas fa-box-archive" style="color:var(--maroon)"></i> Counseling Sessions Archive
        <span id="sessionsCountBadge" style="font-size:13px;font-weight:600;color:var(--text-3)">($countCompleted completed records)</span>
      </h3>
      <span style="font-size:13px;color:var(--text-3)">Official permanent repository of completed counseling appointments, notes history, resolution summaries, and student progress records.</span>
    </div>
    <a href="admin-sessions.php" class="btn-sm-outline"><i class="fas fa-arrow-left"></i> Back to Active</a>
  </div>

  <div style="background:var(--maroon-pale);border:1px solid var(--maroon-tint);border-radius:10px;padding:12px 16px;margin-bottom:16px;font-size:13px;color:var(--maroon);display:flex;align-items:center;gap:10px">
    <i class="fas fa-info-circle" style="font-size:15px;flex-shrink:0"></i>
    <span>These counseling sessions have concluded. All recorded counselor observations, timeline history, and school rule sanctions are preserved in school case files.</span>
  </div>

  <!-- Filter & Search Bar -->
  <div class="filter-bar" style="margin-bottom:18px;flex-wrap:wrap">
    <div style="display:flex;gap:8px;flex:1;min-width:240px">
      <div class="input-wrap" style="flex:1">
        <i class="fas fa-search"></i>
        <input type="text" id="archiveSearchQ" value="$sqVal" placeholder="Search archived sessions by case #, student name, concern, notes, sanctions…" onkeydown="if(event.key==='Enter') doArchiveSearch()"/>
      </div>
      <button class="btn-sm-primary" onclick="doArchiveSearch()"><i class="fas fa-search"></i> Search</button>
    </div>

    <select class="status-select" onchange="applyArchiveFilter('type', this.value)">
      <option value="" $stAll>All Concern Types</option>
      <option value="bullying" $stB>Bullying</option>
      <option value="academic_stress" $stA>Academic Stress</option>
      <option value="emotional" $stE>Emotional</option>
      <option value="family" $stF>Family</option>
      <option value="peer_conflict" $stP>Peer Conflict</option>
      <option value="behavioral" $stBeh>Behavioral</option>
      <option value="other" $stO>Other</option>
    </select>

    <select class="status-select" onchange="applyArchiveFilter('sanction', this.value)">
      <option value="">All Sanction Types</option>
      <option value="yes" $stSancYes>With Sanctions Imposed</option>
      <option value="no"  $stSancNo>No Sanctions / Counseling Only</option>
    </select>

    <button class="btn-sm-outline" onclick="window.location='admin-sessions-archive.php'"><i class="fas fa-times"></i> Reset</button>
  </div>

  <div class="table-wrap">
    <table class="data-table">
      <thead>
        <tr>
          <th>Case #</th>
          <th>Student</th>
          <th>Grade/Sec</th>
          <th>Session Date</th>
          <th>Mode & Location</th>
          <th>Settlement & Resolution</th>
          <th>Sanctions</th>
          <th>Status</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>$rows</tbody>
    </table>
  </div>
</div>

<!-- ============================================================== -->
<!-- COUNSELING SESSION RECORD CONSOLE MODAL -->
<!-- ============================================================== -->
<div class="modal-overlay hidden" id="sessionConsoleModal" onclick="if(event.target===this) closeSessionConsole()">
  <div class="modal-box" style="max-width:920px;width:95%;padding:26px;max-height:92vh;overflow-y:auto">
    <button type="button" class="modal-close" onclick="closeSessionConsole()"><i class="fas fa-times"></i></button>
    <div id="sessionConsoleContent">
      <div style="text-align:center;padding:50px">
        <span class="spinner" style="border-color:rgba(85,0,0,.2);border-top-color:var(--maroon);width:36px;height:36px;border-width:4px;display:inline-block"></span>
      </div>
    </div>
  </div>
</div>

<script>
let _currentSessionData = null;
let searchDebounceTimer = null;

function doArchiveSearch() {
  const q = document.getElementById('archiveSearchQ').value.trim();
  const url = new URL(window.location);
  if (q) url.searchParams.set('q', q);
  else url.searchParams.delete('q');
  window.location = url;
}

function applyArchiveFilter(key, val) {
  const url = new URL(window.location);
  if (val) url.searchParams.set(key, val);
  else url.searchParams.delete(key);
  window.location = url;
}

function filterArchiveLive(query) {
  const q = (query || '').trim().toLowerCase();
  const rows = document.querySelectorAll('.data-table tbody tr.session-row');
  const countBadge = document.getElementById('sessionsCountBadge');
  const tbody = document.querySelector('.data-table tbody');
  if (!tbody) return;

  const existingNoMatch = tbody.querySelector('.live-no-match-row');
  if (existingNoMatch) existingNoMatch.remove();

  if (!rows || rows.length === 0) return;

  if (!q) {
    rows.forEach(r => r.style.display = '');
    if (countBadge) countBadge.textContent = '(' + rows.length + ' completed records)';
    return;
  }

  const tokens = q.split(/\s+/).filter(Boolean);
  let visibleCount = 0;

  rows.forEach(r => {
    const searchData = r.getAttribute('data-search') || '';
    const matchesAll = tokens.every(tok => searchData.includes(tok));
    if (matchesAll) {
      r.style.display = '';
      visibleCount++;
    } else {
      r.style.display = 'none';
    }
  });

  if (visibleCount === 0) {
    const noMatchTr = document.createElement('tr');
    noMatchTr.className = 'live-no-match-row';
    noMatchTr.innerHTML = '<td colspan="9" style="text-align:center;color:var(--text-3);padding:40px">' +
      '<i class="fas fa-search" style="margin-right:8px;opacity:0.5;font-size:16px"></i>' +
      'No completed sessions found matching "<strong>' + escapeHtml(query) + '</strong>".</td>';
    tbody.appendChild(noMatchTr);
  }

  if (countBadge) {
    countBadge.textContent = '(' + visibleCount + (visibleCount !== rows.length ? ' of ' + rows.length : '') + ' completed records)';
  }
}

document.addEventListener('DOMContentLoaded', function() {
  const input = document.getElementById('archiveSearchQ');
  if (input) {
    input.addEventListener('input', function() {
      filterArchiveLive(this.value);
    });
  }
});

function closeSessionConsole() {
  document.getElementById('sessionConsoleModal').classList.add('hidden');
}

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
    renderArchiveConsole(data);
  } catch (err) {
    container.innerHTML = '<div class="error-msg">Failed to connect to session archive record service.</div>';
  }
}

function renderArchiveConsole(data) {
  const s = data.session;
  const notes = data.notes || [];
  const allSessions = data.all_sessions || [];
  const container = document.getElementById('sessionConsoleContent');

  const dt = new Date(s.session_date);
  const formattedDate = dt.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
  const formattedTime = dt.toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit', hour12: true });

  const endedDateStr = s.ended_at ? new Date(s.ended_at).toLocaleString([], { month: 'short', day: 'numeric', year: 'numeric', hour: '2-digit', minute: '2-digit' }) : 'Concluded';

  let evidenceHtml = '';
  if (s.parsed_evidence && s.parsed_evidence.length > 0) {
    evidenceHtml = '<div style="margin-top:12px;padding-top:12px;border-top:1px dashed var(--border)">'
      + '<strong style="font-size:12px;color:var(--text-2);display:block;margin-bottom:6px"><i class="fas fa-photo-film" style="color:var(--maroon)"></i> Attached Case Evidence (' + s.parsed_evidence.length + ')</strong>'
      + '<div class="evidence-gallery-grid">' + renderEvidenceGallery(s.parsed_evidence) + '</div>'
      + '</div>';
  }

  // Multi-session tracker
  let allSessionsHtml = '';
  if (allSessions.length > 0) {
    allSessionsHtml = `
      <div style="background:var(--bg2);border:1px solid var(--border);border-radius:12px;padding:18px;margin-bottom:18px">
        <div style="font-size:13px;font-weight:800;color:var(--text-2);text-transform:uppercase;letter-spacing:1px;margin-bottom:10px;display:flex;align-items:center;justify-content:space-between">
          <span><i class="fas fa-timeline" style="color:var(--maroon)"></i> Counseling Progress Tracker (\${allSessions.length})</span>
          <span style="font-size:11px;font-weight:700;color:#166534;background:#dcfce7;padding:2px 8px;border-radius:10px">Archived Timeline</span>
        </div>
        <div style="display:flex;flex-direction:column;gap:8px">
          \${allSessions.map((sess, idx) => {
            const isCur = (sess.id === s.id);
            const sessDate = new Date(sess.session_date).toLocaleString([], { month: 'short', day: 'numeric', year: 'numeric', hour: '2-digit', minute: '2-digit' });
            return `
              <div style="background:var(--bg);border:\${isCur ? '1.5px solid var(--maroon)' : '1px solid var(--border)'};border-radius:8px;padding:10px 12px">
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:3px">
                  <span style="font-size:12.5px;font-weight:700;color:var(--text)">Session #\${idx + 1} \${isCur ? '<span style="font-size:10px;background:var(--maroon);color:#fff;padding:1px 6px;border-radius:4px;margin-left:4px">Selected Record</span>' : ''}</span>
                  <span class="status-badge status-resolved" style="font-size:10.5px;padding:2px 8px">\${sess.status.charAt(0).toUpperCase() + sess.status.slice(1)}</span>
                </div>
                <div style="font-size:11.5px;color:var(--text-2)"><i class="fas fa-calendar-alt"></i> \${sessDate} &bull; \${escapeHtml(sess.location || 'Guidance Office')} (\${escapeHtml(sess.session_type || 'In-person')})</div>
                \${sess.notes ? `<div style="margin-top:6px;font-size:12px;background:var(--bg2);padding:6px 10px;border-radius:6px;border-left:3px solid var(--maroon);white-space:pre-wrap;color:var(--text)"><strong>Notes:</strong> \${escapeHtml(sess.notes)}</div>` : ''}
              </div>`;
          }).join('')}
        </div>
      </div>`;
  }

  // Notes history
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
    <!-- Top Header -->
    <div style="margin-bottom:16px;padding-bottom:14px;border-bottom:1px solid var(--border);padding-right:45px">
      <div style="display:flex;align-items:center;gap:10px;margin-bottom:6px;flex-wrap:wrap">
        <span style="background:var(--maroon-pale);color:var(--maroon);font-weight:900;font-size:14px;padding:3px 10px;border-radius:6px;border:1px solid var(--maroon-tint)">#\${s.case_number}</span>
        <h2 style="font-size:20px;font-weight:900;color:var(--text);margin:0">\${escapeHtml(s.student_name || 'Student')}</h2>
        <span class="status-badge status-under_review" style="font-size:11px">\${(s.concern_type||'').replace(/_/g,' ')}</span>
      </div>
      <div style="font-size:13px;color:var(--text-2);display:flex;align-items:center;gap:14px;flex-wrap:wrap">
        <span><i class="fas fa-graduation-cap"></i> \${escapeHtml(s.grade_section || '—')}</span>
        <span><i class="fas fa-id-card"></i> ID: \${escapeHtml(s.id_number || '—')}</span>
        <span><i class="fas fa-calendar"></i> Session: \${formattedDate} at \${formattedTime}</span>
      </div>
    </div>

    \${peerReportBanner}

    <!-- Completed Status Banner -->
    <div style="background:#f0fdf4;border:1.5px solid #bbf7d0;border-radius:12px;padding:14px 18px;display:flex;align-items:center;justify-content:space-between;margin-bottom:20px">
      <div style="display:flex;align-items:center;gap:10px">
        <i class="fas fa-check-circle" style="color:#15803d;font-size:20px"></i>
        <div>
          <strong style="color:#15803d;font-size:15px;display:block">Counseling Session Completed & Archived</strong>
          <span style="font-size:12px;color:#166534">Concluded on \${endedDateStr} &bull; Official Guidance Records Preserved</span>
        </div>
      </div>
      <div>
        <span class="status-badge status-resolved" style="font-size:12px;padding:4px 12px"><i class="fas fa-check"></i> Completed</span>
      </div>
    </div>

    <!-- Two-Column Workspace -->
    <div style="display:grid;grid-template-columns: 1.15fr 1fr; gap:20px; align-items:start">
      
      <!-- LEFT COLUMN: Official Case Notes -->
      <div style="background:var(--bg2);border:1px solid var(--border);border-radius:12px;padding:18px">
        <h3 style="font-size:15px;font-weight:800;color:var(--text);margin:0 0 10px 0;display:flex;align-items:center;gap:8px">
          <i class="fas fa-pen-nib" style="color:var(--maroon)"></i> Counselor Case Notes (Official Records)
        </h3>

        <div style="background:var(--bg);border:1.5px solid var(--border);border-radius:10px;padding:14px;font-size:14px;line-height:1.6;color:var(--text);white-space:pre-wrap;margin-bottom:16px;min-height:120px">
          \${escapeHtml(s.notes || 'No session notes were recorded.')}
        </div>

        <div style="border-top:1px solid var(--border);padding-top:14px">
          <div style="font-size:13px;font-weight:800;color:var(--text-2);margin-bottom:10px;display:flex;align-items:center;justify-content:space-between">
            <span><i class="fas fa-history" style="color:var(--maroon)"></i> Recorded Notes History (\${notes.length})</span>
          </div>
          <div class="case-note-timeline">
            \${notesHistoryHtml}
          </div>
        </div>
      </div>

      <!-- RIGHT COLUMN: Resolution & Context -->
      <div>
        <!-- Resolution Card -->
        \${s.resolution ? `
          <div style="background:#f0fdf4;border:1.5px solid #86efac;border-radius:12px;padding:18px;margin-bottom:18px">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px">
              <strong style="color:#15803d;font-size:14px"><i class="fas fa-clipboard-check"></i> Recorded Case Resolution</strong>
              <span style="font-size:11px;font-weight:700;background:#dcfce7;color:#166534;border:1px solid #86efac;padding:3px 8px;border-radius:12px">
                \${escapeHtml(s.resolution_process || 'Settled through Counseling Session')}
              </span>
            </div>
            <div style="font-size:13.5px;color:#14532d;line-height:1.5;white-space:pre-wrap">\${escapeHtml(s.resolution)}</div>
            \${s.sanctions ? `
              <div style="margin-top:12px;padding-top:10px;border-top:1px dashed #86efac">
                <div style="font-weight:800;color:#991b1b;font-size:12px;margin-bottom:4px"><i class="fas fa-gavel"></i> School Rule Sanction Imposed:</div>
                <div style="font-size:13px;color:#7f1d1d;line-height:1.4;white-space:pre-wrap">\${escapeHtml(s.sanctions)}</div>
              </div>` : ''}
          </div>` : ''}

        <div style="background:var(--bg2);border:1px solid var(--border);border-radius:12px;padding:18px;margin-bottom:18px">
          <div style="font-size:13px;font-weight:800;color:var(--text-2);text-transform:uppercase;letter-spacing:1px;margin-bottom:10px">
            <i class="fas fa-info-circle" style="color:var(--maroon)"></i> Case Concern Details
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

        <!-- Student Direct Channel -->
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

        \${allSessionsHtml}

      </div>
    </div>
  `;
}
</script>
HTML;

renderLayout($user, 'Sessions Archive', 'sessions_archive', $content);
