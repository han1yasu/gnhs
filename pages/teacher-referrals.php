<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/layout.php';
$user = requireLogin('teacher');
$db   = getDB();
$uid  = $user['id'];

$filterStatus  = $_GET['status']  ?? '';
$filterUrgency = $_GET['urgency'] ?? '';
$search        = trim($_GET['q']  ?? '');

if ($filterStatus) {
    $where = "WHERE r.teacher_id=$uid AND r.status='".addslashes($filterStatus)."'";
} else {
    $where = "WHERE r.teacher_id=$uid AND r.status NOT IN ('resolved','closed')";
}

if ($filterUrgency) $where .= " AND r.urgency='".addslashes($filterUrgency)."'";

if ($search) {
    $s = addslashes($search);
    $where .= " AND (
        r.ref_number LIKE '%$s%'
        OR r.student_name LIKE '%$s%'
        OR r.grade_section LIKE '%$s%'
        OR r.observations LIKE '%$s%'
    )";
}

$refs = $db->query("SELECT r.*,
    cs.id AS session_id, cs.session_date, cs.session_type, cs.location, cs.meeting_link, cs.status AS session_status, cs.notes AS session_notes,
    u.first_name AS counselor_first, u.last_name AS counselor_last
    FROM referrals r 
    LEFT JOIN counseling_sessions cs ON cs.id = (
        SELECT id FROM counseling_sessions 
        WHERE referral_id = r.id AND status NOT IN ('cancelled')
        ORDER BY session_date DESC LIMIT 1
    )
    LEFT JOIN users u ON cs.counselor_id = u.id
    $where ORDER BY r.submitted_at DESC, r.id DESC")->fetchAll();

$archivedCount = (int)$db->query("SELECT COUNT(*) FROM referrals WHERE teacher_id=$uid AND status IN ('resolved','closed')")->fetchColumn();
$activeCount   = (int)$db->query("SELECT COUNT(*) FROM referrals WHERE teacher_id=$uid AND status NOT IN ('resolved','closed')")->fetchColumn();

function urgencyBadge(string $u): string {
    $map = ['urgent'=>'priority-high','moderate'=>'priority-medium','low'=>'priority-low'];
    return "<span class='priority-badge ".($map[$u]??'priority-low')."'><i class='fas fa-circle'></i>".ucfirst($u)."</span>";
}
function statusBadge(string $s): string {
    $map=['pending'=>'status-pending','resolved'=>'status-resolved'];
    return "<span class='status-badge ".($map[$s]??'status-pending')."'>".($s==='resolved'?'Resolved':'Pending')."</span>";
}

$rows = '';
foreach ($refs as $r) {
    $rawType = strtolower($r['concern_type']);
    $type = ucfirst(str_replace('_',' ',$r['concern_type']));
    $date = date('M d, Y', strtotime($r['submitted_at']));

    $sessionBadge = "<span style='color:var(--text-3);font-size:12px'><i class='fas fa-hourglass-half'></i> Pending Schedule</span>";
    if (!empty($r['session_id'])) {
        $sDt = date('M d, Y h:i A', strtotime($r['session_date']));
        $isVirtual = ($r['session_type'] === 'virtual' || $r['session_type'] === 'online');
        $modeIcon = $isVirtual ? "<i class='fas fa-video' style='color:#2563eb'></i>" : "<i class='fas fa-building' style='color:var(--maroon)'></i>";
        $sessionBadge = "<div style='font-size:12px;font-weight:600;color:var(--text)'>$modeIcon $sDt</div>";
    }

    $refData = htmlspecialchars(json_encode([
        'id'             => (int)$r['id'],
        'ref_number'     => $r['ref_number'],
        'student_name'   => $r['student_name'],
        'grade_section'  => $r['grade_section'],
        'concern_type'   => $r['concern_type'],
        'urgency'        => $r['urgency'],
        'status'         => $r['status'],
        'observations'   => $r['observations'],
        'ai_summary'     => $r['ai_summary'] ?? null,
        'reopen_reason'  => $r['reopen_reason'] ?? null,
        'submitted_at'   => date('M d, Y g:i A', strtotime($r['submitted_at'])),
        'session_id'     => !empty($r['session_id']) ? (int)$r['session_id'] : null,
        'session_date'   => !empty($r['session_date']) ? date('M d, Y \a\t h:i A', strtotime($r['session_date'])) : null,
        'session_type'   => ucfirst($r['session_type'] ?? 'In-person'),
        'location'       => $r['location'] ?? 'Guidance Office',
        'meeting_link'   => $r['meeting_link'] ?? '',
        'session_status' => ucfirst($r['session_status'] ?? ''),
        'session_notes'  => $r['session_notes'] ?? '',
        'counselor_name' => trim(($r['counselor_first'] ?? '') . ' ' . ($r['counselor_last'] ?? '')) ?: 'Guidance Staff'
    ]), ENT_QUOTES, 'UTF-8');

    $searchTokens = [
        $r['ref_number'],
        preg_replace('/[^0-9]/', '', $r['ref_number']),
        $r['student_name'],
        $r['grade_section'],
        $rawType,
        str_replace('_', ' ', $rawType),
        $type,
        $r['urgency'],
        $r['status'],
        str_replace('_', ' ', $r['status']),
        $date,
        substr((string)$r['observations'], 0, 200),
    ];
    $searchData = htmlspecialchars(strtolower(implode(' ', array_filter($searchTokens))), ENT_QUOTES);

    $rows .= "<tr class='ref-row' data-search=\"$searchData\">
        <td><strong style='color:var(--maroon)'>{$r['ref_number']}</strong></td>
        <td>".htmlspecialchars($r['student_name'])."</td>
        <td>".htmlspecialchars($r['grade_section'])."</td>
        <td>$type</td>
        <td>".urgencyBadge($r['urgency'])."</td>
        <td>".statusBadge($r['status'])."</td>
        <td>$sessionBadge</td>
        <td style='color:var(--text-3);font-size:13px'>$date</td>
        <td><button class='btn-sm-outline' onclick='openRefModal(this)' data-ref='$refData'><i class='fas fa-eye'></i> View</button></td>
    </tr>";
}
if (!$rows) $rows = "<tr><td colspan='9' style='text-align:center;color:var(--text-3);padding:40px'>".($search ? "No referrals matching \"".htmlspecialchars($search)."\"." : "No active referrals found. <a href='teacher-referral.php' style='color:var(--maroon);font-weight:700'>Submit one →</a>")."</td></tr>";

$sqVal = htmlspecialchars($search);
$sSel1 = $filterStatus==='pending'      ? 'selected' : '';
$sSel4 = $filterStatus==='resolved'     ? 'selected' : '';
$uSel1 = $filterUrgency==='urgent'      ? 'selected' : '';
$uSel2 = $filterUrgency==='moderate'    ? 'selected' : '';
$uSel3 = $filterUrgency==='low'         ? 'selected' : '';

$content = <<<HTML
<div class="content-card">
  <div class="card-header" style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;">
    <div>
      <h3 style="margin:0;display:flex;align-items:center;gap:10px;">
        <i class="fas fa-list-alt" style="color:var(--maroon)"></i> My Active Referrals
        <span id="teacherRefsCount" style="font-size:13px;font-weight:600;color:var(--text-3);">($activeCount active)</span>
      </h3>
    </div>
    <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap">
      <a href="teacher-referrals-archive.php" class="btn-sm-outline" style="text-decoration:none;display:inline-flex;align-items:center;gap:6px;">
        <i class="fas fa-archive"></i> Referrals Archive ($archivedCount)
      </a>
      <a href="teacher-referral.php" class="btn-sm-primary" style="text-decoration:none;display:inline-flex;align-items:center;gap:6px;">
        <i class="fas fa-plus"></i> New Referral
      </a>
    </div>
  </div>

  <div class="filter-bar" style="margin-bottom:18px;display:flex;gap:10px;flex-wrap:wrap;align-items:center;">
    <div style="display:flex;gap:8px;flex:1;min-width:240px">
      <div class="input-wrap" style="flex:1">
        <i class="fas fa-search"></i>
        <input type="text" id="searchQ" value="$sqVal" placeholder="Search by student, ref #, or notes…" autocomplete="off"
          oninput="filterReferralsLive(this.value)"
          onkeydown="if(event.key==='Enter'){event.preventDefault();doSearch();}"/>
      </div>
      <button class="btn-sm-primary" onclick="doSearch()"><i class="fas fa-search"></i> Search</button>
    </div>

    <select class="status-select" onchange="applyFilter('status',this.value)">
      <option value="">All Status (Active)</option>
      <option value="pending"  $sSel1>Pending</option>
      <option value="resolved" $sSel4>Resolved</option>
    </select>

    <select class="status-select" onchange="applyFilter('urgency',this.value)">
      <option value="">All Urgency</option>
      <option value="urgent"   $uSel1>Urgent</option>
      <option value="moderate" $uSel2>Moderate</option>
      <option value="low"      $uSel3>Low</option>
    </select>

    <button class="btn-sm-outline" onclick="window.location='teacher-referrals.php'"><i class="fas fa-times"></i> Clear</button>
  </div>

  <div class="table-wrap">
    <table class="data-table">
      <thead><tr><th>Ref #</th><th>Student Name</th><th>Grade/Section</th><th>Concern</th><th>Urgency</th><th>Status</th><th>Counseling Session</th><th>Date</th><th>Action</th></tr></thead>
      <tbody>$rows</tbody>
    </table>
  </div>
</div>

<!-- Referral Detail Modal -->
<div class="modal-overlay hidden" id="refModal" onclick="if(event.target.id==='refModal')this.classList.add('hidden')">
  <div class="modal-box" style="max-width:1000px; width:95%">
    <button class="modal-close" onclick="document.getElementById('refModal').classList.add('hidden')"><i class="fas fa-times"></i></button>
    <h2 class="modal-title"><i class="fas fa-exchange-alt" style="color:var(--maroon);margin-right:8px"></i>Referral Detail</h2>
    <div id="refModalBody"></div>
  </div>
</div>
HTML;

$content .= '
<script>
let refSearchTimer = null;

function syncRefSearchUrl(q) {
  try {
    const url = new URL(window.location);
    if (q) url.searchParams.set("q", q);
    else url.searchParams.delete("q");
    window.history.replaceState({}, "", url);
  } catch (e) {}
}

function filterReferralsLive(query) {
  const q = (query || "").trim().toLowerCase();
  const rows = document.querySelectorAll(".data-table tbody tr.ref-row");
  const countBadge = document.getElementById("teacherRefsCount");
  const tbody = document.querySelector(".data-table tbody");
  if (!tbody) return;

  const existingNoMatch = tbody.querySelector(".live-no-match-row");
  if (existingNoMatch) existingNoMatch.remove();
  if (!rows || rows.length === 0) return;

  if (!q) {
    rows.forEach(r => r.style.display = "");
    if (countBadge) countBadge.textContent = "(" + rows.length + " active)";
    clearTimeout(refSearchTimer);
    refSearchTimer = setTimeout(() => syncRefSearchUrl(""), 300);
    return;
  }

  const tokens = q.split(/\s+/).filter(Boolean);
  let visibleCount = 0;
  rows.forEach(r => {
    const hay = r.getAttribute("data-search") || "";
    const matched = tokens.every(tok => hay.includes(tok));
    r.style.display = matched ? "" : "none";
    if (matched) visibleCount++;
  });

  if (visibleCount === 0) {
    const tr = document.createElement("tr");
    tr.className = "live-no-match-row";
    tr.innerHTML = "<td colspan=\"9\" style=\"text-align:center;color:var(--text-3);padding:40px\">No matching referrals found.</td>";
    tbody.appendChild(tr);
  }

  if (countBadge) {
    countBadge.textContent = "(" + visibleCount + (visibleCount !== rows.length ? " of " + rows.length : "") + " active)";
  }

  clearTimeout(refSearchTimer);
  refSearchTimer = setTimeout(() => syncRefSearchUrl(q), 350);
}

function doSearch() {
  const input = document.getElementById("searchQ");
  const val = input ? input.value.trim() : "";
  filterReferralsLive(val);
  syncRefSearchUrl(val);
}

function applyFilter(key, val) {
  const url = new URL(window.location);
  if (val) url.searchParams.set(key, val); else url.searchParams.delete(key);
  const q = document.getElementById("searchQ")?.value.trim();
  if (q) url.searchParams.set("q", q);
  window.location = url;
}

document.addEventListener("DOMContentLoaded", function() {
  const input = document.getElementById("searchQ");
  if (input) {
    input.addEventListener("input", function() {
      filterReferralsLive(this.value);
    });
    if (input.value.trim()) {
      filterReferralsLive(input.value.trim());
    }
  }
});

function openRefModal(btn) {
  const ref = JSON.parse(btn.getAttribute("data-ref"));
  const urgMap   = {urgent:"priority-high", moderate:"priority-medium", low:"priority-low"};
  const statMap  = {pending:"status-pending", resolved:"status-resolved"};
  const statLabel = {pending:"Pending", resolved:"Resolved"};
  const typeLabel = ref.concern_type.replace(/_/g," ").replace(/\b\w/g, l=>l.toUpperCase());

  document.getElementById("refModalBody").innerHTML = `
    <div class="review-header-strip" style="display:flex;flex-wrap:wrap;gap:15px;background:var(--bg2);padding:15px;border-radius:8px;margin-bottom:20px;border:1px solid var(--border)">
      <div style="flex:1;min-width:120px"><label style="font-size:11px;color:var(--text-3);text-transform:uppercase;letter-spacing:1px;display:block;margin-bottom:4px">Referral ID</label><strong style="color:var(--maroon);font-size:16px">#${ref.ref_number}</strong></div>
      <div style="flex:2;min-width:150px"><label style="font-size:11px;color:var(--text-3);text-transform:uppercase;letter-spacing:1px;display:block;margin-bottom:4px">Student</label><span style="font-size:15px;font-weight:600">${ref.student_name}</span></div>
      <div style="flex:1.5;min-width:130px"><label style="font-size:11px;color:var(--text-3);text-transform:uppercase;letter-spacing:1px;display:block;margin-bottom:4px">Type</label><span class="status-badge status-ongoing">${typeLabel}</span></div>
      <div style="flex:1.5;min-width:130px"><label style="font-size:11px;color:var(--text-3);text-transform:uppercase;letter-spacing:1px;display:block;margin-bottom:4px">Grade/Sec</label><span style="font-size:14px">${ref.grade_section}</span></div>
      <div style="flex:1.5;min-width:130px"><label style="font-size:11px;color:var(--text-3);text-transform:uppercase;letter-spacing:1px;display:block;margin-bottom:4px">Submitted</label><span style="font-size:13px;color:var(--text-2)">${ref.submitted_at}</span></div>
    </div>
    
    <div style="display:grid;grid-template-columns: 1.2fr 1fr; gap:25px; align-items:start">
      <div>
        ${ref.ai_summary ? `<div class="ai-box" style="margin-bottom:20px;border-left:4px solid var(--blue);background:rgba(37,99,235,0.05)"><div class="ai-box-label" style="color:var(--blue);font-size:14px;margin-bottom:8px"><i class="fas fa-magic"></i> AI Summary</div><div class="ai-box-text" style="font-size:15px;line-height:1.6">${ref.ai_summary}</div></div>` : \'\'}
        <div style="margin-bottom:14px;background:var(--bg2);padding:20px;border-radius:8px;border:1px solid var(--border)">
          <div style="font-size:13px;font-weight:800;color:var(--text-2);margin-bottom:12px;text-transform:uppercase;letter-spacing:1px"><i class="fas fa-quote-left" style="color:var(--maroon)"></i> My Observations</div>
          <div class="concern-text" style="font-size:15px;line-height:1.7;color:var(--text);background:transparent;padding:0;border:none;white-space:pre-wrap;">${ref.observations}</div>
        </div>
      </div>
      
      <div style="background:var(--bg2);padding:20px;border-radius:8px;border:1px solid var(--border)">
        <h3 style="margin-bottom:20px;font-size:16px;color:var(--text);border-bottom:1px solid var(--border);padding-bottom:10px"><i class="fas fa-info-circle" style="color:var(--maroon)"></i> Referral Info</h3>
        <div class="form-group" style="margin-bottom:15px">
          <label style="display:block;margin-bottom:8px">Urgency</label>
          <span class="priority-badge ${urgMap[ref.urgency]||\'priority-low\'}"><i class="fas fa-circle"></i> ${ref.urgency.charAt(0).toUpperCase()+ref.urgency.slice(1)}</span>
        </div>
        <div class="form-group" style="margin-bottom:20px">
          <label style="display:block;margin-bottom:8px">Current Status</label>
          <span class="status-badge ${statMap[ref.status]||\'status-pending\'}">${statLabel[ref.status]}</span>
        </div>

        <!-- Counseling Session Information -->
        <div class="form-group" style="margin-top:16px;border-top:1px solid var(--border);padding-top:16px">
          <label style="display:block;margin-bottom:8px;font-weight:700"><i class="fas fa-calendar-alt" style="color:var(--maroon)"></i> Counseling Session Status</label>
          ${ref.session_id ? `
            <div style="background:#eff6ff;border:1px solid #bfdbfe;border-radius:8px;padding:12px;font-size:13px">
              <div style="font-weight:700;color:#1e40af;margin-bottom:4px"><i class="fas fa-calendar-check"></i> Session Appointed (${ref.session_status || "Scheduled"})</div>
              <div style="color:var(--text);font-weight:600">${ref.session_date}</div>
              <div style="color:var(--text-2);font-size:12px;margin-top:3px">Counselor: <strong>${ref.counselor_name}</strong> &bull; Mode: <strong>${ref.session_type}</strong></div>
              <div style="color:var(--text-2);font-size:12px;margin-top:2px">Location: ${ref.location}</div>
              ${ref.meeting_link ? `<div style="margin-top:6px"><a href="${ref.meeting_link}" target="_blank" style="color:#2563eb;font-weight:700;font-size:12px"><i class="fas fa-video"></i> Join Virtual Meeting</a></div>` : ""}
            </div>
          ` : `
            <div style="background:#fffbeb;border:1px solid #fde68a;border-radius:8px;padding:10px;font-size:12.5px;color:#92400e">
              <i class="fas fa-hourglass-half"></i> Pending schedule by guidance counselor.
            </div>
          `}
        </div>
        
        <div class="review-actions" style="margin-top:20px;border-top:1px solid var(--border);padding-top:20px;display:flex;gap:10px;">
          <button class="btn-secondary" style="flex:1" onclick="document.getElementById(\'refModal\').classList.add(\'hidden\')"><i class="fas fa-times"></i> Close</button>
          <button class="btn-primary" style="flex:1;background:var(--blue)" onclick="window.location.href=\'teacher-referrals-chat.php?ref_id=${ref.id}\'">
            <i class="fas fa-comment-dots"></i> Go to Chat
          </button>
        </div>
      </div>
    </div>`;

  document.getElementById("refModal").classList.remove("hidden");
}
</script>
';

renderLayout($user, 'My Referrals', 'referrals', $content);
